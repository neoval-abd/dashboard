<?php
/*
 * File laporan_kunjungan.php (BERSIH)
 * Laporan jumlah kunjungan pasien (Non-Batal).
 */

// 1. Setup
$page_title = "Laporan Kunjungan Pasien";
require_once('includes/header.php');
require_once('includes/functions.php');

// 2. Parameter Filter
$tgl_awal = isset($_GET['tgl_awal']) ? htmlspecialchars($_GET['tgl_awal']) : date('Y-m-d');
$jam_awal = isset($_GET['jam_awal']) ? htmlspecialchars($_GET['jam_awal']) : '00:00:00';
$tgl_akhir = isset($_GET['tgl_akhir']) ? htmlspecialchars($_GET['tgl_akhir']) : date('Y-m-d');
$jam_akhir = isset($_GET['jam_akhir']) ? htmlspecialchars($_GET['jam_akhir']) : '23:59:59';
$kd_pj = isset($_GET['kd_pj']) ? htmlspecialchars($_GET['kd_pj']) : ''; 
$action = isset($_GET['action']) ? $_GET['action'] : ''; 

$datetime_awal = $tgl_awal . ' ' . $jam_awal;
$datetime_akhir = $tgl_akhir . ' ' . $jam_akhir;

// 3. Data Penjamin (Dropdown)
$penjabs = [];
$sql_penjab = "SELECT kd_pj, png_jawab FROM penjab ORDER BY png_jawab ASC";
$result_penjab = $koneksi->query($sql_penjab);
if ($result_penjab) {
    while ($row = $result_penjab->fetch_assoc()) {
        $penjabs[] = $row;
    }
}

// 4. Logika Pengambilan Data Tabel (Hanya jika ada action cari)
$data_ralan = [];
$data_ranap = [];
$is_search = ($action == 'cari');

if ($is_search) {
    // Base WHERE: Rentang tanggal & Tidak Batal
    $where_base = " WHERE CONCAT(reg_periksa.tgl_registrasi, ' ', reg_periksa.jam_reg) BETWEEN ? AND ? AND reg_periksa.stts != 'Batal' ";
    $params = [$datetime_awal, $datetime_akhir];
    $types = "ss";

    if (!empty($kd_pj)) {
        $where_base .= " AND reg_periksa.kd_pj = ? ";
        $params[] = $kd_pj;
        $types .= "s";
    }

    // --- Query Ralan ---
    $sql_ralan = "
        SELECT 
            reg_periksa.no_rawat, reg_periksa.tgl_registrasi, reg_periksa.jam_reg, 
            reg_periksa.no_rkm_medis, pasien.nm_pasien, 
            dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab, reg_periksa.stts_daftar
        FROM reg_periksa
        INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
        INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        $where_base AND reg_periksa.status_lanjut = 'Ralan'
        ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
    ";

    $stmt = $koneksi->prepare($sql_ralan);
    if ($stmt) {
        $bind_names = [];
        $bind_names[] = $types;
        for ($i=0; $i<count($params);$i++) { $bind_names[] = &$params[$i]; }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
        
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data_ralan[] = $row;
        }
        $stmt->close();
    }

    // --- Query Ranap ---
    $sql_ranap = "
        SELECT 
            reg_periksa.no_rawat, reg_periksa.tgl_registrasi, reg_periksa.jam_reg, 
            reg_periksa.no_rkm_medis, pasien.nm_pasien, 
            dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab, reg_periksa.stts_daftar
        FROM reg_periksa
        INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
        INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        $where_base AND reg_periksa.status_lanjut = 'Ranap'
        ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
    ";

    $stmt = $koneksi->prepare($sql_ranap);
    if ($stmt) {
        $bind_names = [];
        $bind_names[] = $types;
        for ($i=0; $i<count($params);$i++) { $bind_names[] = &$params[$i]; }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
        
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data_ranap[] = $row;
        }
        $stmt->close();
    }
}
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary">Filter Laporan Kunjungan</h5>
            <form action="laporan_kunjungan.php" method="GET">
                <input type="hidden" name="action" value="cari">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" name="tgl_awal" value="<?php echo $tgl_awal; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jam</label>
                        <input type="time" class="form-control" name="jam_awal" value="<?php echo $jam_awal; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Jam</label>
                        <input type="time" class="form-control" name="jam_akhir" value="<?php echo $jam_akhir; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Penjamin</label>
                        <select name="kd_pj" class="form-select">
                            <option value="">-- Semua Penjamin --</option>
                            <?php foreach($penjabs as $p): ?>
                                <option value="<?php echo $p['kd_pj']; ?>" <?php echo ($kd_pj == $p['kd_pj']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['png_jawab']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Tampilkan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($is_search): ?>
    
    <div class="alert alert-info shadow-sm mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0">Total Kunjungan: <strong><?php echo count($data_ralan) + count($data_ranap); ?></strong></h5>
                <small>Rawat Jalan: <?php echo count($data_ralan); ?> | Rawat Inap: <?php echo count($data_ranap); ?></small>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-4 col-md-12 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Proporsi Kunjungan per Penjamin</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2" style="height: 300px;">
                        <canvas id="chartPieKunjungan"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-md-12 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tren Kunjungan Harian</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 300px;">
                        <canvas id="chartLineKunjungan"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="ralan-tab" data-bs-toggle="tab" data-bs-target="#ralan" type="button" role="tab" aria-controls="ralan" aria-selected="true">
                        Rawat Jalan (<?php echo count($data_ralan); ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ranap-tab" data-bs-toggle="tab" data-bs-target="#ranap" type="button" role="tab" aria-controls="ranap" aria-selected="false">
                        Rawat Inap (<?php echo count($data_ranap); ?>)
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="myTabContent">
                
                <div class="tab-pane fade show active" id="ralan" role="tabpanel" aria-labelledby="ralan-tab">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm dt-table" width="100%">
                            <thead>
                                <tr>
                                    <th>No. Rawat</th>
                                    <th>Tgl Reg</th>
                                    <th>Jam</th>
                                    <th>No. RM</th>
                                    <th>Pasien</th>
                                    <th>Poliklinik</th>
                                    <th>Dokter</th>
                                    <th>Penjamin</th>
                                    <th>Jns Kunjungan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_ralan as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['no_rawat']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tgl_registrasi']); ?></td>
                                    <td><?php echo htmlspecialchars($row['jam_reg']); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rkm_medis']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_pasien']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_poli']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_dokter']); ?></td>
                                    <td><?php echo htmlspecialchars($row['png_jawab']); ?></td>
                                    <td><?php echo htmlspecialchars($row['stts_daftar']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade" id="ranap" role="tabpanel" aria-labelledby="ranap-tab">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm dt-table" width="100%">
                            <thead>
                                <tr>
                                    <th>No. Rawat</th>
                                    <th>Tgl Masuk</th>
                                    <th>Jam</th>
                                    <th>No. RM</th>
                                    <th>Pasien</th>
                                    <th>Asal Poli/IGD</th>
                                    <th>Dokter</th>
                                    <th>Penjamin</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_ranap as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['no_rawat']); ?></td>
                                    <td><?php echo htmlspecialchars($row['tgl_registrasi']); ?></td>
                                    <td><?php echo htmlspecialchars($row['jam_reg']); ?></td>
                                    <td><?php echo htmlspecialchars($row['no_rkm_medis']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_pasien']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_poli']); ?></td>
                                    <td><?php echo htmlspecialchars($row['nm_dokter']); ?></td>
                                    <td><?php echo htmlspecialchars($row['png_jawab']); ?></td>
                                    <td><?php echo htmlspecialchars($row['stts']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php else: ?>
        <div class="alert alert-secondary text-center p-5">
            <h4>Silakan pilih filter tanggal dan klik "Tampilkan"</h4>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<?php ob_start(); ?>
<script>
    $(document).ready(function() {
        // Init DataTables
        $('.dt-table').DataTable({
            "responsive": true,
            "order": [[ 1, "desc" ], [ 2, "desc" ]],
            "pageLength": 10,
            "lengthChange": true,
            // --- TAMBAHAN UNTUK EXPORT ---
            "dom": 'Bfrtip', // B = Buttons, f = filtering, r = processing, t = table, i = info, p = pagination
            "buttons": [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Laporan Kunjungan Pasien - ' + $('input[name="tgl_awal"]').val() + ' sd ' + $('input[name="tgl_akhir"]').val()
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    title: 'Laporan Kunjungan Pasien'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-secondary btn-sm'
                }
            ],
            "language": {
                "search": "Cari:",
                "lengthMenu": "Tampilkan _MENU_ baris",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                "paginate": {
                    "first": "Awal",
                    "last": "Akhir",
                    "next": "Lanjut",
                    "previous": "Kembali"
                }
            }
        });

        // Load Charts
        <?php if ($is_search): ?>
        loadCharts();
        <?php endif; ?>
    });

    function loadCharts() {
        var params = {
            tgl_awal: $('input[name="tgl_awal"]').val(),
            jam_awal: $('input[name="jam_awal"]').val(),
            tgl_akhir: $('input[name="tgl_akhir"]').val(),
            jam_akhir: $('input[name="jam_akhir"]').val(),
            kd_pj: $('select[name="kd_pj"]').val()
        };

        $.ajax({
            url: 'api/data_kunjungan_chart.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(data) {
                renderPieChart(data.pie);
                renderLineChart(data.line);
            },
            error: function(xhr, status, error) {
                console.error("Gagal memuat data chart:", error);
                console.log("Response:", xhr.responseText);
            }
        });
    }

    function renderPieChart(pieData) {
        var ctx = document.getElementById("chartPieKunjungan");
        if(!ctx) return;
        
        var backgroundColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf'];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: pieData.labels,
                datasets: [{
                    data: pieData.data,
                    backgroundColor: backgroundColors,
                    hoverBackgroundColor: backgroundColors,
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' Pasien';
                            }
                        }
                    }
                },
                cutout: '70%',
            },
        });
    }

    function renderLineChart(lineData) {
        var ctx = document.getElementById("chartLineKunjungan");
        if(!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: lineData.labels,
                datasets: lineData.datasets
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { maxTicksLimit: 7 }
                    },
                    y: {
                        ticks: {
                            maxTicksLimit: 5,
                            padding: 10,
                            callback: function(value) { return value; } 
                        },
                        grid: { 
                            color: "rgb(234, 236, 244)", 
                            drawBorder: false, 
                            borderDash: [2], 
                            zeroLineBorderDash: [2] 
                        }
                    },
                }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>