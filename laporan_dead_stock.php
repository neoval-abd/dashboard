<?php
$page_title = "Analisa Dead Stock (Farmasi)";
require_once('includes/header.php');

// Ambil daftar bangsal untuk filter
$bangsals = [];
$sql_bangsal = "SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal";
$res_bangsal = $koneksi->query($sql_bangsal);
if($res_bangsal) {
    while($row = $res_bangsal->fetch_assoc()){
        $bangsals[] = $row;
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-skull-crossbones text-danger"></i> Analisa Dead Stock & Slow Moving</h1>
    </div>
    
    <div class="alert alert-warning py-2 shadow-sm mb-4" role="alert">
        <i class="fas fa-info-circle me-1"></i> <strong>Peringatan!</strong> Laporan ini menunjukkan obat/alkes yang masih memiliki stok fisik, namun <strong>tidak memiliki satupun riwayat pengeluaran (transaksi keluar)</strong> dalam rentang waktu yang diatur.
    </div>

    <!-- Filter Card -->
    <div class="card shadow mb-4 border-left-danger">
        <div class="card-body">
            <form id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Rentang Waktu Mati (Tidak Ada Transaksi)</label>
                    <select class="form-select" id="rentang_waktu" onchange="toggleCustomDate()">
                        <option value="1bulan">1 Bulan Terakhir</option>
                        <option value="3bulan" selected>3 Bulan Terakhir</option>
                        <option value="6bulan">6 Bulan Terakhir</option>
                        <option value="1tahun">> 1 Tahun Terakhir</option>
                        <option value="custom">Custom (Pilih Tanggal)</option>
                    </select>
                </div>
                
                <!-- Custom Date (Hidden by default) -->
                <div class="col-md-2 custom-date-container" style="display:none;">
                    <label class="form-label font-weight-bold">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" value="<?php echo date('Y-m-d', strtotime('-3 months')); ?>">
                </div>
                <div class="col-md-2 custom-date-container" style="display:none;">
                    <label class="form-label font-weight-bold">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Lokasi / Depo Gudang</label>
                    <select class="form-select" id="kd_bangsal">
                        <option value="">-- Semua Lokasi / Depo --</option>
                        <?php foreach($bangsals as $b): ?>
                            <option value="<?= $b['kd_bangsal']; ?>"><?= $b['nm_bangsal']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label font-weight-bold">Pencarian Obat</label>
                    <input type="text" class="form-control" id="keyword" placeholder="Nama/Kode...">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger w-100" onclick="loadData()"><i class="fas fa-search me-1"></i> Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Widget KPI -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Item Dead Stock</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-item">0</span> <small class="text-xs">Macam</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-box-open fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Volume Stok Fisik Menganggur</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800"><span id="kpi-vol">0</span> <small class="text-xs">Satuan</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cubes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2 bg-gradient-light">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Estimasi Nilai Aset Mengendap (HPP)</div>
                            <div class="h3 mb-0 font-weight-bold text-gray-800" id="kpi-aset">Rp 0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-primary" style="opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Top 10 Aset Terbesar -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">Top 10 Aset Terbesar yang Mengendap</h6>
        </div>
        <div class="card-body">
            <canvas id="barChartDead" style="min-height: 350px; max-height: 350px;"></canvas>
        </div>
    </div>

    <!-- DataTables -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-danger">Rincian Obat Dead / Slow Moving</h6>
            <span class="text-muted small">Tanpa Transaksi Keluar sejak: <b id="lbl-cutoff">...</b></span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="dataTableDead" width="100%" cellspacing="0">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th>Kode Obat</th>
                            <th>Nama Obat / Barang</th>
                            <th>Lokasi (Depo)</th>
                            <th class="text-center">Sisa Stok</th>
                            <th class="text-end">HPP Dasar</th>
                            <th class="text-end">Total Nilai Aset (Rp)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Riwayat Obat -->
<div class="modal fade" id="modalRiwayat" tabindex="-1" aria-labelledby="modalRiwayatLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalRiwayatLabel"><i class="fas fa-history me-1"></i> Riwayat Transaksi Obat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="card mb-3 shadow-sm border-left-info">
                    <div class="card-body py-2">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <small class="text-muted d-block mb-1">Obat / Barang:</small>
                                <strong id="det_nama_brng" class="text-primary fs-5">-</strong>
                                <span class="badge bg-secondary ms-2" id="det_kode_brng">-</span>
                            </div>
                            <div class="col-md-3 border-start">
                                <small class="text-muted d-block mb-1">Lokasi Depo:</small>
                                <strong id="det_nm_bangsal">-</strong>
                            </div>
                            <div class="col-md-4 border-start">
                                <small class="text-muted d-block mb-1">Rentang Riwayat:</small>
                                <strong id="det_rentang_tgl">-</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive bg-white p-3 rounded shadow-sm">
                    <table class="table table-bordered table-sm table-striped" id="tableDetailRiwayat" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Tgl / Jam</th>
                                <th>Lokasi</th>
                                <th>Awal</th>
                                <th class="text-success"><i class="fas fa-arrow-down"></i> Masuk</th>
                                <th class="text-danger"><i class="fas fa-arrow-up"></i> Keluar</th>
                                <th>Akhir</th>
                                <th>Keterangan (No. Faktur)</th>
                                <th>Petugas</th>
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
    var tableDead;
    var tableRiwayat;
    var barChartInstance;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    function toggleCustomDate() {
        var val = $('#rentang_waktu').val();
        if(val === 'custom') {
            $('.custom-date-container').show();
        } else {
            $('.custom-date-container').hide();
        }
    }

    $(document).ready(function() {
        tableRiwayat = $('#tableDetailRiwayat').DataTable({
            "responsive": true,
            "pageLength": 10,
            "ordering": false,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
            "columns": [
                { "data": null, render: function(d,t,r){ return r.tanggal + ' ' + r.jam; } },
                { "data": "nm_bangsal", "className":"small" },
                { "data": "stok_awal", "className": "text-center text-muted" },
                { "data": "masuk", "className": "text-center fw-bold text-success", render: function(d){ return d>0 ? "+"+d : d; } },
                { "data": "keluar", "className": "text-center fw-bold text-danger", render: function(d){ return d>0 ? "-"+d : d; } },
                { "data": "stok_akhir", "className": "text-center fw-bold" },
                { "data": null, "className":"small", render: function(d,t,r){ return r.posisi + " - " + r.keterangan + " <br><span class='text-muted'>(" + r.no_faktur + ")</span>"; } },
                { "data": "petugas", "className":"small" }
            ]
        });

        tableDead = $('#dataTableDead').DataTable({
            "responsive": true,
            "pageLength": 25,
            "order": [[ 5, "desc" ]], // Urutkan dari nominal aset terbesar
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'excelHtml5',
                    title: 'Laporan Dead Stock Farmasi',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { 
                        columns: ':visible',
                        format: {
                            // Agar excel mendeteksi angka
                            body: function(data, row, column, node) {
                                // Kolom Stok (3) dan Nilai uang (4, 5)
                                if (column === 3) {
                                    return (data === null || data === undefined) ? '' : String(data).replace(/[^\d.-]/g, '');
                                }
                                if (column === 4 || column === 5) {
                                    return (data === null || data === undefined) ? '' : String(data).replace(/[^\d,-]/g, '').replace(',', '.');
                                }
                                // Bersihkan HTML jika ada
                                var strData = String(data);
                                if (strData.indexOf('<') > -1) {
                                    return strData.replace(/<[^>]+>/g, "").trim();
                                }
                                return data;
                            }
                        }
                    }
                },
                {
                    extend: 'print',
                    title: 'Laporan Dead Stock Farmasi',
                    className: 'btn btn-secondary btn-sm',
                    exportOptions: { columns: ':visible' }
                }
            ],
            "columns": [
                { "data": "kode_brng" },
                { "data": "nama_brng", "className": "fw-bold" },
                { "data": "nm_bangsal", "className": "small text-muted" },
                { 
                    "data": "stok_val", 
                    "className": "text-center fw-bold text-danger",
                    // Simpan nilai asli di attribute untuk sorting/export yg aman jika perlu, 
                    // tapi kita pakai render data numeric
                    render: function(data, type, row) {
                        if (type === 'display') return data;
                        return data;
                    }
                },
                { 
                    "data": "hpp_val", 
                    "className": "text-end",
                    render: function(data, type, row) {
                        return type === 'display' ? formatRupiah(data) : data;
                    }
                },
                { 
                    "data": "aset_val", 
                    "className": "text-end fw-bold text-primary bg-light",
                    render: function(data, type, row) {
                        return type === 'display' ? formatRupiah(data) : data;
                    }
                },
                {
                    "data": null,
                    "className": "text-center",
                    "orderable": false,
                    "render": function(data, type, row) {
                        return `<button class="btn btn-sm btn-info text-white" onclick="lihatRiwayat('${row.kode_brng}', '${row.nama_brng}', '${row.kd_bangsal}', '${row.nm_bangsal}')" title="Lihat Riwayat Transaksi"><i class="fas fa-history"></i></button>`;
                    }
                }
            ]
        });

        // Data akan dimuat saat user klik tombol Tampilkan
    });

    function loadData() {
        var rentang = $('#rentang_waktu').val();
        var tgl1 = $('#tgl_awal').val();
        var tgl2 = $('#tgl_akhir').val();
        var bangsal = $('#kd_bangsal').val();
        var search = $('#keyword').val();

        $('#kpi-item, #kpi-vol, #kpi-aset').text('...');
        $('#lbl-cutoff').text('Loading...');

        $.ajax({
            url: 'api/data_dead_stock.php',
            type: 'GET',
            data: { 
                rentang: rentang, 
                tgl_awal: tgl1, 
                tgl_akhir: tgl2,
                kd_bangsal: bangsal,
                keyword: search 
            },
            dataType: 'json',
            success: function(res) {
                // Update KPI Cards
                $('#kpi-item').text(res.summary.total_item);
                // Formatting custom decimal / ribuan for volume
                $('#kpi-vol').text(new Intl.NumberFormat('id-ID').format(res.summary.total_stok));
                $('#kpi-aset').text(formatRupiah(res.summary.total_aset));
                
                $('#lbl-cutoff').text(res.summary.cutoff_start + ' s.d. ' + res.summary.cutoff_end);

                // Update Table
                tableDead.clear();
                tableDead.rows.add(res.data);
                tableDead.draw();

                renderChart(res.chart);
            },
            error: function(err) {
                alert("Gagal memuat data Dead Stock.");
                console.error(err);
            }
        });
    }

    // Fungsi Render Chart Top 10
    function renderChart(chartData) {
        if (barChartInstance) { barChartInstance.destroy(); }
        
        var ctxBar = document.getElementById('barChartDead').getContext('2d');
        barChartInstance = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Nilai Aset Mengendap (Rp)',
                        data: chartData.data,
                        backgroundColor: 'rgba(231, 74, 59, 0.8)', // Danger color
                        borderColor: '#e74a3b',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatRupiah(context.raw);
                            }
                        }
                    },
                    legend: {
                        display: false // Sembunyikan legenda karena cuma 1 dataset
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatRupiah(value);
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }

    // Fungsi Melihat Riwayat Detail Obat (terhubung ke api_riwayat_obat.php)
    var isCheckingRiwayat = false;
    function lihatRiwayat(kode_brng, nama_brng, kd_bangsal, nm_bangsal) {
        if(isCheckingRiwayat) return;
        
        // Ambil range pencarian dari input dead stock untuk sinkronisasi
        var rentang = $('#rentang_waktu').val();
        var tgl1 = $('#tgl_awal').val();
        var tgl2 = $('#tgl_akhir').val();
        
        // Format label modal
        $('#det_kode_brng').text(kode_brng);
        $('#det_nama_brng').text(nama_brng);
        $('#det_nm_bangsal').text(nm_bangsal || 'Gudang Pusat');
        $('#det_rentang_tgl').html('<i class="fas fa-spinner fa-spin"></i>');
        
        tableRiwayat.clear().draw();
        var modal = new bootstrap.Modal(document.getElementById('modalRiwayat'));
        modal.show();

        isCheckingRiwayat = true;
        
        // Panggil endpoint kita untuk cari tgl cutoff yang sebenarnya
        $.ajax({
            url: 'api/data_dead_stock.php',
            type: 'GET',
            data: { rentang: rentang, tgl_awal: tgl1, tgl_akhir: tgl2, limit: 1 },
            dataType: 'json',
            success: function(res_sync) {
                var cutoff_start = res_sync.summary.cutoff_start;
                var cutoff_end = res_sync.summary.cutoff_end;
                
                $('#det_rentang_tgl').text(cutoff_start + ' s.d ' + cutoff_end);
                
                // Ambil riwayatnya
                $.ajax({
                    url: 'api/data_riwayat_obat.php',
                    type: 'GET',
                    data: { 
                        kode_brng: kode_brng, 
                        kd_bangsal: kd_bangsal,
                        tgl_awal: cutoff_start, 
                        tgl_akhir: cutoff_end 
                    },
                    dataType: 'json',
                    success: function(res_riwayat) {
                        isCheckingRiwayat = false;
                        if(res_riwayat.data) {
                            tableRiwayat.rows.add(res_riwayat.data).draw();
                        } else {
                            if(res_riwayat.error && res_riwayat.error.includes("Akses ditolak")) {
                                alert("Sesi login Anda telah habis, silakan login kembali.");
                            } else {
                                alert("Riwayat tidak ditemukan atau terjadi kesalahan server.");
                            }
                        }
                    },
                    error: function(err) {
                        isCheckingRiwayat = false;
                        alert("Gagal menghubungi server riwayat.");
                    }
                });
            },
            error: function() {
                isCheckingRiwayat = false;
                $('#det_rentang_tgl').text('Gagal sinkron tanggal');
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
