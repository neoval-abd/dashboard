<?php
/*
 * File: api/data_ketepatan_dokter.php
 * Deskripsi: API untuk menghitung dan melacak ketepatan waktu kedatangan/pelayanan dokter
 *            berdasarkan jadwal dokter dan inputan pertama (SOAP Ralan, SOAP Ranap, dll).
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid atau telah berakhir.']);
    exit;
}

// 1. Ambil Parameter Filter
$tgl_awal    = isset($_GET['tgl_awal']) && !empty($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir   = isset($_GET['tgl_akhir']) && !empty($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_poli     = isset($_GET['kd_poli']) ? trim($_GET['kd_poli']) : '';
$kd_dokter   = isset($_GET['kd_dokter']) ? trim($_GET['kd_dokter']) : '';
$status_fltr = isset($_GET['status']) ? trim($_GET['status']) : '';
$toleransi   = isset($_GET['toleransi']) && is_numeric($_GET['toleransi']) ? (int)$_GET['toleransi'] : 15;

// Parameter Sumber Inputan (Modular & Extensible)
$src_soap_ralan     = isset($_GET['src_soap_ralan']) ? (int)$_GET['src_soap_ralan'] : 1;
$src_soap_ranap     = isset($_GET['src_soap_ranap']) ? (int)$_GET['src_soap_ranap'] : 1;
$src_tindakan_ralan = isset($_GET['src_tindakan_ralan']) ? (int)$_GET['src_tindakan_ralan'] : 0;
$src_tindakan_ranap = isset($_GET['src_tindakan_ranap']) ? (int)$_GET['src_tindakan_ranap'] : 0;
$src_resep          = isset($_GET['src_resep']) ? (int)$_GET['src_resep'] : 0;

// Validasi rentang tanggal agar tidak overload (maksimal 62 hari)
$diff_days = (strtotime($tgl_akhir) - strtotime($tgl_awal)) / (60 * 60 * 24);
if ($diff_days > 62) {
    echo json_encode(['status' => 'error', 'message' => 'Rentang filter maksimal 2 bulan (62 hari).']);
    exit;
}

$hari_map = [
    'Sunday'    => 'AKHAD',
    'Monday'    => 'SENIN',
    'Tuesday'   => 'SELASA',
    'Wednesday' => 'RABU',
    'Thursday'  => 'KAMIS',
    'Friday'    => 'JUMAT',
    'Saturday'  => 'SABTU'
];

$cur = strtotime($tgl_awal);
$end = strtotime($tgl_akhir);
$now_ts = time();
$today_str = date('Y-m-d');

$data_rows = [];
$summary = [
    'total_jadwal'           => 0,
    'total_hadir'            => 0,
    'total_tepat_waktu'      => 0,
    'total_terlambat_sedang' => 0,
    'total_terlambat_parah'  => 0,
    'total_belum_input'      => 0,
    'total_menit_terlambat'  => 0,
    'persentase_tepat'       => 0,
    'rata_keterlambatan'     => 0
];

$top_telat_map = []; // Untuk chart top terlambat

while ($cur <= $end) {
    $tgl_str = date('Y-m-d', $cur);
    $day_name = date('l', $cur);
    $hari_id = isset($hari_map[$day_name]) ? $hari_map[$day_name] : 'SENIN';

    // Query Jadwal Dokter pada hari tersebut
    $sql_jadwal = "SELECT j.kd_dokter, d.nm_dokter, j.kd_poli, p.nm_poli, j.jam_mulai, j.jam_selesai, j.kuota
                   FROM jadwal j
                   INNER JOIN dokter d ON j.kd_dokter = d.kd_dokter
                   INNER JOIN poliklinik p ON j.kd_poli = p.kd_poli
                   WHERE j.hari_kerja = '$hari_id' AND d.status = '1'";

    if (!empty($kd_poli)) {
        $sql_jadwal .= " AND j.kd_poli = '" . $koneksi->real_escape_string($kd_poli) . "'";
    }
    if (!empty($kd_dokter)) {
        $sql_jadwal .= " AND j.kd_dokter = '" . $koneksi->real_escape_string($kd_dokter) . "'";
    }

    $sql_jadwal .= " ORDER BY j.kd_dokter ASC, j.jam_mulai ASC";
    $res_jadwal = $koneksi->query($sql_jadwal);

    if ($res_jadwal && $res_jadwal->num_rows > 0) {
        // Kelompokkan jadwal per dokter untuk menghitung window sesi praktik
        $doc_schedules = [];
        while ($row = $res_jadwal->fetch_assoc()) {
            $doc_schedules[$row['kd_dokter']][] = $row;
        }

        foreach ($doc_schedules as $doc_id => $slots) {
            $num_slots = count($slots);

            for ($idx = 0; $idx < $num_slots; $idx++) {
                $slot = $slots[$idx];
                $summary['total_jadwal']++;

                // Tentukan batas waktu (time window) pencarian inputan dokter untuk slot ini
                $w_start = '00:00:00';
                $w_end   = '23:59:59';

                if ($num_slots > 1) {
                    if ($idx === 0) {
                        $w_start = '00:00:00';
                        $next_start = $slots[$idx + 1]['jam_mulai'];
                        $mid = (strtotime($slot['jam_selesai']) + strtotime($next_start)) / 2;
                        $w_end = date('H:i:s', $mid);
                    } elseif ($idx === $num_slots - 1) {
                        $prev_end = $slots[$idx - 1]['jam_selesai'];
                        $mid = (strtotime($prev_end) + strtotime($slot['jam_mulai'])) / 2;
                        $w_start = date('H:i:s', $mid);
                        $w_end = '23:59:59';
                    } else {
                        $prev_end = $slots[$idx - 1]['jam_selesai'];
                        $next_start = $slots[$idx + 1]['jam_mulai'];
                        $mid_start = (strtotime($prev_end) + strtotime($slot['jam_mulai'])) / 2;
                        $mid_end   = (strtotime($slot['jam_selesai']) + strtotime($next_start)) / 2;
                        $w_start = date('H:i:s', $mid_start);
                        $w_end   = date('H:i:s', $mid_end);
                    }
                }

                // Kumpulkan calon input pertama dari sumber yang aktif
                $candidates = [];

                // 1. SOAP Ralan (pemeriksaan_ralan)
                if ($src_soap_ralan) {
                    $q_ralan = $koneksi->query("SELECT pr.jam_rawat, pr.no_rawat, pas.nm_pasien, pas.no_rkm_medis, rp.jam_reg
                        FROM pemeriksaan_ralan pr
                        INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                        INNER JOIN pasien pas ON rp.no_rkm_medis = pas.no_rkm_medis
                        WHERE pr.nip = '$doc_id' 
                          AND pr.tgl_perawatan = '$tgl_str'
                          AND pr.jam_rawat BETWEEN '$w_start' AND '$w_end'
                        ORDER BY pr.jam_rawat ASC LIMIT 1");
                    if ($q_ralan && $row_r = $q_ralan->fetch_assoc()) {
                        $candidates[] = [
                            'jam'          => $row_r['jam_rawat'],
                            'source'       => 'SOAP Ralan',
                            'source_badge' => 'badge-soft-primary',
                            'source_icon'  => 'fas fa-notes-medical',
                            'no_rawat'     => $row_r['no_rawat'],
                            'nm_pasien'    => $row_r['nm_pasien'],
                            'no_rkm_medis' => $row_r['no_rkm_medis'],
                            'jam_reg'      => $row_r['jam_reg']
                        ];
                    }
                }

                // 2. SOAP Ranap (pemeriksaan_ranap - Visite DPJP/Dokter)
                if ($src_soap_ranap) {
                    $q_ranap = $koneksi->query("SELECT pr.jam_rawat, pr.no_rawat, pas.nm_pasien, pas.no_rkm_medis, rp.jam_reg
                        FROM pemeriksaan_ranap pr
                        INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                        INNER JOIN pasien pas ON rp.no_rkm_medis = pas.no_rkm_medis
                        WHERE pr.nip = '$doc_id' 
                          AND pr.tgl_perawatan = '$tgl_str'
                          AND pr.jam_rawat BETWEEN '$w_start' AND '$w_end'
                        ORDER BY pr.jam_rawat ASC LIMIT 1");
                    if ($q_ranap && $row_rn = $q_ranap->fetch_assoc()) {
                        $candidates[] = [
                            'jam'          => $row_rn['jam_rawat'],
                            'source'       => 'SOAP Ranap (Visite)',
                            'source_badge' => 'badge-soft-success',
                            'source_icon'  => 'fas fa-procedures',
                            'no_rawat'     => $row_rn['no_rawat'],
                            'nm_pasien'    => $row_rn['nm_pasien'],
                            'no_rkm_medis' => $row_rn['no_rkm_medis'],
                            'jam_reg'      => $row_rn['jam_reg']
                        ];
                    }
                }

                // 3. Tindakan Ralan (rawat_jl_dr - Opsional)
                if ($src_tindakan_ralan) {
                    $q_tind_ralan = $koneksi->query("SELECT rj.jam_rawat, rj.no_rawat, pas.nm_pasien, pas.no_rkm_medis, rp.jam_reg
                        FROM rawat_jl_dr rj
                        INNER JOIN reg_periksa rp ON rj.no_rawat = rp.no_rawat
                        INNER JOIN pasien pas ON rp.no_rkm_medis = pas.no_rkm_medis
                        WHERE rj.kd_dokter = '$doc_id' 
                          AND rj.tgl_perawatan = '$tgl_str'
                          AND rj.jam_rawat BETWEEN '$w_start' AND '$w_end'
                        ORDER BY rj.jam_rawat ASC LIMIT 1");
                    if ($q_tind_ralan && $row_tr = $q_tind_ralan->fetch_assoc()) {
                        $candidates[] = [
                            'jam'          => $row_tr['jam_rawat'],
                            'source'       => 'Tindakan Ralan',
                            'source_badge' => 'badge-soft-info',
                            'source_icon'  => 'fas fa-hand-holding-medical',
                            'no_rawat'     => $row_tr['no_rawat'],
                            'nm_pasien'    => $row_tr['nm_pasien'],
                            'no_rkm_medis' => $row_tr['no_rkm_medis'],
                            'jam_reg'      => $row_tr['jam_reg']
                        ];
                    }
                }

                // 4. Tindakan Ranap (rawat_inap_dr - Opsional)
                if ($src_tindakan_ranap) {
                    $q_tind_ranap = $koneksi->query("SELECT ri.jam_rawat, ri.no_rawat, pas.nm_pasien, pas.no_rkm_medis, rp.jam_reg
                        FROM rawat_inap_dr ri
                        INNER JOIN reg_periksa rp ON ri.no_rawat = rp.no_rawat
                        INNER JOIN pasien pas ON rp.no_rkm_medis = pas.no_rkm_medis
                        WHERE ri.kd_dokter = '$doc_id' 
                          AND ri.tgl_perawatan = '$tgl_str'
                          AND ri.jam_rawat BETWEEN '$w_start' AND '$w_end'
                        ORDER BY ri.jam_rawat ASC LIMIT 1");
                    if ($q_tind_ranap && $row_tp = $q_tind_ranap->fetch_assoc()) {
                        $candidates[] = [
                            'jam'          => $row_tp['jam_rawat'],
                            'source'       => 'Tindakan Ranap',
                            'source_badge' => 'badge-soft-secondary',
                            'source_icon'  => 'fas fa-syringe',
                            'no_rawat'     => $row_tp['no_rawat'],
                            'nm_pasien'    => $row_tp['nm_pasien'],
                            'no_rkm_medis' => $row_tp['no_rkm_medis'],
                            'jam_reg'      => $row_tp['jam_reg']
                        ];
                    }
                }

                // 5. Peresepan Obat (resep_obat - Opsional)
                if ($src_resep) {
                    $q_resep = $koneksi->query("SELECT ro.jam_peresepan as jam_rawat, ro.no_rawat, pas.nm_pasien, pas.no_rkm_medis, rp.jam_reg
                        FROM resep_obat ro
                        INNER JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
                        INNER JOIN pasien pas ON rp.no_rkm_medis = pas.no_rkm_medis
                        WHERE ro.kd_dokter = '$doc_id' 
                          AND ro.tgl_peresepan = '$tgl_str'
                          AND ro.jam_peresepan BETWEEN '$w_start' AND '$w_end'
                        ORDER BY ro.jam_peresepan ASC LIMIT 1");
                    if ($q_resep && $row_rs = $q_resep->fetch_assoc()) {
                        $candidates[] = [
                            'jam'          => $row_rs['jam_rawat'],
                            'source'       => 'Resep Obat',
                            'source_badge' => 'badge-soft-warning',
                            'source_icon'  => 'fas fa-pills',
                            'no_rawat'     => $row_rs['no_rawat'],
                            'nm_pasien'    => $row_rs['nm_pasien'],
                            'no_rkm_medis' => $row_rs['no_rkm_medis'],
                            'jam_reg'      => $row_rs['jam_reg']
                        ];
                    }
                }

                // Pilih yang paling awal di antara kandidat
                $earliest_jam   = null;
                $source         = '-';
                $source_badge   = '';
                $source_icon    = '';
                $patient_detail = null;

                if (!empty($candidates)) {
                    usort($candidates, function($a, $b) {
                        return strcmp($a['jam'], $b['jam']);
                    });
                    $earliest_jam   = $candidates[0]['jam'];
                    $source         = $candidates[0]['source'];
                    $source_badge   = $candidates[0]['source_badge'];
                    $source_icon    = $candidates[0]['source_icon'];
                    $patient_detail = $candidates[0];
                }

                // Evaluasi Selisih & Status
                $ts_jadwal = strtotime("$tgl_str {$slot['jam_mulai']}");
                $selisih_menit = null;
                $selisih_label = '-';
                $status_code = 'BELUM_INPUT';
                $status_label = 'Belum Ada Input';
                $status_badge = 'badge bg-secondary';

                if ($earliest_jam !== null) {
                    $summary['total_hadir']++;
                    $ts_first = strtotime("$tgl_str $earliest_jam");
                    $selisih_menit = round(($ts_first - $ts_jadwal) / 60);

                    if ($selisih_menit <= $toleransi) {
                        $status_code = 'TEPAT_WAKTU';
                        $summary['total_tepat_waktu']++;
                        $status_label = 'Tepat Waktu';
                        $status_badge = 'badge bg-success';
                        if ($selisih_menit <= 0) {
                            $selisih_label = abs($selisih_menit) . ' mnt lebih awal';
                        } else {
                            $selisih_label = "+$selisih_menit mnt (Toleransi)";
                        }
                    } elseif ($selisih_menit <= 30) {
                        $status_code = 'TERLAMBAT_SEDANG';
                        $summary['total_terlambat_sedang']++;
                        $summary['total_menit_terlambat'] += $selisih_menit;
                        $status_label = 'Terlambat Sedang';
                        $status_badge = 'badge bg-warning text-dark';
                        $selisih_label = "+$selisih_menit mnt";

                        $top_telat_map[$slot['nm_dokter']] = ($top_telat_map[$slot['nm_dokter']] ?? 0) + $selisih_menit;
                    } else {
                        $status_code = 'TERLAMBAT_PARAH';
                        $summary['total_terlambat_parah']++;
                        $summary['total_menit_terlambat'] += $selisih_menit;
                        $status_label = 'Terlambat > 30 Mnt';
                        $status_badge = 'badge bg-danger';
                        $selisih_label = "+$selisih_menit mnt";

                        $top_telat_map[$slot['nm_dokter']] = ($top_telat_map[$slot['nm_dokter']] ?? 0) + $selisih_menit;
                    }
                } else {
                    $summary['total_belum_input']++;
                    // Klasifikasi status jika belum ada inputan
                    if ($tgl_str < $today_str) {
                        $status_code  = 'MANGKIR';
                        $status_label = 'Tidak Ada Input';
                        $status_badge = 'badge bg-dark';
                    } elseif ($tgl_str === $today_str) {
                        if ($now_ts < $ts_jadwal) {
                            $status_code  = 'BELUM_JADWAL';
                            $status_label = 'Belum Mulai Jadwal';
                            $status_badge = 'badge bg-info text-dark';
                        } else {
                            $status_code  = 'BELUM_INPUT';
                            $status_label = 'Belum Ada Input';
                            $status_badge = 'badge bg-danger';
                        }
                    }
                }

                // Filter status jika dipilih pengguna
                if (!empty($status_fltr)) {
                    if ($status_fltr === 'TEPAT_WAKTU' && $status_code !== 'TEPAT_WAKTU') continue;
                    if ($status_fltr === 'TERLAMBAT' && !in_array($status_code, ['TERLAMBAT_SEDANG', 'TERLAMBAT_PARAH'])) continue;
                    if ($status_fltr === 'BELUM_INPUT' && !in_array($status_code, ['BELUM_INPUT', 'MANGKIR', 'BELUM_JADWAL'])) continue;
                }

                // Waktu tunggu pasien pertama (jika ada)
                $tat_pasien = '-';
                if ($patient_detail && !empty($patient_detail['jam_reg'])) {
                    $ts_reg = strtotime("$tgl_str {$patient_detail['jam_reg']}");
                    $ts_first = strtotime("$tgl_str {$patient_detail['jam']}");
                    $tat_menit = round(($ts_first - $ts_reg) / 60);
                    $tat_pasien = ($tat_menit >= 0) ? "$tat_menit mnt" : "0 mnt";
                }

                $data_rows[] = [
                    'tanggal'        => $tgl_str,
                    'hari'           => $hari_id,
                    'kd_dokter'      => $doc_id,
                    'nm_dokter'      => $slot['nm_dokter'],
                    'kd_poli'        => $slot['kd_poli'],
                    'nm_poli'        => $slot['nm_poli'],
                    'jam_mulai'      => substr($slot['jam_mulai'], 0, 5),
                    'jam_selesai'    => substr($slot['jam_selesai'], 0, 5),
                    'jam_pertama'    => $earliest_jam ? substr($earliest_jam, 0, 8) : '-',
                    'source'         => $source,
                    'source_badge'   => $source_badge,
                    'source_icon'    => $source_icon,
                    'selisih_menit'  => $selisih_menit,
                    'selisih_label'  => $selisih_label,
                    'status_code'    => $status_code,
                    'status_label'   => $status_label,
                    'status_badge'   => $status_badge,
                    'tat_pasien'     => $tat_pasien,
                    'pasien_pertama' => $patient_detail ? [
                        'no_rawat'     => $patient_detail['no_rawat'],
                        'no_rkm_medis' => $patient_detail['no_rkm_medis'],
                        'nm_pasien'    => $patient_detail['nm_pasien'],
                        'jam_reg'      => substr($patient_detail['jam_reg'], 0, 8),
                        'jam_input'    => substr($patient_detail['jam'], 0, 8),
                        'tat_menit'    => $tat_pasien
                    ] : null
                ];
            }
        }
    }

    $cur = strtotime('+1 day', $cur);
}

// Hitung persentase & rata-rata
if ($summary['total_hadir'] > 0) {
    $summary['persentase_tepat'] = round(($summary['total_tepat_waktu'] / $summary['total_hadir']) * 100, 1);
}
$total_terlambat = $summary['total_terlambat_sedang'] + $summary['total_terlambat_parah'];
if ($total_terlambat > 0) {
    $summary['rata_keterlambatan'] = round($summary['total_menit_terlambat'] / $total_terlambat, 1);
}

// Siapkan data chart top terlambat (Top 7)
arsort($top_telat_map);
$chart_top_dokter = [
    'labels' => [],
    'values' => []
];
$i = 0;
foreach ($top_telat_map as $dokter_name => $menit) {
    if ($i >= 7) break;
    $chart_top_dokter['labels'][] = $dokter_name;
    $chart_top_dokter['values'][] = $menit;
    $i++;
}

// Output Respons JSON
echo json_encode([
    'status'   => 'success',
    'summary'  => $summary,
    'chart'    => [
        'status_distribution' => [
            'labels' => ['Tepat Waktu', 'Terlambat (<= 30 mnt)', 'Terlambat (> 30 mnt)', 'Belum Ada Input'],
            'values' => [
                $summary['total_tepat_waktu'],
                $summary['total_terlambat_sedang'],
                $summary['total_terlambat_parah'],
                $summary['total_belum_input']
            ]
        ],
        'top_terlambat' => $chart_top_dokter
    ],
    'active_sources' => [
        'soap_ralan'     => $src_soap_ralan,
        'soap_ranap'     => $src_soap_ranap,
        'tindakan_ralan' => $src_tindakan_ralan,
        'tindakan_ranap' => $src_tindakan_ranap,
        'resep_obat'     => $src_resep
    ],
    'data'     => $data_rows
], JSON_UNESCAPED_SLASHES);

