<?php
/*
 * Deskripsi: Rekap Monitoring Klaim Ralan - Data pasien BPJS rawat jalan
 *            beserta diagnosa, prosedur, tarif RS vs INA-CBG, dan selisih
 */

$page_title = "Rekap Monitoring Klaim Ralan";
require_once('includes/header.php');
require_once('includes/functions.php');

// Parameter Filter
$tgl_awal    = isset($_GET['tgl_awal'])    ? htmlspecialchars($_GET['tgl_awal'])    : date('Y-m-01');
$tgl_akhir   = isset($_GET['tgl_akhir'])  ? htmlspecialchars($_GET['tgl_akhir'])   : date('Y-m-d');
$kd_pj       = isset($_GET['kd_pj'])      ? htmlspecialchars($_GET['kd_pj'])       : '';
$stts_pulang = isset($_GET['stts_pulang'])? htmlspecialchars($_GET['stts_pulang']) : '';
$action      = isset($_GET['action'])     ? $_GET['action'] : '';

// Ambil daftar penjab BPJS untuk dipilih sebagai BPJS Kesehatan
$q_pj = $koneksi->query(
    "SELECT kd_pj, png_jawab FROM penjab
     WHERE LOWER(png_jawab) LIKE '%bpjs%'
     ORDER BY png_jawab"
);
$penjab_bpjs = [];
while ($row = $q_pj->fetch_assoc()) {
    $penjab_bpjs[] = $row;
}
if ($kd_pj === '') {
    foreach ($penjab_bpjs as $pj) {
        if (strpos(strtolower($pj['png_jawab']), 'bpjs kesehatan') !== false) {
            $kd_pj = $pj['kd_pj'];
            break;
        }
    }
    if ($kd_pj === '' && !empty($penjab_bpjs)) {
        $kd_pj = $penjab_bpjs[0]['kd_pj'];
    }
}
?>

<style>
/* ── Stat Cards ────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 18px;
}
.stat-card {
    border-radius: 10px;
    padding: 16px 18px;
    position: relative;
    overflow: hidden;
    transition: transform .2s;
}
.stat-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    background: var(--accent-color, #3b82f6);
    border-radius: 10px 0 0 10px;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-card .val {
    font-size: 22px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--accent-color, #3b82f6);
}
.stat-card .lbl {
    font-size: 11px;
    margin-top: 4px;
    letter-spacing: .04em;
}
.stat-card .icon-bg {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    font-size: 28px;
    opacity: .08;
    color: var(--accent-color, #3b82f6);
}
.stat-card.c-blue   { --accent-color: #3b82f6; }
.stat-card.c-green  { --accent-color: #22c55e; }
.stat-card.c-red    { --accent-color: #ef4444; }
.stat-card.c-yellow { --accent-color: #f59e0b; }
.stat-card.c-purple { --accent-color: #a78bfa; }
.stat-card.c-cyan   { --accent-color: #38bdf8; }

/* ── Table ─────────────────────────────────────────────── */
#tblRekap thead th {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}
#tblRekap tbody td {
    font-size: 13px;
    vertical-align: middle;
}

/* ── Number cells ──────────────────────────────────────── */
.num { text-align: right; font-variant-numeric: tabular-nums; }

/* ── Selisih coloring ──────────────────────────────────── */
.selisih-untung { color: #22c55e; font-weight: 600; }
.selisih-rugi   { color: #ef4444; font-weight: 600; }

/* ── Badges ────────────────────────────────────────────── */
.badge-dx {
    font-family: 'Courier New', monospace;
    font-size: 11px;
}
.badge-cbg {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    font-weight: 600;
}

/* ── Loading ───────────────────────────────────────────── */
#loadingArea {
    display: none;
    text-align: center;
    padding: 50px 20px;
}
#loadingArea.show { display: block; }
</style>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="fas fa-clipboard-list me-2 text-primary"></i>Rekap Monitoring Klaim Ralan</h4>
            <span class="text-muted small">Data Pasien BPJS – Diagnosa, Prosedur, Tarif RS vs INA-CBG</span>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h6 class="card-title text-primary mb-3"><i class="fas fa-filter me-2"></i>Filter & Pencarian</h6>
            <form id="frmFilter">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Tgl Kunjungan Awal</label>
                        <input type="date" id="tgl_awal" name="tgl_awal" class="form-control"
                               value="<?php echo htmlspecialchars($tgl_awal); ?>" required>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Tgl Kunjungan Akhir</label>
                        <input type="date" id="tgl_akhir" name="tgl_akhir" class="form-control"
                               value="<?php echo htmlspecialchars($tgl_akhir); ?>" required>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Penjamin</label>
                        <input type="text" class="form-control" value="BPJS Kesehatan" disabled>
                        <input type="hidden" id="kd_pj" name="kd_pj" value="<?php echo htmlspecialchars($kd_pj); ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Status Keluar</label>
                        <select id="stts_pulang" name="stts_pulang" class="form-select">
                            <option value="">-- Semua Status --</option>
                            <option value="Sembuh" <?php echo ($stts_pulang=='Sembuh') ? 'selected':''; ?>>Sembuh</option>
                            <option value="Dirujuk" <?php echo ($stts_pulang=='Dirujuk') ? 'selected':''; ?>>Dirujuk</option>
                            <option value="Batal" <?php echo ($stts_pulang=='Batal') ? 'selected':''; ?>>Batal</option>
                            <option value="Belum" <?php echo ($stts_pulang=='Belum') ? 'selected':''; ?>>Belum</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-search me-1"></i>Cari
                        </button>
                        <button type="reset" id="btnReset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid" id="statGrid" style="display:none !important;">
        <div class="card shadow-sm stat-card c-blue">
            <div class="val" id="stTotal">0</div>
            <div class="lbl text-muted">Total Pasien</div>
            <div class="icon-bg"><i class="fas fa-users"></i></div>
        </div>
        <div class="card shadow-sm stat-card c-cyan">
            <div class="val" id="stTotalRS" style="font-size:16px;">Rp 0</div>
            <div class="lbl text-muted">Total Biaya RS</div>
            <div class="icon-bg"><i class="fas fa-hospital"></i></div>
        </div>
        <div class="card shadow-sm stat-card c-yellow">
            <div class="val" id="stTotalCBG" style="font-size:16px;">Rp 0</div>
            <div class="lbl text-muted">Total Tarif INA-CBG</div>
            <div class="icon-bg"><i class="fas fa-shield-alt"></i></div>
        </div>
        <div class="card shadow-sm stat-card" id="stCardSelisih">
            <div class="val" id="stSelisih" style="font-size:16px;">Rp 0</div>
            <div class="lbl text-muted">Total Selisih</div>
            <div class="icon-bg"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>

    <!-- Loading -->
    <div id="loadingArea">
        <div class="spinner-border text-primary mb-3" role="status"></div>
        <p class="text-muted">Memuat data monitoring klaim ralan...</p>
    </div>

    <!-- Table -->
    <div class="card shadow mb-4" id="tableSection" style="display:none;">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-table me-2"></i>Data Monitoring Klaim Ralan</h6>
            <small id="infoRange" class="text-muted"></small>
        </div>
        <div class="card-body p-2">
            <div class="table-responsive">
                <table id="tblRekap" class="table table-bordered table-striped table-sm" width="100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:38px">No</th>
                            <th>No Rawat</th>
                            <th>SEP</th>
                            <th>No RM</th>
                            <th>Nama Pasien</th>
                            <th>Dokter DPJP</th>
                            <th>Penjamin</th>
                            <th>Tanggal Kunjungan</th>
                            <th>Stts Keluar</th>
                            <th>DU</th>
                            <th>DS 1</th>
                            <th>P 1</th>
                            <th>INA-CBG</th>
                            <th class="num">Total Tarif RS</th>
                            <th class="num">Tarif INA-CBG</th>
                            <th class="num">Selisih</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="13" style="text-align:right; padding-right:12px;">
                                <strong>TOTAL</strong>
                            </td>
                            <td class="num" id="tblTotalRS">Rp 0</td>
                            <td class="num" id="tblTotalCBG">Rp 0</td>
                            <td class="num" id="tblTotalSelisih">Rp 0</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<?php ob_start(); ?>
<script>
// ── Helpers ──────────────────────────────────────────────────────────────────
function fmtRp(num) {
    if (num === null || num === undefined || num === '') return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR',
        minimumFractionDigits: 0, maximumFractionDigits: 0
    }).format(num);
}

function fmtNum(num) {
    return new Intl.NumberFormat('id-ID').format(num);
}

// ── DataTable instance ────────────────────────────────────────────────────────
let dt = null;
function getTable() {
    if (!dt) {
        dt = $('#tblRekap').DataTable({
            dom: "<'row mb-2'<'col-sm-6'B><'col-sm-6'f>>" +
                 "<'row'<'col-12'tr>>" +
                 "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-1"></i>Export Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Rekap_Monitoring_Klaim_Ralan',
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
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100, 200],
            ordering: true,
            responsive: false,
            autoWidth: false,
            columnDefs: [
                { orderable: false, targets: [0] },
                { className: 'num', targets: [13, 14, 15] }
            ]
        });
    }
    return dt;
}

// ── Populate Table ────────────────────────────────────────────────────────────
function populateTable(data) {
    const table = getTable();
    table.clear();

    data.forEach(function(r, i) {
        const selNum  = parseFloat(r.selisih);
        let selClass  = '';
        if      (selNum > 0)  selClass = 'selisih-untung';
        else if (selNum < 0)  selClass = 'selisih-rugi';

        const selHtml = (parseFloat(r.tarif_cbg) > 0)
            ? '<span class="' + selClass + '">' + fmtRp(r.selisih) + '</span>'
            : '<span class="text-muted">–</span>';

        const cbgHtml = r.kode_cbg
            ? '<span class="badge-cbg text-warning">' + escHtml(r.kode_cbg) + '</span>'
            : '<span class="text-muted">–</span>';

        table.row.add([
            i + 1,
            escHtml(r.no_rawat),
            escHtml(r.no_sep) ? escHtml(r.no_sep) : '–',
            escHtml(r.no_rkm_medis),
            escHtml(r.nm_pasien),
            escHtml(r.kd_dokter_jaga),
            escHtml(r.png_jawab),
            escHtml(r.tgl_registrasi),
            escHtml(r.stts_pulang),
            r.du  ? '<span class="badge-dx text-info">' + escHtml(r.du) + '</span>'  : '–',
            r.ds1 ? '<span class="badge-dx text-info">' + escHtml(r.ds1) + '</span>' : '–',
            r.p1  ? '<span class="badge-dx text-info">' + escHtml(r.p1) + '</span>'  : '–',
            cbgHtml,
            fmtRp(r.total_rs),
            parseFloat(r.tarif_cbg) > 0 ? fmtRp(r.tarif_cbg) : '<span class="text-muted">–</span>',
            selHtml
        ]);
    });

    table.draw();
}

function updateFooter(summary) {
    $('#tblTotalRS').text(fmtRp(summary.total_biaya_rs));
    $('#tblTotalCBG').text(fmtRp(summary.total_tarif_cbg));
    $('#tblTotalSelisih').text(fmtRp(summary.total_selisih));
}

// ── Update stat cards ─────────────────────────────────────────────────────────
function updateStats(summary, tgl_awal, tgl_akhir) {
    $('#stTotal').text(fmtNum(summary.total_pasien));
    $('#stTotalRS').text(fmtRp(summary.total_biaya_rs));
    $('#stTotalCBG').text(fmtRp(summary.total_tarif_cbg));
    $('#stSelisih').text(fmtRp(summary.total_selisih));
    updateFooter(summary);

    const sc = document.getElementById('stCardSelisih');
    sc.className = 'card shadow-sm stat-card ' + (summary.total_selisih >= 0 ? 'c-green' : 'c-red');

    $('#infoRange').text('Periode: ' + tgl_awal + ' s.d. ' + tgl_akhir);
    $('#statGrid').css('display', '');
}

// ── Load Data ─────────────────────────────────────────────────────────────────
function loadData() {
    const params = {
        tgl_awal    : $('#tgl_awal').val(),
        tgl_akhir   : $('#tgl_akhir').val(),
        kd_pj       : $('#kd_pj').val(),
        stts_pulang : $('#stts_pulang').val()
    };

    $('#loadingArea').addClass('show');
    $('#tableSection').hide();
    $('#statGrid').hide();
    $('.alert').remove();

    $.ajax({
        url      : 'api/data_monitoring_klaim_ralan.php',
        type     : 'GET',
        dataType : 'json',
        data     : params,
        success  : function(resp) {
            $('#loadingArea').removeClass('show');
            if (resp.error) {
                showAlert('Error: ' + resp.error, 'danger');
                return;
            }
            if (!resp.data || resp.data.length === 0) {
                showAlert('Tidak ada data untuk filter yang dipilih.', 'warning');
                updateFooter({ total_biaya_rs: 0, total_tarif_cbg: 0, total_selisih: 0 });
                return;
            }
            populateTable(resp.data);
            updateStats(resp.summary, params.tgl_awal, params.tgl_akhir);
            $('#tableSection').show();
        },
        error: function(xhr, st, err) {
            $('#loadingArea').removeClass('show');
            showAlert('Gagal memuat data: ' + err, 'danger');
        }
    });
}

// ── Util ──────────────────────────────────────────────────────────────────────
function escHtml(s) {
    if (!s) return '';
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function showAlert(msg, type) {
    const el = '<div class="alert alert-' + type + ' alert-dismissible fade show mt-2" role="alert">' +
        msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    $('#tableSection').before(el);
    setTimeout(function() { $('.alert').fadeOut(function() { $('.alert').remove(); }); }, 6000);
}

// ── Events ────────────────────────────────────────────────────────────────────
$(document).ready(function() {
    $('#frmFilter').on('submit', function(e) {
        e.preventDefault();
        loadData();
    });

    $('#btnReset').on('click', function() {
        const now = new Date();
        const y   = now.getFullYear();
        const m   = String(now.getMonth() + 1).padStart(2, '0');
        const d   = String(now.getDate()).padStart(2, '0');
        $('#tgl_awal').val(y + '-' + m + '-01');
        $('#tgl_akhir').val(y + '-' + m + '-' + d);
        $('#stts_pulang').val('');
        $('#statGrid').hide();
        $('#tableSection').hide();
    });

    <?php if ($action === 'cari'): ?>
    loadData();
    <?php endif; ?>
});
</script>
<?php $page_js = ob_get_clean(); ?>
<?php require_once('includes/footer.php'); ?>
