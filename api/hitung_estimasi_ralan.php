<?php
/*
 * File: api/hitung_estimasi_ralan.php
 * Fungsi: Menghitung estimasi biaya satu pasien Ralan secara on-demand (Lazy Loading).
 * Logika: PERSIS SAMA dengan fungsi hitungEstimasiAkurat() di data_kunjungan_ralan.php
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(30);
mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

$no_rawat = isset($_GET['no_rawat']) ? $koneksi->real_escape_string(trim($_GET['no_rawat'])) : '';
if (empty($no_rawat)) {
    ob_end_clean();
    echo json_encode(['error' => 'Parameter no_rawat kosong.']);
    exit;
}

$koneksi->query("SET sql_mode = ''");

// ===== HELPER =====
function safe_utf8_r($str) {
    if (is_null($str)) return '';
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    return $str;
}

// ===== LOAD SETTINGS (PERSIS SAMA dengan data_kunjungan_ralan.php) =====
$settings = ['service_charge' => 0, 'ppn_obat' => false, 'components' => []];
$q = $koneksi->query("SELECT tampilkan_ppnobat_ralan FROM set_nota LIMIT 1");
if ($q && $r = $q->fetch_assoc()) $settings['ppn_obat'] = ($r['tampilkan_ppnobat_ralan'] == 'Yes');

$q = $koneksi->query("SELECT * FROM set_service_ranap LIMIT 1");
if ($q && $r = $q->fetch_assoc()) {
    $settings['service_charge'] = (float)$r['besar'];
    $keys = ['laborat','radiologi','operasi','obat','ranap_dokter','ranap_paramedis','ralan_dokter','ralan_paramedis','tambahan','potongan','kamar','registrasi','harian','retur_Obat','resep_Pulang'];
    foreach ($keys as $k) $settings['components'][$k] = ($r[$k] == 'Yes');
}

// ===== KALKULASI BIAYA (PERSIS sama dengan hitungEstimasiAkurat & hitungObatSaja) =====
$biaya = [
    'laborat' => 0, 'radiologi' => 0, 'operasi' => 0, 'obat' => 0,
    'ranap_dokter' => 0, 'ranap_paramedis' => 0, 'ralan_dokter' => 0, 'ralan_paramedis' => 0,
    'tambahan' => 0, 'potongan' => 0, 'kamar' => 0, 'registrasi' => 0,
    'harian' => 0, 'retur_Obat' => 0, 'resep_Pulang' => 0
];

$q = $koneksi->query("SELECT biaya_reg FROM reg_periksa WHERE no_rawat='$no_rawat'");
if ($q && $r = $q->fetch_assoc()) $biaya['registrasi'] += (float)$r['biaya_reg'];

$q = $koneksi->query("SELECT SUM(total) as val FROM detail_pemberian_obat WHERE no_rawat='$no_rawat'");
if ($q && $r = $q->fetch_assoc()) $biaya['obat'] += (float)$r['val'];

$q = $koneksi->query("SELECT SUM(besar_tagihan) as val FROM tagihan_obat_langsung WHERE no_rawat='$no_rawat'");
if ($q && $r = $q->fetch_assoc()) $biaya['obat'] += (float)$r['val'];

$q = $koneksi->query("SELECT SUM(r.jml * d.ralan) as val FROM returpasien r JOIN databarang d ON r.kode_brng = d.kode_brng WHERE r.no_rawat='$no_rawat'");
if ($q && $r = $q->fetch_assoc()) $biaya['retur_Obat'] += (float)$r['val'];

$tables = [
    'rawat_jl_dr'           => 'ralan_dokter',
    'rawat_jl_pr'           => 'ralan_paramedis',
    'rawat_jl_drpr'         => 'ralan_dokter',
    'periksa_lab'           => 'laborat',
    'periksa_radiologi'     => 'radiologi',
    'penggunaan_darah_donor'=> 'obat'
];
foreach ($tables as $tbl => $cat) {
    $col = (strpos($tbl, 'periksa_') !== false || $tbl == 'penggunaan_darah_donor') ? 'biaya' : 'biaya_rawat';
    $q = $koneksi->query("SELECT SUM($col) as val FROM $tbl WHERE no_rawat='$no_rawat'");
    if ($q && $r = $q->fetch_assoc()) $biaya[$cat] += (float)$r['val'];
}

// Operasi — sum panjang persis seperti data_kunjungan_ralan.php
$sql_op = "SELECT SUM(biayaoperator1+biayaoperator2+biayaoperator3+biayaasisten_operator1+biayaasisten_operator2+biayadokter_anestesi+biayaasisten_anestesi+biayaasisten_anestesi2+biayadokter_anak+biayaperawaat_resusitas+biayabidan+biayabidan2+biayabidan3+biayaperawat_luar+biayasewaok+biayaalat+akomodasi+bagian_rs+biaya_omloop+biaya_omloop2+biaya_omloop3+biaya_omloop4+biaya_omloop5+biayasarpras+biaya_dokter_pjanak+biaya_dokter_umum) as val FROM operasi WHERE no_rawat='$no_rawat'";
$q = $koneksi->query($sql_op);
if ($q && $r = $q->fetch_assoc()) $biaya['operasi'] += (float)$r['val'];

$q = $koneksi->query("SELECT SUM(besar_biaya) as val FROM tambahan_biaya WHERE no_rawat='$no_rawat'");
if ($q && $r = $q->fetch_assoc()) $biaya['tambahan'] += (float)$r['val'];

$q = $koneksi->query("SELECT SUM(besar_pengurangan) as val FROM pengurangan_biaya WHERE no_rawat='$no_rawat'");
if ($q && $r = $q->fetch_assoc()) $biaya['potongan'] += (float)$r['val'];

$obat_bersih = $biaya['obat'] - $biaya['retur_Obat'];
$ppn_rp = ($settings['ppn_obat'] && $obat_bersih > 0) ? $obat_bersih * 0.11 : 0;

$service_base = 0;
foreach ($settings['components'] as $key => $isActive) {
    if ($isActive && isset($biaya[$key])) {
        $service_base += ($key == 'retur_Obat') ? -($biaya[$key]) : $biaya[$key];
    }
}
$service_rp = ($service_base * $settings['service_charge']) / 100;

$total_biaya = array_sum($biaya) - ($biaya['retur_Obat'] * 2) - ($biaya['potongan'] * 2) + $ppn_rp + $service_rp;
$biaya_obat  = $biaya['obat'] - $biaya['retur_Obat'];

ob_end_clean();
echo json_encode([
    'no_rawat'       => $no_rawat,
    'biaya_obat_raw' => $biaya_obat,
    'biaya_obat'     => 'Rp ' . number_format($biaya_obat, 0, ',', '.'),
    'estimasi_raw'   => $total_biaya,
    'estimasi'       => 'Rp ' . number_format($total_biaya, 0, ',', '.'),
]);
?>
