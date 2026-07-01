<?php
/*
 * File: api/data_rl38_lab.php
 * Fungsi: Mengambil data item pemeriksaan laboratorium untuk RL 3.8.
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

function is_valid_date_rl38($value) {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
}

function dash_if_empty_rl38($value) {
    if (is_string($value)) {
        $value = trim($value);
    }

    return ($value === null || $value === '') ? '-' : $value;
}

function kelompok_umur_rl38($umur_hari) {
    if ($umur_hari === null || $umur_hari === '') {
        return '-';
    }

    $umur_hari = (int) $umur_hari;
    if ($umur_hari <= 7) {
        return '0-7 hari';
    }
    if ($umur_hari <= 28) {
        return '8-28 hari';
    }
    if ($umur_hari < 365) {
        return '29 hari - < 1 tahun';
    }

    $umur_tahun = (int) floor($umur_hari / 365.25);
    if ($umur_tahun <= 4) {
        return '1-4 tahun';
    }
    if ($umur_tahun <= 14) {
        return '5-14 tahun';
    }
    if ($umur_tahun <= 24) {
        return '15-24 tahun';
    }
    if ($umur_tahun <= 44) {
        return '25-44 tahun';
    }
    if ($umur_tahun <= 64) {
        return '45-64 tahun';
    }

    return '>= 65 tahun';
}

function format_umur_rl38($umur_hari) {
    if ($umur_hari === null || $umur_hari === '') {
        return '-';
    }

    $umur_hari = (int) $umur_hari;
    if ($umur_hari < 29) {
        return $umur_hari . ' hari';
    }
    if ($umur_hari < 365) {
        return (int) floor($umur_hari / 30.4375) . ' bln';
    }

    return (int) floor($umur_hari / 365.25) . ' th';
}

$tgl_awal  = is_valid_date_rl38($_GET['tgl_awal'] ?? '') ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = is_valid_date_rl38($_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');
$keyword   = trim($_GET['keyword'] ?? '');
$no_rawat  = trim($_GET['no_rawat'] ?? '');
$no_rm     = trim($_GET['no_rm'] ?? '');

$where = "WHERE periksa_lab.kategori = 'PK' AND periksa_lab.tgl_periksa BETWEEN ? AND ?";
$types = "ss";
$params = [$tgl_awal, $tgl_akhir];

if ($no_rawat !== '') {
    $where .= " AND periksa_lab.no_rawat = ?";
    $types .= "s";
    $params[] = $no_rawat;
}

if ($no_rm !== '') {
    $where .= " AND reg_periksa.no_rkm_medis = ?";
    $types .= "s";
    $params[] = $no_rm;
}

if ($keyword !== '') {
    $where .= " AND (
        periksa_lab.no_rawat LIKE ? OR
        pasien.no_rkm_medis LIKE ? OR
        pasien.nm_pasien LIKE ? OR
        penjab.png_jawab LIKE ? OR
        jns_perawatan_lab.nm_perawatan LIKE ? OR
        template_laboratorium.Pemeriksaan LIKE ? OR
        detail_periksa_lab.nilai LIKE ? OR
        detail_periksa_lab.keterangan LIKE ? OR
        petugas.nama LIKE ? OR
        dokter_pj.nm_dokter LIKE ? OR
        dokter_perujuk.nm_dokter LIKE ? OR
        poliklinik.nm_poli LIKE ?
    )";
    $types .= str_repeat('s', 12);
    $like = '%' . $keyword . '%';
    for ($i = 0; $i < 12; $i++) {
        $params[] = $like;
    }
}

$sql = "
    SELECT
        periksa_lab.no_rawat,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        pasien.jk,
        DATEDIFF(periksa_lab.tgl_periksa, pasien.tgl_lahir) AS umur_hari,
        penjab.png_jawab,
        periksa_lab.tgl_periksa,
        periksa_lab.jam,
        periksa_lab.status,
        jns_perawatan_lab.nm_perawatan,
        template_laboratorium.Pemeriksaan AS item_pemeriksaan,
        detail_periksa_lab.nilai,
        template_laboratorium.satuan,
        detail_periksa_lab.nilai_rujukan,
        detail_periksa_lab.keterangan,
        COALESCE(
            (
                SELECT bangsal.nm_bangsal
                FROM kamar_inap
                INNER JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
                INNER JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
                WHERE kamar_inap.no_rawat = periksa_lab.no_rawat
                ORDER BY kamar_inap.tgl_masuk DESC, kamar_inap.jam_masuk DESC
                LIMIT 1
            ),
            poliklinik.nm_poli
        ) AS ruang,
        petugas.nama AS petugas,
        dokter_perujuk.nm_dokter AS dokter_perujuk,
        dokter_pj.nm_dokter AS penanggung_jawab
    FROM periksa_lab
    INNER JOIN reg_periksa ON periksa_lab.no_rawat = reg_periksa.no_rawat
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    INNER JOIN petugas ON periksa_lab.nip = petugas.nip
    INNER JOIN dokter dokter_pj ON periksa_lab.kd_dokter = dokter_pj.kd_dokter
    LEFT JOIN dokter dokter_perujuk ON periksa_lab.dokter_perujuk = dokter_perujuk.kd_dokter
    INNER JOIN detail_periksa_lab ON
        periksa_lab.no_rawat = detail_periksa_lab.no_rawat AND
        periksa_lab.kd_jenis_prw = detail_periksa_lab.kd_jenis_prw AND
        periksa_lab.tgl_periksa = detail_periksa_lab.tgl_periksa AND
        periksa_lab.jam = detail_periksa_lab.jam
    INNER JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
    INNER JOIN template_laboratorium ON detail_periksa_lab.id_template = template_laboratorium.id_template
    INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    $where
    ORDER BY periksa_lab.tgl_periksa DESC, periksa_lab.jam DESC, detail_periksa_lab.kd_jenis_prw, template_laboratorium.urut";

$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Query RL 3.8 laboratorium tidak dapat diproses.',
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
$pasien_unik = [];
$kunjungan_unik = [];
$pemeriksaan = [];
$kelompok_umur = [];
$jenis_kelamin = ['L' => 0, 'P' => 0, '-' => 0];

while ($row = $result->fetch_assoc()) {
    $umur_hari = $row['umur_hari'];
    $kelompok = kelompok_umur_rl38($umur_hari);
    $jk = dash_if_empty_rl38($row['jk']);
    $item = dash_if_empty_rl38($row['item_pemeriksaan']);
    $pasien_key = dash_if_empty_rl38($row['no_rkm_medis']);
    $kunjungan_key = dash_if_empty_rl38($row['no_rawat']);

    $pasien_unik[$pasien_key] = true;
    $kunjungan_unik[$kunjungan_key] = true;
    $pemeriksaan[$item] = ($pemeriksaan[$item] ?? 0) + 1;
    $kelompok_umur[$kelompok] = ($kelompok_umur[$kelompok] ?? 0) + 1;
    $jenis_kelamin[$jk] = ($jenis_kelamin[$jk] ?? 0) + 1;

    $data[] = [
        'no_rawat' => dash_if_empty_rl38($row['no_rawat']),
        'no_rkm_medis' => dash_if_empty_rl38($row['no_rkm_medis']),
        'nm_pasien' => dash_if_empty_rl38($row['nm_pasien']),
        'pasien' => dash_if_empty_rl38($row['nm_pasien']) . ' (' . dash_if_empty_rl38($row['png_jawab']) . ')',
        'penjab' => dash_if_empty_rl38($row['png_jawab']),
        'jk' => $jk,
        'umur' => format_umur_rl38($umur_hari),
        'kelompok_umur' => $kelompok,
        'tgl_periksa' => dash_if_empty_rl38($row['tgl_periksa']),
        'jam' => dash_if_empty_rl38($row['jam']),
        'pemeriksaan' => dash_if_empty_rl38($row['nm_perawatan']),
        'item_pemeriksaan' => $item,
        'hasil' => dash_if_empty_rl38($row['nilai']),
        'satuan' => dash_if_empty_rl38($row['satuan']),
        'nilai_rujukan' => dash_if_empty_rl38($row['nilai_rujukan']),
        'keterangan' => dash_if_empty_rl38($row['keterangan']),
        'ruang' => dash_if_empty_rl38($row['ruang']),
        'petugas' => dash_if_empty_rl38($row['petugas']),
        'dokter_perujuk' => dash_if_empty_rl38($row['dokter_perujuk']),
        'penanggung_jawab' => dash_if_empty_rl38($row['penanggung_jawab'])
    ];
}

$stmt->close();

arsort($pemeriksaan);
arsort($kelompok_umur);

echo json_encode([
    'success' => true,
    'data' => $data,
    'summary' => [
        'total_item' => count($data),
        'total_pasien' => count($pasien_unik),
        'total_kunjungan' => count($kunjungan_unik),
        'pemeriksaan' => $pemeriksaan,
        'kelompok_umur' => $kelompok_umur,
        'jenis_kelamin' => $jenis_kelamin
    ]
], JSON_UNESCAPED_UNICODE);
