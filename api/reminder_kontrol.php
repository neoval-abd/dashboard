<?php
/*
 * File: api/reminder_kontrol.php
 * Fungsi: Mengelola antrean pengiriman Reminder Kontrol Pasien (BPJS / Tunai / Asuransi)
 *         agar diproses bertahap oleh background service (api/process_reminder_queue.php).
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');
require_once(__DIR__ . '/saungwa_client.php');
require_once(__DIR__ . '/fonnte_client.php');
require_once(__DIR__ . '/reminder_queue_helpers.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Sesi login telah berakhir. Silakan login kembali.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error'   => 'Server error: ' . $error['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

if (!ensure_kontrol_queue_table($koneksi)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Tabel antrean reminder kontrol tidak dapat diakses: ' . $koneksi->error,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$defaultPengirim = trim((string) ($_SESSION['nama_pegawai'] ?? ($_SESSION['user_id'] ?? '')));

// GET: Ambil status antrean terkini untuk daftar ref_key (opsional) atau ringkasan
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    recover_stale_queue_items($koneksi, 'antrean_reminder_kontrol');

    $items = [];
    $sql = "SELECT no_sep AS ref_key, nomr, nama_pasien, phone, status, attempts, scheduled_at, sent_at, last_error, updated_at
            FROM antrean_reminder_kontrol
            ORDER BY updated_at DESC
            LIMIT 500";
    $res = $koneksi->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[$row['ref_key']] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'items'   => $items,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'enqueue'));

    // 0a. Simpan Konfigurasi Kredensial Gateway (SaungWA & Fonnte) ke Database (`konfigurasi_wa_gateway`)
    if ($action === 'save_saungwa_config') {
        $saveRes = save_saungwa_config_file([
            'provider'     => $_POST['provider'] ?? 'saungwa',
            'sender_phone' => $_POST['sender_phone'] ?? '',
            'app_key'      => $_POST['app_key'] ?? '',
            'auth_key'     => $_POST['auth_key'] ?? '',
            'fonnte_token' => $_POST['fonnte_token'] ?? '',
            'test_target'  => $_POST['test_target'] ?? '',
        ], $koneksi);

        if (empty($saveRes['success'])) {
            echo json_encode([
                'success' => false,
                'error'   => $saveRes['error'] ?? 'Gagal menyimpan konfigurasi gateway WhatsApp.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'config'  => $saveRes,
            'message' => 'Konfigurasi gateway WhatsApp berhasil disimpan ke Database.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 0b. Tes Kirim Pesan Langsung (Uji Koneksi API SaungWA / Fonnte)
    if ($action === 'test_saungwa_send') {
        $targetProvider = strtolower(trim((string) ($_POST['provider'] ?? 'saungwa')));
        if (!in_array($targetProvider, ['saungwa', 'fonnte'], true)) {
            $targetProvider = 'saungwa';
        }

        $rawAppKey   = trim((string) ($_POST['app_key'] ?? ''));
        $authKey     = trim((string) ($_POST['auth_key'] ?? ''));
        $fonnteToken = trim((string) ($_POST['fonnte_token'] ?? ''));
        $senderPhone = trim((string) ($_POST['sender_phone'] ?? ''));
        $testTarget  = trim((string) ($_POST['test_phone'] ?? ''));
        $testMsg     = trim((string) ($_POST['test_message'] ?? ''));

        if ($testTarget === '') {
            echo json_encode(['success' => false, 'error' => 'Nomor tujuan tes WhatsApp wajib diisi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $phoneCheck = sanitize_patient_phone($testTarget);
        if (!$phoneCheck['valid']) {
            echo json_encode(['success' => false, 'error' => 'Nomor tujuan tes harus berupa nomor seluler WhatsApp aktif (08xx / 628xx).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($targetProvider === 'fonnte') {
            if ($fonnteToken === '') {
                echo json_encode(['success' => false, 'error' => 'Token Fonnte wajib diisi (buka dashboard https://fonnte.com -> menu Device).'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($testMsg === '') {
                $testMsg = "✅ *TES KONEKSI FONNTE BERHASIL*\n\nSistem *Reminder Kontrol Pasien - RSU Assalam* telah terhubung ke WhatsApp Gateway Fonnte.\nWaktu tes: " . date('d-m-Y H:i:s') . " WIB";
            }

            $sendRes = send_fonnte_message($phoneCheck['phone_wa'], $testMsg, [
                'token'                => $fonnteToken,
                'bypass_cooldown'      => true,
                'ignore_test_redirect' => true,
            ]);

            if (!empty($sendRes['success'])) {
                $savedCfg = save_saungwa_config_file([
                    'provider'     => 'fonnte',
                    'sender_phone' => $senderPhone,
                    'app_key'      => $rawAppKey,
                    'auth_key'     => $authKey,
                    'fonnte_token' => $fonnteToken,
                ], $koneksi);

                echo json_encode([
                    'success'  => true,
                    'provider' => 'fonnte',
                    'config'   => $savedCfg,
                    'message'  => 'Pesan tes berhasil dikirim ke ' . $phoneCheck['phone_display'] . ' melalui Fonnte!',
                    'response' => $sendRes['response'] ?? null,
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success'   => false,
                    'provider'  => 'fonnte',
                    'error'     => $sendRes['error'] ?? 'Gagal mengirim pesan tes via Fonnte.',
                    'http_code' => $sendRes['http_code'] ?? 0,
                    'response'  => $sendRes['response'] ?? null,
                ], JSON_UNESCAPED_UNICODE);
            }
            exit;
        }

        // Jalur SaungWA
        $parsedApp = parse_saungwa_appkey_input($rawAppKey);
        if (!$parsedApp['valid']) {
            echo json_encode([
                'success' => false,
                'error'   => $parsedApp['error'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $appKey = $parsedApp['app_key'];

        if ($authKey === '') {
            echo json_encode(['success' => false, 'error' => 'Auth API Key SaungWA wajib diisi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($testMsg === '') {
            $testMsg = "✅ *TES KONEKSI SAUNGWA BERHASIL*\n\nSistem *Reminder Kontrol Pasien - RSU Assalam* telah terhubung ke WhatsApp Gateway SaungWA.\nWaktu tes: " . date('d-m-Y H:i:s') . " WIB";
        }

        $sendRes = send_saungwa_message($phoneCheck['phone_wa'], $testMsg, [
            'appkey'               => $appKey !== '' ? $appKey : (defined('SAUNGWA_APP_KEY') ? SAUNGWA_APP_KEY : ''),
            'authkey'              => $authKey !== '' ? $authKey : (defined('SAUNGWA_AUTH_KEY') ? SAUNGWA_AUTH_KEY : ''),
            'sender_phone'         => $senderPhone,
            'bypass_cooldown'      => true,
            'ignore_test_redirect' => true,
        ]);

        if (!empty($sendRes['success'])) {
            $savedCfg = save_saungwa_config_file([
                'provider'     => 'saungwa',
                'sender_phone' => $senderPhone,
                'app_key'      => $appKey,
                'auth_key'     => $authKey,
                'fonnte_token' => $fonnteToken,
            ], $koneksi);

            echo json_encode([
                'success'       => true,
                'provider'      => 'saungwa',
                'clean_app_key' => $appKey,
                'config'        => $savedCfg,
                'message'       => 'Pesan tes berhasil dikirim ke ' . $phoneCheck['phone_display'] . ' melalui SaungWA!',
                'response'      => $sendRes['response'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success'       => false,
                'provider'      => 'saungwa',
                'clean_app_key' => $appKey,
                'error'         => $sendRes['error'] ?? 'Gagal mengirim pesan tes via SaungWA.',
                'http_code'     => $sendRes['http_code'] ?? 0,
                'response'      => $sendRes['response'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // 1. Batalkan item antrean yang masih berstatus 'pending'
    if ($action === 'cancel_queue') {
        $refKey = substr(trim((string) ($_POST['ref_key'] ?? ($_POST['no_sep'] ?? ''))), 0, 40);
        if ($refKey === '') {
            echo json_encode(['success' => false, 'error' => 'Kode referensi jadwal kontrol wajib diisi.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $koneksi->prepare("DELETE FROM antrean_reminder_kontrol WHERE no_sep = ? AND status = 'pending'");
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => $koneksi->error], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt->bind_param("s", $refKey);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Jadwal berhasil dikeluarkan dari antrean pengiriman.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'error'   => 'Antrean tidak dapat dibatalkan (mungkin sedang diproses atau sudah terkirim).',
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    // 2. Bulk Enqueue (setelah user melakukan preview & edit template massal)
    if ($action === 'bulk_enqueue') {
        $rawItems = $_POST['items_json'] ?? '';
        $decoded  = is_string($rawItems) ? json_decode($rawItems, true) : null;

        if (!is_array($decoded) || empty($decoded)) {
            echo json_encode(['success' => false, 'error' => 'Tidak ada daftar pasien yang dipilih untuk diantrekan.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $scheduledAt  = date('Y-m-d H:i:s');
        $queuedKeys   = [];
        $skippedCount = 0;

        $koneksi->begin_transaction();
        $sqlInsert = "INSERT INTO antrean_reminder_kontrol
                        (no_sep, nomr, nama_pasien, phone, message, pengirim, status, attempts, scheduled_at, last_error)
                      VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, NULL)
                      ON DUPLICATE KEY UPDATE
                        nomr         = IF(status IN ('sent','processing'), nomr, VALUES(nomr)),
                        nama_pasien  = IF(status IN ('sent','processing'), nama_pasien, VALUES(nama_pasien)),
                        phone        = IF(status IN ('sent','processing'), phone, VALUES(phone)),
                        message      = IF(status IN ('sent','processing'), message, VALUES(message)),
                        pengirim     = IF(status IN ('sent','processing'), pengirim, VALUES(pengirim)),
                        attempts     = IF(status IN ('sent','processing'), attempts, 0),
                        scheduled_at = IF(status IN ('sent','processing'), scheduled_at, VALUES(scheduled_at)),
                        last_error   = IF(status IN ('sent','processing'), last_error, NULL),
                        status       = IF(status IN ('sent','processing'), status, 'pending')";

        $stmt = $koneksi->prepare($sqlInsert);
        if (!$stmt) {
            $koneksi->rollback();
            echo json_encode(['success' => false, 'error' => $koneksi->error], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Ambil daftar yang sudah sent/processing agar hitungannya akurat
        $existingLocked = [];
        $resLocked = $koneksi->query("SELECT no_sep FROM antrean_reminder_kontrol WHERE status IN ('sent','processing')");
        if ($resLocked) {
            while ($rl = $resLocked->fetch_assoc()) {
                $existingLocked[$rl['no_sep']] = true;
            }
        }

        foreach ($decoded as $item) {
            $refKey     = substr(trim((string) ($item['ref_key'] ?? '')), 0, 40);
            $nomr       = substr(trim((string) ($item['nomr'] ?? '')), 0, 40);
            $namaPasien = substr(trim((string) ($item['nama_pasien'] ?? '')), 0, 150);
            $rawPhone   = trim((string) ($item['phone'] ?? ''));
            $msg        = trim((string) ($item['message'] ?? ''));

            if ($refKey === '' || $msg === '' || isset($existingLocked[$refKey])) {
                $skippedCount++;
                continue;
            }

            $phoneCheck = sanitize_patient_phone($rawPhone);
            if (!$phoneCheck['valid']) {
                $skippedCount++;
                continue;
            }

            $cleanPhone = $phoneCheck['phone_wa'];
            $stmt->bind_param("sssssss", $refKey, $nomr, $namaPasien, $cleanPhone, $msg, $defaultPengirim, $scheduledAt);
            if ($stmt->execute()) {
                $queuedKeys[$refKey] = [
                    'status'       => 'pending',
                    'scheduled_at' => $scheduledAt,
                    'message'      => $msg,
                    'phone'        => $cleanPhone,
                ];
            } else {
                $skippedCount++;
            }
        }

        $stmt->close();
        $koneksi->commit();

        $queuedCount = count($queuedKeys);
        echo json_encode([
            'success'       => $queuedCount > 0,
            'queued_count'  => $queuedCount,
            'skipped_count' => $skippedCount,
            'queued_items'  => $queuedKeys,
            'message'       => $queuedCount > 0
                ? "{$queuedCount} pasien berhasil dimasukkan ke antrean service WhatsApp."
                : 'Tidak ada pasien baru yang dapat dimasukkan ke antrean.',
            'error'         => $queuedCount > 0 ? null : 'Semua pasien terpilih sudah terkirim atau nomor HP tidak valid.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. Single Enqueue (setelah user preview & edit sedikit isi pesan di modal)
    $refKey      = substr(trim((string) ($_POST['ref_key'] ?? ($_POST['no_sep'] ?? ''))), 0, 40);
    $nomr        = substr(trim((string) ($_POST['nomr'] ?? '')), 0, 40);
    $namaPasien  = substr(trim((string) ($_POST['nama_pasien'] ?? '')), 0, 150);
    $pengirim    = trim((string) ($_POST['pengirim'] ?? ''));
    if ($pengirim === '') {
        $pengirim = $defaultPengirim;
    }
    $pengirim    = substr($pengirim, 0, 100);
    $rawPhone    = trim((string) ($_POST['phone'] ?? ''));
    $message     = trim((string) ($_POST['message'] ?? ''));
    $forceResend = !empty($_POST['force_resend']);

    if ($refKey === '') {
        echo json_encode(['success' => false, 'error' => 'Kode referensi surat kontrol wajib diisi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($rawPhone === '' || $message === '') {
        echo json_encode(['success' => false, 'error' => 'Nomor WhatsApp dan isi pesan wajib diisi.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $phoneCheck = sanitize_patient_phone($rawPhone);
    if (!$phoneCheck['valid']) {
        echo json_encode([
            'success' => false,
            'error'   => 'Nomor HP tidak valid untuk WhatsApp (harus nomor seluler aktif 08xx / 628xx minimal 10 digit).',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $cleanPhone = $phoneCheck['phone_wa'];

    // Cek status eksisting agar tidak double-send tanpa sengaja
    $stmtCheck = $koneksi->prepare("SELECT status, sent_at FROM antrean_reminder_kontrol WHERE no_sep = ? LIMIT 1");
    if ($stmtCheck) {
        $stmtCheck->bind_param("s", $refKey);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        $existing = $resCheck ? $resCheck->fetch_assoc() : null;
        $stmtCheck->close();

        if ($existing) {
            if ($existing['status'] === 'processing') {
                echo json_encode([
                    'success' => false,
                    'error'   => 'Pesan untuk pasien ini sedang dikirim oleh service saat ini. Mohon tunggu sebentar.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($existing['status'] === 'sent' && !$forceResend) {
                echo json_encode([
                    'success'      => false,
                    'already_sent' => true,
                    'error'        => 'Reminder kontrol pasien ini sudah terkirim pada ' . ($existing['sent_at'] ?? '-') . '.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    $scheduledAt = date('Y-m-d H:i:s');
    $sqlQueue = "INSERT INTO antrean_reminder_kontrol
                    (no_sep, nomr, nama_pasien, phone, message, pengirim, status, attempts, scheduled_at, sent_at, last_error)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, NULL, NULL)
                 ON DUPLICATE KEY UPDATE
                    nomr         = VALUES(nomr),
                    nama_pasien  = VALUES(nama_pasien),
                    phone        = VALUES(phone),
                    message      = VALUES(message),
                    pengirim     = VALUES(pengirim),
                    status       = 'pending',
                    attempts     = 0,
                    scheduled_at = VALUES(scheduled_at),
                    last_error   = NULL";

    $stmt = $koneksi->prepare($sqlQueue);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Gagal menyiapkan query antrean: ' . $koneksi->error], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt->bind_param("sssssss", $refKey, $nomr, $namaPasien, $cleanPhone, $message, $pengirim, $scheduledAt);
    $ok  = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => 'Gagal menyimpan ke antrean: ' . $err], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success'      => true,
        'queue_status' => 'pending',
        'scheduled_at' => $scheduledAt,
        'phone'        => $cleanPhone,
        'saved_msg'    => $message,
        'message'      => 'Pesan berhasil masuk ke antrean service dan akan dikirim otomatis secara bertahap.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
?>