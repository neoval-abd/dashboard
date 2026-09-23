<?php
/*
 * File: api/data_detail_bed.php
 * Fungsi: Menampilkan detail bed per kelas, termasuk bed kosong sesuai mapping SK.
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/config/bed_sk_mapping.php');

$req_kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$bed_in = getSkBedInSql($koneksi);

if ($bed_in === '') {
    echo json_encode(['data' => []]);
    exit;
}

// Ambil semua bed mapping SK/BPJS sebagai master, lalu tempelkan pasien aktif jika ada.
// Urutan: bed kosong paling atas, pasien aktif berikutnya berdasarkan tanggal/jam masuk terbaru.
$sql = "
    SELECT 
        ki.no_rawat, ki.tgl_masuk, ki.jam_masuk,
        p.nm_pasien, p.no_rkm_medis, pj.png_jawab,
        k.kd_kamar, b.nm_bangsal, k.kelas,
        IF(ki.no_rawat IS NULL, 0, 1) as is_terisi,
        DATEDIFF(NOW(), ki.tgl_masuk) as lama_hari
    FROM kamar k
    INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
    LEFT JOIN kamar_inap ki ON k.kd_kamar = ki.kd_kamar
        AND (ki.stts_pulang = '-' OR ki.stts_pulang = '')
    LEFT JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
    LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    WHERE k.kd_kamar IN ($bed_in)
    ORDER BY is_terisi ASC, ki.tgl_masuk DESC, ki.jam_masuk DESC, k.kd_kamar ASC
";

$stmt = $koneksi->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();

$filtered_data = [];

while($row = $res->fetch_assoc()) {
    $nm_bangsal = strtoupper($row['nm_bangsal']);
    $kelas_real = $row['kelas'];

    if (strpos($nm_bangsal, 'ISOLASI') !== false || strpos($nm_bangsal, 'ICU') !== false || 
        strpos($nm_bangsal, 'NICU') !== false || strpos($nm_bangsal, 'PICU') !== false || 
        strpos($nm_bangsal, 'HCU') !== false || strpos($nm_bangsal, 'PERINA') !== false) {
        $kelas_real = 'Kelas Khusus';
    }

    if ($req_kelas != $kelas_real) {
        continue;
    }

    if ((int)$row['is_terisi'] === 0) {
        $row['waktu_masuk'] = '-';
        $row['no_rkm_medis'] = '-';
        $row['nm_pasien'] = 'KOSONG';
        $row['png_jawab'] = '-';
        $row['lama_hari'] = '-';
    } else {
        $row['waktu_masuk'] = $row['tgl_masuk'] . ' ' . $row['jam_masuk'];
        if($row['lama_hari'] == 0) $row['lama_hari'] = 1;
    }

    $row['kelas'] = $kelas_real;
    $filtered_data[] = $row;
}

echo json_encode(['data' => $filtered_data]);
?>
