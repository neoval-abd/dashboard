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

function send_fonnte_message($phone, $message)
{
    $target = normalize_wa_number($phone);
    $originalTarget = $target;
    $message = trim((string) $message);

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
        return ['success' => false, 'error' => 'cURL error: ' . $err, 'http_code' => $http];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['success' => false, 'error' => 'Respons Fonnte tidak valid', 'http_code' => $http, 'raw' => $raw];
    }

    $status = $json['status'] ?? false;
    if ($http >= 200 && $http < 300 && ($status === true || $status === 'true')) {
        return ['success' => true, 'http_code' => $http, 'response' => $json];
    }

    $detail = $json['detail'] ?? ($json['reason'] ?? ($json['message'] ?? 'Gagal mengirim via Fonnte'));
    return ['success' => false, 'error' => $detail, 'http_code' => $http, 'response' => $json];
}
?>
