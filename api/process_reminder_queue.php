<?php
/* Dipanggil berulang oleh service Windows/Linux setiap lima menit. */
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Worker antrean hanya boleh dijalankan via CLI']);
    exit;
}

require_once(dirname(__DIR__) . '/config/koneksi.php');
require_once(__DIR__ . '/fonnte_client.php');
require_once(__DIR__ . '/reminder_queue_helpers.php');

if (!ensure_reminder_queue_table($koneksi)) {
    echo 'Gagal memastikan tabel antrean: ' . $koneksi->error . PHP_EOL;
    exit(1);
}

$cooldownRemaining = get_fonnte_cooldown_remaining();
if ($cooldownRemaining > 0) {
    echo 'Cooldown Fonnte masih ' . $cooldownRemaining . ' detik.' . PHP_EOL;
    exit;
}

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
    echo 'Tidak ada antrean jatuh tempo.' . PHP_EOL;
    exit;
}

$stmt = $koneksi->prepare("UPDATE antrean_reminder_kontrol
                           SET status = 'processing', attempts = attempts + 1, last_error = NULL
                           WHERE id = ?");
$stmt->bind_param("i", $item['id']);
$stmt->execute();
$stmt->close();
$koneksi->commit();

$item['attempts'] = (int) $item['attempts'] + 1;
$send = send_fonnte_message($item['phone'], $item['message']);

if (!empty($send['success'])) {
    $stmt = $koneksi->prepare("UPDATE antrean_reminder_kontrol
                               SET status = 'sent', sent_at = NOW(), last_error = NULL
                               WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $stmt->close();
    echo 'Terkirim: ' . $item['nama_pasien'] . ' (' . $item['no_sep'] . ')' . PHP_EOL;
    exit;
}

$error = $send['error'] ?? 'Gagal mengirim via Fonnte';
$maxAttempts = reminder_queue_max_attempts();
if ($item['attempts'] >= $maxAttempts) {
    $status = 'failed';
    $nextSchedule = $item['scheduled_at'];
} else {
    $status = 'pending';
    $nextSchedule = date('Y-m-d H:i:s', time() + get_fonnte_queue_delay());
}

$stmt = $koneksi->prepare("UPDATE antrean_reminder_kontrol
                           SET status = ?, scheduled_at = ?, last_error = ?
                           WHERE id = ?");
$stmt->bind_param("sssi", $status, $nextSchedule, $error, $item['id']);
$stmt->execute();
$stmt->close();

echo 'Gagal: ' . $error . PHP_EOL;
exit($status === 'failed' ? 1 : 0);
?>
