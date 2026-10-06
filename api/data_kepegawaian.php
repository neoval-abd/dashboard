<?php
require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/includes/kepegawaian.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan login kembali.']);
    exit;
}
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    if (!is_string($action)) throw new InvalidArgumentException('Permintaan tidak valid.');
    if ($action === 'save' || $action === 'save_index') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            throw new InvalidArgumentException('Gunakan metode POST.');
        }
        if (!kp_can_write($koneksi)) {
            http_response_code(403);
            throw new InvalidArgumentException('Anda tidak memiliki izin mengelola pegawai.');
        }
        $token = $_POST['csrf'] ?? '';
        if (!is_string($token) || empty($_SESSION['kp_csrf']) || !hash_equals($_SESSION['kp_csrf'], $token)) {
            http_response_code(403);
            throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.');
        }
        if ($action === 'save') $id = kp_save_employee($koneksi, $_POST, kp_options($koneksi));
        else { kp_save_index($koneksi, $_POST); $id = (int) $_POST['id']; }
        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Data berhasil disimpan.']);
    } elseif ($action === 'list') {
        foreach (['status', 'keyword', 'departemen', 'tanggal'] as $name) {
            if (isset($_GET[$name]) && !is_string($_GET[$name])) throw new InvalidArgumentException('Filter tidak valid.');
        }
        $date = $_GET['tanggal'] ?? kp_reference_date($koneksi);
        $rows = kp_employee_list($koneksi, $_GET['status'] ?? 'AKTIF', substr($_GET['keyword'] ?? '', 0, 150), $_GET['departemen'] ?? '', $date);
        $total_pegawai = (int) kp_rows($koneksi, 'SELECT COUNT(*) AS total FROM pegawai')[0]['total'];
        // Lokasi file foto tidak diperlukan untuk tahap ini.
        foreach ($rows as &$row) unset($row['photo']);
        unset($row);
        echo json_encode(['success' => true, 'rows' => $rows, 'tanggal' => $date, 'total_pegawai' => $total_pegawai], JSON_INVALID_UTF8_SUBSTITUTE);
    } else throw new InvalidArgumentException('Permintaan tidak dikenal.');
} catch (InvalidArgumentException $error) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('[Kepegawaian] ' . $error->getMessage());
    http_response_code(500);
    $duplicate = (int) $error->getCode() === 1062;
    echo json_encode(['success' => false, 'message' => $duplicate ? 'NIP / NIK pegawai sudah digunakan.' : 'Data gagal diproses. Silakan hubungi administrator.']);
}
