<?php
/**
 * api_bpjs_taskid.php
 * Endpoint AJAX: Ambil list task dari server BPJS langsung (live).
 * Signature mengikuti standar resmi BPJS MobileJKN (sama dengan Java).
 * 
 * v2: Task ID yang berhasil diambil otomatis disimpan ke tabel lokal
 *     referensi_mobilejkn_bpjs_taskid (agar local_TID_3..7 terisi).
 */

// auth_guard.php (auto-prepend) sudah load koneksi.php + cek session.
// require_once di bawah hanya fallback jika dipanggil tanpa prepend.
require_once dirname(__DIR__) . '/config/koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$no_rawat  = isset($_GET['no_rawat'])  ? trim($_GET['no_rawat'])  : '';
$nobooking = isset($_GET['nobooking']) ? trim($_GET['nobooking']) : '';

if (empty($no_rawat)) {
    echo json_encode(['success' => false, 'message' => 'no_rawat kosong']);
    exit;
}

// ============================================================
// Ambil kredensial dari konstanta di koneksi.php
// ============================================================
$CONS_ID  = BPJS_CONS_ID;
$USER_KEY = BPJS_USER_KEY;
$SECRET   = BPJS_SECRET;
$BASE_URL = rtrim(BPJS_API_URL, '/');

// ============================================================
// Fungsi HMAC Signature — SAMA PERSIS dengan Java:
//   utc = String.valueOf(api.GetUTCdatetimeAsString())  → Unix time detik
//   msg = consid + "&" + timestamp
//   hmac = HmacSHA256(msg, secretkey) → base64
// ============================================================
function bpjsMakeHeaders(string $consId, string $userKey, string $secret): array {
    // Timestamp: Unix time dalam DETIK (bukan milidetik)
    $timestamp = (string) time();

    // Signature: HMAC-SHA256(consId + "&" + timestamp, secret) → base64
    $msg       = $consId . '&' . $timestamp;
    $signature = base64_encode(hash_hmac('sha256', $msg, $secret, true));

    return [
        'timestamp' => $timestamp,
        'headers'   => [
            'Content-Type: application/json',
            'x-cons-id: '   . $consId,
            'x-timestamp: ' . $timestamp,
            'x-signature: ' . $signature,
            'user_key: '    . $userKey,
        ]
    ];
}

// ============================================================
// Decrypt response BPJS
// Standar BPJS: response dienkripsi AES-256-CBC
//   key = SHA-256(consid + secretkey + timestamp) → 32 byte pertama
//   IV  = MD5(consid + secretkey)                 → 16 byte
// ============================================================
function bpjsDecrypt(string $encryptedBase64, string $consId, string $secret, string $timestamp): ?string {
    $key = substr(hash('sha256', $consId . $secret . $timestamp), 0, 32);
    $iv  = substr(hash('md5',    $consId . $secret), 0, 16);

    $decoded = base64_decode($encryptedBase64);
    if ($decoded === false) return null;

    $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return ($decrypted !== false) ? $decrypted : null;
}

// ============================================================
// Fungsi utama: hit API BPJS getlisttask
// ============================================================
function getListTask(string $kodeBooking, string $baseUrl, string $consId, string $userKey, string $secret): array {
    $hdr       = bpjsMakeHeaders($consId, $userKey, $secret);
    $timestamp = $hdr['timestamp'];
    $headers   = $hdr['headers'];

    $url     = $baseUrl . '/antrean/getlisttask';
    $payload = json_encode(['kodebooking' => $kodeBooking]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'message' => "cURL error: $err", 'tasks' => []];
    }
    if ($http !== 200) {
        return ['success' => false, 'message' => "HTTP $http", 'tasks' => [], 'raw' => $raw];
    }

    $json = json_decode($raw, true);
    if (!$json) {
        return ['success' => false, 'message' => 'JSON parse error', 'raw' => $raw, 'tasks' => []];
    }

    $code = (string)($json['metadata']['code'] ?? '');
    if ($code !== '200') {
        $msg = $json['metadata']['message'] ?? 'Unknown error from BPJS';
        return ['success' => false, 'message' => $msg, 'bpjs_code' => $code, 'tasks' => []];
    }

    // Response bisa plaintext array ATAU string terenkripsi AES
    $response = $json['response'] ?? [];

    if (is_string($response) && !empty($response)) {
        // Coba decrypt AES dulu
        $decrypted = bpjsDecrypt($response, $consId, $secret, $timestamp);
        if ($decrypted) {
            $response = json_decode($decrypted, true) ?? [];
        } else {
            // Fallback: coba base64 decode langsung (jika tidak dienkripsi)
            $b64 = base64_decode($response, true);
            if ($b64) $response = json_decode($b64, true) ?? [];
        }
    }

    // Normalisasi ke array tasks
    $tasks = [];
    if (is_array($response)) {
        foreach ($response as $item) {
            if (!is_array($item)) continue;
            $tasks[] = [
                'taskid'   => (string)($item['taskid']   ?? ''),
                'taskname' => (string)($item['taskname'] ?? ''),
                'waktu'    => (string)($item['waktu']    ?? ''),
                'wakturs'  => (string)($item['wakturs']  ?? ''),
            ];
        }
    }

    return ['success' => true, 'tasks' => $tasks, 'kode' => $kodeBooking];
}

// ============================================================
// Simpan Task ID ke database lokal (referensi_mobilejkn_bpjs_taskid)
// Sama seperti BPJSTaskIDMobileJKN.java di SIMRS Khanza:
//   - waktu dari BPJS = Unix milidetik → konversi ke DATETIME MySQL
//   - INSERT ... ON DUPLICATE KEY UPDATE agar idempotent
// ============================================================
function saveTasksToLocalDB($conn, string $noRawat, array $tasks): array {
    $saved  = 0;
    $errors = [];

    // Validasi koneksi MySQLi
    if (!($conn instanceof mysqli)) {
        return ['saved' => 0, 'errors' => ['Invalid DB connection']];
    }

    // Hanya simpan taskid 3-7 (sesuai kolom di laporan antrol)
    $validTaskIds = ['3', '4', '5', '6', '7'];

    foreach ($tasks as $task) {
        $taskId = (string)($task['taskid'] ?? '');
        if (!in_array($taskId, $validTaskIds)) continue;

        // Konversi waktu dari Unix milidetik → MySQL DATETIME
        $waktuRaw = $task['waktu'] ?? '';
        $waktuMs  = is_numeric($waktuRaw) ? (int)$waktuRaw : 0;
        if ($waktuMs <= 0) continue;

        // BPJS kirim dalam milidetik, PHP date() butuh detik
        $waktuDt = date('Y-m-d H:i:s', (int)($waktuMs / 1000));

        try {
            $sql = "INSERT INTO referensi_mobilejkn_bpjs_taskid (no_rawat, taskid, waktu) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE waktu = VALUES(waktu)";

            $stmt = @$conn->prepare($sql);
            if (!$stmt) {
                $errors[] = "Prepare (taskid=$taskId): " . $conn->error;
                continue;
            }
            $stmt->bind_param("sss", $noRawat, $taskId, $waktuDt);
            if ($stmt->execute()) {
                $saved++;
            } else {
                $errors[] = "Execute (taskid=$taskId): " . $stmt->error;
            }
            $stmt->close();
        } catch (\Throwable $e) {
            $errors[] = "Exception (taskid=$taskId): " . $e->getMessage();
        }
    }

    return ['saved' => $saved, 'errors' => $errors];
}

// ============================================================
// Eksekusi: coba no_rawat dulu, fallback ke nobooking MJKN
// ============================================================
$result = getListTask($no_rawat, $BASE_URL, $CONS_ID, $USER_KEY, $SECRET);

if ((!$result['success'] || empty($result['tasks'])) && !empty($nobooking)) {
    $result2 = getListTask($nobooking, $BASE_URL, $CONS_ID, $USER_KEY, $SECRET);
    if (!empty($result2['tasks'])) {
        $result = $result2;
    }
}

// ============================================================
// Simpan ke database lokal jika berhasil ambil task dari BPJS
// Dibungkus try-catch + ob agar JSON response SELALU bersih
// meskipun terjadi error di sisi DB (misal tabel belum ada).
// ============================================================
if ($result['success'] && !empty($result['tasks'])) {
    ob_start();
    try {
        $saveResult = saveTasksToLocalDB($koneksi, $no_rawat, $result['tasks']);
        $result['db_save'] = $saveResult;
    } catch (\Throwable $e) {
        $result['db_save'] = ['saved' => 0, 'errors' => [$e->getMessage()]];
    }
    // Buang output liar (PHP warning/notice yang bocor)
    $stray = ob_get_clean();
    if (!empty($stray)) {
        error_log('[api_bpjs_taskid] Stray output caught: ' . substr($stray, 0, 200));
    }
}

echo json_encode($result);