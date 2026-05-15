<?php
$page_title = "Peta Demografi Pasien";
require_once('includes/header.php');

$tgl_awalnya = date('Y-m-01');
$tgl_akhirnya = date('Y-m-d');

// Ambil daftar penjamin (penjab) untuk filter
$penjab_list = [];
$res_pj = $koneksi->query("SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab ASC");
if($res_pj) {
    while($row_pj = $res_pj->fetch_assoc()) {
        $penjab_list[] = $row_pj;
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-map-marked-alt text-primary"></i> Peta Demografi & Area Asal Pasien</h1>
    </div>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Dari Tanggal Kunjungan</label>
                    <input type="date" class="form-control" id="tgl_awal" value="<?php echo $tgl_awalnya; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" value="<?php echo $tgl_akhirnya; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label font-weight-bold">Penjamin / Asuransi</label>
                    <select class="form-select" id="kd_pj">
                        <option value="">-- Semua Penjamin --</option>
                        <?php foreach($penjab_list as $pj): ?>
                            <option value="<?= $pj['kd_pj']; ?>"><?= $pj['png_jawab']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="loadData()"><i class="fas fa-search me-1"></i> Cari</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Widget KPI -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Kunjungan Pasien Baru</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-baru">0</span> <small class="text-xs">Org</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Kunjungan Pasien Lama</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-lama">0</span> <small class="text-xs">Org</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Kunjungan Keseluruhan</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-total">0</span> <small class="text-xs">Org</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hospital-user fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Visualisasi Geospasial / Chart -->
    <div class="row">
        <!-- Pie Chart Kabupaten -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 5 Kabupaten Asal</h6>
                </div>
                <div class="card-body h-100 d-flex flex-column justify-content-center align-items-center pb-5">
                    <div class="chart-pie">
                        <canvas id="pieChartKab"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Horizontal Bar Chart Kecamatan -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Kecamatan Kunjungan Terpadat</h6>
                </div>
                <div class="card-body pb-5">
                    <div class="chart-bar" style="height: 300px;">
                        <canvas id="barChartKec"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bar Chart Kelurahan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Top 10 Kelurahan/Desa Asal Kunjungan Tertinggi</h6>
        </div>
        <div class="card-body">
            <div class="chart-bar" style="height: 350px;">
                <canvas id="barChartKel"></canvas>
            </div>
        </div>
    </div>

    <!-- DataTables Detail -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Rincian Komposisi Pasien Per Daerah (Kelurahan)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" id="dataTableDemografi" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr>
                            <th>Kelurahan / Desa</th>
                            <th>Kecamatan</th>
                            <th>Kabupaten / Kota</th>
                            <th class="text-center text-primary">Pasien Baru</th>
                            <th class="text-center text-success">Pasien Lama</th>
                            <th class="text-center fw-bold">Total Kunjungan</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>

<script>
    var tableDemo;
    var pieChartKabInstance;
    var barChartKecInstance;
    var barChartKelInstance;

    $(document).ready(function() {
        tableDemo = $('#dataTableDemografi').DataTable({
            "responsive": true,
            "pageLength": 25,
            "order": [[ 5, "desc" ]], // Urut berdasarkan Total Kunjungan yang terbanyak
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'excelHtml5',
                    title: 'Laporan Kepadataan Kunjungan Pasien Per Daerah (Demografi)',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: ':visible' }
                }
            ],
            "columns": [
                { "data": "nm_kel", "className": "fw-bold" },
                { "data": "nm_kec", "className": "text-muted" },
                { "data": "nm_kab", "className": "text-muted" },
                { "data": "baru", "className": "text-center text-primary" },
                { "data": "lama", "className": "text-center text-success" },
                { "data": "total", "className": "text-center fw-bold" }
            ]
        });

        // Data akan dimuat saat user klik tombol Cari
    });

    function loadData() {
        var tgl1 = $('#tgl_awal').val();
        var tgl2 = $('#tgl_akhir').val();
        var penjab = $('#kd_pj').val();

        $('#kpi-baru, #kpi-lama, #kpi-total').text('...');

        $.ajax({
            url: 'api/data_demografi.php',
            type: 'GET',
            data: { tgl_awal: tgl1, tgl_akhir: tgl2, kd_pj: penjab },
            dataType: 'json',
            success: function(res) {
                // Formatting custom string ID Number
                let idID = new Intl.NumberFormat('id-ID');

                // Update KPI Cards
                $('#kpi-baru').text(idID.format(res.summary.total_pasien_baru));
                $('#kpi-lama').text(idID.format(res.summary.total_pasien_lama));
                $('#kpi-total').text(idID.format(res.summary.total_kunjungan));

                // Update Table
                tableDemo.clear();
                tableDemo.rows.add(res.data);
                tableDemo.draw();

                // Render Visualisasi Charts
                renderPieKabupaten(res.chart.kabupaten);
                renderBarKecamatan(res.chart.kecamatan);
                renderBarKelurahan(res.chart.kelurahan);
            },
            error: function(err) {
                alert("Gagal memuat data Demografi Pasien.");
                console.error(err);
            }
        });
    }

    // Palette warna custom cerah ala dashboard modern
    const C_PALETTE = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69','#e83e8c', '#6f42c1', '#fd7e14'];

    function renderPieKabupaten(chartData) {
        if (pieChartKabInstance) { pieChartKabInstance.destroy(); }
        var ctx = document.getElementById("pieChartKab");
        pieChartKabInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.data,
                    backgroundColor: C_PALETTE.slice(0, chartData.labels.length),
                    hoverBackgroundColor: C_PALETTE.slice(0, chartData.labels.length),
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20, boxWidth: 12 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let val = context.raw;
                                let percentage = Math.round((val / total) * 100);
                                return ' ' + context.label + ': ' + val + ' Pasien (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '70%',
            },
        });
    }

    function renderBarKecamatan(chartData) {
        if (barChartKecInstance) { barChartKecInstance.destroy(); }
        var ctx = document.getElementById("barChartKec");
        barChartKecInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: "Kunjungan",
                    backgroundColor: "#36b9cc",
                    hoverBackgroundColor: "#2c9faf",
                    borderColor: "#36b9cc",
                    data: chartData.data,
                    borderRadius: 4,
                }],
            },
            options: {
                maintainAspectRatio: false,
                indexAxis: 'y', // Membuatnya Horizontal Bar Chart
                plugins: { legend: { display: false } }, // Sembunyikan legenda
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { drawBorder: false, color: "#ebedef" },
                    },
                    y: {
                        grid: { display: false, drawBorder: false }
                    }
                }
            }
        });
    }

    function renderBarKelurahan(chartData) {
        if (barChartKelInstance) { barChartKelInstance.destroy(); }
        var ctx = document.getElementById("barChartKel");
        barChartKelInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: "Kunjungan",
                    backgroundColor: "#4e73df",
                    hoverBackgroundColor: "#2e59d9",
                    borderColor: "#4e73df",
                    data: chartData.data,
                    borderRadius: 4,
                    maxBarThickness: 50
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { maxTicksLimit: 10 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgb(234, 236, 244)", drawBorder: false },
                    }
                }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
