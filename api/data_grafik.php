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
        'pengeluaran' => [],
        'obat_bhp' => []
    ],
    'per_shift' => [
        'labels' => $shift_labels, // Dinamis dari database
        'data' => $shift_data      // Dinamis (array [0, 0, 0, ...])
    ],
    'komposisi_pemasukan' => [
        'labels' => ['Ralan', 'Ranap', 'Obat & BHP', 'Lain-lain'],
        'data' => [0, 0, 0, 0]
    ],
    'komposisi_pengeluaran' => [
        'labels' => [],
        'data' => []
    ],
    'top_obat_qty' => [
        'labels' => [],
        'data' => [],
        'satuan' => []
    ],
    'top_obat_nominal' => [
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
        $daily_obat_bhp = 0;

        foreach ($shift_times as $nama_shift => $times) {
            
            $shift_ranges = getShiftRanges($tanggal_str, $nama_shift, $shift_times);

            $total_ralan_shift = 0;
            $total_ranap_shift = 0;
            $total_obat_shift = 0;
            $total_lain_shift = 0;
            
            foreach ($shift_ranges as $sr) {
                // --- A. Hitung Pemasukan Ralan (Tunai) ---
                $sql_ralan = "
                    SELECT SUM(
                        CASE 
                            WHEN billing.status = 'Retur Obat' THEN (billing.totalbiaya * -1)
                            WHEN billing.status = 'Potongan' THEN (billing.totalbiaya * -1)
                            ELSE billing.totalbiaya 
                        END
                    ) AS Total
                    FROM billing
                    INNER JOIN nota_jalan ON billing.no_rawat = nota_jalan.no_rawat
                    WHERE 
                        CONCAT(nota_jalan.tanggal, ' ', nota_jalan.jam) BETWEEN ? AND ?
                        AND billing.no_rawat NOT IN (
                            SELECT piutang_pasien.no_rawat 
                            FROM piutang_pasien 
                            WHERE piutang_pasien.no_rawat = billing.no_rawat
                        )
                ";
                $stmt_ralan = $koneksi->prepare($sql_ralan);
                if ($stmt_ralan) {
                    $stmt_ralan->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_ralan->execute();
                    $result_ralan = $stmt_ralan->get_result();
                    if ($result_ralan && $r = $result_ralan->fetch_assoc()) {
                        $total_ralan_shift += (float) $r['Total'];
                    }
                    $stmt_ralan->close();
                }

                // --- B. Hitung Pemasukan Ranap (Tunai) ---
                $sql_ranap = "
                    SELECT SUM(
                        CASE 
                            WHEN billing.status = 'Retur Obat' THEN (billing.totalbiaya * -1)
                            WHEN billing.status = 'Potongan' THEN (billing.totalbiaya * -1)
                            ELSE billing.totalbiaya 
                        END
                    ) AS Total
                    FROM billing
                    INNER JOIN nota_inap ON billing.no_rawat = nota_inap.no_rawat
                    WHERE 
                        CONCAT(nota_inap.tanggal, ' ', nota_inap.jam) BETWEEN ? AND ?
                        AND billing.no_rawat NOT IN (
                            SELECT piutang_pasien.no_rawat 
                            FROM piutang_pasien 
                            WHERE piutang_pasien.no_rawat = billing.no_rawat
                        )
                ";
                $stmt_ranap = $koneksi->prepare($sql_ranap);
                if ($stmt_ranap) {
                    $stmt_ranap->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_ranap->execute();
                    $result_ranap = $stmt_ranap->get_result();
                    if ($result_ranap && $r = $result_ranap->fetch_assoc()) {
                        $total_ranap_shift += (float) $r['Total'];
                    }
                    $stmt_ranap->close();
                }

                // --- C. Hitung Penjualan Obat Bebas & BHP (Tunai / Lunas) ---
                $sql_obat = "
                    SELECT 
                        COALESCE(SUM(sub.total_nota), 0) AS Total
                    FROM (
                        SELECT 
                            p.nota_jual,
                            (COALESCE(SUM(d.total), 0) + p.ppn + p.ongkir) AS total_nota
                        FROM penjualan p
                        INNER JOIN detailjual d ON p.nota_jual = d.nota_jual
                        LEFT JOIN tagihan_sadewa ts ON p.nota_jual = ts.no_nota
                        WHERE 
                            COALESCE(ts.tgl_bayar, CONCAT(p.tgl_jual, ' 08:00:00')) BETWEEN ? AND ?
                            AND p.status = 'Sudah Dibayar'
                        GROUP BY p.nota_jual, p.ppn, p.ongkir
                    ) AS sub
                ";
                $stmt_obat = $koneksi->prepare($sql_obat);
                if ($stmt_obat) {
                    $stmt_obat->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_obat->execute();
                    $result_obat = $stmt_obat->get_result();
                    if ($result_obat && $r = $result_obat->fetch_assoc()) {
                        $total_obat_shift += (float) $r['Total'];
                    }
                    $stmt_obat->close();
                }

                // --- D. Hitung Pemasukan Lain ---
                $sql_lain = "
                    SELECT SUM(pemasukan_lain.besar) AS Total
                    FROM pemasukan_lain
                    WHERE pemasukan_lain.tanggal BETWEEN ? AND ?
                ";
                $stmt_lain = $koneksi->prepare($sql_lain);
                if ($stmt_lain) {
                    $stmt_lain->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_lain->execute();
                    $result_lain = $stmt_lain->get_result();
                    if ($result_lain && $r = $result_lain->fetch_assoc()) {
                        $total_lain_shift += (float) $r['Total'];
                    }
                    $stmt_lain->close();
                }
                
                // --- E. Hitung Pengeluaran Harian ---
                $sql_keluar = "
                    SELECT SUM(pengeluaran_harian.biaya) AS Total
                    FROM pengeluaran_harian
                    WHERE pengeluaran_harian.tanggal BETWEEN ? AND ?
                ";
                $stmt_keluar = $koneksi->prepare($sql_keluar);
                if ($stmt_keluar) {
                    $stmt_keluar->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_keluar->execute();
                    $result_keluar = $stmt_keluar->get_result();
                    if ($result_keluar && $r = $result_keluar->fetch_assoc()) {
                        $daily_pengeluaran += (float) $r['Total'];
                    }
                    $stmt_keluar->close();
                }

                // --- F. Hitung Kategori Pengeluaran (Untuk Pie Chart) ---
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
                if ($stmt_kat_keluar) {
                    $stmt_kat_keluar->bind_param("ss", $sr['start'], $sr['end']);
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
                }
            }

            // --- G. Agregasi Data ke Array Response ---
            $total_pemasukan_shift = $total_ralan_shift + $total_ranap_shift + $total_obat_shift + $total_lain_shift;
            $daily_pemasukan_tunai += $total_pemasukan_shift;
            $daily_obat_bhp += $total_obat_shift;
            
            // Agregat untuk 'per_shift' (Bar Chart) - DINAMIS
            $index = $shift_index_map[$nama_shift];
            $response['per_shift']['data'][$index] += $total_pemasukan_shift;

            // Agregat untuk 'komposisi_pemasukan' (Pie Chart)
            $response['komposisi_pemasukan']['data'][0] += $total_ralan_shift;
            $response['komposisi_pemasukan']['data'][1] += $total_ranap_shift;
            $response['komposisi_pemasukan']['data'][2] += $total_obat_shift;
            $response['komposisi_pemasukan']['data'][3] += $total_lain_shift;

        } // Akhir loop per shift

        // 1. Agregat untuk 'tren_harian' (Line Chart)
        $response['tren_harian']['labels'][] = $tanggal->format('d-m-Y');
        $response['tren_harian']['pemasukan'][] = $daily_pemasukan_tunai;
        $response['tren_harian']['pengeluaran'][] = $daily_pengeluaran;
        $response['tren_harian']['obat_bhp'][] = $daily_obat_bhp;

    } // Akhir loop per hari
}

// 9. Finalisasi Data Kategori Pengeluaran
arsort($temp_pengeluaran); 
foreach ($temp_pengeluaran as $kategori => $total) {
    $response['komposisi_pengeluaran']['labels'][] = $kategori;
    $response['komposisi_pengeluaran']['data'][] = $total;
}

// 10. Top 10 Penjualan Obat Bebas / BHP (Berdasarkan Kuantitas)
$sql_top_qty = "
    SELECT 
        b.nama_brng,
        k.satuan,
        SUM(d.jumlah) AS total_qty
    FROM detailjual d
    INNER JOIN penjualan p ON d.nota_jual = p.nota_jual
    INNER JOIN databarang b ON d.kode_brng = b.kode_brng
    INNER JOIN kodesatuan k ON d.kode_sat = k.kode_sat
    WHERE p.tgl_jual BETWEEN ? AND ?
      AND p.status = 'Sudah Dibayar'
    GROUP BY d.kode_brng, b.nama_brng, k.satuan
    ORDER BY total_qty DESC
    LIMIT 10
";
$stmt_top_qty = $koneksi->prepare($sql_top_qty);
if ($stmt_top_qty) {
    $stmt_top_qty->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt_top_qty->execute();
    $res_top_qty = $stmt_top_qty->get_result();
    if ($res_top_qty) {
        while ($row = $res_top_qty->fetch_assoc()) {
            $response['top_obat_qty']['labels'][] = $row['nama_brng'];
            $response['top_obat_qty']['data'][] = (float) $row['total_qty'];
            $response['top_obat_qty']['satuan'][] = $row['satuan'];
        }
    }
    $stmt_top_qty->close();
}

// 11. Top 10 Penjualan Obat Bebas / BHP (Berdasarkan Nilai Penjualan / Nominal)
$sql_top_nom = "
    SELECT 
        b.nama_brng,
        SUM(d.total) AS total_nominal
    FROM detailjual d
    INNER JOIN penjualan p ON d.nota_jual = p.nota_jual
    INNER JOIN databarang b ON d.kode_brng = b.kode_brng
    WHERE p.tgl_jual BETWEEN ? AND ?
      AND p.status = 'Sudah Dibayar'
    GROUP BY d.kode_brng, b.nama_brng
    ORDER BY total_nominal DESC
    LIMIT 10
";
$stmt_top_nom = $koneksi->prepare($sql_top_nom);
if ($stmt_top_nom) {
    $stmt_top_nom->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt_top_nom->execute();
    $res_top_nom = $stmt_top_nom->get_result();
    if ($res_top_nom) {
        while ($row = $res_top_nom->fetch_assoc()) {
            $response['top_obat_nominal']['labels'][] = $row['nama_brng'];
            $response['top_obat_nominal']['data'][] = (float) $row['total_nominal'];
        }
    }
    $stmt_top_nom->close();
}

// 12. Kirim Response JSON
echo json_encode($response);

// 13. Tutup Koneksi
$koneksi->close();

?>