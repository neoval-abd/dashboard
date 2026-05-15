<?php
/*
 * File: api/data_stok_farmasi.php
 * Fungsi: 
 * 1. Cek konfigurasi harga (Dasar/Beli).
 * 2. Agregasi stok dari gudangbarang.
 * 3. Menghitung valuasi aset.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

// 1. Ambil Parameter Filter
$kd_bangsal = isset($_GET['kd_bangsal']) ? $_GET['kd_bangsal'] : '';
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

// 2. Cek Konfigurasi Harga (Sesuai DlgSisaStok.java)
$harga_field = "dasar"; // Default
$sql_conf = "SELECT hargadasar FROM set_harga_obat LIMIT 1";
$res_conf = $koneksi->query($sql_conf);
if ($res_conf && $res_conf->num_rows > 0) {
    $row_conf = $res_conf->fetch_assoc();
    if ($row_conf['hargadasar'] == 'Harga Beli') {
        $harga_field = "h_beli";
    } else {
        $harga_field = "dasar";
    }
}

// 3. Bangun Query Utama
// Kita gunakan GROUP_CONCAT untuk melihat obat ini ada di gudang mana saja dalam satu baris
$where = " WHERE databarang.status = '1' "; // Hanya barang aktif
$params = [];
$types = "";

if (!empty($kd_bangsal)) {
    $where .= " AND gudangbarang.kd_bangsal = ? ";
    $params[] = $kd_bangsal;
    $types .= "s";
}

if (!empty($keyword)) {
    $where .= " AND (databarang.kode_brng LIKE ? OR databarang.nama_brng LIKE ?) ";
    $keyword_param = "%" . $keyword . "%";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $types .= "ss";
}

$sql = "
    SELECT 
        databarang.kode_brng,
        databarang.nama_brng,
        kodesatuan.satuan,
        databarang.$harga_field as harga_dasar,
        SUM(gudangbarang.stok) as total_stok,
        (SUM(gudangbarang.stok) * databarang.$harga_field) as total_aset,
        GROUP_CONCAT(DISTINCT bangsal.nm_bangsal SEPARATOR ', ') as lokasi_stok
    FROM databarang
    INNER JOIN gudangbarang ON databarang.kode_brng = gudangbarang.kode_brng
    INNER JOIN kodesatuan ON databarang.kode_sat = kodesatuan.kode_sat
    INNER JOIN bangsal ON gudangbarang.kd_bangsal = bangsal.kd_bangsal
    $where
    GROUP BY databarang.kode_brng
    HAVING total_stok != 0 -- Opsional: Sembunyikan stok 0 agar tabel bersih
    ORDER BY total_stok DESC
    LIMIT 500 -- Batasi agar browser HP tidak crash
";

$stmt = $koneksi->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $bind_names[] = $types;
        for ($i=0; $i<count($params);$i++) { $bind_names[] = &$params[$i]; }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    $total_aset_global = 0;
    $total_item = 0;
    $stok_kritis = 0;

    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $total_aset_global += (float)$row['total_aset'];
        $total_item++;
        if ((float)$row['total_stok'] < 10) { // Asumsi stok kritis < 10
            $stok_kritis++;
        }
    }
    
    echo json_encode([
        'summary' => [
            'total_aset' => $total_aset_global,
            'total_item' => $total_item,
            'stok_kritis' => $stok_kritis
        ],
        'data' => $data
    ]);
    
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => $koneksi->error]);
}
?>