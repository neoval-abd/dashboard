<?php
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

function get_fonnte_queue_delay()
{
    return defined('FONNTE_QUEUE_DELAY_SECONDS') ? max(60, (int) FONNTE_QUEUE_DELAY_SECONDS) : 600;
}

function reminder_queue_max_attempts()
{
    return defined('FONNTE_QUEUE_MAX_ATTEMPTS') ? max(1, (int) FONNTE_QUEUE_MAX_ATTEMPTS) : 2;
}
?>
