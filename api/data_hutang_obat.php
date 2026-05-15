<?php
require_once('../config/koneksi.php');

header('Content-Type: application/json');

$response = [
    'summary' => [
        'total_sisa_hutang' => 0,
        'total_faktur' => 0,
        'menunggu_tempo' => 0,
        'lewat_tempo' => 0
    ],
    'chart' => [
        'suplier' => [],
        'tempo_bulan' => ['labels' => [], 'data' => []]
    ],
    'data' => []
];

// Query Data Mentah Faktur Belum Lunas
$sql = "SELECT 
    p.no_faktur,
    p.no_order,
    s.nama_suplier, 
    pt.nama AS nama_petugas,
    p.tgl_tempo,
    p.tgl_pesan,
    p.tgl_faktur,
    b.nm_bangsal,
    p.tagihan,
    COALESCE(
        (SELECT SUM(besar_bayar) 
         FROM bayar_pemesanan 
         WHERE bayar_pemesanan.no_faktur = p.no_faktur
        ), 0
    ) AS cicilan_dibayar,
    s.nama_bank,
    s.rekening 
FROM pemesanan p
INNER JOIN datasuplier s ON p.kode_suplier = s.kode_suplier 
INNER JOIN bangsal b ON p.kd_bangsal = b.kd_bangsal 
INNER JOIN petugas pt ON p.nip = pt.nip 
WHERE 
    (p.status = 'Belum Dibayar' OR p.status = 'Belum Lunas')
ORDER BY p.tgl_tempo ASC";

$result = $koneksi->query($sql);

if ($result && $result->num_rows > 0) {
    // Current date to determine overdue
    $current_date = date('Y-m-d');
    
    $suplier_data = [];
    $bulan_data = [];

    while ($row = $result->fetch_assoc()) {
        $tagihan = floatval($row['tagihan']);
        $cicilan = floatval($row['cicilan_dibayar']);
        $sisa_hutang = $tagihan - $cicilan;
        
        $row['tagihan_val'] = $tagihan;
        $row['cicilan_val'] = $cicilan;
        $row['sisa_hutang_val'] = $sisa_hutang;

        // KPI calculations
        $response['summary']['total_sisa_hutang'] += $sisa_hutang;
        $response['summary']['total_faktur']++;

        if ($row['tgl_tempo'] < $current_date) {
            $response['summary']['lewat_tempo'] += $sisa_hutang;
        } else {
            $response['summary']['menunggu_tempo'] += $sisa_hutang;
        }

        // Aggregate for Chart (Proporsi Suplier)
        if (!isset($suplier_data[$row['nama_suplier']])) {
            $suplier_data[$row['nama_suplier']] = 0;
        }
        $suplier_data[$row['nama_suplier']] += $sisa_hutang;

        // Aggregate for Chart (Bulan Jatuh Tempo)
        $bulan_key = date('Y-m', strtotime($row['tgl_tempo']));
        if (!isset($bulan_data[$bulan_key])) {
            $bulan_data[$bulan_key] = 0;
        }
        $bulan_data[$bulan_key] += $sisa_hutang;

        $response['data'][] = $row;
    }

    // Format chart data output for Chart.js Pie Chart
    foreach ($suplier_data as $suplier => $total) {
        $response['chart']['suplier'][] = [
            'name' => $suplier,
            'value' => $total
        ];
    }

    // Sort bulan keys alphabetically and build chart data for Chart.js Bar Chart
    ksort($bulan_data);
    
    $bulan_indonesia = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
        '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Ags',
        '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
    ];
    
    foreach ($bulan_data as $bulan => $total) {
        $parts = explode('-', $bulan);
        $bln_str = isset($bulan_indonesia[$parts[1]]) ? $bulan_indonesia[$parts[1]] : $parts[1];
        $label = $bln_str . ' ' . $parts[0];
        
        $response['chart']['tempo_bulan']['labels'][] = $label;
        $response['chart']['tempo_bulan']['data'][] = $total;
    }
}

echo json_encode($response);
?>
