<?php
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

function param($name, $default = '') { return isset($_GET[$name]) ? trim($_GET[$name]) : $default; }
$tglAwal = param('tgl_awal', date('Y-m-01'));
$tglAkhir = param('tgl_akhir', date('Y-m-d'));
$status = param('status', 'Semua');
if (!in_array($status, ['Semua', 'Ralan', 'Ranap'], true)) $status = 'Semua';

$filters = [
    'poli' => param('poli'), 'jenis' => param('jenis'), 'kategori' => param('kategori'),
    'golongan' => param('golongan'), 'barang' => param('barang')
];
$where = ['dpo.tgl_perawatan BETWEEN ? AND ?'];
$types = 'ss'; $values = [$tglAwal, $tglAkhir];
if ($status !== 'Semua') { $where[] = 'dpo.status = ?'; $types .= 's'; $values[] = $status; }
$columns = [
    'poli' => "CONCAT(rp.kd_poli, ' ', p.nm_poli)", 'jenis' => "CONCAT(db.kdjns, ' ', j.nama)",
    'kategori' => "CONCAT(db.kode_kategori, ' ', kb.nama)", 'golongan' => "CONCAT(db.kode_golongan, ' ', gb.nama)",
    'barang' => "CONCAT(dpo.kode_brng, ' ', db.nama_brng)"
];
foreach ($filters as $key => $value) { if ($value !== '') { $where[] = $columns[$key] . ' LIKE ?'; $types .= 's'; $values[] = '%' . $value . '%'; } }

$sql = "SELECT COALESCE(p.nm_poli, '-') AS nm_poli, dpo.status, dpo.kode_brng, db.nama_brng,
        COALESCE(ks.satuan, db.kode_sat, '-') AS satuan, COALESCE(j.nama, '-') AS jenis,
        COALESCE(kb.nama, '-') AS kategori, COALESCE(gb.nama, '-') AS golongan,
        SUM(dpo.jml) AS jumlah,
        SUM(dpo.total - dpo.embalase - dpo.tuslah) AS biaya_obat,
        SUM(dpo.embalase) AS embalase, SUM(dpo.tuslah) AS tuslah, SUM(dpo.total) AS total
    FROM detail_pemberian_obat dpo
    INNER JOIN reg_periksa rp ON dpo.no_rawat = rp.no_rawat
    INNER JOIN databarang db ON dpo.kode_brng = db.kode_brng
    LEFT JOIN poliklinik p ON rp.kd_poli = p.kd_poli
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN jenis j ON db.kdjns = j.kdjns
    LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
    LEFT JOIN golongan_barang gb ON db.kode_golongan = gb.kode
    WHERE " . implode(' AND ', $where) . "
    GROUP BY rp.kd_poli, dpo.status, dpo.kode_brng
    ORDER BY p.nm_poli, db.nama_brng";

$data = [];
if ($stmt = $koneksi->prepare($sql)) {
    $stmt->bind_param($types, ...$values);
    $stmt->execute(); $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach (['jumlah','biaya_obat','embalase','tuslah','total'] as $field) $row[$field] = (float)$row[$field];
        $data[] = $row;
    }
    $stmt->close();
}
$summary = ['total_item'=>count($data),'total_qty'=>0,'total_biaya'=>0,'total_embalase'=>0,'total_tuslah'=>0,'total_nilai'=>0];
foreach ($data as $row) { $summary['total_qty'] += $row['jumlah']; $summary['total_biaya'] += $row['biaya_obat']; $summary['total_embalase'] += $row['embalase']; $summary['total_tuslah'] += $row['tuslah']; $summary['total_nilai'] += $row['total']; }
echo json_encode(['data'=>$data, 'summary'=>$summary], JSON_UNESCAPED_UNICODE);
