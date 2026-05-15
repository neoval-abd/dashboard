<?php
/*
 * File: api/data_rencana_operasi.php (UPDATE V2 - FILTER STATUS)
 * - Added: Logika filter Status Booking (Menunggu / Semua).
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d', strtotime('+7 days'));
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Menunggu'; // Default Menunggu

$sql = "
    SELECT 
        bo.tanggal, bo.jam_mulai, bo.jam_selesai,
        bo.no_rawat, p.no_rkm_medis, p.nm_pasien,
        d.nm_dokter as operator,
        pkt.nm_perawatan,
        bo.status,
        pj.png_jawab
    FROM booking_operasi bo
    INNER JOIN reg_periksa rp ON bo.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    INNER JOIN dokter d ON bo.kd_dokter = d.kd_dokter
    INNER JOIN paket_operasi pkt ON bo.kode_paket = pkt.kode_paket
    INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    WHERE bo.tanggal BETWEEN ? AND ?
";

// Tambahkan Filter Status jika bukan 'Semua'
$params = [$tgl_awal, $tgl_akhir];
$types = "ss";

if ($status_filter != 'Semua') {
    $sql .= " AND bo.status = ? ";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY bo.tanggal ASC, bo.jam_mulai ASC";

$stmt = $koneksi->prepare($sql);

// Bind param dinamis
$bind_names[] = $types;
for ($i=0; $i<count($params);$i++) { 
    $bind_names[] = &$params[$i]; 
}
call_user_func_array(array($stmt, 'bind_param'), $bind_names);

$stmt->execute();
$res = $stmt->get_result();

$data = [];
while($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(['data' => $data]);
?>