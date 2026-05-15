<?php
/*
 * File: kunjungan_ralan.php
 * Deskripsi: Monitoring Billing Ralan dengan Fitur Audit Mundur.
 */
$page_title = "Billing Rawat Jalan & Audit";
require_once('includes/header.php');
?>

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<style>
/* === SKELETON LOADER LAZY BILLING === */
.skeleton-text {
    display: inline-block;
    width: 80px;
    height: 14px;
    background: linear-gradient(90deg, #e0e0e0 25%, #f5f5f5 50%, #e0e0e0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    border-radius: 4px;
    vertical-align: middle;
}
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
html.theme-glass-solid .skeleton-text,
html.theme-glass-animated .skeleton-text {
    background: linear-gradient(90deg, rgba(255,255,255,0.1) 25%, rgba(255,255,255,0.25) 50%, rgba(255,255,255,0.1) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
}
</style>

<div class="container-fluid">
    
    <div class="card shadow-sm mb-4 border-start border-4 border-primary">
        <div class="card-body py-3">
            <form id="formFilter">
                <div class="row align-items-end g-2">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Dari Tanggal</label>
                        <input type="date" id="tgl_awal" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted mb-1">Sampai Tanggal</label>
                        <input type="date" id="tgl_akhir" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="chk_semua">
                            <label class="form-check-label small" for="chk_semua">
                                <strong>Audit Mode</strong> (Tampilkan Semua s.d Hari Ini)
                            </label>
                        </div>
                        <small class="text-muted" style="font-size: 0.7rem;">Cek seluruh tunggakan masa lalu.</small>
                    </div>
                    <div class="col-md-3">
                        <button type="button" onclick="reloadTable()" class="btn btn-sm btn-primary w-100 fw-bold">
                            <i class="fas fa-filter me-1"></i> Terapkan Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pasien Belum Bayar</h6>
            <div>
                <button onclick="reloadTable()" class="btn btn-sm btn-light border"><i class="fas fa-sync-alt text-gray-500"></i></button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle table-sm" id="tableRalan" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th width="12%">Waktu Reg</th>
                            <th width="20%">No. Rawat / Pasien</th>
                            <th width="20%">Dokter / Poli</th>
                            <th width="12%">Penjamin</th>
                            <th class="text-end">Biaya Obat</th>
                            <th class="text-end bg-success text-white">Total Tagihan</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetailBilling" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Rincian Billing Rawat Jalan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between bg-light p-2 mb-2 rounded border">
                    <div><strong>Pasien:</strong> <span id="lbl-pasien">-</span></div>
                    <div><strong>No. Rawat:</strong> <span id="lbl-norawat">-</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover" style="font-size: 0.85rem;">
                        <thead class="table-dark text-center">
                            <tr>
                                <th width="20%">Kategori</th>
                                <th width="30%">Item / Tindakan</th>
                                <th width="15%">Biaya</th>
                                <th width="5%">Jml</th>
                                <th width="15%">Tambahan</th>
                                <th width="15%">Total</th>
                            </tr>
                        </thead>
                        <tbody id="bodyDetailBilling"></tbody>
                        <tfoot class="table-light fw-bold fs-5">
                            <tr>
                                <td colspan="5" class="text-end">TOTAL TAGIHAN:</td>
                                <td class="text-end text-primary" id="lbl-total">0</td>
                            </tr>
                        </tfoot>
                    </table>
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
    var tableRalan;

    function formatRupiah(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }

    $(document).ready(function() {
        // Logika UX: Jika 'Audit Mode' dicentang, disable tanggal input
        $('#chk_semua').change(function() {
            if($(this).is(':checked')) {
                $('#tgl_awal, #tgl_akhir').prop('disabled', true).addClass('bg-light');
            } else {
                $('#tgl_awal, #tgl_akhir').prop('disabled', false).removeClass('bg-light');
            }
        });

        tableRalan = $('#tableRalan').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "api/data_kunjungan_ralan.php",
                "type": "GET",
                "global": false, // Matikan overlay loading global agar tidak mengganggu saat mengetik/search
                "data": function(d) {
                    // KIRIM PARAMETER FILTER KE BACKEND
                    d.mode = $('#chk_semua').is(':checked') ? 'semua' : 'periode';
                    d.tgl_awal = $('#tgl_awal').val();
                    d.tgl_akhir = $('#tgl_akhir').val();
                }
            },
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Semua"]],
            "dom": 'Bfrtip',
            "buttons": [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-1"></i> Export Excel (pilih dulu [Show all rows])',
                    className: 'btn btn-success btn-sm mb-3',
                    title: 'Laporan Billing Rawat Jalan',
                    exportOptions: {
                        columns: ':visible:not(:last-child)', // Jangan export kolom Aksi
                        format: {
                            body: function(data, row, column, node) {
                                var str = (data === null || data === undefined) ? '' : String(data);

                                // 1. KOLOM RUPIAH (Biaya Obat & Total Tagihan)
                                if (column === 4 || column === 5) {
                                     // Regex ini otomatis membuang huruf/html, sisakan angka, koma, minus
                                     return str.replace(/[^\d,-]/g, '').replace(',', '.');
                                }
                                
                                // 2. KOLOM TEKS (Pasien, Dokter, Penjamin, Status)
                                // Jika mengandung tag HTML
                                if (str.indexOf('<') > -1) {
                                    // Ganti <br> dengan " - " agar baris baru terbaca rapi di Excel
                                    // Lalu hapus semua tag HTML (<...>)
                                    return str.replace(/<br\s*\/?>/gi, " - ").replace(/<[^>]+>/g, "").trim();
                                }

                                return data;
                            }
                        }
                    }
                },
                {
                    extend: 'pageLength',
                    className: 'btn btn-secondary btn-sm mb-3'
                }
            ],
            "order": [], 
            "createdRow": function(row, data, dataIndex) {
                if (data.is_anomali === true) {
                    $(row).addClass('table-warning border-start border-warning border-4');
                }
            },
        "columns": [
                { "data": "waktu" },
                { 
                    "data": null,
                    "render": function(data) {
                        return `<b>${data.no_rawat}</b><br>${data.pasien} <br><small class="text-muted">${data.rm}</small>`;
                    }
                },
                { 
                    "data": null,
                    "render": function(data) {
                        return `<b>${data.poli}</b><br><small>${data.dokter}</small>`;
                    }
                },
                { 
                    "data": null,
                    "render": function(data) {
                        let penjamin = data.penjamin.toLowerCase();
                        let badgeClass = 'bg-secondary'; 
                        if (penjamin.includes('bpjs')) badgeClass = 'bg-success';
                        else if (penjamin.includes('umum')) badgeClass = 'bg-primary';
                        else if (penjamin.includes('asuransi')) badgeClass = 'bg-info text-dark';
                        return `<span class="badge ${badgeClass}">${data.penjamin}</span>`;
                    }
                },
                { 
                    "data": "biaya_obat", 
                    "className": "text-end",
                    "render": function(data, type, row) {
                        if (data === null) return `<span class="skeleton-cell" data-norawat="${row.no_rawat}" data-col="biaya_obat"><span class="skeleton-text"></span></span>`;
                        return data;
                    }
                },
                { 
                    "data": "estimasi", 
                    "className": "text-end fw-bold text-success",
                    "render": function(data, type, row) {
                        if (data === null) return `<span class="skeleton-cell" data-norawat="${row.no_rawat}" data-col="estimasi"><span class="skeleton-text"></span></span>`;
                        return data;
                    }
                },
                { 
                    "data": "status",
                    "className": "text-center",
                    "render": function(data) {
                        if(data === 'Batal') return `<span class="badge bg-danger">BATAL</span>`;
                        if(data === 'Sudah') return `<span class="badge bg-success">Sudah</span>`;
                        return `<span class="badge bg-secondary">${data}</span>`;
                    }
                },
                { 
                    "data": null, "className": "text-center", 
                    "render": function(data, type, row) {
                        return `<button class="btn btn-sm btn-info text-white shadow-sm" 
                                onclick="showDetailBilling('${row.no_rawat}', '${row.pasien.replace(/'/g, "\\'")}')">
                                <i class="fas fa-eye"></i>
                                </button>`;
                    }
                }
            ],
            "drawCallback": function() {
                loadBillingAsync('ralan');
            }
        });
    });

    function reloadTable() { tableRalan.ajax.reload(); }

    // =====================================================
    // LAZY BILLING LOADER — Fetch biaya async per baris
    // =====================================================
    var _billingQueue = [];
    var _billingRunning = 0;
    var _billingConcurrency = 3; // Max 3 request paralel

    function loadBillingAsync(type) {
        // Kumpulkan semua sel skeleton yang belum diisi
        var cells = document.querySelectorAll('.skeleton-cell');
        _billingQueue = [];
        cells.forEach(function(el) {
            var noRawat = el.getAttribute('data-norawat');
            // Hindari duplikat di queue
            if (!_billingQueue.some(function(i){ return i.no_rawat === noRawat; })) {
                _billingQueue.push({ no_rawat: noRawat });
            }
        });
        _processBillingQueue(type);
    }

    function _processBillingQueue(type) {
        while (_billingRunning < _billingConcurrency && _billingQueue.length > 0) {
            var item = _billingQueue.shift();
            _billingRunning++;
            _fetchOneBilling(item.no_rawat, type);
        }
    }

    function _fetchOneBilling(noRawat, type) {
        var apiUrl = 'api/hitung_estimasi_ralan.php';
        $.ajax({
            url: apiUrl,
            type: 'GET',
            global: false,  // Jangan trigger globalLoadingOverlay
            data: { no_rawat: noRawat },
            dataType: 'json',
            success: function(res) {
                // Update sel biaya_obat
                document.querySelectorAll('.skeleton-cell[data-norawat="' + noRawat + '"][data-col="biaya_obat"]').forEach(function(el) {
                    el.outerHTML = res.biaya_obat || '-';
                });
                // Update sel estimasi
                document.querySelectorAll('.skeleton-cell[data-norawat="' + noRawat + '"][data-col="estimasi"]').forEach(function(el) {
                    el.outerHTML = '<span class="fw-bold text-success">' + (res.estimasi || '-') + '</span>';
                });
            },
            error: function() {
                document.querySelectorAll('.skeleton-cell[data-norawat="' + noRawat + '"]').forEach(function(el) {
                    el.outerHTML = '<span class="text-muted">-</span>';
                });
            },
            complete: function() {
                _billingRunning--;
                _processBillingQueue(type);
            }
        });
    }

    function showDetailBilling(noRawat, namaPasien) {
        $('#lbl-pasien').text(namaPasien);
        $('#lbl-norawat').text(noRawat);
        $('#bodyDetailBilling').html('<tr><td colspan="6" class="text-center py-4"><div class="spinner-border text-primary"></div><br>Memuat rincian...</td></tr>');
        $('#lbl-total').text('...');
        $('#modalDetailBilling').modal('show');

        $.ajax({
            url: 'api/data_rincian_billing.php', 
            type: 'GET',
            data: { no_rawat: noRawat },
            dataType: 'json',
            success: function(res) {
                var html = '';
                if (res.data && res.data.length > 0) {
                    res.data.forEach(function(item) {
                        if (item.is_header) {
                            html += `<tr class="table-secondary fw-bold"><td colspan="6">${item.keterangan} ${item.tagihan}</td></tr>`;
                        } else {
                            var style = (item.total < 0) ? 'text-danger fw-bold' : '';
                            html += `<tr>
                                        <td>${item.keterangan}</td>
                                        <td>${item.tagihan}</td>
                                        <td class="text-end">${formatRupiah(item.biaya)}</td>
                                        <td class="text-center">${item.jumlah}</td>
                                        <td class="text-end">${formatRupiah(item.tambahan)}</td>
                                        <td class="text-end fw-bold ${style}">${formatRupiah(item.total)}</td>
                                     </tr>`;
                        }
                    });
                } else {
                    html = '<tr><td colspan="6" class="text-center">Tidak ada data tagihan.</td></tr>';
                }
                $('#bodyDetailBilling').html(html);
                $('#lbl-total').text(res.total_rupiah);
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>
<?php require_once('includes/footer.php'); ?>