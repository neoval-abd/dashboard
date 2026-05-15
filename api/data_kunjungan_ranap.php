<?php
/*
 * File: api/data_kunjungan_ranap.php (FIX V9 - PARITY MODE)
 * Deskripsi: Menampilkan list pasien Ranap dengan kalkulasi biaya realtime.
 * Fix: Menggunakan logika PHP loop untuk Operasi (bukan SQL SUM) untuk mencegah error typo kolom.
 * Fix: Menjamin sinkronisasi 100% dengan data_rincian_billing.php
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(0);

mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

// 1. HELPER FUNCTIONS
function safeFloat($val) {
    if (is_null($val) || $val === '') return 0.0;
    return (float)$val;
}

function safe_query($conn, $sql) {
    $res = $conn->query($sql);
    if ($res === false) { return false; }
    return $res;
}

// 2. LOAD GLOBAL SETTINGS
$setting_kamar = ['hariawal' => 'no', 'lamajam' => 0]; 
$q_jam = safe_query($koneksi, "SELECT hariawal, lamajam FROM set_jam_minimal LIMIT 1");
if($q_jam && $r_jam = $q_jam->fetch_assoc()) $setting_kamar = $r_jam;

$tampilkan_ppn_ranap = false;
$q_set = $koneksi->query("SELECT tampilkan_ppnobat_ranap FROM set_nota LIMIT 1");
if($q_set && $r_set = $q_set->fetch_assoc()){
    if($r_set['tampilkan_ppnobat_ranap'] == 'Yes') $tampilkan_ppn_ranap = true;
}

$service_umum = null; $service_piutang = null;
$q_su = safe_query($koneksi, "SELECT * FROM set_service_ranap LIMIT 1");
if($q_su) $service_umum = $q_su->fetch_assoc();
$q_sp = safe_query($koneksi, "SELECT * FROM set_service_ranap_piutang LIMIT 1");
if($q_sp) $service_piutang = $q_sp->fetch_assoc();

// 3. PARAMETER DATATABLES
$draw   = isset($_GET['draw']) ? intval($_GET['draw']) : 0;
$start  = isset($_GET['start']) ? intval($_GET['start']) : 0;
$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
$search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';
$mode   = isset($_GET['mode']) ? $_GET['mode'] : 'active';
$tgl1   = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl2   = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_pj  = isset($_GET['kd_pj']) ? $koneksi->real_escape_string($_GET['kd_pj']) : '';

// 4. QUERY UTAMA
$where = " WHERE 1=1 ";
if ($mode == 'active') {
    $where .= " AND ki.stts_pulang = '-' ";
} else {
    $where .= " AND ki.tgl_masuk BETWEEN '$tgl1' AND '$tgl2' ";
}

if (!empty($search)) {
    $s = $koneksi->real_escape_string($search);
    $where .= " AND (ki.no_rawat LIKE '%$s%' OR p.nm_pasien LIKE '%$s%' OR d.nm_dokter LIKE '%$s%' OR dpjp.nm_dokter LIKE '%$s%' OR b.nm_bangsal LIKE '%$s%') ";
}

if (!empty($kd_pj) && $kd_pj !== 'all') {
    $where .= " AND rp.kd_pj = '$kd_pj' ";
}

$sql_count = "SELECT COUNT(DISTINCT ki.no_rawat) as total 
              FROM kamar_inap ki 
              JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
              JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
              LEFT JOIN dpjp_ranap dr ON rp.no_rawat = dr.no_rawat
              LEFT JOIN dokter dpjp ON dr.kd_dokter = dpjp.kd_dokter
              LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
              LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
              LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
              LEFT JOIN bridging_sep bs ON rp.no_rawat = bs.no_rawat
              $where";
$q_count = $koneksi->query($sql_count);
$total_records = ($q_count) ? $q_count->fetch_assoc()['total'] : 0;

$sql_data = "SELECT ki.no_rawat, ki.tgl_masuk, ki.jam_masuk, ki.stts_pulang,
             p.nm_pasien, p.no_rkm_medis,
             dpjp.nm_dokter AS dpjp_ranap,
             d.nm_dokter AS dokter_perujuk,
             b.nm_bangsal, k.kd_kamar, k.kelas,
             bs.klsrawat AS bpjs_kelas,
             s.kode_inacbg, s.deskripsi AS inacbg_deskripsi, s.kelas AS inacbg_class, s.tarif AS inacbg_tarif,
             pj.png_jawab, pj.kd_pj, rp.biaya_reg
             FROM kamar_inap ki 
             JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
             JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
             JOIN penjab pj ON rp.kd_pj = pj.kd_pj
             LEFT JOIN dpjp_ranap dr ON rp.no_rawat = dr.no_rawat
             LEFT JOIN dokter dpjp ON dr.kd_dokter = dpjp.kd_dokter
             LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
             LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
             LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
             LEFT JOIN bridging_sep bs ON rp.no_rawat = bs.no_rawat
             LEFT JOIN ranap_inacbg_selection s ON ki.no_rawat = s.no_rawat
             $where
             GROUP BY ki.no_rawat
             ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
             LIMIT $start, $length";

$q_data = $koneksi->query($sql_data);

if (!$q_data) {
    ob_end_clean();
    echo json_encode([
        "draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => [],
        "error" => "SQL Error: " . $koneksi->error
    ]);
    exit;
}

$raw_data = [];
while ($r = $q_data->fetch_assoc()) {
    $raw_data[] = $r;
}
$q_data->free();

// 5. FORMAT OUTPUT (TANPA KALKULASI — Lazy Loading v2)
$data = [];
foreach ($raw_data as $r) {
    $selectedTarif = isset($r['inacbg_tarif']) ? intval($r['inacbg_tarif']) : 0;
    $data[] = [
        "waktu"            => $r['tgl_masuk'],
        "no_rawat"         => $r['no_rawat'],
        "pasien"           => $r['nm_pasien'],
        "rm"               => $r['no_rkm_medis'],
        "dpjp"             => $r['dpjp_ranap'],
        "dokter_perujuk"   => $r['dokter_perujuk'],
        "is_dpjp_fallback" => empty($r['dpjp_ranap']),
        "kamar"            => $r['nm_bangsal'],
        "bpjs_kelas"       => $r['bpjs_kelas'],
        "room_kelas"       => $r['kelas'],
        "selected_inacbg_code"  => $r['kode_inacbg'] ?? '',
        "selected_inacbg_desc"  => $r['inacbg_deskripsi'] ?? '',
        "selected_inacbg_class" => $r['inacbg_class'] ?? '',
        "selected_inacbg_tarif" => $selectedTarif,
        "penjamin"         => $r['png_jawab'],
        "kd_pj"            => $r['kd_pj'],
        // Placeholder — akan diisi async oleh frontend
        "estimasi"         => null,
        "plafon"           => $selectedTarif > 0 ? number_format($selectedTarif, 0, ',', '.') : null,
        "selisih"          => null,
        "is_over"          => false,
        "status_pulang"    => ($r['stts_pulang'] != '-') ? $r['stts_pulang'] : 'Masih Dirawat'
    ];
}

$output = [
    "draw" => $draw,
    "recordsTotal" => $total_records,
    "recordsFiltered" => $total_records,
    "data" => $data
];

ob_end_clean();
echo json_encode($output);
?>