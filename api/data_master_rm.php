<?php
header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

function valid_date_dm($value) {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
}

function bind_params_dm($stmt, $types, &$params) {
    $bind = array_merge([$types], $params);
    call_user_func_array([$stmt, 'bind_param'], array_map(function (&$v) { return $v; }, $bind));
}

function empty_value_dm($value) {
    return ($value === null || $value === '') ? '-' : $value;
}

$tgl_awal = valid_date_dm($_GET['tgl_awal'] ?? '') ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = valid_date_dm($_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');
$keyword = trim($_GET['keyword'] ?? '');
$status_lanjut = trim($_GET['status_lanjut'] ?? 'Ranap');
$limit = (int)($_GET['limit'] ?? 1000);
$limit = max(100, min($limit, 10000));

$columns = [
    ['data' => 'no_ri', 'title' => 'NO.PERAWATAN'],
    ['data' => 'no_rm', 'title' => 'NO.RM'],
    ['data' => 'nama_pasien', 'title' => 'NAMA PASIEN'],
    ['data' => 'ibu_kandung', 'title' => 'IBU KANDUNG'],
    ['data' => 'tgl_lahir', 'title' => 'TGL.LAHIR'],
    ['data' => 'umur', 'title' => 'UMUR'],
    ['data' => 'kel_umur', 'title' => 'KEL.UMUR'],
    ['data' => 'jns_kelamin', 'title' => 'JNS.KELAMIN'],
    ['data' => 'gol_darah', 'title' => 'GOL.DARAH'],
    ['data' => 'suku', 'title' => 'SUKU'],
    ['data' => 'bahasa', 'title' => 'BAHASA'],
    ['data' => 'status_kawin', 'title' => 'STATUS KAWIN'],
    ['data' => 'alamat', 'title' => 'ALAMAT'],
    ['data' => 'desa', 'title' => 'DESA'],
    ['data' => 'kecamatan', 'title' => 'KECAMATAN'],
    ['data' => 'kabupaten', 'title' => 'KABUPATEN'],
    ['data' => 'jns_pendidikan', 'title' => 'JNS.PENDIDIKAN'],
    ['data' => 'pekerjaan', 'title' => 'PEKERJAAN'],
    ['data' => 'no_ktp_nik', 'title' => 'NO.KTP / NIK'],
    ['data' => 'no_bpjs', 'title' => 'NO.BPJS'],
    ['data' => 'no_telpon_hp', 'title' => 'NO.TELPON/HP'],
    ['data' => 'status', 'title' => 'STATUS'],
    ['data' => 'asal_pasien', 'title' => 'ASAL PASIEN'],
    ['data' => 'ket_asal_pasien', 'title' => 'KET.ASAL PASIEN'],
    ['data' => 'poliklinik_ruang', 'title' => 'POLIKLINIK/RUANG'],
    ['data' => 'opname', 'title' => 'OPNAME'],
    ['data' => 'kelas', 'title' => 'KELAS'],
    ['data' => 'cara_bayar', 'title' => 'CARA BAYAR'],
    ['data' => 'no_jaminan', 'title' => 'NO. SEP PASIEN'],
    ['data' => 'covid19_no_sep', 'title' => 'COVID19_NO_SEP'],
    ['data' => 'tgl_masuk', 'title' => 'TGL.MASUK'],
    ['data' => 'tgl_pulang', 'title' => 'TGL.PULANG'],
    ['data' => 'los', 'title' => 'LOS'],
    ['data' => 'cara_keluar', 'title' => 'CARA KELUAR'],
    ['data' => 'keterangan_cara_keluar', 'title' => 'KET. (CARA KELUAR)'],
    ['data' => 'keadaan_keluar', 'title' => 'KEADAAN KELUAR'],
    ['data' => 'bb_lahir', 'title' => 'BB.LAHIR'],
    ['data' => 'tgl_drm_kembali', 'title' => 'TGL.DRM KEMBALI'],
    ['data' => 'dx_masuk', 'title' => 'DX.MASUK'],
    ['data' => 'dpjp', 'title' => 'DOKTER DPJP'],
    ['data' => 'no_dtd', 'title' => 'NO.DTD'],
    ['data' => 'kasus', 'title' => 'MACAM KASUS'],
    ['data' => 'dtd_utama', 'title' => 'DTD UTAMA'],
    ['data' => 'diagnosis_01', 'title' => 'DIAGNOSIS_01'],
    ['data' => 'icd10_01', 'title' => 'ICD 10.01'],
    ['data' => 'icd10_02', 'title' => 'ICD 10.02'],
    ['data' => 'icd10_03', 'title' => 'ICD 10.03'],
    ['data' => 'icd10_04', 'title' => 'ICD 10.04'],
    ['data' => 'icd10_05', 'title' => 'ICD 10.05'],
    ['data' => 'icd10_external_causes', 'title' => 'ICD 10.EXTERNAL CAUSES'],
    ['data' => 'external_causes', 'title' => 'EXTERNAL CAUSES'],
    ['data' => 'no_dtd_external_causes', 'title' => 'NO.DTD EXTERNAL CAUSES'],
    ['data' => 'dtd_external_causes', 'title' => 'DTD EXTERNAL CAUSES'],
    ['data' => 'procedure_01', 'title' => 'PROCEDURE_01'],
    ['data' => 'icd9cm_01', 'title' => 'ICD 9CM.01'],
    ['data' => 'icd9cm_02', 'title' => 'ICD 9CM.02'],
    ['data' => 'icd9cm_03', 'title' => 'ICD 9CM.03'],
    ['data' => 'icd9cm_04', 'title' => 'ICD 9CM.04'],
    ['data' => 'icd9cm_05', 'title' => 'ICD 9CM.05'],
    ['data' => 'umur_tahun', 'title' => 'UMUR (TAHUN)'],
    ['data' => 'umur_hari', 'title' => 'UMUR (HARI)'],
    ['data' => 'sp_procedure', 'title' => 'SP.PROCEDURE'],
    ['data' => 'sp_drug', 'title' => 'SP.DRUG'],
    ['data' => 'sp_investigation', 'title' => 'SP.INVESTIGATION'],
    ['data' => 'sp_prosthesis', 'title' => 'SP.PROSTHESIS'],
    ['data' => 'hak_kelas', 'title' => 'HAK KELAS'],
    ['data' => 'kode_inacbg', 'title' => 'KODE INACBG'],
    ['data' => 'deskripsi_inacbg', 'title' => 'DESKRIPSI INACBG'],
    ['data' => 'biaya_rs', 'title' => 'BIAYA RS'],
    ['data' => 'tariff_ina_cbg_hak', 'title' => 'TARIFF INA CBG (HAK)'],
    ['data' => 'bayar_iur', 'title' => 'BAYAR (IUR)'],
    ['data' => 'selisih', 'title' => 'SELISIH'],
];

$where = "WHERE rp.tgl_registrasi BETWEEN ? AND ?";
$types = "ss";
$params = [$tgl_awal, $tgl_akhir];

if ($status_lanjut !== '') {
    $where .= " AND rp.status_lanjut = ?";
    $types .= "s";
    $params[] = $status_lanjut;
}

if ($keyword !== '') {
    $where .= " AND (rp.no_rawat LIKE ? OR p.no_rkm_medis LIKE ? OR p.nm_pasien LIKE ? OR bs.no_sep LIKE ? OR bsi.no_sep LIKE ?)";
    $types .= "sssss";
    $like = '%' . $keyword . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$sql = "
SELECT
    IF(rp.stts = 'Batal', 'BATAL', '') AS batal,
    rp.no_reg AS no_registrasi,
    rp.no_rawat AS no_ri,
    p.no_rkm_medis AS no_rm,
    p.nm_pasien AS nama_pasien,
    p.nm_ibu AS ibu_kandung,
    p.tgl_lahir,
    p.umur,
    CASE
        WHEN TIMESTAMPDIFF(DAY, p.tgl_lahir, rp.tgl_registrasi) <= 7 THEN '0-7 hari'
        WHEN TIMESTAMPDIFF(DAY, p.tgl_lahir, rp.tgl_registrasi) <= 28 THEN '8-28 hari'
        WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, rp.tgl_registrasi) < 1 THEN '29 hari - < 1 tahun'
        WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, rp.tgl_registrasi) BETWEEN 1 AND 4 THEN '1-4 tahun'
        WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, rp.tgl_registrasi) BETWEEN 5 AND 14 THEN '5-14 tahun'
        WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, rp.tgl_registrasi) BETWEEN 15 AND 24 THEN '15-24 tahun'
        WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, rp.tgl_registrasi) BETWEEN 25 AND 44 THEN '25-44 tahun'
        WHEN TIMESTAMPDIFF(YEAR, p.tgl_lahir, rp.tgl_registrasi) BETWEEN 45 AND 64 THEN '45-64 tahun'
        ELSE '>= 65 tahun'
    END AS kel_umur,
    p.jk AS jns_kelamin,
    p.gol_darah,
    sb.nama_suku_bangsa AS suku,
    bp.nama_bahasa AS bahasa,
    p.stts_nikah AS status_kawin,
    p.alamat,
    kl.nm_kel AS desa,
    kc.nm_kec AS kecamatan,
    kb.nm_kab AS kabupaten,
    p.pnd AS jns_pendidikan,
    p.pekerjaan,
    p.no_ktp AS no_ktp_nik,
    p.no_peserta AS no_bpjs,
    '' AS no_kk,
    p.namakeluarga AS nama_kk,
    p.no_tlp AS no_telpon_hp,
    rp.stts AS status,
    COALESCE(bs.asal_rujukan, bsi.asal_rujukan, '') AS asal_pasien,
    COALESCE(bs.nmppkrujukan, bsi.nmppkrujukan, rp.p_jawab, '') AS ket_asal_pasien,
    COALESCE(bs.tujuankunjungan, bsi.tujuankunjungan, '') AS cara_masuk,
    COALESCE(bg.nm_bangsal, pl.nm_poli) AS poliklinik_ruang,
    rp.status_lanjut AS opname,
    ki.kd_kamar AS no_kamar_tt,
    km.kelas,
    pj.png_jawab AS cara_bayar,
    COALESCE(bs.no_sep, bsi.no_sep, '') AS no_jaminan,
    inc.no_klaim AS covid19_no_sep,
    COALESCE(ki.tgl_masuk, rp.tgl_registrasi) AS tgl_masuk,
    ki.tgl_keluar AS tgl_pulang,
    ki.lama AS los,
    ki.stts_pulang AS cara_keluar,
    ki.stts_pulang AS keterangan_cara_keluar,
    ki.stts_pulang AS keadaan_keluar,
    pb.berat_badan AS bb_lahir,
    '' AS lengkap,
    '' AS no_pemberitahuan,
    '' AS tgl_drm_kembali,
    '' AS tgl_lengkap,
    ki.diagnosa_awal AS dx_masuk,
    COALESCE(dpjp_r.nm_dokter, dokter_reg.nm_dokter) AS dpjp,
    '' AS no_dtd,
    COALESCE(bs.nmdiagnosaawal, bsi.nmdiagnosaawal, '') AS kasus,
    '' AS dtd_utama,
    dx.diagnosis_01,
    dx.icd10_01,
    dx.icd10_02,
    dx.icd10_03,
    dx.icd10_04,
    dx.icd10_05,
    dx.icd10_external_causes,
    dx.external_causes,
    '' AS no_dtd_external_causes,
    '' AS dtd_external_causes,
    pr.procedure_01,
    pr.icd9cm_01,
    pr.icd9cm_02,
    pr.icd9cm_03,
    pr.icd9cm_04,
    pr.icd9cm_05,
    TIMESTAMPDIFF(YEAR, p.tgl_lahir, rp.tgl_registrasi) AS umur_tahun,
    TIMESTAMPDIFF(DAY, p.tgl_lahir, rp.tgl_registrasi) AS umur_hari,
    cmg.sp_procedure,
    cmg.sp_drug,
    cmg.sp_investigation,
    cmg.sp_prosthesis,
    COALESCE(bs.klsrawat, bsi.klsrawat, ris.kelas) AS hak_kelas,
    COALESCE(ris.kode_inacbg, cbg.kode_inacbg) AS kode_inacbg,
    COALESCE(ris.deskripsi, cbg.deskripsi_inacbg) AS deskripsi_inacbg,
    billing.biaya_rs,
    COALESCE(ris.tarif, cbg.tariff_ina_cbg_hak, 0) AS tariff_ina_cbg_hak,
    COALESCE(sel.bayar_iur, 0) AS bayar_iur,
    (COALESCE(ris.tarif, cbg.tariff_ina_cbg_hak, 0) + COALESCE(sel.bayar_iur, 0) - COALESCE(billing.biaya_rs, 0)) AS selisih
FROM reg_periksa rp
INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
LEFT JOIN dokter dokter_reg ON rp.kd_dokter = dokter_reg.kd_dokter
LEFT JOIN kelurahan kl ON p.kd_kel = kl.kd_kel
LEFT JOIN kecamatan kc ON p.kd_kec = kc.kd_kec
LEFT JOIN kabupaten kb ON p.kd_kab = kb.kd_kab
LEFT JOIN suku_bangsa sb ON p.suku_bangsa = sb.id
LEFT JOIN bahasa_pasien bp ON p.bahasa_pasien = bp.id
LEFT JOIN pasien_bayi pb ON p.no_rkm_medis = pb.no_rkm_medis
LEFT JOIN bridging_sep bs ON rp.no_rawat = bs.no_rawat
LEFT JOIN bridging_sep_internal bsi ON rp.no_rawat = bsi.no_rawat
LEFT JOIN inacbg_noklaim_corona inc ON rp.no_rawat = inc.no_rawat
LEFT JOIN (
    SELECT
        no_rawat,
        MIN(CONCAT(tgl_masuk, ' ', jam_masuk)) AS masuk_min,
        SUBSTRING_INDEX(GROUP_CONCAT(kd_kamar ORDER BY tgl_keluar DESC, jam_keluar DESC, tgl_masuk DESC, jam_masuk DESC), ',', 1) AS kd_kamar,
        SUBSTRING_INDEX(GROUP_CONCAT(tgl_masuk ORDER BY tgl_masuk ASC, jam_masuk ASC), ',', 1) AS tgl_masuk,
        NULLIF(SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(tgl_keluar, '0000-00-00') ORDER BY tgl_keluar DESC, jam_keluar DESC), ',', 1), '') AS tgl_keluar,
        SUM(CASE WHEN stts_pulang <> 'Pindah Kamar' THEN lama ELSE 0 END) AS lama,
        SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(stts_pulang, '-') ORDER BY tgl_keluar DESC, jam_keluar DESC SEPARATOR '||'), '||', 1) AS stts_pulang,
        SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(diagnosa_awal, '') ORDER BY tgl_masuk ASC, jam_masuk ASC SEPARATOR '||'), '||', 1) AS diagnosa_awal
    FROM kamar_inap
    GROUP BY no_rawat
) ki ON rp.no_rawat = ki.no_rawat
LEFT JOIN kamar km ON ki.kd_kamar = km.kd_kamar
LEFT JOIN bangsal bg ON km.kd_bangsal = bg.kd_bangsal
LEFT JOIN (
    SELECT dr.no_rawat, SUBSTRING_INDEX(GROUP_CONCAT(d.nm_dokter ORDER BY d.nm_dokter SEPARATOR ', '), ',', 1) AS nm_dokter
    FROM dpjp_ranap dr
    INNER JOIN dokter d ON dr.kd_dokter = d.kd_dokter
    GROUP BY dr.no_rawat
) dpjp_r ON rp.no_rawat = dpjp_r.no_rawat
LEFT JOIN (
    SELECT
        dp.no_rawat,
        MAX(CASE WHEN dp.prioritas = 1 THEN py.nm_penyakit END) AS diagnosis_01,
        MAX(CASE WHEN dp.prioritas = 1 THEN dp.kd_penyakit END) AS icd10_01,
        MAX(CASE WHEN dp.prioritas = 2 THEN dp.kd_penyakit END) AS icd10_02,
        MAX(CASE WHEN dp.prioritas = 3 THEN dp.kd_penyakit END) AS icd10_03,
        MAX(CASE WHEN dp.prioritas = 4 THEN dp.kd_penyakit END) AS icd10_04,
        MAX(CASE WHEN dp.prioritas = 5 THEN dp.kd_penyakit END) AS icd10_05,
        GROUP_CONCAT(CASE WHEN dp.kd_penyakit REGEXP '^[VWXY]' THEN dp.kd_penyakit END ORDER BY dp.prioritas SEPARATOR ', ') AS icd10_external_causes,
        GROUP_CONCAT(CASE WHEN dp.kd_penyakit REGEXP '^[VWXY]' THEN py.nm_penyakit END ORDER BY dp.prioritas SEPARATOR ', ') AS external_causes
    FROM diagnosa_pasien dp
    LEFT JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
    GROUP BY dp.no_rawat
) dx ON rp.no_rawat = dx.no_rawat
LEFT JOIN (
    SELECT
        pp.no_rawat,
        MAX(CASE WHEN pp.prioritas = 1 THEN ic.deskripsi_panjang END) AS procedure_01,
        MAX(CASE WHEN pp.prioritas = 1 THEN pp.kode END) AS icd9cm_01,
        MAX(CASE WHEN pp.prioritas = 2 THEN pp.kode END) AS icd9cm_02,
        MAX(CASE WHEN pp.prioritas = 3 THEN pp.kode END) AS icd9cm_03,
        MAX(CASE WHEN pp.prioritas = 4 THEN pp.kode END) AS icd9cm_04,
        MAX(CASE WHEN pp.prioritas = 5 THEN pp.kode END) AS icd9cm_05
    FROM prosedur_pasien pp
    LEFT JOIN icd9 ic ON pp.kode = ic.kode
    GROUP BY pp.no_rawat
) pr ON rp.no_rawat = pr.no_rawat
LEFT JOIN (
    SELECT
        no_rawat,
        SUM(CASE
            WHEN status = 'TtlRetur Obat' THEN totalbiaya * -1
            WHEN status = 'TtlPotongan' THEN totalbiaya * -1
            ELSE totalbiaya
        END) AS biaya_rs
    FROM billing
    WHERE status != 'Tagihan'
    GROUP BY no_rawat
) billing ON rp.no_rawat = billing.no_rawat
LEFT JOIN (
    SELECT no_rawat, kode_inacbg, deskripsi, tarif, kelas
    FROM ranap_inacbg_selection
) ris ON rp.no_rawat = ris.no_rawat
LEFT JOIN (
    SELECT no_rawat, MAX(kode_inacbg) AS kode_inacbg, MAX(deskripsi_inacbg) AS deskripsi_inacbg, SUM(tariff_ina_cbg_hak) AS tariff_ina_cbg_hak
    FROM (
        SELECT bs.no_rawat, gs.no_sep, gs.code_cbg AS kode_inacbg, gs.deskripsi AS deskripsi_inacbg, gs.tarif AS tariff_ina_cbg_hak
        FROM inacbg_grouping_stage1 gs INNER JOIN bridging_sep bs ON gs.no_sep = bs.no_sep
        UNION ALL
        SELECT kb.no_rawat, gs12.no_sep, gs12.code_cbg, gs12.deskripsi, gs12.tarif
        FROM inacbg_grouping_stage12 gs12 INNER JOIN inacbg_klaim_baru2 kb ON gs12.no_sep = kb.no_sep
        UNION ALL
        SELECT bsi.no_rawat, gsi.no_sep, gsi.code_cbg, gsi.deskripsi, gsi.tarif
        FROM inacbg_grouping_stage1_internal gsi INNER JOIN bridging_sep_internal bsi ON gsi.no_sep = bsi.no_sep
    ) x
    GROUP BY no_rawat
) cbg ON rp.no_rawat = cbg.no_rawat
LEFT JOIN (
    SELECT no_sep, SUM(biaya) AS bayar_iur
    FROM inacbg_selisihbayar
    GROUP BY no_sep
) sel ON COALESCE(bs.no_sep, bsi.no_sep) = sel.no_sep
LEFT JOIN (
    SELECT
        no_sep,
        GROUP_CONCAT(CASE WHEN LOWER(type) LIKE '%procedure%' THEN CONCAT(code_cbg, ' ', deskripsi) END SEPARATOR ', ') AS sp_procedure,
        GROUP_CONCAT(CASE WHEN LOWER(type) LIKE '%drug%' THEN CONCAT(code_cbg, ' ', deskripsi) END SEPARATOR ', ') AS sp_drug,
        GROUP_CONCAT(CASE WHEN LOWER(type) LIKE '%investigation%' THEN CONCAT(code_cbg, ' ', deskripsi) END SEPARATOR ', ') AS sp_investigation,
        GROUP_CONCAT(CASE WHEN LOWER(type) LIKE '%prosthesis%' THEN CONCAT(code_cbg, ' ', deskripsi) END SEPARATOR ', ') AS sp_prosthesis
    FROM inacbg_grouping_stage2
    GROUP BY no_sep
) cmg ON COALESCE(bs.no_sep, bsi.no_sep) = cmg.no_sep
$where
ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC
LIMIT $limit";

$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query Data Master tidak dapat diproses: ' . $koneksi->error, 'data' => [], 'columns' => $columns]);
    exit;
}

bind_params_dm($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    foreach ($columns as $column) {
        $key = $column['data'];
        $row[$key] = empty_value_dm($row[$key] ?? null);
    }
    $data[] = $row;
}
$stmt->close();

$warnings = [
    'NO.DTD dan DTD belum ditemukan tabel referensinya pada skema yang terlihat, sehingga kolom disiapkan kosong.',
    'SP.PROCEDURE/SP.DRUG/SP.INVESTIGATION/SP.PROSTHESIS diambil dari inacbg_grouping_stage2 berdasarkan kolom type; jika type memakai istilah berbeda, hasil bisa kosong.',
];

echo json_encode([
    'success' => true,
    'data' => $data,
    'columns' => $columns,
    'summary' => [
        'total' => count($data),
        'limit' => $limit,
        'periode' => $tgl_awal . ' s/d ' . $tgl_akhir,
    ],
    'warnings' => $warnings,
], JSON_UNESCAPED_UNICODE);
