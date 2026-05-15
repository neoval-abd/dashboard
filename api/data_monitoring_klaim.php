<?php
/*
 * File: api/data_monitoring_klaim.php
 * Fungsi: Mengambil daftar pasien dan status SEP mereka untuk Monitoring Klaim
 * Author: Dashboard System
 * Date: 2025-05-08
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php');

// Parameter Filter
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');  // Awal bulan
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$status_sep = isset($_GET['status_sep']) ? $_GET['status_sep'] : '';  // 'ada', 'tidak_ada', '' = semua
$status_lanjut = isset($_GET['status_lanjut']) ? $_GET['status_lanjut'] : '';
$jam_awal = isset($_GET['jam_awal']) ? $_GET['jam_awal'] : '00:00:00';
$jam_akhir = isset($_GET['jam_akhir']) ? $_GET['jam_akhir'] : '23:59:59';

$datetime_awal = $tgl_awal . ' ' . $jam_awal;
$datetime_akhir = $tgl_akhir . ' ' . $jam_akhir;

// Build Query
$sql = "
    SELECT 
        rp.no_rawat,
        rp.no_rkm_medis,
        p.nm_pasien,
        rp.status_lanjut,
        rp.tgl_registrasi,
        rp.jam_reg,
        pj.png_jawab,
        CASE 
            WHEN bs.no_sep IS NOT NULL AND bs.no_sep != '' THEN bs.no_sep
            ELSE 'SEP Tidak ditemukan'
        END AS no_sep,
        CASE 
            WHEN bs.no_sep IS NOT NULL AND bs.no_sep != '' THEN 'Ada'
            ELSE 'Tidak Ada'
        END AS status_sep,
        IFNULL(bs.no_rujukan, '-') AS no_rujukan,
        IFNULL(bs.nmppkrujukan, '-') AS nmppkrujukan,
        IFNULL(ki.tgl_masuk, '-') AS tgl_masuk,
        IFNULL(ki.tgl_keluar, '-') AS tgl_keluar,
        COALESCE(dok.nm_dokter, regdok.nm_dokter, '-') AS dpjp_ranap
    FROM reg_periksa rp
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    LEFT JOIN bridging_sep bs ON rp.no_rawat = bs.no_rawat
    LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
    LEFT JOIN dpjp_ranap drp ON rp.no_rawat = drp.no_rawat
    LEFT JOIN dokter dok ON drp.kd_dokter = dok.kd_dokter
    LEFT JOIN dokter regdok ON rp.kd_dokter = regdok.kd_dokter
    WHERE CONCAT(rp.tgl_registrasi, ' ', rp.jam_reg) BETWEEN ? AND ?
    AND rp.stts != 'Batal'
";

$params = [$datetime_awal, $datetime_akhir];
$types = "ss";

// Filter hanya pasien BPJS
$sql .= " AND LOWER(pj.png_jawab) LIKE '%bpjs%' ";

// Filter by Status SEP
if ($status_sep == 'ada') {
    $sql .= " AND bs.no_sep IS NOT NULL AND bs.no_sep != '' ";
} elseif ($status_sep == 'tidak_ada') {
    $sql .= " AND (bs.no_sep IS NULL OR bs.no_sep = '') ";
}

// Filter by rawat jalan / rawat inap
if ($status_lanjut === 'Ralan' || $status_lanjut === 'Ranap') {
    $sql .= " AND rp.status_lanjut = ? ";
    $params[] = $status_lanjut;
    $types .= "s";
}

$sql .= " ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC ";

// Execute Query
$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare statement gagal: ' . $koneksi->error]);
    exit;
}

// Bind Parameters
if (count($params) > 0) {
    $bind_params = array_merge([$types], $params);
    call_user_func_array([$stmt, 'bind_param'], array_map(function(&$v) { return $v; }, $bind_params));
}

$stmt->execute();
$result = $stmt->get_result();

$data = [];
$total_pasien = 0;
$total_ada_sep = 0;
$total_tidak_ada_sep = 0;

while ($row = $result->fetch_assoc()) {
    $total_pasien++;
    if ($row['status_sep'] == 'Ada') {
        $total_ada_sep++;
    } else {
        $total_tidak_ada_sep++;
    }
    
    $data[] = $row;
}

$stmt->close();

// Response
echo json_encode([
    'data' => $data,
    'summary' => [
        'total_pasien' => $total_pasien,
        'total_ada_sep' => $total_ada_sep,
        'total_tidak_ada_sep' => $total_tidak_ada_sep,
        'persentase_ada_sep' => ($total_pasien > 0) ? round(($total_ada_sep / $total_pasien) * 100, 2) : 0
    ]
]);
?>
