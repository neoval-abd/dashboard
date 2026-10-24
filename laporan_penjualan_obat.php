<?php
/*
 * File: laporan_penjualan_obat.php
 * Menu: Penjualan Obat (Sirkulasi Obat, Alkes & BHP Medis Keluar Masuk)
 * Sesuai tampilan GUI dan query DlgSirkulasiBarang3.java di SIMRS Khanza
 */

$page_title = "Penjualan Obat - Sirkulasi Farmasi";
require_once('includes/header.php');

// Ambil master data untuk filter lookup
$bangsals = [];
$res_bangsal = $koneksi->query("SELECT kd_bangsal, nm_bangsal FROM bangsal WHERE status='1' ORDER BY nm_bangsal");
if ($res_bangsal) {
    while ($r = $res_bangsal->fetch_assoc()) $bangsals[] = $r;
}

$jenis_list = [];
$res_jns = $koneksi->query("SELECT kdjns, nama FROM jenis ORDER BY nama");
if ($res_jns) {
    while ($r = $res_jns->fetch_assoc()) $jenis_list[] = $r;
}

$kategori_list = [];
$res_kat = $koneksi->query("SELECT kode, nama FROM kategori_barang ORDER BY nama");
if ($res_kat) {
    while ($r = $res_kat->fetch_assoc()) $kategori_list[] = $r;
}

$golongan_list = [];
$res_gol = $koneksi->query("SELECT kode, nama FROM golongan_barang ORDER BY nama");
if ($res_gol) {
    while ($r = $res_gol->fetch_assoc()) $golongan_list[] = $r;
}

$tgl_default = date('Y-m-d');
?>

<!-- Select2 & SheetJS XLSX for rich interactions -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
    /* Styling Khanza & Modern Dashboard Assalam */
    .khanza-frame {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        position: relative;
        background: #fff;
        box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        margin-bottom: 25px;
        overflow: hidden;
    }
    html.theme-glass-solid .khanza-frame,
    html.theme-glass-animated .khanza-frame {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
    }

    .khanza-frame-title {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        padding: 9px 18px;
        font-size: 0.95rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .panelisi-top {
        background: #fdfdfd;
        border-bottom: 1px solid #e5e7eb;
        padding: 14px 18px;
    }
    html.theme-glass-solid .panelisi-top,
    html.theme-glass-animated .panelisi-top {
        background: rgba(15, 23, 42, 0.6);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .panelisi-bottom {
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
        padding: 12px 18px;
    }
    html.theme-glass-solid .panelisi-bottom,
    html.theme-glass-animated .panelisi-bottom {
        background: rgba(15, 23, 42, 0.7);
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .form-filter-label {
        font-size: 0.82rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0;
        white-space: nowrap;
    }
    html.theme-glass-solid .form-filter-label,
    html.theme-glass-animated .form-filter-label {
        color: #cbd5e1;
    }

    .khanza-input {
        font-size: 0.82rem;
        height: 32px;
        padding: 4px 8px;
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
    }
    html.theme-glass-solid .khanza-input,
    html.theme-glass-animated .khanza-input {
        background-color: rgba(15, 23, 42, 0.85);
        color: #f1f5f9;
        border-color: #475569;
    }

    /* Action Buttons Khanza Style */
    .btn-khanza {
        font-size: 0.82rem;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .btn-khanza-cari {
        background: #10b981;
        color: #fff;
        border: 1px solid #059669;
    }
    .btn-khanza-cari:hover { background: #059669; color: #fff; transform: translateY(-1px); }

    .btn-khanza-semua {
        background: #3b82f6;
        color: #fff;
        border: 1px solid #2563eb;
    }
    .btn-khanza-semua:hover { background: #2563eb; color: #fff; transform: translateY(-1px); }

    .btn-khanza-cetak {
        background: #0284c7;
        color: #fff;
        border: 1px solid #0369a1;
    }
    .btn-khanza-cetak:hover { background: #0369a1; color: #fff; transform: translateY(-1px); }

    .btn-khanza-excel {
        background: #16a34a;
        color: #fff;
        border: 1px solid #15803d;
    }
    .btn-khanza-excel:hover { background: #15803d; color: #fff; transform: translateY(-1px); }

    .btn-khanza-grafik {
        background: #8b5cf6;
        color: #fff;
        border: 1px solid #7c3aed;
    }
    .btn-khanza-grafik:hover { background: #7c3aed; color: #fff; transform: translateY(-1px); }

    /* 33 Columns Table Styling */
    .table-sirkulasi-wrap {
        overflow-x: auto;
        max-height: 650px;
        position: relative;
    }
    #tableSirkulasi {
        font-size: 0.77rem;
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
        margin-bottom: 0;
    }
    #tableSirkulasi thead th {
        position: sticky;
        top: 0;
        z-index: 20;
        background: #f1f5f9;
        color: #1e293b;
        font-weight: 700;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
        padding: 7px 9px;
        border-bottom: 2px solid #cbd5e1;
        border-right: 1px solid #e2e8f0;
    }
    html.theme-glass-solid #tableSirkulasi thead th,
    html.theme-glass-animated #tableSirkulasi thead th {
        background: #1e293b;
        color: #f8fafc;
        border-bottom: 2px solid #475569;
        border-right: 1px solid rgba(255,255,255,0.08);
    }

    /* Column Group Color Badges in Table Header */
    .th-grp-base { background: #e2e8f0 !important; color: #0f172a !important; }
    .th-grp-stok { background: #dbeafe !important; color: #1e40af !important; }
    .th-grp-pengadaan { background: #d1fae5 !important; color: #065f46 !important; }
    .th-grp-penerimaan { background: #ecfdf5 !important; color: #047857 !important; }
    .th-grp-penjualan { background: #e0e7ff !important; color: #3730a3 !important; }
    .th-grp-pasien { background: #e0f2fe !important; color: #0369a1 !important; }
    .th-grp-piutang { background: #ccfbf1 !important; color: #115e59 !important; }
    .th-grp-retur { background: #fef3c7 !important; color: #92400e !important; }
    .th-grp-keluar { background: #ffe4e6 !important; color: #9f1239 !important; }
    .th-grp-mutasi { background: #ede9fe !important; color: #5b21b6 !important; }
    .th-grp-hibah { background: #f3e8ff !important; color: #6b21a8 !important; }

    html.theme-glass-solid .th-grp-base, html.theme-glass-animated .th-grp-base { background: #334155 !important; color: #f8fafc !important; }
    html.theme-glass-solid .th-grp-stok, html.theme-glass-animated .th-grp-stok { background: #1e3a8a !important; color: #bfdbfe !important; }
    html.theme-glass-solid .th-grp-pengadaan, html.theme-glass-animated .th-grp-pengadaan { background: #064e3b !important; color: #a7f3d0 !important; }
    html.theme-glass-solid .th-grp-penerimaan, html.theme-glass-animated .th-grp-penerimaan { background: #065f46 !important; color: #6ee7b7 !important; }
    html.theme-glass-solid .th-grp-penjualan, html.theme-glass-animated .th-grp-penjualan { background: #312e81 !important; color: #c7d2fe !important; }
    html.theme-glass-solid .th-grp-pasien, html.theme-glass-animated .th-grp-pasien { background: #0c4a6e !important; color: #bae6fd !important; }
    html.theme-glass-solid .th-grp-piutang, html.theme-glass-animated .th-grp-piutang { background: #134e4a !important; color: #99f6e4 !important; }
    html.theme-glass-solid .th-grp-retur, html.theme-glass-animated .th-grp-retur { background: #78350f !important; color: #fde68a !important; }
    html.theme-glass-solid .th-grp-keluar, html.theme-glass-animated .th-grp-keluar { background: #881337 !important; color: #fecdd3 !important; }
    html.theme-glass-solid .th-grp-mutasi, html.theme-glass-animated .th-grp-mutasi { background: #4c1d95 !important; color: #ddd6fe !important; }
    html.theme-glass-solid .th-grp-hibah, html.theme-glass-animated .th-grp-hibah { background: #581c87 !important; color: #e9d5ff !important; }

    /* Sticky Columns 1 & 2 */
    .col-sticky-1 {
        position: sticky;
        left: 0;
        z-index: 10;
        min-width: 95px;
        max-width: 110px;
    }
    .col-sticky-2 {
        position: sticky;
        left: 95px;
        z-index: 10;
        min-width: 220px;
        max-width: 260px;
        border-right: 2px solid #cbd5e1 !important;
        box-shadow: 3px 0 6px rgba(0,0,0,0.04);
    }
    #tableSirkulasi thead th.col-sticky-1 { z-index: 30; }
    #tableSirkulasi thead th.col-sticky-2 { z-index: 30; }

    /* Alternating Gentle Zebra Striping matching Khanza photo */
    #tableSirkulasi tbody tr:nth-child(even) td {
        background-color: #fff9f5;
    }
    #tableSirkulasi tbody tr:nth-child(odd) td {
        background-color: #ffffff;
    }
    #tableSirkulasi tbody tr:hover td {
        background-color: #e0f2fe !important;
    }
    html.theme-glass-solid #tableSirkulasi tbody tr:nth-child(even) td,
    html.theme-glass-animated #tableSirkulasi tbody tr:nth-child(even) td {
        background-color: rgba(30, 41, 59, 0.6);
        color: #f1f5f9;
    }
    html.theme-glass-solid #tableSirkulasi tbody tr:nth-child(odd) td,
    html.theme-glass-animated #tableSirkulasi tbody tr:nth-child(odd) td {
        background-color: rgba(15, 23, 42, 0.7);
        color: #f1f5f9;
    }
    html.theme-glass-solid #tableSirkulasi tbody tr:hover td,
    html.theme-glass-animated #tableSirkulasi tbody tr:hover td {
        background-color: rgba(56, 189, 248, 0.2) !important;
    }

    #tableSirkulasi td {
        padding: 5px 8px;
        border-bottom: 1px solid #e2e8f0;
        border-right: 1px solid #f1f5f9;
        white-space: nowrap;
        vertical-align: middle;
    }
    html.theme-glass-solid #tableSirkulasi td,
    html.theme-glass-animated #tableSirkulasi td {
        border-bottom: 1px solid rgba(255,255,255,0.06);
        border-right: 1px solid rgba(255,255,255,0.03);
    }

    .num-val {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    /* Footer Grand Total Row */
    #tableSirkulasi tfoot tr td {
        position: sticky;
        bottom: 0;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 800;
        border-top: 2px solid #059669;
        z-index: 15;
    }
    html.theme-glass-solid #tableSirkulasi tfoot tr td,
    html.theme-glass-animated #tableSirkulasi tfoot tr td {
        background: #0f172a;
        color: #f8fafc;
        border-top: 2px solid #10b981;
    }

    /* KPI Metric Cards */
    .metric-card {
        border-radius: 10px;
        padding: 14px 18px;
        position: relative;
        overflow: hidden;
        border: none;
        box-shadow: 0 4px 14px rgba(0,0,0,0.05);
        transition: transform 0.2s ease;
    }
    .metric-card:hover { transform: translateY(-2px); }
    .metric-card .icon-watermark {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 2.8rem;
        opacity: 0.15;
    }
    .metric-card .metric-val {
        font-size: 1.35rem;
        font-weight: 800;
        line-height: 1.2;
    }
    .metric-card .metric-lbl {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
    }

    /* Print Preview */
    @media print {
        body { background: #fff !important; color: #000 !important; }
        .sidebar, #dev-credit-bar, .navbar, .metric-card-row, .panelisi-top, .panelisi-bottom, .no-print {
            display: none !important;
        }
        .main-content, .container-fluid { margin: 0 !important; padding: 0 !important; }
        .khanza-frame { border: none !important; box-shadow: none !important; }
        .table-sirkulasi-wrap { max-height: none !important; overflow: visible !important; }
        #tableSirkulasi { font-size: 7pt !important; width: 100% !important; }
        #tableSirkulasi th, #tableSirkulasi td { padding: 2px 4px !important; }
        .col-sticky-1, .col-sticky-2 { position: static !important; }
    }
</style>

<div class="container-fluid px-3 py-3">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1 text-primary">
                <i class="fas fa-cash-register me-2"></i>Penjualan &amp; Sirkulasi Obat
            </h4>
            <div class="text-muted small">
                <span class="badge bg-success me-1">Khanza SIMRS Farmasi</span>
                Rekap sirkulasi keluar masuk obat, alkes, dan BHP medis lengkap 33 indikator
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary" id="btnHelpGuide" data-bs-toggle="modal" data-bs-target="#modalInfo">
                <i class="fas fa-question-circle me-1"></i>Panduan
            </button>
            <button class="btn btn-sm btn-success" id="btnExportTop" onclick="exportKeExcel()">
                <i class="fas fa-file-excel me-1"></i>Export Excel
            </button>
        </div>
    </div>

    <!-- Executive KPI Summary Cards -->
    <div class="row g-3 mb-4 metric-card-row">
        <div class="col-xl-3 col-md-6">
            <div class="metric-card bg-primary text-white">
                <div class="metric-lbl text-white-50">Total Nilai Aset Stok</div>
                <div class="metric-val mt-1" id="kpi-aset">Rp 0</div>
                <div class="small mt-1 text-white-50"><span id="kpi-item">0</span> item obat terdaftar</div>
                <i class="fas fa-money-bill-wave icon-watermark"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card bg-success text-white">
                <div class="metric-lbl text-white-50">Penjualan Bebas (Rp)</div>
                <div class="metric-val mt-1" id="kpi-jual">Rp 0</div>
                <div class="small mt-1 text-white-50">Status: Sudah Dibayar</div>
                <i class="fas fa-cash-register icon-watermark"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card text-white" style="background: linear-gradient(135deg, #0ea5e9, #0284c7);">
                <div class="metric-lbl text-white-50">Resep Obat Ke Pasien (Rp)</div>
                <div class="metric-val mt-1" id="kpi-pasien">Rp 0</div>
                <div class="small mt-1 text-white-50">Rawat Inap &amp; Rawat Jalan</div>
                <i class="fas fa-user-injured icon-watermark"></i>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card text-white" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="metric-lbl text-white-50">Pengadaan &amp; Penerimaan</div>
                <div class="metric-val mt-1" id="kpi-pengadaan">Rp 0</div>
                <div class="small mt-1 text-white-50">Faktur Pembelian &amp; Pemesanan</div>
                <i class="fas fa-truck-loading icon-watermark"></i>
            </div>
        </div>
    </div>

    <!-- Main Khanza-Style Frame Container -->
    <div class="khanza-frame">
        
        <!-- Frame Title Header (Matches Java: ::[ Sirkulasi Obat, Alkes & BHP Medis Keluar Masuk ]::) -->
        <div class="khanza-frame-title">
            <span><i class="fas fa-cubes me-2"></i>::[ Sirkulasi Obat, Alkes &amp; BHP Medis Keluar Masuk ]::</span>
            <span class="badge bg-white text-dark small" id="lblStatusLokasi">Semua Lokasi / Depo</span>
        </div>

        <!-- Top Filter Panel (Matches panelisi4 in DlgSirkulasiBarang3.java) -->
        <div class="panelisi-top">
            <div class="row g-2 align-items-center">
                <!-- Row 1: Tgl.Transaksi s.d. & Jenis -->
                <div class="col-lg-5 col-md-6">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-filter-label" style="min-width: 105px;">Tgl.Transaksi :</label>
                        <input type="date" class="form-control khanza-input" id="tgl1" value="<?php echo $tgl_default; ?>" style="max-width: 140px;">
                        <span class="form-filter-label">s.d.</span>
                        <input type="date" class="form-control khanza-input" id="tgl2" value="<?php echo $tgl_default; ?>" style="max-width: 140px;">
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-filter-label" style="min-width: 70px;">Jenis :</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control khanza-input" id="nmjns" placeholder="Semua Jenis" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalJenis" title="Pilih Jenis">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <button class="btn btn-outline-danger" type="button" onclick="clearFilter('nmjns')" title="Hapus Filter Jenis">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-12">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-filter-label" style="min-width: 60px;">Lokasi :</label>
                        <select class="form-select khanza-input select2-bangsal" id="kd_bangsal">
                            <option value="">-- Semua Lokasi / Depo --</option>
                            <?php foreach($bangsals as $b): ?>
                                <option value="<?php echo htmlspecialchars($b['kd_bangsal']); ?>">
                                    <?php echo htmlspecialchars($b['nm_bangsal']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Kategori & Golongan -->
                <div class="col-lg-5 col-md-6">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-filter-label" style="min-width: 105px;">Kategori :</label>
                        <div class="input-group input-group-sm flex-grow-1">
                            <input type="text" class="form-control khanza-input" id="nmkategori" placeholder="Semua Kategori" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalKategori" title="Pilih Kategori">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <button class="btn btn-outline-danger" type="button" onclick="clearFilter('nmkategori')" title="Hapus Filter Kategori">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-filter-label" style="min-width: 70px;">Golongan :</label>
                        <div class="input-group input-group-sm flex-grow-1">
                            <input type="text" class="form-control khanza-input" id="nmgolongan" placeholder="Semua Golongan" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalGolongan" title="Pilih Golongan">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <button class="btn btn-outline-danger" type="button" onclick="clearFilter('nmgolongan')" title="Hapus Filter Golongan">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-12 text-end">
                    <button type="button" class="btn btn-khanza btn-khanza-grafik w-100" data-bs-toggle="modal" data-bs-target="#modalGrafik">
                        <i class="fas fa-chart-bar"></i> Grafik 10 Besar Analisa
                    </button>
                </div>
            </div>
        </div>

        <!-- Table Container with 33 Columns -->
        <div class="table-sirkulasi-wrap" id="sirkulasiScroll">
            <table class="table" id="tableSirkulasi">
                <thead>
                    <tr>
                        <!-- 1-3 Base -->
                        <th class="th-grp-base col-sticky-1">Kode Barang</th>
                        <th class="th-grp-base col-sticky-2">Nama Barang</th>
                        <th class="th-grp-base">Satuan</th>
                        <!-- 4-5 Stok -->
                        <th class="th-grp-stok">Stok</th>
                        <th class="th-grp-stok">Stok(Rp)</th>
                        <!-- 6-7 Pengadaan -->
                        <th class="th-grp-pengadaan">Pengadaan</th>
                        <th class="th-grp-pengadaan">Pengadaan(Rp)</th>
                        <!-- 8-9 Penerimaan -->
                        <th class="th-grp-penerimaan">Penerimaan</th>
                        <th class="th-grp-penerimaan">Penerimaan(Rp)</th>
                        <!-- 10-11 Penjualan -->
                        <th class="th-grp-penjualan">Penjualan</th>
                        <th class="th-grp-penjualan">Penjualan(Rp)</th>
                        <!-- 12-13 Ke Pasien -->
                        <th class="th-grp-pasien">Ke Pasien</th>
                        <th class="th-grp-pasien">Ke Pasien(Rp)</th>
                        <!-- 14-15 Piutang Jual -->
                        <th class="th-grp-piutang">Piutang Jual</th>
                        <th class="th-grp-piutang">Piutang Jual(Rp)</th>
                        <!-- 16-17 Retur Beli -->
                        <th class="th-grp-retur">Retur Beli</th>
                        <th class="th-grp-retur">Retur Beli(Rp)</th>
                        <!-- 18-19 Retur Jual -->
                        <th class="th-grp-retur">Retur Jual</th>
                        <th class="th-grp-retur">Retur Jual(Rp)</th>
                        <!-- 20-21 Retur Piutang -->
                        <th class="th-grp-retur">Retur Piutang</th>
                        <th class="th-grp-retur">Retur Piutang(Rp)</th>
                        <!-- 22-23 Pengambilan UTD -->
                        <th class="th-grp-keluar">Pengambilan UTD</th>
                        <th class="th-grp-keluar">Pengambilan UTD(Rp)</th>
                        <!-- 24-25 Stok Keluar Medis -->
                        <th class="th-grp-keluar">Stok Keluar Medis</th>
                        <th class="th-grp-keluar">Stok Keluar Medis(Rp)</th>
                        <!-- 26-27 Resep Pulang -->
                        <th class="th-grp-keluar">Resep Pulang</th>
                        <th class="th-grp-keluar">Resep Pulang(Rp)</th>
                        <!-- 28-29 Mutasi Masuk -->
                        <th class="th-grp-mutasi">Mutasi Masuk</th>
                        <th class="th-grp-mutasi">Mutasi Masuk(Rp)</th>
                        <!-- 30-31 Mutasi Keluar -->
                        <th class="th-grp-mutasi">Mutasi Keluar</th>
                        <th class="th-grp-mutasi">Mutasi Keluar(Rp)</th>
                        <!-- 32-33 Hibah -->
                        <th class="th-grp-hibah">Hibah</th>
                        <th class="th-grp-hibah">Hibah(Rp)</th>
                    </tr>
                </thead>
                <tbody id="sirkulasiBody">
                    <tr>
                        <td colspan="33" class="text-center py-5 text-muted">
                            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                            Sedang memuat data sirkulasi obat...
                        </td>
                    </tr>
                </tbody>
                <tfoot id="sirkulasiFoot" style="display: none;">
                    <tr>
                        <td class="col-sticky-1 fw-bold text-center">&lt;&gt;&gt;</td>
                        <td class="col-sticky-2 fw-bold">Total :</td>
                        <td></td>
                        <td class="num-val" id="ft-stok">0</td>
                        <td class="num-val" id="ft-aset">0</td>
                        <td class="num-val" id="ft-beli-jml">0</td>
                        <td class="num-val" id="ft-beli-tot">0</td>
                        <td class="num-val" id="ft-pesan-jml">0</td>
                        <td class="num-val" id="ft-pesan-tot">0</td>
                        <td class="num-val" id="ft-jual-jml">0</td>
                        <td class="num-val" id="ft-jual-tot">0</td>
                        <td class="num-val" id="ft-pasien-jml">0</td>
                        <td class="num-val" id="ft-pasien-tot">0</td>
                        <td class="num-val" id="ft-piutang-jml">0</td>
                        <td class="num-val" id="ft-piutang-tot">0</td>
                        <td class="num-val" id="ft-retbeli-jml">0</td>
                        <td class="num-val" id="ft-retbeli-tot">0</td>
                        <td class="num-val" id="ft-retjual-jml">0</td>
                        <td class="num-val" id="ft-retjual-tot">0</td>
                        <td class="num-val" id="ft-retpiut-jml">0</td>
                        <td class="num-val" id="ft-retpiut-tot">0</td>
                        <td class="num-val" id="ft-utd-jml">0</td>
                        <td class="num-val" id="ft-utd-tot">0</td>
                        <td class="num-val" id="ft-keluar-jml">0</td>
                        <td class="num-val" id="ft-keluar-tot">0</td>
                        <td class="num-val" id="ft-respulang-jml">0</td>
                        <td class="num-val" id="ft-respulang-tot">0</td>
                        <td class="num-val" id="ft-mutasimasuk-jml">0</td>
                        <td class="num-val" id="ft-mutasimasuk-tot">0</td>
                        <td class="num-val" id="ft-mutasikeluar-jml">0</td>
                        <td class="num-val" id="ft-mutasikeluar-tot">0</td>
                        <td class="num-val" id="ft-hibah-jml">0</td>
                        <td class="num-val" id="ft-hibah-tot">0</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Bottom Action Bar (Matches panelisi1 in DlgSirkulasiBarang3.java) -->
        <div class="panelisi-bottom">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <!-- Search Key Word -->
                <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 480px;">
                    <label class="form-filter-label" style="min-width: 80px;">Key Word :</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control khanza-input" id="TCari" placeholder="Ketik Kode atau Nama Obat...">
                        <button class="btn btn-khanza btn-khanza-cari" type="button" id="BtnCari" title="Cari Data (Enter)">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-khanza btn-khanza-semua" id="BtnAll" title="Tampilkan Semua Data (Reset Filter)">
                        <i class="fas fa-search"></i> Semua
                    </button>
                    <button type="button" class="btn btn-khanza btn-khanza-cetak" id="BtnPrint" onclick="cetakLaporan()" title="Cetak Laporan">
                        <i class="fas fa-print"></i> Cetak
                    </button>
                    <button type="button" class="btn btn-khanza btn-khanza-excel" id="BtnExcel" onclick="exportKeExcel()" title="Export Excel 33 Kolom">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL LOOKUP: JENIS (DlgCariJenis) -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalJenis" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-tags me-2"></i>Pilih Jenis Barang</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <input type="text" class="form-control form-control-sm mb-2" id="searchJenis" placeholder="Cari jenis..." onkeyup="filterModalTable('searchJenis', 'tblJenis')">
                <div class="table-responsive" style="max-height: 380px;">
                    <table class="table table-sm table-hover mb-0" id="tblJenis">
                        <thead class="table-light"><tr><th>Kode</th><th>Nama Jenis</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach($jenis_list as $j): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($j['kdjns']); ?></td>
                                    <td><?php echo htmlspecialchars($j['nama']); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-primary py-0 px-2" onclick="pilihFilter('nmjns', '<?php echo addslashes($j['nama']); ?>', 'modalJenis')">Pilih</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LOOKUP: KATEGORI (DlgCariKategori) -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalKategori" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-shapes me-2"></i>Pilih Kategori Barang</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <input type="text" class="form-control form-control-sm mb-2" id="searchKategori" placeholder="Cari kategori..." onkeyup="filterModalTable('searchKategori', 'tblKategori')">
                <div class="table-responsive" style="max-height: 380px;">
                    <table class="table table-sm table-hover mb-0" id="tblKategori">
                        <thead class="table-light"><tr><th>Kode</th><th>Nama Kategori</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach($kategori_list as $k): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($k['kode']); ?></td>
                                    <td><?php echo htmlspecialchars($k['nama']); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-success py-0 px-2" onclick="pilihFilter('nmkategori', '<?php echo addslashes($k['nama']); ?>', 'modalKategori')">Pilih</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL LOOKUP: GOLONGAN (DlgCariGolongan) -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalGolongan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-layer-group me-2"></i>Pilih Golongan Barang</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <input type="text" class="form-control form-control-sm mb-2" id="searchGolongan" placeholder="Cari golongan..." onkeyup="filterModalTable('searchGolongan', 'tblGolongan')">
                <div class="table-responsive" style="max-height: 380px;">
                    <table class="table table-sm table-hover mb-0" id="tblGolongan">
                        <thead class="table-light"><tr><th>Kode</th><th>Nama Golongan</th><th class="text-center">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach($golongan_list as $g): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($g['kode']); ?></td>
                                    <td><?php echo htmlspecialchars($g['nama']); ?></td>
                                    <td class="text-center">
                                        <button class="btn btn-xs btn-info text-white py-0 px-2" onclick="pilihFilter('nmgolongan', '<?php echo addslashes($g['nama']); ?>', 'modalGolongan')">Pilih</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL GRAFIK ANALISA (Top 10 Chart.js) -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalGrafik" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-purple text-white py-2" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-chart-pie me-2"></i>Grafik Analisa 10 Besar Obat &amp; BHP</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 align-items-center mb-3">
                    <div class="col-md-8">
                        <select class="form-select form-select-sm" id="tipeGrafik" onchange="muatGrafik()">
                            <option value="jual_banyak">Grafik 10 Barang Penjualan Terbanyak</option>
                            <option value="jual_dikit">Grafik 10 Barang Penjualan Tersedikit</option>
                            <option value="resep_banyak">Grafik 10 Barang Resep Ke Pasien Terbanyak</option>
                            <option value="resep_dikit">Grafik 10 Barang Resep Ke Pasien Tersedikit</option>
                            <option value="beli_banyak">Grafik 10 Barang Pembelian (Pengadaan) Terbanyak</option>
                            <option value="beli_dikit">Grafik 10 Barang Pembelian Tersedikit</option>
                            <option value="piutang_banyak">Grafik 10 Barang Piutang Terbanyak</option>
                            <option value="piutang_dikit">Grafik 10 Barang Piutang Tersedikit</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-sm btn-outline-secondary" onclick="muatGrafik()"><i class="fas fa-sync me-1"></i>Refresh Grafik</button>
                    </div>
                </div>
                <div style="height: 380px; position: relative;">
                    <canvas id="chartAnalisa"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL INFORMASI / PANDUAN PENGGUNA -->
<!-- ========================================================================= -->
<div class="modal fade" id="modalInfo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-info-circle me-2"></i>Panduan Modul Penjualan &amp; Sirkulasi Obat</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6><strong>Deskripsi Modul:</strong></h6>
                <p class="small text-muted">
                    Modul ini merupakan representasi web dari modul <strong>Sirkulasi Obat, Alkes &amp; BHP Medis Keluar Masuk</strong> (<code>DlgSirkulasiBarang3.java</code>) pada SIMRS Khanza. Modul ini menghitung pergerakan obat lengkap sebanyak 33 kolom data.
                </p>
                <h6><strong>Struktur Kolom Data (33 Kolom):</strong></h6>
                <ul class="small text-muted">
                    <li><strong>Stok &amp; Nilai Aset:</strong> Posisi saldo stok gudang/depo dan nilai valuasi aset persediaan (stok &times; harga dasar / HPP).</li>
                    <li><strong>Pengadaan &amp; Penerimaan:</strong> Transaksi masuk melalui faktur pembelian (detailbeli) dan surat pemesanan (detailpesan).</li>
                    <li><strong>Penjualan &amp; Ke Pasien:</strong> Penjualan langsung kasir apotek (status sudah bayar) serta pemberian resep perawatan pasien ranap/ralan (detail_pemberian_obat setelah dikurangi tuslah/embalase).</li>
                    <li><strong>Piutang &amp; Retur:</strong> Transaksi piutang, retur beli supplier, retur jual kasir, serta retur piutang.</li>
                    <li><strong>Pengambilan UTD &amp; Pengeluaran Medis:</strong> Distribusi ke Unit Transfusi Darah dan pengeluaran BHP tindakan.</li>
                    <li><strong>Resep Pulang &amp; Mutasi Antar Depo:</strong> Obat dibawa pulang pasien ranap serta mutasi stok masuk/keluar antar bangsal/depo apotek.</li>
                    <li><strong>Hibah:</strong> Penerimaan barang hibah/bantuan medis.</li>
                </ul>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    var globalData = [];
    var chartInstance = null;

    function fmtRp(angka) {
        if (!angka || isNaN(angka)) return '0';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(angka));
    }

    function fmtNum(angka) {
        if (!angka || isNaN(angka)) return '0';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(angka);
    }

    // Modal Table Live Filter
    function filterModalTable(inputId, tableId) {
        var input = document.getElementById(inputId);
        var filter = input.value.toUpperCase();
        var table = document.getElementById(tableId);
        var tr = table.getElementsByTagName("tr");
        for (var i = 1; i < tr.length; i++) {
            var td0 = tr[i].getElementsByTagName("td")[0];
            var td1 = tr[i].getElementsByTagName("td")[1];
            if (td0 || td1) {
                var txt0 = td0 ? (td0.textContent || td0.innerText) : "";
                var txt1 = td1 ? (td1.textContent || td1.innerText) : "";
                if (txt0.toUpperCase().indexOf(filter) > -1 || txt1.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    function pilihFilter(fieldId, value, modalId) {
        $('#' + fieldId).val(value);
        var modalEl = document.getElementById(modalId);
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        loadSirkulasi();
    }

    function clearFilter(fieldId) {
        $('#' + fieldId).val('');
        loadSirkulasi();
    }

    // Load Data Sirkulasi
    function loadSirkulasi() {
        var tgl1 = $('#tgl1').val();
        var tgl2 = $('#tgl2').val();
        var kd_bangsal = $('#kd_bangsal').val();
        var nm_bangsal = $('#kd_bangsal option:selected').text();
        var nmjns = $('#nmjns').val();
        var nmkategori = $('#nmkategori').val();
        var nmgolongan = $('#nmgolongan').val();
        var keyword = $('#TCari').val();

        if (kd_bangsal) {
            $('#lblStatusLokasi').text('Lokasi: ' + nm_bangsal.trim());
        } else {
            $('#lblStatusLokasi').text('Semua Lokasi / Depo');
        }

        $('#BtnCari').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $('#sirkulasiBody').html('<tr><td colspan="33" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Sedang memproses perhitungan sirkulasi farmasi...</td></tr>');
        $('#sirkulasiFoot').hide();

        var params = {
            action: 'table',
            tgl_awal: tgl1,
            tgl_akhir: tgl2,
            kd_bangsal: kd_bangsal,
            nmjns: nmjns,
            nmkategori: nmkategori,
            nmgolongan: nmgolongan,
            keyword: keyword
        };

        $.getJSON('api/data_penjualan_obat.php', params, function(res) {
            $('#BtnCari').prop('disabled', false).html('<i class="fas fa-check"></i>');
            globalData = res.data || [];
            var s = res.summary || {};

            // Update KPI Metric Cards
            $('#kpi-aset').text('Rp ' + fmtRp(s.total_aset));
            $('#kpi-item').text(fmtNum(s.total_item));
            $('#kpi-jual').text('Rp ' + fmtRp(s.total_jual_tot));
            $('#kpi-pasien').text('Rp ' + fmtRp(s.total_pasien_tot));
            $('#kpi-pengadaan').text('Rp ' + fmtRp(s.total_beli_tot + s.total_pesan_tot));

            if (globalData.length === 0) {
                $('#sirkulasiBody').html('<tr><td colspan="33" class="text-center py-5 text-muted"><i class="fas fa-info-circle me-1"></i> Tidak ada data sirkulasi barang pada rentang tanggal dan kriteria filter ini.</td></tr>');
                return;
            }

            var html = '';
            for (var i = 0; i < globalData.length; i++) {
                var r = globalData[i];
                html += '<tr>' +
                    '<td class="col-sticky-1 fw-bold">' + (r.kode_brng || '') + '</td>' +
                    '<td class="col-sticky-2">' + (r.nama_brng || '') + '</td>' +
                    '<td class="text-center">' + (r.satuan || '') + '</td>' +
                    '<td class="num-val">' + fmtNum(r.stok) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.aset) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.pengadaan_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.pengadaan_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.penerimaan_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.penerimaan_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.penjualan_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.penjualan_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.ke_pasien_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.ke_pasien_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.piutang_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.piutang_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.retur_beli_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.retur_beli_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.retur_jual_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.retur_jual_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.retur_piutang_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.retur_piutang_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.utd_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.utd_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.keluar_medis_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.keluar_medis_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.resep_pulang_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.resep_pulang_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.mutasi_masuk_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.mutasi_masuk_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.mutasi_keluar_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.mutasi_keluar_tot) + '</td>' +
                    '<td class="num-val">' + fmtNum(r.hibah_jml) + '</td>' +
                    '<td class="num-val">' + fmtRp(r.hibah_tot) + '</td>' +
                '</tr>';
            }
            $('#sirkulasiBody').html(html);

            // Update Grand Total Footer
            $('#ft-stok').text(fmtNum(s.total_stok));
            $('#ft-aset').text(fmtRp(s.total_aset));
            $('#ft-beli-jml').text(fmtNum(s.total_beli_jml));
            $('#ft-beli-tot').text(fmtRp(s.total_beli_tot));
            $('#ft-pesan-jml').text(fmtNum(s.total_pesan_jml));
            $('#ft-pesan-tot').text(fmtRp(s.total_pesan_tot));
            $('#ft-jual-jml').text(fmtNum(s.total_jual_jml));
            $('#ft-jual-tot').text(fmtRp(s.total_jual_tot));
            $('#ft-pasien-jml').text(fmtNum(s.total_pasien_jml));
            $('#ft-pasien-tot').text(fmtRp(s.total_pasien_tot));
            $('#ft-piutang-jml').text(fmtNum(s.total_piutang_jml));
            $('#ft-piutang-tot').text(fmtRp(s.total_piutang_tot));
            $('#ft-retbeli-jml').text(fmtNum(s.total_retbeli_jml));
            $('#ft-retbeli-tot').text(fmtRp(s.total_retbeli_tot));
            $('#ft-retjual-jml').text(fmtNum(s.total_retjual_jml));
            $('#ft-retjual-tot').text(fmtRp(s.total_retjual_tot));
            $('#ft-retpiut-jml').text(fmtNum(s.total_retpiut_jml));
            $('#ft-retpiut-tot').text(fmtRp(s.total_retpiut_tot));
            $('#ft-utd-jml').text(fmtNum(s.total_utd_jml));
            $('#ft-utd-tot').text(fmtRp(s.total_utd_tot));
            $('#ft-keluar-jml').text(fmtNum(s.total_keluar_jml));
            $('#ft-keluar-tot').text(fmtRp(s.total_keluar_tot));
            $('#ft-respulang-jml').text(fmtNum(s.total_respulang_jml));
            $('#ft-respulang-tot').text(fmtRp(s.total_respulang_tot));
            $('#ft-mutasimasuk-jml').text(fmtNum(s.total_mutasimasuk_jml));
            $('#ft-mutasimasuk-tot').text(fmtRp(s.total_mutasimasuk_tot));
            $('#ft-mutasikeluar-jml').text(fmtNum(s.total_mutasikeluar_jml));
            $('#ft-mutasikeluar-tot').text(fmtRp(s.total_mutasikeluar_tot));
            $('#ft-hibah-jml').text(fmtNum(s.total_hibah_jml));
            $('#ft-hibah-tot').text(fmtRp(s.total_hibah_tot));

            $('#sirkulasiFoot').show();
        }).fail(function() {
            $('#BtnCari').prop('disabled', false).html('<i class="fas fa-check"></i>');
            $('#sirkulasiBody').html('<tr><td colspan="33" class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Terjadi kesalahan saat memuat data sirkulasi farmasi.</td></tr>');
        });
    }

    // Export SheetJS Excel 33 Kolom
    function exportKeExcel() {
        if (!globalData || globalData.length === 0) {
            alert('Tidak ada data yang dapat diexport!');
            return;
        }

        var headers = [
            'Kode Barang', 'Nama Barang', 'Satuan', 'Stok', 'Stok (Rp)',
            'Pengadaan', 'Pengadaan (Rp)', 'Penerimaan', 'Penerimaan (Rp)',
            'Penjualan', 'Penjualan (Rp)', 'Ke Pasien', 'Ke Pasien (Rp)',
            'Piutang Jual', 'Piutang Jual (Rp)', 'Retur Beli', 'Retur Beli (Rp)',
            'Retur Jual', 'Retur Jual (Rp)', 'Retur Piutang', 'Retur Piutang (Rp)',
            'Pengambilan UTD', 'Pengambilan UTD (Rp)', 'Stok Keluar Medis', 'Stok Keluar Medis (Rp)',
            'Resep Pulang', 'Resep Pulang (Rp)', 'Mutasi Masuk', 'Mutasi Masuk (Rp)',
            'Mutasi Keluar', 'Mutasi Keluar (Rp)', 'Hibah', 'Hibah (Rp)'
        ];

        var rows = [];
        var totals = new Array(33).fill(0);

        for (var i = 0; i < globalData.length; i++) {
            var r = globalData[i];
            var row = [
                r.kode_brng || '',
                r.nama_brng || '',
                r.satuan || '',
                Number(r.stok) || 0,
                Number(r.aset) || 0,
                Number(r.pengadaan_jml) || 0,
                Number(r.pengadaan_tot) || 0,
                Number(r.penerimaan_jml) || 0,
                Number(r.penerimaan_tot) || 0,
                Number(r.penjualan_jml) || 0,
                Number(r.penjualan_tot) || 0,
                Number(r.ke_pasien_jml) || 0,
                Number(r.ke_pasien_tot) || 0,
                Number(r.piutang_jml) || 0,
                Number(r.piutang_tot) || 0,
                Number(r.retur_beli_jml) || 0,
                Number(r.retur_beli_tot) || 0,
                Number(r.retur_jual_jml) || 0,
                Number(r.retur_jual_tot) || 0,
                Number(r.retur_piutang_jml) || 0,
                Number(r.retur_piutang_tot) || 0,
                Number(r.utd_jml) || 0,
                Number(r.utd_tot) || 0,
                Number(r.keluar_medis_jml) || 0,
                Number(r.keluar_medis_tot) || 0,
                Number(r.resep_pulang_jml) || 0,
                Number(r.resep_pulang_tot) || 0,
                Number(r.mutasi_masuk_jml) || 0,
                Number(r.mutasi_masuk_tot) || 0,
                Number(r.mutasi_keluar_jml) || 0,
                Number(r.mutasi_keluar_tot) || 0,
                Number(r.hibah_jml) || 0,
                Number(r.hibah_tot) || 0
            ];
            rows.push(row);

            for (var c = 3; c < 33; c++) {
                totals[c] += row[c];
            }
        }

        // Baris Grand Total
        var totalRow = ['<>>', 'TOTAL :', ''];
        for (var c = 3; c < 33; c++) {
            totalRow.push(totals[c]);
        }
        rows.push(totalRow);

        var wb = XLSX.utils.book_new();
        var ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);

        // Auto widths
        ws['!cols'] = [
            { wch: 14 }, { wch: 35 }, { wch: 10 },
            { wch: 10 }, { wch: 14 }, { wch: 10 }, { wch: 14 },
            { wch: 10 }, { wch: 14 }, { wch: 10 }, { wch: 14 },
            { wch: 10 }, { wch: 14 }, { wch: 10 }, { wch: 14 },
            { wch: 10 }, { wch: 14 }, { wch: 10 }, { wch: 14 },
            { wch: 10 }, { wch: 14 }, { wch: 10 }, { wch: 14 },
            { wch: 10 }, { wch: 14 }, { wch: 10 }, { wch: 14 },
            { wch: 10 }, { wch: 14 }, { wch: 10 }, { wch: 14 },
            { wch: 10 }, { wch: 14 }
        ];

        XLSX.utils.book_append_sheet(wb, ws, 'Sirkulasi Obat');
        var filename = 'Sirkulasi_Penjualan_Obat_' + $('#tgl1').val() + '_sd_' + $('#tgl2').val() + '.xlsx';
        XLSX.writeFile(wb, filename);
    }

    // Print Preview
    function cetakLaporan() {
        window.print();
    }

    // Chart.js Analisa 10 Besar
    function muatGrafik() {
        var tipe = $('#tipeGrafik').val();
        var tgl1 = $('#tgl1').val();
        var tgl2 = $('#tgl2').val();
        var kd_bangsal = $('#kd_bangsal').val();

        $.getJSON('api/data_penjualan_obat.php', {
            action: 'grafik',
            tipe: tipe,
            tgl_awal: tgl1,
            tgl_akhir: tgl2,
            kd_bangsal: kd_bangsal
        }, function(res) {
            var ctx = document.getElementById('chartAnalisa').getContext('2d');
            if (chartInstance) {
                chartInstance.destroy();
            }

            var colors = [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#06b6d4', '#ec4899', '#84cc16', '#6366f1', '#14b8a6'
            ];

            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: res.labels || [],
                    datasets: [{
                        label: 'Kuantitas (Qty)',
                        data: res.data_qty || [],
                        backgroundColor: colors,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: {
                            display: true,
                            text: res.title || 'Grafik 10 Besar Obat',
                            font: { size: 14, weight: 'bold' }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var idx = context.dataIndex;
                                    var qty = context.parsed.y;
                                    var rp = (res.data_rp && res.data_rp[idx]) ? ' | Nilai: Rp ' + fmtRp(res.data_rp[idx]) : '';
                                    return ' Jumlah: ' + fmtNum(qty) + rp;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) { return fmtNum(value); }
                            }
                        },
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 20,
                                callback: function(val, index) {
                                    var label = this.getLabelForValue(val);
                                    return label.length > 20 ? label.substr(0, 18) + '...' : label;
                                }
                            }
                        }
                    }
                }
            });
        });
    }

    $(document).ready(function() {
        // Inisialisasi Select2 Bangsal
        $('.select2-bangsal').select2({
            theme: 'bootstrap-5',
            width: '100%'
        }).on('change', function() {
            loadSirkulasi();
        });

        // Event Tombol Cari & Enter di Key Word
        $('#BtnCari').on('click', function() {
            loadSirkulasi();
        });

        $('#TCari').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                loadSirkulasi();
            }
        });

        // Event Tombol Semua
        $('#BtnAll').on('click', function() {
            $('#TCari').val('');
            $('#nmjns').val('');
            $('#nmkategori').val('');
            $('#nmgolongan').val('');
            $('#kd_bangsal').val('').trigger('change.select2');
            loadSirkulasi();
        });

        // Shortcut Keyboard: Alt+2 (Cari), Alt+A (Semua), Alt+P (Print)
        $(document).on('keydown', function(e) {
            if (e.altKey && e.key === '2') {
                e.preventDefault();
                $('#BtnCari').click();
            } else if (e.altKey && (e.key === 'a' || e.key === 'A')) {
                e.preventDefault();
                $('#BtnAll').click();
            } else if (e.altKey && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                cetakLaporan();
            }
        });

        // Trigger Load Pertama Kali
        loadSirkulasi();

        // Chart modal open event
        $('#modalGrafik').on('shown.bs.modal', function() {
            muatGrafik();
        });
    });
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
