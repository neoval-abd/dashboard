<?php
/*
 * File: api/reminder_queue_helpers.php
 * Fungsi: Helper tabel antrean WhatsApp (Ulang Tahun & Reminder Kontrol),
 *         sanitasi/validasi nomor HP pasien, dan proteksi jam kirim aman.
 */

function ensure_birthday_queue_table($koneksi)
{
    $sql = "CREATE TABLE IF NOT EXISTS antrean_ucapan_ulang_tahun (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_rkm_medis VARCHAR(40) NOT NULL,
        tahun INT NOT NULL,
        nama_pasien VARCHAR(150) DEFAULT '',
        phone VARCHAR(30) NOT NULL,
        message TEXT NOT NULL,
        pengirim VARCHAR(100) DEFAULT '',
        status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        scheduled_at DATETIME NOT NULL,
        sent_at DATETIME NULL,
        last_error TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_rm_tahun (no_rkm_medis, tahun),
        KEY idx_status_schedule (status, scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $koneksi->query($sql);
}

function ensure_kontrol_queue_table($koneksi)
{
    $sql = "CREATE TABLE IF NOT EXISTS antrean_reminder_kontrol (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_sep VARCHAR(40) NOT NULL,
        nomr VARCHAR(40) DEFAULT NULL,
        nama_pasien VARCHAR(150) DEFAULT NULL,
        phone VARCHAR(30) NOT NULL,
        message TEXT NOT NULL,
        pengirim VARCHAR(100) DEFAULT NULL,
        status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        scheduled_at DATETIME NOT NULL,
        sent_at DATETIME DEFAULT NULL,
        last_error TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_no_sep (no_sep),
        KEY idx_status_schedule (status, scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $koneksi->query($sql);
}

function get_wa_queue_delay()
{
    if (defined('SAUNGWA_QUEUE_DELAY_SECONDS')) {
        return max(60, (int) SAUNGWA_QUEUE_DELAY_SECONDS);
    }
    return defined('FONNTE_QUEUE_DELAY_SECONDS') ? max(60, (int) FONNTE_QUEUE_DELAY_SECONDS) : 300;
}

function get_fonnte_queue_delay()
{
    return get_wa_queue_delay();
}

function reminder_queue_max_attempts()
{
    if (defined('SAUNGWA_QUEUE_MAX_ATTEMPTS')) {
        return max(1, (int) SAUNGWA_QUEUE_MAX_ATTEMPTS);
    }
    return defined('FONNTE_QUEUE_MAX_ATTEMPTS') ? max(1, (int) FONNTE_QUEUE_MAX_ATTEMPTS) : 2;
}

/**
 * Validasi & normalisasi nomor WhatsApp pasien dengan fallback (mis. SEP -> Master Pasien).
 * Menyaring nomor kosong, karakter '-', telepon rumah (021), atau nomor dummy (08123456789 / 000000).
 */
function sanitize_patient_phone($primaryPhone, $fallbackPhone = '', $primaryLabel = 'SEP', $fallbackLabel = 'Master Pasien')
{
    $candidates = [
        ['raw' => trim((string) $primaryPhone), 'source' => $primaryLabel],
        ['raw' => trim((string) $fallbackPhone), 'source' => $fallbackLabel],
    ];

    $dummyPatterns = [
        '/^6280{6,}$/',
        '/^628(\d)\1{7,}$/',          // digit berulang semua mis. 081111111111, 089999999999
        '/^62812345678\d*$/',          // dummy berurutan 08123456789...
        '/^62898765432\d*$/',
    ];

    foreach ($candidates as $cand) {
        if ($cand['raw'] === '' || $cand['raw'] === '-') {
            continue;
        }

        // Jika satu kolom berisi dua nomor dipisah '/' atau ',', ambil bagian pertama
        $parts = preg_split('/[\/,;]+/', $cand['raw']);
        foreach ($parts as $part) {
            $clean = preg_replace('/[^0-9]/', '', trim($part));
            if ($clean === '') {
                continue;
            }

            if (substr($clean, 0, 1) === '0') {
                $clean = '62' . substr($clean, 1);
            } elseif (substr($clean, 0, 1) === '8') {
                $clean = '62' . $clean;
            }

            // Nomor seluler WA Indonesia wajib diawali 628 dan panjang 11 s/d 15 digit
            $len = strlen($clean);
            if (substr($clean, 0, 3) !== '628' || $len < 11 || $len > 15) {
                continue;
            }

            $isDummy = false;
            foreach ($dummyPatterns as $pattern) {
                if (preg_match($pattern, $clean)) {
                    $isDummy = true;
                    break;
                }
            }
            if ($isDummy) {
                continue;
            }

            $display = '0' . substr($clean, 2);
            return [
                'valid'         => true,
                'phone_wa'      => $clean,
                'phone_display' => $display,
                'source'        => $cand['source'],
            ];
        }
    }

    return [
        'valid'         => false,
        'phone_wa'      => '',
        'phone_display' => '',
        'source'        => '',
    ];
}

/**
 * Klasifikasi kode penjamin / nama penjamin ke 'bpjs', 'tunai', atau 'asuransi'.
 */
function classify_jenis_bayar($kd_pj, $png_jawab)
{
    $kd = strtoupper(trim((string) $kd_pj));
    $nm = strtoupper(trim((string) $png_jawab));

    if ($kd === 'BPJ' || strpos($nm, 'BPJS') !== false || strpos($nm, 'JKN') !== false || strpos($nm, 'KIS') !== false) {
        return 'bpjs';
    }

    if ($kd === '' || $kd === '-' || $kd === 'A09' || $nm === '' || $nm === '-') {
        return 'tunai';
    }

    $tunaiKeywords = [
        'TUNAI', 'UMUM', 'PRIBADI', 'MANDIRI BAYAR', 'CASH', 'PROMO',
        'QRIS', 'KREDIT', 'DEBIT', 'EDC', 'TRANSFER', 'TF ', 'BCA', 'MANDIRI', 'BRI', 'BNI', 'BSI'
    ];
    foreach ($tunaiKeywords as $kw) {
        if (strpos($nm, $kw) !== false) {
            return 'tunai';
        }
    }

    return 'asuransi';
}

/**
 * Cek apakah waktu server saat ini berada pada jam sopan pengiriman WA (default 07:00 - 20:00).
 */
function is_within_wa_safe_hours(&$infoMessage = '')
{
    $startHour = defined('WA_SAFE_HOUR_START') ? (int) WA_SAFE_HOUR_START : 7;
    $endHour   = defined('WA_SAFE_HOUR_END') ? (int) WA_SAFE_HOUR_END : 20;

    $currentHour = (int) date('G');
    if ($currentHour < $startHour || $currentHour >= $endHour) {
        $infoMessage = sprintf(
            'Di luar jam pengiriman aman (%02d:00 - %02d:00 WIB). Antrean ditahan sementara hingga jam operasional.',
            $startHour,
            $endHour
        );
        return false;
    }

    return true;
}

/**
 * Pulihkan antrean yang tersangkut di status 'processing' lebih dari 15 menit (mis. bila CLI terputus).
 */
function recover_stale_queue_items($koneksi, $tableName)
{
    $allowedTables = ['antrean_reminder_kontrol', 'antrean_ucapan_ulang_tahun'];
    if (!in_array($tableName, $allowedTables, true)) {
        return;
    }

    $maxAttempts = reminder_queue_max_attempts();
    $sql = "UPDATE {$tableName}
            SET status = IF(attempts >= {$maxAttempts}, 'failed', 'pending'),
                last_error = COALESCE(last_error, 'Timeout saat memproses pengiriman sebelumnya')
            WHERE status = 'processing'
              AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    @$koneksi->query($sql);
}
?>