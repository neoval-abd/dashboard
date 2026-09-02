<?php
/*
 * File: api/reminder_kontrol.php
 * Fungsi: Kirim reminder kontrol langsung via Fonnte tanpa tabel tambahan.
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
    echo json_encode([
        'sent' => [],
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

    $send = send_fonnte_message($phone, $message);
    echo json_encode(!empty($send['success']) ? [
        'success' => true,
        'message' => 'Reminder berhasil dikirim.',
    ] : [
        'success' => false,
        'error' => $send['error'] ?? 'Gagal mengirim via Fonnte',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
