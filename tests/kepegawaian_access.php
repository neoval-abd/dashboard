<?php
// Read-only access checks. No user permissions or employee data are changed.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/includes/kepegawaian.php';
$mode = $argv[1] ?? '';
$_SESSION['user_id'] = '__kp_test_unknown_user__';
$_SESSION['role'] = 'Manajemen';
if ($mode === '--page-denied') {
    ob_start();
    register_shutdown_function(function () {
        $body = ob_get_clean();
        $ok = http_response_code() === 403 && strpos($body, 'Akses Kepegawaian ditolak') !== false && strpos($body, 'id="kpTable"') === false;
        session_destroy();
        echo ($ok ? 'PASS' : 'FAIL') . ": direct page denied\n";
        if (!$ok) exit(1);
    });
    require dirname(__DIR__) . '/kepegawaian.php';
    exit;
}
if ($mode === '--sidebar-denied') {
    $_SERVER['PHP_SELF'] = '/dashboardassalam/dashboard.php';
    ob_start();
    require dirname(__DIR__) . '/includes/header.php';
    require dirname(__DIR__) . '/includes/footer.php';
    // header.php has an inner buffer with its output validator.
    ob_end_flush();
    $body = ob_get_clean();
    if ($body === '' || strpos($body, 'href="kepegawaian.php"') !== false || strpos($body, 'href="laporan_absensi.php"') === false) throw new RuntimeException('Sidebar access filtering failed.');
    echo "PASS: sidebar hides Kepegawaian and retains Absensi\n";
    session_destroy();
    exit;
}
$menus = json_decode(file_get_contents(dirname(__DIR__) . '/config/sidebar_menu.json'), true);
$hidden = kp_filter_sidebar($menus, false);
if (strpos(json_encode($hidden), 'kepegawaian.php') !== false) throw new RuntimeException('Restricted menu remains visible.');
if (strpos(json_encode($hidden), 'laporan_absensi.php') === false) throw new RuntimeException('Unrelated menu removed.');
if (kp_filter_sidebar($menus, true) !== $menus) throw new RuntimeException('Allowed menus changed.');
$onlyRestricted = [['is_group' => true, 'items' => [['url' => 'kepegawaian.php']]]];
if (kp_filter_sidebar($onlyRestricted, false)) throw new RuntimeException('Empty restricted group remains visible.');
if (kp_can_access($koneksi)) throw new RuntimeException('Unknown user allowed.');
foreach (['true', 'false'] as $permission) {
    $users = kp_rows($koneksi, "SELECT AES_DECRYPT(id_user, 'nur') AS username FROM user WHERE pegawai_admin = ? LIMIT 1", [$permission]);
    if (!$users) continue;
    $_SESSION['user_id'] = $users[0]['username'];
    if (kp_can_access($koneksi) !== ($permission === 'true')) throw new RuntimeException('Database permission not respected.');
}
$_SESSION['role'] = 'Super Admin';
if (!kp_can_access($koneksi)) throw new RuntimeException('Super Admin denied.');
unset($_SESSION['user_id']);
if (kp_can_access($koneksi)) throw new RuntimeException('Unauthenticated session allowed.');
session_destroy();
echo "PASS: menu filtering and database access permissions\n";
