<?php
/*
 * Konfigurasi Lokasi Berkas Pegawai Khanza
 *
 * Di RS Assalam, server khanzaapps berada di host database yang sama dengan DB_HOST (192.168.5.52).
 * Jika dashboard berjalan di server terpisah (mis. 192.168.5.44 atau lokal),
 * berkas diakses melalui HTTP URL dari host server tersebut.
 */

// Pastikan koneksi.php sudah di-load agar DB_HOST tersedia
if (!defined('DB_HOST')) {
    $koneksiFile = __DIR__ . '/koneksi.php';
    if (is_file($koneksiFile)) {
        require_once $koneksiFile;
    }
}

// 1. Host Server Khanza (Otomatis mengambil dari DB_HOST, default: 192.168.5.52)
$khanzaHost = defined('DB_HOST') ? DB_HOST : '192.168.5.52';
if (!defined('KP_BERKAS_HOST')) {
    define('KP_BERKAS_HOST', $khanzaHost);
}

// 2. Port Web Server Khanza di host server (Sesuai Apache Ubuntu/Linux Mint: 8820)
if (!defined('KP_BERKAS_PORT')) {
    define('KP_BERKAS_PORT', '8820');
}

// 3. Base URL HTTP ke folder khanzaapps/penggajian di host server
if (!defined('KP_BERKAS_URL')) {
    $portSuffix = (defined('KP_BERKAS_PORT') && KP_BERKAS_PORT && KP_BERKAS_PORT !== '80') ? (':' . KP_BERKAS_PORT) : '';
    define('KP_BERKAS_URL', 'http://' . KP_BERKAS_HOST . $portSuffix . '/khanzaapps/penggajian/');
}

// 4. Token & URL Bridge Upload ke Server Linux Mint (digunakan saat dashboard diakses dari luar server database)
if (!defined('KP_BRIDGE_KEY')) {
    define('KP_BRIDGE_KEY', 'assalam_berkas_secret_key_2026');
}
if (!defined('KP_BRIDGE_URL')) {
    define('KP_BRIDGE_URL', KP_BERKAS_URL . 'pages/berkaspegawai/upload_receiver.php');
}

// 5. Folder fisik lokal (digunakan jika dashboard di-host di mesin yang sama dengan khanzaapps)
if (!defined('KP_BERKAS_ROOT')) {
    // Cek jika ada custom path via Environment Variable
    $envRoot = getenv('KP_BERKAS_ROOT');
    if ($envRoot && is_dir($envRoot)) {
        define('KP_BERKAS_ROOT', $envRoot);
        return;
    }

    $htdocs = dirname(__DIR__, 2);

    $candidates = [
        // Path fisik server Linux Mint (Ubuntu Apache)
        '/var/www/html/khanzaapps/penggajian',
        '/var/www/html/webapps/penggajian',
        '/opt/lampp/htdocs/khanzaapps/penggajian',
        '/opt/lampp/htdocs/webapps/penggajian',

        // Path dinamis relatif dari parent htdocs
        $htdocs . '/khanzaapps/penggajian',
        $htdocs . '/webapps/penggajian',
        $htdocs . '/khanza/penggajian',
        $htdocs . '/penggajian',

        // Server / Lokal Windows
        'C:/Apache24/htdocs/khanzaapps/penggajian',
        'C:/Apache24/htdocs/webapps/penggajian',
        'C:/xampp/htdocs/khanzaapps/penggajian',
        'C:/xampp/htdocs/webapps/penggajian',
        'D:/xampp/htdocs/khanzaapps/penggajian',
    ];

    $resolvedRoot = null;

    // Prioritas 1: Kandidat yang sudah memiliki folder pages/berkaspegawai
    foreach ($candidates as $cand) {
        if (is_dir($cand . '/pages/berkaspegawai') || is_dir($cand . '/pages/berkaspegawai/berkas')) {
            $resolvedRoot = $cand;
            break;
        }
    }

    // Prioritas 2: Kandidat folder penggajian yang ada
    if (!$resolvedRoot) {
        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                $resolvedRoot = $cand;
                break;
            }
        }
    }

    // Fallback default
    if (!$resolvedRoot) {
        $resolvedRoot = is_dir($htdocs . '/khanzaapps/penggajian')
            ? ($htdocs . '/khanzaapps/penggajian')
            : ($htdocs . '/webapps/penggajian');
    }

    define('KP_BERKAS_ROOT', $resolvedRoot);
}

