<?php
/*
 * File: laporan_rl38_lab.php
 * Modul: RL 3.8 - Rekapitulasi Kegiatan Pelayanan Laboratorium
 * Deskripsi: Menampilkan tab Item Pemeriksaan laboratorium Khanza dalam format web.
 */
$page_title = "RL 3.8 Laboratorium";
require_once('includes/header.php');

$tgl_awal_default = date('Y-m-01');
$tgl_akhir_default = date('Y-m-d');
?>

<style>
    .rl38-stat {
        border-radius: 8px;
        padding: 14px 16px;
        min-height: 88px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .rl38-stat h3 {
        margin: 0;
        font-weight: 700;
        line-height: 1;
    }
    .rl38-stat small {
        display: block;
        margin-top: 5px;
        opacity: .9;
        font-weight: 600;
    }
    .rl38-stat i {
        font-size: 1.8rem;
        opacity: .58;
    }
    table.dataTable thead th {
        background: #f8f9fc;
        font-size: .78rem;
        font-weight: 700;
        color: #333;
        border-bottom: 2px solid #dee2e6 !important;
        white-space: nowrap;
    }
    table.dataTable tbody td {
        font-size: .78rem;
        vertical-align: middle;
        white-space: nowrap;
    }
    .rl38-table-wrap {
        overflow-x: auto;
    }
    .rl38-filter {
        position: relative;
    }
    .rl38-th-filter {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .rl38-filter-btn {
        width: 26px;
        height: 24px;
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
    .rl38-filter-btn.active {
        background: #4e73df;
        border-color: #4e73df;
        color: #fff;
    }
    .rl38-filter-menu {
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
    .rl38-filter-menu.open {
        display: block;
    }
    .rl38-filter-options {
        max-height: 220px;
        overflow-y: auto;
        border: 1px solid #e3e6f0;
        border-radius: 6px;
        padding: 6px;
        background: #fff;
        color: #2f3542;
    }
    .rl38-filter-check {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 0;
        padding: 4px 2px;
        font-size: .78rem;
        cursor: pointer;
        color: #2f3542;
    }
    .rl38-filter-check span {
        color: #2f3542;
    }
    .rl38-filter-actions {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        margin-top: 10px;
    }
    .rl38-filter-search {
        margin-bottom: 8px;
        background: #fff;
        color: #2f3542;
        border-color: #b7c7f4;
    }
    .rl38-filter-search::placeholder {
        color: #6c757d;
    }
    body.dark-mode .rl38-filter-menu,
    body.dark-mode .rl38-filter-options,
    body.dark-mode .rl38-filter-check,
    body.dark-mode .rl38-filter-check span,
    body.dark-mode .rl38-filter-search,
    [data-bs-theme="dark"] .rl38-filter-menu,
    [data-bs-theme="dark"] .rl38-filter-options,
    [data-bs-theme="dark"] .rl38-filter-check,
    [data-bs-theme="dark"] .rl38-filter-check span,
    [data-bs-theme="dark"] .rl38-filter-search {
        background: #fff;
        color: #2f3542;
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-flask text-success me-2"></i> RL 3.8 - Rekapitulasi Kegiatan Pelayanan Laboratorium</h4>
        <small class="text-muted">Tampilan web khusus tab Item Pemeriksaan dari data laboratorium Khanza</small>
    </div>
    <a href="kelola_data_rm.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Dari Tgl. Periksa</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Sampai Tgl. Periksa</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">No. Rawat</label>
                <input type="text" class="form-control form-control-sm" id="noRawat" placeholder="Opsional">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">No. RM</label>
                <input type="text" class="form-control form-control-sm" id="noRm" placeholder="Opsional">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Keyword</label>
                <input type="text" class="form-control form-control-sm" id="keyword" placeholder="Pasien, item, dokter...">
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-primary w-100" id="btnLoad" title="Muat data">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-success w-100" id="btnExport" title="Export Excel" disabled>
                    <i class="fas fa-file-excel"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="rl38-stat" style="background:linear-gradient(135deg,#4e73df,#224abe);">
            <div><h3 id="stItem">0</h3><small>Total Item Pemeriksaan</small></div>
            <i class="fas fa-vials"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="rl38-stat" style="background:linear-gradient(135deg,#1cc88a,#13855c);">
            <div><h3 id="stPasien">0</h3><small>Pasien Unik</small></div>
            <i class="fas fa-user-injured"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="rl38-stat" style="background:linear-gradient(135deg,#f6c23e,#dda20a);">
            <div><h3 id="stKunjungan">0</h3><small>Kunjungan Unik</small></div>
            <i class="fas fa-notes-medical"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="rl38-stat" style="background:linear-gradient(135deg,#36b9cc,#258391);">
            <div><h3 id="stTopItem">-</h3><small>Item Terbanyak</small></div>
            <i class="fas fa-chart-bar"></i>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="m-0 font-weight-bold text-primary">Item Pemeriksaan Laboratorium</h6>
        <span class="badge bg-light text-dark border" id="periodeInfo">-</span>
    </div>
    <div class="card-body">
        <div class="table-responsive rl38-table-wrap">
            <table class="table table-bordered table-hover table-striped" id="tblRL38" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. Rawat</th>
                        <th>No. RM</th>
                        <th>Pasien</th>
                        <th><span class="rl38-th-filter">JK <button type="button" class="rl38-filter-btn" data-key="jk" data-label="Jenis Kelamin" title="Filter Jenis Kelamin"><i class="fas fa-filter"></i></button></span></th>
                        <th>Umur</th>
                        <th><span class="rl38-th-filter">Kelompok Umur <button type="button" class="rl38-filter-btn" data-key="kelompok_umur" data-label="Kel. Umur" title="Filter Kelompok Umur"><i class="fas fa-filter"></i></button></span></th>
                        <th>Tgl. Periksa</th>
                        <th>Jam</th>
                        <th><span class="rl38-th-filter">Pemeriksaan <button type="button" class="rl38-filter-btn" data-key="pemeriksaan" data-label="Pemeriksaan" title="Filter Pemeriksaan"><i class="fas fa-filter"></i></button></span></th>
                        <th><span class="rl38-th-filter">Item Pemeriksaan <button type="button" class="rl38-filter-btn" data-key="item_pemeriksaan" data-label="Item Pemeriksaan" title="Filter Item Pemeriksaan"><i class="fas fa-filter"></i></button></span></th>
                        <th>Hasil</th>
                        <th>Satuan</th>
                        <th>Nilai Rujukan</th>
                        <th>Keterangan</th>
                        <th><span class="rl38-th-filter">Ruang <button type="button" class="rl38-filter-btn" data-key="ruang" data-label="Ruang" title="Filter Ruang"><i class="fas fa-filter"></i></button></span></th>
                        <th>Petugas</th>
                        <th>Dokter Perujuk</th>
                        <th>Penanggung Jawab</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
<div id="filterMenuLayer"></div>

<?php ob_start(); ?>
<script>
let dtRL38;
let dataRL38 = [];
let selectedFilters = {};
let openFilterKey = null;

const FILTER_CONFIG = [
    { key: 'kelompok_umur', label: 'Kel. Umur' },
    { key: 'jk', label: 'Jenis Kelamin' },
    { key: 'ruang', label: 'Ruang' },
    { key: 'pemeriksaan', label: 'Pemeriksaan' },
    { key: 'item_pemeriksaan', label: 'Item Pemeriksaan' }
];

const ID_LANG_RL38 = {
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

function fmtTanggal(value) {
    if (!value || value === '-') return '-';
    const parts = value.split(' ');
    const datePart = parts[0] && parts[0].includes('-') ? parts[0].split('-').reverse().join('-') : parts[0];
    return parts[1] ? datePart + ' ' + parts[1] : datePart;
}

function shortText(value, maxLength) {
    const text = String(value || '-');
    return text.length > maxLength ? text.substring(0, maxLength - 1) + '...' : text;
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(char) {
        return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' })[char];
    });
}

function valueLabel(key, value) {
    if (key === 'jk') {
        if (value === 'L') return 'Laki-laki';
        if (value === 'P') return 'Perempuan';
    }

    return value || '-';
}

function getSelectedList(key) {
    return selectedFilters[key] || [];
}

function setFilterValue(key, value, checked) {
    const values = new Set(getSelectedList(key));
    if (checked) {
        values.add(value);
    } else {
        values.delete(value);
    }
    selectedFilters[key] = Array.from(values);
}

function filterMatches(row) {
    return FILTER_CONFIG.every(function(filter) {
        const selected = getSelectedList(filter.key);
        if (selected.length === 0) {
            return true;
        }

        return selected.includes(String(row[filter.key] || '-'));
    });
}

function updateFilterButton(key) {
    const selected = getSelectedList(key);
    const $button = $(`.rl38-filter-btn[data-key="${key}"]`);

    if (selected.length === 0) {
        $button.removeClass('active');
        $button.attr('title', 'Filter ' + $button.data('label'));
        return;
    }

    $button.addClass('active');
    $button.attr('title', $button.data('label') + ' terfilter: ' + selected.length + ' pilihan');
}

function applyColumnFilters() {
    const activeKey = openFilterKey;
    FILTER_CONFIG.forEach(function(filter) {
        updateFilterButton(filter.key);
    });
    if (dtRL38) {
        dtRL38.draw();
    }
    if (activeKey) {
        repositionFilterMenu(activeKey);
    }
}

function buildFilterBar() {
    const html = FILTER_CONFIG.map(function(filter) {
        return `
            <div class="rl38-filter-menu" data-key="${filter.key}">
                <input type="text" class="form-control form-control-sm rl38-filter-search" placeholder="Cari...">
                <div class="rl38-filter-options"></div>
                <div class="rl38-filter-actions">
                    <button type="button" class="btn btn-sm btn-light border btn-filter-close">Close</button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-filter-clear">Clear</button>
                </div>
            </div>
        `;
    }).join('');

    $('#filterMenuLayer').html(html);
}

function closeFilterMenus(resetKey = true) {
    $('.rl38-filter-menu').removeClass('open');
    if (resetKey) {
        openFilterKey = null;
    }
}

function repositionFilterMenu(key) {
    const $button = $(`.rl38-filter-btn[data-key="${key}"]`);
    const $menu = $(`.rl38-filter-menu[data-key="${key}"]`);
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
    repositionFilterMenu(key);
    const $menu = $(`.rl38-filter-menu[data-key="${key}"]`);
    $menu.addClass('open');
    $menu.find('.rl38-filter-search').val('').trigger('input').focus();
}

function sortFilterValues(key, values) {
    if (key === 'kelompok_umur') {
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

    return values.sort((a, b) => valueLabel(key, a).localeCompare(valueLabel(key, b)));
}

function renderFilterOptions() {
    FILTER_CONFIG.forEach(function(filter) {
        const current = new Set(getSelectedList(filter.key));
        const values = Array.from(new Set(dataRL38.map(row => String(row[filter.key] || '-'))));
        const sortedValues = sortFilterValues(filter.key, values);
        selectedFilters[filter.key] = getSelectedList(filter.key).filter(value => values.includes(value));
        const options = sortedValues.map(function(value) {
            const checked = selectedFilters[filter.key].includes(value) ? 'checked' : '';
            return `
                <label class="rl38-filter-check">
                    <input type="checkbox" value="${escapeHtml(value)}" ${checked}>
                    <span>${escapeHtml(valueLabel(filter.key, value))}</span>
                </label>
            `;
        }).join('');

        const $filter = $(`.rl38-filter-menu[data-key="${filter.key}"]`);
        $filter.find('.rl38-filter-options').html(options || '<div class="text-muted small px-1">Tidak ada pilihan</div>');
        updateFilterButton(filter.key);
    });
}

$.fn.dataTable.ext.search.push(function(settings, searchData, index, rowData) {
    if (settings.nTable.id !== 'tblRL38') {
        return true;
    }

    return filterMatches(rowData || {});
});

function renderSummary(summary) {
    const pemeriksaan = summary.pemeriksaan || {};
    const topItem = Object.keys(pemeriksaan)[0] || '-';

    $('#stItem').text(fmtNum(summary.total_item || 0));
    $('#stPasien').text(fmtNum(summary.total_pasien || 0));
    $('#stKunjungan').text(fmtNum(summary.total_kunjungan || 0));
    $('#stTopItem').text(shortText(topItem, 18));
}

function initTable() {
    if (dtRL38) {
        dtRL38.clear();
        dtRL38.rows.add(dataRL38);
        dtRL38.draw();
        return;
    }

    dtRL38 = $('#tblRL38').DataTable({
        data: dataRL38,
        language: ID_LANG_RL38,
        pageLength: 25,
        scrollX: true,
        order: [[6, 'desc'], [7, 'desc']],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'RL 3.8 Item Pemeriksaan Laboratorium',
                filename: function() {
                    return 'RL38_Item_Lab_' + $('#tglAwal').val().replace(/-/g, '') + '_sd_' + $('#tglAkhir').val().replace(/-/g, '');
                },
                className: 'd-none',
                exportOptions: { columns: ':visible' }
            }
        ],
        columns: [
            { data: null, render: (d,t,r,i) => i.row + 1 },
            { data: 'no_rawat' },
            { data: 'no_rkm_medis' },
            { data: 'pasien' },
            { data: 'jk' },
            { data: 'umur' },
            { data: 'kelompok_umur' },
            { data: 'tgl_periksa', render: d => fmtTanggal(d) },
            { data: 'jam' },
            { data: 'pemeriksaan' },
            { data: 'item_pemeriksaan' },
            { data: 'hasil' },
            { data: 'satuan' },
            { data: 'nilai_rujukan' },
            { data: 'keterangan' },
            { data: 'ruang' },
            { data: 'petugas' },
            { data: 'dokter_perujuk' },
            { data: 'penanggung_jawab' }
        ]
    });
}

function loadData() {
    const tglAwal = $('#tglAwal').val();
    const tglAkhir = $('#tglAkhir').val();

    if (!tglAwal || !tglAkhir) {
        alert('Pilih periode tanggal pemeriksaan.');
        return;
    }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.getJSON('api/data_rl38_lab.php', {
        tgl_awal: tglAwal,
        tgl_akhir: tglAkhir,
        no_rawat: $('#noRawat').val(),
        no_rm: $('#noRm').val(),
        keyword: $('#keyword').val()
    }, function(res) {
        if (!res.success) {
            alert(res.message || 'Gagal memuat data RL 3.8 laboratorium.');
            return;
        }

        dataRL38 = res.data || [];
        initTable();
        renderFilterOptions();
        applyColumnFilters();
        renderSummary(res.summary || {});
        $('#periodeInfo').text(fmtTanggal(tglAwal) + ' s/d ' + fmtTanggal(tglAkhir));
        $('#btnExport').prop('disabled', dataRL38.length === 0);
    }).fail(function() {
        alert('Gagal memuat data RL 3.8. Pastikan koneksi dan API tersedia.');
    }).always(function() {
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
    });
}

$('#btnLoad').on('click', loadData);
$('#keyword, #noRawat, #noRm').on('keydown', function(e) {
    if (e.key === 'Enter') {
        loadData();
    }
});
$('#btnExport').on('click', function() {
    if (dtRL38) {
        dtRL38.button('.buttons-excel').trigger();
    }
});
$(document).on('click', '.rl38-filter-btn', function(e) {
    e.stopPropagation();
    const key = $(this).data('key');
    const $menu = $(`.rl38-filter-menu[data-key="${key}"]`);

    if ($menu.hasClass('open')) {
        closeFilterMenus();
        return;
    }

    openFilterMenu(key);
});
$('#filterMenuLayer').on('click', '.rl38-filter-menu', function(e) {
    e.stopPropagation();
});
$('#filterMenuLayer').on('change', '.rl38-filter-options input[type="checkbox"]', function() {
    const key = $(this).closest('.rl38-filter-menu').data('key');
    setFilterValue(key, String($(this).val()), this.checked);
    applyColumnFilters();
});
$('#filterMenuLayer').on('click', '.btn-filter-clear', function() {
    const key = $(this).closest('.rl38-filter-menu').data('key');
    selectedFilters[key] = [];
    $(this).closest('.rl38-filter-menu').find('.rl38-filter-options input[type="checkbox"]').prop('checked', false);
    applyColumnFilters();
});
$('#filterMenuLayer').on('click', '.btn-filter-close', function() {
    closeFilterMenus();
});
$('#filterMenuLayer').on('input', '.rl38-filter-search', function() {
    const keyword = $(this).val().toLowerCase();
    $(this).closest('.rl38-filter-menu').find('.rl38-filter-check').each(function() {
        $(this).toggle($(this).text().toLowerCase().includes(keyword));
    });
});
$(document).on('click', function() {
    closeFilterMenus();
});
$(window).on('resize scroll', function() {
    if (openFilterKey) {
        repositionFilterMenu(openFilterKey);
    }
});

$(document).ready(function() {
    buildFilterBar();
    initTable();
    loadData();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
