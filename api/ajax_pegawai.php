<?php
/*
 * File: api/ajax_pegawai.php (SECURITY FIX)
 * - FIX KRITIS: SQL Injection → Prepared Statement dengan LIKE + wildcard aman
 * - Auth guard sudah dihandle via auto_prepend_file (api/.htaccess)
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

$koneksi->query("SET sql_mode = ''");

// Ambil parameter pencarian dan validasi ketat
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$data = [];

if (!empty($search) && strlen($search) >= 2) {
    // --- FIX SQL INJECTION: Prepared Statement dengan wildcard LIKE ---
    // Wildcard % ditempatkan di dalam nilai bind_param, bukan di query string
    $like_param = '%' . $search . '%';

    $stmt = $koneksi->prepare(
        "SELECT nik, nama FROM pegawai WHERE nik LIKE ? OR nama LIKE ? LIMIT 20"
    );
    $stmt->bind_param("ss", $like_param, $like_param);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[] = [
                'id'   => htmlspecialchars($row['nik']),  // value (username)
                'text' => htmlspecialchars($row['nik'] . ' - ' . $row['nama']) // label
            ];
        }
    }
    $stmt->close();
}

ob_end_clean();
echo json_encode($data);
$koneksi->close();
?>