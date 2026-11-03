<?php
/*
 * File: api/saungwa_client.php
 * Fungsi: Client resmi integrasi WhatsApp Gateway SaungWA (https://app.saungwa.com/api/create-message)
 *         dengan penyimpanan konfigurasi berbasis Database (`konfigurasi_wa_gateway`) agar 100% berjalan
 *         di server Linux Mint / Ubuntu maupun Windows tanpa memerlukan sudo / chmod pada folder config/.
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

/**
 * Validasi & ekstrak AppKey SaungWA (menolak link /user/device/... dan mengekstrak UUID bila ditempel dari snippet)
 */
function parse_saungwa_appkey_input($rawInput)
{
    $raw = trim((string) $rawInput);
    if ($raw === '') {
        return ['valid' => true, 'app_key' => '', 'error' => ''];
    }

    if (stripos($raw, '/user/device/') !== false || stripos($raw, '/device/') !== false) {
        return [
            'valid'   => false,
            'app_key' => '',
            'error'   => 'Yang ditempel adalah link menu "Device Broadcast" (/user/device/...), bukan AppKey! Buka menu "Webhook App" di sidebar kiri SaungWA -> Buat App -> klik Integration -> salin kode appkey.',
        ];
    }

    if (preg_match('/appkey[\'"]?\s*(?:=>|=|:)\s*[\'"]([a-zA-Z0-9\-]{16,80})[\'"]/i', $raw, $m)) {
        return ['valid' => true, 'app_key' => $m[1], 'error' => ''];
    }

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

    $clean = trim($raw, " \t\n\r\0\x0B\"'");
    return ['valid' => true, 'app_key' => $clean, 'error' => ''];
}

/**
 * Pastikan tabel konfigurasi gateway WA tersedia di database (bebas masalah permission folder Linux).
 */
function ensure_wa_gateway_config_table($db = null)
{
    if (!$db && isset($GLOBALS['koneksi']) && ($GLOBALS['koneksi'] instanceof mysqli)) {
        $db = $GLOBALS['koneksi'];
    }
    if (!($db instanceof mysqli)) {
        return false;
    }

    $sql = "CREATE TABLE IF NOT EXISTS konfigurasi_wa_gateway (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        provider VARCHAR(20) NOT NULL DEFAULT 'saungwa',
        sender_phone VARCHAR(30) NOT NULL DEFAULT '',
        app_key VARCHAR(120) NOT NULL DEFAULT '',
        auth_key VARCHAR(160) NOT NULL DEFAULT '',
        api_url VARCHAR(255) NOT NULL DEFAULT 'https://app.saungwa.com/api/create-message',
        test_target VARCHAR(30) NOT NULL DEFAULT '',
        updated_by VARCHAR(100) DEFAULT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return (bool) @$db->query($sql);
}

/**
 * Ambil konfigurasi aktif dengan prioritas:
 * 1. Tabel database `konfigurasi_wa_gateway` (id = 1)
 * 2. Fallback konstanta `config/saungwa.php` (bila ada)
 */
function get_saungwa_runtime_settings($db = null, $forceReload = false)
{
    static $cached = null;
    if ($cached !== null && !$forceReload) {
        return $cached;
    }

    if (!$db && isset($GLOBALS['koneksi']) && ($GLOBALS['koneksi'] instanceof mysqli)) {
        $db = $GLOBALS['koneksi'];
    }

    // Nilai default dari file config/saungwa.php (jika ada di lokal) atau default standar RS Assalam
    $provider   = defined('WA_ACTIVE_PROVIDER') ? strtolower(trim((string) WA_ACTIVE_PROVIDER)) : 'saungwa';
    $senderRaw  = defined('SAUNGWA_SENDER_PHONE') ? trim((string) SAUNGWA_SENDER_PHONE) : '6289675463447';
    $appKey     = defined('SAUNGWA_APP_KEY') ? trim((string) SAUNGWA_APP_KEY) : '';
    $authKey    = defined('SAUNGWA_AUTH_KEY') ? trim((string) SAUNGWA_AUTH_KEY) : '';
    $apiUrl     = defined('SAUNGWA_API_URL') && trim((string) SAUNGWA_API_URL) !== ''
        ? trim((string) SAUNGWA_API_URL)
        : 'https://app.saungwa.com/api/create-message';
    $testTarget = defined('SAUNGWA_TEST_TARGET') ? trim((string) SAUNGWA_TEST_TARGET) : '';
    $source     = is_file(dirname(__DIR__) . '/config/saungwa.php') ? 'file' : 'default';

    if ($db instanceof mysqli && ensure_wa_gateway_config_table($db)) {
        $res = @$db->query("SELECT provider, sender_phone, app_key, auth_key, api_url, test_target FROM konfigurasi_wa_gateway WHERE id = 1 LIMIT 1");
        if ($res && ($row = $res->fetch_assoc())) {
            $provider   = strtolower(trim((string) ($row['provider'] ?: 'saungwa')));
            $senderRaw  = trim((string) ($row['sender_phone'] !== '' ? $row['sender_phone'] : $senderRaw));
            $appKey     = trim((string) $row['app_key']);
            $authKey    = trim((string) ($row['auth_key'] !== '' ? $row['auth_key'] : $authKey));
            $apiUrl     = trim((string) ($row['api_url'] !== '' ? $row['api_url'] : $apiUrl));
            $testTarget = trim((string) $row['test_target']);
            $source     = 'database';
        } elseif ($appKey !== '' || $authKey !== '') {
            // Seed otomatis baris pertama ke DB bila file lokal memiliki konfigurasi awal
            $stmtSeed = @$db->prepare("INSERT IGNORE INTO konfigurasi_wa_gateway (id, provider, sender_phone, app_key, auth_key, api_url, test_target, updated_by) VALUES (1, ?, ?, ?, ?, ?, ?, 'auto-seed')");
            if ($stmtSeed) {
                $normSender = normalize_wa_number($senderRaw);
                $stmtSeed->bind_param("ssssss", $provider, $normSender, $appKey, $authKey, $apiUrl, $testTarget);
                @$stmtSeed->execute();
                $stmtSeed->close();
                $source = 'database';
            }
        }
    }

    if (!in_array($provider, ['saungwa', 'fonnte'], true)) {
        $provider = 'saungwa';
    }

    $senderNorm = normalize_wa_number($senderRaw);
    $senderDisp = ($senderNorm !== '' && substr($senderNorm, 0, 2) === '62')
        ? ('0' . substr($senderNorm, 2))
        : $senderRaw;

    $parsedApp  = parse_saungwa_appkey_input($appKey);
    $cleanApp   = $parsedApp['valid'] ? $parsedApp['app_key'] : '';
    $hasAppKey  = ($cleanApp !== '');
    $hasAuthKey = ($authKey !== '');
    $isReady    = ($provider === 'fonnte')
        ? (defined('FONNTE_TOKEN') && trim((string) FONNTE_TOKEN) !== '')
        : ($hasAppKey && $hasAuthKey);

    $cached = [
        'provider'       => $provider,
        'sender_phone'   => $senderNorm,
        'sender_display' => $senderDisp,
        'app_key'        => $cleanApp,
        'auth_key'       => $authKey,
        'has_app_key'    => $hasAppKey,
        'has_auth_key'   => $hasAuthKey,
        'api_url'        => $apiUrl,
        'test_target'    => $testTarget,
        'queue_delay'    => defined('SAUNGWA_QUEUE_DELAY_SECONDS') ? (int) SAUNGWA_QUEUE_DELAY_SECONDS : 300,
        'cooldown'       => defined('SAUNGWA_SEND_COOLDOWN_SECONDS') ? (int) SAUNGWA_SEND_COOLDOWN_SECONDS : 120,
        'storage_source' => $source,
        'is_ready'       => $isReady,
    ];

    return $cached;
}

function get_active_wa_provider()
{
    $cfg = get_saungwa_runtime_settings();
    return $cfg['provider'];
}

function get_saungwa_config_summary()
{
    return get_saungwa_runtime_settings();
}

/**
 * Simpan pembaruan konfigurasi SaungWA ke Database (`konfigurasi_wa_gateway`)
 * sehingga tidak pernah gagal akibat izin tulis folder / sudo di Linux Mint.
 * Jika file config/saungwa.php kebetulan writable (mis. di Windows lokal), ikut disinkronkan secara opsional.
 */
function save_saungwa_config_file(array $input, $db = null)
{
    if (!$db && isset($GLOBALS['koneksi']) && ($GLOBALS['koneksi'] instanceof mysqli)) {
        $db = $GLOBALS['koneksi'];
    }

    $current  = get_saungwa_runtime_settings($db);
    $provider = strtolower(trim((string) ($input['provider'] ?? $current['provider'])));
    if (!in_array($provider, ['saungwa', 'fonnte'], true)) {
        $provider = 'saungwa';
    }

    $senderPhone = normalize_wa_number($input['sender_phone'] ?? $current['sender_phone']);
    $rawAppKey   = trim((string) ($input['app_key'] ?? $current['app_key']));
    $parsedApp   = parse_saungwa_appkey_input($rawAppKey);
    if (!$parsedApp['valid']) {
        return [
            'success' => false,
            'error'   => $parsedApp['error'],
        ];
    }
    $appKey     = $parsedApp['app_key'];
    $authKey    = trim((string) ($input['auth_key'] ?? $current['auth_key']));
    $testTarget = trim((string) ($input['test_target'] ?? $current['test_target']));
    if ($testTarget !== '') {
        $testTarget = normalize_wa_number($testTarget);
    }
    $apiUrl     = $current['api_url'] ?: 'https://app.saungwa.com/api/create-message';
    $updatedBy  = substr(trim((string) ($_SESSION['nama_pegawai'] ?? ($_SESSION['user_id'] ?? 'admin'))), 0, 100);

    $savedToDb = false;
    $dbErr     = '';

    if ($db instanceof mysqli && ensure_wa_gateway_config_table($db)) {
        $sql = "INSERT INTO konfigurasi_wa_gateway
                    (id, provider, sender_phone, app_key, auth_key, api_url, test_target, updated_by)
                VALUES (1, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    provider     = VALUES(provider),
                    sender_phone = VALUES(sender_phone),
                    app_key      = VALUES(app_key),
                    auth_key     = VALUES(auth_key),
                    api_url      = VALUES(api_url),
                    test_target  = VALUES(test_target),
                    updated_by   = VALUES(updated_by)";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssssss", $provider, $senderPhone, $appKey, $authKey, $apiUrl, $testTarget, $updatedBy);
            if ($stmt->execute()) {
                $savedToDb = true;
            } else {
                $dbErr = $stmt->error;
            }
            $stmt->close();
        } else {
            $dbErr = $db->error;
        }
    }

    // Opsional (best-effort): sinkronkan ke config/saungwa.php HANYA bila folder/file mengizinkan tulis (tidak wajib di Linux)
    $targetFile = dirname(__DIR__) . '/config/saungwa.php';
    $configDir  = dirname($targetFile);
    $savedToFile = false;
    if ((is_file($targetFile) && is_writable($targetFile)) || (!is_file($targetFile) && is_writable($configDir))) {
        $sandbox     = defined('SAUNGWA_SANDBOX') ? (bool) SAUNGWA_SANDBOX : false;
        $sslVerify   = defined('SAUNGWA_SSL_VERIFY') ? (bool) SAUNGWA_SSL_VERIFY : false;
        $cooldown    = defined('SAUNGWA_SEND_COOLDOWN_SECONDS') ? max(0, (int) SAUNGWA_SEND_COOLDOWN_SECONDS) : 120;
        $queueDelay  = defined('SAUNGWA_QUEUE_DELAY_SECONDS') ? max(60, (int) SAUNGWA_QUEUE_DELAY_SECONDS) : 300;
        $maxAttempts = defined('SAUNGWA_QUEUE_MAX_ATTEMPTS') ? max(1, (int) SAUNGWA_QUEUE_MAX_ATTEMPTS) : 2;
        $safeStart   = defined('WA_SAFE_HOUR_START') ? (int) WA_SAFE_HOUR_START : 7;
        $safeEnd     = defined('WA_SAFE_HOUR_END') ? (int) WA_SAFE_HOUR_END : 20;

        $phpContent = "<?php\n"
            . "/*\n"
            . " * Konfigurasi Integrasi WhatsApp Gateway SaungWA\n"
            . " * Diperbarui pada: " . date('Y-m-d H:i:s') . "\n"
            . " */\n\n"
            . "define('WA_ACTIVE_PROVIDER', " . var_export($provider, true) . ");\n"
            . "define('SAUNGWA_SENDER_PHONE', " . var_export($senderPhone, true) . ");\n"
            . "define('SAUNGWA_APP_KEY', " . var_export($appKey, true) . ");\n"
            . "define('SAUNGWA_AUTH_KEY', " . var_export($authKey, true) . ");\n"
            . "define('SAUNGWA_API_URL', " . var_export($apiUrl, true) . ");\n"
            . "define('SAUNGWA_SANDBOX', " . var_export($sandbox, true) . ");\n"
            . "define('SAUNGWA_SSL_VERIFY', " . var_export($sslVerify, true) . ");\n"
            . "define('SAUNGWA_TEST_TARGET', " . var_export($testTarget, true) . ");\n"
            . "define('SAUNGWA_SEND_COOLDOWN_SECONDS', " . $cooldown . ");\n"
            . "define('SAUNGWA_QUEUE_DELAY_SECONDS', " . $queueDelay . ");\n"
            . "define('SAUNGWA_QUEUE_MAX_ATTEMPTS', " . $maxAttempts . ");\n"
            . "define('WA_SAFE_HOUR_START', " . $safeStart . ");\n"
            . "define('WA_SAFE_HOUR_END', " . $safeEnd . ");\n"
            . "?>\n";

        if (@file_put_contents($targetFile, $phpContent, LOCK_EX) !== false) {
            $savedToFile = true;
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($targetFile, true);
            }
        }
    }

    if (!$savedToDb && !$savedToFile) {
        return [
            'success' => false,
            'error'   => 'Gagal menyimpan konfigurasi ke database' . ($dbErr !== '' ? ': ' . $dbErr : '.'),
        ];
    }

    // Muat ulang cache runtime setelah tersimpan
    $fresh = get_saungwa_runtime_settings($db, true);
    $fresh['success']    = true;
    $fresh['saved_mode'] = $savedToDb ? 'database' : 'file';
    return $fresh;
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
    $runtimeCfg     = get_saungwa_runtime_settings();
    $target         = normalize_wa_number($phone);
    $originalTarget = $target;
    $cleanMsg       = trim((string) $message);

    $rawAppKey      = (isset($options['appkey']) && trim((string) $options['appkey']) !== '')
        ? trim((string) $options['appkey'])
        : $runtimeCfg['app_key'];
    $parsedApp      = parse_saungwa_appkey_input($rawAppKey);
    if (!$parsedApp['valid']) {
        return [
            'success' => false,
            'error'   => $parsedApp['error'],
        ];
    }
    $appKey          = $parsedApp['app_key'];
    $authKey         = (isset($options['authkey']) && trim((string) $options['authkey']) !== '')
        ? trim((string) $options['authkey'])
        : $runtimeCfg['auth_key'];
    $apiUrl          = $runtimeCfg['api_url'] ?: 'https://app.saungwa.com/api/create-message';
    $bypassCooldown  = !empty($options['bypass_cooldown']);
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

    if (!$ignoreTestRedir && $runtimeCfg['test_target'] !== '') {
        $testTarget = normalize_wa_number($runtimeCfg['test_target']);
        if ($testTarget !== '') {
            $target   = $testTarget;
            $cleanMsg = "[MODE UJI COBA SAUNGWA]\nTarget asli: {$originalTarget}\n\n" . $cleanMsg;
        }
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'Ekstensi PHP cURL belum aktif di server Apache/PHP (di Linux Mint jalankan: sudo apt install php-curl).'];
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
