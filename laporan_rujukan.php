<?php
/*
 * File: laporan_rujukan.php
 * Modul: RL 3.13 — Data Rujukan Masuk & Keluar
 * Deskripsi: Menampilkan data rujukan masuk (rujuk_masuk) dan rujuk keluar (rujuk)
 *            dengan filter tanggal dan export Excel.
 */
$page_title = "RL 3.13 Rujukan";
require_once('includes/header.php');

$tgl_awal_default  = date('Y-m-01');
$tgl_akhir_default = date('Y-m-d');
?>

<style>
    .nav-tabs .nav-link { font-weight: 600; color: #555; border: none; padding: 10px 24px; }
    .nav-tabs .nav-link.active {
        color: #fff; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px 10px 0 0; border: none;
    }
    .nav-tabs { border-bottom: 2px solid #e9ecef; }
    .tab-pane { padding-top: 20px; }
    .stat-rujuk {
        border-radius: 12px; padding: 16px 20px; color: #fff;
    }
    .stat-rujuk h3 { margin: 0; font-weight: 700; }
    .stat-rujuk small { opacity: 0.8; }
    table.dataTable thead th {
        background: #f8f9fc; font-size: 0.82rem; font-weight: 700;
        color: #333; border-bottom: 2px solid #dee2e6 !important;
    }
    table.dataTable tbody td { font-size: 0.82rem; vertical-align: middle; }
</style>

<!-- Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-exchange-alt text-primary me-2"></i> RL 3.13 — Data Rujukan</h4>
        <small class="text-muted">Rujukan Masuk & Rujuk Keluar antar Fasilitas Kesehatan</small>
    </div>
</div>

<!-- Filter -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Dari Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary px-4" id="btnLoad">
                    <i class="fas fa-sync-alt me-1"></i> Muat Data
                </button>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-sm btn-success me-1" id="btnExport" disabled>
                    <i class="fas fa-file-excel me-1"></i> Export Excel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-3" id="statRow">
    <div class="col">
        <div class="stat-rujuk" style="background:linear-gradient(135deg,#667eea,#764ba2);">
            <h3 id="stMasuk">0</h3><small>Rujukan Masuk</small>
        </div>
    </div>
    <div class="col">
        <div class="stat-rujuk" style="background:linear-gradient(135deg,#f093fb,#f5576c);">
            <h3 id="stKeluar">0</h3><small>Rujuk Keluar</small>
        </div>
    </div>
    <div class="col">
        <div class="stat-rujuk" style="background:linear-gradient(135deg,#f6d365,#fda085);">
            <h3 id="stInternal">0</h3><small>Rujuk Masuk Poli</small>
        </div>
    </div>
    <div class="col">
        <div class="stat-rujuk" style="background:linear-gradient(135deg,#43e97b,#38f9d7);">
            <h3 id="stTotal">0</h3><small>Total</small>
        </div>
    </div>
    <div class="col">
        <div class="stat-rujuk" style="background:linear-gradient(135deg,#4facfe,#00f2fe);">
            <h3 id="stPeriode">-</h3><small>Periode</small>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" id="rujukTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tabMasuk">
            <i class="fas fa-sign-in-alt me-1"></i> Rujukan Masuk <span class="badge bg-secondary ms-1" id="badgeMasuk">0</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabKeluar">
            <i class="fas fa-sign-out-alt me-1"></i> Rujuk Keluar <span class="badge bg-secondary ms-1" id="badgeKeluar">0</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tabInternal">
            <i class="fas fa-clinic-medical me-1"></i> Rujuk Masuk Internal Poli <span class="badge bg-secondary ms-1" id="badgeInternal">0</span>
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Rujukan Masuk -->
    <div class="tab-pane fade show active" id="tabMasuk">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="tblMasuk" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Perujuk / Rujukan dari</th>
                        <th>Alamat Perujuk</th>
                        <th>No. Rujuk</th>
                        <th>No. Rawat</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Umur</th>
                        <th>JK</th>
                        <th>Tgl. Masuk</th>
                        <th>J.M. Perujuk</th>
                        <th>Dokter Perujuk</th>
                        <th>Diagnosa</th>
                        <th>Poli</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Rujuk Keluar -->
    <div class="tab-pane fade" id="tabKeluar">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="tblKeluar" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. Rujuk</th>
                        <th>No. Rawat</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Umur</th>
                        <th>JK</th>
                        <th>Rujuk Ke</th>
                        <th>Tgl. Rujuk</th>
                        <th>Jam</th>
                        <th>Diagnosa</th>
                        <th>Dokter Perujuk</th>
                        <th>Kategori</th>
                        <th>Ambulance</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Rujuk Masuk Poli -->
    <div class="tab-pane fade" id="tabInternal">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="tblInternal" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. Rawat</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>Umur</th>
                        <th>JK</th>
                        <th>Tgl. Registrasi</th>
                        <th>Poli Tujuan</th>
                        <th>Dokter</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let dtMasuk, dtKeluar, dtInternal;
let dataMasuk = [], dataKeluar = [], dataInternal = [];
const ID_LANG = {
    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data', infoFiltered: '(difilter dari _MAX_ total)', zeroRecords: 'Data tidak ditemukan',
    paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
};

function fmtRp(n) {
    return 'Rp ' + Number(n||0).toLocaleString('id-ID');
}

function initTables() {
    if (dtMasuk) dtMasuk.destroy();
    if (dtKeluar) dtKeluar.destroy();
    if (dtInternal) dtInternal.destroy();

    dtMasuk = $('#tblMasuk').DataTable({
        data: dataMasuk,
        language: ID_LANG,
        pageLength: 25,
        order: [[9, 'desc']],
        columns: [
            { data: null, render: (d,t,r,i) => i.row+1 },
            { data: 'perujuk' },
            { data: 'alamat' },
            { data: 'no_rujuk' },
            { data: 'no_rawat' },
            { data: 'no_rkm_medis' },
            { data: 'nm_pasien' },
            { data: 'umur' },
            { data: 'jk' },
            { data: 'tgl_masuk' },
            { data: 'jm_perujuk', render: d => fmtRp(d) },
            { data: 'dokter_perujuk' },
            { data: 'kd_penyakit_desc' },
            { data: 'nm_poli' }
        ]
    });

    dtKeluar = $('#tblKeluar').DataTable({
        data: dataKeluar,
        language: ID_LANG,
        pageLength: 25,
        order: [[8, 'desc']],
        columns: [
            { data: null, render: (d,t,r,i) => i.row+1 },
            { data: 'no_rujuk' },
            { data: 'no_rawat' },
            { data: 'no_rkm_medis' },
            { data: 'nm_pasien' },
            { data: 'umur' },
            { data: 'jk' },
            { data: 'rujuk_ke' },
            { data: 'tgl_rujuk' },
            { data: 'jam' },
            { data: 'keterangan_diagnosa' },
            { data: 'nm_dokter' },
            { data: 'kat_rujuk' },
            { data: 'ambulance' },
            { data: 'keterangan' }
        ]
    });

    dtInternal = $('#tblInternal').DataTable({
        data: dataInternal,
        language: ID_LANG,
        pageLength: 25,
        order: [[6, 'desc']],
        columns: [
            { data: null, render: (d,t,r,i) => i.row+1 },
            { data: 'no_rawat' },
            { data: 'no_rkm_medis' },
            { data: 'nm_pasien' },
            { data: 'umur' },
            { data: 'jk' },
            { data: 'tgl_registrasi' },
            { data: 'nm_poli' },
            { data: 'nm_dokter' }
        ]
    });
}

// Load data from API
function loadData() {
    const tglAwal  = $('#tglAwal').val();
    const tglAkhir = $('#tglAkhir').val();
    if (!tglAwal || !tglAkhir) { alert('Pilih periode tanggal'); return; }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Memuat...');

    $.getJSON('api/data_rujukan.php', { tgl_awal: tglAwal, tgl_akhir: tglAkhir }, function(res) {
        dataMasuk    = res.masuk    || [];
        dataKeluar   = res.keluar   || [];
        dataInternal = res.internal || [];

        initTables();

        $('#stMasuk').text(dataMasuk.length);
        $('#stKeluar').text(dataKeluar.length);
        $('#stInternal').text(dataInternal.length);
        $('#stTotal').text(dataMasuk.length + dataKeluar.length + dataInternal.length);
        $('#badgeMasuk').text(dataMasuk.length);
        $('#badgeKeluar').text(dataKeluar.length);
        $('#badgeInternal').text(dataInternal.length);

        const fmtTgl = d => d.split('-').reverse().join('-');
        $('#stPeriode').html(fmtTgl(tglAwal) + '<br>s/d ' + fmtTgl(tglAkhir));

        $('#btnExport').prop('disabled', dataMasuk.length === 0 && dataKeluar.length === 0 && dataInternal.length === 0);
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Muat Data');
    }).fail(function() {
        alert('Gagal memuat data. Pastikan API tersedia.');
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Muat Data');
    });
}

// Export Excel
function exportExcel() {
    const tglAwal  = $('#tglAwal').val();
    const tglAkhir = $('#tglAkhir').val();
    const wb = XLSX.utils.book_new();

    // Sheet 1: Rujukan Masuk
    if (dataMasuk.length > 0) {
        const headers = ['No','Perujuk/Rujukan','Alamat','No.Rujuk','No.Rawat','No.RM','Nama Pasien','Umur','JK','Tgl.Masuk','J.M.Perujuk','Dokter Perujuk','Diagnosa','Poli'];
        const rows = dataMasuk.map((r,i) => [
            i+1, r.perujuk||'', r.alamat||'', r.no_rujuk||'', r.no_rawat||'',
            r.no_rkm_medis||'', r.nm_pasien||'', r.umur||'', r.jk||'', r.tgl_masuk||'',
            r.jm_perujuk||0, r.dokter_perujuk||'', r.kd_penyakit_desc||'', r.nm_poli||''
        ]);
        const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
        ws['!cols'] = [{wch:4},{wch:25},{wch:20},{wch:22},{wch:22},{wch:12},{wch:28},{wch:8},{wch:4},{wch:12},{wch:14},{wch:24},{wch:35},{wch:24}];
        // Bold header
        for (let C = 0; C < headers.length; C++) {
            const addr = XLSX.utils.encode_cell({r:0,c:C});
            if (!ws[addr]) continue;
            ws[addr].s = { font:{bold:true,color:{rgb:'FFFFFF'}}, fill:{fgColor:{rgb:'4e73df'}}, alignment:{horizontal:'center'} };
        }
        // Format JM Perujuk as number
        for (let R = 1; R <= rows.length; R++) {
            const addr = XLSX.utils.encode_cell({r:R,c:10});
            if (ws[addr] && ws[addr].v !== '') { ws[addr].t='n'; ws[addr].z='#,##0'; }
        }
        XLSX.utils.book_append_sheet(wb, ws, 'Rujukan Masuk');
    }

    // Sheet 2: Rujuk Keluar
    if (dataKeluar.length > 0) {
        const headers = ['No','No.Rujuk','No.Rawat','No.RM','Nama Pasien','Umur','JK','Rujuk Ke','Tgl.Rujuk','Jam','Diagnosa','Dokter Perujuk','Kategori','Ambulance','Keterangan'];
        const rows = dataKeluar.map((r,i) => [
            i+1, r.no_rujuk||'', r.no_rawat||'', r.no_rkm_medis||'', r.nm_pasien||'',
            r.umur||'', r.jk||'', r.rujuk_ke||'', r.tgl_rujuk||'', r.jam||'',
            r.keterangan_diagnosa||'', r.nm_dokter||'', r.kat_rujuk||'', r.ambulance||'', r.keterangan||''
        ]);
        const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
        ws['!cols'] = [{wch:4},{wch:22},{wch:22},{wch:12},{wch:28},{wch:8},{wch:4},{wch:30},{wch:12},{wch:10},{wch:35},{wch:26},{wch:14},{wch:12},{wch:20}];
        for (let C = 0; C < headers.length; C++) {
            const addr = XLSX.utils.encode_cell({r:0,c:C});
            if (!ws[addr]) continue;
            ws[addr].s = { font:{bold:true,color:{rgb:'FFFFFF'}}, fill:{fgColor:{rgb:'f5576c'}}, alignment:{horizontal:'center'} };
        }
        XLSX.utils.book_append_sheet(wb, ws, 'Rujuk Keluar');
    }

    // Sheet 3: Rujuk Masuk Poli
    if (dataInternal.length > 0) {
        const headers = ['No','No.Rawat','No.RM','Nama Pasien','Umur','JK','Tgl.Registrasi','Poli Tujuan','Dokter'];
        const rows = dataInternal.map((r,i) => [
            i+1, r.no_rawat||'', r.no_rkm_medis||'', r.nm_pasien||'',
            r.umur||'', r.jk||'', r.tgl_registrasi||'', r.nm_poli||'', r.nm_dokter||''
        ]);
        const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
        ws['!cols'] = [{wch:4},{wch:22},{wch:12},{wch:28},{wch:8},{wch:4},{wch:14},{wch:28},{wch:26}];
        for (let C = 0; C < headers.length; C++) {
            const addr = XLSX.utils.encode_cell({r:0,c:C});
            if (!ws[addr]) continue;
            ws[addr].s = { font:{bold:true,color:{rgb:'FFFFFF'}}, fill:{fgColor:{rgb:'fda085'}}, alignment:{horizontal:'center'} };
        }
        XLSX.utils.book_append_sheet(wb, ws, 'Rujuk Masuk Internal Poli');
    }

    if (wb.SheetNames.length === 0) { alert('Tidak ada data untuk di-export.'); return; }
    const tgl = tglAwal.replace(/-/g,'') + '_sd_' + tglAkhir.replace(/-/g,'');
    XLSX.writeFile(wb, 'RL313_Rujukan_' + tgl + '.xlsx', { cellStyles: true });
}

// Event bindings
$('#btnLoad').on('click', loadData);
$('#btnExport').on('click', exportExcel);

// Auto-load on page open
$(document).ready(function() { loadData(); });
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
