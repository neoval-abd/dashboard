<?php
/*
 * File: laporan_analisa_lengkap.php (UPDATE V3 - WIDE TABLE)
 * - Menampilkan semua kolom detail (SEP, Wilayah, Perusahaan, dll).
 * - Tabel menggunakan scroll horizontal (scrollX).
 */

$page_title = "Analisa Data Lengkap (Deep Dive)";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

$penjabs = [];
$sql_pj = "SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab";
$res_pj = $koneksi->query($sql_pj);
while($row = $res_pj->fetch_assoc()){ $penjabs[] = $row; }
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

<style>
    /* Agar header tabel tidak pecah saat scroll horizontal */
    th { white-space: nowrap; }
</style>

<div class="container-fluid">

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary mb-3"><i class="fas fa-filter me-2"></i>Filter Data (Tgl Bayar)</h5>
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
                    <label class="form-label small fw-bold">Status Bayar</label>
                    <select class="form-select" id="status_bayar">
                        <option value="">-- Semua --</option>
                        <option value="Sudah Bayar" selected>Sudah Bayar</option>
                        <option value="Belum Bayar">Belum Bayar</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Jenis Kunjungan</label>
                    <select class="form-select" id="status_lanjut">
                        <option value="">-- Semua --</option>
                        <option value="Ralan">Rawat Jalan</option>
                        <option value="Ranap">Rawat Inap</option>
                    </select>
                </div>
                <div class="col-md-2">
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
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-start border-4 border-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Pendapatan (Filter Ini)</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="val-pendapatan">Rp 0</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-money-bill-wave fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-md-6 mb-4">
            <div class="card border-start border-4 border-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Kunjungan</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="val-kunjungan">0</div>
                            <div class="small mt-1 text-muted"><span id="val-ralan">0</span> Ralan | <span id="val-ranap">0</span> Ranap</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Data Detail Lengkap</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover table-sm text-sm" id="dataTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Tgl Reg</th>
                            <th>Tgl Bayar</th>
                            <th>No. Rawat</th>
                            <th>Sts Pasien</th> <th>Jns Rawat</th>
                            <th>No. RM</th>
                            <th>Nama Pasien</th>
                            <th>No. Tlp</th>
                            <th>Penjamin</th>
                            <th>Poli</th>
                            <th>Dokter DPJP</th>
                            <th>Dr. Perujuk</th>
                            <th>Diagnosa</th>
                            <th>No. SEP</th>
                            <th>No. Rujukan</th>
                            <th>Faskes Rujuk</th>
                            <th>Perusahaan</th>
                            <th>Kabupaten</th>
                            <th>Kecamatan</th>
                            <th>Kelurahan</th>
                            <th class="text-end">Total Biaya</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="20" class="text-end">TOTAL HALAMAN INI:</td>
                            <td class="text-end text-primary" id="pageTotal">0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="modalDetailNota" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Detail Nota</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="isi-nota-container">
                <p class="text-center">Memuat data...</p>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
    var myTable;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        $('.select2-single').select2({ theme: "bootstrap-5", placeholder: "Pilih Penjamin", allowClear: true });

        myTable = $('#dataTable').DataTable({
            "responsive": false, // Matikan responsive agar scrollX bekerja
            "scrollX": true,     // Aktifkan scroll horizontal
            "dom": 'Bfrtip', 
            /* "buttons": [
                { extend: 'excelHtml5', className: 'btn btn-success btn-sm', text: '<i class="fas fa-file-excel"></i> Excel Full', title: 'Analisa Data Lengkap' },
                { extend: 'print', className: 'btn btn-secondary btn-sm', text: '<i class="fas fa-print"></i> Print' }
            ],  */
			"buttons": [
                { 
                    extend: 'excelHtml5', 
                    className: 'btn btn-success btn-sm', 
                    text: '<i class="fas fa-file-excel"></i> Excel Full', 
                    title: 'Analisa Data Lengkap',
                    exportOptions: {
                        // Pastikan kolom Aksi (terakhir) tidak ikut
                        columns: ':visible:not(:last-child)',
                        format: {
                            body: function(data, row, column, node) {
                                // 1. KHUSUS KOLOM RUPIAH (Index 20)
                                if (column === 20) {
                                    return typeof data === 'string' ?
                                        data.replace(/\./g, '').replace(',', '.') :
                                        data;
                                }

                                // 2. KHUSUS KOLOM TEXT LAINNYA (Untuk membersihkan <span class="badge">)
                                // Jika data adalah string dan mengandung tanda kurung siku HTML (<)
                                if (typeof data === 'string' && data.indexOf('<') > -1) {
                                    // Regex ini akan menghapus semua tag HTML dan menyisakan teksnya saja
                                    // Contoh: <span class="badge">Baru</span>  Menjadi:  Baru
                                    return data.replace(/<[^>]+>/g, "").trim();
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
            "pageLength": 25,
            "columns": [
                { "data": "tgl_registrasi" },
                { "data": "tgl_byr" },
                { "data": "no_rawat" },
                { "data": "Pasien_Baru_Lama", render: function(d){ return d=='Baru'?'<span class="badge bg-info">Baru</span>':'Lama'; } },
                { "data": "status_lanjut", render: function(d){ return d=='Ranap'?'<span class="badge bg-warning text-dark">Ranap</span>':'Ralan'; } },
                { "data": "no_rkm_medis" },
                { "data": "nm_pasien", className: "fw-bold" },
                { "data": "no_tlp" },
                { "data": "png_jawab" },
                { "data": "nm_poli" },
                { "data": "nm_dokter" },
                { "data": "dokter_perujuk" },
                { "data": "nm_penyakit", render: function(d,t,r){ return (r.kd_penyakit||'') + ' ' + (d||''); } },
                { "data": "no_sep" },
                { "data": "no_rujukan" },
                { "data": "nmppkrujukan" },
                { "data": "nama_perusahaan" },
                { "data": "nm_kab" },
                { "data": "nm_kec" },
                { "data": "nm_kel" },
                { "data": "TotalBiaya", className: "text-end fw-bold text-primary", render: $.fn.dataTable.render.number('.', ',', 0, '') },
                { 
                    "data": null, className: "text-center",
                    "render": function(data, type, row) {
                        return `<button class="btn btn-sm btn-outline-success btn-lihat-nota" data-norawat="${row.no_rawat}"><i class="fas fa-receipt"></i></button>`;
                    }
                }
            ],
            "order": [[ 1, "desc" ]],
            "footerCallback": function (row, data, start, end, display) {
                var api = this.api();
                var intVal = function (i) { return typeof i === 'string' ? i.replace(/[\.,]/g, '') * 1 : typeof i === 'number' ? i : 0; };
                
                var pageTotal = api.column(20, { page: 'current' }).data().reduce(function (a, b) {
                    return intVal(a) + intVal(b);
                }, 0);
                
                $('#pageTotal').html(formatRupiah(pageTotal));
            }
        });

        // Data akan dimuat saat user klik tombol Tampilkan
        
        // Listener Tombol Nota
        $(document).on('click', '.btn-lihat-nota', function() {
            var noRawat = $(this).data('norawat');
            $("#isi-nota-container").html("<p class='text-center'>Memuat data...</p>");
            $('#modalDetailNota').modal('show');
            $.ajax({
                url: "api/get_detail_nota.php", type: "GET", data: { no_rawat: noRawat }, dataType: "json",
                success: function(response) {
                    var html = '<table class="table table-sm table-striped"><thead><tr><th>Ket</th><th>Nama</th><th class="text-end">Biaya</th><th class="text-center">Jml</th><th class="text-end">Total</th></tr></thead><tbody>';
                    var grandTotal = 0;
                    if (response.length > 0) {
                        response.forEach(function(item) {
                            html += `<tr><td>${item.status}</td><td>${item.nm_perawatan}</td><td class="text-end">${formatRupiah(item.biaya)}</td><td class="text-center">${item.jumlah}</td><td class="text-end">${formatRupiah(item.totalbiaya)}</td></tr>`;
                            grandTotal += parseFloat(item.totalbiaya);
                        });
                    }
                    html += '</tbody><tfoot class="fw-bold"><tr><td colspan="4" class="text-end">TOTAL:</td><td class="text-end">'+formatRupiah(grandTotal)+'</td></tr></tfoot></table>';
                    $("#isi-nota-container").html(html);
                }
            });
        });
    });

    function loadData() {
        $('#val-pendapatan').text('Loading...');
        $('#val-kunjungan').text('...');
        
        var params = {
            tgl_awal: $('#tgl_awal').val(),
            tgl_akhir: $('#tgl_akhir').val(),
            status_bayar: $('#status_bayar').val(),
            status_lanjut: $('#status_lanjut').val(),
            kd_pj: $('#kd_pj').val()
        };

        $.ajax({
            url: 'api/data_analisa_lengkap.php', type: 'GET', data: params, dataType: 'json',
            success: function(response) {
                $('#val-pendapatan').text(formatRupiah(response.summary.total_pendapatan));
                $('#val-kunjungan').text(response.summary.total_kunjungan.toLocaleString());
                $('#val-ralan').text(response.summary.total_ralan);
                $('#val-ranap').text(response.summary.total_ranap);

                myTable.clear();
                if (response.data.length > 0) {
                    myTable.rows.add(response.data).draw();
                } else {
                    myTable.draw();
                }
            },
            error: function() { alert("Gagal memuat data."); }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>