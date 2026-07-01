<?php
/*
 * File: api/data_bbl.php
 * Fungsi: Mengambil data Bayi Baru Lahir dari tabel pasien_bayi.
 * Sumber query disesuaikan dari DlgIKBBayi.java.
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

function is_valid_date_bbl($value) {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
}

$tgl_awal  = is_valid_date_bbl($_GET['tgl_awal'] ?? '') ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = is_valid_date_bbl($_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');
$jk        = strtoupper(trim($_GET['jk'] ?? ''));

if (!in_array($jk, ['', 'L', 'P'], true)) {
    $jk = '';
}

$where = "WHERE pasien.tgl_lahir BETWEEN ? AND ?";
$types = "ss";
$params = [$tgl_awal, $tgl_akhir];

if ($jk !== '') {
    $where .= " AND pasien.jk = ?";
    $types .= "s";
    $params[] = $jk;
}

$sql = "
    SELECT
        pasien.no_rkm_medis,
        pasien.nm_pasien,
        pasien.jk,
        pasien.tgl_lahir,
        pasien_bayi.jam_lahir,
        pasien.umur,
        pasien.tgl_daftar,
        pasien.nm_ibu,
        pasien_bayi.umur_ibu,
        pasien_bayi.nama_ayah,
        pasien_bayi.umur_ayah,
        CONCAT_WS(', ', pasien.alamat, kelurahan.nm_kel, kecamatan.nm_kec, kabupaten.nm_kab) AS alamat,
        pasien_bayi.berat_badan,
        pasien_bayi.panjang_badan,
        pasien_bayi.lingkar_kepala,
        pasien_bayi.proses_lahir,
        pasien_bayi.anakke,
        pasien_bayi.keterangan,
        pasien_bayi.diagnosa,
        pasien_bayi.penyulit_kehamilan,
        pasien_bayi.ketuban,
        pasien_bayi.lingkar_perut,
        pasien_bayi.lingkar_dada,
        pegawai.nama AS penolong,
        pasien_bayi.no_skl,
        pasien_bayi.g,
        pasien_bayi.p,
        pasien_bayi.a,
        pasien_bayi.f1,
        pasien_bayi.u1,
        pasien_bayi.t1,
        pasien_bayi.r1,
        pasien_bayi.w1,
        pasien_bayi.n1,
        pasien_bayi.f5,
        pasien_bayi.u5,
        pasien_bayi.t5,
        pasien_bayi.r5,
        pasien_bayi.w5,
        pasien_bayi.n5,
        pasien_bayi.f10,
        pasien_bayi.u10,
        pasien_bayi.t10,
        pasien_bayi.r10,
        pasien_bayi.w10,
        pasien_bayi.n10,
        pasien_bayi.resusitas,
        pasien_bayi.obat_diberikan,
        pasien_bayi.mikasi,
        pasien_bayi.mikonium
    FROM pasien
    INNER JOIN pasien_bayi ON pasien.no_rkm_medis = pasien_bayi.no_rkm_medis
    LEFT JOIN pegawai ON pasien_bayi.penolong = pegawai.nik
    LEFT JOIN kelurahan ON pasien.kd_kel = kelurahan.kd_kel
    LEFT JOIN kecamatan ON pasien.kd_kec = kecamatan.kd_kec
    LEFT JOIN kabupaten ON pasien.kd_kab = kabupaten.kd_kab
    $where
    ORDER BY pasien.no_rkm_medis DESC";

$data = [];
$summary = [
    'total' => 0,
    'laki' => 0,
    'perempuan' => 0,
    'rerata_berat' => 0,
    'rerata_panjang' => 0
];

$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query tidak dapat diproses.', 'data' => [], 'summary' => $summary], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($jk !== '') {
    $stmt->bind_param($types, $tgl_awal, $tgl_akhir, $jk);
} else {
    $stmt->bind_param($types, $tgl_awal, $tgl_akhir);
}
$stmt->execute();
$result = $stmt->get_result();

$totalBerat = 0;
$countBerat = 0;
$totalPanjang = 0;
$countPanjang = 0;

while ($row = $result->fetch_assoc()) {
    $berat = is_numeric($row['berat_badan']) ? (float) $row['berat_badan'] : 0;
    $panjang = is_numeric($row['panjang_badan']) ? (float) $row['panjang_badan'] : 0;

    if ($berat > 0) {
        $totalBerat += $berat;
        $countBerat++;
    }
    if ($panjang > 0) {
        $totalPanjang += $panjang;
        $countPanjang++;
    }

    if ($row['jk'] === 'L') {
        $summary['laki']++;
    } elseif ($row['jk'] === 'P') {
        $summary['perempuan']++;
    }

    $data[] = [
        'no_rkm_medis' => $row['no_rkm_medis'] ?: '-',
        'nm_pasien' => $row['nm_pasien'] ?: '-',
        'jk' => $row['jk'] ?: '-',
        'tgl_lahir' => $row['tgl_lahir'] ?: '-',
        'jam_lahir' => $row['jam_lahir'] ?: '-',
        'umur' => $row['umur'] ?: '-',
        'tgl_daftar' => $row['tgl_daftar'] ?: '-',
        'nm_ibu' => $row['nm_ibu'] ?: '-',
        'umur_ibu' => $row['umur_ibu'] ?: '-',
        'nama_ayah' => $row['nama_ayah'] ?: '-',
        'umur_ayah' => $row['umur_ayah'] ?: '-',
        'alamat' => $row['alamat'] ?: '-',
        'berat_badan' => $row['berat_badan'] ?: '-',
        'panjang_badan' => $row['panjang_badan'] ?: '-',
        'lingkar_kepala' => $row['lingkar_kepala'] ?: '-',
        'proses_lahir' => $row['proses_lahir'] ?: '-',
        'anakke' => $row['anakke'] ?: '-',
        'keterangan' => $row['keterangan'] ?: '-',
        'diagnosa' => $row['diagnosa'] ?: '-',
        'penyulit_kehamilan' => $row['penyulit_kehamilan'] ?: '-',
        'ketuban' => $row['ketuban'] ?: '-',
        'lingkar_perut' => $row['lingkar_perut'] ?: '-',
        'lingkar_dada' => $row['lingkar_dada'] ?: '-',
        'penolong' => $row['penolong'] ?: '-',
        'no_skl' => $row['no_skl'] ?: '-',
        'g' => $row['g'] ?: '-',
        'p' => $row['p'] ?: '-',
        'a' => $row['a'] ?: '-',
        'f1' => $row['f1'] ?: '-',
        'u1' => $row['u1'] ?: '-',
        't1' => $row['t1'] ?: '-',
        'r1' => $row['r1'] ?: '-',
        'w1' => $row['w1'] ?: '-',
        'n1' => $row['n1'] ?: '-',
        'f5' => $row['f5'] ?: '-',
        'u5' => $row['u5'] ?: '-',
        't5' => $row['t5'] ?: '-',
        'r5' => $row['r5'] ?: '-',
        'w5' => $row['w5'] ?: '-',
        'n5' => $row['n5'] ?: '-',
        'f10' => $row['f10'] ?: '-',
        'u10' => $row['u10'] ?: '-',
        't10' => $row['t10'] ?: '-',
        'r10' => $row['r10'] ?: '-',
        'w10' => $row['w10'] ?: '-',
        'n10' => $row['n10'] ?: '-',
        'resusitas' => $row['resusitas'] ?: '-',
        'obat_diberikan' => $row['obat_diberikan'] ?: '-',
        'mikasi' => $row['mikasi'] ?: '-',
        'mikonium' => $row['mikonium'] ?: '-'
    ];
}

$stmt->close();

$summary['total'] = count($data);
$summary['rerata_berat'] = $countBerat > 0 ? round($totalBerat / $countBerat, 1) : 0;
$summary['rerata_panjang'] = $countPanjang > 0 ? round($totalPanjang / $countPanjang, 1) : 0;

echo json_encode([
    'success' => true,
    'data' => $data,
    'summary' => $summary
], JSON_UNESCAPED_UNICODE);
