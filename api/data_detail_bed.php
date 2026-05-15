<?php
/*
 * File: api/data_detail_bed.php (FIX V2 - PHP FILTERING)
 * Fungsi: Menampilkan pasien aktif per kelas.
 * Perbaikan: Memindahkan logika filter kelas ke PHP untuk akurasi & kompatibilitas.
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

$req_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// Ambil SEMUA pasien aktif
$sql = "
    SELECT 
        ki.no_rawat, ki.tgl_masuk, ki.jam_masuk, 
        p.nm_pasien, p.no_rkm_medis, pj.png_jawab,
        k.kd_kamar, b.nm_bangsal, k.kelas,
        DATEDIFF(NOW(), ki.tgl_masuk) as lama_hari
    FROM kamar_inap ki
    INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
    INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
    WHERE (ki.stts_pulang = '-' OR ki.stts_pulang = '')
    ORDER BY ki.tgl_masuk DESC
";

$stmt = $koneksi->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();

$filtered_data = [];

while($row = $res->fetch_assoc()) {
    // Format Waktu
    $row['waktu_masuk'] = $row['tgl_masuk'] . ' ' . $row['jam_masuk'];
    if($row['lama_hari'] == 0) $row['lama_hari'] = 1;

    // Logika Penentuan Kelas (Harus sama persis dengan api/data_dashboard.php)
    $nm_bangsal = strtoupper($row['nm_bangsal']);
    $kelas_real = $row['kelas'];

    if (strpos($nm_bangsal, 'ISOLASI') !== false || strpos($nm_bangsal, 'ICU') !== false || 
        strpos($nm_bangsal, 'NICU') !== false || strpos($nm_bangsal, 'PICU') !== false || 
        strpos($nm_bangsal, 'HCU') !== false || strpos($nm_bangsal, 'PERINA') !== false) {
        $kelas_real = 'Kelas Khusus';
    }

    // Filter Sesuai Request
    if ($req_kelas == $kelas_real) {
        $filtered_data[] = $row;
    }
}

echo json_encode(['data' => $filtered_data]);
?>