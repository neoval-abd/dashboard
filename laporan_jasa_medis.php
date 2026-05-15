<?php
/*
 * File: laporan_jasa_medis.php (UPDATE V3 - FINAL UX)
 * - Fix: Hyperlink Detail sekarang membuka di tab yang sama (Self).
 * - Ikon diganti menjadi panah kanan.
 */

$page_title = "Laporan Jasa Medis Dokter";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// Ambil Penjamin
$penjabs = [];
$sql_pj = "SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab";
$res_pj = $koneksi->query($sql_pj);
while($row = $res_pj->fetch_assoc()){ $penjabs[] = $row; }

// Ambil Dokter (Untuk Filter)
$dokters = [];
$sql_dr = "SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter";
$res_dr = $koneksi->query($sql_dr);
while($row = $res_dr->fetch_assoc()){ $dokters[] = $row; }
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid">

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3"><i class="fas fa-user-md me-2"></i>Filter Jasa Medis</h5>
            <form id="filterForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Dokter</label>
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
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="loadData()">
                        <i class="fas fa-search me-2"></i> Tampilkan
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
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Jasa Medis (Est)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-total">Rp 0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-stethoscope fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">JM Rawat Jalan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-ralan">Rp 0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-walking fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">JM Rawat Inap</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-ranap">Rp 0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-bed fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">JM Operasi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-operasi">Rp 0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-cut fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Dokter (Jasa Medis Tertinggi)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 350px;">
                        <canvas id="chartTop"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Komposisi Sumber JM</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2" style="height: 300px;">
                        <canvas id="chartPie"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Rincian Jasa Medis per Dokter</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm text-sm" id="dataTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nama Dokter</th>
                            <th>Spesialis</th>
                            <th class="text-end">Ralan</th>
                            <th class="text-end">Ranap</th>
                            <th class="text-end">Operasi</th>
                            <th class="text-end">Penunjang</th>
                            <th class="text-end">Total JM (Rp)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">GRAND TOTAL:</td>
                            <td class="text-end" id="ft-ralan">0</td>
                            <td class="text-end" id="ft-ranap">0</td>
                            <td class="text-end" id="ft-operasi">0</td>
                            <td class="text-end" id="ft-penunjang">0</td>
                            <td class="text-end" id="ft-total">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    var myTable;
    var chartTop, chartPie;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        $('.select2-single').select2({ theme: "bootstrap-5", placeholder: "Cari...", allowClear: true });

        myTable = $('#dataTable').DataTable({
            "responsive": true, 
            "dom": 'Bfrtip',
            /*"buttons": [ 
                { extend: 'excel', className: 'btn-sm btn-success', title: 'Laporan Jasa Medis Dokter' },
                { extend: 'print', className: 'btn-sm btn-secondary' } 
            ], */
			"buttons": [ 
    { 
        extend: 'excelHtml5', 
        className: 'btn-sm btn-success', 
        title: 'Laporan Jasa Medis Dokter',
        exportOptions: {
            columns: ':visible:not(:last-child)',
            format: {
                body: function(data, row, column, node) {
                    // Bersihkan kolom index 2 s.d 6 (Kolom Angka/Rupiah)
                    if ([2, 3, 4, 5, 6].includes(column)) {
                        return typeof data === 'string' ? data.replace(/[^\d,-]/g, '').replace(',', '.') : data;
                    }
                    return data;
                }
            }
        }
    },
    {//extend: 'print', className: 'btn-sm btn-secondary' 
	extend: 'print', 
                    className: 'btn btn-secondary btn-sm', 
                    text: '<i class="fas fa-print"></i> Print',
                    exportOptions: {
                        columns: ':visible:not(:last-child)'
	}
} 
],
            "pageLength": 25,
            "columns": [
                { "data": "nm_dokter", className: "fw-bold" },
                { "data": "spesialis" },
                { "data": "Ralan", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "Ranap", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "Operasi", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "Penunjang", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "Total", className: "text-end fw-bold text-primary", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { 
                    "data": null, 
                    "className": "text-center",
                    "render": function(data, type, row) {
                        // FIX: Menghapus target="_blank" agar membuka di tab yang sama
                        var url = `laporan_tindakan.php?tgl_awal=${$('#tgl_awal').val()}&tgl_akhir=${$('#tgl_akhir').val()}&kd_dokter=${row.kd_dokter}`;
                        return `<a href="${url}" class="btn btn-sm btn-outline-primary" title="Lihat Rincian Tindakan"><i class="fas fa-arrow-circle-right"></i> Detail</a>`;
                    }
                }
            ],
            "order": [[ 6, "desc" ]] 
        });

        // Data akan dimuat saat user klik tombol Tampilkan
    });

    function loadData() {
        var params = {
            tgl_awal: $('#tgl_awal').val(),
            tgl_akhir: $('#tgl_akhir').val(),
            kd_pj: $('#kd_pj').val(),
            kd_dokter: $('#kd_dokter').val() 
        };

        $('#val-total').text('Loading...');

        $.ajax({
            url: 'api/data_jasa_medis.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                $('#val-total').text(formatRupiah(response.summary.total_jm));
                $('#val-ralan').text(formatRupiah(response.summary.jm_ralan));
                $('#val-ranap').text(formatRupiah(response.summary.jm_ranap));
                $('#val-operasi').text(formatRupiah(response.summary.jm_operasi));

                renderChartTop(response.chart);
                renderChartPie(response.summary);

                myTable.clear().rows.add(response.table).draw();
                
                $('#ft-ralan').text(formatRupiah(response.summary.jm_ralan));
                $('#ft-ranap').text(formatRupiah(response.summary.jm_ranap));
                $('#ft-operasi').text(formatRupiah(response.summary.jm_operasi));
                $('#ft-penunjang').text(formatRupiah(response.summary.jm_penunjang));
                $('#ft-total').text(formatRupiah(response.summary.total_jm));
            },
            error: function() { alert("Gagal memuat data."); }
        });
    }

    function renderChartTop(data) {
        var ctx = document.getElementById("chartTop").getContext('2d');
        if(chartTop) chartTop.destroy();

        chartTop = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: "Total Jasa Medis",
                    data: data.data,
                    backgroundColor: '#4e73df',
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    function renderChartPie(summary) {
        var ctx = document.getElementById("chartPie").getContext('2d');
        if(chartPie) chartPie.destroy();

        var labels = ['Ralan', 'Ranap', 'Operasi', 'Penunjang'];
        var data = [summary.jm_ralan, summary.jm_ranap, summary.jm_operasi, summary.jm_penunjang];

        chartPie = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: ['#1cc88a', '#36b9cc', '#e74a3b', '#f6c23e'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>