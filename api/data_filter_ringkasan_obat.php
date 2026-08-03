<?php
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'penggunaan';
$queries = $mode === 'penerimaan' ? [
    'jenis' => "SELECT nama AS value, nama AS label FROM jenis ORDER BY nama"
] : [
    'poli' => "SELECT kd_poli AS value, CONCAT(kd_poli, ' - ', nm_poli) AS label FROM poliklinik WHERE status='1' ORDER BY nm_poli",
    'jenis' => "SELECT kdjns AS value, CONCAT(kdjns, ' - ', nama) AS label FROM jenis ORDER BY nama",
    'kategori' => "SELECT kode AS value, CONCAT(kode, ' - ', nama) AS label FROM kategori_barang ORDER BY nama",
    'golongan' => "SELECT kode AS value, CONCAT(kode, ' - ', nama) AS label FROM golongan_barang ORDER BY nama"
];

$data = [];
foreach ($queries as $name => $sql) {
    $data[$name] = [];
    if ($result = $koneksi->query($sql)) while ($row = $result->fetch_assoc()) $data[$name][] = $row;
}
echo json_encode($data, JSON_UNESCAPED_UNICODE);
