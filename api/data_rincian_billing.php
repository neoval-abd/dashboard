<?php
/*
 * File: api/data_rincian_billing.php (FIX V29 - ACCURATE SERVICE CHARGE)
 * - Fix: Memisahkan akumulator Ralan vs Ranap agar tidak double counting.
 * - Fix: Menggunakan persentase dinamis dari tabel set_service_ranap.
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(0);

mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

$koneksi->query("SET sql_mode = ''");
$no_rawat = isset($_GET['no_rawat']) ? $koneksi->real_escape_string($_GET['no_rawat']) : '';
$rows = [];
$grand_total = 0.0;

// --- 1. VARIABEL AKUMULATOR TERPISAH (PENTING!) ---
$sum_kamar = 0; $sum_reg = 0; 
$sum_dr_ralan = 0; $sum_pr_ralan = 0; // Ralan
$sum_dr_ranap = 0; $sum_pr_ranap = 0; // Ranap
$sum_lab = 0; $sum_rad = 0; $sum_op = 0; $sum_obat = 0; 
$sum_retur = 0; $sum_tambah = 0; $sum_potong = 0; $sum_harian = 0;

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

function sendResponse($data) {
    ob_end_clean();
    echo json_encode($data, JSON_INVALID_UTF8_IGNORE);
    exit;
}

function safe_query($conn, $sql) {
    $res = $conn->query($sql);
    return ($res === false) ? false : $res;
}

if(empty($no_rawat)) sendResponse(['data' => [], 'total_rupiah' => 0]);

// CEK SETTING JAM MINIMAL (KAMAR)
$setting_kamar = ['hariawal' => 'no', 'lamajam' => 0]; 
$q_jam = safe_query($koneksi, "SELECT hariawal, lamajam FROM set_jam_minimal LIMIT 1");
if($q_jam && $r_jam = $q_jam->fetch_assoc()) $setting_kamar = $r_jam;

// CEK INFO PASIEN
$status_lanjut = 'Ralan'; 
$kd_pj = '-';
$q_info = safe_query($koneksi, "SELECT status_lanjut, kd_pj FROM reg_periksa WHERE no_rawat='$no_rawat'");
if($q_info && $r_info = $q_info->fetch_assoc()){
    $status_lanjut = $r_info['status_lanjut'];
    $kd_pj = $r_info['kd_pj'];
}

// CEK PPN
$pakai_ppn = false;
$q_set = $koneksi->query("SELECT tampilkan_ppnobat_ralan, tampilkan_ppnobat_ranap FROM set_nota LIMIT 1");
if($q_set && $r_set = $q_set->fetch_assoc()){
    if($status_lanjut == 'Ralan' && $r_set['tampilkan_ppnobat_ralan'] == 'Yes') $pakai_ppn = true;
    else if($status_lanjut == 'Ranap' && $r_set['tampilkan_ppnobat_ranap'] == 'Yes') $pakai_ppn = true;
}

function addRow(&$rows, &$grand_total, $keterangan, $tagihan, $biaya, $jumlah, $tambahan, $total, $is_header = false) {
    $rows[] = [
        'keterangan' => textToUtf8($keterangan),
        'tagihan'    => textToUtf8($tagihan),
        'biaya'      => safeFloat($biaya),
        'jumlah'     => safeFloat($jumlah),
        'tambahan'   => safeFloat($tambahan),
        'total'      => safeFloat($total),
        'is_header'  => $is_header
    ];
    if (!$is_header) $grand_total += safeFloat($total);
}

try {
    // A. REGISTRASI
    $q_reg = safe_query($koneksi, "SELECT rp.biaya_reg, k.kd_kamar, b.nm_bangsal FROM reg_periksa rp LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE rp.no_rawat='$no_rawat' LIMIT 1");
    if($q_reg && $r = $q_reg->fetch_assoc()){
        if(!empty($r['nm_bangsal'])) addRow($rows, $grand_total, "Bangsal/Kamar", ": " . $r['nm_bangsal'], 0, 0, 0, 0, true);
        if(safeFloat($r['biaya_reg']) > 0) {
            $val = safeFloat($r['biaya_reg']);
            addRow($rows, $grand_total, "Registrasi", "Biaya Pendaftaran", $val, 1, 0, $val);
            $sum_reg += $val;
        }
    }

    // B. DOKTER
    $sql_dr = "SELECT d.nm_dokter FROM rawat_inap_dr rid JOIN dokter d ON rid.kd_dokter = d.kd_dokter WHERE rid.no_rawat='$no_rawat' GROUP BY rid.kd_dokter UNION SELECT d.nm_dokter FROM rawat_jl_dr rjd JOIN dokter d ON rjd.kd_dokter = d.kd_dokter WHERE rjd.no_rawat='$no_rawat' GROUP BY rjd.kd_dokter";
    $q_dr = safe_query($koneksi, $sql_dr);
    if($q_dr && $q_dr->num_rows > 0){
        addRow($rows, $grand_total, "Dokter", ":", 0, 0, 0, 0, true);
        while($r = $q_dr->fetch_assoc()) addRow($rows, $grand_total, "", $r['nm_dokter'], 0, 0, 0, 0, true);
    }

    // C. KAMAR INAP
    $sql_kamar = "SELECT k.kd_kamar, b.nm_bangsal, k.trf_kamar, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar, ki.stts_pulang, ki.lama, ki.ttl_biaya FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar = k.kd_kamar JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.no_rawat='$no_rawat'";
    $q_kamar = safe_query($koneksi, $sql_kamar);
    if ($q_kamar) {
        while($r = $q_kamar->fetch_assoc()) {
            $tgl_masuk = $r['tgl_masuk'];
            $tgl_keluar = ($r['tgl_keluar'] != '0000-00-00') ? $r['tgl_keluar'] : date('Y-m-d');
            
            $d1 = new DateTime($tgl_masuk);
            $d2 = new DateTime($tgl_keluar);
            $diff = $d2->diff($d1);
            $hari_raw = $diff->days;

            if ($setting_kamar['hariawal'] == 'yes') {
                $hari = $hari_raw + 1;
            } else {
                $hari = $hari_raw; // Bisa 0 jika hari yang sama
            }
            
            if (safeFloat($r['ttl_biaya']) > 0 && safeFloat($r['lama']) > 0) $hari = safeFloat($r['lama']);

            $biaya_kamar = $hari * safeFloat($r['trf_kamar']);
            
            if($biaya_kamar > 0 || $hari > 0) {
                addRow($rows, $grand_total, "Kamar Inap", $r['nm_bangsal'], $r['trf_kamar'], $hari, 0, $biaya_kamar);
                $sum_kamar += $biaya_kamar;
            }

            $kd = $koneksi->real_escape_string($r['kd_kamar']);
            $q_s = safe_query($koneksi, "SELECT nama_biaya, besar_biaya FROM biaya_sekali WHERE kd_kamar='$kd'");
            if($q_s) while($rs = $q_s->fetch_assoc()) {
                 $val = safeFloat($rs['besar_biaya']);
                 addRow($rows, $grand_total, "  + Biaya Awal", $rs['nama_biaya'], $val, 1, 0, $val);
                 $sum_harian += $val;
            }

            $q_h = safe_query($koneksi, "SELECT nama_biaya, besar_biaya FROM biaya_harian WHERE kd_kamar='$kd'");
            if($q_h) while($rh = $q_h->fetch_assoc()) {
                $val = $hari * safeFloat($rh['besar_biaya']);
                addRow($rows, $grand_total, "  + Biaya Harian", $rh['nama_biaya'], $rh['besar_biaya'], $hari, 0, $val);
                $sum_harian += $val;
            }
        }
    }

    // D. OBAT & BHP
    $q_ol = safe_query($koneksi, "SELECT besar_tagihan FROM tagihan_obat_langsung WHERE no_rawat='$no_rawat'");
    if($q_ol && $r = $q_ol->fetch_assoc()) {
        $val = safeFloat($r['besar_tagihan']);
        addRow($rows, $grand_total, "Obat & BHP", "Tagihan Obat Langsung", $val, 1, 0, $val);
        $sum_obat += $val;
    }
    
    $q_oop = safe_query($koneksi, "SELECT o.nm_obat, b.hargasatuan, b.jumlah, (b.hargasatuan * b.jumlah) as total FROM beri_obat_operasi b JOIN obatbhp_ok o ON b.kd_obat = o.kd_obat WHERE b.no_rawat='$no_rawat'");
    if($q_oop) while($r = $q_oop->fetch_assoc()) {
        $val = safeFloat($r['total']);
        addRow($rows, $grand_total, "BHP Operasi", $r['nm_obat'], $r['hargasatuan'], $r['jumlah'], 0, $val);
        $sum_obat += $val;
    }

    $q_dpo = safe_query($koneksi, "SELECT d.nama_brng, dp.biaya_obat, dp.jml, (dp.embalase + dp.tuslah) as tambahan, dp.total FROM detail_pemberian_obat dp JOIN databarang d ON dp.kode_brng = d.kode_brng WHERE dp.no_rawat='$no_rawat'");
    if($q_dpo) {
        while($r = $q_dpo->fetch_assoc()){
            $val = safeFloat($r['total']);
            addRow($rows, $grand_total, "Obat/Alkes", $r['nama_brng'], $r['biaya_obat'], $r['jml'], $r['tambahan'], $val);
            $sum_obat += $val;
        }
    }

    $sql_retur = "SELECT d.nama_brng, r.jml, (r.jml * d.ralan) as total_estimasi FROM returpasien r JOIN databarang d ON r.kode_brng = d.kode_brng WHERE r.no_rawat='$no_rawat'";
    $q_ret = safe_query($koneksi, $sql_retur);
    if($q_ret) {
        while($r = $q_ret->fetch_assoc()){
            $val_total = safeFloat($r['total_estimasi']);
            addRow($rows, $grand_total, "Retur Obat", $r['nama_brng'], 0, $r['jml'], 0, (-1 * abs($val_total)));
            $sum_retur += abs($val_total);
        }
    }

    if ($pakai_ppn) {
        $obat_bersih = $sum_obat - $sum_retur;
        if ($obat_bersih > 0) {
            $ppn_rp = round($obat_bersih * 0.11); 
            addRow($rows, $grand_total, "PPN Obat", "PPN 11% (Obat - Retur)", $ppn_rp, 1, 0, $ppn_rp);
        }
    }

    // E. TINDAKAN (DIPISAH RALAN/RANAP AGAR TIDAK DOUBLE COUNTING)
    $sql_tind = "SELECT 'Ralan Dokter' as kat, j.nm_perawatan, t.biaya_rawat as biaya, 1 as jml, t.biaya_rawat as total FROM rawat_jl_dr t JOIN jns_perawatan j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ralan Paramedis', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_jl_pr t JOIN jns_perawatan j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ralan Dr+Pr', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_jl_drpr t JOIN jns_perawatan j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ranap Dokter', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_inap_dr t JOIN jns_perawatan_inap j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ranap Paramedis', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_inap_pr t JOIN jns_perawatan_inap j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ranap Dr+Pr', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_inap_drpr t JOIN jns_perawatan_inap j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Laboratorium', j.nm_perawatan, t.biaya, 1, t.biaya FROM periksa_lab t JOIN jns_perawatan_lab j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Radiologi', j.nm_perawatan, t.biaya, 1, t.biaya FROM periksa_radiologi t JOIN jns_perawatan_radiologi j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'";
    
    $q_tind = safe_query($koneksi, $sql_tind);
    if($q_tind) while($r = $q_tind->fetch_assoc()) {
        $val = safeFloat($r['total']);
        addRow($rows, $grand_total, $r['kat'], $r['nm_perawatan'], $r['biaya'], $r['jml'], 0, $val);
        
        $kat = strtolower($r['kat']);
        // AKUMULASI PINTAR (MEMISAHKAN RALAN & RANAP)
        if(strpos($kat, 'lab') !== false) $sum_lab += $val;
        else if(strpos($kat, 'radiologi') !== false) $sum_rad += $val;
        
        // Ralan
        else if(strpos($kat, 'ralan') !== false && strpos($kat, 'dokter') !== false) $sum_dr_ralan += $val;
        else if(strpos($kat, 'ralan') !== false && strpos($kat, 'paramedis') !== false) $sum_pr_ralan += $val;
        else if(strpos($kat, 'ralan') !== false && strpos($kat, 'dr+pr') !== false) $sum_dr_ralan += $val; // Asumsi masuk dr
        
        // Ranap
        else if(strpos($kat, 'ranap') !== false && strpos($kat, 'dokter') !== false) $sum_dr_ranap += $val;
        else if(strpos($kat, 'ranap') !== false && strpos($kat, 'paramedis') !== false) $sum_pr_ranap += $val;
        else if(strpos($kat, 'ranap') !== false && strpos($kat, 'dr+pr') !== false) $sum_dr_ranap += $val; // Asumsi masuk dr
    }

    // F. OPERASI
    $q_op = safe_query($koneksi, "SELECT p.nm_perawatan, o.* FROM operasi o JOIN paket_operasi p ON o.kode_paket = p.kode_paket WHERE o.no_rawat='$no_rawat'");
    if($q_op) {
        while($r = $q_op->fetch_assoc()){
            addRow($rows, $grand_total, "Tindakan Operasi", $r['nm_perawatan'], 0, 0, 0, 0, true);
            $komponen = ['biayaoperator1','biayaoperator2','biayaoperator3','biayaasisten_operator1','biayaasisten_operator2','biayadokter_anestesi','biayaasisten_anestesi','biayasewaok','biayaalat','akomodasi','bagian_rs','biaya_omloop','biayasarpras','biaya_dokter_anak','biayaperawaat_resusitas','biayabidan'];
            foreach($komponen as $k) { 
                if(safeFloat($r[$k]) > 0) {
                    $val = safeFloat($r[$k]);
                    addRow($rows, $grand_total, " - Komponen", $k, $val, 1, 0, $val);
                    $sum_op += $val;
                }
            }
        }
    }

    // G. TAMBAHAN & POTONGAN
    $q_add = safe_query($koneksi, "SELECT nama_biaya, besar_biaya FROM tambahan_biaya WHERE no_rawat='$no_rawat'");
    if($q_add) while($r = $q_add->fetch_assoc()) {
        $val = safeFloat($r['besar_biaya']);
        addRow($rows, $grand_total, "Biaya Tambahan", $r['nama_biaya'], $val, 1, 0, $val);
        $sum_tambah += $val;
    }

    $q_min = safe_query($koneksi, "SELECT nama_pengurangan, besar_pengurangan FROM pengurangan_biaya WHERE no_rawat='$no_rawat'");
    if($q_min) while($r = $q_min->fetch_assoc()) {
        $val = (-1 * abs(safeFloat($r['besar_pengurangan'])));
        addRow($rows, $grand_total, "Potongan Biaya", $r['nama_pengurangan'], $r['besar_pengurangan'], 1, 0, $val);
        $sum_potong += $val;
    }

    // H. JASA ADMINISTRASI MEDIS (REVISI: LOGIKA PILAH SETTING)
    // Hanya hitung jika pasien RANAP (sesuai logika Khanza umumnya)
    if($status_lanjut == 'Ranap') {
        $tabel_service = 'set_service_ranap'; // Default
        // Logika Piutang: Jika penjamin bukan umum/strip
        if($kd_pj != '-' && $kd_pj != 'UMUM' && $kd_pj != 'A01') $tabel_service = 'set_service_ranap_piutang';
        
        $q_serv = safe_query($koneksi, "SELECT * FROM $tabel_service LIMIT 1");
        if($q_serv && $s = $q_serv->fetch_assoc()) {
            $total_basis = 0;
            
            if($s['laborat'] == 'Yes') $total_basis += $sum_lab;
            if($s['radiologi'] == 'Yes') $total_basis += $sum_rad;
            if($s['operasi'] == 'Yes') $total_basis += $sum_op;
            if($s['obat'] == 'Yes') $total_basis += ($sum_obat - $sum_retur);
            
            // FIX: Hanya tambahkan RANAP jika ranap=Yes, RALAN jika ralan=Yes
            if($s['ranap_dokter'] == 'Yes') $total_basis += $sum_dr_ranap;
            if($s['ranap_paramedis'] == 'Yes') $total_basis += $sum_pr_ranap;
            if($s['ralan_dokter'] == 'Yes') $total_basis += $sum_dr_ralan;
            if($s['ralan_paramedis'] == 'Yes') $total_basis += $sum_pr_ralan;
            
            if($s['tambahan'] == 'Yes') $total_basis += $sum_tambah;
            if($s['potongan'] == 'Yes') $total_basis += $sum_potong; 
            if($s['kamar'] == 'Yes') $total_basis += $sum_kamar;
            if($s['registrasi'] == 'Yes') $total_basis += $sum_reg;
            if($s['harian'] == 'Yes') $total_basis += $sum_harian;
            
            $persen = safeFloat($s['besar']);
            if($total_basis > 0 && $persen > 0) {
                $biaya_jasa = round($total_basis * ($persen / 100));
                
                // Cek duplikasi di billing real
                $cek_double = safe_query($koneksi, "SELECT totalbiaya FROM billing WHERE no_rawat='$no_rawat' AND (nm_perawatan LIKE '%Administrasi%' OR nm_perawatan LIKE '%Service%')");
                if($cek_double && $cek_double->num_rows > 0) {
                     // Sudah ada di billing real, jangan double charge
                } else {
                     addRow($rows, $grand_total, "Jasa Administrasi", $s['nama_service'] . " ($persen%)", $biaya_jasa, 1, 0, $biaya_jasa);
                }
            }
        }
    }

    sendResponse([
        'data' => $rows,
        'total_rupiah' => number_format((float)$grand_total, 0, ',', '.'),
        'total_raw' => $grand_total
    ]);

} catch (Exception $e) {
    sendResponse(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>