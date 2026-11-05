<?php
// Read-only access checks for Laporan Keuangan module.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/includes/keuangan.php';

$mode = $argv[1] ?? '';
$_SESSION['user_id'] = '__keu_test_unauthorized_user__';
$_SESSION['role'] = 'Manajemen';
$_SESSION['is_admin'] = false;
unset($_SESSION['keuangan']);

// Test 1: Direct page access denial
if ($mode === '--page-denied') {
    $targetFile = $argv[2] ?? 'laporan_kas.php';
    ob_start();
    register_shutdown_function(function () use ($targetFile) {
        $body = ob_get_clean();
        $ok = http_response_code() === 403 && strpos($body, 'Akses Laporan Keuangan Ditolak') !== false;
        session_destroy();
        echo ($ok ? 'PASS' : 'FAIL') . ": direct page denied for {$targetFile}\n";
        if (!$ok) exit(1);
    });
    require dirname(__DIR__) . '/' . $targetFile;
    exit;
}

// Test 2: Sidebar filtering
$menus = json_decode(file_get_contents(dirname(__DIR__) . '/config/sidebar_menu.json'), true);

$hidden = keu_filter_sidebar($menus, false);
$hiddenJson = json_encode($hidden);

// All financial report URLs must be hidden
foreach (keu_get_financial_pages() as $fnPage) {
    if (strpos($hiddenJson, $fnPage) !== false) {
        throw new RuntimeException("Restricted financial page remains visible: {$fnPage}");
    }
}
if (strpos($hiddenJson, 'grp_keuangan') !== false) {
    throw new RuntimeException('Restricted group grp_keuangan remains visible.');
}

// Unrelated menus must remain visible
if (strpos($hiddenJson, 'laporan_absensi.php') === false) {
    throw new RuntimeException('Unrelated menu laporan_absensi.php was mistakenly removed.');
}
if (strpos($hiddenJson, 'kunjungan_ralan.php') === false) {
    throw new RuntimeException('Unrelated menu kunjungan_ralan.php was mistakenly removed.');
}

// With access granted, all menus must be preserved
$allowed = keu_filter_sidebar($menus, true);
if ($allowed !== $menus) {
    throw new RuntimeException('Allowed menus were modified when can_access is true.');
}

// Test empty group removal
$onlyRestricted = [['is_group' => true, 'id' => 'grp_test', 'title' => 'Test', 'items' => [['url' => 'laporan_kas.php']]]];
if (keu_filter_sidebar($onlyRestricted, false)) {
    throw new RuntimeException('Empty restricted group remains visible after its items were filtered.');
}

// Test unauthorized user
if (keu_can_access($koneksi)) {
    throw new RuntimeException('Unauthorized test user was allowed access.');
}

// Test database query permission matching: find a real user with keuangan = 'true' and one with 'false'
$qTrue = $koneksi->query("SELECT AES_DECRYPT(id_user, 'nur') AS username FROM user WHERE keuangan = 'true' LIMIT 1");
if ($qTrue && ($rTrue = $qTrue->fetch_assoc())) {
    $_SESSION['user_id'] = $rTrue['username'];
    $_SESSION['role'] = 'Manajemen';
    unset($_SESSION['keuangan']);
    if (!keu_can_access($koneksi)) {
        throw new RuntimeException("User with keuangan = 'true' ({$rTrue['username']}) was denied access.");
    }
}

$qFalse = $koneksi->query("SELECT AES_DECRYPT(id_user, 'nur') AS username FROM user WHERE keuangan = 'false' AND harian_menejemen = 'true' LIMIT 1");
if ($qFalse && ($rFalse = $qFalse->fetch_assoc())) {
    $_SESSION['user_id'] = $rFalse['username'];
    $_SESSION['role'] = 'Manajemen';
    unset($_SESSION['keuangan']);
    if (keu_can_access($koneksi)) {
        throw new RuntimeException("User with keuangan = 'false' ({$rFalse['username']}) was improperly granted access.");
    }
}

// Test Super Admin
$_SESSION['role'] = 'Super Admin';
if (!keu_can_access($koneksi)) {
    throw new RuntimeException('Super Admin was denied financial access.');
}

// Test unauthenticated session
unset($_SESSION['user_id']);
unset($_SESSION['role']);
if (keu_can_access($koneksi)) {
    throw new RuntimeException('Unauthenticated session was allowed financial access.');
}

session_destroy();
echo "PASS: financial menu filtering, database permissions, and role checks\n";
