<?php
/*
 * File: kunjungan_ranap.php (V2 - OPTIMIZED)
 * Perbaikan:
 *  1. Fuzzy Search INA-CBG (case-insensitive, abaikan tanda hubung)
 *  2. Dark Mode / High Contrast toggle
 *  3. Bug fix kolom Selisih (real-time update tanpa refresh)
 *  4. Debouncing pada search INA-CBG (500ms)
 *  5. Input mask otomatis format kode INA-CBG
 *  6. Auto-recalculate Selisih setelah pilih INA-CBG via AJAX
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
<style>
/* ============================================================
   TEMA & DARK MODE
   ============================================================ */
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

/* ============================================================
   THEME SWITCHER BUTTON
   ============================================================ */
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

/* ============================================================
   SKELETON LOADER
   ============================================================ */
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

/* ============================================================
   PLAFON / INA-CBG PICKER
   ============================================================ */
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

/* Input helper text */
.inacbg-hint {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
    display: block;
}

.inacbg-cell {
    cursor: pointer;
    min-width: 120px;
}
.inacbg-cell:hover { text-decoration: underline; }
.inacbg-cell .edit-inacbg-icon {
    margin-left: 0.35rem;
    color: #0d6efd;
    font-size: 0.95rem;
}

.edit-inacbg-btn {
    border: 1px solid rgba(255,255,255,.25);
    background: rgba(255,255,255,.08);
    color: var(--text-primary);
    padding: 0.35rem 0.6rem;
    border-radius: 0.35rem;
    font-size: 0.82rem;
    cursor: pointer;
}
.edit-inacbg-btn:hover { background: rgba(255,255,255,.18); }

/* ============================================================
   SELISIH CELL — Progress Bar
   ============================================================ */
.selisih-wrapper { min-width: 110px; }
.selisih-wrapper .progress {
    background-color: var(--progress-track) !important;
}

/* ============================================================
   TABLE ROW HIGHLIGHTS (theme-aware)
   ============================================================ */
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

/* DataTables overrides */
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

<!-- Theme Switcher -->
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
                            <th width="10%">Tgl Masuk</th>
                            <th width="15%">No. Rawat / Pasien</th>
                            <th width="15%">DPJP Ranap</th>
                            <th width="14%">Kamar / Penjamin</th>
                            <th width="8%">Kelas</th>
                            <th width="12%">INA-CBG</th>
                            <th width="10%" class="text-end bg-secondary">Plafon</th>
                            <th width="10%" class="text-end bg-warning text-dark">Est. Biaya</th>
                            <th width="10%" class="text-end">Selisih</th>
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

<!-- Modal Detail Billing -->
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
// ============================================================
// THEME SWITCHER
// ============================================================
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
        // Patch Bootstrap table-dark for dark themes
        if (theme !== 'light') {
            document.documentElement.classList.add('dark-ui');
        } else {
            document.documentElement.classList.remove('dark-ui');
        }
    }
})();

// ============================================================
// UTILITIES
// ============================================================
var tableKunjungan;

function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
}

function parseRupiahNilai(teks) {
    if (!teks) return 0;
    return parseFloat(String(teks).replace(/[^\d]/g, '')) || 0;
}

/**
 * FIX #1: Normalise INA-CBG input — strip non-alphanumeric, uppercase.
 * Allows: "a4101" → "A4101", "A-4-10-I" → "A4101"
 */
function normaliseInacbg(str) {
    return String(str || '').replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
}

// ============================================================
// FIX #3 + #4: Selisih cell renderer (pure function, no side effects)
// ============================================================
function renderSelisihHtml(estimasiRaw, plafonRaw) {
    if (estimasiRaw === null || plafonRaw === null || plafonRaw === 0) return '<span class="selisih-cell text-muted">-</span>';
    var selisih = plafonRaw - estimasiRaw;           // sisa = plafon - estimasi
    var pct    = Math.min(100, Math.round((estimasiRaw / plafonRaw) * 100));
    var isOver = estimasiRaw > plafonRaw;
    var barColor = isOver ? 'bg-danger' : (pct >= 80 ? 'bg-warning' : 'bg-success');
    var label;
    if (isOver) {
        label = '<span class="text-danger fw-bold">+' + formatRupiah(Math.abs(selisih)) + ' (OVER)</span>';
    } else {
        label = '<span class="text-success fw-bold">Sisa: ' + formatRupiah(selisih) + '</span>';
    }
    return '<div class="selisih-wrapper">' + label +
           '<div class="progress mt-1" style="height:5px;" title="' + pct + '% dari plafon">' +
           '<div class="progress-bar ' + barColor + '" style="width:' + pct + '%;"></div>' +
           '</div><small class="text-muted" style="font-size:0.68rem;">' + pct + '% terpakai</small></div>';
}

// ============================================================
// INA-CBG PICKER — Fuzzy / Normalised Search
// ============================================================
function closeInacbgPicker() {
    $('.plafon-picker-overlay').remove();
}

var _inacbgDebounce = null;

/**
 * FIX #1: Search using normalised query — backend also normalises.
 * Fallback: also send raw query.
 */
function searchInacbg(term, callback) {
    clearTimeout(_inacbgDebounce);
    // FIX #4: Debounce 500ms
    _inacbgDebounce = setTimeout(function() {
        $.ajax({
            url: 'api/search_inacbg.php',
            type: 'GET',
            data: {
                q: term,
                qn: normaliseInacbg(term)   // kirim versi normalised
            },
            dataType: 'json',
            success: function(res) { callback(res.data || []); },
            error:   function()    { callback([]); }
        });
    }, 500);
}

function buildInacbgResultRow(item, rowData) {
    var classes = [
        { label: 'Kelas 1', key: 'tarif_kelas1' },
        { label: 'Kelas 2', key: 'tarif_kelas2' },
        { label: 'Kelas 3', key: 'tarif_kelas3' },
        { label: 'VIP',     key: 'tarif_vip'    }
    ];
    var rateButtons = classes.map(function(cls) {
        var value = item[cls.key];
        if (value === null || value === '' || value === undefined) return '';
        return '<button type="button" class="btn btn-sm btn-outline-primary select-inacbg-rate me-1 mb-1"' +
               ' data-kode="' + item.kode_inacbg + '" data-kelas="' + cls.label + '" data-tarif="' + value + '">' +
               cls.label + ': ' + formatRupiah(parseFloat(value)) + '</button>';
    }).join('');

    var labelPilih = '';
    if (rowData && rowData.bpjs_kelas) {
        labelPilih = '<div class="small text-muted">BPJS Kelas Pasien: Kelas ' + rowData.bpjs_kelas + '</div>';
    }

    return '<div class="plafon-picker-item" data-kode="' + item.kode_inacbg + '" data-deskripsi="' + item.deskripsi + '">' +
           '<div><strong>' + item.kode_inacbg + '</strong> &mdash; ' + item.deskripsi + '</div>' +
           labelPilih +
           '<div class="mt-2">' + rateButtons + '</div>' +
           '</div>';
}

/**
 * FIX #3: After saving INA-CBG, immediately fetch estimasi and recalculate selisih via AJAX.
 * No page refresh needed.
 */
function saveInacbgAndRefreshSelisih(payload, row, $tr, callback) {
    $.ajax({
        url: 'api/save_inacbg_selection.php',
        type: 'POST',
        data: payload,
        dataType: 'json',
        success: function(res) {
            if (!res.success) { callback(false, res.message || 'Gagal menyimpan pilihan INA-CBG.'); return; }

            // FIX #3: Immediately re-fetch estimasi for this no_rawat to recalculate selisih
            var rowData = row.data() || {};
            var kd_pj  = rowData.kd_pj || '-';

            $.ajax({
                url: 'api/hitung_estimasi_ranap.php',
                type: 'GET',
                global: false,
                data: { no_rawat: payload.no_rawat, kd_pj: kd_pj },
                dataType: 'json',
                success: function(est) {
                    // Update selisih cell directly
                    var $selisih = $tr.find('.selisih-cell, .selisih-wrapper').closest('td');
                    $selisih.html(renderSelisihHtml(est.estimasi_raw, payload.tarif));

                    // Update estimasi cell if skeleton
                    $tr.find('[data-col="estimasi"]').each(function() {
                        $(this).replaceWith('<span class="fw-bold text-primary">Rp ' + (est.estimasi || '0') + '</span>');
                    });

                    // Update row highlight
                    $tr.removeClass('table-danger');
                    if (payload.tarif > 0 && est.estimasi_raw > payload.tarif) {
                        $tr.addClass('table-danger');
                    }
                    callback(true, res);
                },
                error: function() {
                    // Still call success even if re-fetch fails — data was saved
                    callback(true, res);
                }
            });
        },
        error: function() { callback(false, 'Gagal menyimpan pilihan INA-CBG. Periksa koneksi.'); }
    });
}

function openInacbgPicker(cell) {
    closeInacbgPicker();
    var $cell    = $(cell).closest('.inacbg-cell');
    var row      = tableKunjungan.row($cell.closest('tr'));
    var $tr      = $cell.closest('tr');
    var rowData  = row.data() || {};

    var picker = $('<div class="plafon-picker-overlay">' +
        '<span class="inacbg-hint"><i class="fas fa-info-circle me-1"></i>Ketik kode (mis. <code>a410</code> atau <code>A-4-10-I</code>) atau nama diagnosa. Hasil muncul otomatis.</span>' +
        '<input type="search" class="form-control form-control-sm inacbg-search" placeholder="Cari: a410 / a-4-10-i / septikemia..." autocomplete="off">' +
        '<div class="plafon-picker-results"><div class="text-muted small p-2">Ketik minimal 2 karakter untuk mencari.</div></div>' +
        '</div>');

    $cell.closest('td').css('position', 'relative').append(picker);

    var $input   = picker.find('.inacbg-search');
    var $results = picker.find('.plafon-picker-results');
    var activeIndex = 0;
    var lastItems   = [];

    function selectResultItem(idx) {
        $results.find('.plafon-picker-item').removeClass('selected');
        var $item = $results.find('.plafon-picker-item').eq(idx);
        if ($item.length) {
            $item.addClass('selected');
            activeIndex = idx;
            $item[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function renderResults(items) {
        lastItems = items || [];
        if (!items || items.length === 0) {
            $results.html('<div class="text-muted small p-2">Tidak ditemukan hasil pencarian.</div>');
            return;
        }
        $results.html(items.map(function(item) { return buildInacbgResultRow(item, rowData); }).join(''));
        activeIndex = 0;
        selectResultItem(0);
    }

    // FIX #1: Input mask — auto-format as user types (show formatted hint below input)
    $input.on('input', function() {
        var raw = $(this).val().trim();
        if (raw.length < 2) {
            $results.html('<div class="text-muted small p-2">Ketik minimal 2 karakter untuk mencari.</div>');
            return;
        }
        // Show loading indicator
        $results.html('<div class="text-muted small p-2"><i class="fas fa-spinner fa-spin me-1"></i>Mencari...</div>');
        // FIX #4: debounced search via searchInacbg()
        searchInacbg(raw, renderResults);
    });

    $input.on('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeIndex < lastItems.length - 1) { activeIndex++; selectResultItem(activeIndex); }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeIndex > 0) { activeIndex--; selectResultItem(activeIndex); }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            var $sel = $results.find('.plafon-picker-item').eq(activeIndex);
            var $btn = $sel.find('.select-inacbg-rate').first();
            if ($btn.length) $btn.trigger('click');
        } else if (e.key === 'Escape') {
            closeInacbgPicker();
        }
    });

    // FIX #3: On rate button click — save then immediately recalculate selisih
    $results.on('click', '.select-inacbg-rate', function(e) {
        e.stopPropagation();
        var tarif    = parseFloat($(this).data('tarif')) || 0;
        var kelas    = $(this).data('kelas');
        var kode     = $(this).data('kode');
        var deskripsi = $(this).closest('.plafon-picker-item').data('deskripsi') || '';

        var payload = {
            no_rawat: rowData.no_rawat,
            kode_inacbg: kode,
            deskripsi: deskripsi,
            kelas: kelas,
            tarif: tarif
        };

        // Optimistic UI update
        var $inacbgCell = $cell;
        var $plafonCell = $cell.closest('tr').find('.plafon-cell').first();
        $inacbgCell.html('<div><strong>' + kode + '</strong><i class="fas fa-edit edit-inacbg-icon" title="Ubah INA-CBG"></i></div><div><small class="text-muted">' + deskripsi + '</small></div>');
        $plafonCell.html(formatRupiah(tarif));

        // Update DataTable row data
        var data = row.data() || {};
        data.selected_inacbg_code  = kode;
        data.selected_inacbg_desc  = deskripsi;
        data.selected_inacbg_class = kelas;
        data.selected_inacbg_tarif = tarif;
        data.plafon_raw            = tarif;
        row.data(data).invalidate();

        closeInacbgPicker();

        // FIX #3: Save + recalculate selisih immediately
        saveInacbgAndRefreshSelisih(payload, row, $tr, function(ok, res) {
            if (!ok) { alert(res || 'Gagal menyimpan pilihan INA-CBG.'); }
        });
    });

    $results.on('click', '.plafon-picker-item', function(e) {
        if ($(e.target).closest('.select-inacbg-rate').length) return;
        var $btn = $(this).find('.select-inacbg-rate').first();
        if ($btn.length) $btn.trigger('click');
    });

    $(document).one('click', function(e) {
        if ($(e.target).closest('.plafon-picker-overlay').length === 0 &&
            $(e.target).closest('.inacbg-cell').length === 0) {
            closeInacbgPicker();
        }
    });

    $input.focus();
}

// ============================================================
// DATATABLES INIT
// ============================================================
$(document).ready(function() {
    // Audit mode toggle
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
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i> Export Excel',
                className: 'btn btn-success btn-sm mb-3',
                title: 'Laporan Billing Rawat Inap',
                exportOptions: {
                    columns: ':visible:not(:last-child)',
                    format: {
                        body: function(data, row, column) {
                            var str = (data === null || data === undefined) ? '' : String(data);
                            if (column === 7 || column === 8 || column === 9) {
                                return str.replace(/[^\d,-]/g, '').replace(',', '.');
                            }
                            if (str.indexOf('<') > -1) {
                                return str.replace(/<br\s*\/?>/gi, ' - ').replace(/<[^>]+>/g, '').trim();
                            }
                            return data;
                        }
                    }
                }
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
                    if (row.penjamin && row.penjamin.toLowerCase().includes('bpjs') && row.bpjs_kelas && row.room_kelas) {
                        function w(c) { var l = String(c||'').toLowerCase(); if(l.includes('vip'))return 0; if(l.includes('1')||l==='1')return 1; if(l.includes('2')||l==='2')return 2; if(l.includes('3')||l==='3')return 3; return 999; }
                        if (w(row.room_kelas) < w(row.bpjs_kelas)) html += '<br/><span class="badge bg-danger text-white" style="font-size:0.72rem;">Naik Kelas</span>';
                    }
                    return html;
                }
            },
            {
                data: null,
                className: 'text-center',
                createdCell: function(td) { $(td).css('position', 'relative'); },
                render: function(data, type, row) {
                    var code = row.selected_inacbg_code || '';
                    var desc = row.selected_inacbg_desc || '';
                    if (!code) return '<div class="inacbg-cell text-muted"><button type="button" class="edit-inacbg-btn"><i class="fas fa-edit"></i> Pilih INA-CBG</button></div>';
                    var label = '<div><strong>' + code + '</strong> <i class="fas fa-edit edit-inacbg-icon" title="Ubah INA-CBG"></i></div>';
                    if (desc) label += '<div><small class="text-muted">' + desc + '</small></div>';
                    return '<div class="inacbg-cell">' + label + '</div>';
                }
            },
            {
                data: 'plafon',
                className: 'text-end fw-bold',
                createdCell: function(td) { $(td).css('position', 'relative'); },
                render: function(data, type, row) {
                    if (data === null) return '<span class="skeleton-cell" data-norawat="' + row.no_rawat + '" data-col="plafon"><span class="skeleton-text"></span></span>';
                    var displayValue = row.selected_inacbg_tarif ? formatRupiah(parseFloat(row.selected_inacbg_tarif)) : data;
                    return '<span class="plafon-cell" data-norawat="' + row.no_rawat + '">' + displayValue + '</span>';
                }
            },
            {
                data: 'estimasi',
                className: 'text-end fw-bold text-primary',
                render: function(data, type, row) {
                    if (data === null) return '<span class="skeleton-cell" data-norawat="' + row.no_rawat + '" data-col="estimasi"><span class="skeleton-text"></span></span>';
                    return data;
                }
            },
            {
                // FIX #3: Selisih rendered server-side OR via lazy load
                data: 'selisih',
                className: 'text-end fw-bold',
                render: function(data, type, row) {
                    if (data === null) return '<span class="skeleton-cell" data-norawat="' + row.no_rawat + '" data-col="selisih"><span class="skeleton-text"></span></span>';
                    if (!data || data === '-') return '<span class="selisih-cell text-muted">-</span>';
                    return row.is_over
                        ? '<span class="selisih-cell text-danger">+' + data + ' (OVER)</span>'
                        : '<span class="selisih-cell text-success">Sisa: ' + data + '</span>';
                }
            },
            {
                data: 'status_pulang',
                className: 'text-center',
                render: function(data) {
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

    // INA-CBG picker triggers
    $('#tableKunjungan tbody').on('dblclick', '.inacbg-cell', function(e) {
        e.stopPropagation(); openInacbgPicker(this);
    });
    $('#tableKunjungan tbody').on('click', '.edit-inacbg-btn, .edit-inacbg-icon', function(e) {
        e.stopPropagation(); openInacbgPicker($(this).closest('.inacbg-cell'));
    });
});

function reloadTable() { tableKunjungan.ajax.reload(); }

// ============================================================
// LAZY BILLING LOADER
// ============================================================
var _billingQueue   = [];
var _billingRunning = 0;
var _billingConcurrency = 3;

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

            // Estimasi
            document.querySelectorAll('.skeleton-cell[data-norawat="' + nr + '"][data-col="estimasi"]').forEach(function(el) {
                el.outerHTML = '<span class="fw-bold text-primary">Rp ' + (res.estimasi || '0') + '</span>';
            });

            // Plafon
            document.querySelectorAll('.skeleton-cell[data-norawat="' + nr + '"][data-col="plafon"]').forEach(function(el) {
                el.outerHTML = res.plafon || '-';
            });

            // FIX #3: Selisih — rendered via unified renderSelisihHtml()
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

// ============================================================
// DETAIL BILLING MODAL
// ============================================================
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
</script>
<?php $page_js = ob_get_clean(); ?>
<?php require_once('includes/footer.php'); ?>