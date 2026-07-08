<?php
$page_title = "Reminder Kontrol Pasien";
require_once('includes/header.php');

// ============================================================
// Query: Pasien yang dijadwalkan KONTROL pada range tanggal pilihan user
// Patokan utama: bridging_surat_kontrol_bpjs (tgl_rencana)
// Data pelengkap (no RM, nama, jenis kelamin, no HP) diambil dari bridging_sep
// via no_sep. Status terkirim dicek dari log_kirim_reminder_kontrol.
// ============================================================
$today = date('Y-m-d');
$tgl_awal = $_GET['tgl_awal'] ?? $today;
$tgl_akhir = $_GET['tgl_akhir'] ?? $today;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_awal)) $tgl_awal = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir)) $tgl_akhir = $tgl_awal;
if (strtotime($tgl_awal) > strtotime($tgl_akhir)) {
    [$tgl_awal, $tgl_akhir] = [$tgl_akhir, $tgl_awal];
}

$periode_label = ($tgl_awal === $tgl_akhir)
    ? date('d F Y', strtotime($tgl_awal))
    : date('d F Y', strtotime($tgl_awal)) . ' - ' . date('d F Y', strtotime($tgl_akhir));

$sql = "SELECT 
    k.no_sep,
    k.tgl_surat,
    k.no_surat,
    k.tgl_rencana,
    k.kd_dokter_bpjs,
    k.nm_dokter_bpjs,
    k.kd_poli_bpjs,
    k.nm_poli_bpjs,
    s.nomr,
    s.nama_pasien,
    s.jkel,
    s.notelep,
    s.no_kartu,
    l.tgl_kirim
FROM bridging_surat_kontrol_bpjs k
INNER JOIN bridging_sep s ON k.no_sep = s.no_sep
LEFT JOIN (
    SELECT no_sep, MAX(tgl_kirim) AS tgl_kirim
    FROM log_kirim_reminder_kontrol
    GROUP BY no_sep
) l ON l.no_sep = k.no_sep
WHERE k.tgl_rencana BETWEEN ? AND ?
ORDER BY s.nama_pasien ASC";

$kontrol_patients = [];
$sent_count      = 0;
$has_phone_count = 0;

if ($stmt = $koneksi->prepare($sql)) {
    $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['is_sent'] = !empty($row['tgl_kirim']) ? 1 : 0;

            // Sanitize nomor HP (sama seperti versi ulang tahun)
            $phone_raw = trim($row['notelep'] ?? '');
            $row['has_phone'] = false;
            $row['phone_wa']  = '';
            $row['phone_raw'] = $phone_raw;
            if (!empty($phone_raw) && $phone_raw !== '-') {
                $clean = preg_replace('/[^0-9]/', '', $phone_raw);
                if (substr($clean, 0, 1) === '0') $clean = '62' . substr($clean, 1);
                elseif (substr($clean, 0, 2) !== '62') $clean = '62' . $clean;
                $row['phone_wa']  = $clean;
                $row['has_phone'] = true;
            }

            if ($row['is_sent'])    $sent_count++;
            if ($row['has_phone'])  $has_phone_count++;

            $kontrol_patients[] = $row;
        }
    }
    } else {
        // DEBUG SEMENTARA — hapus baris ini setelah masalah ketemu
    }

$total         = count($kontrol_patients);
$pending_count = $total - $sent_count;
?>

<style>
    .kontrol-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }
    .kontrol-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    .kontrol-header {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: #fff;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .kontrol-avatar {
        width: 52px; height: 52px;
        border-radius: 50%;
        background: rgba(255,255,255,0.25);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .kontrol-body { padding: 18px 20px; }
    .kontrol-info { font-size: 0.85rem; color: #555; }
    .kontrol-info .label { font-weight: 600; color: #333; min-width: 110px; display: inline-block; }
    .kontrol-date { color: #6c757d; font-size: 0.875rem; }
    .kontrol-badge {
        display: inline-block;
        background: linear-gradient(135deg, #ff9966 0%, #ff5e62 100%);
        color: #fff;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .kontrol-actions { padding: 0 20px 18px; display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-wa {
        background: #25D366;
        color: #fff;
        border: none;
        padding: 8px 18px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-wa:hover { background: #1ebe57; color: #fff; }
    .btn-wa:disabled { background: #ccc; cursor: not-allowed; }
    .kontrol-empty {
        text-align: center;
        padding: 60px 20px;
    }
    .kontrol-empty i { font-size: 4rem; color: #ddd; margin-bottom: 16px; }
    .filter-bar {
        background: #fff;
        border: 1px solid rgba(0,0,0,0.08);
        border-radius: 12px;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .filter-bar .form-control {
        background-color: #fff !important;
        color: #212529 !important;
        border: 1px solid #ced4da !important;
        color-scheme: light;
    }
    .filter-bar .form-control::placeholder {
        color: #6c757d !important;
        opacity: 1;
    }
    .filter-bar .form-control:focus {
        background-color: #fff !important;
        color: #212529 !important;
        border-color: #86b7fe !important;
        box-shadow: 0 0 0 .2rem rgba(13,110,253,.18) !important;
    }
    .filter-btn {
        padding: 8px 20px;
        border-radius: 20px;
        border: 2px solid #dee2e6;
        background: #fff;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s;
        color: #555;
    }
    .filter-btn:hover { border-color: #4facfe; color: #4facfe; }
    .filter-btn.active {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: #fff;
        border-color: transparent;
    }
    .kontrol-col.hidden-card { display: none !important; }
    .stat-kontrol {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: #fff;
        border-radius: 14px;
        padding: 20px 24px;
    }
    .msg-editor textarea {
        width: 100%;
        min-height: 140px;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 12px;
        font-size: 0.9rem;
        resize: vertical;
    }
    .msg-editor textarea:focus {
        outline: none;
        border-color: #25D366;
        box-shadow: 0 0 0 3px rgba(37,211,102,0.15);
    }
</style>

<!-- Header -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
    <h1 class="h2"><i class="fas fa-bell text-warning me-2"></i> Reminder Kontrol Pasien</h1>
    <span class="badge bg-primary fs-6 px-3 py-2">
        <i class="fas fa-calendar-day me-1"></i> <?php echo htmlspecialchars($periode_label); ?>
    </span>
</div>

<!-- Stats -->
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="stat-kontrol">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold"><?php echo $total; ?></h2>
                    <small class="opacity-75">Total kontrol periode ini</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-kontrol" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-paper-plane fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="sentCount"><?php echo $sent_count; ?></h2>
                    <small class="opacity-75">Sudah dikirim</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-kontrol" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-hourglass-half fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="pendingCount"><?php echo $pending_count; ?></h2>
                    <small class="opacity-75">Belum dikirim</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-kontrol" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-phone fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold"><?php echo $has_phone_count; ?></h2>
                    <small class="opacity-75">Punya nomor HP</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mb-4">
    <span style="color: #484848;" class="fw-bold"><i class="fas fa-filter me-1"></i> Filter:</span>
    <form method="get" class="d-flex align-items-center gap-2 flex-wrap me-2">
        <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tgl_awal); ?>" style="max-width: 155px;">
        <span class="text-muted small">s/d</span>
        <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?php echo htmlspecialchars($tgl_akhir); ?>" style="max-width: 155px;">
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-search me-1"></i> Tampilkan
        </button>
        <a href="laporan_reminder_kontrol.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-undo me-1"></i> Hari Ini
        </a>
    </form>
    <button class="filter-btn active" data-filter="pending" id="btnFilterPending">
        <i class="fas fa-hourglass-half me-1"></i> Belum Dikirim <span class="badge bg-light text-dark ms-1" id="badgePending"><?php echo $pending_count; ?></span>
    </button>
    <button class="filter-btn" data-filter="sent" id="btnFilterSent">
        <i class="fas fa-check me-1"></i> Sudah Dikirim <span class="badge bg-light text-dark ms-1" id="badgeSent"><?php echo $sent_count; ?></span>
    </button>
    <button class="filter-btn" data-filter="all" id="btnFilterAll">
        <i class="fas fa-list me-1"></i> Semua Pasien <span class="badge bg-light text-dark ms-1"><?php echo $total; ?></span>
    </button>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="small text-muted"><i class="fas fa-search"></i></span>
        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari nama / no. RM..." style="max-width: 220px; border-radius: 20px;">
    </div>
</div>

<!-- Content -->
<?php if ($total === 0): ?>
    <div class="kontrol-empty">
        <i class="fas fa-calendar-check d-block"></i>
        <h4 class="text-muted fw-bold">Tidak ada pasien yang dijadwalkan kontrol pada periode ini</h4>
        <p class="text-muted">Silakan ubah range tanggal untuk melihat jadwal kontrol lainnya.</p>
    </div>
<?php else: ?>
    <div class="row g-3" id="kontrolGrid">
        <?php foreach ($kontrol_patients as $i => $p): ?>
            <?php
                $gender_icon  = ($p['jkel'] === 'L') ? 'fa-mars text-primary' : 'fa-venus text-danger';
                $gender_label = ($p['jkel'] === 'L') ? 'Laki-laki' : 'Perempuan';
                $nomr   = htmlspecialchars($p['nomr']);
                $nm     = htmlspecialchars($p['nama_pasien']);
                $poli   = htmlspecialchars($p['nm_poli_bpjs'] ?? '-');
                $dokter = htmlspecialchars($p['nm_dokter_bpjs'] ?? '-');
                $no_surat = htmlspecialchars($p['no_surat']);
                $tgl_surat   = !empty($p['tgl_surat'])   ? date('d-m-Y', strtotime($p['tgl_surat']))   : '-';
                $tgl_rencana = !empty($p['tgl_rencana'])  ? date('d-m-Y', strtotime($p['tgl_rencana'])) : '-';
            ?>
            <div class="col-lg-4 col-md-6 kontrol-col" data-sent="<?php echo $p['is_sent']; ?>" data-name="<?php echo strtolower($p['nama_pasien']); ?>" data-nomr="<?php echo strtolower($p['nomr']); ?>">
                <div class="kontrol-card" data-idx="<?php echo $i; ?>">
                    <div class="kontrol-header">
                        <div class="kontrol-avatar">
                            <i class="fas <?php echo $gender_icon; ?>" style="color:#fff"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold"><?php echo $nm; ?></h5>
                            <small class="opacity-75"><?php echo $nomr; ?> &middot; <?php echo $gender_label; ?></small>
                        </div>
                    </div>
                    <div class="kontrol-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="kontrol-badge"><i class="fas fa-calendar-check me-1"></i> Jadwal Kontrol</span>
                            <small class="kontrol-date fs-6"><i class="fas fa-calendar me-1"></i> <?php echo $tgl_rencana; ?></small>
                        </div>
                        <div class="kontrol-info mt-2">
                            <div><span class="label"><i class="fas fa-stethoscope me-1"></i> Poli</span>: <strong><?php echo $poli; ?></strong></div>
                            <div class="mt-1"><span class="label"><i class="fas fa-user-md me-1"></i> Dokter</span>: <strong><?php echo $dokter; ?></strong></div>
                            <div class="mt-1"><span class="label"><i class="fas fa-file-alt me-1"></i> No.SKDP</span>: <?php echo $no_surat; ?></div>
                            <div class="mt-1"><span class="label"><i class="fas fa-clock me-1"></i> Tgl Surat</span>: <?php echo $tgl_surat; ?></div>
                            <div class="mt-1"><span class="label"><i class="fas fa-phone me-1"></i> No. HP</span>: 
                                <?php if ($p['has_phone']): ?>
                                    <strong><?php echo htmlspecialchars($p['phone_raw']); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Tidak tersedia</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="kontrol-actions">
                        <?php if ($p['has_phone']): ?>
                            <button class="btn-wa btn-send-wa <?php echo $p['is_sent'] ? 'btn-already-sent' : ''; ?>" 
                                data-phone="<?php echo $p['phone_wa']; ?>" 
                                data-name="<?php echo $nm; ?>" 
                                data-nomr="<?php echo $nomr; ?>"
                                data-no-sep="<?php echo htmlspecialchars($p['no_sep']); ?>"
                                data-poli="<?php echo $poli; ?>"
                                data-dokter="<?php echo $dokter; ?>"
                                data-tanggal="<?php echo $tgl_rencana; ?>"
                                <?php if ($p['is_sent']) echo 'disabled style="background:#6c757d;cursor:default;"'; ?>>
                                <?php if ($p['is_sent']): ?>
                                    <i class="fas fa-check"></i> Terkirim
                                <?php else: ?>
                                    <i class="fab fa-whatsapp"></i> Kirim Reminder
                                <?php endif; ?>
                            </button>
                        <?php else: ?>
                            <button class="btn-wa" disabled>
                                <i class="fas fa-phone-slash"></i> No. HP Tidak Ada
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary btn-preview" 
                            data-name="<?php echo $nm; ?>" data-poli="<?php echo $poli; ?>" 
                            data-dokter="<?php echo $dokter; ?>" data-tanggal="<?php echo $tgl_rencana; ?>">
                            <i class="fas fa-eye"></i> Preview Pesan
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Message Preview Modal -->
<div class="modal fade" id="msgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #25D366, #128C7E);">
                <h5 class="modal-title fw-bold"><i class="fab fa-whatsapp me-2"></i> Kirim Reminder Kontrol</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body msg-editor">
                <div class="mb-3">
                    <label class="form-label fw-bold">Kepada:</label>
                    <p class="mb-0" id="msgRecipient">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Pesan Reminder:</label>
                    <textarea id="msgText" class="form-control"></textarea>
                </div>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Pesan akan masuk antrean dan dikirim otomatis bertahap melalui Fonnte.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button id="btnSendWA" type="button" class="btn-wa" style="padding:10px 24px;">
                    <i class="fas fa-clock me-1"></i> Masukkan Antrean
                </button>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
$(document).ready(function() {
    let currentFilter = 'pending'; // default: belum dikirim
    let sentCount    = <?php echo $sent_count; ?>;
    let pendingCount = <?php echo $pending_count; ?>;
    let fonnteCooldownSeconds = 0;
    let fonnteCooldownUntil = 0;
    let fonnteCooldownTimer = null;

    function showSuccessNotification(message) {
        let $notif = $('#waSuccessNotif');
        if (!$notif.length) {
            $('body').append(`
                <div id="waSuccessNotif" class="alert alert-success shadow-lg d-flex align-items-center gap-2"
                    style="display:none; position:fixed; top:22px; right:22px; z-index:9999; border-radius:12px; min-width:280px; max-width:420px;">
                    <i class="fas fa-check-circle"></i>
                    <span class="wa-success-text"></span>
                </div>
            `);
            $notif = $('#waSuccessNotif');
        }

        $notif.find('.wa-success-text').text(message);
        $notif.stop(true, true).fadeIn(180);
        setTimeout(function() {
            $notif.fadeOut(250);
        }, 3500);
    }

    function getAjaxErrorMessage(xhr, fallback) {
        if (xhr.responseJSON && xhr.responseJSON.error) {
            return xhr.responseJSON.error;
        }
        if (xhr.responseJSON && xhr.responseJSON.message) {
            return xhr.responseJSON.message;
        }

        const responseText = String(xhr.responseText || '').trim();
        if (responseText) {
            try {
                const parsed = JSON.parse(responseText);
                if (parsed.error) return parsed.error;
                if (parsed.message) return parsed.message;
            } catch (e) {
                return responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 220);
            }
        }

        if (xhr.status) {
            return fallback + ' (HTTP ' + xhr.status + ')';
        }

        return fallback;
    }

    function getCooldownRemaining() {
        return Math.max(0, Math.ceil((fonnteCooldownUntil - Date.now()) / 1000));
    }

    function startFonnteCooldown(seconds) {
        seconds = parseInt(seconds, 10) || 0;
        if (seconds <= 0) {
            fonnteCooldownUntil = 0;
            updateFonnteCooldownUi();
            return;
        }

        fonnteCooldownUntil = Date.now() + (seconds * 1000);
        updateFonnteCooldownUi();

        if (fonnteCooldownTimer) {
            clearInterval(fonnteCooldownTimer);
        }

        fonnteCooldownTimer = setInterval(function() {
            updateFonnteCooldownUi();
            if (getCooldownRemaining() <= 0) {
                clearInterval(fonnteCooldownTimer);
                fonnteCooldownTimer = null;
            }
        }, 1000);
    }

    function updateFonnteCooldownUi() {
        const remaining = getCooldownRemaining();
        const isCoolingDown = remaining > 0;

        $('.btn-send-wa').each(function() {
            const $btn = $(this);
            const $col = $btn.closest('.kontrol-col');
            const isSent = String($col.attr('data-sent') || $col.data('sent') || '0') === '1';
            const isQueued = String($col.attr('data-queued') || $col.data('queued') || '0') === '1';
            if (isSent) return;
            if (isQueued) return;

            $btn.prop('disabled', false);
            if (isCoolingDown) {
                $btn.attr('title', 'Fonnte sedang jeda ' + remaining + ' detik. Klik tetap hanya masuk antrean.');
            } else {
                $btn.html('<i class="fab fa-whatsapp"></i> Kirim Reminder');
                $btn.removeAttr('title');
            }
        });

        const $modalBtn = $('#btnSendWA');
        if ($modalBtn.length && !$modalBtn.data('is-sending')) {
            $modalBtn.prop('disabled', false);
            $modalBtn.html('<i class="fas fa-clock me-1"></i> Masukkan Antrean');
        }
    }

    // Apply filter + search
    function applyFilterAndSearch() {
        const filter = currentFilter;
        const q = $('#searchInput').val().toLowerCase().trim();

        $('.kontrol-col').each(function() {
            const isSent = $(this).data('sent');
            const name = String($(this).data('name') || '');
            const nomr = String($(this).data('nomr') || '');
            const searchMatch = !q || name.indexOf(q) !== -1 || nomr.indexOf(q) !== -1;
            let show = searchMatch;
            if (filter === 'pending') show = show && (isSent == 0);
            if (filter === 'sent')    show = show && (isSent == 1);

            if (show) {
                $(this).removeClass('hidden-card');
            } else {
                $(this).addClass('hidden-card');
            }
        });
    }

    $('#btnFilterPending').on('click', function() {
        currentFilter = 'pending';
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        applyFilterAndSearch();
    });
    $('#btnFilterSent').on('click', function() {
        currentFilter = 'sent';
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        applyFilterAndSearch();
    });
    $('#btnFilterAll').on('click', function() {
        currentFilter = 'all';
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        applyFilterAndSearch();
    });
    $('#searchInput').on('input', function() {
        applyFilterAndSearch();
    });

    function markCardAsSent(cardBtn) {
        if (!cardBtn) return;

        const $btn = $(cardBtn);
        const $col = $btn.closest('.kontrol-col');
        const wasSent = String($col.attr('data-sent') || $col.data('sent') || '0') === '1';

        $btn.html('<i class="fas fa-check"></i> Terkirim')
            .prop('disabled', true)
            .css({background: '#6c757d', cursor: 'default'});
        $col.attr('data-sent', 1).data('sent', 1);
        $col.attr('data-queued', 0).data('queued', 0);

        if (!wasSent) {
            sentCount++;
            pendingCount = Math.max(0, pendingCount - 1);
            $('#sentCount').text(sentCount);
            $('#pendingCount').text(pendingCount);
            $('#badgeSent').text(sentCount);
            $('#badgePending').text(pendingCount);
        }

        applyFilterAndSearch();
    }

    function markCardAsQueued(cardBtn, scheduledAt) {
        if (!cardBtn) return;

        const $btn = $(cardBtn);
        const $col = $btn.closest('.kontrol-col');
        const label = scheduledAt ? 'Terjadwal ' + scheduledAt.substring(11, 16) : 'Terjadwal';

        $btn.html('<i class="fas fa-clock"></i> ' + label)
            .prop('disabled', true)
            .css({background: '#0d6efd', cursor: 'default'});
        $col.attr('data-queued', 1).data('queued', 1);
    }

    function loadSentStatus() {
        $.getJSON('api/reminder_kontrol.php', function(res) {
            const sentList = res.sent || [];
            const sentSeps = sentList.map(item => String(item.no_sep || ''));
            const queuedList = res.queued || [];
            const queuedBySep = {};
            fonnteCooldownSeconds = parseInt(res.fonnte_cooldown_seconds, 10) || 0;

            queuedList.forEach(function(item) {
                if (item && item.no_sep) queuedBySep[String(item.no_sep)] = item;
            });

            $('.btn-send-wa').each(function() {
                const noSep = String($(this).data('no-sep') || '');
                if (sentSeps.includes(noSep)) {
                    markCardAsSent(this);
                } else if (queuedBySep[noSep] && queuedBySep[noSep].status !== 'failed') {
                    markCardAsQueued(this, queuedBySep[noSep].scheduled_at || '');
                }
            });

            startFonnteCooldown(parseInt(res.fonnte_cooldown_remaining, 10) || 0);
        });
    }

    applyFilterAndSearch();
    loadSentStatus();

    // Default message template
    function getDefaultMsg(name, poli, dokter, tanggal) {
        return `Yth. Bapak/Ibu *${name}*,\n\nKami mengingatkan bahwa Anda memiliki jadwal *KONTROL* pada tanggal, ${tanggal} di Poli ${poli} dengan dokter ${dokter}.\n\nMohon untuk datang sesuai jadwal yang telah ditentukan. Apabila ada pertanyaan atau perubahan jadwal, silakan hubungi kami.\n\nTerima kasih.\n\nSalam hangat,\nRSU Adella Slawi`;
    }

    // Open message editor modal
    $(document).on('click', '.btn-send-wa', function() {
        const phone   = $(this).data('phone');
        const name    = $(this).data('name');
        const nomr    = $(this).data('nomr');
        const noSep   = $(this).data('no-sep');
        const poli    = $(this).data('poli');
        const dokter  = $(this).data('dokter');
        const tanggal = $(this).data('tanggal');

        $('#msgRecipient').html(`<strong>${name}</strong> <span class="text-muted">(${nomr})</span> <i class="fab fa-whatsapp text-success ms-1"></i> ${phone}`);
        $('#msgText').val(getDefaultMsg(name, poli, dokter, tanggal));

        $('#btnSendWA').data('phone', phone).data('card-btn', this).data('no-sep', noSep)
            .data('nomr', nomr).data('name', name);

        new bootstrap.Modal(document.getElementById('msgModal')).show();
    });

    // Preview button
    $(document).on('click', '.btn-preview', function() {
        const name    = $(this).data('name');
        const poli    = $(this).data('poli');
        const dokter  = $(this).data('dokter');
        const tanggal = $(this).data('tanggal');
        alert(getDefaultMsg(name, poli, dokter, tanggal));
    });

    $('#btnSendWA').on('click', function(e) {
        e.preventDefault();
        const phone = $(this).data('phone');
        const noSep = $(this).data('no-sep');
        const nomr  = $(this).data('nomr');
        const name  = $(this).data('name');
        const msg   = $('#msgText').val();

        const cardBtn = $(this).data('card-btn');
        const $sendBtn = $(this);
        $sendBtn.data('is-sending', true).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menjadwalkan...');

        $.ajax({
            url: 'api/reminder_kontrol.php',
            method: 'POST',
            dataType: 'json',
            global: false,
            timeout: 45000,
            data: {
                no_sep: noSep,
                nomr: nomr,
                nama_pasien: name,
                phone: phone,
                message: msg,
                pengirim: ''
            }
        }).done(function(res) {
            if (!res || res.success !== true) {
                if (res && res.fonnte && res.fonnte.retry_after) {
                    startFonnteCooldown(res.fonnte.retry_after);
                }
                alert((res && res.error) ? res.error : 'Reminder gagal dikirim. Silakan coba lagi atau hubungi admin.');
                return;
            }

            markCardAsQueued(cardBtn, res.scheduled_at || '');
            bootstrap.Modal.getInstance(document.getElementById('msgModal'))?.hide();
            setTimeout(function() {
                showSuccessNotification('Reminder ' + name + ' masuk antrean kirim.');
            }, 250);
        }).fail(function(xhr) {
            alert(getAjaxErrorMessage(xhr, 'Reminder gagal dikirim. Silakan coba lagi atau hubungi admin.'));
        }).always(function() {
            $sendBtn.data('is-sending', false);
            updateFonnteCooldownUi();
        });
    });
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
