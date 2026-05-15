<?php
/*
 * File: laporan_operasi_view.php (FIX V4 - FINAL JS RENDER)
 * - Fix: Menampilkan 3 Kolom di Modal Detail (Komponen, Penerima, Nominal).
 * - Sebelumnya JS hanya merender 2 kolom sehingga nama tidak muncul.
 */
$page_title = "Laporan Kamar Operasi (OK)";
require_once('includes/header.php');
require_once('includes/functions.php');

$tgl_awal = date('Y-m-01');
$tgl_akhir = date('Y-m-d');
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary"><i class="fas fa-filter me-2"></i>Filter Periode Operasi</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="date" class="form-control" id="tgl_awal" value="<?php echo $tgl_awal; ?>">
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" id="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="loadData()">Tampilkan</button>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="opTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="realisasi-tab" data-bs-toggle="tab" data-bs-target="#realisasi" type="button">
                <i class="fas fa-check-circle me-2 text-success"></i>Realisasi (Selesai)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="rencana-tab" data-bs-toggle="tab" data-bs-target="#rencana" type="button">
                <i class="fas fa-calendar-alt me-2 text-warning"></i>Rencana (Jadwal)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="opTabsContent">
        
        <div class="tab-pane fade show active" id="realisasi">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-start border-4 border-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Pendapatan OK</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="val-omzet">Loading...</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-4 border-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Pasien Operasi</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800" id="val-pasien">...</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-start border-4 border-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Proporsi Pasien</div>
                            <div class="small" id="val-proporsi">...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Top 10 Jenis Operasi Terbanyak</h6></div>
                        <div class="card-body"><div class="chart-bar" style="height: 300px;"><canvas id="chartTopOp"></canvas></div></div>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Rincian Operasi Selesai (Max 3000 Data)</h6></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tableRealisasi" class="table table-bordered table-striped table-sm" width="100%">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>No. Rawat</th>
                                            <th>Pasien</th>
                                            <th>Tindakan Operasi</th>
                                            <th>Operator</th>
                                            <th>Penjamin</th>
                                            <th class="text-end">Total Biaya</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="rencana">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-warning text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold"><i class="fas fa-clock me-2"></i>Jadwal Operasi Akan Datang</h6>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="status_booking" id="st_menunggu" value="Menunggu" checked onchange="loadRencana()">
                        <label class="btn btn-outline-light text-dark bg-white" for="st_menunggu">Menunggu</label>
                        <input type="radio" class="btn-check" name="status_booking" id="st_semua" value="Semua" onchange="loadRencana()">
                        <label class="btn btn-outline-light text-dark bg-white" for="st_semua">Semua</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tableRencana" class="table table-bordered table-hover" width="100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Jadwal (Tgl & Jam)</th>
                                    <th>No. RM / Pasien</th>
                                    <th>Rencana Tindakan</th>
                                    <th>Dokter Operator</th>
                                    <th>Penjamin</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBiaya" tabindex="-1">
    <div class="modal-dialog modal-lg"> 
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Rincian Biaya & Pelaksana Operasi</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-striped table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th width="30%">Komponen Biaya</th>
                            <th width="40%">Penerima / Keterangan</th> <th width="30%" class="text-end">Nominal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody id="bodyBiaya"></tbody>
                    <tfoot class="fw-bold bg-light">
                        <tr>
                            <td colspan="2" class="text-end">TOTAL BIAYA OPERASI:</td>
                            <td class="text-end text-primary" id="totalBiayaModal">0</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
    var tableRealisasi, tableRencana, chartTopOp;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        // Table Realisasi
        tableRealisasi = $('#tableRealisasi').DataTable({
            "dom": 'Bfrtip', "buttons": ['excel', 'print'], "pageLength": 10, "deferRender": true,
            "order": [[ 0, "desc" ]],
            "columns": [
                { "data": "tgl_operasi" },
                { "data": "no_rawat" },
                { "data": "nm_pasien" },
                { "data": "nm_perawatan" },
                { "data": "operator" },
                { "data": "png_jawab" },
                { "data": "total_biaya", className: "text-end fw-bold", render: function(d){return formatRupiah(d);} },
                { "data": null, className: "text-center", render: function(d,t,r){
                    return `<button class="btn btn-sm btn-info text-white" onclick="showDetail('${r.no_rawat}','${r.tgl_operasi}')"><i class="fas fa-search-plus me-1"></i> Rincian</button>`;
                }}
            ]
        });

        // Table Rencana
        tableRencana = $('#tableRencana').DataTable({
            "dom": 'Bfrtip', "buttons": ['excel', 'print'], "deferRender": true,
            "columns": [
                { "data": "tanggal", render: function(d,t,r){ return '<b>'+d+'</b><br>'+r.jam_mulai+' - '+r.jam_selesai; } },
                { "data": "no_rkm_medis", render: function(d,t,r){ return d+'<br>'+r.nm_pasien; } },
                { "data": "nm_perawatan" },
                { "data": "operator" },
                { "data": "png_jawab" },
                { "data": "status", render: function(d){ 
                    if(d=='Menunggu') return '<span class="badge bg-warning text-dark">'+d+'</span>';
                    if(d=='Batal') return '<span class="badge bg-danger">'+d+'</span>';
                    return '<span class="badge bg-success">'+d+'</span>'; 
                }}
            ]
        });

        // Data akan dimuat saat user klik tombol Tampilkan
    });

    function loadData() {
        var t1 = $('#tgl_awal').val();
        var t2 = $('#tgl_akhir').val();

        $.ajax({
            url: 'api/data_operasi.php', type: 'GET', data: {tgl_awal: t1, tgl_akhir: t2}, dataType: 'json',
            success: function(res) {
                $('#val-omzet').text(formatRupiah(res.summary.total_omzet));
                $('#val-pasien').text(res.summary.total_pasien);
                $('#val-proporsi').html(`BPJS: ${res.summary.bpjs} <br> Umum: ${res.summary.umum}`);
                tableRealisasi.clear().rows.add(res.data).draw();
                renderChart(res.chart_labels, res.chart_values);
            }
        });
        loadRencana();
    }

    function loadRencana() {
        var t1 = $('#tgl_awal').val();
        var t2 = $('#tgl_akhir').val();
        var status = $('input[name="status_booking"]:checked').val();
        $.ajax({
            url: 'api/data_rencana_operasi.php', type: 'GET', data: {tgl_awal: t1, tgl_akhir: t2, status: status}, dataType: 'json',
            success: function(res) { tableRencana.clear().rows.add(res.data).draw(); }
        });
    }

    function renderChart(labels, values) {
        var ctx = document.getElementById("chartTopOp").getContext('2d');
        if(chartTopOp) chartTopOp.destroy();
        chartTopOp = new Chart(ctx, {
            type: 'bar',
            data: { labels: labels, datasets: [{ label: 'Jumlah', data: values, backgroundColor: '#4e73df' }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: {display: false} } }
        });
    }

    function showDetail(noRawat, tgl) {
        $('#bodyBiaya').html('<tr><td colspan="3" class="text-center">Loading...</td></tr>');
        $('#modalBiaya').modal('show');
        $.ajax({
            url: 'api/data_detail_operasi.php', type: 'GET', data: {no_rawat: noRawat, tgl_operasi: tgl}, dataType: 'json',
            success: function(res) {
                var html = '';
                if(res && res.rincian) {
                    res.rincian.forEach(function(item){
                        // FIX: Menambahkan kolom 'item.penerima'
                        html += `<tr>
                                    <td>${item.komponen}</td>
                                    <td class="text-primary fw-bold">${item.penerima}</td>
                                    <td class="text-end">${formatRupiah(item.nilai)}</td>
                                 </tr>`;
                    });
                    $('#totalBiayaModal').text(formatRupiah(res.total));
                } else {
                    html = '<tr><td colspan="3" class="text-center">Data tidak ditemukan</td></tr>';
                }
                $('#bodyBiaya').html(html);
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>
<?php require_once('includes/footer.php'); ?>