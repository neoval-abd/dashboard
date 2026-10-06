<?php
require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/config/kendali_biaya.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function plafon_response($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    plafon_response(401, ['success' => false, 'message' => 'Silakan login kembali.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    plafon_response(405, ['success' => false, 'message' => 'Gunakan metode POST.']);
}
$token = $_POST['csrf'] ?? '';
if (!is_string($token) || empty($_SESSION['plafon_csrf']) || !hash_equals($_SESSION['plafon_csrf'], $token)) {
    plafon_response(403, ['success' => false, 'message' => 'Sesi formulir tidak valid. Muat ulang halaman.']);
}
$no_rawat = $_POST['no_rawat'] ?? '';
$nominal = $_POST['nominal'] ?? '';
if (!is_string($no_rawat) || !preg_match('/^\d{4}\/\d{2}\/\d{2}\/\d{6}$/D', $no_rawat)
    || !is_string($nominal) || ($nominal !== '' && !preg_match('/^\d{1,15}$/D', $nominal))) {
    plafon_response(422, ['success' => false, 'message' => 'Isi plafon dengan nominal rupiah bulat, minimal 0 dan maksimal 15 digit.']);
}
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $stmt = $koneksi->prepare('SELECT no_rawat FROM reg_periksa WHERE no_rawat = ? AND status_lanjut = ? LIMIT 1');
    $status_lanjut = 'Ranap';
    $stmt->bind_param('ss', $no_rawat, $status_lanjut);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$exists) plafon_response(404, ['success' => false, 'message' => 'Nomor rawat inap tidak ditemukan.']);

    if ($nominal === '') {
        $stmt = $koneksi->prepare('DELETE FROM ' . kendali_biaya_table() . ' WHERE no_rawat = ?');
        $stmt->bind_param('s', $no_rawat);
    } else {
        $stmt = $koneksi->prepare('INSERT INTO ' . kendali_biaya_table() . ' (no_rawat, nominal, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nominal = VALUES(nominal), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
        $user_id = (string) $_SESSION['user_id'];
        $stmt->bind_param('sss', $no_rawat, $nominal, $user_id);
    }
    $stmt->execute();
    $stmt->close();
    plafon_response(200, ['success' => true, 'no_rawat' => $no_rawat, 'plafon_raw' => $nominal === '' ? null : (float) $nominal, 'has_plafon' => $nominal !== '', 'message' => 'Plafon tersimpan.']);
} catch (Throwable $error) {
    error_log('[Kendali Biaya] ' . $error->getMessage());
    plafon_response(500, ['success' => false, 'message' => 'Plafon gagal disimpan. Silakan coba kembali atau hubungi administrator.']);
}
