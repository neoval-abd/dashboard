<?php
// Jalankan: php tests/kepegawaian_readonly.php. Semua pengujian hanya membaca DB.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/config/koneksi.php';
require dirname(__DIR__) . '/includes/kepegawaian.php';
$mode = $argv[1] ?? '';
if (in_array($mode, ['--invalid-csrf', '--no-permission', '--read-no-permission', '--index-no-permission', '--invalid-data'], true)) {
    $_SESSION['user_id'] = '__kp_test_unknown_user__';
    $_SESSION['role'] = in_array($mode, ['--no-permission', '--read-no-permission', '--index-no-permission'], true) ? 'Manajemen' : 'Super Admin';
    $_SESSION['kp_csrf'] = 'test-token';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['action' => 'save', 'csrf' => $mode === '--invalid-csrf' ? 'bad-token' : 'test-token'];
    if ($mode === '--read-no-permission') { $_SERVER['REQUEST_METHOD'] = 'GET'; $_POST = []; $_GET = ['action' => 'list']; }
    if ($mode === '--index-no-permission') $_POST['action'] = 'save_index';
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
if ($date !== date('Y-m-d')) throw new RuntimeException('Tanggal awal harus memakai hari ini, bukan periode set_tahun.');
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
$durationCases = [
    ['2020-01-01', '2026-10-06', '6 Tahun 9 Bulan'],
    ['2020-01-23', '2026-10-06', '6 Tahun 8 Bulan'],
    ['2020-09-07', '2026-10-06', '6 Tahun 0 Bulan'],
    ['2020-12-31', '2026-10-06', '5 Tahun 9 Bulan'],
    ['2024-05-02', '2026-10-06', '2 Tahun 5 Bulan'],
    ['2020-01-23', '2021-02-28', '1 Tahun 1 Bulan'],
    ['2020-10-07', '2026-10-06', '5 Tahun 11 Bulan'],
    ['2020-10-07', '2026-10-07', '6 Tahun 0 Bulan'],
    ['2020-02-29', '2021-02-28', '0 Tahun 11 Bulan'],
    ['2020-02-29', '2021-03-01', '1 Tahun 0 Bulan'],
    ['2026-10-07', '2026-10-06', 'Belum mulai'],
    ['2026-10-06', '2026-10-06', '0 Tahun 0 Bulan'],
    ['0000-00-00', '2026-10-06', '-'],
    ['2020-02-30', '2026-10-06', '-'],
];
foreach ($durationCases as [$start, $reference, $expected]) {
    if (kp_duration($start, $reference) !== $expected) throw new RuntimeException('Durasi gagal: ' . $start . ' / ' . $reference);
}
// Sebelum ulang tahun kerja, tahun kabisat tidak boleh menaikkan index lebih awal.
$beforeAnniversary = kp_service_period('2020-10-07', '2026-10-06');
$onAnniversary = kp_service_period('2020-10-07', '2026-10-07');
if ($beforeAnniversary->y !== 5 || $onAnniversary->y !== 6) throw new RuntimeException('Batas tahun masa kerja gagal.');
$historic = kp_employee_list($koneksi, 'AKTIF', '', '', '2021-02-28');
foreach ($rows as $row) {
    if (kp_valid_date($row['mulai_kerja']) && $row['mulai_kerja'] <= $date && $row['lama_kerja'] === 'Belum mulai') throw new RuntimeException('Pegawai yang sudah mulai salah ditandai belum mulai.');
    if ($row['index_masa_kerja'] < 0 || $row['index_masa_kerja'] > 14) throw new RuntimeException('Index masa kerja di luar batas.');
}
if (count($historic) !== count($rows)) throw new RuntimeException('Tanggal acuan mengubah jumlah pegawai.');
$_SESSION['role'] = 'Super Admin';
if (!kp_can_write($koneksi)) throw new RuntimeException('Hak admin gagal.');
$_SESSION['role'] = 'Manajemen'; $_SESSION['user_id'] = '__kp_test_unknown_user__';
if (kp_can_write($koneksi)) throw new RuntimeException('Hak user gagal.');
echo "PASS: query master/index, filter, validasi, izin, durasi. Tidak ada data ditulis.\n";
session_destroy();
