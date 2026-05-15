<?php
/*
 * File: api/data_pasien_ranap_aktif.php
 * Fungsi: Mengambil daftar pasien yang sedang dirawat (Belum Pulang).
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

// Ambil pasien yang stts_pulang = '-' atau kosong
$sql = "
    SELECT 
        ki.no_rawat, 
        ki.tgl_masuk, 
        ki.jam_masuk, 
        p.nm_pasien, 
        p.no_rkm_medis,
        pj.png_jawab,
        k.kd_kamar, 
        b.nm_bangsal,
        k.kelas,
        DATEDIFF(NOW(), ki.tgl_masuk) as lama_inap
    FROM kamar_inap ki
    INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
    INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
    WHERE ki.stts_pulang = '-' OR ki.stts_pulang = ''
    ORDER BY ki.tgl_masuk DESC
";

$stmt = $koneksi->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while($row = $res->fetch_assoc()) {
    $row['waktu_masuk'] = $row['tgl_masuk'] . ' ' . $row['jam_masuk'];
    // Koreksi lama inap jika 0 hari
    if($row['lama_inap'] == 0) $row['lama_inap'] = 1;
    $data[] = $row;
}

echo json_encode(['data' => $data]);
?>