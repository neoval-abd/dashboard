-- ==============================================================================
-- DOKUMENTASI DATABASE GENERATOR NOMOR DOKUMEN AKREDITASI
-- Sistem Dashboard Eksekutif RS Assalam
-- Tanggal: 05 November 2026
-- ==============================================================================
-- File ini mendokumentasikan skema tabel `generate_nomor_dok` yang digunakan oleh
-- modul Akreditasi RS Assalam (laporan_generate_nomor_dok.php) untuk mengenerate
-- nomor surat / dokumen resmi, SK, SPO, Panduan, dan Program Kerja secara otomatis.
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `generate_nomor_dok` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nomor_urut` INT UNSIGNED NOT NULL COMMENT 'Nomor urut dokumen per jenis & tahun',
    `kode_instansi` VARCHAR(20) NOT NULL DEFAULT 'RSA' COMMENT 'Kode singkatan RS (RSA)',
    `jenis_kode` VARCHAR(10) NOT NULL COMMENT 'Kode jenis dokumen: SK, SPO, PED, PAN, PRO, PROG, KER',
    `jenis_nama` VARCHAR(80) NOT NULL COMMENT 'Nama jenis dokumen lengkap',
    `pokja_kode` VARCHAR(40) NOT NULL COMMENT 'Kode Pokja Akreditasi misal: TKRS, KPS, PMKP, MFK, dll',
    `pokja_nama` VARCHAR(150) NOT NULL COMMENT 'Nama Pokja Akreditasi lengkap',
    `keterangan` TEXT NOT NULL COMMENT 'Judul / perihal dokumen yang digenerate',
    `bulan` TINYINT UNSIGNED NOT NULL COMMENT 'Bulan terbit dokumen (1-12)',
    `bulan_romawi` VARCHAR(8) NOT NULL COMMENT 'Bulan terbit dalam angka Romawi (I-XII)',
    `tahun` SMALLINT UNSIGNED NOT NULL COMMENT 'Tahun terbit dokumen',
    `nomor_dok` VARCHAR(80) NOT NULL COMMENT 'Format nomor lengkap: NOMOR/JENIS/POKJA/RSA/BULAN_ROMAWI/TAHUN',
    `created_by` VARCHAR(100) DEFAULT NULL COMMENT 'Nama pengguna / staf pembuat nomor dokumen',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_nomor_dok` (`nomor_dok`),
    KEY `idx_dok_tahun` (`tahun`),
    KEY `idx_dok_pokja_jenis` (`pokja_kode`, `jenis_kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Riwayat Penomoran Dokumen Akreditasi RS Assalam';

