<?php
/*
 * File: api/data_indikator_ranap.php
 * Fungsi: Menghitung Indikator (BOR, ALOS, TOI, BTO, NDR, GDR)
 * Logika: 
 * - Hari Perawatan (HP): Sum DATEDIFF semua pasien keluar (termasuk pindah).
 * - Pasien Keluar (D): Count pasien (kecuali pindah kamar).
 * - Bed (TT): Count kamar aktif.
 */

// 1. Set header JSON
header('Content-Type: application/json');

// 2. Include koneksi dan fungsi bantu
require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/config/bed_sk_mapping.php');
require_once(dirname(__DIR__) . '/includes/functions.php');

// 3. Cek sesi login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); 
    echo json_encode(['error' => 'Akses ditolak.']); 
    exit;
}

// 4. Ambil Parameter Filter dari URL
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_bangsal = isset($_GET['kd_bangsal']) ? $_GET['kd_bangsal'] : ''; // Opsional: Filter per bangsal

// 5. Hitung Jumlah Hari dalam Periode (t)
// Rumus: (Tgl Akhir - Tgl Awal) + 1
$start = new DateTime($tgl_awal);
$end = new DateTime($tgl_akhir);
$days_period = $end->diff($start)->days + 1;

// 6. Siapkan Variabel Penampung
$total_bed = 0;         // A (Available Beds)
$hari_perawatan = 0;    // HP (Hari Perawatan)
$pasien_keluar = 0;     // D (Discharges - Hidup+Mati)
$pasien_mati = 0;       // Total Mati (untuk GDR)
$pasien_mati_48 = 0;    // Mati > 48 Jam (untuk NDR)

// ---------------------------------------------------------
// LANGKAH A: Hitung Jumlah Tempat Tidur (TT)
// ---------------------------------------------------------
// Pelaporan BPJS memakai kapasitas SK lokal, bukan seluruh bed operasional Khanza.
$total_bed = countSkBeds($koneksi, $kd_bangsal);
if ($total_bed == 0) {
    $total_bed = 1;
}


// ---------------------------------------------------------
// LANGKAH B: Hitung Hari Perawatan (HP) - NUMERATOR BOR
// ---------------------------------------------------------
// HP dihitung sebagai sensus harian bed yang masuk mapping SK.
$hari_perawatan = calculateSkHariPerawatan($koneksi, $tgl_awal, $tgl_akhir, $kd_bangsal);


// ---------------------------------------------------------
// LANGKAH C: Hitung Pasien Keluar (D) & Kematian
// ---------------------------------------------------------
// Logic: Pasien keluar Hidup + Mati.
// PENTING: Exclude 'Pindah Kamar' agar tidak double count untuk ALOS/BTO.

$bed_in = getSkBedInSql($koneksi);
$where_bangsal = '';
if (!empty($kd_bangsal)) {
    $where_bangsal = " AND k.kd_bangsal = '" . $koneksi->real_escape_string($kd_bangsal) . "'";
}

$sql_pasien = "
    SELECT 
        COUNT(ki.no_rawat) as total_keluar,
        SUM(IF(ki.stts_pulang = 'Meninggal', 1, 0)) as total_mati,
        SUM(IF(ki.stts_pulang = 'Meninggal' AND DATEDIFF(ki.tgl_keluar, ki.tgl_masuk) >= 2, 1, 0)) as mati_lebih_48
    FROM kamar_inap ki
    INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
    WHERE ki.kd_kamar IN ($bed_in)
    AND ki.tgl_keluar BETWEEN '$tgl_awal' AND '$tgl_akhir'
    AND ki.stts_pulang != 'Pindah Kamar'
    $where_bangsal
";

$res_pasien = $koneksi->query($sql_pasien);
if ($res_pasien) {
    $row = $res_pasien->fetch_assoc();
    $pasien_keluar = (int)$row['total_keluar'];
    $pasien_mati = (int)$row['total_mati'];
    $pasien_mati_48 = (int)$row['mati_lebih_48'];
}

// Hindari division by zero untuk pasien keluar
$pembagi_pasien = ($pasien_keluar == 0) ? 1 : $pasien_keluar;


// ---------------------------------------------------------
// LANGKAH D: Kalkulasi Indikator 
// ---------------------------------------------------------

// 1. BOR (Bed Occupancy Rate) %
// Rumus: (Hari Perawatan / (TT x Periode)) * 100
$bor = ($hari_perawatan / ($total_bed * $days_period)) * 100;

// 2. ALOS (Average Length of Stay) Hari
// Rumus: Hari Perawatan / Pasien Keluar (Hidup+Mati)
$alos = $hari_perawatan / $pembagi_pasien;

// 3. TOI (Turn Over Interval) Hari
// Rumus: ((TT x Periode) - Hari Perawatan) / Pasien Keluar
$toi = (($total_bed * $days_period) - $hari_perawatan) / $pembagi_pasien;

// 4. BTO (Bed Turn Over) Kali
// Rumus: Pasien Keluar / TT
$bto = $pasien_keluar / $total_bed;

// 5. GDR (Gross Death Rate) Permil
// Rumus: (Total Mati / Pasien Keluar) * 1000
$gdr = ($pasien_mati / $pembagi_pasien) * 1000;

// 6. NDR (Net Death Rate) Permil
// Rumus: (Mati > 48 Jam / Pasien Keluar) * 1000
$ndr = ($pasien_mati_48 / $pembagi_pasien) * 1000;


// ---------------------------------------------------------
// OUTPUT JSON
// ---------------------------------------------------------
$response = [
    'periode' => [
        'hari' => $days_period,
        'awal' => $tgl_awal,
        'akhir' => $tgl_akhir
    ],
    'data_dasar' => [
        'jumlah_bed' => $total_bed,
        'hari_perawatan' => $hari_perawatan,
        'pasien_keluar' => $pasien_keluar,
        'pasien_mati' => $pasien_mati,
        'pasien_mati_48' => $pasien_mati_48
    ],
    'indikator' => [
        'bor' => round($bor, 2),
        'alos' => round($alos, 2),
        'toi' => round($toi, 2),
        'bto' => round($bto, 2),
        'gdr' => round($gdr, 2),
        'ndr' => round($ndr, 2)
    ]
];

echo json_encode($response);
?>
