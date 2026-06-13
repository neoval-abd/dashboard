<?php
/*
 * File: laporan_ringkasan_obat.php
 * Modul: Ringkasan Penerimaan Obat, Alkes & BHP Medis
 * Deskripsi: Rekap penerimaan barang dari pemesanan (detailpesan)
 *            dengan filter tanggal, supplier, jenis, dll.
 */
$page_title = "Ringkasan Penerimaan Obat";
require_once('includes/header.php');

$tgl_awal_default  = date('Y-m-01');
$tgl_akhir_default = date('Y-m-d');
?>

<style>
    .stat-card {
        border-radius: 12px; padding: 16px 20px; color: #fff;
    }
    .stat-card h3 { margin: 0; font-weight: 700; }
    .stat-card small { opacity: 0.8; }
    table.dataTable thead th {
        background: #f8f9fc; font-size: 0.82rem; font-weight: 700;
        color: #333; border-bottom: 2px solid #dee2e6 !important;
    }
    table.dataTable tbody td { font-size: 0.82rem; vertical-align: middle; }
    .filter-section .form-label { font-size: 0.78rem; font-weight: 600; margin-bottom: 2px; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-boxes text-success me-2"></i> Ringkasan Penerimaan Obat, Alkes & BHP Medis</h4>
        <small class="text-muted">Rekap penerimaan barang dari pemesanan berdasarkan detail faktur</small>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-3 filter-section">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">No. Faktur</label>
                <input type="text" class="form-control form-control-sm" id="fNoFaktur" placeholder="Semua">
            </div>
            <div class="col-md-2">
                <label class="form-label">Supplier</label>
                <input type="text" class="form-control form-control-sm" id="fSupplier" placeholder="Semua">
            </div>
            <div class="col-md-2">
                <label class="form-label">Petugas</label>
                <input type="text" class="form-control form-control-sm" id="fPetugas" placeholder="Semua">
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary px-3 w-100" id="btnLoad">
                    <i class="fas fa-sync-alt me-1"></i> Muat Data
                </button>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-2">
                <label class="form-label">Industri Farmasi</label>
                <input type="text" class="form-control form-control-sm" id="fIndustri" placeholder="Semua">
            </div>
            <div class="col-md-2">
                <label class="form-label">Jenis</label>
                <input type="text" class="form-control form-control-sm" id="fJenis" placeholder="Semua">
            </div>
            <div class="col-md-2">
                <label class="form-label">Nama Barang</label>
                <input type="text" class="form-control form-control-sm" id="fBarang" placeholder="Semua">
            </div>
            <div class="col-md-3">
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-sm btn-success" id="btnExport" disabled>
                    <i class="fas fa-file-excel me-1"></i> Export Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#667eea,#764ba2);">
            <h3 id="stItem">0</h3><small>Jenis Barang</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#43e97b,#38f9d7);">
            <h3 id="stQty">0</h3><small>Total Qty</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#f093fb,#f5576c);">
            <h3 id="stNilai">Rp 0</h3><small>Total Nilai</small>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="tblData" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Satuan</th>
                        <th>Jenis</th>
                        <th class="text-end">Jumlah</th>
                        <th class="text-end">Harga / Item</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr style="background:#f0f0f0; font-weight:700;">
                        <td colspan="5" class="text-end">GRAND TOTAL:</td>
                        <td class="text-end" id="footQty">0</td>
                        <td></td>
                        <td class="text-end" id="footTotal">Rp 0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let dt, tableData = [];
const ID_LANG = {
    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data', infoFiltered: '(difilter dari _MAX_ total)', zeroRecords: 'Data tidak ditemukan',
    paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
};

function fmtRp(n) { return 'Rp ' + Number(n||0).toLocaleString('id-ID', {maximumFractionDigits:2}); }
function fmtNum(n) { return Number(n||0).toLocaleString('id-ID', {maximumFractionDigits:2}); }

function initTable() {
    if (dt) dt.destroy();
    dt = $('#tblData').DataTable({
        data: tableData,
        language: ID_LANG,
        pageLength: 50,
        order: [[2, 'asc']],
        columns: [
            { data: null, render: (d,t,r,i) => i.row+1 },
            { data: 'kode_brng' },
            { data: 'nama_brng' },
            { data: 'satuan' },
            { data: 'namajenis' },
            { data: 'jumlah', className: 'text-end', render: d => fmtNum(d) },
            { data: 'harga_per_item', className: 'text-end', render: d => fmtRp(d) },
            { data: 'total', className: 'text-end', render: d => fmtRp(d) }
        ]
    });
}

function loadData() {
    const params = {
        tgl_awal:   $('#tglAwal').val(),
        tgl_akhir:  $('#tglAkhir').val(),
        no_faktur:  $('#fNoFaktur').val(),
        supplier:   $('#fSupplier').val(),
        petugas:    $('#fPetugas').val(),
        industri:   $('#fIndustri').val(),
        jenis:      $('#fJenis').val(),
        barang:     $('#fBarang').val()
    };
    if (!params.tgl_awal || !params.tgl_akhir) { alert('Pilih periode tanggal'); return; }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Memuat...');

    $.getJSON('api/data_ringkasan_obat.php', params, function(res) {
        tableData = res.data || [];
        const s = res.summary || {};

        initTable();

        $('#stItem').text(s.total_item || 0);
        $('#stQty').text(fmtNum(s.total_qty || 0));
        $('#stNilai').text(fmtRp(s.total_nilai || 0));
        $('#footQty').text(fmtNum(s.total_qty || 0));
        $('#footTotal').text(fmtRp(s.total_nilai || 0));

        const fmtTgl = d => d.split('-').reverse().join('-');
        $('#stPeriode').html(fmtTgl(params.tgl_awal) + '<br>s/d ' + fmtTgl(params.tgl_akhir));

        $('#btnExport').prop('disabled', tableData.length === 0);
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Muat Data');
    }).fail(function() {
        alert('Gagal memuat data. Pastikan API tersedia.');
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Muat Data');
    });
}

function exportExcel() {
    const tglAwal  = $('#tglAwal').val();
    const tglAkhir = $('#tglAkhir').val();
    const wb = XLSX.utils.book_new();

    const headers = ['No','Kode Barang','Nama Barang','Satuan','Jenis','Jumlah','Harga/Item','Total'];
    const rows = tableData.map((r,i) => [
        i+1, r.kode_brng||'', r.nama_brng||'', r.satuan||'', r.namajenis||'',
        r.jumlah||0, r.harga_per_item||0, r.total||0
    ]);

    // Grand total row
    const totalQty = tableData.reduce((a,r) => a + (r.jumlah||0), 0);
    const totalNilai = tableData.reduce((a,r) => a + (r.total||0), 0);
    rows.push(['','','','','GRAND TOTAL', totalQty, '', totalNilai]);

    const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
    ws['!cols'] = [{wch:4},{wch:16},{wch:35},{wch:12},{wch:14},{wch:12},{wch:16},{wch:18}];

    // Bold header
    for (let C = 0; C < headers.length; C++) {
        const addr = XLSX.utils.encode_cell({r:0,c:C});
        if (!ws[addr]) continue;
        ws[addr].s = { font:{bold:true,color:{rgb:'FFFFFF'}}, fill:{fgColor:{rgb:'1cc88a'}}, alignment:{horizontal:'center'} };
    }
    // Number format for Jumlah, Harga, Total
    for (let R = 1; R <= rows.length; R++) {
        [5,6,7].forEach(function(C) {
            const addr = XLSX.utils.encode_cell({r:R,c:C});
            if (ws[addr] && ws[addr].v !== '') { ws[addr].t='n'; ws[addr].z='#,##0.00'; }
        });
    }
    // Bold grand total row
    const lastR = rows.length;
    for (let C = 0; C < headers.length; C++) {
        const addr = XLSX.utils.encode_cell({r:lastR,c:C});
        if (ws[addr]) ws[addr].s = { font:{bold:true} };
    }

    XLSX.utils.book_append_sheet(wb, ws, 'Ringkasan Penerimaan');
    const tgl = tglAwal.replace(/-/g,'') + '_sd_' + tglAkhir.replace(/-/g,'');
    XLSX.writeFile(wb, 'Ringkasan_Penerimaan_Obat_' + tgl + '.xlsx', { cellStyles: true });
}

$('#btnLoad').on('click', loadData);
$('#btnExport').on('click', exportExcel);

// Enter key triggers load
$('.filter-section input[type="text"]').on('keypress', function(e) {
    if (e.which === 13) { e.preventDefault(); loadData(); }
});

$(document).ready(function() { loadData(); });
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
