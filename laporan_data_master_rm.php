<?php
$page_title = "Data Master Rekam Medis";
require_once('includes/header.php');

$tgl_awal_default = date('Y-m-01');
$tgl_akhir_default = date('Y-m-d');
?>

<style>
    .dmrm-stat {
        border-radius: 8px;
        padding: 14px 16px;
        min-height: 82px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .dmrm-stat h3 {
        margin: 0;
        font-weight: 700;
        line-height: 1;
    }
    .dmrm-stat small {
        display: block;
        margin-top: 5px;
        opacity: .9;
        font-weight: 600;
    }
    .dmrm-stat i {
        font-size: 1.7rem;
        opacity: .62;
    }
    #tblDataMasterRm thead th {
        background: #f8f9fc;
        font-size: .72rem;
        font-weight: 700;
        color: #303642;
        border-bottom: 2px solid #d9deea !important;
        white-space: nowrap;
        vertical-align: middle;
    }
    #tblDataMasterRm tbody td {
        font-size: .74rem;
        vertical-align: middle;
        white-space: nowrap;
    }
    .dmrm-table-wrap {
        overflow-x: auto;
    }
    .dmrm-note {
        border-left: 4px solid #f6c23e;
        border-radius: 8px;
    }
    .dmrm-wide-card .card-body {
        padding: .75rem;
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-database text-primary me-2"></i> Data Master Rekam Medis</h4>
        <small class="text-muted">Tampilan data detail sesuai format lama untuk export Excel dan olah mandiri unit RM</small>
    </div>
    <a href="kelola_data_rm.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold mb-1">Dari Tgl. Registrasi</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold mb-1">Sampai Tgl. Registrasi</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold mb-1">Status Lanjut</label>
                <select class="form-select form-select-sm" id="statusLanjut">
                    <option value="Ranap" selected>Ranap</option>
                    <option value="Ralan">Ralan</option>
                    <option value="">Semua</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label small fw-bold mb-1">Batas Data</label>
                <select class="form-select form-select-sm" id="limitData">
                    <option value="1000" selected>1.000 baris</option>
                    <option value="3000">3.000 baris</option>
                    <option value="5000">5.000 baris</option>
                    <option value="10000">10.000 baris</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-8">
                <label class="form-label small fw-bold mb-1">Keyword</label>
                <input type="text" class="form-control form-control-sm" id="keyword" placeholder="No rawat, No RM, pasien, SEP...">
            </div>
            <div class="col-lg-1 col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary w-100" id="btnLoad" title="Muat data">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button class="btn btn-sm btn-success w-100" id="btnExport" title="Export Excel" disabled>
                    <i class="fas fa-file-excel"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="dmrm-stat" style="background:linear-gradient(135deg,#4e73df,#224abe);">
            <div><h3 id="stTotal">0</h3><small>Total Baris</small></div>
            <i class="fas fa-table"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dmrm-stat" style="background:linear-gradient(135deg,#1cc88a,#13855c);">
            <div><h3 id="stPeriode">-</h3><small>Periode</small></div>
            <i class="fas fa-calendar-alt"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dmrm-stat" style="background:linear-gradient(135deg,#36b9cc,#258391);">
            <div><h3 id="stKolom">80</h3><small>Kolom Export</small></div>
            <i class="fas fa-columns"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="dmrm-stat" style="background:linear-gradient(135deg,#f6c23e,#dda20a);">
            <div><h3 id="stLimit">1.000</h3><small>Batas Tarikan</small></div>
            <i class="fas fa-download"></i>
        </div>
    </div>
</div>

<div class="alert alert-warning dmrm-note py-2 small" id="warningBox" style="display:none;"></div>

<div class="card shadow mb-4 dmrm-wide-card">
    <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="m-0 font-weight-bold text-primary">Data Master</h6>
        <span class="badge bg-light text-dark border" id="infoRange">Data belum dimuat</span>
    </div>
    <div class="card-body">
        <div class="table-responsive dmrm-table-wrap">
            <table class="table table-bordered table-hover table-striped" id="tblDataMasterRm" style="width:100%">
                <thead><tr id="dmrmHead"></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
let dtDataMaster = null;
let columnsDataMaster = [];

const ID_LANG_DMRM = {
    search: 'Cari:',
    lengthMenu: 'Tampilkan _MENU_ data',
    info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data',
    infoFiltered: '(difilter dari _MAX_ total)',
    zeroRecords: 'Data tidak ditemukan',
    paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
};

function fmtNum(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function fmtShortDate(value) {
    if (!value || !value.includes('-')) return value || '-';
    const part = value.split('-');
    return part[2] + '/' + part[1] + '/' + part[0].slice(-2);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(char) {
        return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' })[char];
    });
}

function renderWarnings(warnings) {
    if (!warnings || warnings.length === 0) {
        $('#warningBox').hide().empty();
        return;
    }
    const html = '<strong>Catatan mapping data:</strong><ul class="mb-0 mt-1">' +
        warnings.map(w => '<li>' + escapeHtml(w) + '</li>').join('') +
        '</ul>';
    $('#warningBox').html(html).show();
}

function buildTable(columns, data) {
    columnsDataMaster = columns || [];
    $('#dmrmHead').html(columnsDataMaster.map(col => '<th>' + escapeHtml(col.title) + '</th>').join(''));

    if (dtDataMaster) {
        dtDataMaster.destroy();
        $('#tblDataMasterRm tbody').empty();
    }

    dtDataMaster = $('#tblDataMasterRm').DataTable({
        data: data || [],
        columns: columnsDataMaster.map(col => ({
            data: col.data,
            defaultContent: '-',
            render: function(value, type) {
                if (type !== 'display') return value;
                return escapeHtml(value || '-');
            }
        })),
        language: ID_LANG_DMRM,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 250, 500],
        scrollX: true,
        scrollCollapse: true,
        order: [[2, 'desc']],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Data Master Rekam Medis',
                filename: function() {
                    return 'Data_Master_RM_' + $('#tglAwal').val().replace(/-/g, '') + '_sd_' + $('#tglAkhir').val().replace(/-/g, '');
                },
                className: 'd-none',
                exportOptions: {
                    columns: ':visible',
                    format: {
                        body: function(data) {
                            return $('<div>').html(data).text();
                        }
                    }
                }
            },
            {
                extend: 'colvis',
                text: 'Kolom',
                className: 'btn btn-sm btn-outline-secondary'
            }
        ]
    });
}

function loadDataMaster() {
    const tglAwal = $('#tglAwal').val();
    const tglAkhir = $('#tglAkhir').val();

    if (!tglAwal || !tglAkhir) {
        alert('Pilih periode tanggal registrasi.');
        return;
    }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $('#btnExport').prop('disabled', true);

    $.getJSON('api/data_master_rm.php', {
        tgl_awal: tglAwal,
        tgl_akhir: tglAkhir,
        status_lanjut: $('#statusLanjut').val(),
        keyword: $('#keyword').val(),
        limit: $('#limitData').val()
    }, function(res) {
        if (!res.success) {
            alert(res.message || 'Gagal memuat Data Master RM.');
            return;
        }

        buildTable(res.columns || [], res.data || []);
        renderWarnings(res.warnings || []);

        $('#stTotal').text(fmtNum(res.summary?.total || 0));
        $('#stPeriode').text(fmtShortDate(tglAwal) + ' - ' + fmtShortDate(tglAkhir));
        $('#stKolom').text(fmtNum((res.columns || []).length));
        $('#stLimit').text(fmtNum($('#limitData').val()));
        $('#infoRange').text('Periode registrasi: ' + tglAwal + ' s/d ' + tglAkhir);
        $('#btnExport').prop('disabled', !(res.data || []).length);
    }).fail(function(xhr) {
        alert('Gagal memuat Data Master RM. Detail: ' + (xhr.responseText || xhr.statusText));
    }).always(function() {
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
    });
}

$('#btnLoad').on('click', loadDataMaster);
$('#btnExport').on('click', function() {
    if (dtDataMaster) {
        dtDataMaster.button('.buttons-excel').trigger();
    }
});
$('#keyword').on('keydown', function(e) {
    if (e.key === 'Enter') {
        loadDataMaster();
    }
});
$('#limitData').on('change', function() {
    $('#stLimit').text(fmtNum($(this).val()));
});

$(document).ready(function() {
    loadDataMaster();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
