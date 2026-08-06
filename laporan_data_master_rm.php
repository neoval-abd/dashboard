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
        line-height: 1.35;
        white-space: nowrap;
        vertical-align: middle;
    }
    #tblDataMasterRm tbody td {
        font-size: .74rem;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #tblDataMasterRm {
        table-layout: fixed;
    }
    #tblDataMasterRm th,
    #tblDataMasterRm td {
        padding: .55rem .65rem;
        box-sizing: border-box;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #tblDataMasterRm .dmrm-cell-text {
        display: block;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dmrm-table-wrap {
        overflow: hidden;
    }
    .dmrm-note {
        border-left: 4px solid #f6c23e;
        border-radius: 8px;
    }
    .dmrm-wide-card .card-body {
        padding: .75rem;
    }
    .dmrm-wide-card .dataTables_wrapper .row:first-child {
        row-gap: .75rem;
    }
    .dmrm-wide-card .dt-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
    }
    .dmrm-wide-card .dt-buttons .btn {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        min-height: 30px;
        padding: .25rem .65rem;
        font-size: .78rem;
        font-weight: 600;
        line-height: 1.2;
    }
    .dt-button-collection {
        max-height: min(72vh, 520px);
        overflow-y: auto !important;
        overflow-x: hidden;
        scrollbar-width: thin;
    }
    .dt-button-collection .dt-button {
        display: flex !important;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        width: 100%;
        min-width: 220px;
        white-space: nowrap;
    }
    .dmrm-wide-card .dataTables_filter {
        margin: 0;
        text-align: right;
    }
    .dmrm-wide-card .dataTables_filter label {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        margin: 0;
        font-size: .8rem;
        font-weight: 600;
    }
    .dmrm-wide-card .dataTables_filter input {
        width: min(260px, 56vw);
        margin-left: 0;
    }
    .dmrm-wide-card .dataTables_scrollHeadInner table,
    .dmrm-wide-card .dataTables_scrollBody table {
        margin-bottom: 0 !important;
        table-layout: fixed !important;
    }
    .dmrm-th-filter {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        max-width: 100%;
    }
    .dmrm-th-title {
        display: inline-block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .dmrm-filter-btn {
        flex: 0 0 auto;
        width: 24px;
        height: 22px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #cfd6e6;
        border-radius: 4px;
        background: #fff;
        color: #5a5c69;
        vertical-align: middle;
    }
    .dmrm-filter-btn.active {
        background: #4e73df;
        border-color: #4e73df;
        color: #fff;
    }
    .dmrm-filter-menu {
        position: absolute;
        z-index: 2050;
        width: 280px;
        max-width: calc(100vw - 32px);
        background: #fff;
        color: #2f3542;
        border: 1px solid #d1d3e2;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,.16);
        padding: 10px;
        display: none;
    }
    .dmrm-filter-menu.open {
        display: block;
    }
    .dmrm-filter-options {
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e3e6f0;
        border-radius: 6px;
        padding: 6px;
        background: #fff;
        color: #2f3542;
    }
    .dmrm-filter-check {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        padding: 4px 2px;
        font-size: .78rem;
        cursor: pointer;
        color: #2f3542;
    }
    .dmrm-filter-check span {
        color: #2f3542;
        overflow-wrap: anywhere;
    }
    .dmrm-filter-actions {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin-top: 10px;
    }
    .dmrm-filter-search {
        margin-bottom: 8px;
        background: #fff;
        color: #2f3542;
        border-color: #b7c7f4;
    }
    .dmrm-filter-search::placeholder {
        color: #6c757d;
    }
    body.dark-mode .dmrm-filter-menu,
    body.dark-mode .dmrm-filter-options,
    body.dark-mode .dmrm-filter-check,
    body.dark-mode .dmrm-filter-check span,
    body.dark-mode .dmrm-filter-search,
    [data-bs-theme="dark"] .dmrm-filter-menu,
    [data-bs-theme="dark"] .dmrm-filter-options,
    [data-bs-theme="dark"] .dmrm-filter-check,
    [data-bs-theme="dark"] .dmrm-filter-check span,
    [data-bs-theme="dark"] .dmrm-filter-search {
        background: #fff;
        color: #2f3542;
    }
    html.theme-glass-solid #tblDataMasterRm thead th,
    html.theme-glass-animated #tblDataMasterRm thead th {
        background: rgba(15, 23, 42, .9) !important;
        color: #f8fafc !important;
        border-color: rgba(255, 255, 255, .12) !important;
    }
    html.theme-glass-solid .dmrm-wide-card .dt-buttons .btn,
    html.theme-glass-animated .dmrm-wide-card .dt-buttons .btn {
        background: #334155 !important;
        border-color: #64748b !important;
        color: #f8fafc !important;
    }
    @media (max-width: 575.98px) {
        .dmrm-wide-card .dataTables_filter,
        .dmrm-wide-card .dataTables_filter label,
        .dmrm-wide-card .dataTables_filter input {
            width: 100%;
        }
        .dmrm-wide-card .dataTables_filter {
            text-align: left;
        }
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
        <div class="dmrm-table-wrap">
            <table class="table table-bordered table-hover table-striped" id="tblDataMasterRm">
                <thead><tr id="dmrmHead"></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<div id="dmrmFilterMenuLayer"></div>

<?php ob_start(); ?>
<script>
let dtDataMaster = null;
let columnsDataMaster = [];
let dataDataMaster = [];
let selectedColumnFilters = {};
let openFilterKey = null;
let filterValueCache = {};
const MAX_FILTER_OPTIONS = 300;

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

function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(value);
    }

    return String(value).replace(/["\\]/g, '\\$&');
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

function getColumnStyle(title) {
    const normalized = String(title || '').toUpperCase();
    const explicit = {
        'BATAL': 104,
        'NO.REGISTRASI': 170,
        'NO.PERAWATAN': 170,
        'NO.RI': 154,
        'NO.RM': 132,
        'NAMA PASIEN': 210,
        'IBU KANDUNG': 190,
        'TGL.LAHIR': 150,
        'UMUR': 118,
        'KEL.UMUR': 150,
        'JNS.KELAMIN': 168,
        'GOL.DARAH': 154,
        'SUKU': 122,
        'BAHASA': 136,
        'STATUS KAWIN': 170,
        'LOS': 100,
        'KELAS': 112
    };
    const titleWidth = String(title || '').length * 9 + 76;
    const width = explicit[normalized] || Math.max(128, Math.min(280, titleWidth));

    return { width: width + 'px', className: 'dmrm-ellipsis' };
}

function getColumnFilterKey(column) {
    return column.data;
}

function getColumnFilterTitle(column) {
    return column.title || column.data;
}

function getFilterConfig() {
    return columnsDataMaster.map(column => ({
        key: getColumnFilterKey(column),
        label: getColumnFilterTitle(column)
    }));
}

function getSelectedFilterList(key) {
    return selectedColumnFilters[key] || [];
}

function setFilterValue(key, value, checked) {
    const values = new Set(getSelectedFilterList(key));
    if (checked) {
        values.add(value);
    } else {
        values.delete(value);
    }
    selectedColumnFilters[key] = Array.from(values);
}

function filterMatches(row) {
    return getFilterConfig().every(function(filter) {
        const selected = getSelectedFilterList(filter.key);
        if (selected.length === 0) {
            return true;
        }

        return selected.includes(String(row[filter.key] || '-'));
    });
}

function updateFilterButton(key) {
    const selected = getSelectedFilterList(key);
    const $button = $(`.dmrm-filter-btn[data-key="${cssEscape(key)}"]`);

    if (selected.length === 0) {
        $button.removeClass('active');
        $button.attr('title', 'Filter ' + ($button.data('label') || key));
        return;
    }

    $button.addClass('active');
    $button.attr('title', ($button.data('label') || key) + ' terfilter: ' + selected.length + ' pilihan');
}

function applyColumnFilters() {
    const activeKey = openFilterKey;
    getFilterConfig().forEach(function(filter) {
        updateFilterButton(filter.key);
    });

    if (dtDataMaster) {
        dtDataMaster.draw();
    }
    if (activeKey) {
        repositionFilterMenu(activeKey);
    }
}

function pruneSelectedFilters() {
    Object.keys(selectedColumnFilters).forEach(function(key) {
        if (!selectedColumnFilters[key] || selectedColumnFilters[key].length === 0) {
            return;
        }

        const values = new Set(dataDataMaster.map(row => String(row[key] || '-')));
        selectedColumnFilters[key] = selectedColumnFilters[key].filter(value => values.has(value));
    });
}

function closeFilterMenus(resetKey = true) {
    $('.dmrm-filter-menu').removeClass('open');
    if (resetKey) {
        openFilterKey = null;
    }
}

function repositionFilterMenu(key) {
    const $button = $(`.dmrm-filter-btn[data-key="${cssEscape(key)}"]`).filter(':visible').first();
    const $menu = $(`.dmrm-filter-menu[data-key="${cssEscape(key)}"]`);
    if (!$button.length || !$menu.length) {
        return;
    }

    const offset = $button.offset();
    const menuWidth = $menu.outerWidth() || 280;
    const left = Math.max(12, Math.min(offset.left, $(window).width() - menuWidth - 12));

    $menu.css({
        top: offset.top + $button.outerHeight() + 6,
        left: left
    });
}

function openFilterMenu(key) {
    closeFilterMenus(false);
    openFilterKey = key;
    renderFilterOptions(key);
    repositionFilterMenu(key);
    const $menu = $(`.dmrm-filter-menu[data-key="${cssEscape(key)}"]`);
    $menu.addClass('open');
    $menu.find('.dmrm-filter-search').val('').focus();
}

function sortFilterValues(key, values) {
    if (key === 'kel_umur') {
        const order = ['0-7 hari', '8-28 hari', '29 hari - < 1 tahun', '1-4 tahun', '5-14 tahun', '15-24 tahun', '25-44 tahun', '45-64 tahun', '>= 65 tahun', '-'];
        return values.sort((a, b) => {
            const ia = order.indexOf(a);
            const ib = order.indexOf(b);
            if (ia !== -1 || ib !== -1) {
                return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
            }
            return a.localeCompare(b);
        });
    }

    return values.sort((a, b) => a.localeCompare(b, 'id-ID', { numeric: true, sensitivity: 'base' }));
}

function getFilterValues(key) {
    if (!filterValueCache[key]) {
        filterValueCache[key] = sortFilterValues(key, Array.from(new Set(dataDataMaster.map(row => String(row[key] || '-')))));
    }

    return filterValueCache[key];
}

function buildFilterMenus() {
    const html = getFilterConfig().map(function(filter) {
        return `
            <div class="dmrm-filter-menu" data-key="${escapeHtml(filter.key)}">
                <input type="text" class="form-control form-control-sm dmrm-filter-search" placeholder="Cari...">
                <div class="dmrm-filter-options"></div>
                <div class="dmrm-filter-actions">
                    <button type="button" class="btn btn-sm btn-light border btn-filter-close">Close</button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-filter-clear">Clear</button>
                </div>
            </div>
        `;
    }).join('');

    $('#dmrmFilterMenuLayer').html(html);
}

function renderFilterOptions(key, keyword = '') {
    const filter = getFilterConfig().find(item => item.key === key);
    if (!filter) {
        return;
    }

    const selected = getSelectedFilterList(key);
    const normalizedKeyword = String(keyword || '').toLowerCase();
    const allValues = getFilterValues(key);
    selectedColumnFilters[key] = selected.filter(value => allValues.includes(value));

    let sortedValues = allValues;
    if (normalizedKeyword !== '') {
        sortedValues = sortedValues.filter(value => value.toLowerCase().includes(normalizedKeyword));
    }

    const selectedFirst = selectedColumnFilters[key].filter(value => sortedValues.includes(value));
    const visibleValues = Array.from(new Set(selectedFirst.concat(sortedValues))).slice(0, MAX_FILTER_OPTIONS);
    const hiddenCount = Math.max(0, sortedValues.length - visibleValues.length);
    const options = visibleValues.map(function(value) {
        const checked = selectedColumnFilters[key].includes(value) ? 'checked' : '';
        return `
            <label class="dmrm-filter-check">
                <input type="checkbox" value="${escapeHtml(value)}" ${checked}>
                <span>${escapeHtml(value)}</span>
            </label>
        `;
    }).join('');
    const note = hiddenCount > 0
        ? '<div class="text-muted small px-1 mt-1">Menampilkan ' + fmtNum(visibleValues.length) + ' dari ' + fmtNum(sortedValues.length) + ' pilihan. Ketik pencarian untuk mempersempit.</div>'
        : '';

    const $filter = $(`.dmrm-filter-menu[data-key="${cssEscape(key)}"]`);
    $filter.find('.dmrm-filter-options').html(options || '<div class="text-muted small px-1">Tidak ada pilihan</div>');
    $filter.find('.dmrm-filter-options').append(note);
    updateFilterButton(key);
}

$.fn.dataTable.ext.search.push(function(settings, searchData, index, rowData) {
    if (settings.nTable.id !== 'tblDataMasterRm') {
        return true;
    }

    return filterMatches(rowData || dataDataMaster[index] || {});
});

function buildColumnGroup(columnStyles) {
    return '<colgroup>' + columnStyles.map(style => '<col style="width:' + style.width + '">').join('') + '</colgroup>';
}

function syncColumnLayout(columnStyles) {
    const tableWidth = columnStyles.reduce((total, style) => total + parseInt(style.width, 10), 0);
    const colGroup = buildColumnGroup(columnStyles);
    $('.dmrm-wide-card .dataTables_scrollHeadInner').css({
        width: tableWidth + 'px',
        minWidth: tableWidth + 'px'
    });
    const $tables = $('#tblDataMasterRm')
        .add($('.dmrm-wide-card .dataTables_scrollHead table'))
        .add($('.dmrm-wide-card .dataTables_scrollBody table'));

    $tables.each(function() {
        const $table = $(this);
        $table.children('colgroup').remove();
        $table.prepend(colGroup);
        $table.css({
            width: tableWidth + 'px',
            minWidth: tableWidth + 'px',
            tableLayout: 'fixed'
        });
    });
}

function buildTable(columns, data) {
    columnsDataMaster = columns || [];
    dataDataMaster = data || [];
    filterValueCache = {};
    const columnStyles = columnsDataMaster.map(col => getColumnStyle(col.title));

    if (dtDataMaster) {
        dtDataMaster.destroy();
        $('#tblDataMasterRm colgroup').remove();
        $('#tblDataMasterRm tbody').empty();
    }

    $('#dmrmHead').html(columnsDataMaster.map((col, index) => {
        const style = columnStyles[index];
        const key = getColumnFilterKey(col);
        const title = getColumnFilterTitle(col);
        return '<th class="' + style.className + '" style="width:' + style.width + '">' +
            '<span class="dmrm-th-filter">' +
                '<span class="dmrm-th-title" title="' + escapeHtml(title) + '">' + escapeHtml(title) + '</span>' +
                '<button type="button" class="dmrm-filter-btn" data-key="' + escapeHtml(key) + '" data-label="' + escapeHtml(title) + '" title="Filter ' + escapeHtml(title) + '">' +
                    '<i class="fas fa-filter"></i>' +
                '</button>' +
            '</span>' +
        '</th>';
    }).join(''));
    buildFilterMenus();
    syncColumnLayout(columnStyles);

    dtDataMaster = $('#tblDataMasterRm').DataTable({
        data: dataDataMaster,
        columns: columnsDataMaster.map((col, index) => {
            const style = columnStyles[index];
            return {
                data: col.data,
                defaultContent: '-',
                width: style.width,
                className: style.className,
                render: function(value, type) {
                    if (type !== 'display') return value;
                    const displayValue = escapeHtml(value || '-');
                    return '<span class="dmrm-cell-text" title="' + displayValue + '">' + displayValue + '</span>';
                }
            };
        }),
        language: ID_LANG_DMRM,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 250, 500],
        scrollX: true,
        scrollCollapse: true,
        autoWidth: false,
        columnDefs: columnStyles.map((style, index) => ({
            targets: index,
            width: style.width,
            className: style.className
        })),
        order: [[2, 'desc']],
        dom: "<'row align-items-center mb-2'<'col-sm-6'B><'col-sm-6'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row align-items-center mt-2'<'col-sm-5'i><'col-sm-7'p>>",
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
                text: '<i class="fas fa-columns"></i><span>  Kolom</span>',
                className: 'btn btn-secondary btn-sm',
                columns: ':not(.noVis)'
            }
        ],
        initComplete: function() {
            pruneSelectedFilters();
            applyColumnFilters();
            syncColumnLayout(columnStyles);
            this.api().columns.adjust();
            syncColumnLayout(columnStyles);
        }
    });

    dtDataMaster.on('column-visibility.dt', function() {
        getFilterConfig().forEach(function(filter) {
            updateFilterButton(filter.key);
        });
        syncColumnLayout(columnStyles);
        dtDataMaster.columns.adjust();
        syncColumnLayout(columnStyles);
    });

    dtDataMaster.on('draw.dt', function() {
        getFilterConfig().forEach(function(filter) {
            updateFilterButton(filter.key);
        });
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
$(document).on('click', '.dmrm-filter-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const key = $(this).data('key');
    const $menu = $(`.dmrm-filter-menu[data-key="${cssEscape(key)}"]`);

    if ($menu.hasClass('open')) {
        closeFilterMenus();
        return;
    }

    openFilterMenu(key);
});
$('#dmrmFilterMenuLayer').on('click', '.dmrm-filter-menu', function(e) {
    e.stopPropagation();
});
$('#dmrmFilterMenuLayer').on('change', '.dmrm-filter-options input[type="checkbox"]', function() {
    const key = $(this).closest('.dmrm-filter-menu').data('key');
    setFilterValue(key, String($(this).val()), this.checked);
    applyColumnFilters();
});
$('#dmrmFilterMenuLayer').on('click', '.btn-filter-clear', function() {
    const key = $(this).closest('.dmrm-filter-menu').data('key');
    selectedColumnFilters[key] = [];
    renderFilterOptions(key, $(this).closest('.dmrm-filter-menu').find('.dmrm-filter-search').val());
    applyColumnFilters();
});
$('#dmrmFilterMenuLayer').on('click', '.btn-filter-close', function() {
    closeFilterMenus();
});
$('#dmrmFilterMenuLayer').on('input', '.dmrm-filter-search', function() {
    const key = $(this).closest('.dmrm-filter-menu').data('key');
    renderFilterOptions(key, $(this).val());
});
$(document).on('click', function() {
    closeFilterMenus();
});
$('#limitData').on('change', function() {
    $('#stLimit').text(fmtNum($(this).val()));
});

$(document).ready(function() {
    loadDataMaster();
});

$(window).on('resize', function() {
    if (dtDataMaster) {
        dtDataMaster.columns.adjust();
    }
    if (openFilterKey) {
        repositionFilterMenu(openFilterKey);
    }
});
$(window).on('scroll', function() {
    if (openFilterKey) {
        repositionFilterMenu(openFilterKey);
    }
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
