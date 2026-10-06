<?php
// Jalankan: php tests/kepegawaian_readonly.php. Semua pengujian hanya membaca DB.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/config/koneksi.php';
require dirname(__DIR__) . '/includes/kepegawaian.php';
$mode = $argv[1] ?? '';
if (in_array($mode, ['--invalid-csrf', '--no-permission', '--invalid-data'], true)) {
    $_SESSION['user_id'] = '__kp_test_unknown_user__';
    $_SESSION['role'] = $mode === '--no-permission' ? 'Manajemen' : 'Super Admin';
    $_SESSION['kp_csrf'] = 'test-token';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['action' => 'save', 'csrf' => $mode === '--invalid-csrf' ? 'bad-token' : 'test-token'];
    // Validasi wajib isi gagal sebelum begin_transaction; tidak ada INSERT/UPDATE.
    ob_start();
    require dirname(__DIR__) . '/api/data_kepegawaian.php';
    $result = json_decode(ob_get_clean(), true);
    $expected = $mode === '--invalid-data' ? 422 : 403;
    if (http_response_code() !== $expected || !$result || $result['success'] !== false) throw new RuntimeException('Penolakan API gagal.');
    echo 'PASS: ', $mode, ' HTTP ', $expected, "\n";
    session_destroy();
    exit;
}
$options = kp_options($koneksi);
$date = kp_reference_date($koneksi);
$rows = kp_employee_list($koneksi, 'AKTIF', '', '', $date);
echo json_encode(['options_count' => count($options), 'reference_date' => $date, 'active_rows' => count($rows)]), "\n";
$all = kp_employee_list($koneksi, '', '', '', $date);
if (count($all) < count($rows)) throw new RuntimeException('Filter status gagal.');
if ($rows) {
    $match = kp_employee_list($koneksi, 'AKTIF', $rows[0]['nik'], $rows[0]['departemen'], $date);
    if (!$match) throw new RuntimeException('Filter NIP/departemen gagal.');
    $input = array_intersect_key($rows[0], kp_fields());
    kp_validate_employee($input, $options);
    foreach (['nama' => str_repeat('a', 51), 'tgl_lahir' => '2026-02-30', 'jk' => 'invalid', 'departemen' => 'invalid', 'wajibmasuk' => '-6'] as $key => $bad) {
        try { kp_validate_employee(array_replace($input, [$key => $bad]), $options); throw new RuntimeException('Validasi ' . $key . ' gagal.'); }
        catch (InvalidArgumentException $expected) { }
    }
    $sum = 0;
    foreach (['indek','index_pendidikan','index_masa_kerja','index_status','index_jabatan','index_kelompok','index_resiko','index_emergency','index_evaluasi','index_pencapaian'] as $field) $sum += (float) $rows[0][$field];
    $expectedTotal = $rows[0]['pengurang'] > 0 ? $sum * $rows[0]['pengurang'] / 100 : $sum;
    if (abs($expectedTotal - $rows[0]['total_index']) > 0.00001) throw new RuntimeException('Total index gagal.');
}
if (kp_employee_list($koneksi, 'AKTIF', "%' OR 1=1 --", '', $date)) throw new RuntimeException('Filter literal gagal.');
if (kp_duration('2020-01-01', '2026-10-05') !== '6 Tahun 9 Bulan') throw new RuntimeException('Durasi gagal.');
$_SESSION['role'] = 'Super Admin';
if (!kp_can_write($koneksi)) throw new RuntimeException('Hak admin gagal.');
$_SESSION['role'] = 'Manajemen'; $_SESSION['user_id'] = '__kp_test_unknown_user__';
if (kp_can_write($koneksi)) throw new RuntimeException('Hak user gagal.');
echo "PASS: query master/index, filter, validasi, izin, durasi. Tidak ada data ditulis.\n";
session_destroy();
