<?php
/*
 * File: laporan_monitoring_klaim.php
 * Deskripsi: Dashboard Monitoring Klaim - Menampilkan status SEP pasien
 * Author: Dashboard System
 * Date: 2025-05-08
 */

// 1. Setup & Keamanan
$page_title = "Monitoring Klaim";
require_once('includes/header.php');
require_once('includes/functions.php');

// 2. Parameter Filter
$tgl_awal = isset($_GET['tgl_awal']) ? htmlspecialchars($_GET['tgl_awal']) : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? htmlspecialchars($_GET['tgl_akhir']) : date('Y-m-d');
$jam_awal = isset($_GET['jam_awal']) ? htmlspecialchars($_GET['jam_awal']) : '00:00:00';
$jam_akhir = isset($_GET['jam_akhir']) ? htmlspecialchars($_GET['jam_akhir']) : '23:59:59';
$status_sep = isset($_GET['status_sep']) ? htmlspecialchars($_GET['status_sep']) : '';
$status_lanjut = isset($_GET['status_lanjut']) ? htmlspecialchars($_GET['status_lanjut']) : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// 4. Ambil Data Instansi untuk Header
$q_instansi = $koneksi->query("SELECT nama_instansi FROM setting LIMIT 1");
$data_instansi = $q_instansi->fetch_assoc();
$nama_rs = $data_instansi['nama_instansi'] ?? 'Rumah Sakit';
$logo_src = "core/logo.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?> - Dashboard RS</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
        }

        body {
            background: #090b14;
            color: #e5e7eb;
            padding: 20px 0;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            border: 1px solid rgba(255,255,255,0.08);
            background: #0f172a;
            box-shadow: 0 10px 40px rgba(0,0,0,0.35);
            border-radius: 12px;
        }

        .card-body {
            color: #e5e7eb;
        }

        .card-header {
            background: #111827;
            color: #f8fafc;
            border: none;
            padding: 15px 20px;
        }

        .stat-card {
            text-align: center;
            padding: 20px;
            border-left: 4px solid var(--info);
            background: #111827;
            color: #f8fafc;
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-card.danger {
            border-left-color: var(--danger);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #f8fafc;
        }

        .stat-label {
            color: rgba(248,250,252,0.72);
            font-size: 14px;
            margin-top: 5px;
        }

        .filter-section {
            background: #111827;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .badge-sep-ada {
            background-color: var(--success);
            color: white;
        }

        .badge-sep-tidak {
            background-color: var(--danger);
            color: white;
        }

        .form-control,
        .form-select {
            background: #0f172a;
            border: 1px solid rgba(255,255,255,0.12);
            color: #f8fafc;
        }

        .form-control[readonly],
        .form-control:disabled,
        .form-select:disabled {
            background: #0f172a !important;
            border-color: rgba(255,255,255,0.12) !important;
            color: #f8fafc !important;
            opacity: 1;
        }

        .form-control::placeholder {
            color: rgba(248,250,252,0.7);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37,99,235,0.2);
            background: #0f172a;
            color: #f8fafc;
        }

        .table {
            color: #e5e7eb;
        }

        .table thead th {
            background: #111827;
            color: #f8fafc;
            border-color: rgba(255,255,255,0.08);
        }

        .table tbody tr {
            background: #0d111f;
        }

        .table tbody tr:hover {
            background-color: rgba(255,255,255,0.08);
        }

        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #111827;
        }

        .btn-filter {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: white;
        }

        .btn-filter:hover {
            background: var(--primary);
            color: white;
        }

        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }

        #tblMonitoringKlaim tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .loading-spinner.show {
            display: block;
        }

        .btn-filter {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: white;
        }

        .btn-filter:hover {
            background: var(--primary);
            color: white;
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="mb-0" style="color: var(--primary);">
                            <i class="fas fa-file-alt me-2"></i>Monitoring Klaim
                        </h2>
                        <small class="text-muted">Pantau Status SEP Pasien untuk Optimalisasi Klaim</small>
                    </div>
                    <img src="<?php echo $logo_src; ?>" height="60" alt="Logo">
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form id="frmFilter" method="GET" class="needs-validation">
            <input type="hidden" name="action" value="cari">
            
            <div class="row g-3">
                <div class="col-lg-2">
                    <label class="form-label fw-bold">Tanggal Awal</label>
                    <input type="date" id="tgl_awal" name="tgl_awal" class="form-control" 
                           value="<?php echo htmlspecialchars($tgl_awal); ?>" required>
                </div>

                <div class="col-lg-2">
                    <label class="form-label fw-bold">Jam Awal</label>
                    <input type="time" id="jam_awal" name="jam_awal" class="form-control" 
                           value="<?php echo htmlspecialchars(substr($jam_awal, 0, 5)); ?>">
                </div>

                <div class="col-lg-2">
                    <label class="form-label fw-bold">Tanggal Akhir</label>
                    <input type="date" id="tgl_akhir" name="tgl_akhir" class="form-control" 
                           value="<?php echo htmlspecialchars($tgl_akhir); ?>" required>
                </div>

                <div class="col-lg-2">
                    <label class="form-label fw-bold">Jam Akhir</label>
                    <input type="time" id="jam_akhir" name="jam_akhir" class="form-control" 
                           value="<?php echo htmlspecialchars(substr($jam_akhir, 0, 5)); ?>">
                </div>

                <div class="col-lg-2">
                    <label class="form-label fw-bold">Penjamin</label>
                    <input type="text" class="form-control" value="BPJS KESEHATAN" readonly>
                </div>

                <div class="col-lg-2">
                    <label class="form-label fw-bold">Status SEP</label>
                    <select id="status_sep" name="status_sep" class="form-select">
                        <option value="">-- Semua Status --</option>
                        <option value="ada" <?php echo ($status_sep == 'ada') ? 'selected' : ''; ?>>
                            Ada SEP
                        </option>
                        <option value="tidak_ada" <?php echo ($status_sep == 'tidak_ada') ? 'selected' : ''; ?>>
                            Tidak Ada SEP
                        </option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <label class="form-label fw-bold">Jenis Pelayanan</label>
                    <select id="status_lanjut" name="status_lanjut" class="form-select">
                        <option value="">-- Semua Pelayanan --</option>
                        <option value="Ralan" <?php echo ($status_lanjut == 'Ralan') ? 'selected' : ''; ?>>
                            Rawat Jalan
                        </option>
                        <option value="Ranap" <?php echo ($status_lanjut == 'Ranap') ? 'selected' : ''; ?>>
                            Rawat Inap
                        </option>
                    </select>
                </div>
            </div>

            <div class="row g-2 mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-filter btn-sm">
                        <i class="fas fa-search me-2"></i>Cari Data
                    </button>
                    <button type="reset" class="btn btn-secondary btn-sm">
                        <i class="fas fa-redo me-2"></i>Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4" id="summaryCards" style="display: none;">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="stat-value" id="totalPasien">0</div>
                <div class="stat-label">Total Pasien</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card success">
                <div class="stat-value" id="totalAdaSep">0</div>
                <div class="stat-label">Pasien dengan SEP</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card danger">
                <div class="stat-value" id="totalTidakSep">0</div>
                <div class="stat-label">Pasien Tanpa SEP</div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card warning">
                <div class="stat-value" id="persenSep">0%</div>
                <div class="stat-label">Persentase SEP</div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Memuat data...</p>
    </div>

    <!-- Data Table Section -->
    <div class="card" id="tableSection" style="display: none;">
        <div class="card-header">
            <h5 class="mb-0">Daftar Pasien & Status Klaim</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tblMonitoringKlaim" class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%">No</th>
                            <th style="width: 10%">No RM</th>
                            <th style="width: 18%">Nama Pasien</th>
                            <th style="width: 10%">No Rawat</th>
                            <th style="width: 10%">Status</th>
                            <th style="width: 12%">Tgl Registrasi</th>
                            <th style="width: 12%">Tgl Masuk</th>
                            <th style="width: 12%">Tgl Pulang</th>
                            <th style="width: 13%">DPJP Ranap</th>
                            <th style="width: 15%">Penjamin</th>
                            <th style="width: 15%">No SEP</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
$(document).ready(function() {
    // Jika ada parameter action=cari, load data otomatis
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'cari') {
        loadData();
    }

    // Event listener untuk form submit
    $('#frmFilter').on('submit', function(e) {
        e.preventDefault();
        loadData();
        return false;
    });
});

function loadData() {
    const tgl_awal = $('#tgl_awal').val();
    const jam_awal = $('#jam_awal').val() + ':00';
    const tgl_akhir = $('#tgl_akhir').val();
    const jam_akhir = $('#jam_akhir').val() + ':00';
    const status_sep = $('#status_sep').val();
    const status_lanjut = $('#status_lanjut').val();

    // Tampilkan loading spinner
    $('#loadingSpinner').addClass('show');
    $('#tableSection').hide();
    $('#summaryCards').hide();

    $.ajax({
        url: 'api/data_monitoring_klaim.php',
        type: 'GET',
        dataType: 'json',
        data: {
            tgl_awal: tgl_awal,
            jam_awal: jam_awal,
            tgl_akhir: tgl_akhir,
            jam_akhir: jam_akhir,
            status_sep: status_sep,
            status_lanjut: status_lanjut
        },
        success: function(response) {
            if (response.data && response.data.length > 0) {
                populateTable(response.data);
                updateSummary(response.summary);
                $('#tableSection').show();
                $('#summaryCards').show();
            } else {
                showAlert('Tidak ada data yang ditemukan', 'warning');
            }
            $('#loadingSpinner').removeClass('show');
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            showAlert('Gagal memuat data. Silakan coba lagi.', 'danger');
            $('#loadingSpinner').removeClass('show');
        }
    });
}

function ensureTable() {
    if (!$.fn.dataTable.isDataTable('#tblMonitoringKlaim')) {
        $('#tblMonitoringKlaim').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-1"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Monitoring_Klaim',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
            },
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            responsive: true,
            autoWidth: false
        });
    }
    return $('#tblMonitoringKlaim').DataTable();
}

function populateTable(data) {
    const table = ensureTable();
    table.clear();

    data.forEach(function(row, index) {
        const sepBadge = row.status_sep === 'Ada'
            ? '<span class="badge badge-sep-ada">Ada</span>'
            : '<span class="badge badge-sep-tidak">Tidak Ada</span>';

        const regTanggal = new Date(row.tgl_registrasi).toLocaleDateString('id-ID', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }) + ' ' + row.jam_reg;

        const tglMasuk = (row.tgl_masuk && row.tgl_masuk !== '-' && row.tgl_masuk !== '0000-00-00' && row.tgl_masuk !== '0000-00-00 00:00:00') ? row.tgl_masuk : '-';
        const tglPulang = (row.tgl_keluar && row.tgl_keluar !== '-' && row.tgl_keluar !== '0000-00-00' && row.tgl_keluar !== '0000-00-00 00:00:00') ? row.tgl_keluar : '-';
        const dpjpRanap = row.dpjp_ranap ? row.dpjp_ranap : '-';
        const statusLanjut = row.status_lanjut === 'Ranap' ? '<span class="badge badge-sep-ada">Ranap</span>' : '<span class="badge badge-sep-tidak">Ralan</span>';

        table.row.add([
            index + 1,
            row.no_rkm_medis,
            row.nm_pasien,
            row.no_rawat,
            statusLanjut,
            regTanggal,
            tglMasuk,
            tglPulang,
            dpjpRanap,
            row.png_jawab,
            row.no_sep
        ]);
    });

    table.draw();
}

function updateSummary(summary) {
    $('#totalPasien').text(summary.total_pasien);
    $('#totalAdaSep').text(summary.total_ada_sep);
    $('#totalTidakSep').text(summary.total_tidak_ada_sep);
    $('#persenSep').text(summary.persentase_ada_sep + '%');
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insert alert di atas table section
    $('#tableSection').before(alertHtml);
    
    // Auto-dismiss setelah 5 detik
    setTimeout(() => {
        $('.alert').fadeOut(() => $('.alert').remove());
    }, 5000);
}
</script>

<?php require_once('includes/footer.php'); ?>
</body>
</html>
