<?php
/*
 * File: laporan_rekap_obat_poli.php
 * Modul: Rekap Obat Poli
 */
$page_title = "Rekap Obat Poli";
require_once('includes/header.php');

$tgl_awal_default  = date('Y-m-01');
$tgl_akhir_default = date('Y-m-d');

function fetchOptions($koneksi, $sql) {
    $items = [];
    $res = $koneksi->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}

$penjab = fetchOptions($koneksi, "SELECT kd_pj, png_jawab FROM penjab ORDER BY png_jawab");
$jenis = fetchOptions($koneksi, "SELECT kdjns, nama FROM jenis ORDER BY nama");
$kategori = fetchOptions($koneksi, "SELECT kode, nama FROM kategori_barang ORDER BY nama");
$golongan = fetchOptions($koneksi, "SELECT kode, nama FROM golongan_barang ORDER BY nama");
?>

<style>
    .filter-section .form-label { font-size: .78rem; font-weight: 700; margin-bottom: 3px; }
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    .stat-card {
        border-left: 4px solid var(--accent, #4e73df);
        border-radius: 8px;
        padding: 14px 16px;
        position: relative;
        overflow: hidden;
    }
    .stat-card .value {
        font-size: 1.18rem;
        font-weight: 800;
        color: var(--accent, #4e73df);
        line-height: 1.2;
    }
    .stat-card .label {
        margin-top: 4px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .stat-card .icon-bg {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--accent, #4e73df);
        font-size: 2rem;
        opacity: .12;
    }
    .stat-blue { --accent: #4e73df; }
    .stat-green { --accent: #1cc88a; }
    .stat-cyan { --accent: #36b9cc; }
    .stat-amber { --accent: #f6c23e; }
    #tblRekap thead th { white-space: nowrap; font-size: .78rem; }
    #tblRekap tbody td { vertical-align: middle; font-size: .82rem; }
    #tblRekap tfoot td { font-weight: 800; text-align: center;}
    .num { text-align: right; font-variant-numeric: tabular-nums; }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-prescription-bottle-alt text-primary me-2"></i>Rekap Obat Poli</h4>
        <small class="text-muted">Rekap penggunaan obat rawat jalan, dikelompokkan menjadi Poliklinik dan IGD</small>
    </div>
</div>

<div class="card shadow-sm mb-3 filter-section">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Tgl. Beri Obat Awal</label>
                <input type="date" class="form-control form-control-sm" id="tglAwal" value="<?php echo $tgl_awal_default; ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Tgl. Beri Obat Akhir</label>
                <input type="date" class="form-control form-control-sm" id="tglAkhir" value="<?php echo $tgl_akhir_default; ?>">
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Poli</label>
                <select class="form-select form-select-sm" id="fPoli">
                    <option value="">Semua</option>
                    <option value="Poliklinik">Poliklinik</option>
                    <option value="IGD">IGD</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Cara Bayar</label>
                <select class="form-select form-select-sm" id="fPenjab">
                    <option value="">Semua Cara Bayar</option>
                    <?php foreach ($penjab as $p): ?>
                        <option value="<?php echo htmlspecialchars($p['kd_pj']); ?>"><?php echo htmlspecialchars($p['png_jawab']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-primary flex-fill" id="btnLoad">
                    <i class="fas fa-search me-1"></i>Tampilkan
                </button>
                <button class="btn btn-sm btn-outline-secondary" id="btnReset" title="Reset filter">
                    <i class="fas fa-undo"></i>
                </button>
            </div>
        </div>

        <div class="row g-2 mt-1">
            <div class="col-lg-3 col-md-4">
                <label class="form-label">Jenis</label>
                <select class="form-select form-select-sm" id="fJenis">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenis as $j): ?>
                        <option value="<?php echo htmlspecialchars($j['kdjns']); ?>"><?php echo htmlspecialchars($j['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-4">
                <label class="form-label">Kategori</label>
                <select class="form-select form-select-sm" id="fKategori">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?php echo htmlspecialchars($k['kode']); ?>"><?php echo htmlspecialchars($k['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-4">
                <label class="form-label">Golongan</label>
                <select class="form-select form-select-sm" id="fGolongan">
                    <option value="">Semua Golongan</option>
                    <?php foreach ($golongan as $g): ?>
                        <option value="<?php echo htmlspecialchars($g['kode']); ?>"><?php echo htmlspecialchars($g['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<div class="stat-grid">
    <div class="card shadow-sm stat-card stat-blue">
        <div class="value" id="stItem">0</div>
        <div class="label text-muted">Jenis Obat</div>
        <div class="icon-bg"><i class="fas fa-pills"></i></div>
    </div>
    <div class="card shadow-sm stat-card stat-green">
        <div class="value" id="stQty">0</div>
        <div class="label text-muted">Total Jumlah</div>
        <div class="icon-bg"><i class="fas fa-calculator"></i></div>
    </div>
    <div class="card shadow-sm stat-card stat-cyan">
        <div class="value" id="stTotal">Rp 0</div>
        <div class="label text-muted">Grand Total</div>
        <div class="icon-bg"><i class="fas fa-coins"></i></div>
    </div>
    <div class="card shadow-sm stat-card stat-amber">
        <div class="value" id="stIGD">Rp 0</div>
        <div class="label text-muted">Total IGD</div>
        <div class="icon-bg"><i class="fas fa-hospital-user"></i></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table me-2"></i>Data Rekap Obat Poli</h6>
        <small class="text-muted" id="infoRange"></small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="tblRekap" style="width:100%">
                <thead class="table-dark">
                    <tr>
                        <th style="width:42px;">No</th>
                        <th>Poli</th>
                        <th class="num">Jml</th>
                        <th>Kode Obat</th>
                        <th>Nama Obat</th>
                        <th class="num">Biaya Obat</th>
                        <th class="num">Embalase</th>
                        <th class="num">Tuslah</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TOTAL</td>
                        <td class="num" id="ftQty">0</td>
                        <td colspan="2"></td>
                        <td class="num" id="ftBiaya">Rp 0</td>
                        <td class="num" id="ftEmbalase">Rp 0</td>
                        <td class="num" id="ftTuslah">Rp 0</td>
                        <td class="num" id="ftTotal">Rp 0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
let dt = null;
const ID_LANG = {
    search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
    infoEmpty: 'Tidak ada data', infoFiltered: '(difilter dari _MAX_ total)', zeroRecords: 'Data tidak ditemukan',
    paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
};

function fmtRp(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function fmtNum(n) {
    return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

function escHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function initTable() {
    if (dt) return dt;
    dt = $('#tblRekap').DataTable({
        language: ID_LANG,
        pageLength: 50,
        order: [[1, 'asc'], [4, 'asc']],
        dom: "<'row mb-2'<'col-sm-6'B><'col-sm-6'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i>Export Excel',
                className: 'btn btn-success btn-sm',
                title: 'Rekap_Obat_Poli',
                footer: true,
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-1"></i>Print',
                className: 'btn btn-outline-secondary btn-sm',
                footer: true,
                exportOptions: { columns: ':visible' }
            }
        ],
        columnDefs: [
            { orderable: false, targets: 0 },
            { className: 'num', targets: [2, 5, 6, 7, 8] }
        ]
    });
    return dt;
}

function updateSummary(summary) {
    $('#stItem').text(fmtNum(summary.total_item));
    $('#stQty').text(fmtNum(summary.total_qty));
    $('#stTotal').text(fmtRp(summary.grand_total));
    $('#stIGD').text(fmtRp(summary.total_igd));

    $('#ftQty').text(fmtNum(summary.total_qty));
    $('#ftBiaya').text(fmtRp(summary.total_biaya));
    $('#ftEmbalase').text(fmtRp(summary.total_embalase));
    $('#ftTuslah').text(fmtRp(summary.total_tuslah));
    $('#ftTotal').text(fmtRp(summary.grand_total));
}

function loadData() {
    const params = {
        tgl_awal: $('#tglAwal').val(),
        tgl_akhir: $('#tglAkhir').val(),
        poli: $('#fPoli').val(),
        kd_pj: $('#fPenjab').val(),
        kdjenis: $('#fJenis').val(),
        kdkategori: $('#fKategori').val(),
        kdgolongan: $('#fGolongan').val()
    };

    if (!params.tgl_awal || !params.tgl_akhir) {
        alert('Pilih periode tanggal terlebih dahulu.');
        return;
    }

    $('#btnLoad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Memuat');

    $.getJSON('api/data_rekap_obat_poli.php', params, function(res) {
        if (res.error) {
            alert(res.error);
            return;
        }

        const rows = res.data || [];
        const table = initTable();
        table.clear();
        rows.forEach(function(r, idx) {
            table.row.add([
                idx + 1,
                escHtml(r.nm_poli),
                fmtNum(r.jml),
                escHtml(r.kode_brng),
                escHtml(r.nama_brng),
                fmtRp(r.biaya_obat),
                fmtRp(r.embalase),
                fmtRp(r.tuslah),
                fmtRp(r.total)
            ]);
        });
        table.draw();

        updateSummary(res.summary || {});
        $('#infoRange').text('Periode: ' + params.tgl_awal + ' s.d. ' + params.tgl_akhir);
    }).fail(function(xhr) {
        const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Gagal memuat data rekap obat poli.';
        alert(msg);
    }).always(function() {
        $('#btnLoad').prop('disabled', false).html('<i class="fas fa-search me-1"></i>Tampilkan');
    });
}

$('#btnLoad').on('click', loadData);
$('#btnReset').on('click', function() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    $('#tglAwal').val(y + '-' + m + '-01');
    $('#tglAkhir').val(y + '-' + m + '-' + d);
    $('#fPoli, #fPenjab, #fJenis, #fKategori, #fGolongan').val('');
    loadData();
});

$(document).ready(function() {
    initTable();
    loadData();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
