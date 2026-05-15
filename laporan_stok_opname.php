<?php
/*
 * File: laporan_stok_opname.php
 * Tampilan: Dashboard Hasil Stok Opname (Kerugian & Surplus).
 */

$page_title = "Laporan Hasil Stok Opname";
require_once('includes/header.php');

// Ambil daftar bangsal/gudang untuk filter
$bangsals = [];
$sql_bangsal = "SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal";
$res_bangsal = $koneksi->query($sql_bangsal);
while($row = $res_bangsal->fetch_assoc()){
    $bangsals[] = $row;
}

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid">
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3">Filter Laporan Opname</h5>
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Lokasi / Depo</label>
                    <select class="form-select select2-single" id="kd_bangsal">
                        <option value="">-- Semua Lokasi --</option>
                        <?php foreach($bangsals as $b): ?>
                            <option value="<?php echo $b['kd_bangsal']; ?>"><?php echo $b['nm_bangsal']; ?></option>
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
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Nilai Selisih Kurang (Hilang)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-hilang">Rp 0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-minus-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Nilai Selisih Lebih (Surplus)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-lebih">Rp 0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-plus-circle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Net Finansial (Lebih - Kurang)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-net">Rp 0</div>
                            <small id="txt-item-selisih" class="text-muted">0 Item Selisih</small>
                        </div>
                        <div class="col-auto"><i class="fas fa-balance-scale fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">Top 10 Kerugian Stok (Barang Hilang)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-bar" style="height: 350px;">
                        <canvas id="chartTopRugi"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tren Nilai Opname per Tanggal</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 350px;">
                        <canvas id="chartTren"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Rincian Hasil Stok Opname</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm text-sm" id="dataTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Tanggal</th>
                            <th>Lokasi</th>
                            <th>Nama Barang</th>
                            <th>Stok Sys</th>
                            <th>Real</th>
                            <th>Selisih</th>
                            <th>Harga</th>
                            <th class="text-end">Nilai Hilang (Rp)</th>
                            <th class="text-end">Nilai Lebih (Rp)</th>
                            <th>Ket</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    var myTable;
    var chartTopRugi, chartTren;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        // Init Select2
        $('.select2-single').select2({
            theme: "bootstrap-5",
            placeholder: "Pilih Lokasi...",
            allowClear: true
        });

        // Init DataTable
        myTable = $('#dataTable').DataTable({
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [
                { 
                    extend: 'excelHtml5', 
                    className: 'btn btn-success btn-sm', 
                    text: '<i class="fas fa-file-excel"></i> Excel', 
                    title: 'Laporan Stok Opname',
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function(data, row, column, node) {
                                var str = (data === null || data === undefined) ? '' : String(data);

                                // 1. KOLOM ANGKA & SELISIH (Index 5, 6, 7, 8)
                                // Col 5: Selisih (ada <span>), Col 6-8: Rupiah (ada titik)
                                if (column === 5 || column === 6 || column === 7 || column === 8) {
                                    // Hapus tag HTML dulu (untuk membuang <span> pada kolom selisih)
                                    let clean = str.replace(/<[^>]+>/g, "");
                                    // Bersihkan format angka (hapus titik ribuan, ganti koma jadi titik)
                                    return clean.replace(/[^\d,-]/g, '').replace(',', '.');
                                }

                                // 2. KOLOM NAMA BARANG (Index 2)
                                // Format aslinya: <b>Nama</b><br><small>Kode</small>
                                if (column === 2 && str.indexOf('<') > -1) {
                                    // Ganti <br> dengan " - " agar Nama dan Kode terpisah rapi
                                    // Lalu hapus sisa tag HTML
                                    return str.replace(/<br\s*\/?>/gi, " - ").replace(/<[^>]+>/g, "").trim();
                                }

                                // Pembersihan Umum untuk kolom lain yang mungkin ada HTML
                                if (str.indexOf('<') > -1) {
                                     return str.replace(/<[^>]+>/g, "").trim();
                                }

                                return data;
                            }
                        }
                    }
                },
                { extend: 'pdfHtml5', className: 'btn btn-danger btn-sm', text: '<i class="fas fa-file-pdf"></i> PDF', orientation: 'landscape' },
                { extend: 'print', className: 'btn btn-secondary btn-sm', text: '<i class="fas fa-print"></i> Print' }
            ],
            "columns": [
                { "data": "tanggal" },
                { "data": "nm_bangsal" },
                { "data": "nama_brng", render: function(data, type, row) { return '<b>'+data+'</b><br><small>'+row.kode_brng+'</small>'; } },
                { "data": "stok_sistem", className: "text-center" },
                { "data": "stok_real", className: "text-center fw-bold" },
                { "data": "selisih", className: "text-center fw-bold", render: function(data, type, row) {
                    // Hitung selisih secara eksplisit: real - sistem
                    // Field 'selisih' di DB SIMKES disimpan sebagai nilai absolut,
                    // sehingga tidak bisa diandalkan tanda positif/negatifnya.
                    let sys  = parseFloat(row.stok_sistem) || 0;
                    let real = parseFloat(row.stok_real)   || 0;
                    let diff = real - sys;
                    if (diff < 0) return '<span class="text-danger">' + diff + '</span>';
                    if (diff > 0) return '<span class="text-success">+' + diff + '</span>';
                    return '0';
                }},
                { "data": "h_beli", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "nomihilang", className: "text-end text-danger", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "nomilebih", className: "text-end text-success", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { "data": "keterangan" }
            ],
            "order": [[ 0, "desc" ]],
            "pageLength": 25
        });

        // Data akan dimuat saat user klik tombol Cari
    });

    function loadData() {
        var tglAwal = $('#tgl_awal').val();
        var tglAkhir = $('#tgl_akhir').val();
        var bangsal = $('#kd_bangsal').val();

        $.ajax({
            url: 'api/data_stok_opname.php',
            type: 'GET',
            data: { tgl_awal: tglAwal, tgl_akhir: tglAkhir, kd_bangsal: bangsal },
            dataType: 'json',
            success: function(response) {
                // 1. Update Summary Cards
                $('#val-hilang').text(formatRupiah(response.summary.total_nilai_hilang));
                $('#val-lebih').text(formatRupiah(response.summary.total_nilai_lebih));
                $('#val-net').text(formatRupiah(response.summary.net_selisih));
                $('#txt-item-selisih').text(response.summary.total_item_selisih + ' Item Selisih');

                // 2. Update Charts
                renderChartTop(response.chart_top);
                renderChartTren(response.chart_tren);

                // 3. Update Table
                myTable.clear();
                myTable.rows.add(response.detail);
                myTable.draw();
            },
            error: function() { alert("Gagal memuat data opname."); }
        });
    }

    function renderChartTop(data) {
        var ctx = document.getElementById("chartTopRugi").getContext('2d');
        if(chartTopRugi) chartTopRugi.destroy();

        var labels = data.map(item => item.nama);
        var values = data.map(item => item.nilai);

        chartTopRugi = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: "Nilai Kerugian (Rp)",
                    data: values,
                    backgroundColor: '#e74a3b',
                    borderColor: '#e74a3b',
                    borderWidth: 1,
                    borderRadius: 5
                }],
            },
            options: {
                indexAxis: 'y', // Horizontal Bar
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) { return 'Rugi: ' + formatRupiah(c.raw); } } }
                },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    function renderChartTren(data) {
        var ctx = document.getElementById("chartTren").getContext('2d');
        if(chartTren) chartTren.destroy();

        var labels = Object.keys(data); // Tanggal
        var dataHilang = labels.map(d => data[d].hilang);
        var dataLebih = labels.map(d => data[d].lebih);

        chartTren = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Kerugian (Hilang)",
                        data: dataHilang,
                        borderColor: '#e74a3b',
                        backgroundColor: 'rgba(231, 74, 59, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: "Surplus (Lebih)",
                        data: dataLebih,
                        borderColor: '#1cc88a',
                        backgroundColor: 'rgba(28, 200, 138, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ],
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