<?php
/*
 * File: api/search_inacbg.php (V2 - FUZZY SEARCH)
 * Perbaikan:
 *  1. Menerima parameter `qn` (normalised: tanpa tanda hubung, uppercase)
 *  2. Query menggunakan REPLACE() untuk menghapus '-' dari kode_inacbg
 *     sehingga "a4101" tetap cocok dengan "A-4-10-I"
 *  3. Fallback: jika qn tersedia, gunakan itu; jika tidak, gunakan q biasa
 */

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

// Ambil kedua parameter — q (raw) dan qn (normalised)
$q  = isset($_GET['q'])  ? trim($_GET['q'])  : '';
$qn = isset($_GET['qn']) ? trim($_GET['qn']) : '';

// Normalise server-side juga: hapus non-alphanumeric, uppercase
function normalise_code($str) {
    return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $str));
}

// Gunakan qn jika tersedia, fallback ke normalise($q)
$search_normalised = ($qn !== '') ? strtoupper($qn) : normalise_code($q);
$search_raw        = $koneksi->real_escape_string($q);
$search_norm_esc   = $koneksi->real_escape_string($search_normalised);

$sql = "SELECT id, kode_inacbg, deskripsi, jenis_rawat, tarif_kelas1, tarif_kelas2, tarif_kelas3, tarif_vip
        FROM est_biaya_inacbg
        WHERE is_aktif = 1";

if ($search_normalised !== '' || $search_raw !== '') {
    /*
     * Strategi pencarian (prioritas berurutan):
     * 1. Kode EXACT (setelah normalisasi) → ditampilkan paling atas
     * 2. Kode STARTS WITH normalised string
     * 3. Kode mengandung normalised string (fuzzy kode)
     * 4. Deskripsi mengandung raw query
     *
     * Implementasi: gunakan REPLACE(kode_inacbg, '-', '') untuk strip tanda hubung
     * lalu bandingkan dengan $search_normalised (yang sudah di-strip juga).
     */
    $sql .= " AND (
        REPLACE(REPLACE(kode_inacbg, '-', ''), ' ', '') LIKE '%{$search_norm_esc}%'
        OR deskripsi LIKE '%{$search_raw}%'
    )";
}

/*
 * Urutkan: kode yang DIAWALI dengan normalised string muncul lebih dulu,
 * baru sisanya (deskripsi match). Ini membuat hasil terasa lebih relevan.
 */
if ($search_normalised !== '') {
    $sql .= " ORDER BY
        CASE WHEN REPLACE(REPLACE(kode_inacbg, '-', ''), ' ', '') LIKE '{$search_norm_esc}%' THEN 0 ELSE 1 END ASC,
        kode_inacbg ASC
        LIMIT 50";
} else {
    $sql .= " ORDER BY kode_inacbg ASC LIMIT 50";
}

$res  = $koneksi->query($sql);
$data = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $data[] = [
            'id'          => $row['id'],
            'kode_inacbg' => $row['kode_inacbg'],
            'deskripsi'   => $row['deskripsi'],
            'jenis_rawat' => $row['jenis_rawat'],
            'tarif_kelas1'=> $row['tarif_kelas1'],
            'tarif_kelas2'=> $row['tarif_kelas2'],
            'tarif_kelas3'=> $row['tarif_kelas3'],
            'tarif_vip'   => $row['tarif_vip'],
        ];
    }
}

echo json_encode(['data' => $data]);