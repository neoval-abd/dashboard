<?php
$page_title = "Jasa Med. & Paramed.";
require_once('includes/header.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

$dokters = [];
$res_dr = $koneksi->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter");
if ($res_dr) {
    while ($row = $res_dr->fetch_assoc()) { $dokters[] = $row; }
}

$petugas = [];
$res_pt = $koneksi->query("SELECT nip, nama FROM petugas WHERE stts_aktif='AKTIF' ORDER BY nama");
if ($res_pt) {
    while ($row = $res_pt->fetch_assoc()) { $petugas[] = $row; }
}

$units = [];
$res_unit = $koneksi->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli");
if ($res_unit) {
    while ($row = $res_unit->fetch_assoc()) { $units[] = $row; }
}

$penjabs = [];
$res_pj = $koneksi->query("SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab");
if ($res_pj) {
    while ($row = $res_pj->fetch_assoc()) { $penjabs[] = $row; }
}
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .jm-toolbar .form-label { margin-bottom: 3px; }
    .jm-tabs { gap: 4px; overflow-x: auto; flex-wrap: nowrap; padding-bottom: 2px; }
    .jm-tabs .nav-link { white-space: nowrap; padding: 7px 12px; font-size: .78rem; border-radius: 6px 6px 0 0; }
    .jm-stat { border-radius: 8px; padding: 12px 14px; min-height: 78px; color: #fff; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .jm-stat h4 { margin: 0; font-size: 1.2rem; font-weight: 800; }
    .jm-stat small { display: block; margin-top: 4px; font-weight: 700; opacity: .9; text-transform: uppercase; }
    .jm-stat i { font-size: 1.55rem; opacity: .65; }
    #tblJasaMedis thead th { background: #f8f9fc; font-size: .72rem; font-weight: 800; color: #303642; border-bottom: 2px solid #d9deea !important; white-space: nowrap; vertical-align: middle; }
    #tblJasaMedis tbody td, #tblJasaMedis tfoot th { font-size: .74rem; vertical-align: middle; white-space: nowrap; }
    #tblJasaMedis tfoot th { background: #f8f9fc; font-weight: 800; }
    .jm-table-card .card-body { padding: .75rem; }
    .select2-container--bootstrap-5 .select2-selection { min-height: 31px; }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-user-nurse text-primary me-2"></i> Jasa Med. & Paramed.</h4>
        <small class="text-muted">Detail tindakan dokter, paramedis, operasi, radiologi, dan laboratorium sesuai format Detail Tindakan</small>
    </div>
    <span class="badge bg-light text-dark border" id="infoPeriode">Data belum dimuat</span>
</div>

<div class="card shadow-sm mb-3 jm-toolbar">
    <div class="card-body py-2">
        <ul class="nav nav-tabs jm-tabs mb-3" id="jmTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-tab="ralan_dokter" type="button">Ralan Dokter</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="ralan_paramedis" type="button">Ralan Paramedis</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="ralan_dokter_paramedis" type="button">Ralan Dokter & Paramedis</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="operasi_vk" type="button">Operasi & VK</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="ranap_dokter" type="button">Ranap Dokter</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="ranap_paramedis" type="button">Ranap Paramedis</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="ranap_dokter_paramedis" type="button">Ranap Dokter & Paramedis</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="radiologi" type="button">Pemeriksaan Radiologi</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="laboratorium" type="button">Pemeriksaan Laboratorium</button></li>
            <li class="nav-item"><button class="nav-link" data-tab="detail_laboratorium" type="button">Detail Pemeriksaan Laboratorium</button></li>
        </ul>

        <div class="row g-2 align-items-end">
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Tanggal Awal</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo htmlspecialchars($tgl_awal); ?>">
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Tanggal Akhir</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo htmlspecialchars($tgl_akhir); ?>">
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Status</label>
                <select class="form-select form-select-sm" id="statusBayar">
                    <option value="Semua">Semua</option>
                    <option value="Piutang Belum Lunas">Piutang Belum Lunas</option>
                    <option value="Piutang Sudah Lunas">Piutang Sudah Lunas</option>
                    <option value="Sudah Bayar Non Piutang">Sudah Bayar Non Piutang</option>
                    <option value="Belum Terclosing Kasir">Belum Terclosing Kasir</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Dokter</label>
                <select class="form-select form-select-sm jm-select" id="kdDokter">
                    <option value="">Semua Dokter</option>
                    <?php foreach ($dokters as $d): ?>
                        <option value="<?php echo htmlspecialchars($d['kd_dokter']); ?>"><?php echo htmlspecialchars($d['nm_dokter']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Petugas</label>
                <select class="form-select form-select-sm jm-select" id="kdPetugas">
                    <option value="">Semua Petugas</option>
                    <?php foreach ($petugas as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['nip']); ?>"><?php echo htmlspecialchars($p['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Unit/Poli</label>
                <select class="form-select form-select-sm jm-select" id="kdUnit">
                    <option value="">Semua Unit</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo htmlspecialchars($u['kd_poli']); ?>"><?php echo htmlspecialchars($u['nm_poli']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Cara Bayar</label>
                <select class="form-select form-select-sm jm-select" id="kdPj">
                    <option value="">Semua Cara Bayar</option>
                    <?php foreach ($penjabs as $pj): ?>
                        <option value="<?php echo htmlspecialchars($pj['kd_pj']); ?>"><?php echo htmlspecialchars($pj['png_jawab']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="form-label small fw-bold">Show</label>
                <select class="form-select form-select-sm" id="pageSize">
                    <option value="10" selected>10 rows</option>
                    <option value="50">50 rows</option>
                    <option value="100">100 rows</option>
                    <option value="-1">Semua</option>
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label small fw-bold">Key Word</label>
                <input type="text" class="form-control form-control-sm" id="keyword" placeholder="No rawat, RM, pasien, tindakan, dokter...">
            </div>
            <div class="col-xl-3 col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill" id="btnLoad" title="Tampilkan"><i class="fas fa-check me-1"></i> Tampilkan</button>
                <button class="btn btn-sm btn-success" id="btnExcel" title="Export Excel" disabled><i class="fas fa-file-excel me-1"></i> Export</button>
                <button class="btn btn-sm btn-secondary" id="btnPrint" title="Print" disabled><i class="fas fa-print"></i></button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6"><div class="jm-stat" style="background:linear-gradient(135deg,#4e73df,#224abe);"><div><h4 id="stRows">0</h4><small>Total Baris</small></div><i class="fas fa-table"></i></div></div>
    <div class="col-xl-3 col-md-6"><div class="jm-stat" style="background:linear-gradient(135deg,#1cc88a,#13855c);"><div><h4 id="stTotal">Rp 0</h4><small>Total Biaya</small></div><i class="fas fa-money-bill-wave"></i></div></div>
    <div class="col-xl-3 col-md-6"><div class="jm-stat" style="background:linear-gradient(135deg,#36b9cc,#258391);"><div><h4 id="stDokter">Rp 0</h4><small>JM Dokter</small></div><i class="fas fa-user-md"></i></div></div>
    <div class="col-xl-3 col-md-6"><div class="jm-stat" style="background:linear-gradient(135deg,#f6c23e,#dda20a);"><div><h4 id="stParamedis">Rp 0</h4><small>JM Paramedis</small></div><i class="fas fa-user-nurse"></i></div></div>
</div>

<div class="card shadow mb-4 jm-table-card">
    <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="m-0 font-weight-bold text-primary" id="tableTitle">Detail Tindakan</h6>
        <span class="badge bg-light text-dark border" id="infoRows">Data belum dimuat</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover" id="tblJasaMedis" style="width:100%">
                <thead><tr id="jmHead"></tr></thead>
                <tbody></tbody>
                <tfoot><tr id="jmFoot"></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
let dtJasaMedis = null;
let activeTab = 'ralan_dokter';
let activeColumns = [];
let activeRequest = null;

const ID_LANG_JM = {
    search: 'Cari:',
    lengthMenu: 'Tampilkan _MENU_ data',
    info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data',
    infoFiltered: '(difilter dari _MAX_ total)',
    zeroRecords: 'Data tidak ditemukan',
    paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(char) {
        return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' })[char];
    });
}

function fmtNum(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function fmtRp(value) {
    return 'Rp ' + fmtNum(value);
}

function exportCellValue(data, row, column) {
    const col = activeColumns[column] || {};
    const text = $('<div>').html(data).text();
    if (col.money) {
        return '\u200C' + text;
    }
    return text;
}

function buildFooter(columns, summary) {
    let totalLabelPlaced = false;
    const cells = columns.map(function(col) {
        if (col.money) {
            const value = summary && Object.prototype.hasOwnProperty.call(summary, col.data) ? summary[col.data] : 0;
            return '<th class="text-end">' + fmtNum(value) + '</th>';
        }
        if (!totalLabelPlaced && col.data === 'ruangan') {
            totalLabelPlaced = true;
            return '<th class="text-end">Jumlah Total:</th>';
        }
        return '<th></th>';
    });
    $('#jmFoot').html(cells.join(''));
}

function buildTable(columns, data, title, summary) {
    activeColumns = columns || [];

    if (dtJasaMedis) {
        dtJasaMedis.clear();
        dtJasaMedis.destroy();
        dtJasaMedis = null;
    }

    $('#tblJasaMedis')
        .empty()
        .append('<thead><tr id="jmHead"></tr></thead><tbody></tbody><tfoot><tr id="jmFoot"></tr></tfoot>');
    $('#jmHead').html(activeColumns.map(col => '<th>' + escapeHtml(col.title) + '</th>').join(''));
    buildFooter(activeColumns, summary || {});

    dtJasaMedis = $('#tblJasaMedis').DataTable({
        data: data || [],
        columns: activeColumns.map(function(col) {
            return {
                data: col.data,
                defaultContent: col.money ? 0 : '-',
                className: col.money ? 'text-end' : '',
                render: function(value, type) {
                    if (col.money) {
                        return type === 'display' ? fmtNum(value) : Number(value || 0);
                    }
                    if (type !== 'display') return value;
                    return escapeHtml(value || '-');
                }
            };
        }),
        language: ID_LANG_JM,
        pageLength: Number($('#pageSize').val() || 10),
        lengthMenu: [[10, 50, 100, -1], [10, 50, 100, 'Semua']],
        scrollX: true,
        scrollY: '54vh',
        scrollCollapse: true,
        order: [[1, 'desc']],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Jasa Med dan Paramed - ' + (title || 'Detail'),
                filename: function() {
                    return 'Jasa_Med_Paramed_' + activeTab + '_' + $('#tglAwal').val().replace(/-/g, '') + '_sd_' + $('#tglAkhir').val().replace(/-/g, '');
                },
                className: 'd-none',
                exportOptions: {
                    columns: ':visible',
                    format: { body: exportCellValue }
                }
            },
            {
                extend: 'print',
                title: 'Jasa Med. & Paramed. - ' + (title || 'Detail'),
                className: 'd-none',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'colvis',
                text: 'Kolom',
                className: 'btn btn-sm'
            }
        ]
    });
}

function collectParams() {
    return {
        tab: activeTab,
        tgl_awal: $('#tglAwal').val(),
        tgl_akhir: $('#tglAkhir').val(),
        status: $('#statusBayar').val(),
        kd_dokter: $('#kdDokter').val(),
        kd_petugas: $('#kdPetugas').val(),
        kd_unit: $('#kdUnit').val(),
        kd_pj: $('#kdPj').val(),
        keyword: $('#keyword').val(),
        limit: 20000
    };
}

function loadJasaMedis() {
    if (!$('#tglAwal').val() || !$('#tglAkhir').val()) {
        alert('Pilih periode tanggal terlebih dahulu.');
        return;
    }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Memuat');
    $('#btnExcel, #btnPrint').prop('disabled', true);
    $('#infoRows').text('Mengambil data...');

    if (activeRequest && activeRequest.readyState !== 4) {
        activeRequest.abort();
    }

    const request = $.getJSON('api/data_jasa_medis.php', collectParams(), function(res) {
        if (!res.success) {
            alert(res.message || 'Gagal memuat data.');
            return;
        }

        try {
            buildTable(res.columns || [], res.data || [], res.title, res.summary || {});
            $('#tableTitle').text(res.title || 'Detail Tindakan');
            $('#stRows').text(fmtNum(res.summary?.total_rows || 0));
            $('#stTotal').text(fmtRp(res.summary?.total_biaya || 0));
            $('#stDokter').text(fmtRp(res.summary?.jm_dokter || 0));
            $('#stParamedis').text(fmtRp(res.summary?.jm_paramedis || 0));
            $('#infoRows').text(fmtNum(res.summary?.total_rows || 0) + ' baris');
            $('#infoPeriode').text('Periode: ' + (res.periode || '-'));
            $('#btnExcel, #btnPrint').prop('disabled', !(res.data || []).length);
        } catch (err) {
            console.error(err);
            $('#infoRows').text('Gagal membangun tabel');
            alert('Data berhasil diambil, tapi tabel gagal dibangun: ' + err.message);
        }
    }).fail(function(xhr, textStatus) {
        if (textStatus === 'abort') return;
        alert('Gagal memuat data. Detail: ' + (xhr.responseText || xhr.statusText));
        $('#infoRows').text('Gagal memuat data');
    }).always(function() {
        if (activeRequest === request) {
            activeRequest = null;
        }
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Tampilkan');
        if (window.hideGlobalLoading) window.hideGlobalLoading();
    });
    activeRequest = request;
}

$('#jmTabs .nav-link').on('click', function() {
    $('#jmTabs .nav-link').removeClass('active');
    $(this).addClass('active');
    activeTab = $(this).data('tab');
    loadJasaMedis();
});

$('#btnLoad').on('click', loadJasaMedis);
$('#btnExcel').on('click', function() { if (dtJasaMedis) dtJasaMedis.button('.buttons-excel').trigger(); });
$('#btnPrint').on('click', function() { if (dtJasaMedis) dtJasaMedis.button('.buttons-print').trigger(); });
$('#keyword').on('keydown', function(e) { if (e.key === 'Enter') loadJasaMedis(); });
$('#pageSize').on('change', function() {
    if (dtJasaMedis) {
        dtJasaMedis.page.len(Number($(this).val())).draw(false);
    }
});

$(document).ready(function() {
    $('.jm-select').select2({ theme: 'bootstrap-5', placeholder: 'Cari...', allowClear: true, width: '100%' });
    loadJasaMedis();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
