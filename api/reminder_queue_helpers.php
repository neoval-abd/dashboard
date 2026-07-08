<?php
function ensure_reminder_queue_table($koneksi)
{
    $sql = "CREATE TABLE IF NOT EXISTS antrean_reminder_kontrol (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_sep VARCHAR(40) NOT NULL,
        nomr VARCHAR(40) DEFAULT '',
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
        UNIQUE KEY uniq_no_sep (no_sep),
        KEY idx_status_schedule (status, scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $koneksi->query($sql);
}

function get_fonnte_queue_delay()
{
    return defined('FONNTE_QUEUE_DELAY_SECONDS') ? max(60, (int) FONNTE_QUEUE_DELAY_SECONDS) : 600;
}

function get_next_reminder_schedule($koneksi)
{
    $delay = get_fonnte_queue_delay();
    $base = time();

    $sql = "SELECT MAX(scheduled_at) AS scheduled_at
            FROM antrean_reminder_kontrol
            WHERE status IN ('pending', 'processing')
              AND scheduled_at > NOW()";
    $res = $koneksi->query($sql);
    if ($res && ($row = $res->fetch_assoc()) && !empty($row['scheduled_at'])) {
        $base = max($base, strtotime($row['scheduled_at']));
    }

    return date('Y-m-d H:i:s', $base + $delay);
}

function reminder_queue_max_attempts()
{
    return defined('FONNTE_QUEUE_MAX_ATTEMPTS') ? max(1, (int) FONNTE_QUEUE_MAX_ATTEMPTS) : 2;
}
?>
