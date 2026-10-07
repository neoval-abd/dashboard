<?php
$page_title = 'Kepegawaian';
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/kepegawaian.php';
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$kp_access = false;
try {
    $kp_access = kp_can_access($koneksi);
} catch (Throwable $error) {
    error_log('[Kepegawaian Akses] ' . $error->getMessage());
}
if (!$kp_access) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><title>Akses Ditolak</title><h3>Akses Kepegawaian ditolak.</h3><p>Anda tidak memiliki hak akses admin pegawai.</p><a href="dashboard.php">Kembali ke dashboard</a></html>';
    exit;
}
require_once __DIR__ . '/includes/header.php';
$kp_error = '';
$kp_options = [];
$kp_write = false;
$kp_date = date('Y-m-d');
try {
    $kp_options = kp_options($koneksi);
    $kp_write = kp_can_write($koneksi);
    $kp_date = kp_reference_date($koneksi);
} catch (Throwable $error) {
    error_log('[Kepegawaian] ' . $error->getMessage());
    $kp_error = 'Data referensi belum dapat dimuat. Silakan hubungi administrator.';
}
if (empty($_SESSION['kp_csrf'])) $_SESSION['kp_csrf'] = bin2hex(random_bytes(32));
function kp_escape($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<style>
.kp-tabs { gap:4px; overflow-x:auto; overflow-y:hidden; flex-wrap:nowrap; padding-bottom:2px; }
.kp-tabs .nav-link { white-space:nowrap; padding:8px 16px; font-size:.82rem; border-radius:6px 6px 0 0; }
.kp-stat { border-radius:8px; padding:14px 16px; min-height:78px; color:#fff; display:flex; justify-content:space-between; align-items:center; }
.kp-stat strong { font-size:1.3rem; display:block; }
.kp-stat small { font-weight:700; }
.kp-stat i { font-size:1.7rem; opacity:.65; }
#kpTable th, #kpTable td { font-size:.76rem; white-space:nowrap; vertical-align:middle; }
#kpTable th { font-weight:800; }
#kpForm .form-label { font-size:.8rem; font-weight:600; margin-bottom:4px; }
.kp-section { font-size:.9rem; font-weight:700; padding-bottom:8px; border-bottom:1px solid var(--bs-secondary); margin-bottom:14px; }
.kp-message { white-space:pre-wrap; }
</style>
<div class="d-flex justify-content-between align-items-center flex-wrap pt-3 pb-2 mb-3 border-bottom">
    <div><h4 class="mb-0"><i class="fas fa-id-card text-primary me-2"></i>Kepegawaian</h4>
    <small class="text-muted">Data pegawai dan index kepegawaian RS Assalam</small></div>
    <span class="badge bg-light text-dark border"><?php echo $kp_write ? 'Akses kelola pegawai' : 'Akses lihat data'; ?></span>
</div>
<?php if ($kp_error): ?><div class="alert alert-danger"><?php echo kp_escape($kp_error); ?></div><?php endif; ?>
<div id="kpMessage" class="alert kp-message d-none" role="status" aria-live="polite"></div>
<div class="card shadow-sm mb-3"><div class="card-body py-2">
    <ul class="nav nav-tabs kp-tabs mb-3" role="tablist" aria-label="Kepegawaian">
        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" aria-selected="true" aria-controls="kpDataPanel" id="kpListTab" data-kp-tab="list">List Data</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" aria-controls="kpInputPanel" id="kpInputTab" data-kp-tab="input">Input Data</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" aria-selected="false" aria-controls="kpDataPanel" id="kpIndexTab" data-kp-tab="index">Index Pegawai</button></li>
    </ul>
    <form id="kpFilters" class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small fw-bold" for="kpStatus">Status</label><select id="kpStatus" class="form-select form-select-sm"><option value="">Semua Status</option><?php foreach (kp_fields()['stts_aktif'][2] as $status): ?><option value="<?php echo kp_escape($status); ?>" <?php echo $status === 'AKTIF' ? 'selected' : ''; ?>><?php echo kp_escape($status); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label small fw-bold" for="kpDepartment">Departemen</label><select id="kpDepartment" class="form-select form-select-sm"><option value="">Semua Departemen</option><?php foreach ($kp_options['departemen'] ?? [] as $option): ?><option value="<?php echo kp_escape($option['value']); ?>"><?php echo kp_escape($option['label']); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label small fw-bold" for="kpDate">Tanggal Acuan Perhitungan</label><input id="kpDate" type="date" class="form-control form-control-sm" required value="<?php echo kp_escape($kp_date); ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-bold" for="kpKeyword">Kata Kunci</label><input id="kpKeyword" class="form-control form-control-sm" maxlength="150" placeholder="NIP, nama, jabatan, pendidikan..."></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary btn-sm flex-grow-1" id="kpLoad" type="submit"><i class="fas fa-check me-1"></i>Tampilkan</button><button type="button" class="btn btn-success btn-sm" id="kpExport" title="Export CSV" aria-label="Export CSV"><i class="fas fa-file-excel"></i></button></div>
    </form>
    <small class="text-muted d-block mt-2" id="kpReferenceHelp">Tanggal acuan awal memakai hari ini: <?php echo kp_escape($kp_date); ?>. Pilih tanggal lain untuk melihat perhitungan pada periode tersebut. Index pendidikan, status, dan jabatan mengikuti nilai master kepegawaian.</small>
</div></div>
<div id="kpDataPanel" role="tabpanel" aria-labelledby="kpListTab">
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="kp-stat bg-primary"><div><strong id="kpCount">0</strong><small>TOTAL PEGAWAI</small></div><i class="fas fa-users"></i></div></div>
        <div class="col-md-3"><div class="kp-stat bg-success"><div><strong id="kpActive">0</strong><small>PEGAWAI AKTIF</small></div><i class="fas fa-user-check"></i></div></div>
        <div class="col-md-3"><div class="kp-stat bg-info"><div><strong id="kpUnits">0</strong><small>DEPARTEMEN</small></div><i class="fas fa-building"></i></div></div>
        <div class="col-md-3"><div class="kp-stat bg-warning"><div><strong id="kpIndexSum">0</strong><small>TOTAL INDEX</small></div><i class="fas fa-chart-bar"></i></div></div>
    </div>
    <div id="kpIndexEditor" class="card shadow-sm mb-3 d-none"><div class="card-body">
        <h6 id="kpIndexName">Edit Index Pegawai</h6>
        <form id="kpIndexForm" class="row g-3"><input type="hidden" name="id">
            <div class="col-md-3"><label class="form-label" for="kpIndek">Index Struktural</label><input id="kpIndek" name="indek" type="number" min="0" max="127" step="1" required class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label" for="kpPengurang">Pengurang (%)</label><input id="kpPengurang" name="pengurang" type="number" min="0" max="100" step="any" required class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label" for="kpCuti">Cuti Diambil Manual</label><input id="kpCuti" name="cuti_diambil" type="number" min="0" max="2147483647" step="1" required class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label" for="kpDankes">Dana Kesehatan / Tahun</label><input id="kpDankes" name="dankes" type="number" min="0" max="1000000000000" step="any" required class="form-control form-control-sm"></div>
            <div class="col-12"><small class="text-muted">Cuti manual ditambahkan ke cuti lampiran dan pengajuan yang disetujui. Pengurang mengikuti rumus Khanza: 0 memakai total penuh; nilai di atas 0 menjadi persentase pengali total index.</small></div>
            <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm">Simpan Index</button><button type="button" class="btn btn-secondary btn-sm" id="kpCancelIndex">Batal</button></div>
        </form>
    </div></div>
    <div class="card shadow-sm mb-3"><div class="card-header d-flex justify-content-between align-items-center"><h6 class="mb-0" id="kpTableTitle">List Data Pegawai</h6><span class="badge bg-light text-dark border" id="kpTableInfo">Memuat data...</span></div><div class="card-body"><table id="kpTable" class="table table-bordered table-striped table-hover w-100"></table><small id="kpIndexNote" class="text-muted d-none">Index, gaji pokok, cuti, dan dana kesehatan dihitung sesuai sumber penggajian Khanza dengan tanggal acuan di atas.</small></div></div>
</div>
<div id="kpInputPanel" class="card shadow-sm mb-3 d-none" role="tabpanel" aria-labelledby="kpInputTab"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3"><h6 id="kpFormTitle" class="mb-0">Input Data Pegawai</h6><button type="button" id="kpNew" class="btn btn-outline-primary btn-sm">Pegawai Baru</button></div>
    <?php if (!$kp_write): ?><div class="alert alert-info py-2">Data dapat dilihat. Penyimpanan memerlukan hak akses kelola pegawai.</div><?php endif; ?>
    <form id="kpForm"><input type="hidden" name="id" value="0"><fieldset <?php echo !$kp_write || $kp_error ? 'disabled' : ''; ?>><div class="row g-3">
    <?php
    $sections = ['nik' => 'Identitas & Penempatan', 'npwp' => 'Biodata & Pendidikan', 'mulai_kerja' => 'Kepegawaian & Rekening'];
    foreach (kp_fields() as $field => $def):
        if (isset($sections[$field])): ?><div class="col-12 mt-4"><div class="kp-section"><?php echo kp_escape($sections[$field]); ?></div></div><?php endif; ?>
        <div class="col-md-6 col-xl-4"><label for="kpField_<?php echo $field; ?>" class="form-label"><?php echo kp_escape($def[0]); ?><?php if ($def[3]): ?><span class="text-danger"> *</span><?php endif; ?></label>
        <?php if ($def[1] === 'master' || $def[1] === 'select'): ?>
            <select id="kpField_<?php echo $field; ?>" name="<?php echo $field; ?>" class="form-select form-select-sm" <?php echo $def[3] ? 'required' : ''; ?>>
                <?php if ($def[1] === 'master'): ?><option value="">Pilih...</option><?php endif; ?>
                <?php foreach ($kp_options[$field] ?? [] as $option): ?><option value="<?php echo kp_escape($option['value']); ?>"><?php echo kp_escape($field === 'indexins' ? $option['value'] . ' — ' . $option['label'] . '%' : $option['label']); ?></option><?php endforeach; ?>
            </select>
        <?php else: ?>
            <input id="kpField_<?php echo $field; ?>" name="<?php echo $field; ?>" type="<?php echo $def[1]; ?>" class="form-control form-control-sm" <?php echo $def[3] ? 'required' : ''; ?> <?php if ($def[1] === 'text') echo 'maxlength="' . $def[2] . '"'; ?> <?php if ($def[1] === 'number') echo 'min="' . $def[2][0] . '" max="' . $def[2][1] . '" step="1" value="-5"'; ?>>
        <?php endif; ?><?php if ($field === 'nik'): ?><small id="kpNipHelp" class="text-muted d-none">NIP pegawai yang sudah tersimpan dikunci agar hubungan data Khanza tetap sesuai.</small><?php endif; ?></div>
    <?php endforeach; ?>
    <div class="col-12"><small class="text-muted">Wajib Masuk: -5 mengikuti jadwal; -4 bulan dikurangi hari Ahad; -3 bulan dikurangi dua hari dan libur nasional; -2 bulan dikurangi empat hari; -1 kosong; 0 bulan dikurangi hari libur; angka positif jumlah hari.</small></div>
    <div class="col-12 d-flex gap-2 mt-4"><button type="submit" id="kpSave" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Simpan</button><button type="button" id="kpReset" class="btn btn-secondary btn-sm">Kosongkan</button></div>
    </div></fieldset></form>
</div></div>
<?php ob_start(); ?>
<script>window.kpConfig = <?php echo json_encode(['canWrite' => $kp_write && !$kp_error, 'csrf' => $_SESSION['kp_csrf'], 'ready' => !$kp_error], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="assets/js/kepegawaian.js?v=<?php echo filemtime(__DIR__ . '/assets/js/kepegawaian.js'); ?>"></script>
<?php $page_js = ob_get_clean(); require_once __DIR__ . '/includes/footer.php'; ?>
