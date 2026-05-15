<?php
$page_title = "Kinerja Pelayanan (Waktu Tunggu)";
require_once('includes/header.php');

$tgl_awalnya = date('Y-m-01');
$tgl_akhirnya = date('Y-m-d');

// Ambil daftar penjamin (penjab)
$penjab_list = [];
$res_pj = $koneksi->query("SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab ASC");
if($res_pj) {
    while($row_pj = $res_pj->fetch_assoc()) {
        $penjab_list[] = $row_pj;
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-2">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-stopwatch text-primary"></i> Laporan Waktu Tunggu Pelayanan (TAT Ralan)</h1>
    </div>
    
    <div class="alert alert-info py-2 shadow-sm mb-4" role="alert">
        <i class="fas fa-info-circle me-1"></i> <strong>Apa itu TAT?</strong> TAT (<em>Turn Around Time</em>) adalah total waktu yang dibutuhkan sejak pelayanan dimulai hingga selesai untuk satu proses spesifik. Dalam konteks Rumah Sakit, ini mengukur berapa lama pasien harus menunggu dari awal pendaftaran sampai mereka mendapat pelayanan secara menyeluruh.
    </div>

    <!-- Filter Card -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" value="<?php echo $tgl_awalnya; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" value="<?php echo $tgl_akhirnya; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Penjamin / Asuransi</label>
                    <select class="form-select" id="kd_pj">
                        <option value="">-- Semua Penjamin --</option>
                        <?php foreach($penjab_list as $pj): ?>
                            <option value="<?= $pj['kd_pj']; ?>"><?= $pj['png_jawab']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label font-weight-bold">Cari Pasien / No RM</label>
                    <input type="text" class="form-control" id="keyword" placeholder="Opsional...">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="loadData()"><i class="fas fa-search me-1"></i> Cari</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Widget KPI -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Average TAT Pendaftaran -> Poli (Validasi)</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-dp">0</span> <small class="text-xs">Menit</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-stethoscope fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Average TAT Resep -> Selesai Obat (Farmasi)</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-ro">0</span> <small class="text-xs">Menit</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-pills fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Average Daftar -> Obat Diterima (Medis)</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-total">0</span> <small class="text-xs">Menit</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-medkit fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Average TAT (Daftar -> Keluar Kasir)</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-kasir">0</span> <small class="text-xs">Menit</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-flag-checkered fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Tren Rata-rata Waktu Pelayanan Harian</h6>
        </div>
        <div class="card-body">
            <canvas id="lineChartTAT" style="min-height: 350px; max-height: 350px;"></canvas>
        </div>
    </div>

    <!-- Rincian Data -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Rincian Waktu Pelayanan Per Pasien</h6>
            <span class="badge bg-secondary" id="countPasien">0 Pasien</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTableTAT" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr>
                            <th>Tgl. Registrasi</th>
                            <th>No. RM</th>
                            <th>Nama Pasien</th>
                            <th>Penjamin</th>
                            <th>Poliklinik</th>
                            <th>Jam Daftar</th>
                            <th>Jam Periksa</th>
                            <th>Daftar->Poli</th>
                            <th>Jam Obat</th>
                            <th>Resep->Obat</th>
                            <th>Jam Kasir</th>
                            <th class="text-center">Daftar->Kasir</th>
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
    var tableTAT;
    var lineChartInstance;

    $(document).ready(function() {
        tableTAT = $('#dataTableTAT').DataTable({
            "responsive": true,
            "pageLength": 10,
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'excelHtml5',
                    title: 'Laporan Waktu Tunggu Pelayanan Pasien Ralan',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: ':visible' }
                }
            ],
            "columns": [
                { "data": "tgl_registrasi" },
                { "data": "no_rkm_medis" },
                { "data": "nm_pasien" },
                { "data": "png_jawab", "className": "text-muted small" },
                { "data": "nm_poli" },
                { "data": "jam_reg", "className": "text-center" },
                { "data": "jam_periksa", "defaultContent": "-", "className": "text-center" },
                { "data": "durasi_dp", "defaultContent": "-", "className": "text-center bg-light text-primary", render: function(d) { return d ? d + " mnt" : "-"; } },
                { "data": "jam_selesai_obat", "defaultContent": "-", "className": "text-center" },
                { "data": "durasi_ro", "defaultContent": "-", "className": "text-center bg-light text-success", render: function(d) { return d ? d + " mnt" : "-"; } },
                { "data": "jam_kasir", "defaultContent": "-", "className": "text-center", render: function(d, type, row) { return row.is_ranap ? "<span class='badge bg-info'>Pasien Ranap</span>" : (d ? d : "-"); } },
                { "data": "durasi_kasir", "defaultContent": "-", "className": "text-center font-weight-bold bg-warning text-dark", render: function(d, type, row) { return row.is_ranap ? "-" : (d ? d + " mnt" : "-"); } }
            ]
        });

        // Data akan dimuat saat user klik tombol Cari
    });

    function loadData() {
        var tk_awal = $('#tgl_awal').val();
        var tk_akhir = $('#tgl_akhir').val();
        var penjab = $('#kd_pj').val();
        var search = $('#keyword').val();

        $('#kpi-dp, #kpi-ro, #kpi-total, #kpi-kasir').text('...');

        $.ajax({
            url: 'api/data_waktu_tunggu.php',
            type: 'GET',
            data: { tgl_awal: tk_awal, tgl_akhir: tk_akhir, kd_pj: penjab, keyword: search },
            dataType: 'json',
            success: function(res) {
                // Update KPI Cards
                $('#kpi-dp').text(res.summary.avg_daftar_periksa);
                $('#kpi-ro').text(res.summary.avg_resep_obat);
                $('#kpi-total').text(res.summary.avg_total_tat);
                $('#kpi-kasir').text(res.summary.avg_total_kasir);
                $('#countPasien').text(res.summary.jml_pasien + ' Pasien');

                // Update Table
                tableTAT.clear();
                tableTAT.rows.add(res.data);
                tableTAT.draw();

                renderChart(res.chart);
            },
            error: function(err) {
                alert("Gagal memuat data Waktu Tunggu Pelayanan.");
                console.error(err);
            }
        });
    }

    function renderChart(chartData) {
        if (lineChartInstance) { lineChartInstance.destroy(); }
        
        var ctxLine = document.getElementById('lineChartTAT').getContext('2d');
        lineChartInstance = new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Daftar -> Periksa (Poli)',
                        data: chartData.data_daftar_periksa,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        pointBackgroundColor: '#4e73df',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Resep -> Selesai Obat (Farmasi)',
                        data: chartData.data_resep_obat,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        pointBackgroundColor: '#1cc88a',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Daftar -> Keluar Kasir (Total Keluar RS)',
                        data: chartData.data_total_kasir,
                        borderColor: '#f6c23e',
                        backgroundColor: 'rgba(246, 194, 62, 0.1)',
                        pointBackgroundColor: '#f6c23e',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' Menit';
                            }
                        }
                    },
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Rata-rata TAT (Menit)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
