<?php
$page_title = "Ucapan Ulang Tahun Pasien";
require_once('includes/header.php');

// ============================================================
// Query: Pasien yang berulang tahun hari ini
// ============================================================
$today_month = (int) date('n');
$today_day   = (int) date('j');

$sql = "SELECT 
    p.no_rkm_medis,
    p.nm_pasien,
    p.tgl_lahir,
    p.no_tlp,
    p.jk,
    TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURDATE()) AS usia,
    (SELECT MAX(rp.tgl_registrasi) 
     FROM reg_periksa rp 
     WHERE rp.no_rkm_medis = p.no_rkm_medis) AS last_visit
FROM pasien p
WHERE MONTH(p.tgl_lahir) = ? 
  AND DAY(p.tgl_lahir) = ?
  AND p.tgl_lahir IS NOT NULL
  AND p.tgl_lahir != '0000-00-00'
ORDER BY p.nm_pasien ASC";

$five_years_ago = date('Y-m-d', strtotime('-5 years'));
$one_year_ago  = date('Y-m-d', strtotime('-1 year'));
$active_count = 0;
$one_year_count = 0;

$birthday_patients = [];
if ($stmt = $koneksi->prepare($sql)) {
    $stmt->bind_param("ii", $today_month, $today_day);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['is_active']    = (!empty($row['last_visit']) && $row['last_visit'] >= $five_years_ago) ? 1 : 0;
            $row['is_active_1y'] = (!empty($row['last_visit']) && $row['last_visit'] >= $one_year_ago) ? 1 : 0;
            if ($row['is_active'])    $active_count++;
            if ($row['is_active_1y']) $one_year_count++;
            $birthday_patients[] = $row;
        }
    }
    $stmt->close();
}

$total = count($birthday_patients);
?>

<style>
    .bday-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,0.08);
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }
    .bday-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    }
    .bday-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .bday-avatar {
        width: 52px; height: 52px;
        border-radius: 50%;
        background: rgba(255,255,255,0.25);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }
    .bday-body { padding: 18px 20px; }
    .bday-info { font-size: 0.85rem; color: #555; }
    .bday-info .label { font-weight: 600; color: #333; min-width: 90px; display: inline-block; }
    .bday-age-badge {
        display: inline-block;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: #fff;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .bday-actions { padding: 0 20px 18px; display: flex; gap: 8px; flex-wrap: wrap; }
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
    .bday-empty {
        text-align: center;
        padding: 60px 20px;
    }
    .bday-empty i { font-size: 4rem; color: #ddd; margin-bottom: 16px; }
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
    .filter-btn:hover { border-color: #667eea; color: #667eea; }
    .filter-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-color: transparent;
    }
    .bday-col.hidden-card { display: none !important; }
    .stat-bday {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-radius: 14px;
        padding: 20px 24px;
    }
    /* Message editor modal */
    .msg-editor textarea {
        width: 100%;
        min-height: 120px;
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
    <h1 class="h2"><i class="fas fa-birthday-cake text-danger me-2"></i> Ucapan Ulang Tahun Pasien</h1>
    <span class="badge bg-primary fs-6 px-3 py-2">
        <i class="fas fa-calendar-day me-1"></i> <?php echo date('d F Y'); ?>
    </span>
</div>

<!-- Stats -->
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="stat-bday">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-gift fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold"><?php echo $total; ?></h2>
                    <small class="opacity-75">Total ultah hari ini</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-bday" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-user-check fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="visibleCount"><?php echo $active_count; ?></h2>
                    <small class="opacity-75">Ditampilkan</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-bday" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-paper-plane fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="sentCount">0</h2>
                    <small class="opacity-75">Ucapan terkirim</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-bday" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-phone fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="hasPhoneCount">0</h2>
                    <small class="opacity-75">Punya nomor HP</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mb-4">
    <span class="fw-bold text-muted"><i class="fas fa-filter me-1"></i> Filter:</span>
    <button class="filter-btn" data-filter="1y" id="btnFilter1y">
        <i class="fas fa-hourglass-half me-1"></i> 1 Tahun Terakhir <span class="badge bg-light text-dark ms-1"><?php echo $one_year_count; ?></span>
    </button>
    <button class="filter-btn active" data-filter="active" id="btnFilterActive">
        <i class="fas fa-clock me-1"></i> 5 Tahun Terakhir <span class="badge bg-light text-dark ms-1"><?php echo $active_count; ?></span>
    </button>
    <button class="filter-btn" data-filter="all" id="btnFilterAll">
        <i class="fas fa-list me-1"></i> Semua Pasien <span class="badge bg-light text-dark ms-1"><?php echo $total; ?></span>
    </button>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="small text-muted"><i class="fas fa-search"></i></span>
        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari nama pasien..." style="max-width: 200px; border-radius: 20px;">
    </div>
</div>

<!-- Content -->
<?php if ($total === 0): ?>
    <div class="bday-empty">
        <i class="fas fa-birthday-cake d-block"></i>
        <h4 class="text-muted fw-bold">Tidak ada pasien berulang tahun hari ini</h4>
        <p class="text-muted">Coba cek lagi besok, atau ubah tanggal server untuk testing.</p>
    </div>
<?php else: ?>
    <div class="row g-3" id="bdayGrid">
        <?php foreach ($birthday_patients as $i => $p): ?>
            <?php
                $phone_raw = trim($p['no_tlp'] ?? '');
                $phone_wa  = '';
                $has_phone = false;
                if (!empty($phone_raw) && $phone_raw !== '-') {
                    // Sanitize: remove spaces, dashes, leading 0 → 62
                    $clean = preg_replace('/[^0-9]/', '', $phone_raw);
                    if (substr($clean, 0, 1) === '0') $clean = '62' . substr($clean, 1);
                    elseif (substr($clean, 0, 2) !== '62') $clean = '62' . $clean;
                    $phone_wa  = $clean;
                    $has_phone = true;
                }
                $gender_icon = ($p['jk'] === 'L') ? 'fa-mars text-primary' : 'fa-venus text-danger';
                $gender_label = ($p['jk'] === 'L') ? 'Laki-laki' : 'Perempuan';
                $usia = (int) $p['usia'];
                $rm = htmlspecialchars($p['no_rkm_medis']);
                $nm = htmlspecialchars($p['nm_pasien']);
                $tgl = !empty($p['tgl_lahir']) ? date('d-m-Y', strtotime($p['tgl_lahir'])) : '-';
            ?>
            <div class="col-lg-4 col-md-6 bday-col" data-active="<?php echo $p['is_active']; ?>" data-active-1y="<?php echo $p['is_active_1y']; ?>" data-name="<?php echo strtolower($p['nm_pasien']); ?>">
                <div class="bday-card" data-idx="<?php echo $i; ?>">
                    <div class="bday-header">
                        <div class="bday-avatar">
                            <i class="fas <?php echo $gender_icon; ?>" style="color:#fff"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold"><?php echo $nm; ?></h5>
                            <small class="opacity-75"><?php echo $rm; ?> &middot; <?php echo $gender_label; ?></small>
                        </div>
                    </div>
                    <div class="bday-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="bday-age-badge"><i class="fas fa-star me-1"></i> Usia ke-<?php echo $usia; ?></span>
                            <small class="text-muted fs-6"><i class="fas fa-calendar me-1"></i> <?php echo $tgl; ?></small>
                        </div>
                        <div class="bday-info mt-2">
                            <div><span class="label"><i class="fas fa-phone me-1"></i> No. HP</span>: 
                                <?php if ($has_phone): ?>
                                    <strong><?php echo htmlspecialchars($phone_raw); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Tidak tersedia</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($p['last_visit'])): ?>
                            <div class="mt-1"><span class="label"><i class="fas fa-hospital me-1"></i> Kunjungan</span>: 
                                <span><?php echo date('d-m-Y', strtotime($p['last_visit'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bday-actions">
                        <?php if ($has_phone): ?>
                            <button class="btn-wa btn-send-wa" 
                                data-phone="<?php echo $phone_wa; ?>" 
                                data-name="<?php echo $nm; ?>" 
                                data-age="<?php echo $usia; ?>"
                                data-rm="<?php echo $rm; ?>">
                                <i class="fab fa-whatsapp"></i> Kirim Ucapan
                            </button>
                        <?php else: ?>
                            <button class="btn-wa" disabled>
                                <i class="fas fa-phone-slash"></i> No. HP Tidak Ada
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary btn-preview" 
                            data-name="<?php echo $nm; ?>" data-age="<?php echo $usia; ?>">
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
                <h5 class="modal-title fw-bold"><i class="fab fa-whatsapp me-2"></i> Kirim Ucapan Ulang Tahun</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body msg-editor">
                <div class="mb-3">
                    <label class="form-label fw-bold">Kepada:</label>
                    <p class="mb-0" id="msgRecipient">-</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Pesan Ucapan:</label>
                    <textarea id="msgText" class="form-control"></textarea>
                </div>
                <div class="alert alert-info py-2 small mb-0">
                    <i class="fas fa-info-circle me-1"></i> Pesan akan dibuka di WhatsApp. Anda bisa edit lagi sebelum kirim.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a id="btnSendWA" href="#" target="_blank" class="btn-wa text-decoration-none" style="padding:10px 24px;">
                    <i class="fab fa-whatsapp me-1"></i> Buka WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
$(document).ready(function() {
    const TOTAL = <?php echo $total; ?>;
    const ACTIVE_COUNT = <?php echo $active_count; ?>;
    const ONE_YEAR_COUNT = <?php echo $one_year_count; ?>;
    let currentFilter = 'active'; // default: 5 tahun terakhir
    let sentCount = 0;

    // Count visible phone buttons
    function updatePhoneCount() {
        const visible = $('.bday-col:not(.hidden-card) .btn-send-wa').length;
        $('#hasPhoneCount').text(visible);
    }

    // Apply filter + search
    function applyFilterAndSearch() {
        const filter = currentFilter;
        const q = $('#searchInput').val().toLowerCase().trim();
        let visibleCount = 0;

        $('.bday-col').each(function() {
            const isActive = $(this).data('active');
            const isActive1y = $(this).data('active-1y');
            const name = $(this).data('name') || '';
            const nameMatch = !q || name.indexOf(q) !== -1;
            let show = nameMatch;
            if (filter === 'active') show = show && (isActive == 1);
            if (filter === '1y')     show = show && (isActive1y == 1);

            if (show) {
                $(this).removeClass('hidden-card');
                visibleCount++;
            } else {
                $(this).addClass('hidden-card');
            }
        });
        $('#visibleCount').text(visibleCount);
        updatePhoneCount();
    }

    // Filter button clicks
    $('#btnFilterActive').on('click', function() {
        currentFilter = 'active';
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        applyFilterAndSearch();
    });
    $('#btnFilter1y').on('click', function() {
        currentFilter = '1y';
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

    // Search input
    $('#searchInput').on('input', function() {
        applyFilterAndSearch();
    });

    if (TOTAL === 0) return;

    // Apply default filter on load
    applyFilterAndSearch();

    // Default message template
    function getDefaultMsg(name, age) {
        return `Selamat ulang tahun yang ke-${age}, ${name}! \n\nSemoga di usia yang baru ini senantiasa diberikan kesehatan, kebahagiaan, dan panjang umur. Amin YRA.\n\nSalam hangat dari kami,\nRSU Adella`;
    }

    // Open message editor modal
    $(document).on('click', '.btn-send-wa', function() {
        const phone = $(this).data('phone');
        const name  = $(this).data('name');
        const age   = $(this).data('age');
        const rm    = $(this).data('rm');

        $('#msgRecipient').html(`<strong>${name}</strong> <span class="text-muted">(${rm})</span> <i class="fab fa-whatsapp text-success ms-1"></i> ${phone}`);
        $('#msgText').val(getDefaultMsg(name, age));
        
        // Store phone for send button
        $('#btnSendWA').data('phone', phone).data('card-btn', this);
        
        new bootstrap.Modal(document.getElementById('msgModal')).show();
    });

    // Preview button
    $(document).on('click', '.btn-preview', function() {
        const name = $(this).data('name');
        const age  = $(this).data('age');
        alert(getDefaultMsg(name, age));
    });

    // Update WA link when sending
    $('#btnSendWA').on('click', function(e) {
        const phone = $(this).data('phone');
        const msg   = encodeURIComponent($('#msgText').val());
        $(this).attr('href', `https://wa.me/${phone}?text=${msg}`);
        
        // Mark card as sent
        const cardBtn = $(this).data('card-btn');
        if (cardBtn) {
            $(cardBtn).html('<i class="fas fa-check"></i> Terkirim').prop('disabled', true)
                .css({background: '#6c757d', cursor: 'default'});
            sentCount++;
            $('#sentCount').text(sentCount);
        }
    });
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
