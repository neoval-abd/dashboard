<?php
/*
 * File: laporan_penyakit.php (UPDATE V5 - FINAL FIX NO RAWAT)
 * - Menampilkan No. Rawat di tabel Modal Detail (Wajib ada).
 * - Memastikan sinkronisasi antara HTML <thead> dan JS Columns.
 */

$page_title = "Laporan 10 Besar Penyakit";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary">Filter & Pencarian</h5>
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="tgl_awal" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="tgl_akhir" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis Kunjungan</label>
                    <select class="form-select" name="status_lanjut" id="status_lanjut">
                        <option value="">-- Semua --</option>
                        <option value="Ralan">Rawat Jalan</option>
                        <option value="Ranap">Rawat Inap</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="loadData()">
                        <i class="fas fa-search me-2"></i> Tampilkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card shadow mb-4 h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Grafik Morbiditas (Top 10)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 450px;">
                        <canvas id="chartPenyakit"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5 mb-4">
            <div class="card shadow mb-4 h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tabel Peringkat</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm" id="dataTable" width="100%" cellspacing="0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Penyakit</th>
                                    <th class="text-end">Jml</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Rincian Pasien: <span id="modalTitlePenyakit" class="fw-bold">...</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tableDetail" class="table table-striped table-hover table-sm w-100">
                        <thead>
                            <tr>
                                <th>No. Rawat</th>
                                <th>Tgl Reg</th>
                                <th>No. RM</th>
                                <th>Nama Pasien</th>
                                <th>L/P</th>
                                <th>Umur</th>
                                <th>Alamat (Kab/Kec/Kel)</th>
                                <th>Dokter</th>
                                <th>Penjamin</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    var myChart; 
    var myTable; 
    var detailTable; 

    $(document).ready(function() {
        // 1. Init Table Summary
        myTable = $('#dataTable').DataTable({
            "responsive": true,
            "dom": 'Bfrtip', 
            "buttons": [
                { extend: 'excelHtml5', className: 'btn btn-success btn-sm', text:'Excel', title: 'Top 10 Penyakit' },
                { extend: 'print', className: 'btn btn-secondary btn-sm', text:'Print' }
            ],
            "columns": [
                { "data": "kode", className: "fw-bold" },
                { "data": "nama" },
                { "data": "jumlah", className: "text-end fw-bold" },
                { 
                    "data": null,
                    className: "text-center",
                    render: function(data, type, row) {
                        // Tombol aksi untuk membuka modal detail
                        return `<button class="btn btn-sm btn-info text-white" onclick="openDetail('${row.kode}', '${row.nama}')"><i class="fas fa-list"></i></button>`;
                    }
                }
            ],
            "order": [[ 2, "desc" ]],
            "pageLength": 10,
            "searching": false, 
            "lengthChange": false
        });

        // 2. Init Table Detail (Di dalam Modal)
        detailTable = $('#tableDetail').DataTable({
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [
                { extend: 'excelHtml5', className: 'btn btn-success btn-sm', text: 'Export Excel Detail' }
            ],
            "columns": [
                // PERBAIKAN DISINI: Mapping Kolom harus sesuai urutan <thead>
                { "data": "no_rawat", className: "fw-bold text-primary" }, 
                { "data": "tgl_registrasi" },
                { "data": "no_rkm_medis" },
                { "data": "nm_pasien" },
                { "data": "jk" },
                { "data": "umur" },
                { "data": "alamat_lengkap" },
                { "data": "nm_dokter" },
                { "data": "png_jawab" }
            ],
            "order": [[ 1, "asc" ]], // Urutkan berdasarkan Tanggal Registrasi
            "pageLength": 10
        });

        // Data akan dimuat saat user klik tombol Tampilkan
    });

    function loadData() {
        var tglAwal = $('#tgl_awal').val();
        var tglAkhir = $('#tgl_akhir').val();
        var statusLanjut = $('#status_lanjut').val();

        $.ajax({
            url: 'api/data_top_penyakit.php',
            type: 'GET',
            data: { tgl_awal: tglAwal, tgl_akhir: tglAkhir, status_lanjut: statusLanjut },
            dataType: 'json',
            success: function(response) {
                renderChart(response.chart);
                myTable.clear();
                myTable.rows.add(response.table);
                myTable.draw();
            },
            error: function() { alert("Gagal memuat data."); }
        });
    }

    function openDetail(kdPenyakit, nmPenyakit) {
        var tglAwal = $('#tgl_awal').val();
        var tglAkhir = $('#tgl_akhir').val();
        var statusLanjut = $('#status_lanjut').val();

        $('#modalTitlePenyakit').text(kdPenyakit + ' - ' + nmPenyakit);
        $('#modalDetail').modal('show');
        
        // Bersihkan tabel sebelum load baru
        detailTable.clear().draw();
        
        $.ajax({
            url: 'api/data_detail_penyakit.php',
            type: 'GET',
            data: { 
                tgl_awal: tglAwal, 
                tgl_akhir: tglAkhir, 
                status_lanjut: statusLanjut,
                kd_penyakit: kdPenyakit 
            },
            dataType: 'json',
            success: function(response) {
                detailTable.clear();
                if (response.data && response.data.length > 0) {
                    detailTable.rows.add(response.data);
                }
                detailTable.draw();
            },
            error: function() { console.error("Gagal load detail"); }
        });
    }

    function renderChart(chartData) {
        var ctx = document.getElementById("chartPenyakit").getContext('2d');
        if(myChart) myChart.destroy();

        myChart = new Chart(ctx, {
            type: 'bar', 
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: "Jumlah Kasus",
                    data: chartData.data,
                    backgroundColor: '#4e73df',
                    borderColor: '#4e73df',
                    borderWidth: 1,
                    borderRadius: 5
                }],
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: function(context) { return 'Jumlah: ' + context.parsed.x; } }
                    }
                },
                scales: {
                    x: { beginAtZero: true, grid: { display: true, drawBorder: false } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>