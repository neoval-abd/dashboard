<?php
/*
 * File: laporan_ketepatan_dokter.php
 * Deskripsi: Halaman Dashboard untuk melacak dan menilai ketepatan waktu kedatangan/pelayanan dokter
 *            berdasarkan perbandingan jam jadwal SIMRS (tabel jadwal) dengan jejak digital 
 *            inputan pertama dokter (SOAP Ralan, SOAP Ranap/Visite, dsb).
 */

$page_title = "Ketepatan Dokter";
require_once('includes/header.php');

$today = date('Y-m-d');

// Ambil daftar poliklinik aktif untuk dropdown filter
$list_poli = [];
$res_poli = $koneksi->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status='1' ORDER BY nm_poli ASC");
if ($res_poli) {
    while ($row = $res_poli->fetch_assoc()) {
        $list_poli[] = $row;
    }
}

// Ambil daftar dokter aktif untuk dropdown filter
$list_dokter = [];
$res_doc = $koneksi->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status='1' ORDER BY nm_dokter ASC");
if ($res_doc) {
    while ($row = $res_doc->fetch_assoc()) {
        $list_dokter[] = $row;
    }
}
?>

<style>
    /* Styling khusus dashboard ketepatan dokter */
    .kpi-card {
        border-radius: 12px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important;
    }
    .badge-soft-primary {
        background-color: rgba(13, 110, 253, 0.12);
        color: #0d6efd;
        border: 1px solid rgba(13, 110, 253, 0.25);
    }
    .badge-soft-success {
        background-color: rgba(25, 135, 84, 0.12);
        color: #198754;
        border: 1px solid rgba(25, 135, 84, 0.25);
    }
    .badge-soft-info {
        background-color: rgba(13, 202, 240, 0.12);
        color: #0dcaf0;
        border: 1px solid rgba(13, 202, 240, 0.25);
    }
    .badge-soft-secondary {
        background-color: rgba(108, 117, 125, 0.12);
        color: #6c757d;
        border: 1px solid rgba(108, 117, 125, 0.25);
    }
    .badge-soft-warning {
        background-color: rgba(255, 193, 7, 0.15);
        color: #b07d00;
        border: 1px solid rgba(255, 193, 7, 0.3);
    }
    .timeline-badge-step {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
    }
</style>

<div class="container-fluid py-2">
    <!-- Header Page -->
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fas fa-user-clock text-primary me-2"></i>Absensi Dokter (Berdasarkan Pertama Kali Input)
            </h1>
        </div>
        <div class="mt-2 mt-sm-0">
            <span class="badge bg-light text-primary border p-2">
                <i class="fas fa-calendar-day text-primary me-1"></i> Data Realtime SIMRS
            </span>
        </div>
    </div>

    <!-- Alert Edukasi / Konteks Indikator -->
    <div class="alert alert-info border-0 shadow-sm mb-4 py-2 px-3 d-flex align-items-center" role="alert" style="border-radius: 10px;">
        <i class="fas fa-info-circle fa-lg me-3 text-info"></i>
        <div class="small">
            <strong>Cara Kerja Penilaian:</strong> Waktu kedatangan dokter dinilai dari <em>jejak digital pertama</em> dokter di sistem (SOAP CPPT Ralan atau Visite Ranap), lalu dikomparasikan terhadap <strong>Jam Mulai</strong> di jadwal praktik dokter poli. Toleransi tepat waktu default adalah <strong>&le; 15 menit</strong>.
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4 border-0" style="border-radius: 12px;">
        <div class="card-body">
            <form id="formFilter" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Dari Tanggal</label>
                    <input type="date" class="form-control" id="tgl_awal" value="<?= $today; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="tgl_akhir" value="<?= $today; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Poliklinik</label>
                    <select class="form-select" id="kd_poli">
                        <option value="">-- Semua Poliklinik --</option>
                        <?php foreach ($list_poli as $p): ?>
                            <option value="<?= htmlspecialchars($p['kd_poli']); ?>">
                                <?= htmlspecialchars($p['nm_poli']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Dokter</label>
                    <select class="form-select" id="kd_dokter">
                        <option value="">-- Semua Dokter --</option>
                        <?php foreach ($list_dokter as $d): ?>
                            <option value="<?= htmlspecialchars($d['kd_dokter']); ?>">
                                <?= htmlspecialchars($d['nm_dokter']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Status Kepatuhan</label>
                    <select class="form-select" id="status_filter">
                        <option value="">-- Semua Status --</option>
                        <option value="TEPAT_WAKTU">Tepat Waktu (&le; 15 Mnt)</option>
                        <option value="TERLAMBAT">Terlambat (> 15 Mnt)</option>
                        <option value="BELUM_INPUT">Belum Ada Input</option>
                    </select>
                </div>

                <!-- Parameter Sumber Inputan (Modular & Extensible) -->
                <div class="col-12 mt-2">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-bold small text-primary">
                                <i class="fas fa-sliders-h me-1"></i> Parameter Deteksi Inputan Pertama Dokter:
                            </span>
                            <span class="badge bg-secondary small" style="font-size: 0.72rem;">Tambah parameter sesuai kebutuhan</span>
                        </div>
                        <div class="row g-2 align-items-center">
                            <div class="col-auto">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="src_soap_ralan" checked>
                                    <label class="form-check-label small" for="src_soap_ralan">
                                        <i class="fas fa-notes-medical text-primary me-1"></i> SOAP Ralan (Poli)
                                    </label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="src_soap_ranap" checked>
                                    <label class="form-check-label small" for="src_soap_ranap">
                                        <i class="fas fa-procedures text-success me-1"></i> SOAP Ranap (Visite)
                                    </label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="src_tindakan_ralan">
                                    <label class="form-check-label small text-muted" for="src_tindakan_ralan">
                                        <i class="fas fa-hand-holding-medical me-1"></i> Tindakan Ralan
                                    </label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="src_tindakan_ranap">
                                    <label class="form-check-label small text-muted" for="src_tindakan_ranap">
                                        <i class="fas fa-syringe me-1"></i> Tindakan Ranap
                                    </label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="checkbox" id="src_resep">
                                    <label class="form-check-label small text-muted" for="src_resep">
                                        <i class="fas fa-pills me-1"></i> Resep Obat
                                    </label>
                                </div>
                            </div>
                            <div class="col-auto ms-auto d-flex align-items-center">
                                <label class="small text-muted me-2 mb-0">Toleransi:</label>
                                <div class="input-group input-group-sm" style="width: 105px;">
                                    <input type="number" class="form-control text-center" id="toleransi" value="15" min="0" max="60">
                                    <span class="input-group-text">Mnt</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Action & Shortcut Tanggal -->
                <div class="col-12 d-flex flex-wrap align-items-center justify-content-between pt-1">
                    <div class="btn-group btn-group-sm mb-2 mb-md-0" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="setDateRange('today')">Hari Ini</button>
                        <button type="button" class="btn btn-outline-primary" onclick="setDateRange('yesterday')">Kemarin</button>
                        <button type="button" class="btn btn-outline-primary" onclick="setDateRange('last7')">7 Hari Terakhir</button>
                        <button type="button" class="btn btn-outline-primary" onclick="setDateRange('thisMonth')">Bulan Ini</button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary btn-sm px-4 shadow-sm" onclick="loadData()">
                            <i class="fas fa-search me-1"></i> Terapkan Filter
                        </button>
                        <button type="button" class="btn btn-light btn-sm border ms-1" onclick="resetFilter()">
                            <i class="fas fa-undo me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- 1. Kepatuhan On-Time % -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm kpi-card h-100 border-start border-4 border-success bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs fw-bold text-success text-uppercase">Kepatuhan On-Time</span>
                        <div class="rounded-circle p-2 bg-success text-white" style="width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="h3 mb-1 fw-bold text-gray-800" id="kpi-persen-tepat">0%</div>
                    <div class="progress mb-2" style="height: 6px;">
                        <div id="kpi-bar-tepat" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="text-muted" id="kpi-detail-tepat">0 dari 0 dokter hadir tepat waktu</small>
                </div>
            </div>
        </div>

        <!-- 2. Dokter Hadir & Tepat Waktu -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm kpi-card h-100 border-start border-4 border-primary bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs fw-bold text-primary text-uppercase">Dokter Tepat Waktu</span>
                        <div class="rounded-circle p-2 bg-primary text-white" style="width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="h3 mb-1 fw-bold text-gray-800" id="kpi-total-tepat">0</div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-calendar-check me-1 text-primary"></i>
                        Dari total <span id="kpi-total-jadwal" class="fw-bold">0</span> sesi jadwal
                    </small>
                </div>
            </div>
        </div>

        <!-- 3. Rata-rata Keterlambatan -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm kpi-card h-100 border-start border-4 border-warning bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs fw-bold text-warning text-uppercase">Rata-rata Keterlambatan</span>
                        <div class="rounded-circle p-2 bg-warning text-dark" style="width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                    </div>
                    <div class="h3 mb-1 fw-bold text-gray-800">
                        <span id="kpi-rata-telat">0</span> <small class="text-muted fs-6">Menit</small>
                    </div>
                    <small class="text-muted d-block mt-2" id="kpi-detail-telat">
                        0 dokter terlambat (> 15 mnt)
                    </small>
                </div>
            </div>
        </div>

        <!-- 4. Belum Ada Input / Mangkir -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm kpi-card h-100 border-start border-4 border-danger bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-xs fw-bold text-danger text-uppercase">Belum Ada Input</span>
                        <div class="rounded-circle p-2 bg-danger text-white" style="width:38px; height:38px; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                    <div class="h3 mb-1 fw-bold text-gray-800" id="kpi-belum-input">0</div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-exclamation-triangle text-danger me-1"></i> Terjadwal tanpa jejak input
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-chart-pie me-1"></i> Proporsi Kepatuhan Pelayanan
                    </h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100" style="border-radius: 12px;">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="fas fa-chart-bar me-1"></i> Top Dokter Terlambat (Total Menit)
                    </h6>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="chartTopTelat"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
        <div class="card-header bg-white py-3 border-0 d-flex flex-row align-items-center justify-content-between">
            <div>
                <h6 class="m-0 fw-bold text-primary">
                    <i class="fas fa-list-alt me-1"></i> Log Rincian Ketepatan Waktu Pelayanan Dokter
                </h6>
                <small class="text-muted">Setiap baris mewakili 1 sesi jadwal praktik dokter dan inputan pertama yang terdeteksi.</small>
            </div>
            <span class="badge bg-primary rounded-pill px-3 py-2" id="badgeTotalData">0 Sesi Jadwal</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle w-100" id="tableKetepatan">
                    <thead class="table-light text-center small text-uppercase">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Tanggal & Hari</th>
                            <th>Dokter Spesialis</th>
                            <th>Poliklinik</th>
                            <th>Jam Jadwal</th>
                            <th>Input Pertama</th>
                            <th>Sumber Inputan</th>
                            <th>Selisih (Deviasi)</th>
                            <th>Status Kepatuhan</th>
                            <th style="width: 90px;">Pasien 1</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <!-- Data dimuat via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Pasien Pertama -->
<div class="modal fade" id="modalDetailPasien" tabindex="-1" aria-labelledby="modalDetailPasienLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h6 class="modal-title fw-bold" id="modalDetailPasienLabel">
                    <i class="fas fa-user-injured me-2"></i>Detail Pasien Pertama yang Dilayani
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center pb-3 border-bottom mb-3">
                    <div class="rounded-circle bg-light d-inline-flex p-3 mb-2 shadow-sm text-primary">
                        <i class="fas fa-id-card-alt fa-2x"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark" id="modal-nm-pasien">-</h5>
                    <div class="text-muted small">No. RM: <span id="modal-no-rkm-medis" class="fw-bold">-</span> | No. Rawat: <span id="modal-no-rawat" class="font-monospace">-</span></div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Dokter:</div>
                            <div class="fw-bold small text-dark" id="modal-nm-dokter">-</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light">
                            <div class="text-muted small">Poliklinik:</div>
                            <div class="fw-bold small text-dark" id="modal-nm-poli">-</div>
                        </div>
                    </div>
                </div>

                <!-- Timeline Perjalanan Waktu -->
                <h6 class="fw-bold small text-primary mb-3">
                    <i class="fas fa-stopwatch me-1"></i> Linimasa Respon Pelayanan:
                </h6>
                <ul class="list-group list-group-flush small mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fas fa-clock text-secondary me-2"></i>Jam Mulai Jadwal Dokter</span>
                        <span class="badge bg-secondary font-monospace" id="modal-jam-jadwal">-</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fas fa-ticket-alt text-info me-2"></i>Jam Registrasi Pasien</span>
                        <span class="badge bg-info text-dark font-monospace" id="modal-jam-reg">-</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fas fa-file-medical text-primary me-2"></i>Jam Input Pertama Dokter</span>
                        <span class="badge bg-primary font-monospace" id="modal-jam-input">-</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <span><i class="fas fa-layer-group text-dark me-2"></i>Jenis/Sumber Aktivitas</span>
                        <span class="fw-bold text-dark" id="modal-source">-</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 bg-light rounded mt-2">
                        <span class="fw-bold text-dark"><i class="fas fa-hourglass-half text-warning me-2"></i>Lama Pasien Menunggu (TAT)</span>
                        <span class="fw-bold text-danger fs-6" id="modal-tat-pasien">-</span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer border-0 bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    var tableInstance = null;
    var chartStatusInstance = null;
    var chartTopTelatInstance = null;
    var currentDataList = [];

    document.addEventListener("DOMContentLoaded", function() {
        loadData();
    });

    function setDateRange(range) {
        var today = new Date();
        var tglAwal = document.getElementById('tgl_awal');
        var tglAkhir = document.getElementById('tgl_akhir');

        var formatDate = function(d) {
            var month = '' + (d.getMonth() + 1),
                day = '' + d.getDate(),
                year = d.getFullYear();
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            return [year, month, day].join('-');
        };

        if (range === 'today') {
            tglAwal.value = formatDate(today);
            tglAkhir.value = formatDate(today);
        } else if (range === 'yesterday') {
            var y = new Date();
            y.setDate(today.getDate() - 1);
            tglAwal.value = formatDate(y);
            tglAkhir.value = formatDate(y);
        } else if (range === 'last7') {
            var l7 = new Date();
            l7.setDate(today.getDate() - 6);
            tglAwal.value = formatDate(l7);
            tglAkhir.value = formatDate(today);
        } else if (range === 'thisMonth') {
            var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            tglAwal.value = formatDate(firstDay);
            tglAkhir.value = formatDate(today);
        }
        loadData();
    }

    function resetFilter() {
        var today = '<?= $today; ?>';
        document.getElementById('tgl_awal').value = today;
        document.getElementById('tgl_akhir').value = today;
        document.getElementById('kd_poli').value = '';
        document.getElementById('kd_dokter').value = '';
        document.getElementById('status_filter').value = '';
        document.getElementById('src_soap_ralan').checked = true;
        document.getElementById('src_soap_ranap').checked = true;
        document.getElementById('src_tindakan_ralan').checked = false;
        document.getElementById('src_tindakan_ranap').checked = false;
        document.getElementById('src_resep').checked = false;
        document.getElementById('toleransi').value = 15;
        loadData();
    }

    function loadData() {
        var tglAwal = document.getElementById('tgl_awal').value;
        var tglAkhir = document.getElementById('tgl_akhir').value;
        var kdPoli = document.getElementById('kd_poli').value;
        var kdDokter = document.getElementById('kd_dokter').value;
        var statusFilter = document.getElementById('status_filter').value;
        var toleransi = document.getElementById('toleransi').value;

        var srcSoapRalan = document.getElementById('src_soap_ralan').checked ? 1 : 0;
        var srcSoapRanap = document.getElementById('src_soap_ranap').checked ? 1 : 0;
        var srcTindRalan = document.getElementById('src_tindakan_ralan').checked ? 1 : 0;
        var srcTindRanap = document.getElementById('src_tindakan_ranap').checked ? 1 : 0;
        var srcResep     = document.getElementById('src_resep').checked ? 1 : 0;

        // Show table loading state
        if (tableInstance) {
            tableInstance.clear().draw();
        }

        var url = 'api/data_ketepatan_dokter.php?' + new URLSearchParams({
            tgl_awal: tglAwal,
            tgl_akhir: tglAkhir,
            kd_poli: kdPoli,
            kd_dokter: kdDokter,
            status: statusFilter,
            toleransi: toleransi,
            src_soap_ralan: srcSoapRalan,
            src_soap_ranap: srcSoapRanap,
            src_tindakan_ralan: srcTindRalan,
            src_tindakan_ranap: srcTindRanap,
            src_resep: srcResep
        }).toString();

        fetch(url)
            .then(function(res) { return res.json(); })
            .then(function(res) {
                if (res.status !== 'success') {
                    alert('Peringatan: ' + (res.message || 'Gagal memuat data.'));
                    return;
                }

                currentDataList = res.data || [];
                updateSummary(res.summary);
                renderCharts(res.chart);
                renderTable(currentDataList);
            })
            .catch(function(err) {
                console.error('Error fetching data:', err);
                alert('Terjadi kesalahan saat memuat data ketepatan dokter.');
            });
    }

    function updateSummary(s) {
        if (!s) return;
        document.getElementById('kpi-persen-tepat').innerText = (s.persentase_tepat || 0) + '%';
        document.getElementById('kpi-bar-tepat').style.width = (s.persentase_tepat || 0) + '%';
        document.getElementById('kpi-detail-tepat').innerText = (s.total_tepat_waktu || 0) + ' dari ' + (s.total_hadir || 0) + ' dokter aktif tepat waktu';

        document.getElementById('kpi-total-tepat').innerText = (s.total_tepat_waktu || 0);
        document.getElementById('kpi-total-jadwal').innerText = (s.total_jadwal || 0);

        document.getElementById('kpi-rata-telat').innerText = (s.rata_keterlambatan || 0);
        var totalTelat = (s.total_terlambat_sedang || 0) + (s.total_terlambat_parah || 0);
        document.getElementById('kpi-detail-telat').innerText = totalTelat + ' dokter terlambat (> ' + document.getElementById('toleransi').value + ' mnt)';

        document.getElementById('kpi-belum-input').innerText = (s.total_belum_input || 0);
        document.getElementById('badgeTotalData').innerText = (s.total_jadwal || 0) + ' Sesi Jadwal';
    }

    function renderCharts(c) {
        if (!c) return;

        // 1. Donut Chart Status
        if (chartStatusInstance) chartStatusInstance.destroy();
        var ctxStatus = document.getElementById('chartStatus').getContext('2d');
        chartStatusInstance = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: c.status_distribution.labels,
                datasets: [{
                    data: c.status_distribution.values,
                    backgroundColor: ['#198754', '#ffc107', '#dc3545', '#6c757d'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                cutout: '65%'
            }
        });

        // 2. Bar Chart Top Terlambat
        if (chartTopTelatInstance) chartTopTelatInstance.destroy();
        var ctxTop = document.getElementById('chartTopTelat').getContext('2d');
        chartTopTelatInstance = new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: c.top_terlambat.labels,
                datasets: [{
                    label: 'Total Menit Terlambat',
                    data: c.top_terlambat.values,
                    backgroundColor: 'rgba(220, 53, 69, 0.75)',
                    borderColor: '#dc3545',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ctx.raw + ' Menit keterlambatan'; }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: { display: true, text: 'Menit Keterlambatan' }
                    }
                }
            }
        });
    }

    function renderTable(data) {
        if (tableInstance) {
            tableInstance.destroy();
        }

        var tbody = document.querySelector('#tableKetepatan tbody');
        tbody.innerHTML = '';

        data.forEach(function(row, idx) {
            var tr = document.createElement('tr');

            // Button detail pasien pertama
            var btnPasien = '<span class="text-muted">-</span>';
            if (row.pasien_pertama) {
                btnPasien = `<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" onclick="showDetail(${idx})" title="Lihat Pasien Pertama">
                    <i class="fas fa-eye me-1"></i>Detail
                </button>`;
            }

            var badgeSource = `<span class="badge ${row.source_badge || 'bg-light text-dark'}">
                <i class="${row.source_icon || 'fas fa-tag'} me-1"></i>${row.source}
            </span>`;

            var jamJadwalDisplay = `<span class="font-monospace fw-bold">${row.jam_mulai}</span> - <span class="font-monospace text-muted">${row.jam_selesai}</span>`;
            var jamPertamaDisplay = row.jam_pertama !== '-' ? `<span class="badge bg-light text-dark border font-monospace fs-7">${row.jam_pertama}</span>` : '<span class="text-muted">-</span>';

            var deviasiDisplay = row.selisih_label;
            if (row.selisih_menit !== null) {
                if (row.selisih_menit <= 0) {
                    deviasiDisplay = `<span class="text-success fw-bold"><i class="fas fa-arrow-down me-1"></i>${row.selisih_label}</span>`;
                } else if (row.selisih_menit <= document.getElementById('toleransi').value) {
                    deviasiDisplay = `<span class="text-primary fw-bold"><i class="fas fa-check me-1"></i>${row.selisih_label}</span>`;
                } else {
                    deviasiDisplay = `<span class="text-danger fw-bold"><i class="fas fa-arrow-up me-1"></i>${row.selisih_label}</span>`;
                }
            }

            tr.innerHTML = `
                <td class="text-center font-monospace">${idx + 1}</td>
                <td><span class="fw-bold">${row.tanggal}</span> <br><small class="badge bg-light text-secondary">${row.hari}</small></td>
                <td><strong class="text-dark">${row.nm_dokter}</strong></td>
                <td>${row.nm_poli}</td>
                <td class="text-center">${jamJadwalDisplay}</td>
                <td class="text-center">${jamPertamaDisplay}</td>
                <td class="text-center">${badgeSource}</td>
                <td class="text-center">${deviasiDisplay}</td>
                <td class="text-center"><span class="${row.status_badge}">${row.status_label}</span></td>
                <td class="text-center">${btnPasien}</td>
            `;
            tbody.appendChild(tr);
        });

        tableInstance = $('#tableKetepatan').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
                emptyTable: 'Tidak ada jadwal dokter atau data pelayanan ditemukan untuk periode ini.'
            },
            dom: '<"d-flex flex-wrap justify-content-between align-items-center mb-3"Bf>rt<"d-flex flex-wrap justify-content-between align-items-center mt-3"ip>',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel text-success me-1"></i> Export Excel',
                    className: 'btn btn-outline-secondary btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        format: {
                            body: function(data) {
                                return window.dtExportCleanText ? window.dtExportCleanText(data) : data;
                            }
                        }
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print me-1"></i> Cetak',
                    className: 'btn btn-outline-secondary btn-sm',
                    exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8] }
                },
                {
                    extend: 'copy',
                    text: '<i class="fas fa-copy me-1"></i> Salin',
                    className: 'btn btn-outline-secondary btn-sm'
                }
            ],
            pageLength: 25,
            order: [[0, 'asc']]
        });
    }

    function showDetail(index) {
        var row = currentDataList[index];
        if (!row || !row.pasien_pertama) return;

        var p = row.pasien_pertama;
        document.getElementById('modal-nm-pasien').innerText = p.nm_pasien || '-';
        document.getElementById('modal-no-rkm-medis').innerText = p.no_rkm_medis || '-';
        document.getElementById('modal-no-rawat').innerText = p.no_rawat || '-';
        document.getElementById('modal-nm-dokter').innerText = row.nm_dokter || '-';
        document.getElementById('modal-nm-poli').innerText = row.nm_poli || '-';
        document.getElementById('modal-jam-jadwal').innerText = row.jam_mulai + ' - ' + row.jam_selesai;
        document.getElementById('modal-jam-reg').innerText = p.jam_reg || '-';
        document.getElementById('modal-jam-input').innerText = p.jam_input || '-';
        document.getElementById('modal-source').innerText = row.source || '-';
        document.getElementById('modal-tat-pasien').innerText = p.tat_menit || '-';

        var myModal = new bootstrap.Modal(document.getElementById('modalDetailPasien'));
        myModal.show();
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>

