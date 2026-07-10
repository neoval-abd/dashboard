<?php
/*
 * File: api/data_rekap_obat_poli.php
 * Fungsi: Rekap penggunaan obat rawat jalan per kelompok poli
 */

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

$tgl_awal   = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir  = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$poli       = isset($_GET['poli']) ? $_GET['poli'] : '';
$kd_pj      = isset($_GET['kd_pj']) ? $_GET['kd_pj'] : '';
$kdjenis    = isset($_GET['kdjenis']) ? $_GET['kdjenis'] : '';
$kdkategori = isset($_GET['kdkategori']) ? $_GET['kdkategori'] : '';
$kdgolongan = isset($_GET['kdgolongan']) ? $_GET['kdgolongan'] : '';

function emptySummary() {
    return [
        'total_item'     => 0,
        'total_qty'      => 0.0,
        'total_biaya'    => 0.0,
        'total_embalase' => 0.0,
        'total_tuslah'   => 0.0,
        'grand_total'    => 0.0,
        'total_igd'      => 0.0,
        'total_poli'     => 0.0
    ];
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_awal) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir)) {
    http_response_code(400);
    echo json_encode(['error' => 'Format tanggal tidak valid.']);
    exit;
}

$kelompok_poli_expr = "
    CASE
        WHEN LOWER(CONCAT(rp.kd_poli, ' ', pl.nm_poli)) LIKE '%igd%' THEN 'IGD'
        ELSE 'Poliklinik'
    END
";

$sql = "
    SELECT
        $kelompok_poli_expr AS kelompok_poli,
        rp.kd_poli,
        pl.nm_poli,
        dpo.kode_brng,
        db.nama_brng,
        SUM(dpo.jml) AS jml,
        SUM(dpo.total) - SUM(dpo.embalase + dpo.tuslah) AS biaya_obat,
        SUM(dpo.embalase) AS embalase,
        SUM(dpo.tuslah) AS tuslah,
        SUM(dpo.total) AS total
    FROM detail_pemberian_obat dpo
    INNER JOIN reg_periksa rp ON dpo.no_rawat = rp.no_rawat
    INNER JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
    INNER JOIN databarang db ON dpo.kode_brng = db.kode_brng
    WHERE dpo.status = 'Ralan'
      AND rp.tgl_registrasi BETWEEN ? AND ?
";

$params = [$tgl_awal, $tgl_akhir];
$types  = 'ss';

if ($poli === 'IGD') {
    $sql .= " AND LOWER(CONCAT(rp.kd_poli, ' ', pl.nm_poli)) LIKE '%igd%' ";
} elseif ($poli === 'Poliklinik') {
    $sql .= " AND LOWER(CONCAT(rp.kd_poli, ' ', pl.nm_poli)) NOT LIKE '%igd%' ";
}

if ($kd_pj !== '') {
    $sql .= " AND rp.kd_pj = ? ";
    $params[] = $kd_pj;
    $types .= 's';
}

if ($kdjenis !== '') {
    $sql .= " AND db.kdjns = ? ";
    $params[] = $kdjenis;
    $types .= 's';
}

if ($kdkategori !== '') {
    $sql .= " AND db.kode_kategori = ? ";
    $params[] = $kdkategori;
    $types .= 's';
}

if ($kdgolongan !== '') {
    $sql .= " AND db.kode_golongan = ? ";
    $params[] = $kdgolongan;
    $types .= 's';
}

$sql .= "
    GROUP BY kelompok_poli, rp.kd_poli, pl.nm_poli, dpo.kode_brng, db.nama_brng
    ORDER BY FIELD(kelompok_poli, 'IGD', 'Poliklinik'), pl.nm_poli, db.nama_brng
";

$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $koneksi->error]);
    exit;
}

$bind_params = array_merge([$types], $params);
call_user_func_array(
    [$stmt, 'bind_param'],
    array_map(function (&$v) { return $v; }, $bind_params)
);

$stmt->execute();
$res = $stmt->get_result();

$data = [];
$summary = emptySummary();
while ($row = $res->fetch_assoc()) {
    $jml      = (float)$row['jml'];
    $biaya    = (float)$row['biaya_obat'];
    $embalase = (float)$row['embalase'];
    $tuslah   = (float)$row['tuslah'];
    $total    = (float)$row['total'];

    $data[] = [
        'kelompok_poli' => $row['kelompok_poli'],
        'kd_poli'       => $row['kd_poli'],
        'nm_poli'       => $row['nm_poli'],
        'kode_brng'     => $row['kode_brng'],
        'nama_brng'     => $row['nama_brng'],
        'jml'           => $jml,
        'biaya_obat'    => $biaya,
        'embalase'      => $embalase,
        'tuslah'        => $tuslah,
        'total'         => $total
    ];

    $summary['total_qty']      += $jml;
    $summary['total_biaya']    += $biaya;
    $summary['total_embalase'] += $embalase;
    $summary['total_tuslah']   += $tuslah;
    $summary['grand_total']    += $total;

    if ($row['kelompok_poli'] === 'IGD') {
        $summary['total_igd'] += $total;
    } else {
        $summary['total_poli'] += $total;
    }
}
$stmt->close();

$summary['total_item'] = count($data);

echo json_encode([
    'data' => $data,
    'summary' => $summary,
    'periode' => [
        'awal' => $tgl_awal,
        'akhir' => $tgl_akhir
    ]
], JSON_UNESCAPED_UNICODE);
