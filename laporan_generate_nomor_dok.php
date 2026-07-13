<?php
/*
 * Modul: Generate Nomor DOK
 * Fungsi: Penomoran dokumen akreditasi SK, Pedoman, dan SPO.
 */
$page_title = "Generate Nomor DOK";
require_once('config/koneksi.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function dok_months() {
    return [
        1 => ['name' => 'Januari', 'roman' => 'I'],
        2 => ['name' => 'Februari', 'roman' => 'II'],
        3 => ['name' => 'Maret', 'roman' => 'III'],
        4 => ['name' => 'April', 'roman' => 'IV'],
        5 => ['name' => 'Mei', 'roman' => 'V'],
        6 => ['name' => 'Juni', 'roman' => 'VI'],
        7 => ['name' => 'Juli', 'roman' => 'VII'],
        8 => ['name' => 'Agustus', 'roman' => 'VIII'],
        9 => ['name' => 'September', 'roman' => 'IX'],
        10 => ['name' => 'Oktober', 'roman' => 'X'],
        11 => ['name' => 'November', 'roman' => 'XI'],
        12 => ['name' => 'Desember', 'roman' => 'XII'],
    ];
}

function dok_types() {
    return [
        'SK' => 'Kebijakan Direktur / SK',
        'PDM' => 'Pedoman / PDM',
        'SPO' => 'Standar Prosedur Operasional / SPO',
    ];
}

function dok_pokja() {
    return [
        'TKRS' => 'Tata Kelola Rumah Sakit',
        'KPS' => 'Kualifikasi dan Pendidikan Staf',
        'MFK' => 'Manajemen Fasilitas dan Keselamatan',
        'PMKP' => 'Peningkatan Mutu dan Keselamatan Pasien',
        'MRMIK' => 'Manajemen Rekam Medis dan Informasi Kesehatan',
        'PPI' => 'Pencegahan dan Pengendalian Infeksi',
        'AKP' => 'Akses dan Kesinambungan Pelayanan',
        'HPK' => 'Hak Pasien dan Keterlibatan Keluarga',
        'PP' => 'Pengkajian Pasien',
        'PAP' => 'Pelayanan dan Asuhan Pasien',
        'PAB' => 'Pelayanan Anestesi dan Bedah',
        'PKPO' => 'Pelayanan Kefarmasian dan Penggunaan Obat',
        'KKE' => 'Komunikasi dan Edukasi',
        'SKP' => 'Sasaran Keselamatan Pasien',
        'PROGNAS PONEK' => 'Program Nasional PONEK',
        'PROGNAS HIV' => 'Program Nasional HIV',
        'PROGNAS TB' => 'Program Nasional TB',
        'PROGNAS PPRA' => 'Program Nasional PPRA',
        'PROGNAS STUNTING' => 'Program Nasional Stunting',
        'PROGNAS KB' => 'Program Nasional KB',
    ];
}

function dok_ensure_table($koneksi) {
    $sql = "CREATE TABLE IF NOT EXISTS generate_nomor_dok (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nomor_urut INT UNSIGNED NOT NULL,
        kode_instansi VARCHAR(20) NOT NULL DEFAULT 'RSA',
        jenis_kode VARCHAR(10) NOT NULL,
        jenis_nama VARCHAR(80) NOT NULL,
        pokja_kode VARCHAR(40) NOT NULL,
        pokja_nama VARCHAR(150) NOT NULL,
        keterangan TEXT NOT NULL,
        bulan TINYINT UNSIGNED NOT NULL,
        bulan_romawi VARCHAR(8) NOT NULL,
        tahun SMALLINT UNSIGNED NOT NULL,
        nomor_dok VARCHAR(80) NOT NULL,
        created_by VARCHAR(100) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_nomor_dok (nomor_dok),
        KEY idx_dok_tahun (tahun),
        KEY idx_dok_pokja_jenis (pokja_kode, jenis_kode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return (bool) $koneksi->query($sql);
}

function dok_next_number($koneksi, $tahun, $jenis_kode) {
    $next = 1;
    if ($stmt = $koneksi->prepare("SELECT COALESCE(MAX(nomor_urut), 0) + 1 AS next_no FROM generate_nomor_dok WHERE tahun = ? AND jenis_kode = ?")) {
        $stmt->bind_param("is", $tahun, $jenis_kode);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $next = (int) $row['next_no'];
            }
        }
        $stmt->close();
    }
    return $next;
}

function dok_build_number($urut, $jenis, $bulan_romawi, $tahun, $kode_instansi = 'RSA') {
    return str_pad((string) $urut, 3, '0', STR_PAD_LEFT) . '/' . $kode_instansi . '/' . $jenis . '/' . $bulan_romawi . '/' . $tahun;
}

$months = dok_months();
$types = dok_types();
$pokja = dok_pokja();
$current_year = (int) date('Y');
$current_month = (int) date('n');
$alert = null;
$table_ready = dok_ensure_table($koneksi);
$is_super_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_ready) {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $delete_id = (int) ($_POST['id'] ?? 0);
        $redirect_year = (int) ($_POST['tahun'] ?? $current_year);
        if ($redirect_year < 2000 || $redirect_year > 2100) $redirect_year = $current_year;

        if (!$is_super_admin) {
            $alert = ['type' => 'danger', 'text' => 'Hapus nomor dokumen hanya bisa dilakukan oleh Super Admin.'];
        } elseif ($delete_id <= 0) {
            $alert = ['type' => 'danger', 'text' => 'Data dokumen tidak valid untuk dihapus.'];
        } else {
            if ($stmt = $koneksi->prepare("DELETE FROM generate_nomor_dok WHERE id = ?")) {
                $stmt->bind_param("i", $delete_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $stmt->close();
                    header('Location: laporan_generate_nomor_dok.php?tahun=' . $redirect_year . '&deleted=1');
                    exit;
                }
                $stmt->close();
            }
            $alert = ['type' => 'danger', 'text' => 'Data dokumen gagal dihapus atau sudah tidak tersedia.'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $table_ready && ($_POST['action'] ?? 'create') === 'create') {
    $jenis_kode = $_POST['jenis_kode'] ?? '';
    $pokja_kode = $_POST['pokja_kode'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');
    $bulan = (int) ($_POST['bulan'] ?? $current_month);
    $tahun = $current_year;
    $kode_instansi = 'RSA';

    if (!isset($types[$jenis_kode]) || !isset($pokja[$pokja_kode]) || !isset($months[$bulan]) || $keterangan === '') {
        $alert = ['type' => 'danger', 'text' => 'Lengkapi jenis dokumen, POKJA/BAB, keterangan, dan bulan terlebih dahulu.'];
    } else {
        $koneksi->begin_transaction();
        $nomor_dok = '';
        $nomor_urut = 0;
        $inserted = false;

        for ($try = 0; $try < 3 && !$inserted; $try++) {
            $nomor_urut = dok_next_number($koneksi, $tahun, $jenis_kode);
            $nomor_dok = dok_build_number($nomor_urut, $jenis_kode, $months[$bulan]['roman'], $tahun, $kode_instansi);
            $jenis_nama = $types[$jenis_kode];
            $pokja_nama = $pokja[$pokja_kode];
            $bulan_romawi = $months[$bulan]['roman'];
            $created_by = $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'System';

            $sql = "INSERT INTO generate_nomor_dok
                (nomor_urut, kode_instansi, jenis_kode, jenis_nama, pokja_kode, pokja_nama, keterangan, bulan, bulan_romawi, tahun, nomor_dok, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $koneksi->prepare($sql)) {
                $stmt->bind_param(
                    "issssssisiss",
                    $nomor_urut,
                    $kode_instansi,
                    $jenis_kode,
                    $jenis_nama,
                    $pokja_kode,
                    $pokja_nama,
                    $keterangan,
                    $bulan,
                    $bulan_romawi,
                    $tahun,
                    $nomor_dok,
                    $created_by
                );
                $inserted = $stmt->execute();
                $stmt->close();
            }
        }

        if ($inserted) {
            $koneksi->commit();
            header('Location: laporan_generate_nomor_dok.php?saved=' . urlencode($nomor_dok));
            exit;
        }

        $koneksi->rollback();
        $alert = ['type' => 'danger', 'text' => 'Nomor dokumen gagal disimpan. Silakan coba lagi.'];
    }
}

$saved_number = $_GET['saved'] ?? '';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$year_filter = (int) ($_GET['tahun'] ?? $current_year);
if ($year_filter < 2000 || $year_filter > 2100) $year_filter = $current_year;
$next_numbers = [];
foreach ($types as $type_code => $type_label) {
    $next_numbers[$type_code] = $table_ready ? dok_next_number($koneksi, $current_year, $type_code) : 1;
}
$next_preview = dok_build_number($next_numbers['SK'] ?? 1, 'SK', $months[$current_month]['roman'], $current_year);

$documents = [];
$summary = [
    'total' => 0,
    'by_type' => array_fill_keys(array_keys($types), 0),
    'by_pokja' => [],
];
foreach ($pokja as $code => $label) {
    $summary['by_pokja'][$code] = array_fill_keys(array_keys($types), 0);
}

if ($table_ready) {
    if ($stmt = $koneksi->prepare("SELECT * FROM generate_nomor_dok WHERE tahun = ? ORDER BY nomor_urut DESC, id DESC")) {
        $stmt->bind_param("i", $year_filter);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $documents[] = $row;
                $summary['total']++;
                if (isset($summary['by_type'][$row['jenis_kode']])) {
                    $summary['by_type'][$row['jenis_kode']]++;
                }
                if (isset($summary['by_pokja'][$row['pokja_kode']][$row['jenis_kode']])) {
                    $summary['by_pokja'][$row['pokja_kode']][$row['jenis_kode']]++;
                }
            }
        }
        $stmt->close();
    }
}

require_once('includes/header.php');
?>

<style>
    .dok-filter .form-label { font-size: .78rem; font-weight: 700; margin-bottom: 4px; }
    .dok-filter textarea.form-control,
    .dok-filter input.form-control[readonly],
    .dok-filter input.form-control[disabled],
    .dok-filter .form-control-plain {
        background-color: var(--bs-body-bg, #fff);
        color: var(--bs-body-color, #212529);
        border-color: var(--bs-border-color, #ced4da);
    }
    .dok-filter textarea.form-control::placeholder {
        color: var(--bs-secondary-color, #6c757d);
        opacity: 1;
    }
    .dok-preview {
        border-radius: 8px;
        border: 1px dashed #0d6efd;
        background: rgba(13, 110, 253, .08);
        padding: 14px 16px;
    }
    .dok-preview .number {
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: .02em;
        color: #0d6efd;
        font-variant-numeric: tabular-nums;
        word-break: break-word;
    }
    .dok-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }
    .dok-stat {
        border-left: 4px solid var(--accent, #0d6efd);
        border-radius: 8px;
        padding: 14px 16px;
        position: relative;
        overflow: hidden;
    }
    .dok-stat .value {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--accent, #0d6efd);
        line-height: 1.2;
    }
    .dok-stat .label {
        font-size: .72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .dok-stat i {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 2rem;
        opacity: .14;
        color: var(--accent, #0d6efd);
    }
    .dok-blue { --accent: #0d6efd; }
    .dok-green { --accent: #198754; }
    .dok-orange { --accent: #fd7e14; }
    .dok-cyan { --accent: #0dcaf0; }
    .pokja-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 10px;
    }
    .pokja-box {
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
    }
    .pokja-title {
        padding: 8px 10px;
        font-weight: 800;
        font-size: .78rem;
        background: #e7f1ff;
        color: #0d6efd;
        border-bottom: 1px solid rgba(0,0,0,.08);
        min-height: 38px;
        display: flex;
        align-items: center;
    }
    .pokja-counts {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        text-align: center;
    }
    .pokja-counts div {
        padding: 8px 4px;
        border-right: 1px solid rgba(0,0,0,.08);
    }
    .pokja-counts div:last-child { border-right: 0; }
    .pokja-counts small {
        display: block;
        color: #6c757d;
        font-size: .68rem;
        font-weight: 700;
    }
    .pokja-counts strong { font-size: 1rem; }
    #tblDok thead th { white-space: nowrap; font-size: .78rem; }
    #tblDok tbody td { vertical-align: middle; font-size: .82rem; }
    .nomor-pill {
        display: inline-block;
        padding: 5px 9px;
        border-radius: 8px;
        background: rgba(13, 110, 253, .1);
        color: #0d6efd;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    html.theme-glass-solid .pokja-box,
    html.theme-glass-animated .pokja-box {
        background: rgba(30, 41, 59, .75);
        border-color: rgba(255,255,255,.1);
    }
    html.theme-glass-solid .pokja-title,
    html.theme-glass-animated .pokja-title {
        background: rgba(56, 189, 248, .15);
        border-color: rgba(255,255,255,.1);
        color: #38bdf8;
    }
    html.theme-glass-solid .dok-preview,
    html.theme-glass-animated .dok-preview {
        background: rgba(56, 189, 248, .11);
        border-color: #38bdf8;
    }
    html.theme-glass-solid .dok-preview .number,
    html.theme-glass-animated .dok-preview .number { color: #38bdf8; }
    html.theme-glass-solid .dok-filter textarea.form-control,
    html.theme-glass-animated .dok-filter textarea.form-control,
    html.theme-glass-solid .dok-filter input.form-control[readonly],
    html.theme-glass-animated .dok-filter input.form-control[readonly] {
        background: rgba(15, 23, 42, .72);
        border-color: rgba(148, 163, 184, .45);
        color: rgba(255,255,255,.92);
    }
    html.theme-glass-solid .dok-filter textarea.form-control::placeholder,
    html.theme-glass-animated .dok-filter textarea.form-control::placeholder {
        color: rgba(226, 232, 240, .62);
    }
    .dok-confirm-modal .modal-content {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        overflow: hidden;
    }
    .dok-confirm-modal .modal-header {
        background: linear-gradient(135deg, #0d6efd, #0dcaf0);
        color: #fff;
        border-bottom: 0;
        padding: 18px 20px;
    }
    .dok-confirm-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.18);
        margin-right: 12px;
        flex: 0 0 auto;
    }
    .dok-confirm-modal .modal-body {
        padding: 20px;
        font-weight: 700;
    }
    .dok-confirm-box {
        border: 1px dashed rgba(13, 110, 253, .35);
        background: rgba(13, 110, 253, .06);
        border-radius: 10px;
        padding: 12px 14px;
        font-weight: 700;
        color: #0d6efd;
    }
    .dok-confirm-modal .modal-footer {
        border-top: 0;
        padding: 0 20px 20px;
        gap: 8px;
    }
    .dok-confirm-modal .btn {
        border-radius: 8px;
        font-weight: 700;
        padding: 8px 14px;
    }
    .dok-confirm-modal.is-danger .modal-header {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
    }
    .dok-confirm-modal.is-danger .dok-confirm-box {
        border-color: rgba(220, 53, 69, .35);
        background: rgba(220, 53, 69, .06);
        color: #dc3545;
    }
    html.theme-glass-solid .dok-confirm-modal .modal-content,
    html.theme-glass-animated .dok-confirm-modal .modal-content {
        background: rgba(15, 23, 42, .96);
        color: rgba(255,255,255,.92);
        border: 1px solid rgba(148, 163, 184, .2);
    }
    html.theme-glass-solid .dok-confirm-modal .text-muted,
    html.theme-glass-animated .dok-confirm-modal .text-muted {
        color: rgba(226, 232, 240, .68) !important;
    }
</style>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h4 class="mb-0"><i class="fas fa-file-signature text-primary me-2"></i>Generate Nomor DOK</h4>
        <small class="text-muted">Penomoran dokumen akreditasi SK, Pedoman, dan SPO</small>
    </div>
    <span class="badge bg-primary fs-6 px-3 py-2">Tahun <?php echo $year_filter; ?></span>
</div>

<?php if (!$table_ready): ?>
    <div class="alert alert-danger">Tabel generate_nomor_dok belum bisa dibuat. Periksa hak akses database.</div>
<?php endif; ?>
<?php if ($alert): ?>
    <div class="alert alert-<?php echo htmlspecialchars($alert['type']); ?>"><?php echo htmlspecialchars($alert['text']); ?></div>
<?php endif; ?>
<?php if ($saved_number !== ''): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-1"></i> Nomor dokumen berhasil dibuat:
        <strong><?php echo htmlspecialchars($saved_number); ?></strong>
    </div>
<?php endif; ?>
<?php if ($deleted): ?>
    <div class="alert alert-success">
        <i class="fas fa-trash-alt me-1"></i> Nomor dokumen berhasil dihapus.
    </div>
<?php endif; ?>

<div class="dok-stat-grid mb-3">
    <div class="card shadow-sm dok-stat dok-blue">
        <div class="value"><?php echo number_format($summary['total'], 0, ',', '.'); ?></div>
        <div class="label text-muted">Total Dokumen</div>
        <i class="fas fa-folder-open"></i>
    </div>
    <div class="card shadow-sm dok-stat dok-green">
        <div class="value"><?php echo number_format($summary['by_type']['SK'], 0, ',', '.'); ?></div>
        <div class="label text-muted">Kebijakan / SK</div>
        <i class="fas fa-gavel"></i>
    </div>
    <div class="card shadow-sm dok-stat dok-orange">
        <div class="value"><?php echo number_format($summary['by_type']['PDM'], 0, ',', '.'); ?></div>
        <div class="label text-muted">Pedoman / PDM</div>
        <i class="fas fa-book"></i>
    </div>
    <div class="card shadow-sm dok-stat dok-cyan">
        <div class="value"><?php echo number_format($summary['by_type']['SPO'], 0, ',', '.'); ?></div>
        <div class="label text-muted">SPO</div>
        <i class="fas fa-clipboard-list"></i>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card shadow-sm dok-filter h-100">
            <div class="card-header py-3">
                <h6 class="m-0 text-primary fw-bold"><i class="fas fa-plus-circle me-2"></i>Input Dokumen</h6>
            </div>
            <div class="card-body">
                <form method="post" id="dokForm">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Jenis Dokumen</label>
                        <select class="form-select" name="jenis_kode" id="jenisKode" required>
                            <?php foreach ($types as $code => $label): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">POKJA / BAB</label>
                        <select class="form-select" name="pokja_kode" required>
                            <?php foreach ($pokja as $code => $label): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($code . ' - ' . $label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan / Judul Dokumen</label>
                        <textarea class="form-control" name="keterangan" rows="4" placeholder="Contoh: Tim Pelayanan Obstetri Neonatal Emergensi Komprehensif..." required></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Bulan</label>
                            <select class="form-select" name="bulan" id="bulanDok" required>
                                <?php foreach ($months as $num => $month): ?>
                                    <option value="<?php echo $num; ?>" data-roman="<?php echo htmlspecialchars($month['roman']); ?>" <?php echo $num === $current_month ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($month['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Tahun</label>
                            <input type="text" class="form-control" value="<?php echo $current_year; ?>" readonly>
                        </div>
                    </div>
                    <div class="dok-preview mb-3">
                        <small class="text-muted fw-bold">Preview nomor berikutnya</small>
                        <div class="number" id="previewNomor"><?php echo htmlspecialchars($next_preview); ?></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" <?php echo !$table_ready ? 'disabled' : ''; ?>>
                        <i class="fas fa-save me-1"></i>Simpan & Generate Nomor
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="m-0 text-primary fw-bold"><i class="fas fa-chart-bar me-2"></i>Dashboard POKJA / BAB</h6>
                <form method="get" class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-bold">Tahun</label>
                    <input type="number" name="tahun" class="form-control form-control-sm" style="width: 100px;" value="<?php echo $year_filter; ?>" min="2000" max="2100">
                    <button class="btn btn-sm btn-outline-primary"><i class="fas fa-filter"></i></button>
                </form>
            </div>
            <div class="card-body">
                <div class="pokja-grid">
                    <?php foreach ($summary['by_pokja'] as $code => $counts): ?>
                        <?php
                            $label = $pokja[$code] ?? $code;
                            $total_pokja = array_sum($counts);
                            if ($total_pokja === 0 && !isset($pokja[$code])) continue;
                        ?>
                        <div class="pokja-box">
                            <div class="pokja-title"><?php echo htmlspecialchars($code); ?></div>
                            <div class="pokja-counts">
                                <div><small>SK</small><strong><?php echo (int) ($counts['SK'] ?? 0); ?></strong></div>
                                <div><small>SPO</small><strong><?php echo (int) ($counts['SPO'] ?? 0); ?></strong></div>
                                <div><small>PDM</small><strong><?php echo (int) ($counts['PDM'] ?? 0); ?></strong></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 text-primary fw-bold"><i class="fas fa-table me-2"></i>Rekapan Nomor Dokumen</h6>
        <small class="text-muted">Format: Nomor Urut/RSA/Jenis/Bulan/Tahun</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm align-middle" id="tblDok" width="100%">
                <thead class="table-primary">
                    <tr>
                        <th>No</th>
                        <th>Nomor Dokumen</th>
                        <th>Jenis</th>
                        <th>POKJA / BAB</th>
                        <th>Keterangan</th>
                        <th>Bulan</th>
                        <th>Tahun</th>
                        <th>Dibuat</th>
                        <?php if ($is_super_admin): ?>
                            <th class="no-export text-center">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $idx => $doc): ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td><span class="nomor-pill"><?php echo htmlspecialchars($doc['nomor_dok']); ?></span></td>
                            <td><?php echo htmlspecialchars($doc['jenis_nama']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($doc['pokja_kode']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($doc['pokja_nama']); ?></small>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($doc['keterangan'])); ?></td>
                            <td><?php echo htmlspecialchars($months[(int)$doc['bulan']]['name'] ?? $doc['bulan_romawi']); ?></td>
                            <td><?php echo (int) $doc['tahun']; ?></td>
                            <td>
                                <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($doc['created_at']))); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($doc['created_by'] ?? '-'); ?></small>
                            </td>
                            <?php if ($is_super_admin): ?>
                                <td class="text-center">
                                    <form method="post" class="d-inline delete-dok-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $doc['id']; ?>">
                                        <input type="hidden" name="tahun" value="<?php echo (int) $year_filter; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus dokumen">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade dok-confirm-modal" id="dokConfirmModal" tabindex="-1" aria-labelledby="dokConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <span class="dok-confirm-icon"><i class="fas fa-check-circle"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="dokConfirmTitle">Konfirmasi Data</h5>
                        <small id="dokConfirmSubtitle">Pastikan data dokumen sudah sesuai.</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3 text-muted" id="dokConfirmMessage">Data sudah benar dan siap dibuatkan nomor dokumen?</p>
                <div class="dok-confirm-box" id="dokConfirmPreview">
                    <?php echo htmlspecialchars($next_preview); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="dokConfirmCancel">
                    <i class="fas fa-times me-1"></i>Tidak
                </button>
                <button type="button" class="btn btn-primary" id="dokConfirmYes">
                    <i class="fas fa-save me-1"></i>Ya, Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
const nextUrutByJenis = <?php echo json_encode($next_numbers, JSON_UNESCAPED_SLASHES); ?>;
const yearDok = <?php echo (int) $current_year; ?>;
let pendingDokForm = null;
let dokConfirmModal = null;

function updatePreviewNomor() {
    const jenis = $('#jenisKode').val() || 'SK';
    const roman = $('#bulanDok option:selected').data('roman') || 'I';
    const nextUrut = nextUrutByJenis[jenis] || 1;
    const nomor = String(nextUrut).padStart(3, '0') + '/RSA/' + jenis + '/' + roman + '/' + yearDok;
    $('#previewNomor').text(nomor);
}

function openDokConfirm(options) {
    const modalEl = document.getElementById('dokConfirmModal');
    if (!modalEl) return false;

    modalEl.classList.toggle('is-danger', options.variant === 'danger');
    $('#dokConfirmTitle').text(options.title);
    $('#dokConfirmSubtitle').text(options.subtitle);
    $('#dokConfirmMessage').text(options.message);
    $('#dokConfirmPreview').text(options.preview);
    $('#dokConfirmYes')
        .removeClass('btn-primary btn-danger')
        .addClass(options.variant === 'danger' ? 'btn-danger' : 'btn-primary')
        .html(options.confirmHtml);

    if (window.bootstrap && bootstrap.Modal) {
        dokConfirmModal = dokConfirmModal || new bootstrap.Modal(modalEl);
        dokConfirmModal.show();
        return true;
    }

    return false;
}

$(document).ready(function() {
    $('#tblDok').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ data',
            info: 'Menampilkan _START_ s/d _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Data tidak ditemukan',
            paginate: { first:'Awal', last:'Akhir', next:'Selanjutnya', previous:'Sebelumnya' }
        },
        dom: "<'row mb-2'<'col-sm-6'B><'col-sm-6'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i>Export Excel',
                className: 'btn btn-success btn-sm',
                title: 'Generate_Nomor_DOK_<?php echo $year_filter; ?>',
                exportOptions: { columns: ':visible:not(.no-export)' }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-1"></i>Print',
                className: 'btn btn-outline-secondary btn-sm',
                exportOptions: { columns: ':visible:not(.no-export)' }
            }
        ]
    });

    $('#dokForm').on('submit', function(e) {
        e.preventDefault();
        pendingDokForm = this;
        const shown = openDokConfirm({
            title: 'Konfirmasi Data',
            subtitle: 'Pastikan data dokumen sudah sesuai.',
            message: 'Data sudah benar dan siap dibuatkan nomor dokumen?',
            preview: $('#previewNomor').text(),
            confirmHtml: '<i class="fas fa-save me-1"></i>Ya, Simpan',
            variant: 'primary'
        });

        if (!shown && confirm('Data sudah benar?')) {
            pendingDokForm.submit();
        }
    });

    $('.delete-dok-form').on('submit', function(e) {
        e.preventDefault();
        pendingDokForm = this;
        const nomorDok = $(this).closest('tr').find('.nomor-pill').text() || 'Nomor dokumen ini';
        const shown = openDokConfirm({
            title: 'Hapus Dokumen',
            subtitle: 'Aksi ini hanya untuk Super Admin.',
            message: 'Yakin ingin menghapus nomor dokumen ini? Data yang sudah dihapus tidak bisa dikembalikan.',
            preview: nomorDok,
            confirmHtml: '<i class="fas fa-trash-alt me-1"></i>Ya, Hapus',
            variant: 'danger'
        });

        if (!shown && confirm('Hapus nomor dokumen ini? Data yang sudah dihapus tidak bisa dikembalikan.')) {
            pendingDokForm.submit();
        }
    });

    $('#dokConfirmYes').on('click', function() {
        if (pendingDokForm) {
            pendingDokForm.submit();
        }
    });

    $('#jenisKode, #bulanDok').on('change', updatePreviewNomor);
    updatePreviewNomor();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
