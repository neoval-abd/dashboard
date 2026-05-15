<?php
/*
 * File api/data_grafik.php (PERBAIKAN)
 * API untuk menyuplai data JSON ke semua grafik di dashboard.
 * Logika shift sekarang dinamis berdasarkan tabel closing_kasir.
 * PHP 7.3 compatible.
 */

// 1. Set Header sebagai JSON
header('Content-Type: application/json');

// 2. Sertakan Koneksi & Fungsi
require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/includes/functions.php');

// 3. Keamanan: Pastikan hanya user yang sudah login yang bisa mengakses
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); 
    echo json_encode(['error' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit;
}

// 4. Ambil Parameter Filter Tanggal
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// 5. Siapkan Data Pendukung
// Komentar: Mengambil jam kerja per shift dari database
$shift_times = getShiftTimes($koneksi);

// --- PERUBAHAN DIMULAI DI SINI ---
// 6. Inisialisasi Struktur Data JSON secara Dinamis
$shift_labels = array_keys($shift_times);
$shift_data = array_fill(0, count($shift_labels), 0);
// Buat Peta (Map) untuk memetakan nama shift ke index array
// Contoh: ['Pagi'=>0, 'Siang'=>1, 'Malam'=>2]
$shift_index_map = array_flip($shift_labels); 

$response = [
    'tren_harian' => [
        'labels' => [],
        'pemasukan' => [],
        'pengeluaran' => []
    ],
    'per_shift' => [
        'labels' => $shift_labels, // Dinamis dari database
        'data' => $shift_data      // Dinamis (array [0, 0, 0, ...])
    ],
    'komposisi_pemasukan' => [
        'labels' => ['Ralan', 'Ranap', 'Lain-lain'],
        'data' => [0, 0, 0]
    ],
    'komposisi_pengeluaran' => [
        'labels' => [],
        'data' => []
    ]
];

$temp_pengeluaran = [];
// --- PERUBAHAN INISIALISASI SELESAI ---

// 7. Siapkan rentang tanggal untuk di-loop
$start_date = new DateTime($tgl_awal);
$end_date = new DateTime($tgl_akhir);
$end_date->modify('+1 day');

$interval = new DateInterval('P1D');
$date_range = new DatePeriod($start_date, $interval, $end_date);

// 8. Proses Utama: Loop Per Hari dan Per Shift
if ($date_range) {
    foreach ($date_range as $tanggal) {
        $tanggal_str = $tanggal->format('Y-m-d');
        
        $daily_pemasukan_tunai = 0;
        $daily_pengeluaran = 0;

        foreach ($shift_times as $nama_shift => $times) {
            
            $range = getShiftDateTimeRange($tanggal_str, $nama_shift, $shift_times);

            $total_ralan_shift = 0;
            $total_ranap_shift = 0;
            $total_lain_shift = 0;
            
            // --- A. Hitung Pemasukan Ralan (Tunai) ---
            $sql_ralan = "
                SELECT SUM(billing.totalbiaya) AS Total
                FROM billing
                INNER JOIN nota_jalan ON billing.no_rawat = nota_jalan.no_rawat
                WHERE 
                    CONCAT(nota_jalan.tanggal, ' ', nota_jalan.jam) BETWEEN ? AND ?
                    AND billing.no_rawat NOT IN (
                        SELECT piutang_pasien.no_rawat 
                        FROM piutang_pasien 
                        WHERE piutang_pasien.no_rawat = billing.no_rawat
                    )
                    AND billing.status NOT IN ('Potongan', 'Retur Obat')
            ";
            $stmt_ralan = $koneksi->prepare($sql_ralan);
            $stmt_ralan->bind_param("ss", $range['start'], $range['end']);
            $stmt_ralan->execute();
            $result_ralan = $stmt_ralan->get_result();
            if ($result_ralan) {
                $total_ralan_shift = (float) $result_ralan->fetch_assoc()['Total'];
            }
            $stmt_ralan->close();

            // --- B. Hitung Pemasukan Ranap (Tunai) ---
            $sql_ranap = "
                SELECT SUM(billing.totalbiaya) AS Total
                FROM billing
                INNER JOIN nota_inap ON billing.no_rawat = nota_inap.no_rawat
                WHERE 
                    CONCAT(nota_inap.tanggal, ' ', nota_inap.jam) BETWEEN ? AND ?
                    AND billing.no_rawat NOT IN (
                        SELECT piutang_pasien.no_rawat 
                        FROM piutang_pasien 
                        WHERE piutang_pasien.no_rawat = billing.no_rawat
                    )
                    AND billing.status NOT IN ('Potongan', 'Retur Obat')
            ";
            $stmt_ranap = $koneksi->prepare($sql_ranap);
            $stmt_ranap->bind_param("ss", $range['start'], $range['end']);
            $stmt_ranap->execute();
            $result_ranap = $stmt_ranap->get_result();
            if ($result_ranap) {
                $total_ranap_shift = (float) $result_ranap->fetch_assoc()['Total'];
            }
            $stmt_ranap->close();

            // --- C. Hitung Pemasukan Lain ---
            $sql_lain = "
                SELECT SUM(pemasukan_lain.besar) AS Total
                FROM pemasukan_lain
                WHERE pemasukan_lain.tanggal BETWEEN ? AND ?
            ";
            $stmt_lain = $koneksi->prepare($sql_lain);
            $stmt_lain->bind_param("ss", $range['start'], $range['end']);
            $stmt_lain->execute();
            $result_lain = $stmt_lain->get_result();
            if ($result_lain) {
                $total_lain_shift = (float) $result_lain->fetch_assoc()['Total'];
            }
            $stmt_lain->close();
            
            // --- D. Hitung Pengeluaran Harian ---
            $sql_keluar = "
                SELECT SUM(pengeluaran_harian.biaya) AS Total
                FROM pengeluaran_harian
                WHERE pengeluaran_harian.tanggal BETWEEN ? AND ?
            ";
            $stmt_keluar = $koneksi->prepare($sql_keluar);
            $stmt_keluar->bind_param("ss", $range['start'], $range['end']);
            $stmt_keluar->execute();
            $result_keluar = $stmt_keluar->get_result();
            if ($result_keluar) {
                $daily_pengeluaran += (float) $result_keluar->fetch_assoc()['Total'];
            }
            $stmt_keluar->close();

            // --- E. Hitung Kategori Pengeluaran (Untuk Pie Chart) ---
            $sql_kat_keluar = "
                SELECT 
                    kategori_pengeluaran_harian.nama_kategori, 
                    SUM(pengeluaran_harian.biaya) AS Subtotal
                FROM pengeluaran_harian 
                INNER JOIN kategori_pengeluaran_harian 
                    ON pengeluaran_harian.kode_kategori = kategori_pengeluaran_harian.kode_kategori
                WHERE pengeluaran_harian.tanggal BETWEEN ? AND ?
                GROUP BY kategori_pengeluaran_harian.nama_kategori
            ";
            $stmt_kat_keluar = $koneksi->prepare($sql_kat_keluar);
            $stmt_kat_keluar->bind_param("ss", $range['start'], $range['end']);
            $stmt_kat_keluar->execute();
            $result_kat = $stmt_kat_keluar->get_result();
            if ($result_kat) {
                while ($row_kat = $result_kat->fetch_assoc()) {
                    $kategori = $row_kat['nama_kategori'];
                    $subtotal = (float) $row_kat['Subtotal'];
                    if (!isset($temp_pengeluaran[$kategori])) {
                        $temp_pengeluaran[$kategori] = 0;
                    }
                    $temp_pengeluaran[$kategori] += $subtotal;
                }
            }
            $stmt_kat_keluar->close();

            
            // --- F. Agregasi Data ke Array Response ---
            $total_pemasukan_shift = $total_ralan_shift + $total_ranap_shift + $total_lain_shift;
            $daily_pemasukan_tunai += $total_pemasukan_shift;
            
            // --- PERUBAHAN DI SINI ---
            // Agregat untuk 'per_shift' (Bar Chart) - DINAMIS
            // Komentar: Menggunakan peta/map untuk memasukkan data ke index yang benar
            $index = $shift_index_map[$nama_shift];
            $response['per_shift']['data'][$index] += $total_pemasukan_shift;
            // --- AKHIR PERUBAHAN ---

            // Agregat untuk 'komposisi_pemasukan' (Pie Chart)
            $response['komposisi_pemasukan']['data'][0] += $total_ralan_shift;
            $response['komposisi_pemasukan']['data'][1] += $total_ranap_shift;
            $response['komposisi_pemasukan']['data'][2] += $total_lain_shift;

        } // Akhir loop per shift

        // 1. Agregat untuk 'tren_harian' (Line Chart)
        $response['tren_harian']['labels'][] = $tanggal->format('d-m-Y');
        $response['tren_harian']['pemasukan'][] = $daily_pemasukan_tunai;
        $response['tren_harian']['pengeluaran'][] = $daily_pengeluaran;

    } // Akhir loop per hari
}

// 9. Finalisasi Data Kategori Pengeluaran
arsort($temp_pengeluaran); 
foreach ($temp_pengeluaran as $kategori => $total) {
    $response['komposisi_pengeluaran']['labels'][] = $kategori;
    $response['komposisi_pengeluaran']['data'][] = $total;
}

// 10. Kirim Response JSON
echo json_encode($response);

// 11. Tutup Koneksi
$koneksi->close();

?>