<?php
/*
 * File: api/data_rekap_obat_ppn.php
 * Fungsi: Rekap PPN obat berdasarkan modul Khanza
 */

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

$tgl_awal  = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$keyword   = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$mode      = isset($_GET['mode']) ? $_GET['mode'] : 'pengadaan';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_awal) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir)) {
    http_response_code(400);
    echo json_encode(['error' => 'Format tanggal tidak valid.']);
    exit;
}

function emptySummary() {
    return [
        'total_record' => 0,
        'total'        => 0.0,
        'ppn'          => 0.0,
        'total_ppn'    => 0.0
    ];
}

function bindParams($stmt, $types, $params) {
    $bind_params = array_merge([$types], $params);
    call_user_func_array(
        [$stmt, 'bind_param'],
        array_map(function (&$v) { return $v; }, $bind_params)
    );
}

function buildLike(&$sql, &$params, &$types, $keyword, $columns) {
    if ($keyword === '') {
        return;
    }

    $parts = [];
    foreach ($columns as $column) {
        $parts[] = "$column LIKE ?";
        $params[] = '%' . $keyword . '%';
        $types .= 's';
    }
    $sql .= ' AND (' . implode(' OR ', $parts) . ') ';
}

$configs = [
    'pengadaan' => [
        'title' => 'PPN Pengadaan Obat',
        'date_label' => 'Tgl.Beli',
        'columns' => ['tanggal', 'no_nota', 'supplier', 'petugas', 'total', 'ppn', 'total_ppn'],
        'sql' => "
            SELECT
                p.tgl_beli AS tanggal,
                p.no_faktur AS no_nota,
                CONCAT(p.kode_suplier, ' ', ds.nama_suplier) AS supplier,
                CONCAT(p.nip, ' ', pt.nama) AS petugas,
                p.total2 AS total,
                p.ppn AS ppn,
                p.tagihan AS total_ppn
            FROM pembelian p
            INNER JOIN datasuplier ds ON p.kode_suplier = ds.kode_suplier
            INNER JOIN petugas pt ON p.nip = pt.nip
            WHERE p.tgl_beli BETWEEN ? AND ?
        ",
        'search' => ['p.no_faktur', 'p.kode_suplier', 'ds.nama_suplier', 'p.nip', 'pt.nama'],
        'order' => ' ORDER BY p.tgl_beli, p.no_faktur '
    ],
    'penerimaan' => [
        'title' => 'PPN Penerimaan Obat',
        'date_label' => 'Tgl.Terima',
        'columns' => ['tanggal', 'no_nota', 'supplier', 'petugas', 'total', 'ppn', 'total_ppn'],
        'sql' => "
            SELECT
                p.tgl_pesan AS tanggal,
                p.no_faktur AS no_nota,
                CONCAT(p.kode_suplier, ' ', ds.nama_suplier) AS supplier,
                CONCAT(p.nip, ' ', pt.nama) AS petugas,
                p.total2 AS total,
                p.ppn AS ppn,
                p.tagihan AS total_ppn
            FROM pemesanan p
            INNER JOIN datasuplier ds ON p.kode_suplier = ds.kode_suplier
            INNER JOIN petugas pt ON p.nip = pt.nip
            WHERE p.tgl_pesan BETWEEN ? AND ?
        ",
        'search' => ['p.no_faktur', 'p.kode_suplier', 'ds.nama_suplier', 'p.nip', 'pt.nama'],
        'order' => ' ORDER BY p.tgl_pesan, p.no_faktur '
    ],
    'rawat_jalan' => [
        'title' => 'PPN Obat Rawat Jalan',
        'date_label' => 'Tgl.Nota',
        'columns' => ['tanggal', 'no_nota', 'nama_pasien', 'total', 'ppn', 'total_ppn'],
        'sql' => "
            SELECT
                nj.tanggal AS tanggal,
                nj.no_nota AS no_nota,
                CONCAT(ps.no_rkm_medis, ' ', ps.nm_pasien) AS nama_pasien,
                COALESCE(SUM(CASE WHEN b.status = 'Obat' THEN b.totalbiaya ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN b.status = 'Obat' AND b.nm_perawatan = 'PPN Obat' THEN b.totalbiaya ELSE 0 END), 0) AS total,
                COALESCE(SUM(CASE WHEN b.status = 'Obat' AND b.nm_perawatan = 'PPN Obat' THEN b.totalbiaya ELSE 0 END), 0) AS ppn,
                COALESCE(SUM(CASE WHEN b.status = 'Obat' THEN b.totalbiaya ELSE 0 END), 0) AS total_ppn
            FROM nota_jalan nj
            INNER JOIN reg_periksa rp ON nj.no_rawat = rp.no_rawat
            INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
            LEFT JOIN billing b ON nj.no_rawat = b.no_rawat
            WHERE nj.tanggal BETWEEN ? AND ?
        ",
        'search' => ['nj.no_nota', 'ps.no_rkm_medis', 'ps.nm_pasien'],
        'group' => ' GROUP BY nj.tanggal, nj.no_nota, ps.no_rkm_medis, ps.nm_pasien ',
        'order' => ' ORDER BY nj.tanggal, nj.no_nota '
    ],
    'jual_bebas' => [
        'title' => 'PPN Obat Jual Bebas',
        'date_label' => 'Tgl.Jual',
        'columns' => ['tanggal', 'no_nota', 'nama_pasien', 'petugas', 'total', 'ppn', 'total_ppn'],
        'sql' => "
            SELECT
                pj.tgl_jual AS tanggal,
                pj.nota_jual AS no_nota,
                CONCAT(pj.no_rkm_medis, ' ', ps.nm_pasien) AS nama_pasien,
                CONCAT(pj.nip, ' ', pt.nama) AS petugas,
                SUM(dj.total) AS total,
                ROUND(pj.ppn) AS ppn,
                SUM(dj.total) + ROUND(pj.ppn) AS total_ppn
            FROM penjualan pj
            INNER JOIN pasien ps ON pj.no_rkm_medis = ps.no_rkm_medis
            INNER JOIN petugas pt ON pj.nip = pt.nip
            INNER JOIN detailjual dj ON pj.nota_jual = dj.nota_jual
            WHERE pj.status = 'Sudah Dibayar'
              AND pj.tgl_jual BETWEEN ? AND ?
        ",
        'search' => ['pj.nota_jual', 'pj.no_rkm_medis', 'ps.nm_pasien', 'pj.nip', 'pt.nama'],
        'group' => ' GROUP BY pj.tgl_jual, pj.nota_jual, pj.no_rkm_medis, ps.nm_pasien, pj.nip, pt.nama, pj.ppn ',
        'order' => ' ORDER BY pj.tgl_jual, pj.nota_jual '
    ],
    'rawat_inap' => [
        'title' => 'PPN Obat Rawat Inap',
        'date_label' => 'Tgl.Nota',
        'columns' => ['tanggal', 'no_nota', 'nama_pasien', 'total', 'ppn', 'total_ppn'],
        'sql' => "
            SELECT
                ni.tanggal AS tanggal,
                ni.no_nota AS no_nota,
                CONCAT(ps.no_rkm_medis, ' ', ps.nm_pasien) AS nama_pasien,
                COALESCE(SUM(CASE WHEN b.status = 'Obat' THEN b.totalbiaya ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN b.status = 'Obat' AND b.nm_perawatan = 'PPN Obat' THEN b.totalbiaya ELSE 0 END), 0) AS total,
                COALESCE(SUM(CASE WHEN b.status = 'Obat' AND b.nm_perawatan = 'PPN Obat' THEN b.totalbiaya ELSE 0 END), 0) AS ppn,
                COALESCE(SUM(CASE WHEN b.status = 'Obat' THEN b.totalbiaya ELSE 0 END), 0) AS total_ppn
            FROM nota_inap ni
            INNER JOIN reg_periksa rp ON ni.no_rawat = rp.no_rawat
            INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
            LEFT JOIN billing b ON ni.no_rawat = b.no_rawat
            WHERE ni.tanggal BETWEEN ? AND ?
        ",
        'search' => ['ni.no_nota', 'ps.no_rkm_medis', 'ps.nm_pasien'],
        'group' => ' GROUP BY ni.tanggal, ni.no_nota, ps.no_rkm_medis, ps.nm_pasien ',
        'order' => ' ORDER BY ni.tanggal, ni.no_nota '
    ],
    'piutang' => [
        'title' => 'PPN Piutang Obat',
        'date_label' => 'Tgl.Jual',
        'columns' => ['tanggal', 'no_nota', 'nama_pasien', 'petugas', 'total', 'ppn', 'total_ppn'],
        'sql' => "
            SELECT
                p.tgl_piutang AS tanggal,
                p.nota_piutang AS no_nota,
                CONCAT(p.no_rkm_medis, ' ', ps.nm_pasien) AS nama_pasien,
                CONCAT(p.nip, ' ', pt.nama) AS petugas,
                SUM(dp.total) AS total,
                ROUND(p.ppn) AS ppn,
                SUM(dp.total) + ROUND(p.ppn) AS total_ppn
            FROM piutang p
            INNER JOIN pasien ps ON p.no_rkm_medis = ps.no_rkm_medis
            INNER JOIN petugas pt ON p.nip = pt.nip
            INNER JOIN detailpiutang dp ON p.nota_piutang = dp.nota_piutang
            WHERE p.tgl_piutang BETWEEN ? AND ?
        ",
        'search' => ['p.nota_piutang', 'p.no_rkm_medis', 'ps.nm_pasien', 'p.nip', 'pt.nama'],
        'group' => ' GROUP BY p.tgl_piutang, p.nota_piutang, p.no_rkm_medis, ps.nm_pasien, p.nip, pt.nama, p.ppn ',
        'order' => ' ORDER BY p.tgl_piutang, p.nota_piutang '
    ]
];

if (!isset($configs[$mode])) {
    http_response_code(400);
    echo json_encode(['error' => 'Mode laporan tidak valid.']);
    exit;
}

$cfg = $configs[$mode];
$sql = $cfg['sql'];
$params = [$tgl_awal, $tgl_akhir];
$types = 'ss';

buildLike($sql, $params, $types, $keyword, $cfg['search']);
$sql .= isset($cfg['group']) ? $cfg['group'] : '';
$sql .= $cfg['order'];

$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $koneksi->error]);
    exit;
}

bindParams($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
$summary = emptySummary();
while ($row = $res->fetch_assoc()) {
    $total = (float)$row['total'];
    $ppn = (float)$row['ppn'];
    $total_ppn = (float)$row['total_ppn'];

    $item = [
        'tanggal'   => $row['tanggal'],
        'no_nota'   => $row['no_nota'],
        'total'     => $total,
        'ppn'       => $ppn,
        'total_ppn' => $total_ppn
    ];

    if (isset($row['supplier'])) {
        $item['supplier'] = $row['supplier'];
    }
    if (isset($row['nama_pasien'])) {
        $item['nama_pasien'] = $row['nama_pasien'];
    }
    if (isset($row['petugas'])) {
        $item['petugas'] = $row['petugas'];
    }

    $data[] = $item;
    $summary['total'] += $total;
    $summary['ppn'] += $ppn;
    $summary['total_ppn'] += $total_ppn;
}
$stmt->close();

$summary['total_record'] = count($data);

echo json_encode([
    'mode' => $mode,
    'title' => $cfg['title'],
    'date_label' => $cfg['date_label'],
    'columns' => $cfg['columns'],
    'data' => $data,
    'summary' => $summary,
    'periode' => [
        'awal' => $tgl_awal,
        'akhir' => $tgl_akhir
    ]
], JSON_UNESCAPED_UNICODE);
