<?php
/*
 * File: api/ucapan_ulang_tahun.php
 * Fungsi: Track status pengiriman ucapan ulang tahun
 *         GET  = ambil daftar RM yang sudah dikirim tahun ini
 *         POST = tandai RM sebagai sudah dikirim
 */
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');
require_once(__DIR__ . '/fonnte_client.php');
require_once(__DIR__ . '/reminder_queue_helpers.php');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $error['message'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

$tahun = (int) date('Y');

// GET: return list of no_rkm_medis already sent this year
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ensure_birthday_queue_table($koneksi);

    $sent = [];
    $sql = "SELECT no_rkm_medis, tgl_kirim, pengirim 
            FROM ucapan_ulang_tahun 
            WHERE tahun = $tahun";
    $res = $koneksi->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sent[] = $row;
        }
    }
    echo json_encode(['sent' => $sent], JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: mark as sent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rm = trim($_POST['no_rkm_medis'] ?? '');
    $pengirim = trim($_POST['pengirim'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $nama_pasien = trim($_POST['nama_pasien'] ?? '');
    
    if (empty($rm)) {
        echo json_encode(['success' => false, 'error' => 'no_rkm_medis required']);
        exit;
    }

    if ($phone === '' || $message === '') {
        echo json_encode(['success' => false, 'error' => 'Nomor HP dan pesan wajib diisi']);
        exit;
    }

    if (!ensure_birthday_queue_table($koneksi)) {
        echo json_encode(['success' => false, 'error' => 'Tabel antrean ulang tahun gagal dibuat: ' . $koneksi->error]);
        exit;
    }

    $scheduledAt = date('Y-m-d H:i:s');
    $queueSql = "INSERT INTO antrean_ucapan_ulang_tahun
                    (no_rkm_medis, tahun, nama_pasien, phone, message, pengirim, status, attempts, scheduled_at, last_error)
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, NULL)
                 ON DUPLICATE KEY UPDATE
                    nama_pasien = VALUES(nama_pasien),
                    phone = VALUES(phone),
                    message = VALUES(message),
                    pengirim = VALUES(pengirim),
                    status = IF(antrean_ucapan_ulang_tahun.status = 'sent', antrean_ucapan_ulang_tahun.status, 'pending'),
                    scheduled_at = IF(antrean_ucapan_ulang_tahun.status = 'sent', antrean_ucapan_ulang_tahun.scheduled_at, VALUES(scheduled_at)),
                    last_error = IF(antrean_ucapan_ulang_tahun.status = 'sent', antrean_ucapan_ulang_tahun.last_error, NULL)";

    if ($queueStmt = $koneksi->prepare($queueSql)) {
        $queueStmt->bind_param("sisssss", $rm, $tahun, $nama_pasien, $phone, $message, $pengirim, $scheduledAt);
        $queueOk = $queueStmt->execute();
        $queueError = $queueStmt->error;
        $queueStmt->close();
        if (!$queueOk) {
            echo json_encode(['success' => false, 'error' => $queueError], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => $koneksi->error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $sql = "INSERT INTO ucapan_ulang_tahun (no_rkm_medis, tahun, tgl_kirim, pengirim)
            VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE tgl_kirim = NOW(), pengirim = VALUES(pengirim)";
    
    if ($stmt = $koneksi->prepare($sql)) {
        $stmt->bind_param("sis", $rm, $tahun, $pengirim);
        $ok = $stmt->execute();
        $error = $stmt->error;
        $stmt->close();
        echo json_encode($ok ? [
            'success' => true,
            'message' => 'Ucapan diterima sistem dan akan dikirim oleh service.',
        ] : ['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => $koneksi->error]);
    }
    exit;
}

echo json_encode(['error' => 'Method not allowed']);
