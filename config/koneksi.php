<?php
/*
 * File: config/koneksi.php (SECURITY HARDENED v2)
 * - Session hardening: httponly, samesite=Strict, use_only_cookies
 * - display_errors dimatikan (tidak bocorkan info ke publik)
 * - Security Headers: X-Frame-Options, X-Content-Type-Options, Referrer-Policy
 * - Koneksi tetap menggunakan MySQLi (sesuai pola Khanza)
 */

// 1. Mulai Session dengan Aman & Auto-Detect HTTPS
if (session_status() == PHP_SESSION_NONE) {

    // Deteksi Otomatis koneksi aman (HTTPS)
    $is_https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );

    // Hardening: Paksa session hanya via cookie (bukan URL), strict mode
    ini_set('session.use_only_cookies', 1);   // Session ID tidak boleh lewat URL
    ini_set('session.use_strict_mode', 1);     // Tolak session ID ilegal
    ini_set('session.cookie_httponly', 1);     // Anti XSS cookie theft
    ini_set('session.cookie_secure', $is_https ? '1' : '0'); // Otomatis True jika HTTPS

    // Set parameter cookie (SameSite=Strict tersedia native di PHP 7.3+)
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https, 
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

// 2. HTTP Security Headers — dikirim sedini mungkin
// (Header dasar, tidak memblokir CDN eksternal)
if (!headers_sent()) {
    // Mencegah halaman di-embed dalam iframe di domain lain (anti-Clickjacking)
    header('X-Frame-Options: SAMEORIGIN');

    // Mencegah browser menebak MIME type (anti MIME-Sniffing)
    header('X-Content-Type-Options: nosniff');

    // Kontrol informasi Referer yang dikirim saat navigasi
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Filter XSS bawaan browser (legacy, tetapi masih berguna)
    header('X-XSS-Protection: 1; mode=block');

    // Batasi akses ke fitur browser sensitif
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// 3. Pengaturan Error Reporting
// WAJIB: display_errors=0 di production. Errors dicatat ke log server, bukan ditampilkan ke user.
error_reporting(0);
ini_set('display_errors', 0);

// 4. Detail Koneksi Database
define('DB_HOST', '192.168.20.167');
define('DB_USER', 'client');
define('DB_PASS', 'clientpass');
define('DB_NAME', 'sikadella2026');
define('DB_PORT', '3306'); 

// 5. Buat Koneksi menggunakan MySQLi
$koneksi = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// 6. Cek Koneksi (Pesan error generik — jangan bocorkan detail koneksi!)
if ($koneksi->connect_error) {
    // Log error ke file, bukan tampilkan ke user
    error_log('[Dashboard Eksekutif] Koneksi DB gagal: ' . $koneksi->connect_error);
    die('Layanan sementara tidak tersedia. Silakan hubungi administrator.');
}

// 7. Set Charset
$koneksi->set_charset("utf8mb4");

// 8. Set Timezone
date_default_timezone_set('Asia/Jakarta');
?>