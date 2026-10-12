<?php
/**
 * Khanza Berkas Bridge Receiver
 * Lokasi file di server Linux Mint:
 * /var/www/html/khanzaapps/penggajian/pages/berkaspegawai/upload_receiver.php
 *
 * Fungsi:
 * Menerima file upload berkas pegawai dari Dashboard Assalam (baik saat diakses dari localhost
 * maupun dari server dashboard) dan menyimpannya langsung ke folder berkas di Linux Mint.
 */

// Kunci keamanan (harus cocok dengan KP_BRIDGE_KEY di Dashboard Assalam)
const BRIDGE_KEY = 'assalam_berkas_secret_key_2026';

header('Content-Type: application/json; charset=utf-8');

// Verifikasi token
$token = $_POST['token'] ?? $_GET['token'] ?? '';
if (!hash_equals(BRIDGE_KEY, (string) $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized bridge token']);
    exit;
}

$action = $_POST['action'] ?? 'upload';
$storageDir = __DIR__ . '/berkas';

if (!is_dir($storageDir)) {
    if (!@mkdir($storageDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Folder berkas tidak dapat dibuat di server']);
        exit;
    }
}

if ($action === 'upload') {
    if (empty($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan dalam permintaan']);
        exit;
    }

    $targetName = basename($_POST['filename'] ?? $_FILES['file']['name']);
    if ($targetName === '' || $targetName === '.' || $targetName === '..' || strpbrk($targetName, "\\/:*?\"<>|\0") !== false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama file tidak valid']);
        exit;
    }

    $targetPath = $storageDir . '/' . $targetName;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
        @chmod($targetPath, 0664);
        echo json_encode(['success' => true, 'filename' => $targetName]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file ke folder berkas Linux Mint']);
    }
} elseif ($action === 'delete') {
    $targetName = basename($_POST['filename'] ?? '');
    if ($targetName === '' || $targetName === '.' || $targetName === '..' || strpbrk($targetName, "\\/:*?\"<>|\0") !== false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nama file tidak valid']);
        exit;
    }
    $targetPath = $storageDir . '/' . $targetName;
    $deleted = is_file($targetPath) ? @unlink($targetPath) : false;
    echo json_encode(['success' => true, 'deleted' => $deleted]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal']);
}
