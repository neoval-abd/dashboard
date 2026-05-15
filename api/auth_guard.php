<?php
/*
 * File: api/auth_guard.php
 * =========================================================================
 * GARDA KEAMANAN API — AUTO-PREPEND (RULE #0 ZERO TRUST)
 * =========================================================================
 * File ini TIDAK dipanggil secara manual. Ia diinjeksi secara otomatis
 * oleh Apache ke SEMUA file PHP di folder /api/ menggunakan directive
 * `php_value auto_prepend_file` di api/.htaccess.
 *
 * CARA KERJA:
 * Sebelum baris pertama kode di data_dashboard.php, data_grafik.php, dll
 * dieksekusi, PHP wajib menjalankan file ini terlebih dahulu.
 * Jika session tidak valid → kirim HTTP 401 JSON → hentikan eksekusi.
 * Pembajak yang mencoba akses /api/* langsung dari browser akan mendapat
 * respons 401 Unauthorized, bukan data sensitif.
 */

// Muat koneksi.php untuk menginisialisasi session secara aman (hardened)
// Gunakan flag untuk mencegah koneksi DB ganda jika koneksi sudah ada
if (!defined('KONEKSI_LOADED')) {
    require_once(dirname(__DIR__) . '/config/koneksi.php');
    define('KONEKSI_LOADED', true);
}

// --- CEK SESSION ---
// Jika tidak ada user_id di session = belum login / session expired / akses langsung
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {

    // Set HTTP status 401 Unauthorized
    http_response_code(401);

    // Pastikan response JSON (API endpoint harus konsisten returnnya)
    // Cek apakah header Content-Type sudah di-set oleh file aslinya
    // Kalau belum, set sendiri
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    // Respons JSON generik — jangan bocorkan detail teknis
    echo json_encode([
        'status'  => 'error',
        'code'    => 401,
        'message' => 'Sesi tidak valid atau telah berakhir. Silakan login kembali.',
    ]);

    // Hentikan eksekusi total — file API asli tidak akan jalan sama sekali
    exit;
}
// Jika sampai sini = user sudah login = lanjutkan ke file API yang sebenarnya
?>
