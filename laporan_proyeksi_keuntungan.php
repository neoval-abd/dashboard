<?php
/*
 * File: laporan_proyeksi_keuntungan.php
 * Tampilan: Dashboard Profit Obat (Pasien vs Jual Bebas)
 */

$page_title = "Proyeksi Keuntungan Obat";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// Ambil Data Penjamin untuk Filter
$penjabs = [];
$sql_pj = "SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab";
$res_pj = $koneksi->query($sql_pj);
while($row = $res_pj->fetch_assoc()){ $penjabs[] = $row; }
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid">
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3">Filter Data Keuangan Obat</h5>
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
                    <label class="form-label small fw-bold">Status Bayar (Pasien)</label>
                    <select class="form-select" id="status_bayar">
                        <option value="">-- Semua Status --</option>
                        <option value="Lunas">Sudah Lunas (Tunai)</option>
                        <option value="Piutang">Masih Piutang</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Penjamin (Pasien)</label>
                    <select class="form-select select2-single" id="kd_pj">
                        <option value="">-- Semua Penjamin --</option>
                        <?php foreach($penjabs as $p): ?>
                            <option value="<?php echo $p['kd_pj']; ?>"><?php echo $p['png_jawab']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100" onclick="loadData()">
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
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Omzet (Netto)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-omzet">...</div>
                            <small class="text-muted">*Sudah dikurangi retur</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-cash-register fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Modal (HPP)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-modal">...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Keuntungan</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-profit">...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-chart-line fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Profit Margin</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-margin">0%</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-percentage fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Grafik Tren Keuntungan Harian</h6>
        </div>
        <div class="card-body">
            <div class="chart-area" style="height: 350px;">
                <canvas id="chartProfit"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-success text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-user-injured me-2"></i>Penjualan Obat Pasien (Resep)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablePasien" class="table table-striped table-hover table-sm text-sm" width="100%">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>No. Rawat</th>
                                    <th>Nama Obat</th>
                                    <th>Jml</th>
                                    <th class="text-end">Tagihan</th>
                                    <th class="text-end">Laba</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-info text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-shopping-cart me-2"></i>Penjualan Obat Bebas (Apotek)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableBebas" class="table table-striped table-hover table-sm text-sm" width="100%">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>No. Nota</th>
                                    <th>Nama Obat</th>
                                    <th>Jml</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Laba</th>
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

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    var tablePasien, tableBebas, chartProfit;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        // Init Select2
        $('.select2-single').select2({
            theme: "bootstrap-5",
            placeholder: "Pilih Penjamin",
            allowClear: true
        });

        // Init DataTables
        tablePasien = $('#tablePasien').DataTable({
            "responsive": true, "pageLength": 10, "dom": 'Bfrtip',
            //"buttons": [ {extend: 'excel', title: 'Laporan Obat Pasien', className: 'btn-sm btn-success'} ],
            buttons: [ {
                extend: 'excelHtml5', 
                title: 'Laporan Pemberian Obat Ke Pasien', // Sesuaikan judul per tabel (Obat Pasien / Obat Bebas)
                className: 'btn-sm btn-success',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function(data, row, column, node) {
                            // 1. FORMAT RUPIAH (Kolom 4 & 5)
                            if (column === 4 || column === 5) {
                                return typeof data === 'string' ? data.replace(/[^\d,-]/g, '').replace(',', '.') : data;
                            }

                            // 2. BERSIHKAN HTML (Untuk kolom No. Rawat/Nota yang ada <br> dan <small>)
                            if (typeof data === 'string') {
                                // Ganti tag <br> dengan tanda strip " - " agar teks tidak menempel
                                let text = data.replace(/<br\s*\/?>/gi, " - ");
                                // Hapus semua tag HTML lain (<small>, <span>, dll)
                                return text.replace(/<[^>]+>/g, "").trim();
                            }

                            return data;
                        }
                    }
                }
            } ],
			"order": [[ 0, "desc" ]],
            "columns": [
                { "data": "tanggal", render: function(d){ return d.split(' ')[0]; } },
                { "data": "no_rawat", render: function(d,t,r){ return '<small>'+d+'<br>'+r.nm_pasien+'</small>'; } },
                { "data": "nama_brng" },
                { "data": "jml", className: "text-center" },
                { "data": "subtotal_jual", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "profit", className: "text-end fw-bold text-success", render: $.fn.dataTable.render.number('.', ',', 0, '') }
            ]
        });

        tableBebas = $('#tableBebas').DataTable({
            "responsive": true, "pageLength": 10, "dom": 'Bfrtip',
            //"buttons": [ {extend: 'excel', title: 'Laporan Obat Bebas', className: 'btn-sm btn-info'} ],
            buttons: [ {
                extend: 'excelHtml5', 
                title: 'Laporan Obat Bebas', // Sesuaikan judul per tabel (Obat Pasien / Obat Bebas)
                className: 'btn-sm btn-success',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function(data, row, column, node) {
                            // 1. FORMAT RUPIAH (Kolom 4 & 5)
                            if (column === 4 || column === 5) {
                                return typeof data === 'string' ? data.replace(/[^\d,-]/g, '').replace(',', '.') : data;
                            }

                            // 2. BERSIHKAN HTML (Untuk kolom No. Rawat/Nota yang ada <br> dan <small>)
                            if (typeof data === 'string') {
                                // Ganti tag <br> dengan tanda strip " - " agar teks tidak menempel
                                let text = data.replace(/<br\s*\/?>/gi, " - ");
                                // Hapus semua tag HTML lain (<small>, <span>, dll)
                                return text.replace(/<[^>]+>/g, "").trim();
                            }

                            return data;
                        }
                    }
                }
            } ],
			"order": [[ 0, "desc" ]],
            "columns": [
                { "data": "tanggal" },
                { "data": "nota_jual", render: function(d,t,r){ return '<small>'+d+'<br>'+(r.pembeli || '-')+'</small>'; } },
                { "data": "nama_brng" },
                { "data": "jumlah", className: "text-center" },
                { "data": "subtotal_jual", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "profit", className: "text-end fw-bold text-success", render: $.fn.dataTable.render.number('.', ',', 0, '') }
            ]
        });

        // Data akan dimuat saat user klik tombol Tampilkan
    });

    function loadData() {
        var params = {
            tgl_awal: $('#tgl_awal').val(),
            tgl_akhir: $('#tgl_akhir').val(),
            status_bayar: $('#status_bayar').val(),
            kd_pj: $('#kd_pj').val()
        };

        $.ajax({
            url: 'api/data_proyeksi_keuntungan.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                // 1. Update Summary Cards
                $('#val-omzet').text(formatRupiah(response.summary.omzet));
                $('#val-modal').text(formatRupiah(response.summary.modal));
                $('#val-profit').text(formatRupiah(response.summary.profit));
                
                let margin = 0;
                if(response.summary.omzet > 0) {
                    margin = (response.summary.profit / response.summary.omzet) * 100;
                }
                $('#val-margin').text(margin.toFixed(2) + '%');

                // 2. Update Tables
                tablePasien.clear().rows.add(response.pasien).draw();
                tableBebas.clear().rows.add(response.bebas).draw();

                // 3. Update Chart
                renderChart(response.chart);
            },
            error: function() { alert("Gagal memuat data."); }
        });
    }

    function renderChart(data) {
        var ctx = document.getElementById("chartProfit").getContext('2d');
        if(chartProfit) chartProfit.destroy();

        chartProfit = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: "Omzet",
                        data: data.omzet,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        tension: 0.3, fill: false
                    },
                    {
                        label: "Keuntungan",
                        data: data.profit,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        tension: 0.3, fill: true
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: { callbacks: { label: function(c) { return c.dataset.label + ': ' + formatRupiah(c.raw); } } }
                },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>