<?php
/*
 * File: api/data_dashboard.php (FIX V6 - OMZET CHART DATA)
 * - Fix: Menambahkan array 'labels' dan 'data' pada respon omzet agar Chart Donut muncul.
 * - Logic lainnya tetap sama (sudah robust).
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 
require_once(dirname(__DIR__) . '/config/bed_sk_mapping.php');

$today = date('Y-m-d');
$year = date('Y');
$tgl_awal_bulan = date('Y-m-01');

$response = [];

// ==========================================================================
// 1. OMZET HARI INI (FIX CHART DATA)
// ==========================================================================
$sql_omzet = "
    SELECT 
        'Ralan' as jenis,
        IF(pp.no_rawat IS NULL OR pp.status='Lunas', 'Tunai', 'Piutang') as status_bayar,
        COALESCE(SUM(b.totalbiaya), 0) as total
    FROM nota_jalan nj
    INNER JOIN billing b ON nj.no_rawat = b.no_rawat
    LEFT JOIN piutang_pasien pp ON nj.no_rawat = pp.no_rawat
    WHERE nj.tanggal = '$today'
    GROUP BY status_bayar

    UNION ALL

    SELECT 
        'Ranap' as jenis,
        IF(pp.no_rawat IS NULL OR pp.status='Lunas', 'Tunai', 'Piutang') as status_bayar,
        COALESCE(SUM(b.totalbiaya), 0) as total
    FROM nota_inap ni
    INNER JOIN billing b ON ni.no_rawat = b.no_rawat
    LEFT JOIN piutang_pasien pp ON ni.no_rawat = pp.no_rawat
    WHERE ni.tanggal = '$today'
    GROUP BY status_bayar
";

$res_omzet = $koneksi->query($sql_omzet);

// Init Variable
$total_tunai = 0;
$total_piutang = 0;
$total_all = 0;

if ($res_omzet) {
    while($row = $res_omzet->fetch_assoc()) {
        $val = (float)$row['total'];
        if($row['status_bayar'] == 'Tunai') {
            $total_tunai += $val;
        } else {
            $total_piutang += $val;
        }
        $total_all += $val;
    }
}

// Struktur Response Lengkap untuk Widget & Chart
$response['omzet'] = [
    'tunai' => $total_tunai,
    'piutang' => $total_piutang,
    'total' => $total_all,
    // Data untuk Chart.js
    'labels' => ['Tunai', 'Piutang'],
    'data' => [$total_tunai, $total_piutang]
];


// ==========================================================================
// 2. KETERSEDIAAN BED (PER KELAS & PER LANTAI)
// ==========================================================================
$floor_map = getSkBedFloorMapping();
$bed_in = getSkBedInSql($koneksi);
$sql_bed = "
    SELECT k.kd_kamar, k.kelas, k.status, b.nm_bangsal
    FROM kamar k
    INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
    WHERE k.statusdata = '1'
      AND k.kd_kamar IN ($bed_in)
";
$res_bed = $koneksi->query($sql_bed);
$bed_groups = [];

$lantai_groups = [
    2 => [
        'lantai' => 2,
        'nama' => 'Lantai 2',
        'sublabel' => 'Rawat Inap & NICU',
        'total' => 0,
        'terisi' => 0,
        'kosong' => 0,
        'groups' => []
    ],
    3 => [
        'lantai' => 3,
        'nama' => 'Lantai 3',
        'sublabel' => 'Rawat Inap & ICU',
        'total' => 0,
        'terisi' => 0,
        'kosong' => 0,
        'groups' => []
    ]
];

$total_bed_rs = 0;
$total_terisi_rs = 0;

if ($res_bed) {
    while($row = $res_bed->fetch_assoc()) {
        $kd_kamar = $row['kd_kamar'];
        $nm = strtoupper($row['nm_bangsal']);
        $status = $row['status']; 
        $kategori = $row['kelas'];
        $is_terisi = ($status == 'ISI');
        
        if (strpos($nm, 'ISOLASI') !== false || strpos($nm, 'ICU') !== false || 
            strpos($nm, 'NICU') !== false || strpos($nm, 'PICU') !== false || 
            strpos($nm, 'HCU') !== false || strpos($nm, 'PERINA') !== false) {
            $kategori = 'Kelas Khusus';
        }

        if (!isset($bed_groups[$kategori])) {
            $bed_groups[$kategori] = ['kelas' => $kategori, 'total' => 0, 'terisi' => 0, 'kosong' => 0];
        }

        $bed_groups[$kategori]['total']++;
        if ($is_terisi) {
            $bed_groups[$kategori]['terisi']++;
            $total_terisi_rs++;
        } else {
            $bed_groups[$kategori]['kosong']++;
        }
        $total_bed_rs++;

        // Mapping per Lantai & Ruang Group
        if (isset($floor_map[$kd_kamar])) {
            $finfo = $floor_map[$kd_kamar];
            $fl = (int)$finfo['lantai'];
            $grp = $finfo['ruangan_group'];

            if (isset($lantai_groups[$fl])) {
                $lantai_groups[$fl]['total']++;
                if ($is_terisi) {
                    $lantai_groups[$fl]['terisi']++;
                } else {
                    $lantai_groups[$fl]['kosong']++;
                }

                if (!isset($lantai_groups[$fl]['groups'][$grp])) {
                    $lantai_groups[$fl]['groups'][$grp] = [
                        'nama' => $grp,
                        'kelas' => $finfo['kelas'],
                        'total' => 0,
                        'terisi' => 0,
                        'kosong' => 0
                    ];
                }

                $lantai_groups[$fl]['groups'][$grp]['total']++;
                if ($is_terisi) {
                    $lantai_groups[$fl]['groups'][$grp]['terisi']++;
                } else {
                    $lantai_groups[$fl]['groups'][$grp]['kosong']++;
                }
            }
        }
    }
}
ksort($bed_groups);
$bed_data = array_values($bed_groups);

// Hitung persentase BOR per lantai dan format groups
foreach ($lantai_groups as $fl => &$ldata) {
    $bor = ($ldata['total'] > 0) ? round(($ldata['terisi'] / $ldata['total']) * 100, 1) : 0;
    $ldata['bor'] = $bor;
    $ldata['groups'] = array_values($ldata['groups']);
}
unset($ldata);

// Hitung BOR Global
$start = new DateTime($tgl_awal_bulan);
$end = new DateTime($today);
$days_period = $end->diff($start)->days + 1;

$hari_perawatan = calculateSkHariPerawatan($koneksi, $tgl_awal_bulan, $today);
$total_bed_sk = getSkBedCapacity();
$pembagi_bor = ($total_bed_sk * $days_period);
$bor_rs = ($pembagi_bor > 0) ? ($hari_perawatan / $pembagi_bor) * 100 : 0;

$response['bed'] = [
    'bor_global' => round($bor_rs, 2),
    'bor_basis' => 'SK BPJS',
    'total_bed_sk' => $total_bed_sk,
    'hari_perawatan_sk' => $hari_perawatan,
    'per_kelas' => $bed_data,
    'per_lantai' => array_values($lantai_groups),
    'total_terisi' => $total_terisi_rs
];

// ==========================================================================
// 3. KUNJUNGAN HARI INI
// ==========================================================================
$visit = ['Ralan' => 0, 'Ranap' => 0, 'Total' => 0];

// Ralan langsung dari reg_periksa
$q_ralan = $koneksi->query("SELECT COUNT(*) as jumlah FROM reg_periksa WHERE tgl_registrasi = '$today' AND stts != 'Batal' AND status_lanjut = 'Ralan'");
if ($q_ralan && $row = $q_ralan->fetch_assoc()) {
    $visit['Ralan'] = (int)$row['jumlah'];
}

// Ranap difilter hanya yang menempati Bed terdaftar SK (sinkron dengan laporan_kunjungan.php)
$ranap_kamar_filter = ($bed_in !== '') ? " AND kamar_fo.kd_kamar IN ($bed_in)" : " AND 1 = 0";
$sql_ranap = "
    SELECT COUNT(DISTINCT rp.no_rawat) as jumlah
    FROM reg_periksa rp
    INNER JOIN kamar kamar_fo ON kamar_fo.kd_kamar = (
        SELECT kamar_inap.kd_kamar
        FROM kamar_inap
        WHERE kamar_inap.no_rawat = rp.no_rawat
        ORDER BY kamar_inap.tgl_masuk ASC, kamar_inap.jam_masuk ASC
        LIMIT 1
    )
    WHERE rp.tgl_registrasi = '$today'
      AND rp.stts != 'Batal'
      AND rp.status_lanjut = 'Ranap'
      $ranap_kamar_filter
";
$q_ranap = $koneksi->query($sql_ranap);
if ($q_ranap && $row = $q_ranap->fetch_assoc()) {
    $visit['Ranap'] = (int)$row['jumlah'];
}

$visit['Total'] = $visit['Ralan'] + $visit['Ranap'];
$response['kunjungan'] = $visit;

// ==========================================================================
// 4. KUNJUNGAN AKTIF
// ==========================================================================
$sql_aktif = "SELECT COUNT(*) as total FROM reg_periksa WHERE status_bayar = 'Belum Bayar' AND stts != 'Batal'";
$res_aktif = $koneksi->query($sql_aktif);
$response['kunjungan_aktif'] = ($res_aktif) ? (int)$res_aktif->fetch_assoc()['total'] : 0;

// ==========================================================================
// 5. TOP 5 POLI
// ==========================================================================
$sql_poli = "SELECT p.nm_poli, COUNT(r.no_rawat) as jumlah FROM reg_periksa r INNER JOIN poliklinik p ON r.kd_poli = p.kd_poli WHERE r.tgl_registrasi = '$today' AND r.stts != 'Batal' GROUP BY r.kd_poli ORDER BY jumlah DESC LIMIT 5";
$res_poli = $koneksi->query($sql_poli);
$top_poli = [];
if ($res_poli) {
    while($r = $res_poli->fetch_assoc()) { $top_poli[] = $r; }
}
$response['top_poli'] = $top_poli;

// ==========================================================================
// 6. TREN TAHUNAN (SINKRON DENGAN BED SK BPJS)
// ==========================================================================
$d_ralan = array_fill(1, 12, 0);
$d_ranap = array_fill(1, 12, 0);
$d_total = array_fill(1, 12, 0);

// 6a. Tren Ralan
$sql_tren_ralan = "
    SELECT MONTH(tgl_registrasi) as bulan, COUNT(*) as jumlah 
    FROM reg_periksa 
    WHERE YEAR(tgl_registrasi) = '$year' 
      AND stts != 'Batal' 
      AND status_lanjut = 'Ralan' 
    GROUP BY MONTH(tgl_registrasi)
";
$res_tren_ralan = $koneksi->query($sql_tren_ralan);
if ($res_tren_ralan) {
    while ($row = $res_tren_ralan->fetch_assoc()) {
        $b = (int)$row['bulan'];
        $j = (int)$row['jumlah'];
        $d_ralan[$b] = $j;
        $d_total[$b] += $j;
    }
}

// 6b. Tren Ranap (Difilter hanya Bed SK terdaftar, sinkron dengan laporan_kunjungan.php)
$ranap_tren_kamar_filter = ($bed_in !== '') ? " AND kamar_fo.kd_kamar IN ($bed_in)" : " AND 1 = 0";
$sql_tren_ranap = "
    SELECT MONTH(rp.tgl_registrasi) as bulan, COUNT(DISTINCT rp.no_rawat) as jumlah
    FROM reg_periksa rp
    INNER JOIN kamar kamar_fo ON kamar_fo.kd_kamar = (
        SELECT kamar_inap.kd_kamar
        FROM kamar_inap
        WHERE kamar_inap.no_rawat = rp.no_rawat
        ORDER BY kamar_inap.tgl_masuk ASC, kamar_inap.jam_masuk ASC
        LIMIT 1
    )
    WHERE YEAR(rp.tgl_registrasi) = '$year'
      AND rp.stts != 'Batal'
      AND rp.status_lanjut = 'Ranap'
      $ranap_tren_kamar_filter
    GROUP BY MONTH(rp.tgl_registrasi)
";
$res_tren_ranap = $koneksi->query($sql_tren_ranap);
if ($res_tren_ranap) {
    while ($row = $res_tren_ranap->fetch_assoc()) {
        $b = (int)$row['bulan'];
        $j = (int)$row['jumlah'];
        $d_ranap[$b] = $j;
        $d_total[$b] += $j;
    }
}

$response['tren'] = ['ralan' => array_values($d_ralan), 'ranap' => array_values($d_ranap), 'total' => array_values($d_total)];

echo json_encode($response);
?>
