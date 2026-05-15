<?php
/*
 * File: api/data_indikator_per_bangsal.php
 * Fungsi: Menghitung Indikator Barber Johnson per Bangsal.
 * Logika Spesifik:
 * - Pasien Pindah Kamar DIHITUNG sebagai Pasien Keluar (D) untuk bangsal tersebut.
 * - Menggunakan LEFT JOIN agar bangsal yang kosong tetap muncul di laporan.
 */

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

// 1. Ambil Parameter
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// 2. Hitung Periode Hari (t)
$start = new DateTime($tgl_awal);
$end = new DateTime($tgl_akhir);
$days_period = $end->diff($start)->days + 1;

// 3. Ambil Data Master Bangsal & Jumlah Bed (A)
// Kita hanya mengambil bangsal yang memiliki kamar aktif
$bangsal_data = [];
$sql_bed = "
    SELECT 
        bangsal.kd_bangsal, 
        bangsal.nm_bangsal, 
        COUNT(kamar.kd_kamar) as jumlah_bed
    FROM bangsal 
    INNER JOIN kamar ON bangsal.kd_bangsal = kamar.kd_bangsal
    WHERE kamar.statusdata='1' 
    GROUP BY bangsal.kd_bangsal
    ORDER BY bangsal.nm_bangsal ASC
";
$res_bed = $koneksi->query($sql_bed);
while($row = $res_bed->fetch_assoc()) {
    $bangsal_data[$row['kd_bangsal']] = [
        'nm_bangsal' => $row['nm_bangsal'],
        'bed' => (int)$row['jumlah_bed'],
        'hp' => 0,
        'd' => 0,
        'mati' => 0,
        'mati_48' => 0
    ];
}

// 4. Ambil Data Transaksi Pasien per Bangsal
// Join kamar_inap -> kamar -> bangsal
// Logika D: Hitung semua baris (Termasuk Pindah Kamar)
$sql_transaksi = "
    SELECT 
        kamar.kd_bangsal,
        SUM(IF(DATEDIFF(ki.tgl_keluar, ki.tgl_masuk) = 0, 1, DATEDIFF(ki.tgl_keluar, ki.tgl_masuk))) as total_hp,
        COUNT(ki.no_rawat) as total_keluar,
        SUM(IF(ki.stts_pulang = 'Meninggal', 1, 0)) as total_mati,
        SUM(IF(ki.stts_pulang = 'Meninggal' AND DATEDIFF(ki.tgl_keluar, ki.tgl_masuk) >= 2, 1, 0)) as mati_lebih_48
    FROM kamar_inap ki
    INNER JOIN kamar ON ki.kd_kamar = kamar.kd_kamar
    WHERE ki.tgl_keluar BETWEEN '$tgl_awal' AND '$tgl_akhir'
    GROUP BY kamar.kd_bangsal
";

$res_trans = $koneksi->query($sql_transaksi);
if ($res_trans) {
    while($row = $res_trans->fetch_assoc()) {
        $kd = $row['kd_bangsal'];
        // Update array master jika bangsal ada (jika bangsal tidak punya bed aktif, diabaikan)
        if (isset($bangsal_data[$kd])) {
            $bangsal_data[$kd]['hp'] = (int)$row['total_hp'];
            $bangsal_data[$kd]['d'] = (int)$row['total_keluar'];
            $bangsal_data[$kd]['mati'] = (int)$row['total_mati'];
            $bangsal_data[$kd]['mati_48'] = (int)$row['mati_lebih_48'];
        }
    }
}

// 5. Kalkulasi Indikator per Bangsal
$final_data = [];
foreach ($bangsal_data as $row) {
    $bed = $row['bed'];
    $hp = $row['hp'];
    $d = $row['d'];
    $mati = $row['mati'];
    $mati_48 = $row['mati_48'];

    // Mencegah division by zero
    $pembagi_d = ($d == 0) ? 1 : $d;
    $pembagi_bed = ($bed == 0) ? 1 : $bed;

    // Rumus Barber Johnson
    $bor = ($hp / ($bed * $days_period)) * 100;
    $alos = $hp / $pembagi_d;
    $toi = (($bed * $days_period) - $hp) / $pembagi_d;
    $bto = $d / $pembagi_bed;
    $gdr = ($mati / $pembagi_d) * 1000;
    $ndr = ($mati_48 / $pembagi_d) * 1000;

    $final_data[] = [
        'bangsal' => $row['nm_bangsal'],
        'bed' => $bed,
        'hp' => $hp,
        'd' => $d, // Ini sudah termasuk Pindah Kamar
        'bor' => round($bor, 2),
        'alos' => round($alos, 2),
        'toi' => round($toi, 2),
        'bto' => round($bto, 2),
        'gdr' => round($gdr, 2),
        'ndr' => round($ndr, 2)
    ];
}

// 6. Kirim Response
echo json_encode(['data' => $final_data]);
?>