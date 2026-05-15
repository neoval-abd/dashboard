<?php
require_once('../config/koneksi.php');

header('Content-Type: application/json');

// Ambil parameter filter
$rentang = isset($_GET['rentang']) ? $_GET['rentang'] : '3bulan';
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : '';
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : '';
$kd_bangsal = isset($_GET['kd_bangsal']) ? $koneksi->real_escape_string($_GET['kd_bangsal']) : '';
$keyword = isset($_GET['keyword']) ? $koneksi->real_escape_string($_GET['keyword']) : '';

// Tentukan tanggal batas (cutoff)
$cutoff_start = "";
$cutoff_end = date('Y-m-d'); // Default end date is today

if ($rentang === 'custom') {
    $cutoff_start = $koneksi->real_escape_string($tgl_awal);
    $cutoff_end = $koneksi->real_escape_string($tgl_akhir);
} else {
    // Generate cutoff start based on predefined range
    switch ($rentang) {
        case '1bulan':
            $cutoff_start = date('Y-m-d', strtotime('-1 month'));
            break;
        case '3bulan':
            $cutoff_start = date('Y-m-d', strtotime('-3 months'));
            break;
        case '6bulan':
            $cutoff_start = date('Y-m-d', strtotime('-6 months'));
            break;
        case '1tahun':
            $cutoff_start = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            $cutoff_start = date('Y-m-d', strtotime('-3 months'));
            break;
    }
}

$response = [
    'summary' => [
        'total_item' => 0,
        'total_aset' => 0,
        'total_stok' => 0,
        'cutoff_start' => $cutoff_start,
        'cutoff_end' => $cutoff_end
    ],
    'chart' => [
        'labels' => [],
        'data' => []
    ],
    'data' => []
];

// Pastikan tanggal valid
if (empty($cutoff_start) || empty($cutoff_end)) {
    echo json_encode($response);
    exit;
}

/* 
 * Query Logika Dead Stock:
 * Cari dari gudangbarang (stok > 0), join databarang (untuk nama & hpp).
 * HPP yang digunakan adalah dasarnya tabel databarang `dasar` atau `h_beli`.
 * Filter NOT EXISTS di transaksi riwayat_barang_medis posisi = 'Keluar' pada rentang waktu.
 */

$sql = "SELECT 
    g.kode_brng,
    d.nama_brng,
    g.kd_bangsal,
    b.nm_bangsal,
    g.stok,
    d.dasar as hpp_dasar
FROM gudangbarang g
INNER JOIN databarang d ON g.kode_brng = d.kode_brng
INNER JOIN bangsal b ON g.kd_bangsal = b.kd_bangsal
WHERE g.stok > 0 
  AND NOT EXISTS (
      SELECT 1 
      FROM riwayat_barang_medis r 
      WHERE r.kode_brng = g.kode_brng 
        AND r.kd_bangsal = g.kd_bangsal 
        AND r.posisi = 'Keluar' 
        AND r.tanggal BETWEEN '$cutoff_start' AND '$cutoff_end'
  )";

if (!empty($kd_bangsal)) {
    $sql .= " AND g.kd_bangsal = '$kd_bangsal'";
}

if (!empty($keyword)) {
    $sql .= " AND (g.kode_brng LIKE '%$keyword%' OR d.nama_brng LIKE '%$keyword%')";
}

// Biar performanya baik, limitasi tidak dipakai jika ingin total agregat akurat.
$result = $koneksi->query($sql);

if ($result) {
    $total_item = 0;
    $total_aset = 0;
    $total_stok = 0;

    while ($row = $result->fetch_assoc()) {
        $stok = floatval($row['stok']);
        $hpp = floatval($row['hpp_dasar']);
        $subtotal_aset = $stok * $hpp;

        $row['stok_val'] = $stok;
        $row['hpp_val'] = $hpp;
        $row['aset_val'] = $subtotal_aset;

        $total_item++;
        $total_stok += $stok;
        $total_aset += $subtotal_aset;

        $response['data'][] = $row;
    }

    // Urutkan data berdasarkan aset_val descending untuk chart top 10
    usort($response['data'], function($a, $b) {
        return $b['aset_val'] <=> $a['aset_val'];
    });

    // Ambil top 10 untuk chart
    $top10 = array_slice($response['data'], 0, 10);
    foreach ($top10 as $item) {
        // Potong nama barang jika terlalu panjang untuk label chart
        $nama_singkat = strlen($item['nama_brng']) > 20 ? substr($item['nama_brng'], 0, 20) . '...' : $item['nama_brng'];
        $response['chart']['labels'][] = $nama_singkat;
        $response['chart']['data'][] = $item['aset_val'];
    }

    $response['summary']['total_item'] = $total_item;
    $response['summary']['total_stok'] = $total_stok;
    $response['summary']['total_aset'] = $total_aset;
}

echo json_encode($response);
?>
