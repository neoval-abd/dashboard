<?php
/*
 * File: api/data_detail_kinerja_dokter.php
 * Fungsi: Menampilkan rincian kunjungan pasien per dokter (Ralan & Ranap).
 * Logika Billing: Mengambil SUM totalbiaya dari tabel billing (sesuai laporan_billing_global).
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_dokter = isset($_GET['kd_dokter']) ? $_GET['kd_dokter'] : '';

if (empty($kd_dokter)) {
    echo json_encode(['data' => []]);
    exit;
}

$data = [];

// --- QUERY 1: RAWAT JALAN (Dokter Utama di reg_periksa) ---
// Join ke nota_jalan untuk ambil waktu tutup billing
$sql_ralan = "
    SELECT 
        reg_periksa.no_rawat,
        reg_periksa.tgl_registrasi,
        reg_periksa.jam_reg,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        penjab.png_jawab,
        'Ralan' as status_lanjut,
        nota_jalan.tanggal as tgl_tutup,
        nota_jalan.jam as jam_tutup,
        (SELECT SUM(
            CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END
        ) FROM billing WHERE billing.no_rawat = reg_periksa.no_rawat) AS total_billing
    FROM reg_periksa
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN nota_jalan ON reg_periksa.no_rawat = nota_jalan.no_rawat
    WHERE 
        reg_periksa.kd_dokter = ?
        AND reg_periksa.status_lanjut = 'Ralan'
        AND reg_periksa.tgl_registrasi BETWEEN ? AND ?
        AND reg_periksa.stts != 'Batal'
";

$stmt = $koneksi->prepare($sql_ralan);
$stmt->bind_param("sss", $kd_dokter, $tgl_awal, $tgl_akhir);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $data[] = formatRow($row);
}
$stmt->close();

// --- QUERY 2: RAWAT INAP (Dokter dari tabel dpjp_ranap) ---
// Join ke nota_inap untuk ambil waktu tutup billing
$sql_ranap = "
    SELECT 
        reg_periksa.no_rawat,
        reg_periksa.tgl_registrasi,
        reg_periksa.jam_reg,
        reg_periksa.no_rkm_medis,
        pasien.nm_pasien,
        penjab.png_jawab,
        'Ranap' as status_lanjut,
        nota_inap.tanggal as tgl_tutup,
        nota_inap.jam as jam_tutup,
        (SELECT SUM(
            CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END
        ) FROM billing WHERE billing.no_rawat = reg_periksa.no_rawat) AS total_billing
    FROM dpjp_ranap
    INNER JOIN reg_periksa ON dpjp_ranap.no_rawat = reg_periksa.no_rawat
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN nota_inap ON reg_periksa.no_rawat = nota_inap.no_rawat
    WHERE 
        dpjp_ranap.kd_dokter = ?
        AND reg_periksa.tgl_registrasi BETWEEN ? AND ?
        AND reg_periksa.stts != 'Batal'
";

$stmt = $koneksi->prepare($sql_ranap);
$stmt->bind_param("sss", $kd_dokter, $tgl_awal, $tgl_akhir);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $data[] = formatRow($row);
}
$stmt->close();

// Helper function untuk format output
function formatRow($row) {
    return [
        'tgl_reg' => $row['tgl_registrasi'] . ' ' . $row['jam_reg'],
        'tgl_tutup' => ($row['tgl_tutup']) ? $row['tgl_tutup'] . ' ' . $row['jam_tutup'] : '-',
        'no_rawat' => $row['no_rawat'],
        'no_rm' => $row['no_rkm_medis'],
        'pasien' => $row['nm_pasien'],
        'penjamin' => $row['png_jawab'],
        'status' => $row['status_lanjut'],
        'total' => (float)$row['total_billing']
    ];
}

// Urutkan berdasarkan tanggal registrasi descending
usort($data, function($a, $b) {
    return strtotime($b['tgl_reg']) - strtotime($a['tgl_reg']);
});

echo json_encode(['data' => $data]);
$koneksi->close();
?>