<?php
/*
 * File laporan_billing_global.php (PERBAIKAN V5 - Fix Chart.js V3 Syntax)
 * - Memperbaiki sintaks Chart.js agar kompatibel dengan versi 3.x
 * - Menampilkan chart Pie dan Line dengan konfigurasi yang benar.
 * PHP 7.3 compatible.
 */

// 1. Set Judul & Sertakan Header
$page_title = "Laporan Billing Global";
require_once('includes/header.php');
require_once('includes/functions.php');

// 2. Ambil Parameter Filter
$tgl_awal = isset($_GET['tgl_awal']) ? htmlspecialchars($_GET['tgl_awal']) : date('Y-m-d');
$jam_awal = isset($_GET['jam_awal']) ? htmlspecialchars($_GET['jam_awal']) : '00:00:00';
$tgl_akhir = isset($_GET['tgl_akhir']) ? htmlspecialchars($_GET['tgl_akhir']) : date('Y-m-d');
$jam_akhir = isset($_GET['jam_akhir']) ? htmlspecialchars($_GET['jam_akhir']) : '23:59:59';
$kd_pj = isset($_GET['kd_pj']) ? htmlspecialchars($_GET['kd_pj']) : ''; 
$status_bayar = isset($_GET['status_bayar']) ? htmlspecialchars($_GET['status_bayar']) : ''; 
$action = isset($_GET['action']) ? $_GET['action'] : ''; 

// Gabungkan datetime
$datetime_awal = $tgl_awal . ' ' . $jam_awal;
$datetime_akhir = $tgl_akhir . ' ' . $jam_akhir;

// 3. Ambil Data Penjamin untuk Dropdown
$penjabs = [];
$sql_penjab = "SELECT kd_pj, png_jawab FROM penjab ORDER BY png_jawab ASC";
$result_penjab = $koneksi->query($sql_penjab);
if ($result_penjab) {
    while ($row = $result_penjab->fetch_assoc()) {
        $penjabs[] = $row;
    }
}

// 4. Logika Utama: Hanya jalan jika tombol 'Tampilkan' diklik
$data_billing = [];
$total_semua_billing = 0;
$is_search = ($action == 'cari');

if ($is_search) {
    
    // Bangun Klausa WHERE Dinamis
    $where_tambahan = "";
    $params = [];
    $types = "";

    // Parameter wajib (Tanggal)
    $params[] = $datetime_awal;
    $params[] = $datetime_akhir;
    $types .= "ss";
    
    // Filter Penjamin
    if (!empty($kd_pj)) {
        $where_tambahan .= " AND penjab.kd_pj = ? ";
        $params[] = $kd_pj;
        $types .= "s";
    }

    // Kueri UNION
    $sql_ralan = "
        SELECT 
            reg_periksa.no_rawat, 
            nota_jalan.no_nota, 
            pasien.nm_pasien, 
            nota_jalan.tanggal, 
            nota_jalan.jam, 
            penjab.png_jawab, 
            'Ralan' AS status_lanjut,
            (SELECT SUM(
            CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END
                ) 
            FROM billing 
            WHERE billing.no_rawat = reg_periksa.no_rawat
                ) AS total_rupiah,
            IF(reg_periksa.no_rawat IN (SELECT piutang_pasien.no_rawat FROM piutang_pasien WHERE piutang_pasien.no_rawat = reg_periksa.no_rawat), 'Piutang', 'Tunai') AS status_bayar
        FROM reg_periksa 
        INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis 
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj 
        INNER JOIN nota_jalan ON reg_periksa.no_rawat = nota_jalan.no_rawat
        WHERE CONCAT(nota_jalan.tanggal, ' ', nota_jalan.jam) BETWEEN ? AND ? 
        $where_tambahan
    ";

    $sql_ranap = "
        SELECT 
            reg_periksa.no_rawat, 
            nota_inap.no_nota, 
            pasien.nm_pasien, 
            nota_inap.tanggal, 
            nota_inap.jam, 
            penjab.png_jawab, 
            'Ranap' AS status_lanjut,
            (SELECT SUM(
            CASE 
                WHEN billing.status = 'TtlRetur Obat' THEN (billing.totalbiaya * -1)
                WHEN billing.status = 'TtlPotongan' THEN (billing.totalbiaya * -1)
                ELSE billing.totalbiaya 
            END
                ) 
            FROM billing 
            WHERE billing.no_rawat = reg_periksa.no_rawat
                ) AS total_rupiah,
            IF(reg_periksa.no_rawat IN (SELECT piutang_pasien.no_rawat FROM piutang_pasien WHERE piutang_pasien.no_rawat = reg_periksa.no_rawat), 'Piutang', 'Tunai') AS status_bayar
        FROM reg_periksa 
        INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis 
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj 
        INNER JOIN nota_inap ON reg_periksa.no_rawat = nota_inap.no_rawat
        WHERE CONCAT(nota_inap.tanggal, ' ', nota_inap.jam) BETWEEN ? AND ? 
        $where_tambahan
    ";

    $sql_union = "($sql_ralan) UNION ALL ($sql_ranap) ORDER BY tanggal, jam";

    $stmt = $koneksi->prepare($sql_union);
    
    if ($stmt) {
        $all_params = array_merge($params, $params);
        $all_types = $types . $types;
        
        $bind_names[] = $all_types;
        for ($i=0; $i<count($all_params);$i++) {
            $bind_names[] = &$all_params[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (!empty($status_bayar)) {
                if ($row['status_bayar'] !== $status_bayar) {
                    continue; 
                }
            }
            $data_billing[] = $row;
            $total_semua_billing += (float)$row['total_rupiah'];
        }
        $stmt->close();
    } else {
        echo "Error: " . $koneksi->error;
    }
}
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary">Filter Data Billing Global</h5>
            <form action="laporan_billing_global.php" method="GET">
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
                    <div class="col-md-2">
                        <label class="form-label">Status Bayar</label>
                        <select name="status_bayar" class="form-select">
                            <option value="">-- Semua --</option>
                            <option value="Tunai" <?php echo ($status_bayar == 'Tunai') ? 'selected' : ''; ?>>Tunai</option>
                            <option value="Piutang" <?php echo ($status_bayar == 'Piutang') ? 'selected' : ''; ?>>Piutang</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Tampilkan Data
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($is_search): ?>
    
    <div class="alert alert-info shadow-sm d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0">Total Billing (Hasil Filter)</h5>
            <small>Jumlah Transaksi: <?php echo count($data_billing); ?></small>
        </div>
        <h3 class="mb-0 fw-bold text-primary"><?php echo formatRupiah($total_semua_billing); ?></h3>
    </div>
        
    <div class="row mb-4">
        <div class="col-lg-4 col-md-12 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Proporsi per Penjamin</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2" style="height: 300px;">
                        <canvas id="chartPiePenjamin"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-md-12 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tren Pendapatan (Berdasarkan Filter)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 300px;">
                        <canvas id="chartLineTren"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabel-billing-global" class="table table-striped table-bordered table-sm" style="width:100%">
                    <thead>
                        <tr>
                            <th>Waktu Bayar</th>
                            <th>No. Rawat</th>
                            <th>No. Nota</th>
                            <th>Nama Pasien</th>
                            <th>Status</th>
                            <th>Penjamin</th>
                            <th>Pembayaran</th>
                            <th class="text-end">Total (Rp)</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_billing) > 0): ?>
                            <?php foreach ($data_billing as $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['tanggal'] . ' ' . $data['jam']); ?></td>
                                <td><?php echo htmlspecialchars($data['no_rawat']); ?></td>
                                <td><?php echo htmlspecialchars($data['no_nota']); ?></td>
                                <td><?php echo htmlspecialchars($data['nm_pasien']); ?></td>
                                <td><?php echo htmlspecialchars($data['status_lanjut']); ?></td>
                                <td><?php echo htmlspecialchars($data['png_jawab']); ?></td>
                                <td><?php 
                                    if ($data['status_bayar'] == 'Piutang') {
                                        echo '<span class="badge bg-warning text-dark">Piutang</span>';
                                    } else {
                                        echo '<span class="badge bg-success">Tunai</span>';
                                    }
                                ?></td>
                                <td class="text-end"><?php echo formatRupiah($data['total_rupiah']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-success btn-sm btn-lihat-nota" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalDetailNota"
                                            data-norawat="<?php echo htmlspecialchars($data['no_rawat']); ?>"
                                            data-nonota="<?php echo htmlspecialchars($data['no_nota']); ?>">
                                        Nota
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="text-center">Tidak ada data ditemukan untuk filter ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div> 
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-secondary text-center p-5">
            <h4>Silakan pilih filter tanggal dan klik "Tampilkan Data"</h4>
            <p>Data tidak dimuat otomatis untuk menjaga performa aplikasi.</p>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalDetailNota" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Detail Isi Nota: <span id="nomor-nota-modal">...</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="isi-nota-container">
                    <p class="text-center">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<?php ob_start(); ?>
<script>
    function formatRupiah(angka) {
        if(angka == null || isNaN(angka)) return "Rp 0";
        var number_string = angka.toString().replace(/[^,\d]/g, ''),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            
        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return 'Rp ' + rupiah;
    }

    $(document).ready(function() {
        if ($('#tabel-billing-global').length) {
            $('#tabel-billing-global').DataTable({ 
                "responsive": true, 
                "order": [[ 0, "desc" ]],
                "pageLength": 25, 
                "lengthChange": true,
                // --- TAMBAHAN MODUL EXPORT ---
                "dom": 'Bfrtip', 
                "buttons": [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Export Excel',
                        className: 'btn btn-success btn-sm',
                        title: 'Laporan Billing Global - ' + $('input[name="tgl_awal"]').val() + ' sd ' + $('input[name="tgl_akhir"]').val()
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> Export PDF',
                        className: 'btn btn-danger btn-sm',
                        orientation: 'landscape',
                        pageSize: 'A4',
                        title: 'Laporan Billing Global ' + $('input[name="tgl_awal"]').val() + ' sd ' + $('input[name="tgl_akhir"]').val()
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
                // --- SELESAI TAMBAHAN ---
            });
            
            loadCharts();
        }
        
        $(document).on('click', '.btn-lihat-nota', function() {
            var noRawat = $(this).data('norawat');
            var noNota = $(this).data('nonota');
            
            $("#nomor-nota-modal").text(noNota + " (No. Rawat: " + noRawat + ")");
            $("#isi-nota-container").html("<p class='text-center'>Memuat data...</p>");

            $.ajax({
                url: "api/get_detail_nota.php", 
                type: "GET",
                data: { no_rawat: noRawat }, 
                dataType: "json",
                success: function(response) {
                    var html = '<table class="table table-sm">';
                    html += '<thead style="border-bottom: 2px solid #333;"><tr>';
                    html += '<th scope="col" style="width: 5%;">Ket.</th>';
                    html += '<th scope="col" style="width: 45%;">Perawatan/Tindakan/Obat</th>';
                    html += '<th scope="col" style="width: 20%;">Status</th>';
                    html += '<th scope="col" class="text-end" style="width: 10%;">Biaya</th>';
                    html += '<th scope="col" class="text-center" style="width: 5%;">Jml</th>';
                    html += '<th scope="col" class="text-end" style="width: 15%;">Total</th>';
                    html += '</tr></thead><tbody>';
                    
                    var grandTotal = 0;
                    
                    if (Array.isArray(response) && response.length > 0) {
                        response.forEach(function(item) {
                            var no = item.no || '';
                            var nm_perawatan = item.nm_perawatan || 'N/A';
                            var status = item.status || 'N/A';
                            
                            // Clean up zero values untuk mereduksi visual clutter
                            var biayaText = parseFloat(item.biaya) > 0 ? formatRupiah(item.biaya) : '';
                            var jumlahText = parseFloat(item.jumlah) > 0 ? parseFloat(item.jumlah) : '';
                            var totalbiayaText = parseFloat(item.totalbiaya) !== 0 ? formatRupiah(item.totalbiaya) : '';
                            var statusText = (status === '-' || status === '') ? '' : status;

                            html += '<tr>';
                            html += '<td>' + (no || '') + '</td>';
                            html += '<td>' + (nm_perawatan) + '</td>';
                            html += '<td>' + (statusText) + '</td>';
                            html += '<td class="text-end">' + (biayaText) + '</td>';
                            html += '<td class="text-center">' + (jumlahText) + '</td>';
                            html += '<td class="text-end">' + (totalbiayaText) + '</td>';
                            html += '</tr>';
                            
                            var totalbiayaNum = parseFloat(item.totalbiaya) || 0;
                            if (status !== '' && status !== '-') {
                                grandTotal += totalbiayaNum;
                            }
                        });
                    } else {
                        html += '<tr><td colspan="6" class="text-center">Tidak ada data detail billing ditemukan.</td></tr>';
                    }
                    
                    html += '</tbody><tfoot style="border-top: 2px solid #333;">';
                    html += '<tr><th colspan="5" class="text-end h5">Grand Total:</th><th class="text-end h5">' + formatRupiah(grandTotal) + '</th></tr>';
                    html += '</tfoot></table>';
                    
                    $("#isi-nota-container").html(html);
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $("#isi-nota-container").html("<p class='text-danger'>Gagal memuat data. Status: " + textStatus + ", Error: " + errorThrown + "</p>");
                }
            });
        });
        
    function loadCharts() {
        var params = {
            tgl_awal: $('input[name="tgl_awal"]').val(),
            jam_awal: $('input[name="jam_awal"]').val(),
            tgl_akhir: $('input[name="tgl_akhir"]').val(),
            jam_akhir: $('input[name="jam_akhir"]').val(),
            kd_pj: $('select[name="kd_pj"]').val(),
            status_bayar: $('select[name="status_bayar"]').val()
        };

        $.ajax({
            url: 'api/data_billing_global_chart.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(data) {
                renderPieChart(data.pie);
                renderLineChart(data.line);
            },
            error: function(err) {
                console.error("Gagal memuat data chart:", err);
            }
        });
    }

    function renderPieChart(pieData) {
        var ctx = document.getElementById("chartPiePenjamin");
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
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        padding: 15,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                var label = context.label || '';
                                var value = context.parsed;
                                return label + ': ' + formatRupiah(value);
                            }
                        }
                    }
                },
                cutout: '70%',
            },
        });
    }

    function renderLineChart(lineData) {
        var ctx = document.getElementById("chartLineTren");
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
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: "rgb(255,255,255)",
                        bodyColor: "#858796",
                        titleColor: '#6e707e',
                        borderColor: '#dddfeb',
                        borderWidth: 1,
                        padding: 15,
                        displayColors: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        callbacks: {
                            label: function(context) {
                                var label = context.dataset.label || '';
                                return label + ': ' + formatRupiah(context.parsed.y);
                            }
                        }
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
                            callback: function(value) { return formatRupiah(value); }
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
        
    });
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>