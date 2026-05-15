<?php
/*
 * File dashboard.php (PERBAIKAN V3 - Fix Logika Retur)
 * Halaman utama untuk owner. Menampilkan filter, KPI, dan Grafik.
 * - KPI sekarang menghitung komponen pengurang (Retur/Potongan)
 * PHP 7.3 compatible.
 */

// ... (Header & Setup awal sama) ...
$page_title = "Dashboard Keuangan";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

$total_pemasukan_tunai = 0;
$total_pengeluaran = 0;
$total_piutang_terbentuk = 0;
$net_cash_flow = 0;

$shift_times = getShiftTimes($koneksi);
$start_date = new DateTime($tgl_awal);
$end_date = new DateTime($tgl_akhir);
$end_date->modify('+1 day');
$interval = new DateInterval('P1D');
$date_range = new DatePeriod($start_date, $interval, $end_date);

if ($date_range) {
    foreach ($date_range as $tanggal) {
        $tanggal_str = $tanggal->format('Y-m-d');
        foreach ($shift_times as $nama_shift => $times) {
            $range = getShiftDateTimeRange($tanggal_str, $nama_shift, $shift_times);

            // PERBAIKAN UTAMA DI SINI: Menggunakan CASE WHEN untuk pengurangan
            // A. KUERI PEMASUKAN RALAN (TUNAI)
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
                $stmt_ralan->bind_param("ss", $range['start'], $range['end']);
                $stmt_ralan->execute();
                $result_ralan = $stmt_ralan->get_result();
                if ($result_ralan) {
                    $total_pemasukan_tunai += (float) $result_ralan->fetch_assoc()['Total'];
                }
                $stmt_ralan->close();
            }

            // B. KUERI PEMASUKAN RANAP (TUNAI)
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
                $stmt_ranap->bind_param("ss", $range['start'], $range['end']);
                $stmt_ranap->execute();
                $result_ranap = $stmt_ranap->get_result();
                if ($result_ranap) {
                    $total_pemasukan_tunai += (float) $result_ranap->fetch_assoc()['Total'];
                }
                $stmt_ranap->close();
            }

            // C. KUERI PEMASUKAN LAIN (Tetap)
            $sql_lain = "SELECT SUM(pemasukan_lain.besar) AS Total FROM pemasukan_lain WHERE pemasukan_lain.tanggal BETWEEN ? AND ?";
            $stmt_lain = $koneksi->prepare($sql_lain);
            if($stmt_lain) {
                $stmt_lain->bind_param("ss", $range['start'], $range['end']);
                $stmt_lain->execute();
                $result_lain = $stmt_lain->get_result();
                if ($result_lain) {
                    $total_pemasukan_tunai += (float) $result_lain->fetch_assoc()['Total'];
                }
                $stmt_lain->close();
            }
            
            // D. KUERI PENGELUARAN (Tetap)
            $sql_keluar = "SELECT SUM(pengeluaran_harian.biaya) AS Total FROM pengeluaran_harian WHERE pengeluaran_harian.tanggal BETWEEN ? AND ?";
            $stmt_keluar = $koneksi->prepare($sql_keluar);
            if($stmt_keluar) {
                $stmt_keluar->bind_param("ss", $range['start'], $range['end']);
                $stmt_keluar->execute();
                $result_keluar = $stmt_keluar->get_result();
                if ($result_keluar) {
                    $total_pengeluaran += (float) $result_keluar->fetch_assoc()['Total'];
                }
                $stmt_keluar->close();
            }
        }
    }
}

// E. KUERI PIUTANG TERBENTUK (Tetap, karena piutang biasanya sudah bersih/netto di tabel piutang_pasien)
$sql_piutang = "SELECT SUM(piutang_pasien.totalpiutang) AS Total FROM piutang_pasien WHERE piutang_pasien.tgl_piutang BETWEEN ? AND ?";
$stmt_piutang = $koneksi->prepare($sql_piutang);
if ($stmt_piutang) {
    $stmt_piutang->bind_param("ss", $tgl_awal, $tgl_akhir);
    $stmt_piutang->execute();
    $result_piutang = $stmt_piutang->get_result();
    if ($result_piutang) {
        $row_piutang = $result_piutang->fetch_assoc();
        $total_piutang_terbentuk = (float) $row_piutang['Total'];
    }
    $stmt_piutang->close();
} else {
    $total_piutang_terbentuk = 0;
    error_log("Gagal prepare kueri piutang: " . $koneksi->error);
}

$net_cash_flow = $total_pemasukan_tunai - $total_pengeluaran;
?>
<!-- HTML BAGIAN BAWAH SAMA PERSIS -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">Filter Data</h5>
        <form action="laporan_kas.php" method="GET" class="row g-3">
            <div class="col-md-5">
                <label for="tgl_awal" class="form-label">Dari Tanggal</label>
                <input type="date" class="form-control" name="tgl_awal" id="tgl_awal" value="<?php echo htmlspecialchars($tgl_awal); ?>">
            </div>
            <div class="col-md-5">
                <label for="tgl_akhir" class="form-label">Sampai Tanggal</label>
                <input type="date" class="form-control" name="tgl_akhir" id="tgl_akhir" value="<?php echo htmlspecialchars($tgl_akhir); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100">Tampilkan</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="bottom" 
                        data-bs-title="Total uang tunai yang diterima dari Ralan, Ranap, dan Pemasukan Lain (Omzet Tunai). Sudah dikurangi Retur/Potongan.">
                        Pemasukan Tunai
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo formatRupiah($total_pemasukan_tunai); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- (Sisa KPI dan Grafik sama seperti file sebelumnya) -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Total uang tunai yang dikeluarkan untuk biaya operasional harian.">Pengeluaran Tunai</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($total_pengeluaran); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="Kas Bersih. Pemasukan Tunai - Pengeluaran Tunai.">Nett Cash Flow (Kas Bersih)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($net_cash_flow); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="laporan_piutang_detail.php?tgl_awal=<?php echo htmlspecialchars($tgl_awal); ?>&tgl_akhir=<?php echo htmlspecialchars($tgl_akhir); ?>" style="text-decoration: none;">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-title="KLIK UNTUK MELIHAT DETAIL. Total tagihan yang belum dibayar.">Piutang Terbentuk (Klik Detail)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatRupiah($total_piutang_terbentuk); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Grafik Tren Pemasukan vs Pengeluaran Harian</h6></div>
            <div class="card-body"><canvas id="chartTrenHarian"></canvas></div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Total Pemasukan Tunai per Shift</h6></div>
            <div class="card-body">
                <canvas id="chartPerShift"></canvas>
                <hr class="mt-4">
                <h6 class="text-center">Lihat Detail Laporan per Shift</h6>
                <div class="d-grid gap-2 mt-4">
                    <a class="btn btn-info" href="laporan_detail.php?tgl_awal=<?php echo htmlspecialchars($tgl_awal); ?>&tgl_akhir=<?php echo htmlspecialchars($tgl_akhir); ?>">Lihat Laporan Detail (Per Tanggal & Per Shift)</a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6"><div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Komposisi Pengeluaran</h6></div><div class="card-body"><canvas id="chartPengeluaran"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card shadow mb-4"><div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Komposisi Pemasukan Tunai</h6></div><div class="card-body"><canvas id="chartKomposisiPemasukan"></canvas></div></div></div>
</div>

<?php ob_start(); ?>
<script>
    var tglAwal = '<?php echo $tgl_awal; ?>';
    var tglAkhir = '<?php echo $tgl_akhir; ?>';
    var myChartTrenHarian, myChartPerShift, myChartPengeluaran, myChartKomposisiPemasukan;
    var ctxTren = document.getElementById('chartTrenHarian').getContext('2d');
    var ctxShift = document.getElementById('chartPerShift').getContext('2d');
    var ctxKeluar = document.getElementById('chartPengeluaran').getContext('2d');
    var ctxMasuk = document.getElementById('chartKomposisiPemasukan').getContext('2d');

    function formatRupiah(angka) {
        if(angka == null || isNaN(angka)) return "Rp 0";
        var number_string = angka.toString().replace(/[^,\d\-]/g, ''),
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

    function loadAllCharts() {
        var apiUrl = 'api/data_grafik.php?tgl_awal=' + tglAwal + '&tgl_akhir=' + tglAkhir;
        fetch(apiUrl)
            .then(function(response) {
                if (!response.ok) throw new Error('API request failed');
                return response.json();
            })
            .then(function(data) {
                renderChartTren(data.tren_harian);
                renderChartPerShift(data.per_shift);
                renderChartPengeluaran(data.komposisi_pengeluaran);
                renderChartKomposisiPemasukan(data.komposisi_pemasukan);
            })
            .catch(function(error) { console.error('Error:', error); });
    }
    // ... (Fungsi renderChart sama seperti sebelumnya, copy paste dari file lama) ...
    // UNTUK KEPRAKTISAN, SAYA HARAP ANDA SUDAH PUNYA FUNGSI RENDER CHART DI FILE LAMA
    // JIKA TIDAK, SAYA BISA TULISKAN ULANG SECARA LENGKAP
    
    function renderChartTren(data) {
        if (myChartTrenHarian) myChartTrenHarian.destroy(); 
        myChartTrenHarian = new Chart(ctxTren, {
            type: 'line',
            data: {
                labels: data.labels, 
                datasets: [{label: 'Pemasukan Tunai', data: data.pemasukan, borderColor: 'rgb(25, 135, 84)', backgroundColor: 'rgba(25, 135, 84, 0.1)', fill: true, tension: 0.1}, {label: 'Pengeluaran Tunai', data: data.pengeluaran, borderColor: 'rgb(220, 53, 69)', backgroundColor: 'rgba(220, 53, 69, 0.1)', fill: true, tension: 0.1}]
            },
            options: { responsive: true, plugins: { tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + formatRupiah(context.parsed.y); } } } }, scales: { y: { ticks: { callback: function(value) { return formatRupiah(value); } } } } }
        });
    }
    function renderChartPerShift(data) {
        if (myChartPerShift) myChartPerShift.destroy();
        myChartPerShift = new Chart(ctxShift, {
            type: 'bar',
            data: {
                labels: data.labels, 
                datasets: [{label: 'Total Pemasukan Tunai', data: data.data, backgroundColor: ['rgba(255, 99, 132, 0.2)', 'rgba(54, 162, 235, 0.2)', 'rgba(255, 206, 86, 0.2)', 'rgba(75, 192, 192, 0.2)'], borderColor: ['rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)', 'rgba(255, 206, 86, 1)', 'rgba(75, 192, 192, 1)'], borderWidth: 1}]
            },
            options: { responsive: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { return 'Total: ' + formatRupiah(context.parsed.y); } } } }, scales: { y: { ticks: { callback: function(value) { return formatRupiah(value); } } } } }
        });
    }
    function renderChartPengeluaran(data) {
        if (myChartPengeluaran) myChartPengeluaran.destroy();
        myChartPengeluaran = new Chart(ctxKeluar, {
            type: 'doughnut',
            data: { labels: data.labels, datasets: [{data: data.data, backgroundColor: ['#e74c3c', '#f1c40f', '#9b59b6', '#3498db', '#2ecc71', '#e67e22']}] },
            options: { responsive: true, plugins: { tooltip: { callbacks: { label: function(context) { return context.label + ': ' + formatRupiah(context.parsed); } } } } }
        });
    }
    function renderChartKomposisiPemasukan(data) {
        if (myChartKomposisiPemasukan) myChartKomposisiPemasukan.destroy();
        myChartKomposisiPemasukan = new Chart(ctxMasuk, {
            type: 'pie',
            data: { labels: data.labels, datasets: [{data: data.data, backgroundColor: ['#2ecc71', '#3498db', '#e67e22']}] },
            options: { responsive: true, plugins: { tooltip: { callbacks: { label: function(context) { return context.label + ': ' + formatRupiah(context.parsed); } } } } }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) })
        loadAllCharts();
    });
</script>
<?php $page_js = ob_get_clean(); ?>
<?php require_once('includes/footer.php'); ?>