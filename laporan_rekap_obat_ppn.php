<?php
/*
 * File: laporan_rekap_obat_ppn.php
 * Modul: Rekap Obat PPN
 */
$page_title = "Rekap Obat PPN";
require_once('includes/header.php');

$tgl_awal_default  = date('Y-m-d');
$tgl_akhir_default = date('Y-m-d');
?>

<style>
    .filter-section .form-label { font-size: .78rem; font-weight: 700; margin-bottom: 3px; }
    .ppn-tabs .nav-link {
        font-size: .82rem;
        font-weight: 700;
        border-radius: 8px 8px 0 0;
    }
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .stat-card {
        border-left: 4px solid var(--accent, #4e73df);
        border-radius: 8px;
        padding: 14px 16px;
        position: relative;
        overflow: hidden;
    }
    .stat-card .value {
        font-size: 1.16rem;
        font-weight: 800;
        color: var(--accent, #4e73df);
        line-height: 1.2;
    }
    .stat-card .label {
        margin-top: 4px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .stat-card .icon-bg {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--accent, #4e73df);
        font-size: 2rem;
        opacity: .12;
    }
    .stat-blue { --accent: #4e73df; }
    .stat-green { --accent: #1cc88a; }
    .stat-cyan { --accent: #36b9cc; }
    .stat-amber { --accent: #f6c23e; }
    #tblPPN thead th { white-space: nowrap; font-size: .78rem; }
    #tblPPN tbody td { vertical-align: middle; font-size: .82rem; }
    #tblPPN tfoot td { font-weight: 800; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-percent text-primary me-2"></i>Rekap Obat PPN</h4>
        <small class="text-muted">Rekap PPN pengadaan, penerimaan, obat rawat jalan, jual bebas, rawat inap, dan piutang obat</small>
    </div>
</div>

<div class="card shadow-sm mb-3 filter-section">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Tanggal Awal</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-lg-4 col-md-8">
                <label class="form-label">Key Word</label>
                <input type="text" class="form-control form-control-sm" id="keyword" placeholder="No nota, pasien/supplier, petugas">
            </div>
            <div class="col-lg-4 col-md-12 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill" id="btnLoad">
                    <i class="fas fa-search me-1"></i>Tampilkan
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="btnReset" title="Reset filter">
                    <i class="fas fa-undo"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs ppn-tabs mb-3" id="ppnTabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-mode="pengadaan">PPN Pengadaan Obat</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-mode="penerimaan">PPN Penerimaan Obat</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-mode="rawat_jalan">PPN Obat Rawat Jalan</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-mode="jual_bebas">PPN Obat Jual Bebas</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-mode="rawat_inap">PPN Obat Rawat Inap</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-mode="piutang">PPN Piutang Obat</button></li>
</ul>

<div class="stat-grid">
    <div class="card shadow-sm stat-card stat-blue">
        <div class="value" id="stRecord">0</div>
        <div class="label text-muted">Jumlah Record</div>
        <div class="icon-bg"><i class="fas fa-list"></i></div>
    </div>
    <div class="card shadow-sm stat-card stat-green">
        <div class="value" id="stTotal">Rp 0</div>
        <div class="label text-muted">Total</div>
        <div class="icon-bg"><i class="fas fa-coins"></i></div>
    </div>
    <div class="card shadow-sm stat-card stat-amber">
        <div class="value" id="stPPN">Rp 0</div>
        <div class="label text-muted">PPN</div>
        <div class="icon-bg"><i class="fas fa-percent"></i></div>
    </div>
    <div class="card shadow-sm stat-card stat-cyan">
        <div class="value" id="stTotalPPN">Rp 0</div>
        <div class="label text-muted">Total + PPN</div>
        <div class="icon-bg"><i class="fas fa-file-invoice-dollar"></i></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary" id="tableTitle"><i class="fas fa-table me-2"></i>Data PPN Obat</h6>
        <small class="text-muted" id="infoRange"></small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="tblPPN" style="width:100%">
                <thead class="table-dark"></thead>
                <tbody></tbody>
                <tfoot></tfoot>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
let dt = null;
let currentMode = 'pengadaan';
let currentColumns = [];

const ID_LANG = {
    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data', infoFiltered: '(difilter dari _MAX_ total)', zeroRecords: 'Data tidak ditemukan',
    paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
};

const COLUMN_LABELS = {
    tanggal: 'Tanggal',
    no_nota: 'No.Nota',
    supplier: 'Suplier',
    nama_pasien: 'Nama Pasien',
    petugas: 'Petugas',
    total: 'Total',
    ppn: 'PPN',
    total_ppn: 'Total+PPN'
};

function fmtRp(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function fmtPlain(n) {
    return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function escHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function buildTableShell(columns) {
    if (dt) {
        dt.destroy();
        dt = null;
    }

    const ths = columns.map(c => '<th' + (['total','ppn','total_ppn'].includes(c) ? ' class="num"' : '') + '>' + COLUMN_LABELS[c] + '</th>').join('');
    const totalLabelSpan = Math.max(columns.length - 2, 1);
    $('#tblPPN thead').html('<tr><th style="width:42px;">No</th>' + ths + '</tr>');
    $('#tblPPN tbody').empty();
    $('#tblPPN tfoot').html(
        '<tr>' +
        '<td colspan="' + totalLabelSpan + '" class="text-end">TOTAL</td>' +
        '<td class="num" id="ftTotal">Rp 0</td>' +
        '<td class="num" id="ftPPN">Rp 0</td>' +
        '<td class="num" id="ftTotalPPN">Rp 0</td>' +
        '</tr>'
    );

    currentColumns = columns;
    dt = $('#tblPPN').DataTable({
        language: ID_LANG,
        pageLength: 50,
        order: [[1, 'asc'], [2, 'asc']],
        dom: "<'row mb-2'<'col-sm-6'B><'col-sm-6'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i>Export Excel',
                className: 'btn btn-success btn-sm',
                title: 'Rekap_Obat_PPN',
                footer: true,
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-1"></i>Print',
                className: 'btn btn-outline-secondary btn-sm',
                footer: true,
                exportOptions: { columns: ':visible' }
            }
        ],
        columnDefs: [
            { orderable: false, targets: 0 },
            { className: 'num', targets: columns.map((c, i) => ['total','ppn','total_ppn'].includes(c) ? i + 1 : null).filter(v => v !== null) }
        ]
    });
}

function updateSummary(summary) {
    $('#stRecord').text(fmtPlain(summary.total_record));
    $('#stTotal').text(fmtRp(summary.total));
    $('#stPPN').text(fmtRp(summary.ppn));
    $('#stTotalPPN').text(fmtRp(summary.total_ppn));
    $('#ftTotal').text(fmtRp(summary.total));
    $('#ftPPN').text(fmtRp(summary.ppn));
    $('#ftTotalPPN').text(fmtRp(summary.total_ppn));
}

function rowValue(row, column) {
    if (['total','ppn','total_ppn'].includes(column)) {
        return fmtRp(row[column]);
    }
    return escHtml(row[column]);
}

function loadData() {
    const params = {
        mode: currentMode,
        tgl_awal: $('#tglAwal').val(),
        tgl_akhir: $('#tglAkhir').val(),
        keyword: $('#keyword').val()
    };

    if (!params.tgl_awal || !params.tgl_akhir) {
        alert('Pilih periode tanggal terlebih dahulu.');
        return;
    }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Memuat');

    $.getJSON('api/data_rekap_obat_ppn.php', params, function(res) {
        if (res.error) {
            alert(res.error);
            return;
        }

        const columns = res.columns || [];
        if (columns.join('|') !== currentColumns.join('|')) {
            buildTableShell(columns);
        }

        $('#tableTitle').html('<i class="fas fa-table me-2"></i>' + escHtml(res.title || 'Data PPN Obat'));
        $('#infoRange').text('Periode: ' + params.tgl_awal + ' s.d. ' + params.tgl_akhir);

        dt.clear();
        (res.data || []).forEach(function(row, idx) {
            dt.row.add([idx + 1].concat(columns.map(c => rowValue(row, c))));
        });
        dt.draw();

        updateSummary(res.summary || {});
    }).fail(function(xhr) {
        const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Gagal memuat data rekap obat PPN.';
        alert(msg);
    }).always(function() {
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-search me-1"></i>Tampilkan');
    });
}

$('#ppnTabs button').on('click', function() {
    $('#ppnTabs button').removeClass('active');
    $(this).addClass('active');
    currentMode = $(this).data('mode');
    currentColumns = [];
    loadData();
});

$('#btnLoad').on('click', loadData);
$('#keyword').on('keypress', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        loadData();
    }
});
$('#btnReset').on('click', function() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    $('#tglAwal').val(y + '-' + m + '-' + d);
    $('#tglAkhir').val(y + '-' + m + '-' + d);
    $('#keyword').val('');
    loadData();
});

$(document).ready(function() {
    buildTableShell(['tanggal', 'no_nota', 'supplier', 'petugas', 'total', 'ppn', 'total_ppn']);
    loadData();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
