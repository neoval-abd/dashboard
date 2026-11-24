<?php
/*
 * File: laporan_proyeksi_keuntungan.php
 * Tampilan: Dashboard Profit Obat (Pasien Resep, Penjualan Bebas & Retur Obat)
 */

$page_title = "Proyeksi Keuntungan Obat";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal  = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
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
    
    <!-- Filter Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3"><i class="fas fa-filter me-2"></i>Filter Data Keuangan Obat</h5>
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

    <!-- 6 KPI Summary Cards: Pasien, Bebas, Retur, Modal, Keuntungan, Profit Margin -->
    <div class="row mb-4">
        <!-- 1. Penjualan Obat Pasien (Resep) -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1" style="font-size:0.72rem;">Obat Pasien (Resep)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-omzet-pasien">...</div>
                            <small class="text-muted" style="font-size:0.68rem;">Ralan &amp; Ranap</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-prescription-bottle-alt fa-2x text-primary opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Penjualan Obat Bebas (Apotek) -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1" style="font-size:0.72rem;">Penjualan Bebas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-omzet-bebas">...</div>
                            <small class="text-muted" style="font-size:0.68rem;">Penjualan Apotek</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-shopping-cart fa-2x text-info opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Retur Obat -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-danger shadow h-100 py-2" style="border-left: 0.25rem solid #e74a3b !important;">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1" style="font-size:0.72rem;">Retur Obat</div>
                            <div class="h5 mb-0 font-weight-bold text-danger" id="val-retur">- Rp 0</div>
                            <small class="text-muted" style="font-size:0.68rem;">Pengurang Nilai Omzet</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-undo-alt fa-2x text-danger opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Total Modal (HPP) -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1" style="font-size:0.72rem;">Total Modal (HPP)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-modal">...</div>
                            <small class="text-muted" style="font-size:0.68rem;">Biaya Beli Bersih</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-boxes fa-2x text-warning opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Total Keuntungan -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1" style="font-size:0.72rem;">Total Keuntungan</div>
                            <div class="h5 mb-0 font-weight-bold text-success" id="val-profit">...</div>
                            <small class="text-muted" style="font-size:0.68rem;">Laba Bersih Setelah Retur</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-chart-line fa-2x text-success opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Profit Margin -->
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-left-secondary shadow h-100 py-2" style="border-left: 0.25rem solid #6c757d !important;">
                <div class="card-body py-2">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1" style="font-size:0.72rem;">Profit Margin</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-margin">0%</div>
                            <small class="text-muted" id="val-netto-note" style="font-size:0.68rem;">Netto: Rp 0</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-percentage fa-2x text-secondary opacity-50"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik Tren Harian -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-area me-2"></i>Grafik Tren Keuangan &amp; Keuntungan Harian</h6>
        </div>
        <div class="card-body">
            <div class="chart-area" style="height: 350px;">
                <canvas id="chartProfit"></canvas>
            </div>
        </div>
    </div>

    <!-- Area Tabel Detail: Tabbed Interface untuk Pasien, Bebas, dan Retur -->
    <div class="card shadow mb-4">
        <div class="card-header py-2 bg-light border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-pills card-header-pills" id="obatTab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold text-sm py-1 px-3" id="tab-pasien-btn" data-bs-toggle="pill" data-bs-target="#tab-pasien" type="button" role="tab">
                        <i class="fas fa-prescription-bottle-alt me-1 text-primary"></i> Penjualan Obat Pasien (Resep)
                        <span class="badge bg-primary ms-1" id="badge-count-pasien">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-sm py-1 px-3" id="tab-bebas-btn" data-bs-toggle="pill" data-bs-target="#tab-bebas" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-1 text-info"></i> Penjualan Obat Bebas (Apotek)
                        <span class="badge bg-info ms-1" id="badge-count-bebas">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-sm py-1 px-3 text-danger" id="tab-retur-btn" data-bs-toggle="pill" data-bs-target="#tab-retur" type="button" role="tab">
                        <i class="fas fa-undo-alt me-1 text-danger"></i> Data Retur Obat
                        <span class="badge bg-danger ms-1" id="badge-count-retur">0</span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="obatTabContent">
                <!-- Tab Pasien -->
                <div class="tab-pane fade show active" id="tab-pasien" role="tabpanel">
                    <div class="table-responsive">
                        <table id="tablePasien" class="table table-striped table-hover table-sm text-sm" width="100%">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>No. Rawat &amp; Pasien</th>
                                    <th>Penjamin</th>
                                    <th>Nama Obat</th>
                                    <th class="text-center">Jml</th>
                                    <th class="text-end">Tagihan</th>
                                    <th class="text-end">Laba</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Bebas -->
                <div class="tab-pane fade" id="tab-bebas" role="tabpanel">
                    <div class="table-responsive">
                        <table id="tableBebas" class="table table-striped table-hover table-sm text-sm" width="100%">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>No. Nota &amp; Pembeli</th>
                                    <th>Nama Obat</th>
                                    <th class="text-center">Jml</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Laba</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Retur -->
                <div class="tab-pane fade" id="tab-retur" role="tabpanel">
                    <div class="table-responsive">
                        <table id="tableRetur" class="table table-striped table-hover table-sm text-sm" width="100%">
                            <thead>
                                <tr>
                                    <th>Tanggal Retur</th>
                                    <th>No. Retur &amp; Pasien / Pembeli</th>
                                    <th>Nama Obat</th>
                                    <th class="text-center">Jml Retur</th>
                                    <th class="text-end">Nilai Retur</th>
                                    <th class="text-end">Modal Retur</th>
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
    var tablePasien, tableBebas, tableRetur, chartProfit;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka || 0);
    }

    function safeExportNumber(val) {
        if (typeof dtExportNumber === 'function') return dtExportNumber(val);
        return typeof val === 'string' ? val.replace(/[^\d,-]/g, '').replace(',', '.') : val;
    }

    function safeExportClean(val) {
        if (typeof dtExportCleanText === 'function') return dtExportCleanText(val);
        return typeof val === 'string' ? val.replace(/<br\s*[\/]?>/gi, ' - ').replace(/<[^>]+>/g, '').trim() : val;
    }

    $(document).ready(function() {
        // Init Select2
        $('.select2-single').select2({
            theme: "bootstrap-5",
            placeholder: "Pilih Penjamin",
            allowClear: true
        });

        // 1. Init DataTable Pasien (Resep)
        tablePasien = $('#tablePasien').DataTable({
            "responsive": true,
            "pageLength": 10,
            "dom": 'Bfrtip',
            buttons: [ {
                extend: 'excelHtml5', 
                title: 'Laporan Pemberian Obat Ke Pasien (Resep)',
                className: 'btn-sm btn-success',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function(data, row, column, node) {
                            if (column === 5 || column === 6) {
                                return safeExportNumber(data);
                            }
                            return safeExportClean(data);
                        }
                    }
                }
            } ],
            "order": [[ 0, "desc" ]],
            "columns": [
                { "data": "tanggal", render: function(d){ return d ? d.split(' ')[0] : ''; } },
                { "data": "no_rawat", render: function(d, t, r){ return '<small>' + d + '<br><strong>' + (r.nm_pasien || '-') + '</strong></small>'; } },
                { "data": "png_jawab", render: function(d){ return '<span class="badge bg-secondary">' + (d || '-') + '</span>'; } },
                { "data": "nama_brng" },
                { "data": "jml", className: "text-center" },
                { "data": "subtotal_jual", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "profit", className: "text-end fw-bold text-success", render: $.fn.dataTable.render.number('.', ',', 0, '') }
            ]
        });

        // 2. Init DataTable Penjualan Bebas (Apotek)
        tableBebas = $('#tableBebas').DataTable({
            "responsive": true,
            "pageLength": 10,
            "dom": 'Bfrtip',
            buttons: [ {
                extend: 'excelHtml5', 
                title: 'Laporan Penjualan Obat Bebas (Apotek)',
                className: 'btn-sm btn-info text-white',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function(data, row, column, node) {
                            if (column === 4 || column === 5) {
                                return safeExportNumber(data);
                            }
                            return safeExportClean(data);
                        }
                    }
                }
            } ],
            "order": [[ 0, "desc" ]],
            "columns": [
                { "data": "tanggal" },
                { "data": "nota_jual", render: function(d, t, r){ return '<small>' + d + '<br><strong>' + (r.pembeli || '-') + '</strong></small>'; } },
                { "data": "nama_brng" },
                { "data": "jumlah", className: "text-center" },
                { "data": "subtotal_jual", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "profit", className: "text-end fw-bold text-success", render: $.fn.dataTable.render.number('.', ',', 0, '') }
            ]
        });

        // 3. Init DataTable Retur Obat
        tableRetur = $('#tableRetur').DataTable({
            "responsive": true,
            "pageLength": 10,
            "dom": 'Bfrtip',
            buttons: [ {
                extend: 'excelHtml5', 
                title: 'Laporan Retur Penjualan Obat',
                className: 'btn-sm btn-danger',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function(data, row, column, node) {
                            if (column === 4 || column === 5) {
                                return safeExportNumber(data);
                            }
                            return safeExportClean(data);
                        }
                    }
                }
            } ],
            "order": [[ 0, "desc" ]],
            "columns": [
                { "data": "tanggal" },
                { 
                    "data": "no_retur_jual", 
                    render: function(d, t, r) { 
                        var pasienText = r.nm_pasien && r.nm_pasien !== '-' ? r.nm_pasien : 'Umum / Toko';
                        if (r.no_rkm_medis) pasienText = r.no_rkm_medis + ' - ' + pasienText;
                        return '<small>' + d + '<br><span class="text-muted">' + pasienText + '</span></small>'; 
                    } 
                },
                { "data": "nama_brng" },
                { "data": "jml_retur", className: "text-center" },
                { "data": "subtotal_retur", className: "text-end fw-bold text-danger", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "modal_retur", className: "text-end", render: $.fn.dataTable.render.number('.', ',', 0, '') }
            ]
        });

        // Tab switch: sesuaikan lebar kolom DataTables
        $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function(e) {
            if ($.fn.dataTable) {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
            }
        });

        // Otomatis muat data pertama kali
        loadData();
    });

    function loadData() {
        var params = {
            tgl_awal: $('#tgl_awal').val(),
            tgl_akhir: $('#tgl_akhir').val(),
            status_bayar: $('#status_bayar').val(),
            kd_pj: $('#kd_pj').val()
        };

        var $btn = $('#filterForm button');
        var origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Memuat...');

        $.ajax({
            url: 'api/data_proyeksi_keuntungan.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                var sum = response.summary || {};

                // 1. Update 6 KPI Summary Cards
                var omzetPasien = sum.omzet_pasien || 0;
                var omzetBebas  = sum.omzet_bebas || 0;
                var omzetRetur  = sum.omzet_retur !== undefined ? sum.omzet_retur : (sum.retur || 0);
                var totalModal  = sum.modal || 0;
                var totalProfit = sum.profit || 0;
                var totalOmzet  = sum.omzet || (omzetPasien + omzetBebas - omzetRetur);

                $('#val-omzet-pasien').text(formatRupiah(omzetPasien));
                $('#val-omzet-bebas').text(formatRupiah(omzetBebas));
                $('#val-retur').text(omzetRetur > 0 ? ('- ' + formatRupiah(omzetRetur)) : formatRupiah(0));
                $('#val-modal').text(formatRupiah(totalModal));
                $('#val-profit').text(formatRupiah(totalProfit));

                var margin = sum.margin !== undefined ? sum.margin : 0;
                if (margin === 0 && totalOmzet > 0) {
                    margin = (totalProfit / totalOmzet) * 100;
                }
                $('#val-margin').text(Number(margin).toFixed(2) + '%');
                $('#val-netto-note').text('Netto: ' + formatRupiah(totalOmzet));

                // 2. Update Tab Badges Count
                var dataPasien = response.pasien || [];
                var dataBebas  = response.bebas || [];
                var dataRetur  = response.retur || [];

                $('#badge-count-pasien').text(dataPasien.length);
                $('#badge-count-bebas').text(dataBebas.length);
                $('#badge-count-retur').text(dataRetur.length);

                // 3. Update DataTables
                tablePasien.clear().rows.add(dataPasien).draw();
                tableBebas.clear().rows.add(dataBebas).draw();
                tableRetur.clear().rows.add(dataRetur).draw();

                // 4. Update Chart
                renderChart(response.chart || {});
            },
            error: function() { 
                alert("Gagal memuat data keuangan obat. Silakan periksa koneksi atau coba lagi."); 
            },
            complete: function() {
                $btn.prop('disabled', false).html(origHtml);
            }
        });
    }

    function renderChart(data) {
        var canvas = document.getElementById("chartProfit");
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        if (chartProfit) chartProfit.destroy();

        var labels        = data.labels || [];
        var dsOmzetPasien = data.omzet_pasien || [];
        var dsOmzetBebas  = data.omzet_bebas || [];
        var dsOmzetRetur  = data.omzet_retur || [];
        var dsProfit      = data.profit || [];

        chartProfit = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Omzet Resep Pasien",
                        data: dsOmzetPasien,
                        borderColor: '#4e73df',
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: "Penjualan Bebas",
                        data: dsOmzetBebas,
                        borderColor: '#36b9cc',
                        backgroundColor: 'rgba(54, 185, 204, 0.05)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: "Retur Obat",
                        data: dsOmzetRetur,
                        borderColor: '#e74a3b',
                        backgroundColor: 'rgba(231, 74, 59, 0.05)',
                        tension: 0.3,
                        fill: false
                    },
                    {
                        label: "Total Keuntungan (Netto)",
                        data: dsProfit,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.12)',
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: { 
                        callbacks: { 
                            label: function(c) { 
                                return c.dataset.label + ': ' + formatRupiah(c.raw); 
                            } 
                        } 
                    }
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) {
                                return formatRupiah(val);
                            }
                        }
                    } 
                }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
