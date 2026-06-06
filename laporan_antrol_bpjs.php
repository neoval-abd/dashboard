<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$page_title = "Pengiriman Antrean Online BPJS";
include 'includes/header.php';

$tgl1 = isset($_GET['tgl1']) ? $_GET['tgl1'] : date('Y-m-d', strtotime('-7 days'));
$tgl2 = isset($_GET['tgl2']) ? $_GET['tgl2'] : date('Y-m-d');

// ============================================================
// Query DB Lokal — HANYA untuk mendapatkan daftar pasien.
// Task ID TIDAK lagi dibaca dari tabel lokal sebagai penentu
// status. Status final akan ditentukan oleh API BPJS (live).
// Kolom log TID_x di sini hanya sebagai fallback/referensi.
// ============================================================
$sql = "SELECT 
    rp.no_rawat,
    rmj.nobooking,
    rp.tgl_registrasi,
    ps.no_rkm_medis,
    ps.nm_pasien,
    pj.png_jawab,
    d.nm_dokter,
    mpb.nm_poli_bpjs AS `Poliklinik`,
    IFNULL(rmj.status, 'On Site') AS `Status Checkin MJKN`,
    COALESCE((CASE WHEN rp.stts = 'Batal' THEN rp.stts END), '-') AS `Cancel`,
    rmj.status AS `status_mjkn`,
    rmb.statuskirim AS `statuskirim_batal`,
    rmb.keterangan AS `ket_batal`,
    (SELECT no_sep FROM bridging_sep WHERE no_rawat = rp.no_rawat LIMIT 1) AS `no_sep`,
    CONCAT(rp.tgl_registrasi, ' ', rp.jam_reg) AS `Jam Registrasi`,
    rmj.validasi AS `Jam Checkin`,
    -- Kolom lokal: hanya dipakai untuk Raw/IT tab & fallback
    DATE_FORMAT(MAX(CASE WHEN rmjt.taskid = '3' THEN rmjt.waktu END), '%Y-%m-%d %H:%i:%s') AS `local_TID_3`,
    DATE_FORMAT(MAX(CASE WHEN rmjt.taskid = '4' THEN rmjt.waktu END), '%Y-%m-%d %H:%i:%s') AS `local_TID_4`,
    DATE_FORMAT(MAX(CASE WHEN rmjt.taskid = '5' THEN rmjt.waktu END), '%Y-%m-%d %H:%i:%s') AS `local_TID_5`,
    DATE_FORMAT(MAX(CASE WHEN rmjt.taskid = '6' THEN rmjt.waktu END), '%Y-%m-%d %H:%i:%s') AS `local_TID_6`,
    DATE_FORMAT(MAX(CASE WHEN rmjt.taskid = '7' THEN rmjt.waktu END), '%Y-%m-%d %H:%i:%s') AS `local_TID_7`,
    -- Referensi ERM (untuk tab Raw/IT)
    (SELECT CONCAT(pr.tgl_perawatan, ' ', pr.jam_rawat)
     FROM pemeriksaan_ralan pr INNER JOIN dokter d2 ON pr.nip = d2.kd_dokter
     WHERE pr.no_rawat = rp.no_rawat ORDER BY pr.tgl_perawatan, pr.jam_rawat LIMIT 1) AS `ERM_TID4`,
    mb.kembali AS `ERM_TID5`,
    CONCAT(ro.tgl_perawatan, ' ', ro.jam) AS `ERM_TID6`,
    CONCAT(ro.tgl_penyerahan, ' ', ro.jam_penyerahan) AS `ERM_TID7`,
    -- Apakah pasien punya resep? (dari ERM lokal)
    (ro.no_rawat IS NOT NULL) AS `has_resep`
FROM reg_periksa rp
LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
LEFT JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
LEFT JOIN maping_poli_bpjs mpb ON rp.kd_poli = mpb.kd_poli_rs
LEFT JOIN referensi_mobilejkn_bpjs_taskid rmjt ON rp.no_rawat = rmjt.no_rawat
LEFT JOIN referensi_mobilejkn_bpjs rmj ON rp.no_rawat = rmj.no_rawat
LEFT JOIN mutasi_berkas mb ON rp.no_rawat = mb.no_rawat
LEFT JOIN resep_obat ro ON rp.no_rawat = ro.no_rawat
LEFT JOIN referensi_mobilejkn_bpjs_batal rmb ON rp.no_rawat = rmb.no_rawat_batal
WHERE rp.tgl_registrasi BETWEEN ? AND ?
    AND mpb.nm_poli_bpjs NOT LIKE '%INSTALASI GAWAT DARURAT%'
    AND pj.png_jawab LIKE '%BPJ%'
GROUP BY rp.no_rawat
ORDER BY rp.no_rawat ASC";

// Kumpulkan daftar pasien ke array PHP (bukan langsung render HTML)
// agar bisa dikirim ke frontend sebagai JSON untuk proses AJAX live.
$patients = [];

if ($stmt = $koneksi->prepare($sql)) {
    $stmt->bind_param("ss", $tgl1, $tgl2);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $is_batal_rs   = ($row['Cancel'] === 'Batal');
            $has_local_t3  = !empty($row['local_TID_3']);

            // Cek anomali batal (sama seperti versi lama)
            $is_anomaly_batal = false;
            if ($is_batal_rs && $has_local_t3) {
                if ($row['status_mjkn'] !== 'Batal' || $row['statuskirim_batal'] !== 'Sudah') {
                    $is_anomaly_batal = true;
                }
            }
            if ($is_batal_rs && !$is_anomaly_batal) continue;

            $patients[] = [
                'no_rawat'         => $row['no_rawat'],
                'nobooking'        => $row['nobooking'] ?? '',
                'tgl_registrasi'   => $row['tgl_registrasi'],
                'nm_pasien'        => $row['nm_pasien'],
                'nm_dokter'        => $row['nm_dokter'],
                'Poliklinik'       => $row['Poliklinik'],
                'no_sep'           => $row['no_sep'] ?? '',
                'has_resep'        => (bool)$row['has_resep'],
                'is_anomaly_batal' => $is_anomaly_batal,
                'ket_batal'        => $row['ket_batal'] ?? '',
                'Jam_Registrasi'   => $row['Jam Registrasi'],
                'Jam_Checkin'      => $row['Jam Checkin'] ?? '',
                // Data lokal (untuk tab Raw/IT — hanya referensi SIMRS)
                'local' => [
                    'TID3' => $row['local_TID_3'] ?? '',
                    'TID4' => $row['local_TID_4'] ?? '',
                    'TID5' => $row['local_TID_5'] ?? '',
                    'TID6' => $row['local_TID_6'] ?? '',
                    'TID7' => $row['local_TID_7'] ?? '',
                ],
                'erm' => [
                    'TID3' => $row['Jam Checkin'] ?: $row['Jam Registrasi'],
                    'TID4' => $row['ERM_TID4'] ?? '',
                    'TID5' => $row['ERM_TID5'] ?? '',
                    'TID6' => $row['ERM_TID6'] ?? '',
                    'TID7' => $row['ERM_TID7'] ?? '',
                ],
            ];
        }
    }
}
?>

<style>
    .glass-card { background: rgba(255,255,255,0.95); border: 1px solid rgba(255,255,255,0.4); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    .scorecard { padding: 15px; border-radius: 12px; text-align: center; border: 1px solid #e9ecef; background: #fff; transition: transform 0.2s; }
    .scorecard:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .scorecard h5 { font-size: 0.8rem; color: #6c757d; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
    .scorecard h2 { font-size: 1.6rem; font-weight: 700; margin-bottom: 0; }
    .text-danger-score { color: #dc3545; }
    .text-success-score { color: #198754; }
    .table-container { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-top-left-radius: 0; }
    .view-toggle { display: inline-flex; background: #f8f9fa; border-radius: 20px; padding: 3px; border: 1px solid #dee2e6; }
    .view-toggle .btn { border-radius: 17px; padding: 4px 15px; font-size: 0.85rem; font-weight: 600; border: none; }
    .view-toggle .btn.active { background: #0d6efd; color: white; box-shadow: 0 2px 5px rgba(13,110,253,0.3); }
    .view-toggle .btn:not(.active) { color: #6c757d; }
    .nav-tabs .nav-link { font-size: 0.95rem; font-weight: 600; color: #6c757d; border-radius: 10px 10px 0 0; border: none; padding: 12px 20px; margin-right: 5px; }
    .nav-tabs .nav-link.active { background-color: #fff; border: 1px solid rgba(0,0,0,0.08); border-bottom-color: transparent; box-shadow: 0 -3px 10px rgba(0,0,0,0.03); }
    .nav-tabs { border-bottom: 2px solid rgba(0,0,0,0.05); }

    /* Progress bar loading BPJS */
    #bpjsLoadBar { transition: width 0.3s ease; }
    .bpjs-loading-row td { background: #fffbe6 !important; }
    .badge-bpjs { font-size: 0.68rem; }

    /* Indikator sumber data */
    .src-bpjs  { color: #0d6efd; font-size: 0.6rem; font-weight: 700; }
    .src-local { color: #fd7e14; font-size: 0.6rem; font-weight: 700; }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2"><i class="fas fa-mobile-alt text-success me-2"></i> Eksekutif Report: Antrean Online BPJS</h1>
    <form class="d-flex" method="GET" action="laporan_antrol_bpjs.php" onsubmit="document.getElementById('globalLoadingOverlay').style.display='flex';">
        <div class="input-group input-group-sm shadow-sm">
            <span class="input-group-text bg-white"><i class="fas fa-calendar-alt"></i></span>
            <input type="date" class="form-control" name="tgl1" value="<?php echo $tgl1; ?>" required>
            <span class="input-group-text bg-white">s.d</span>
            <input type="date" class="form-control" name="tgl2" value="<?php echo $tgl2; ?>" required>
            <button class="btn btn-success" type="submit"><i class="fas fa-filter me-1"></i> Render Data</button>
        </div>
    </form>
</div>

<!-- Progress BPJS Live Fetch -->
<div class="alert alert-info py-2 mb-3" id="bpjsProgressBox">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span><i class="fas fa-satellite-dish me-1"></i> <strong>Sedang mengambil data live dari server BPJS...</strong></span>
        <span id="bpjsProgressText" class="fw-bold">0 / <?php echo count($patients); ?></span>
    </div>
    <div class="progress" style="height: 6px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" id="bpjsLoadBar" style="width:0%"></div>
    </div>
    <div class="mt-1 small text-muted" id="bpjsProgressNote">Data ditampilkan bertahap saat setiap pasien selesai diambil dari API BPJS.</div>
</div>

<!-- SCORECARDS (diisi dinamis oleh JS) -->
<div class="row mb-4 g-3" id="scorecardRow">
    <div class="col-md-2 col-6">
        <div class="scorecard bg-light border-0">
            <h5>Pendaftaran BPJS</h5>
            <h2 class="text-dark" id="sc_total">-</h2>
            <small class="text-muted" id="sc_resep">Px dengan resep: -</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard border-top-0 border-end-0 border-bottom-0 border-4 border-primary">
            <h5>Task 3 (Admisi)</h5>
            <h2 id="sc_t3_pct">-</h2>
            <small class="text-muted" id="sc_t3_n">- terkirim</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard border-top-0 border-end-0 border-bottom-0 border-4 border-info">
            <h5>Task 4 (Layan Dok)</h5>
            <h2 id="sc_t4_pct">-</h2>
            <small class="text-muted" id="sc_t4_n">- terkirim</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard border-top-0 border-end-0 border-bottom-0 border-4 border-success">
            <h5>Task 5 (Usai Poli)</h5>
            <h2 id="sc_t5_pct">-</h2>
            <small class="text-muted" id="sc_t5_n">- terkirim</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard border-top-0 border-end-0 border-bottom-0 border-4 border-warning">
            <h5>Task 6 (Apotek Val)</h5>
            <h2 id="sc_t6_pct">-</h2>
            <small class="text-muted" id="sc_t6_n">- Resep</small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard border-top-0 border-end-0 border-bottom-0 border-4 border-danger">
            <h5>Task 7 (Ambil Obat)</h5>
            <h2 id="sc_t7_pct">-</h2>
            <small class="text-muted" id="sc_t7_n">- Resep</small>
        </div>
    </div>
</div>

<!-- CHARTS -->
<div class="row mb-4 g-4">
    <div class="col-lg-4">
        <div class="glass-card p-3 h-100 d-flex flex-column">
            <h6 class="text-muted fw-bold"><i class="fas fa-ring me-2 text-primary"></i> Journey Completion Status</h6>
            <p class="small text-muted mb-3">Persentase Rantai Bridging Utuh (Px Non-Obat lulus di T5, Px dengan Resep lulus di T7).</p>
            <div class="flex-grow-1 position-relative" style="min-height: 220px;">
                <canvas id="doughnutChart"></canvas>
            </div>
            <div class="text-center mt-3">
                <span class="badge bg-success me-2">Lengkap: <span id="leg_complete">0</span></span>
                <span class="badge bg-danger">Bocor: <span id="leg_bocor">0</span></span>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="glass-card p-3 h-100 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="text-muted fw-bold mb-0"><i class="fas fa-chart-bar me-2 text-success"></i> Analisis Tingkat Drop-Off</h6>
                    <small class="text-muted">Pantau di titik mana rekam bridging sering terputus.</small>
                </div>
                <div class="view-toggle">
                    <button class="btn active" id="btnBar" onclick="switchChart('bar')"><i class="fas fa-chart-column"></i> Bar</button>
                    <button class="btn" id="btnFunnel" onclick="switchChart('funnel')"><i class="fas fa-filter"></i> Funnel</button>
                </div>
            </div>
            <div class="flex-grow-1 position-relative" style="min-height: 250px;">
                <canvas id="pipelineChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TABS -->
<ul class="nav nav-tabs border-0 mt-5" id="antrolTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active text-success" id="matrix-tab" data-bs-toggle="tab" data-bs-target="#matrix-pane" type="button"><i class="fas fa-tasks me-2"></i>Matrix Keparipurnaan (Eksekutif)</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link text-primary" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw-pane" type="button"><i class="fas fa-code-branch me-2"></i>Data Timestamp & AI Bot (IT)</button>
    </li>
</ul>

<div class="tab-content" id="antrolTabsContent">
    <!-- TAB 1: Eksekutif -->
    <div class="tab-pane fade show active" id="matrix-pane" role="tabpanel" tabindex="0">
        <div class="table-container pt-4 border border-top-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-shield-alt text-success me-2"></i>Laporan Validasi Antrol Standard <span class="badge bg-info text-dark ms-2" style="font-size:0.7rem;"><i class="fas fa-satellite-dish"></i> Sumber: BPJS Live API</span></h5>
            </div>
            <div class="table-responsive">
                <table id="antrolTable" class="table table-striped table-hover align-middle w-100" style="font-size: 0.82rem;">
                    <thead class="table-dark">
                        <tr>
                            <th>Terdaftar (Tgl)</th>
                            <th>No Rawat / Nomer Antrean</th>
                            <th>Pasien / Dokter</th>
                            <th>Poli</th>
                            <th>[3] Admisi</th>
                            <th>[4] CPPT</th>
                            <th>[5] End Layan</th>
                            <th>[6] Val Obat</th>
                            <th>[7] Beri Obat</th>
                            <th class="text-center">Status Journey</th>
                        </tr>
                    </thead>
                    <tbody id="antrolTableBody">
                        <!-- Diisi oleh JS setelah BPJS live fetch -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: IT Raw -->
    <div class="tab-pane fade" id="raw-pane" role="tabpanel" tabindex="0">
        <div class="table-container pt-4 border border-top-0">
            <h5 class="fw-bold mb-3"><i class="fas fa-laptop-code text-primary me-2"></i>Analisis Timestamp (Bot IT)</h5>
            <div class="alert alert-info py-2 small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Kolom <b>ERM RS</b> = jejak dari SIMRS lokal. Kolom <b>BPJS Live</b> = data aktual dari server BPJS.
                <b>Orange Flag</b> = Bot menambal, <b>Red Flag</b> = SIMRS gagal bridging, <b>Blue Flag</b> = data hanya ada di BPJS (dikirim dari luar SIMRS).
            </div>
            <div class="table-responsive">
                <table id="rawTable" class="table table-bordered table-hover align-middle w-100" style="font-size: 0.82rem;">
                    <thead class="table-light">
                        <tr>
                            <th width="12%">No Rawat</th>
                            <th width="15%">Nama Pasien</th>
                            <th>Task 3 (Admisi)</th>
                            <th>Task 4 (Awal Medis)</th>
                            <th>Task 5 (Selesai Medis)</th>
                            <th>Task 6 (Farmasi Obat)</th>
                            <th>Task 7 (Penyerahan)</th>
                        </tr>
                    </thead>
                    <tbody id="rawTableBody">
                        <!-- Diisi oleh JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- JS: Semua logika live fetch + render dinamis                 -->
<!-- ============================================================ -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ============================================================
// Data pasien dari PHP (daftar) — Task ID BELUM ada di sini.
// Task ID akan diambil live dari BPJS via AJAX per-pasien.
// ============================================================
const PATIENTS = <?php echo json_encode($patients, JSON_UNESCAPED_UNICODE); ?>;
const TOTAL    = PATIENTS.length;
const TGL1     = '<?php echo $tgl1; ?>';
const TGL2     = '<?php echo $tgl2; ?>';

// Metrics (dihitung ulang seiring data masuk)
let metrics = {
    total: 0, resep: 0,
    t3: 0, t4: 0, t5: 0, t6: 0, t7: 0,
    complete: 0, incomplete: 0
};

let dtExec = null;  // DataTable instance (eksekutif)
let dtRaw  = null;  // DataTable instance (raw)
let doughnutChart = null;
let pipelineChartInst = null;

// ============================================================
// Utilitas
// ============================================================
function extractTime(dtStr) {
    // Ambil HH:mm:ss dari "YYYY-MM-DD HH:mm:ss" atau "HH:mm:ss"
    if (!dtStr || dtStr.trim() === '' || dtStr.trim() === '-') return null;
    const m = dtStr.match(/(\d{2}:\d{2}:\d{2})/);
    return m ? m[1] : null;
}

function fmtTime(t) { return t || '-'; }

function prc(sent, total) {
    if (total === 0) return '0.0';
    return ((sent / total) * 100).toFixed(1);
}

// ============================================================
// Build badge eksekutif dari waktu BPJS
// ============================================================
function buildBadgeExec(bpjsTime, taskid) {
    if (bpjsTime) {
        const t = extractTime(bpjsTime) || bpjsTime;
        return `<span class='badge bg-success badge-bpjs' title='BPJS Live [T${taskid}]: ${t}'><i class='fas fa-check'></i> ${t}</span>`;
    }
    return `<span class='badge bg-danger badge-bpjs'><i class='fas fa-times'></i> Gagal</span>`;
}

// ============================================================
// Build cell Raw/IT: bandingkan ERM vs BPJS
// ============================================================
function buildRawCell(erm, bpjs) {
    const erm_t  = extractTime(erm);
    const bpjs_t = bpjs ? (extractTime(bpjs) || bpjs) : null;

    const out_erm  = erm_t
        ? `<div class='small text-truncate' style='max-width:140px'><span class='text-secondary'>ERM RS:</span> <b>${erm_t}</b></div>`
        : '';
    const out_bpjs = bpjs_t
        ? `<div class='small text-truncate' style='max-width:140px'><span class='text-primary fw-bold'>BPJS:</span> ${bpjs_t}</div>`
        : '';

    let flags = '';

    // Orange: ERM kosong tapi BPJS ada (dikirim dari luar SIMRS / Bot tambal)
    if (!erm_t && bpjs_t) {
        flags += `<div class='mt-1'><span class='badge shadow-sm text-dark' style='background-color:#fd7e14;font-size:0.65rem;' title='Tidak ada jejak di SIMRS, tetapi Task ID sudah terkirim ke BPJS (via luar SIMRS / Bot)'><i class='fas fa-robot'></i> Dari Luar SIMRS</span></div>`;
    }
    // Red: ERM ada tapi BPJS kosong (gagal bridging)
    if (erm_t && !bpjs_t) {
        flags += `<div class='mt-1'><span class='badge bg-danger shadow-sm' style='font-size:0.65rem;' title='Aktivitas di SIMRS ada, namun tidak sampai ke server BPJS'><i class='fas fa-exclamation-triangle'></i> Gagal Bridging</span></div>`;
    }
    // Yellow: Keduanya ada, selisih > 20 menit
    if (erm_t && bpjs_t) {
        const et = new Date('1970-01-01T' + erm_t);
        const bt = new Date('1970-01-01T' + bpjs_t);
        const diff = (bt - et) / 60000;
        if (diff > 20) {
            flags += `<div class='mt-1'><span class='badge bg-warning text-dark shadow-sm' style='font-size:0.65rem;'>Selisih ${Math.round(diff)}m <i class='fas fa-robot'></i></span></div>`;
        }
    }

    if (!erm_t && !bpjs_t) return `<div class='text-muted small text-center w-100'>-</div>`;
    return out_erm + out_bpjs + flags;
}

// ============================================================
// Render satu baris ke tabel setelah data BPJS datang
// ============================================================
function renderRow(p, bpjsTasks) {
    // bpjsTasks: array dari API BPJS: [{taskid, taskname, waktu, wakturs}, ...]
    // Kita gunakan sebagai sumber kebenaran.

    // Buat map taskid -> waktu (BPJS server time)
    const bpjsMap = {};
    (bpjsTasks || []).forEach(t => {
        bpjsMap[String(t.taskid)] = t.waktu || t.wakturs || '';
    });

    const t3 = bpjsMap['3'] || null;
    const t4 = bpjsMap['4'] || null;
    const t5 = bpjsMap['5'] || null;
    const t6 = bpjsMap['6'] || null;
    const t7 = bpjsMap['7'] || null;

    // has_resep: cek dari ERM lokal ATAU dari keberadaan T6/T7 di BPJS
    const has_resep = p.has_resep || !!t6 || !!t7;

    // Update metrics
    if (!p.is_anomaly_batal) {
        metrics.total++;
        if (has_resep) metrics.resep++;
        if (t3) metrics.t3++;
        if (t4) metrics.t4++;
        if (t5) metrics.t5++;
        if (has_resep && t6) metrics.t6++;
        if (has_resep && t7) metrics.t7++;

        const complete = has_resep
            ? (t3 && t4 && t5 && t6 && t7)
            : (t3 && t4 && t5);

        if (complete) metrics.complete++; else metrics.incomplete++;
    }

    // Badge eksekutif
    const b3 = buildBadgeExec(t3, 3);
    const b4 = buildBadgeExec(t4, 4);
    const b5 = buildBadgeExec(t5, 5);
    const b6 = has_resep ? buildBadgeExec(t6, 6) : "<span class='badge bg-secondary badge-bpjs'>N/A</span>";
    const b7 = has_resep ? buildBadgeExec(t7, 7) : "<span class='badge bg-secondary badge-bpjs'>N/A</span>";

    // Journey badge
    let journey_badge;
    if (p.is_anomaly_batal) {
        const ket = p.ket_batal || 'Task 99 BPJS belum terkirim';
        journey_badge = `<div class='badge bg-danger shadow-sm text-wrap' style='max-width:140px;font-size:0.75rem;'><i class='fas fa-bomb'></i> ANOMALI BATAL:<br><small class='fw-normal'>${ket}</small></div>`;
    } else {
        const complete = has_resep ? (t3&&t4&&t5&&t6&&t7) : (t3&&t4&&t5);
        journey_badge = complete
            ? "<span class='badge bg-primary'><i class='fas fa-certificate'></i> LENGKAP</span>"
            : "<span class='badge bg-dark'><i class='fas fa-exclamation-triangle'></i> BOCOR</span>";
    }

    // Sub-badges
    let mjkn_badge = p.nobooking
        ? `<br><span class='badge shadow-sm mt-1' style='background-color:#0dcaf0;color:#000;font-size:0.70rem;'><i class='fas fa-mobile-alt me-1'></i> MJKN: ${p.nobooking}</span>` : '';
    if (p.no_sep)
        mjkn_badge += `<br><span class='badge mt-1 text-dark shadow-sm' style='background-color:#ffc107;font-size:0.70rem;'><i class='fas fa-file-medical-alt me-1'></i> SEP: ${p.no_sep}</span>`;
    if (p.is_anomaly_batal)
        mjkn_badge += `<br><span class='badge bg-danger mt-1' style='font-size:0.70rem;'><i class='fas fa-exclamation-triangle'></i> Anomali Batal</span>`;

    const execRow = [
        p.tgl_registrasi,
        `<strong>${p.no_rawat}</strong>${mjkn_badge}`,
        `${p.nm_pasien}<br><small class='text-muted'>${p.nm_dokter}</small>`,
        p.Poliklinik,
        b3, b4, b5, b6, b7,
        journey_badge
    ];

    // Raw/IT row — bandingkan ERM vs BPJS
    const c3 = buildRawCell(p.erm.TID3, t3);
    const c4 = buildRawCell(p.erm.TID4, t4);
    const c5 = buildRawCell(p.erm.TID5, t5);
    const c6 = buildRawCell(p.erm.TID6, t6);
    const c7 = buildRawCell(p.erm.TID7, t7);

    const rawRow = [
        `<strong>${p.no_rawat}</strong>${mjkn_badge}`,
        p.nm_pasien,
        c3, c4, c5, c6, c7
    ];

    return { execRow, rawRow };
}

// ============================================================
// Update semua widget scorecard + chart (dipanggil berkala)
// ============================================================
function updateWidgets() {
    // Scorecard teks
    const pct = (n, d) => d === 0 ? '0.0%' : ((n/d)*100).toFixed(1) + '%';
    const cls = (v) => parseFloat(v) < 90 ? 'text-danger-score' : 'text-success-score';

    $('#sc_total').text(metrics.total);
    $('#sc_resep').text('Px dengan resep: ' + metrics.resep);

    ['t3','t4','t5'].forEach(k => {
        const val = pct(metrics[k], metrics.total);
        $(`#sc_${k}_pct`).text(val).attr('class', cls(val));
        $(`#sc_${k}_n`).text(metrics[k] + ' terkirim');
    });
    ['t6','t7'].forEach(k => {
        const val = pct(metrics[k], metrics.resep);
        $(`#sc_${k}_pct`).text(val).attr('class', cls(val));
        $(`#sc_${k}_n`).text('dari ' + metrics.resep + ' Resep');
    });

    // Legend doughnut
    $('#leg_complete').text(metrics.complete);
    $('#leg_bocor').text(metrics.incomplete);

    // Update doughnut data
    if (doughnutChart) {
        doughnutChart.data.datasets[0].data = [metrics.complete, metrics.incomplete];
        doughnutChart.update();
    }

    // Update pipeline/bar data
    if (pipelineChartInst) {
        pipelineChartInst.data.datasets[0].data = [metrics.t3, metrics.t4, metrics.t5, metrics.t6, metrics.t7];
        pipelineChartInst.update();
    }
}

// ============================================================
// Inisialisasi DataTables (kosong dulu)
// ============================================================
function initDataTables() {
    dtExec = $('#antrolTable').DataTable({
        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [{
            extend: 'excelHtml5',
            text: '<i class="fas fa-file-excel me-1"></i> Export Eksekutif',
            className: 'btn btn-success btn-sm',
            title: `Matriks Data Kepatuhan Task ID BPJS (${TGL1} sd ${TGL2})`
        }],
        pageLength: 25,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
        columns: [
            {}, {}, {}, {},
            {}, {}, {}, {}, {},
            { className: 'text-center' }
        ]
    });

    dtRaw = $('#rawTable').DataTable({
        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [{
            extend: 'excelHtml5',
            text: '<i class="fas fa-file-excel me-1"></i> Export Data Analisis Bot',
            className: 'btn btn-primary btn-sm',
            title: `Raw Data Timestamp ERM vs BPJS Bot Analytics (${TGL1} sd ${TGL2})`
        }],
        pageLength: 25,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
    });
}

// ============================================================
// Inisialisasi Chart
// ============================================================
function initCharts() {
    const doughnutCtx = document.getElementById('doughnutChart').getContext('2d');
    doughnutChart = new Chart(doughnutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Rantai Penuh (Komplit)', 'Bocor (Incomplete Journey)'],
            datasets: [{
                data: [0, 0],
                backgroundColor: ['#198754', '#dc3545'],
                borderWidth: 2, borderColor: '#fff'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
    renderBarChart();
}

let pipelineChartMode = 'bar';
const ctxPipeline = document.getElementById('pipelineChart').getContext('2d');

function renderBarChart() {
    if (pipelineChartInst) pipelineChartInst.destroy();
    pipelineChartInst = new Chart(ctxPipeline, {
        type: 'bar',
        data: {
            labels: ['Task 3 (Admisi)', 'Task 4 (CPPT Dokter)', 'Task 5 (Selesai Poli)', 'Task 6 (Apotek Val)', 'Task 7 (Penyerahan Obat)'],
            datasets: [{
                label: 'Volume Berhasil Terkirim ke BPJS',
                data: [0, 0, 0, 0, 0],
                backgroundColor: ['#0d6efd', '#0dcaf0', '#198754', '#ffc107', '#dc3545'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        } 
    });
}

function renderFunnelChart() {
    if (pipelineChartInst) pipelineChartInst.destroy();
    pipelineChartInst = new Chart(ctxPipeline, {
        type: 'bar',
        data: {
            labels: ['Task 3 (Masuk)', 'Task 4 (Lolos)', 'Task 5 (Lolos)', 'Task 6 (Sub-Farmasi)', 'Task 7 (Sub-Farmasi)'],
            datasets: [{
                axis: 'y',
                label: 'Volume Melewati Titik',
                data: [metrics.t3, metrics.t4, metrics.t5, metrics.t6, metrics.t7],
                backgroundColor: 'rgba(25,135,84,0.7)',
                borderColor: '#198754', borderWidth: 1, borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } }
        }
    });
}

function switchChart(type) {
    $('.view-toggle .btn').removeClass('active');
    if (type === 'bar') { $('#btnBar').addClass('active'); renderBarChart(); }
    else                { $('#btnFunnel').addClass('active'); renderFunnelChart(); }
    pipelineChartMode = type;
}

// ============================================================
// Main: Live fetch per-pasien secara berurutan (seperti Java)
// ============================================================
async function fetchAllBPJS() {
    if (TOTAL === 0) {
        $('#bpjsProgressBox').html('<span class="text-muted"><i class="fas fa-info-circle"></i> Tidak ada data pasien pada rentang tanggal ini.</span>');
        return;
    }

    initDataTables();
    initCharts();

    let done = 0;

    for (const p of PATIENTS) {
        // Tampilkan placeholder row "loading" di tabel
        const placeholderId = 'row_' + p.no_rawat.replace(/\//g, '_').replace(/ /g, '_');

        dtExec.row.add([
            p.tgl_registrasi,
            `<strong>${p.no_rawat}</strong>`,
            `${p.nm_pasien}<br><small class='text-muted'>${p.nm_dokter}</small>`,
            p.Poliklinik,
            `<span class='spinner-border spinner-border-sm text-secondary' style='width:12px;height:12px'></span>`,
            `<span class='text-muted small'>...</span>`,
            `<span class='text-muted small'>...</span>`,
            `<span class='text-muted small'>...</span>`,
            `<span class='text-muted small'>...</span>`,
            `<span class='badge bg-secondary'>Loading...</span>`
        ]).draw(false);

        // Fetch BPJS live
        let bpjsTasks = [];
        try {
            const qs = new URLSearchParams({
                no_rawat:  p.no_rawat,
                nobooking: p.nobooking || ''
            });
            const resp = await fetch('api/api_bpjs_taskid.php?' + qs.toString());
            if (resp.ok) {
                const json = await resp.json();
                if (json.success) bpjsTasks = json.tasks || [];
            }
        } catch (e) {
            console.warn('Fetch error for', p.no_rawat, e);
        }

        // Render baris dengan data BPJS
        const { execRow, rawRow } = renderRow(p, bpjsTasks);

        // Update baris terakhir yang baru ditambahkan (placeholder) dengan data real
        // Karena DataTables tidak punya row-by-id mudah, kita hapus row terakhir dan tambah yang benar
        const allRows = dtExec.rows().nodes();
        const lastRow = allRows[allRows.length - 1];
        const rowIdx  = dtExec.row(lastRow).index();
        dtExec.row(rowIdx).data(execRow).draw(false);

        // Raw table
        dtRaw.row.add(rawRow).draw(false);

        done++;

        // Update progress bar
        const pct = Math.round((done / TOTAL) * 100);
        $('#bpjsLoadBar').css('width', pct + '%');
        $('#bpjsProgressText').text(done + ' / ' + TOTAL);

        // Update widget scorecard & chart bertahap
        updateWidgets();
    }

    // Selesai
    $('#bpjsProgressBox').removeClass('alert-info').addClass('alert-success')
        .html(`<i class='fas fa-check-circle me-1'></i> <strong>Selesai!</strong> ${TOTAL} pasien berhasil diverifikasi langsung dari server BPJS.`);
}

// ============================================================
// Jalankan saat DOM siap
// ============================================================
$(document).ready(function () {
    fetchAllBPJS();
});
</script>

<?php include 'includes/footer.php'; ?>