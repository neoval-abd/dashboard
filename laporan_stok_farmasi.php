<?php
/*
 * File: laporan_stok_farmasi.php (UPDATE V2)
 * Fitur Baru: 
 * - Searchable Dropdown (Select2) untuk Filter Lokasi.
 * - Filter Lokasi/Depo pada Modal Riwayat Stok Digital.
 */
$page_title = "Monitoring Stok Farmasi";
require_once('includes/header.php');

// Ambil daftar bangsal untuk filter
$bangsals = [];
$sql_bangsal = "SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal";
$res_bangsal = $koneksi->query($sql_bangsal);
while($row = $res_bangsal->fetch_assoc()){
    $bangsals[] = $row;
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    /* Custom CSS */
    .obat-card { border-left: 4px solid #4e73df; margin-bottom: 10px; }
    .obat-card .nama-obat { font-weight: bold; color: #333; font-size: 1.1rem; }
    .obat-card .stok-besar { font-size: 1.5rem; font-weight: bold; color: #1cc88a; }
    .obat-card .lokasi { font-size: 0.8rem; color: #858796; }
    /* Fix z-index select2 dalam modal */
    .select2-container--open { z-index: 9999999 !important; }
</style>

<div class="container-fluid">
    
    <div class="row mb-4">
        <div class="col-md-4 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Nilai Aset (Rp)</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="val-aset">Loading...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Item Obat</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="val-item">...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-pills fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Stok Menipis (< 10)</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="val-kritis">...</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-5">
                    <select class="form-select select2-single" id="kd_bangsal">
                        <option value="">-- Semua Lokasi/Depo --</option>
                        <?php foreach($bangsals as $b): ?>
                            <option value="<?php echo $b['kd_bangsal']; ?>"><?php echo $b['nm_bangsal']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control" id="keyword" placeholder="Cari nama obat...">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" onclick="loadData()">Cari</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Data Stok Aktif</h6>
            <small class="text-muted">Menampilkan max 500 data teratas</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableStok" class="table table-hover table-striped" width="100%">
                    <thead class="table-dark">
                        <tr>
                            <th>Nama Obat / Barang</th>
                            <th class="text-center">Satuan</th>
                            <th class="text-end">Total Stok</th>
                            <th class="text-end">Nilai Aset</th>
                            <th>Lokasi (Depo)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRiwayat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i>Kartu Stok Digital: <span id="modalTitleObat" class="fw-bold">...</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3 g-2">
                    <div class="col-md-3">
                        <label class="small fw-bold">Dari Tanggal</label>
                        <input type="date" class="form-control" id="hist_tgl_awal" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="small fw-bold">Sampai Tanggal</label>
                        <input type="date" class="form-control" id="hist_tgl_akhir" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                         <label class="small fw-bold">Filter Depo/Gudang</label>
                         <select class="form-select select2-modal" id="hist_kd_bangsal">
                            <option value="">-- Semua Lokasi --</option>
                            <?php foreach($bangsals as $b): ?>
                                <option value="<?php echo $b['kd_bangsal']; ?>"><?php echo $b['nm_bangsal']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-info text-white w-100" onclick="refreshHistory()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="tableRiwayat" class="table table-sm table-bordered table-hover w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu</th>
                                <th>Keterangan / Posisi</th>
                                <th>Lokasi</th>
                                <th>No. Faktur</th>
                                <th class="text-center">Masuk</th>
                                <th class="text-center">Keluar</th>
                                <th class="text-center">Saldo</th>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    var tableStok, tableRiwayat;
    var currentKodeBrng = '';

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        // 1. Inisialisasi Select2 pada Filter Utama
        $('.select2-single').select2({
            theme: "bootstrap-5",
            placeholder: "Pilih atau Ketik Nama Depo...",
            allowClear: true
        });

        // 2. Inisialisasi Select2 pada Modal (dropdownParent penting agar bisa search di dalam modal)
        $('.select2-modal').select2({
            theme: "bootstrap-5",
            placeholder: "Pilih Depo...",
            allowClear: true,
            dropdownParent: $('#modalRiwayat') 
        });

        // 3. Init Table Stok
        tableStok = $('#tableStok').DataTable({
            "responsive": true,
            "dom": 'Bfrtip',
            //"buttons": [ 'excel', 'print' ],
            buttons: [ 
                {
                    extend: 'excelHtml5',
                    title: 'Laporan Stok Farmasi',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: ':visible:not(:last-child)', // Cegah kolom Aksi ikut ter-export
                        format: {
                            body: function(data, row, column, node) {
                                // PENTING: Paksa data jadi String dulu agar tidak crash pada tipe Number (Stok)
                                var strData = (data === null || data === undefined) ? '' : String(data);

                                // 1. KHUSUS KOLOM ANGKA (Stok: Col 2 & Aset: Col 3)
                                if (column === 2 || column === 3) {
                                    // Hapus tag HTML (jika ada)
                                    let clean = strData.replace(/<[^>]+>/g, "");
                                    // Bersihkan karakter non-angka (kecuali koma & minus)
                                    // Ganti koma desimal jadi titik (standar Excel)
                                    return clean.replace(/[^\d,-]/g, '').replace(',', '.');
                                }

                                // 2. BERSIHKAN HTML PADA KOLOM TEKS (Misal: Nama Obat)
                                // Ganti <br> dengan strip " - " supaya tidak nempel
                                if (strData.indexOf('<') > -1) {
                                    return strData.replace(/<br\s*\/?>/gi, " - ").replace(/<[^>]+>/g, "").trim();
                                }

                                return data;
                            }
                        }
                    }
                }, 
                {
                    extend: 'print',
                    className: 'btn btn-secondary btn-sm',
                    text: '<i class="fas fa-print"></i> Print',
                    exportOptions: {
                        columns: ':visible:not(:last-child)'
                    }
                } 
            ],
			"pageLength": 10,
            "order": [[ 2, "desc" ]], 
            "columns": [
                { "data": "nama_brng", 
                  "render": function(data, type, row) {
                      return `<div><strong>${data}</strong><br><small class="text-muted">${row.kode_brng}</small></div>`;
                  }
                },
                { "data": "satuan", className: "text-center" },
                { "data": "total_stok", className: "text-end fw-bold fs-5 text-success" },
                { "data": "total_aset", className: "text-end", render: function(data) { return formatRupiah(data); } },
                { "data": "lokasi_stok", className: "small text-muted" },
                { 
                    "data": null,
                    "className": "text-center",
                    "render": function(data, type, row) {
                        return `<button class="btn btn-sm btn-warning text-white" onclick="openHistory('${row.kode_brng}', '${row.nama_brng}')">
                                    <i class="fas fa-history"></i> Riwayat
                                </button>`;
                    }
                }
            ]
        });

        // 4. Init Table Riwayat
        tableRiwayat = $('#tableRiwayat').DataTable({
            "responsive": true,
            "order": [[ 0, "desc" ]],
            "pageLength": 10,
            "columns": [
                { "data": "tanggal", render: function(data, type, row) { return data + ' ' + row.jam; } },
                { "data": "posisi", render: function(data, type, row) { return `<strong>${data}</strong><br><small>${row.keterangan}</small>`; } },
                { "data": "nm_bangsal" },
                { "data": "no_faktur" },
                { "data": "masuk", className: "text-center text-success fw-bold" },
                { "data": "keluar", className: "text-center text-danger fw-bold" },
                { "data": "stok_akhir", className: "text-center fw-bold bg-light" }
            ]
        });

        // Data akan dimuat saat user klik tombol Cari
    });

    function loadData() {
        var bangsal = $('#kd_bangsal').val();
        var keyword = $('#keyword').val();

        // Loading state
        $('#val-aset').text('Loading...');
        $('#val-item').text('...');

        $.ajax({
            url: 'api/data_stok_farmasi.php',
            type: 'GET',
            data: { kd_bangsal: bangsal, keyword: keyword },
            dataType: 'json',
            success: function(response) {
                $('#val-aset').text(formatRupiah(response.summary.total_aset));
                $('#val-item').text(response.summary.total_item);
                $('#val-kritis').text(response.summary.stok_kritis);

                tableStok.clear();
                tableStok.rows.add(response.data);
                tableStok.draw();
            },
            error: function() { alert("Gagal memuat data stok."); }
        });
    }

    function openHistory(kode, nama) {
        currentKodeBrng = kode;
        $('#modalTitleObat').text(nama);
        $('#modalRiwayat').modal('show');
        
        // Reset filter modal ke default
        $('#hist_kd_bangsal').val('').trigger('change'); 
        
        refreshHistory();
    }

    function refreshHistory() {
        if(!currentKodeBrng) return;
        var tgl1 = $('#hist_tgl_awal').val();
        var tgl2 = $('#hist_tgl_akhir').val();
        var bangsal = $('#hist_kd_bangsal').val(); // Ambil nilai filter modal

        tableRiwayat.clear().draw();
        
        $.ajax({
            url: 'api/data_riwayat_obat.php',
            type: 'GET',
            data: { 
                kode_brng: currentKodeBrng, 
                tgl_awal: tgl1, 
                tgl_akhir: tgl2,
                kd_bangsal: bangsal // Kirim ke API
            },
            dataType: 'json',
            success: function(response) {
                tableRiwayat.rows.add(response.data);
                tableRiwayat.draw();
            },
            error: function() { console.error("Gagal load riwayat"); }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>