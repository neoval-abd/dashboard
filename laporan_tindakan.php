<?php
/*
 * File: laporan_tindakan.php (UPDATE V5 - +Widget Jasmed)
 */

$page_title = "Laporan Analisa Tindakan Medis";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

$penjabs = [];
$sql_pj = "SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab";
$res_pj = $koneksi->query($sql_pj);
while($row = $res_pj->fetch_assoc()){ $penjabs[] = $row; }

$dokters = [];
$sql_dr = "SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter";
$res_dr = $koneksi->query($sql_dr);
while($row = $res_dr->fetch_assoc()){ $dokters[] = $row; }
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid">

    <div class="alert alert-warning border-left-warning shadow-sm mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Disclaimer:</strong> 
        "Total Revenue" adalah Total Tagihan ke Pasien (Gross). 
        "Total Jasa Medis" adalah bagian Hak Dokter (Netto) dari tindakan tersebut.
    </div>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3"><i class="fas fa-filter me-2"></i>Filter Laporan</h5>
            <form id="filterForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Kategori Unit</label>
                    <select class="form-select" id="kategori">
                        <option value="">-- Semua Unit --</option>
                        <option value="Rawat Jalan">Rawat Jalan</option>
                        <option value="Rawat Inap">Rawat Inap</option>
                        <option value="Operasi">Operasi</option>
                        <option value="Laboratorium">Laboratorium</option>
                        <option value="Radiologi">Radiologi</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Dokter / Pelaksana</label>
                    <select class="form-select select2-single" id="kd_dokter">
                        <option value="">-- Semua Dokter --</option>
                        <?php foreach($dokters as $d): ?>
                            <option value="<?php echo $d['kd_dokter']; ?>"><?php echo $d['nm_dokter']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Penjamin</label>
                    <select class="form-select select2-single" id="kd_pj">
                        <option value="">-- Semua Penjamin --</option>
                        <?php foreach($penjabs as $p): ?>
                            <option value="<?php echo $p['kd_pj']; ?>"><?php echo $p['png_jawab']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12 text-end">
                    <button type="button" class="btn btn-primary" onclick="loadData()">
                        <i class="fas fa-search me-2"></i> Tampilkan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue (Gross)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-revenue">Loading...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2 bg-gray-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Jasa Medis (Dokter)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-jasmed">...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-md fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Jml Tindakan (Qty)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-qty">...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-syringe fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Rata-rata Nilai</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-avg">...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Komposisi Pendapatan</h6></div>
                <div class="card-body"><div class="chart-pie pt-4 pb-2" style="height: 300px;"><canvas id="chartPie"></canvas></div></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Tren Pendapatan Harian</h6></div>
                <div class="card-body"><div class="chart-area" style="height: 300px;"><canvas id="chartLine"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Top 10 Tindakan (Pendapatan Tertinggi)</h6></div>
        <div class="card-body"><div class="chart-bar" style="height: 400px;"><canvas id="chartBar"></canvas></div></div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Rincian Transaksi Tindakan</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm text-sm" id="dataTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Waktu</th>
                            <th>No. Rawat</th>
                            <th>No. RM</th>
                            <th>Pasien</th>
                            <th>Nama Tindakan</th>
                            <th>Dokter/Pelaksana</th>
                            <th class="text-end">JM Dokter</th>
                            <th class="text-end">Total Biaya</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    var myTable;
    var chartPie, chartLine, chartBar;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        $('.select2-single').select2({ theme: "bootstrap-5", placeholder: "Cari...", allowClear: true });

        const urlParams = new URLSearchParams(window.location.search);
        const kdDokterParam = urlParams.get('kd_dokter');
        if(kdDokterParam){
            $('#kd_dokter').val(kdDokterParam).trigger('change');
        }

        myTable = $('#dataTable').DataTable({
            "responsive": true, 
            "dom": 'Bfrtip',
            /*"buttons": [ 
                { extend: 'excel', className: 'btn-sm btn-success', title: 'Laporan Rincian Tindakan' },
                { extend: 'print', className: 'btn-sm btn-secondary' } 
            ], */
			"buttons": [ 
    { 
        extend: 'excelHtml5', 
        className: 'btn-sm btn-success', 
        title: 'Laporan Rincian Tindakan',
        exportOptions: {
            columns: ':visible',
            format: {
                body: function(data, row, column, node) {
                    // Kolom 6 & 7 adalah Rupiah
                    if (column === 6 || column === 7) {
                        return typeof data === 'string' ? data.replace(/[^\d,-]/g, '').replace(',', '.') : data;
                    }
                    return data;
                }
            }
        }
    },
    { extend: 'print', className: 'btn-sm btn-secondary' } 
],
            "pageLength": 25,
            "deferRender": true,
            "processing": true,
            "columns": [
                { "data": "waktu" },
                { "data": "no_rawat" },
                { "data": "no_rkm_medis" },
                { "data": "nm_pasien" },
                { "data": "nm_perawatan" },
                { "data": "nm_dokter" },
                { "data": "jm_dokter", className: "text-end fw-bold text-success", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "biaya", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') }
            ],
            "order": [[ 0, "desc" ]] 
        });

        // Data akan dimuat saat user klik tombol Tampilkan
    });

    function loadData() {
        $('#val-revenue').text('Loading...');
        
        var params = {
            tgl_awal: $('#tgl_awal').val(),
            tgl_akhir: $('#tgl_akhir').val(),
            kd_pj: $('#kd_pj').val(),
            kategori: $('#kategori').val(),
            kd_dokter: $('#kd_dokter').val()
        };

        $.ajax({
            url: 'api/data_laporan_tindakan.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                $('#val-revenue').text(formatRupiah(response.summary.total_revenue));
                // UPDATE CARD BARU
                $('#val-jasmed').text(formatRupiah(response.summary.total_jasmed));
                
                $('#val-qty').text(response.summary.total_tindakan.toLocaleString());
                $('#val-top').text(response.summary.top_unit);
                $('#val-avg').text(formatRupiah(response.summary.avg_tindakan));

                renderChartPie(response.chart_pie);
                renderChartLine(response.chart_line);
                renderChartBar(response.chart_bar);

                myTable.clear();
                if (response.detail.length > 0) {
                    myTable.rows.add(response.detail).draw();
                } else {
                    myTable.draw();
                }
            },
            error: function() { alert("Gagal memuat data."); }
        });
    }

    // (Kode Render Chart Sama Seperti Sebelumnya)
    function renderChartPie(data) {
        var ctx = document.getElementById("chartPie").getContext('2d');
        if(chartPie) chartPie.destroy();
        chartPie = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.data,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    function renderChartLine(data) {
        var ctx = document.getElementById("chartLine").getContext('2d');
        if(chartLine) chartLine.destroy();
        chartLine = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    { label: "Tindakan Medis", data: data.medis, borderColor: '#4e73df', tension: 0.3, fill: false },
                    { label: "Penunjang (Lab/Rad)", data: data.penunjang, borderColor: '#1cc88a', tension: 0.3, fill: false }
                ]
            },
            options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    function renderChartBar(data) {
        var ctx = document.getElementById("chartBar").getContext('2d');
        if(chartBar) chartBar.destroy();
        chartBar = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{ label: "Total Pendapatan", data: data.data, backgroundColor: '#36b9cc', borderRadius: 5 }]
            },
            options: {
                indexAxis: 'y', maintainAspectRatio: false, scales: { x: { beginAtZero: true } }, plugins: { legend: { display: false } }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>