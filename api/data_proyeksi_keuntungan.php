<?php
/*
 * File: api/data_proyeksi_keuntungan.php
 * Dashboard Profit Obat (Pasien Resep, Penjualan Bebas & Retur)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

$tgl_awal     = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir    = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$status_bayar = isset($_GET['status_bayar']) ? $_GET['status_bayar'] : '';
$kd_pj        = isset($_GET['kd_pj']) ? $_GET['kd_pj'] : '';

// Init Summary
$summary = [
    // 1. Penjualan Obat Pasien (Resep)
    'omzet_pasien'  => 0,
    'modal_pasien'  => 0,
    'profit_pasien' => 0,
    'qty_pasien'    => 0,

    // 2. Penjualan Obat Bebas (Apotek)
    'omzet_bebas'   => 0,
    'modal_bebas'   => 0,
    'profit_bebas'  => 0,
    'qty_bebas'     => 0,

    // 3. Retur Obat
    'omzet_retur'   => 0,
    'modal_retur'   => 0,
    'profit_retur'  => 0,
    'qty_retur'     => 0,

    // 4. Total Keseluruhan (Netto)
    'omzet_bruto'   => 0,
    'omzet'         => 0,
    'modal'         => 0,
    'profit'        => 0,
    'margin'        => 0,
    'retur'         => 0
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
    $clean_kd_pj = $koneksi->real_escape_string($kd_pj);
    $filter_pasien .= " AND reg_periksa.kd_pj = '$clean_kd_pj' ";
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
        
        $sub_jual   = (float)$row['subtotal_jual'];
        $sub_modal  = (float)$row['subtotal_modal'];
        $sub_profit = (float)$row['profit'];
        $sub_qty    = (float)$row['jml'];

        $summary['omzet_pasien']  += $sub_jual;
        $summary['modal_pasien']  += $sub_modal;
        $summary['profit_pasien'] += $sub_profit;
        $summary['qty_pasien']    += $sub_qty;
        
        $tgl = date('Y-m-d', strtotime($row['tanggal']));
        if(!isset($daily_data[$tgl])) {
            $daily_data[$tgl] = [
                'omzet' => 0, 'modal' => 0, 'profit' => 0,
                'omzet_pasien' => 0, 'omzet_bebas' => 0, 'omzet_retur' => 0
            ];
        }
        $daily_data[$tgl]['omzet']        += $sub_jual;
        $daily_data[$tgl]['omzet_pasien'] += $sub_jual;
        $daily_data[$tgl]['modal']        += $sub_modal;
        $daily_data[$tgl]['profit']       += $sub_profit;
    }
    $stmt->close();
} else {
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
            
            $sub_jual   = (float)$row['subtotal_jual'];
            $sub_modal  = (float)$row['subtotal_modal'];
            $sub_profit = (float)$row['profit'];
            $sub_qty    = (float)$row['jumlah'];

            $summary['omzet_bebas']  += $sub_jual;
            $summary['modal_bebas']  += $sub_modal;
            $summary['profit_bebas'] += $sub_profit;
            $summary['qty_bebas']    += $sub_qty;
            
            $tgl = date('Y-m-d', strtotime($row['tanggal']));
            if(!isset($daily_data[$tgl])) {
                $daily_data[$tgl] = [
                    'omzet' => 0, 'modal' => 0, 'profit' => 0,
                    'omzet_pasien' => 0, 'omzet_bebas' => 0, 'omzet_retur' => 0
                ];
            }
            $daily_data[$tgl]['omzet']       += $sub_jual;
            $daily_data[$tgl]['omzet_bebas'] += $sub_jual;
            $daily_data[$tgl]['modal']       += $sub_modal;
            $daily_data[$tgl]['profit']      += $sub_profit;
        }
        $stmt2->close();
    }
}


// =================================================================================
// 3. RETUR PENJUALAN OBAT
// =================================================================================
$data_retur = [];
if (empty($kd_pj) && $status_bayar != 'Piutang') {
    $sql_retur = "
        SELECT 
            rj.tgl_retur as tanggal,
            rj.no_retur_jual,
            rj.no_rkm_medis,
            COALESCE(p.nm_pasien, '-') as nm_pasien,
            b.nama_brng,
            drj.jml_retur,
            drj.h_retur,
            drj.subtotal as subtotal_retur, 
            (b.h_beli * drj.jml_retur) as modal_retur,
            (drj.subtotal - (b.h_beli * drj.jml_retur)) as profit_retur
        FROM detreturjual drj
        INNER JOIN returjual rj ON drj.no_retur_jual = rj.no_retur_jual
        INNER JOIN databarang b ON drj.kode_brng = b.kode_brng
        LEFT JOIN pasien p ON rj.no_rkm_medis = p.no_rkm_medis
        WHERE rj.tgl_retur BETWEEN ? AND ?
        ORDER BY rj.tgl_retur DESC
    ";

    $stmt3 = $koneksi->prepare($sql_retur);
    if ($stmt3) {
        $stmt3->bind_param("ss", $tgl_awal, $tgl_akhir);
        $stmt3->execute();
        $res3 = $stmt3->get_result();
        
        while ($row = $res3->fetch_assoc()) {
            $data_retur[] = $row;

            $retur_omzet  = (float)($row['subtotal_retur'] ?? 0);
            $retur_modal  = (float)($row['modal_retur'] ?? 0);
            $retur_profit = (float)($row['profit_retur'] ?? 0);
            $retur_qty    = (float)($row['jml_retur'] ?? 0);

            $summary['omzet_retur']  += $retur_omzet;
            $summary['modal_retur']  += $retur_modal;
            $summary['profit_retur'] += $retur_profit;
            $summary['qty_retur']    += $retur_qty;

            // Kurangi Data Harian (Grafik) agar sinkron dengan summary
            $tgl = date('Y-m-d', strtotime($row['tanggal']));
            if (!isset($daily_data[$tgl])) {
                $daily_data[$tgl] = [
                    'omzet' => 0, 'modal' => 0, 'profit' => 0,
                    'omzet_pasien' => 0, 'omzet_bebas' => 0, 'omzet_retur' => 0
                ];
            }
            $daily_data[$tgl]['omzet']       -= $retur_omzet;
            $daily_data[$tgl]['omzet_retur'] += $retur_omzet;
            $daily_data[$tgl]['modal']       -= $retur_modal;
            $daily_data[$tgl]['profit']      -= $retur_profit;
        }
        $stmt3->close();
    } else {
        error_log("Query Retur Error: " . $koneksi->error);
    }
}

// =================================================================================
// 4. KALKULASI FINAL SUMMARY (NETTO & PERSENTASE)
// =================================================================================
// Total Omzet Kotor = Pasien + Bebas
$summary['omzet_bruto'] = $summary['omzet_pasien'] + $summary['omzet_bebas'];

// Total Omzet Bersih (Netto) = Omzet Kotor - Retur
$summary['omzet'] = $summary['omzet_bruto'] - $summary['omzet_retur'];

// Total Modal Bersih (HPP) = Modal Pasien + Modal Bebas - Modal Retur
$summary['modal'] = ($summary['modal_pasien'] + $summary['modal_bebas']) - $summary['modal_retur'];

// Total Keuntungan Bersih (Profit) = Profit Pasien + Profit Bebas - Profit Retur
$summary['profit'] = ($summary['profit_pasien'] + $summary['profit_bebas']) - $summary['profit_retur'];

// Retur (backward compatible alias)
$summary['retur'] = $summary['omzet_retur'];

// Profit Margin (%)
if ($summary['omzet'] > 0) {
    $summary['margin'] = round(($summary['profit'] / $summary['omzet']) * 100, 2);
} else {
    $summary['margin'] = 0;
}

// =================================================================================
// 5. FORMAT CHART DATA
// =================================================================================
ksort($daily_data); 
$chart_labels       = [];
$chart_omzet        = [];
$chart_omzet_pasien = [];
$chart_omzet_bebas  = [];
$chart_omzet_retur  = [];
$chart_modal        = [];
$chart_profit       = [];

foreach($daily_data as $tgl => $val) {
    $chart_labels[]       = date('d/m', strtotime($tgl));
    $chart_omzet[]        = round($val['omzet']);
    $chart_omzet_pasien[] = round($val['omzet_pasien'] ?? 0);
    $chart_omzet_bebas[]  = round($val['omzet_bebas'] ?? 0);
    $chart_omzet_retur[]  = round($val['omzet_retur'] ?? 0);
    $chart_modal[]        = round($val['modal']);
    $chart_profit[]       = round($val['profit']);
}

echo json_encode([
    'summary' => $summary,
    'pasien'  => $data_pasien,
    'bebas'   => $data_bebas,
    'retur'   => $data_retur,
    'chart'   => [
        'labels'       => $chart_labels,
        'omzet'        => $chart_omzet,
        'omzet_pasien' => $chart_omzet_pasien,
        'omzet_bebas'  => $chart_omzet_bebas,
        'omzet_retur'  => $chart_omzet_retur,
        'modal'        => $chart_modal,
        'profit'       => $chart_profit
    ]
]);

$koneksi->close();
?>