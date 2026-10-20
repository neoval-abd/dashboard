<?php
/*
 * File: api/get_detail_penjualan.php
 * Mengambil rincian penjualan obat bebas, alkes & BHP medis berdasarkan nota_jual.
 * Sesuai referensi SIMRS Khanza DlgCariPenjualan.java.
 * PHP 7.3 compatible.
 */

header('Content-Type: application/json; charset=utf-8');

require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/includes/functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Keamanan: Cek sesi login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); 
    echo json_encode(['error' => 'Akses ditolak. Sesi login tidak ditemukan.']);
    exit;
}

$nota_jual = isset($_GET['nota_jual']) ? trim($_GET['nota_jual']) : '';
if (empty($nota_jual)) {
    http_response_code(400); 
    echo json_encode(['error' => 'Parameter nota_jual wajib disertakan.']);
    exit;
}

// 1. Ambil Header Penjualan
$sql_header = "
    SELECT 
        p.nota_jual,
        p.tgl_jual,
        p.nip,
        COALESCE(pt.nama, '-') AS nama_petugas,
        p.no_rkm_medis,
        p.nm_pasien,
        p.keterangan,
        p.jns_jual,
        COALESCE(p.ongkir, 0) AS ongkir,
        COALESCE(p.ppn, 0) AS ppn,
        COALESCE(p.nama_bayar, '-') AS nama_bayar,
        COALESCE(b.nm_bangsal, '-') AS nm_bangsal,
        p.status,
        COALESCE(ts.tgl_bayar, CONCAT(p.tgl_jual, ' 08:00:00')) AS waktu_bayar
    FROM penjualan p
    LEFT JOIN petugas pt ON p.nip = pt.nip
    LEFT JOIN bangsal b ON p.kd_bangsal = b.kd_bangsal
    LEFT JOIN tagihan_sadewa ts ON p.nota_jual = ts.no_nota
    WHERE p.nota_jual = ?
    LIMIT 1
";

$stmt_header = $koneksi->prepare($sql_header);
if (!$stmt_header) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mempersiapkan kueri header: ' . $koneksi->error]);
    exit;
}

$stmt_header->bind_param("s", $nota_jual);
$stmt_header->execute();
$res_header = $stmt_header->get_result();
$header = $res_header->fetch_assoc();
$stmt_header->close();

if (!$header) {
    http_response_code(404);
    echo json_encode(['error' => 'Data penjualan dengan nota ' . htmlspecialchars($nota_jual) . ' tidak ditemukan.']);
    exit;
}

// 2. Ambil Item Detail Penjualan
$sql_items = "
    SELECT 
        d.kode_brng,
        COALESCE(b.nama_brng, '-') AS nama_brng,
        d.kode_sat,
        COALESCE(k.satuan, '-') AS satuan,
        d.h_jual,
        d.jumlah,
        d.subtotal,
        d.dis,
        d.bsr_dis,
        d.tambahan,
        d.embalase,
        d.tuslah,
        d.aturan_pakai,
        d.total,
        d.no_batch,
        d.no_faktur
    FROM detailjual d
    LEFT JOIN databarang b ON d.kode_brng = b.kode_brng
    LEFT JOIN kodesatuan k ON d.kode_sat = k.kode_sat
    WHERE d.nota_jual = ?
    ORDER BY d.kode_brng ASC
";

$stmt_items = $koneksi->prepare($sql_items);
$items = [];
if ($stmt_items) {
    $stmt_items->bind_param("s", $nota_jual);
    $stmt_items->execute();
    $res_items = $stmt_items->get_result();
    while ($row = $res_items->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt_items->close();
}

// 3. Ambil Racikan jika ada
$sql_racik = "
    SELECT 
        r.no_racik,
        r.nama_racik,
        r.kd_racik,
        COALESCE(m.nm_racik, '-') AS metode,
        r.jml_dr,
        r.aturan_pakai,
        r.keterangan
    FROM obat_racikan_jual r
    LEFT JOIN metode_racik m ON r.kd_racik = m.kd_racik
    WHERE r.nota_jual = ?
    ORDER BY r.no_racik ASC
";

$stmt_racik = $koneksi->prepare($sql_racik);
$racikan = [];
if ($stmt_racik) {
    $stmt_racik->bind_param("s", $nota_jual);
    $stmt_racik->execute();
    $res_racik = $stmt_racik->get_result();
    while ($row = $res_racik->fetch_assoc()) {
        $racikan[] = $row;
    }
    $stmt_racik->close();
}

echo json_encode([
    'success' => true,
    'header' => $header,
    'items' => $items,
    'racikan' => $racikan
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
