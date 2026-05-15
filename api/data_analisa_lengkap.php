<?php
/*
 * File: api/data_analisa_lengkap.php (REVISI V3 - FULL ORIGINAL QUERY)
 * Fungsi: Analisa Data Lengkap dengan semua kolom detail (SEP, Wilayah, Perusahaan, dll).
 * Logic: Sesuai Query SQL Original User.
 */

ini_set('display_errors', 0);
ini_set('memory_limit', '-1'); 
set_time_limit(300);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

// 1. AMBIL PARAMETER FILTER
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_pj = isset($_GET['kd_pj']) ? $_GET['kd_pj'] : ''; 
$status_bayar = isset($_GET['status_bayar']) ? $_GET['status_bayar'] : ''; // Sudah Bayar / Belum Bayar
$status_lanjut = isset($_GET['status_lanjut']) ? $_GET['status_lanjut'] : ''; // Ralan / Ranap

// 2. KONSTRUKSI WHERE CLAUSE (Untuk reg_periksa)
$where_clauses = [];

// Default: Status Bayar 'Sudah Bayar' jika tidak dipilih (Sesuai query original),
// tapi jika user memilih 'Belum Bayar' di filter, kita ikuti filter user.
if (!empty($status_bayar)) {
    $where_clauses[] = "reg_periksa.status_bayar = '$status_bayar'";
} else {
    // Default behavior jika filter kosong: Tampilkan yg sudah bayar (sesuai query original)
    // Atau hilangkan baris ini jika ingin menampilkan semua by default.
    // $where_clauses[] = "reg_periksa.status_bayar = 'Sudah Bayar'"; 
}

if (!empty($status_lanjut)) {
    $where_clauses[] = "reg_periksa.status_lanjut = '$status_lanjut'";
}
if (!empty($kd_pj)) {
    $where_clauses[] = "reg_periksa.kd_pj = '$kd_pj'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = " AND " . implode(" AND ", $where_clauses);
}

// 3. QUERY RAKSASA (FULL COLUMN ORIGINAL)
$sql = "
        SELECT
        reg_periksa.tgl_registrasi,
        b.tgl_byr,
        reg_periksa.no_rawat,
        reg_periksa.stts_daftar AS Pasien_Baru_Lama,
        reg_periksa.status_lanjut,
        reg_periksa.status_bayar,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        pasien.no_tlp,
        diag.kd_penyakit,
        penyakit.nm_penyakit,
        penjab.png_jawab,
        poliklinik.nm_poli,

        -- DPJP
        GROUP_CONCAT(DISTINCT d_dpjp.nm_dokter SEPARATOR ', ') AS nm_dokter,

        -- dokter IGD
        dokter.nm_dokter AS dokter_perujuk,

        sep.no_sep,
        sep.no_rujukan,
        sep.nmppkrujukan,
        perusahaan_pasien.nama_perusahaan,
        kabupaten.nm_kab,
        kecamatan.nm_kec,
        kelurahan.nm_kel,
        b.TotalBiaya

        FROM reg_periksa

        INNER JOIN (
            SELECT 
                no_rawat, 
                SUM(totalbiaya) AS TotalBiaya,
                MAX(tgl_byr) AS tgl_byr
            FROM billing
            WHERE tgl_byr BETWEEN ? AND ?
            GROUP BY no_rawat
        ) AS b ON reg_periksa.no_rawat = b.no_rawat

        LEFT JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        LEFT JOIN kabupaten ON pasien.kd_kab = kabupaten.kd_kab
        LEFT JOIN kecamatan ON pasien.kd_kec = kecamatan.kd_kec
        LEFT JOIN kelurahan ON pasien.kd_kel = kelurahan.kd_kel
        LEFT JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        LEFT JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli

        -- dokter IGD
        LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter

        -- DPJP
        LEFT JOIN dpjp_ranap ON reg_periksa.no_rawat = dpjp_ranap.no_rawat
        LEFT JOIN dokter d_dpjp ON dpjp_ranap.kd_dokter = d_dpjp.kd_dokter

        -- diagnosa
        LEFT JOIN (
            SELECT no_rawat, MAX(kd_penyakit) AS kd_penyakit 
            FROM diagnosa_pasien 
            GROUP BY no_rawat
        ) AS diag ON reg_periksa.no_rawat = diag.no_rawat

        LEFT JOIN penyakit ON diag.kd_penyakit = penyakit.kd_penyakit

        -- SEP
        LEFT JOIN (
            SELECT no_rawat, no_sep, no_rujukan, nmppkrujukan 
            FROM bridging_sep 
            GROUP BY no_rawat
        ) AS sep ON reg_periksa.no_rawat = sep.no_rawat

        LEFT JOIN perusahaan_pasien 
            ON pasien.perusahaan_pasien = perusahaan_pasien.kode_perusahaan

        WHERE 1=1 
        $where_sql

        GROUP BY reg_periksa.no_rawat

        ORDER BY b.tgl_byr DESC, reg_periksa.tgl_registrasi DESC
";

$stmt = $koneksi->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data_detail = [];
    $summary = [
        'total_pendapatan' => 0,
        'total_kunjungan' => 0,
        'total_ralan' => 0,
        'total_ranap' => 0
    ];

    while ($row = $result->fetch_assoc()) {
        // Hitung Summary
        $summary['total_pendapatan'] += (float)$row['TotalBiaya'];
        $summary['total_kunjungan']++;
        if ($row['status_lanjut'] == 'Ralan') $summary['total_ralan']++;
        else $summary['total_ranap']++;

        $data_detail[] = $row;
    }
    
    echo json_encode([
        'summary' => $summary,
        'data' => $data_detail
    ]);
    
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => $koneksi->error]);
}

$koneksi->close();
?>