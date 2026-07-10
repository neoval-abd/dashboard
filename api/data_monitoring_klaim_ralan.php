<?php
/*
 * File: api/data_monitoring_klaim_ralan.php
 * Fungsi: Mengambil data monitoring klaim ralan pasien BPJS
 *         (rawat jalan dengan SEP dan tarif INA-CBG)
 * Author: Dashboard System
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php');

// ─── Parameter Filter ────────────────────────────────────────────────────────
$tgl_awal    = isset($_GET['tgl_awal'])    ? $_GET['tgl_awal']    : date('Y-m-01');
$tgl_akhir   = isset($_GET['tgl_akhir'])   ? $_GET['tgl_akhir']   : date('Y-m-d');
$kd_pj       = isset($_GET['kd_pj'])       ? $_GET['kd_pj']       : '';
$stts_pulang = isset($_GET['stts_pulang']) ? $_GET['stts_pulang'] : '';
$stts_keluar_expr = "
    CASE
        WHEN rp.stts = 'Sudah' THEN 'Sembuh'
        ELSE rp.stts
    END
";

// ─── Helper: Summary kosong ──────────────────────────────────────────────────
function getSummaryEmpty() {
    return [
        'total_pasien'   => 0,
        'total_biaya_rs' => 0.0,
        'total_tarif_cbg'=> 0.0,
        'total_selisih'  => 0.0,
        'cnt_untung'     => 0,
        'cnt_rugi'       => 0
    ];
}

// ─── Query utama: pasien ralan BPJS dengan SEP ──────────────────────────────
$sql_main = "
    SELECT
        rp.no_rawat,
        rp.no_rkm_medis,
        p.nm_pasien,
        pj.png_jawab,
        pj.kd_pj,
        rp.kd_dokter,
        d.nm_dokter,
        rp.tgl_registrasi,
        rp.jam_reg,
        $stts_keluar_expr AS stts_pulang,
        COALESCE(
            (SELECT bs.no_sep FROM bridging_sep bs WHERE bs.no_rawat = rp.no_rawat LIMIT 1),
            (SELECT bsi.no_sep FROM bridging_sep_internal bsi WHERE bsi.no_rawat = rp.no_rawat LIMIT 1)
        ) AS no_sep
    FROM reg_periksa rp
    INNER JOIN pasien      p   ON rp.no_rkm_medis = p.no_rkm_medis
    INNER JOIN penjab      pj  ON rp.kd_pj        = pj.kd_pj
    LEFT JOIN dokter       d   ON rp.kd_dokter    = d.kd_dokter
    WHERE rp.status_lanjut = 'Ralan'
      AND rp.tgl_registrasi BETWEEN ? AND ?
      AND LOWER(pj.png_jawab) LIKE '%bpjs%'
      AND (
            EXISTS (SELECT 1 FROM bridging_sep bs WHERE bs.no_rawat = rp.no_rawat AND bs.no_sep <> '')
         OR EXISTS (SELECT 1 FROM bridging_sep_internal bsi WHERE bsi.no_rawat = rp.no_rawat AND bsi.no_sep <> '')
      )
";

$params = [$tgl_awal, $tgl_akhir];
$types  = "ss";

if (!empty($kd_pj)) {
    $sql_main .= " AND rp.kd_pj = ? ";
    $params[] = $kd_pj;
    $types  .= "s";
}
if (!empty($stts_pulang)) {
    $sql_main .= " AND $stts_keluar_expr = ? ";
    $params[] = $stts_pulang;
    $types  .= "s";
}

$sql_main .= " ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC ";

$stmt = $koneksi->prepare($sql_main);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed: ' . $koneksi->error]);
    exit;
}

$bind_params = array_merge([$types], $params);
call_user_func_array(
    [$stmt, 'bind_param'],
    array_map(function(&$v){ return $v; }, $bind_params)
);
$stmt->execute();
$result = $stmt->get_result();

$rows      = [];
$no_rawats = [];
while ($row = $result->fetch_assoc()) {
    $rows[]      = $row;
    $no_rawats[] = $row['no_rawat'];
}
$stmt->close();

if (empty($rows)) {
    echo json_encode(['data' => [], 'summary' => getSummaryEmpty()]);
    exit;
}

// ─── Batch query: billing per no_rawat ───────────────────────────────────────
$in_place = implode(',', array_fill(0, count($no_rawats), '?'));
$in_types = str_repeat('s', count($no_rawats));

$sql_billing = "
    SELECT no_rawat, status, SUM(totalbiaya) AS total
    FROM billing
    WHERE no_rawat IN ($in_place)
    GROUP BY no_rawat, status
";
$stmt2 = $koneksi->prepare($sql_billing);
$stmt2->bind_param($in_types, ...$no_rawats);
$stmt2->execute();
$res2 = $stmt2->get_result();

$billing_map = [];   // [no_rawat][status] = total
while ($b = $res2->fetch_assoc()) {
    $billing_map[$b['no_rawat']][$b['status']] = (float)$b['total'];
}
$stmt2->close();

// ─── Batch query: diagnosa (ambil DU & DS1 saja, status Ralan) ───────────────
$sql_dx = "
    SELECT no_rawat, kd_penyakit, prioritas
    FROM diagnosa_pasien
    WHERE no_rawat IN ($in_place)
      AND status = 'Ralan'
      AND prioritas IN (1, 2)
    ORDER BY prioritas
";
$stmt3 = $koneksi->prepare($sql_dx);
$stmt3->bind_param($in_types, ...$no_rawats);
$stmt3->execute();
$res3 = $stmt3->get_result();

$dx_map = [];  // [no_rawat][prioritas] = kd_penyakit
while ($d = $res3->fetch_assoc()) {
    $dx_map[$d['no_rawat']][$d['prioritas']] = $d['kd_penyakit'];
}
$stmt3->close();

// ─── Batch query: prosedur utama (prioritas 1) ───────────────────────────────
$sql_pr = "
    SELECT no_rawat, kode, prioritas
    FROM prosedur_pasien
    WHERE no_rawat IN ($in_place)
      AND status = 'Ralan'
      AND prioritas = 1
";
$stmt4 = $koneksi->prepare($sql_pr);
$stmt4->bind_param($in_types, ...$no_rawats);
$stmt4->execute();
$res4 = $stmt4->get_result();

$pr_map = [];  // [no_rawat] = kode
while ($pr = $res4->fetch_assoc()) {
    $pr_map[$pr['no_rawat']] = $pr['kode'];
}
$stmt4->close();

// ─── Batch query: tarif INA-CBG + kode CBG ───────────────────────────────────
// Gabung dari 3 sumber (stage1, stage12 via klaim_baru2, stage1_internal)
$sql_cbg = "
    SELECT bs.no_rawat,
           gs.code_cbg,
           gs.tarif
    FROM inacbg_grouping_stage1 gs
    INNER JOIN bridging_sep bs ON gs.no_sep = bs.no_sep
    WHERE bs.no_rawat IN ($in_place)

    UNION ALL

    SELECT kb.no_rawat,
           gs12.code_cbg,
           gs12.tarif
    FROM inacbg_grouping_stage12 gs12
    INNER JOIN inacbg_klaim_baru2 kb ON gs12.no_sep = kb.no_sep
    WHERE kb.no_rawat IN ($in_place)

    UNION ALL

    SELECT bsi.no_rawat,
           gsi.code_cbg,
           gsi.tarif
    FROM inacbg_grouping_stage1_internal gsi
    INNER JOIN bridging_sep_internal bsi ON gsi.no_sep = bsi.no_sep
    WHERE bsi.no_rawat IN ($in_place)
";

$in_types3 = str_repeat('s', count($no_rawats) * 3);
$params_cbg = array_merge($no_rawats, $no_rawats, $no_rawats);
$stmt5 = $koneksi->prepare($sql_cbg);
$stmt5->bind_param($in_types3, ...$params_cbg);
$stmt5->execute();
$res5 = $stmt5->get_result();

$cbg_map = [];  // [no_rawat] = ['code' => ..., 'tarif' => ...]
while ($cbg = $res5->fetch_assoc()) {
    $nr = $cbg['no_rawat'];
    if (!isset($cbg_map[$nr])) {
        $cbg_map[$nr] = ['code' => $cbg['code_cbg'], 'tarif' => 0.0];
    }
    $cbg_map[$nr]['tarif'] += (float)$cbg['tarif'];
    if (!empty($cbg['code_cbg']) && $cbg_map[$nr]['code'] !== $cbg['code_cbg']) {
        $cbg_map[$nr]['code'] = trim($cbg_map[$nr]['code'] . ' ' . $cbg['code_cbg']);
    }
}
$stmt5->close();

// ─── Susun data akhir ─────────────────────────────────────────────────────────
$data       = [];
$ttl_biaya  = 0;
$ttl_tarif  = 0;
$ttl_selisih= 0;
$cnt_untung = 0;
$cnt_rugi   = 0;

// Kategori billing yang masuk ke Total Tarif RS (untuk ralan)
$kategori_total = [
    'Laborat','Radiologi','Operasi','Obat',
    'Ralan Dokter','Ralan Dokter Paramedis','Ralan Paramedis',
    'Tambahan','Potongan','Registrasi','Service'
];

foreach ($rows as $r) {
    $nr = $r['no_rawat'];

    // Hitung total biaya RS
    $total_rs = 0.0;
    $bil = $billing_map[$nr] ?? [];
    foreach ($kategori_total as $kat) {
        $total_rs += $bil[$kat] ?? 0.0;
    }

    // Tarif INA-CBG
    $tarif_cbg = $cbg_map[$nr]['tarif']  ?? 0.0;
    $kode_cbg  = $cbg_map[$nr]['code']   ?? '';

    // Selisih (Tarif CBG - Total RS)
    $selisih = ($tarif_cbg > 0) ? ($tarif_cbg - $total_rs) : 0.0;

    // Diagnosa
    $du  = $dx_map[$nr][1] ?? '';
    $ds1 = $dx_map[$nr][2] ?? '';

    // Prosedur utama
    $p1 = $pr_map[$nr] ?? '';

    // Running totals
    $ttl_biaya   += $total_rs;
    $ttl_tarif   += $tarif_cbg;
    $ttl_selisih += $selisih;
    if ($selisih >= 0) $cnt_untung++; else $cnt_rugi++;

    $data[] = [
        'no_rawat'       => $nr,
        'no_rkm_medis'   => $r['no_rkm_medis'],
        'nm_pasien'      => $r['nm_pasien'],
        'png_jawab'      => $r['png_jawab'],
        'kd_pj'          => $r['kd_pj'],
        'kd_dokter_jaga' => $r['nm_dokter'] ?? '-',
        'tgl_registrasi' => $r['tgl_registrasi'] . ' ' . $r['jam_reg'],
        'stts_pulang'    => $r['stts_pulang'],
        'no_sep'         => $r['no_sep'],
        'du'             => $du,
        'ds1'            => $ds1,
        'p1'             => $p1,
        'kode_cbg'       => $kode_cbg,
        'total_rs'       => $total_rs,
        'tarif_cbg'      => $tarif_cbg,
        'selisih'        => $selisih,
    ];
}

// ─── Return response ──────────────────────────────────────────────────────────
$response = [
    'data' => $data,
    'summary' => [
        'total_pasien'    => count($data),
        'total_biaya_rs'  => $ttl_biaya,
        'total_tarif_cbg' => $ttl_tarif,
        'total_selisih'   => $ttl_selisih,
        'cnt_untung'      => $cnt_untung,
        'cnt_rugi'        => $cnt_rugi
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
