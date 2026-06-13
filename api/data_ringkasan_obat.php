<?php
/*
 * File: api/data_ringkasan_obat.php
 * Fungsi: Ringkasan Penerimaan Obat, Alkes & BHP Medis
 *         (rekap dari pemesanan + detailpesan)
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

$tgl_awal  = isset($_GET['tgl_awal'])  ? $koneksi->real_escape_string($_GET['tgl_awal'])  : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $koneksi->real_escape_string($_GET['tgl_akhir']) : date('Y-m-d');
$no_faktur = isset($_GET['no_faktur']) ? '%' . $koneksi->real_escape_string($_GET['no_faktur']) . '%' : '%';
$supplier  = isset($_GET['supplier'])  ? '%' . $koneksi->real_escape_string($_GET['supplier']) . '%' : '%';
$petugas   = isset($_GET['petugas'])   ? '%' . $koneksi->real_escape_string($_GET['petugas']) . '%' : '%';
$jenis     = isset($_GET['jenis'])     ? '%' . $koneksi->real_escape_string($_GET['jenis']) . '%' : '%';
$barang    = isset($_GET['barang'])    ? '%' . $koneksi->real_escape_string($_GET['barang']) . '%' : '%';
$industri  = isset($_GET['industri'])  ? '%' . $koneksi->real_escape_string($_GET['industri']) . '%' : '%';

$sql = "
    SELECT 
        dp.kode_brng,
        db.nama_brng,
        db.kode_sat,
        ks.satuan,
        j.nama AS namajenis,
        SUM(dp.jumlah2) AS jumlah,
        SUM(dp.total) AS total
    FROM pemesanan p
    INNER JOIN datasuplier ds ON p.kode_suplier = ds.kode_suplier
    INNER JOIN petugas pt ON p.nip = pt.nip
    INNER JOIN bangsal b ON p.kd_bangsal = b.kd_bangsal
    INNER JOIN detailpesan dp ON p.no_faktur = dp.no_faktur
    INNER JOIN databarang db ON dp.kode_brng = db.kode_brng
    INNER JOIN kodesatuan ks ON db.kode_sat = ks.kode_sat
    INNER JOIN jenis j ON db.kdjns = j.kdjns
    INNER JOIN industrifarmasi inf ON db.kode_industri = inf.kode_industri
    WHERE p.tgl_pesan BETWEEN '$tgl_awal' AND '$tgl_akhir'
      AND p.no_faktur LIKE '$no_faktur'
      AND ds.nama_suplier LIKE '$supplier'
      AND pt.nama LIKE '$petugas'
      AND j.nama LIKE '$jenis'
      AND db.nama_brng LIKE '$barang'
      AND inf.nama_industri LIKE '$industri'
    GROUP BY dp.kode_brng
    ORDER BY db.nama_brng ASC";

$data = [];
$res = $koneksi->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $jumlah = (float) $row['jumlah'];
        $total  = (float) $row['total'];
        $harga_per_item = $jumlah > 0 ? $total / $jumlah : 0;
        $data[] = [
            'kode_brng'      => $row['kode_brng'],
            'nama_brng'      => $row['nama_brng'],
            'kode_sat'       => $row['kode_sat'],
            'satuan'         => $row['satuan'],
            'namajenis'      => $row['namajenis'],
            'jumlah'         => $jumlah,
            'harga_per_item' => round($harga_per_item, 2),
            'total'          => $total
        ];
    }
}

// Summary
$total_qty   = array_sum(array_column($data, 'jumlah'));
$total_nilai = array_sum(array_column($data, 'total'));
$total_item  = count($data);

echo json_encode([
    'data'    => $data,
    'summary' => [
        'total_item'  => $total_item,
        'total_qty'   => $total_qty,
        'total_nilai' => $total_nilai
    ]
], JSON_UNESCAPED_UNICODE);
