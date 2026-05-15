<?php
/*
 * File api/data_billing_global_chart.php (PERBAIKAN V6 - STRICT FILTER)
 * - FIX INFLASI DATA BPJS: Menambahkan filter ketat untuk mengabaikan baris 'Subtotal' dan 'Grand Total'.
 * - LOGIKA: Hanya menjumlahkan komponen detail (Obat, Tindakan, dll) + Retur/Potongan.
 * - Mencegah double counting antara rincian dan rekapitulasi.
 */

// Matikan error display agar JSON valid
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
$status_bayar = isset($_GET['status_bayar']) ? $_GET['status_bayar'] : ''; 

$datetime_awal = $tgl_awal . ' ' . $jam_awal;
$datetime_akhir = $tgl_akhir . ' ' . $jam_akhir;

// 2. Siapkan Parameter
$params = [];
$types = "";

// Parameter Wajib (Tanggal Ralan + Tanggal Ranap)
$params[] = $datetime_awal; $params[] = $datetime_akhir;
$params[] = $datetime_awal; $params[] = $datetime_akhir;
$types = "ssss";

// Filter Tambahan
$sql_filter_pj = "";
if (!empty($kd_pj)) {
    $sql_filter_pj = " AND reg_periksa.kd_pj = ? ";
    $params[] = $kd_pj; $params[] = $kd_pj;
    $types .= "ss";
}

// 3. Kueri Agregasi dengan Filter Anti-Inflasi
// Kita hanya mengambil baris yang BUKAN 'Tagihan' dan BUKAN 'Ttl...' (kecuali TtlRetur/TtlPotongan).
$sql_union = "
    SELECT 
        tgl_transaksi, 
        png_jawab, 
        status_bayar_calc,
        SUM(total_bersih) as omzet
    FROM (
        -- BAGIAN 1: RALAN
        SELECT 
            DATE(nota_jalan.tanggal) as tgl_transaksi,
            penjab.png_jawab,
            IF(piutang_pasien.no_rawat IS NULL, 'Tunai', 'Piutang') as status_bayar_calc,
            (CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END) as total_bersih
        FROM reg_periksa
        INNER JOIN nota_jalan ON reg_periksa.no_rawat = nota_jalan.no_rawat
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        INNER JOIN billing ON reg_periksa.no_rawat = billing.no_rawat
        LEFT JOIN piutang_pasien ON reg_periksa.no_rawat = piutang_pasien.no_rawat
        WHERE 
            CONCAT(nota_jalan.tanggal, ' ', nota_jalan.jam) BETWEEN ? AND ?
            AND (
                billing.status = 'TtlRetur Obat' 
                OR billing.status = 'TtlPotongan' 
                OR (billing.status != 'Tagihan' AND billing.status NOT LIKE 'Ttl%')
            )
            $sql_filter_pj

        UNION ALL

        -- BAGIAN 2: RANAP
        SELECT 
            DATE(nota_inap.tanggal) as tgl_transaksi,
            penjab.png_jawab,
            IF(piutang_pasien.no_rawat IS NULL, 'Tunai', 'Piutang') as status_bayar_calc,
            (CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END) as total_bersih
        FROM reg_periksa
        INNER JOIN nota_inap ON reg_periksa.no_rawat = nota_inap.no_rawat
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        INNER JOIN billing ON reg_periksa.no_rawat = billing.no_rawat
        LEFT JOIN piutang_pasien ON reg_periksa.no_rawat = piutang_pasien.no_rawat
        WHERE 
            CONCAT(nota_inap.tanggal, ' ', nota_inap.jam) BETWEEN ? AND ?
            AND (
                billing.status = 'TtlRetur Obat' 
                OR billing.status = 'TtlPotongan' 
                OR (billing.status != 'Tagihan' AND billing.status NOT LIKE 'Ttl%')
            )
            $sql_filter_pj
    ) AS gabungan
    WHERE 1=1
";

if (!empty($status_bayar)) {
    $sql_union .= " AND status_bayar_calc = ? ";
    $params[] = $status_bayar;
    $types .= "s";
}

$sql_union .= " GROUP BY tgl_transaksi, png_jawab ORDER BY tgl_transaksi ASC";

// 4. Eksekusi
$stmt = $koneksi->prepare($sql_union);

if ($stmt) {
    $bind_names[] = $types;
    for ($i=0; $i<count($params);$i++) { 
        $bind_names[] = &$params[$i]; 
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 5. Struktur Data
    $pie_data_raw = []; 
    $line_data_raw = []; 
    $list_penjab = []; 
    $list_tanggal = []; 

    while ($row = $result->fetch_assoc()) {
        $penjab = $row['png_jawab'];
        $tanggal = date('d-m-Y', strtotime($row['tgl_transaksi']));
        $total = (float)$row['omzet']; 

        if (!isset($pie_data_raw[$penjab])) $pie_data_raw[$penjab] = 0;
        $pie_data_raw[$penjab] += $total;

        if (!isset($line_data_raw[$tanggal][$penjab])) $line_data_raw[$tanggal][$penjab] = 0;
        $line_data_raw[$tanggal][$penjab] += $total;

        if (!in_array($penjab, $list_penjab)) $list_penjab[] = $penjab;
        if (!in_array($tanggal, $list_tanggal)) $list_tanggal[] = $tanggal;
    }
    $stmt->close();
    $koneksi->close();

    // 6. Format JSON
    $pie_response = [
        'labels' => array_keys($pie_data_raw),
        'data' => array_values($pie_data_raw)
    ];

    $list_tanggal = array_values(array_unique($list_tanggal));

    $line_datasets = [];
    $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf'];
    $c_idx = 0;

    foreach ($list_penjab as $pj) {
        $data_points = [];
        foreach ($list_tanggal as $tgl) {
            $val = isset($line_data_raw[$tgl][$pj]) ? $line_data_raw[$tgl][$pj] : 0;
            $data_points[] = $val;
        }
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