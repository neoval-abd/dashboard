<?php
/*
 * File: laporan_kinerja_dokter.php (UPDATE V2)
 * - Fitur Baru: Modal Detail Pasien per Dokter.
 * - Fitur Baru: Export Data Detail (Excel/PDF).
 * - Menampilkan total pendapatan billing yang dihasilkan dokter tersebut.
 */

$page_title = "Laporan Kinerja Dokter";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary">Filter Periode</h5>
            <form id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="tgl_awal" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="tgl_akhir" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="loadData()">
                        <i class="fas fa-search me-2"></i> Tampilkan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Grafik Volume Pelayanan Dokter (Top 15)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 400px;">
                        <canvas id="chartDokter"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Rincian Kinerja Seluruh Dokter</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover" id="dataTable" width="100%" cellspacing="0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nama Dokter</th>
                                    <th class="text-center">Jml Pasien Ralan</th>
                                    <th class="text-center">Jml Pasien Ranap</th>
                                    <th class="text-center">Total Volume</th>
                                    <th class="text-center" width="10%">Aksi</th>
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
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-md me-2"></i>Rincian Pasien: <span id="modalTitleDokter" class="fw-bold">...</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table id="tableDetail" class="table table-striped table-hover table-sm w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu Registrasi</th>
                                <th>Waktu Tutup Billing</th>
                                <th>No. Rawat</th>
                                <th>No. RM</th>
                                <th>Nama Pasien</th>
                                <th>Status</th>
                                <th>Penjamin</th>
                                <th class="text-end">Total Billing (Rp)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="7" class="text-end">Total Pendapatan:</th>
                                <th class="text-end" id="totalPendapatanDokter">0</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<?php ob_start(); ?>
<script>
    var myChart; 
    var myTable; 
    var detailTable;

    // Helper format rupiah
    function formatMoney(amount) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
    }

    $(document).ready(function() {
        // 1. Init Main Table
        myTable = $('#dataTable').DataTable({
            "responsive": true,
            "dom": 'Bfrtip', 
            "buttons": [
                { extend: 'excelHtml5', className: 'btn btn-success btn-sm', text: '<i class="fas fa-file-excel"></i> Excel Summary' },
                { extend: 'print', className: 'btn btn-secondary btn-sm', text: '<i class="fas fa-print"></i> Print' }
            ],
            "columns": [
                { "data": "nama", className: "fw-bold" },
                { "data": "ralan", className: "text-center text-success" },
                { "data": "ranap", className: "text-center text-warning fw-bold" },
                { "data": "total", className: "text-center fw-bolder" },
                { 
                    "data": null, 
                    className: "text-center",
                    render: function(data, type, row) {
                        // Tombol untuk membuka modal detail
                        // row.kd_dokter tidak ada di response API sebelumnya? 
                        // Kita harus memastikan API utama mengembalikan key arraynya sebagai data.
                        // API kita sebelumnya me-return object indexed by code, lalu di reindex jadi array.
                        // Kita perlu menyisipkan kode dokter ke dalam array response.
                        return `<button class="btn btn-sm btn-info text-white" onclick="openDetail('${row.kode}', '${row.nama}')">
                                    <i class="fas fa-list-ul me-1"></i> Detail
                                </button>`;
                    }
                }
            ],
            "order": [[ 3, "desc" ]], 
            "pageLength": 25
        });

        // 2. Init Detail Table (Inside Modal)
        detailTable = $('#tableDetail').DataTable({
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [
                { extend: 'excelHtml5', className: 'btn btn-success btn-sm', text: '<i class="fas fa-file-excel"></i> Export Excel' },
                { extend: 'pdfHtml5', className: 'btn btn-danger btn-sm', text: '<i class="fas fa-file-pdf"></i> Export PDF', orientation: 'landscape' }
            ],
            "columns": [
                { "data": "tgl_reg" },
                { "data": "tgl_tutup" },
                { "data": "no_rawat" },
                { "data": "no_rm" },
                { "data": "pasien" },
                { "data": "status", 
                  render: function(data) {
                      return data === 'Ralan' ? '<span class="badge bg-success">Ralan</span>' : '<span class="badge bg-warning text-dark">Ranap</span>';
                  }
                },
                { "data": "penjamin" },
                { "data": "total", className: "text-end fw-bold", render: function(data) { return formatMoney(data); } }
            ],
            "pageLength": 10,
            "footerCallback": function (row, data, start, end, display) {
                var api = this.api();
                // Hitung total pendapatan dari data yang tampil
                var total = api.column(7, { page: 'current' }).data().reduce(function (a, b) {
                    return parseFloat(a) + parseFloat(b);
                }, 0);
                // Update footer
                $('#totalPendapatanDokter').html(formatMoney(total));
            }
        });

        // Data akan dimuat saat user klik tombol Tampilkan
    });

    function loadData() {
        var tglAwal = $('#tgl_awal').val();
        var tglAkhir = $('#tgl_akhir').val();

        $.ajax({
            url: 'api/data_kinerja_dokter.php',
            type: 'GET',
            data: { tgl_awal: tglAwal, tgl_akhir: tglAkhir },
            dataType: 'json',
            success: function(response) {
                renderChart(response.chart);
                
                // Inject kode dokter ke dalam data tabel agar bisa diklik
                // Karena API kinerja dokter mengembalikan array of objects, 
                // tapi di loop PHP sebelumnya kita belum memasukkan 'kode' dokter secara eksplisit ke dalam item array.
                // Mari kita perbaiki data di client side atau server side. 
                // Server side lebih baik. *Saya sudah update kode PHP di bawah untuk menyertakan 'kode'*.
                
                myTable.clear();
                myTable.rows.add(response.table);
                myTable.draw();
            },
            error: function() { alert("Gagal memuat data."); }
        });
    }

    function openDetail(kdDokter, nmDokter) {
        var tglAwal = $('#tgl_awal').val();
        var tglAkhir = $('#tgl_akhir').val();

        $('#modalTitleDokter').text(nmDokter);
        $('#modalDetail').modal('show');
        
        detailTable.clear().draw(); // Kosongkan dulu
        
        $.ajax({
            url: 'api/data_detail_kinerja_dokter.php',
            type: 'GET',
            data: { 
                tgl_awal: tglAwal, 
                tgl_akhir: tglAkhir, 
                kd_dokter: kdDokter 
            },
            dataType: 'json',
            success: function(response) {
                detailTable.clear();
                if (response.data && response.data.length > 0) {
                    detailTable.rows.add(response.data);
                }
                detailTable.draw();
                
                // Update Total Global di footer (bukan per page, tapi total semua data)
                var totalSemua = response.data.reduce(function(a, b) {
                    return a + parseFloat(b.total);
                }, 0);
                $('#totalPendapatanDokter').html(formatMoney(totalSemua));
            },
            error: function() { console.error("Gagal load detail"); }
        });
    }

    function renderChart(chartData) {
        var ctx = document.getElementById("chartDokter").getContext('2d');
        if(myChart) myChart.destroy();

        myChart = new Chart(ctx, {
            type: 'bar', 
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: "Rawat Jalan",
                        data: chartData.ralan,
                        backgroundColor: '#1cc88a',
                        hoverBackgroundColor: '#17a673',
                    },
                    {
                        label: "Rawat Inap",
                        data: chartData.ranap,
                        backgroundColor: '#f6c23e',
                        hoverBackgroundColor: '#dda20a',
                    }
                ],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true }
                },
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>