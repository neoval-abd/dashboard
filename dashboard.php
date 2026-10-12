<?php
/*
 * File: dashboard.php (UPDATE V6 - HYPERLINKS)
 * - Fix: Link Pasien Masuk -> laporan_kunjungan.php
 * - Fix: Widget Kunjungan Aktif -> Tombol Ralan & Ranap terpisah.
 * - Fix: Link Tren -> laporan_kunjungan.php
 * - Fix: Link Top Poli -> laporan_kinerja_dokter.php
 */
$page_title = "Executive Dashboard";
require_once('includes/header.php');
?>

<style>
    .card-metric { transition: transform .2s; cursor: pointer; }
    .card-metric:hover { transform: scale(1.03); }
    .icon-circle {
        height: 3rem; width: 3rem; border-radius: 50%; display: flex; 
        align-items: center; justify-content: center; font-size: 1.5rem; color: white;
        flex-shrink: 0;
    }
    .bg-gradient-primary { background: linear-gradient(45deg, #4e73df, #224abe); }
    .bg-gradient-success { background: linear-gradient(45deg, #1cc88a, #13855c); }
    .bg-gradient-info { background: linear-gradient(45deg, #36b9cc, #258391); }
    .bg-gradient-warning { background: linear-gradient(45deg, #f6c23e, #dda20a); }
    .bg-gradient-danger { background: linear-gradient(45deg, #e74a3b, #be2617); }
    .text-xs-bold { font-size: 0.75rem; font-weight: 700; }
    
    .bed-row { transition: all .2s ease; cursor: pointer; border: 1px solid rgba(0,0,0,0.06); }
    .bed-row:hover { background-color: rgba(78, 115, 223, 0.08) !important; transform: translateY(-2px); box-shadow: 0 .25rem .75rem rgba(0,0,0,.08) !important; }
    
    /* ==============================================================
       NEON BADGE & ROOM CHIP STYLES (Light & Dark Mode Compatible)
       ============================================================== */
    .badge-neon-cyan {
        background: rgba(14, 165, 233, 0.12) !important;
        border: 1px solid #0284c7 !important;
        color: #38bdf8 !important;
        box-shadow: 0 0 8px rgba(56, 189, 248, 0.2);
        font-weight: 600;
        letter-spacing: 0.2px;
    }
    .badge-neon-emerald {
        background: rgba(16, 185, 129, 0.12) !important;
        border: 1px solid #10b981 !important;
        color: #34d399 !important;
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.25);
        font-weight: 600;
    }
    .badge-neon-amber {
        background: rgba(245, 158, 11, 0.12) !important;
        border: 1px solid #f59e0b !important;
        color: #fbbf24 !important;
        box-shadow: 0 0 8px rgba(245, 158, 11, 0.25);
        font-weight: 600;
    }
    .badge-neon-rose {
        background: rgba(239, 68, 68, 0.12) !important;
        border: 1px solid #ef4444 !important;
        color: #f87171 !important;
        box-shadow: 0 0 8px rgba(239, 68, 68, 0.25);
        font-weight: 600;
    }
    .badge-neon-blue {
        background: rgba(59, 130, 246, 0.15) !important;
        border: 1px solid #3b82f6 !important;
        color: #60a5fa !important;
        box-shadow: 0 0 8px rgba(59, 130, 246, 0.2);
        font-weight: 600;
    }

    /* Room Chip Base */
    .room-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        font-size: 0.74rem;
        font-weight: 500;
        border-radius: 6px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        user-select: none;
        line-height: 1.35;
        text-decoration: none;
    }
    .room-chip:hover {
        transform: translateY(-1px) scale(1.03);
    }

    /* Room Chip - Available (Kamar ada Bed Kosong) */
    .room-chip-available {
        background: rgba(16, 185, 129, 0.08);
        border: 1px solid rgba(16, 185, 129, 0.45);
        color: #1e293b;
    }
    .room-chip-available .room-chip-name {
        color: #0f172a;
    }
    .room-chip-available .room-chip-count {
        color: #059669;
        font-weight: 700;
    }
    .room-chip-available:hover {
        background: rgba(16, 185, 129, 0.18);
        border-color: #10b981;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.35);
    }

    /* Room Chip - Full (Kamar Penuh 0 ksg) */
    .room-chip-full {
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.4);
        color: #7f1d1d;
    }
    .room-chip-full .room-chip-name {
        color: #7f1d1d;
    }
    .room-chip-full .room-chip-count {
        color: #dc2626;
        font-weight: 700;
    }
    .room-chip-full:hover {
        background: rgba(239, 68, 68, 0.18);
        border-color: #ef4444;
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.35);
    }

    .room-divider {
        border-top: 1px solid rgba(0, 0, 0, 0.07);
    }

    /* Dark Theme / Glass Overrides (Tampilan Neon Khusus Mode Gelap) */
    html.theme-glass-solid .bed-row,
    html.theme-glass-animated .bed-row,
    [class*="theme-glass"] .bed-row,
    .theme-dark .bed-row {
        background-color: rgba(30, 41, 59, 0.5) !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
    }
    html.theme-glass-solid .bed-row:hover,
    html.theme-glass-animated .bed-row:hover,
    [class*="theme-glass"] .bed-row:hover,
    .theme-dark .bed-row:hover {
        background-color: rgba(51, 65, 85, 0.65) !important;
        border-color: rgba(56, 189, 248, 0.35) !important;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35) !important;
    }

    html.theme-glass-solid .room-divider,
    html.theme-glass-animated .room-divider,
    [class*="theme-glass"] .room-divider,
    .theme-dark .room-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.09) !important;
    }

    html.theme-glass-solid .bed-info-text,
    html.theme-glass-animated .bed-info-text,
    [class*="theme-glass"] .bed-info-text,
    .theme-dark .bed-info-text {
        color: #cbd5e1 !important;
    }

    /* Neon Glow Room Chips di Mode Dark */
    html.theme-glass-solid .room-chip-available,
    html.theme-glass-animated .room-chip-available,
    [class*="theme-glass"] .room-chip-available,
    .theme-dark .room-chip-available {
        background: rgba(16, 185, 129, 0.12) !important;
        border: 1px solid rgba(16, 185, 129, 0.5) !important;
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.16) !important;
    }
    html.theme-glass-solid .room-chip-available .room-chip-name,
    html.theme-glass-animated .room-chip-available .room-chip-name,
    [class*="theme-glass"] .room-chip-available .room-chip-name,
    .theme-dark .room-chip-available .room-chip-name {
        color: #f1f5f9 !important;
        font-weight: 500 !important;
    }
    html.theme-glass-solid .room-chip-available .room-chip-count,
    html.theme-glass-animated .room-chip-available .room-chip-count,
    [class*="theme-glass"] .room-chip-available .room-chip-count,
    .theme-dark .room-chip-available .room-chip-count {
        color: #34d399 !important;
        text-shadow: 0 0 8px rgba(52, 211, 153, 0.5) !important;
    }
    html.theme-glass-solid .room-chip-available:hover,
    html.theme-glass-animated .room-chip-available:hover,
    [class*="theme-glass"] .room-chip-available:hover,
    .theme-dark .room-chip-available:hover {
        background: rgba(16, 185, 129, 0.22) !important;
        border-color: #34d399 !important;
        box-shadow: 0 0 14px rgba(52, 211, 153, 0.45) !important;
    }

    html.theme-glass-solid .room-chip-full,
    html.theme-glass-animated .room-chip-full,
    [class*="theme-glass"] .room-chip-full,
    .theme-dark .room-chip-full {
        background: rgba(239, 68, 68, 0.15) !important;
        border: 1px solid rgba(239, 68, 68, 0.55) !important;
        box-shadow: 0 0 8px rgba(239, 68, 68, 0.18) !important;
    }
    html.theme-glass-solid .room-chip-full .room-chip-name,
    html.theme-glass-animated .room-chip-full .room-chip-name,
    [class*="theme-glass"] .room-chip-full .room-chip-name,
    .theme-dark .room-chip-full .room-chip-name {
        color: #fecaca !important;
        font-weight: 500 !important;
    }
    html.theme-glass-solid .room-chip-full .room-chip-count,
    html.theme-glass-animated .room-chip-full .room-chip-count,
    [class*="theme-glass"] .room-chip-full .room-chip-count,
    .theme-dark .room-chip-full .room-chip-count {
        color: #f87171 !important;
        text-shadow: 0 0 8px rgba(248, 113, 113, 0.6) !important;
    }
    html.theme-glass-solid .room-chip-full:hover,
    html.theme-glass-animated .room-chip-full:hover,
    [class*="theme-glass"] .room-chip-full:hover,
    .theme-dark .room-chip-full:hover {
        background: rgba(239, 68, 68, 0.25) !important;
        border-color: #f87171 !important;
        box-shadow: 0 0 14px rgba(239, 68, 68, 0.5) !important;
    }
    
    /* Style untuk Link Header Chart */
    .chart-header-link { cursor: pointer; transition: color 0.2s; }
    .chart-header-link:hover h6 { color: #2e59d9 !important; text-decoration: underline; }

    /* ==============================================================
       RESPONSIVE TABLET & MEDIUM SCREEN OPTIMIZATIONS (<= 991.98px)
       ============================================================== */
    @media (max-width: 991.98px) {
        .card-body {
            padding: 0.85rem !important;
        }
        .icon-circle {
            height: 2.35rem;
            width: 2.35rem;
            font-size: 1.15rem;
        }
        .card-metric .h5 {
            font-size: 1.15rem;
        }
        .card-metric .text-xs {
            font-size: 0.7rem;
        }

        /* Modal Detail Bed Responsif Tablet: Lebar 96vw agar tabel 7 kolom leluasa */
        #modalDetailBed .modal-dialog {
            max-width: 96vw !important;
            margin: 0.75rem auto !important;
        }
        #modalDetailBed .modal-body {
            padding: 0.75rem !important;
        }
        #modalDetailBed table#tableDetailBed th,
        #modalDetailBed table#tableDetailBed td {
            white-space: nowrap;
            font-size: 0.8rem;
            padding: 6px 8px !important;
        }

        /* Credit Bar Bawah: Icon-only agar pas satu baris tanpa scrollbar horizontal */
        #dev-credit-bar {
            padding: 0 8px;
            gap: 6px;
        }
        #dev-credit-bar .dev-link-btn span {
            display: none !important;
        }
        #dev-credit-bar .dev-credit-brand .dev-role {
            display: none !important;
        }
    }
</style>

<div class="container-fluid">

    <div class="row mb-4">
        
        <!-- CARD 1: OMZET HARI INI -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-4 border-primary shadow h-100 py-2 card-metric" onclick="window.location.href='laporan_billing_global.php'">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="me-2" style="min-width: 0;">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Omzet Hari Ini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800 text-truncate" id="val-omzet-total">...</div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="badge bg-success" style="font-size: 0.7rem;" title="Tunai"><i class="fas fa-money-bill me-1"></i><span id="val-omzet-tunai">0</span></span>
                                <span class="badge bg-warning text-dark" style="font-size: 0.7rem;" title="Piutang"><i class="fas fa-file-invoice me-1"></i><span id="val-omzet-piutang">0</span></span>
                            </div>
                        </div>
                        <div class="icon-circle bg-gradient-primary ms-1"><i class="fas fa-cash-register"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 2: PASIEN REGISTRASI MASUK -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-4 border-success shadow h-100 py-2 card-metric" onclick="window.location.href='laporan_kunjungan.php'">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="me-2" style="min-width: 0;">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Pasien Registrasi Masuk</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-visit-total">...</div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mt-2 text-xs-bold text-muted">
                                <span class="text-nowrap">Ralan: <span id="val-visit-ralan" class="text-success fw-bold">0</span></span>
                                <span class="text-muted opacity-50">|</span>
                                <span class="text-nowrap">Ranap: <span id="val-visit-ranap" class="text-warning fw-bold">0</span></span>
                            </div>
                        </div>
                        <div class="icon-circle bg-gradient-success ms-1"><i class="fas fa-user-plus"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 3: BOR BULAN INI -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-4 border-info shadow h-100 py-2 card-metric" onclick="window.location.href='laporan_indikator_ranap.php'">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="me-2 flex-grow-1" style="min-width: 0;">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">BOR Bulan Ini</div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <div class="h5 mb-0 font-weight-bold text-gray-800 text-nowrap" id="val-bor">...%</div>
                                <div class="progress flex-grow-1" style="height: 8px; min-width: 45px;">
                                    <div class="progress-bar bg-info" role="progressbar" id="bar-bor" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="icon-circle bg-gradient-info ms-2"><i class="fas fa-procedures"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 4: KUNJUNGAN AKTIF -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-4 border-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="me-2" style="min-width: 0;">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Kunjungan Aktif (Blm Bayar)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="val-aktif">...</div>
                            <div class="d-flex gap-1 mt-2">
                                <a href="kunjungan_ralan.php" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 0.7rem;"><i class="fas fa-walking me-1"></i>Ralan</a>
                                <a href="kunjungan_ranap.php" class="btn btn-sm btn-outline-warning py-0 px-2" style="font-size: 0.7rem;"><i class="fas fa-bed me-1"></i>Ranap</a>
                            </div>
                        </div>
                        <div class="icon-circle bg-gradient-danger ms-1"><i class="fas fa-file-invoice-dollar"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between chart-header-link" 
                     onclick="window.location.href='laporan_kunjungan.php'" title="Klik untuk lihat detail kunjungan">
                    <h6 class="m-0 font-weight-bold text-primary">Tren Kunjungan Tahun Ini <i class="fas fa-external-link-alt ms-2 small text-gray-400"></i></h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 320px;">
                        <canvas id="chartTren"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Sumber Omzet Hari Ini</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2" style="height: 250px; cursor: pointer;" onclick="window.location.href='laporan_billing_global.php'">
                        <canvas id="chartOmzet"></canvas>
                    </div>
                    <div class="mt-4 text-center small text-muted">
                        *Klik chart untuk laporan detail
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        
        <!-- KOLOM KIRI: Top 5 Poli & Ketersediaan Bed per Lantai -->
        <div class="col-lg-6 mb-4">
            <!-- 1. TOP 5 POLI -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 chart-header-link" onclick="window.location.href='laporan_kinerja_dokter.php'" title="Klik untuk lihat kunjungan dokter">
                    <h6 class="m-0 font-weight-bold text-primary">Top 5 Poliklinik Hari Ini (Live Queue) <i class="fas fa-external-link-alt ms-2 small text-gray-400"></i></h6>
                </div>
                <div class="card-body" id="container-top-poli">
                    <div class="text-center p-3">Loading...</div>
                </div>
            </div>

            <!-- 2. KETERSEDIAAN BED PER LANTAI (REALTIME) -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-layer-group me-2"></i>Ketersediaan Bed per Lantai (Realtime)</h6>
                    <span class="badge badge-neon-cyan"><i class="fas fa-hospital me-1"></i>Total 60 Bed</span>
                </div>
                <div class="card-body" id="container-bed-lantai">
                    <div class="text-center p-3">Loading...</div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: Ketersediaan Bed per Kelas (Realtime) -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4 h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-procedures me-2"></i>Ketersediaan Bed per Kelas (Realtime)</h6>
                    <span class="badge badge-neon-cyan"><i class="fas fa-bed me-1"></i>Total 60 Bed</span>
                </div>
                <div class="card-body" id="container-bed">
                    <div class="text-center p-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODAL DETAIL BED RAWAT INAP -->
<div class="modal fade" id="modalDetailBed" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-procedures me-2"></i>Pasien Rawat Inap: <span id="modalTitleKelas" class="fw-bold"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body position-relative">
                <!-- State Loading Otomatis -->
                <div id="modalDetailBedLoading" class="text-center py-5">
                    <div class="spinner-border text-info" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-3 fw-bold text-gray-800" id="modalLoadingText" style="font-size: 1.05rem;">Memuat data tempat tidur...</div>
                    <div class="text-muted small">Mengambil status bed dan data pasien aktif secara realtime</div>
                </div>

                <!-- State Error -->
                <div id="modalDetailBedError" class="alert alert-danger my-3" style="display: none;"></div>

                <!-- Konten Data Tabel -->
                <div id="modalDetailBedContent" style="display: none;">
                    <div class="table-responsive">
                        <table id="tableDetailBed" class="table table-bordered table-striped table-sm w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Masuk</th>
                                    <th>No. RM</th>
                                    <th>Nama Pasien</th>
                                    <th>Bangsal / Kamar</th>
                                    <th>Kelas</th>
                                    <th>Penjamin</th>
                                    <th>Lama (Hari)</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
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
    var chartTren, chartOmzet, tableDetailBed, detailBedXhr = null;
    var detailBedCache = {};
    var detailBedCacheTtl = 30000;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        // Init DataTables Modal dengan custom language agar tidak muncul pesan default 'data tidak dimuat otomatis'
        tableDetailBed = $('#tableDetailBed').DataTable({
            "pageLength": 10, 
            "processing": false,
            "deferRender": true, 
            "autoWidth": false, 
            "order": [], 
            "searchDelay": 350,
            "dom": 'Bfrtip', 
            "buttons": ['excel'],
            "language": {
                "emptyTable": '<div class="text-center py-4 text-muted"><i class="fas fa-bed fa-2x mb-2 d-block text-secondary"></i>Tidak ada data tempat tidur / pasien pada kategori ini.</div>',
                "zeroRecords": '<div class="text-center py-4 text-muted"><i class="fas fa-search fa-2x mb-2 d-block text-secondary"></i>Tidak ada data yang cocok dengan pencarian.</div>',
                "search": "Cari:",
                "paginate": {
                    "previous": "Previous",
                    "next": "Next"
                }
            },
            "columns": [
                { "data": "waktu_masuk", "defaultContent": "-" },
                { "data": "no_rkm_medis", "defaultContent": "-" },
                { 
                    "data": "nm_pasien", 
                    "defaultContent": "-",
                    render: function(d, t, r) { 
                        if (!r || r.is_terisi == 0) {
                            var stts = (r && r.status_bed ? r.status_bed : 'KOSONG').toUpperCase();
                            if (stts === 'DIBOOKING') {
                                return '<span class="badge bg-warning text-dark"><i class="fas fa-bookmark me-1"></i>DIBOOKING</span>';
                            }
                            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>BED KOSONG</span>';
                        }
                        return '<span class="fw-bold">' + (d || '-') + '</span>'; 
                    } 
                },
                { 
                    "data": "nm_bangsal", 
                    "defaultContent": "-",
                    render: function(d, t, r) { 
                        if (!r) return d || '-';
                        var ltBadge = r.lantai ? '<span class="badge bg-secondary me-1">Lt. ' + r.lantai + '</span>' : '';
                        var krisBadge = r.is_kris ? '<span class="badge bg-info text-white ms-1" title="KRIS">KRIS</span>' : '';
                        var rName = r.nama_ruangan || d || '-';
                        return ltBadge + rName + ' <small class="text-muted">(' + (r.kd_kamar || '-') + ')</small>' + krisBadge; 
                    } 
                },
                { 
                    "data": "kelas",
                    "defaultContent": "-",
                    render: function(d, t, r) {
                        return (r && r.kelas_sk) ? r.kelas_sk : (d || '-');
                    }
                },
                { "data": "png_jawab", "defaultContent": "-" },
                { "data": "lama_hari", "defaultContent": "-", className: "text-center fw-bold" }
            ]
        });

        // Rekalkulasi kolom DataTables saat modal bed terbuka di mode tablet/desktop
        $('#modalDetailBed').on('shown.bs.modal', function () {
            if (tableDetailBed) {
                try {
                    tableDetailBed.columns.adjust();
                } catch(e) {}
            }
        });

        loadDashboardData();
    });

    function loadDashboardData() {
        $.ajax({
            url: 'api/data_dashboard.php', type: 'GET', dataType: 'json',
            global: false,   // ← Jangan trigger globalLoadingOverlay saat refresh dashboard
            success: function(res) {
                // 1. Omzet
                $('#val-omzet-total').text(formatRupiah(res.omzet.total));
                $('#val-omzet-tunai').text(formatRupiah(res.omzet.tunai));
                $('#val-omzet-piutang').text(formatRupiah(res.omzet.piutang));

                // 2. Kunjungan
                $('#val-visit-total').text(res.kunjungan.Total);
                $('#val-visit-ralan').text(res.kunjungan.Ralan);
                $('#val-visit-ranap').text(res.kunjungan.Ranap);
                
                // 3. BOR
                $('#val-bor').text(res.bed.bor_global + '%');
                $('#bar-bor').css('width', res.bed.bor_global + '%');
                
                // 4. Kunjungan Aktif
                $('#val-aktif').text(res.kunjungan_aktif.toLocaleString());

                // 5. Charts & Widgets
                renderChartTren(res.tren);
                renderChartOmzet(res.omzet);
                renderTopPoli(res.top_poli, res.kunjungan.Total);
                renderBedLantaiMonitor(res.bed.per_lantai);
                renderBedMonitor(res.bed.per_kelas);
            },
            error: function() { console.error("Gagal memuat data dashboard"); }
        });
    }

    function renderTopPoli(data, totalKunjungan) {
        var html = '';
        if(data.length > 0) {
            data.forEach(function(item) {
                var pct = (totalKunjungan > 0) ? (item.jumlah / totalKunjungan) * 100 : 0;
                html += `
                    <h4 class="small font-weight-bold">${item.nm_poli} <span class="float-end">${item.jumlah}</span></h4>
                    <div class="progress mb-4">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: ${pct}%"></div>
                    </div>
                `;
            });
        } else { html = '<div class="text-center text-muted">Belum ada kunjungan hari ini</div>'; }
        $('#container-top-poli').html(html);
    }

    function renderBedLantaiMonitor(data) {
        var html = '';
        if (data && data.length > 0) {
            data.forEach(function(item) {
                var bor = item.bor || 0;
                var color = bor > 85 ? 'bg-danger' : (bor > 60 ? 'bg-warning' : 'bg-success');
                var badgeClass = bor > 85 ? 'badge-neon-rose' : (bor > 60 ? 'badge-neon-amber' : 'badge-neon-emerald');
                
                var groupsHtml = '';
                if (item.groups && item.groups.length > 0) {
                    groupsHtml += '<div class="d-flex flex-wrap gap-1 mt-2 pt-2 room-divider">';
                    item.groups.forEach(function(g) {
                        var gKosong = g.kosong;
                        var gTerisi = g.terisi;
                        var chipClass = gKosong > 0 ? 'room-chip-available' : 'room-chip-full';
                        var gTitle = g.nama + ' (' + g.kelas + '): Terisi ' + gTerisi + ' dari ' + g.total + ' Bed, Kosong ' + gKosong;
                        groupsHtml += `
                            <span class="room-chip ${chipClass}" 
                                  title="${gTitle}"
                                  onclick="event.stopPropagation(); showBedDetail({type: 'ruangan', val: '${g.nama}', title: '${g.nama} (Lt. ${item.lantai})'})">
                                <span class="room-chip-name">${g.nama}</span>
                                <span class="room-chip-count">(${gKosong} ksg)</span>
                            </span>
                        `;
                    });
                    groupsHtml += '</div>';
                }

                html += `
                    <div class="mb-3 p-3 rounded bed-row shadow-sm" 
                         onclick="showBedDetail({type: 'lantai', val: ${item.lantai}, title: '${item.nama} (${item.sublabel})'})">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge badge-neon-blue fs-6 px-2 py-1"><i class="fas fa-layer-group me-1"></i>${item.nama}</span>
                                <span class="small text-muted fw-bold">${item.sublabel}</span>
                            </div>
                            <div>
                                <span class="badge ${badgeClass}">${Math.round(bor)}% Terisi</span>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between small font-weight-bold mt-2">
                            <span class="text-secondary bed-info-text">Terisi ${item.terisi} dari ${item.total} Bed</span>
                            <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Kosong: ${item.kosong} Bed</span>
                        </div>
                        
                        <div class="progress mt-1" style="height: 10px;">
                            <div class="progress-bar ${color}" role="progressbar" style="width: ${bor}%"></div>
                        </div>
                        
                        ${groupsHtml}
                    </div>
                `;
            });
        } else {
            html = '<div class="text-center text-muted p-3">Data ketersediaan per lantai tidak tersedia</div>';
        }
        $('#container-bed-lantai').html(html);
    }

    function renderBedMonitor(data) {
        var html = '';
        if(data.length > 0) {
            data.forEach(function(item) {
                var bor = (item.total > 0) ? (item.terisi / item.total) * 100 : 0;
                var kosong = item.total - item.terisi;
                var color = bor > 85 ? 'bg-danger' : (bor > 60 ? 'bg-warning' : 'bg-success');
                
                html += `
                    <div class="mb-3 p-2 rounded bed-row shadow-sm" onclick="showBedDetail('${item.kelas}')">
                        <div class="d-flex justify-content-between small font-weight-bold">
                            <span class="text-primary fw-bold">${item.kelas}</span>
                            <span class="bed-info-text">Terisi ${item.terisi} dari ${item.total} (${Math.round(bor)}%)</span>
                        </div>
                        <div class="progress mt-1" style="height: 10px;">
                            <div class="progress-bar ${color}" role="progressbar" style="width: ${bor}%"></div>
                        </div>
                        <div class="text-end mt-1" style="font-size: 0.75rem;">
                            <span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Kosong: ${kosong} Bed</span>
                        </div>
                    </div>
                `;
            });
        } else { html = '<div class="text-center text-muted">Data bed tidak tersedia</div>'; }
        $('#container-bed').html(html);
    }

    function renderDetailBedRows(rows) {
        try {
            if (tableDetailBed) {
                tableDetailBed.clear();
                if (rows && rows.length > 0) {
                    tableDetailBed.rows.add(rows);
                }
                tableDetailBed.draw(false);
            }
        } catch(e) {
            console.warn('DataTable draw error:', e);
        }
    }

    function showBedDetail(param) {
        var queryData = {};
        var displayTitle = '';
        var cacheKey = '';

        if (typeof param === 'string') {
            queryData = { kelas: param };
            displayTitle = param;
            cacheKey = 'kelas_' + param;
        } else if (typeof param === 'object' && param !== null) {
            if (param.type === 'lantai') {
                queryData = { lantai: param.val };
                displayTitle = param.title || ('Lantai ' + param.val);
                cacheKey = 'lantai_' + param.val;
            } else if (param.type === 'ruangan') {
                queryData = { ruangan: param.val };
                displayTitle = param.title || ('Ruang ' + param.val);
                cacheKey = 'ruangan_' + param.val;
            } else {
                queryData = { kelas: param.val };
                displayTitle = param.title || param.val;
                cacheKey = 'kelas_' + param.val;
            }
        }

        $('#modalTitleKelas').text(displayTitle);
        
        // Reset state modal
        $('#modalDetailBedError').hide();
        $('#modalDetailBedContent').hide();
        $('#modalDetailBedLoading').show();
        $('#modalLoadingText').text('Memuat data tempat tidur: ' + displayTitle + '...');
        $('#modalDetailBed').modal('show');

        // Gunakan cache jika masih valid
        if (detailBedCache[cacheKey] && (Date.now() - detailBedCache[cacheKey].time) < detailBedCacheTtl) {
            renderDetailBedRows(detailBedCache[cacheKey].rows);
            $('#modalDetailBedLoading').hide();
            $('#modalDetailBedContent').show();
            setTimeout(function() {
                try {
                    if (tableDetailBed) tableDetailBed.columns.adjust();
                } catch(e) {}
            }, 50);
            return;
        }

        if (detailBedXhr && detailBedXhr.readyState !== 4) {
            detailBedXhr.abort();
        }

        detailBedXhr = $.ajax({
            url: 'api/data_detail_bed.php',
            type: 'GET',
            data: queryData,
            dataType: 'json',
            cache: false,
            global: false,
            timeout: 20000,
            success: function(res) {
                var rows = (res && res.data && Array.isArray(res.data)) ? res.data : [];
                detailBedCache[cacheKey] = { rows: rows, time: Date.now() };
                
                $('#modalDetailBedLoading').hide();
                $('#modalDetailBedError').hide();
                $('#modalDetailBedContent').show();
                
                renderDetailBedRows(rows);
                
                setTimeout(function() {
                    try {
                        if (tableDetailBed) tableDetailBed.columns.adjust();
                    } catch(e) {}
                }, 50);
            },
            error: function(xhr, status) {
                if (status !== 'abort') {
                    var pesanError = (status === 'timeout')
                        ? 'Request timeout. Koneksi terlalu lambat, silakan coba lagi.'
                        : 'Gagal memuat data tempat tidur (status: ' + status + ').';
                    $('#modalDetailBedLoading').hide();
                    $('#modalDetailBedContent').hide();
                    $('#modalDetailBedError').html('<i class="fas fa-exclamation-triangle me-2"></i>' + pesanError).show();
                }
            }
        });
    }

    function renderChartTren(data) {
        var ctx = document.getElementById("chartTren").getContext('2d');
        if(chartTren) chartTren.destroy();

        var pluginDataLabels = {
            id: 'trendDataLabels',
            afterDatasetsDraw: function(chart) {
                var ctx = chart.ctx;
                ctx.save();
                
                var theme = localStorage.getItem('app_theme') || 'theme-glass-animated';
                var isDark = theme.includes('glass') || document.documentElement.classList.contains('theme-glass');
                
                chart.data.datasets.forEach(function(dataset, datasetIndex) {
                    var meta = chart.getDatasetMeta(datasetIndex);
                    if (meta.hidden) return; // Lewati jika dataset disembunyikan via legend
                    
                    meta.data.forEach(function(element, index) {
                        var val = dataset.data[index];
                        if (val === null || val === undefined || val <= 0) return; // Hanya tampilkan angka jika ada kunjungan
                        
                        var x = element.x;
                        var y = element.y;
                        var text = Number(val).toLocaleString('id-ID');
                        
                        // Posisi teks: titik paling kiri (Jan) digeser sedikit ke kanan agar tidak menempel sumbu Y
                        var textX = x;
                        if (index === 0) {
                            ctx.textAlign = 'left';
                            textX = x + 3;
                        } else if (index === dataset.data.length - 1) {
                            ctx.textAlign = 'right';
                            textX = x - 3;
                        } else {
                            ctx.textAlign = 'center';
                        }
                        ctx.textBaseline = 'bottom';
                        
                        // Outline kontras untuk tema gelap maupun terang
                        ctx.strokeStyle = isDark ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.95)';
                        ctx.lineWidth = 3;
                        ctx.strokeText(text, textX, y - 6);
                        
                        // Warna angka sesuai dataset
                        var textColor = dataset.borderColor;
                        if (isDark) {
                            if (datasetIndex === 0) textColor = '#7096fe';
                            else if (datasetIndex === 1) textColor = '#facc15';
                            else if (datasetIndex === 2) textColor = '#34d399';
                        } else {
                            if (datasetIndex === 0) textColor = '#2e59d9';
                            else if (datasetIndex === 1) textColor = '#d97706';
                            else if (datasetIndex === 2) textColor = '#059669';
                        }
                        
                        ctx.fillStyle = textColor;
                        ctx.fillText(text, textX, y - 6);
                    });
                });
                
                ctx.restore();
            }
        };
        
        chartTren = new Chart(ctx, {
            type: 'line',
            plugins: [pluginDataLabels],
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"],
                datasets: [
                    {
                        label: "Total",
                        data: data.total,
                        borderColor: "#4e73df",
                        backgroundColor: "#4e73df",
                        borderWidth: 3,
                        tension: 0.3,
                        pointRadius: function(c) {
                            var v = c.dataset.data[c.dataIndex];
                            return (v && v > 0) ? 4 : 0;
                        },
                        pointHoverRadius: 6
                    },
                    {
                        label: "Rawat Jalan",
                        data: data.ralan,
                        borderColor: "rgba(246, 194, 62, 0.85)",
                        backgroundColor: "rgba(246, 194, 62, 0.85)",
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.3,
                        pointRadius: function(c) {
                            var v = c.dataset.data[c.dataIndex];
                            return (v && v > 0) ? 4 : 0;
                        },
                        pointHoverRadius: 6
                    },
                    {
                        label: "Rawat Inap",
                        data: data.ranap,
                        borderColor: "rgba(28, 200, 138, 0.85)",
                        backgroundColor: "rgba(28, 200, 138, 0.85)",
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.3,
                        pointRadius: function(c) {
                            var v = c.dataset.data[c.dataIndex];
                            return (v && v > 0) ? 4 : 0;
                        },
                        pointHoverRadius: 6
                    }
                ],
            },
            options: { 
                maintainAspectRatio: false, 
                layout: {
                    padding: {
                        top: 25,
                        right: 15,
                        left: 18,
                        bottom: 5
                    }
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grace: '10%'
                    } 
                }, 
                plugins: { 
                    legend: { display: true } 
                } 
            }
        });
    }

    function renderChartOmzet(dataObj) {
        var ctx = document.getElementById("chartOmzet").getContext('2d');
        if(chartOmzet) chartOmzet.destroy();
        chartOmzet = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: dataObj.labels,
                datasets: [{
                    data: dataObj.data,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, cutout: '70%' }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>
<?php require_once('includes/footer.php'); ?>


