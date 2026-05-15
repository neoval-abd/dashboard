<?php
require_once('../config/koneksi.php');

header('Content-Type: application/json');

// Ambil parameter tanggal
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$keyword = isset($_GET['keyword']) ? $koneksi->real_escape_string($_GET['keyword']) : '';

$response = [
    'summary' => [
        'avg_daftar_periksa' => 0,
        'avg_resep_obat' => 0,
        'avg_total_tat' => 0,
        'avg_total_kasir' => 0,
        'jml_pasien' => 0
    ],
    'chart' => [
        'labels' => [],
        'data_daftar_periksa' => [],
        'data_resep_obat' => [],
        'data_total_kasir' => []
    ],
    'data' => []
];

// Query Base: Mengambil data registrasi dan titik waktu pertama dari poli & resep
// (Kita modifikasi dari panduan agar subquery langsung di-JOIN/SELECT)
$sql = "SELECT 
    reg_periksa.no_rawat,
    reg_periksa.no_rkm_medis,
    pasien.nm_pasien,
    poliklinik.nm_poli,
    dokter.nm_dokter,
    penjab.png_jawab,
    reg_periksa.tgl_registrasi,
    reg_periksa.jam_reg,
    reg_periksa.status_lanjut,
    
    (SELECT jam_rawat FROM pemeriksaan_ralan WHERE no_rawat = reg_periksa.no_rawat ORDER BY tgl_perawatan ASC, jam_rawat ASC LIMIT 1) as jam_periksa,
    (SELECT jam_peresepan FROM resep_obat WHERE no_rawat = reg_periksa.no_rawat ORDER BY tgl_peresepan ASC, jam_peresepan ASC LIMIT 1) as jam_resep,
    (SELECT jam FROM resep_obat WHERE no_rawat = reg_periksa.no_rawat ORDER BY tgl_peresepan ASC, jam_peresepan ASC LIMIT 1) as jam_selesai_obat,
    (SELECT jam FROM nota_jalan WHERE no_rawat = reg_periksa.no_rawat ORDER BY tanggal ASC, jam ASC LIMIT 1) as jam_kasir
    
FROM reg_periksa 
INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis 
INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli 
INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter 
INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj 
WHERE reg_periksa.stts <> 'Batal' 
  AND reg_periksa.tgl_registrasi BETWEEN '$tgl_awal' AND '$tgl_akhir'";

$kd_pj = isset($_GET['kd_pj']) ? $koneksi->real_escape_string($_GET['kd_pj']) : '';
if (!empty($kd_pj)) {
    $sql .= " AND reg_periksa.kd_pj = '$kd_pj'";
}

if (!empty($keyword)) {
    $sql .= " AND (reg_periksa.no_rawat LIKE '%$keyword%' OR reg_periksa.no_rkm_medis LIKE '%$keyword%' OR pasien.nm_pasien LIKE '%$keyword%')";
}

$sql .= " ORDER BY reg_periksa.tgl_registrasi ASC, reg_periksa.jam_reg ASC";

$result = $koneksi->query($sql);

if ($result) {
    $sum_daftar_periksa = 0;
    $count_daftar_periksa = 0;
    
    $sum_resep_obat = 0;
    $count_resep_obat = 0;
    
    $sum_total_tat = 0;
    $count_total_tat = 0;

    $sum_total_kasir = 0;
    $count_total_kasir = 0;

    // Untuk grouping chart harian
    $daily_stats = [];

    while ($row = $result->fetch_assoc()) {
        $tgl_reg = $row['tgl_registrasi'];
        $jam_reg = $row['jam_reg'];
        $jam_periksa = $row['jam_periksa'];
        $jam_resep = $row['jam_resep'];
        $jam_selesai = $row['jam_selesai_obat'];
        $jam_kasir = $row['jam_kasir'];

        // Init Daily Data
        if (!isset($daily_stats[$tgl_reg])) {
            $daily_stats[$tgl_reg] = [
                'sum_dp' => 0, 'cnt_dp' => 0,
                'sum_ro' => 0, 'cnt_ro' => 0,
                'sum_ks' => 0, 'cnt_ks' => 0
            ];
        }

        // Hitung Daftar -> Periksa (Poli)
        $durasi_dp = null;
        if (!empty($jam_periksa)) {
            $detik = strtotime($jam_periksa) - strtotime($jam_reg);
            if ($detik >= 0) {
                $durasi_dp = round($detik / 60, 2);
                $sum_daftar_periksa += $durasi_dp;
                $count_daftar_periksa++;
                
                $daily_stats[$tgl_reg]['sum_dp'] += $durasi_dp;
                $daily_stats[$tgl_reg]['cnt_dp']++;
            }
        }

        // Hitung Resep -> Selesai Obat (Farmasi)
        $durasi_ro = null;
        if (!empty($jam_resep) && !empty($jam_selesai)) {
            $detik = strtotime($jam_selesai) - strtotime($jam_resep);
            if ($detik >= 0) {
                $durasi_ro = round($detik / 60, 2);
                $sum_resep_obat += $durasi_ro;
                $count_resep_obat++;
                
                $daily_stats[$tgl_reg]['sum_ro'] += $durasi_ro;
                $daily_stats[$tgl_reg]['cnt_ro']++;
            }
        }

        // Hitung Total Waktu (Daftar -> Selesai Obat)
        $durasi_total = null;
        if (!empty($jam_selesai) && !empty($jam_reg)) {
            $detik = strtotime($jam_selesai) - strtotime($jam_reg);
            if ($detik >= 0) {
                $durasi_total = round($detik / 60, 2);
                $sum_total_tat += $durasi_total;
                $count_total_tat++;
            }
        }

        // Hitung Total Waktu (Daftar -> Kasir)
        $durasi_kasir = null;
        if (!empty($jam_kasir) && !empty($jam_reg)) {
            $detik = strtotime($jam_kasir) - strtotime($jam_reg);
            if ($detik >= 0) {
                $durasi_kasir = round($detik / 60, 2);
                $sum_total_kasir += $durasi_kasir;
                $count_total_kasir++;
                
                $daily_stats[$tgl_reg]['sum_ks'] += $durasi_kasir;
                $daily_stats[$tgl_reg]['cnt_ks']++;
            }
        }

        $row['durasi_dp'] = $durasi_dp;
        $row['durasi_ro'] = $durasi_ro;
        $row['durasi_total'] = $durasi_total;
        $row['durasi_kasir'] = $durasi_kasir;
        $row['is_ranap'] = ($row['status_lanjut'] == 'Ranap') ? true : false;

        $response['data'][] = $row;
    }

    // Kalkulasi Rata-rata Total KPI
    $response['summary']['jml_pasien'] = count($response['data']);
    if ($count_daftar_periksa > 0) $response['summary']['avg_daftar_periksa'] = round($sum_daftar_periksa / $count_daftar_periksa, 1);
    if ($count_resep_obat > 0) $response['summary']['avg_resep_obat'] = round($sum_resep_obat / $count_resep_obat, 1);
    if ($count_total_tat > 0) $response['summary']['avg_total_tat'] = round($sum_total_tat / $count_total_tat, 1);
    if ($count_total_kasir > 0) $response['summary']['avg_total_kasir'] = round($sum_total_kasir / $count_total_kasir, 1);

    // Format Data Chart
    foreach ($daily_stats as $tgl => $st) {
        $response['chart']['labels'][] = date('d-M', strtotime($tgl));
        
        $avg_dp = ($st['cnt_dp'] > 0) ? round($st['sum_dp'] / $st['cnt_dp'], 1) : 0;
        $avg_ro = ($st['cnt_ro'] > 0) ? round($st['sum_ro'] / $st['cnt_ro'], 1) : 0;
        $avg_ks = ($st['cnt_ks'] > 0) ? round($st['sum_ks'] / $st['cnt_ks'], 1) : 0;
        
        $response['chart']['data_daftar_periksa'][] = $avg_dp;
        $response['chart']['data_resep_obat'][] = $avg_ro;
        $response['chart']['data_total_kasir'][] = $avg_ks;
    }
} else {
    // Log db error for backend debugging if needed
    // error_log("DB Query Error: " . $koneksi->error);
}

echo json_encode($response);
?>
