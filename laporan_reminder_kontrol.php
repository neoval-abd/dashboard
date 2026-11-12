<?php
/*
 * File: laporan_reminder_kontrol.php
 * Fungsi: Halaman Reminder Kontrol Pasien (BPJS dari bridging_surat_kontrol_bpjs,
 *         Tunai & Asuransi dari skdp_bpjs) dengan alur Preview -> Edit -> Masuk Antrean Service.
 */
$page_title = "Reminder Kontrol Pasien";
require_once('includes/header.php');
require_once(__DIR__ . '/api/saungwa_client.php');
require_once(__DIR__ . '/api/reminder_queue_helpers.php');

$gw_config = get_saungwa_config_summary();

ensure_kontrol_queue_table($koneksi);
recover_stale_queue_items($koneksi, 'antrean_reminder_kontrol');

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Default tanggal: jika belum dipilih user, tampilkan Hari Ini (dengan tombol cepat H-1 Besok)
$tgl_awal    = $_GET['tgl_awal'] ?? $today;
$tgl_akhir   = $_GET['tgl_akhir'] ?? $tgl_awal;
$jenis_bayar = strtolower(trim((string) ($_GET['jenis_bayar'] ?? 'all')));

if (!in_array($jenis_bayar, ['all', 'bpjs', 'tunai', 'asuransi'], true)) {
    $jenis_bayar = 'all';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_awal)) {
    $tgl_awal = $today;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_akhir)) {
    $tgl_akhir = $tgl_awal;
}
if (strtotime($tgl_awal) > strtotime($tgl_akhir)) {
    [$tgl_awal, $tgl_akhir] = [$tgl_akhir, $tgl_awal];
}

$periode_label = ($tgl_awal === $tgl_akhir)
    ? date('d F Y', strtotime($tgl_awal))
    : date('d F Y', strtotime($tgl_awal)) . ' - ' . date('d F Y', strtotime($tgl_akhir));

$items_by_ref = [];
$seen_bpjs_rm_date = [];

// ============================================================
// 1. SUMBER DATA PASIEN BPJS: bridging_surat_kontrol_bpjs + bridging_sep + fallback pasien
// ============================================================
$sql_bpjs = "SELECT 
    k.no_sep,
    k.tgl_surat,
    k.no_surat,
    k.tgl_rencana,
    k.kd_dokter_bpjs,
    k.nm_dokter_bpjs,
    k.kd_poli_bpjs,
    k.nm_poli_bpjs,
    s.nomr,
    COALESCE(NULLIF(TRIM(p.nm_pasien), ''), s.nama_pasien) AS nama_pasien,
    COALESCE(NULLIF(p.jk, ''), s.jkel) AS jkel,
    s.notelep AS sep_phone,
    p.no_tlp AS pas_phone
FROM bridging_surat_kontrol_bpjs k
INNER JOIN bridging_sep s ON k.no_sep = s.no_sep
LEFT JOIN pasien p ON s.nomr = p.no_rkm_medis
WHERE k.tgl_rencana BETWEEN ? AND ?
ORDER BY k.tgl_rencana ASC, nama_pasien ASC";

if ($stmtBpjs = $koneksi->prepare($sql_bpjs)) {
    $stmtBpjs->bind_param("ss", $tgl_awal, $tgl_akhir);
    if ($stmtBpjs->execute()) {
        $resBpjs = $stmtBpjs->get_result();
        while ($row = $resBpjs->fetch_assoc()) {
            $refKey = trim((string) ($row['no_surat'] ?: $row['no_sep']));
            if ($refKey === '') {
                continue;
            }

            $phoneInfo = sanitize_patient_phone(
                $row['sep_phone'] ?? '',
                $row['pas_phone'] ?? '',
                'SEP BPJS',
                'Master RM'
            );

            $nomr = trim((string) $row['nomr']);
            $tglRencana = trim((string) $row['tgl_rencana']);
            if ($nomr !== '' && $tglRencana !== '') {
                $seen_bpjs_rm_date[$nomr . '|' . $tglRencana] = true;
            }

            $items_by_ref[$refKey] = [
                'ref_key'       => $refKey,
                'legacy_no_sep' => trim((string) $row['no_sep']),
                'sumber_tabel'  => 'bridging_surat_kontrol_bpjs',
                'jenis_bayar'   => 'bpjs',
                'penjamin_nama' => 'BPJS Kesehatan',
                'nomr'          => $nomr,
                'nama_pasien'   => trim((string) $row['nama_pasien']),
                'jkel'          => trim((string) $row['jkel']),
                'no_surat'      => trim((string) $row['no_surat']),
                'tgl_surat'     => trim((string) $row['tgl_surat']),
                'tgl_rencana'   => $tglRencana,
                'kd_dokter_bpjs'=> trim((string) ($row['kd_dokter_bpjs'] ?? '')),
                'nm_poli'       => trim((string) ($row['nm_poli_bpjs'] ?: '-')),
                'nm_dokter'     => trim((string) ($row['nm_dokter_bpjs'] ?: '-')),
                'catatan'       => '',
                'has_phone'     => $phoneInfo['valid'],
                'phone_wa'      => $phoneInfo['phone_wa'],
                'phone_display' => $phoneInfo['phone_display'],
                'phone_source'  => $phoneInfo['source'],
                'phone_raw'     => trim((string) ($row['sep_phone'] ?: ($row['pas_phone'] ?? ''))),
            ];
        }
    }
    $stmtBpjs->close();
}

// ============================================================
// 2. SUMBER DATA PASIEN TUNAI & ASURANSI: tabel skdp_bpjs
// ============================================================
$dt_awal  = $tgl_awal . ' 00:00:00';
$dt_akhir = $tgl_akhir . ' 23:59:59';

$sql_skdp = "SELECT 
    sk.tahun,
    sk.no_antrian,
    sk.no_rkm_medis AS nomr,
    p.nm_pasien AS nama_pasien,
    p.jk AS jkel,
    p.no_tlp AS pas_phone,
    DATE(sk.tanggal_rujukan) AS tgl_surat,
    DATE(sk.tanggal_datang) AS tgl_rencana,
    sk.kd_dokter,
    d.nm_dokter,
    sk.alasan1,
    sk.rtl1,
    sk.status AS status_skdp,
    COALESCE(br.kd_pj, rp_last.kd_pj, p.kd_pj) AS kd_pj_final,
    pj.png_jawab,
    COALESCE(
        pol_br.nm_poli,
        pol_rp.nm_poli,
        (SELECT pol3.nm_poli FROM jadwal j3 INNER JOIN poliklinik pol3 ON j3.kd_poli = pol3.kd_poli WHERE j3.kd_dokter = sk.kd_dokter LIMIT 1),
        '-'
    ) AS nm_poli_final
FROM skdp_bpjs sk
INNER JOIN pasien p ON sk.no_rkm_medis = p.no_rkm_medis
LEFT JOIN dokter d ON sk.kd_dokter = d.kd_dokter
LEFT JOIN booking_registrasi br 
    ON br.no_rkm_medis = sk.no_rkm_medis 
   AND br.tanggal_periksa = DATE(sk.tanggal_datang) 
   AND br.kd_dokter = sk.kd_dokter
LEFT JOIN poliklinik pol_br ON br.kd_poli = pol_br.kd_poli
LEFT JOIN reg_periksa rp_last 
    ON rp_last.no_rawat = (
        SELECT rp2.no_rawat FROM reg_periksa rp2 
        WHERE rp2.no_rkm_medis = sk.no_rkm_medis 
          AND rp2.tgl_registrasi <= DATE(sk.tanggal_rujukan)
          AND rp2.stts != 'Batal'
        ORDER BY rp2.tgl_registrasi DESC, rp2.jam_reg DESC LIMIT 1
    )
LEFT JOIN poliklinik pol_rp ON rp_last.kd_poli = pol_rp.kd_poli
LEFT JOIN penjab pj ON pj.kd_pj = COALESCE(br.kd_pj, rp_last.kd_pj, p.kd_pj)
WHERE sk.tanggal_datang BETWEEN ? AND ?
  AND sk.status != 'Batal Periksa'
ORDER BY sk.tanggal_datang ASC, p.nm_pasien ASC";

if ($stmtSkdp = $koneksi->prepare($sql_skdp)) {
    $stmtSkdp->bind_param("ss", $dt_awal, $dt_akhir);
    if ($stmtSkdp->execute()) {
        $resSkdp = $stmtSkdp->get_result();
        while ($row = $resSkdp->fetch_assoc()) {
            $kategoriBayar = classify_jenis_bayar($row['kd_pj_final'] ?? '', $row['png_jawab'] ?? '');

            // Tabel skdp_bpjs khusus digunakan untuk pasien Tunai & Asuransi
            if ($kategoriBayar === 'bpjs') {
                continue;
            }

            $nomr       = trim((string) $row['nomr']);
            $tglRencana = trim((string) $row['tgl_rencana']);
            if ($nomr !== '' && $tglRencana !== '' && isset($seen_bpjs_rm_date[$nomr . '|' . $tglRencana])) {
                continue;
            }

            $refKey = 'SKDP-' . trim((string) $row['tahun']) . '-' . trim((string) $row['no_antrian']);
            if (isset($items_by_ref[$refKey])) {
                continue;
            }

            $phoneInfo = sanitize_patient_phone($row['pas_phone'] ?? '', '', 'Master RM', '');

            $catatanParts = [];
            $alasan1 = trim((string) ($row['alasan1'] ?? ''));
            $rtl1    = trim((string) ($row['rtl1'] ?? ''));
            if ($alasan1 !== '' && $alasan1 !== '-') {
                $catatanParts[] = $alasan1;
            }
            if ($rtl1 !== '' && $rtl1 !== '-' && strcasecmp($rtl1, $alasan1) !== 0) {
                $catatanParts[] = $rtl1;
            }

            $penjaminLabel = trim((string) ($row['png_jawab'] ?? ''));
            if ($penjaminLabel === '' || $penjaminLabel === '-') {
                $penjaminLabel = ($kategoriBayar === 'asuransi') ? 'Asuransi' : 'TUNAI';
            }

            $items_by_ref[$refKey] = [
                'ref_key'       => $refKey,
                'legacy_no_sep' => '',
                'sumber_tabel'  => 'skdp_bpjs',
                'kd_dokter'     => trim((string) ($row['kd_dokter'] ?? '')),
                'jenis_bayar'   => $kategoriBayar, // 'tunai' atau 'asuransi'
                'penjamin_nama' => $penjaminLabel,
                'nomr'          => $nomr,
                'nama_pasien'   => trim((string) $row['nama_pasien']),
                'jkel'          => trim((string) $row['jkel']),
                'no_surat'      => trim((string) $row['no_antrian']),
                'tgl_surat'     => trim((string) $row['tgl_surat']),
                'tgl_rencana'   => $tglRencana,
                'nm_poli'       => trim((string) ($row['nm_poli_final'] ?: '-')),
                'nm_dokter'     => trim((string) ($row['nm_dokter'] ?: '-')),
                'catatan'       => implode(' • ', $catatanParts),
                'has_phone'     => $phoneInfo['valid'],
                'phone_wa'      => $phoneInfo['phone_wa'],
                'phone_display' => $phoneInfo['phone_display'],
                'phone_source'  => $phoneInfo['source'],
                'phone_raw'     => trim((string) ($row['pas_phone'] ?? '')),
            ];
        }
    }
    $stmtSkdp->close();
}

// Urutkan gabungan data berdasarkan tanggal kontrol lalu nama pasien
// Pre-fetch jadwal praktik dokter untuk resolusi otomatis jam kontrol
$jadwal_map = [];
$rJadwal = $koneksi->query("SELECT kd_dokter, hari_kerja, jam_mulai, jam_selesai FROM jadwal");
if ($rJadwal) {
    while ($rowJ = $rJadwal->fetch_assoc()) {
        $key = trim($rowJ['kd_dokter']) . '|' . trim($rowJ['hari_kerja']);
        if (!isset($jadwal_map[$key])) {
            $jadwal_map[$key] = date('H:i', strtotime($rowJ['jam_mulai'])) . ' - ' . date('H:i', strtotime($rowJ['jam_selesai'])) . ' WIB';
        }
    }
}

$dokter_bpjs_map = [];
$rMapBpjs = $koneksi->query("SELECT kd_dokter, kd_dokter_bpjs FROM maping_dokter_dpjpvclaim");
if ($rMapBpjs) {
    while ($rowM = $rMapBpjs->fetch_assoc()) {
        $dokter_bpjs_map[trim($rowM['kd_dokter_bpjs'])] = trim($rowM['kd_dokter']);
    }
}

if (!function_exists('get_hari_kontrol_indonesia')) {
    function get_hari_kontrol_indonesia($dateStr) {
        $dayNum = (int) date('N', strtotime($dateStr));
        $map = [
            1 => 'SENIN',
            2 => 'SELASA',
            3 => 'RABU',
            4 => 'KAMIS',
            5 => 'JUMAT',
            6 => 'SABTU',
            7 => 'AKHAD',
        ];
        return $map[$dayNum] ?? 'SENIN';
    }
}

$kontrol_patients = array_values($items_by_ref);
usort($kontrol_patients, function ($a, $b) {
    $cmp = strcmp($a['tgl_rencana'], $b['tgl_rencana']);
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcasecmp($a['nama_pasien'], $b['nama_pasien']);
});

// ============================================================
// 3. SINKRONISASI STATUS ANTREAN DARI TABEL antrean_reminder_kontrol
// ============================================================
$queue_lookup_keys = [];
foreach ($kontrol_patients as $item) {
    $queue_lookup_keys[] = $item['ref_key'];
    if ($item['legacy_no_sep'] !== '') {
        $queue_lookup_keys[] = $item['legacy_no_sep'];
    }
}
$queue_lookup_keys = array_values(array_unique($queue_lookup_keys));

$queue_map = [];
if (!empty($queue_lookup_keys)) {
    foreach (array_chunk($queue_lookup_keys, 200) as $chunk) {
        $escaped = array_map(function ($k) use ($koneksi) {
            return "'" . $koneksi->real_escape_string($k) . "'";
        }, $chunk);
        $inClause = implode(',', $escaped);
        $qRes = $koneksi->query("SELECT no_sep, phone, message, status, attempts, scheduled_at, sent_at, last_error
                                 FROM antrean_reminder_kontrol
                                 WHERE no_sep IN ({$inClause})");
        if ($qRes) {
            while ($qr = $qRes->fetch_assoc()) {
                $queue_map[$qr['no_sep']] = $qr;
            }
        }
    }
}

$total_all      = count($kontrol_patients);
$count_bpjs     = 0;
$count_tunai    = 0;
$count_asuransi = 0;

$sent_count      = 0;
$queued_count    = 0;
$failed_count    = 0;
$unqueued_count  = 0;
$has_phone_count = 0;

foreach ($kontrol_patients as &$p) {
    $hariIndo = get_hari_kontrol_indonesia($p['tgl_rencana']);
    $kdDok = !empty($p['kd_dokter']) ? $p['kd_dokter'] : ($dokter_bpjs_map[$p['kd_dokter_bpjs'] ?? ''] ?? ($p['kd_dokter_bpjs'] ?? ''));
    $jamPraktek = $jadwal_map[$kdDok . '|' . $hariIndo] ?? ($jadwal_map[($p['kd_dokter_bpjs'] ?? '') . '|' . $hariIndo] ?? 'Sesuai Jadwal Praktik Dokter');
    $p['jam_praktek'] = $jamPraktek;
    if ($p['jenis_bayar'] === 'bpjs') {
        $count_bpjs++;
    } elseif ($p['jenis_bayar'] === 'tunai') {
        $count_tunai++;
    } else {
        $count_asuransi++;
    }

    if ($p['has_phone']) {
        $has_phone_count++;
    }

    $qRow = $queue_map[$p['ref_key']] ?? ($p['legacy_no_sep'] !== '' ? ($queue_map[$p['legacy_no_sep']] ?? null) : null);
    if ($qRow) {
        $st = $qRow['status'];
        $p['queue_status'] = ($st === 'processing') ? 'pending' : $st; // pending | sent | failed
        $p['raw_status']   = $st;
        $p['saved_msg']    = (string) ($qRow['message'] ?? '');
        $p['saved_phone']  = (string) ($qRow['phone'] ?? '');
        $p['sent_at']      = !empty($qRow['sent_at']) ? date('d/m H:i', strtotime($qRow['sent_at'])) : '';
        $p['scheduled_at'] = !empty($qRow['scheduled_at']) ? date('d/m H:i', strtotime($qRow['scheduled_at'])) : '';
        $p['last_error']   = (string) ($qRow['last_error'] ?? '');
    } else {
        $p['queue_status'] = 'none';
        $p['raw_status']   = 'none';
        $p['saved_msg']    = '';
        $p['saved_phone']  = '';
        $p['sent_at']      = '';
        $p['scheduled_at'] = '';
        $p['last_error']   = '';
    }

    if ($p['queue_status'] === 'sent') {
        $sent_count++;
    } elseif ($p['queue_status'] === 'pending') {
        $queued_count++;
    } elseif ($p['queue_status'] === 'failed') {
        $failed_count++;
    } else {
        $unqueued_count++;
    }
}
unset($p);

$unsent_total = $unqueued_count + $failed_count;
$day_after    = date('Y-m-d', strtotime('+2 days'));
?>

<style>
    .page-header-bar {
        border-bottom: 1px solid #e2e8f0;
    }

    .kontrol-card {
        background: var(--card-bg, #fff);
        border-radius: 14px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .kontrol-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }
    .kontrol-header {
        background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
        color: #fff;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .kontrol-header h6 {
        color: #fff !important;
    }
    .kontrol-header.hdr-tunai {
        background: linear-gradient(135deg, #0d9488 0%, #10b981 100%);
    }
    .kontrol-header.hdr-asuransi {
        background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    }
    .kontrol-avatar {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.22);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .kontrol-body {
        padding: 16px 18px;
        flex: 1;
    }
    .kontrol-info {
        font-size: 0.84rem;
        color: #334155;
    }
    .kontrol-info .label {
        font-weight: 600;
        min-width: 96px;
        display: inline-block;
        color: #64748b;
    }
    .kontrol-info strong {
        color: #0f172a;
    }
    .kontrol-info .sub-muted {
        color: #64748b;
    }
    .phone-src-badge {
        display: inline-block;
        font-size: 0.68rem;
        font-weight: 600;
        padding: 1px 7px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        margin-left: 4px;
        vertical-align: middle;
    }

    .kontrol-badge-pay {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.74rem;
        letter-spacing: 0.2px;
        border: 1px solid transparent;
    }
    .pay-bpjs { background: #dbeafe; color: #1d4ed8; }
    .pay-tunai { background: #d1fae5; color: #047857; }
    .pay-asuransi { background: #ede9fe; color: #6d28d9; }

    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid transparent;
    }
    .st-none { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .st-pending { background: #fef3c7; color: #b45309; }
    .st-sent { background: #dcfce7; color: #15803d; }
    .st-failed { background: #fee2e2; color: #b91c1c; }

    .kontrol-actions {
        padding: 0 18px 16px;
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
        align-items: center;
    }
    .btn-wa {
        background: #25D366;
        color: #fff;
        border: none;
        padding: 7px 14px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-wa:hover { background: #1ebe57; color: #fff; }
    .btn-wa:disabled { background: #64748b; cursor: not-allowed; }
    .btn-wa.btn-queued { background: #f59e0b; color: #fff; }
    .btn-wa.btn-queued:hover { background: #d97706; color: #fff; }
    .btn-wa.btn-retry { background: #ef4444; color: #fff; }
    .btn-wa.btn-retry:hover { background: #dc2626; color: #fff; }

    .btn-wa-manual {
        color: #0d9488;
        border-color: rgba(13, 148, 136, 0.45);
        background: rgba(13, 148, 136, 0.05);
        font-weight: 600;
        font-size: 0.8rem;
    }
    .btn-wa-manual:hover { background: #0d9488; border-color: #0d9488; color: #fff; }

    .kontrol-empty {
        text-align: center;
        padding: 55px 20px;
        background: var(--card-bg, #fff);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
    }
    .kontrol-empty i { font-size: 3.6rem; color: #cbd5e1; margin-bottom: 14px; }

    /* ============================================================
       FILTER PANEL (CENTERED + INLINE TAMPILKAN + DARK MODE READY)
       ============================================================ */
    .filter-panel {
        background: var(--card-bg, #fff);
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
        padding: 16px 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    }
    .filter-row-top {
        border-bottom: 1px solid #e2e8f0;
    }
    .filter-label {
        font-weight: 700;
        font-size: 0.83rem;
        color: #1e293b;
        white-space: nowrap;
    }
    .filter-sep {
        color: #64748b;
        font-weight: 600;
        white-space: nowrap;
    }
    .date-filter-form {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
        margin: 0;
    }
    .filter-panel .date-input-fixed {
        width: 146px !important;
        min-width: 140px;
        max-width: 146px;
        flex: 0 0 146px;
    }
    .btn-tampilkan {
        white-space: nowrap;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        padding: 5px 14px;
        border-radius: 8px;
    }
    @media (max-width: 640px) {
        .date-filter-form {
            flex-wrap: wrap;
            justify-content: flex-start;
        }
    }

    .filter-panel .form-control,
    .filter-panel .form-select {
        background-color: #ffffff;
        color: #1e293b;
        border: 1px solid #cbd5e1;
        color-scheme: light;
    }
    .filter-panel .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    /* Segmented Shortcut Buttons (Hari Ini | Besok H-1 | Lusa H-2) */
    .date-shortcut-group {
        display: inline-flex;
        align-items: center;
        border-radius: 9px;
        overflow: hidden;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        flex-shrink: 0;
    }
    .btn-shortcut {
        border: none;
        border-right: 1px solid #cbd5e1;
        background: transparent;
        color: #475569;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 5px 12px;
        cursor: pointer;
        transition: all 0.18s;
        white-space: nowrap;
    }
    .btn-shortcut:last-child {
        border-right: none;
    }
    .btn-shortcut.btn-shortcut-h1 {
        color: #2563eb;
        font-weight: 700;
    }
    .btn-shortcut:hover {
        background: rgba(37, 99, 235, 0.08);
        color: #1d4ed8;
    }
    .btn-shortcut.active {
        background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
        color: #ffffff !important;
    }

    /* Filter Pills (Penjamin & Status) + Custom Count Badges */
    .filter-btn, .pay-filter-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 13px;
        border-radius: 20px;
        border: 1.5px solid #cbd5e1;
        background: #ffffff;
        font-weight: 600;
        font-size: 0.81rem;
        cursor: pointer;
        transition: all 0.2s;
        color: #334155;
        white-space: nowrap;
    }
    .filter-btn:hover, .pay-filter-btn:hover {
        border-color: #2563eb;
        color: #2563eb;
    }
    .filter-btn.active {
        background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 3px 10px rgba(37, 99, 235, 0.25);
    }
    .pay-filter-btn.active {
        background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 3px 10px rgba(79, 70, 229, 0.25);
    }
    .pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        padding: 1px 7px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.35;
    }
    .pill-neutral { background: #e2e8f0; color: #334155; }
    .pill-blue    { background: #dbeafe; color: #1d4ed8; }
    .pill-green   { background: #dcfce7; color: #15803d; }
    .pill-purple  { background: #ede9fe; color: #6d28d9; }
    .pill-amber   { background: #fef3c7; color: #b45309; }

    .filter-btn.active .pill-count,
    .pay-filter-btn.active .pill-count {
        background: rgba(255, 255, 255, 0.24) !important;
        color: #ffffff !important;
        border-color: transparent !important;
    }

    .filter-divider-vr {
        width: 1px;
        height: 24px;
        background: #cbd5e1;
        margin: 0 4px;
    }

    .kontrol-col.hidden-card { display: none !important; }

    .stat-kontrol {
        color: #fff;
        border-radius: 14px;
        padding: 18px 20px;
        height: 100%;
    }
    .stat-kontrol h2 {
        color: #fff !important;
    }

    /* Modal Styling */
    .modal-meta-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
    }
    .modal-field-label {
        color: #1e293b;
        font-weight: 700;
    }
    .modal-sample-text {
        color: #1e293b;
        white-space: pre-line;
        font-family: system-ui, -apple-system, sans-serif;
    }
    .modal-footer-themed {
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }
    .msg-editor textarea,
    .msg-editor .modal-phone-input {
        background-color: #ffffff;
        color: #1e293b;
        border: 1px solid #cbd5e1;
    }
    .msg-editor textarea {
        width: 100%;
        min-height: 175px;
        border-radius: 10px;
        padding: 12px;
        font-size: 0.88rem;
        line-height: 1.5;
        resize: vertical;
    }
    .msg-editor textarea:focus,
    .msg-editor .modal-phone-input:focus {
        outline: none;
        border-color: #25D366;
        box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.15);
    }

    .error-note {
        font-size: 0.76rem;
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 6px 10px;
        margin-top: 8px;
    }

    /* ============================================================
       DARK / GLASS THEME OVERRIDES (theme-glass-solid & animated)
       ============================================================ */
    html.theme-glass-solid .page-header-bar,
    html.theme-glass-animated .page-header-bar,
    html.theme-glass-solid .filter-row-top,
    html.theme-glass-animated .filter-row-top {
        border-bottom-color: rgba(148, 163, 184, 0.18);
    }

    html.theme-glass-solid .filter-label,
    html.theme-glass-animated .filter-label {
        color: #f1f5f9;
    }
    html.theme-glass-solid .filter-sep,
    html.theme-glass-animated .filter-sep {
        color: #94a3b8;
    }
    html.theme-glass-solid .filter-divider-vr,
    html.theme-glass-animated .filter-divider-vr {
        background: rgba(148, 163, 184, 0.25);
    }

    html.theme-glass-solid .filter-panel .form-control,
    html.theme-glass-animated .filter-panel .form-control,
    html.theme-glass-solid .filter-panel .form-select,
    html.theme-glass-animated .filter-panel .form-select {
        background-color: var(--input-bg, rgba(15, 23, 42, 0.88)) !important;
        color: var(--input-text, #f8fafc) !important;
        border: 1px solid var(--input-border, rgba(148, 163, 184, 0.35)) !important;
        color-scheme: dark;
    }
    html.theme-glass-solid .filter-panel .form-control::placeholder,
    html.theme-glass-animated .filter-panel .form-control::placeholder {
        color: #94a3b8 !important;
    }
    html.theme-glass-solid .filter-panel .form-control:focus,
    html.theme-glass-animated .filter-panel .form-control:focus {
        border-color: #38bdf8 !important;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2) !important;
    }

    html.theme-glass-solid .date-shortcut-group,
    html.theme-glass-animated .date-shortcut-group {
        background: rgba(15, 23, 42, 0.65);
        border-color: rgba(148, 163, 184, 0.3);
    }
    html.theme-glass-solid .btn-shortcut,
    html.theme-glass-animated .btn-shortcut {
        color: #cbd5e1;
        border-right-color: rgba(148, 163, 184, 0.25);
    }
    html.theme-glass-solid .btn-shortcut.btn-shortcut-h1,
    html.theme-glass-animated .btn-shortcut.btn-shortcut-h1 {
        color: #38bdf8;
    }
    html.theme-glass-solid .btn-shortcut:hover,
    html.theme-glass-animated .btn-shortcut:hover {
        background: rgba(56, 189, 248, 0.14);
        color: #f8fafc;
    }
    html.theme-glass-solid .btn-shortcut.active,
    html.theme-glass-animated .btn-shortcut.active {
        background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
        color: #ffffff !important;
    }

    html.theme-glass-solid .filter-btn,
    html.theme-glass-animated .filter-btn,
    html.theme-glass-solid .pay-filter-btn,
    html.theme-glass-animated .pay-filter-btn {
        background: rgba(15, 23, 42, 0.65);
        border-color: rgba(148, 163, 184, 0.28);
        color: #e2e8f0;
    }
    html.theme-glass-solid .filter-btn:hover,
    html.theme-glass-animated .filter-btn:hover,
    html.theme-glass-solid .pay-filter-btn:hover,
    html.theme-glass-animated .pay-filter-btn:hover {
        border-color: #38bdf8;
        color: #38bdf8;
        background: rgba(30, 41, 59, 0.85);
    }
    html.theme-glass-solid .filter-btn.active,
    html.theme-glass-animated .filter-btn.active {
        background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 3px 12px rgba(6, 182, 212, 0.32);
    }
    html.theme-glass-solid .pay-filter-btn.active,
    html.theme-glass-animated .pay-filter-btn.active {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 3px 12px rgba(139, 92, 246, 0.35);
    }

    html.theme-glass-solid .pill-neutral,
    html.theme-glass-animated .pill-neutral { background: rgba(148, 163, 184, 0.22); color: #e2e8f0; }
    html.theme-glass-solid .pill-blue,
    html.theme-glass-animated .pill-blue    { background: rgba(56, 189, 248, 0.2);  color: #7dd3fc; }
    html.theme-glass-solid .pill-green,
    html.theme-glass-animated .pill-green   { background: rgba(16, 185, 129, 0.22); color: #6ee7b7; }
    html.theme-glass-solid .pill-purple,
    html.theme-glass-animated .pill-purple  { background: rgba(168, 85, 247, 0.22); color: #d8b4fe; }
    html.theme-glass-solid .pill-amber,
    html.theme-glass-animated .pill-amber   { background: rgba(245, 158, 11, 0.22); color: #fcd34d; }

    /* Patient Cards Dark Mode */
    html.theme-glass-solid .kontrol-card,
    html.theme-glass-animated .kontrol-card {
        background: var(--card-bg, rgba(30, 41, 59, 0.78));
        border-color: var(--card-border, rgba(255, 255, 255, 0.12));
        backdrop-filter: var(--glass-blur, blur(12px));
        -webkit-backdrop-filter: var(--glass-blur, blur(12px));
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.28);
    }
    html.theme-glass-solid .kontrol-card:hover,
    html.theme-glass-animated .kontrol-card:hover {
        border-color: rgba(56, 189, 248, 0.4);
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.42);
    }
    html.theme-glass-solid .kontrol-info,
    html.theme-glass-animated .kontrol-info {
        color: #e2e8f0;
    }
    html.theme-glass-solid .kontrol-info .label,
    html.theme-glass-animated .kontrol-info .label {
        color: #94a3b8;
    }
    html.theme-glass-solid .kontrol-info strong,
    html.theme-glass-animated .kontrol-info strong {
        color: #f8fafc;
    }
    html.theme-glass-solid .kontrol-info .sub-muted,
    html.theme-glass-animated .kontrol-info .sub-muted {
        color: #94a3b8;
    }
    html.theme-glass-solid .phone-src-badge,
    html.theme-glass-animated .phone-src-badge {
        background: rgba(56, 189, 248, 0.14);
        color: #7dd3fc;
        border-color: rgba(56, 189, 248, 0.35);
    }

    html.theme-glass-solid .pay-bpjs,
    html.theme-glass-animated .pay-bpjs {
        background: rgba(59, 130, 246, 0.18);
        color: #93c5fd;
        border-color: rgba(96, 165, 250, 0.35);
    }
    html.theme-glass-solid .pay-tunai,
    html.theme-glass-animated .pay-tunai {
        background: rgba(16, 185, 129, 0.18);
        color: #6ee7b7;
        border-color: rgba(52, 211, 153, 0.35);
    }
    html.theme-glass-solid .pay-asuransi,
    html.theme-glass-animated .pay-asuransi {
        background: rgba(168, 85, 247, 0.2);
        color: #d8b4fe;
        border-color: rgba(192, 132, 252, 0.38);
    }

    html.theme-glass-solid .st-none,
    html.theme-glass-animated .st-none {
        background: rgba(148, 163, 184, 0.16);
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.28);
    }
    html.theme-glass-solid .st-pending,
    html.theme-glass-animated .st-pending {
        background: rgba(245, 158, 11, 0.18);
        color: #fcd34d;
        border-color: rgba(251, 191, 36, 0.38);
    }
    html.theme-glass-solid .st-sent,
    html.theme-glass-animated .st-sent {
        background: rgba(34, 197, 94, 0.18);
        color: #86efac;
        border-color: rgba(74, 222, 128, 0.38);
    }
    html.theme-glass-solid .st-failed,
    html.theme-glass-animated .st-failed {
        background: rgba(239, 68, 68, 0.18);
        color: #fca5a5;
        border-color: rgba(248, 113, 113, 0.38);
    }

    html.theme-glass-solid .btn-wa-manual,
    html.theme-glass-animated .btn-wa-manual {
        color: #34d399;
        border-color: rgba(52, 211, 153, 0.45);
        background: rgba(16, 185, 129, 0.12);
    }
    html.theme-glass-solid .btn-wa-manual:hover,
    html.theme-glass-animated .btn-wa-manual:hover {
        background: #10b981;
        border-color: #10b981;
        color: #ffffff;
    }

    html.theme-glass-solid .error-note,
    html.theme-glass-animated .error-note {
        background: rgba(239, 68, 68, 0.14);
        color: #fca5a5;
        border-color: rgba(248, 113, 113, 0.35);
    }

    html.theme-glass-solid .kontrol-empty,
    html.theme-glass-animated .kontrol-empty {
        background: var(--card-bg, rgba(30, 41, 59, 0.75)) !important;
        border-color: var(--card-border, rgba(255, 255, 255, 0.12)) !important;
    }
    html.theme-glass-solid .kontrol-empty i,
    html.theme-glass-animated .kontrol-empty i {
        color: #475569;
    }

    /* Modals Dark Mode */
    html.theme-glass-solid .modal-meta-box,
    html.theme-glass-animated .modal-meta-box {
        background: rgba(15, 23, 42, 0.55);
        border-color: rgba(148, 163, 184, 0.22);
    }
    html.theme-glass-solid .modal-field-label,
    html.theme-glass-animated .modal-field-label,
    html.theme-glass-solid .modal-sample-text,
    html.theme-glass-animated .modal-sample-text {
        color: #f1f5f9;
    }
    html.theme-glass-solid .modal-footer-themed,
    html.theme-glass-animated .modal-footer-themed {
        background: rgba(15, 23, 42, 0.45) !important;
        border-top: 1px solid rgba(148, 163, 184, 0.18) !important;
    }
    html.theme-glass-solid .msg-editor textarea,
    html.theme-glass-animated .msg-editor textarea,
    html.theme-glass-solid .msg-editor .modal-phone-input,
    html.theme-glass-animated .msg-editor .modal-phone-input,
    html.theme-glass-solid .msg-editor .modal-themed-input,
    html.theme-glass-animated .msg-editor .modal-themed-input {
        background-color: var(--input-bg, rgba(15, 23, 42, 0.9)) !important;
        color: var(--input-text, #f8fafc) !important;
        border-color: var(--input-border, rgba(148, 163, 184, 0.35)) !important;
        color-scheme: dark;
    }

    .msg-editor .modal-themed-input {
        background-color: #ffffff;
        color: #1e293b;
        border: 1px solid #cbd5e1;
    }

    .btn-gw-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 13px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 700;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-gw-ready {
        background: #dcfce7;
        color: #15803d;
        border-color: #86efac;
    }
    .btn-gw-ready:hover { background: #bbf7d0; color: #166534; }
    .btn-gw-warn {
        background: #fef3c7;
        color: #b45309;
        border-color: #fcd34d;
    }
    .btn-gw-warn:hover { background: #fde68a; color: #92400e; }

    html.theme-glass-solid .btn-gw-ready,
    html.theme-glass-animated .btn-gw-ready {
        background: rgba(16, 185, 129, 0.18);
        color: #6ee7b7;
        border-color: rgba(52, 211, 153, 0.45);
    }
    html.theme-glass-solid .btn-gw-warn,
    html.theme-glass-animated .btn-gw-warn {
        background: rgba(245, 158, 11, 0.2);
        color: #fcd34d;
        border-color: rgba(251, 191, 36, 0.45);
    }

    .gw-setup-banner {
        background: #fffbeb;
        border: 1px solid #fcd34d;
        color: #92400e;
        border-radius: 12px;
        padding: 11px 16px;
        font-size: 0.84rem;
    }
    html.theme-glass-solid .gw-setup-banner,
    html.theme-glass-animated .gw-setup-banner {
        background: rgba(245, 158, 11, 0.14);
        border-color: rgba(251, 191, 36, 0.38);
        color: #fde68a;
    }
</style>

<!-- Header -->
<div class="page-header-bar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
    <div>
        <h1 class="h3 mb-1 fw-bold"><i class="fas fa-bell text-warning me-2"></i>Reminder Kontrol Pasien</h1>
        <p class="text-muted small mb-0">
            Preview, Edit lalu kirim pesan reminder kontrol akan masuk ke <strong>Antrean Service (~5 menit/pesan)</strong> — mencakup pasien BPJS, Tunai, dan Asuransi.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap mt-2 mt-md-0">
        <button type="button"
                id="btnOpenSaungwaConfig"
                class="btn-gw-status <?php echo $gw_config['is_ready'] ? 'btn-gw-ready' : 'btn-gw-warn'; ?>"
                title="Klik untuk pengaturan kredensial & tes kirim SaungWA">
            <?php if ($gw_config['provider'] === 'fonnte'): ?>
                <i class="fas fa-plug"></i>
                <span id="gwStatusLabel">Gateway: Fonnte<?php echo $gw_config['is_ready'] ? ' (Aktif)' : ' (Token Kosong)'; ?></span>
            <?php elseif ($gw_config['is_ready']): ?>
                <i class="fas fa-check-circle"></i>
                <span id="gwStatusLabel">SaungWA Aktif (<?php echo htmlspecialchars($gw_config['sender_display'] ?: 'Connected'); ?>)</span>
            <?php else: ?>
                <i class="fas fa-exclamation-triangle"></i>
                <span id="gwStatusLabel">SaungWA: Lengkapi AppKey</span>
            <?php endif; ?>
        </button>

        <span class="badge bg-primary fs-6 px-3 py-2">
            <i class="fas fa-calendar-day me-1"></i> <?php echo htmlspecialchars($periode_label); ?>
        </span>
    </div>
</div>

<?php if ($gw_config['provider'] === 'saungwa' && !$gw_config['has_app_key']): ?>
<div id="saungwaAppKeyNotice" class="gw-setup-banner d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <i class="fas fa-key me-1"></i>
        <strong>AuthKey &amp; Nomor WA (<?php echo htmlspecialchars($gw_config['sender_display'] ?: '089675463447'); ?>) sudah tersimpan!</strong>
        Tinggal 1 langkah lagi: ambil <code>AppKey</code> dari menu <strong>Webhook App</strong> di dashboard SaungWA lalu isi pada pengaturan untuk mengaktifkan pengiriman otomatis.
    </div>
    <button type="button" class="btn btn-sm btn-warning fw-bold text-dark" onclick="document.getElementById('btnOpenSaungwaConfig').click()">
        <i class="fas fa-cog me-1"></i> Isi AppKey &amp; Tes Kirim
    </button>
</div>
<?php else: ?>
<div id="saungwaAppKeyNotice" class="gw-setup-banner d-none mb-3"></div>
<?php endif; ?>

<!-- Stats KPI -->
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-kontrol" style="background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-calendar-check fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="totalVisibleCount"><?php echo $total_all; ?></h2>
                    <small class="opacity-90 d-block">Total Jadwal Kontrol</small>
                    <small class="opacity-75" style="font-size:0.73rem;">
                        BPJS: <?php echo $count_bpjs; ?> &middot; Tunai: <?php echo $count_tunai; ?> &middot; Asuransi: <?php echo $count_asuransi; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-kontrol" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-clock fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="queuedCount"><?php echo $queued_count; ?></h2>
                    <small class="opacity-90 d-block">Dalam Antrean Service</small>
                    <small class="opacity-75" style="font-size:0.73rem;">Menunggu giliran kirim (~10-20 mnt/pesan)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-kontrol" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-check-double fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="sentCount"><?php echo $sent_count; ?></h2>
                    <small class="opacity-90 d-block">Sudah Terkirim</small>
                    <small class="opacity-75" style="font-size:0.73rem;">Tersimpan di log antrean</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-kontrol" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
            <div class="d-flex align-items-center gap-3">
                <i class="fas fa-hourglass-half fa-2x opacity-75"></i>
                <div>
                    <h2 class="mb-0 fw-bold" id="unsentCount"><?php echo $unsent_total; ?></h2>
                    <small class="opacity-90 d-block">Belum Diantrean / Gagal</small>
                    <small class="opacity-75" style="font-size:0.73rem;">
                        HP Valid: <?php echo $has_phone_count; ?>/<?php echo $total_all; ?>
                        <?php if ($failed_count > 0): ?>
                            &middot; Gagal: <span id="failedSubCount"><?php echo $failed_count; ?></span>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Panel -->
<div class="filter-panel mb-4">
    <!-- Baris 1: Tanggal + Tombol Tampilkan Sejajar (Rata Kiri) + Shortcut H-1 + Bulk Action -->
    <div class="filter-row-top d-flex justify-content-start align-items-center gap-3 flex-wrap pb-3 mb-3">
        <form method="get" id="dateFilterForm" class="date-filter-form">
            <input type="hidden" name="jenis_bayar" id="hiddenJenisBayar" value="<?php echo htmlspecialchars($jenis_bayar); ?>">
            <span class="filter-label"><i class="fas fa-calendar-alt text-primary me-1"></i> Jadwal Kontrol:</span>
            <input type="date" name="tgl_awal" id="inputTglAwal" class="form-control form-control-sm date-input-fixed" value="<?php echo htmlspecialchars($tgl_awal); ?>">
            <span class="filter-sep small">s/d</span>
            <input type="date" name="tgl_akhir" id="inputTglAkhir" class="form-control form-control-sm date-input-fixed" value="<?php echo htmlspecialchars($tgl_akhir); ?>">
            <button type="submit" class="btn btn-sm btn-primary fw-bold btn-tampilkan">
                <i class="fas fa-search me-1"></i> Tampilkan
            </button>
        </form>

        <div class="date-shortcut-group" role="group" aria-label="Shortcut Tanggal">
            <button type="button" class="btn-shortcut <?php echo ($tgl_awal === $today && $tgl_akhir === $today) ? 'active' : ''; ?>" onclick="quickDate('<?php echo $today; ?>', '<?php echo $today; ?>')">
                Hari Ini
            </button>
            <button type="button" class="btn-shortcut btn-shortcut-h1 <?php echo ($tgl_awal === $tomorrow && $tgl_akhir === $tomorrow) ? 'active' : ''; ?>" onclick="quickDate('<?php echo $tomorrow; ?>', '<?php echo $tomorrow; ?>')">
                <i class="fas fa-bell me-1"></i>Besok (H-1)
            </button>
            <button type="button" class="btn-shortcut <?php echo ($tgl_awal === $day_after && $tgl_akhir === $day_after) ? 'active' : ''; ?>" onclick="quickDate('<?php echo $day_after; ?>', '<?php echo $day_after; ?>')">
                Lusa (H-2)
            </button>
        </div>

        <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm flex-shrink-0 ms-xl-auto" id="btnOpenBulkModal">
            <i class="fas fa-layer-group me-1"></i> Preview &amp; Antrekan Massal
        </button>
    </div>

    <!-- Baris 2: Filter Penjamin (BPJS / Tunai / Asuransi) + Filter Status Antrean + Cari -->
    <div class="d-flex justify-content-start align-items-center gap-2 flex-wrap">
        <span class="filter-label me-1"><i class="fas fa-id-card text-success me-1"></i> Penjamin:</span>
        <button type="button" class="pay-filter-btn <?php echo $jenis_bayar === 'all' ? 'active' : ''; ?>" data-pay="all">
            Semua <span class="pill-count pill-neutral"><?php echo $total_all; ?></span>
        </button>
        <button type="button" class="pay-filter-btn <?php echo $jenis_bayar === 'bpjs' ? 'active' : ''; ?>" data-pay="bpjs">
            BPJS <span class="pill-count pill-blue"><?php echo $count_bpjs; ?></span>
        </button>
        <button type="button" class="pay-filter-btn <?php echo $jenis_bayar === 'tunai' ? 'active' : ''; ?>" data-pay="tunai">
            Tunai <span class="pill-count pill-green"><?php echo $count_tunai; ?></span>
        </button>
        <button type="button" class="pay-filter-btn <?php echo $jenis_bayar === 'asuransi' ? 'active' : ''; ?>" data-pay="asuransi">
            Asuransi <span class="pill-count pill-purple"><?php echo $count_asuransi; ?></span>
        </button>

        <div class="filter-divider-vr d-none d-lg-block"></div>

        <span class="filter-label me-1"><i class="fas fa-tasks text-warning me-1"></i> Status:</span>
        <button type="button" class="filter-btn active" data-filter="unsent" id="btnFilterUnsent">
            Belum Diantrean <span class="pill-count pill-neutral" id="badgeUnsent"><?php echo $unsent_total; ?></span>
        </button>
        <button type="button" class="filter-btn" data-filter="pending" id="btnFilterQueued">
            Dalam Antrean <span class="pill-count pill-amber" id="badgeQueued"><?php echo $queued_count; ?></span>
        </button>
        <button type="button" class="filter-btn" data-filter="sent" id="btnFilterSent">
            Terkirim <span class="pill-count pill-green" id="badgeSent"><?php echo $sent_count; ?></span>
        </button>
        <button type="button" class="filter-btn" data-filter="all" id="btnFilterAll">
            Semua
        </button>

        <div class="d-flex align-items-center ms-xl-auto mt-2 mt-xl-0">
            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Cari nama / RM / poli / dokter..." style="min-width: 225px; border-radius: 20px;">
        </div>
    </div>
</div>

<!-- Grid Kartu Pasien -->
<?php if ($total_all === 0): ?>
    <div class="kontrol-empty card border-0 shadow-sm" style="border-radius:14px;">
        <div class="card-body py-5">
            <i class="fas fa-calendar-check d-block"></i>
            <h5 class="text-muted fw-bold">Tidak ada pasien yang dijadwalkan kontrol pada periode ini</h5>
            <p class="text-muted small mb-3">Coba klik tombol <strong>Besok (H-1)</strong> atau pilih rentang tanggal lainnya.</p>
            <button type="button" class="btn btn-primary btn-sm" onclick="quickDate('<?php echo $tomorrow; ?>', '<?php echo $tomorrow; ?>')">
                <i class="fas fa-bell me-1"></i> Cek Jadwal Besok (H-1)
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3" id="kontrolGrid">
        <?php foreach ($kontrol_patients as $i => $p): ?>
            <?php
                $gender_icon  = ($p['jkel'] === 'L') ? 'fa-mars' : 'fa-venus';
                $gender_label = ($p['jkel'] === 'L') ? 'Laki-laki' : 'Perempuan';
                $nomr         = htmlspecialchars($p['nomr']);
                $nm           = htmlspecialchars($p['nama_pasien']);
                $poli         = htmlspecialchars($p['nm_poli'] ?: '-');
                $dokter       = htmlspecialchars($p['nm_dokter'] ?: '-');
                $no_surat     = htmlspecialchars($p['no_surat'] ?: '-');
                $catatan      = htmlspecialchars($p['catatan'] ?? '');
                $penjaminNama = htmlspecialchars($p['penjamin_nama']);
                $tgl_surat    = !empty($p['tgl_surat']) ? date('d-m-Y', strtotime($p['tgl_surat'])) : '-';
                $tgl_rencana  = !empty($p['tgl_rencana']) ? date('d-m-Y', strtotime($p['tgl_rencana'])) : '-';

                $hdrClass = '';
                $payClass = 'pay-bpjs';
                $suratLabel = 'No. SKDP';
                if ($p['jenis_bayar'] === 'tunai') {
                    $hdrClass = 'hdr-tunai';
                    $payClass = 'pay-tunai';
                    $suratLabel = 'No. Surat';
                } elseif ($p['jenis_bayar'] === 'asuransi') {
                    $hdrClass = 'hdr-asuransi';
                    $payClass = 'pay-asuransi';
                    $suratLabel = 'No. Surat';
                }

                $qStatus = $p['queue_status']; // none | pending | sent | failed
            ?>
            <div class="col-lg-4 col-md-6 kontrol-col"
                 data-ref-key="<?php echo htmlspecialchars($p['ref_key']); ?>"
                 data-pay="<?php echo htmlspecialchars($p['jenis_bayar']); ?>"
                 data-status="<?php echo htmlspecialchars($qStatus); ?>"
                 data-has-phone="<?php echo $p['has_phone'] ? '1' : '0'; ?>"
                 data-search="<?php echo htmlspecialchars(strtolower($p['nama_pasien'] . ' ' . $p['nomr'] . ' ' . $p['nm_poli'] . ' ' . $p['nm_dokter'] . ' ' . $p['penjamin_nama'])); ?>">
                <div class="kontrol-card" data-idx="<?php echo $i; ?>">
                    <div class="kontrol-header <?php echo $hdrClass; ?>">
                        <div class="kontrol-avatar">
                            <i class="fas <?php echo $gender_icon; ?>" style="color:#fff"></i>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <h6 class="mb-0 fw-bold text-truncate" title="<?php echo $nm; ?>"><?php echo $nm; ?></h6>
                            <small class="opacity-85"><?php echo $nomr; ?> &middot; <?php echo $gender_label; ?></small>
                        </div>
                    </div>

                    <div class="kontrol-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-2">
                            <span class="kontrol-badge-pay <?php echo $payClass; ?>">
                                <i class="fas fa-shield-alt"></i> <?php echo $penjaminNama; ?>
                            </span>
                            <span class="status-pill badge-status-card <?php echo 'st-' . $qStatus; ?>">
                                <?php if ($qStatus === 'sent'): ?>
                                    <i class="fas fa-check-circle"></i> Terkirim <?php echo htmlspecialchars($p['sent_at']); ?>
                                <?php elseif ($qStatus === 'pending'): ?>
                                    <i class="fas fa-clock"></i> Dalam Antrean
                                <?php elseif ($qStatus === 'failed'): ?>
                                    <i class="fas fa-exclamation-circle"></i> Gagal Kirim
                                <?php else: ?>
                                    <i class="fas fa-hourglass-start"></i> Belum Diantrean
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="kontrol-info mt-2">
                            <div><span class="label"><i class="fas fa-calendar-day me-1 text-primary"></i> Kontrol</span>: <strong class="text-primary"><?php echo $tgl_rencana; ?></strong> <?php if (!empty($p['jam_praktek']) && $p['jam_praktek'] !== 'Sesuai Jadwal Praktik Dokter'): ?><span class="badge bg-secondary ms-1" style="font-size:0.73rem;"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($p['jam_praktek']); ?></span><?php endif; ?></div>
                            <div class="mt-1"><span class="label"><i class="fas fa-stethoscope me-1"></i> Poli</span>: <strong><?php echo $poli; ?></strong></div>
                            <div class="mt-1"><span class="label"><i class="fas fa-user-md me-1"></i> Dokter</span>: <strong><?php echo $dokter; ?></strong></div>
                            <div class="mt-1"><span class="label"><i class="fas fa-file-alt me-1"></i> <?php echo $suratLabel; ?></span>: <strong><?php echo $no_surat; ?></strong> <span class="sub-muted">(<?php echo $tgl_surat; ?>)</span></div>
                            <?php if ($catatan !== ''): ?>
                                <div class="mt-1"><span class="label"><i class="fas fa-clipboard-list me-1"></i> Catatan</span>: <span class="sub-muted"><?php echo $catatan; ?></span></div>
                            <?php endif; ?>
                            <div class="mt-1"><span class="label"><i class="fas fa-phone me-1"></i> No. WA</span>:
                                <?php if ($p['has_phone']): ?>
                                    <strong class="card-phone-label"><?php echo htmlspecialchars($p['phone_display']); ?></strong>
                                    <?php if ($p['phone_source'] !== ''): ?>
                                        <span class="phone-src-badge"><?php echo htmlspecialchars($p['phone_source']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-danger fst-italic">Nomor HP tidak valid / kosong</span>
                                    <?php if ($p['phone_raw'] !== '' && $p['phone_raw'] !== '-'): ?>
                                        <small class="sub-muted">(<?php echo htmlspecialchars($p['phone_raw']); ?>)</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($qStatus === 'failed' && $p['last_error'] !== ''): ?>
                            <div class="error-note card-error-box">
                                <i class="fas fa-info-circle me-1"></i> <?php echo htmlspecialchars($p['last_error']); ?>
                            </div>
                        <?php else: ?>
                            <div class="error-note card-error-box d-none"></div>
                        <?php endif; ?>
                    </div>

                    <div class="kontrol-actions">
                        <?php if ($p['has_phone']): ?>
                            <button type="button"
                                class="btn-wa btn-open-preview <?php echo $qStatus === 'pending' ? 'btn-queued' : ($qStatus === 'failed' ? 'btn-retry' : ''); ?>"
                                data-ref-key="<?php echo htmlspecialchars($p['ref_key']); ?>"
                                data-phone="<?php echo htmlspecialchars($p['saved_phone'] ?: $p['phone_wa']); ?>"
                                data-name="<?php echo $nm; ?>"
                                data-nomr="<?php echo $nomr; ?>"
                                data-penjamin="<?php echo $penjaminNama; ?>"
                                data-poli="<?php echo $poli; ?>"
                                data-dokter="<?php echo $dokter; ?>"
                                data-tanggal="<?php echo $tgl_rencana; ?>"
                                data-jam="<?php echo htmlspecialchars($p['jam_praktek'] ?: 'Sesuai Jadwal Praktik Dokter'); ?>"
                                data-no-surat="<?php echo $no_surat; ?>"
                                data-catatan="<?php echo $catatan; ?>"
                                data-saved-msg="<?php echo htmlspecialchars($p['saved_msg']); ?>"
                                data-status="<?php echo htmlspecialchars($qStatus); ?>"
                                <?php if ($qStatus === 'sent') echo 'disabled style="background:#64748b;cursor:default;"'; ?>>
                                <?php if ($qStatus === 'sent'): ?>
                                    <i class="fas fa-check"></i> Sudah Terkirim
                                <?php elseif ($qStatus === 'pending'): ?>
                                    <i class="fas fa-edit"></i> Edit Antrean
                                <?php elseif ($qStatus === 'failed'): ?>
                                    <i class="fas fa-redo"></i> Preview & Ulangi
                                <?php else: ?>
                                    <i class="fab fa-whatsapp"></i> Preview & Antrekan
                                <?php endif; ?>
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-success btn-wa-manual"
                                data-phone="<?php echo htmlspecialchars($p['phone_wa']); ?>"
                                data-name="<?php echo $nm; ?>"
                                data-poli="<?php echo $poli; ?>"
                                data-dokter="<?php echo $dokter; ?>"
                                data-tanggal="<?php echo $tgl_rencana; ?>"
                                title="Buka langsung di WhatsApp Web / Aplikasi WA">
                                <i class="fab fa-whatsapp"></i> Manual
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-wa btn-open-preview"
                                data-ref-key="<?php echo htmlspecialchars($p['ref_key']); ?>"
                                data-phone=""
                                data-name="<?php echo $nm; ?>"
                                data-nomr="<?php echo $nomr; ?>"
                                data-penjamin="<?php echo $penjaminNama; ?>"
                                data-poli="<?php echo $poli; ?>"
                                data-dokter="<?php echo $dokter; ?>"
                                data-tanggal="<?php echo $tgl_rencana; ?>"
                                data-no-surat="<?php echo $no_surat; ?>"
                                data-catatan="<?php echo $catatan; ?>"
                                data-saved-msg=""
                                data-status="none"
                                style="background:#64748b;"
                                title="Isi nomor WA manual lalu masukkan antrean">
                                <i class="fas fa-pen"></i> Isi No. WA & Preview
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="emptyFilteredNotice" class="kontrol-empty card border-0 shadow-sm mt-3 d-none" style="border-radius:14px;">
        <div class="card-body py-4">
            <i class="fas fa-filter d-block" style="font-size:2.5rem;"></i>
            <h6 class="text-muted fw-bold mb-1">Tidak ada kartu yang cocok dengan filter ini</h6>
            <p class="text-muted small mb-0">Ubah filter Penjamin / Status di atas untuk melihat pasien lainnya.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Modal 1: Preview & Edit Pesan per Pasien sebelum Masuk Antrean Service -->
<div class="modal fade" id="msgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #25D366, #128C7E);">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fab fa-whatsapp me-2"></i> Preview &amp; Edit Pesan Reminder Kontrol
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body msg-editor p-4">
                <div class="modal-meta-box mb-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="modal-field-label fs-6" id="msgRecipientName">-</div>
                            <div class="text-muted small" id="msgRecipientMeta">-</div>
                        </div>
                        <span class="badge bg-primary" id="msgRecipientPay">-</span>
                    </div>
                    <div class="row g-2 mt-2 align-items-center">
                        <div class="col-sm-5">
                            <label class="form-label small modal-field-label mb-1">Nomor WhatsApp Tujuan:</label>
                            <input type="text" id="msgPhoneInput" class="form-control form-control-sm modal-phone-input" placeholder="Contoh: 081234567890">
                        </div>
                        <div class="col-sm-7">
                            <div class="small text-muted mt-3 mt-sm-4">
                                <i class="fas fa-shield-alt text-success me-1"></i> Pesan akan masuk ke <strong>Antrean Service</strong> dan dikirim otomatis tiap ~5 menit agar aman dari blokir.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label modal-field-label mb-0">Isi Pesan Reminder (Bisa Diedit):</label>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnResetDefaultMsg">
                        <i class="fas fa-undo me-1"></i> Kembalikan ke Template Awal
                    </button>
                </div>
                <textarea id="msgText" class="form-control"></textarea>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">Tips: Gunakan tanda <code>*teks*</code> untuk huruf tebal di WhatsApp.</small>
                    <small class="text-muted"><span id="msgCharCount">0</span> karakter</small>
                </div>
            </div>
            <div class="modal-footer modal-footer-themed d-flex justify-content-between">
                <div>
                    <button type="button" id="btnCancelQueueItem" class="btn btn-outline-danger btn-sm d-none">
                        <i class="fas fa-trash-alt me-1"></i> Batalkan dari Antrean
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button id="btnEnqueueWA" type="button" class="btn-wa" style="padding:9px 22px;">
                        <i class="fas fa-clock me-1"></i> Masukkan ke Antrean Service
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Preview & Edit Template Antrean Massal -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #2563eb, #06b6d4);">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fas fa-layer-group me-2"></i> Preview &amp; Antrekan Massal ke Service WA
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body msg-editor p-4">
                <div class="alert alert-info py-2 px-3 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Akan mengantrekan <strong id="bulkEligibleCount">0</strong> pasien (sesuai filter Penjamin aktif yang <em>belum terkirim</em> dan <em>memiliki nomor WA valid</em>). Estimasi selesai kirim oleh service 5 menit: <strong id="bulkEstDuration">-</strong>.
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label modal-field-label mb-0">Template Pesan (Bisa Diedit Sebelum Diantrean):</label>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnResetBulkTemplate">
                            <i class="fas fa-undo me-1"></i> Reset Template
                        </button>
                    </div>
                    <textarea id="bulkTemplateText" class="form-control" style="min-height:150px;"></textarea>
                    <small class="text-muted d-block mt-1">
                        Variabel otomatis: <code>{nama}</code>, <code>{tanggal}</code>, <code>{jam}</code>, <code>{poli}</code>, <code>{dokter}</code>, <code>{penjamin}</code>, <code>{no_surat}</code> (atau format <code>[Nama Pasien]</code>, <code>[Tanggal]</code>, <code>[Jam]</code>, dll)
                    </small>
                </div>

                <div class="modal-meta-box">
                    <div class="small fw-bold text-muted mb-1">
                        <i class="fas fa-eye me-1"></i> Contoh Hasil Preview (Pasien Pertama):
                    </div>
                    <div id="bulkSamplePreview" class="small modal-sample-text">-</div>
                </div>
            </div>
            <div class="modal-footer modal-footer-themed">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary fw-bold px-4" id="btnConfirmBulkEnqueue">
                    <i class="fas fa-paper-plane me-1"></i> Masukkan Semua ke Antrean Service
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Konfigurasi & Uji Koneksi SaungWA -->
<div class="modal fade" id="saungwaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:none;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #059669, #0d9488);">
                <h5 class="modal-title fw-bold text-white">
                    <i class="fab fa-whatsapp me-2"></i> Integrasi &amp; Pengaturan Gateway WhatsApp (SaungWA &amp; Fonnte)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body msg-editor p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small modal-field-label mb-1">Pilih Gateway WhatsApp Aktif:</label>
                        <select id="cfgProvider" class="form-select form-select-sm modal-themed-input">
                            <option value="saungwa" <?php echo $gw_config['provider'] === 'saungwa' ? 'selected' : ''; ?>>SaungWA (Utama / Scan QR)</option>
                            <option value="fonnte" <?php echo $gw_config['provider'] === 'fonnte' ? 'selected' : ''; ?>>Fonnte (Cadangan / WhatsApp Official API)</option>
                        </select>
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                            Bisa diganti fleksibel kapan saja. Worker antrean otomatis mengikuti gateway terpilih.
                        </small>
                    </div>
                    <div class="col-md-6" id="boxSenderPhone">
                        <label class="form-label small modal-field-label mb-1">Nomor WA Perangkat RS ASSALAM:</label>
                        <input type="text" id="cfgSenderPhone" class="form-control form-control-sm modal-themed-input"
                               value="<?php echo htmlspecialchars($gw_config['sender_display'] ?: '089675463447'); ?>"
                               placeholder="Contoh: 089675463447">
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">Nomor pengirim resmi RS untuk identifikasi antrean.</small>
                    </div>
                </div>

                <!-- Panduan SaungWA -->
                <div id="boxSaungwaGuide" class="modal-meta-box mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                        <div class="small modal-field-label text-success">
                            <i class="fas fa-list-ol me-1"></i> Cara Mendapatkan <code>AppKey</code> di Dashboard SaungWA:
                        </div>
                        <a href="https://app.saungwa.com/user/apps" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:0.76rem;">
                            <i class="fas fa-external-link-alt me-1"></i> Buka Webhook App SaungWA
                        </a>
                    </div>
                    <ol class="small text-muted mb-0 ps-3">
                        <li>Buka menu <strong>Webhook App</strong> (sidebar kiri SaungWA &mdash; <strong>bukan</strong> menu <em>Device Broadcast</em>).</li>
                        <li>Klik <strong>Create App</strong>, pilih Device <strong>RS ASSALAM (6289675463447)</strong>, isi nama &amp; link bebas.</li>
                        <li>Klik <strong>Integration</strong>, lalu salin kode <strong><code>appkey</code></strong> (format UUID) ke kolom di bawah.</li>
                    </ol>
                </div>

                <!-- Panduan Fonnte -->
                <div id="boxFonnteGuide" class="modal-meta-box mb-3 d-none">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                        <div class="small modal-field-label text-info">
                            <i class="fas fa-key me-1"></i> Cara Mendapatkan <code>Token</code> di Dashboard Fonnte:
                        </div>
                        <a href="https://fonnte.com" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info py-0 px-2" style="font-size:0.76rem;">
                            <i class="fas fa-external-link-alt me-1"></i> Buka Dashboard Fonnte
                        </a>
                    </div>
                    <ol class="small text-muted mb-0 ps-3">
                        <li>Buka dan login ke <strong>https://fonnte.com</strong>.</li>
                        <li>Masuk ke menu <strong>Device</strong> (untuk Scan QR biasa) atau menu <strong>WhatsApp Official (WABA)</strong> jika memakai jalur Meta resmi.</li>
                        <li>Salin <strong>Token</strong> perangkat Anda lalu tempelkan pada kolom Token Fonnte di bawah.</li>
                    </ol>
                </div>

                <!-- Kolom Kredensial SaungWA -->
                <div id="groupSaungwaFields" class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label small modal-field-label mb-1">
                            App Key (<code>appkey</code> dari menu <strong>Webhook App</strong> SaungWA): <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="cfgAppKey" class="form-control form-control-sm modal-themed-input"
                               value="<?php echo htmlspecialchars($gw_config['app_key']); ?>"
                               placeholder="Tempel AppKey UUID di sini (contoh: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)">
                    </div>
                    <div class="col-12">
                        <label class="form-label small modal-field-label mb-1">
                            Auth API Key (<code>authkey</code> dari menu <strong>Edit Profile &rarr; Auth Key</strong>): <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="password" id="cfgAuthKey" class="form-control modal-themed-input"
                                   value="<?php echo htmlspecialchars($gw_config['auth_key']); ?>"
                                   placeholder="Masukkan Auth API Key SaungWA">
                            <button class="btn btn-outline-secondary" type="button" id="btnToggleAuthKey" title="Tampilkan / Sembunyikan AuthKey">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kredensial Fonnte -->
                <div id="groupFonnteFields" class="row g-3 mb-3 d-none">
                    <div class="col-12">
                        <label class="form-label small modal-field-label mb-1">
                            Token Fonnte (Mendukung WhatsApp Web &amp; WhatsApp Official API / WABA): <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="password" id="cfgFonnteToken" class="form-control modal-themed-input"
                                   value="<?php echo htmlspecialchars($gw_config['fonnte_token'] ?? ''); ?>"
                                   placeholder="Masukkan Token dari Fonnte.com (contoh: 1oFryxP8vVwKkaMWhaHM)">
                            <button class="btn btn-outline-secondary" type="button" id="btnToggleFonnteToken" title="Tampilkan / Sembunyikan Token">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                            Token ini dapat berupa token perangkat Scan QR maupun token WhatsApp Official Business API Meta.
                        </small>
                    </div>
                </div>

                <div class="modal-meta-box">
                    <div class="small modal-field-label mb-1">
                        <i class="fas fa-paper-plane text-primary me-1"></i> Uji Kirim Pesan Langsung (<span id="testGwLabel">SaungWA</span>)
                    </div>
                    <small class="text-muted d-block mb-2">
                        Masukkan nomor WA pribadi/lain yang aktif (<strong>jangan</strong> diisi nomor pengirim <code><?php echo htmlspecialchars($gw_config['sender_display'] ?: '089675463447'); ?></code>).
                    </small>
                    <div class="row g-2 align-items-center">
                        <div class="col-sm-7">
                            <input type="text" id="cfgTestPhone" class="form-control form-control-sm modal-themed-input"
                                   value=""
                                   placeholder="Nomor WA tujuan tes (contoh: 0857xxxxxxxx)">
                        </div>
                        <div class="col-sm-5 d-grid">
                            <button type="button" id="btnTestSaungwaSend" class="btn btn-sm btn-primary fw-bold">
                                <i class="fas fa-bolt me-1"></i> <span id="btnTestLabel">Simpan &amp; Kirim Pesan Tes</span>
                            </button>
                        </div>
                    </div>
                    <div id="saungwaTestResult" class="alert small py-2 px-3 mt-2 mb-0 d-none"></div>
                </div>
            </div>
            <div class="modal-footer modal-footer-themed d-flex justify-content-between">
                <small class="text-muted"><i class="fas fa-database me-1"></i>Tersimpan otomatis di Database (tanpa perlu izin folder / <code>sudo</code> di Linux).</small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="btnSaveSaungwaConfig">
                        <i class="fas fa-save me-1"></i> Simpan Konfigurasi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
function quickDate(awal, akhir) {
    document.getElementById('inputTglAwal').value = awal;
    document.getElementById('inputTglAkhir').value = akhir;
    document.getElementById('dateFilterForm').submit();
}

$(document).ready(function() {
    let currentStatusFilter = 'unsent'; // unsent (none + failed) | pending | sent | all
    let currentPayFilter    = <?php echo json_encode($jenis_bayar); ?>;

    const DEFAULT_TEMPLATE = `Assalamu’alaikum Wr. Wb.\n\nYth. Bapak/Ibu *{nama}*,\n\nKami dari *Rumah Sakit Assalam* mengingatkan jadwal kontrol Bapak/Ibu:\n\n📅 Tanggal: *{tanggal}*\n⏰ Jam: *{jam}*\n👨‍⚕️ Dokter: *{dokter}*\n🏥 Poliklinik: *{poli}*\n\nMohon hadir sesuai jadwal yang telah ditentukan. Balas *HADIR* untuk konfirmasi kehadiran, atau hubungi kami di 0812-2345-7995 bila ada perubahan jadwal.\n\nMohon memastikan:\n✅ Surat rujukan masih berlaku pada tanggal kunjungan jika jaminan menggunakan BPJS Kesehatan\n✅ Surat rujukan sesuai dengan poli tujuan.\n✅ Mendaftar Melalui Aplikasi MJKN\n\nApabila surat rujukan telah habis masa berlaku atau terdapat kendala administrasi, silakan menghubungi fasilitas kesehatan penerbit rujukan untuk mendapatkan informasi lebih lanjut.\n\nTerima kasih.\n\n*Rumah Sakit Assalam*\n_Ihsan Dalam Pelayanan_`;

    function buildMessageFromTemplate(tpl, data) {
        return String(tpl || DEFAULT_TEMPLATE)
            .replace(/\{nama\}|\[nama\s*pasien\]/gi, data.name || '-')
            .replace(/\{tanggal\}|\[tanggal\]/gi, data.tanggal || '-')
            .replace(/\{jam\}|\[jam\]/gi, data.jam || 'Sesuai Jadwal Praktik Dokter')
            .replace(/\{poli\}|\[nama\s*poli\]|\[poliklinik\]/gi, data.poli || '-')
            .replace(/\{dokter\}|\[nama\s*dokter\]/gi, data.dokter || '-')
            .replace(/\{penjamin\}|\[penjamin\]/gi, data.penjamin || '-')
            .replace(/\{no_surat\}|\[no\s*surat\]/gi, data.noSurat || '-');
    }

    function getDefaultMsg(data) {
        return buildMessageFromTemplate(DEFAULT_TEMPLATE, data);
    }

    function showToastNotification(message, isError) {
        let $notif = $('#waToastNotif');
        if (!$notif.length) {
            $('body').append(`
                <div id="waToastNotif" class="alert shadow-lg d-flex align-items-center gap-2"
                    style="display:none; position:fixed; top:22px; right:22px; z-index:9999; border-radius:12px; min-width:290px; max-width:440px;">
                    <i class="fas fa-check-circle toast-icon"></i>
                    <span class="wa-toast-text small fw-bold"></span>
                </div>
            `);
            $notif = $('#waToastNotif');
        }

        $notif.removeClass('alert-success alert-danger')
              .addClass(isError ? 'alert-danger' : 'alert-success');
        $notif.find('.toast-icon')
              .removeClass('fa-check-circle fa-exclamation-circle')
              .addClass(isError ? 'fa-exclamation-circle' : 'fa-check-circle');
        $notif.find('.wa-toast-text').text(message);
        $notif.stop(true, true).fadeIn(180);
        setTimeout(function() {
            $notif.fadeOut(280);
        }, 3800);
    }

    function getAjaxErrorMessage(xhr, fallback) {
        if (xhr.responseJSON && xhr.responseJSON.error) return xhr.responseJSON.error;
        if (xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
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
        return xhr.status ? (fallback + ' (HTTP ' + xhr.status + ')') : fallback;
    }

    // Recalculate KPI counters based on active Penjamin filter
    function refreshCountersAndFilter() {
        const q = $('#searchInput').val().toLowerCase().trim();

        let totalPay = 0;
        let cntUnsent = 0;
        let cntQueued = 0;
        let cntSent   = 0;
        let visibleCards = 0;

        $('.kontrol-col').each(function() {
            const $col = $(this);
            const pay    = String($col.attr('data-pay') || '');
            const status = String($col.attr('data-status') || 'none');
            const hay    = String($col.attr('data-search') || '');

            const matchPay = (currentPayFilter === 'all' || pay === currentPayFilter);
            if (matchPay) {
                totalPay++;
                if (status === 'sent') cntSent++;
                else if (status === 'pending') cntQueued++;
                else cntUnsent++;
            }

            let matchStatus = true;
            if (currentStatusFilter === 'unsent') {
                matchStatus = (status === 'none' || status === 'failed');
            } else if (currentStatusFilter === 'pending') {
                matchStatus = (status === 'pending');
            } else if (currentStatusFilter === 'sent') {
                matchStatus = (status === 'sent');
            }

            const matchSearch = (!q || hay.indexOf(q) !== -1);
            if (matchPay && matchStatus && matchSearch) {
                $col.removeClass('hidden-card');
                visibleCards++;
            } else {
                $col.addClass('hidden-card');
            }
        });

        $('#totalVisibleCount').text(totalPay);
        $('#queuedCount').text(cntQueued);
        $('#sentCount').text(cntSent);
        $('#unsentCount').text(cntUnsent);

        $('#badgeUnsent').text(cntUnsent);
        $('#badgeQueued').text(cntQueued);
        $('#badgeSent').text(cntSent);

        if (visibleCards === 0 && $('.kontrol-col').length > 0) {
            $('#emptyFilteredNotice').removeClass('d-none');
        } else {
            $('#emptyFilteredNotice').addClass('d-none');
        }
    }

    // Filter Penjamin (Semua / BPJS / Tunai / Asuransi)
    $('.pay-filter-btn').on('click', function() {
        currentPayFilter = $(this).data('pay');
        $('#hiddenJenisBayar').val(currentPayFilter);
        $('.pay-filter-btn').removeClass('active');
        $(this).addClass('active');
        refreshCountersAndFilter();
    });

    // Filter Status Antrean
    $('.filter-btn').on('click', function() {
        currentStatusFilter = $(this).data('filter');
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        refreshCountersAndFilter();
    });

    $('#searchInput').on('input', function() {
        refreshCountersAndFilter();
    });

    // Update tampilan kartu ketika status berubah (masuk antrean / batal antrean)
    function updateCardQueueState(refKey, newStatus, savedMsg, savedPhone) {
        const $col = $(`.kontrol-col[data-ref-key="${refKey.replace(/"/g, '\\"')}"]`);
        if (!$col.length) return;

        $col.attr('data-status', newStatus);
        if (savedPhone) {
            $col.attr('data-has-phone', '1');
        }

        const $pill = $col.find('.badge-status-card');
        $pill.removeClass('st-none st-pending st-sent st-failed');

        const $btn = $col.find('.btn-open-preview');
        $btn.attr('data-status', newStatus);
        if (savedMsg !== undefined) $btn.attr('data-saved-msg', savedMsg);
        if (savedPhone) $btn.attr('data-phone', savedPhone);

        $col.find('.card-error-box').addClass('d-none').text('');

        if (newStatus === 'pending') {
            $pill.addClass('st-pending').html('<i class="fas fa-clock"></i> Dalam Antrean');
            $btn.removeClass('btn-retry').addClass('btn-queued')
                .prop('disabled', false)
                .removeAttr('style')
                .html('<i class="fas fa-edit"></i> Edit Antrean');
        } else if (newStatus === 'none') {
            $pill.addClass('st-none').html('<i class="fas fa-hourglass-start"></i> Belum Diantrean');
            $btn.removeClass('btn-queued btn-retry')
                .prop('disabled', false)
                .removeAttr('style')
                .html('<i class="fab fa-whatsapp"></i> Preview & Antrekan');
        } else if (newStatus === 'sent') {
            $pill.addClass('st-sent').html('<i class="fas fa-check-circle"></i> Terkirim');
            $btn.removeClass('btn-queued btn-retry')
                .prop('disabled', true)
                .css({background: '#64748b', cursor: 'default'})
                .html('<i class="fas fa-check"></i> Sudah Terkirim');
        }

        refreshCountersAndFilter();
    }

    function updateCharCounter() {
        $('#msgCharCount').text(($('#msgText').val() || '').length);
    }
    $('#msgText').on('input', updateCharCounter);

    // Buka Modal Preview & Edit per Pasien
    let activeCardData = null;

    $(document).on('click', '.btn-open-preview', function() {
        const $btn = $(this);
        activeCardData = {
            refKey:   String($btn.attr('data-ref-key') || ''),
            phone:    String($btn.attr('data-phone') || ''),
            name:     String($btn.attr('data-name') || ''),
            nomr:     String($btn.attr('data-nomr') || ''),
            penjamin: String($btn.attr('data-penjamin') || 'BPJS'),
            poli:     String($btn.attr('data-poli') || '-'),
            dokter:   String($btn.attr('data-dokter') || '-'),
            tanggal:  String($btn.attr('data-tanggal') || '-'),
            jam:      String($btn.attr('data-jam') || 'Sesuai Jadwal Praktik Dokter'),
            noSurat:  String($btn.attr('data-no-surat') || '-'),
            savedMsg: String($btn.attr('data-saved-msg') || ''),
            status:   String($btn.attr('data-status') || 'none')
        };

        $('#msgRecipientName').text(`${activeCardData.name} (${activeCardData.nomr})`);
        $('#msgRecipientMeta').text(`Jadwal Kontrol: ${activeCardData.tanggal} • ${activeCardData.poli} • ${activeCardData.dokter}`);
        $('#msgRecipientPay').text(activeCardData.penjamin);
        $('#msgPhoneInput').val(activeCardData.phone);

        const initialText = activeCardData.savedMsg ? activeCardData.savedMsg : getDefaultMsg(activeCardData);
        $('#msgText').val(initialText);
        updateCharCounter();

        if (activeCardData.status === 'pending') {
            $('#btnCancelQueueItem').removeClass('d-none');
            $('#btnEnqueueWA').html('<i class="fas fa-save me-1"></i> Simpan Perubahan Antrean');
        } else {
            $('#btnCancelQueueItem').addClass('d-none');
            $('#btnEnqueueWA').html('<i class="fas fa-clock me-1"></i> Masukkan ke Antrean Service');
        }

        new bootstrap.Modal(document.getElementById('msgModal')).show();
    });

    $('#btnResetDefaultMsg').on('click', function() {
        if (!activeCardData) return;
        $('#msgText').val(getDefaultMsg(activeCardData));
        updateCharCounter();
    });

    // Klik Masukkan ke Antrean Service (Single)
    $('#btnEnqueueWA').on('click', function(e) {
        e.preventDefault();
        if (!activeCardData) return;

        const phone = $('#msgPhoneInput').val().trim();
        const msg   = $('#msgText').val().trim();

        if (!phone) {
            alert('Nomor WhatsApp tujuan wajib diisi terlebih dahulu.');
            $('#msgPhoneInput').focus();
            return;
        }
        if (!msg) {
            alert('Isi pesan reminder tidak boleh kosong.');
            $('#msgText').focus();
            return;
        }

        const $btn = $(this);
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan ke Antrean...');

        $.ajax({
            url: 'api/reminder_kontrol.php',
            method: 'POST',
            dataType: 'json',
            global: false,
            timeout: 30000,
            data: {
                action: 'enqueue',
                ref_key: activeCardData.refKey,
                nomr: activeCardData.nomr,
                nama_pasien: activeCardData.name,
                phone: phone,
                message: msg
            }
        }).done(function(res) {
            if (!res || res.success !== true) {
                alert((res && res.error) ? res.error : 'Gagal memasukkan ke antrean.');
                return;
            }

            updateCardQueueState(activeCardData.refKey, 'pending', msg, res.phone || phone);
            bootstrap.Modal.getInstance(document.getElementById('msgModal'))?.hide();
            showToastNotification('Reminder ' + activeCardData.name + ' masuk ke antrean service.', false);
        }).fail(function(xhr) {
            alert(getAjaxErrorMessage(xhr, 'Gagal menghubungi server antrean.'));
        }).always(function() {
            $btn.prop('disabled', false).html(origHtml);
        });
    });

    // Batalkan item dari antrean selagi masih 'pending'
    $('#btnCancelQueueItem').on('click', function() {
        if (!activeCardData) return;
        if (!confirm('Keluarkan ' + activeCardData.name + ' dari antrean pengiriman otomatis?')) return;

        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: 'api/reminder_kontrol.php',
            method: 'POST',
            dataType: 'json',
            global: false,
            data: {
                action: 'cancel_queue',
                ref_key: activeCardData.refKey
            }
        }).done(function(res) {
            if (!res || res.success !== true) {
                alert((res && res.error) ? res.error : 'Gagal membatalkan antrean.');
                return;
            }
            updateCardQueueState(activeCardData.refKey, 'none', '', activeCardData.phone);
            bootstrap.Modal.getInstance(document.getElementById('msgModal'))?.hide();
            showToastNotification('Antrean ' + activeCardData.name + ' berhasil dibatalkan.', false);
        }).fail(function(xhr) {
            alert(getAjaxErrorMessage(xhr, 'Gagal membatalkan antrean.'));
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    // Kirim Manual via wa.me
    $(document).on('click', '.btn-wa-manual', function() {
        const phone = String($(this).data('phone') || '');
        const message = getDefaultMsg({
            name:     $(this).data('name'),
            poli:     $(this).data('poli'),
            dokter:   $(this).data('dokter'),
            tanggal:  $(this).data('tanggal'),
            jam:      $(this).data('jam') || 'Sesuai Jadwal Praktik Dokter',
            penjamin: $(this).closest('.kontrol-col').find('.kontrol-badge-pay').text().trim()
        });
        window.open('https://wa.me/' + encodeURIComponent(phone) + '?text=' + encodeURIComponent(message), '_blank', 'noopener');
    });

    // ============================================================
    // ANTREKAN MASSAL DENGAN PREVIEW & EDIT TEMPLATE
    // ============================================================
    function getBulkEligibleItems() {
        const list = [];
        $('.kontrol-col').each(function() {
            const $col     = $(this);
            const pay      = String($col.attr('data-pay') || '');
            const status   = String($col.attr('data-status') || 'none');
            const hasPhone = String($col.attr('data-has-phone') || '0') === '1';

            const matchPay = (currentPayFilter === 'all' || pay === currentPayFilter);
            if (!matchPay || !hasPhone) return;
            if (status !== 'none' && status !== 'failed') return;

            const $btn = $col.find('.btn-open-preview');
            list.push({
                refKey:   String($btn.attr('data-ref-key') || ''),
                phone:    String($btn.attr('data-phone') || ''),
                name:     String($btn.attr('data-name') || ''),
                nomr:     String($btn.attr('data-nomr') || ''),
                penjamin: String($btn.attr('data-penjamin') || '-'),
                poli:     String($btn.attr('data-poli') || '-'),
                dokter:   String($btn.attr('data-dokter') || '-'),
                tanggal:  String($btn.attr('data-tanggal') || '-'),
            jam:      String($btn.attr('data-jam') || 'Sesuai Jadwal Praktik Dokter'),
                noSurat:  String($btn.attr('data-no-surat') || '-')
            });
        });
        return list;
    }

    function refreshBulkSamplePreview() {
        const eligible = getBulkEligibleItems();
        const tpl = $('#bulkTemplateText').val();
        if (!eligible.length) {
            $('#bulkSamplePreview').text('Tidak ada pasien yang belum diantrean pada filter saat ini.');
            return;
        }
        $('#bulkSamplePreview').text(buildMessageFromTemplate(tpl, eligible[0]));
    }

    $('#btnOpenBulkModal').on('click', function() {
        const eligible = getBulkEligibleItems();
        $('#bulkEligibleCount').text(eligible.length);

        const totalMinutes = eligible.length * 5;
        const hrs = Math.floor(totalMinutes / 60);
        const mins = totalMinutes % 60;
        const estLabel = eligible.length === 0
            ? '0 menit'
            : (hrs > 0 ? `± ${hrs} jam ${mins} menit` : `± ${mins} menit`);
        $('#bulkEstDuration').text(estLabel);

        if (!$('#bulkTemplateText').val()) {
            $('#bulkTemplateText').val(DEFAULT_TEMPLATE);
        }
        refreshBulkSamplePreview();
        $('#btnConfirmBulkEnqueue').prop('disabled', eligible.length === 0);

        new bootstrap.Modal(document.getElementById('bulkModal')).show();
    });

    $('#bulkTemplateText').on('input', refreshBulkSamplePreview);
    $('#btnResetBulkTemplate').on('click', function() {
        $('#bulkTemplateText').val(DEFAULT_TEMPLATE);
        refreshBulkSamplePreview();
    });

    $('#btnConfirmBulkEnqueue').on('click', function() {
        const eligible = getBulkEligibleItems();
        if (!eligible.length) return;

        const tpl = $('#bulkTemplateText').val().trim();
        if (!tpl) {
            alert('Template pesan tidak boleh kosong.');
            return;
        }

        const payloadItems = eligible.map(function(d) {
            return {
                ref_key:     d.refKey,
                nomr:        d.nomr,
                nama_pasien: d.name,
                phone:       d.phone,
                message:     buildMessageFromTemplate(tpl, d)
            };
        });

        const $btn = $(this);
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Mengantrekan...');

        $.ajax({
            url: 'api/reminder_kontrol.php',
            method: 'POST',
            dataType: 'json',
            global: false,
            timeout: 45000,
            data: {
                action: 'bulk_enqueue',
                items_json: JSON.stringify(payloadItems)
            }
        }).done(function(res) {
            if (!res || res.success !== true) {
                alert((res && res.error) ? res.error : 'Gagal mengantrekan pasien.');
                return;
            }

            if (res.queued_items) {
                Object.keys(res.queued_items).forEach(function(rk) {
                    const info = res.queued_items[rk];
                    updateCardQueueState(rk, 'pending', info.message, info.phone);
                });
            }

            bootstrap.Modal.getInstance(document.getElementById('bulkModal'))?.hide();
            showToastNotification(res.message || 'Pasien berhasil masuk antrean service.', false);
        }).fail(function(xhr) {
            alert(getAjaxErrorMessage(xhr, 'Gagal memproses antrean massal.'));
        }).always(function() {
            $btn.prop('disabled', false).html(origHtml);
        });
    });

    // ============================================================
    // PENGATURAN & UJI KONEKSI SAUNGWA
    // ============================================================
    function updateGatewayBadgeUI(cfg) {
        if (!cfg) return;
        const $btn    = $('#btnOpenSaungwaConfig');
        const $notice = $('#saungwaAppKeyNotice');

        $btn.removeClass('btn-gw-ready btn-gw-warn');
        if (cfg.is_ready) {
            $btn.addClass('btn-gw-ready');
            if (cfg.provider === 'fonnte') {
                $btn.html('<i class="fas fa-plug"></i> <span id="gwStatusLabel">Gateway: Fonnte (Aktif)</span>');
            } else {
                const disp = cfg.sender_display || 'Connected';
                $btn.html('<i class="fas fa-check-circle"></i> <span id="gwStatusLabel">SaungWA Aktif (' + $('<div>').text(disp).html() + ')</span>');
            }
            $notice.addClass('d-none');
        } else {
            $btn.addClass('btn-gw-warn');
            const label = (cfg.provider === 'fonnte') ? 'Gateway: Fonnte (Token Kosong)' : 'SaungWA: Lengkapi AppKey';
            $btn.html('<i class="fas fa-exclamation-triangle"></i> <span id="gwStatusLabel">' + label + '</span>');
        }
    }

    $('#btnOpenSaungwaConfig').on('click', function() {
        $('#saungwaTestResult').addClass('d-none').removeClass('alert-success alert-danger').text('');
        new bootstrap.Modal(document.getElementById('saungwaModal')).show();
    });

    function syncGatewayProviderUI() {
        const prov = $('#cfgProvider').val();
        if (prov === 'fonnte') {
            $('#boxSaungwaGuide').addClass('d-none');
            $('#boxFonnteGuide').removeClass('d-none');
            $('#groupSaungwaFields').addClass('d-none');
            $('#groupFonnteFields').removeClass('d-none');
            $('#testGwLabel').text('Fonnte');
            $('#btnTestLabel').text('Simpan & Kirim Pesan Tes (Fonnte)');
        } else {
            $('#boxSaungwaGuide').removeClass('d-none');
            $('#boxFonnteGuide').addClass('d-none');
            $('#groupSaungwaFields').removeClass('d-none');
            $('#groupFonnteFields').addClass('d-none');
            $('#testGwLabel').text('SaungWA');
            $('#btnTestLabel').text('Simpan & Kirim Pesan Tes (SaungWA)');
        }
    }
    $('#cfgProvider').on('change', syncGatewayProviderUI);
    syncGatewayProviderUI();

    $('#btnToggleAuthKey').on('click', function() {
        const $inp = $('#cfgAuthKey');
        const isPwd = $inp.attr('type') === 'password';
        $inp.attr('type', isPwd ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    $('#btnToggleFonnteToken').on('click', function() {
        const $inp = $('#cfgFonnteToken');
        const isPwd = $inp.attr('type') === 'password';
        $inp.attr('type', isPwd ? 'text' : 'password');
        $(this).find('i').toggleClass('fa-eye fa-eye-slash');
    });

    $('#btnSaveSaungwaConfig').on('click', function() {
        const $btn     = $(this);
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Menyimpan...');

        $.ajax({
            url: 'api/reminder_kontrol.php',
            method: 'POST',
            dataType: 'json',
            global: false,
            data: {
                action: 'save_saungwa_config',
                provider: $('#cfgProvider').val(),
                sender_phone: $('#cfgSenderPhone').val().trim(),
                app_key: $('#cfgAppKey').val().trim(),
                auth_key: $('#cfgAuthKey').val().trim(),
                fonnte_token: $('#cfgFonnteToken').val().trim()
            }
        }).done(function(res) {
            if (!res || res.success !== true) {
                alert((res && res.error) ? res.error : 'Gagal menyimpan konfigurasi WhatsApp Gateway.');
                return;
            }
            if (res.config && res.config.app_key !== undefined) {
                $('#cfgAppKey').val(res.config.app_key);
            }
            updateGatewayBadgeUI(res.config);
            showToastNotification(res.message || 'Konfigurasi WhatsApp Gateway tersimpan.', false);
            bootstrap.Modal.getInstance(document.getElementById('saungwaModal'))?.hide();
        }).fail(function(xhr) {
            alert(getAjaxErrorMessage(xhr, 'Gagal menyimpan konfigurasi WhatsApp Gateway.'));
        }).always(function() {
            $btn.prop('disabled', false).html(origHtml);
        });
    });

    $('#btnTestSaungwaSend').on('click', function() {
        const prov        = $('#cfgProvider').val();
        const appKey      = $('#cfgAppKey').val().trim();
        const authKey     = $('#cfgAuthKey').val().trim();
        const fonnteToken = $('#cfgFonnteToken').val().trim();
        const testTo      = $('#cfgTestPhone').val().trim();
        const $resBox     = $('#saungwaTestResult');

        if (prov === 'fonnte') {
            if (!fonnteToken) {
                $resBox.removeClass('d-none alert-success').addClass('alert-danger')
                       .html('<i class="fas fa-exclamation-circle me-1"></i> <strong>Token Fonnte masih kosong!</strong> Buka dashboard <em>https://fonnte.com</em> &rarr; menu Device &rarr; salin Token ke atas.');
                $('#cfgFonnteToken').focus();
                return;
            }
        } else {
            if (!appKey) {
                $resBox.removeClass('d-none alert-success').addClass('alert-danger')
                       .html('<i class="fas fa-exclamation-circle me-1"></i> <strong>AppKey masih kosong!</strong> Buka menu <em>Webhook App</em> di SaungWA &rarr; Buat App untuk 089675463447 &rarr; salin AppKey ke atas.');
                $('#cfgAppKey').focus();
                return;
            }
            if (!authKey) {
                $resBox.removeClass('d-none alert-success').addClass('alert-danger')
                       .text('Auth API Key SaungWA wajib diisi.');
                $('#cfgAuthKey').focus();
                return;
            }
        }

        if (!testTo) {
            $resBox.removeClass('d-none alert-success').addClass('alert-danger')
                   .text('Masukkan nomor WA tujuan tes terlebih dahulu.');
            $('#cfgTestPhone').focus();
            return;
        }

        const normPhone = function(v) {
            let c = String(v || '').replace(/[^0-9]/g, '');
            if (c.startsWith('0')) c = '62' + c.slice(1);
            else if (c && !c.startsWith('62')) c = '62' + c;
            return c;
        };
        const senderNorm = normPhone($('#cfgSenderPhone').val());
        if (prov === 'saungwa' && senderNorm && normPhone(testTo) === senderNorm) {
            $resBox.removeClass('d-none alert-success').addClass('alert-danger')
                   .html('<i class="fas fa-exclamation-triangle me-1"></i> Nomor tujuan tes <strong>tidak boleh sama</strong> dengan Nomor WA Perangkat Pengirim (<code>' + $('<div>').text($('#cfgSenderPhone').val().trim()).html() + '</code>). Masukkan nomor WA pribadi/lain yang aktif.');
            $('#cfgTestPhone').focus();
            return;
        }

        const $btn     = $(this);
        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Mengirim Tes...');
        $resBox.addClass('d-none');

        $.ajax({
            url: 'api/reminder_kontrol.php',
            method: 'POST',
            dataType: 'json',
            global: false,
            timeout: 40000,
            data: {
                action: 'test_saungwa_send',
                provider: prov,
                sender_phone: $('#cfgSenderPhone').val().trim(),
                app_key: appKey,
                auth_key: authKey,
                fonnte_token: fonnteToken,
                test_phone: testTo
            }
        }).done(function(res) {
            if (res && res.clean_app_key) {
                $('#cfgAppKey').val(res.clean_app_key);
            }
            if (res && res.success === true) {
                $resBox.removeClass('d-none alert-danger').addClass('alert-success')
                       .html('<i class="fas fa-check-circle me-1"></i> ' + $('<div>').text(res.message).html());
                updateGatewayBadgeUI(res.config || {
                    provider: prov,
                    sender_display: $('#cfgSenderPhone').val().trim(),
                    is_ready: true
                });
                showToastNotification('Tes kirim ' + (prov === 'fonnte' ? 'Fonnte' : 'SaungWA') + ' berhasil!', false);
            } else {
                const errMsg = (res && res.error) ? res.error : 'Gagal mengirim pesan tes.';
                $resBox.removeClass('d-none alert-success').addClass('alert-danger')
                       .html('<i class="fas fa-times-circle me-1"></i> ' + $('<div>').text(errMsg).html());
            }
        }).fail(function(xhr) {
            $resBox.removeClass('d-none alert-success').addClass('alert-danger')
                   .text(getAjaxErrorMessage(xhr, 'Gagal menghubungi server saat tes kirim.'));
        }).always(function() {
            $btn.prop('disabled', false).html(origHtml);
        });
    });

    refreshCountersAndFilter();
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
