<?php
/*
 * File: includes/keuangan.php
 * Deskripsi: Otorisasi & Hak Akses Modul Laporan Keuangan
 *            Memeriksa hak akses native SIMRS Khanza (tabel `user`, kolom `keuangan` = 'true')
 *            atau akun dengan role 'Super Admin'.
 */

if (!function_exists('keu_get_financial_pages')) {
    function keu_get_financial_pages()
    {
        return [
            'laporan_kas.php',
            'laporan_billing_global.php',
            'laporan_piutang_detail.php',
            'laporan_detail.php',
            'laporan_jasa_medis_baru.php',
            'laporan_tindakan.php',
            'laporan_analisa_lengkap.php',
        ];
    }
}

if (!function_exists('keu_can_access')) {
    function keu_can_access($db = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Super Admin selalu memiliki hak akses penuh
        if ((isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') || !empty($_SESSION['is_admin'])) {
            return true;
        }

        // Gunakan koneksi database yang diberikan atau dari global
        if (!$db && isset($GLOBALS['koneksi']) && ($GLOBALS['koneksi'] instanceof mysqli)) {
            $db = $GLOBALS['koneksi'];
        }

        if (!($db instanceof mysqli)) {
            return isset($_SESSION['keuangan']) && $_SESSION['keuangan'] === true;
        }

        // Cek langsung ke tabel user Khanza (kolom keuangan = 'true')
        try {
            $stmt = $db->prepare("SELECT keuangan FROM user WHERE AES_DECRYPT(id_user, 'nur') = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res && ($row = $res->fetch_assoc())) {
                        $hasAccess = (isset($row['keuangan']) && $row['keuangan'] === 'true');
                        $_SESSION['keuangan'] = $hasAccess;
                        $stmt->close();
                        return $hasAccess;
                    }
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log('[Keuangan Akses DB] ' . $e->getMessage());
        }

        return isset($_SESSION['keuangan']) && $_SESSION['keuangan'] === true;
    }
}

if (!function_exists('keu_filter_sidebar')) {
    function keu_filter_sidebar($menus, $can_access)
    {
        if (!is_array($menus)) {
            return [];
        }

        if ($can_access) {
            return $menus;
        }

        $financialPages = keu_get_financial_pages();
        $visible = [];

        foreach ($menus as $menu) {
            // 1. Jika menu merupakan grup 'Laporan Keuangan' (id: grp_keuangan)
            if (!empty($menu['is_group'])) {
                $groupId = $menu['id'] ?? '';
                $groupTitle = strtolower(trim((string)($menu['title'] ?? '')));
                if ($groupId === 'grp_keuangan' || $groupTitle === 'laporan keuangan') {
                    continue; // Sembunyikan seluruh grup Laporan Keuangan
                }

                if (isset($menu['items']) && is_array($menu['items'])) {
                    $menu['items'] = keu_filter_sidebar($menu['items'], false);
                    if (!$menu['items']) {
                        continue; // Jika item di dalam grup kosong setelah difilter, sembunyikan grup
                    }
                }
            } else {
                // 2. Jika menu individual yang mengarah ke salah satu file laporan keuangan
                $path = parse_url($menu['url'] ?? '', PHP_URL_PATH);
                $base = basename((string) $path);
                if (in_array($base, $financialPages, true)) {
                    continue;
                }
            }

            $visible[] = $menu;
        }

        return $visible;
    }
}

if (!function_exists('keu_require_access')) {
    function keu_require_access($db = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }

        $can = false;
        try {
            $can = keu_can_access($db);
        } catch (Throwable $e) {
            error_log('[Keuangan Akses] ' . $e->getMessage());
        }

        if (!$can) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Akses Ditolak</title>'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
                . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">'
                . '<style>'
                . 'body{background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;font-family:system-ui,-apple-system,sans-serif;}'
                . '.card-error{max-width:500px;border:1px solid rgba(255,255,255,0.1);border-radius:18px;box-shadow:0 20px 40px rgba(0,0,0,0.5);padding:36px 28px;text-align:center;background:rgba(30,41,59,0.95);backdrop-filter:blur(10px);}'
                . '.icon-lock{width:76px;height:76px;border-radius:50%;background:rgba(239,68,68,0.15);color:#ef4444;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;border:1px solid rgba(239,68,68,0.3);}'
                . '</style>'
                . '</head><body><div class="card-error">'
                . '<div class="icon-lock"><i class="fas fa-lock"></i></div>'
                . '<h3 class="fw-bold mb-2 text-white">Akses Laporan Keuangan Ditolak</h3>'
                . '<p class="text-secondary small mb-4" style="color:#94a3b8 !important;">Halaman ini khusus untuk pengguna yang memiliki hak akses keuangan (<code>keuangan = true</code> di SIMRS Khanza). Silakan hubungi bagian IT/Administrator jika Anda memerlukan akses ini.</p>'
                . '<a href="dashboard.php" class="btn btn-primary px-4 py-2 fw-semibold" style="border-radius:10px;"><i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard</a>'
                . '</div></body></html>';
            exit;
        }
    }
}
?>
