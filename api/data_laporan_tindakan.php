<?php
/*
 * File: api/data_laporan_tindakan.php (FIX LOGIC v6)
 * - Kolom 'biaya' = Total Tagihan (Gross)
 * - Kolom 'jm_dokter' = Jasa Medis Dokter (Netto - tarif_tindakandr)
 * - Agar sinkron dengan laporan JM Dokter.
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
$filter_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : ''; 
$kd_dokter = isset($_GET['kd_dokter']) ? $_GET['kd_dokter'] : ''; 

$join_base = " INNER JOIN reg_periksa rp ON t.no_rawat = rp.no_rawat INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis ";
$where_global = "";
if (!empty($kd_pj)) {
    $where_global .= " AND rp.kd_pj = '$kd_pj' ";
}

$queries = [];

// --- A. RAWAT JALAN ---
if (empty($filter_kategori) || $filter_kategori == 'Rawat Jalan') {
    $where_dr = (!empty($kd_dokter)) ? " AND t.kd_dokter = '$kd_dokter' " : "";
    
    // 1. Ralan - Dokter
    // FIX: biaya = biaya_rawat, jm_dokter = tarif_tindakandr
    $queries[] = "SELECT t.tgl_perawatan as tanggal, t.jam_rawat as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien, 
                  jns.nm_perawatan, t.biaya_rawat as biaya, t.tarif_tindakandr as jm_dokter, 'Rawat Jalan' as kategori, d.nm_dokter
                  FROM rawat_jl_dr t 
                  INNER JOIN jns_perawatan jns ON t.kd_jenis_prw = jns.kd_jenis_prw 
                  INNER JOIN dokter d ON t.kd_dokter = d.kd_dokter 
                  $join_base WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global $where_dr";
    
    // 2. Ralan - Paramedis
    if (empty($kd_dokter)) { 
        $queries[] = "SELECT t.tgl_perawatan as tanggal, t.jam_rawat as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                      jns.nm_perawatan, t.biaya_rawat as biaya, 0 as jm_dokter, 'Rawat Jalan' as kategori, '-' as nm_dokter
                      FROM rawat_jl_pr t INNER JOIN jns_perawatan jns ON t.kd_jenis_prw = jns.kd_jenis_prw $join_base
                      WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global";
    }
    
    // 3. Ralan - Dokter & Paramedis
    $queries[] = "SELECT t.tgl_perawatan as tanggal, t.jam_rawat as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                  jns.nm_perawatan, t.biaya_rawat as biaya, t.tarif_tindakandr as jm_dokter, 'Rawat Jalan' as kategori, d.nm_dokter
                  FROM rawat_jl_drpr t INNER JOIN jns_perawatan jns ON t.kd_jenis_prw = jns.kd_jenis_prw INNER JOIN dokter d ON t.kd_dokter = d.kd_dokter 
                  $join_base WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global $where_dr";
}

// --- B. RAWAT INAP ---
if (empty($filter_kategori) || $filter_kategori == 'Rawat Inap') {
    $where_dr = (!empty($kd_dokter)) ? " AND t.kd_dokter = '$kd_dokter' " : "";

    // 1. Ranap - Dokter
    // FIX: biaya = biaya_rawat, jm_dokter = tarif_tindakandr
    $queries[] = "SELECT t.tgl_perawatan as tanggal, t.jam_rawat as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                  jns.nm_perawatan, t.biaya_rawat as biaya, t.tarif_tindakandr as jm_dokter, 'Rawat Inap' as kategori, d.nm_dokter
                  FROM rawat_inap_dr t INNER JOIN jns_perawatan_inap jns ON t.kd_jenis_prw = jns.kd_jenis_prw INNER JOIN dokter d ON t.kd_dokter = d.kd_dokter 
                  $join_base WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global $where_dr";
    
    // 2. Ranap - Paramedis
    if (empty($kd_dokter)) {
        $queries[] = "SELECT t.tgl_perawatan as tanggal, t.jam_rawat as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                      jns.nm_perawatan, t.biaya_rawat as biaya, 0 as jm_dokter, 'Rawat Inap' as kategori, '-' as nm_dokter
                      FROM rawat_inap_pr t INNER JOIN jns_perawatan_inap jns ON t.kd_jenis_prw = jns.kd_jenis_prw $join_base
                      WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global";
    }

    // 3. Ranap - Dokter & Paramedis
    $queries[] = "SELECT t.tgl_perawatan as tanggal, t.jam_rawat as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                  jns.nm_perawatan, t.biaya_rawat as biaya, t.tarif_tindakandr as jm_dokter, 'Rawat Inap' as kategori, d.nm_dokter
                  FROM rawat_inap_drpr t INNER JOIN jns_perawatan_inap jns ON t.kd_jenis_prw = jns.kd_jenis_prw INNER JOIN dokter d ON t.kd_dokter = d.kd_dokter 
                  $join_base WHERE t.tgl_perawatan BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global $where_dr";
}

// --- C. OPERASI ---
if (empty($filter_kategori) || $filter_kategori == 'Operasi') {
    $biaya_total_op = "(t.biayaoperator1 + t.biayaoperator2 + t.biayaoperator3 + t.biayaasisten_operator1 + t.biayaasisten_operator2 + t.biayainstrumen + t.biayadokter_anak + t.biayaperawaat_resusitas + t.biayadokter_anestesi + t.biayaasisten_anestesi + t.biayabidan + t.biayaperawat_luar + t.biayaalat + t.biayasewaok + t.akomodasi + t.bagian_rs + t.biaya_omloop + t.biaya_omloop2 + t.biayasarpras + t.biaya_dokter_pjanak + t.biaya_dokter_umum)";
    
    if (!empty($kd_dokter)) {
        $sql_op = "
            SELECT 
                t.tgl_operasi as tanggal, '00:00:00' as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                pkt.nm_perawatan, $biaya_total_op as biaya,
                (CASE 
                    WHEN t.operator1 = '$kd_dokter' THEN t.biayaoperator1
                    WHEN t.operator2 = '$kd_dokter' THEN t.biayaoperator2
                    WHEN t.operator3 = '$kd_dokter' THEN t.biayaoperator3
                    WHEN t.dokter_anestesi = '$kd_dokter' THEN t.biayadokter_anestesi
                    WHEN t.dokter_anak = '$kd_dokter' THEN t.biayadokter_anak
                    WHEN t.dokter_umum = '$kd_dokter' THEN t.biaya_dokter_umum
                    ELSE 0
                END) as jm_dokter,
                'Operasi' as kategori,
                (SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter') as nm_dokter
            FROM operasi t
            INNER JOIN paket_operasi pkt ON t.kode_paket = pkt.kode_paket
            $join_base
            WHERE t.tgl_operasi BETWEEN '$tgl_awal 00:00:00' AND '$tgl_akhir 23:59:59' $where_global
            AND (t.operator1='$kd_dokter' OR t.operator2='$kd_dokter' OR t.operator3='$kd_dokter' OR t.dokter_anestesi='$kd_dokter' OR t.dokter_anak='$kd_dokter' OR t.dokter_umum='$kd_dokter')
        ";
        $queries[] = $sql_op;
    } else {
        $queries[] = "
            SELECT 
                t.tgl_operasi as tanggal, '00:00:00' as jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                pkt.nm_perawatan, $biaya_total_op as biaya, 
                t.biayaoperator1 as jm_dokter, 'Operasi' as kategori, d.nm_dokter
            FROM operasi t 
            INNER JOIN paket_operasi pkt ON t.kode_paket = pkt.kode_paket 
            INNER JOIN dokter d ON t.operator1 = d.kd_dokter 
            $join_base
            WHERE t.tgl_operasi BETWEEN '$tgl_awal 00:00:00' AND '$tgl_akhir 23:59:59' $where_global
        ";
    }
}

// --- D. PENUNJANG ---
if (empty($filter_kategori) || $filter_kategori == 'Laboratorium') {
    $where_dr = (!empty($kd_dokter)) ? " AND t.kd_dokter = '$kd_dokter' " : "";
    $queries[] = "SELECT t.tgl_periksa as tanggal, t.jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                  jns.nm_perawatan, t.biaya, t.tarif_tindakan_dokter as jm_dokter, 'Laboratorium' as kategori, d.nm_dokter
                  FROM periksa_lab t INNER JOIN jns_perawatan_lab jns ON t.kd_jenis_prw = jns.kd_jenis_prw INNER JOIN dokter d ON t.kd_dokter = d.kd_dokter 
                  $join_base WHERE t.tgl_periksa BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global $where_dr";
}
if (empty($filter_kategori) || $filter_kategori == 'Radiologi') {
    $where_dr = (!empty($kd_dokter)) ? " AND t.kd_dokter = '$kd_dokter' " : "";
    $queries[] = "SELECT t.tgl_periksa as tanggal, t.jam, t.no_rawat, rp.no_rkm_medis, p.nm_pasien,
                  jns.nm_perawatan, t.biaya, t.tarif_tindakan_dokter as jm_dokter, 'Radiologi' as kategori, d.nm_dokter
                  FROM periksa_radiologi t INNER JOIN jns_perawatan_radiologi jns ON t.kd_jenis_prw = jns.kd_jenis_prw INNER JOIN dokter d ON t.kd_dokter = d.kd_dokter 
                  $join_base WHERE t.tgl_periksa BETWEEN '$tgl_awal' AND '$tgl_akhir' $where_global $where_dr";
}

if (empty($queries)) { echo json_encode(['summary' => [], 'detail' => []]); exit; }

$sql_union = implode(" UNION ALL ", $queries);
$sql_final = "SELECT * FROM ($sql_union) AS gabungan ORDER BY tanggal DESC, jam DESC";

$result = $koneksi->query($sql_final);

$data_detail = [];
$summary = [
    'total_revenue' => 0,
    'total_jasmed' => 0,
    'total_tindakan' => 0,
    'top_unit' => '',
    'avg_tindakan' => 0
];

$chart_kategori = [];
$chart_tren = [];
$chart_top_tindakan = [];

if ($result) {
    while($row = $result->fetch_assoc()) {
        $biaya = (float)$row['biaya'];
        $jasmed = (float)$row['jm_dokter'];
        $kategori = $row['kategori'];
        $tgl = date('Y-m-d', strtotime($row['tanggal']));
        $nama_tindakan = $row['nm_perawatan'];
        
        $row['waktu'] = $tgl . ' ' . $row['jam'];
        $data_detail[] = $row;

        $summary['total_revenue'] += $biaya;
        $summary['total_jasmed'] += $jasmed;
        $summary['total_tindakan']++;

        if(!isset($chart_kategori[$kategori])) $chart_kategori[$kategori] = 0;
        $chart_kategori[$kategori] += $biaya;

        if(!isset($chart_tren[$tgl])) $chart_tren[$tgl] = ['medis'=>0, 'penunjang'=>0];
        if ($kategori == 'Laboratorium' || $kategori == 'Radiologi') { $chart_tren[$tgl]['penunjang'] += $biaya; } 
        else { $chart_tren[$tgl]['medis'] += $biaya; }

        if(!isset($chart_top_tindakan[$nama_tindakan])) { $chart_top_tindakan[$nama_tindakan] = ['count'=>0, 'revenue'=>0]; }
        $chart_top_tindakan[$nama_tindakan]['count']++;
        $chart_top_tindakan[$nama_tindakan]['revenue'] += $biaya;
    }
}

if($summary['total_tindakan'] > 0) { $summary['avg_tindakan'] = $summary['total_revenue'] / $summary['total_tindakan']; }
arsort($chart_kategori);
$summary['top_unit'] = array_key_first($chart_kategori) ?? '-';

$pie_labels = array_keys($chart_kategori);
$pie_values = array_values($chart_kategori);

ksort($chart_tren);
$line_labels = array_keys($chart_tren);
$line_medis = array_column($chart_tren, 'medis');
$line_penunjang = array_column($chart_tren, 'penunjang');

uasort($chart_top_tindakan, function($a, $b) { return $b['revenue'] - $a['revenue']; });
$top_10 = array_slice($chart_top_tindakan, 0, 10);
$bar_labels = array_keys($top_10);
$bar_values = array_column($top_10, 'revenue');
$bar_counts = array_column($top_10, 'count');

echo json_encode([
    'summary' => $summary,
    'detail' => $data_detail,
    'chart_pie' => ['labels' => $pie_labels, 'data' => $pie_values],
    'chart_line' => ['labels' => $line_labels, 'medis' => $line_medis, 'penunjang' => $line_penunjang],
    'chart_bar' => ['labels' => $bar_labels, 'data' => $bar_values, 'counts' => $bar_counts]
]);

$koneksi->close();
?>