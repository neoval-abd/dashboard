<?php
/*
 * File: api/ucapan_ulang_tahun.php
 * Fungsi: Track status pengiriman ucapan ulang tahun
 *         GET  = ambil daftar RM yang sudah dikirim tahun ini
 *         POST = tandai RM sebagai sudah dikirim
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');
require_once(__DIR__ . '/fonnte_client.php');

$tahun = (int) date('Y');

// GET: return list of no_rkm_medis already sent this year
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sent = [];
    $sql = "SELECT no_rkm_medis, tgl_kirim, pengirim 
            FROM ucapan_ulang_tahun 
            WHERE tahun = $tahun";
    $res = $koneksi->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sent[] = $row;
        }
    }
    echo json_encode(['sent' => $sent], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: mark as sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rm = trim($_POST['no_rkm_medis'] ?? '');
    $pengirim = trim($_POST['pengirim'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($rm)) {
        echo json_encode(['success' => false, 'error' => 'no_rkm_medis required']);
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
    
    $sql = "INSERT INTO ucapan_ulang_tahun (no_rkm_medis, tahun, tgl_kirim, pengirim)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE tgl_kirim = NOW(), pengirim = VALUES(pengirim)";
    
    if ($stmt = $koneksi->prepare($sql)) {
        $stmt->bind_param("sis", $rm, $tahun, $pengirim);
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
