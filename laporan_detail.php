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
    SELECT 
        pemasukan_lain.no_masuk,
        pemasukan_lain.tanggal,
        pemasukan_lain.keterangan,
        pemasukan_lain.keperluan,
        pemasukan_lain.besar,
        pemasukan_lain.nip,
        COALESCE(petugas.nama, '-') AS nama_petugas,
        pemasukan_lain.kode_kategori,
        COALESCE(kategori_pemasukan_lain.nama_kategori, '-') AS nama_kategori
    FROM pemasukan_lain 
    LEFT JOIN petugas ON pemasukan_lain.nip = petugas.nip 
    LEFT JOIN kategori_pemasukan_lain ON pemasukan_lain.kode_kategori = kategori_pemasukan_lain.kode_kategori 
    WHERE pemasukan_lain.tanggal BETWEEN ? AND ? 
    ORDER BY pemasukan_lain.tanggal
";
$stmt_pemasukan = $koneksi->prepare($sql_pemasukan);

$sql_obat = "
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
        (COALESCE(SUM(d.total), 0) + COALESCE(p.ppn, 0) + COALESCE(p.ongkir, 0)) AS total_jual,
        COALESCE(ts.tgl_bayar, CONCAT(p.tgl_jual, ' 08:00:00')) AS waktu_bayar
    FROM penjualan p
    LEFT JOIN detailjual d ON p.nota_jual = d.nota_jual
    LEFT JOIN petugas pt ON p.nip = pt.nip
    LEFT JOIN bangsal b ON p.kd_bangsal = b.kd_bangsal
    LEFT JOIN tagihan_sadewa ts ON p.nota_jual = ts.no_nota
    WHERE p.status = 'Sudah Dibayar'
        AND COALESCE(ts.tgl_bayar, CONCAT(p.tgl_jual, ' 08:00:00')) BETWEEN ? AND ?
    GROUP BY p.nota_jual, p.tgl_jual, p.nip, pt.nama, p.no_rkm_medis, p.nm_pasien, p.keterangan, p.jns_jual, p.ongkir, p.ppn, p.nama_bayar, b.nm_bangsal, waktu_bayar
    ORDER BY waktu_bayar ASC, p.nota_jual ASC
";
$stmt_obat = $koneksi->prepare($sql_obat);

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
if (!$stmt_ralan || !$stmt_ranap || !$stmt_obat || !$stmt_pemasukan || !$stmt_pengeluaran) {
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

$sql_sum_pemasukan = "SELECT SUM(besar) AS total_harian FROM pemasukan_lain WHERE DATE(tanggal) = ?";
$stmt_sum_pemasukan = $koneksi->prepare($sql_sum_pemasukan);

$sql_sum_pengeluaran = "SELECT SUM(biaya) AS total_harian FROM pengeluaran_harian WHERE DATE(tanggal) = ?";
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
                
                // Ambil data setiap shift untuk tanggal ini agar Total Header sinkron 100% dengan isi shift
                $shifts_data = [];
                $grand_total_harian = 0;
                $total_masuk_harian = 0;
                $total_keluar_harian = 0;
                $shift_index = 0;
                foreach ($shift_times as $nama_shift => $times) {
                    $range = getShiftDateTimeRange($tanggal_str, $nama_shift, $shift_times);
                    $shift_ranges = getShiftRanges($tanggal_str, $nama_shift, $shift_times);
                    
                    $data_ralan = [];
                    $data_ranap = [];
                    $data_obat = [];
                    $data_pemasukan = [];
                    $data_pengeluaran = [];

                    foreach ($shift_ranges as $sr) {
                        // --- Ambil Data Ralan ---
                        $stmt_ralan->bind_param("ss", $sr['start'], $sr['end']);
                        $stmt_ralan->execute();
                        $result_ralan = $stmt_ralan->get_result();
                        while ($row = $result_ralan->fetch_assoc()) $data_ralan[] = $row;
                        
                        // --- Ambil Data Ranap ---
                        $stmt_ranap->bind_param("ss", $sr['start'], $sr['end']);
                        $stmt_ranap->execute();
                        $result_ranap = $stmt_ranap->get_result();
                        while ($row = $result_ranap->fetch_assoc()) $data_ranap[] = $row;

                        // --- Ambil Data Penjualan Obat Bebas & BHP ---
                        $stmt_obat->bind_param("ss", $sr['start'], $sr['end']);
                        $stmt_obat->execute();
                        $result_obat = $stmt_obat->get_result();
                        while ($row = $result_obat->fetch_assoc()) $data_obat[] = $row;

                        // --- Ambil Data Pemasukan ---
                        $stmt_pemasukan->bind_param("ss", $sr['start'], $sr['end']);
                        $stmt_pemasukan->execute();
                        $result_pemasukan = $stmt_pemasukan->get_result();
                        while ($row = $result_pemasukan->fetch_assoc()) $data_pemasukan[] = $row;

                        // --- Ambil Data Pengeluaran ---
                        $stmt_pengeluaran->bind_param("ss", $sr['start'], $sr['end']);
                        $stmt_pengeluaran->execute();
                        $result_pengeluaran = $stmt_pengeluaran->get_result();
                        while ($row = $result_pengeluaran->fetch_assoc()) $data_pengeluaran[] = $row;
                    }

                    // --- Hitung Summary Total per Shift ---
                    $total_ralan       = array_sum(array_column($data_ralan, 'total_rupiah'));
                    $total_ranap       = array_sum(array_column($data_ranap, 'total_rupiah'));
                    $total_obat        = array_sum(array_column($data_obat, 'total_jual'));
                    $total_pemasukan   = array_sum(array_column($data_pemasukan, 'besar'));
                    $total_pengeluaran = array_sum(array_column($data_pengeluaran, 'biaya'));
                    
                    $shift_masuk       = $total_ralan + $total_ranap + $total_obat + $total_pemasukan;
                    $shift_keluar      = $total_pengeluaran;
                    $shift_bersih      = $shift_masuk - $shift_keluar;

                    $total_masuk_harian  += $shift_masuk;
                    $total_keluar_harian += $shift_keluar;
                    $grand_total_harian  += $shift_bersih;

                    $shifts_data[] = [
                        'nama_shift' => $nama_shift,
                        'shift_id' => $day_id . '-shift-' . $shift_index,
                        'range' => $range,
                        'data_ralan' => $data_ralan,
                        'data_ranap' => $data_ranap,
                        'data_obat' => $data_obat,
                        'data_pemasukan' => $data_pemasukan,
                        'data_pengeluaran' => $data_pengeluaran,
                        'total_ralan' => $total_ralan,
                        'total_ranap' => $total_ranap,
                        'total_obat' => $total_obat,
                        'total_pemasukan' => $total_pemasukan,
                        'total_pengeluaran' => $total_pengeluaran,
                        'shift_masuk' => $shift_masuk,
                        'shift_keluar' => $shift_keluar,
                        'shift_bersih' => $shift_bersih
                    ];
                    $shift_index++;
                }
                
                
        ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading-<?php echo $day_id; ?>">
                <button class="accordion-button fs-5 fw-bold d-flex align-items-center gap-2 flex-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $day_id; ?>" aria-expanded="true" aria-controls="collapse-<?php echo $day_id; ?>">
                    <span class="me-auto text-truncate" style="min-width: 220px;">Laporan Tanggal: <?php echo $tanggal->format('d-m-Y'); ?></span>
                    <div class="d-flex align-items-center gap-2 flex-wrap me-2" onclick="event.stopPropagation()">
                        <span class="badge bg-success rounded-pill px-3 py-2 d-inline-flex align-items-center shadow-sm" style="font-size:0.85rem;" title="Total Pemasukan Tunai (Ralan + Ranap + Lain-lain)">
                            <i class="fas fa-arrow-circle-down me-1"></i> Masuk: <?php echo formatRupiah($total_masuk_harian); ?>
                        </span>
                        <span class="badge bg-danger rounded-pill px-3 py-2 d-inline-flex align-items-center shadow-sm" style="font-size:0.85rem;" title="Total Pengeluaran Harian">
                            <i class="fas fa-arrow-circle-up me-1"></i> Keluar: <?php echo formatRupiah($total_keluar_harian); ?>
                        </span>
                        <span class="badge bg-primary rounded-pill px-3 py-2 d-inline-flex align-items-center fw-bold shadow-sm" style="font-size:0.92rem;" title="Sisa Kas Bersih (Masuk dikurangi Keluar)">
                            <i class="fas fa-wallet me-1"></i> Sisa Kas: <?php echo formatRupiah($grand_total_harian); ?>
                        </span>
                    </div>
                </button>
            </h2>
            <div id="collapse-<?php echo $day_id; ?>" class="accordion-collapse collapse <?php echo ($day_index == 0) ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo $day_id; ?>" data-bs-parent="#accordionTanggal">
                <div class="accordion-body">
                    
                    <div class="accordion" id="accordionShift-<?php echo $day_id; ?>">
                        <?php
                        foreach ($shifts_data as $s):
                            $shift_id          = $s['shift_id'];
                            $nama_shift        = $s['nama_shift'];
                            $range             = $s['range'];
                            $data_ralan        = $s['data_ralan'];
                            $data_ranap        = $s['data_ranap'];
                            $data_obat         = $s['data_obat'];
                            $data_pemasukan    = $s['data_pemasukan'];
                            $data_pengeluaran  = $s['data_pengeluaran'];
                            $total_ralan       = $s['total_ralan'];
                            $total_ranap       = $s['total_ranap'];
                            $total_obat        = $s['total_obat'];
                            $total_pemasukan   = $s['total_pemasukan'];
                            $total_pengeluaran = $s['total_pengeluaran'];
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-<?php echo $shift_id; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $shift_id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $shift_id; ?>">
                                    Shift <?php echo htmlspecialchars($nama_shift); ?> (<?php echo isset($range['display']) ? htmlspecialchars($range['display']) : ($range['start'] . ' s/d ' . $range['end']); ?>)
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
                                <span style="display:inline-block; background:#ffc107; color:#212529; border-radius:50px; padding:3px 10px; font-weight:600;">
                                    &#128138; <?php echo count($data_obat); ?> Obat/BHP &mdash; <?php echo formatRupiah($total_obat); ?>
                                </span>
                                <span style="display:inline-block; background:#198754; color:#fff; border-radius:50px; padding:3px 10px; font-weight:600;">
                                    &#43; Lain-lain: <?php echo formatRupiah($total_pemasukan); ?>
                                </span>
                                <span style="display:inline-block; background:#dc3545; color:#fff; border-radius:50px; padding:3px 10px; font-weight:600;">
                                    &minus; Keluar: <?php echo formatRupiah($total_pengeluaran); ?>
                                </span>
                                <?php 
                                    $sisa_shift = isset($s['shift_bersih']) ? $s['shift_bersih'] : (($total_ralan + $total_ranap + $total_obat + $total_pemasukan) - $total_pengeluaran);
                                    $sisa_color = ($sisa_shift >= 0) ? '#20c997' : '#ff6b6b';
                                ?>
                                <span class="ms-auto" style="display:inline-block; background:#212529; color:#fff; border-radius:50px; padding:3px 12px; font-weight:700;">
                                    &#61; Setoran Shift: <span style="color:<?php echo $sisa_color; ?>;"><?php echo formatRupiah($sisa_shift); ?></span>
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
                                            <button class="nav-link" id="obat-tab-<?php echo $shift_id; ?>" data-bs-toggle="tab" data-bs-target="#obat-<?php echo $shift_id; ?>" type="button">
                                                Obat &amp; BHP (<?php echo count($data_obat); ?>)
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
                                        <div class="tab-pane fade" id="obat-<?php echo $shift_id; ?>" role="tabpanel">
                                            <div class="card-body border border-top-0 p-3">
                                                <div class="table-responsive">
                                                    <table id="tabel-obat-<?php echo $shift_id; ?>" class="table table-striped table-bordered table-sm align-middle" style="width:100%">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="min-width: 95px;">No. Nota</th>
                                                                <th style="min-width: 120px;">Waktu Bayar</th>
                                                                <th style="min-width: 150px;">Petugas</th>
                                                                <th style="min-width: 170px;">Pasien / Pembeli</th>
                                                                <th style="min-width: 80px;">Jns. Jual</th>
                                                                <th style="min-width: 120px;">Keterangan</th>
                                                                <th style="min-width: 100px;">Asal Stok</th>
                                                                <th style="min-width: 100px;">Akun Bayar</th>
                                                                <th class="text-end" style="min-width: 75px;">PPN</th>
                                                                <th class="text-end" style="min-width: 75px;">Ongkir</th>
                                                                <th class="text-end" style="min-width: 105px;">Total (Rp)</th>
                                                                <th class="text-center" style="min-width: 70px;">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($data_obat as $data): ?>
                                                            <tr>
                                                                <td class="fw-semibold text-primary"><?php echo htmlspecialchars($data['nota_jual']); ?></td>
                                                                <td><?php echo htmlspecialchars($data['waktu_bayar']); ?></td>
                                                                <td><?php echo htmlspecialchars($data['nama_petugas']); ?></td>
                                                                <td>
                                                                    <?php 
                                                                        $pasien_label = htmlspecialchars($data['nm_pasien']);
                                                                        if (!empty($data['no_rkm_medis']) && $data['no_rkm_medis'] !== '-') {
                                                                            $pasien_label = '<span class="text-muted small">' . htmlspecialchars($data['no_rkm_medis']) . '</span> ' . $pasien_label;
                                                                        }
                                                                        echo $pasien_label;
                                                                    ?>
                                                                </td>
                                                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($data['jns_jual']); ?></span></td>
                                                                <td><?php echo htmlspecialchars($data['keterangan']); ?></td>
                                                                <td><?php echo htmlspecialchars($data['nm_bangsal']); ?></td>
                                                                <td><span class="badge bg-success-subtle text-success border border-success"><?php echo htmlspecialchars($data['nama_bayar']); ?></span></td>
                                                                <td class="text-end"><?php echo formatRupiah($data['ppn']); ?></td>
                                                                <td class="text-end"><?php echo formatRupiah($data['ongkir']); ?></td>
                                                                <td class="text-end fw-bold text-dark"><?php echo formatRupiah($data['total_jual']); ?></td>
                                                                <td class="text-center">
                                                                    <button type="button" class="btn btn-warning btn-sm btn-lihat-penjualan py-0 px-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalDetailPenjualan" data-notajual="<?php echo htmlspecialchars($data['nota_jual']); ?>" title="Lihat Rincian Item Obat/BHP">
                                                                        <i class="fas fa-pills me-1"></i>Nota
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                        <tfoot>
                                                            <tr class="table-light fw-bold">
                                                                <td colspan="10" class="text-end">&gt;&gt; Total Penjualan Obat &amp; BHP :</td>
                                                                <td class="text-end text-primary fw-bold"><?php echo formatRupiah($total_obat); ?></td>
                                                                <td></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="tab-pane fade" id="pemasukan-<?php echo $shift_id; ?>" role="tabpanel">
                                            <div class="card-body border border-top-0 p-3">
											<div class="table-responsive">
                                                <table id="tabel-pemasukan-<?php echo $shift_id; ?>" class="table table-striped table-bordered table-sm align-middle" style="width:100%">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="min-width: 90px;">Nomor</th>
                                                            <th style="min-width: 125px;">Tanggal</th>
                                                            <th style="min-width: 180px;">Kategori</th>
                                                            <th style="min-width: 180px;">Petugas</th>
                                                            <th class="text-end" style="min-width: 110px;">Pemasukan</th>
                                                            <th style="min-width: 140px;">Terima Dari</th>
                                                            <th style="min-width: 150px;">Keperluan</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($data_pemasukan as $data): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($data['no_masuk']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['tanggal']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['kode_kategori'] . ' ' . $data['nama_kategori']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['nama_petugas']); ?></td>
                                                            <td class="text-end fw-semibold"><?php echo formatRupiah($data['besar']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['keterangan']); ?></td>
                                                            <td><?php echo htmlspecialchars($data['keperluan']); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="table-light fw-bold">
                                                            <td colspan="4" class="text-end">&gt;&gt; Total Pemasukan :</td>
                                                            <td class="text-end text-success fw-bold"><?php echo formatRupiah($total_pemasukan); ?></td>
                                                            <td colspan="2"></td>
                                                        </tr>
                                                    </tfoot>
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
        $stmt_obat->close();
        $stmt_pemasukan->close();
        $stmt_pengeluaran->close();
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

<!-- Modal Detail Penjualan Obat Bebas & BHP -->
<div class="modal fade" id="modalDetailPenjualan" tabindex="-1" aria-labelledby="modalPenjualanLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-2">
                <h5 class="modal-title fs-6 fw-bold" id="modalPenjualanLabel">
                    <i class="fas fa-pills me-2"></i>Rincian Penjualan Obat, Alkes &amp; BHP: <span id="nomor-penjualan-modal" class="badge bg-dark text-white ms-1">...</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="isi-penjualan-container">
                    <p class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
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
            "order": [], // Pertahankan urutan awal (sesuai query SQL / kronologis)
            "pageLength": 10, // Batasi 10 baris per halaman
            "lengthChange": false // Sembunyikan opsi "Show X entries"
        });

        // Sesuaikan lebar kolom saat pindah tab
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            $($.fn.dataTable.tables(true)).DataTable().columns.adjust().responsive.recalc();
        });
        
        // Komentar: Event handler untuk tombol "Lihat Nota" (Billing Rawat Jalan & Inap)
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

        // Event handler untuk tombol "Lihat Nota" Penjualan Obat Bebas, Alkes & BHP
        $(document).on('click', '.btn-lihat-penjualan', function() {
            var notaJual = $(this).data('notajual');
            $("#nomor-penjualan-modal").text(notaJual);
            $("#isi-penjualan-container").html("<p class='text-center py-4'><i class='fas fa-spinner fa-spin me-2'></i>Memuat rincian penjualan...</p>");

            $.ajax({
                url: "api/get_detail_penjualan.php",
                type: "GET",
                data: { nota_jual: notaJual },
                dataType: "json",
                success: function(res) {
                    if (res.error) {
                        $("#isi-penjualan-container").html("<p class='text-danger text-center'>" + res.error + "</p>");
                        return;
                    }
                    var h = res.header || {};
                    var html = '';

                    // Header Info Card
                    html += '<div class="card bg-light border-0 mb-3 shadow-none">';
                    html += '<div class="card-body p-2" style="font-size:0.85rem;">';
                    html += '<div class="row g-2">';
                    html += '<div class="col-md-6">';
                    html += '<strong>No. Nota:</strong> ' + (h.nota_jual || '-') + '<br>';
                    html += '<strong>Waktu Bayar:</strong> ' + (h.waktu_bayar || h.tgl_jual || '-') + '<br>';
                    html += '<strong>Petugas:</strong> ' + (h.nama_petugas || '-');
                    html += '</div>';
                    html += '<div class="col-md-6">';
                    html += '<strong>Pasien / Pembeli:</strong> ' + (h.nm_pasien || '-') + (h.no_rkm_medis && h.no_rkm_medis !== '-' ? ' (' + h.no_rkm_medis + ')' : '') + '<br>';
                    html += '<strong>Asal Stok:</strong> ' + (h.nm_bangsal || '-') + '<br>';
                    html += '<strong>Akun Bayar:</strong> <span class="badge bg-success">' + (h.nama_bayar || '-') + '</span>';
                    html += '</div>';
                    html += '</div>';
                    if (h.keterangan && h.keterangan !== '-') {
                        html += '<div class="mt-1 pt-1 border-top text-muted"><strong>Keterangan:</strong> ' + h.keterangan + '</div>';
                    }
                    html += '</div></div>';

                    // Tabel Rincian Obat/BHP
                    html += '<div class="table-responsive"><table class="table table-bordered table-sm align-middle" style="font-size:0.83rem;">';
                    html += '<thead class="table-light"><tr>';
                    html += '<th class="text-center" style="width:35px;">No</th>';
                    html += '<th>Barang (Kode & Nama)</th>';
                    html += '<th class="text-center">Jml</th>';
                    html += '<th class="text-center">Satuan</th>';
                    html += '<th class="text-end">Harga</th>';
                    html += '<th class="text-end">Subtotal</th>';
                    html += '<th class="text-end">Diskon</th>';
                    html += '<th class="text-end">Tamb+Emb+Tus</th>';
                    html += '<th class="text-end">Total</th>';
                    html += '<th>No. Batch</th>';
                    html += '<th>Aturan Pakai</th>';
                    html += '</tr></thead><tbody>';

                    var subtotalItem = 0;
                    if (Array.isArray(res.items) && res.items.length > 0) {
                        res.items.forEach(function(item, idx) {
                            var tot = parseFloat(item.total) || 0;
                            subtotalItem += tot;
                            var embTuslah = (parseFloat(item.embalase) || 0) + (parseFloat(item.tuslah) || 0) + (parseFloat(item.tambahan) || 0);
                            html += '<tr>';
                            html += '<td class="text-center">' + (idx + 1) + '</td>';
                            html += '<td><span class="fw-semibold text-primary">' + (item.kode_brng || '') + '</span> - ' + (item.nama_brng || '') + '</td>';
                            html += '<td class="text-center fw-bold">' + (item.jumlah || 0) + '</td>';
                            html += '<td class="text-center">' + (item.satuan || '') + '</td>';
                            html += '<td class="text-end">' + formatRupiah(item.h_jual) + '</td>';
                            html += '<td class="text-end">' + formatRupiah(item.subtotal) + '</td>';
                            html += '<td class="text-end text-danger">' + (parseFloat(item.bsr_dis) > 0 ? formatRupiah(item.bsr_dis) : '-') + '</td>';
                            html += '<td class="text-end">' + (embTuslah > 0 ? formatRupiah(embTuslah) : '-') + '</td>';
                            html += '<td class="text-end fw-bold text-dark">' + formatRupiah(item.total) + '</td>';
                            html += '<td>' + (item.no_batch || '-') + '</td>';
                            html += '<td>' + (item.aturan_pakai || '-') + '</td>';
                            html += '</tr>';
                        });
                    } else {
                        html += '<tr><td colspan="11" class="text-center text-muted">Tidak ada rincian item barang.</td></tr>';
                    }
                    html += '</tbody>';

                    // Footer Calculations
                    var ppn = parseFloat(h.ppn) || 0;
                    var ongkir = parseFloat(h.ongkir) || 0;
                    var grandTotal = subtotalItem + ppn + ongkir;

                    html += '<tfoot class="table-light">';
                    html += '<tr><th colspan="8" class="text-end">Subtotal Obat / BHP :</th><th class="text-end fw-bold">' + formatRupiah(subtotalItem) + '</th><th colspan="2"></th></tr>';
                    if (ppn > 0) {
                        html += '<tr><th colspan="8" class="text-end">PPN :</th><th class="text-end text-warning fw-bold">' + formatRupiah(ppn) + '</th><th colspan="2"></th></tr>';
                    }
                    if (ongkir > 0) {
                        html += '<tr><th colspan="8" class="text-end">Ongkos Kirim :</th><th class="text-end text-info fw-bold">' + formatRupiah(ongkir) + '</th><th colspan="2"></th></tr>';
                    }
                    html += '<tr class="table-warning"><th colspan="8" class="text-end fs-6">TOTAL PENJUALAN :</th><th class="text-end fs-6 fw-bold text-dark">' + formatRupiah(grandTotal) + '</th><th colspan="2"></th></tr>';
                    html += '</tfoot></table></div>';

                    // Jika ada racikan
                    if (Array.isArray(res.racikan) && res.racikan.length > 0) {
                        html += '<div class="mt-3"><h6 class="fw-bold fs-7 mb-2 text-secondary"><i class="fas fa-mortar-pestle me-1"></i>Daftar Racikan:</h6>';
                        html += '<ul class="list-group list-group-flush border rounded" style="font-size:0.83rem;">';
                        res.racikan.forEach(function(rc) {
                            html += '<li class="list-group-item d-flex justify-content-between align-items-center py-2">';
                            html += '<div><strong>No. ' + rc.no_racik + ': ' + rc.nama_racik + '</strong> (' + rc.jml_dr + ' ' + rc.metode + ')<br><small class="text-muted">Aturan: ' + (rc.aturan_pakai || '-') + ' | Ket: ' + (rc.keterangan || '-') + '</small></div>';
                            html += '</li>';
                        });
                        html += '</ul></div>';
                    }

                    $("#isi-penjualan-container").html(html);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $("#isi-penjualan-container").html("<p class='text-danger text-center'>Gagal memuat data penjualan: " + errorThrown + "</p>");
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