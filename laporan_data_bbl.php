<?php
/*
 * File: laporan_data_bbl.php
 * Modul: Data BBL - Bayi Baru Lahir
 * Deskripsi: Menampilkan data pasien bayi dari modul DlgIKBBayi Khanza.
 */
$page_title = "Data BBL";
require_once('includes/header.php');

$tgl_awal_default = date('Y-m-01');
$tgl_akhir_default = date('Y-m-d');
?>

<style>
    .bbl-stat {
        border-radius: 10px;
        padding: 16px 18px;
        color: #fff;
        min-height: 96px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .bbl-stat h3 {
        margin: 0;
        font-weight: 700;
        line-height: 1;
    }
    .bbl-stat small {
        opacity: .86;
        font-weight: 600;
    }
    .bbl-stat i {
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
    .bbl-table-wrap {
        overflow-x: auto;
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-baby text-primary me-2"></i> Data BBL</h4>
        <small class="text-muted">Data Bayi Baru Lahir dari tabel pasien_bayi sesuai modul DlgIKBBayi</small>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Dari Tanggal Lahir</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Sampai Tanggal Lahir</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold mb-1">Jenis Kelamin</label>
                <select class="form-select form-select-sm" id="jk">
                    <option value="">Semua</option>
                    <option value="L">Laki-laki</option>
                    <option value="P">Perempuan</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100" id="btnLoad">
                    <i class="fas fa-sync-alt me-1"></i> Muat Data
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

<div class="row g-3 mb-3">
    <div class="col-xl-3 col-md-6">
        <div class="bbl-stat" style="background:linear-gradient(135deg,#4e73df,#224abe);">
            <div><h3 id="stTotal">0</h3><small>Total BBL</small></div>
            <i class="fas fa-baby"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="bbl-stat" style="background:linear-gradient(135deg,#36b9cc,#258391);">
            <div><h3 id="stLaki">0</h3><small>Laki-laki</small></div>
            <i class="fas fa-mars"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="bbl-stat" style="background:linear-gradient(135deg,#e83e8c,#b82069);">
            <div><h3 id="stPerempuan">0</h3><small>Perempuan</small></div>
            <i class="fas fa-venus"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="bbl-stat" style="background:linear-gradient(135deg,#1cc88a,#13855c);">
            <div><h3 id="stRerata">0</h3><small>Rerata BB / PB</small></div>
            <i class="fas fa-weight"></i>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Bayi Baru Lahir</h6>
        <span class="badge bg-light text-dark border" id="periodeInfo">-</span>
    </div>
    <div class="card-body">
        <div class="table-responsive bbl-table-wrap">
            <table class="table table-bordered table-hover table-striped" id="tblBBL" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>No. RM</th>
                        <th>Nama Bayi</th>
                        <th>JK</th>
                        <th>Tgl. Lahir</th>
                        <th>Jam</th>
                        <th>Umur</th>
                        <th>Tgl. Daftar</th>
                        <th>Nama Ibu</th>
                        <th>Umur Ibu</th>
                        <th>Nama Ayah</th>
                        <th>Umur Ayah</th>
                        <th>Alamat</th>
                        <th>BB</th>
                        <th>PB</th>
                        <th>LK</th>
                        <th>Proses Lahir</th>
                        <th>Lahir Ke</th>
                        <th>Diagnosa</th>
                        <th>Penolong</th>
                        <th>No. SKL</th>
                        <th>G</th>
                        <th>P</th>
                        <th>A</th>
                        <th>N 1'</th>
                        <th>N 5'</th>
                        <th>N 10'</th>
                        <th>Penyulit</th>
                        <th>Ketuban</th>
                        <th>LK Perut</th>
                        <th>LK Dada</th>
                        <th>Resusitas</th>
                        <th>Obat</th>
                        <th>Mikasi</th>
                        <th>Mikonium</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
let dtBBL;
let dataBBL = [];

const ID_LANG_BBL = {
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
    return value && value.includes('-') ? value.split('-').reverse().join('-') : value;
}

function initTable() {
    if (dtBBL) {
        dtBBL.clear();
        dtBBL.rows.add(dataBBL);
        dtBBL.draw();
        return;
    }

    dtBBL = $('#tblBBL').DataTable({
        data: dataBBL,
        language: ID_LANG_BBL,
        pageLength: 25,
        scrollX: true,
        order: [[4, 'desc'], [5, 'desc']],
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                title: 'Data BBL',
                className: 'd-none',
                exportOptions: { columns: ':visible' }
            }
        ],
        columns: [
            { data: null, render: (d,t,r,i) => i.row + 1 },
            { data: 'no_rkm_medis' },
            { data: 'nm_pasien' },
            { data: 'jk' },
            { data: 'tgl_lahir', render: d => fmtTanggal(d) },
            { data: 'jam_lahir' },
            { data: 'umur' },
            { data: 'tgl_daftar', render: d => fmtTanggal(d) },
            { data: 'nm_ibu' },
            { data: 'umur_ibu' },
            { data: 'nama_ayah' },
            { data: 'umur_ayah' },
            { data: 'alamat' },
            { data: 'berat_badan' },
            { data: 'panjang_badan' },
            { data: 'lingkar_kepala' },
            { data: 'proses_lahir' },
            { data: 'anakke' },
            { data: 'diagnosa' },
            { data: 'penolong' },
            { data: 'no_skl' },
            { data: 'g' },
            { data: 'p' },
            { data: 'a' },
            { data: 'n1' },
            { data: 'n5' },
            { data: 'n10' },
            { data: 'penyulit_kehamilan' },
            { data: 'ketuban' },
            { data: 'lingkar_perut' },
            { data: 'lingkar_dada' },
            { data: 'resusitas' },
            { data: 'obat_diberikan' },
            { data: 'mikasi' },
            { data: 'mikonium' },
            { data: 'keterangan' }
        ]
    });
}

function loadData() {
    const tglAwal = $('#tglAwal').val();
    const tglAkhir = $('#tglAkhir').val();
    const jk = $('#jk').val();

    if (!tglAwal || !tglAkhir) {
        alert('Pilih periode tanggal lahir.');
        return;
    }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Memuat...');

    $.getJSON('api/data_bbl.php', { tgl_awal: tglAwal, tgl_akhir: tglAkhir, jk: jk }, function(res) {
        if (!res.success) {
            alert(res.message || 'Gagal memuat data BBL.');
            return;
        }

        dataBBL = res.data || [];
        initTable();

        const summary = res.summary || {};
        $('#stTotal').text(fmtNum(summary.total));
        $('#stLaki').text(fmtNum(summary.laki));
        $('#stPerempuan').text(fmtNum(summary.perempuan));
        $('#stRerata').html(fmtNum(summary.rerata_berat) + ' gr<br><small>' + fmtNum(summary.rerata_panjang) + ' cm</small>');
        $('#periodeInfo').text(fmtTanggal(tglAwal) + ' s/d ' + fmtTanggal(tglAkhir));
        $('#btnExport').prop('disabled', dataBBL.length === 0);
    }).fail(function() {
        alert('Gagal memuat data BBL. Pastikan koneksi dan API tersedia.');
    }).always(function() {
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i> Muat Data');
    });
}

$('#btnLoad').on('click', loadData);
$('#btnExport').on('click', function() {
    if (dtBBL) {
        dtBBL.button('.buttons-excel').trigger();
    }
});

$(document).ready(function() {
    initTable();
    loadData();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
