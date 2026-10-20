<?php
$page_title = 'Kepegawaian';
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/kepegawaian.php';
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$kp_access = false;
try {
    $kp_access = kp_can_access($koneksi);
} catch (Throwable $error) {
    error_log('[Kepegawaian Akses] ' . $error->getMessage());
}
if (!$kp_access) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Akses Ditolak</title><h3>Akses Kepegawaian ditolak.</h3><p>Anda tidak memiliki hak akses admin pegawai.</p><a href="dashboard.php">Kembali ke dashboard</a></html>';
    exit;
}
require_once __DIR__ . '/includes/header.php';
$kp_error = '';
$kp_options = [];
$kp_write = false;
$kp_date = date('Y-m-d');
try {
    $kp_options = kp_options($koneksi);
    $kp_write = kp_can_write($koneksi);
    $kp_date = kp_reference_date($koneksi);
} catch (Throwable $error) {
    error_log('[Kepegawaian] ' . $error->getMessage());
    $kp_error = 'Data referensi belum dapat dimuat. Silakan hubungi administrator.';
}
if (empty($_SESSION['kp_csrf'])) $_SESSION['kp_csrf'] = bin2hex(random_bytes(32));
function kp_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<style>
.kp-tabs { gap:4px; overflow-x:auto; overflow-y:hidden; flex-wrap:nowrap; padding-bottom:2px; }
.kp-tabs .nav-link { white-space:nowrap; padding:8px 16px; font-size:.82rem; border-radius:6px 6px 0 0; }
.kp-stat { border-radius:8px; padding:14px 16px; min-height:78px; color:#fff; display:flex; justify-content:space-between; align-items:center; }
.kp-stat strong { font-size:1.3rem; display:block; }
.kp-stat small { font-weight:700; }
.kp-stat i { font-size:1.7rem; opacity:.65; }
#kpTable th, #kpTable td { font-size:.76rem; white-space:nowrap; vertical-align:middle; }
#kpTable th { font-weight:800; }
#kpForm .form-label { font-size:.8rem; font-weight:600; margin-bottom:4px; }
.kp-section { font-size:.9rem; font-weight:700; padding-bottom:8px; border-bottom:1px solid var(--bs-secondary); margin-bottom:14px; }
.kp-message { white-space:pre-wrap; }
/* ========================================================
   THEME VARIABLES UNTUK MODUL KEPEGAWAIAN (DUAL THEME)
   ======================================================== */
:root {
    /* DEFAULT: THEME LIGHT (MODE TERANG / WHITE MODE) */
    --kp-text-title: #0f172a;          /* Slate 900 - hitam pekat untuk judul & nama */
    --kp-text-body: #1e293b;           /* Slate 800 - teks utama */
    --kp-text-muted: #64748b;          /* Slate 500 - subtext & meta */
    --kp-text-subtle: #94a3b8;         /* Slate 400 - teks kecil / placeholder */
    
    --kp-border-color: #e2e8f0;        /* Slate 200 - border halus */
    --kp-border-strong: #cbd5e1;       /* Slate 300 - border kontrol & badge */
    
    --kp-bg-surface: #ffffff;          /* Background surface putih */
    --kp-bg-subtle: #f8fafc;           /* Slate 50 - background panel header */
    --kp-bg-input: #ffffff;            /* Background input */
    
    /* Tab Navigation */
    --kp-tab-text: #475569;
    --kp-tab-bg: #f8fafc;
    --kp-tab-border: #e2e8f0;
    --kp-tab-hover-text: #0d6efd;
    --kp-tab-hover-bg: #eff6ff;
    --kp-tab-active-text: #0d6efd;
    --kp-tab-active-bg: #ffffff;
    --kp-tab-active-border: #0d6efd;
    
    /* Top & General Badges */
    --kp-badge-bg: #f1f5f9;
    --kp-badge-text: #334155;
    --kp-badge-border: #cbd5e1;
    
    /* Category Filter Pills (Tab Hijau Khas SIMRS) */
    --kp-cat-text: #15803d;            /* Hijau tua kontras & tajam */
    --kp-cat-bg: #f0fdf4;
    --kp-cat-border: #86efac;
    --kp-cat-hover-bg: #dcfce7;
    --kp-cat-hover-text: #166534;
    
    /* Search Box & Status Filter */
    --kp-search-bg: #ffffff;
    --kp-search-border: #cbd5e1;
    --kp-search-focus-border: #0d6efd;
    --kp-search-focus-glow: rgba(13, 110, 253, 0.2);
    --kp-select-option-bg: #ffffff;
    --kp-select-option-text: #1e293b;
    
    /* Employee List Cards */
    --kp-card-item-border: #f1f5f9;
    --kp-card-item-hover-bg: #f0fdf4;
    --kp-card-item-hover-border: #16a34a;
    --kp-card-item-active-bg: #eff6ff;
    --kp-card-item-active-border: #0d6efd;
    --kp-card-item-active-name: #0d6efd;
    --kp-card-item-active-sub: #2563eb;
    --kp-card-item-active-badge-bg: #dbeafe;
    --kp-card-item-active-badge-text: #1e40af;
    
    /* Accordion Groups */
    --kp-acc-btn-bg: #f8fafc;
    --kp-acc-btn-text: #0f172a;
    --kp-acc-btn-open-bg: #ecfdf5;
    --kp-acc-btn-open-text: #15803d;
    --kp-acc-btn-open-border: #a7f3d0;
    --kp-acc-arrow-filter: none;       /* Panah normal abu-abu gelap */
    
    /* Table inside Accordion */
    --kp-tbl-th-bg: #f8fafc;
    --kp-tbl-th-text: #334155;
    --kp-tbl-th-border: #e2e8f0;
    --kp-doc-name: #0f172a;
    --kp-doc-name-empty: #334155;
    
    /* File Chip & Action Buttons */
    --kp-file-chip-bg: #f8fafc;
    --kp-file-chip-border: #cbd5e1;
    --kp-file-btn-bg: #ffffff;
    --kp-file-btn-text: #0284c7;
    --kp-file-btn-hover-bg: #0284c7;
    --kp-file-btn-hover-text: #ffffff;
    
    /* Minimalist Scrollbar */
    --kp-scroll-thumb: rgba(100, 116, 139, 0.35);
    --kp-scroll-thumb-hover: #0d6efd;
}

/* OVERRIDES UNTUK DARK MODE (SOLID DARK NAVY & ANIMATED MESH GRADIENT) */
html.theme-glass-solid,
html.theme-glass-animated {
    --kp-text-title: #f8fafc;
    --kp-text-body: #f1f5f9;
    --kp-text-muted: #94a3b8;
    --kp-text-subtle: #64748b;
    
    --kp-border-color: rgba(255, 255, 255, 0.08);
    --kp-border-strong: rgba(255, 255, 255, 0.16);
    
    --kp-bg-surface: rgba(255, 255, 255, 0.03);
    --kp-bg-subtle: rgba(255, 255, 255, 0.02);
    --kp-bg-input: rgba(255, 255, 255, 0.05);
    
    /* Tab Navigation */
    --kp-tab-text: #94a3b8;
    --kp-tab-bg: rgba(255, 255, 255, 0.02);
    --kp-tab-border: transparent;
    --kp-tab-hover-text: #38bdf8;
    --kp-tab-hover-bg: rgba(56, 189, 248, 0.08);
    --kp-tab-active-text: #ffffff;
    --kp-tab-active-bg: rgba(13, 110, 253, 0.28);
    --kp-tab-active-border: #38bdf8;
    
    /* Top & General Badges */
    --kp-badge-bg: rgba(255, 255, 255, 0.08);
    --kp-badge-text: #e2e8f0;
    --kp-badge-border: rgba(255, 255, 255, 0.16);
    
    /* Category Filter Pills */
    --kp-cat-text: #4ade80;
    --kp-cat-bg: rgba(25, 135, 84, 0.12);
    --kp-cat-border: rgba(25, 135, 84, 0.45);
    --kp-cat-hover-bg: rgba(25, 135, 84, 0.28);
    --kp-cat-hover-text: #ffffff;
    
    /* Search Box & Status Filter */
    --kp-search-bg: rgba(255, 255, 255, 0.05);
    --kp-search-border: rgba(255, 255, 255, 0.15);
    --kp-search-focus-border: #38bdf8;
    --kp-search-focus-glow: rgba(56, 189, 248, 0.25);
    --kp-select-option-bg: #0f172a;
    --kp-select-option-text: #f8fafc;
    
    /* Employee List Cards */
    --kp-card-item-border: rgba(255, 255, 255, 0.07);
    --kp-card-item-hover-bg: rgba(25, 135, 84, 0.14);
    --kp-card-item-hover-border: #198754;
    --kp-card-item-active-bg: rgba(13, 110, 253, 0.28);
    --kp-card-item-active-border: #38bdf8;
    --kp-card-item-active-name: #ffffff;
    --kp-card-item-active-sub: #93c5fd;
    --kp-card-item-active-badge-bg: rgba(255, 255, 255, 0.25);
    --kp-card-item-active-badge-text: #ffffff;
    
    /* Accordion Groups */
    --kp-acc-btn-bg: rgba(255, 255, 255, 0.03);
    --kp-acc-btn-text: #f8fafc;
    --kp-acc-btn-open-bg: rgba(25, 135, 84, 0.16);
    --kp-acc-btn-open-text: #4ade80;
    --kp-acc-btn-open-border: rgba(25, 135, 84, 0.4);
    --kp-acc-arrow-filter: invert(1) brightness(1.5);
    
    /* Table inside Accordion */
    --kp-tbl-th-bg: rgba(255, 255, 255, 0.04);
    --kp-tbl-th-text: #cbd5e1;
    --kp-tbl-th-border: rgba(255, 255, 255, 0.08);
    --kp-doc-name: #f8fafc;
    --kp-doc-name-empty: #cbd5e1;
    
    /* File Chip & Action Buttons */
    --kp-file-chip-bg: rgba(255, 255, 255, 0.05);
    --kp-file-chip-border: rgba(255, 255, 255, 0.14);
    --kp-file-btn-bg: rgba(255, 255, 255, 0.05);
    --kp-file-btn-text: #e2e8f0;
    --kp-file-btn-hover-bg: rgba(56, 189, 248, 0.25);
    --kp-file-btn-hover-text: #ffffff;
    
    /* Minimalist Scrollbar */
    --kp-scroll-thumb: rgba(148, 163, 184, 0.28);
    --kp-scroll-thumb-hover: rgba(56, 189, 248, 0.6);
}

/* Minimalist Custom Scrollbar */
.kp-emp-list-container,
.kp-tabs,
.accordion-body,
#kpPreviewBody {
    scrollbar-width: thin;
    scrollbar-color: var(--kp-scroll-thumb) transparent;
}
.kp-emp-list-container::-webkit-scrollbar,
.kp-tabs::-webkit-scrollbar,
#kpPreviewBody::-webkit-scrollbar {
    width: 5px;
    height: 5px;
}
.kp-emp-list-container::-webkit-scrollbar-track,
.kp-tabs::-webkit-scrollbar-track,
#kpPreviewBody::-webkit-scrollbar-track {
    background: transparent;
}
.kp-emp-list-container::-webkit-scrollbar-thumb,
.kp-tabs::-webkit-scrollbar-thumb,
#kpPreviewBody::-webkit-scrollbar-thumb {
    background: var(--kp-scroll-thumb);
    border-radius: 10px;
    transition: background 0.2s ease;
}
.kp-emp-list-container::-webkit-scrollbar-thumb:hover,
.kp-tabs::-webkit-scrollbar-thumb:hover,
#kpPreviewBody::-webkit-scrollbar-thumb:hover {
    background: var(--kp-scroll-thumb-hover);
}

/* Tab Navigation Styling */
.kp-tabs {
    border-bottom: 2px solid var(--kp-border-color) !important;
}
.kp-tabs .nav-link {
    color: var(--kp-tab-text) !important;
    background: var(--kp-tab-bg) !important;
    border: 1px solid var(--kp-tab-border) !important;
    border-bottom: none !important;
    font-weight: 600;
    font-size: .83rem;
    padding: 8px 16px;
    border-radius: 6px 6px 0 0;
    transition: all 0.2s ease;
}
.kp-tabs .nav-link:hover {
    color: var(--kp-tab-hover-text) !important;
    background: var(--kp-tab-hover-bg) !important;
}
.kp-tabs .nav-link.active {
    color: var(--kp-tab-active-text) !important;
    background: var(--kp-tab-active-bg) !important;
    border-color: var(--kp-border-strong) var(--kp-border-strong) transparent !important;
    border-bottom: 2px solid var(--kp-tab-active-border) !important;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.12);
}

/* Header & Badges */
.kp-top-badge {
    background: var(--kp-badge-bg) !important;
    color: var(--kp-badge-text) !important;
    border: 1px solid var(--kp-badge-border) !important;
    font-size: .78rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 6px;
}
.kp-emp-counter {
    background: var(--kp-badge-bg) !important;
    color: var(--kp-badge-text) !important;
    border: 1px solid var(--kp-badge-border) !important;
    font-size: .78rem;
    font-weight: 600;
    padding: 5px 12px;
    border-radius: 6px;
}
.kp-card-header {
    background: var(--kp-bg-subtle) !important;
    border-bottom: 1px solid var(--kp-border-color) !important;
    color: var(--kp-text-title) !important;
}

/* Category Filter Pills */
.kp-cat-filter {
    font-size: .8rem;
    font-weight: 600;
    border-radius: 20px;
    padding: 5px 14px;
    transition: all .2s;
    border: 1px solid var(--kp-cat-border) !important;
    background: var(--kp-cat-bg) !important;
    color: var(--kp-cat-text) !important;
}
.kp-cat-filter:hover {
    transform: translateY(-1px);
    background: var(--kp-cat-hover-bg) !important;
    color: var(--kp-cat-hover-text) !important;
}
.kp-cat-filter.btn-success,
.kp-cat-filter.active {
    background: #198754 !important;
    color: #ffffff !important;
    border-color: #198754 !important;
    box-shadow: 0 2px 8px rgba(25, 135, 84, 0.35);
}

/* Search Box Styling */
.kp-search-group {
    position: relative;
    display: flex;
    align-items: center;
    background: var(--kp-search-bg);
    border: 1px solid var(--kp-search-border);
    border-radius: 8px;
    padding: 2px 8px;
    transition: all 0.2s ease;
}
.kp-search-group:focus-within {
    border-color: var(--kp-search-focus-border);
    box-shadow: 0 0 0 3px var(--kp-search-focus-glow);
}
.kp-search-icon {
    color: var(--kp-text-muted);
    font-size: .82rem;
    margin-right: 8px;
    margin-left: 4px;
    pointer-events: none;
}
.kp-search-field {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    color: var(--kp-text-body) !important;
    padding-left: 0 !important;
    padding-right: 24px !important;
    font-size: .82rem;
}
.kp-search-field::placeholder {
    color: var(--kp-text-subtle) !important;
    opacity: 0.9;
}
.kp-search-clear {
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--kp-text-muted) !important;
    padding: 2px 6px;
    text-decoration: none;
    font-size: .75rem;
}
.kp-search-clear:hover {
    color: #ef4444 !important;
}

/* Status Filter Select */
.kp-filter-select {
    background-color: var(--kp-search-bg) !important;
    border: 1px solid var(--kp-search-border) !important;
    color: var(--kp-text-body) !important;
    border-radius: 8px;
}
.kp-filter-select option {
    background-color: var(--kp-select-option-bg) !important;
    color: var(--kp-select-option-text) !important;
}
html.theme-glass-solid .kp-filter-select option,
html.theme-glass-animated .kp-filter-select option,
html.theme-glass-solid .kp-category-select option,
html.theme-glass-animated .kp-category-select option {
    background-color: #0f172a !important;
    color: #f8fafc !important;
}
html.theme-light .kp-filter-select option,
html.theme-light .kp-category-select option {
    background-color: #ffffff !important;
    color: #1e293b !important;
}

/* Employee Cards (Left List) */
.kp-emp-list-container {
    max-height: calc(100vh - 310px);
    min-height: 480px;
    overflow-y: auto;
}
.kp-emp-card {
    background: transparent !important;
    color: inherit !important;
    border-color: var(--kp-card-item-border) !important;
    transition: all .15s ease-in-out;
    border-left: 3px solid transparent !important;
}
.kp-emp-card:hover {
    background: var(--kp-card-item-hover-bg) !important;
    border-left-color: var(--kp-card-item-hover-border) !important;
}
.kp-emp-card.active {
    background: var(--kp-card-item-active-bg) !important;
    border-left-color: var(--kp-card-item-active-border) !important;
}
.kp-emp-name {
    color: var(--kp-text-title) !important;
}
.kp-emp-subtext {
    color: var(--kp-text-muted) !important;
}
.kp-emp-meta {
    color: var(--kp-text-muted) !important;
}
.kp-emp-progress-text {
    color: var(--kp-text-muted) !important;
}
.kp-badge-nip {
    background: var(--kp-badge-bg) !important;
    color: var(--kp-badge-text) !important;
    border: 1px solid var(--kp-badge-border);
}
.kp-badge-active-nip {
    background: var(--kp-card-item-active-badge-bg) !important;
    color: var(--kp-card-item-active-badge-text) !important;
    border: 1px solid var(--kp-card-item-active-badge-text);
}
.kp-emp-card.active .kp-emp-name {
    color: var(--kp-card-item-active-name) !important;
    font-weight: 700;
}
.kp-emp-card.active .kp-emp-subtext,
.kp-emp-card.active .kp-emp-meta {
    color: var(--kp-card-item-active-sub) !important;
}
.kp-emp-card.active .kp-emp-progress-text {
    color: var(--kp-card-item-active-name) !important;
    font-weight: 700;
}

/* Detail Panel Elements */
.kp-detail-name {
    color: var(--kp-text-title) !important;
}
.kp-detail-subtext {
    color: var(--kp-text-muted) !important;
}
.kp-detail-meta {
    color: var(--kp-text-muted) !important;
}
.kp-jk-badge {
    background: var(--kp-badge-bg) !important;
    color: var(--kp-badge-text) !important;
    border-color: var(--kp-badge-border) !important;
}
.kp-category-select {
    background-color: var(--kp-cat-bg) !important;
    border: 1px solid var(--kp-cat-border) !important;
    color: var(--kp-cat-text) !important;
    border-radius: 6px;
}
.kp-category-select option {
    background-color: var(--kp-select-option-bg) !important;
    color: var(--kp-select-option-text) !important;
}

/* Accordion Groups */
.kp-berkas-groups .accordion-item {
    background: var(--kp-bg-surface) !important;
    border-color: var(--kp-border-color) !important;
}
.kp-berkas-groups .accordion-button {
    background: var(--kp-acc-btn-bg) !important;
    color: var(--kp-acc-btn-text) !important;
    border-color: transparent !important;
}
.kp-berkas-groups .accordion-button:not(.collapsed) {
    background: var(--kp-acc-btn-open-bg) !important;
    color: var(--kp-acc-btn-open-text) !important;
    border-bottom: 1px solid var(--kp-acc-btn-open-border) !important;
    box-shadow: none;
}
.kp-berkas-groups .accordion-button::after {
    filter: var(--kp-acc-arrow-filter) !important;
}
.kp-table-header th {
    background: var(--kp-tbl-th-bg) !important;
    color: var(--kp-tbl-th-text) !important;
    border-color: var(--kp-tbl-th-border) !important;
}
.kp-doc-name-filled {
    color: var(--kp-doc-name) !important;
}
.kp-doc-name-empty {
    color: var(--kp-doc-name-empty) !important;
}

/* File Chips */
.kp-file-chip {
    border: 1px solid var(--kp-file-chip-border) !important;
    background: var(--kp-file-chip-bg);
}
.kp-file-btn {
    background: var(--kp-file-btn-bg) !important;
    color: var(--kp-file-btn-text) !important;
    border: 1px solid var(--kp-file-chip-border) !important;
}
.kp-file-btn:hover {
    background: var(--kp-file-btn-hover-bg) !important;
    color: var(--kp-file-btn-hover-text) !important;
}
.kp-file-del-btn {
    border: none !important;
    border-left: 1px solid var(--kp-file-chip-border) !important;
    background: rgba(239, 68, 68, 0.1) !important;
    color: #ef4444 !important;
}
.kp-file-del-btn:hover {
    background: #ef4444 !important;
    color: #ffffff !important;
}
.btn-xs { padding: 2px 7px; font-size: .75rem; border-radius: 4px; }

/* Dashboard SIP Monitoring */
.kp-sip-dashboard-card {
    border-radius: 12px !important;
    border: 1px solid var(--kp-border-color) !important;
    background: var(--kp-bg-surface) !important;
}
.kp-sip-title {
    color: var(--kp-text-title) !important;
    font-size: 0.95rem;
}
.kp-sip-tile {
    padding: 10px 12px;
    border-radius: 10px;
    border: 1px solid var(--kp-border-color);
    background: var(--kp-bg-subtle);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
    position: relative;
}
.kp-sip-tile:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: var(--kp-border-strong);
}
.kp-sip-tile.active {
    box-shadow: 0 0 0 2px #0d6efd, 0 4px 12px rgba(13, 110, 253, 0.15);
    background: var(--kp-bg-surface);
}
.kp-sip-tile-label {
    font-size: 0.74rem;
    font-weight: 600;
    color: var(--kp-text-muted);
}
.kp-sip-tile-val {
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1.2;
    margin: 3px 0 2px 0;
    font-family: monospace;
}
.kp-sip-tile-sub {
    font-size: 0.68rem;
    color: var(--kp-text-subtle);
}
.kp-sip-tile-icon {
    font-size: 1.1rem;
    opacity: 0.85;
}
.kp-sip-input-card {
    background: var(--kp-bg-subtle);
    border: 1px dashed var(--kp-border-strong) !important;
}
.kp-sip-form-title {
    color: var(--kp-text-title) !important;
}

/* SweetAlert2 Styling & Theme Integration */
.swal2-container {
    z-index: 10050 !important;
}
.kp-swal-popup {
    border-radius: 16px !important;
    padding: 1.5rem !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45) !important;
}
.kp-swal-popup .swal2-title {
    font-size: 1.25rem !important;
    font-weight: 700 !important;
    padding-top: 0.5rem !important;
}
.kp-swal-popup .swal2-html-container {
    margin: 1rem 0.5rem !important;
    font-size: 0.9rem !important;
}
.kp-swal-popup .swal2-actions {
    margin-top: 1.25rem !important;
    gap: 10px !important;
}
.kp-swal-popup .swal2-styled {
    border-radius: 8px !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
    padding: 8px 18px !important;
    box-shadow: none !important;
}

/* Dark / Glass Mode SweetAlert */
html.theme-glass-solid .kp-swal-popup,
html.theme-glass-animated .kp-swal-popup {
    background: #1e293b !important;
    color: #f8fafc !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.75) !important;
}
html.theme-glass-solid .kp-swal-popup .swal2-title,
html.theme-glass-animated .kp-swal-popup .swal2-title {
    color: #f8fafc !important;
}
html.theme-glass-solid .kp-swal-popup .swal2-html-container,
html.theme-glass-animated .kp-swal-popup .swal2-html-container {
    color: #cbd5e1 !important;
}
html.theme-glass-solid .kp-swal-filebox,
html.theme-glass-animated .kp-swal-filebox {
    background: rgba(239, 68, 68, 0.12) !important;
    border: 1px dashed rgba(239, 68, 68, 0.4) !important;
    color: #fca5a5 !important;
}

/* Light Mode SweetAlert */
html.theme-light .kp-swal-popup {
    background: #ffffff !important;
    color: #1e293b !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
}
html.theme-light .kp-swal-popup .swal2-title {
    color: #0f172a !important;
}
html.theme-light .kp-swal-popup .swal2-html-container {
    color: #475569 !important;
}
html.theme-light .kp-swal-filebox {
    background: #fef2f2 !important;
    border: 1px dashed #f87171 !important;
    color: #b91c1c !important;
}
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap pt-3 pb-2 mb-3 border-bottom">
    <div><h4 class="mb-0"><i class="fas fa-id-card text-primary me-2"></i>Kepegawaian</h4>
    <small class="text-muted">Data pegawai dan index kepegawaian RS Assalam</small></div>
    <span class="badge kp-top-badge border"><?php echo $kp_write ? '<i class="fas fa-shield-alt text-success me-1"></i>Akses kelola pegawai' : '<i class="fas fa-eye text-info me-1"></i>Akses lihat data'; ?></span>
</div>
<?php if ($kp_error): ?><div class="alert alert-danger"><?php echo kp_escape($kp_error); ?></div><?php endif; ?>
<div id="kpMessage" class="alert kp-message d-none" role="status" aria-live="polite"></div>
<div class="card shadow-sm mb-3"><div class="card-body py-2">
    <ul class="nav nav-tabs kp-tabs mb-3" role="tablist" aria-label="Kepegawaian">
        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" aria-selected="true" aria-controls="kpDataPanel" id="kpListTab" data-kp-tab="list"><i class="fas fa-list-ul me-2"></i>List Data</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" aria-controls="kpInputPanel" id="kpInputTab" data-kp-tab="input"><i class="fas fa-user-plus me-2"></i>Input Data</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" aria-controls="kpDataPanel" id="kpIndexTab" data-kp-tab="index"><i class="fas fa-calculator me-2"></i>Index Pegawai</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" aria-controls="kpBerkasPanel" id="kpBerkasTab" data-kp-tab="berkas"><i class="fas fa-folder-open me-2 text-success"></i>Berkas Pegawai</button></li>
    </ul>
    <form id="kpFilters" class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small fw-bold" for="kpStatus">Status</label><select id="kpStatus" class="form-select form-select-sm"><option value="">Semua Status</option><?php foreach (kp_fields()['stts_aktif'][2] as $status): ?><option value="<?php echo kp_escape($status); ?>" <?php echo $status === 'AKTIF' ? 'selected' : ''; ?>><?php echo kp_escape($status); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small fw-bold" for="kpDepartment">Departemen</label><select id="kpDepartment" class="form-select form-select-sm"><option value="">Semua Departemen</option><?php foreach ($kp_options['departemen'] ?? [] as $option): ?><option value="<?php echo kp_escape($option['value']); ?>"><?php echo kp_escape($option['label']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small fw-bold" for="kpDate">Tanggal Acuan Perhitungan</label><input id="kpDate" type="date" class="form-control form-control-sm" required value="<?php echo kp_escape($kp_date); ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-bold" for="kpKeyword">Kata Kunci</label><input id="kpKeyword" class="form-control form-control-sm" maxlength="150" placeholder="NIP, nama, jabatan, pendidikan..."></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary btn-sm flex-grow-1" id="kpLoad" type="submit"><i class="fas fa-check me-1"></i>Tampilkan</button><button type="button" class="btn btn-success btn-sm" id="kpExport" title="Export CSV" aria-label="Export CSV"><i class="fas fa-file-excel"></i></button></div>
    </form>
    <small class="text-muted d-block mt-2" id="kpReferenceHelp">Tanggal acuan awal memakai hari ini: <?php echo kp_escape($kp_date); ?>. Pilih tanggal lain untuk melihat perhitungan pada periode tersebut. Index pendidikan, status, dan jabatan mengikuti nilai master kepegawaian.</small>
</div></div>
<div id="kpDataPanel" role="tabpanel" aria-labelledby="kpListTab">
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="kp-stat bg-primary"><div><strong id="kpCount">0</strong><small>TOTAL PEGAWAI</small></div><i class="fas fa-users"></i></div></div>
        <div class="col-md-3"><div class="kp-stat bg-success"><div><strong id="kpActive">0</strong><small>PEGAWAI AKTIF</small></div><i class="fas fa-user-check"></i></div></div>
        <div class="col-md-3"><div class="kp-stat bg-info"><div><strong id="kpUnits">0</strong><small>DEPARTEMEN</small></div><i class="fas fa-building"></i></div></div>
        <div class="col-md-3"><div class="kp-stat bg-warning"><div><strong id="kpIndexSum">0</strong><small>TOTAL INDEX</small></div><i class="fas fa-chart-bar"></i></div></div>
    </div>
    <div id="kpIndexEditor" class="card shadow-sm mb-3 d-none"><div class="card-body">
        <h6 id="kpIndexName">Edit Index Pegawai</h6>
        <form id="kpIndexForm" class="row g-3"><input type="hidden" name="id">
            <div class="col-md-3"><label class="form-label" for="kpIndek">Index Struktural</label><input id="kpIndek" name="indek" type="number" min="0" max="127" step="1" required class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label" for="kpPengurang">Pengurang (%)</label><input id="kpPengurang" name="pengurang" type="number" min="0" max="100" step="any" required class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label" for="kpCuti">Cuti Diambil Manual</label><input id="kpCuti" name="cuti_diambil" type="number" min="0" max="2147483647" step="1" required class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label" for="kpDankes">Dana Kesehatan / Tahun</label><input id="kpDankes" name="dankes" type="number" min="0" max="1000000000000" step="any" required class="form-control form-control-sm"></div>
            <div class="col-12"><small class="text-muted">Cuti manual ditambahkan ke cuti lampiran dan pengajuan yang disetujui. Pengurang mengikuti rumus Khanza: 0 memakai total penuh; nilai di atas 0 menjadi persentase pengali total index.</small></div>
            <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm">Simpan Index</button><button type="button" class="btn btn-secondary btn-sm" id="kpCancelIndex">Batal</button></div>
        </form>
    </div></div>
    <div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between align-items-center"><h6 class="mb-0" id="kpTableTitle">List Data Pegawai</h6><span class="badge bg-light text-dark border" id="kpTableInfo">Memuat data...</span></div><div class="card-body"><table id="kpTable" class="table table-bordered table-striped table-hover w-100"></table><small id="kpIndexNote" class="text-muted d-none">Index, gaji pokok, cuti, dan dana kesehatan dihitung sesuai sumber penggajian Khanza dengan tanggal acuan di atas.</small></div></div>
</div>
<div id="kpInputPanel" class="card shadow-sm mb-3 d-none" role="tabpanel" aria-labelledby="kpInputTab"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3"><h6 id="kpFormTitle" class="mb-0">Input Data Pegawai</h6><button type="button" id="kpNew" class="btn btn-outline-primary btn-sm">Pegawai Baru</button></div>
    <?php if (!$kp_write): ?><div class="alert alert-info py-2">Data dapat dilihat. Penyimpanan memerlukan hak akses kelola pegawai.</div><?php endif; ?>
    <form id="kpForm"><input type="hidden" name="id" value="0"><fieldset <?php echo !$kp_write || $kp_error ? 'disabled' : ''; ?>><div class="row g-3">
    <?php
    $sections = ['nik' => 'Identitas & Penempatan', 'npwp' => 'Biodata & Pendidikan', 'mulai_kerja' => 'Kepegawaian & Rekening'];
    foreach (kp_fields() as $field => $def):
        if (isset($sections[$field])): ?><div class="col-12 mt-4"><div class="kp-section"><?php echo kp_escape($sections[$field]); ?></div></div><?php endif; ?>
        <div class="col-md-6 col-xl-4"><label for="kpField_<?php echo $field; ?>" class="form-label"><?php echo kp_escape($def[0]); ?><?php if ($def[3]): ?><span class="text-danger"> *</span><?php endif; ?></label>
        <?php if ($def[1] === 'master' || $def[1] === 'select'): ?>
            <select id="kpField_<?php echo $field; ?>" name="<?php echo $field; ?>" class="form-select form-select-sm" <?php echo $def[3] ? 'required' : ''; ?>>
                <?php if ($def[1] === 'master'): ?><option value="">Pilih...</option><?php endif; ?>
                <?php foreach ($kp_options[$field] ?? [] as $option): ?><option value="<?php echo kp_escape($option['value']); ?>"><?php echo kp_escape($field === 'indexins' ? $option['value'] . ' — ' . $option['label'] . '%' : $option['label']); ?></option><?php endforeach; ?>
            </select>
        <?php else: ?>
            <input id="kpField_<?php echo $field; ?>" name="<?php echo $field; ?>" type="<?php echo $def[1]; ?>" class="form-control form-control-sm" <?php echo $def[3] ? 'required' : ''; ?> <?php if ($def[1] === 'text') echo 'maxlength="' . $def[2] . '"'; ?> <?php if ($def[1] === 'number') echo 'min="' . $def[2][0] . '" max="' . $def[2][1] . '" step="1" value="-5"'; ?>>
        <?php endif; ?><?php if ($field === 'nik'): ?><small id="kpNipHelp" class="text-muted d-none">NIP pegawai yang sudah tersimpan dikunci agar hubungan data Khanza tetap sesuai.</small><?php endif; ?></div>
    <?php endforeach; ?>
    <div class="col-12"><small class="text-muted">Wajib Masuk: -5 mengikuti jadwal; -4 bulan dikurangi hari Ahad; -3 bulan dikurangi dua hari dan libur nasional; -2 bulan dikurangi empat hari; -1 kosong; 0 bulan dikurangi hari libur; angka positif jumlah hari.</small></div>
    <div class="col-12 d-flex gap-2 mt-4"><button type="submit" id="kpSave" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Simpan</button><button type="button" id="kpReset" class="btn btn-secondary btn-sm">Kosongkan</button></div>
    </div></fieldset></form>
</div></div>

<!-- Panel Berkas Pegawai -->
<div id="kpBerkasPanel" class="d-none" role="tabpanel" aria-labelledby="kpBerkasTab">
    <!-- Dashboard Monitoring SIP Nakes -->
    <div class="card shadow-sm mb-3 kp-sip-dashboard-card" id="kpSipDashboard">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-stethoscope text-success fs-5"></i>
                    <div>
                        <h6 class="mb-0 fw-bold kp-sip-title">Dashboard Monitoring Surat Izin Praktik (SIP) Nakes</h6>
                        <small class="kp-emp-subtext" style="font-size: .74rem;">Pemantauan masa berlaku izin praktik Dokter, Perawat, Bidan, & Nakes lainnya</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border d-none d-md-inline" style="font-size: .72rem;">
                        <i class="fas fa-filter text-primary me-1"></i>Klik kartu untuk filter data
                    </span>
                    <button type="button" class="btn btn-xs btn-outline-secondary kp-sip-reset-btn d-none" id="kpSipFilterReset" title="Tampilkan Semua Nakes">
                        <i class="fas fa-undo me-1"></i>Reset Filter SIP
                    </button>
                </div>
            </div>
            <!-- 6 KPI Interactive Cards -->
            <div class="row g-2" id="kpSipCardsRow">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="kp-sip-tile" data-sip-filter="nakes" role="button" tabindex="0" title="Klik untuk menampilkan seluruh Nakes wajib SIP">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="kp-sip-tile-label">Total Nakes</span>
                            <i class="fas fa-user-md kp-sip-tile-icon text-primary"></i>
                        </div>
                        <div class="kp-sip-tile-val text-primary" id="kpSipCountTotal">0</div>
                        <div class="kp-sip-tile-sub">Wajib SIP</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="kp-sip-tile" data-sip-filter="active" role="button" tabindex="0" title="Klik untuk memfilter SIP aktif (>90 hari)">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="kp-sip-tile-label">Masa Aman</span>
                            <i class="fas fa-shield-alt kp-sip-tile-icon text-success"></i>
                        </div>
                        <div class="kp-sip-tile-val text-success" id="kpSipCountActive">0</div>
                        <div class="kp-sip-tile-sub">&gt; 90 hari tersisa</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="kp-sip-tile" data-sip-filter="warning" role="button" tabindex="0" title="Klik untuk memfilter SIP dalam masa peringatan (31-90 hari)">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="kp-sip-tile-label">Peringatan</span>
                            <i class="fas fa-exclamation-circle kp-sip-tile-icon text-warning"></i>
                        </div>
                        <div class="kp-sip-tile-val text-warning" id="kpSipCountWarning">0</div>
                        <div class="kp-sip-tile-sub">31 - 90 hari lagi</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="kp-sip-tile" data-sip-filter="kritis" role="button" tabindex="0" title="Klik untuk memfilter SIP masa kritis (≤30 hari)">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="kp-sip-tile-label">Masa Kritis</span>
                            <i class="fas fa-hourglass-end kp-sip-tile-icon text-orange" style="color: #f97316;"></i>
                        </div>
                        <div class="kp-sip-tile-val" style="color: #f97316;" id="kpSipCountKritis">0</div>
                        <div class="kp-sip-tile-sub">≤ 30 hari lagi</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="kp-sip-tile" data-sip-filter="expired" role="button" tabindex="0" title="Klik untuk memfilter SIP yang sudah kadaluarsa">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="kp-sip-tile-label">Kadaluarsa</span>
                            <i class="fas fa-times-circle kp-sip-tile-icon text-danger"></i>
                        </div>
                        <div class="kp-sip-tile-val text-danger" id="kpSipCountExpired">0</div>
                        <div class="kp-sip-tile-sub">Perlu perpanjangan</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="kp-sip-tile" data-sip-filter="empty" role="button" tabindex="0" title="Klik untuk memfilter Nakes yang belum mengunggah SIP">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="kp-sip-tile-label">Belum Upload</span>
                            <i class="fas fa-file-excel kp-sip-tile-icon text-secondary"></i>
                        </div>
                        <div class="kp-sip-tile-val text-secondary" id="kpSipCountEmpty">0</div>
                        <div class="kp-sip-tile-sub">Belum ada berkas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Kategori (Tab Hijau Khas SIMRS Khanza) & Pencarian -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-1 align-items-center mb-2" id="kpBerkasCategoryTabs">
                <button type="button" class="btn btn-sm btn-success kp-cat-filter active" data-cat=""><i class="fas fa-layer-group me-1"></i>Semua Kategori</button>
            </div>
            <div class="row g-2 align-items-center">
                <div class="col-md-4 col-lg-3">
                    <div class="kp-search-group">
                        <i class="fas fa-search kp-search-icon"></i>
                        <input type="text" class="form-control form-control-sm kp-search-field" id="kpBerkasSearch" placeholder="Cari NIP, nama, jabatan..." maxlength="100">
                        <button class="btn btn-link kp-search-clear" type="button" id="kpBerkasResetSearch" title="Hapus pencarian"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm kp-filter-select" id="kpBerkasStatusFilter">
                        <option value="all">Semua Status Kelengkapan</option>
                        <option value="complete">Lengkap (100%)</option>
                        <option value="incomplete">Belum Lengkap (&lt;100%)</option>
                        <option value="empty">Belum Ada Berkas (0 file)</option>
                    </select>
                </div>
                <div class="col-md-5 col-lg-6 d-flex justify-content-md-end gap-2 align-items-center">
                    <span class="badge kp-emp-counter" id="kpBerkasEmpCount">0 pegawai</span>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="kpBerkasRefresh" title="Muat ulang ringkasan berkas">
                        <i class="fas fa-sync-alt me-1"></i>Segarkan Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Master-Detail Layout -->
    <div class="row g-3">
        <!-- Kolom Kiri: Daftar Pegawai -->
        <div class="col-lg-4 col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2 d-flex justify-content-between align-items-center kp-card-header">
                    <strong class="small mb-0"><i class="fas fa-users text-primary me-1"></i>Daftar Pegawai</strong>
                    <small class="kp-emp-subtext" style="font-size: .75rem;">Pilih untuk buka berkas</small>
                </div>
                <div class="card-body p-0 kp-emp-list-container" id="kpBerkasEmpList">
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Memuat data pegawai...
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Detail & Checklist Dokumen Pegawai -->
        <div class="col-lg-8 col-xl-8" id="kpBerkasDetailPanel">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center py-5 text-muted">
                    <div class="py-5">
                        <i class="fas fa-folder-open fa-4x text-success mb-3 opacity-50"></i>
                        <h5>Pilih Pegawai di Sebelah Kiri</h5>
                        <p class="small text-muted mb-3" style="max-width: 480px; margin: 0 auto;">
                            Pilih pegawai pada daftar di sebelah kiri atau klik tombol <span class="badge bg-success"><i class="fas fa-paperclip"></i> Berkas</span> di tab List Data untuk memeriksa kelengkapan, mengunggah berkas baru, atau melihat preview dokumen.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Upload Berkas -->
<div class="modal fade" id="kpUploadModal" tabindex="-1" aria-labelledby="kpUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="kpUploadForm" enctype="multipart/form-data">
                <div class="modal-header bg-light py-2">
                    <h6 class="modal-title mb-0" id="kpUploadModalLabel"><i class="fas fa-cloud-upload-alt text-primary me-2"></i>Upload Berkas Pegawai</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" name="nik" id="kpUploadNik">
                    <input type="hidden" name="kategori" id="kpUploadKategori">
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Pegawai</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="kpUploadNamaPegawai" readonly>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1" for="kpUploadKode">Jenis Berkas <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="kode" id="kpUploadKode" required>
                            <option value="">Pilih Jenis Berkas...</option>
                        </select>
                    </div>

                    <!-- Kolom Khusus Upload SIP (Surat Izin Praktik) -->
                    <div id="kpUploadSipSection" class="p-3 mb-2 rounded border d-none kp-sip-input-card">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fas fa-certificate text-warning"></i>
                            <strong class="small kp-sip-form-title">Masa Berlaku Surat Izin Praktik (SIP)</strong>
                            <span class="badge bg-warning text-dark ms-auto" style="font-size: .68rem;">Khusus SIP</span>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-1" for="kpUploadNoSip">Nomor SIP</label>
                            <input type="text" class="form-control form-control-sm" name="no_sip" id="kpUploadNoSip" placeholder="Contoh: 503/012/SIP/DPMPTSP/2024" maxlength="100">
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1" for="kpUploadTglTerbit">Tanggal Terbit</label>
                                <input type="date" class="form-control form-control-sm" name="tgl_terbit" id="kpUploadTglTerbit">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1" for="kpUploadTglKadaluarsa">Tgl Kadaluarsa / Berlaku <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm" name="tgl_kadaluarsa" id="kpUploadTglKadaluarsa">
                            </div>
                        </div>
                        <div class="form-text small mt-1" style="font-size: .72rem;">Tanggal masa berlaku akan dipantau otomatis pada dashboard untuk peringatan perpanjangan izin.</div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1" for="kpUploadDate">Tanggal Upload <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" name="tgl" id="kpUploadDate" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-1" for="kpUploadFileInput">Pilih File (JPG, PNG, PDF) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control form-control-sm" name="files[]" id="kpUploadFileInput" accept=".jpg,.jpeg,.png,.pdf" multiple required>
                        <div class="form-text" style="font-size: .72rem;">Maksimal 10 MB per file. Dapat memilih beberapa file sekaligus.</div>
                        <div id="kpUploadFileList" class="mt-2"></div>
                    </div>

                    <div id="kpUploadError" class="alert alert-danger py-2 small d-none"></div>

                    <div id="kpUploadProgress" class="progress mb-2 d-none" style="height: 14px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%; font-size: .7rem;">0%</div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="kpUploadSubmitBtn"><i class="fas fa-upload me-1"></i>Simpan & Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit / Atur Masa Berlaku SIP -->
<div class="modal fade" id="kpEditSipModal" tabindex="-1" aria-labelledby="kpEditSipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="kpEditSipForm">
                <div class="modal-header bg-light py-2">
                    <h6 class="modal-title mb-0" id="kpEditSipModalLabel"><i class="fas fa-calendar-check text-primary me-2"></i>Atur Masa Berlaku SIP</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <input type="hidden" name="nik" id="kpEditSipNik">
                    <input type="hidden" name="kode" id="kpEditSipKode">
                    
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Pegawai Nakes</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="kpEditSipNamaPegawai" readonly>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1">Nomor SIP</label>
                        <input type="text" class="form-control form-control-sm" name="no_sip" id="kpEditSipNo" placeholder="Contoh: 503/012/SIP/DPMPTSP/2024" maxlength="100">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1" for="kpEditSipTglTerbit">Tanggal Terbit</label>
                            <input type="date" class="form-control form-control-sm" name="tgl_terbit" id="kpEditSipTglTerbit">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1" for="kpEditSipTglKadaluarsa">Tgl Kadaluarsa / Berlaku <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" name="tgl_kadaluarsa" id="kpEditSipTglKadaluarsa" required>
                        </div>
                    </div>

                    <div id="kpEditSipError" class="alert alert-danger py-2 small d-none"></div>
                </div>
                <div class="modal-footer py-2 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="kpEditSipSubmitBtn"><i class="fas fa-save me-1"></i>Simpan Masa Berlaku</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Preview Berkas -->
<div class="modal fade" id="kpPreviewModal" tabindex="-1" aria-labelledby="kpPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2 bg-light">
                <div>
                    <h6 class="modal-title mb-0" id="kpPreviewTitle">Preview Berkas</h6>
                    <small class="text-muted" id="kpPreviewMeta" style="font-size: .75rem;"></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="#" target="_blank" class="btn btn-outline-primary btn-sm" id="kpPreviewOpenNewTab" title="Buka di tab baru">
                        <i class="fas fa-external-link-alt me-1"></i>Tab Baru
                    </a>
                    <a href="#" class="btn btn-outline-success btn-sm" id="kpPreviewDownload" title="Unduh file">
                        <i class="fas fa-download me-1"></i>Unduh
                    </a>
                    <?php if ($kp_write): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="kpPreviewDelete" title="Hapus berkas ini">
                        <i class="fas fa-trash-alt me-1"></i>Hapus
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0" id="kpPreviewBody">
                <div class="text-center py-5 text-muted">Memuat preview...</div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>window.kpConfig = <?php echo json_encode(['canWrite' => $kp_write && !$kp_error, 'csrf' => $_SESSION['kp_csrf'], 'ready' => !$kp_error], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/kepegawaian.js?v=<?php echo filemtime(__DIR__ . '/assets/js/kepegawaian.js'); ?>"></script>
<script src="assets/js/kepegawaian_berkas.js?v=<?php echo filemtime(__DIR__ . '/assets/js/kepegawaian_berkas.js'); ?>"></script>
<?php $page_js = ob_get_clean(); require_once __DIR__ . '/includes/footer.php'; ?>
