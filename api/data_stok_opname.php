<?php
/*
 * File: api/data_stok_opname.php
 * Fungsi: Menyediakan data hasil stok opname (Summary, Chart, & Detail).
 * Logika: Mengambil data matang dari tabel 'opname' SIMKES Khanza.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

// 1. Ambil Parameter
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_bangsal = isset($_GET['kd_bangsal']) ? $_GET['kd_bangsal'] : '';

// 2. Siapkan Filter Query
$where = " WHERE opname.tanggal BETWEEN ? AND ? ";
$params = [$tgl_awal, $tgl_akhir];
$types = "ss";

if (!empty($kd_bangsal)) {
    $where .= " AND opname.kd_bangsal = ? ";
    $params[] = $kd_bangsal;
    $types .= "s";
}

// -----------------------------------------------------------
// A. QUERY DATA DETAIL & SUMMARY
// -----------------------------------------------------------
$sql = "
    SELECT 
        opname.tanggal,
        opname.kode_brng,
        databarang.nama_brng,
        bangsal.nm_bangsal,
        opname.h_beli,
        opname.stok as stok_sistem,
        opname.real as stok_real,
        opname.selisih,
        opname.nomihilang, -- Nilai Kerugian (Rupiah)
        opname.lebih,
        opname.nomilebih,  -- Nilai Surplus (Rupiah)
        opname.keterangan,
        opname.no_batch,
        opname.no_faktur
    FROM opname
    INNER JOIN databarang ON opname.kode_brng = databarang.kode_brng
    INNER JOIN bangsal ON opname.kd_bangsal = bangsal.kd_bangsal
    $where
    ORDER BY opname.tanggal DESC, opname.nomihilang DESC
";

$stmt = $koneksi->prepare($sql);
if ($stmt) {
    $bind_names[] = $types;
    for ($i=0; $i<count($params);$i++) { $bind_names[] = &$params[$i]; }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data_detail = [];
    $summary = [
        'total_item_selisih' => 0,
        'total_nilai_hilang' => 0,
        'total_nilai_lebih' => 0,
        'net_selisih' => 0
    ];

    // Variabel untuk Chart Top 10 Rugi
    $top_rugi = []; 

    // Variabel untuk Chart Tren Harian
    $tren_harian = [];

    while ($row = $result->fetch_assoc()) {
        
        // --- [BUGFIX KHANZA] ---
        // Seringkali aplikasi Desktop Khanza tidak menyimpan "nomilebih" dengan benar saat terjadi surplus,
        // sehingga kita bantu hitung secara realtime di sisi Server API Dashboard
        $sys  = (float)$row['stok_sistem'];
        $real = (float)$row['stok_real'];
        $hbeli= (float)$row['h_beli'];
        $diff = $real - $sys;

        // Force perhitungan nilai finansial
        if ($diff < 0) {
            $row['nomihilang'] = abs($diff) * $hbeli;
            $row['nomilebih'] = 0;
        } elseif ($diff > 0) {
            $row['nomihilang'] = 0;
            $row['nomilebih'] = $diff * $hbeli;
        } else {
            $row['nomihilang'] = 0;
            $row['nomilebih'] = 0;
        }
        // ------------------------

        // Format Data Tabel
        $data_detail[] = $row;

        // Hitung Summary
        // Kita hitung item yang benar-benar selisih (diff tidak 0)
        if ($diff != 0) {
            $summary['total_item_selisih']++;
        }
        $summary['total_nilai_hilang'] += (float)$row['nomihilang'];
        $summary['total_nilai_lebih'] += (float)$row['nomilebih'];

        // Siapkan Data Top 10 Rugi (Hanya ambil yang nomihilang > 0)
        if ((float)$row['nomihilang'] > 0) {
            $top_rugi[] = [
                'nama' => $row['nama_brng'],
                'nilai' => (float)$row['nomihilang']
            ];
        }

        // Siapkan Data Tren Harian
        $tgl = date('d-m-Y', strtotime($row['tanggal']));
        if (!isset($tren_harian[$tgl])) {
            $tren_harian[$tgl] = ['hilang' => 0, 'lebih' => 0];
        }
        $tren_harian[$tgl]['hilang'] += (float)$row['nomihilang'];
        $tren_harian[$tgl]['lebih'] += (float)$row['nomilebih'];
    }
    
    $summary['net_selisih'] = $summary['total_nilai_lebih'] - $summary['total_nilai_hilang'];

    // Sort Top 10 Rugi (Descending) & Ambil 10 Teratas
    usort($top_rugi, function($a, $b) { return $b['nilai'] - $a['nilai']; });
    $chart_top_rugi = array_slice($top_rugi, 0, 10);

    echo json_encode([
        'summary' => $summary,
        'detail' => $data_detail,
        'chart_top' => $chart_top_rugi,
        'chart_tren' => $tren_harian
    ]);
    
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => $koneksi->error]);
}
?>