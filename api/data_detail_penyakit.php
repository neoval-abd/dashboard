<?php
/*
 * File: api/data_detail_penyakit.php (UPDATE V2)
 * Fungsi: Drill-down detail pasien berdasarkan kode penyakit.
 * Perubahan: Memastikan kolom no_rawat terekspos dengan jelas.
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
$kd_penyakit = isset($_GET['kd_penyakit']) ? $_GET['kd_penyakit'] : '';
$status_lanjut = isset($_GET['status_lanjut']) ? $_GET['status_lanjut'] : '';

if(empty($kd_penyakit)) {
    echo json_encode(['data' => []]);
    exit;
}

// Bangun Query Filter
$where_status = "";
$params = [$tgl_awal, $tgl_akhir];
$types = "ss";

if (!empty($status_lanjut)) {
    $where_status = " AND reg_periksa.status_lanjut = ? ";
    $params[] = $status_lanjut;
    $types .= "s";
}

// Parameter wajib terakhir (Kode Penyakit)
$params[] = $kd_penyakit;
$types .= "s";

// QUERY KOMPLEKS
$sql = "
    SELECT 
        reg_periksa.no_rawat,      -- PENTING: Primary Key Transaksi
        reg_periksa.tgl_registrasi,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        pasien.jk,
        pasien.umur,
        dokter.nm_dokter,
        poliklinik.nm_poli,
        penjab.png_jawab,
        CONCAT(kelurahan.nm_kel, ', ', kecamatan.nm_kec, ', ', kabupaten.nm_kab) as alamat_lengkap
    FROM penyakit
    INNER JOIN diagnosa_pasien ON penyakit.kd_penyakit = diagnosa_pasien.kd_penyakit
    INNER JOIN reg_periksa ON reg_periksa.no_rawat = diagnosa_pasien.no_rawat
    INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    INNER JOIN kabupaten ON pasien.kd_kab = kabupaten.kd_kab
    INNER JOIN kecamatan ON pasien.kd_kec = kecamatan.kd_kec
    INNER JOIN kelurahan ON pasien.kd_kel = kelurahan.kd_kel
    WHERE 
        diagnosa_pasien.prioritas = '1'
        AND reg_periksa.tgl_registrasi BETWEEN ? AND ?
        $where_status
        AND diagnosa_pasien.kd_penyakit = ?
    GROUP BY diagnosa_pasien.no_rawat
    ORDER BY reg_periksa.tgl_registrasi DESC
";

$stmt = $koneksi->prepare($sql);

if ($stmt) {
    // Bind Param
    $bind_names[] = $types;
    for ($i=0; $i<count($params);$i++) { $bind_names[] = &$params[$i]; }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);

    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['data' => $data]);
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => $koneksi->error]);
}
?>