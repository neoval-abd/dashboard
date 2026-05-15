<?php
require_once('../config/koneksi.php');

header('Content-Type: application/json');

// Ambil parameter filter
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_pj = isset($_GET['kd_pj']) ? $koneksi->real_escape_string($_GET['kd_pj']) : '';

$response = [
    'summary' => [
        'total_kunjungan' => 0,
        'total_pasien_baru' => 0,
        'total_pasien_lama' => 0
    ],
    'chart' => [
        'kabupaten' => ['labels' => [], 'data' => []],
        'kecamatan' => ['labels' => [], 'data' => []],
        'kelurahan' => ['labels' => [], 'data' => []]
    ],
    'data' => [] // Data tabel detail per kelurahan
];

// Query Utama: Kunjungan Pasien dengan Agregasi Kelurahan, Kecamatan, dan Kabupaten
// Kita menggunakan reg_periksa karena ini menunjukkan real kunjungan (bukan sekedar master pasien).
// Asumsi status pasien Baru/Lama tercatat di reg_periksa.status_poli
$sql = "SELECT 
    kel.nm_kel, 
    kec.nm_kec, 
    kab.nm_kab,
    COUNT(rp.no_rawat) as total_kunjungan,
    SUM(CASE WHEN rp.status_poli = 'Baru' THEN 1 ELSE 0 END) as kunjungan_baru,
    SUM(CASE WHEN rp.status_poli = 'Lama' THEN 1 ELSE 0 END) as kunjungan_lama
FROM reg_periksa rp
INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
INNER JOIN kelurahan kel ON p.kd_kel = kel.kd_kel
INNER JOIN kecamatan kec ON p.kd_kec = kec.kd_kec
INNER JOIN kabupaten kab ON p.kd_kab = kab.kd_kab
WHERE rp.stts <> 'Batal' 
  AND rp.tgl_registrasi BETWEEN '$tgl_awal' AND '$tgl_akhir'";

if (!empty($kd_pj)) {
    $sql .= " AND rp.kd_pj = '$kd_pj'";
}

// Group By Kelurahan (paling spesifik)
$sql .= " GROUP BY kel.kd_kel, kel.nm_kel, kec.kd_kec, kec.nm_kec, kab.kd_kab, kab.nm_kab
          ORDER BY total_kunjungan DESC";

$result = $koneksi->query($sql);

if ($result) {
    // Array bantu untuk agregat level lebih tinggi (Chart)
    $agg_kab = [];
    $agg_kec = [];

    while ($row = $result->fetch_assoc()) {
        $jml = (int)$row['total_kunjungan'];
        $baru = (int)$row['kunjungan_baru'];
        $lama = (int)$row['kunjungan_lama'];

        // Summary Total
        $response['summary']['total_kunjungan'] += $jml;
        $response['summary']['total_pasien_baru'] += $baru;
        $response['summary']['total_pasien_lama'] += $lama;

        // Data Table
        $response['data'][] = [
            'nm_kel' => $row['nm_kel'],
            'nm_kec' => $row['nm_kec'],
            'nm_kab' => $row['nm_kab'],
            'baru' => $baru,
            'lama' => $lama,
            'total' => $jml
        ];

        // Agregat Kabupaten
        $kab_name = $row['nm_kab'];
        if (!isset($agg_kab[$kab_name])) $agg_kab[$kab_name] = 0;
        $agg_kab[$kab_name] += $jml;

        // Agregat Kecamatan
        $kec_name = $row['nm_kec'] . ' (' . $kab_name . ')';
        if (!isset($agg_kec[$kec_name])) $agg_kec[$kec_name] = 0;
        $agg_kec[$kec_name] += $jml;
    }

    // --- Persiapan Data Chart Top 5 & 10 ---

    // 1. Chart Kabupaten (Pie - Top 5)
    arsort($agg_kab);
    $count_kab = 0;
    foreach ($agg_kab as $kab => $val) {
        if ($count_kab < 5) {
            $response['chart']['kabupaten']['labels'][] = $kab;
            $response['chart']['kabupaten']['data'][] = $val;
        } else {
            // Gabungkan sisanya ke "Lainnya"
            if (!isset($response['chart']['kabupaten']['labels'][5])) {
                $response['chart']['kabupaten']['labels'][5] = 'Kabupaten Lainnya';
                $response['chart']['kabupaten']['data'][5] = 0;
            }
            $response['chart']['kabupaten']['data'][5] += $val;
        }
        $count_kab++;
    }

    // 2. Chart Kecamatan (Horizontal Bar - Top 10)
    arsort($agg_kec);
    $response['chart']['kecamatan']['labels'] = array_slice(array_keys($agg_kec), 0, 10);
    $response['chart']['kecamatan']['data'] = array_slice(array_values($agg_kec), 0, 10);

    // 3. Chart Kelurahan (Vertical Bar - Top 10)
    // Data list table sudah terurut, tinggal ambil 10 teratas
    for ($i = 0; $i < min(10, count($response['data'])); $i++) {
        $response['chart']['kelurahan']['labels'][] = $response['data'][$i]['nm_kel'] . ' - ' . $response['data'][$i]['nm_kec'];
        $response['chart']['kelurahan']['data'][] = $response['data'][$i]['total'];
    }
}

echo json_encode($response);
?>
