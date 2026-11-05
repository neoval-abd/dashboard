-- ==============================================================================
-- DOKUMENTASI DATABASE WHATSAPP GATEWAY & WA BLAST
-- Sistem Dashboard Eksekutif RS Assalam
-- Tanggal: 05 November 2026
-- ==============================================================================
-- File ini mendokumentasikan skema tabel untuk fitur WhatsApp Blast & Gateway:
-- 1. konfigurasi_wa_gateway     : Menyimpan kredensial SaungWA & Fonnte, status provider aktif.
-- 2. antrean_reminder_kontrol   : Antrean pengiriman pengingat jadwal kontrol pasien (BPJS/Tunai/Asuransi).
-- 3. antrean_ucapan_ulang_tahun : Antrean pengiriman ucapan selamat ulang tahun pasien.
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. TABEL KONFIGURASI GATEWAY WHATSAPP (SAUNGWA & FONNTE)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `konfigurasi_wa_gateway` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    `provider` VARCHAR(20) NOT NULL DEFAULT 'saungwa' COMMENT 'Provider aktif: saungwa / fonnte',
    `sender_phone` VARCHAR(30) NOT NULL DEFAULT '' COMMENT 'Nomor WA pengirim RS Assalam (contoh: 6289675463447)',
    `app_key` VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'AppKey SaungWA dari menu Webhook App',
    `auth_key` VARCHAR(160) NOT NULL DEFAULT '' COMMENT 'AuthKey SaungWA dari menu Edit Profile',
    `fonnte_token` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Token perangkat atau token WABA resmi dari Fonnte',
    `api_url` VARCHAR(255) NOT NULL DEFAULT 'https://app.saungwa.com/api/create-message' COMMENT 'Endpoint API kirim SaungWA',
    `test_target` VARCHAR(30) NOT NULL DEFAULT '' COMMENT 'Target redirect pengujian (opsional)',
    `last_dispatch_at` DATETIME DEFAULT NULL COMMENT 'Timestamp pengiriman terakhir untuk rate limiting / pengunci lintas-server',
    `updated_by` VARCHAR(100) DEFAULT NULL COMMENT 'Username atau nama pegawai yang terakhir mengubah pengaturan',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Konfigurasi Kredensial Gateway WhatsApp Terpadu';

-- Inisialisasi baris konfigurasi default (id = 1):
INSERT IGNORE INTO `konfigurasi_wa_gateway` (`id`, `provider`, `sender_phone`, `app_key`, `auth_key`, `fonnte_token`, `updated_by`)
VALUES (1, 'saungwa', '6289675463447', '', '', '', 'system');

-- ------------------------------------------------------------------------------
-- SCRIPT MIGRASI / ALTER TABLE (Bila tabel sudah ada sebelumnya):
-- Catatan: Aplikasi PHP secara otomatis mengeksekusi pemeriksaan ini saat berjalan.
-- ------------------------------------------------------------------------------
-- ALTER TABLE `konfigurasi_wa_gateway` ADD COLUMN IF NOT EXISTS `last_dispatch_at` DATETIME DEFAULT NULL AFTER `test_target`;
-- ALTER TABLE `konfigurasi_wa_gateway` ADD COLUMN IF NOT EXISTS `fonnte_token` VARCHAR(100) NOT NULL DEFAULT '' AFTER `auth_key`;


-- ------------------------------------------------------------------------------
-- 2. TABEL ANTREAN REMINDER KONTROL PASIEN (BPJS, TUNAI, ASURANSI)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `antrean_reminder_kontrol` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `no_sep` VARCHAR(40) NOT NULL COMMENT 'Nomor SEP untuk BPJS atau No Registrasi / SKDP untuk Tunai/Asuransi',
    `nomr` VARCHAR(40) DEFAULT NULL COMMENT 'Nomor Rekam Medis pasien',
    `nama_pasien` VARCHAR(150) DEFAULT NULL COMMENT 'Nama lengkap pasien',
    `phone` VARCHAR(30) NOT NULL COMMENT 'Nomor WhatsApp pasien (format normal 628...)',
    `message` TEXT NOT NULL COMMENT 'Isi teks pesan pengingat jadwal kontrol',
    `pengirim` VARCHAR(100) DEFAULT NULL COMMENT 'Nama staf / user yang memasukkan antrean',
    `status` ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending' COMMENT 'Status antrean kirim',
    `attempts` INT NOT NULL DEFAULT 0 COMMENT 'Jumlah percobaan pengiriman (maks 2x sebelum failed)',
    `scheduled_at` DATETIME NOT NULL COMMENT 'Jadwal waktu pengiriman (diberi jeda ~5 menit per pesan)',
    `sent_at` DATETIME DEFAULT NULL COMMENT 'Waktu pesan berhasil terkirim ke WhatsApp',
    `last_error` TEXT DEFAULT NULL COMMENT 'Pesan error terakhir dari gateway jika pengiriman gagal',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_no_sep` (`no_sep`),
    KEY `idx_status_schedule` (`status`, `scheduled_at`),
    KEY `idx_nomr` (`nomr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Antrean Pengiriman Pesan Reminder Kontrol Pasien';


-- ------------------------------------------------------------------------------
-- 3. TABEL ANTREAN UCAPAN ULANG TAHUN PASIEN
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `antrean_ucapan_ulang_tahun` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `no_rkm_medis` VARCHAR(40) NOT NULL COMMENT 'Nomor Rekam Medis pasien',
    `tahun` INT NOT NULL COMMENT 'Tahun pengiriman ucapan ulang tahun',
    `nama_pasien` VARCHAR(150) DEFAULT '' COMMENT 'Nama pasien',
    `phone` VARCHAR(30) NOT NULL COMMENT 'Nomor WhatsApp pasien',
    `message` TEXT NOT NULL COMMENT 'Isi ucapan selamat ulang tahun',
    `pengirim` VARCHAR(100) DEFAULT '' COMMENT 'Nama staf yang memasukkan antrean',
    `status` ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
    `attempts` INT NOT NULL DEFAULT 0,
    `scheduled_at` DATETIME NOT NULL,
    `sent_at` DATETIME NULL,
    `last_error` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_rm_tahun` (`no_rkm_medis`, `tahun`),
    KEY `idx_status_schedule` (`status`, `scheduled_at`),
    KEY `idx_no_rkm_medis` (`no_rkm_medis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Antrean Pengiriman Ucapan Selamat Ulang Tahun Pasien';


-- ------------------------------------------------------------------------------
-- QUERY BANTUAN UNTUK MONITORING & MAINTENANCE IT / DBA:
-- ------------------------------------------------------------------------------
-- 1. Cek Ringkasan Antrean Reminder Kontrol:
-- SELECT status, COUNT(*) AS jumlah FROM antrean_reminder_kontrol GROUP BY status;

-- 2. Cek 10 Pesan Terakhir yang Gagal beserta Detail Error:
-- SELECT id, no_sep, nomr, nama_pasien, phone, attempts, last_error, updated_at
-- FROM antrean_reminder_kontrol
-- WHERE status IN ('pending', 'failed') AND last_error IS NOT NULL
-- ORDER BY updated_at DESC LIMIT 10;

-- 3. Reset Antrean yang Gagal agar Dikirim Ulang oleh Worker:
-- UPDATE antrean_reminder_kontrol
-- SET status = 'pending', attempts = 0, scheduled_at = NOW(), last_error = NULL
-- WHERE status = 'failed';
