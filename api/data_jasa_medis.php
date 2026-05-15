<?php
/*
 * File: api/data_jasa_medis.php (FIX LOGIC v3)
 * - Fix: Menggunakan 'tarif_tindakandr' (Jasa Dokter Murni) untuk Ralan/Ranap Dokter.
 * - Sebelumnya menggunakan 'biaya_rawat' (Gross) yang menyebabkan angka bengkak.
 */

ini_set('display_errors', 0);
ini_set('memory_limit', '-1'); 
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_pj = isset($_GET['kd_pj']) ? $_GET['kd_pj'] : ''; 
$kd_dokter = isset($_GET['kd_dokter']) ? $_GET['kd_dokter'] : ''; 

// Filter Penjamin
$join_reg = "";
$where_pj = "";
if (!empty($kd_pj)) {
    $join_reg = " INNER JOIN reg_periksa rp ON t.no_rawat = rp.no_rawat ";
    $where_pj = " AND rp.kd_pj = '$kd_pj' ";
}

$queries = [];

// A. RALAN
// FIX: Gunakan tarif_tindakandr, BUKAN biaya_rawat
$queries[] = "SELECT t.kd_dokter, t.tarif_tindakandr as jm, 'Ralan' as kategori 
              FROM rawat_jl_dr t $join_reg WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_pj";
$queries[] = "SELECT t.kd_dokter, t.tarif_tindakandr as jm, 'Ralan' as kategori 
              FROM rawat_jl_drpr t $join_reg WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_pj";

// B. RANAP
// FIX: Gunakan tarif_tindakandr, BUKAN biaya_rawat
$queries[] = "SELECT t.kd_dokter, t.tarif_tindakandr as jm, 'Ranap' as kategori 
              FROM rawat_inap_dr t $join_reg WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_pj";
$queries[] = "SELECT t.kd_dokter, t.tarif_tindakandr as jm, 'Ranap' as kategori 
              FROM rawat_inap_drpr t $join_reg WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_pj";

// C. OPERASI (Multi Peran)
$op_roles = [
    'operator1' => 'biayaoperator1', 'operator2' => 'biayaoperator2', 'operator3' => 'biayaoperator3',
    'dokter_anestesi' => 'biayadokter_anestesi', 'dokter_anak' => 'biayadokter_anak', 'dokter_umum' => 'biaya_dokter_umum'
];
foreach($op_roles as $role => $biaya) {
    $queries[] = "SELECT t.$role as kd_dokter, t.$biaya as jm, 'Operasi' as kategori 
                  FROM operasi t $join_reg WHERE t.tgl_operasi BETWEEN '$tgl_awal 00:00:00' AND '$tgl_akhir 23:59:59' $where_pj AND t.$biaya > 0";
}

// D. PENUNJANG
$queries[] = "SELECT t.kd_dokter, t.tarif_tindakan_dokter as jm, 'Radiologi' as kategori 
              FROM periksa_radiologi t $join_reg WHERE t.tgl_periksa BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_pj AND t.tarif_tindakan_dokter > 0";
$queries[] = "SELECT t.kd_dokter, t.tarif_tindakan_dokter as jm, 'Laboratorium' as kategori 
              FROM periksa_lab t $join_reg WHERE t.tgl_periksa BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_pj AND t.tarif_tindakan_dokter > 0";

// EKSEKUSI
$sql_union = implode(" UNION ALL ", $queries);

$sql_final = "
    SELECT 
        u.kd_dokter, d.nm_dokter, d.kd_sps, s.nm_sps, u.kategori, SUM(u.jm) as total_jm
    FROM ($sql_union) u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps
    WHERE u.kd_dokter <> '-' AND u.kd_dokter <> ''
";

if (!empty($kd_dokter)) {
    $sql_final .= " AND u.kd_dokter = '$kd_dokter' ";
}

$sql_final .= " GROUP BY u.kd_dokter, u.kategori ORDER BY total_jm DESC";

$result = $koneksi->query($sql_final);

$data_dokter = []; 
$summary = ['total_jm'=>0, 'jm_ralan'=>0, 'jm_ranap'=>0, 'jm_operasi'=>0, 'jm_penunjang'=>0];

if ($result) {
    while($row = $result->fetch_assoc()) {
        $kd = $row['kd_dokter'];
        $kat = $row['kategori'];
        $jm = (float)$row['total_jm'];

        if (!isset($data_dokter[$kd])) {
            $data_dokter[$kd] = [
                'kd_dokter' => $kd,
                'nm_dokter' => $row['nm_dokter'],
                'spesialis' => $row['nm_sps'],
                'Ralan' => 0, 'Ranap' => 0, 'Operasi' => 0, 'Penunjang' => 0, 'Total' => 0
            ];
        }

        if ($kat == 'Ralan') { $data_dokter[$kd]['Ralan'] += $jm; $summary['jm_ralan'] += $jm; }
        elseif ($kat == 'Ranap') { $data_dokter[$kd]['Ranap'] += $jm; $summary['jm_ranap'] += $jm; }
        elseif ($kat == 'Operasi') { $data_dokter[$kd]['Operasi'] += $jm; $summary['jm_operasi'] += $jm; }
        else { $data_dokter[$kd]['Penunjang'] += $jm; $summary['jm_penunjang'] += $jm; }

        $data_dokter[$kd]['Total'] += $jm;
        $summary['total_jm'] += $jm;
    }
}

$table_data = array_values($data_dokter);
usort($table_data, function($a, $b) { return $b['Total'] - $a['Total']; });

$chart_top10 = array_slice($table_data, 0, 10);
$labels = array_column($chart_top10, 'nm_dokter');
$data_total = array_column($chart_top10, 'Total');

echo json_encode([
    'summary' => $summary,
    'table' => $table_data,
    'chart' => ['labels' => $labels, 'data' => $data_total]
]);

$koneksi->close();
?>