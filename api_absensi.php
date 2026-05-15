<?php
session_start();
require_once 'config/koneksi.php';

// Proteksi
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak']);
    exit;
}

$act = isset($_GET['act']) ? $_GET['act'] : '';

// --- FUNGSI HELPER ---
function validTeks($str, $koneksi) {
    return $koneksi->real_escape_string(trim($str));
}

// 3. GET EVALUASI (Analyze)
if ($act == 'analyze') {
    $tgl1 = validTeks($_GET['tgl1'], $koneksi);
    $tgl2 = validTeks($_GET['tgl2'], $koneksi);
    $dep = validTeks($_GET['dep'], $koneksi);
    $filter = validTeks($_GET['filter'], $koneksi); // 'ALL' atau 'MANGKIR'

    $ref_jam = [];
    $q_jam = $koneksi->query("SELECT dep_id, shift, jam_masuk, jam_pulang FROM jam_jaga");
    if($q_jam){
        while($j = $q_jam->fetch_assoc()) {
            $ref_jam[$j['dep_id']][$j['shift']] = [
                'in' => substr($j['jam_masuk'], 0, 5),
                'out' => substr($j['jam_pulang'], 0, 5)
            ];
        }
    }

    $filter_dep = ($dep != 'ALL' && $dep != '') ? "AND departemen = '$dep'" : "";
    $q_peg = "SELECT id, nik, nama, departemen FROM pegawai WHERE stts_aktif = 'AKTIF' $filter_dep ORDER BY nama ASC";
    $res_peg = $koneksi->query($q_peg);
    
    $pegawai_list = [];
    if($res_peg){
        while($p = $res_peg->fetch_assoc()) {
            $pegawai_list[] = $p;
        }
    }

    $data_evaluasi = [];
    $current_date = strtotime($tgl1);
    $end_date = strtotime($tgl2);
    $now_ts = time();

    while ($current_date <= $end_date) {
        $date_sql = date('Y-m-d', $current_date);
        $is_today = ($date_sql == date('Y-m-d'));
        
        $thn = date('Y', $current_date);
        $bln = date('m', $current_date);
        $hari_angka = date('j', $current_date);
        $col_h = "h" . $hari_angka;

        $logs_rekap = [];
        $q_log = $koneksi->query("SELECT id, jam_datang, jam_pulang, shift, status FROM rekap_presensi WHERE jam_datang LIKE '$date_sql%'");
        if($q_log) { while($l = $q_log->fetch_assoc()) $logs_rekap[$l['id']] = $l; }

        $logs_temp = [];
        $q_tmp = $koneksi->query("SELECT id, jam_datang, shift, status FROM temporary_presensi WHERE jam_datang LIKE '$date_sql%'");
        if($q_tmp) { while($t = $q_tmp->fetch_assoc()) $logs_temp[$t['id']] = $t; }

        foreach ($pegawai_list as $peg) {
            $id_peg = $peg['id'];
            $dep_peg = $peg['departemen'];
            
            $jadwal_kode = '-';
            $q_add = $koneksi->query("SELECT $col_h as shift FROM jadwal_tambahan WHERE id='$id_peg' AND tahun='$thn' AND (bulan='$bln' OR bulan='".(int)$bln."')");
            if($q_add && $row_add = $q_add->fetch_assoc()){
                if(!empty($row_add['shift'])) $jadwal_kode = $row_add['shift'];
            }
            
            if($jadwal_kode == '-' || $jadwal_kode == '') {
                $q_main = $koneksi->query("SELECT $col_h as shift FROM jadwal_pegawai WHERE id='$id_peg' AND tahun='$thn' AND (bulan='$bln' OR bulan='".(int)$bln."')");
                if($q_main && $row_main = $q_main->fetch_assoc()){
                    $jadwal_kode = $row_main['shift'];
                }
            }

            if(empty($jadwal_kode)) $jadwal_kode = '-';
            
            if(in_array(strtoupper($jadwal_kode), ['-', '', 'L', 'LIBUR', 'CUTI', 'OFF'])) continue; 

            $data_log = null;
            $status_kehadiran = 'MANGKIR';
            $keterangan = 'Tidak Ada Data Absensi';

            if (isset($logs_rekap[$id_peg])) {
                $data_log = $logs_rekap[$id_peg];
                $status_kehadiran = 'HADIR';
                $keterangan = $data_log['status'];
            } elseif (isset($logs_temp[$id_peg])) {
                $data_log = $logs_temp[$id_peg];
                $status_kehadiran = 'DINAS';
                $keterangan = "Sedang Bekerja (Belum Pulang)";
                $data_log['jam_pulang'] = null; 
            }

            $jadwal_in = '-'; 
            $jadwal_out = '-';
            $ts_masuk_jadwal = 0;

            if (isset($ref_jam[$dep_peg][$jadwal_kode])) {
                $jadwal_in = $ref_jam[$dep_peg][$jadwal_kode]['in'];
                $jadwal_out = $ref_jam[$dep_peg][$jadwal_kode]['out'];
                $ts_masuk_jadwal = strtotime("$date_sql $jadwal_in");
            }

            if ($is_today && $status_kehadiran == 'MANGKIR' && $ts_masuk_jadwal > 0) {
                if ($now_ts < $ts_masuk_jadwal) {
                    $status_kehadiran = 'BELUM_WAKTUNYA';
                    $keterangan = "Jadwal Belum Dimulai";
                }
            }

            if ($filter == 'MANGKIR') {
                if ($status_kehadiran == 'HADIR' || $status_kehadiran == 'DINAS' || $status_kehadiran == 'BELUM_WAKTUNYA') continue;
            }

            $jam_masuk_akt = ($data_log) ? date('H:i', strtotime($data_log['jam_datang'])) : '-';
            $jam_pulang_akt = '-';
            
            if ($data_log && !empty($data_log['jam_pulang']) && $data_log['jam_pulang'] != '0000-00-00 00:00:00') {
                $jam_pulang_akt = date('H:i', strtotime($data_log['jam_pulang']));
            } elseif ($status_kehadiran == 'DINAS') {
                $jam_pulang_akt = 'Sedang Dinas';
            }

            $data_evaluasi[] = [
                'tanggal' => date('d M Y', $current_date),
                'nik' => $peg['nik'],
                'nama' => $peg['nama'],
                'departemen' => $peg['departemen'],
                'jadwal' => $jadwal_kode,
                'jadwal_in' => $jadwal_in,
                'jadwal_out' => $jadwal_out,
                'status_evaluasi' => $status_kehadiran,
                'jam_masuk' => $jam_masuk_akt,
                'jam_pulang' => $jam_pulang_akt,
                'keterangan' => $keterangan
            ];
        }
        $current_date = strtotime("+1 day", $current_date);
    }

    echo json_encode(['data' => $data_evaluasi]);
    exit;
}

// 2. GET DEPARTEMEN
if ($act == 'get_dep') {
    $sql = "SELECT dep_id, nama FROM departemen ORDER BY nama ASC";
    $res = $koneksi->query($sql);
    $data = [];
    if($res){
        while($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode($data);
    exit;
}

// 3. GET REKAP (Rekap Pelanggaran)
if ($act == 'rekap') {
    $tgl1 = validTeks($_GET['tgl1'], $koneksi);
    $tgl2 = validTeks($_GET['tgl2'], $koneksi);
    $dep = validTeks($_GET['dep'], $koneksi);

    $ref_jam = [];
    $q_jam = $koneksi->query("SELECT dep_id, shift, jam_masuk, jam_pulang FROM jam_jaga");
    if($q_jam) {
        while ($j = $q_jam->fetch_assoc()) {
            $ref_jam[$j['dep_id']][$j['shift']] = [
                'in' => substr($j['jam_masuk'], 0, 5),
                'out' => substr($j['jam_pulang'], 0, 5)
            ];
        }
    }

    $filter_dep = ($dep != 'ALL' && $dep != '') ? "AND departemen = '$dep'" : "";
    $q_peg = "SELECT id, nik, nama, departemen FROM pegawai WHERE stts_aktif = 'AKTIF' $filter_dep ORDER BY nama ASC";
    $res_peg = $koneksi->query($q_peg);

    $pegawai_list = [];
    if($res_peg) {
        while ($p = $res_peg->fetch_assoc()) {
            $pegawai_list[] = $p;
        }
    }

    // Pre-fetch Data Cuti
    $q_cuti = "SELECT pc.nik, pc.tanggal_awal, pc.tanggal_akhir FROM pengajuan_cuti pc WHERE pc.status_persetujuan_HRD='Disetujui' AND pc.tanggal_awal <= '$tgl2' AND pc.tanggal_akhir >= '$tgl1'";
    $r_cuti = $koneksi->query($q_cuti);
    $arr_cuti = [];
    if($r_cuti) {
        while ($c = $r_cuti->fetch_assoc()) {
            $arr_cuti[$c['nik']][] = ['awal' => strtotime($c['tanggal_awal']), 'akhir' => strtotime($c['tanggal_akhir'])];
        }
    }

    $final_data = [];
    $now_ts = time();

    foreach ($pegawai_list as $peg) {
        $id_peg = $peg['id'];
        $nik_peg = $peg['nik'];
        $dep_peg = $peg['departemen'];

        $logs_m = [];
        $q_log = "SELECT jam_datang, jam_pulang, status, keterlambatan, durasi FROM rekap_presensi WHERE id='$id_peg' AND jam_datang >= '$tgl1 00:00:00' AND jam_datang <= '$tgl2 23:59:59'";
        $r_log = $koneksi->query($q_log);
        if($r_log) {
            while ($l = $r_log->fetch_assoc()) {
                $tgl_only = date('Y-m-d', strtotime($l['jam_datang']));
                $logs_m[$tgl_only] = $l;
            }
        }

        $jml_t1 = 0; $rp_t1 = 0;
        $jml_t2 = 0; $rp_t2 = 0;
        $jml_mangkir = 0; $rp_mangkir = 0;
        $jml_toleransi = 0; $jml_cuti = 0; $jml_hadir = 0;
        $total_menit_kerja = 0;

        $rincian_hari = [];

        $current_date = strtotime($tgl1);
        $end_date = strtotime($tgl2);

        while ($current_date <= $end_date) {
            $date_str = date('Y-m-d', $current_date);
            $is_today = ($date_str == date('Y-m-d'));

            $thn = date('Y', $current_date);
            $bln = date('m', $current_date);
            $hari_angka = date('j', $current_date);
            $col_h = "h" . $hari_angka;

            $jadwal_kode = '-';
            $q_add = $koneksi->query("SELECT $col_h as shift FROM jadwal_tambahan WHERE id='$id_peg' AND tahun='$thn' AND (bulan='$bln' OR bulan='" . (int)$bln . "')");
            if ($q_add && $row_add = $q_add->fetch_assoc()) {
                if(!empty($row_add['shift'])) $jadwal_kode = $row_add['shift'];
            }

            if ($jadwal_kode == '-' || $jadwal_kode == '') {
                $q_main = $koneksi->query("SELECT $col_h as shift FROM jadwal_pegawai WHERE id='$id_peg' AND tahun='$thn' AND (bulan='$bln' OR bulan='" . (int)$bln . "')");
                if ($q_main && $row_main = $q_main->fetch_assoc()) {
                    $jadwal_kode = $row_main['shift'];
                }
            }
            if (empty($jadwal_kode)) $jadwal_kode = '-';

            $is_cuti_approved = false;
            if (isset($arr_cuti[$nik_peg])) {
                foreach ($arr_cuti[$nik_peg] as $ct) {
                    if ($current_date >= $ct['awal'] && $current_date <= $ct['akhir']) {
                        $is_cuti_approved = true;
                        break;
                    }
                }
            }

            $is_libur = in_array(strtoupper($jadwal_kode), ['-', '', 'L', 'LIBUR', 'CUTI', 'OFF']);
            $has_log = isset($logs_m[$date_str]);

            $kat_harian = 'NORMAL';
            $jadwal_in_v = '-';
            $jam_in_v = '-';
            $jam_out_v = '-';

            if (isset($ref_jam[$dep_peg][$jadwal_kode])) {
                $jadwal_in_v = $ref_jam[$dep_peg][$jadwal_kode]['in'];
            }

            if ($has_log) {
                $jml_hadir++;
                $log = $logs_m[$date_str];
                $jam_in_v = date('H:i', strtotime($log['jam_datang']));
                if ($log['jam_pulang'] != '0000-00-00 00:00:00')
                    $jam_out_v = date('H:i', strtotime($log['jam_pulang']));

                if (!empty($log['durasi']) && $log['durasi'] != '-') {
                    $parts = explode(':', $log['durasi']);
                    if (count($parts) >= 2) {
                        $jam = (int)$parts[0];
                        $menit = (int)$parts[1];
                        $total_menit_kerja += ($jam * 60) + $menit;
                    }
                }

                $stts_telat = strtolower($log['status']);
                if (strpos($stts_telat, 'terlambat i ') !== false || $stts_telat == 'terlambat i') {
                    $jml_t1++;
                    $kat_harian = 'TELAT 1';
                }
                else if (strpos($stts_telat, 'terlambat ii') !== false) {
                    $jml_t2++;
                    $kat_harian = 'TELAT 2';
                }
                else if (strpos($stts_telat, 'toleransi') !== false) {
                    $jml_toleransi++;
                    $kat_harian = 'TOLERANSI';
                }

            } else {
                if ($is_cuti_approved || strtoupper($jadwal_kode) == 'CUTI') {
                    $jml_cuti++;
                    $kat_harian = 'CUTI';
                } else if (!$is_libur) {
                    $ts_masuk = strtotime("$date_str $jadwal_in_v");
                    if ($is_today && $now_ts < $ts_masuk) {
                        $kat_harian = 'BELUM WAKTUNYA';
                    } else {
                        $q_temp = $koneksi->query("SELECT id FROM temporary_presensi WHERE id='$id_peg' AND jam_datang LIKE '$date_str%'");
                        if ($q_temp && $q_temp->num_rows == 0) {
                            $jml_mangkir++;
                            $kat_harian = 'MANGKIR';
                        } else {
                            $kat_harian = 'Sedang Aktif Bekerja';
                        }
                    }
                }
            }

            if ($kat_harian != 'NORMAL' && $kat_harian != 'BELUM WAKTUNYA' && !$is_libur) {
                $rincian_hari[] = [
                    'tanggal' => date('d/m/Y', $current_date),
                    'jadwal_in' => $jadwal_in_v,
                    'jam_in' => $jam_in_v,
                    'jam_out' => $jam_out_v,
                    'kategori' => $kat_harian
                ];
            }

            $current_date = strtotime("+1 day", $current_date);
        }

        $tot_jam = floor($total_menit_kerja / 60);
        $sisa_m = $total_menit_kerja % 60;
        $str_durasi = "{$tot_jam}j {$sisa_m}m";

        $final_data[] = [
            'nik' => $nik_peg,
            'nama' => $peg['nama'],
            'dept' => $dep_peg,
            'jml_hadir' => $jml_hadir,
            'jml_telat1' => $jml_t1,
            'jml_telat2' => $jml_t2,
            'jml_mangkir' => $jml_mangkir,
            'jml_toleransi' => $jml_toleransi,
            'jml_cuti' => $jml_cuti,
            'total_durasi' => $str_durasi,
            'rincian_hari' => $rincian_hari
        ];
    }

    // Sort by Mangkir Terbanyak (Descending)
    usort($final_data, function($a, $b) {
        return $b['jml_mangkir'] <=> $a['jml_mangkir'];
    });

    echo json_encode(['status' => 'success', 'data' => $final_data]);
    exit;
}
?>
