<?php
/*
 * File: api/data_kunjungan_ranap.php (FIX V27 - FILTER & AUDIT MODE)
 * - Fitur: Mode 'Active' (Default) vs 'Audit' (Cek Pulang Belum Bayar).
 * - Security: Future Ban pada filter tanggal.
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(0);
mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

$koneksi->query("SET sql_mode = ''");

// --- HELPER ---
function textToUtf8($str) {
    if (is_null($str)) return "";
    $str = (string)$str;
    if (function_exists('mb_convert_encoding')) return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    return @utf8_encode($str);
}

function safeFloat($val) {
    if (is_null($val) || $val === '') return 0.0;
    return (float)$val;
}

// --- SETTINGS ---
function getSettings($conn) {
    $settings = ['service_charge' => 0, 'ppn_obat' => false, 'components' => []];
    $q = $conn->query("SELECT tampilkan_ppnobat_ranap FROM set_nota LIMIT 1");
    if($q && $r = $q->fetch_assoc()) $settings['ppn_obat'] = ($r['tampilkan_ppnobat_ranap'] == 'Yes');

    $q = $conn->query("SELECT * FROM set_service_ranap LIMIT 1");
    if($q && $r = $q->fetch_assoc()) {
        $settings['service_charge'] = safeFloat($r['besar']);
        $keys = ['laborat', 'radiologi', 'operasi', 'obat', 'ranap_dokter', 'ranap_paramedis', 'ralan_dokter', 'ralan_paramedis', 'tambahan', 'potongan', 'kamar', 'registrasi', 'harian', 'retur_Obat', 'resep_Pulang'];
        foreach($keys as $k) $settings['components'][$k] = ($r[$k] == 'Yes');
    }
    return $settings;
}

// --- HITUNG ESTIMASI (Logic Sama) ---
function hitungEstimasiAkurat($conn, $no_rawat, $settings) {
    $biaya = [
        'laborat' => 0.0, 'radiologi' => 0.0, 'operasi' => 0.0, 'obat' => 0.0,
        'ranap_dokter' => 0.0, 'ranap_paramedis' => 0.0, 'ralan_dokter' => 0.0, 'ralan_paramedis' => 0.0,
        'tambahan' => 0.0, 'potongan' => 0.0, 'kamar' => 0.0, 'registrasi' => 0.0,
        'harian' => 0.0, 'retur_Obat' => 0.0, 'resep_Pulang' => 0.0
    ];

    $q = $conn->query("SELECT biaya_reg FROM reg_periksa WHERE no_rawat='$no_rawat'");
    if($q && $r = $q->fetch_assoc()) $biaya['registrasi'] += safeFloat($r['biaya_reg']);

    $q = $conn->query("SELECT SUM(total) as val FROM detail_pemberian_obat WHERE no_rawat='$no_rawat'");
    if($q && $r = $q->fetch_assoc()) $biaya['obat'] += safeFloat($r['val']);
    
    $q = $conn->query("SELECT SUM(besar_tagihan) as val FROM tagihan_obat_langsung WHERE no_rawat='$no_rawat'");
    if($q && $r = $q->fetch_assoc()) $biaya['obat'] += safeFloat($r['val']);

    $q = $conn->query("SELECT SUM(r.jml * d.ralan) as val FROM returpasien r JOIN databarang d ON r.kode_brng = d.kode_brng WHERE r.no_rawat='$no_rawat'");
    if($q && $r = $q->fetch_assoc()) $biaya['retur_Obat'] += safeFloat($r['val']);

    $tables = [
        'rawat_jl_dr'=>'ralan_dokter', 'rawat_jl_pr'=>'ralan_paramedis', 'rawat_jl_drpr'=>'ralan_dokter', 
        'rawat_inap_dr'=>'ranap_dokter', 'rawat_inap_pr'=>'ranap_paramedis', 'rawat_inap_drpr'=>'ranap_dokter',
        'periksa_lab'=>'laborat', 'periksa_radiologi'=>'radiologi', 'penggunaan_darah_donor'=>'obat'
    ];
    foreach($tables as $tbl => $cat) {
        $col = (strpos($tbl, 'periksa_') !== false || $tbl == 'penggunaan_darah_donor') ? 'biaya' : 'biaya_rawat';
        $q = $conn->query("SELECT SUM($col) as val FROM $tbl WHERE no_rawat='$no_rawat'");
        if($q && $r = $q->fetch_assoc()) $biaya[$cat] += safeFloat($r['val']);
    }

    $sql_op = "SELECT SUM(biayaoperator1+biayaoperator2+biayaoperator3+biayaasisten_operator1+biayaasisten_operator2+biayadokter_anestesi+biayaasisten_anestesi+biayaasisten_anestesi2+biayadokter_anak+biayaperawaat_resusitas+biayabidan+biayabidan2+biayabidan3+biayaperawat_luar+biayasewaok+biayaalat+akomodasi+bagian_rs+biaya_omloop+biaya_omloop2+biaya_omloop3+biaya_omloop4+biaya_omloop5+biayasarpras+biaya_dokter_pjanak+biaya_dokter_umum) as val FROM operasi WHERE no_rawat='$no_rawat'";
    $q = $conn->query($sql_op);
    if($q && $r = $q->fetch_assoc()) $biaya['operasi'] += safeFloat($r['val']);

    $q_kamar = $conn->query("SELECT ki.kd_kamar, k.trf_kamar, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar, ki.stts_pulang, ki.lama, ki.ttl_biaya FROM kamar_inap ki INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar WHERE ki.no_rawat='$no_rawat'");
    if ($q_kamar) {
        while($r_kamar = $q_kamar->fetch_assoc()) {
            if (safeFloat($r_kamar['ttl_biaya']) > 0) {
                $biaya['kamar'] += safeFloat($r_kamar['ttl_biaya']);
            } else {
                if($r_kamar['tgl_keluar'] != '0000-00-00') { 
                    $tgl_keluar = $r_kamar['tgl_keluar']; 
                } else {
                    $tgl_keluar = date('Y-m-d');
                }
                try {
                    $ts1 = strtotime($r_kamar['tgl_masuk'] . ' ' . $r_kamar['jam_masuk']);
                    $ts2 = strtotime($tgl_keluar . ' ' . ($r_kamar['jam_keluar'] == '00:00:00' ? date('H:i:s') : $r_kamar['jam_keluar']));
                    $hari = floor(($ts2 - $ts1) / (60 * 60 * 24));
                } catch (Exception $e) { $hari = 1; }
                if ($hari <= 0) $hari = 1;
                $biaya['kamar'] += ($hari * safeFloat($r_kamar['trf_kamar']));
            }

            $kd_kamar = $r_kamar['kd_kamar'];
            $q_h = $conn->query("SELECT besar_biaya FROM biaya_harian WHERE kd_kamar='$kd_kamar'");
            while($rh = $q_h->fetch_assoc()) $biaya['harian'] += ($hari * safeFloat($rh['besar_biaya']));

            $q_s = $conn->query("SELECT besar_biaya FROM biaya_sekali WHERE kd_kamar='$kd_kamar'");
            while($rs = $q_s->fetch_assoc()) $biaya['kamar'] += safeFloat($rs['besar_biaya']);
        }
    }
    
    $q = $conn->query("SELECT SUM(besar_biaya) as val FROM tambahan_biaya WHERE no_rawat='$no_rawat'");
    if($q && $r = $q->fetch_assoc()) $biaya['tambahan'] += safeFloat($r['val']);
    
    $q = $conn->query("SELECT SUM(besar_pengurangan) as val FROM pengurangan_biaya WHERE no_rawat='$no_rawat'");
    if($q && $r = $q->fetch_assoc()) $biaya['potongan'] += safeFloat($r['val']);

    $obat_bersih = $biaya['obat'] - $biaya['retur_Obat'];
    $ppn_rp = ($settings['ppn_obat'] && $obat_bersih > 0) ? $obat_bersih * 0.11 : 0;

    $service_base = 0;
    foreach ($settings['components'] as $key => $isActive) {
        if ($isActive && isset($biaya[$key])) {
            $service_base += ($key == 'retur_Obat') ? -($biaya[$key]) : $biaya[$key];
        }
    }
    $service_rp = ($service_base * $settings['service_charge']) / 100;

    return array_sum($biaya) - ($biaya['retur_Obat'] * 2) - ($biaya['potongan'] * 2) + $ppn_rp + $service_rp;
}

$settings = getSettings($koneksi);
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 10;
$search_value = isset($_GET['search']['value']) ? $koneksi->real_escape_string($_GET['search']['value']) : '';
$draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 1;

// --- LOGIKA FILTER MODE (ACTIVE vs AUDIT) ---
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'active'; // Default Active Only
$tgl_awal = isset($_GET['tgl_awal']) ? $koneksi->real_escape_string($_GET['tgl_awal']) : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $koneksi->real_escape_string($_GET['tgl_akhir']) : date('Y-m-d');
$hari_ini = date('Y-m-d');

// Future Ban Rule
if ($tgl_akhir > $hari_ini) {
    $tgl_akhir = $hari_ini;
}

// Base Filter: Selalu 'Belum Bayar'
$filter_sql = " AND rp.status_bayar = 'Belum Bayar' ";

if ($mode == 'audit') {
    // Mode Audit: Tampilkan semua (Termasuk yg sudah Pulang) berdasarkan Tanggal Masuk
    $filter_sql .= " AND ki.tgl_masuk BETWEEN '$tgl_awal' AND '$tgl_akhir' ";
} else {
    // Mode Active: Hanya yang masih dirawat (stts_pulang kosong/minus). 
    // Tanggal filter diabaikan agar semua pasien aktif terlihat.
    $filter_sql .= " AND (ki.stts_pulang = '-' OR ki.stts_pulang = '') ";
}

$sql_from = "
    FROM kamar_inap ki
    INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
    INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
    INNER JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    LEFT JOIN perkiraan_biaya_ranap pbr ON ki.no_rawat = pbr.no_rawat
    LEFT JOIN dokter d_reg ON rp.kd_dokter = d_reg.kd_dokter
    WHERE 1=1
    $filter_sql 
";

if (!empty($search_value)) {
    // Saat search, batasan filter tetap berlaku agar konteks terjaga
    $sql_from .= " AND (
        p.nm_pasien LIKE '%$search_value%' 
        OR ki.no_rawat LIKE '%$search_value%' 
        OR rp.no_rkm_medis LIKE '%$search_value%'
        OR b.nm_bangsal LIKE '%$search_value%'
    ) ";
}

$q_cnt = $koneksi->query("SELECT COUNT(*) as total " . $sql_from);
if(!$q_cnt) { ob_end_clean(); die(json_encode(["error" => "SQL Count Error"])); }
$totalFiltered = $q_cnt->fetch_assoc()['total'];
$totalData = $totalFiltered;

$sql_limit = ($length != -1) ? "LIMIT $start, $length" : "";

$sql_data = "
    SELECT 
        ki.no_rawat, ki.tgl_masuk, ki.jam_masuk, ki.stts_pulang,
        p.no_rkm_medis, p.nm_pasien, pj.png_jawab, 
        k.kd_kamar, b.nm_bangsal, k.kelas,
        pbr.tarif as plafon_db, 
        DATEDIFF(NOW(), ki.tgl_masuk) as lama,
        (SELECT d.nm_dokter FROM dpjp_ranap dr JOIN dokter d ON dr.kd_dokter = d.kd_dokter WHERE dr.no_rawat = ki.no_rawat LIMIT 1) as dpjp_ranap,
        d_reg.nm_dokter as dokter_reg
    " . $sql_from . "
    ORDER BY ki.tgl_masuk DESC
    $sql_limit 
";

$res_data = $koneksi->query($sql_data);
if(!$res_data) { ob_end_clean(); die(json_encode(["error" => "SQL Data Error"])); }

$raw_data = [];

while ($row = $res_data->fetch_assoc()) {
    $total_biaya = hitungEstimasiAkurat($koneksi, $row['no_rawat'], $settings);
    
    $plafon_db = $row['plafon_db'];
    $has_plafon = !is_null($plafon_db) && $plafon_db !== ''; 

    if ($has_plafon) {
        $plafon_val = safeFloat($plafon_db);
        $selisih = $total_biaya - $plafon_val;
        $is_over = ($plafon_val > 0 && $total_biaya > $plafon_val);
        $plafon_display = 'Rp ' . number_format($plafon_val, 0, ',', '.');
        $selisih_display = 'Rp ' . number_format($selisih, 0, ',', '.');
        $selisih_raw = $selisih; 
    } else {
        $plafon_display = '-';
        $selisih_display = '-';
        $is_over = false;
        $selisih_raw = -999999999; 
    }

    $dpjp = $row['dpjp_ranap'];
    $is_fallback = false;
    if (empty($dpjp)) {
        $dpjp = $row['dokter_reg'];
        $is_fallback = true;
    }

    $lama = (safeFloat($row['lama']) < 1) ? 1 : $row['lama'];
    
    // Status Pulang Logic
    $stts_pulang = $row['stts_pulang'];
    if ($stts_pulang == '' || $stts_pulang == '-') {
        $stts_pulang = 'Masih Dirawat';
    }

    $raw_data[] = [
        'waktu' => $row['tgl_masuk'] . ' ' . $row['jam_masuk'],
        'no_rawat' => $row['no_rawat'],
        'rm' => $row['no_rkm_medis'],
        'pasien' => textToUtf8($row['nm_pasien']),
        'kamar' => textToUtf8($row['nm_bangsal']) . ' (' . $row['kelas'] . ')',
        'penjamin' => textToUtf8($row['png_jawab']),
        'lama' => $lama . ' Hari',
        'dpjp' => textToUtf8($dpjp),
        'is_dpjp_fallback' => $is_fallback,
        'estimasi_raw' => $total_biaya, 
        'estimasi' => 'Rp ' . number_format((float)$total_biaya, 0, ',', '.'),
        'plafon' => $plafon_display,
        'selisih' => $selisih_display,
        'is_over' => $is_over,
        'selisih_raw' => $selisih_raw,
        'status_pulang' => textToUtf8($stts_pulang) // Data untuk kolom status
    ];
}

usort($raw_data, function($a, $b) {
    if ($a['is_over'] && !$b['is_over']) return -1;
    if (!$a['is_over'] && $b['is_over']) return 1;
    if ($a['is_over'] && $b['is_over']) {
        return ($a['selisih_raw'] > $b['selisih_raw']) ? -1 : 1;
    }
    return 0; 
});

ob_end_clean();
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => (int)$totalData,
    "recordsFiltered" => (int)$totalFiltered,
    "data" => $raw_data
], JSON_INVALID_UTF8_IGNORE);
$koneksi->close();
?>