<?php
/*
 * File: api/data_proyeksi_keuntungan.php (FIX v2)
 * - Fix Fatal Error: Kolom h_beli tidak ada di tabel detreturjual.
 * - Solusi: JOIN ke tabel databarang untuk ambil h_beli.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$status_bayar = isset($_GET['status_bayar']) ? $_GET['status_bayar'] : '';
$kd_pj = isset($_GET['kd_pj']) ? $_GET['kd_pj'] : '';

// Init Summary
$summary = [
    'omzet' => 0,
    'modal' => 0,
    'profit' => 0,
    'retur' => 0
];

// Init Chart Data
$daily_data = [];

// =================================================================================
// 1. QUERY OBAT PASIEN (RALAN & RANAP)
// =================================================================================
$filter_pasien = "";
if ($status_bayar == 'Lunas') {
    $filter_pasien .= " AND reg_periksa.no_rawat NOT IN (SELECT no_rawat FROM piutang_pasien WHERE status='Belum Lunas') ";
} elseif ($status_bayar == 'Piutang') {
    $filter_pasien .= " AND reg_periksa.no_rawat IN (SELECT no_rawat FROM piutang_pasien WHERE status='Belum Lunas') ";
}

if (!empty($kd_pj)) {
    $filter_pasien .= " AND reg_periksa.kd_pj = '$kd_pj' ";
}

$sql_pasien = "
    SELECT 
        dpo.tgl_perawatan as tanggal,
        dpo.no_rawat,
        pasien.nm_pasien,
        penjab.png_jawab,
        databarang.nama_brng,
        dpo.jml,
        dpo.biaya_obat as h_jual, 
        dpo.h_beli,
        dpo.total as subtotal_jual, 
        (dpo.h_beli * dpo.jml) as subtotal_modal,
        (dpo.total - (dpo.h_beli * dpo.jml)) as profit
    FROM detail_pemberian_obat dpo
    INNER JOIN reg_periksa ON dpo.no_rawat = reg_periksa.no_rawat
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    INNER JOIN databarang ON dpo.kode_brng = databarang.kode_brng
    WHERE dpo.tgl_perawatan BETWEEN ? AND ?
    $filter_pasien
    ORDER BY dpo.tgl_perawatan DESC, dpo.jam DESC
";

$data_pasien = [];
$stmt = $koneksi->prepare($sql_pasien);

if ($stmt) {
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $res = $stmt->get_result();

    while($row = $res->fetch_assoc()) {
        $data_pasien[] = $row;
        
        $summary['omzet'] += (float)$row['subtotal_jual'];
        $summary['modal'] += (float)$row['subtotal_modal'];
        $summary['profit'] += (float)$row['profit'];
        
        $tgl = date('Y-m-d', strtotime($row['tanggal']));
        if(!isset($daily_data[$tgl])) $daily_data[$tgl] = ['omzet'=>0, 'modal'=>0, 'profit'=>0];
        $daily_data[$tgl]['omzet'] += (float)$row['subtotal_jual'];
        $daily_data[$tgl]['modal'] += (float)$row['subtotal_modal'];
        $daily_data[$tgl]['profit'] += (float)$row['profit'];
    }
    $stmt->close();
} else {
    // Error handling sederhana jika query gagal prepare
    error_log("Query Pasien Error: " . $koneksi->error);
}


// =================================================================================
// 2. QUERY PENJUALAN BEBAS (APOTEK/TOKO)
// =================================================================================
$data_bebas = [];
if (empty($kd_pj) && $status_bayar != 'Piutang') { 
    $sql_bebas = "
        SELECT 
            penjualan.tgl_jual as tanggal,
            penjualan.nota_jual,
            penjualan.nm_pasien as pembeli,
            databarang.nama_brng,
            dj.jumlah,
            dj.h_jual,
            dj.h_beli,
            dj.total as subtotal_jual, 
            (dj.h_beli * dj.jumlah) as subtotal_modal,
            (dj.total - (dj.h_beli * dj.jumlah)) as profit
        FROM detailjual dj
        INNER JOIN penjualan ON dj.nota_jual = penjualan.nota_jual
        INNER JOIN databarang ON dj.kode_brng = databarang.kode_brng
        WHERE penjualan.tgl_jual BETWEEN ? AND ?
        ORDER BY penjualan.tgl_jual DESC
    ";
    
    $stmt2 = $koneksi->prepare($sql_bebas);
    if ($stmt2) {
        $stmt2->bind_param("ss", $tgl_awal, $tgl_akhir);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        
        while($row = $res2->fetch_assoc()) {
            $data_bebas[] = $row;
            
            $summary['omzet'] += (float)$row['subtotal_jual'];
            $summary['modal'] += (float)$row['subtotal_modal'];
            $summary['profit'] += (float)$row['profit'];
            
            $tgl = date('Y-m-d', strtotime($row['tanggal']));
            if(!isset($daily_data[$tgl])) $daily_data[$tgl] = ['omzet'=>0, 'modal'=>0, 'profit'=>0];
            $daily_data[$tgl]['omzet'] += (float)$row['subtotal_jual'];
            $daily_data[$tgl]['modal'] += (float)$row['subtotal_modal'];
            $daily_data[$tgl]['profit'] += (float)$row['profit'];
        }
        $stmt2->close();
    }
}


// =================================================================================
// 3. HITUNG RETUR (PENGURANG PROFIT) - FIX JOIN
// =================================================================================
// Kita join ke databarang karena detreturjual tidak punya h_beli
$sql_retur_bebas = "
    SELECT 
        SUM(drj.subtotal) as total_retur_jual, 
        SUM(b.h_beli * drj.jml_retur) as modal_retur
    FROM detreturjual drj
    INNER JOIN returjual rj ON drj.no_retur_jual = rj.no_retur_jual
    INNER JOIN databarang b ON drj.kode_brng = b.kode_brng
    WHERE rj.tgl_retur BETWEEN ? AND ?
";

$stmt3 = $koneksi->prepare($sql_retur_bebas);
if ($stmt3) {
    $stmt3->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    
    if ($res3) {
        $row_retur = $res3->fetch_assoc();
        $retur_omzet = (float)$row_retur['total_retur_jual'];
        $retur_modal = (float)$row_retur['modal_retur'];
        $retur_profit = $retur_omzet - $retur_modal;

        // Kurangi Summary dengan Retur
        $summary['omzet'] -= $retur_omzet;
        $summary['modal'] -= $retur_modal;
        $summary['profit'] -= $retur_profit;
        $summary['retur'] = $retur_omzet; 
    }
    $stmt3->close();
} else {
    error_log("Query Retur Error: " . $koneksi->error);
}

// =================================================================================
// 4. FORMAT CHART DATA
// =================================================================================
ksort($daily_data); 
$chart_labels = [];
$chart_omzet = [];
$chart_modal = [];
$chart_profit = [];

foreach($daily_data as $tgl => $val) {
    $chart_labels[] = date('d/m', strtotime($tgl));
    $chart_omzet[] = $val['omzet'];
    $chart_modal[] = $val['modal'];
    $chart_profit[] = $val['profit'];
}

echo json_encode([
    'summary' => $summary,
    'pasien' => $data_pasien,
    'bebas' => $data_bebas,
    'chart' => [
        'labels' => $chart_labels,
        'omzet' => $chart_omzet,
        'modal' => $chart_modal,
        'profit' => $chart_profit
    ]
]);

$koneksi->close();
?>