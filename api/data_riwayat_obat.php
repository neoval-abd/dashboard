<?php
/*
 * File: api/data_riwayat_obat.php (UPDATE V2)
 * Fungsi: Menampilkan kartu stok digital dengan Filter Depo/Gudang.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

$kode_brng = isset($_GET['kode_brng']) ? $_GET['kode_brng'] : '';
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_bangsal = isset($_GET['kd_bangsal']) ? $_GET['kd_bangsal'] : ''; // Filter Baru

if (empty($kode_brng)) {
    echo json_encode(['data' => []]); exit;
}

// Bangun Query Dinamis
$sql = "
    SELECT 
        r.tanggal,
        r.jam,
        r.stok_awal,
        r.masuk,
        r.keluar,
        r.stok_akhir,
        r.posisi,
        r.no_faktur,
        r.keterangan,
        b.nm_bangsal,
        r.petugas
    FROM riwayat_barang_medis r
    INNER JOIN bangsal b ON r.kd_bangsal = b.kd_bangsal
    WHERE 
        r.kode_brng = ? 
        AND r.tanggal BETWEEN ? AND ?
";

// Siapkan parameter binding
$types = "sss";
$params = [$kode_brng, $tgl_awal, $tgl_akhir];

// Tambahkan filter bangsal jika ada
if (!empty($kd_bangsal)) {
    $sql .= " AND r.kd_bangsal = ? ";
    $types .= "s";
    $params[] = $kd_bangsal;
}

$sql .= " ORDER BY r.tanggal DESC, r.jam DESC";

$stmt = $koneksi->prepare($sql);

if ($stmt) {
    // Bind param secara dinamis
    $bind_names[] = $types;
    for ($i=0; $i<count($params);$i++) { 
        $bind_names[] = &$params[$i]; 
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['data' => $data]);
} else {
    echo json_encode(['error' => $koneksi->error]);
}
?>