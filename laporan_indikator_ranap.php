<?php
/*
 * File: laporan_indikator_ranap.php (UPDATE V2)
 * - Tab Global: Data agregat RS (Exclude Pindah Kamar).
 * - Tab Bangsal: Data per ruang (Include Pindah Kamar).
 */

$page_title = "Indikator Pelayanan Rawat Inap";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01'); 
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary">Filter Periode Laporan</h5>
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" class="form-control" name="tgl_awal" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" class="form-control" name="tgl_akhir" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-primary w-100" onclick="loadAllData()">
                        <i class="fas fa-search me-2"></i> Hitung
                    </button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="global-tab" data-bs-toggle="tab" data-bs-target="#global" type="button" role="tab">Laporan Global (RS)</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bangsal-tab" data-bs-toggle="tab" data-bs-target="#bangsal" type="button" role="tab">Per Bangsal</button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        
        <div class="tab-pane fade show active" id="global" role="tabpanel">
            <div class="alert alert-info shadow-sm">
                <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i>Data Dasar Perhitungan (RS):</h6>
                <div class="row text-center" id="data-dasar-container">
                    <div class="col">Loading...</div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-start border-4 border-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">BOR (Occupancy)</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800" id="val-bor">...</div>
                                    <small class="text-muted">Target: 60-85%</small>
                                </div>
                                <div class="col-auto"><i class="fas fa-bed fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-4 border-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">ALOS (Length of Stay)</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800" id="val-alos">...</div>
                                    <small class="text-muted">Target: 6-9 Hari</small>
                                </div>
                                <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-4 border-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">TOI (Turn Over Interval)</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800" id="val-toi">...</div>
                                    <small class="text-muted">Target: 1-3 Hari</small>
                                </div>
                                <div class="col-auto"><i class="fas fa-sync-alt fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-4 border-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">BTO (Bed Turn Over)</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800" id="val-bto">...</div>
                                    <small class="text-muted">Target: 40-50 Kali/Th</small>
                                </div>
                                <div class="col-auto"><i class="fas fa-people-arrows fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-4 border-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">NDR (Net Death Rate)</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800" id="val-ndr">...</div>
                                    <small class="text-muted">< 25 per 1000</small>
                                </div>
                                <div class="col-auto"><i class="fas fa-heart-broken fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-4 border-dark shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">GDR (Gross Death Rate)</div>
                                    <div class="h3 mb-0 font-weight-bold text-gray-800" id="val-gdr">...</div>
                                    <small class="text-muted">< 45 per 1000</small>
                                </div>
                                <div class="col-auto"><i class="fas fa-cross fa-2x text-gray-300"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="bangsal" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Catatan:</strong> Untuk perhitungan per bangsal, pasien "Pindah Kamar" dihitung sebagai Pasien Keluar (Discharge) agar perhitungan pemakaian tempat tidur (TOI/BTO) akurat.
                    </div>
                    <div class="table-responsive">
                        <table id="table-bangsal" class="table table-striped table-bordered table-hover" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nama Bangsal</th>
                                    <th class="text-center">Bed (TT)</th>
                                    <th class="text-center">Hari Rawat (HP)</th>
                                    <th class="text-center">Pasien Keluar (D)</th>
                                    <th class="text-center">BOR (%)</th>
                                    <th class="text-center">ALOS</th>
                                    <th class="text-center">TOI</th>
                                    <th class="text-center">BTO</th>
                                    <th class="text-center">NDR</th>
                                    <th class="text-center">GDR</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
		
		<div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">Kamus Indikator (Referensi)</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-sm text-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Indikator</th>
                                <th>Rumus</th>
                                <th>Nilai Ideal (Barber Johnson)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>BOR</td>
                                <td>(Hari Perawatan / (Tempat Tidur x Periode Hari)) x 100</td>
                                <td>60 - 85 %</td>
                            </tr>
                            <tr>
                                <td>ALOS</td>
                                <td>Hari Perawatan / Pasien Keluar (Hidup + Mati)</td>
                                <td>6 - 9 Hari</td>
                            </tr>
                            <tr>
                                <td>TOI</td>
                                <td>((Tempat Tidur x Periode) - Hari Perawatan) / Pasien Keluar</td>
                                <td>1 - 3 Hari</td>
                            </tr>
                            <tr>
                                <td>BTO</td>
                                <td>Pasien Keluar / Tempat Tidur</td>
                                <td>40 - 50 Kali / Tahun</td>
                            </tr>
                            <tr>
                                <td>NDR</td>
                                <td>(Meninggal > 48 Jam / Pasien Keluar) x 1000</td>
                                <td>< 25 per 1000</td>
                            </tr>
                            <tr>
                                <td>GDR</td>
                                <td>(Total Meninggal / Pasien Keluar) x 1000</td>
                                <td>< 45 per 1000</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<?php ob_start(); ?>
<script>
    var tableBangsal;

    $(document).ready(function() {
        // Init DataTables untuk Bangsal
        tableBangsal = $('#table-bangsal').DataTable({
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [
                { extend: 'excelHtml5', className: 'btn btn-success btn-sm', title: 'Laporan Indikator Per Bangsal' },
                { extend: 'pdfHtml5', className: 'btn btn-danger btn-sm', title: 'Laporan Indikator Per Bangsal' },
                { extend: 'print', className: 'btn btn-secondary btn-sm' }
            ],
            "columns": [
                { "data": "bangsal" },
                { "data": "bed", className: "text-center" },
                { "data": "hp", className: "text-center" },
                { "data": "d", className: "text-center" },
                { "data": "bor", className: "text-center fw-bold" },
                { "data": "alos", className: "text-center" },
                { "data": "toi", className: "text-center" },
                { "data": "bto", className: "text-center" },
                { "data": "ndr", className: "text-center" },
                { "data": "gdr", className: "text-center" }
            ]
        });

        // Load data pertama kali
        loadAllData();
    });

    function loadAllData() {
        var tglAwal = $('#tgl_awal').val();
        var tglAkhir = $('#tgl_akhir').val();

        // 1. Load Data Global
        $('#data-dasar-container').html('<div class="col">Sedang menghitung...</div>');
        $.ajax({
            url: 'api/data_indikator_ranap.php',
            type: 'GET',
            data: { tgl_awal: tglAwal, tgl_akhir: tglAkhir },
            dataType: 'json',
            success: function(response) {
                updateUIGlobal(response);
            },
            error: function() { $('#data-dasar-container').html('<div class="col text-danger">Gagal memuat data global.</div>'); }
        });

        // 2. Load Data Per Bangsal
        // Gunakan DataTables API untuk reload ajax
        // Kita butuh cara manual karena DataTables ajax source biasanya di-init di awal
        // Cara paling mudah: Clear table -> Ajax Call -> Add Rows
        
        $.ajax({
            url: 'api/data_indikator_per_bangsal.php',
            type: 'GET',
            data: { tgl_awal: tglAwal, tgl_akhir: tglAkhir },
            dataType: 'json',
            success: function(response) {
                tableBangsal.clear();
                tableBangsal.rows.add(response.data);
                tableBangsal.draw();
            },
            error: function() { console.error("Gagal memuat data bangsal"); }
        });
    }

    function updateUIGlobal(data) {
        var d = data.data_dasar;
        var htmlDasar = `
            <div class="col-md-2 col-6 mb-2"><div class="fw-bold text-primary">${d.jumlah_bed}</div><small>Tempat Tidur</small></div>
            <div class="col-md-2 col-6 mb-2"><div class="fw-bold text-success">${d.hari_perawatan}</div><small>Hari Perawatan</small></div>
            <div class="col-md-2 col-6 mb-2"><div class="fw-bold text-info">${d.pasien_keluar}</div><small>Pasien Keluar</small></div>
            <div class="col-md-2 col-6 mb-2"><div class="fw-bold text-danger">${d.pasien_mati}</div><small>Mati (GDR)</small></div>
            <div class="col-md-2 col-6 mb-2"><div class="fw-bold text-dark">${d.pasien_mati_48}</div><small>Mati >48h (NDR)</small></div>
            <div class="col-md-2 col-6 mb-2"><div class="fw-bold text-secondary">${data.periode.hari}</div><small>Periode (Hari)</small></div>
        `;
        $('#data-dasar-container').html(htmlDasar);

        var i = data.indikator;
        $('#val-bor').text(i.bor + ' %');
        $('#val-alos').text(i.alos + ' Hari');
        $('#val-toi').text(i.toi + ' Hari');
        $('#val-bto').text(i.bto + ' Kali');
        $('#val-ndr').text(i.ndr + ' ‰');
        $('#val-gdr').text(i.gdr + ' ‰');
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>