<?php
/*
 * File: manage_users.php
 * Deskripsi: Utility untuk Manage Hak Akses Eksekutif via tabel user
 */

// --- KONFIGURASI AKSES ---
// Masukkan NIK/Username Super Admin yang diizinkan mengakses halaman ini
$super_admins = ['rsuadella']; 

session_start();
require_once('config/koneksi.php');

// Cek apakah user yang login adalah Super Admin
$current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['username']) ? $_SESSION['username'] : '');

if (!in_array($current_user, $super_admins) && (!isset($_SESSION['role']) || $_SESSION['role'] != 'Super Admin')) {
    die("<div style='text-align:center; margin-top:50px;'><h3>⛔ AKSES DITOLAK</h3><p>Halaman ini khusus untuk Super Admin IT.</p><a href='index.php'>Kembali</a></div>");
}

// PROSES FORM (SIMPAN/HAPUS)
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'];
    
    if ($act == 'save') {
        $username = $koneksi->real_escape_string($_POST['username']);

        if (!empty($username)) {
            // Kita update langsung ke tabel user khanza menggunakan enkripsi aes
            $sql = "UPDATE user SET harian_menejemen = 'true', bulanan_menejemen = 'true' WHERE id_user = AES_ENCRYPT('$username', 'nur')";
            if ($koneksi->query($sql)) {
                $msg = "<div class='alert alert-success'>Akses Eksekutif untuk <b>$username</b> berhasil diberikan.</div>";
            } else {
                $msg = "<div class='alert alert-danger'>Gagal menyimpan: " . $koneksi->error . "</div>";
            }
        }
    } elseif ($act == 'delete') {
        $username = $koneksi->real_escape_string($_POST['username_del']);
        $id_user_hex = isset($_POST['id_user_hex']) ? $koneksi->real_escape_string($_POST['id_user_hex']) : '';
        
        // Gunakan UNHEX jika id_user_hex tersedia untuk delete absolut (mengatasi bug akun tanpa enkripsi standard)
        if (!empty($id_user_hex)) {
            $sql = "UPDATE user SET harian_menejemen = 'false', bulanan_menejemen = 'false' WHERE id_user = UNHEX('$id_user_hex')";
        } else {
            $sql = "UPDATE user SET harian_menejemen = 'false', bulanan_menejemen = 'false' WHERE id_user = AES_ENCRYPT('$username', 'nur')";
        }

        if ($koneksi->query($sql)) {
            $msg = "<div class='alert alert-warning'>Akses Eksekutif untuk <b>" . htmlspecialchars($username) . "</b> telah dicabut.</div>";
        } else {
            $msg = "<div class='alert alert-danger'>Gagal mencabut: " . $koneksi->error . "</div>";
        }
    }
}

$page_title = "Manajemen Akses Eksekutif";
require_once('includes/header.php');
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-user-shield me-2"></i>Pengaturan Hak Akses Eksekutif</h1>
    
    <?= $msg; ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow border-left-primary">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Berikan Akses Baru</h6>
                </div>
                <div class="card-body">
                    
                    <div class="alert alert-info" style="font-size: 0.85rem;">
                        <i class="fas fa-info-circle me-1"></i> <strong>Info untuk IT / Super Admin:</strong><br>
                        Ketika Anda menambahkan NIK pengguna di sini, sistem akan secara mandiri mengupdate tabel <code>user</code> di database SIMKES Khanza untuk mengubah kolom <code>harian_menejemen</code> dan <code>bulanan_menejemen</code> secara bersamaan menjadi <b>'true'</b>. Mode ini dijamin native dan memusatkan sumber otoritas (Zero-Trust Architecture).
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="act" value="save">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Cari Pegawai (Username/NIP)</label>
                            <select class="form-select" id="select_pegawai" name="username" required>
                                <option value="">-- Cari Nama/NIK --</option>
                            </select>
                            <small class="text-muted">Ketik Nama atau NIK Pegawai dari database Khanza.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check-circle me-1"></i> Jadikan Eksekutif</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Daftar Akun yang Diberi Akses Eksekutif</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="tableUsers" width="100%" cellspacing="0">
                            <thead class="table-light">
                                <tr>
                                    <th>Username (NIK)</th>
                                    <th>Nama Pegawai</th>
                                    <th>Status Akses</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Mengambil data user yang memiliki harian_menejemen & bulanan_menejemen = 'true'
                                $sql_list = "
                                    SELECT HEX(u.id_user) as id_user_hex, AES_DECRYPT(u.id_user, 'nur') as id_user_dec, p.nama 
                                    FROM user u 
                                    LEFT JOIN pegawai p ON AES_DECRYPT(u.id_user, 'nur') = p.nik 
                                    WHERE u.harian_menejemen = 'true' AND u.bulanan_menejemen = 'true'
                                ";
                                $q = $koneksi->query($sql_list);
                                while($row = $q->fetch_assoc()) {
                                    $username_esc = htmlspecialchars($row['id_user_dec'] ?? 'ERROR (Data Tidak Wajar/Invalid Encryption)');
                                    $nama_esc = htmlspecialchars($row['nama'] ?? 'User Tidak Dikenal (Bukan Pegawai)');
                                ?>
                                <tr>
                                    <td><?= $username_esc ?></td>
                                    <td><?= $nama_esc ?></td>
                                    <td>
                                        <span class="badge bg-success">Harian & Bulanan Menejemen (native)</span>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" onsubmit="return confirm('Cabut akses Eksekutif untuk user ini? Status harian & bulanan menejemen akan di-set menjadi false.');" style="display:inline;">
                                            <input type="hidden" name="act" value="delete">
                                            <input type="hidden" name="username_del" value="<?= $username_esc ?>">
                                            <input type="hidden" name="id_user_hex" value="<?= htmlspecialchars($row['id_user_hex']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Cabut Akses"><i class="fas fa-trash-alt me-1"></i> Cabut</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    // Inisialisasi DataTable
    $('#tableUsers').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/id.json',
        }
    });

    // Inisialisasi Select2 dengan AJAX
    $('#select_pegawai').select2({
        theme: 'bootstrap-5',
        placeholder: 'Ketik Nama atau NIK Pegawai...',
        allowClear: true,
        minimumInputLength: 3, // Minimal ketik 3 huruf baru mencari
        ajax: {
            url: 'api/ajax_pegawai.php',
            dataType: 'json',
            delay: 250,
            global: false, // Mematikan global interceptor AJAX (mencegah tirai loading muncul berulang)
            data: function (params) {
                return { q: params.term }; // Kirim parameter 'q'
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        }
    });
});
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>