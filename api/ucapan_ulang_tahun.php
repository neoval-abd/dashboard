<?php
/*
 * File: api/ucapan_ulang_tahun.php
 * Fungsi: Track status pengiriman ucapan ulang tahun
 *         GET  = ambil daftar RM yang sudah dikirim tahun ini
 *         POST = tandai RM sebagai sudah dikirim
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

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
    $rm = isset($_POST['no_rkm_medis']) ? $koneksi->real_escape_string($_POST['no_rkm_medis']) : '';
    $pengirim = isset($_POST['pengirim']) ? $koneksi->real_escape_string($_POST['pengirim']) : '';
    
    if (empty($rm)) {
        echo json_encode(['success' => false, 'error' => 'no_rkm_medis required']);
        exit;
    }
    
    // INSERT ... ON DUPLICATE KEY UPDATE (in case re-sent same year)
    $sql = "INSERT INTO ucapan_ulang_tahun (no_rkm_medis, tahun, tgl_kirim, pengirim)
            VALUES ('$rm', $tahun, NOW(), '$pengirim')
            ON DUPLICATE KEY UPDATE tgl_kirim = NOW(), pengirim = '$pengirim'";
    
    if ($koneksi->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $koneksi->error]);
    }
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
