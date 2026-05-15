<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$page_title = "Pengiriman Data Satu Sehat";
include 'includes/header.php';

$tgl1 = isset($_GET['tgl1']) ? $_GET['tgl1'] : date('Y-m-d');
$tgl2 = isset($_GET['tgl2']) ? $_GET['tgl2'] : date('Y-m-d');

// Persiapan Variabel Metrik
$metrics = [
    'encounter' => ['target' => 0, 'sent' => 0],
    'diagnosa'  => ['target' => 0, 'sent' => 0],
    'ttv'       => ['target' => 0, 'sent' => 0],
    'resep'     => ['target' => 0, 'sent' => 0],
    'lab'       => ['target' => 0, 'sent' => 0],
    'rad'       => ['target' => 0, 'sent' => 0]
];

$trend = [];
$tableRows = "";

// Heavy Left Join Query
$sql = "SELECT 
            rp.no_rawat, 
            rp.tgl_registrasi, 
            p.nm_pasien, 
            p.no_ktp AS ktp_pasien, 
            pg.nama AS nm_dokter, 
            pg.no_ktp AS ktp_dokter, 
            poli.nm_poli,
            
            (SELECT sse.id_encounter FROM satu_sehat_encounter sse WHERE sse.no_rawat = rp.no_rawat LIMIT 1) AS id_encounter,
            
            (SELECT COUNT(dp.no_rawat) FROM diagnosa_pasien dp WHERE dp.no_rawat = rp.no_rawat) AS total_diagnosa,
            (SELECT COUNT(sc.no_rawat) FROM satu_sehat_condition sc WHERE sc.no_rawat = rp.no_rawat) AS diagnosa_sent,
            
            (SELECT COUNT(pr.no_rawat) FROM pemeriksaan_ralan pr WHERE pr.no_rawat = rp.no_rawat) + 
            (SELECT COUNT(pra.no_rawat) FROM pemeriksaan_ranap pra WHERE pra.no_rawat = rp.no_rawat) AS total_ttv,
            (SELECT COUNT(so.no_rawat) FROM satu_sehat_observationttvsuhu so WHERE so.no_rawat = rp.no_rawat) AS ttv_sent,
            
            (SELECT COUNT(ro.no_resep) FROM resep_obat ro WHERE ro.no_rawat = rp.no_rawat) AS total_resep,
            (SELECT COUNT(mr.no_resep) FROM satu_sehat_medicationrequest mr INNER JOIN resep_obat ro ON mr.no_resep=ro.no_resep WHERE ro.no_rawat = rp.no_rawat) AS resep_sent,
            
            (SELECT COUNT(pl.noorder) FROM permintaan_lab pl WHERE pl.no_rawat = rp.no_rawat) AS total_lab,
            (SELECT COUNT(sl.noorder) FROM satu_sehat_servicerequest_lab sl INNER JOIN permintaan_lab pl ON sl.noorder=pl.noorder WHERE pl.no_rawat = rp.no_rawat) AS lab_sent,
            
            (SELECT COUNT(prad.noorder) FROM permintaan_radiologi prad WHERE prad.no_rawat = rp.no_rawat) AS total_rad,
            (SELECT COUNT(sr.noorder) FROM satu_sehat_servicerequest_radiologi sr INNER JOIN permintaan_radiologi prad ON sr.noorder=prad.noorder WHERE prad.no_rawat = rp.no_rawat) AS rad_sent

        FROM reg_periksa rp
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        INNER JOIN pegawai pg ON rp.kd_dokter = pg.nik
        INNER JOIN poliklinik poli ON rp.kd_poli = poli.kd_poli
        WHERE rp.tgl_registrasi BETWEEN ? AND ? AND rp.stts <> 'Batal' AND rp.status_bayar='Sudah Bayar'";

if ($stmt = $koneksi->prepare($sql)) {
    $stmt->bind_param("ss", $tgl1, $tgl2);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tgl = $row['tgl_registrasi'];
            if(!isset($trend[$tgl])) {
                $trend[$tgl] = ['target' => 0, 'sent' => 0];
            }
            
            // --- Metric Calc --- //
            $metrics['encounter']['target']++;
            $trend[$tgl]['target']++;
            $is_anomaly = false;
            $status_badges = [];

            if (!empty($row['id_encounter'])) {
                $metrics['encounter']['sent']++;
                $trend[$tgl]['sent']++;
            } else {
                $is_anomaly = true;
                $status_badges[] = "<a href='#' class='badge bg-danger text-decoration-none shadow-sm erm-detail-btn' data-norawat='{$row['no_rawat']}' data-modul='Encounter' title='Inspeksi RAW ERM'><i class='fas fa-search me-1'></i>Encounter</a>";
            }

            if ($row['total_diagnosa'] > 0) {
                $metrics['diagnosa']['target']++;
                $trend[$tgl]['target']++;
                if ($row['diagnosa_sent'] > 0) {
                    $metrics['diagnosa']['sent']++;
                    $trend[$tgl]['sent']++;
                } else {
                    $is_anomaly = true;
                    $status_badges[] = "<a href='#' class='badge bg-warning text-dark text-decoration-none shadow-sm erm-detail-btn' data-norawat='{$row['no_rawat']}' data-modul='Diagnosa' title='Inspeksi ICD10'><i class='fas fa-search me-1'></i>Diagnosa</a>";
                }
            }

            if ($row['total_ttv'] > 0) {
                $metrics['ttv']['target']++;
                $trend[$tgl]['target']++;
                if ($row['ttv_sent'] > 0) {
                    $metrics['ttv']['sent']++;
                    $trend[$tgl]['sent']++;
                } else {
                    $is_anomaly = true;
                    $status_badges[] = "<a href='#' class='badge bg-warning text-dark text-decoration-none shadow-sm erm-detail-btn' data-norawat='{$row['no_rawat']}' data-modul='TTV' title='Inspeksi TTV'><i class='fas fa-search me-1'></i>TTV</a>";
                }
            }

            if ($row['total_resep'] > 0) {
                $metrics['resep']['target']++;
                $trend[$tgl]['target']++;
                if ($row['resep_sent'] > 0) {
                    $metrics['resep']['sent']++;
                    $trend[$tgl]['sent']++;
                } else {
                    $is_anomaly = true;
                    $status_badges[] = "<a href='#' class='badge bg-warning text-dark text-decoration-none shadow-sm erm-detail-btn' data-norawat='{$row['no_rawat']}' data-modul='Resep' title='Inspeksi Resep Obat'><i class='fas fa-search me-1'></i>Resep Obat</a>";
                }
            }

            if ($row['total_lab'] > 0) {
                $metrics['lab']['target']++;
                $trend[$tgl]['target']++;
                if ($row['lab_sent'] > 0) {
                    $metrics['lab']['sent']++;
                    $trend[$tgl]['sent']++;
                } else {
                    $is_anomaly = true;
                    $status_badges[] = "<a href='#' class='badge bg-warning text-dark text-decoration-none shadow-sm erm-detail-btn' data-norawat='{$row['no_rawat']}' data-modul='Lab' title='Inspeksi Order Lab PK'><i class='fas fa-search me-1'></i>Lab PK</a>";
                }
            }

            if ($row['total_rad'] > 0) {
                $metrics['rad']['target']++;
                $trend[$tgl]['target']++;
                if ($row['rad_sent'] > 0) {
                    $metrics['rad']['sent']++;
                    $trend[$tgl]['sent']++;
                } else {
                    $is_anomaly = true;
                    $status_badges[] = "<a href='#' class='badge bg-warning text-dark text-decoration-none shadow-sm erm-detail-btn' data-norawat='{$row['no_rawat']}' data-modul='Radiologi' title='Inspeksi Radiologi'><i class='fas fa-search me-1'></i>Radiologi</a>";
                }
            }

            // Validasi KTP NIK
            $ktp_p = $row['ktp_pasien'];
            $ktp_d = $row['ktp_dokter'];
            
            $valid_p = (strlen($ktp_p) === 16 && is_numeric($ktp_p));
            $valid_d = (strlen($ktp_d) === 16 && is_numeric($ktp_d));

            // Mark NIK Invalid as warning in labels
            $lbl_p = !$valid_p ? "<span class='text-danger fw-bold' title='Format KTP tidak valid (bukan 16 digit angka)'>".($ktp_p==''?'-KOSONG-':$ktp_p)." <i class='fas fa-exclamation-triangle'></i></span>" : htmlspecialchars($ktp_p);
            $lbl_d = !$valid_d ? "<span class='text-danger fw-bold' title='Format KTP tidak valid (bukan 16 digit angka)'>".($ktp_d==''?'-KOSONG-':$ktp_d)." <i class='fas fa-exclamation-triangle'></i></span>" : htmlspecialchars($ktp_d);

            if(!$valid_p || !$valid_d) {
                $is_anomaly = true;
            }

            $status_html = empty($status_badges) ? "<span class='badge bg-success'><i class='fas fa-check'></i> Tuntas</span>" : implode(" ", $status_badges);

            // Row rendering
            $tableRows .= "<tr>
                <td>{$row['tgl_registrasi']}</td>
                <td>{$row['no_rawat']}</td>
                <td>{$row['nm_pasien']} <br><small>NIK: $lbl_p</small></td>
                <td>{$row['nm_dokter']} <br><small>NIK: $lbl_d</small></td>
                <td>{$row['nm_poli']}</td>
                <td>{$status_html}</td>
            </tr>";
        }
    }
}

// Persiapan Variabel untuk Chart.js
$chartData = [
    'labels' => [],
    'encounter' => [],
    'diagnosa' => [],
    'ttv' => [],
    'resep' => [],
    'lab' => [],
    'rad' => []
];

// Calculate Percentages
function calcPct($sent, $target) {
    if ($target == 0) return 0;
    return round(($sent / $target) * 100, 1);
}

$pctEncounter = calcPct($metrics['encounter']['sent'], $metrics['encounter']['target']);
$pctDiagnosa  = calcPct($metrics['diagnosa']['sent'], $metrics['diagnosa']['target']);
$pctTTV       = calcPct($metrics['ttv']['sent'], $metrics['ttv']['target']);
$pctResep     = calcPct($metrics['resep']['sent'], $metrics['resep']['target']);
$pctLab       = calcPct($metrics['lab']['sent'], $metrics['lab']['target']);
$pctRad       = calcPct($metrics['rad']['sent'], $metrics['rad']['target']);

// Trend Labels
ksort($trend);
$trendLabels = array_keys($trend);
$trendPct = [];
foreach ($trendLabels as $dt) {
    $trendPct[] = calcPct($trend[$dt]['sent'], $trend[$dt]['target']);
}

?>

<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        border: 1px solid rgba(255,255,255,0.4);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    }
    .scorecard {
        padding: 15px;
        border-radius: 12px;
        text-align: center;
        border: 1px solid #e9ecef;
        background: #fff;
        transition: transform 0.2s;
    }
    .scorecard:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .scorecard h5 { font-size: 0.9rem; color: #6c757d; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
    .scorecard h2 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0px; }
    .text-danger-score { color: #dc3545; }
    .text-success-score { color: #198754; }
    
    .table-container { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2"><i class="fas fa-satellite-dish text-primary me-2"></i> Kepatuhan Bridging Satu Sehat</h1>
    <form class="d-flex" method="GET" action="laporan_satu_sehat.php" onsubmit="document.getElementById('globalLoadingOverlay').style.display='flex';">
        <div class="input-group input-group-sm shadow-sm">
            <span class="input-group-text bg-white"><i class="fas fa-calendar-alt"></i></span>
            <input type="date" class="form-control" name="tgl1" value="<?php echo $tgl1; ?>" required>
            <span class="input-group-text bg-white">s.d</span>
            <input type="date" class="form-control" name="tgl2" value="<?php echo $tgl2; ?>" required>
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter me-1"></i> Filter Data</button>
        </div>
    </form>
</div>

<!-- TOP SCORECARDS -->
<div class="row mb-4 g-3">
    <div class="col-md-2 col-6">
        <div class="scorecard">
            <h5>Encounter</h5>
            <h2 class="<?php echo ($pctEncounter < 90) ? 'text-danger-score' : 'text-success-score'; ?>"><?php echo $pctEncounter; ?>%</h2>
            <small class="text-muted"><?php echo number_format($metrics['encounter']['sent'],0,',','.'); ?> / <?php echo number_format($metrics['encounter']['target'],0,',','.'); ?></small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard">
            <h5>Diagnosa</h5>
            <h2 class="<?php echo ($pctDiagnosa < 90) ? 'text-danger-score' : 'text-success-score'; ?>"><?php echo $pctDiagnosa; ?>%</h2>
            <small class="text-muted"><?php echo number_format($metrics['diagnosa']['sent'],0,',','.'); ?> / <?php echo number_format($metrics['diagnosa']['target'],0,',','.'); ?></small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard">
            <h5>TTV Observ.</h5>
            <h2 class="<?php echo ($pctTTV < 90) ? 'text-danger-score' : 'text-success-score'; ?>"><?php echo $pctTTV; ?>%</h2>
            <small class="text-muted"><?php echo number_format($metrics['ttv']['sent'],0,',','.'); ?> / <?php echo number_format($metrics['ttv']['target'],0,',','.'); ?></small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard">
            <h5>Resep Obat</h5>
            <h2 class="<?php echo ($pctResep < 90) ? 'text-danger-score' : 'text-success-score'; ?>"><?php echo $pctResep; ?>%</h2>
            <small class="text-muted"><?php echo number_format($metrics['resep']['sent'],0,',','.'); ?> / <?php echo number_format($metrics['resep']['target'],0,',','.'); ?></small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard">
            <h5>Lab PK</h5>
            <h2 class="<?php echo ($pctLab < 90) ? 'text-danger-score' : 'text-success-score'; ?>"><?php echo $pctLab; ?>%</h2>
            <small class="text-muted"><?php echo number_format($metrics['lab']['sent'],0,',','.'); ?> / <?php echo number_format($metrics['lab']['target'],0,',','.'); ?></small>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="scorecard">
            <h5>Radiologi</h5>
            <h2 class="<?php echo ($pctRad < 90) ? 'text-danger-score' : 'text-success-score'; ?>"><?php echo $pctRad; ?>%</h2>
            <small class="text-muted"><?php echo number_format($metrics['rad']['sent'],0,',','.'); ?> / <?php echo number_format($metrics['rad']['target'],0,',','.'); ?></small>
        </div>
    </div>
</div>

<!-- CHARTS SECTION -->
<div class="row mb-4 g-4">
    <div class="col-lg-8">
        <div class="glass-card p-3 h-100">
            <h6 class="text-muted fw-bold mb-3"><i class="fas fa-chart-line me-2"></i>Trend Keberhasilan Keseluruhan Harian</h6>
            <canvas id="trendChart" height="90"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="glass-card p-3 h-100 d-flex flex-column align-items-center justify-content-center">
            <h6 class="text-muted fw-bold mb-3 w-100"><i class="fas fa-chart-pie me-2"></i>Total Komparasi</h6>
            <div style="height: 200px; width: 100%;">
                <canvas id="doughnutChart"></canvas>
            </div>
            <small class="text-muted mt-2 text-center">Menyandingkan seluruh entitas target vs sukses terkirim.</small>
        </div>
    </div>
</div>

<!-- DATA TABLE ANOMALY -->
<div class="table-container mb-5">
    <h5 class="fw-bold mb-3"><i class="fas fa-table text-primary me-2"></i>Data Pasien & Status Pengiriman (Detail)</h5>
    <div class="alert alert-info py-2 small">
        <i class="fas fa-info-circle me-1"></i> Data dalam tabel di bawah ini menyoroti nomor KTP (sebagai validasi utama Kemenkes) dan pilar modul yang belum terkirim. Anda dapat melakukan klik pada <strong>Modul Gagal Dikirim (contoh: Encounter, TTV)</strong> untuk melihat rincian raw data Elektronik Rekam Medis (ERM). Gunakan fitur <strong>Export Excel</strong> untuk analisis mendalam atau rekonsiliasi.
    </div>
    <div class="table-responsive">
        <table id="satuSehatTable" class="table table-striped table-hover align-middle w-100" style="font-size: 0.85rem;">
            <thead class="table-dark">
                <tr>
                    <th>Tanggal</th>
                    <th>No. Rawat</th>
                    <th>Pasien & NIK</th>
                    <th>Dokter PPA & NIK</th>
                    <th>Poliklinik/Bangsal</th>
                    <th>Modul Gagal Dikirim (Klik Untuk Cek RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php echo $tableRows; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL Cek ERM -->
<div class="modal fade" id="modalErmDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-laptop-medical me-2"></i> Inspeksi Modul ERM <span id="ermDetailTitle"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ermDetailBody">
                <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Mengambil raw data rincian dari server Khanza...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    // Inisialisasi DataTables dengan tombol Export Excel
    $('#satuSehatTable').DataTable({
        dom: '<"row mb-3"<"col-md-6"B><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i> Export ke Excel',
                className: 'btn btn-success btn-sm',
                title: 'Data Validasi Satu Sehat (<?php echo $tgl1; ?> sd <?php echo $tgl2; ?>)'
            }
        ],
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        }
    });

    // Handle klik pada badge anomali ERM
    $(document).on('click', '.erm-detail-btn', function(e) {
        e.preventDefault();
        let noRawat = $(this).data('norawat');
        let modul = $(this).data('modul');
        
        $('#ermDetailTitle').text('- ' + modul);
        $('#ermDetailBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Membedah record raw tabel RM ('+modul+') milik ' + noRawat + '...</p></div>');
        $('#modalErmDetail').modal('show');
        
        $.ajax({
            url: 'api/get_erm_satu_sehat.php',
            type: 'GET',
            data: { no_rawat: noRawat, modul: modul },
            success: function(response) {
                $('#ermDetailBody').html(response);
            },
            error: function() {
                $('#ermDetailBody').html('<div class="alert alert-danger"><i class="fas fa-times-circle"></i> Koneksi ke backend ERM gagal atau terjadi kesalahan server.</div>');
            }
        });
    });

    // Chart.js Data Init
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const doughnutCtx = document.getElementById('doughnutChart').getContext('2d');

    const totalTarget = <?php echo array_sum(array_column($metrics, 'target')); ?>;
    const totalSent = <?php echo array_sum(array_column($metrics, 'sent')); ?>;
    const totalFailed = totalTarget - totalSent;

    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [{
                label: 'Success Rate (%)',
                data: <?php echo json_encode($trendPct); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, max: 100 }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    new Chart(doughnutCtx, {
        type: 'doughnut',
        data: {
            labels: ['Berhasil Kirim', 'Gagal/Menunggu Pengecekan RM'],
            datasets: [{
                data: [totalSent, totalFailed],
                backgroundColor: ['#198754', '#dc3545'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
