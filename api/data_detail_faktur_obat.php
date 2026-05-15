<?php
/*
 * File: api/data_detail_faktur_obat.php
 * Fungsi: Mengambil rincian item obat berdasarkan No. Faktur pemesanan.
 * Sumber: Mengikuti struktur query Java DlgCariPemesanan.java
 *         Tabel detail: detailpesan (bukan detailpemesanan)
 *         Kolom harga : h_pesan (bukan h_beli)
 *         Satuan      : JOIN kodesatuan via kode_sat
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

$no_faktur = isset($_GET['no_faktur']) ? trim($_GET['no_faktur']) : '';

if (empty($no_faktur)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parameter no_faktur wajib diisi.']);
    exit;
}

$response = [
    'faktur_info' => null,
    'items'       => [],
    'total_items' => 0
];

// -----------------------------------------------
// 1. Info Header Faktur (dari tabel pemesanan)
// -----------------------------------------------
$sql_info = "
    SELECT
        p.no_faktur,
        p.no_order,
        p.tgl_pesan,
        p.tgl_faktur,
        p.tgl_tempo,
        p.tagihan,
        p.total2,
        p.ppn,
        p.meterai,
        s.nama_suplier,
        s.nama_bank,
        s.rekening,
        pt.nama AS nama_petugas,
        b.nm_bangsal
    FROM pemesanan p
    INNER JOIN datasuplier s  ON p.kode_suplier = s.kode_suplier
    INNER JOIN petugas    pt  ON p.nip           = pt.nip
    INNER JOIN bangsal    b   ON p.kd_bangsal     = b.kd_bangsal
    WHERE p.no_faktur = ?
    LIMIT 1
";

$stmt_info = $koneksi->prepare($sql_info);
if ($stmt_info) {
    $stmt_info->bind_param('s', $no_faktur);
    $stmt_info->execute();
    $res_info = $stmt_info->get_result();
    if ($row_info = $res_info->fetch_assoc()) {
        $response['faktur_info'] = $row_info;
    }
    $stmt_info->close();
}

// -----------------------------------------------
// 2. Rincian Item Obat dari tabel detailpesan
//    (nama tabel benar sesuai kode Java asli SIMRS Khanza)
// -----------------------------------------------
$sql_detail = "
    SELECT
        dp.kode_brng,
        db.nama_brng,
        dp.kode_sat,
        ks.satuan,
        dp.jumlah,
        dp.h_pesan       AS h_beli,
        dp.subtotal,
        dp.dis           AS diskon_persen,
        dp.besardis      AS diskon,
        dp.total,
        dp.no_batch,
        dp.kadaluarsa
    FROM detailpesan dp
    INNER JOIN databarang  db ON dp.kode_brng = db.kode_brng
    INNER JOIN kodesatuan  ks ON dp.kode_sat  = ks.kode_sat
    WHERE dp.no_faktur = ?
    ORDER BY db.nama_brng ASC
";

$stmt_det = $koneksi->prepare($sql_detail);
if ($stmt_det) {
    $stmt_det->bind_param('s', $no_faktur);
    $stmt_det->execute();
    $res_det = $stmt_det->get_result();

    while ($row = $res_det->fetch_assoc()) {
        $row['h_beli']        = (float)$row['h_beli'];
        $row['jumlah']        = (float)$row['jumlah'];
        $row['subtotal']      = (float)$row['subtotal'];
        $row['diskon_persen'] = (float)$row['diskon_persen'];
        $row['diskon']        = (float)$row['diskon'];
        $row['total']         = (float)$row['total'];
        $response['items'][] = $row;
        $response['total_items']++;
    }
    $stmt_det->close();
} else {
    // Jika prepare gagal, sertakan error untuk debugging
    $response['query_error'] = $koneksi->error;
}

echo json_encode($response);
?>
