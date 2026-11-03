<?php
/*
 * File: api/saungwa_client.php
 * Fungsi: Client resmi integrasi WhatsApp Gateway SaungWA (https://app.saungwa.com/api/create-message)
 *         beserta router gateway terpadu (SaungWA / Fonnte) dan manajemen file konfigurasi.
 */

$saungwaConfigPath = dirname(__DIR__) . '/config/saungwa.php';
if (is_file($saungwaConfigPath)) {
    require_once($saungwaConfigPath);
}

$fonnteClientPath = __DIR__ . '/fonnte_client.php';
if (is_file($fonnteClientPath)) {
    require_once($fonnteClientPath);
}

if (!function_exists('normalize_wa_number')) {
    function normalize_wa_number($phone)
    {
        $clean = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($clean === '') {
            return '';
        }
        if (substr($clean, 0, 1) === '0') {
            return '62' . substr($clean, 1);
        }
        if (substr($clean, 0, 2) !== '62') {
            return '62' . $clean;
        }
        return $clean;
    }
}

function parse_saungwa_appkey_input($rawInput)
{
    $raw = trim((string) $rawInput);
    if ($raw === '') {
        return ['valid' => true, 'app_key' => '', 'error' => ''];
    }

    // Deteksi salah tempel URL dari menu Device Broadcast (/user/device/...)
    if (stripos($raw, '/user/device/') !== false || stripos($raw, '/device/') !== false) {
        return [
            'valid'   => false,
            'app_key' => '',
            'error'   => 'Yang ditempel adalah link menu "Device Broadcast" (/user/device/...), bukan AppKey! Silakan klik menu "Webhook App" (ikon kuning di atas History Pesan pada sidebar kiri SaungWA) -> Buat App untuk RS ASSALAM -> klik Integration -> salin kode appkey.',
        ];
    }

    // Jika user menempel potongan kode PHP/cURL dari halaman Integration SaungWA, ekstrak nilai appkey-nya
    if (preg_match('/appkey[\'"]?\s*(?:=>|=|:)\s*[\'"]([a-zA-Z0-9\-]{16,80})[\'"]/i', $raw, $m)) {
        return ['valid' => true, 'app_key' => $m[1], 'error' => ''];
    }

    // Jika user menempel URL dari halaman Webhook App yang mengandung UUID
    if (preg_match('/^https?:\/\//i', $raw)) {
        if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $raw, $mUuid)) {
            return ['valid' => true, 'app_key' => $mUuid[1], 'error' => ''];
        }
        return [
            'valid'   => false,
            'app_key' => '',
            'error'   => 'AppKey tidak boleh berupa alamat URL (https://...). Salin hanya kode AppKey (contoh: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx) dari menu Webhook App di SaungWA.',
        ];
    }

    // Bersihkan tanda kutip jika ikut tersalin
    $clean = trim($raw, " \t\n\r\0\x0B\"'");
    return ['valid' => true, 'app_key' => $clean, 'error' => ''];
}

function get_active_wa_provider()
{
    $prov = defined('WA_ACTIVE_PROVIDER') ? strtolower(trim((string) WA_ACTIVE_PROVIDER)) : 'saungwa';
    return in_array($prov, ['saungwa', 'fonnte'], true) ? $prov : 'saungwa';
}

function get_saungwa_config_summary()
{
    $provider    = get_active_wa_provider();
    $senderRaw   = defined('SAUNGWA_SENDER_PHONE') ? trim((string) SAUNGWA_SENDER_PHONE) : '';
    $senderNorm  = normalize_wa_number($senderRaw);
    $senderDisp  = ($senderNorm !== '' && substr($senderNorm, 0, 2) === '62')
        ? ('0' . substr($senderNorm, 2))
        : $senderRaw;

    $appKey      = defined('SAUNGWA_APP_KEY') ? trim((string) SAUNGWA_APP_KEY) : '';
    $authKey     = defined('SAUNGWA_AUTH_KEY') ? trim((string) SAUNGWA_AUTH_KEY) : '';
    $apiUrl      = defined('SAUNGWA_API_URL') ? trim((string) SAUNGWA_API_URL) : 'https://app.saungwa.com/api/create-message';
    $testTarget  = defined('SAUNGWA_TEST_TARGET') ? trim((string) SAUNGWA_TEST_TARGET) : '';
    $queueDelay  = defined('SAUNGWA_QUEUE_DELAY_SECONDS') ? (int) SAUNGWA_QUEUE_DELAY_SECONDS : 300;
    $cooldown    = defined('SAUNGWA_SEND_COOLDOWN_SECONDS') ? (int) SAUNGWA_SEND_COOLDOWN_SECONDS : 120;

    $parsedApp   = parse_saungwa_appkey_input($appKey);
    $hasAppKey   = ($parsedApp['valid'] && $parsedApp['app_key'] !== '');
    $hasAuthKey  = ($authKey !== '');
    $isReady     = ($provider === 'fonnte')
        ? (defined('FONNTE_TOKEN') && trim((string) FONNTE_TOKEN) !== '')
        : ($hasAppKey && $hasAuthKey);

    return [
        'provider'       => $provider,
        'sender_phone'   => $senderNorm,
        'sender_display' => $senderDisp,
        'app_key'        => $appKey,
        'auth_key'       => $authKey,
        'has_app_key'    => $hasAppKey,
        'has_auth_key'   => $hasAuthKey,
        'api_url'        => $apiUrl,
        'test_target'    => $testTarget,
        'queue_delay'    => $queueDelay,
        'cooldown'       => $cooldown,
        'is_ready'       => $isReady,
    ];
}

/**
 * Simpan pembaruan konfigurasi SaungWA secara aman ke config/saungwa.php
 */
function save_saungwa_config_file(array $input)
{
    $provider = strtolower(trim((string) ($input['provider'] ?? get_active_wa_provider())));
    if (!in_array($provider, ['saungwa', 'fonnte'], true)) {
        $provider = 'saungwa';
    }

    $senderPhone = normalize_wa_number($input['sender_phone'] ?? (defined('SAUNGWA_SENDER_PHONE') ? SAUNGWA_SENDER_PHONE : ''));
    $rawAppKey   = trim((string) ($input['app_key'] ?? (defined('SAUNGWA_APP_KEY') ? SAUNGWA_APP_KEY : '')));
    $parsedApp   = parse_saungwa_appkey_input($rawAppKey);
    if (!$parsedApp['valid']) {
        return [
            'success' => false,
            'error'   => $parsedApp['error'],
        ];
    }
    $appKey      = $parsedApp['app_key'];
    $authKey     = trim((string) ($input['auth_key'] ?? (defined('SAUNGWA_AUTH_KEY') ? SAUNGWA_AUTH_KEY : '')));
    $testTarget  = trim((string) ($input['test_target'] ?? (defined('SAUNGWA_TEST_TARGET') ? SAUNGWA_TEST_TARGET : '')));
    if ($testTarget !== '') {
        $testTarget = normalize_wa_number($testTarget);
    }

    $apiUrl      = defined('SAUNGWA_API_URL') && trim((string) SAUNGWA_API_URL) !== ''
        ? trim((string) SAUNGWA_API_URL)
        : 'https://app.saungwa.com/api/create-message';
    $sandbox     = defined('SAUNGWA_SANDBOX') ? (bool) SAUNGWA_SANDBOX : false;
    $sslVerify   = defined('SAUNGWA_SSL_VERIFY') ? (bool) SAUNGWA_SSL_VERIFY : false;
    $cooldown    = defined('SAUNGWA_SEND_COOLDOWN_SECONDS') ? max(0, (int) SAUNGWA_SEND_COOLDOWN_SECONDS) : 120;
    $queueDelay  = defined('SAUNGWA_QUEUE_DELAY_SECONDS') ? max(60, (int) SAUNGWA_QUEUE_DELAY_SECONDS) : 300;
    $maxAttempts = defined('SAUNGWA_QUEUE_MAX_ATTEMPTS') ? max(1, (int) SAUNGWA_QUEUE_MAX_ATTEMPTS) : 2;
    $safeStart   = defined('WA_SAFE_HOUR_START') ? (int) WA_SAFE_HOUR_START : 7;
    $safeEnd     = defined('WA_SAFE_HOUR_END') ? (int) WA_SAFE_HOUR_END : 20;

    $phpContent = "<?php\n"
        . "/*\n"
        . " * Konfigurasi Integrasi WhatsApp Gateway SaungWA (https://app.saungwa.com)\n"
        . " * Diperbarui pada: " . date('Y-m-d H:i:s') . "\n"
        . " */\n\n"
        . "define('WA_ACTIVE_PROVIDER', " . var_export($provider, true) . ");\n\n"
        . "// Nomor WA perangkat pengirim di SaungWA\n"
        . "define('SAUNGWA_SENDER_PHONE', " . var_export($senderPhone, true) . ");\n\n"
        . "// Kredensial API SaungWA (POST https://app.saungwa.com/api/create-message)\n"
        . "define('SAUNGWA_APP_KEY', " . var_export($appKey, true) . ");\n"
        . "define('SAUNGWA_AUTH_KEY', " . var_export($authKey, true) . ");\n"
        . "define('SAUNGWA_API_URL', " . var_export($apiUrl, true) . ");\n\n"
        . "// Pengaturan Koneksi & Keamanan Pengiriman\n"
        . "define('SAUNGWA_SANDBOX', " . var_export($sandbox, true) . ");\n"
        . "define('SAUNGWA_SSL_VERIFY', " . var_export($sslVerify, true) . ");\n"
        . "define('SAUNGWA_TEST_TARGET', " . var_export($testTarget, true) . ");\n\n"
        . "// Pengaturan Jeda Antrean Service (~5 menit per pesan agar aman dari blokir WhatsApp)\n"
        . "define('SAUNGWA_SEND_COOLDOWN_SECONDS', " . $cooldown . ");\n"
        . "define('SAUNGWA_QUEUE_DELAY_SECONDS', " . $queueDelay . ");\n"
        . "define('SAUNGWA_QUEUE_MAX_ATTEMPTS', " . $maxAttempts . ");\n\n"
        . "// Jam sopan pengiriman otomatis ke pasien (07:00 s/d 20:00 WIB)\n"
        . "define('WA_SAFE_HOUR_START', " . $safeStart . ");\n"
        . "define('WA_SAFE_HOUR_END', " . $safeEnd . ");\n"
        . "?>\n";

    $targetFile = dirname(__DIR__) . '/config/saungwa.php';
    $written = @file_put_contents($targetFile, $phpContent, LOCK_EX);
    if ($written === false) {
        return [
            'success' => false,
            'error'   => 'Gagal menulis ke file config/saungwa.php. Pastikan folder config memiliki izin tulis.',
        ];
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($targetFile, true);
    }

    return [
        'success'        => true,
        'provider'       => $provider,
        'app_key'        => $appKey,
        'sender_phone'   => $senderPhone,
        'sender_display' => ($senderPhone !== '' && substr($senderPhone, 0, 2) === '62') ? ('0' . substr($senderPhone, 2)) : $senderPhone,
        'has_app_key'    => ($appKey !== ''),
        'has_auth_key'   => ($authKey !== ''),
        'is_ready'       => ($provider === 'fonnte')
            ? (defined('FONNTE_TOKEN') && trim((string) FONNTE_TOKEN) !== '')
            : ($appKey !== '' && $authKey !== ''),
    ];
}

function get_saungwa_send_cooldown()
{
    return defined('SAUNGWA_SEND_COOLDOWN_SECONDS') ? max(0, (int) SAUNGWA_SEND_COOLDOWN_SECONDS) : 120;
}

function get_saungwa_cooldown_remaining()
{
    $cooldown = get_saungwa_send_cooldown();
    if ($cooldown <= 0) {
        return 0;
    }

    $lockFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dashboard_saungwa_send.lock';
    if (!is_file($lockFile)) {
        return 0;
    }

    $lastSentAt = (int) trim((string) @file_get_contents($lockFile));
    if ($lastSentAt <= 0) {
        return 0;
    }

    return max(0, $cooldown - (time() - $lastSentAt));
}

function acquire_saungwa_send_slot(&$lockHandle)
{
    $cooldown = get_saungwa_send_cooldown();
    if ($cooldown <= 0) {
        return ['success' => true];
    }

    $lockFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dashboard_saungwa_send.lock';
    $lockHandle = @fopen($lockFile, 'c+');
    if (!$lockHandle) {
        return ['success' => false, 'error' => 'File pengunci jeda SaungWA tidak dapat dibuat'];
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fclose($lockHandle);
        $lockHandle = null;
        return ['success' => false, 'error' => 'Pengiriman sebelumnya masih berlangsung. Tunggu beberapa detik.'];
    }

    rewind($lockHandle);
    $lastSentAt = (int) trim((string) stream_get_contents($lockHandle));
    $remaining  = $cooldown - (time() - $lastSentAt);
    if ($lastSentAt > 0 && $remaining > 0) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        $lockHandle = null;
        return [
            'success'     => false,
            'error'       => 'Jeda pengaman aktif: tunggu ' . $remaining . ' detik sebelum pengiriman berikutnya.',
            'retry_after' => $remaining,
        ];
    }

    return ['success' => true];
}

function release_saungwa_send_slot($lockHandle, $markSent)
{
    if (!$lockHandle) {
        return;
    }

    if ($markSent) {
        ftruncate($lockHandle, 0);
        rewind($lockHandle);
        fwrite($lockHandle, (string) time());
        fflush($lockHandle);
    }

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}

/**
 * Kirim pesan WhatsApp melalui SaungWA API (POST https://app.saungwa.com/api/create-message)
 */
function send_saungwa_message($phone, $message, array $options = [])
{
    $target         = normalize_wa_number($phone);
    $originalTarget = $target;
    $cleanMsg       = trim((string) $message);

    $rawAppKey      = isset($options['appkey'])
        ? trim((string) $options['appkey'])
        : (defined('SAUNGWA_APP_KEY') ? trim((string) SAUNGWA_APP_KEY) : '');
    $parsedApp      = parse_saungwa_appkey_input($rawAppKey);
    if (!$parsedApp['valid']) {
        return [
            'success' => false,
            'error'   => $parsedApp['error'],
        ];
    }
    $appKey         = $parsedApp['app_key'];
    $authKey        = isset($options['authkey'])
        ? trim((string) $options['authkey'])
        : (defined('SAUNGWA_AUTH_KEY') ? trim((string) SAUNGWA_AUTH_KEY) : '');
    $apiUrl         = defined('SAUNGWA_API_URL') && trim((string) SAUNGWA_API_URL) !== ''
        ? trim((string) SAUNGWA_API_URL)
        : 'https://app.saungwa.com/api/create-message';
    $bypassCooldown = !empty($options['bypass_cooldown']);
    $ignoreTestRedir = !empty($options['ignore_test_redirect']);

    if ($authKey === '') {
        return [
            'success' => false,
            'error'   => 'AuthKey SaungWA belum diisi. Salin Auth API Key dari menu Edit Profile -> Auth Key di SaungWA.',
        ];
    }

    if ($appKey === '') {
        return [
            'success' => false,
            'error'   => 'AppKey SaungWA belum diisi. Buka menu "Webhook App" di dashboard SaungWA -> Buat App untuk perangkat 089675463447 -> salin AppKey ke Pengaturan SaungWA.',
        ];
    }

    if ($target === '') {
        return ['success' => false, 'error' => 'Nomor tujuan WhatsApp kosong atau tidak valid.'];
    }

    if ($cleanMsg === '') {
        return ['success' => false, 'error' => 'Isi pesan WhatsApp kosong.'];
    }

    if (!$ignoreTestRedir && defined('SAUNGWA_TEST_TARGET') && trim((string) SAUNGWA_TEST_TARGET) !== '') {
        $testTarget = normalize_wa_number(SAUNGWA_TEST_TARGET);
        if ($testTarget !== '') {
            $target   = $testTarget;
            $cleanMsg = "[MODE UJI COBA SAUNGWA]\nTarget asli: {$originalTarget}\n\n" . $cleanMsg;
        }
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'Ekstensi PHP cURL belum aktif di server Apache/PHP.'];
    }

    $rateLimitLock = null;
    if (!$bypassCooldown) {
        $slot = acquire_saungwa_send_slot($rateLimitLock);
        if (empty($slot['success'])) {
            return $slot;
        }
    }

    $sandboxFlag = (defined('SAUNGWA_SANDBOX') && SAUNGWA_SANDBOX) ? 'true' : 'false';
    $sslVerify   = defined('SAUNGWA_SSL_VERIFY') ? (bool) SAUNGWA_SSL_VERIFY : false;

    // SaungWA menggunakan multipart/form-data (array pada CURLOPT_POSTFIELDS)
    $postFields = [
        'appkey'  => $appKey,
        'authkey' => $authKey,
        'to'      => $target,
        'message' => $cleanMsg,
        'sandbox' => $sandboxFlag,
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 35,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_SSL_VERIFYPEER => $sslVerify,
        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
    ]);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        if (!$bypassCooldown) {
            release_saungwa_send_slot($rateLimitLock, false);
        }
        return [
            'success'   => false,
            'error'     => 'Koneksi ke server SaungWA gagal (cURL): ' . $err,
            'http_code' => $http,
        ];
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        if (!$bypassCooldown) {
            release_saungwa_send_slot($rateLimitLock, true);
        }
        $snippet = trim(preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
        if (strlen($snippet) > 160) {
            $snippet = substr($snippet, 0, 160) . '...';
        }
        return [
            'success'   => false,
            'error'     => 'Respons SaungWA tidak berformat JSON (HTTP ' . $http . ')' . ($snippet !== '' ? ': ' . $snippet : ''),
            'http_code' => $http,
            'raw'       => $raw,
        ];
    }

    // Evaluasi status sukses SaungWA: {"message_status":"Success","data":{"to":"...","status_code":200}}
    $msgStatus  = strtolower(trim((string) ($json['message_status'] ?? '')));
    $dataCode   = isset($json['data']['status_code']) ? (int) $json['data']['status_code'] : 0;
    $boolStatus = $json['status'] ?? null;

    $isSuccess = ($http >= 200 && $http < 300) && (
        $msgStatus === 'success'
        || $dataCode === 200
        || $boolStatus === true
        || $boolStatus === 'true'
    );

    if ($isSuccess) {
        if (!$bypassCooldown) {
            release_saungwa_send_slot($rateLimitLock, true);
        }
        return [
            'success'   => true,
            'provider'  => 'saungwa',
            'target'    => $target,
            'http_code' => $http,
            'response'  => $json,
        ];
    }

    if (!$bypassCooldown) {
        // Jangan kunci cooldown bila gagal karena kredensial 401/400 agar admin bisa langsung perbaiki
        $markCooldown = ($http >= 500 || $http === 429);
        release_saungwa_send_slot($rateLimitLock, $markCooldown);
    }

    $errDetail = '';
    if (!empty($json['error']) && is_string($json['error'])) {
        $errDetail = $json['error'];
    } elseif (!empty($json['message']) && is_string($json['message'])) {
        $errDetail = $json['message'];
    } elseif (!empty($json['data']['message']) && is_string($json['data']['message'])) {
        $errDetail = $json['data']['message'];
    } elseif (!empty($json['message_status']) && is_string($json['message_status'])) {
        $errDetail = $json['message_status'];
    } else {
        $errDetail = 'Ditolak oleh SaungWA (HTTP ' . $http . ')';
    }

    // Uraikan rincian field apabila SaungWA mengembalikan "Validation errors" (array data / errors)
    $validationFields = [];
    foreach (['data', 'errors'] as $errContainerKey) {
        if (!empty($json[$errContainerKey]) && is_array($json[$errContainerKey])) {
            foreach ($json[$errContainerKey] as $field => $msgs) {
                if ($field === 'status_code' || $field === 'to') {
                    continue;
                }
                if (is_array($msgs)) {
                    $validationFields[] = $field . ': ' . implode(', ', array_map('strval', $msgs));
                } elseif (is_string($msgs) && $field !== 'message') {
                    $validationFields[] = $field . ': ' . $msgs;
                }
            }
        }
    }
    if (!empty($validationFields)) {
        $joinedFields = implode(' | ', $validationFields);
        $errDetail .= ' (' . $joinedFields . ')';
        if (stripos($joinedFields, 'appkey') !== false) {
            $errDetail .= ' -> Pastikan AppKey diambil dari menu "Webhook App" di SaungWA (bukan ID dari menu Device Broadcast).';
        }
    }

    if ($http === 401) {
        $errDetail .= ' — Periksa kecocokan AppKey (menu Webhook App) & AuthKey, serta pastikan perangkat 089675463447 berstatus TERSAMBUNG.';
    }

    return [
        'success'   => false,
        'provider'  => 'saungwa',
        'error'     => $errDetail,
        'http_code' => $http,
        'response'  => $json,
    ];
}

function get_wa_gateway_cooldown_remaining()
{
    if (get_active_wa_provider() === 'fonnte' && function_exists('get_fonnte_cooldown_remaining')) {
        return get_fonnte_cooldown_remaining();
    }
    return get_saungwa_cooldown_remaining();
}

/**
 * Router pengiriman WhatsApp terpadu untuk worker antrean (SaungWA / Fonnte)
 */
function send_wa_gateway_message($phone, $message, array $options = [])
{
    $provider = get_active_wa_provider();
    if ($provider === 'fonnte' && function_exists('send_fonnte_message')) {
        $res = send_fonnte_message($phone, $message);
        $res['provider'] = 'fonnte';
        return $res;
    }

    return send_saungwa_message($phone, $message, $options);
}
?>

