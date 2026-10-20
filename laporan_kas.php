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
$total_layanan_jasa = 0;
$total_obat_bhp = 0;
$total_pengeluaran = 0;
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
            $shift_ranges = getShiftRanges($tanggal_str, $nama_shift, $shift_times);

            $shift_ralan = 0;
            $shift_ranap = 0;
            $shift_obat = 0;
            $shift_lain = 0;

            foreach ($shift_ranges as $sr) {
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
                    $stmt_ralan->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_ralan->execute();
                    $result_ralan = $stmt_ralan->get_result();
                    if ($result_ralan && $r = $result_ralan->fetch_assoc()) {
                        $shift_ralan += (float) $r['Total'];
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
                    $stmt_ranap->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_ranap->execute();
                    $result_ranap = $stmt_ranap->get_result();
                    if ($result_ranap && $r = $result_ranap->fetch_assoc()) {
                        $shift_ranap += (float) $r['Total'];
                    }
                    $stmt_ranap->close();
                }

                // C. KUERI PENJUALAN OBAT & BHP (LUNAS - SESUAI KHANZA DlgCariPenjualan)
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
                        $shift_obat += (float) $r['Total'];
                    }
                    $stmt_obat->close();
                }

                // D. KUERI PEMASUKAN LAIN (Tetap)
                $sql_lain = "SELECT SUM(pemasukan_lain.besar) AS Total FROM pemasukan_lain WHERE pemasukan_lain.tanggal BETWEEN ? AND ?";
                $stmt_lain = $koneksi->prepare($sql_lain);
                if($stmt_lain) {
                    $stmt_lain->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_lain->execute();
                    $result_lain = $stmt_lain->get_result();
                    if ($result_lain && $r = $result_lain->fetch_assoc()) {
                        $shift_lain += (float) $r['Total'];
                    }
                    $stmt_lain->close();
                }
                
                // E. KUERI PENGELUARAN (Tetap)
                $sql_keluar = "SELECT SUM(pengeluaran_harian.biaya) AS Total FROM pengeluaran_harian WHERE pengeluaran_harian.tanggal BETWEEN ? AND ?";
                $stmt_keluar = $koneksi->prepare($sql_keluar);
                if($stmt_keluar) {
                    $stmt_keluar->bind_param("ss", $sr['start'], $sr['end']);
                    $stmt_keluar->execute();
                    $result_keluar = $stmt_keluar->get_result();
                    if ($result_keluar && $r = $result_keluar->fetch_assoc()) {
                        $total_pengeluaran += (float) $r['Total'];
                    }
                    $stmt_keluar->close();
                }
            }

            // Akumulasi
            $total_layanan_jasa += ($shift_ralan + $shift_ranap + $shift_lain);
            $total_obat_bhp += $shift_obat;
            $total_pemasukan_tunai += ($shift_ralan + $shift_ranap + $shift_lain + $shift_obat);
        }
    }
}

$net_cash_flow = $total_pemasukan_tunai - $total_pengeluaran;

$pct_layanan = $total_pemasukan_tunai > 0 ? round(($total_layanan_jasa / $total_pemasukan_tunai) * 100, 1) : 0;
$pct_obat = $total_pemasukan_tunai > 0 ? round(($total_obat_bhp / $total_pemasukan_tunai) * 100, 1) : 0;
$pct_pengeluaran = $total_pemasukan_tunai > 0 ? min(100, round(($total_pengeluaran / $total_pemasukan_tunai) * 100, 1)) : 0;
$pct_cashflow = $total_pemasukan_tunai > 0 ? max(0, round(($net_cash_flow / $total_pemasukan_tunai) * 100, 1)) : 0;
?>

<style>
    /* Styling Kartu Metrik Adaptive (Theme Light & Dark) */
    .metric-card-title {
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .metric-card-sub {
        color: #64748b;
        font-size: 0.8rem;
    }
    .metric-track-bar {
        height: 8px;
        border-radius: 9999px;
        overflow: hidden;
        background: #e2e8f0;
    }
    .metric-divider {
        border-top: 1px solid #e2e8f0;
    }
    .metric-text-label {
        color: #1e293b;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .metric-text-value {
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 700;
    }
    .metric-ratio-text {
        color: #64748b;
        font-size: 0.8rem;
        font-weight: 600;
    }

    /* Mode Glassmorphism / Dark Theme Overrides */
    html.theme-glass-solid .metric-card-title,
    html.theme-glass-animated .metric-card-title {
        color: #94a3b8;
    }
    html.theme-glass-solid .metric-card-sub,
    html.theme-glass-animated .metric-card-sub {
        color: #94a3b8;
    }
    html.theme-glass-solid .metric-track-bar,
    html.theme-glass-animated .metric-track-bar {
        background: rgba(255, 255, 255, 0.12);
    }
    html.theme-glass-solid .metric-divider,
    html.theme-glass-animated .metric-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }
    html.theme-glass-solid .metric-text-label,
    html.theme-glass-animated .metric-text-label {
        color: #cbd5e1;
    }
    html.theme-glass-solid .metric-text-value,
    html.theme-glass-animated .metric-text-value {
        color: #f8fafc;
    }
    html.theme-glass-solid .metric-ratio-text,
    html.theme-glass-animated .metric-ratio-text {
        color: #94a3b8;
    }
</style>

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
    <!-- KARTU 1: PEMASUKAN TUNAI DENGAN BREAKDOWN DETAIL (LAYANAN & JASA + OBAT & BHP) -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card shadow h-100" style="border-left: 5px solid #16a34a; border-radius: 12px;">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="text-xs text-uppercase mb-1 metric-card-title">Pemasukan tunai</div>
                    <div class="h3 mb-1 font-weight-bold" style="color: #16a34a;">
                        <?php echo formatRupiah($total_pemasukan_tunai); ?>
                    </div>
                    <div class="small mb-3 metric-card-sub">Total, sudah termasuk Obat dan BHP</div>
                    
                    <!-- Progress Bar Stacked (Hijau & Kuning) -->
                    <div class="d-flex mb-3 metric-track-bar">
                        <div style="width: <?php echo $pct_layanan; ?>%; background: #16a34a;" title="Layanan dan jasa: <?php echo $pct_layanan; ?>%"></div>
                        <div style="width: <?php echo $pct_obat; ?>%; background: #d97706;" title="Obat dan BHP: <?php echo $pct_obat; ?>%"></div>
                    </div>
                </div>

                <!-- Breakdown List -->
                <div class="pt-2 metric-divider">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #16a34a; margin-right: 8px;"></span>
                            <span class="metric-text-label">Layanan dan Pemasukan lain-lain</span>
                        </div>
                        <div class="metric-text-value">
                            <?php echo formatRupiah($total_layanan_jasa); ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #d97706; margin-right: 8px;"></span>
                            <span class="metric-text-label">Obat dan BHP</span>
                        </div>
                        <div class="metric-text-value">
                            <?php echo formatRupiah($total_obat_bhp); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KARTU 2: PENGELUARAN TUNAI -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card shadow h-100" style="border-left: 5px solid #dc2626; border-radius: 12px;">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="text-xs text-uppercase mb-1 metric-card-title">Pengeluaran tunai</div>
                    <div class="h3 mb-1 font-weight-bold" style="color: #dc2626;">
                        <?php echo formatRupiah($total_pengeluaran); ?>
                    </div>
                    <div class="small mb-3 metric-card-sub">Total pengeluaran operasional kasir</div>
                    
                    <!-- Progress Bar Pengeluaran -->
                    <div class="d-flex mb-3 metric-track-bar">
                        <div style="width: <?php echo $pct_pengeluaran; ?>%; background: #dc2626;" title="Rasio Pengeluaran: <?php echo $pct_pengeluaran; ?>%"></div>
                    </div>
                </div>

                <!-- Breakdown Info -->
                <div class="pt-2 metric-divider">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #dc2626; margin-right: 8px;"></span>
                            <span class="metric-text-label">Beban Pengeluaran Harian</span>
                        </div>
                        <div class="metric-text-value">
                            <?php echo formatRupiah($total_pengeluaran); ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #64748b; margin-right: 8px;"></span>
                            <span class="metric-text-label">Rasio Kas Keluar</span>
                        </div>
                        <div class="metric-ratio-text">
                            <?php echo $pct_pengeluaran; ?>% dari Pemasukan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KARTU 3: NETT CASH FLOW (KAS BERSIH) -->
    <div class="col-lg-4 col-md-12 mb-4">
        <div class="card shadow h-100" style="border-left: 5px solid #0284c7; border-radius: 12px;">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="text-xs text-uppercase mb-1 metric-card-title">Nett Cash Flow (Kas Bersih)</div>
                    <div class="h3 mb-1 font-weight-bold" style="color: #0284c7;">
                        <?php echo formatRupiah($net_cash_flow); ?>
                    </div>
                    <div class="small mb-3 metric-card-sub">Pemasukan Tunai - Pengeluaran Tunai</div>
                    
                    <!-- Progress Bar Cash Flow -->
                    <div class="d-flex mb-3 metric-track-bar">
                        <div style="width: <?php echo $pct_cashflow; ?>%; background: #0284c7;" title="Kas Bersih: <?php echo $pct_cashflow; ?>%"></div>
                    </div>
                </div>

                <!-- Breakdown Info -->
                <div class="pt-2 metric-divider">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #0284c7; margin-right: 8px;"></span>
                            <span class="metric-text-label">Sisa Kas Riil</span>
                        </div>
                        <div class="metric-text-value">
                            <?php echo formatRupiah($net_cash_flow); ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 3px; background: #64748b; margin-right: 8px;"></span>
                            <span class="metric-text-label">Margin Kas Bersih</span>
                        </div>
                        <div class="metric-ratio-text" style="color: #0284c7; font-weight: 700;">
                            <?php echo $pct_cashflow; ?>% dari Omzet
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Grafik Tren Pemasukan vs Pengeluaran Harian</h6>
                <div>
                    <span class="badge bg-success me-1">Pemasukan</span>
                    <span class="badge bg-danger me-1">Pengeluaran</span>
                    <span class="badge bg-warning text-dark">Obat & BHP</span>
                </div>
            </div>
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

<!-- ROW BARU: TOP 10 PENJUALAN OBAT TERBANYAK -->
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-pills me-2 text-warning"></i>Top 10 Penjualan Obat (Kuantitas / Qty)</h6>
                <span class="badge bg-warning text-dark">Donut Chart</span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 350px;">
                    <canvas id="chartTop10ObatQty"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-coins me-2 text-success"></i>Top 10 Penjualan Obat (Nilai Omzet Rp)</h6>
                <span class="badge bg-success">Pie Chart</span>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 350px;">
                    <canvas id="chartTop10ObatNominal"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    var tglAwal = '<?php echo $tgl_awal; ?>';
    var tglAkhir = '<?php echo $tgl_akhir; ?>';
    var myChartTrenHarian, myChartPerShift, myChartPengeluaran, myChartKomposisiPemasukan, myChartTop10Qty, myChartTop10Nominal;
    var ctxTren = document.getElementById('chartTrenHarian').getContext('2d');
    var ctxShift = document.getElementById('chartPerShift').getContext('2d');
    var ctxKeluar = document.getElementById('chartPengeluaran').getContext('2d');
    var ctxMasuk = document.getElementById('chartKomposisiPemasukan').getContext('2d');
    var ctxTopQty = document.getElementById('chartTop10ObatQty').getContext('2d');
    var ctxTopNominal = document.getElementById('chartTop10ObatNominal').getContext('2d');

    function formatRupiah(angka) {
        if(angka == null || isNaN(angka)) return "Rp 0";
        var number_string = Math.round(angka).toString().replace(/[^,\d\-]/g, ''),
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

    function formatAngka(angka) {
        if(angka == null || isNaN(angka)) return "0";
        return Number(angka).toLocaleString('id-ID');
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
                renderChartTop10Qty(data.top_obat_qty);
                renderChartTop10Nominal(data.top_obat_nominal);
            })
            .catch(function(error) { console.error('Error loadAllCharts:', error); });
    }
    
    function renderChartTren(data) {
        if (myChartTrenHarian) myChartTrenHarian.destroy(); 
        myChartTrenHarian = new Chart(ctxTren, {
            type: 'line',
            data: {
                labels: data.labels, 
                datasets: [
                    {
                        label: 'Pemasukan Tunai', 
                        data: data.pemasukan, 
                        borderColor: 'rgb(25, 135, 84)', 
                        backgroundColor: 'rgba(25, 135, 84, 0.1)', 
                        fill: true, 
                        tension: 0.1,
                        borderWidth: 2
                    }, 
                    {
                        label: 'Pengeluaran Tunai', 
                        data: data.pengeluaran, 
                        borderColor: 'rgb(220, 53, 69)', 
                        backgroundColor: 'rgba(220, 53, 69, 0.1)', 
                        fill: true, 
                        tension: 0.1,
                        borderWidth: 2
                    },
                    {
                        label: 'Obat & BHP', 
                        data: data.obat_bhp, 
                        borderColor: '#f6c23e', // Garis kuning sesuai permintaan user
                        backgroundColor: 'rgba(246, 194, 62, 0.15)', 
                        fill: false, 
                        tension: 0.1,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#f6c23e'
                    }
                ]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true,
                plugins: { 
                    tooltip: { 
                        callbacks: { 
                            label: function(context) { return context.dataset.label + ': ' + formatRupiah(context.parsed.y); } 
                        } 
                    } 
                }, 
                scales: { 
                    y: { 
                        ticks: { 
                            callback: function(value) { return formatRupiah(value); } 
                        } 
                    } 
                } 
            }
        });
    }

    function renderChartPerShift(data) {
        if (myChartPerShift) myChartPerShift.destroy();
        myChartPerShift = new Chart(ctxShift, {
            type: 'bar',
            data: {
                labels: data.labels, 
                datasets: [{
                    label: 'Total Pemasukan Tunai', 
                    data: data.data, 
                    backgroundColor: ['rgba(255, 99, 132, 0.2)', 'rgba(54, 162, 235, 0.2)', 'rgba(255, 206, 86, 0.2)', 'rgba(75, 192, 192, 0.2)'], 
                    borderColor: ['rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)', 'rgba(255, 206, 86, 1)', 'rgba(75, 192, 192, 1)'], 
                    borderWidth: 1
                }]
            },
            options: { 
                responsive: true, 
                plugins: { 
                    legend: { display: false }, 
                    tooltip: { 
                        callbacks: { 
                            label: function(context) { return 'Total: ' + formatRupiah(context.parsed.y); } 
                        } 
                    } 
                }, 
                scales: { 
                    y: { 
                        ticks: { 
                            callback: function(value) { return formatRupiah(value); } 
                        } 
                    } 
                } 
            }
        });
    }

    function renderChartPengeluaran(data) {
        if (myChartPengeluaran) myChartPengeluaran.destroy();
        var labels = (data && data.labels && data.labels.length > 0) ? data.labels : ['Belum Ada Pengeluaran'];
        var values = (data && data.data && data.data.length > 0) ? data.data : [0];
        myChartPengeluaran = new Chart(ctxKeluar, {
            type: 'doughnut',
            data: { 
                labels: labels, 
                datasets: [{
                    data: values, 
                    backgroundColor: ['#e74c3c', '#f1c40f', '#9b59b6', '#3498db', '#2ecc71', '#e67e22', '#1abc9c']
                }] 
            },
            options: { 
                responsive: true, 
                plugins: { 
                    tooltip: { 
                        callbacks: { 
                            label: function(context) { return context.label + ': ' + formatRupiah(context.parsed); } 
                        } 
                    } 
                } 
            }
        });
    }

    function renderChartKomposisiPemasukan(data) {
        if (myChartKomposisiPemasukan) myChartKomposisiPemasukan.destroy();
        myChartKomposisiPemasukan = new Chart(ctxMasuk, {
            type: 'pie',
            data: { 
                labels: data.labels, 
                datasets: [{
                    data: data.data, 
                    backgroundColor: ['#2ecc71', '#3498db', '#f6c23e', '#e67e22'] // Ralan (Hijau), Ranap (Biru), Obat & BHP (Kuning), Lain-lain (Oranye)
                }] 
            },
            options: { 
                responsive: true, 
                plugins: { 
                    tooltip: { 
                        callbacks: { 
                            label: function(context) { 
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var val = context.parsed;
                                var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + formatRupiah(val) + ' (' + pct + '%)'; 
                            } 
                        } 
                    } 
                } 
            }
        });
    }

    // Chart Top 10 Obat Berdasarkan Kuantitas (Donut Chart)
    function renderChartTop10Qty(data) {
        if (myChartTop10Qty) myChartTop10Qty.destroy();
        var colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1', '#fd7e14', '#20c997', '#0dcaf0', '#d63384'];
        var labels = (data && data.labels && data.labels.length > 0) ? data.labels : ['Tidak Ada Data'];
        var values = (data && data.data && data.data.length > 0) ? data.data : [0];
        var satuan = (data && data.satuan) ? data.satuan : [];

        myChartTop10Qty = new Chart(ctxTopQty, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.slice(0, labels.length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: { size: 10 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var val = context.parsed;
                                var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                var unit = satuan[context.dataIndex] ? ' ' + satuan[context.dataIndex] : '';
                                return context.label + ': ' + formatAngka(val) + unit + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Chart Top 10 Obat Berdasarkan Nominal (Pie Chart)
    function renderChartTop10Nominal(data) {
        if (myChartTop10Nominal) myChartTop10Nominal.destroy();
        var colors = ['#1cc88a', '#4e73df', '#f6c23e', '#36b9cc', '#e74a3b', '#6f42c1', '#20c997', '#fd7e14', '#0dcaf0', '#d63384'];
        var labels = (data && data.labels && data.labels.length > 0) ? data.labels : ['Tidak Ada Data'];
        var values = (data && data.data && data.data.length > 0) ? data.data : [0];

        myChartTop10Nominal = new Chart(ctxTopNominal, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.slice(0, labels.length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: { size: 10 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var val = context.parsed;
                                var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + formatRupiah(val) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
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
