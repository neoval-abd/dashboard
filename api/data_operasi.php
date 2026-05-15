<?php
/*
 * File: api/data_operasi.php (UPDATE V3 - FIX COLUMN)
 * - Fix Error: Menghapus kolom 'jam_operasi_selesai' dan 'status' yang tidak ada di tabel operasi.
 * - Output: Data operasi yang sudah terealisasi (Selesai).
 */

// Konfigurasi Performa
ini_set('display_errors', 0);
ini_set('memory_limit', '-1'); 
set_time_limit(300); 
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// --- FIX LOGIKA TANGGAL ---
// Tambahkan jam secara eksplisit agar mencakup seluruh detik di tanggal tersebut
$tgl_awal_sql = $tgl_awal . " 00:00:00";
$tgl_akhir_sql = $tgl_akhir . " 23:59:59";

// Hitung Total Biaya (Semua Komponen Jasa & Sarpras)
$biaya_total = "(t.biayaoperator1 + t.biayaoperator2 + t.biayaoperator3 + t.biayaasisten_operator1 + t.biayaasisten_operator2 + t.biayainstrumen + t.biayadokter_anak + t.biayaperawaat_resusitas + t.biayadokter_anestesi + t.biayaasisten_anestesi + t.biayabidan + t.biayaperawat_luar + t.biayaalat + t.biayasewaok + t.akomodasi + t.bagian_rs + t.biaya_omloop + t.biaya_omloop2 + t.biayasarpras + t.biaya_dokter_pjanak + t.biaya_dokter_umum)";

// Query Utama (Kolom disesuaikan dengan schema real Khanza)
$sql = "
    SELECT 
        t.no_rawat, 
        t.tgl_operasi, 
        p.no_rkm_medis, 
        p.nm_pasien,
        pkt.nm_perawatan,
        d.nm_dokter as operator,
        pj.png_jawab,
        $biaya_total as total_biaya
    FROM operasi t
    INNER JOIN reg_periksa rp ON t.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    INNER JOIN paket_operasi pkt ON t.kode_paket = pkt.kode_paket
    LEFT JOIN dokter d ON t.operator1 = d.kd_dokter
    WHERE t.tgl_operasi BETWEEN ? AND ?
    ORDER BY t.tgl_operasi DESC
    LIMIT 3000 
";

$stmt = $koneksi->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ss", $tgl_awal_sql, $tgl_akhir_sql);
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    $summary = ['total_omzet' => 0, 'total_pasien' => 0, 'bpjs' => 0, 'umum' => 0];
    $chart_top = [];

    while($row = $res->fetch_assoc()) {
        $data[] = $row;
        
        // Agregasi KPI
        $summary['total_omzet'] += (float)$row['total_biaya'];
        $summary['total_pasien']++;
        
        // Deteksi Penjamin Sederhana
        if (stripos($row['png_jawab'], 'BPJS') !== false) {
            $summary['bpjs']++;
        } else {
            $summary['umum']++;
        }

        // Agregasi Chart Top Operasi
        $nm_op = $row['nm_perawatan'];
        if(!isset($chart_top[$nm_op])) $chart_top[$nm_op] = 0;
        $chart_top[$nm_op]++;
    }

    // Format Chart (Top 10)
    arsort($chart_top);
    $top_10 = array_slice($chart_top, 0, 10);

    echo json_encode([
        'summary' => $summary,
        'data' => $data,
        'chart_labels' => array_keys($top_10),
        'chart_values' => array_values($top_10)
    ]);

    $stmt->close();
} else {
    // Kirim error JSON valid jika query gagal agar frontend tidak hang
    http_response_code(500);
    echo json_encode(['error' => 'Query Error: ' . $koneksi->error]);
}

$koneksi->close();
?>