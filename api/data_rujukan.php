<?php
/*
 * File: api/data_rujukan.php
 * Fungsi: Mengambil data Rujukan Masuk (rujuk_masuk) dan Rujuk Keluar (rujuk)
 *         untuk laporan RL 3.13
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

$tgl_awal  = isset($_GET['tgl_awal'])  ? $koneksi->real_escape_string($_GET['tgl_awal'])  : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $koneksi->real_escape_string($_GET['tgl_akhir']) : date('Y-m-d');

// ─── Rujukan Masuk ──────────────────────────────────────────
$sql_masuk = "
    SELECT 
        rm.no_rawat,
        rm.perujuk,
        rm.alamat,
        rm.no_rujuk,
        rm.jm_perujuk,
        rm.dokter_perujuk,
        rm.kategori_rujuk,
        rm.keterangan,
        rm.no_balasan,
        p.no_rkm_medis,
        p.nm_pasien,
        p.alamat AS alamat_pasien,
        p.jk,
        TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURDATE()) AS umur,
        rp.tgl_registrasi AS tgl_masuk,
        pol.nm_poli,
        CONCAT(icd.kd_penyakit, ' - ', icd.nm_penyakit) AS kd_penyakit_desc
    FROM rujuk_masuk rm
    LEFT JOIN reg_periksa rp ON rm.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN penyakit icd ON rm.kd_penyakit = icd.kd_penyakit
    LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
    WHERE rp.tgl_registrasi BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ORDER BY rp.tgl_registrasi DESC, rm.no_rawat ASC";

$masuk = [];
$res_masuk = $koneksi->query($sql_masuk);
if ($res_masuk) {
    while ($row = $res_masuk->fetch_assoc()) {
        $masuk[] = [
            'no_rawat'        => $row['no_rawat'],
            'perujuk'         => $row['perujuk'] ?: '-',
            'alamat'          => $row['alamat'] ?: '-',
            'no_rujuk'        => $row['no_rujuk'] ?: '-',
            'no_rkm_medis'    => $row['no_rkm_medis'] ?: '-',
            'nm_pasien'       => $row['nm_pasien'] ?: '-',
            'umur'            => $row['umur'] !== null ? $row['umur'] . ' th' : '-',
            'jk'              => $row['jk'] ?: '-',
            'tgl_masuk'       => $row['tgl_masuk'] ?: '-',
            'jm_perujuk'      => (float) ($row['jm_perujuk'] ?? 0),
            'dokter_perujuk'  => $row['dokter_perujuk'] ?: '-',
            'kd_penyakit_desc'=> $row['kd_penyakit_desc'] ?: '-',
            'kategori_rujuk'  => $row['kategori_rujuk'] ?: '-',
            'nm_poli'         => $row['nm_poli'] ?: '-',
            'keterangan'      => $row['keterangan'] ?: '-',
            'no_balasan'      => $row['no_balasan'] ?: '-'
        ];
    }
}

// ─── Rujuk Keluar ───────────────────────────────────────────
$sql_keluar = "
    SELECT 
        r.no_rujuk,
        r.no_rawat,
        r.rujuk_ke,
        r.tgl_rujuk,
        r.jam,
        r.keterangan_diagnosa,
        r.kat_rujuk,
        r.ambulance,
        r.keterangan,
        p.no_rkm_medis,
        p.nm_pasien,
        p.jk,
        TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURDATE()) AS umur,
        d.nm_dokter
    FROM rujuk r
    LEFT JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
    WHERE r.tgl_rujuk BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ORDER BY r.tgl_rujuk DESC, r.no_rujuk ASC";

$keluar = [];
$res_keluar = $koneksi->query($sql_keluar);
if ($res_keluar) {
    while ($row = $res_keluar->fetch_assoc()) {
        $keluar[] = [
            'no_rujuk'              => $row['no_rujuk'],
            'no_rawat'              => $row['no_rawat'],
            'no_rkm_medis'          => $row['no_rkm_medis'] ?: '-',
            'nm_pasien'             => $row['nm_pasien'] ?: '-',
            'umur'                  => $row['umur'] !== null ? $row['umur'] . ' th' : '-',
            'jk'                    => $row['jk'] ?: '-',
            'rujuk_ke'              => $row['rujuk_ke'] ?: '-',
            'tgl_rujuk'             => $row['tgl_rujuk'] ?: '-',
            'jam'                   => $row['jam'] ?: '-',
            'keterangan_diagnosa'   => $row['keterangan_diagnosa'] ?: '-',
            'nm_dokter'             => $row['nm_dokter'] ?: '-',
            'kat_rujuk'             => $row['kat_rujuk'] ?: '-',
            'ambulance'             => $row['ambulance'] ?: '-',
            'keterangan'            => $row['keterangan'] ?: '-'
        ];
    }
}

// ─── Rujuk Masuk Poli (Internal) ──────────────────────────────
$sql_poli = "
    SELECT 
        rip.no_rawat,
        rip.kd_dokter,
        rip.kd_poli,
        p.no_rkm_medis,
        p.nm_pasien,
        p.jk,
        TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURDATE()) AS umur,
        rp.tgl_registrasi,
        d.nm_dokter,
        pol.nm_poli
    FROM rujukan_internal_poli rip
    LEFT JOIN reg_periksa rp ON rip.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON rip.kd_dokter = d.kd_dokter
    LEFT JOIN poliklinik pol ON rip.kd_poli = pol.kd_poli
    WHERE rp.tgl_registrasi BETWEEN '$tgl_awal' AND '$tgl_akhir'
    ORDER BY rp.tgl_registrasi DESC, rip.no_rawat ASC";

$internal = [];
$res_poli = $koneksi->query($sql_poli);
if ($res_poli) {
    while ($row = $res_poli->fetch_assoc()) {
        $internal[] = [
            'no_rawat'       => $row['no_rawat'],
            'no_rkm_medis'   => $row['no_rkm_medis'] ?: '-',
            'nm_pasien'      => $row['nm_pasien'] ?: '-',
            'umur'           => $row['umur'] !== null ? $row['umur'] . ' th' : '-',
            'jk'             => $row['jk'] ?: '-',
            'tgl_registrasi' => $row['tgl_registrasi'] ?: '-',
            'nm_poli'        => $row['nm_poli'] ?: '-',
            'nm_dokter'      => $row['nm_dokter'] ?: '-'
        ];
    }
}

echo json_encode([
    'masuk'    => $masuk,
    'keluar'   => $keluar,
    'internal' => $internal
], JSON_UNESCAPED_UNICODE);
