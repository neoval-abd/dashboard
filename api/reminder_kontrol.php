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
    $sent = [];
    $sql = "SELECT no_sep, nomr, nama_pasien, tgl_kirim, pengirim
            FROM log_kirim_reminder_kontrol";
    $res = $koneksi->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sent[] = $row;
        }
    }
    echo json_encode(['sent' => $sent], JSON_UNESCAPED_UNICODE);
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

    if ($phone !== '' || $message !== '') {
        $send = send_fonnte_message($phone, $message);
        if (empty($send['success'])) {
            echo json_encode([
                'success' => false,
                'error' => $send['error'] ?? 'Gagal mengirim via Fonnte',
                'fonnte' => $send,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $sql = "INSERT INTO log_kirim_reminder_kontrol (no_sep, nomr, nama_pasien, tgl_kirim, pengirim)
            VALUES (?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                nomr = VALUES(nomr),
                nama_pasien = VALUES(nama_pasien),
                tgl_kirim = NOW(),
                pengirim = VALUES(pengirim)";

    if ($stmt = $koneksi->prepare($sql)) {
        $stmt->bind_param("ssss", $no_sep, $nomr, $nama_pasien, $pengirim);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        echo json_encode($ok ? ['success' => true, 'sent_via' => ($phone !== '' ? 'fonnte' : 'manual')] : ['success' => false, 'error' => $error]);
    } else {
        echo json_encode(['success' => false, 'error' => $koneksi->error]);
    }
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
