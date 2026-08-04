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

$sql = "SELECT CONCAT(dpo.tgl_perawatan, ' ', dpo.jam) AS tanggal_pemberian,
        COALESCE(p.nm_poli, '-') AS nm_poli, dpo.status, rp.no_rkm_medis, ps.nm_pasien,
        CONCAT_WS(', ', NULLIF(ps.alamat, ''), kel.nm_kel, kec.nm_kec, kab.nm_kab, prop.nm_prop) AS alamat_pasien,
        COALESCE(ro.no_resep, '-') AS no_resep, COALESCE(dk.nm_dokter, '-') AS nm_dokter,
        dpo.kode_brng, db.nama_brng,
        COALESCE(ks.satuan, db.kode_sat, '-') AS satuan, COALESCE(j.nama, '-') AS jenis,
        COALESCE(kb.nama, '-') AS kategori, COALESCE(gb.nama, '-') AS golongan,
        dpo.jml AS jumlah,
        (dpo.total - dpo.embalase - dpo.tuslah) AS biaya_obat,
        dpo.embalase, dpo.tuslah, dpo.total
    FROM detail_pemberian_obat dpo
    INNER JOIN reg_periksa rp ON dpo.no_rawat = rp.no_rawat
    INNER JOIN databarang db ON dpo.kode_brng = db.kode_brng
    INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
    LEFT JOIN poliklinik p ON rp.kd_poli = p.kd_poli
    LEFT JOIN kelurahan kel ON ps.kd_kel = kel.kd_kel
    LEFT JOIN kecamatan kec ON ps.kd_kec = kec.kd_kec
    LEFT JOIN kabupaten kab ON ps.kd_kab = kab.kd_kab
    LEFT JOIN propinsi prop ON ps.kd_prop = prop.kd_prop
    LEFT JOIN resep_obat ro ON ro.no_rawat = dpo.no_rawat AND ro.tgl_perawatan = dpo.tgl_perawatan AND ro.jam = dpo.jam
    LEFT JOIN dokter dk ON ro.kd_dokter = dk.kd_dokter
    LEFT JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    LEFT JOIN jenis j ON db.kdjns = j.kdjns
    LEFT JOIN kategori_barang kb ON db.kode_kategori = kb.kode
    LEFT JOIN golongan_barang gb ON db.kode_golongan = gb.kode
    WHERE " . implode(' AND ', $where) . "
    ORDER BY dpo.tgl_perawatan, dpo.jam, dpo.no_rawat, dpo.kode_brng";

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
