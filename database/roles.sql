-- ==============================================================================
-- DOKUMENTASI DATABASE MANAJEMEN ROLE & HAK AKSES PENGGUNA
-- Sistem Dashboard Eksekutif RS Assalam
-- Tanggal: 05 November 2026
-- ==============================================================================
-- File ini mendokumentasikan skema tabel `roles` yang digunakan untuk memetakan
-- hak akses akun (Super Admin, Manajemen, Keuangan, Kepegawaian, Pelayanan, dll)
-- yang melengkapi tabel otentikasi bawaan Khanza SIMRS (`user`).
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `roles` (
    `username` VARCHAR(20) NOT NULL COMMENT 'Username login (AES_ENCRYPT / ID Pegawai Khanza)',
    `role` VARCHAR(50) DEFAULT NULL COMMENT 'Nama Role: Super Admin, Manajemen, Keuangan, Kepegawaian, Pelayanan, dll',
    `cap` VARCHAR(255) DEFAULT NULL COMMENT 'Label kemampuan/hak akses ringkas',
    `module` TEXT DEFAULT NULL COMMENT 'Daftar kode modul JSON yang diizinkan untuk diakses pengguna',
    PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pemetaan Role dan Hak Akses Dashboard Assalam';

-- Catatan Penggunaan:
-- Tabel ini dikelola melalui menu Super Admin -> Manajemen Pengguna (manage_users.php).
-- Pengguna dengan role Super Admin memiliki hak penuh untuk mengatur hak akses akun lain.

