<?php
/*
 * File: api/data_rincian_billing.php (FIX V26 - HYBRID 7.3 & 8.3 COMPATIBLE)
 */

ob_start();
ini_set('display_errors', 0);
error_reporting(0);
set_time_limit(0);

// PENTING: Matikan Exception MySQLi
mysqli_report(MYSQLI_REPORT_OFF);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

$koneksi->query("SET sql_mode = ''");
$no_rawat = isset($_GET['no_rawat']) ? $koneksi->real_escape_string($_GET['no_rawat']) : '';
$rows = [];
$grand_total = 0.0;

function textToUtf8($str) {
    if (is_null($str)) return "";
    $str = (string)$str;
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }
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

if(empty($no_rawat)) sendResponse(['data' => [], 'total_rupiah' => 0]);

$status_lanjut = 'Ralan'; 
$pakai_ppn = false;

$q_status = $koneksi->query("SELECT status_lanjut FROM reg_periksa WHERE no_rawat='$no_rawat'");
if($q_status && $r_st = $q_status->fetch_assoc()){
    $status_lanjut = $r_st['status_lanjut'];
}

$q_set = $koneksi->query("SELECT tampilkan_ppnobat_ralan, tampilkan_ppnobat_ranap FROM set_nota LIMIT 1");
if($q_set && $r_set = $q_set->fetch_assoc()){
    if($status_lanjut == 'Ralan' && $r_set['tampilkan_ppnobat_ralan'] == 'Yes') {
        $pakai_ppn = true;
    } else if($status_lanjut == 'Ranap' && $r_set['tampilkan_ppnobat_ranap'] == 'Yes') {
        $pakai_ppn = true;
    }
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

function safe_query($conn, $sql) {
    $res = $conn->query($sql);
    return ($res === false) ? false : $res;
}

try {
    // 1. REGISTRASI
    $q_reg = safe_query($koneksi, "SELECT rp.biaya_reg, k.kd_kamar, b.nm_bangsal FROM reg_periksa rp LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE rp.no_rawat='$no_rawat' LIMIT 1");
    if($q_reg && $r = $q_reg->fetch_assoc()){
        if(!empty($r['nm_bangsal'])) addRow($rows, $grand_total, "Bangsal/Kamar", ": " . $r['nm_bangsal'], 0, 0, 0, 0, true);
        if(safeFloat($r['biaya_reg']) > 0) addRow($rows, $grand_total, "Registrasi", "Biaya Pendaftaran", $r['biaya_reg'], 1, 0, $r['biaya_reg']);
    }

    // 2. DOKTER
    $sql_dr = "SELECT d.nm_dokter FROM rawat_inap_dr rid JOIN dokter d ON rid.kd_dokter = d.kd_dokter WHERE rid.no_rawat='$no_rawat' GROUP BY rid.kd_dokter UNION SELECT d.nm_dokter FROM rawat_jl_dr rjd JOIN dokter d ON rjd.kd_dokter = d.kd_dokter WHERE rjd.no_rawat='$no_rawat' GROUP BY rjd.kd_dokter";
    $q_dr = safe_query($koneksi, $sql_dr);
    if($q_dr && $q_dr->num_rows > 0){
        addRow($rows, $grand_total, "Dokter", ":", 0, 0, 0, 0, true);
        while($r = $q_dr->fetch_assoc()) addRow($rows, $grand_total, "", $r['nm_dokter'], 0, 0, 0, 0, true);
    }

    // 3. KAMAR INAP
    $sql_kamar = "SELECT k.kd_kamar, b.nm_bangsal, k.trf_kamar, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar, ki.stts_pulang, ki.lama, ki.ttl_biaya FROM kamar_inap ki JOIN kamar k ON ki.kd_kamar = k.kd_kamar JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal WHERE ki.no_rawat='$no_rawat'";
    $q_kamar = safe_query($koneksi, $sql_kamar);
    if ($q_kamar) {
        while($r = $q_kamar->fetch_assoc()) {
            $hari = 1;
            if (safeFloat($r['ttl_biaya']) > 0) {
                $biaya_kamar = safeFloat($r['ttl_biaya']);
                $hari = (safeFloat($r['lama']) > 0) ? $r['lama'] : 1;
            } else {
                if($r['tgl_keluar'] != '0000-00-00') { $tgl_keluar = $r['tgl_keluar']; } else { $tgl_keluar = date('Y-m-d'); }
                try {
                    $ts1 = strtotime($r['tgl_masuk'] . ' ' . $r['jam_masuk']);
                    $ts2 = strtotime($tgl_keluar . ' ' . ($r['jam_keluar'] == '00:00:00' ? date('H:i:s') : $r['jam_keluar']));
                    $hari = floor(($ts2 - $ts1) / (60 * 60 * 24));
                } catch (Exception $e) { $hari = 1; }
                if ($hari <= 0) $hari = 1;
                $biaya_kamar = $hari * safeFloat($r['trf_kamar']);
            }
            
            addRow($rows, $grand_total, "Kamar Inap", $r['nm_bangsal'], $r['trf_kamar'], $hari, 0, $biaya_kamar);

            $kd = $koneksi->real_escape_string($r['kd_kamar']);
            $q_s = safe_query($koneksi, "SELECT nama_biaya, besar_biaya FROM biaya_sekali WHERE kd_kamar='$kd'");
            if($q_s) while($rs = $q_s->fetch_assoc()) addRow($rows, $grand_total, "  + Biaya Awal", $rs['nama_biaya'], $rs['besar_biaya'], 1, 0, $rs['besar_biaya']);

            $q_h = safe_query($koneksi, "SELECT nama_biaya, besar_biaya FROM biaya_harian WHERE kd_kamar='$kd'");
            if($q_h) while($rh = $q_h->fetch_assoc()) addRow($rows, $grand_total, "  + Biaya Harian", $rh['nama_biaya'], $rh['besar_biaya'], $hari, 0, ($hari * safeFloat($rh['besar_biaya'])));
        }
    }

    // 4. OBAT & BHP
    $subtotal_obat = 0.0;
    $q_ol = safe_query($koneksi, "SELECT besar_tagihan FROM tagihan_obat_langsung WHERE no_rawat='$no_rawat'");
    if($q_ol && $r = $q_ol->fetch_assoc()) {
        $val = safeFloat($r['besar_tagihan']);
        addRow($rows, $grand_total, "Obat & BHP", "Tagihan Obat Langsung", $val, 1, 0, $val);
        $subtotal_obat += $val;
    }
    
    $q_oop = safe_query($koneksi, "SELECT o.nm_obat, b.hargasatuan, b.jumlah, (b.hargasatuan * b.jumlah) as total FROM beri_obat_operasi b JOIN obatbhp_ok o ON b.kd_obat = o.kd_obat WHERE b.no_rawat='$no_rawat'");
    if($q_oop) while($r = $q_oop->fetch_assoc()) addRow($rows, $grand_total, "BHP Operasi", $r['nm_obat'], $r['hargasatuan'], $r['jumlah'], 0, $r['total']); 

    $q_dpo = safe_query($koneksi, "SELECT d.nama_brng, dp.biaya_obat, dp.jml, (dp.embalase + dp.tuslah) as tambahan, dp.total FROM detail_pemberian_obat dp JOIN databarang d ON dp.kode_brng = d.kode_brng WHERE dp.no_rawat='$no_rawat'");
    if($q_dpo) {
        while($r = $q_dpo->fetch_assoc()){
            addRow($rows, $grand_total, "Obat/Alkes", $r['nama_brng'], $r['biaya_obat'], $r['jml'], $r['tambahan'], $r['total']);
            $subtotal_obat += safeFloat($r['total']);
        }
    }

    $subtotal_retur = 0.0;
    $sql_retur = "SELECT d.nama_brng, r.jml, (r.jml * d.ralan) as total_estimasi FROM returpasien r JOIN databarang d ON r.kode_brng = d.kode_brng WHERE r.no_rawat='$no_rawat'";
    $q_ret = safe_query($koneksi, $sql_retur);
    if($q_ret) {
        while($r = $q_ret->fetch_assoc()){
            $val_total = safeFloat($r['total_estimasi']);
            addRow($rows, $grand_total, "Retur Obat", $r['nama_brng'], 0, $r['jml'], 0, (-1 * abs($val_total)));
            $subtotal_retur += abs($val_total);
        }
    }

    if ($pakai_ppn) {
        $obat_bersih = $subtotal_obat - $subtotal_retur;
        if ($obat_bersih > 0) {
            $ppn_rp = round($obat_bersih * 0.11); 
            addRow($rows, $grand_total, "PPN Obat", "PPN 11% (Obat - Retur)", $ppn_rp, 1, 0, $ppn_rp);
        }
    }

    // 5. TINDAKAN
    $sql_tind = "SELECT 'Ralan Dokter' as kat, j.nm_perawatan, t.biaya_rawat as biaya, 1 as jml, t.biaya_rawat as total FROM rawat_jl_dr t JOIN jns_perawatan j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ralan Paramedis', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_jl_pr t JOIN jns_perawatan j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ralan Dr+Pr', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_jl_drpr t JOIN jns_perawatan j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ranap Dokter', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_inap_dr t JOIN jns_perawatan_inap j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ranap Paramedis', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_inap_pr t JOIN jns_perawatan_inap j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Ranap Dr+Pr', j.nm_perawatan, t.biaya_rawat, 1, t.biaya_rawat FROM rawat_inap_drpr t JOIN jns_perawatan_inap j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Laboratorium', j.nm_perawatan, t.biaya, 1, t.biaya FROM periksa_lab t JOIN jns_perawatan_lab j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'
    UNION ALL SELECT 'Radiologi', j.nm_perawatan, t.biaya, 1, t.biaya FROM periksa_radiologi t JOIN jns_perawatan_radiologi j ON t.kd_jenis_prw=j.kd_jenis_prw WHERE t.no_rawat='$no_rawat'";
    
    $q_tind = safe_query($koneksi, $sql_tind);
    if($q_tind) while($r = $q_tind->fetch_assoc()) addRow($rows, $grand_total, $r['kat'], $r['nm_perawatan'], $r['biaya'], $r['jml'], 0, $r['total']);

    // 6. OPERASI
    $q_op = safe_query($koneksi, "SELECT p.nm_perawatan, o.* FROM operasi o JOIN paket_operasi p ON o.kode_paket = p.kode_paket WHERE o.no_rawat='$no_rawat'");
    if($q_op) {
        while($r = $q_op->fetch_assoc()){
            addRow($rows, $grand_total, "Tindakan Operasi", $r['nm_perawatan'], 0, 0, 0, 0, true);
            $komponen = ['biayaoperator1','biayaoperator2','biayaoperator3','biayaasisten_operator1','biayaasisten_operator2','biayadokter_anestesi','biayaasisten_anestesi','biayasewaok','biayaalat','akomodasi','bagian_rs','biaya_omloop','biayasarpras','biaya_dokter_anak','biayaperawaat_resusitas','biayabidan'];
            foreach($komponen as $k) { if(safeFloat($r[$k]) > 0) addRow($rows, $grand_total, " - Komponen", $k, $r[$k], 1, 0, $r[$k]); }
        }
    }

    // 7. TAMBAHAN
    $q_add = safe_query($koneksi, "SELECT nama_biaya, besar_biaya FROM tambahan_biaya WHERE no_rawat='$no_rawat'");
    if($q_add) while($r = $q_add->fetch_assoc()) addRow($rows, $grand_total, "Biaya Tambahan", $r['nama_biaya'], $r['besar_biaya'], 1, 0, $r['besar_biaya']);

    $q_min = safe_query($koneksi, "SELECT nama_pengurangan, besar_pengurangan FROM pengurangan_biaya WHERE no_rawat='$no_rawat'");
    if($q_min) while($r = $q_min->fetch_assoc()) addRow($rows, $grand_total, "Potongan Biaya", $r['nama_pengurangan'], $r['besar_pengurangan'], 1, 0, (-1 * abs(safeFloat($r['besar_pengurangan']))));

    sendResponse([
        'data' => $rows,
        'total_rupiah' => number_format((float)$grand_total, 0, ',', '.'),
        'total_raw' => $grand_total
    ]);

} catch (Exception $e) {
    sendResponse(['status' => 'error', 'message' => 'Server Error: ' . $e->getMessage()]);
}
?>