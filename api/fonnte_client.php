<?php
require_once(dirname(__DIR__) . '/config/fonnte.php');

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

function clean_fonnte_message($message)
{
    return trim(preg_replace('/\R*\s*Sent via fonnte\.com\s*$/i', '', (string) $message));
}

function get_fonnte_send_cooldown()
{
    return defined('FONNTE_SEND_COOLDOWN_SECONDS') ? max(0, (int) FONNTE_SEND_COOLDOWN_SECONDS) : 0;
}

function get_fonnte_cooldown_remaining()
{
    $cooldown = get_fonnte_send_cooldown();
    if ($cooldown <= 0) {
        return 0;
    }

    $lockFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dashboard_fonnte_send.lock';
    if (!is_file($lockFile)) {
        return 0;
    }

    $lastSentAt = (int) trim((string) @file_get_contents($lockFile));
    if ($lastSentAt <= 0) {
        return 0;
    }

    return max(0, $cooldown - (time() - $lastSentAt));
}

function acquire_fonnte_send_slot(&$lockHandle)
{
    $cooldown = get_fonnte_send_cooldown();
    if ($cooldown <= 0) {
        return ['success' => true];
    }

    $lockFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dashboard_fonnte_send.lock';
    $lockHandle = @fopen($lockFile, 'c+');
    if (!$lockHandle) {
        return ['success' => false, 'error' => 'File pengunci jeda Fonnte tidak dapat dibuat'];
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fclose($lockHandle);
        $lockHandle = null;
        return ['success' => false, 'error' => 'Pengiriman sebelumnya masih diproses. Coba lagi beberapa detik lagi.'];
    }

    rewind($lockHandle);
    $lastSentAt = (int) trim(stream_get_contents($lockHandle));
    $remaining = $cooldown - (time() - $lastSentAt);
    if ($lastSentAt > 0 && $remaining > 0) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        $lockHandle = null;
        return ['success' => false, 'error' => 'Tunggu ' . $remaining . ' detik lagi sebelum mengirim pesan berikutnya.', 'retry_after' => $remaining];
    }

    return ['success' => true];
}

function release_fonnte_send_slot($lockHandle, $markSent)
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

function send_fonnte_message($phone, $message)
{
    $target = normalize_wa_number($phone);
    $originalTarget = $target;
    $message = clean_fonnte_message($message);
    $rateLimitLock = null;

    if (trim(FONNTE_TOKEN) === '') {
        return ['success' => false, 'error' => 'Token Fonnte belum diisi di config/fonnte.php'];
    }

    if ($target === '') {
        return ['success' => false, 'error' => 'Nomor tujuan kosong'];
    }

    if ($message === '') {
        return ['success' => false, 'error' => 'Pesan kosong'];
    }

    if (defined('FONNTE_TEST_TARGET') && trim(FONNTE_TEST_TARGET) !== '') {
        $testTarget = normalize_wa_number(FONNTE_TEST_TARGET);
        if ($testTarget !== '') {
            $target = $testTarget;
            $message = "[MODE TEST]\nTarget asli: {$originalTarget}\n\n" . $message;
        }
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'error' => 'Ekstensi cURL PHP belum aktif'];
    }

    $slot = acquire_fonnte_send_slot($rateLimitLock);
    if (empty($slot['success'])) {
        return $slot;
    }

    $payload = [
        'target' => $target,
        'message' => $message,
        'countryCode' => FONNTE_COUNTRY_CODE,
    ];

    $ch = curl_init(FONNTE_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . FONNTE_TOKEN,
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => defined('FONNTE_SSL_VERIFY') ? FONNTE_SSL_VERIFY : true,
        CURLOPT_SSL_VERIFYHOST => (defined('FONNTE_SSL_VERIFY') && FONNTE_SSL_VERIFY) ? 2 : 0,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        release_fonnte_send_slot($rateLimitLock, false);
        return ['success' => false, 'error' => 'cURL error: ' . $err, 'http_code' => $http];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        release_fonnte_send_slot($rateLimitLock, true);
        return ['success' => false, 'error' => 'Respons Fonnte tidak valid', 'http_code' => $http, 'raw' => $raw];
    }

    $status = $json['status'] ?? false;
    if ($http >= 200 && $http < 300 && ($status === true || $status === 'true')) {
        release_fonnte_send_slot($rateLimitLock, true);
        return ['success' => true, 'http_code' => $http, 'response' => $json];
    }

    release_fonnte_send_slot($rateLimitLock, true);
    $detail = $json['detail'] ?? ($json['reason'] ?? ($json['message'] ?? 'Gagal mengirim via Fonnte'));
    return ['success' => false, 'error' => $detail, 'http_code' => $http, 'response' => $json];
}
?>
