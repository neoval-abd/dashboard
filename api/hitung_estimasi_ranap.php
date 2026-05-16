<?php
/*
 * File: api/hitung_estimasi_ranap.php
 * Fungsi: Menghitung estimasi biaya satu pasien Ranap secara on-demand (Lazy Loading).
 * Logika: PERSIS SAMA dengan blok kalkulasi di data_kunjungan_ranap.php (V9 PARITY)
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
$kd_pj    = isset($_GET['kd_pj'])    ? $koneksi->real_escape_string(trim($_GET['kd_pj']))    : '-';

if (empty($no_rawat)) {
    ob_end_clean();
    echo json_encode(['error' => 'Parameter no_rawat kosong.']);
    exit;
}

// ===== HELPER =====
function safeFloat_r($val) {
    if (is_null($val) || $val === '') return 0.0;
    return (float)$val;
}
function safe_q($conn, $sql) {
    $res = $conn->query($sql);
    return ($res === false) ? false : $res;
}

// ===== LOAD GLOBAL SETTINGS (identik data_kunjungan_ranap.php) =====
$setting_kamar = ['hariawal' => 'no', 'lamajam' => 0];
$q_jam = safe_q($koneksi, "SELECT hariawal, lamajam FROM set_jam_minimal LIMIT 1");
if ($q_jam && $r_jam = $q_jam->fetch_assoc()) $setting_kamar = $r_jam;

$tampilkan_ppn_ranap = false;
$q_set = safe_q($koneksi, "SELECT tampilkan_ppnobat_ranap FROM set_nota LIMIT 1");
if ($q_set && $r_set = $q_set->fetch_assoc()) {
    if ($r_set['tampilkan_ppnobat_ranap'] == 'Yes') $tampilkan_ppn_ranap = true;
}

$service_umum = null; $service_piutang = null;
$q_su = safe_q($koneksi, "SELECT * FROM set_service_ranap LIMIT 1");
if ($q_su) $service_umum = $q_su->fetch_assoc();
$q_sp = safe_q($koneksi, "SELECT * FROM set_service_ranap_piutang LIMIT 1");
if ($q_sp) $service_piutang = $q_sp->fetch_assoc();

// ===== KALKULASI BIAYA (PERSIS SAMA dengan data_kunjungan_ranap.php V9) =====
$grand_total = 0.0;
$sum_kamar = 0; $sum_reg = 0;
$sum_dr_ralan = 0; $sum_pr_ralan = 0;
$sum_dr_ranap = 0; $sum_pr_ranap = 0;
$sum_lab = 0; $sum_rad = 0; $sum_op = 0; $sum_obat = 0;
$sum_retur = 0; $sum_tambah = 0; $sum_potong = 0; $sum_harian = 0;

// A. Registrasi
$q = safe_q($koneksi, "SELECT biaya_reg FROM reg_periksa WHERE no_rawat='$no_rawat'");
if ($q && $r = $q->fetch_assoc()) {
    $val = safeFloat_r($r['biaya_reg']);
    if ($val > 0) { $sum_reg += $val; $grand_total += $val; }
}

// B. Kamar Inap (History Mode)
$q_hist_kamar = safe_q($koneksi, "SELECT k.kd_kamar, k.trf_kamar, ki.tgl_masuk, ki.tgl_keluar, ki.lama, ki.ttl_biaya FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar = k.kd_kamar WHERE ki.no_rawat='$no_rawat'");
if ($q_hist_kamar) {
    while ($rhk = $q_hist_kamar->fetch_assoc()) {
        $tgl_masuk  = $rhk['tgl_masuk'];
        $tgl_keluar = ($rhk['tgl_keluar'] != '0000-00-00') ? $rhk['tgl_keluar'] : date('Y-m-d');
        $d1 = new DateTime($tgl_masuk); $d2 = new DateTime($tgl_keluar);
        $diff = $d2->diff($d1);
        $hari_raw = $diff->days;

        if ($setting_kamar['hariawal'] == 'yes') $hari = $hari_raw + 1;
        else $hari = $hari_raw;

        if (safeFloat_r($rhk['ttl_biaya']) > 0 && safeFloat_r($rhk['lama']) > 0) $hari = safeFloat_r($rhk['lama']);

        $biaya_satu_kamar = $hari * safeFloat_r($rhk['trf_kamar']);
        if ($biaya_satu_kamar > 0) { $sum_kamar += $biaya_satu_kamar; $grand_total += $biaya_satu_kamar; }

        $kd_k = $koneksi->real_escape_string($rhk['kd_kamar']);
        $q_bs = safe_q($koneksi, "SELECT SUM(besar_biaya) as tot FROM biaya_sekali WHERE kd_kamar='$kd_k'");
        if ($q_bs && $row_bs = $q_bs->fetch_assoc()) { $val = safeFloat_r($row_bs['tot']); $sum_harian += $val; $grand_total += $val; }

        $q_bh = safe_q($koneksi, "SELECT SUM(besar_biaya) as tot FROM biaya_harian WHERE kd_kamar='$kd_k'");
        if ($q_bh && $row_bh = $q_bh->fetch_assoc()) { $val = ($hari * safeFloat_r($row_bh['tot'])); $sum_harian += $val; $grand_total += $val; }
    }
}

// C. Operasi (PHP Loop — Aman dari Typo SQL)
$q_op = safe_q($koneksi, "SELECT * FROM operasi WHERE no_rawat='$no_rawat'");
if ($q_op) {
    while ($r_op = $q_op->fetch_assoc()) {
        $komponen = ['biayaoperator1','biayaoperator2','biayaoperator3','biayaasisten_operator1','biayaasisten_operator2','biayadokter_anestesi','biayaasisten_anestesi','biayasewaok','biayaalat','akomodasi','bagian_rs','biaya_omloop','biayasarpras','biaya_dokter_anak','biayaperawaat_resusitas','biayabidan'];
        foreach ($komponen as $k) {
            if (isset($r_op[$k])) { $val = safeFloat_r($r_op[$k]); $sum_op += $val; }
        }
    }
}
$grand_total += $sum_op;

// D. Tindakan (UNION Mode)
// Lab charges can be stored as detail_periksa_lab.biaya_item for grouped lab packages.
// Use detail_item totals when available, otherwise fall back to the raw periksa_lab.biaya value.
$sql_tind = "SELECT 'lab' as grp, SUM(CASE WHEN COALESCE(t.detail_sum,0) > 0 THEN t.detail_sum ELSE t.biaya END) as tot
             FROM (
                 SELECT p.no_rawat, p.kd_jenis_prw, p.tgl_periksa, p.jam, p.biaya, SUM(d.biaya_item) as detail_sum
                 FROM periksa_lab p
                 LEFT JOIN detail_periksa_lab d ON p.no_rawat=d.no_rawat AND p.kd_jenis_prw=d.kd_jenis_prw AND p.tgl_periksa=d.tgl_periksa AND p.jam=d.jam
                 WHERE p.no_rawat='$no_rawat'
                 GROUP BY p.no_rawat, p.kd_jenis_prw, p.tgl_periksa, p.jam, p.biaya
             ) t
             UNION ALL SELECT 'rad', SUM(biaya) FROM periksa_radiologi WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'dr_ralan', SUM(biaya_rawat) FROM rawat_jl_dr WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'pr_ralan', SUM(biaya_rawat) FROM rawat_jl_pr WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'dr_ralan', SUM(biaya_rawat) FROM rawat_jl_drpr WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'dr_ranap', SUM(biaya_rawat) FROM rawat_inap_dr WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'pr_ranap', SUM(biaya_rawat) FROM rawat_inap_pr WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'dr_ranap', SUM(biaya_rawat) FROM rawat_inap_drpr WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'tambah', SUM(besar_biaya) FROM tambahan_biaya WHERE no_rawat='$no_rawat'
             UNION ALL SELECT 'potong', SUM(besar_pengurangan) FROM pengurangan_biaya WHERE no_rawat='$no_rawat'";
$q_tind = safe_q($koneksi, $sql_tind);
if ($q_tind) {
    while ($rt = $q_tind->fetch_assoc()) {
        $val = safeFloat_r($rt['tot']); $grp = $rt['grp'];
        if ($val != 0) {
            if ($grp == 'lab')      $sum_lab     += $val;
            elseif ($grp == 'rad')  $sum_rad     += $val;
            elseif ($grp == 'dr_ralan') $sum_dr_ralan += $val;
            elseif ($grp == 'pr_ralan') $sum_pr_ralan += $val;
            elseif ($grp == 'dr_ranap') $sum_dr_ranap += $val;
            elseif ($grp == 'pr_ranap') $sum_pr_ranap += $val;
            elseif ($grp == 'tambah')   $sum_tambah   += $val;
            elseif ($grp == 'potong') { $sum_potong += (-1 * abs($val)); $grand_total += (-1 * abs($val)); continue; }
            $grand_total += $val;
        }
    }
}

// E. Obat & Retur
$sql_obat = "SELECT SUM(total) as tot FROM detail_pemberian_obat WHERE no_rawat='$no_rawat'
             UNION ALL SELECT SUM(besar_tagihan) FROM tagihan_obat_langsung WHERE no_rawat='$no_rawat'
             UNION ALL SELECT SUM(hargasatuan * jumlah) FROM beri_obat_operasi WHERE no_rawat='$no_rawat'";
$q_obat = safe_q($koneksi, $sql_obat);
if ($q_obat) while ($ro = $q_obat->fetch_assoc()) $sum_obat += safeFloat_r($ro['tot']);
$grand_total += $sum_obat;

$q_ret = safe_q($koneksi, "SELECT SUM(r.jml * d.ralan) as tot FROM returpasien r JOIN databarang d ON r.kode_brng = d.kode_brng WHERE r.no_rawat='$no_rawat'");
if ($q_ret && $rr = $q_ret->fetch_assoc()) $sum_retur += abs(safeFloat_r($rr['tot']));
$grand_total -= $sum_retur;

// F. PPN
if ($tampilkan_ppn_ranap) {
    $obat_bersih = $sum_obat - $sum_retur;
    if ($obat_bersih > 0) $grand_total += round($obat_bersih * 0.11);
}

// G. Jasa Admin (Service) — pilih service berdasarkan penjamin
$s = null;
if ($kd_pj != '-' && $kd_pj != 'UMUM' && $kd_pj != 'A01') $s = $service_piutang;
else $s = $service_umum;

if ($s) {
    $basis = 0;
    if ($s['laborat']          == 'Yes') $basis += $sum_lab;
    if ($s['radiologi']        == 'Yes') $basis += $sum_rad;
    if ($s['operasi']          == 'Yes') $basis += $sum_op;
    if ($s['obat']             == 'Yes') $basis += ($sum_obat - $sum_retur);
    if ($s['ranap_dokter']     == 'Yes') $basis += $sum_dr_ranap;
    if ($s['ranap_paramedis']  == 'Yes') $basis += $sum_pr_ranap;
    if ($s['ralan_dokter']     == 'Yes') $basis += $sum_dr_ralan;
    if ($s['ralan_paramedis']  == 'Yes') $basis += $sum_pr_ralan;
    if ($s['tambahan']         == 'Yes') $basis += $sum_tambah;
    if ($s['potongan']         == 'Yes') $basis += $sum_potong;
    if ($s['kamar']            == 'Yes') $basis += $sum_kamar;
    if ($s['registrasi']       == 'Yes') $basis += $sum_reg;
    if ($s['harian']           == 'Yes') $basis += $sum_harian;

    $persen = safeFloat_r($s['besar']);
    if ($basis > 0 && $persen > 0) {
        $jasa_admin = round($basis * ($persen / 100));
        $cek = safe_q($koneksi, "SELECT totalbiaya FROM billing WHERE no_rawat='$no_rawat' AND (nm_perawatan LIKE '%Administrasi%' OR nm_perawatan LIKE '%Service%')");
        if (!$cek || $cek->num_rows == 0) {
            $grand_total += $jasa_admin;
        } else {
            while ($row_bill = $cek->fetch_assoc()) $grand_total += safeFloat_r($row_bill['totalbiaya']);
        }
    }
}

// DPJP
$dpjp = '';
$is_dpjp_fallback = false;
$q_dpjp = safe_q($koneksi, "SELECT d.nm_dokter FROM dpjp_ranap dr JOIN dokter d ON dr.kd_dokter = d.kd_dokter WHERE dr.no_rawat='$no_rawat' LIMIT 1");
if ($q_dpjp && $rd = $q_dpjp->fetch_assoc()) $dpjp = $rd['nm_dokter'];
else $is_dpjp_fallback = true;

// H. Ambil Plafon dari pilihan INA-CBG terlebih dahulu, lalu fallback ke tabel perkiraan_biaya_ranap
$plafon_val = 0;
$has_plafon  = false;
$q_inacbg = safe_q($koneksi, "SELECT tarif FROM ranap_inacbg_selection WHERE no_rawat='$no_rawat' LIMIT 1");
if ($q_inacbg && $r_inacbg = $q_inacbg->fetch_assoc()) {
    if (!is_null($r_inacbg['tarif']) && $r_inacbg['tarif'] !== '') {
        $plafon_val = safeFloat_r($r_inacbg['tarif']);
        $has_plafon = ($plafon_val > 0);
    }
}

if (!$has_plafon) {
    $q_plafon = safe_q($koneksi, "SELECT tarif FROM perkiraan_biaya_ranap WHERE no_rawat='$no_rawat' LIMIT 1");
    if ($q_plafon && $r_plafon = $q_plafon->fetch_assoc()) {
        if (!is_null($r_plafon['tarif']) && $r_plafon['tarif'] !== '') {
            $plafon_val = safeFloat_r($r_plafon['tarif']);
            $has_plafon = ($plafon_val > 0);
        }
    }
}

$selisih_val = $grand_total - $plafon_val;
$is_over     = ($has_plafon && $grand_total > $plafon_val);

// Persentase penggunaan plafon (untuk progress bar)
$pct = ($has_plafon && $plafon_val > 0) ? min(100, round(($grand_total / $plafon_val) * 100)) : 0;


ob_end_clean();
echo json_encode([
    'no_rawat'         => $no_rawat,
    'estimasi'         => number_format($grand_total, 0, ',', '.'),
    'estimasi_raw'     => $grand_total,
    'plafon'           => $has_plafon ? ('Rp ' . number_format($plafon_val, 0, ',', '.')) : '-',
    'plafon_raw'       => $plafon_val,
    'has_plafon'       => $has_plafon,
    'selisih'          => $has_plafon ? ('Rp ' . number_format(abs($selisih_val), 0, ',', '.')) : '-',
    'selisih_raw'      => $has_plafon ? $selisih_val : null,
    'is_over'          => $is_over,
    'pct'              => $pct,
    'dpjp'             => $dpjp,
    'is_dpjp_fallback' => $is_dpjp_fallback,
]);

?>
