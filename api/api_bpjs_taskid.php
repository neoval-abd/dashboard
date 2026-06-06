<?php
/**
 * api_bpjs_taskid.php
 * Endpoint AJAX: Ambil list task dari server BPJS langsung (live).
 * Signature mengikuti standar resmi BPJS MobileJKN (sama dengan Java).
 */
session_start();
require_once 'config/koneksi.php';

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
// Eksekusi: coba no_rawat dulu, fallback ke nobooking MJKN
// ============================================================
$result = getListTask($no_rawat, $BASE_URL, $CONS_ID, $USER_KEY, $SECRET);

if ((!$result['success'] || empty($result['tasks'])) && !empty($nobooking)) {
    $result2 = getListTask($nobooking, $BASE_URL, $CONS_ID, $USER_KEY, $SECRET);
    if (!empty($result2['tasks'])) {
        $result = $result2;
    }
}

echo json_encode($result);