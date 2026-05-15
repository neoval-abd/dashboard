<?php
/*
 * File: index.php (SECURITY HARDENED v3)
 * - TAMBAHAN: Generate CSRF Token (sekali per load halaman)
 * - TAMBAHAN: Tampilkan pesan lockout dengan countdown jika brute-force terdeteksi
 * - TAMBAHAN: Tampilkan sisa percobaan login
 * - Credit footer Ichsan Leonhart (diperlukan agar terlihat bahkan di halaman login)
 */

// Require koneksi.php untuk session dan nama instansi
if (file_exists('config/koneksi.php')) {
    require_once('config/koneksi.php');
}

// === GENERATE CSRF TOKEN ===
// Token baru di-generate setiap kali halaman login dimuat (one-time use)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// === STATUS LOGIN THROTTLE ===
$lockout_until  = isset($_SESSION['login_lockout_until']) ? (int)$_SESSION['login_lockout_until'] : 0;
$login_attempts = isset($_SESSION['login_attempts'])      ? (int)$_SESSION['login_attempts']      : 0;
$now            = time();

// Hitung sisa waktu lockout (dari session, lebih akurat dari GET parameter)
$sisa_lockout = ($lockout_until > $now) ? ($lockout_until - $now) : 0;

// Sisa percobaan dari GET parameter (fallback)
$sisa_coba = isset($_GET['sisa_coba']) ? (int)$_GET['sisa_coba'] : (6 - $login_attempts);
$sisa_coba = max(0, min(6, $sisa_coba)); // clamp 0-6

// === NAMA INSTANSI ===
$nama_instansi = "Rumah Sakit"; // Default fallback
if (isset($koneksi)) {
    $sql_setting = "SELECT nama_instansi FROM setting LIMIT 1";
    $result_setting = $koneksi->query($sql_setting);
    if ($result_setting && $result_setting->num_rows > 0) {
        $row_setting = $result_setting->fetch_assoc();
        $nama_instansi = htmlspecialchars($row_setting['nama_instansi']);
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?php echo $nama_instansi; ?></title>

    <link rel="icon" href="core/logo.php" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        /* Card Login */
        .login-card {
            max-width: 420px;
            width: 100%;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            color: #fff;
            padding: 2.5rem;
        }

        .login-logo {
            max-width: 72px;
            height: auto;
            margin-bottom: 12px;
            filter: drop-shadow(0 4px 12px rgba(99, 179, 237, 0.5));
        }

        .login-card h5 {
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .login-card .text-muted, .login-card small {
            color: rgba(255,255,255,0.55) !important;
        }

        .form-label {
            color: rgba(255,255,255,0.8);
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .form-control {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 10px;
            padding: 0.65rem 1rem;
            transition: all 0.2s;
        }

        .form-control::placeholder { color: rgba(255,255,255,0.35); }
        .form-control:focus {
            background: rgba(255,255,255,0.15);
            border-color: rgba(99, 179, 237, 0.7);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 700;
            color: #0a0a1a;
            font-size: 0.95rem;
            transition: all 0.3s;
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 172, 254, 0.5);
            color: #0a0a1a;
        }

        hr { border-color: rgba(255,255,255,0.15); }

        .alert-danger-glass {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff8a9a;
            border-radius: 10px;
            padding: 0.65rem 1rem;
            font-size: 0.88rem;
        }

        .alert-warning-glass {
            background: rgba(255, 193, 7, 0.15);
            border: 1px solid rgba(255, 193, 7, 0.4);
            color: #ffd966;
            border-radius: 10px;
            padding: 0.8rem 1rem;
            font-size: 0.88rem;
        }

        .badge-attempt {
            display: inline-block;
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.6);
            margin-top: 0.5rem;
        }

        /* Credit footer login */
        .login-credit {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 0.78rem;
            color: rgba(255,255,255,0.45);
        }
        .login-credit a {
            color: rgba(99, 179, 237, 0.8);
            text-decoration: none;
        }
        .login-credit a:hover { color: #4facfe; text-decoration: underline; }

        /* Timeline CSS */
        .timeline { position: relative; padding: 1rem 0; margin: 0; }
        .timeline::before { content: ''; position: absolute; top: 0; bottom: 0; left: 20px; width: 2px; background: #dee2e6; }
        .timeline-item { position: relative; margin-bottom: 2rem; padding-left: 50px; }
        .timeline-item::before { content: ''; position: absolute; left: 14px; top: 5px; width: 14px; height: 14px; border-radius: 50%; background: #0d6efd; border: 3px solid #fff; box-shadow: 0 0 0 2px #0d6efd; z-index: 1; }
        .timeline-date { font-size: 0.85rem; font-weight: bold; color: #6c757d; margin-bottom: 0.5rem; display: block; }
        .timeline-content { background: #fff; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef; color: #212529; }
        .timeline-content h5 { font-size: 1rem; font-weight: 700; margin-bottom: 0; }
        .modal-changelog-body { background-color: #f8f9fa; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <img src="core/logo.php" alt="Logo RS" class="login-logo">
        <h5 class="mb-1"><?php echo $nama_instansi; ?></h5>
        <span class="text-muted small text-uppercase" style="letter-spacing:1px;">Dashboard Eksekutif</span>
    </div>

    <hr>

    <?php
    // Tampilkan pesan berdasarkan kondisi
    $error = isset($_GET['error']) ? $_GET['error'] : '';

    if ($sisa_lockout > 0 || $error === 'locked') {
        // Dalam kondisi lockout — tampilkan pesan cooldown
        $tampil_sisa = max($sisa_lockout, (int)($_GET['sisa'] ?? 60));
        echo '<div class="alert-warning-glass mb-3">
            <i class="fas fa-lock me-2"></i> <strong>Akses dikunci sementara!</strong><br>
            Terlalu banyak percobaan Login yang gagal. Silakan coba lagi dalam
            <strong><span id="countdown">' . $tampil_sisa . '</span> detik</strong>.
        </div>';
    } elseif ($error === 'no_access') {
        // User/password benar, tapi belum dikasih hak akses oleh Super Admin Server
        echo '<div class="alert-warning-glass mb-3">
            <i class="fas fa-exclamation-circle me-2"></i> <strong>Akun valid, namun belum berhak!</strong><br>
            Hak akses Dashboard Eksekutif Anda belum diaktifkan. Silakan hubungi <b>IT / Super Admin</b> untuk mencentang otoritas <em>Harian</em> dan <em>Bulanan Menejemen</em> Anda di sistem.
        </div>';
    } elseif ($error === '1') {
        // Login gagal biasa
        $hint_sisa = '';
        if ($sisa_coba > 0 && $login_attempts > 0) {
            $hint_sisa = '<br><span class="badge-attempt"><i class="fas fa-exclamation-triangle me-1"></i>Sisa percobaan: ' . $sisa_coba . ' kali lagi</span>';
        }
        echo '<div class="alert-danger-glass mb-3">
            <i class="fas fa-times-circle me-2"></i> Username atau Password salah!' . $hint_sisa . '
        </div>';
    }
    ?>

    <form action="core/login_process.php" method="POST" id="loginForm">
        <!-- CSRF Token — ditanam sebagai hidden field (Anti-CSRF Rule #0) -->
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

        <div class="mb-3">
            <label for="username" class="form-label">Username / NIP</label>
            <input type="text" class="form-control" id="username" name="username"
                   placeholder="Masukkan ID Pengguna" required autofocus
                   <?php echo ($sisa_lockout > 0) ? 'disabled' : ''; ?>>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="Masukkan Kata Sandi" required
                   <?php echo ($sisa_lockout > 0) ? 'disabled' : ''; ?>>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-login" id="btnLogin"
                    <?php echo ($sisa_lockout > 0) ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt me-2"></i> Masuk Aplikasi
            </button>
        </div>

        <div class="text-center mt-3">
            <a href="#" data-bs-toggle="modal" data-bs-target="#changelogModal"
               style="font-size:0.78rem; color:rgba(99,179,237,0.7); text-decoration:none;">
                <i class="fas fa-history me-1"></i>Riwayat Pengembangan
            </a>
        </div>
    </form>

    <!-- Credit Footer Login -->
    <div class="login-credit">
        Dikembangkan oleh <a href="https://saweria.co/ichsanleonhart" target="_blank" rel="noopener">Ichsan Leonhart</a><br>
        <a href="https://wa.me/6285726123777" target="_blank" rel="noopener"><i class="fab fa-whatsapp me-1"></i>6285726123777</a>
        &nbsp;|&nbsp;
        <a href="https://t.me/IchsanLeonhart" target="_blank" rel="noopener"><i class="fab fa-telegram me-1"></i>@IchsanLeonhart</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Countdown Timer untuk Lockout -->
<script>
(function() {
    var countdownEl = document.getElementById('countdown');
    var btnLogin    = document.getElementById('btnLogin');
    var usernameEl  = document.getElementById('username');
    var passwordEl  = document.getElementById('password');

    if (!countdownEl) return;

    var sisa = parseInt(countdownEl.textContent, 10);
    if (isNaN(sisa) || sisa <= 0) return;

    var timer = setInterval(function() {
        sisa--;
        countdownEl.textContent = sisa;

        if (sisa <= 0) {
            clearInterval(timer);
            // Aktifkan kembali form setelah cooldown habis
            if (btnLogin)    { btnLogin.removeAttribute('disabled'); }
            if (usernameEl)  { usernameEl.removeAttribute('disabled'); }
            if (passwordEl)  { passwordEl.removeAttribute('disabled'); }
            // Muat ulang token CSRF baru
            window.location.href = 'index.php';
        }
    }, 1000);
})();
</script>

<!-- Modal Changelog -->
<div class="modal fade" id="changelogModal" tabindex="-1" aria-labelledby="changelogModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="changelogModalLabel"><i class="fas fa-history me-2"></i>Riwayat Pengembangan Sistem</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body modal-changelog-body">
        <div class="p-2">
            <div id="changelog-container" class="timeline">
                <div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Memuat riwayat pengembangan...</div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var changelogModal = document.getElementById('changelogModal');
    if (changelogModal) {
        changelogModal.addEventListener('show.bs.modal', function () {
            var container = document.getElementById('changelog-container');
            if (container.dataset.loaded === 'true') return;
            fetch('change_log.md?v=' + new Date().getTime())
              .then(function(r) {
                  if(!r.ok) throw new Error("Gagal memuat log");
                  return r.text();
              })
              .then(function(text) {
                 var regex = /## \s*\[([^\]]+)\]\s*—\s*([^\n]+)\s+###\s*([^\n]+)\s+((?:-[^\n]+\s*)+)/g;
                 var matches = [], m;
                 while ((m = regex.exec(text)) !== null) matches.push(m);
                 if (matches.length === 0) {
                     container.innerHTML = '<div class="alert alert-warning">Belum ada catatan.</div>';
                     return;
                 }
                 matches.reverse();
                 var html = '';
                 matches.forEach(function(m) {
                      var version   = m[1];
                      var date      = m[2].trim();
                      var types     = m[3].trim();
                      var itemsText = m[4].trim();
                      var itemsHtml = itemsText.split('\n').filter(function(i){return i.trim()!=='';}).map(function(i){
                          var t = i.trim();
                          if (t.startsWith('-')) t = t.substring(1).trim();
                          t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                          return '<li class="mb-1">' + t + '</li>';
                      }).join('');
                      html += '<div class="timeline-item"><span class="timeline-date"><i class="far fa-clock me-1"></i>' + date + '</span><div class="timeline-content shadow-sm"><h5><span class="badge bg-primary me-2">' + version + '</span> <small class="text-muted" style="font-size:0.8rem;">' + types + '</small></h5><ul class="mb-0 mt-3 ps-3" style="font-size:0.9rem;color:#495057;">' + itemsHtml + '</ul></div></div>';
                 });
                 container.innerHTML = html;
                 container.dataset.loaded = 'true';
              })
              .catch(function(err) {
                  container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + err.message + '</div>';
              });
        });
    }
});
</script>
</body>
</html>