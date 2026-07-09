<?php
/*
 * File: api/reminder_kontrol.php
 * Fungsi: Track status pengiriman reminder kontrol
 *         GET  = ambil daftar SEP yang sudah dikirim
 *         POST = tandai SEP sebagai sudah dikirim
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');
require_once(__DIR__ . '/fonnte_client.php');
require_once(__DIR__ . '/reminder_queue_helpers.php');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $error['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ensure_reminder_queue_table($koneksi);

    $sent = [];
    $sql = "SELECT no_sep, nomr, nama_pasien, tgl_kirim, pengirim
            FROM log_kirim_reminder_kontrol";
    $res = $koneksi->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sent[] = $row;
        }
    }

    echo json_encode([
        'sent' => $sent,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_sep = trim($_POST['no_sep'] ?? '');
    $nomr = trim($_POST['nomr'] ?? '');
    $nama_pasien = trim($_POST['nama_pasien'] ?? '');
    $pengirim = trim($_POST['pengirim'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($no_sep === '') {
        echo json_encode(['success' => false, 'error' => 'no_sep required']);
        exit;
    }

    if ($phone === '' || $message === '') {
        echo json_encode(['success' => false, 'error' => 'Nomor HP dan pesan wajib diisi']);
        exit;
    }

    if (!ensure_reminder_queue_table($koneksi)) {
        echo json_encode(['success' => false, 'error' => 'Tabel antrean reminder gagal dibuat: ' . $koneksi->error]);
        exit;
    }

    $scheduledAt = date('Y-m-d H:i:s');
    $sql = "INSERT INTO antrean_reminder_kontrol
                (no_sep, nomr, nama_pasien, phone, message, pengirim, status, attempts, scheduled_at, last_error)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, NULL)
            ON DUPLICATE KEY UPDATE
                nomr = VALUES(nomr),
                nama_pasien = VALUES(nama_pasien),
                phone = VALUES(phone),
                message = VALUES(message),
                pengirim = VALUES(pengirim),
                status = IF(antrean_reminder_kontrol.status = 'sent', antrean_reminder_kontrol.status, 'pending'),
                scheduled_at = IF(antrean_reminder_kontrol.status = 'sent', antrean_reminder_kontrol.scheduled_at, VALUES(scheduled_at)),
                last_error = IF(antrean_reminder_kontrol.status = 'sent', antrean_reminder_kontrol.last_error, NULL)";

    if ($stmt = $koneksi->prepare($sql)) {
        $stmt->bind_param("sssssss", $no_sep, $nomr, $nama_pasien, $phone, $message, $pengirim, $scheduledAt);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        if ($ok) {
            $logSql = "INSERT INTO log_kirim_reminder_kontrol
                           (no_sep, nomr, nama_pasien, tgl_kirim, pengirim)
                       VALUES (?, ?, ?, NOW(), ?)
                       ON DUPLICATE KEY UPDATE
                           nomr = VALUES(nomr),
                           nama_pasien = VALUES(nama_pasien),
                           tgl_kirim = NOW(),
                           pengirim = VALUES(pengirim)";
            $logStmt = $koneksi->prepare($logSql);
            if (!$logStmt) {
                echo json_encode(['success' => false, 'error' => $koneksi->error]);
                exit;
            }
            $logStmt->bind_param("ssss", $no_sep, $nomr, $nama_pasien, $pengirim);
            $ok = $logStmt->execute();
            $error = $logStmt->error;
            $logStmt->close();
        }

        echo json_encode($ok ? [
            'success' => true,
            'message' => 'Reminder diterima sistem dan akan dikirim oleh service.',
        ] : ['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => $koneksi->error]);
    }
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
