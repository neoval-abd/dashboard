<?php
/*
 * File: api/data_triase_igd.php
 * Fungsi: Mengambil data triase IGD dari tabel Khanza untuk RL 3.3.
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

function is_valid_date_triase($value) {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
}

function dash_if_empty($value) {
    return ($value === null || $value === '') ? '-' : $value;
}

$tgl_awal  = is_valid_date_triase($_GET['tgl_awal'] ?? '') ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = is_valid_date_triase($_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');
$keyword   = trim($_GET['keyword'] ?? '');

$where = "WHERE data_triase_igd.tgl_kunjungan BETWEEN ? AND ?";
$types = "ss";
$params = [$tgl_awal . ' 00:00:00', $tgl_akhir . ' 23:59:59'];

if ($keyword !== '') {
    $where .= " AND (
        reg_periksa.no_rawat LIKE ? OR
        pasien.no_rkm_medis LIKE ? OR
        pasien.nm_pasien LIKE ? OR
        data_triase_igd.cara_masuk LIKE ? OR
        data_triase_igd.alat_transportasi LIKE ? OR
        data_triase_igd.alasan_kedatangan LIKE ? OR
        data_triase_igd.keterangan_kedatangan LIKE ? OR
        master_triase_macam_kasus.macam_kasus LIKE ?
    )";
    $types .= str_repeat('s', 8);
    $like = '%' . $keyword . '%';
    for ($i = 0; $i < 8; $i++) {
        $params[] = $like;
    }
}

$sql = "
    SELECT
        reg_periksa.no_rawat,
        pasien.no_rkm_medis,
        pasien.nm_pasien,
        pasien.jk,
        TIMESTAMPDIFF(YEAR, pasien.tgl_lahir, CURDATE()) AS umur,
        data_triase_igd.tgl_kunjungan,
        data_triase_igd.cara_masuk,
        data_triase_igd.alat_transportasi,
        data_triase_igd.alasan_kedatangan,
        data_triase_igd.keterangan_kedatangan,
        data_triase_igd.kode_kasus,
        master_triase_macam_kasus.macam_kasus,
        data_triase_igd.tekanan_darah,
        data_triase_igd.nadi,
        data_triase_igd.pernapasan,
        data_triase_igd.suhu,
        data_triase_igd.saturasi_o2,
        data_triase_igd.nyeri,
        data_triase_igdsekunder.anamnesa_singkat,
        pegawai_sekunder.nama AS petugas_sekunder
    FROM reg_periksa
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN data_triase_igd ON reg_periksa.no_rawat = data_triase_igd.no_rawat
    INNER JOIN master_triase_macam_kasus ON data_triase_igd.kode_kasus = master_triase_macam_kasus.kode_kasus
    LEFT JOIN data_triase_igdsekunder ON data_triase_igd.no_rawat = data_triase_igdsekunder.no_rawat
    LEFT JOIN pegawai pegawai_sekunder ON data_triase_igdsekunder.nik = pegawai_sekunder.nik
    $where
    ORDER BY data_triase_igd.tgl_kunjungan DESC, reg_periksa.no_rawat DESC";

$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Query triase tidak dapat diproses.',
        'data' => [],
        'summary' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$bind = [$types];
foreach ($params as $key => $value) {
    $bind[] = &$params[$key];
}
call_user_func_array([$stmt, 'bind_param'], $bind);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$summary = [
    'total' => 0,
    'sekunder' => 0,
    'kasus' => []
];

while ($row = $result->fetch_assoc()) {
    if (!empty($row['anamnesa_singkat'])) {
        $summary['sekunder']++;
    }

    $kasus = dash_if_empty($row['macam_kasus']);
    if (!isset($summary['kasus'][$kasus])) {
        $summary['kasus'][$kasus] = 0;
    }
    $summary['kasus'][$kasus]++;

    $data[] = [
        'no_rawat' => dash_if_empty($row['no_rawat']),
        'no_rkm_medis' => dash_if_empty($row['no_rkm_medis']),
        'nm_pasien' => dash_if_empty($row['nm_pasien']),
        'jk' => dash_if_empty($row['jk']),
        'umur' => $row['umur'] !== null ? $row['umur'] . ' th' : '-',
        'tgl_kunjungan' => dash_if_empty($row['tgl_kunjungan']),
        'cara_masuk' => dash_if_empty($row['cara_masuk']),
        'alat_transportasi' => dash_if_empty($row['alat_transportasi']),
        'alasan_kedatangan' => dash_if_empty($row['alasan_kedatangan']),
        'keterangan_kedatangan' => dash_if_empty($row['keterangan_kedatangan']),
        'kode_kasus' => dash_if_empty($row['kode_kasus']),
        'macam_kasus' => dash_if_empty($row['macam_kasus']),
        'tekanan_darah' => dash_if_empty($row['tekanan_darah']),
        'nadi' => dash_if_empty($row['nadi']),
        'pernapasan' => dash_if_empty($row['pernapasan']),
        'suhu' => dash_if_empty($row['suhu']),
        'saturasi_o2' => dash_if_empty($row['saturasi_o2']),
        'nyeri' => dash_if_empty($row['nyeri']),
        'anamnesa_singkat' => dash_if_empty($row['anamnesa_singkat']),
        'petugas_sekunder' => dash_if_empty($row['petugas_sekunder'])
    ];
}

$stmt->close();

arsort($summary['kasus']);
$summary['total'] = count($data);
$summary['kasus_terbanyak'] = count($summary['kasus']) > 0 ? array_key_first($summary['kasus']) : '-';

echo json_encode([
    'success' => true,
    'data' => $data,
    'summary' => $summary
], JSON_UNESCAPED_UNICODE);
