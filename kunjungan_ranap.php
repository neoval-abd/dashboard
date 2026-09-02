<?php
/*
 * File: kunjungan_ranap.php (V2)
 */
$page_title = "Billing Rawat Inap & Audit";
require_once('includes/header.php');
if (!isset($koneksi)) {
    require_once('config/koneksi.php');
}
$penjab_list = [];
$res_pj = $koneksi->query("SELECT kd_pj, png_jawab FROM penjab WHERE status='1' ORDER BY png_jawab ASC");
if ($res_pj) {
    while ($row = $res_pj->fetch_assoc()) {
        $penjab_list[] = $row;
    }
}
?>

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
:root {
    --bg-page:        #f4f6f9;
    --bg-card:        #ffffff;
    --bg-card-header: #ffffff;
    --bg-table-head:  #212529;
    --bg-row-hover:   #f1f5fb;
    --text-primary:   #212529;
    --text-muted:     #6c757d;
    --text-head:      #ffffff;
    --border-color:   #dee2e6;
    --badge-bpjs-bg:  #198754;
    --badge-umum-bg:  #0d6efd;
    --input-bg:       #ffffff;
    --input-text:     #212529;
    --picker-bg:      #ffffff;
    --picker-hover:   #f8f9fa;
    --picker-border:  rgba(0,0,0,0.15);
    --picker-item-border: #e9ecef;
    --skeleton-from:  #e0e0e0;
    --skeleton-mid:   #f5f5f5;
    --progress-track: #dee2e6;
    --shadow-card:    0 0.125rem 0.5rem rgba(0,0,0,0.08);
    --shadow-picker:  0 0.75rem 1.5rem rgba(0,0,0,0.15);
    --transition:     0.2s ease;
}

html[data-theme="dark"] {
    --bg-page:        #0f1117;
    --bg-card:        #1a1d27;
    --bg-card-header: #1e2130;
    --bg-table-head:  #0d1117;
    --bg-row-hover:   #252836;
    --text-primary:   #e2e8f0;
    --text-muted:     #94a3b8;
    --text-head:      #e2e8f0;
    --border-color:   #2d3348;
    --badge-bpjs-bg:  #16a34a;
    --badge-umum-bg:  #2563eb;
    --input-bg:       #252836;
    --input-text:     #e2e8f0;
    --picker-bg:      #1e2130;
    --picker-hover:   #252836;
    --picker-border:  rgba(255,255,255,0.12);
    --picker-item-border: #2d3348;
    --skeleton-from:  #2d3348;
    --skeleton-mid:   #3a3f52;
    --progress-track: #2d3348;
    --shadow-card:    0 0.125rem 0.5rem rgba(0,0,0,0.4);
    --shadow-picker:  0 0.75rem 1.5rem rgba(0,0,0,0.5);
}

html[data-theme="high-contrast"] {
    --bg-page:        #000000;
    --bg-card:        #0a0a0a;
    --bg-card-header: #111111;
    --bg-table-head:  #000000;
    --bg-row-hover:   #1a1a1a;
    --text-primary:   #ffffff;
    --text-muted:     #cccccc;
    --text-head:      #ffff00;
    --border-color:   #555555;
    --badge-bpjs-bg:  #00cc44;
    --badge-umum-bg:  #3399ff;
    --input-bg:       #111111;
    --input-text:     #ffffff;
    --picker-bg:      #111111;
    --picker-hover:   #222222;
    --picker-border:  rgba(255,255,255,0.3);
    --picker-item-border: #444444;
    --skeleton-from:  #333333;
    --skeleton-mid:   #555555;
    --progress-track: #333333;
    --shadow-card:    0 0 0 1px #555;
    --shadow-picker:  0 0.75rem 1.5rem rgba(0,0,0,0.8);
}

body {
    background-color: var(--bg-page) !important;
    color: var(--text-primary) !important;
    transition: background-color var(--transition), color var(--transition);
}

.card {
    background-color: var(--bg-card) !important;
    border-color: var(--border-color) !important;
    box-shadow: var(--shadow-card) !important;
    transition: background-color var(--transition);
}

.card-header {
    background-color: var(--bg-card-header) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}

.table {
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

.table td, .table th {
    border-color: var(--border-color) !important;
}

.table-dark thead th,
thead.table-dark th {
    background-color: var(--bg-table-head) !important;
    color: var(--text-head) !important;
    border-color: var(--border-color) !important;
}

.table-hover tbody tr:hover {
    background-color: var(--bg-row-hover) !important;
    color: var(--text-primary) !important;
}

.form-control, .form-select {
    background-color: var(--input-bg) !important;
    color: var(--input-text) !important;
    border-color: var(--border-color) !important;
}

.form-control:focus, .form-select:focus {
    background-color: var(--input-bg) !important;
    color: var(--input-text) !important;
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.25) !important;
}

.form-label, label {
    color: var(--text-muted) !important;
}

.text-muted {
    color: var(--text-muted) !important;
}

.modal-content {
    background-color: var(--bg-card) !important;
    color: var(--text-primary) !important;
    border-color: var(--border-color) !important;
}

.modal-header, .modal-footer {
    border-color: var(--border-color) !important;
}

#themeSwitcher {
    position: fixed;
    top: 60px;
    right: 16px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.theme-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid var(--border-color);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    transition: transform 0.15s, box-shadow 0.15s;
    box-shadow: var(--shadow-card);
    background-color: var(--bg-card);
    color: var(--text-primary);
}

.theme-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.theme-btn.active {
    border-color: #0d6efd;
    box-shadow: 0 0 0 3px rgba(13,110,253,0.3);
}

.skeleton-text {
    display: inline-block;
    width: 90px;
    height: 14px;
    background: linear-gradient(90deg, var(--skeleton-from) 25%, var(--skeleton-mid) 50%, var(--skeleton-from) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    border-radius: 4px;
    vertical-align: middle;
}

@keyframes shimmer {
    0%   { background-position: -200% 0; }
    100% { background-position:  200% 0; }
}

.plafon-cell {
    cursor: pointer;
    min-width: 90px;
    display: inline-block;
}
.plafon-cell:hover { text-decoration: underline; }

.plafon-picker-overlay {
    position: absolute;
    z-index: 1051;
    top: 0;
    left: 0;
    right: 0;
    background: var(--picker-bg);
    border: 1px solid var(--picker-border);
    box-shadow: var(--shadow-picker);
    padding: 0.75rem;
    max-height: 420px;
    overflow: hidden;
    border-radius: 0.375rem;
    transition: background-color var(--transition);
}

.plafon-picker-overlay .form-control {
    margin-bottom: 0.5rem;
    background-color: var(--input-bg) !important;
    color: var(--input-text) !important;
    border-color: var(--border-color) !important;
}

.plafon-picker-results {
    max-height: 280px;
    overflow: auto;
}

.plafon-picker-item {
    padding: 0.65rem 0.5rem;
    border-bottom: 1px solid var(--picker-item-border);
    color: var(--text-primary);
    transition: background-color 0.1s;
}
.plafon-picker-item:last-child { border-bottom: none; }
.plafon-picker-item:hover { background: var(--picker-hover); }
.plafon-picker-item.selected { background: var(--picker-hover); outline: 2px solid #0d6efd; }
.plafon-picker-item small {
    display: block;
    color: var(--text-muted);
}

.selisih-wrapper { min-width: 110px; }
.selisih-wrapper .progress {
    background-color: var(--progress-track) !important;
}

.selisih-cell {
    display: inline-block;
    width: 100%;
    text-align: center;
}

html[data-theme="dark"] .table-danger,
html[data-theme="high-contrast"] .table-danger {
    background-color: rgba(220, 53, 69, 0.18) !important;
    color: var(--text-primary) !important;
}

html[data-theme="dark"] .table-warning,
html[data-theme="high-contrast"] .table-warning {
    background-color: rgba(255, 193, 7, 0.12) !important;
    color: var(--text-primary) !important;
}

html[data-theme="dark"] .table-secondary,
html[data-theme="high-contrast"] .table-secondary {
    background-color: rgba(108, 117, 125, 0.2) !important;
    color: var(--text-primary) !important;
}

html[data-theme="dark"] .bg-light,
html[data-theme="high-contrast"] .bg-light {
    background-color: var(--bg-row-hover) !important;
}

html[data-theme="dark"] .bg-white,
html[data-theme="high-contrast"] .bg-white {
    background-color: var(--bg-card) !important;
}

html[data-theme="dark"] .dataTables_wrapper .dataTables_filter input,
html[data-theme="dark"] .dataTables_wrapper .dataTables_length select,
html[data-theme="high-contrast"] .dataTables_wrapper .dataTables_filter input,
html[data-theme="high-contrast"] .dataTables_wrapper .dataTables_length select {
    background-color: var(--input-bg) !important;
    color: var(--input-text) !important;
    border-color: var(--border-color) !important;
}

html[data-theme="dark"] .dataTables_wrapper .dataTables_info,
html[data-theme="dark"] .dataTables_wrapper .dataTables_paginate,
html[data-theme="high-contrast"] .dataTables_wrapper .dataTables_info,
html[data-theme="high-contrast"] .dataTables_wrapper .dataTables_paginate {
    color: var(--text-primary) !important;
}

html[data-theme="dark"] .page-link,
html[data-theme="high-contrast"] .page-link {
    background-color: var(--bg-card) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
}

html[data-theme="dark"] .page-item.active .page-link,
html[data-theme="high-contrast"] .page-item.active .page-link {
    background-color: #0d6efd !important;
    border-color: #0d6efd !important;
    color: #fff !important;
}
</style>

<div id="themeSwitcher" title="Ganti Tema">
    <button class="theme-btn active" data-theme="light" title="Light Mode">☀️</button>
    <button class="theme-btn" data-theme="dark" title="Dark Mode">🌙</button>
    <button class="theme-btn" data-theme="high-contrast" title="High Contrast">⚡</button>
</div>

<div class="container-fluid">

    <div class="card shadow-sm mb-4 border-start border-4 border-info">
        <div class="card-body py-3">
            <form id="formFilter">
                <div class="row align-items-end g-2">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Dari Tanggal Masuk</label>
                        <input type="date" id="tgl_awal" class="form-control form-control-sm" value="<?php echo date('Y-m-01'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
                        <input type="date" id="tgl_akhir" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Penjamin / Asuransi</label>
                        <select id="kd_pj" class="form-select form-select-sm">
                            <option value="all">Semua Penjamin</option>
                            <?php foreach ($penjab_list as $pj): ?>
                                <option value="<?php echo htmlspecialchars($pj['kd_pj']); ?>"><?php echo htmlspecialchars($pj['png_jawab']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="chk_audit">
                            <label class="form-check-label small" for="chk_audit">
                                <strong>Mode Audit</strong> (Termasuk Pasien Sudah Pulang)
                            </label>
                        </div>
                        <small class="text-muted" style="font-size: 0.7rem;">
                            Jika dicentang, menampilkan pasien pulang yang belum lunas sesuai periode tanggal masuk.
                        </small>
                    </div>
                    <div class="col-md-3">
                        <button type="button" onclick="reloadTable()" class="btn btn-sm btn-primary w-100 fw-bold">
                            <i class="fas fa-filter me-1"></i> Terapkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pasien & Estimasi Biaya</h6>
            <button onclick="reloadTable()" class="btn btn-sm btn-light border"><i class="fas fa-sync-alt"></i></button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle table-sm" id="tableKunjungan" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th width="8%">Tgl Masuk</th>
                            <th width="17%">No. Rawat / Pasien</th>
                            <th width="14%">DPJP Ranap</th>
                            <th width="10%">Kamar / Penjamin</th>
                            <th width="6%">Kelas</th>
                            <th width="10%" class="text-center bg-secondary">Plafon</th>
                            <th width="10%" class="text-center bg-warning text-dark">Est. Biaya</th>
                            <th width="14%" class="text-center">Selisih</th>
                            <th width="5%" class="text-center">Status</th>
                            <th width="5%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetailBilling" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Rincian Billing</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between p-2 mb-2 rounded border" style="background:var(--bg-row-hover)">
                    <div><strong>Pasien:</strong> <span id="lbl-pasien">-</span></div>
                    <div><strong>No. Rawat:</strong> <span id="lbl-norawat">-</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" style="font-size: 0.85rem;">
                        <thead class="table-dark text-center">
                            <tr>
                                <th width="20%">Kategori / Keterangan</th>
                                <th width="25%">Tagihan / Tindakan</th>
                                <th width="12%">Biaya</th>
                                <th width="5%">Jml</th>
                                <th width="12%">Tambahan</th>
                                <th width="15%">Total Biaya</th>
                            </tr>
                        </thead>
                        <tbody id="bodyDetailBilling"></tbody>
                        <tfoot class="fw-bold fs-5">
                            <tr>
                                <td colspan="5" class="text-end">TOTAL TAGIHAN:</td>
                                <td class="text-end text-primary" id="lbl-total">0</td>
                            </tr>
                        </tfoot>
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

(function() {
    var saved = localStorage.getItem('ranap_theme') || 'light';
    applyTheme(saved);

    document.querySelectorAll('.theme-btn').forEach(function(btn) {
        if (btn.dataset.theme === saved) btn.classList.add('active');
        btn.addEventListener('click', function() {
            document.querySelectorAll('.theme-btn').forEach(function(b){ b.classList.remove('active'); });
            btn.classList.add('active');
            applyTheme(btn.dataset.theme);
            localStorage.setItem('ranap_theme', btn.dataset.theme);
        });
    });

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme === 'light' ? '' : theme);
        if (theme !== 'light') {
            document.documentElement.classList.add('dark-ui');
        } else {
            document.documentElement.classList.remove('dark-ui');
        }
    }
})();

var tableKunjungan;

function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
}

function parseRupiahNilai(teks) {
    if (!teks) return 0;
    return parseFloat(String(teks).replace(/[^\d]/g, '')) || 0;
}

function renderSelisihHtml(estimasiRaw, plafonRaw) {
    if (estimasiRaw === null || plafonRaw === null || plafonRaw === 0) return '<span class="selisih-cell text-muted">-</span>';
    var selisih = plafonRaw - estimasiRaw;           // sisa = plafon - estimasi
    var isOver = estimasiRaw > plafonRaw;
    if (isOver) {
        return '<span class="selisih-cell text-danger fw-bold">-' + formatRupiah(Math.abs(selisih)) + '</span>';
    }
    return '<span class="selisih-cell text-success fw-bold">Sisa: ' + formatRupiah(selisih) + '</span>';
}

// DATATABLES INIT
$(document).ready(function() {
    $('#chk_audit').change(function() {
        var checked = $(this).is(':checked');
        $('#tgl_awal, #tgl_akhir').prop('disabled', !checked).toggleClass('bg-light', !checked);
    }).trigger('change');

    $('#kd_pj').change(function() { reloadTable(); });

    tableKunjungan = $('#tableKunjungan').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/data_kunjungan_ranap.php',
            type: 'GET',
            global: false,
            data: function(d) {
                d.mode     = $('#chk_audit').is(':checked') ? 'audit' : 'active';
                d.tgl_awal = $('#tgl_awal').val();
                d.tgl_akhir= $('#tgl_akhir').val();
                d.kd_pj    = $('#kd_pj').val();
            }
        },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        dom: 'Bfrtip',
        buttons: [
                {
                    text: '<i class="fas fa-file-excel me-1"></i> Export Excel',
                    className: 'btn btn-success btn-sm mb-3',
                    id: 'btnExportExcel',
                    action: function() { exportToExcel(); }
                },
                { extend: 'pageLength', className: 'btn btn-secondary btn-sm mb-3' }
            ],
        order: [],
        createdRow: function(row, data) {
            if (data.is_over === true) $(row).addClass('table-danger');
            if (data.status_pulang !== '-' && data.status_pulang !== 'Masih Dirawat') $(row).addClass('table-warning');
        },
        columns: [
            { data: 'waktu' },
            {
                data: null,
                render: function(data) {
                    return '<b>' + data.no_rawat + '</b><br>' + data.pasien + ' <br><small class="text-muted">RM: ' + data.rm + '</small>';
                }
            },
            {
                data: 'dpjp',
                render: function(data, type, row) {
                    if (data === null || data === '') return '<span class="skeleton-cell" data-norawat="' + row.no_rawat + '" data-col="dpjp"><span class="skeleton-text" style="width:100px"></span></span>';
                    var html = '<b>' + data + '</b>';
                    if (row.is_dpjp_fallback) html += '<br><small class="badge bg-warning text-dark" style="font-size:0.7em;">DPJP -</small>';
                    return html;
                }
            },
            {
                data: null,
                render: function(data) {
                    var penjamin  = data.penjamin.toLowerCase();
                    var badgeClass = 'bg-secondary', badgeStyle = '';
                    if (penjamin.includes('bpjs'))       badgeClass = 'bg-success';
                    else if (penjamin.includes('umum'))  badgeClass = 'bg-primary';
                    else if (penjamin.includes('asuransi') || penjamin.includes('inhealth')) {
                        badgeClass = ''; badgeStyle = 'background-color:#e83e8c;color:white;';
                    }
                    return data.kamar + '<br><span class="badge ' + badgeClass + '" style="' + badgeStyle + 'border:1px solid #ddd;">' + data.penjamin + '</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                render: function(data, type, row) {
                    var displayClass = row.bpjs_kelas ? 'Kelas ' + row.bpjs_kelas : (row.room_kelas || '-');
                    var html = '<strong>' + displayClass + '</strong>';
                    var isSpecialWard = /HCU|PERINA|PERINATOLOGI|NICU|PICU|ICU|ISOLASI/i.test(String(row.kamar || ''));
                    if (row.penjamin && row.penjamin.toLowerCase().includes('bpjs') && row.bpjs_kelas && row.room_kelas && !isSpecialWard) {
                        function w(c) { var l = String(c||'').toLowerCase(); if(l.includes('vip'))return 0; if(l.includes('1')||l==='1')return 1; if(l.includes('2')||l==='2')return 2; if(l.includes('3')||l==='3')return 3; return 999; }
                        if (w(row.room_kelas) < w(row.bpjs_kelas)) html += '<br/><span class="badge bg-danger text-white" style="font-size:0.72rem;">Naik Kelas</span>';
                    }
                    return html;
                }
            },
            {
                data: 'plafon',
                className: 'text-center fw-bold',
                createdCell: function(td) { $(td).css('position', 'relative'); },
                render: function(data, type, row) {
                    if (type === 'export') {
                        return dtExportNumber(data);
                    }
                    if (data === null) return '<span class="skeleton-cell" data-norawat="' + row.no_rawat + '" data-col="plafon"><span class="skeleton-text"></span></span>';
                    return '<span class="plafon-cell" data-norawat="' + row.no_rawat + '">' + data + '</span>';
                }
            },
            {
                data: 'estimasi',
                className: 'text-center fw-bold text-primary',
                render: function(data, type, row) {
                    if (type === 'export') {
                        return dtExportNumber(data);
                    }
                    if (data === null) return '<span class="skeleton-cell" data-norawat="' + row.no_rawat + '" data-col="estimasi"><span class="skeleton-text"></span></span>';
                    return data;
                }
            },
            {
                data: 'selisih',
                className: 'text-center fw-bold',
                render: function(data, type, row) {
                    if (type === 'export') {
                        if (data === null || data === undefined || data === '-') return '';
                        return dtExportNumber(data);
                    }
                    if (data === null) return '<span class="skeleton-cell" data-norawat="' + row.no_rawat + '" data-col="selisih"><span class="skeleton-text"></span></span>';
                    if (!data || data === '-') return '<span class="selisih-cell text-muted">-</span>';
                    return row.is_over
                        ? '<span class="selisih-cell text-danger">-' + data.trim() + '</span>'
                        : '<span class="selisih-cell text-success">Sisa: ' + data + '</span>';
                }
            },
            {
                data: 'status_pulang',
                className: 'text-center',
                render: function(data, type) {
                    if (type === 'export') return data;
                    return (data === 'Masih Dirawat' || data === '-')
                        ? '<span class="badge bg-info text-dark">Aktif</span>'
                        : '<span class="badge bg-warning text-dark">' + data + '</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                render: function(data, type, row) {
                    return '<button class="btn btn-sm btn-primary shadow-sm" onclick="showDetailBilling(\'' + row.no_rawat + '\',\'' + row.pasien.replace(/'/g, "\\'") + '\')" title="Lihat Rincian Lengkap"><i class="fas fa-list-ul"></i></button>';
                }
            }
        ],
        drawCallback: function() { loadBillingAsync(); }
    });

});

function reloadTable() { tableKunjungan.ajax.reload(); }

var _billingQueue   = [];
var _billingRunning = 0;
var _billingConcurrency = 3;
var _billingCache   = {};

function loadBillingAsync() {
    var cells = document.querySelectorAll('.skeleton-cell');
    _billingQueue = [];
    cells.forEach(function(el) {
        var noRawat = el.getAttribute('data-norawat');
        if (!_billingQueue.some(function(i){ return i.no_rawat === noRawat; })) {
            var rowData = tableKunjungan.rows().data().toArray().find(function(r){ return r.no_rawat === noRawat; });
            _billingQueue.push({ no_rawat: noRawat, kd_pj: rowData ? (rowData.kd_pj || '-') : '-' });
        }
    });
    _processBillingQueue();
}

function _processBillingQueue() {
    while (_billingRunning < _billingConcurrency && _billingQueue.length > 0) {
        _billingRunning++;
        _fetchOneBilling(_billingQueue.shift());
    }
}

function _fetchOneBilling(item) {
    $.ajax({
        url: 'api/hitung_estimasi_ranap.php',
        type: 'GET',
        global: false,
        data: { no_rawat: item.no_rawat, kd_pj: item.kd_pj },
        dataType: 'json',
        success: function(res) {
            var nr = res.no_rawat;
            _billingCache[nr] = res;

            // Estimasi
            document.querySelectorAll('.skeleton-cell[data-norawat="' + nr + '"][data-col="estimasi"]').forEach(function(el) {
                el.outerHTML = '<span class="fw-bold text-primary">Rp ' + (res.estimasi || '0') + '</span>';
            });

            // Plafon
            document.querySelectorAll('.skeleton-cell[data-norawat="' + nr + '"][data-col="plafon"]').forEach(function(el) {
                el.outerHTML = res.plafon || '-';
            });

            // FIX #3: Selisih 
            document.querySelectorAll('.skeleton-cell[data-norawat="' + nr + '"][data-col="selisih"]').forEach(function(el) {
                el.outerHTML = renderSelisihHtml(res.estimasi_raw, res.plafon_raw);
            });

            // DPJP
            document.querySelectorAll('.skeleton-cell[data-norawat="' + nr + '"][data-col="dpjp"]').forEach(function(el) {
                var html = '<b>' + (res.dpjp || '-') + '</b>';
                if (res.is_dpjp_fallback) html += '<br><small class="badge bg-warning text-dark" style="font-size:0.7em;">DPJP -</small>';
                el.outerHTML = html;
            });
        },
        error: function() {
            document.querySelectorAll('.skeleton-cell[data-norawat="' + item.no_rawat + '"]').forEach(function(el) {
                el.outerHTML = '<span class="text-muted">-</span>';
            });
        },
        complete: function() {
            _billingRunning--;
            _processBillingQueue();
        }
    });
}

// DETAIL BILLING MODAL
function showDetailBilling(noRawat, namaPasien) {
    $('#lbl-pasien').text(namaPasien);
    $('#lbl-norawat').text(noRawat);
    $('#bodyDetailBilling').html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div><br>Menghitung ulang rincian biaya...</td></tr>');
    $('#lbl-total').text('...');
    $('#modalDetailBilling').modal('show');

    $.ajax({
        url: 'api/data_rincian_billing.php',
        type: 'GET',
        data: { no_rawat: noRawat },
        dataType: 'json',
        success: function(res) {
            var html = '';
            if (res.data && res.data.length > 0) {
                res.data.forEach(function(item) {
                    if (item.is_header) {
                        html += '<tr class="table-secondary fw-bold"><td colspan="6">' + item.keterangan + ' ' + item.tagihan + '</td></tr>';
                    } else {
                        var style = (item.total < 0) ? 'text-danger fw-bold' : '';
                        html += '<tr><td>' + item.keterangan + '</td><td>' + item.tagihan + '</td>' +
                                '<td class="text-end">' + formatRupiah(item.biaya) + '</td>' +
                                '<td class="text-center">' + item.jumlah + '</td>' +
                                '<td class="text-end">' + formatRupiah(item.tambahan) + '</td>' +
                                '<td class="text-end fw-bold ' + style + '">' + formatRupiah(item.total) + '</td></tr>';
                    }
                });
            } else {
                html = '<tr><td colspan="6" class="text-center">Tidak ada data tagihan.</td></tr>';
            }
            $('#bodyDetailBilling').html(html);
            $('#lbl-total').text(res.total_rupiah);
        }
    });
}


// ================================================================
// EXPORT EXCEL — dengan fetch data billing terlebih dahulu
// ================================================================
function exportToExcel() {
    var allRows = tableKunjungan.rows({ search: 'applied' }).data().toArray();
    if (allRows.length === 0) { alert('Tidak ada data untuk diekspor.'); return; }

    // Cek baris mana yang belum di-cache
    var toFetch = allRows.filter(function(r) { return !_billingCache[r.no_rawat]; });

    if (toFetch.length > 0) {
        var btn = document.querySelector('.dt-button.btn-success');
        if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Menyiapkan data...';

        var done = 0;
        var concurrency = 5;
        var queue = toFetch.slice();

        function fetchNext() {
            if (queue.length === 0) return;
            var item = queue.shift();
            $.ajax({
                url: 'api/hitung_estimasi_ranap.php',
                type: 'GET',
                global: false,
                data: { no_rawat: item.no_rawat, kd_pj: item.kd_pj || '-' },
                dataType: 'json',
                success: function(res) { _billingCache[res.no_rawat] = res; },
                complete: function() {
                    done++;
                    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> ' + done + '/' + toFetch.length + ' data...';
                    fetchNext();
                    if (done === toFetch.length) {
                        if (btn) btn.innerHTML = '<i class="fas fa-file-excel me-1"></i> Export Excel';
                        _doExport(allRows);
                    }
                }
            });
        }

        for (var i = 0; i < Math.min(concurrency, toFetch.length); i++) fetchNext();
    } else {
        _doExport(allRows);
    }
}

function _doExport(rows) {
    var headers = ['Tgl Masuk', 'No. Rawat', 'Pasien', 'RM', 'DPJP Ranap',
                   'Kamar', 'Penjamin', 'Kelas', 'Plafon', 'Est. Biaya', 'Selisih', 'Status'];

    var wsData = [headers];
    var overRows = []; 

    rows.forEach(function(r) {
        var cache  = _billingCache[r.no_rawat] || {};
        var plafon = cache.plafon_raw ? parseFloat(cache.plafon_raw) : '';
        var estimasi = cache.estimasi_raw !== undefined ? parseFloat(cache.estimasi_raw) : '';
        var selisih  = '';
        var isOver   = false;

        if (plafon !== '' && estimasi !== '') {
            selisih = plafon - estimasi;
            isOver  = estimasi > plafon;
        }

        var kelas = r.bpjs_kelas ? 'Kelas ' + r.bpjs_kelas : (r.room_kelas || '-');
        var naik  = '';
        var isSpecialWard = /HCU|PERINA|PERINATOLOGI|NICU|PICU|ICU|ISOLASI/i.test(String(r.kamar || ''));
        if (r.penjamin && r.penjamin.toLowerCase().includes('bpjs') && r.bpjs_kelas && r.room_kelas && !isSpecialWard) {
            function w(c) { var l = String(c||'').toLowerCase(); if(l.includes('vip'))return 0; if(l.includes('1'))return 1; if(l.includes('2'))return 2; if(l.includes('3'))return 3; return 999; }
            if (w(r.room_kelas) < w(r.bpjs_kelas)) naik = ' (Naik Kelas)';
        }

        var rowArr = [
            r.waktu || '',
            r.no_rawat || '',
            r.pasien || '',
            r.rm || '',
            r.dpjp || (cache.dpjp || '-'),
            r.kamar || '',
            r.penjamin || '',
            kelas + naik,
            plafon !== '' ? plafon : '',
            estimasi !== '' ? estimasi : '',
            selisih !== '' ? selisih : '',
            r.status_pulang || ''
        ];

        if (isOver) overRows.push(wsData.length); 
        wsData.push(rowArr);
    });

    var ws = XLSX.utils.aoa_to_sheet(wsData);

    var numCols = [8, 9, 10];
    var range = XLSX.utils.decode_range(ws['!ref']);

    for (var R = 1; R <= range.e.r; R++) {
        numCols.forEach(function(C) {
            var addr = XLSX.utils.encode_cell({ r: R, c: C });
            if (ws[addr] && ws[addr].v !== '') {
                ws[addr].t = 'n';
                ws[addr].z = '#,##0';
            }
        });
    }

    overRows.forEach(function(rowIdx) {
        var addr = XLSX.utils.encode_cell({ r: rowIdx, c: 10 }); // kolom Selisih
        if (!ws[addr]) ws[addr] = { t: 's', v: '' };
        if (!ws[addr].s) ws[addr].s = {};
        ws[addr].s = {
            font: { color: { rgb: 'FF0000' }, bold: true },
            fill: { fgColor: { rgb: 'FFCCCC' } }
        };
    });

    ws['!cols'] = [
        { wch: 12 }, { wch: 22 }, { wch: 24 }, { wch: 12 }, { wch: 26 },
        { wch: 18 }, { wch: 16 }, { wch: 14 }, { wch: 18 }, { wch: 18 },
        { wch: 22 }, { wch: 14 }
    ];

    for (var C = 0; C <= 11; C++) {
        var hAddr = XLSX.utils.encode_cell({ r: 0, c: C });
        if (!ws[hAddr]) continue;
        ws[hAddr].s = {
            font: { bold: true, color: { rgb: 'FFFFFF' } },
            fill: { fgColor: { rgb: '212529' } },
            alignment: { horizontal: 'center' }
        };
    }

    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Billing Ranap');

    var tgl = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    XLSX.writeFile(wb, 'Billing_Ranap_' + tgl + '.xlsx', { cellStyles: true });
}
</script>
<?php $page_js = ob_get_clean(); ?>
<?php require_once('includes/footer.php'); ?>
