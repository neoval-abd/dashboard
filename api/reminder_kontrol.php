<?php
/**
 * API endpoint: simpan log pengiriman reminder kontrol.
 * Sesuaikan path require di bawah dengan koneksi DB yang dipakai
 * oleh api/ucapan_ulang_tahun.php agar konsisten dengan struktur project Anda.
 */
header('Content-Type: application/json');
require_once('../includes/koneksi.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_sep      = trim($_POST['no_sep'] ?? '');
    $nomr        = trim($_POST['nomr'] ?? '');
    $nama_pasien = trim($_POST['nama_pasien'] ?? '');
    $pengirim    = trim($_POST['pengirim'] ?? '');

    if (empty($no_sep)) {
        echo json_encode(['success' => false, 'message' => 'no_sep wajib diisi']);
        exit;
    }

    $sql = "INSERT INTO log_kirim_reminder_kontrol (no_sep, nomr, nama_pasien, tgl_kirim, pengirim)
            VALUES (?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE tgl_kirim = NOW(), pengirim = VALUES(pengirim)";

    if ($stmt = $koneksi->prepare($sql)) {
        $stmt->bind_param("ssss", $no_sep, $nomr, $nama_pasien, $pengirim);
        $ok = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => (bool) $ok]);
    } else {
        echo json_encode(['success' => false, 'message' => $koneksi->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
