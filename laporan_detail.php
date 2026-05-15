<?php
/*
 * File laporan_detail.php (PEROMBAKAN TOTAL V3)
 * Menampilkan Laporan Detail Agregat per Hari, lalu per Shift.
 * Menggunakan Accordion untuk manajemen data yang besar.
 * PHP 7.3 compatible.
 */

// 1. Set Judul & Sertakan Header (Otomatis koneksi & session check)
$page_title = "Laporan Detail Transaksi per Shift";
require_once('includes/header.php');
require_once('includes/functions.php');

// 2. Ambil Parameter dari URL atau set default
$tgl_awal = isset($_GET['tgl_awal']) ? htmlspecialchars($_GET['tgl_awal']) : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? htmlspecialchars($_GET['tgl_akhir']) : date('Y-m-d');

// 3. Ambil Info Jam Shift
$shift_times = getShiftTimes($koneksi);
if (empty($shift_times)) {
    die("Error: Data 'closing_kasir' tidak ditemukan. Harap isi data shift.");
}

// 4. Siapkan Kueri SQL (Sudah termasuk permintaan kolom tambahan Anda)
// Kueri ini akan kita gunakan berulang kali di dalam loop nanti
$sql_ralan = "
    SELECT 
        reg_periksa.no_rawat, 
        nota_jalan.no_nota, 
        pasien.nm_pasien, 
        nota_jalan.tanggal, 
        nota_jalan.jam, 
        dokter.nm_dokter, 
        penjab.png_jawab,
        (SELECT SUM(
            CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END
        ) 
        FROM billing 
        WHERE billing.no_rawat = reg_periksa.no_rawat
        ) AS total_rupiah
    FROM reg_periksa 
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis 
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj 
    INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter 
    INNER JOIN nota_jalan ON reg_periksa.no_rawat = nota_jalan.no_rawat 
    WHERE reg_periksa.status_lanjut = 'Ralan' 
        AND reg_periksa.no_rawat NOT IN (
            SELECT piutang_pasien.no_rawat 
            FROM piutang_pasien 
            WHERE piutang_pasien.no_rawat = reg_periksa.no_rawat
        ) 
        AND CONCAT(nota_jalan.tanggal, ' ', nota_jalan.jam) BETWEEN ? AND ? 
    ORDER BY nota_jalan.tanggal, nota_jalan.jam
";
$stmt_ralan = $koneksi->prepare($sql_ralan);

$sql_ranap = "
    SELECT 
        reg_periksa.no_rawat, 
        nota_inap.no_nota, 
        pasien.nm_pasien, 
        nota_inap.tanggal, 
        nota_inap.jam, 
        penjab.png_jawab,
        (SELECT SUM(
            CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END
        ) 
        FROM billing 
        WHERE billing.no_rawat = reg_periksa.no_rawat
        ) AS total_rupiah,
        COALESCE(
            (SELECT dokter.nm_dokter 
             FROM dpjp_ranap 
             INNER JOIN dokter ON dpjp_ranap.kd_dokter = dokter.kd_dokter 
             WHERE dpjp_ranap.no_rawat = reg_periksa.no_rawat 
             LIMIT 1),
            (SELECT dokter.nm_dokter 
             FROM dokter 
             WHERE dokter.kd_dokter = reg_periksa.kd_dokter)
        ) AS dokter_dpjp
    FROM reg_periksa 
    INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis 
    INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj 
    INNER JOIN nota_inap ON reg_periksa.no_rawat = nota_inap.no_rawat 
    WHERE reg_periksa.status_lanjut = 'Ranap' 
        AND reg_periksa.no_rawat NOT IN (
            SELECT piutang_pasien.no_rawat 
            FROM piutang_pasien 
            WHERE piutang_pasien.no_rawat = reg_periksa.no_rawat
        ) 
        AND CONCAT(nota_inap.tanggal, ' ', nota_inap.jam) BETWEEN ? AND ? 
    ORDER BY nota_inap.tanggal, nota_inap.jam
";
$stmt_ranap = $koneksi->prepare($sql_ranap);

$sql_pemasukan = "
    SELECT pemasukan_lain.tanggal, pemasukan_lain.keterangan, pemasukan_lain.besar, 
           kategori_pemasukan_lain.nama_kategori 
    FROM pemasukan_lain 
    INNER JOIN kategori_pemasukan_lain ON pemasukan_lain.kode_kategori = kategori_pemasukan_lain.kode_kategori 
    WHERE pemasukan_lain.tanggal BETWEEN ? AND ? 
    ORDER BY pemasukan_lain.tanggal
";
$stmt_pemasukan = $koneksi->prepare($sql_pemasukan);

$sql_pengeluaran = "
    SELECT pengeluaran_harian.tanggal, pengeluaran_harian.keterangan, pengeluaran_harian.biaya, 
           kategori_pengeluaran_harian.nama_kategori 
    FROM pengeluaran_harian 
    INNER JOIN kategori_pengeluaran_harian 
        ON pengeluaran_harian.kode_kategori = kategori_pengeluaran_harian.kode_kategori 
    WHERE pengeluaran_harian.tanggal BETWEEN ? AND ? 
    ORDER BY pengeluaran_harian.tanggal
";
$stmt_pengeluaran = $koneksi->prepare($sql_pengeluaran);

// Cek jika ada kueri yang gagal di-prepare
if (!$stmt_ralan || !$stmt_ranap || !$stmt_pemasukan || !$stmt_pengeluaran) {
    die("Gagal mempersiapkan kueri SQL: " . $koneksi->error);
}

// --- KUERI AGREGAT HARIAN UNTUK SUMMARY ---
$sql_sum_ralan = "
    SELECT SUM(
        CASE 
            WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
            WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
            ELSE billing.totalbiaya 
        END
    ) AS total_harian
    FROM reg_periksa 
    INNER JOIN nota_jalan ON reg_periksa.no_rawat = nota_jalan.no_rawat 
    LEFT JOIN billing ON billing.no_rawat = reg_periksa.no_rawat
    WHERE reg_periksa.status_lanjut = 'Ralan' 
        AND reg_periksa.no_rawat NOT IN (
            SELECT piutang_pasien.no_rawat FROM piutang_pasien WHERE piutang_pasien.no_rawat = reg_periksa.no_rawat
        ) 
        AND nota_jalan.tanggal = ?
";
$stmt_sum_ralan = $koneksi->prepare($sql_sum_ralan);

$sql_sum_ranap = "
    SELECT SUM(
        CASE 
            WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
            WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
            ELSE billing.totalbiaya 
        END
    ) AS total_harian
    FROM reg_periksa 
    INNER JOIN nota_inap ON reg_periksa.no_rawat = nota_inap.no_rawat 
    LEFT JOIN billing ON billing.no_rawat = reg_periksa.no_rawat
    WHERE reg_periksa.status_lanjut = 'Ranap' 
        AND reg_periksa.no_rawat NOT IN (
            SELECT piutang_pasien.no_rawat FROM piutang_pasien WHERE piutang_pasien.no_rawat = reg_periksa.no_rawat
        ) 
        AND nota_inap.tanggal = ?
";
$stmt_sum_ranap = $koneksi->prepare($sql_sum_ranap);

$sql_sum_pemasukan = "SELECT SUM(besar) AS total_harian FROM pemasukan_lain WHERE tanggal = ?";
$stmt_sum_pemasukan = $koneksi->prepare($sql_sum_pemasukan);

$sql_sum_pengeluaran = "SELECT SUM(biaya) AS total_harian FROM pengeluaran_harian WHERE tanggal = ?";
$stmt_sum_pengeluaran = $koneksi->prepare($sql_sum_pengeluaran);

// 5. Siapkan loop tanggal
$start_date = new DateTime($tgl_awal);
$end_date = new DateTime($tgl_akhir);
$end_date->modify('+1 day'); 

$interval = new DateInterval('P1D');
$date_range = new DatePeriod($start_date, $interval, $end_date);

?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title">Filter Laporan Detail</h5>
            <form action="laporan_detail.php" method="GET" class="row g-3">
                <div class="col-md-5">
                    <label for="tgl_awal" class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="tgl_awal" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-5">
                    <label for="tgl_akhir" class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="tgl_akhir" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="accordion" id="accordionTanggal">
        <?php
        if ($date_range):
            $day_index = 0; // Untuk ID unik accordion
            foreach ($date_range as $tanggal):
                $tanggal_str = $tanggal->format('Y-m-d');
                $day_id = 'hari-' . $tanggal->format('Ymd');
                
                // Ambil summary harian
                $stmt_sum_ralan->bind_param("s", $tanggal_str);
                $stmt_sum_ralan->execute();
                $harian_ralan = $stmt_sum_ralan->get_result()->fetch_assoc()['total_harian'] ?? 0;

                $stmt_sum_ranap->bind_param("s", $tanggal_str);
                $stmt_sum_ranap->execute();
                $harian_ranap = $stmt_sum_ranap->get_result()->fetch_assoc()['total_harian'] ?? 0;

                $stmt_sum_pemasukan->bind_param("s", $tanggal_str);
                $stmt_sum_pemasukan->execute();
                $harian_pemasukan = $stmt_sum_pemasukan->get_result()->fetch_assoc()['total_harian'] ?? 0;

                $stmt_sum_pengeluaran->bind_param("s", $tanggal_str);
                $stmt_sum_pengeluaran->execute();
                $harian_pengeluaran = $stmt_sum_pengeluaran->get_result()->fetch_assoc()['total_harian'] ?? 0;
                
                $grand_total_harian = $harian_ralan + $harian_ranap + $harian_pemasukan - $harian_pengeluaran;
        ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading-<?php echo $day_id; ?>">
                <button class="accordion-button fs-5 fw-bold d-flex align-items-center gap-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $day_id; ?>" aria-expanded="true" aria-controls="collapse-<?php echo $day_id; ?>">
                    <span class="me-auto">Laporan Tanggal: <?php echo $tanggal->format('d-m-Y'); ?></span>
                    <span class="badge bg-primary rounded-pill d-flex align-items-center" style="font-size:1rem;" title="Grand Total Hari Ini" onclick="event.stopPropagation()">
                        Total: <?php echo formatRupiah($grand_total_harian); ?>
                    </span>
                </button>
            </h2>
            <div id="collapse-<?php echo $day_id; ?>" class="accordion-collapse collapse <?php echo ($day_index == 0) ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $day_id; ?>" data-bs-parent="#accordionTanggal">
                <div class="accordion-body">
                    
                    <div class="accordion" id="accordionShift-<?php echo $day_id; ?>">
                        <?php
                        $shift_index = 0; // Untuk ID unik accordion
                        foreach ($shift_times as $nama_shift => $times):
                            $shift_id = $day_id . '-shift-' . $shift_index;
                            
                            // Dapatkan rentang datetime (Start & End) untuk shift ini
                            $range = getShiftDateTimeRange($tanggal_str, $nama_shift, $shift_times);
                            
                            // --- Ambil Data Ralan ---
                            $stmt_ralan->bind_param("ss", $range['start'], $range['end']);
                            $stmt_ralan->execute();
                            $result_ralan = $stmt_ralan->get_result();
                            $data_ralan = [];
                            while ($row = $result_ralan->fetch_assoc()) $data_ralan[] = $row;
                            
                            // --- Ambil Data Ranap ---
                            $stmt_ranap->bind_param("ss", $range['start'], $range['end']);
                            $stmt_ranap->execute();
                            $result_ranap = $stmt_ranap->get_result();
                            $data_ranap = [];
                            while ($row = $result_ranap->fetch_assoc()) $data_ranap[] = $row;

                            // --- Ambil Data Pemasukan ---
                            $stmt_pemasukan->bind_param("ss", $range['start'], $range['end']);
                            $stmt_pemasukan->execute();
                            $result_pemasukan = $stmt_pemasukan->get_result();
                            $data_pemasukan = [];
                            while ($row = $result_pemasukan->fetch_assoc()) $data_pemasukan[] = $row;

                            // --- Ambil Data Pengeluaran ---
                            $stmt_pengeluaran->bind_param("ss", $range['start'], $range['end']);
                            $stmt_pengeluaran->execute();
                            $result_pengeluaran = $stmt_pengeluaran->get_result();
                            $data_pengeluaran = [];
                            while ($row = $result_pengeluaran->fetch_assoc()) $data_pengeluaran[] = $row;

                            // --- Hitung Summary Total per Shift ---
                            $total_ralan     = array_sum(array_column($data_ralan, 'total_rupiah'));
                            $total_ranap     = array_sum(array_column($data_ranap, 'total_rupiah'));
                            $total_pemasukan = array_sum(array_column($data_pemasukan, 'besar'));
                            $total_pengeluaran = array_sum(array_column($data_pengeluaran, 'biaya'));
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-<?php echo $shift_id; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $shift_id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $shift_id; ?>">
                                    Shift <?php echo htmlspecialchars($nama_shift); ?> (<?php echo $range['start'] . ' s/d ' . $range['end']; ?>)
                                </button>
                            </h2>
                            <div class="d-flex flex-wrap gap-2 align-items-center px-3 py-2" style="background:#f0f4f8; border-bottom:1px solid #dee2e6; font-size:0.82rem;">
                                <span style="color:#555; font-weight:600;">Ringkasan Shift:</span>
                                <span style="display:inline-block; background:#0d6efd; color:#fff; border-radius:50px; padding:3px 10px; font-weight:600;">
                                    &#128203; <?php echo count($data_ralan); ?> Ralan &mdash; <?php echo formatRupiah($total_ralan); ?>
                                </span>
                                <span style="display:inline-block; background:#0dcaf0; color:#000; border-radius:50px; padding:3px 10px; font-weight:600;">
                                    &#127916; <?php echo count($data_ranap); ?> Ranap &mdash; <?php echo formatRupiah($total_ranap); ?>
                                </span>
                                <span style="display:inline-block; background:#198754; color:#fff; border-radius:50px; padding:3px 10px; font-weight:600;">
                                    &#43; Lain-lain: <?php echo formatRupiah($total_pemasukan); ?>
                                </span>
                                <span style="display:inline-block; background:#dc3545; color:#fff; border-radius:50px; padding:3px 10px; font-weight:600;">
                                    &minus; Keluar: <?php echo formatRupiah($total_pengeluaran); ?>
                                </span>
                            </div>
                            <div id="collapse-<?php echo $shift_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $shift_id; ?>" data-bs-parent="#accordionShift-<?php echo $day_id; ?>">
                                <div class="accordion-body">
                                    
                                    <ul class="nav nav-tabs" id="tab-<?php echo $shift_id; ?>" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="ralan-tab-<?php echo $shift_id; ?>" data-bs-toggle="tab" data-bs-target="#ralan-<?php echo $shift_id; ?>" type="button">
                                                Ralan (<?php echo count($data_ralan); ?>)
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="ranap-tab-<?php echo $shift_id; ?>" data-bs-toggle="tab" data-bs-target="#ranap-<?php echo $shift_id; ?>" type="button">
                                                Ranap (<?php echo count($data_ranap); ?>)
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="pemasukan-tab-<?php echo $shift_id; ?>" data-bs-toggle="tab" data-bs-target="#pemasukan-<?php echo $shift_id; ?>" type="button">
                                                Lain (<?php echo count($data_pemasukan); ?>)
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="pengeluaran-tab-<?php echo $shift_id; ?>" data-bs-toggle="tab" data-bs-target="#pengeluaran-<?php echo $shift_id; ?>" type="button">
                                                Keluar (<?php echo count($data_pengeluaran); ?>)
                                            </button>
                                        </li>
                                    </ul>
                                    
                                    <div class="tab-content" id="tab-content-<?php echo $shift_id; ?>">
                                        <div class="tab-pane fade show active" id="ralan-<?php echo $shift_id; ?>" role="tabpanel">
                                            <div class="card-body border border-top-0 p-3">
												<div class="table-responsive">
                                                <table id="tabel-ralan-<?php echo $shift_id; ?>" class="table table-striped table-bordered table-sm" style="width:100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Waktu Bayar</th>
                                                            <th>No. Rawat</th>
                                                            <th>No. Nota</th>
                                                            <th>Nama Pasien</th>
                                                            <th>Cara Bayar</th>
                                                            <th>Dokter</th>
                                                            <th class="text-end">Total (Rp)</th>
                                                            <th>Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($data_ralan as $data): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($data['tanggal'] . ' ' . $data['jam']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['no_rawat']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['no_nota']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['nm_pasien']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['png_jawab']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['nm_dokter']); ?></td>
                                                            <td class="text-end"><?php echo formatRupiah($data['total_rupiah']); ?></td>
                                                            <td>
                                                                <button type-="button" class="btn btn-success btn-sm btn-lihat-nota" data-bs-toggle="modal" data-bs-target="#modalDetailNota" data-norawat="<?php echo htmlspecialchars($data['no_rawat']); ?>" data-nonota="<?php echo htmlspecialchars($data['no_nota']); ?>">
                                                                    Nota
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
												</div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="ranap-<?php echo $shift_id; ?>" role="tabpanel">
                                            <div class="card-body border border-top-0 p-3">
											<div class="table-responsive">
                                                <table id="tabel-ranap-<?php echo $shift_id; ?>" class="table table-striped table-bordered table-sm" style="width:100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Waktu Bayar</th>
                                                            <th>No. Rawat</th>
                                                            <th>No. Nota</th>
                                                            <th>Nama Pasien</th>
                                                            <th>Cara Bayar</th>
                                                            <th>Dokter DPJP</th>
                                                            <th class="text-end">Total (Rp)</th>
                                                            <th>Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($data_ranap as $data): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($data['tanggal'] . ' ' . $data['jam']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['no_rawat']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['no_nota']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['nm_pasien']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['png_jawab']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['dokter_dpjp']); ?></td>
                                                            <td class="text-end"><?php echo formatRupiah($data['total_rupiah']); ?></td>
                                                            <td>
                                                                <button type="button" class="btn btn-success btn-sm btn-lihat-nota" data-bs-toggle="modal" data-bs-target="#modalDetailNota" data-norawat="<?php echo htmlspecialchars($data['no_rawat']); ?>" data-nonota="<?php echo htmlspecialchars($data['no_nota']); ?>">
                                                                    Nota
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
											</div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="pemasukan-<?php echo $shift_id; ?>" role="tabpanel">
                                            <div class="card-body border border-top-0 p-3">
											<div class="table-responsive">
                                                <table id="tabel-pemasukan-<?php echo $shift_id; ?>" class="table table-striped table-bordered table-sm" style="width:100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Tanggal</th>
                                                            <th>Kategori</th>
                                                            <th>Keterangan</th>
                                                            <th class="text-end">Besar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($data_pemasukan as $data): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($data['tanggal']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['nama_kategori']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['keterangan']); ?></td>
                                                            <td class="text-end"><?php echo formatRupiah($data['besar']); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
											</div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="pengeluaran-<?php echo $shift_id; ?>" role="tabpanel">
                                            <div class="card-body border border-top-0 p-3">
											<div class="table-responsive">
                                                <table id="tabel-pengeluaran-<?php echo $shift_id; ?>" class="table table-striped table-bordered table-sm" style="width:100%">
                                                    <thead>
                                                        <tr>
                                                            <th>Tanggal</th>
                                                            <th>Kategori</th>
                                                            <th>Keterangan</th>
                                                            <th class="text-end">Biaya</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($data_pengeluaran as $data): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($data['tanggal']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['nama_kategori']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['keterangan']); ?></td>
                                                            <td class="text-end"><?php echo formatRupiah($data['biaya']); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
											</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                        <?php
                            $shift_index++;
                        endforeach; // Akhir loop shift
                        ?>
                    </div> </div>
            </div>
        </div>
        <?php
                $day_index++;
            endforeach; // Akhir loop tanggal
        endif; // Akhir if date_range
        
        // Tutup semua statement yang disiapkan
        $stmt_ralan->close();
        $stmt_ranap->close();
        $stmt_pemasukan->close();
        $stmt_pengeluaran->close();
        $stmt_sum_ralan->close();
        $stmt_sum_ranap->close();
        $stmt_sum_pemasukan->close();
        $stmt_sum_pengeluaran->close();
        ?>
    </div> </div>

<div class="modal fade" id="modalDetailNota" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Detail Isi Nota: <span id="nomor-nota-modal">...</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="isi-nota-container">
                    <p class="text-center">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>


<?php
// Komentar: Kita 'inject' JavaScript ini ke footer.php
ob_start(); 
?>
<script>
    
    // Fungsi helper JS untuk format Rupiah
    function formatRupiah(angka) {
        if(angka == null || isNaN(angka)) return "Rp 0";
        var number_string = angka.toString().replace(/[^,\d]/g, ''),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            
        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return 'Rp ' + rupiah;
    }

    // Komentar: Jalankan skrip saat dokumen siap
    $(document).ready(function() {
        
        // Komentar: Inisialisasi DataTables
        // Kita menggunakan selector class '.table' agar semua tabel
        // yang ada di dalam accordion ini otomatis menjadi DataTables.
        // Ini mungkin berat jika datanya puluhan ribu, tapi kita coba dulu.
        $('table').DataTable({ 
            "responsive": true, 
            "order": [[ 0, "desc" ]],
            "pageLength": 10, // Batasi 10 baris per halaman
            "lengthChange": false // Sembunyikan opsi "Show X entries"
        });
        
        
        // Komentar: Event handler untuk tombol "Lihat Nota"
        $(document).on('click', '.btn-lihat-nota', function() {
            var noRawat = $(this).data('norawat');
            var noNota = $(this).data('nonota');
            
            $("#nomor-nota-modal").text(noNota + " (No. Rawat: " + noRawat + ")");
            $("#isi-nota-container").html("<p class='text-center'>Memuat data...</p>");

            $.ajax({
                url: "api/get_detail_nota.php", 
                type: "GET",
                data: { no_rawat: noRawat },
                dataType: "json",
                success: function(response) {
                    // Komentar: Logika Tampilan Nota V2 (dari memori)
                    var html = '<table class="table table-sm">';
                    html += '<thead style="border-bottom: 2px solid #333;"><tr>';
                    html += '<th scope="col" style="width: 5%;">Ket.</th>';
                    html += '<th scope="col" style="width: 45%;">Perawatan/Tindakan/Obat</th>';
                    html += '<th scope="col" style="width: 20%;">Status</th>';
                    html += '<th scope="col" class="text-end" style="width: 10%;">Biaya</th>';
                    html += '<th scope="col" class="text-center" style="width: 5%;">Jml</th>';
                    html += '<th scope="col" class="text-end" style="width: 15%;">Total</th>';
                    html += '</tr></thead><tbody>';
                    
                    var grandTotal = 0;
                    
                    if (Array.isArray(response) && response.length > 0) {
                        response.forEach(function(item) {
                            var no = item.no || '';
                            var nm_perawatan = item.nm_perawatan || 'N/A';
                            var status = item.status || 'N/A';
                            
                            // Clean up zero values untuk mereduksi visual clutter
                            var biayaText = parseFloat(item.biaya) > 0 ? formatRupiah(item.biaya) : '';
                            var jumlahText = parseFloat(item.jumlah) > 0 ? parseFloat(item.jumlah) : '';
                            var totalbiayaText = parseFloat(item.totalbiaya) !== 0 ? formatRupiah(item.totalbiaya) : '';
                            var statusText = (status === '-' || status === '') ? '' : status;

                            html += '<tr>';
                            html += '<td>' + (no || '') + '</td>';
                            html += '<td>' + (nm_perawatan) + '</td>';
                            html += '<td>' + (statusText) + '</td>';
                            html += '<td class="text-end">' + (biayaText) + '</td>';
                            html += '<td class="text-center">' + (jumlahText) + '</td>';
                            html += '<td class="text-end">' + (totalbiayaText) + '</td>';
                            html += '</tr>';
                            
                            var totalbiayaNum = parseFloat(item.totalbiaya) || 0;
                            if (status !== '' && status !== '-') {
                                grandTotal += totalbiayaNum;
                            }
                        });
                    } else {
                        html += '<tr><td colspan="6" class="text-center">Tidak ada data detail billing ditemukan.</td></tr>';
                    }
                    
                    html += '</tbody><tfoot style="border-top: 2px solid #333;">';
                    html += '<tr><th colspan="5" class="text-end h5">Grand Total:</th><th class="text-end h5">' + formatRupiah(grandTotal) + '</th></tr>';
                    html += '</tfoot></table>';
                    
                    $("#isi-nota-container").html(html);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $("#isi-nota-container").html("<p class='text-danger'>Gagal memuat data. Status: " + textStatus + ", Error: " + errorThrown + "</p>");
                }
            });
        });
        
    });
</script>
<?php
// Komentar: Simpan semua skrip JS di atas ke variabel $page_js
$page_js = ob_get_clean();
?>

<?php
// 8. Sertakan Footer
require_once('includes/footer.php');
?>