<?php
/*
 * File: core/login_process.php (SECURITY HARDENED v4)
 * - FIX KRITIS: SQL Injection pada query nama petugas/dokter → Prepared Statement
 * - TAMBAHAN: Validasi CSRF Token (Zero-Trust Anti-CSRF)
 * - TAMBAHAN: Login Throttle — 6 kali gagal → cooldown 60 detik
 * - PERBAIKAN: Hapus session_start() duplikat (koneksi.php yang handle)
 */

// Require koneksi.php DULU (dia yang handle session_start() secara aman + hardened)
require_once(dirname(__DIR__) . '/config/koneksi.php');

// 1. Ambil data dari form
$username       = isset($_POST['username']) ? trim($_POST['username']) : '';
$password_input = isset($_POST['password']) ? trim($_POST['password']) : '';
$csrf_token     = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

// =========================================================================
// VALIDASI AWAL: Form tidak boleh kosong
// =========================================================================
if (empty($username) || empty($password_input)) {
    header('Location: ../index.php?error=1');
    exit;
}

// =========================================================================
// VALIDASI CSRF TOKEN (Anti-CSRF — Zero Trust Rule #0)
// =========================================================================
// Bandingkan token dari form dengan yang ada di session menggunakan hash_equals()
// (mencegah timing attack)
if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    // Token tidak cocok = kemungkinan serangan CSRF — hentikan eksekusi
    http_response_code(403);
    die('Akses ditolak.');
}
// Hapus token sekali pakai (one-time use) untuk mencegah replay attack
unset($_SESSION['csrf_token']);

// =========================================================================
// VALIDASI THROTTLE (Anti-Brute Force — 6 kali gagal → cooldown 60s)
// =========================================================================
$now            = time();
$lockout_until  = isset($_SESSION['login_lockout_until']) ? (int)$_SESSION['login_lockout_until'] : 0;
$login_attempts = isset($_SESSION['login_attempts'])      ? (int)$_SESSION['login_attempts']      : 0;

// Cek apakah masih dalam periode lockout
if ($lockout_until > $now) {
    $sisa_detik = $lockout_until - $now;
    header("Location: ../index.php?error=locked&sisa=" . $sisa_detik);
    exit;
}

// Reset counter jika lockout sudah berakhir
if ($lockout_until > 0 && $lockout_until <= $now) {
    $_SESSION['login_attempts']      = 0;
    $_SESSION['login_lockout_until'] = 0;
    $login_attempts = 0;
}

// =========================================================================
// A. CEK SUPER ADMIN (Tabel 'admin') — BYPASS ROLES, Khanza AES Pattern
// =========================================================================
$sql_admin = "
    SELECT
        AES_DECRYPT(usere, 'nur') as usere,
        AES_DECRYPT(passworde, 'windi') as passworde
    FROM admin
    WHERE AES_DECRYPT(usere, 'nur') = ?
";

$stmt_admin = $koneksi->prepare($sql_admin);
$stmt_admin->bind_param("s", $username);
$stmt_admin->execute();
$res_admin = $stmt_admin->get_result();

if ($res_admin->num_rows === 1) {
    $row_admin = $res_admin->fetch_assoc();

    if ($row_admin['passworde'] === $password_input) {
        // ✅ Login berhasil sebagai Super Admin
        session_regenerate_id(true); // Anti-Session Fixation

        // Reset throttle counter
        $_SESSION['login_attempts']      = 0;
        $_SESSION['login_lockout_until'] = 0;

        $_SESSION['user_id']   = $username;
        $_SESSION['nama_user'] = 'Super Admin';
        $_SESSION['is_admin']  = true;
        $_SESSION['role']      = 'Super Admin';

        // Normal Flow
        header("Location: ../dashboard.php");
        exit;
    }
}
$stmt_admin->close();

// =========================================================================
// B. CEK USER BIASA (Tabel 'user') — Wajib cek roles
// =========================================================================
$sql_user = "
    SELECT
        AES_DECRYPT(id_user, 'nur') as id_user,
        AES_DECRYPT(password, 'windi') as password,
        harian_menejemen,
        bulanan_menejemen,
        pegawai_admin
    FROM user
    WHERE AES_DECRYPT(id_user, 'nur') = ?
";

$stmt_user = $koneksi->prepare($sql_user);
$stmt_user->bind_param("s", $username);
$stmt_user->execute();
$res_user = $stmt_user->get_result();

if ($res_user->num_rows === 1) {
    $row_user = $res_user->fetch_assoc();

    if ($row_user['password'] === $password_input) {

        // --- VALIDASI AKSES DARI TABEL USER DIREKSI ---
        // WAJIB: Kedua kolom hak akses dashboard eksekutif ini harus diset 'true'
        if ($row_user['harian_menejemen'] === 'true' && $row_user['bulanan_menejemen'] === 'true') {
            // ✅ Login berhasil sebagai User Manajemen/Eksekutif
            session_regenerate_id(true); // Anti-Session Fixation

            // Reset throttle counter
            $_SESSION['login_attempts']      = 0;
            $_SESSION['login_lockout_until'] = 0;

            $_SESSION['user_id']  = $username;
            // Set role menjadi Manajemen jika lolos otentikasi
            $_SESSION['role']     = 'Manajemen';
            $_SESSION['is_admin'] = false;

            // --- FIX KRITIS: Query nama pakai Prepared Statement (no SQL Injection) ---
            // Cek dari tabel petugas
            $q_nama = $koneksi->prepare("SELECT nama FROM petugas WHERE nip = ?");
            $q_nama->bind_param("s", $username);
            $q_nama->execute();
            $r_nama = $q_nama->get_result();

            if ($r_nama->num_rows == 0) {
                $q_nama->close();
                // Cek dari tabel dokter jika tidak ada di petugas
                $q_nama = $koneksi->prepare("SELECT nm_dokter as nama FROM dokter WHERE kd_dokter = ?");
                $q_nama->bind_param("s", $username);
                $q_nama->execute();
                $r_nama = $q_nama->get_result();
            }

            if ($r_nama && $row_nama = $r_nama->fetch_assoc()) {
                $_SESSION['nama_user'] = $row_nama['nama'];
            } else {
                $_SESSION['nama_user'] = $username;
            }
            $q_nama->close();

            $stmt_user->close();
            header("Location: ../dashboard.php");
            exit;
        } else {
            // Kredensial benar, tetapi GAK PUNYA hak akses eksekutif
            $stmt_user->close();
            header("Location: ../index.php?error=no_access");
            exit;
        }
    }
}
$stmt_user->close();

// =========================================================================
// C. GAGAL LOGIN — Tambah counter throttle
// =========================================================================
$login_attempts++;
$_SESSION['login_attempts'] = $login_attempts;

if ($login_attempts >= 6) {
    // Kunci akun selama 60 detik
    $_SESSION['login_lockout_until'] = $now + 60;
    header("Location: ../index.php?error=locked&sisa=60");
    exit;
}

// Redirect kembali ke login dengan info sisa percobaan
$sisa_percobaan = 6 - $login_attempts;
header("Location: ../index.php?error=1&sisa_coba=" . $sisa_percobaan);
exit;
?>