<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$page_title = "Laporan Absensi & Kedisiplinan";
include 'includes/header.php';
?>

<style>
    /* Premium Glassmorphism & UI Tuning */
    :root {
        --glass-bg: rgba(255, 255, 255, 0.85);
        --glass-border: rgba(255, 255, 255, 0.4);
        --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
        --gradient-primary: linear-gradient(135deg, #0d6efd, #0dcaf0);
        --gradient-danger: linear-gradient(135deg, #dc3545, #fd7e14);
        --gradient-warning: linear-gradient(135deg, #ffc107, #ffecd2);
        --gradient-success: linear-gradient(135deg, #198754, #20c997);
    }
    
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        box-shadow: var(--glass-shadow);
        border-radius: 16px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .glass-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(31, 38, 135, 0.15);
    }

    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 600;
        padding: 12px 24px;
        border-radius: 12px 12px 0 0;
        transition: all 0.3s ease;
        position: relative;
    }
    .nav-tabs .nav-link::after {
        content: '';
        position: absolute;
        bottom: 0; left: 50%;
        transform: translateX(-50%);
        width: 0; height: 3px;
        background: var(--gradient-primary);
        transition: width 0.3s ease;
    }
    .nav-tabs .nav-link:hover { color: #0d6efd; background: rgba(13, 110, 253, 0.05); }
    .nav-tabs .nav-link.active {
        color: #0d6efd;
        background: transparent;
        border: none;
    }
    .nav-tabs .nav-link.active::after { width: 100%; }

    .stat-box {
        padding: 20px;
        border-radius: 16px;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .stat-box i {
        position: absolute;
        right: -10px; bottom: -15px;
        font-size: 5rem;
        opacity: 0.2;
    }
    .stat-box h3 { font-size: 2rem; font-weight: 800; margin-bottom: 5px; }
    .stat-box p { font-size: 0.9rem; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 1px; }

    .bg-gradient-primary { background: var(--gradient-primary); }
    .bg-gradient-danger { background: var(--gradient-danger); }
    .bg-gradient-warning { background: var(--gradient-warning); color: #333 !important; }
    .bg-gradient-success { background: var(--gradient-success); }
    
    .table-glass { background: transparent; }
    .table-glass thead th {
        background: rgba(13, 110, 253, 0.08);
        color: #495057;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid rgba(13, 110, 253, 0.2);
    }
    .table-glass tbody tr { transition: background 0.2s; }
    .table-glass tbody tr:hover { background: rgba(13, 110, 253, 0.04); }

    .filter-panel { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; }
    
    .chart-container { position: relative; height: 350px; width: 100%; }

    .badge-soft-danger { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
    .badge-soft-success { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
    .badge-soft-warning { background-color: rgba(255, 193, 7, 0.2); color: #d39e00; }
    .badge-soft-info { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2"><i class="fas fa-user-clock text-primary me-2"></i> Laporan Absensi & Kedisiplinan</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <!-- Setting Denda dihapus sesuai request untuk menyederhanakan konfigurasi -->
    </div>
</div>

<!-- FILTER PANEL -->
<div class="filter-panel d-flex flex-wrap gap-3 align-items-end">
    <div class="flex-grow-1" style="min-width: 200px;">
        <label class="form-label small fw-bold text-muted text-uppercase">Tgl Awal</label>
        <input type="date" id="tgl1" class="form-control" value="<?php echo date('Y-m-01'); ?>">
    </div>
    <div class="flex-grow-1" style="min-width: 200px;">
        <label class="form-label small fw-bold text-muted text-uppercase">Tgl Akhir</label>
        <input type="date" id="tgl2" class="form-control" value="<?php echo date('Y-m-d'); ?>">
    </div>
    <div class="flex-grow-1" style="min-width: 250px;">
        <label class="form-label small fw-bold text-muted text-uppercase">Departemen</label>
        <select id="dep" class="form-select">
            <option value="ALL">Semua Departemen</option>
        </select>
    </div>
    <div>
        <button class="btn btn-primary px-4 fw-bold shadow-sm" onclick="loadAllData()">
            <i class="fas fa-search me-2"></i> Analisa Data
        </button>
    </div>
</div>

<!-- TABS -->
<ul class="nav nav-tabs mb-4 border-0" id="absensiTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="evaluasi-tab" data-bs-toggle="tab" data-bs-target="#evaluasi" type="button" role="tab"><i class="fas fa-clipboard-check me-2"></i> Evaluasi Kehadiran</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="potongan-tab" data-bs-toggle="tab" data-bs-target="#potongan" type="button" role="tab"><i class="fas fa-exclamation-circle me-2"></i> Rekap Pelanggaran</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="analitik-tab" data-bs-toggle="tab" data-bs-target="#analitik" type="button" role="tab"><i class="fas fa-chart-pie me-2"></i> Indikator Kedisiplinan</button>
    </li>
</ul>

<div class="tab-content" id="absensiTabContent">
    
    <!-- TAB 1: EVALUASI KETIDAKHADIRAN -->
    <div class="tab-pane fade show active" id="evaluasi" role="tabpanel">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="fw-bold m-0 text-primary">Data Rekap Kehadiran vs Jadwal (Realtime)</h5>
                <select id="filterEvaluasi" class="form-select w-auto form-select-sm border-danger text-danger fw-bold" onchange="loadEvaluasi()">
                    <option value="MANGKIR">Hanya Mangkir / Belum Hadir</option>
                    <option value="ALL">Tampilkan Semua Jadwal</option>
                </select>
            </div>
            <div class="table-responsive">
                <table id="tblEvaluasi" class="table table-glass table-hover w-100">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Pegawai</th>
                            <th class="text-center">Shift</th>
                            <th class="text-center">Jadwal Wajib</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Masuk</th>
                            <th class="text-center">Pulang</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: DENDA KETERLAMBATAN -->
    <div class="tab-pane fade" id="potongan" role="tabpanel">
        <div class="glass-card p-4">
            <h5 class="fw-bold mb-4 text-primary">Rekapitulasi Denda Terlambat & Mangkir</h5>
            <div class="table-responsive">
                <table id="tblPotongan" class="table table-glass table-hover w-100 align-middle">
                    <thead>
                        <tr>
                            <th>Nama Pegawai</th>
                            <th class="text-center">Durasi Kerja</th>
                            <th class="text-center">Telat 1</th>
                            <th class="text-center">Telat 2</th>
                            <th class="text-center">Mangkir</th>
                            <th class="text-center">Cuti/Libur</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: ANALITIK KEDISIPLINAN -->
    <div class="tab-pane fade" id="analitik" role="tabpanel">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-box bg-gradient-danger shadow-sm">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3 id="statMangkir">0</h3>
                    <p>Total Mangkir</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box bg-gradient-warning shadow-sm">
                    <i class="fas fa-stopwatch"></i>
                    <h3 id="statTelat">0</h3>
                    <p>Total Terlambat</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box bg-gradient-success shadow-sm">
                    <i class="fas fa-check-circle"></i>
                    <h3 id="statHadir">0</h3>
                    <p>Total Kehadiran</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-8">
                <div class="glass-card p-4 h-100">
                    <h6 class="fw-bold text-center mb-3">Peta Pelanggaran Indisipliner per Departemen</h6>
                    <div class="chart-container">
                        <canvas id="chartPelanggaran"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 h-100">
                    <h6 class="fw-bold text-center mb-3">Distribusi Jenis Pelanggaran</h6>
                    <div class="chart-container">
                        <canvas id="chartPie"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let dtEvaluasi, dtPotongan;
    let chartBar, chartPie;

    const formatRp = (angka) => {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(angka);
    };

    $(document).ready(function() {
        // Load Departemen
        $.get('api_absensi.php?act=get_dep', function(res) {
            try {
                let data = JSON.parse(res);
                data.forEach(d => $('#dep').append(`<option value="${d.dep_id}">${d.nama}</option>`));
            } catch(e) {}
        });

        // Initialize DataTables
        dtEvaluasi = $('#tblEvaluasi').DataTable({
            dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Export Excel', className: 'btn btn-success btn-sm' }
            ],
            columns: [
                { data: 'tanggal', className: 'text-nowrap' },
                { data: 'nama', render: (d, t, r) => `<strong>${d}</strong><br><small class="text-muted">${r.nik} - ${r.departemen}</small>` },
                { data: 'jadwal', className: 'text-center fw-bold text-primary text-uppercase' },
                { data: null, className: 'text-center font-monospace', render: r => (r.jadwal_in === '-') ? '-' : `${r.jadwal_in} - ${r.jadwal_out}` },
                { data: 'status_evaluasi', className: 'text-center', render: d => {
                    if(d === 'MANGKIR') return '<span class="badge badge-soft-danger border border-danger">MANGKIR</span>';
                    if(d === 'DINAS') return '<span class="badge badge-soft-warning border border-warning">DINAS</span>';
                    if(d === 'BELUM_WAKTUNYA') return '<span class="badge badge-soft-info border border-info">BELUM HABIS</span>';
                    return '<span class="badge badge-soft-success border border-success">HADIR</span>';
                }},
                { data: 'jam_masuk', className: 'text-center font-monospace text-success fw-bold' },
                { data: 'jam_pulang', className: 'text-center font-monospace text-warning fw-bold' },
                { data: 'keterangan', className: 'small text-muted fst-italic' }
            ]
        });

        dtPotongan = $('#tblPotongan').DataTable({
            dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Export Excel', className: 'btn btn-success btn-sm' }
            ],
            order: [[5, 'desc']], // Sort by total denda
            columns: [
                { data: 'nama', render: (d,t,r) => `<strong>${d}</strong><br><small class="text-muted">${r.nik} | ${r.dept}</small>` },
                { data: 'total_durasi', className: 'text-center font-monospace text-primary fw-bold' },
                { data: null, className: 'text-center', render: r => r.jml_telat1 > 0 ? `<div class="badge bg-warning text-dark">${r.jml_telat1}x</div>` : '-' },
                { data: null, className: 'text-center', render: r => r.jml_telat2 > 0 ? `<div class="badge bg-orange text-white" style="background:#fd7e14">${r.jml_telat2}x</div>` : '-' },
                { data: null, className: 'text-center', render: r => r.jml_mangkir > 0 ? `<div class="badge bg-danger">${r.jml_mangkir}x</div>` : '-' },
                { data: 'jml_cuti', className: 'text-center', render: d => d > 0 ? `<span class="badge bg-secondary">${d}x Cuti</span>` : '-' }
            ]
        });

        // Initialize Charts
        initCharts();

        // Initial Load
        loadAllData();
    });

    function initCharts() {
        chartBar = new Chart(document.getElementById('chartPelanggaran'), {
            type: 'bar',
            data: { labels: [], datasets: [] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, stacked: true }, x: { stacked: true } } }
        });
        chartPie = new Chart(document.getElementById('chartPie'), {
            type: 'doughnut',
            data: { labels: ['Telat 1', 'Telat 2', 'Mangkir'], datasets: [] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    function loadAllData() {
        $('#globalLoadingOverlay').css('display', 'flex'); // Assuming from header.php
        
        // Panggil 2 endpoint secara paralel
        $.when(
            $.get(`api_absensi.php?act=analyze&tgl1=${$('#tgl1').val()}&tgl2=${$('#tgl2').val()}&dep=${$('#dep').val()}&filter=${$('#filterEvaluasi').val()}`),
            $.get(`api_absensi.php?act=rekap&tgl1=${$('#tgl1').val()}&tgl2=${$('#tgl2').val()}&dep=${$('#dep').val()}`)
        ).done(function(resEval, resRekap) {
            $('#globalLoadingOverlay').hide();
            
            try {
                // Populate Evaluasi
                let jsonEval = JSON.parse(resEval[0]);
                dtEvaluasi.clear().rows.add(jsonEval.data).draw();
                
                // Populate Rekap & Analytics
                let jsonRekap = JSON.parse(resRekap[0]);
                dtPotongan.clear().rows.add(jsonRekap.data).draw();
                
                processAnalytics(jsonRekap.data);
            } catch(e) {
                Swal.fire('Error', 'Terjadi kesalahan saat mengolah data response.', 'error');
            }
        }).fail(function() {
            $('#globalLoadingOverlay').hide();
            Swal.fire('Error', 'Gagal memuat data dari server.', 'error');
        });
    }

    function loadEvaluasi() {
        $('#globalLoadingOverlay').css('display', 'flex');
        $.get(`api_absensi.php?act=analyze&tgl1=${$('#tgl1').val()}&tgl2=${$('#tgl2').val()}&dep=${$('#dep').val()}&filter=${$('#filterEvaluasi').val()}`, function(res) {
            $('#globalLoadingOverlay').hide();
            try {
                let json = JSON.parse(res);
                dtEvaluasi.clear().rows.add(json.data).draw();
            }catch(e) {}
        });
    }

    function processAnalytics(data) {
        let tMangkir = 0, tTelat1 = 0, tTelat2 = 0, tHadir = 0;
        let depData = {};

        data.forEach(p => {
            tMangkir += (p.jml_mangkir || 0);
            tTelat1 += (p.jml_telat1 || 0);
            tTelat2 += (p.jml_telat2 || 0);
            tHadir += (p.jml_hadir || 0);
            
            if(!depData[p.dept]) depData[p.dept] = {m:0, t1:0, t2:0};
            depData[p.dept].m += (p.jml_mangkir || 0);
            depData[p.dept].t1 += (p.jml_telat1 || 0);
            depData[p.dept].t2 += (p.jml_telat2 || 0);
        });

        let totTelat = tTelat1 + tTelat2;
        $('#statMangkir').text(tMangkir);
        $('#statTelat').text(totTelat);
        $('#statHadir').text(tHadir);
        
        let labels = Object.keys(depData);
        let dm = [], dt1 = [], dt2 = [];
        labels.forEach(l => {
            dm.push(depData[l].m);
            dt1.push(depData[l].t1);
            dt2.push(depData[l].t2);
        });

        chartBar.data.labels = labels;
        chartBar.data.datasets = [
            { label: 'Mangkir', data: dm, backgroundColor: '#dc3545' },
            { label: 'Telat 2', data: dt2, backgroundColor: '#fd7e14' },
            { label: 'Telat 1', data: dt1, backgroundColor: '#ffc107' }
        ];
        chartBar.update();

        chartPie.data.datasets = [{
            data: [tTelat1, tTelat2, tMangkir],
            backgroundColor: ['#ffc107', '#fd7e14', '#dc3545'],
            borderWidth: 0
        }];
        chartPie.update();
    }

</script>
<?php
$page_js = ob_get_clean();
include 'includes/footer.php';
?>
