<?php
/*
 * File: api/data_detail_bed.php
 * Fungsi: Menampilkan detail bed per kelas, termasuk bed kosong sesuai mapping SK.
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/config/bed_sk_mapping.php');

$req_kelas   = isset($_GET['kelas']) ? trim($_GET['kelas']) : '';
$req_lantai  = isset($_GET['lantai']) ? (int)$_GET['lantai'] : 0;
$req_ruangan = isset($_GET['ruangan']) ? trim($_GET['ruangan']) : '';
$bed_in      = getSkBedInSql($koneksi);
$floor_map   = getSkBedFloorMapping();

if ($bed_in === '') {
    echo json_encode(['data' => []]);
    exit;
}

// Ambil semua bed mapping SK/BPJS sebagai master, lalu tempelkan pasien aktif jika ada.
// Urutan: Pasien aktif duluan (tgl_masuk terbaru), lalu bed terbooking, lalu bed kosong
$sql = "
    SELECT 
        ki.no_rawat, ki.tgl_masuk, ki.jam_masuk,
        p.nm_pasien, p.no_rkm_medis, pj.png_jawab,
        k.kd_kamar, b.nm_bangsal, k.kelas, k.status as status_kamar,
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
    ORDER BY is_terisi DESC, ki.tgl_masuk DESC, ki.jam_masuk DESC, k.kd_kamar ASC
";

$res = $koneksi->query($sql);
$filtered_data = [];

if ($res) {
    while($row = $res->fetch_assoc()) {
        $kd_kamar   = $row['kd_kamar'];
        $nm_bangsal = strtoupper($row['nm_bangsal']);
        $kelas_real = $row['kelas'];
        $finfo      = isset($floor_map[$kd_kamar]) ? $floor_map[$kd_kamar] : null;
        
        $lantai        = $finfo ? $finfo['lantai'] : 0;
        $nama_ruangan  = $finfo ? $finfo['ruangan'] : $row['nm_bangsal'];
        $ruangan_group = $finfo ? $finfo['ruangan_group'] : $row['nm_bangsal'];
        $is_kris       = $finfo ? $finfo['kris'] : false;

        if (strpos($nm_bangsal, 'ISOLASI') !== false || strpos($nm_bangsal, 'ICU') !== false || 
            strpos($nm_bangsal, 'NICU') !== false || strpos($nm_bangsal, 'PICU') !== false || 
            strpos($nm_bangsal, 'HCU') !== false || strpos($nm_bangsal, 'PERINA') !== false) {
            $kelas_real = 'Kelas Khusus';
        }

        // 1. Filter Kelas (jika ditentukan)
        if ($req_kelas !== '' && $req_kelas !== 'all' && $req_kelas !== $kelas_real) {
            continue;
        }

        // 2. Filter Lantai (jika ditentukan)
        if ($req_lantai > 0 && $lantai !== $req_lantai) {
            continue;
        }

        // 3. Filter Ruangan / Group (jika ditentukan)
        if ($req_ruangan !== '' && $req_ruangan !== 'all') {
            $match = (strcasecmp($nama_ruangan, $req_ruangan) === 0) ||
                     (strcasecmp($ruangan_group, $req_ruangan) === 0);
            if (!$match) {
                continue;
            }
        }

        $status_kamar = strtoupper($row['status_kamar']);

        if ((int)$row['is_terisi'] === 0) {
            $row['waktu_masuk']  = '-';
            $row['no_rkm_medis'] = '-';
            $row['nm_pasien']    = ($status_kamar === 'DIBOOKING') ? 'DIBOOKING' : 'KOSONG';
            $row['png_jawab']    = '-';
            $row['lama_hari']    = '-';
        } else {
            $row['waktu_masuk'] = $row['tgl_masuk'] . ' ' . $row['jam_masuk'];
            if((int)$row['lama_hari'] <= 0) $row['lama_hari'] = 1;
        }

        $row['lantai']        = $lantai;
        $row['nama_ruangan']  = $nama_ruangan;
        $row['ruangan_group'] = $ruangan_group;
        $row['is_kris']       = $is_kris;
        $row['status_bed']    = $status_kamar;
        $row['kelas']         = $kelas_real;
        $row['kelas_sk']      = $finfo ? $finfo['kelas'] : $kelas_real;

        $filtered_data[] = $row;
    }
}

echo json_encode(['data' => $filtered_data]);
?>
