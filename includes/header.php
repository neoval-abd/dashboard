<?php
/*
 * File header.php (SECURITY HARDENED — KILL SWITCH ANTI-TAMPERING)
 * - TAMBAHAN: ob_start() dengan callback validasi copyright (Rule #17)
 * - Callback bernama samar: sanitize_output_buffer()
 * - Jika salah satu dari 5 komponen copyright hilang → return "" (Blank Page murni)
 * - Pembajak akan kebingungan karena tidak ada pesan error sama sekali
 */

// =========================================================================
// [KILL SWITCH ANTI-TAMPERING — SERVER SIDE — RULE #17]
// Fungsi ini mengecek keberadaan 5 komponen copyright wajib di dalam
// buffer HTML output sebelum dikirimkan ke browser.
// =========================================================================
function sanitize_output_buffer($buffer) {
    // 5 Signature Base64 yang wajib ada minimal DUAKALI di HTML output
    // (Sekali di Bar Footer, sekali di Modal Developer)
    // Jika salah satu dicurangi/dihapus/dirubah → return "" (BLANK PAGE)
    $signatures = [
        base64_decode('SWNoc2FuIExlb25oYXJ0'),                    // Ichsan Leonhart (Nama)
        base64_decode('c2F3ZXJpYS5jby9pY2hzYW5sZW9uaGFydA=='),   // saweria.co/ichsanleonhart
        base64_decode('NjI4NTcyNjEyMzc3Nw=='),                    // 6285726123777 (WA)
        base64_decode('QEljaHNhbkxlb25oYXJ0'),                    // @IchsanLeonhart (Telegram)
        base64_decode('aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL2ljaHNhbmxlb25oYXJ0L2FkZC1vbnNfd2ViYXBwc19raGFuemEvbWFpbi9xcmlzLWljaHNhbi5wbmc='), // QRIS URL
    ];

    foreach ($signatures as $sig) {
        // strpos() mengembalikan false jika string tidak ditemukan sama sekali
        // Kita gunakan strpos agar lebih robust terhadap variasi output
        if (strpos($buffer, $sig) === false) {
            // BLANKING: return string kosong tanpa keterangan (Rule #17)
            return "";
        }
    }

    return $buffer;
}

// Mulai output buffering SEBELUM HTML apapun ditulis
// Callback akan dipanggil otomatis saat script selesai (ob_end_flush)
ob_start('sanitize_output_buffer');

// =========================================================================
// [END KILL SWITCH — SERVER SIDE]
// =========================================================================

require_once(dirname(__DIR__) . '/config/koneksi.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$nama_instansi = "Dashboard RS";
$logo_src = "core/logo.php";

$sql_setting = "SELECT setting.nama_instansi FROM setting LIMIT 1";
$result_setting = $koneksi->query($sql_setting);
if ($result_setting && $result_setting->num_rows > 0) {
    $row_setting = $result_setting->fetch_assoc();
    $nama_instansi = htmlspecialchars($row_setting['nama_instansi']);
}

$current_page = basename($_SERVER['PHP_SELF']);

function get_collapse_class($pages, $current) {
    return in_array($current, $pages) ? 'show' : '';
}
function is_active($page, $current) {
    return ($page == $current) ? 'active' : '';
}
function get_arrow_class($pages, $current) {
    return in_array($current, $pages) ? '' : 'collapsed';
}
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?> - <?php echo $nama_instansi; ?></title>
    <!-- [PREMIUM THEME ENGINE] Anti-FOUC (Flash of Unstyled Content) -->
    <script>
        (function() {
            var theme = localStorage.getItem('app_theme') || 'theme-glass-animated'; // Default Theme
            document.documentElement.classList.add(theme);
        })();
    </script>
    <link rel="icon" href="<?php echo $logo_src; ?>" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 56px;
            --transition-speed: 0.3s;
            --sidebar-bg: #f8f9fa;
            --primary-color: #0d6efd;
            --credit-bar-height: 46px; /* Tinggi credit bar bawah */
        }

        body {
            font-size: .875rem;
            overflow-x: hidden;
            background-color: #f4f6f9; /* Default Bright Background */
            color: #212529;            /* Default Bright Text */
            transition: background-color 0.4s ease, color 0.4s ease;
        }

        /* ==========================================================
           [PREMIUM THEME ENGINE] GLASSMORPHISM STYLES & OVERRIDES 
           ========================================================== */
        
        /* 1. SOLID DARK NAVY THEME */
        html.theme-glass-solid body {
            background-color: #0f172a;
        }

        /* 2. ANIMATED MESH GRADIENT THEME (Futuristic) */
        html.theme-glass-animated body {
            background: linear-gradient(-45deg, #0f172a, #2e1065, #1e1b4b, #0f172a, #172554);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Variable Overrides untuk mode Glassmorphism (Solid & Animated) */
        html.theme-glass-solid body, html.theme-glass-animated body {
            color: #f8fafc;
            /* Komponen Base */
            --card-bg: rgba(30, 41, 59, 0.75);
            --card-border: rgba(255, 255, 255, 0.1);
            --glass-blur: blur(12px);
            /* DataTables Peforma Kejelasan Tinggi */
            --table-bg: rgba(15, 23, 42, 0.85); 
            --table-text: #f1f5f9;
            --table-border: rgba(255, 255, 255, 0.1);
            /* Navigation */
            --nav-bg: rgba(15, 23, 42, 0.9);
            --nav-border: rgba(255, 255, 255, 0.1);
            --sidebar-hover: rgba(255,255,255,0.05);
            /* Input / Interactive */
            --input-bg: rgba(15, 23, 42, 0.9);
            --input-text: #f8fafc;
            --input-border: #475569;
            --primary-light: #38bdf8;
            --modal-bg: #1e293b;
        }

        /* Apply Overrides & Blur Effects */
        html.theme-glass-solid .card, html.theme-glass-animated .card {
            background: var(--card-bg) !important;
            border: 1px solid var(--card-border) !important;
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            color: #f8fafc !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
        }
        html.theme-glass-solid .card-header, html.theme-glass-animated .card-header,
        html.theme-glass-solid .card-footer, html.theme-glass-animated .card-footer {
            border-color: var(--card-border) !important;
            background-color: transparent !important;
        }

        /* Glass Navbar & Sidebar */
        html.theme-glass-solid .navbar.bg-dark, html.theme-glass-animated .navbar.bg-dark {
            background-color: var(--nav-bg) !important;
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-bottom: 1px solid var(--nav-border) !important;
        }
        html.theme-glass-solid .sidebar, html.theme-glass-animated .sidebar {
            background-color: var(--nav-bg) !important;
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border-right: 1px solid var(--nav-border) !important;
        }
        
        /* Glass Header Text & Link adjustments */
        html.theme-glass-solid .nav-link, html.theme-glass-animated .nav-link { color: #cbd5e1; }
        html.theme-glass-solid .nav-link:hover, html.theme-glass-animated .nav-link:hover { color: var(--primary-light); background-color: var(--sidebar-hover); }
        html.theme-glass-solid .nav-link.active, html.theme-glass-animated .nav-link.active { 
            color: var(--primary-light); 
            background-color: rgba(56, 189, 248, 0.15); 
            border-left-color: var(--primary-light); 
        }
        html.theme-glass-solid .sidebar-group-header, html.theme-glass-animated .sidebar-group-header { color: #cbd5e1; }
        html.theme-glass-solid .sidebar-group-header:hover, html.theme-glass-animated .sidebar-group-header:hover { color: var(--primary-light); }
        html.theme-glass-solid .collapse .nav-flex-column, html.theme-glass-animated .collapse .nav-flex-column { background-color: transparent; }

        /* Glass DataTables High Readability Override */
        html.theme-glass-solid .table, html.theme-glass-animated .table {
            background-color: var(--table-bg) !important;
            color: var(--table-text) !important;
            border-color: var(--table-border) !important;
        }
        html.theme-glass-solid th, html.theme-glass-animated th,
        html.theme-glass-solid td, html.theme-glass-animated td,
        html.theme-glass-solid tr, html.theme-glass-animated tr {
            background-color: transparent !important;
            background: transparent !important;
            color: var(--table-text) !important;
            border-bottom-color: var(--table-border) !important;
        }
        html.theme-glass-solid .table-hover > tbody > tr:hover > *, html.theme-glass-animated .table-hover > tbody > tr:hover > * {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
        }
        
        /* DataTables Controls (Length, Filter, Pagination) */
        html.theme-glass-solid .dataTables_wrapper, html.theme-glass-animated .dataTables_wrapper { color: var(--table-text) !important; }
        html.theme-glass-solid .dataTables_info, html.theme-glass-animated .dataTables_info,
        html.theme-glass-solid .dataTables_length, html.theme-glass-animated .dataTables_length,
        html.theme-glass-solid .dataTables_filter, html.theme-glass-animated .dataTables_filter {
            color: var(--table-text) !important;
        }
        html.theme-glass-solid select, html.theme-glass-animated select,
        html.theme-glass-solid input, html.theme-glass-animated input {
            background-color: var(--input-bg);
            color: var(--input-text);
            border: 1px solid var(--input-border);
        }
        html.theme-glass-solid .form-control:focus, html.theme-glass-animated .form-control:focus,
        html.theme-glass-solid .form-select:focus, html.theme-glass-animated .form-select:focus {
            background-color: var(--input-bg);
            color: var(--input-text);
            border-color: var(--primary-light);
            box-shadow: 0 0 0 .25rem rgba(56,189,248,.25);
        }

        /* Glass Modals */
        html.theme-glass-solid .modal-content, html.theme-glass-animated .modal-content {
            background-color: var(--modal-bg);
            border: 1px solid var(--card-border);
            color: var(--table-text);
            backdrop-filter: var(--glass-blur);
        }
        html.theme-glass-solid .modal-header, html.theme-glass-animated .modal-header {
            border-bottom-color: var(--card-border);
            background-color: #0f172a !important; /* Gelap pekat */
        }
        html.theme-glass-solid .modal-footer, html.theme-glass-animated .modal-footer {
            border-top-color: var(--card-border);
        }

        /* Glass Accordion */
        html.theme-glass-solid .accordion-item, html.theme-glass-animated .accordion-item {
            background-color: var(--card-bg) !important;
            border-color: var(--card-border) !important;
            color: var(--table-text) !important;
        }
        html.theme-glass-solid .accordion-button, html.theme-glass-animated .accordion-button {
            background-color: rgba(15, 23, 42, 0.4) !important;
            color: var(--table-text) !important;
            box-shadow: none !important;
        }
        html.theme-glass-solid .accordion-button:not(.collapsed), html.theme-glass-animated .accordion-button:not(.collapsed) {
            background-color: rgba(56, 189, 248, 0.15) !important;
            color: var(--primary-light) !important;
        }
        html.theme-glass-solid .accordion-button::after, html.theme-glass-animated .accordion-button::after {
            filter: brightness(0) invert(1);
        }
        html.theme-glass-solid .accordion-body, html.theme-glass-animated .accordion-body {
            color: var(--table-text) !important;
            background-color: transparent !important;
        }

        /* =========================================================
           [AGGRESSIVE OVERRIDES] Menumpas "White on White" Bug DataTables & Modal
           ========================================================= */
        /* 1. Bunuh bg-white dan bg-light bawaan Bootstrap dan custom classes */
        html.theme-glass-solid .bg-white, html.theme-glass-animated .bg-white,
        html.theme-glass-solid .bg-light, html.theme-glass-animated .bg-light,
        html.theme-glass-solid .table-light, html.theme-glass-animated .table-light,
        html.theme-glass-solid .table-white, html.theme-glass-animated .table-white {
            background-color: rgba(255,255,255,0.05) !important;
            color: var(--table-text) !important;
        }

        /* 2. Atasi Inline Styles Hardcoded (style="background-color: white; / #fff") di Modul */
        html.theme-glass-solid [style*="background-color: white"], html.theme-glass-animated [style*="background-color: white"],
        html.theme-glass-solid [style*="background-color: #fff"], html.theme-glass-animated [style*="background-color: #fff"],
        html.theme-glass-solid [style*="background-color:#fff"], html.theme-glass-animated [style*="background-color:#fff"],
        html.theme-glass-solid [style*="background: #fff"], html.theme-glass-animated [style*="background: #fff"],
        html.theme-glass-solid [style*="background: white"], html.theme-glass-animated [style*="background: white"] {
            background-color: transparent !important;
            background: transparent !important;
            color: var(--table-text) !important;
        }

        /* 3. Paksa modal-body, card-body, dan list-group agar tidak putih solid */
        html.theme-glass-solid .modal-body, html.theme-glass-animated .modal-body,
        html.theme-glass-solid .card-body, html.theme-glass-animated .card-body {
            background-color: transparent !important; 
        }
        html.theme-glass-solid .list-group-item, html.theme-glass-animated .list-group-item {
            background-color: rgba(0,0,0,0.2) !important;
            border-color: rgba(255,255,255,0.1) !important;
            color: var(--table-text) !important;
        }

        /* 4. Menumpas Box/Card membandel di spesifik Laporan (Satu Sehat, Antrol, Audit, Absensi) */
        html.theme-glass-solid .scorecard, html.theme-glass-animated .scorecard,
        html.theme-glass-solid .table-container, html.theme-glass-animated .table-container,
        html.theme-glass-solid .header-rs, html.theme-glass-animated .header-rs,
        html.theme-glass-solid .filter-box, html.theme-glass-animated .filter-box,
        html.theme-glass-solid .filter-panel, html.theme-glass-animated .filter-panel,
        html.theme-glass-solid .glass-card, html.theme-glass-animated .glass-card {
            background: var(--card-bg) !important;
            border-color: var(--card-border) !important;
            color: var(--table-text) !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
        }
        
        /* Pewarnaan Teks Spesifik di Rangkuman Card Module Laporan */
        html.theme-glass-solid .scorecard h5, html.theme-glass-animated .scorecard h5,
        html.theme-glass-solid .scorecard h2, html.theme-glass-animated .scorecard h2,
        html.theme-glass-solid .filter-box label, html.theme-glass-animated .filter-box label,
        html.theme-glass-solid .filter-panel label, html.theme-glass-animated .filter-panel label {
            color: #f8fafc !important;
        }

        /* Global Text adjustments for Dark Theme */
        html.theme-glass-solid h1, html.theme-glass-animated h1,
        html.theme-glass-solid h2, html.theme-glass-animated h2,
        html.theme-glass-solid h3, html.theme-glass-animated h3,
        html.theme-glass-solid h4, html.theme-glass-animated h4,
        html.theme-glass-solid h5, html.theme-glass-animated h5,
        html.theme-glass-solid h6, html.theme-glass-animated h6,
        html.theme-glass-solid .text-muted, html.theme-glass-animated .text-muted,
        html.theme-glass-solid .text-dark, html.theme-glass-animated .text-dark {
            color: var(--table-text) !important;
        }
        
        html.theme-glass-solid .alert-info, html.theme-glass-animated .alert-info {
            background-color: rgba(13, 202, 240, 0.1) !important;
            color: #bae6fd !important;
            border: 1px solid rgba(13, 202, 240, 0.2) !important;
        }

        /* Glass Footer Overrides */
        html.theme-glass-solid #dev-credit-bar {
            background: rgba(15, 23, 42, 0.95);
            border-top: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        }
        html.theme-glass-animated #dev-credit-bar {
            background: linear-gradient(90deg, #0f0c29, #302b63, #24243e);
            border-top: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        }
        html.theme-glass-solid .dev-credit-brand .dev-name, html.theme-glass-animated .dev-credit-brand .dev-name { color: #fff; }
        html.theme-glass-solid .dev-credit-brand .dev-role, html.theme-glass-animated .dev-credit-brand .dev-role { color: rgba(255,255,255,0.5) !important; }
        html.theme-glass-solid .dev-divider, html.theme-glass-animated .dev-divider { background: rgba(255,255,255,0.15); }
        html.theme-glass-solid .dev-link-btn-neutral, html.theme-glass-animated .dev-link-btn-neutral {
            background: rgba(255,255,255,0.1) !important;
            border-color: rgba(255,255,255,0.2) !important;
            color: #fff !important;
        }

        /* --- NAVBAR (FIXED TOP) --- */
        .navbar {
            height: var(--header-height);
            z-index: 1040; /* Pastikan navbar selalu di atas konten */
        }
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
            width: var(--sidebar-width);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* --- SIDEBAR (INDEPENDENT) --- */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            padding-top: var(--header-height);
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            border-right: 1px solid #dee2e6;
            transition: transform var(--transition-speed) ease-in-out;
            overflow-y: auto;
        }

        /* --- MAIN CONTENT (DYNAMIC WIDTH) --- */
        main {
            display: block;
            width: auto;
            margin-left: var(--sidebar-width);
            padding: 20px;
            /* Tambahkan padding bawah agar konten tidak tertutup credit bar */
            padding-bottom: calc(var(--credit-bar-height) + 20px);
            min-height: calc(100vh - var(--header-height));
            transition: margin-left var(--transition-speed) ease-in-out;
        }

        /* --- LOGIKA TOGGLE DESKTOP --- */
        body.sidebar-closed .sidebar { transform: translateX(-100%); }
        body.sidebar-closed main    { margin-left: 0; }

        /* --- LOGIKA TOGGLE MOBILE --- */
        @media (max-width: 767.98px) {
            .sidebar { transform: translateX(-100%); }
            main { margin-left: 0; }
            body.sidebar-open .sidebar { transform: translateX(0); box-shadow: 0 0 15px rgba(0,0,0,0.2); }
            .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; }
            body.sidebar-open .sidebar-overlay { display: block; }
        }

        .nav-link { color: #333; padding: 8px 16px; font-weight: 500; }
        .nav-link:hover { color: var(--primary-color); background-color: #e9ecef; }
        .nav-link.active { color: var(--primary-color); background-color: #e7f1ff; border-left: 3px solid var(--primary-color); }
        .sidebar-group-header { cursor: pointer; padding: 10px 15px; margin-top: 5px; color: #6c757d; font-size: 0.75rem; font-weight: 700; display: flex; justify-content: space-between; text-transform: uppercase;}
        .sidebar-group-header:hover { color: var(--primary-color); }
        .sidebar-group-header .fa-chevron-down { transition: transform 0.3s; }
        .sidebar-group-header.collapsed .fa-chevron-down { transform: rotate(-90deg); }
        .collapse .nav-flex-column { padding-left: 10px; background-color: #fff; }

        /* ========== GLOBAL LOADING OVERLAY ========== */
        #globalLoadingOverlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(3px);
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            transition: opacity 0.2s ease;
        }
        #globalLoadingOverlay .loading-box {
            text-align: center;
            background: #fff;
            border-radius: 16px;
            padding: 36px 48px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            border: 1px solid #e9ecef;
        }
        #globalLoadingOverlay .spinner-border { width: 3rem; height: 3rem; border-width: 0.3em; }
        #globalLoadingOverlay .loading-text { margin-top: 16px; font-size: 0.95rem; font-weight: 600; color: #495057; margin-bottom: 0; }
        #globalLoadingOverlay .loading-subtext { font-size: 0.8rem; color: #adb5bd; margin-top: 4px; }
        @keyframes dotsAnim {
            0%   { content: ''; }
            33%  { content: '.'; }
            66%  { content: '..'; }
            100% { content: '...'; }
        }
        #globalLoadingOverlay .dots::after { content: ''; display: inline-block; animation: dotsAnim 1.2s steps(1, end) infinite; }

        /* ========== DEVELOPER CREDIT BAR (ANTI-TAMPERING COMPONENT) ========== */
        #dev-credit-bar {
            position: fixed;
            bottom: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--credit-bar-height);
            z-index: 1025;
            background: #ffffff;
            border-top: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            transition: left var(--transition-speed) ease-in-out;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        }
        /* Sesuaikan posisi saat sidebar ditutup */
        body.sidebar-closed #dev-credit-bar { left: 0; }

        @media (max-width: 767.98px) {
            #dev-credit-bar { left: 0; }
        }

        .dev-credit-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .dev-credit-brand .dev-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: #0a0a1a;
            flex-shrink: 0;
        }
        .dev-credit-brand .dev-name {
            font-size: 0.78rem;
            font-weight: 700;
            color: #212529;
            white-space: nowrap;
        }
        .dev-credit-brand .dev-role {
            font-size: 0.65rem;
            color: #6c757d;
            white-space: nowrap;
        }

        .dev-divider {
            width: 1px;
            height: 24px;
            background: #dee2e6;
            flex-shrink: 0;
        }

        .dev-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
        }
        .dev-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none !important;
            transition: all 0.2s;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        .dev-link-btn-neutral {
            color: #495057 !important;
            background: rgba(0,0,0,0.05);
            border-color: transparent;
        }
        .dev-link-btn:hover { transform: translateY(-1px); }

        .dev-link-saweria {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: #fff !important;
        }
        .dev-link-saweria:hover {
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.5);
            color: #fff !important;
        }

        .dev-link-wa {
            background: rgba(37, 211, 102, 0.15);
            border-color: rgba(37, 211, 102, 0.4);
            color: #25d366 !important;
        }
        .dev-link-wa:hover {
            background: rgba(37, 211, 102, 0.25);
            color: #25d366 !important;
        }

        .dev-link-tg {
            background: rgba(0, 136, 204, 0.15);
            border-color: rgba(0, 136, 204, 0.4);
            color: #0088cc !important;
        }
        .dev-link-tg:hover {
            background: rgba(0, 136, 204, 0.25);
            color: #0088cc !important;
        }

        /* QRIS Thumbnail — WAJIB SELALU TERLIHAT (dipakai kill switch client-side) */
        .dev-qris-wrap {
            position: relative;
            flex-shrink: 0;
            cursor: pointer;
        }
        .dev-qris-wrap img {
            width: 34px;
            height: 34px;
            border-radius: 6px;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.3);
            transition: all 0.2s;
        }
        .dev-qris-wrap:hover img {
            transform: scale(1.1);
            border-color: rgba(255,255,255,0.7);
        }
        .dev-qris-wrap .qris-tooltip {
            position: absolute;
            bottom: 44px;
            right: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            padding: 12px;
            width: 180px;
            text-align: center;
            display: none;
            z-index: 99999;
        }
        .dev-qris-wrap .qris-tooltip img {
            width: 156px;
            height: 156px;
            border: none;
            border-radius: 8px;
        }
        .dev-qris-wrap .qris-tooltip p {
            font-size: 0.72rem;
            color: #444;
            margin: 8px 0 0;
            font-weight: 600;
        }
        .dev-qris-wrap:hover .qris-tooltip,
        .dev-qris-wrap:focus-within .qris-tooltip { display: block; }
        .dev-qris-wrap .qris-label {
            font-size: 0.6rem;
            color: rgba(255,255,255,0.5);
            text-align: center;
            margin-top: 2px;
        }

        /* Hide text on mobile — hanya tampilkan icon */
        @media (max-width: 575.98px) {
            .dev-link-btn span { display: none; }
            .dev-credit-brand .dev-role { display: none; }
        }
    </style>
</head>
<body>

<!-- ===== GLOBAL LOADING OVERLAY ===== -->
<div id="globalLoadingOverlay">
    <div class="loading-box">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="loading-text">Memuat data<span class="dots"></span></p>
        <p class="loading-subtext">Mohon tunggu, proses pengambilan data sedang berlangsung</p>
    </div>
</div>
<!-- ===== END LOADING OVERLAY ===== -->

<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
  <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="#">
      <img src="<?php echo $logo_src; ?>" alt="Logo" width="25" height="25" class="d-inline-block align-text-top me-2">
      <?php echo $nama_instansi; ?>
  </a>

  <button class="btn btn-link text-white d-none d-md-block ms-2" type="button" id="sidebarToggleDesktop" style="z-index:1050; cursor:pointer;">
      <i class="fas fa-bars fa-lg"></i>
  </button>

  <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" id="sidebarToggleMobile" style="right: 10px; top: 15px; z-index:1050; cursor:pointer;">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="w-100"></div>

  <div class="navbar-nav d-flex flex-row align-items-center">
    
    <!-- [THEME ENGINE] Dropdown Switcher Tema -->
    <div class="nav-item dropdown me-2">
      <a class="nav-link dropdown-toggle text-warning" href="#" id="themeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Ganti Tema">
        <i class="fas fa-palette"></i>
      </a>
      <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="themeDropdown" style="font-size: 0.85rem; min-width: 200px;">
        <li><h6 class="dropdown-header">Pilih Tema Aplikasi</h6></li>
        <li><a class="dropdown-item theme-selector" href="#" data-theme="theme-light"><i class="fas fa-sun me-2 text-warning"></i> Terang</a></li>
        <li><a class="dropdown-item theme-selector" href="#" data-theme="theme-glass-solid"><i class="fas fa-moon me-2 text-secondary"></i> Gelap</a></li>
        <li><a class="dropdown-item theme-selector" href="#" data-theme="theme-glass-animated"><i class="fas fa-meteor me-2 text-primary"></i> Glass</a></li>
      </ul>
    </div>

    <!-- Script Logika Ganti Tema + Reload DataTables/Charts otomatis -->
    <script>
      document.addEventListener("DOMContentLoaded", function () {
          const themeSelectors = document.querySelectorAll('.theme-selector');
          themeSelectors.forEach(function(item) {
              item.addEventListener('click', function(e) {
                  e.preventDefault();
                  const selectedTheme = this.getAttribute('data-theme');
                  const currentTheme = localStorage.getItem('app_theme') || 'theme-glass-animated';
                  
                  if (selectedTheme !== currentTheme) {
                      // Hapus tema lama, tambahkan tema baru ke tag <html>
                      document.documentElement.classList.remove('theme-light', 'theme-glass-solid', 'theme-glass-animated');
                      document.documentElement.classList.add(selectedTheme);
                      
                      // Simpan ke LocalStorage
                      localStorage.setItem('app_theme', selectedTheme);
                      
                      // Beritahu sistem tentang perubahan (misal untuk redraw Chart)
                      window.dispatchEvent(new Event('themeChanged'));
                  }
              });
          });
      });
    </script>

    <div class="nav-item text-nowrap">
      <span class="nav-link px-3 text-white small">Halo, <?php echo htmlspecialchars($_SESSION['nama_user']); ?></span>
    </div>
    <div class="nav-item text-nowrap">
      <a class="nav-link px-3 text-danger" href="core/logout.php" title="Keluar"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</header>

<div class="sidebar-overlay" id="mobileOverlay"></div>

<nav id="sidebarMenu" class="sidebar">
  <div class="pt-3 pb-5">
    <ul class="nav flex-column mb-2">
      <li class="nav-item">
        <a class="nav-link <?php echo is_active('dashboard.php', $current_page); ?>" href="dashboard.php">
          <i class="fas fa-home me-2 text-primary" style="width: 20px;"></i> Dashboard Utama
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#guideModal">
          <i class="fas fa-book-reader me-2 text-info" style="width: 20px;"></i> Panduan Pengguna
        </a>
      </li>
    </ul>

    <?php
    // BACA FILE JSON SIDEBAR
    $sidebar_json_file = dirname(__DIR__) . '/config/sidebar_menu.json';
    $sidebar_menus = [];
    if (file_exists($sidebar_json_file)) {
        $json_data = file_get_contents($sidebar_json_file);
        $sidebar_menus = json_decode($json_data, true);
    }

    // LOOPING MENU DARI JSON
    if (!empty($sidebar_menus)) {
        foreach ($sidebar_menus as $menu) {
            if (isset($menu['is_active']) && $menu['is_active'] === false) {
                continue;
            }

            if (isset($menu['is_group']) && $menu['is_group']) {
                $group_id = $menu['id'];
                $group_title = $menu['title'];

                $group_urls = [];
                if (isset($menu['items']) && is_array($menu['items'])) {
                    foreach ($menu['items'] as $item) {
                        if (isset($item['url'])) {
                            $group_urls[] = $item['url'];
                        }
                    }
                }
                ?>
                <div class="sidebar-group-header <?php echo get_arrow_class($group_urls, $current_page); ?>" data-bs-toggle="collapse" data-bs-target="#<?php echo $group_id; ?>">
                    <span><?php echo htmlspecialchars($group_title); ?></span> <i class="fas fa-chevron-down"></i>
                </div>
                <div class="collapse <?php echo get_collapse_class($group_urls, $current_page); ?>" id="<?php echo $group_id; ?>">
                    <ul class="nav flex-column nav-flex-column">
                        <?php
                        if (isset($menu['items']) && is_array($menu['items'])) {
                            foreach ($menu['items'] as $item) {
                                if (isset($item['is_active']) && $item['is_active'] === false) {
                                    continue;
                                }
                                ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo is_active($item['url'], $current_page); ?>" href="<?php echo htmlspecialchars($item['url']); ?>">
                                        <i class="<?php echo htmlspecialchars($item['icon']); ?> me-2" style="width: 20px;"></i> <?php echo htmlspecialchars($item['title']); ?>
                                    </a>
                                </li>
                                <?php
                            }
                        }
                        ?>
                    </ul>
                </div>
                <?php
            } else {
                ?>
                <ul class="nav flex-column mb-2">
                    <li class="nav-item">
                        <a class="nav-link <?php echo is_active($menu['url'], $current_page); ?>" href="<?php echo htmlspecialchars($menu['url']); ?>">
                            <i class="<?php echo htmlspecialchars($menu['icon']); ?> me-2" style="width: 20px;"></i> <?php echo htmlspecialchars($menu['title']); ?>
                        </a>
                    </li>
                </ul>
                <?php
            }
        }
    }
    ?>

	<?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'Super Admin') { ?>
    <div class="sidebar-group-header <?php echo get_arrow_class(['laporan_audit_trail.php', 'manage_users.php', 'setting_sidebar.php'], $current_page); ?>" data-bs-toggle="collapse" data-bs-target="#menuSuperAdmin">
        <span><i class="fas fa-user-shield me-1"></i> Super Admin</span> <i class="fas fa-chevron-down"></i>
    </div>
    <div class="collapse <?php echo get_collapse_class(['laporan_audit_trail.php', 'manage_users.php', 'setting_sidebar.php'], $current_page); ?>" id="menuSuperAdmin">
		<ul class="nav flex-column nav-flex-column">
            <li class="nav-item"><a class="nav-link <?php echo is_active('laporan_audit_trail.php', $current_page); ?>" href="laporan_audit_trail.php"><i class="fas fa-shield-alt me-2" style="width: 20px;"></i> Audit Trail</a></li>
			<li class="nav-item"><a class="nav-link <?php echo is_active('manage_users.php', $current_page); ?>" href="manage_users.php"><i class="fas fa-users-cog me-2" style="width: 20px;"></i> Manage Users</a></li>
            <li class="nav-item"><a class="nav-link <?php echo is_active('setting_sidebar.php', $current_page); ?>" href="setting_sidebar.php"><i class="fas fa-sliders-h me-2" style="width: 20px;"></i> Setting Sidebar</a></li>
		</ul>
	</div>
	<?php } ?>

    <br><br>
  </div>
</nav>


<!-- Modal Changelog -->
<div class="modal fade" id="changelogModal" tabindex="-1" aria-labelledby="changelogModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="changelogModalLabel"><i class="fas fa-history me-2"></i>Riwayat Pengembangan Sistem</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body modal-changelog-body" style="background-color: #f8f9fa;">
        <div class="p-2">
            <div id="changelog-container" class="timeline">
                <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Memuat riwayat pengembangan...</div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<style>
.timeline { position: relative; padding: 1rem 0; margin: 0; }
.timeline::before { content: ''; position: absolute; top: 0; bottom: 0; left: 20px; width: 2px; background: #dee2e6; }
.timeline-item { position: relative; margin-bottom: 2rem; padding-left: 50px; }
.timeline-item::before { content: ''; position: absolute; left: 14px; top: 5px; width: 14px; height: 14px; border-radius: 50%; background: #0d6efd; border: 3px solid #fff; box-shadow: 0 0 0 2px #0d6efd; z-index: 1; }
.timeline-date { font-size: 0.85rem; font-weight: bold; color: #6c757d; margin-bottom: 0.5rem; display: block; }
.timeline-content { background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef; }
.timeline-content h5 { font-size: 1rem; font-weight: 700; margin-bottom: 0px; color: #212529; }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const changelogModal = document.getElementById('changelogModal');
    if (changelogModal) {
        changelogModal.addEventListener('show.bs.modal', function () {
            const container = document.getElementById('changelog-container');
            if (container.dataset.loaded === 'true') return;
            fetch('change_log.md?v=' + new Date().getTime())
              .then(response => {
                  if(!response.ok) throw new Error("Gagal memuat log");
                  return response.text();
              })
              .then(text => {
                 const regex = /## \s*\[([^\]]+)\]\s*—\s*([^\n]+)\s+###\s*([^\n]+)\s+((?:-[^\n]+\s*)+)/g;
                 let matches = [], match;
                 while ((match = regex.exec(text)) !== null) matches.push(match);
                 if (matches.length === 0) {
                     container.innerHTML = '<div class="alert alert-warning">Belum ada catatan changelog.</div>';
                     return;
                 }
                 matches.reverse();
                 let html = '';
                 matches.forEach(m => {
                      let version = m[1], date = m[2].trim(), types = m[3].trim(), itemsText = m[4].trim();
                      let itemsHtml = itemsText.split('\n').filter(i => i.trim() !== '').map(i => {
                          let t = i.trim();
                          if (t.startsWith('-')) t = t.substring(1).trim();
                          t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                          return '<li class="mb-1">' + t + '</li>';
                      }).join('');
                      html += `<div class="timeline-item"><span class="timeline-date"><i class="far fa-clock me-1"></i> ${date}</span><div class="timeline-content shadow-sm"><h5><span class="badge bg-primary me-2">${version}</span> <small class="text-muted" style="font-size:0.8rem;">${types}</small></h5><ul class="mb-0 mt-3 ps-3" style="font-size:0.9rem;color:#495057;">${itemsHtml}</ul></div></div>`;
                 });
                 container.innerHTML = html;
                 container.dataset.loaded = 'true';
              })
              .catch(err => { container.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`; });
        });
    }
});

    // ================================================================
    // SIDEBAR TOGGLE & PERSISTENCE — ROBUST MULTI-STRATEGY
    // Bekerja di: XAMPP Windows, Linux Apache, PHP 7.3, PHP 8.x
    // ================================================================
    var _sidebarToggleAttached = false;

    function attachSidebarToggle() {
        // Hindari double-binding jika dipanggil lebih dari sekali
        if (_sidebarToggleAttached) return;

        var btnDesktop = document.getElementById('sidebarToggleDesktop');
        var btnMobile  = document.getElementById('sidebarToggleMobile');
        var overlay    = document.getElementById('mobileOverlay');

        // Jika element belum ada di DOM, batalkan (akan dicoba lagi via event)
        if (!btnDesktop && !btnMobile) return;

        _sidebarToggleAttached = true;

        // --- Restore state awal dari localStorage saat halaman dibuka ---
        try {
            if (localStorage.getItem('sidebarState') === 'closed') {
                document.body.classList.add('sidebar-closed');
            }
        } catch(e) { /* Abaikan jika localStorage tidak tersedia */ }

        // --- Desktop Toggle: tambah/hapus class sidebar-closed pada body ---
        if (btnDesktop) {
            btnDesktop.addEventListener('click', function(e) {
                e.stopPropagation();
                if (document.body.classList.contains('sidebar-closed')) {
                    document.body.classList.remove('sidebar-closed');
                } else {
                    document.body.classList.add('sidebar-closed');
                }
                try {
                    localStorage.setItem('sidebarState',
                        document.body.classList.contains('sidebar-closed') ? 'closed' : 'open'
                    );
                } catch(e) { /* Abaikan */ }
            });
        }

        // --- Mobile Toggle: tambah/hapus class sidebar-open pada body ---
        if (btnMobile) {
            btnMobile.addEventListener('click', function(e) {
                e.stopPropagation();
                if (document.body.classList.contains('sidebar-open')) {
                    document.body.classList.remove('sidebar-open');
                } else {
                    document.body.classList.add('sidebar-open');
                }
            });
        }

        // --- Tutup sidebar mobile saat overlay diklik ---
        if (overlay) {
            overlay.addEventListener('click', function() {
                document.body.classList.remove('sidebar-open');
            });
        }
    }

    // Strategy 1: Langsung (jika script ada di akhir body & DOM sudah ready)
    attachSidebarToggle();

    // Strategy 2: Jika Strategy 1 gagal (element belum ada), coba saat DOMContentLoaded
    document.addEventListener('DOMContentLoaded', attachSidebarToggle);

    // Strategy 3: Fallback terakhir via window.load (setelah semua resource ter-load)
    window.addEventListener('load', attachSidebarToggle);
</script>

<main>
    <div class="container-fluid">