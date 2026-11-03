<?php
/*
 * File: api/process_reminder_queue.php
 * Fungsi: Worker CLI antrean WhatsApp (dipanggil berulang ~5 menit oleh service .bat / .sh / Task Scheduler).
 *         Memproses 1 pesan per siklus (Prioritas 1: Reminder Kontrol, Prioritas 2: Ucapan Ulang Tahun)
 *         agar ritme pengiriman alami & aman dari blokir WhatsApp.
 *
 * Tips Test IT di luar jam kerja:
 *   php api/process_reminder_queue.php --force
 */
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Worker antrean hanya boleh dijalankan via CLI']);
    exit;
}

require_once(dirname(__DIR__) . '/config/koneksi.php');
require_once(__DIR__ . '/saungwa_client.php');
require_once(__DIR__ . '/reminder_queue_helpers.php');

$argvList  = isset($argv) && is_array($argv) ? $argv : [];
$forceMode = in_array('--force', $argvList, true);
$activeGw  = strtoupper(get_active_wa_provider());

if (!ensure_kontrol_queue_table($koneksi)) {
    echo 'Gagal memastikan tabel antrean reminder kontrol: ' . $koneksi->error . PHP_EOL;
    exit(1);
}

if (!ensure_birthday_queue_table($koneksi)) {
    echo 'Gagal memastikan tabel antrean ulang tahun: ' . $koneksi->error . PHP_EOL;
    exit(1);
}

// Pulihkan item yang mungkin tersangkut 'processing' lebih dari 15 menit
recover_stale_queue_items($koneksi, 'antrean_reminder_kontrol');
recover_stale_queue_items($koneksi, 'antrean_ucapan_ulang_tahun');

// Cek jam sopan pengiriman (07:00 - 20:00 WIB), kecuali dijalankan dengan --force untuk test IT
$safeHourMsg = '';
if (!$forceMode && !is_within_wa_safe_hours($safeHourMsg)) {
    echo $safeHourMsg . PHP_EOL;
    exit(0);
}

if (!$forceMode) {
    $cooldownRemaining = get_wa_gateway_cooldown_remaining();
    if ($cooldownRemaining > 0) {
        echo 'Cooldown gateway WA (' . $activeGw . ') masih ' . $cooldownRemaining . ' detik.' . PHP_EOL;
        exit(0);
    }
}

function get_pending_queue_counts($koneksi)
{
    $kontrol = 0;
    $ultah   = 0;

    $r1 = $koneksi->query("SELECT COUNT(*) AS cnt FROM antrean_reminder_kontrol WHERE status = 'pending'");
    if ($r1 && ($row1 = $r1->fetch_assoc())) {
        $kontrol = (int) $row1['cnt'];
    }

    $r2 = $koneksi->query("SELECT COUNT(*) AS cnt FROM antrean_ucapan_ulang_tahun WHERE status = 'pending'");
    if ($r2 && ($row2 = $r2->fetch_assoc())) {
        $ultah = (int) $row2['cnt'];
    }

    return ['kontrol' => $kontrol, 'ultah' => $ultah];
}

function process_kontrol_item($koneksi, $forceMode = false)
{
    $koneksi->begin_transaction();

    $item = null;
    $sql = "SELECT *
            FROM antrean_reminder_kontrol
            WHERE status = 'pending'
              AND scheduled_at <= NOW()
            ORDER BY scheduled_at ASC, id ASC
            LIMIT 1
            FOR UPDATE";
    $res = $koneksi->query($sql);
    if ($res) {
        $item = $res->fetch_assoc();
    }

    if (!$item) {
        $koneksi->commit();
        return false;
    }

    $stmt = $koneksi->prepare("UPDATE antrean_reminder_kontrol
                               SET status = 'processing', attempts = attempts + 1, last_error = NULL
                               WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $stmt->close();
    $koneksi->commit();

    $item['attempts'] = (int) $item['attempts'] + 1;
    $send = send_wa_gateway_message($item['phone'], $item['message'], ['bypass_cooldown' => $forceMode]);
    $gwLabel = strtoupper((string) ($send['provider'] ?? get_active_wa_provider()));

    if (!empty($send['success'])) {
        $stmt = $koneksi->prepare("UPDATE antrean_reminder_kontrol
                                   SET status = 'sent', sent_at = NOW(), last_error = NULL
                                   WHERE id = ?");
        $stmt->bind_param("i", $item['id']);
        $stmt->execute();
        $stmt->close();

        $counts = get_pending_queue_counts($koneksi);
        echo sprintf(
            '[%s][Reminder Kontrol] Terkirim ke %s (%s - %s) | Sisa antrean: %d kontrol, %d ultah%s',
            $gwLabel,
            $item['nama_pasien'] ?: '-',
            $item['nomr'] ?: $item['no_sep'],
            $item['phone'],
            $counts['kontrol'],
            $counts['ultah'],
            PHP_EOL
        );
        return true;
    }

    $error       = $send['error'] ?? 'Gagal mengirim pesan WhatsApp';
    $maxAttempts = reminder_queue_max_attempts();
    if ($item['attempts'] >= $maxAttempts) {
        $status       = 'failed';
        $nextSchedule = $item['scheduled_at'];
    } else {
        $status       = 'pending';
        $nextSchedule = date('Y-m-d H:i:s', time() + get_wa_queue_delay());
    }

    $stmt = $koneksi->prepare("UPDATE antrean_reminder_kontrol
                               SET status = ?, scheduled_at = ?, last_error = ?
                               WHERE id = ?");
    $stmt->bind_param("sssi", $status, $nextSchedule, $error, $item['id']);
    $stmt->execute();
    $stmt->close();

    echo '[' . $gwLabel . '][Reminder Kontrol] Gagal (' . $status . '): ' . $error . PHP_EOL;
    return true;
}

function process_birthday_item($koneksi, $forceMode = false)
{
    $koneksi->begin_transaction();

    $item = null;
    $sql = "SELECT *
            FROM antrean_ucapan_ulang_tahun
            WHERE status = 'pending'
              AND scheduled_at <= NOW()
            ORDER BY scheduled_at ASC, id ASC
            LIMIT 1
            FOR UPDATE";
    $res = $koneksi->query($sql);
    if ($res) {
        $item = $res->fetch_assoc();
    }

    if (!$item) {
        $koneksi->commit();
        return false;
    }

    $stmt = $koneksi->prepare("UPDATE antrean_ucapan_ulang_tahun
                               SET status = 'processing', attempts = attempts + 1, last_error = NULL
                               WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $stmt->close();
    $koneksi->commit();

    $item['attempts'] = (int) $item['attempts'] + 1;
    $send = send_wa_gateway_message($item['phone'], $item['message'], ['bypass_cooldown' => $forceMode]);
    $gwLabel = strtoupper((string) ($send['provider'] ?? get_active_wa_provider()));

    if (!empty($send['success'])) {
        $stmt = $koneksi->prepare("UPDATE antrean_ucapan_ulang_tahun
                                   SET status = 'sent', sent_at = NOW(), last_error = NULL
                                   WHERE id = ?");
        $stmt->bind_param("i", $item['id']);
        $stmt->execute();
        $stmt->close();

        $counts = get_pending_queue_counts($koneksi);
        echo sprintf(
            '[%s][Ucapan Ultah] Terkirim ke %s (%s) | Sisa antrean: %d kontrol, %d ultah%s',
            $gwLabel,
            $item['nama_pasien'],
            $item['no_rkm_medis'],
            $counts['kontrol'],
            $counts['ultah'],
            PHP_EOL
        );
        return true;
    }

    $error       = $send['error'] ?? 'Gagal mengirim pesan WhatsApp';
    $maxAttempts = reminder_queue_max_attempts();
    if ($item['attempts'] >= $maxAttempts) {
        $status       = 'failed';
        $nextSchedule = $item['scheduled_at'];
    } else {
        $status       = 'pending';
        $nextSchedule = date('Y-m-d H:i:s', time() + get_wa_queue_delay());
    }

    $stmt = $koneksi->prepare("UPDATE antrean_ucapan_ulang_tahun
                               SET status = ?, scheduled_at = ?, last_error = ?
                               WHERE id = ?");
    $stmt->bind_param("sssi", $status, $nextSchedule, $error, $item['id']);
    $stmt->execute();
    $stmt->close();

    echo '[' . $gwLabel . '][Ucapan Ultah] Gagal (' . $status . '): ' . $error . PHP_EOL;
    return true;
}

// Prioritas 1: Reminder Kontrol Pasien
if (process_kontrol_item($koneksi, $forceMode)) {
    exit(0);
}

// Prioritas 2: Ucapan Ulang Tahun Pasien
if (process_birthday_item($koneksi, $forceMode)) {
    exit(0);
}

echo 'Tidak ada antrean jatuh tempo.' . PHP_EOL;
?>