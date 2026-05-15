<?php
/**
 * File: api/save_inacbg_selection.php
 * Deskripsi: Simpan pilihan INA-CBG untuk rawat inap agar tidak hilang setelah refresh.
 */

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

function sendResponse($success, $message = '', $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

$no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
$kode_inacbg  = isset($_POST['kode_inacbg']) ? trim($_POST['kode_inacbg']) : '';
$deskripsi    = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
$kelas       = isset($_POST['kelas']) ? trim($_POST['kelas']) : '';
$tarif       = isset($_POST['tarif']) ? intval($_POST['tarif']) : 0;

if ($no_rawat === '' || $kode_inacbg === '' || $tarif <= 0) {
    sendResponse(false, 'Data INA-CBG tidak lengkap.');
}

$no_rawat    = $koneksi->real_escape_string($no_rawat);
$kode_inacbg = $koneksi->real_escape_string($kode_inacbg);
$deskripsi   = $koneksi->real_escape_string($deskripsi);
$kelas       = $koneksi->real_escape_string($kelas);
$tarif       = intval($tarif);

$sql = "INSERT INTO ranap_inacbg_selection (no_rawat, kode_inacbg, deskripsi, kelas, tarif)
        VALUES ('$no_rawat', '$kode_inacbg', '$deskripsi', '$kelas', $tarif)
        ON DUPLICATE KEY UPDATE
            kode_inacbg = VALUES(kode_inacbg),
            deskripsi = VALUES(deskripsi),
            kelas = VALUES(kelas),
            tarif = VALUES(tarif),
            updated_at = CURRENT_TIMESTAMP";

if ($koneksi->query($sql) === false) {
    sendResponse(false, 'Gagal menyimpan pilihan INA-CBG.');
}

sendResponse(true, 'Pilihan INA-CBG tersimpan.', [
    'kode_inacbg' => $kode_inacbg,
    'deskripsi' => $deskripsi,
    'kelas' => $kelas,
    'tarif' => $tarif
]);
