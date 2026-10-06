CREATE DATABASE IF NOT EXISTS `kendali_biaya`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kendali_biaya`.`plafon_ranap` (
    `no_rawat` VARCHAR(30) NOT NULL,
    `nominal` DECIMAL(15,0) NOT NULL,
    `updated_by` VARCHAR(100) NOT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`no_rawat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
