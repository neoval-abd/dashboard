<?php
// CLI integration check: all writes are rolled back, including existing plafon.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/config/koneksi.php';
require_once dirname(__DIR__) . '/config/kendali_biaya.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mode = $argv[1] ?? 'save';
$row = $koneksi->query("SELECT no_rawat, kd_pj FROM reg_periksa WHERE status_lanjut='Ranap' ORDER BY tgl_registrasi DESC LIMIT 1")->fetch_assoc();
if (!$row) { fwrite(STDERR, "No inpatient encounter available.\n"); exit(1); }
$no_rawat = $row['no_rawat'];
$before = kendali_biaya_plafon($koneksi, $no_rawat);
$koneksi->begin_transaction();
$stmt = $koneksi->prepare('INSERT INTO ' . kendali_biaya_table() . ' (no_rawat, nominal, updated_by) VALUES (?, 1000000, ?) ON DUPLICATE KEY UPDATE nominal=1000000');
$test_user = 'plafon-integration-test';
$stmt->bind_param('ss', $no_rawat, $test_user);
$stmt->execute();
$stmt->close();
$_SESSION['user_id'] = $test_user;
$_SESSION['plafon_csrf'] = 'test-token';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['csrf' => 'test-token', 'no_rawat' => $no_rawat, 'nominal' => '2500000'];
$expected_status = 200;
$expected_nominal = 2500000.0;
switch ($mode) {
    case 'save': break;
    case 'zero': $_POST['nominal'] = '0'; $expected_nominal = 0.0; break;
    case 'clear': $_POST['nominal'] = ''; $expected_nominal = null; break;
    case 'max': $_POST['nominal'] = '999999999999999'; $expected_nominal = 999999999999999.0; break;
    case 'csrf': $_POST['csrf'] = 'bad-token'; $expected_status = 403; break;
    case 'auth': unset($_SESSION['user_id']); $expected_status = 401; break;
    case 'method': $_SERVER['REQUEST_METHOD'] = 'GET'; $expected_status = 405; break;
    case 'negative': $_POST['nominal'] = '-1'; $expected_status = 422; break;
    case 'overflow': $_POST['nominal'] = '1000000000000000'; $expected_status = 422; break;
    case 'array': $_POST['nominal'] = ['100']; $expected_status = 422; break;
    case 'notfound': $_POST['no_rawat'] = '0000/00/00/000000'; $expected_status = 404; break;
    case 'estimate':
        $_GET = ['no_rawat' => $no_rawat, 'kd_pj' => $row['kd_pj']];
        $expected_nominal = 1000000.0;
        break;
    default: $koneksi->rollback(); exit(1);
}
ob_start();
register_shutdown_function(function() use ($mode, $koneksi, $no_rawat, $before, $expected_status, $expected_nominal) {
    $body = ob_get_clean();
    $json = json_decode($body, true);
    $ok = is_array($json) && (http_response_code() ?: 200) === $expected_status;
    $stored = kendali_biaya_plafon($koneksi, $no_rawat);
    if ($expected_status === 200) {
        $response_nominal = isset($json['plafon_raw']) ? (float) $json['plafon_raw'] : null;
        $ok = $ok && $stored === $expected_nominal && $response_nominal === $expected_nominal;
        if ($mode === 'estimate') {
            $ok = $ok && $json['has_plafon'] && $json['plafon_error'] === null
                && abs($json['selisih_raw'] - (1000000 - $json['estimasi_raw'])) < 0.001
                && $json['is_over'] === ($json['estimasi_raw'] > 1000000);
        } else $ok = $ok && $json['success'] === true;
    } else $ok = $ok && $json['success'] === false && $stored === 1000000.0;
    $koneksi->rollback();
    $ok = $ok && kendali_biaya_plafon($koneksi, $no_rawat) === $before;
    echo ($ok ? 'PASS ' : 'FAIL ') . $mode . " (rolled back)\n";
    if (!$ok) exit(1);
});
require dirname(__DIR__) . ($mode === 'estimate' ? '/api/hitung_estimasi_ranap.php' : '/api/simpan_plafon_ranap.php');
