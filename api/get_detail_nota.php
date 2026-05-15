<?php
/*
 * File api/get_detail_nota.php (PERBAIKAN V3 - Fix Retur/Potongan)
 * - Menambahkan logika CASE WHEN untuk mengubah nilai Retur/Potongan menjadi negatif
 * PHP 7.3 compatible.
 */

// 1. Set Header sebagai JSON
header('Content-Type: application/json');

// 2. Sertakan Koneksi & Fungsi
require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/includes/functions.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Keamanan
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); 
    echo json_encode(['error' => 'Akses ditolak.']);
    exit;
}

// 4. Ambil Parameter
$no_rawat = isset($_GET['no_rawat']) ? trim($_GET['no_rawat']) : '';

if (empty($no_rawat)) {
    http_response_code(400); 
    echo json_encode(['error' => 'No. Rawat tidak valid.']);
    exit;
}

// 5. Siapkan Kueri (LOGIKA BARU)
// Komentar: Perhatikan CASE WHEN pada kolom 'totalbiaya'.
// Jika status adalah 'Retur Obat' atau 'Potongan', kita kalikan -1 agar menjadi pengurang.
$sql_billing = "
    SELECT 
        billing.noindex,
        billing.no,
        billing.nm_perawatan, 
        billing.status,
        billing.biaya,
        billing.jumlah,
        (CASE 
            WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
            WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
            ELSE billing.totalbiaya 
        END) AS totalbiaya
    FROM billing 
    WHERE billing.no_rawat = ?      
    ORDER BY billing.noindex
";

// 6. Eksekusi Kueri
$stmt = $koneksi->prepare($sql_billing);
if ($stmt === false) {
    http_response_code(500); 
    echo json_encode(['error' => 'Gagal mempersiapkan kueri: ' . $koneksi->error]);
    exit;
}

$stmt->bind_param("s", $no_rawat);
$stmt->execute();
$result = $stmt->get_result();

// 7. Fetch Data
$data_billing = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data_billing[] = $row;
    }
}

$stmt->close();
$koneksi->close();

echo json_encode($data_billing);
?>