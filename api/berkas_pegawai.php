<?php
require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/includes/berkas_pegawai.php';
$action = $_POST['action'] ?? $_GET['action'] ?? 'summary';
if (!is_string($action)) $action = '';
$kpb_raw = $action === 'file';

function kpb_respond($code, $payload, $raw)
{
    http_response_code($code);
    if ($raw) { header('Content-Type: text/plain; charset=utf-8'); echo $payload['message'] ?? ''; return; }
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
}

if (!$kpb_raw) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
}
if (empty($_SESSION['user_id'])) {
    kpb_respond(401, ['success' => false, 'message' => 'Silakan login kembali.'], $kpb_raw);
    exit;
}
try {
    if (!kp_can_access($koneksi)) {
        http_response_code(403);
        throw new InvalidArgumentException('Anda tidak memiliki hak akses admin pegawai.');
    }
    if ($action === 'upload' || $action === 'delete' || $action === 'save_sip') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); throw new InvalidArgumentException('Gunakan metode POST.'); }
        // Upload yang melebihi post_max_size membuat $_POST kosong.
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) throw new InvalidArgumentException('Ukuran unggahan terlalu besar.');
        $token = $_POST['csrf'] ?? '';
        if (!is_string($token) || empty($_SESSION['kp_csrf']) || !hash_equals($_SESSION['kp_csrf'], $token)) { http_response_code(403); throw new InvalidArgumentException('Sesi formulir tidak valid. Muat ulang halaman.'); }
        if (!kp_can_write($koneksi)) { http_response_code(403); throw new InvalidArgumentException('Anda tidak memiliki hak kelola berkas pegawai.'); }
        session_write_close();
        if ($action === 'upload') {
            $sipMeta = [
                'no_sip'         => $_POST['no_sip'] ?? null,
                'tgl_terbit'     => $_POST['tgl_terbit'] ?? null,
                'tgl_kadaluarsa' => $_POST['tgl_kadaluarsa'] ?? null,
            ];
            $count = kpb_upload($koneksi, $_POST['nik'] ?? null, $_POST['kode'] ?? null, $_POST['tgl'] ?? null, $_FILES['files'] ?? null, $sipMeta);
            kpb_respond(200, ['success' => true, 'message' => $count . ' file berhasil diunggah.'], false);
        } elseif ($action === 'save_sip') {
            kpb_save_sip_meta($koneksi, $_POST['nik'] ?? null, $_POST['kode'] ?? null, $_POST['no_sip'] ?? null, $_POST['tgl_terbit'] ?? null, $_POST['tgl_kadaluarsa'] ?? null);
            kpb_respond(200, ['success' => true, 'message' => 'Data masa berlaku SIP berhasil disimpan.'], false);
        } else {
            $removed = kpb_delete($koneksi, $_POST['nik'] ?? null, $_POST['kode'] ?? null, $_POST['berkas'] ?? null);
            kpb_respond(200, ['success' => true, 'message' => 'Berkas berhasil dihapus dari sistem.'], false);
        }
    } elseif ($action === 'summary') {
        session_write_close();
        $sumResult = kpb_summary($koneksi);
        kpb_respond(200, [
            'success'   => true,
            'rows'      => $sumResult['rows'],
            'sip_stats' => $sumResult['sip_stats'],
            'kategori'  => kpb_categories()
        ], false);
    } elseif ($action === 'detail') {
        session_write_close();
        $category = $_GET['kategori'] ?? '';
        if (!is_string($category)) throw new InvalidArgumentException('Kategori berkas tidak valid.');
        kpb_respond(200, ['success' => true] + kpb_detail($koneksi, $_GET['nik'] ?? null, $category), false);
    } elseif ($action === 'file') {
        session_write_close();
        if (!kpb_stream($koneksi, $_GET['nik'] ?? null, $_GET['kode'] ?? null, $_GET['berkas'] ?? null, !empty($_GET['download']))) {
            kpb_respond(404, ['message' => 'File berkas tidak ditemukan di server.'], true);
        }
    } else {
        throw new InvalidArgumentException('Permintaan tidak dikenal.');
    }
} catch (InvalidArgumentException $error) {
    kpb_respond(http_response_code() >= 400 ? http_response_code() : 422, ['success' => false, 'message' => $error->getMessage()], $kpb_raw);
} catch (KpbStorageException $error) {
    error_log('[Berkas Pegawai] ' . $error->getMessage() . ' KP_BERKAS_ROOT=' . KP_BERKAS_ROOT);
    kpb_respond(503, ['success' => false, 'message' => $error->getMessage() . ' Periksa KP_BERKAS_ROOT di config/berkas_pegawai.php.'], $kpb_raw);
} catch (Throwable $error) {
    error_log('[Berkas Pegawai] ' . $error->getMessage());
    kpb_respond(500, ['success' => false, 'message' => 'Berkas gagal diproses. Silakan hubungi administrator.'], $kpb_raw);
}
