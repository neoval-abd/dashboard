<?php
/*
 * File: install_roles.php
 * Deskripsi: Halaman First-Time Onboarding untuk membuat tabel instalasi 'roles'
 * di tabel database Khanza.
 */

session_start();
require_once 'config/koneksi.php';

// Validasi Keamanan: HARUS Super Admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("<div style='text-align:center; margin-top:50px;'><h3>⛔ AKSES DITOLAK</h3><p>Halaman ini khusus instalasi awal (Root Privileges).</p></div>");
}

$page_title = "Onboarding - Setup Database Dashboard";
// Variabel untuk menampung pesan instalasi
$install_status = "";
$is_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    // Jalankan skema pembuatan roles
    $sql_create = "CREATE TABLE IF NOT EXISTS `roles` (
        `username` varchar(20) NOT NULL,
        `role` varchar(50) DEFAULT NULL,
        `cap` varchar(255) DEFAULT NULL,
        `module` text DEFAULT NULL,
        PRIMARY KEY (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    // Matikan report error mysqli mentah sementara agar bisa kita tangkap
    mysqli_report(MYSQLI_REPORT_OFF);

    $execute = $koneksi->query($sql_create);

    if ($execute) {
        $is_success = true;
        // Langsung redirect
        header("Location: manage_users.php?msg=install_success");
        exit;
    } else {
        // Tangkap Error!
        $error_msg = $koneksi->error;
        if (strpos(strtolower($error_msg), 'denied') !== false || strpos(strtolower($error_msg), 'command denied') !== false) {
            $install_status = "
            <div class='alert alert-danger shadow-sm border-start border-4 border-danger text-start'>
                <h5 class='fw-bold text-danger'><i class='fas fa-ban me-2'></i>Akses Database Ditolak (Read-Only User)</h5>
                <p class='text-dark'>Sistem mendeteksi bahwa pengguna SQL yang Anda pakai di <code>config/koneksi.php</code> <b>tidak memiliki hak akses (Privilege) CREATE</b> untuk membangun tabel baru di dalam database ini.</p>
                <hr>
                <p class='mb-2 fw-bold text-dark'>🛠️ Solusi Instalasi Cepat bagi IT:</p>
                <ol class='mb-0 text-dark'>
                    <li>Buka aplikasi manajemen database Anda (<b>Navicat</b> atau <b>HeidiSQL</b>).</li>
                    <li>Ubah _privilege_ pengguna SQL tersebut agar sementara waktu ditambahkan kemampuan <b>CREATE</b> dan <b>CRUD</b>.</li>
                    <li>Selesai merapihkan izin di Navicat, kembali ke layar ini dan klik lagi tombol <b>Jalankan Setup Instalasi</b> di bawah.</li>
                    <li>(Opsional) Jika berhasil, Anda dapat mengunci lagi (*Read-Only*) _user_ SQL tersebut demi mengejar kepatuhan kebijakan RS Anda.</li>
                </ol>
                <div class='mt-3 small text-muted font-monospace bg-light p-2 rounded border'>*System Response: <code>$error_msg</code></div>
            </div>";
        } else {
            $install_status = "
            <div class='alert alert-danger shadow-sm text-start'>
                <i class='fas fa-exclamation-triangle me-2'></i> Gagal mengeksekusi Query. Sistem mengembalikan pesan: <br><code>$error_msg</code>
            </div>";
        }
    }
}

// Menghindari error apabila header meload hal yang sama
require_once 'includes/header.php';
?>

<div class="container-fluid d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="glass-card p-5 mt-4" style="max-width: 750px; text-align: center; border: 1px solid rgba(13, 110, 253, 0.3);">
        <div class="mb-4">
            <i class="fas fa-database text-primary" style="font-size: 5rem; text-shadow: 0px 4px 15px rgba(13, 110, 253, 0.4);"></i>
        </div>
        <h2 class="fw-bold mb-2">Halo, Tim IT! 👋</h2>
        <h5 class="text-muted mb-4">Selamat datang di Instalasi Awal Eksekutif Dashboard</h5>

        <?= $install_status ?>

        <div class="alert text-start mb-4 shadow-sm" style="background-color: var(--card-bg); border: 1px solid rgba(13, 110, 253, 0.2); color: var(--table-text);">
            <p>Untuk mengamankan akses ke seluruh fitur tingkat tinggi (_premium_) di panel ini, Dashboard Eksekutif membutuhkan sebuah tabel khusus pendamping (bernama <code>roles</code>) yang akan mendompleng ke dalam barisan _database_ SIMRS Khanza (Sik) milik RS Anda.</p>
            <p class="mb-2 mx-0 fw-bold">Dengan menekan tombol di bawah, sistem *Auto-Installer* akan:</p>
            <ul class="mb-0">
                <li>Mencetak pondasi (tabel <code>roles</code>) secara otomatis tanpa merusk struktur tabel yang sudah ada.</li>
                <li>Tidak mengganggu parameter maupun fungsi inti SIMRS.</li>
                <li>Setelah rampung, Anda otomatis akan diarahkan ke panel <b>Manajemen User</b> untuk menunjuk NIK petugas mana yang boleh menyandang hak Manajemen.</li>
            </ul>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="install">
            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm" style="border-radius: 12px; font-size: 1.1rem; padding: 15px; transition: transform 0.2s;">
                <i class="fas fa-magic me-2"></i> Jalankan Setup Tembak Instalasi (Otomatis)
            </button>
        </form>
    </div>
</div>

<?php 
// Tambahan CSS spesifik 
ob_start(); 
?>
<style>
    .glass-card {
        background: var(--card-bg) !important;
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
        color: var(--table-text);
    }
    .btn-primary:active {
        transform: scale(0.98);
    }
</style>
<?php 
$page_js = ob_get_clean();
require_once 'includes/footer.php'; 
?>
