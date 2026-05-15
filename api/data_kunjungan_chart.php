<?php
/*
 * File api/data_kunjungan_chart.php (BERSIH)
 * API untuk menyuplai data Chart Kunjungan Pasien.
 * Pastikan tidak ada teks/spasi sebelum tag <?php
 */

// Matikan error display agar tidak merusak JSON jika ada warning kecil
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/includes/functions.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

// 1. Ambil Parameter
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$jam_awal = isset($_GET['jam_awal']) ? $_GET['jam_awal'] : '00:00:00';
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$jam_akhir = isset($_GET['jam_akhir']) ? $_GET['jam_akhir'] : '23:59:59';
$kd_pj = isset($_GET['kd_pj']) ? $_GET['kd_pj'] : ''; 

$datetime_awal = $tgl_awal . ' ' . $jam_awal;
$datetime_akhir = $tgl_akhir . ' ' . $jam_akhir;

// 2. Siapkan Query
$where_tambahan = "";
$params = [];
$types = "";

// Parameter Wajib
$params[] = $datetime_awal; 
$params[] = $datetime_akhir;
$types = "ss";

if (!empty($kd_pj)) {
    $where_tambahan .= " AND reg_periksa.kd_pj = ? ";
    $params[] = $kd_pj;
    $types .= "s";
}

// Query Agregasi (Hitung jumlah no_rawat per tanggal per penjamin)
$sql = "
    SELECT 
        reg_periksa.tgl_registrasi, 
        penjab.png_jawab, 
        COUNT(reg_periksa.no_rawat) as total_kunjungan
    FROM reg_periksa 
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj 
    WHERE 
        CONCAT(reg_periksa.tgl_registrasi, ' ', reg_periksa.jam_reg) BETWEEN ? AND ?
        AND reg_periksa.stts != 'Batal'
        $where_tambahan
    GROUP BY reg_periksa.tgl_registrasi, penjab.png_jawab
    ORDER BY reg_periksa.tgl_registrasi ASC
";

$stmt = $koneksi->prepare($sql);

if ($stmt) {
    // Bind Param Dinamis
    $bind_names[] = $types;
    for ($i=0; $i<count($params);$i++) { 
        $bind_names[] = &$params[$i]; 
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Struktur Data untuk Chart
    $pie_data_raw = []; // [Penjab => Total Count]
    $line_data_raw = []; // [Tanggal => [Penjab => Count]]
    $list_penjab = []; 
    $list_tanggal = []; 

    while ($row = $result->fetch_assoc()) {
        $penjab = $row['png_jawab'];
        $tanggal = date('d-m-Y', strtotime($row['tgl_registrasi']));
        $total = (int)$row['total_kunjungan'];

        // Data Pie Chart
        if (!isset($pie_data_raw[$penjab])) $pie_data_raw[$penjab] = 0;
        $pie_data_raw[$penjab] += $total;

        // Data Line Chart
        if (!isset($line_data_raw[$tanggal][$penjab])) $line_data_raw[$tanggal][$penjab] = 0;
        $line_data_raw[$tanggal][$penjab] += $total;

        // Kumpulkan Key Unik
        if (!in_array($penjab, $list_penjab)) $list_penjab[] = $penjab;
        if (!in_array($tanggal, $list_tanggal)) $list_tanggal[] = $tanggal;
    }
    $stmt->close();
    $koneksi->close();

    // 3. Format Data JSON
    // A. PIE CHART
    $pie_response = [
        'labels' => array_keys($pie_data_raw),
        'data' => array_values($pie_data_raw)
    ];

    // B. LINE CHART
    // Pastikan tanggal unik dan terurut
    $list_tanggal = array_values(array_unique($list_tanggal));
    
    $line_datasets = [];
    // Warna Chart (Palette Bootstrap/Flat UI)
    $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf'];
    $c_idx = 0;

    foreach ($list_penjab as $pj) {
        $data_points = [];
        foreach ($list_tanggal as $tgl) {
            // Jika tidak ada data di tanggal itu, isi 0
            $val = isset($line_data_raw[$tgl][$pj]) ? $line_data_raw[$tgl][$pj] : 0;
            $data_points[] = $val;
        }

        // Jika warna habis, generate random
        $color = isset($colors[$c_idx]) ? $colors[$c_idx] : '#' . substr(md5($pj), 0, 6);
        
        $line_datasets[] = [
            'label' => $pj,
            'data' => $data_points,
            'borderColor' => $color,
            'backgroundColor' => $color,
            'fill' => false,
            'tension' => 0.1
        ];
        $c_idx++;
    }

    $line_response = [
        'labels' => $list_tanggal,
        'datasets' => $line_datasets
    ];

    echo json_encode([
        'pie' => $pie_response,
        'line' => $line_response
    ]);

} else {
    http_response_code(500);
    echo json_encode(['error' => $koneksi->error]);
}
?>