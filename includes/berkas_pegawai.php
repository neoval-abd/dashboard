<?php
// Berkas kepegawaian, kompatibel dengan penggajian/pages/berkaspegawai/detail.php Khanza.
// master_berkas_pegawai (kode, kategori, nama_berkas, no_urut) dan berkas_pegawai (nik, tgl_uploud, kode_berkas, berkas).
// Kolom berkas berisi path relatif folder penggajian, contoh: pages/berkaspegawai/berkas/xxx.jpg
require_once __DIR__ . '/kepegawaian.php';
if (is_file(dirname(__DIR__) . '/config/berkas_pegawai.php')) require_once dirname(__DIR__) . '/config/berkas_pegawai.php';
if (!defined('KP_BERKAS_ROOT')) define('KP_BERKAS_ROOT', dirname(__DIR__, 2) . '/khanzaapps/penggajian');
const KPB_DIR = 'pages/berkaspegawai/berkas/';
const KPB_MAX_SIZE = 10485760; // 10 MB per file
const KPB_MAX_FILES = 10;

class KpbStorageException extends RuntimeException {}

function kpb_categories()
{
    return [
        'Tenaga klinis Dokter Umum' => 'Dokter Umum',
        'Tenaga klinis Dokter Spesialis' => 'Dokter Spesialis',
        'Tenaga klinis Perawat dan Bidan' => 'Perawat & Bidan',
        'Tenaga klinis Profesi Lain' => 'Profesi Lain',
        'Tenaga Non Klinis' => 'Non Klinis',
    ];
}

// Prefix kode master Khanza sudah mengelompokkan berkas (LMN0011, IDN0029, dst.).
function kpb_groups()
{
    return [
        'LMN' => ['Lamaran & Rekrutmen', 'fa-envelope-open-text'],
        'IDN' => ['Identitas', 'fa-id-badge'],
        'DOK' => ['Pendidikan & Legalitas', 'fa-graduation-cap'],
        'TES' => ['Seleksi', 'fa-clipboard-check'],
        'SPK' => ['Kontrak & SK', 'fa-file-signature'],
        'UTK' => ['Uraian Tugas & Kewenangan', 'fa-tasks'],
        'EVA' => ['Evaluasi', 'fa-chart-line'],
        'SRT' => ['Surat', 'fa-envelope'],
        'STF' => ['Sertifikat Pelatihan', 'fa-certificate'],
    ];
}

function kpb_is_other($name)
{
    return strcasecmp(trim((string) $name), 'Dll') === 0;
}

function kpb_text($value, $label, $max)
{
    if (!is_string($value)) throw new InvalidArgumentException($label . ' tidak valid.');
    $value = trim($value);
    if ($value === '' || strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/', $value)) throw new InvalidArgumentException($label . ' tidak valid.');
    return $value;
}

function kpb_valid_category($category)
{
    if (!is_string($category) || !array_key_exists($category, kpb_categories())) throw new InvalidArgumentException('Kategori berkas tidak valid.');
    return $category;
}

function kpb_find_employee($db, $nik)
{
    $nik = kpb_text($nik, 'NIP pegawai', 20);
    $rows = kp_rows($db, "SELECT p.nik, p.nama, p.jk, p.jbtn, p.bidang, p.pendidikan, p.stts_aktif, p.stts_kerja, p.mulai_kerja,
        d.nama AS departemen_nama, j.nama AS jenjang, dk.nm_dokter
        FROM pegawai p
        LEFT JOIN departemen d ON d.dep_id = p.departemen
        LEFT JOIN jnj_jabatan j ON j.kode = p.jnj_jabatan
        LEFT JOIN dokter dk ON dk.kd_dokter = p.nik
        WHERE p.nik = ?", [$nik]);
    if (!$rows) throw new InvalidArgumentException('Pegawai tidak ditemukan.');
    return $rows[0];
}

// Perkiraan kategori untuk pegawai yang belum punya berkas sama sekali. Tetap bisa diganti user.
function kpb_guess_category($row)
{
    $text = strtolower(($row['jbtn'] ?? '') . ' ' . ($row['bidang'] ?? '') . ' ' . ($row['pendidikan'] ?? ''));
    if (!empty($row['nm_dokter']) || preg_match('/\bdokter\b|\bdr\./', $text)) {
        return preg_match('/sp\.|spesialis/i', ($row['nm_dokter'] ?? '') . ' ' . ($row['nama'] ?? '') . ' ' . $text) ? 'Tenaga klinis Dokter Spesialis' : 'Tenaga klinis Dokter Umum';
    }
    if (preg_match('/perawat|bidan|ners|keperawatan|kebidanan/', $text)) return 'Tenaga klinis Perawat dan Bidan';
    if (preg_match('/apoteker|farmasi|analis|laborat|radiogra|radiologi|gizi|fisioterap|rekam|rmik|perekam/', $text)) return 'Tenaga klinis Profesi Lain';
    return 'Tenaga Non Klinis';
}

// Kategori utama = kategori dengan berkas terbanyak; tanpa berkas memakai perkiraan.
function kpb_pick_category($row, $perCategory)
{
    if ($perCategory) {
        uasort($perCategory, function ($a, $b) { return $b['files'] <=> $a['files']; });
        return [array_key_first($perCategory), 'berkas'];
    }
    return [kpb_guess_category($row), 'perkiraan'];
}

function kpb_master_totals($db)
{
    $totals = [];
    foreach (kp_rows($db, "SELECT kategori, COUNT(*) AS total, SUM(nama_berkas <> 'Dll') AS wajib FROM master_berkas_pegawai GROUP BY kategori") as $row) {
        $totals[$row['kategori']] = (int) $row['wajib'];
    }
    return $totals;
}

function kpb_summary($db)
{
    $totals = kpb_master_totals($db);
    $stats = [];
    foreach (kp_rows($db, "SELECT b.nik, m.kategori, COUNT(*) AS files,
            COUNT(DISTINCT CASE WHEN m.nama_berkas <> 'Dll' THEN b.kode_berkas END) AS filled,
            MAX(b.tgl_uploud) AS last_upload
        FROM berkas_pegawai b JOIN master_berkas_pegawai m ON m.kode = b.kode_berkas
        GROUP BY b.nik, m.kategori") as $row) {
        $stats[$row['nik']][$row['kategori']] = ['files' => (int) $row['files'], 'filled' => (int) $row['filled'], 'last' => $row['last_upload']];
    }
    // Sama dengan Khanza: pegawai berstatus KELUAR tidak ditampilkan.
    $employees = kp_rows($db, "SELECT p.nik, p.nama, p.jbtn, p.bidang, p.pendidikan, p.stts_aktif, d.nama AS departemen_nama, dk.nm_dokter
        FROM pegawai p
        LEFT JOIN departemen d ON d.dep_id = p.departemen
        LEFT JOIN dokter dk ON dk.kd_dokter = p.nik
        WHERE p.stts_aktif <> 'KELUAR' ORDER BY p.nama");
    $rows = [];
    foreach ($employees as $employee) {
        $per = $stats[$employee['nik']] ?? [];
        list($category, $source) = kpb_pick_category($employee, $per);
        $last = '';
        foreach ($per as $item) if ($item['last'] > $last) $last = $item['last'];
        $rows[] = [
            'nik' => $employee['nik'], 'nama' => $employee['nama'], 'jbtn' => $employee['jbtn'],
            'departemen' => $employee['departemen_nama'], 'stts_aktif' => $employee['stts_aktif'],
            'kategori' => $category, 'sumber' => $source,
            'kategori_berkas' => array_map(function ($item) { return $item['files']; }, $per),
            'files' => array_sum(array_column($per, 'files')),
            'filled' => $per[$category]['filled'] ?? 0,
            'wajib' => $totals[$category] ?? 0,
            'last_upload' => $last,
        ];
    }
    return $rows;
}

function kpb_storage_dir($create = false)
{
    $root = realpath(KP_BERKAS_ROOT);
    if ($root === false || !is_dir($root)) {
        if (is_dir(KP_BERKAS_ROOT)) {
            $root = rtrim(KP_BERKAS_ROOT, '/\\');
        } else {
            throw new KpbStorageException('Folder webapps penggajian Khanza tidak ditemukan (' . KP_BERKAS_ROOT . ').');
        }
    }
    $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim(KPB_DIR, '/'));
    if (!is_dir($dir) && (!$create || !@mkdir($dir, 0775, true))) throw new KpbStorageException('Folder berkas pegawai Khanza tidak ditemukan (' . $dir . ').');
    return $dir;
}

// Hanya path di dalam folder berkas Khanza yang boleh dibaca/dihapus.
function kpb_file_path($berkas)
{
    if (!is_string($berkas)) return null;
    $berkas = str_replace('\\', '/', trim($berkas));
    if (strpos($berkas, KPB_DIR) !== 0) return null;
    $name = substr($berkas, strlen(KPB_DIR));
    if ($name === '' || $name === '.' || $name === '..' || $name !== basename($name) || strpbrk($name, "\\/:*?\"<>|\0") !== false) return null;
    try { $dir = kpb_storage_dir(); } catch (KpbStorageException $error) { return null; }
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    return is_file($path) ? $path : null;
}

function kpb_file_info($row)
{
    $name = substr($row['berkas'], strrpos($row['berkas'], '/') !== false ? strrpos($row['berkas'], '/') + 1 : 0);
    $localFile = kpb_file_path($row['berkas']);
    $hasFile = ($localFile !== null) || (defined('KP_BERKAS_URL') && !empty(KP_BERKAS_URL));
    return [
        'tgl' => $row['tgl_uploud'], 'berkas' => $row['berkas'], 'nama_file' => $name,
        'ext' => strtolower(pathinfo($name, PATHINFO_EXTENSION)), 'ada' => $hasFile,
    ];
}

function kpb_detail($db, $nik, $category = '')
{
    $employee = kpb_find_employee($db, $nik);
    $files = kp_rows($db, "SELECT b.tgl_uploud, b.kode_berkas, b.berkas, m.kategori
        FROM berkas_pegawai b JOIN master_berkas_pegawai m ON m.kode = b.kode_berkas
        WHERE b.nik = ? ORDER BY b.tgl_uploud, b.berkas", [$employee['nik']]);
    $per = [];
    foreach ($files as $file) $per[$file['kategori']]['files'] = ($per[$file['kategori']]['files'] ?? 0) + 1;
    if ($category === '' || $category === null) list($category, $source) = kpb_pick_category($employee, $per);
    else { kpb_valid_category($category); $source = 'pilihan'; }
    $byCode = [];
    foreach ($files as $file) if ($file['kategori'] === $category) $byCode[$file['kode_berkas']][] = kpb_file_info($file);
    $groups = [];
    $known = kpb_groups();
    foreach (kp_rows($db, 'SELECT kode, nama_berkas FROM master_berkas_pegawai WHERE kategori = ? ORDER BY no_urut, kode', [$category]) as $master) {
        $prefix = strtoupper(substr($master['kode'], 0, 3));
        if (!isset($known[$prefix])) $prefix = 'LAIN';
        if (!isset($groups[$prefix])) $groups[$prefix] = ['kode' => $prefix, 'label' => $known[$prefix][0] ?? 'Lainnya', 'icon' => $known[$prefix][1] ?? 'fa-folder', 'items' => []];
        $groups[$prefix]['items'][] = ['kode' => $master['kode'], 'nama' => $master['nama_berkas'], 'lainnya' => kpb_is_other($master['nama_berkas']), 'files' => $byCode[$master['kode']] ?? []];
    }
    $others = [];
    foreach ($per as $name => $item) if ($name !== $category) $others[$name] = $item['files'];
    $storage = true;
    try { 
        kpb_storage_dir(true); 
    } catch (KpbStorageException $error) { 
        // Jika folder fisik lokal tidak ada di server ini, cek apakah remote host URL aktif
        $storage = (defined('KP_BERKAS_URL') && !empty(KP_BERKAS_URL));
    }
    $employee['dokter'] = !empty($employee['nm_dokter']);
    unset($employee['nm_dokter']);
    return ['pegawai' => $employee, 'kategori' => $category, 'sumber' => $source, 'groups' => array_values($groups), 'kategori_lain' => $others, 'storage' => $storage];
}

function kpb_normalize_files($input)
{
    if (!is_array($input) || !isset($input['name'])) return [];
    if (!is_array($input['name'])) return [$input];
    $files = [];
    foreach (array_keys($input['name']) as $i) {
        if (!is_int($i)) continue;
        $files[] = ['name' => $input['name'][$i], 'type' => $input['type'][$i] ?? '', 'tmp_name' => $input['tmp_name'][$i] ?? '', 'error' => $input['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $input['size'][$i] ?? 0];
    }
    return $files;
}

function kpb_upload_error($code, $name)
{
    $messages = [UPLOAD_ERR_INI_SIZE => 'melebihi batas ukuran server', UPLOAD_ERR_FORM_SIZE => 'melebihi batas ukuran', UPLOAD_ERR_PARTIAL => 'hanya terunggah sebagian', UPLOAD_ERR_NO_FILE => 'tidak dipilih'];
    return 'File ' . $name . ' ' . ($messages[$code] ?? 'gagal diunggah') . '.';
}

function kpb_mime($path)
{
    if (class_exists('finfo')) return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    return function_exists('mime_content_type') ? (string) mime_content_type($path) : '';
}

function kpb_safe_name($value)
{
    return trim(preg_replace('/[^A-Za-z0-9.-]+/', '_', $value), '._') ?: 'x';
}

function kpb_upload($db, $nik, $code, $date, $input)
{
    $employee = kpb_find_employee($db, $nik);
    $code = kpb_text($code, 'Jenis berkas', 10);
    if (!kp_rows($db, 'SELECT kode FROM master_berkas_pegawai WHERE kode = ?', [$code])) throw new InvalidArgumentException('Jenis berkas tidak ditemukan.');
    if (!is_string($date) || !kp_valid_date($date)) throw new InvalidArgumentException('Tanggal upload tidak valid.');
    $files = kpb_normalize_files($input);
    if (!$files) throw new InvalidArgumentException('Pilih file yang akan diunggah.');
    if (count($files) > KPB_MAX_FILES) throw new InvalidArgumentException('Maksimal ' . KPB_MAX_FILES . ' file sekali unggah.');
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];
    $prepared = [];
    // Validasi seluruh file lebih dulu agar tidak ada unggahan setengah jadi.
    foreach ($files as $file) {
        $label = is_string($file['name']) ? basename($file['name']) : 'file';
        if ((int) $file['error'] !== UPLOAD_ERR_OK) throw new InvalidArgumentException(kpb_upload_error((int) $file['error'], $label));
        if ((int) $file['size'] <= 0 || (int) $file['size'] > KPB_MAX_SIZE) throw new InvalidArgumentException('Ukuran file ' . $label . ' maksimal 10 MB.');
        if (!is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('File ' . $label . ' tidak valid.');
        $ext = strtolower(pathinfo($label, PATHINFO_EXTENSION));
        $mime = kpb_mime($file['tmp_name']);
        if (!isset($allowed[$ext]) || $allowed[$ext] !== $mime) throw new InvalidArgumentException('File ' . $label . ' harus berformat JPG, PNG, atau PDF.');
        if ($mime !== 'application/pdf' && @getimagesize($file['tmp_name']) === false) throw new InvalidArgumentException('Gambar ' . $label . ' rusak atau tidak dapat dibaca.');
        $prepared[] = [$file['tmp_name'], $ext === 'jpeg' ? 'jpg' : $ext];
    }
    $dir = null;
    $useRemoteBridge = false;
    try {
        $dir = kpb_storage_dir(true);
        if (!is_writable($dir)) $dir = null;
    } catch (Throwable $e) {
        $dir = null;
    }

    if (!$dir) {
        if (defined('KP_BRIDGE_URL') && !empty(KP_BRIDGE_URL)) {
            $useRemoteBridge = true;
        } else {
            throw new KpbStorageException('Folder berkas pegawai Khanza tidak dapat ditulis dan URL bridge belum terkonfigurasi.');
        }
    }

    $saved = [];
    $savedRemote = [];
    if (!$db->begin_transaction()) throw new RuntimeException('Transaksi tidak dapat dimulai.');
    try {
        foreach ($prepared as $item) {
            // Nama unik: Khanza memakai nama asli sehingga file bernama sama saling menimpa.
            $name = kpb_safe_name($employee['nik']) . '_' . kpb_safe_name($code) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $item[1];

            if ($useRemoteBridge) {
                // Unggah via cURL multipart bridge ke server Linux Mint (192.168.5.52:8820)
                if (!kpb_upload_remote($item[0], $name)) {
                    throw new KpbStorageException('Gagal mengirim file ke server Linux Mint (192.168.5.52:8820). Pastikan upload_receiver.php sudah dipasang di /var/www/html/khanzaapps/penggajian/pages/berkaspegawai/upload_receiver.php');
                }
                $savedRemote[] = $name;
            } else {
                $target = $dir . DIRECTORY_SEPARATOR . $name;
                if (!move_uploaded_file($item[0], $target)) throw new KpbStorageException('File gagal disimpan ke folder berkas Khanza.');
                $saved[] = $target;
            }

            kp_query($db, 'INSERT INTO berkas_pegawai (nik, tgl_uploud, kode_berkas, berkas) VALUES (?, ?, ?, ?)', [$employee['nik'], $date, $code, KPB_DIR . $name])->close();
        }
        if (!$db->commit()) throw new RuntimeException('Commit gagal.');
    } catch (Throwable $error) {
        $db->rollback();
        foreach ($saved as $path) @unlink($path);
        foreach ($savedRemote as $rName) kpb_delete_remote($rName);
        throw $error;
    }
    return count($prepared);
}

function kpb_upload_remote($tmpFile, $filename)
{
    $url = defined('KP_BRIDGE_URL') ? KP_BRIDGE_URL : '';
    $token = defined('KP_BRIDGE_KEY') ? KP_BRIDGE_KEY : '';
    if (!$url || !$token) return false;

    $cfile = new CURLFile($tmpFile, kpb_mime($tmpFile) ?: 'application/octet-stream', $filename);
    $postData = [
        'token'    => $token,
        'action'   => 'upload',
        'filename' => $filename,
        'file'     => $cfile,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $json = json_decode($response, true);
        return !empty($json['success']);
    }

    error_log("[Berkas Bridge] Upload error ke $url: HTTP $httpCode " . ($err ?: $response));
    return false;
}

function kpb_delete_remote($filename)
{
    $url = defined('KP_BRIDGE_URL') ? KP_BRIDGE_URL : '';
    $token = defined('KP_BRIDGE_KEY') ? KP_BRIDGE_KEY : '';
    if (!$url || !$token) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'token'    => $token,
            'action'   => 'delete',
            'filename' => $filename,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return true;
}

function kpb_delete($db, $nik, $code, $berkas)
{
    $nik = kpb_text($nik, 'NIP pegawai', 20);
    $code = kpb_text($code, 'Jenis berkas', 10);
    $berkas = kpb_text($berkas, 'Berkas', 500);
    // Khanza menghapus semua file dengan nik+kode yang sama; dashboard hanya menghapus satu file yang dipilih.
    $stmt = kp_query($db, 'DELETE FROM berkas_pegawai WHERE nik = ? AND kode_berkas = ? AND berkas = ? LIMIT 1', [$nik, $code, $berkas]);
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected < 1) throw new InvalidArgumentException('Berkas tidak ditemukan atau sudah dihapus.');
    // File fisik hanya dihapus jika tidak lagi dirujuk baris lain.
    if (kp_rows($db, 'SELECT 1 FROM berkas_pegawai WHERE berkas = ? LIMIT 1', [$berkas])) return false;
    $path = kpb_file_path($berkas);
    if ($path) {
        return @unlink($path);
    }
    return kpb_delete_remote(basename($berkas));
}

function kpb_stream($db, $nik, $code, $berkas, $download)
{
    $nik = kpb_text($nik, 'NIP pegawai', 20);
    $code = kpb_text($code, 'Jenis berkas', 10);
    $berkas = kpb_text($berkas, 'Berkas', 500);
    if (!kp_rows($db, 'SELECT 1 FROM berkas_pegawai WHERE nik = ? AND kode_berkas = ? AND berkas = ? LIMIT 1', [$nik, $code, $berkas])) return false;

    // 1. Prioritaskan file fisik lokal jika ada
    $path = kpb_file_path($berkas);
    if ($path && is_file($path)) {
        $mime = kpb_mime($path);
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true) && !$download;
        $name = basename($path);
        header('Content-Type: ' . ($inline || in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true) ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . kpb_safe_name($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('Cache-Control: private, max-age=300');
        readfile($path);
        return true;
    }

    // 2. Ambil dari Remote Server Khanza (host DB_HOST: 192.168.5.52)
    if (defined('KP_BERKAS_URL') && !empty(KP_BERKAS_URL)) {
        $cleanBerkas = ltrim(str_replace('\\', '/', $berkas), '/');
        $remoteUrl = rtrim(KP_BERKAS_URL, '/') . '/' . $cleanBerkas;
        return kpb_stream_remote($remoteUrl, basename($cleanBerkas), $download);
    }

    return false;
}

function kpb_stream_remote($url, $name, $download)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || $data === false || $data === '') {
        error_log("[Berkas Pegawai] Gagal mengambil file dari host remote ($url): HTTP $httpCode " . ($err ?: ''));
        return false;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mimeMap = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];
    $mime = $mimeMap[$ext] ?? ($contentType ?: 'application/octet-stream');
    $inline = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) && !$download;

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($data));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . kpb_safe_name($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Cache-Control: private, max-age=300');
    echo $data;
    return true;
}

