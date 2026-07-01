<?php
/*
 * File: laporan_triase_igd.php
 * Modul: RL 3.3 - Data Triase IGD
 * Deskripsi: Menampilkan data triase IGD dari Khanza untuk kebutuhan RM/export Excel.
 */
$page_title = "RL 3.3 Triase IGD";
require_once('includes/header.php');

$tgl_awal_default = date('Y-m-01');
$tgl_akhir_default = date('Y-m-d');
?>

<style>
    .triase-stat {
        border-radius: 10px;
        padding: 16px 18px;
        color: #fff;
        min-height: 96px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .triase-stat h3 {
        margin: 0;
        font-weight: 700;
        line-height: 1;
    }
    .triase-stat .case-name {
        display: block;
        max-width: 100%;
        font-size: .82rem;
        line-height: 1.2;
        opacity: .92;
        white-space: normal;
    }
    .triase-stat small {
        opacity: .86;
        font-weight: 600;
    }
    .triase-stat i {
        font-size: 2rem;
        opacity: .55;
    }
    table.dataTable thead th {
        background: #f8f9fc;
        font-size: .8rem;
        font-weight: 700;
        color: #333;
        border-bottom: 2px solid #dee2e6 !important;
        white-space: nowrap;
    }
    table.dataTable tbody td {
        font-size: .8rem;
        vertical-align: middle;
        white-space: nowrap;
    }
    .triase-table-wrap {
        overflow-x: auto;
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-heartbeat text-danger me-2"></i> RL 3.3 - Data Triase IGD</h4>
        <small class="text-muted">Data Triase IGD Khanza untuk Kegiatan Pelayanan Rawat Darurat</small>
    </div>
    <a href="kelola_data_rm.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Dari Tgl. Triase</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Sampai Tgl. Triase</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Keyword</label>
                <input type="text" class="form-control form-control-sm" id="keyword" placeholder="No rawat, RM, nama, kasus...">
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-primary w-100" id="btnLoad">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-success w-100" id="btnExport" disabled>
                    <i class="fas fa-file-excel me-1"></i> Export Excel
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3" id="caseStatsRow">
    <div class="col-12">
        <div class="triase-stat" style="background:linear-gradient(135deg,#4e73df,#224abe);">
            <div><h3>0</h3><small class="case-name">Memuat data macam kasus...</small></div>
            <i class="fas fa-spinner fa-spin"></i>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Data Triase IGD</h6>
        <span class="badge bg-light text-dark border" id="periodeInfo">-</span>
    </div>
    <div class="card-body">
        <div class="table-responsive triase-table-wrap">
            <table class="table table-bordered table-hover table-striped" id="tblTriase" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. Rawat</th>
                        <th>No. RM</th>
                        <th>Nama Pasien</th>
                        <th>JK</th>
                        <th>Umur</th>
                        <th>Tgl. Kunjungan</th>
                        <th>Cara Masuk</th>
                        <th>Transportasi</th>
                        <th>Alasan Kedatangan</th>
                        <th>Keterangan</th>
                        <th>Kode Kasus</th>
                        <th>Macam Kasus</th>
                        <th>Tensi</th>
                        <th>Nadi</th>
                        <th>Respirasi</th>
                        <th>Suhu</th>
                        <th>Saturasi O2</th>
                        <th>Nyeri</th>
                        <th>Anamnesa Singkat</th>
                        <th>Petugas</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
let dtTriase;
let dataTriase = [];

const ID_LANG_TRIASE = {
    search: 'Cari:',
    lengthMenu: 'Tampilkan _MENU_ data',
    info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data',
    infoFiltered: '(difilter dari _MAX_ total)',
    zeroRecords: 'Data tidak ditemukan',
    paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
};

function fmtNum(value) {
    return Number(value || 0).toLocaleString('id-ID');
}

function fmtTanggal(value) {
    if (!value || value === '-') return '-';
    const parts = value.split(' ');
    const datePart = parts[0] && parts[0].includes('-') ? parts[0].split('-').reverse().join('-') : parts[0];
    return parts[1] ? datePart + ' ' + parts[1] : datePart;
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function(char) {
        return ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' })[char];
    });
}

function renderCaseStats(kasus) {
    const entries = Object.entries(kasus || {});
    const colors = [
        ['#e74a3b', '#be2617'],
        ['#1cc88a', '#13855c'],
        ['#f6c23e', '#dda20a'],
        ['#4e73df', '#224abe'],
        ['#36b9cc', '#258391'],
        ['#858796', '#5a5c69']
    ];

    if (entries.length === 0) {
        $('#caseStatsRow').html(`
            <div class="col-12">
                <div class="triase-stat" style="background:linear-gradient(135deg,#858796,#5a5c69);">
                    <div><h3>0</h3><small class="case-name">Belum ada data macam kasus pada periode ini</small></div>
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
        `);
        return;
    }

    const html = entries.map(function(item, index) {
        const color = colors[index % colors.length];
        return `
            <div class="col-xl-3 col-md-6">
                <div class="triase-stat" style="background:linear-gradient(135deg,${color[0]},${color[1]});">
                    <div>
                        <h3>${fmtNum(item[1])}</h3>
                        <small class="case-name">${escapeHtml(item[0])}</small>
                    </div>
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
        `;
    }).join('');

    $('#caseStatsRow').html(html);
}

function initTable() {
    if (dtTriase) {
        dtTriase.clear();
        dtTriase.rows.add(dataTriase);
        dtTriase.draw();
        return;
    }

    dtTriase = $('#tblTriase').DataTable({
        data: dataTriase,
        language: ID_LANG_TRIASE,
        pageLength: 25,
        scrollX: true,
        order: [[6, 'desc']],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'RL 3.3 Data Triase IGD',
                filename: function() {
                    return 'RL33_Triase_IGD_' + $('#tglAwal').val().replace(/-/g, '') + '_sd_' + $('#tglAkhir').val().replace(/-/g, '');
                },
                className: 'd-none',
                exportOptions: { columns: ':visible' }
            }
        ],
        columns: [
            { data: null, render: (d,t,r,i) => i.row + 1 },
            { data: 'no_rawat' },
            { data: 'no_rkm_medis' },
            { data: 'nm_pasien' },
            { data: 'jk' },
            { data: 'umur' },
            { data: 'tgl_kunjungan', render: d => fmtTanggal(d) },
            { data: 'cara_masuk' },
            { data: 'alat_transportasi' },
            { data: 'alasan_kedatangan' },
            { data: 'keterangan_kedatangan' },
            { data: 'kode_kasus' },
            { data: 'macam_kasus' },
            { data: 'tekanan_darah' },
            { data: 'nadi' },
            { data: 'pernapasan' },
            { data: 'suhu' },
            { data: 'saturasi_o2' },
            { data: 'nyeri' },
            { data: 'anamnesa_singkat' },
            { data: 'petugas_sekunder' }
        ]
    });
}

function loadData() {
    const tglAwal = $('#tglAwal').val();
    const tglAkhir = $('#tglAkhir').val();
    const keyword = $('#keyword').val();

    if (!tglAwal || !tglAkhir) {
        alert('Pilih periode tanggal triase.');
        return;
    }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.getJSON('api/data_triase_igd.php', { tgl_awal: tglAwal, tgl_akhir: tglAkhir, keyword: keyword }, function(res) {
        if (!res.success) {
            alert(res.message || 'Gagal memuat data triase IGD.');
            return;
        }

        dataTriase = res.data || [];
        initTable();

        const summary = res.summary || {};
        renderCaseStats(summary.kasus);
        $('#periodeInfo').text(fmtTanggal(tglAwal) + ' s/d ' + fmtTanggal(tglAkhir));
        $('#btnExport').prop('disabled', dataTriase.length === 0);
    }).fail(function() {
        alert('Gagal memuat data triase IGD. Pastikan koneksi dan API tersedia.');
    }).always(function() {
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
    });
}

$('#btnLoad').on('click', loadData);
$('#keyword').on('keydown', function(e) {
    if (e.key === 'Enter') {
        loadData();
    }
});
$('#btnExport').on('click', function() {
    if (dtTriase) {
        dtTriase.button('.buttons-excel').trigger();
    }
});

$(document).ready(function() {
    initTable();
    loadData();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
