<?php
ini_set('display_errors', 0);
ini_set('memory_limit', '-1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.', 'data' => []]);
    exit;
}

function jm_valid_date($value) {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
}

function jm_bind_params($stmt, $types, &$params) {
    if ($types === '') {
        return;
    }
    $bind = array_merge([$types], $params);
    call_user_func_array([$stmt, 'bind_param'], array_map(function (&$value) { return $value; }, $bind));
}

function jm_col($data, $title, $money = false) {
    return ['data' => $data, 'title' => $title, 'money' => $money];
}

function jm_base_columns() {
    return [
        jm_col('no', 'No.'),
        jm_col('no_rawat', 'No.Rawat'),
        jm_col('no_rkm_medis', 'No.R.M.'),
        jm_col('nm_pasien', 'Nama Pasien'),
    ];
}

function jm_money_columns($doctor = true, $paramedic = false, $lab = false) {
    $columns = [
        jm_col('jasa_sarana', 'Jasa Sarana', true),
        jm_col('paket_bhp', 'Paket BHP', true),
    ];
    if ($doctor) {
        $columns[] = jm_col('jm_dokter', 'JM Dokter', true);
    }
    if ($paramedic) {
        $columns[] = jm_col('jm_paramedis', 'JM Paramedis', true);
    }
    if ($lab) {
        $columns[] = jm_col('bagian_perujuk', 'Bagian Perujuk', true);
        $columns[] = jm_col('bagian_laborat', 'Bagian Laborat', true);
    }
    $columns[] = jm_col('kso', 'KSO', true);
    $columns[] = jm_col('menejemen', 'Menejemen', true);
    $columns[] = jm_col('total_biaya', 'Total Biaya', true);
    return $columns;
}

function jm_status_sql($status) {
    if ($status === 'Piutang Belum Lunas') {
        return " AND rp.status_bayar='Sudah Bayar' AND EXISTS (SELECT 1 FROM piutang_pasien pp WHERE pp.no_rawat=rp.no_rawat AND pp.status='Belum Lunas')";
    }
    if ($status === 'Piutang Sudah Lunas') {
        return " AND rp.status_bayar='Sudah Bayar' AND EXISTS (SELECT 1 FROM piutang_pasien pp WHERE pp.no_rawat=rp.no_rawat AND pp.status='Lunas')";
    }
    if ($status === 'Sudah Bayar Non Piutang') {
        return " AND rp.status_bayar='Sudah Bayar' AND NOT EXISTS (SELECT 1 FROM piutang_pasien pp WHERE pp.no_rawat=rp.no_rawat)";
    }
    if ($status === 'Belum Terclosing Kasir') {
        return " AND rp.status_bayar='Belum Bayar'";
    }
    return '';
}

function jm_add_common_filters(&$where, &$types, &$params, $filters, $search_cols) {
    if ($filters['kd_pj'] !== '') {
        $where .= ' AND rp.kd_pj = ?';
        $types .= 's';
        $params[] = $filters['kd_pj'];
    }
    if ($filters['kd_unit'] !== '') {
        $where .= ' AND rp.kd_poli = ?';
        $types .= 's';
        $params[] = $filters['kd_unit'];
    }
    if ($filters['keyword'] !== '') {
        $parts = [];
        foreach ($search_cols as $col) {
            $parts[] = $col . ' LIKE ?';
            $types .= 's';
            $params[] = '%' . $filters['keyword'] . '%';
        }
        $where .= ' AND (' . implode(' OR ', $parts) . ')';
    }
}

function jm_room_ranap_sql($table_alias) {
    return "(SELECT bg.nm_bangsal FROM kamar_inap ki INNER JOIN kamar km ON ki.kd_kamar=km.kd_kamar INNER JOIN bangsal bg ON km.kd_bangsal=bg.kd_bangsal WHERE ki.no_rawat={$table_alias}.no_rawat ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC LIMIT 1)";
}

$tgl_awal = jm_valid_date($_GET['tgl_awal'] ?? '') ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = jm_valid_date($_GET['tgl_akhir'] ?? '') ? $_GET['tgl_akhir'] : date('Y-m-d');
$tab = $_GET['tab'] ?? 'ralan_dokter';
$status = $_GET['status'] ?? 'Semua';
$limit = max(100, min((int)($_GET['limit'] ?? 5000), 20000));

$filters = [
    'keyword' => trim($_GET['keyword'] ?? ''),
    'kd_dokter' => trim($_GET['kd_dokter'] ?? ''),
    'kd_petugas' => trim($_GET['kd_petugas'] ?? ''),
    'kd_unit' => trim($_GET['kd_unit'] ?? ''),
    'kd_pj' => trim($_GET['kd_pj'] ?? ''),
];

$tabs = [
    'ralan_dokter' => 'Ralan Dokter',
    'ralan_paramedis' => 'Ralan Paramedis',
    'ralan_dokter_paramedis' => 'Ralan Dokter & Paramedis',
    'operasi_vk' => 'Operasi & VK',
    'ranap_dokter' => 'Ranap Dokter',
    'ranap_paramedis' => 'Ranap Paramedis',
    'ranap_dokter_paramedis' => 'Ranap Dokter & Paramedis',
    'radiologi' => 'Pemeriksaan Radiologi',
    'laboratorium' => 'Pemeriksaan Laboratorium',
    'detail_laboratorium' => 'Detail Pemeriksaan Laboratorium',
];
if (!isset($tabs[$tab])) {
    $tab = 'ralan_dokter';
}

$date_start = $tgl_awal . ' 00:00:00';
$date_end = $tgl_akhir . ' 23:59:59';
$types = 'ss';
$params = [$date_start, $date_end];
$where = '';
$columns = [];
$sql = '';

if ($tab === 'ralan_dokter') {
    $columns = array_merge(jm_base_columns(), [
        jm_col('kd_tindakan', 'Kd.Tnd'),
        jm_col('nm_tindakan', 'Perawatan/Tindakan'),
        jm_col('kd_dokter', 'Kode Dokter'),
        jm_col('nm_dokter', 'Dokter Yg Menangani'),
        jm_col('tanggal', 'Tanggal'),
        jm_col('jam', 'Jam'),
        jm_col('cara_bayar', 'Cara Bayar'),
        jm_col('ruangan', 'Ruangan'),
    ], jm_money_columns(true, false));

    $where = "WHERE CONCAT(t.tgl_perawatan,' ',t.jam_rawat) BETWEEN ? AND ?" . jm_status_sql($status);
    if ($filters['kd_dokter'] !== '') {
        $where .= ' AND t.kd_dokter = ?';
        $types .= 's';
        $params[] = $filters['kd_dokter'];
    }
    jm_add_common_filters($where, $types, $params, $filters, ['t.no_rawat', 'rp.no_rkm_medis', 'p.nm_pasien', 't.kd_jenis_prw', 'j.nm_perawatan', 't.kd_dokter', 'd.nm_dokter', 'pj.png_jawab', 'pl.nm_poli']);
    $sql = "SELECT t.no_rawat,rp.no_rkm_medis,p.nm_pasien,t.kd_jenis_prw AS kd_tindakan,j.nm_perawatan AS nm_tindakan,t.kd_dokter,d.nm_dokter,t.tgl_perawatan AS tanggal,t.jam_rawat AS jam,pj.png_jawab AS cara_bayar,pl.nm_poli AS ruangan,t.material AS jasa_sarana,t.bhp AS paket_bhp,t.tarif_tindakandr AS jm_dokter,0 AS jm_paramedis,t.kso,t.menejemen,t.biaya_rawat AS total_biaya FROM rawat_jl_dr t INNER JOIN reg_periksa rp ON rp.no_rawat=t.no_rawat INNER JOIN pasien p ON p.no_rkm_medis=rp.no_rkm_medis INNER JOIN dokter d ON d.kd_dokter=t.kd_dokter INNER JOIN jns_perawatan j ON j.kd_jenis_prw=t.kd_jenis_prw INNER JOIN poliklinik pl ON pl.kd_poli=rp.kd_poli INNER JOIN penjab pj ON pj.kd_pj=rp.kd_pj $where ORDER BY t.no_rawat DESC, t.jam_rawat DESC LIMIT $limit";
} elseif ($tab === 'ralan_paramedis') {
    $columns = array_merge(jm_base_columns(), [
        jm_col('kd_tindakan', 'Kd.Tnd'),
        jm_col('nm_tindakan', 'Perawatan/Tindakan'),
        jm_col('nip', 'NIP'),
        jm_col('nm_petugas', 'Paramedis Yg Menangani'),
        jm_col('tanggal', 'Tanggal'),
        jm_col('jam', 'Jam'),
        jm_col('cara_bayar', 'Cara Bayar'),
        jm_col('ruangan', 'Ruangan'),
    ], jm_money_columns(false, true));

    $where = "WHERE CONCAT(t.tgl_perawatan,' ',t.jam_rawat) BETWEEN ? AND ?" . jm_status_sql($status);
    if ($filters['kd_petugas'] !== '') {
        $where .= ' AND t.nip = ?';
        $types .= 's';
        $params[] = $filters['kd_petugas'];
    }
    jm_add_common_filters($where, $types, $params, $filters, ['t.no_rawat', 'rp.no_rkm_medis', 'p.nm_pasien', 't.kd_jenis_prw', 'j.nm_perawatan', 't.nip', 'pt.nama', 'pj.png_jawab', 'pl.nm_poli']);
    $sql = "SELECT t.no_rawat,rp.no_rkm_medis,p.nm_pasien,t.kd_jenis_prw AS kd_tindakan,j.nm_perawatan AS nm_tindakan,'' AS kd_dokter,'' AS nm_dokter,t.nip,pt.nama AS nm_petugas,t.tgl_perawatan AS tanggal,t.jam_rawat AS jam,pj.png_jawab AS cara_bayar,pl.nm_poli AS ruangan,t.material AS jasa_sarana,t.bhp AS paket_bhp,0 AS jm_dokter,t.tarif_tindakanpr AS jm_paramedis,t.kso,t.menejemen,t.biaya_rawat AS total_biaya FROM rawat_jl_pr t INNER JOIN reg_periksa rp ON rp.no_rawat=t.no_rawat INNER JOIN pasien p ON p.no_rkm_medis=rp.no_rkm_medis INNER JOIN petugas pt ON pt.nip=t.nip INNER JOIN jns_perawatan j ON j.kd_jenis_prw=t.kd_jenis_prw INNER JOIN poliklinik pl ON pl.kd_poli=rp.kd_poli INNER JOIN penjab pj ON pj.kd_pj=rp.kd_pj $where ORDER BY t.no_rawat DESC, t.jam_rawat DESC LIMIT $limit";
} elseif ($tab === 'ralan_dokter_paramedis') {
    $columns = array_merge(jm_base_columns(), [
        jm_col('kd_tindakan', 'Kd.Tnd'),
        jm_col('nm_tindakan', 'Perawatan/Tindakan'),
        jm_col('kd_dokter', 'Kode Dokter'),
        jm_col('nm_dokter', 'Dokter Yg Menangani'),
        jm_col('nip', 'NIP'),
        jm_col('nm_petugas', 'Paramedis Yg Menangani'),
        jm_col('tanggal', 'Tanggal'),
        jm_col('jam', 'Jam'),
        jm_col('cara_bayar', 'Cara Bayar'),
        jm_col('ruangan', 'Ruangan'),
    ], jm_money_columns(true, true));

    $where = "WHERE CONCAT(t.tgl_perawatan,' ',t.jam_rawat) BETWEEN ? AND ?" . jm_status_sql($status);
    if ($filters['kd_dokter'] !== '') {
        $where .= ' AND t.kd_dokter = ?';
        $types .= 's';
        $params[] = $filters['kd_dokter'];
    }
    if ($filters['kd_petugas'] !== '') {
        $where .= ' AND t.nip = ?';
        $types .= 's';
        $params[] = $filters['kd_petugas'];
    }
    jm_add_common_filters($where, $types, $params, $filters, ['t.no_rawat', 'rp.no_rkm_medis', 'p.nm_pasien', 't.kd_jenis_prw', 'j.nm_perawatan', 't.kd_dokter', 'd.nm_dokter', 't.nip', 'pt.nama', 'pj.png_jawab', 'pl.nm_poli']);
    $sql = "SELECT t.no_rawat,rp.no_rkm_medis,p.nm_pasien,t.kd_jenis_prw AS kd_tindakan,j.nm_perawatan AS nm_tindakan,t.kd_dokter,d.nm_dokter,t.nip,pt.nama AS nm_petugas,t.tgl_perawatan AS tanggal,t.jam_rawat AS jam,pj.png_jawab AS cara_bayar,pl.nm_poli AS ruangan,t.material AS jasa_sarana,t.bhp AS paket_bhp,t.tarif_tindakandr AS jm_dokter,t.tarif_tindakanpr AS jm_paramedis,t.kso,t.menejemen,t.biaya_rawat AS total_biaya FROM rawat_jl_drpr t INNER JOIN reg_periksa rp ON rp.no_rawat=t.no_rawat INNER JOIN pasien p ON p.no_rkm_medis=rp.no_rkm_medis INNER JOIN dokter d ON d.kd_dokter=t.kd_dokter INNER JOIN petugas pt ON pt.nip=t.nip INNER JOIN jns_perawatan j ON j.kd_jenis_prw=t.kd_jenis_prw INNER JOIN poliklinik pl ON pl.kd_poli=rp.kd_poli INNER JOIN penjab pj ON pj.kd_pj=rp.kd_pj $where ORDER BY t.no_rawat DESC, t.jam_rawat DESC LIMIT $limit";
} elseif (in_array($tab, ['ranap_dokter', 'ranap_paramedis', 'ranap_dokter_paramedis'], true)) {
    $is_dr = $tab !== 'ranap_paramedis';
    $is_pr = $tab !== 'ranap_dokter';
    $table = ['ranap_dokter' => 'rawat_inap_dr', 'ranap_paramedis' => 'rawat_inap_pr', 'ranap_dokter_paramedis' => 'rawat_inap_drpr'][$tab];
    $jasa_dokter_sql = $is_dr ? 't.tarif_tindakandr' : '0';
    $jasa_pr_sql = $is_pr ? 't.tarif_tindakanpr' : '0';
    $select_people = $is_dr ? "t.kd_dokter,d.nm_dokter," : "'' AS kd_dokter,'' AS nm_dokter,";
    $select_people .= $is_pr ? "t.nip,pt.nama AS nm_petugas," : "'' AS nip,'' AS nm_petugas,";
    $joins_people = ($is_dr ? ' INNER JOIN dokter d ON d.kd_dokter=t.kd_dokter' : '') . ($is_pr ? ' INNER JOIN petugas pt ON pt.nip=t.nip' : '');
    $search_cols = ['t.no_rawat', 'rp.no_rkm_medis', 'p.nm_pasien', 't.kd_jenis_prw', 'j.nm_perawatan', 'pj.png_jawab'];
    if ($is_dr) {
        array_push($search_cols, 't.kd_dokter', 'd.nm_dokter');
    }
    if ($is_pr) {
        array_push($search_cols, 't.nip', 'pt.nama');
    }
    $people_cols = [];
    if ($is_dr) {
        $people_cols[] = jm_col('kd_dokter', 'Kode Dokter');
        $people_cols[] = jm_col('nm_dokter', 'Dokter Yg Menangani');
    }
    if ($is_pr) {
        $people_cols[] = jm_col('nip', 'NIP');
        $people_cols[] = jm_col('nm_petugas', 'Paramedis Yg Menangani');
    }
    $columns = array_merge(jm_base_columns(), [
        jm_col('kd_tindakan', 'Kd.Tnd'),
        jm_col('nm_tindakan', 'Perawatan/Tindakan'),
    ], $people_cols, [
        jm_col('tanggal', 'Tanggal'),
        jm_col('jam', 'Jam'),
        jm_col('cara_bayar', 'Cara Bayar'),
        jm_col('ruangan', 'Ruangan'),
    ], jm_money_columns($is_dr, $is_pr));

    $where = "WHERE CONCAT(t.tgl_perawatan,' ',t.jam_rawat) BETWEEN ? AND ?" . jm_status_sql($status);
    if ($is_dr && $filters['kd_dokter'] !== '') {
        $where .= ' AND t.kd_dokter = ?';
        $types .= 's';
        $params[] = $filters['kd_dokter'];
    }
    if ($is_pr && $filters['kd_petugas'] !== '') {
        $where .= ' AND t.nip = ?';
        $types .= 's';
        $params[] = $filters['kd_petugas'];
    }
    jm_add_common_filters($where, $types, $params, $filters, $search_cols);
    $room_sql = jm_room_ranap_sql('t');
    $sql = "SELECT t.no_rawat,rp.no_rkm_medis,p.nm_pasien,t.kd_jenis_prw AS kd_tindakan,j.nm_perawatan AS nm_tindakan,$select_people t.tgl_perawatan AS tanggal,t.jam_rawat AS jam,pj.png_jawab AS cara_bayar,COALESCE($room_sql,'-') AS ruangan,t.material AS jasa_sarana,t.bhp AS paket_bhp,$jasa_dokter_sql AS jm_dokter,$jasa_pr_sql AS jm_paramedis,t.kso,t.menejemen,t.biaya_rawat AS total_biaya FROM $table t INNER JOIN reg_periksa rp ON rp.no_rawat=t.no_rawat INNER JOIN pasien p ON p.no_rkm_medis=rp.no_rkm_medis$joins_people INNER JOIN jns_perawatan_inap j ON j.kd_jenis_prw=t.kd_jenis_prw INNER JOIN penjab pj ON pj.kd_pj=rp.kd_pj $where ORDER BY t.no_rawat DESC, t.jam_rawat DESC LIMIT $limit";
} elseif ($tab === 'operasi_vk') {
    $columns = array_merge(jm_base_columns(), [
        jm_col('kd_tindakan', 'Kode Paket'),
        jm_col('nm_tindakan', 'Operasi/Tindakan'),
        jm_col('operator1', 'Operator 1'),
        jm_col('dokter_anestesi', 'Dokter Anestesi'),
        jm_col('tanggal', 'Tanggal'),
        jm_col('jam', 'Jam'),
        jm_col('cara_bayar', 'Cara Bayar'),
        jm_col('ruangan', 'Ruangan'),
        jm_col('biayaoperator1', 'JM Operator 1', true),
        jm_col('biayaoperator2', 'JM Operator 2', true),
        jm_col('biayaoperator3', 'JM Operator 3', true),
        jm_col('biayadokter_anestesi', 'JM Anestesi', true),
        jm_col('biayadokter_anak', 'JM Dokter Anak', true),
        jm_col('biaya_dokter_umum', 'JM Dokter Umum', true),
        jm_col('bagian_rs', 'Bagian RS', true),
        jm_col('biayaalat', 'Alat', true),
        jm_col('biayasewaok', 'Sewa OK', true),
        jm_col('akomodasi', 'Akomodasi', true),
        jm_col('total_biaya', 'Total Biaya', true),
    ]);
    $total_op = "(t.biayaoperator1+t.biayaoperator2+t.biayaoperator3+t.biayaasisten_operator1+t.biayaasisten_operator2+t.biayaasisten_operator3+t.biayainstrumen+t.biayadokter_anak+t.biayaperawaat_resusitas+t.biayadokter_anestesi+t.biayaasisten_anestesi+t.biayaasisten_anestesi2+t.biayabidan+t.biayabidan2+t.biayabidan3+t.biayaperawat_luar+t.biayaalat+t.biayasewaok+t.akomodasi+t.bagian_rs+t.biaya_omloop+t.biaya_omloop2+t.biaya_omloop3+t.biaya_omloop4+t.biaya_omloop5+t.biayasarpras+t.biaya_dokter_pjanak+t.biaya_dokter_umum)";
    $where = "WHERE t.tgl_operasi BETWEEN ? AND ?" . jm_status_sql($status);
    if ($filters['kd_dokter'] !== '') {
        $where .= ' AND (t.operator1=? OR t.operator2=? OR t.operator3=? OR t.dokter_anestesi=? OR t.dokter_anak=? OR t.dokter_umum=?)';
        $types .= 'ssssss';
        array_push($params, $filters['kd_dokter'], $filters['kd_dokter'], $filters['kd_dokter'], $filters['kd_dokter'], $filters['kd_dokter'], $filters['kd_dokter']);
    }
    jm_add_common_filters($where, $types, $params, $filters, ['t.no_rawat', 'rp.no_rkm_medis', 'p.nm_pasien', 't.kode_paket', 'po.nm_perawatan', 'pj.png_jawab', 'd1.nm_dokter', 'da.nm_dokter']);
    $room_sql = "IF(t.status='Ralan',pl.nm_poli," . jm_room_ranap_sql('t') . ")";
    $sql = "SELECT t.no_rawat,rp.no_rkm_medis,p.nm_pasien,t.kode_paket AS kd_tindakan,po.nm_perawatan AS nm_tindakan,COALESCE(d1.nm_dokter,'-') AS operator1,COALESCE(da.nm_dokter,'-') AS dokter_anestesi,DATE(t.tgl_operasi) AS tanggal,TIME(t.tgl_operasi) AS jam,pj.png_jawab AS cara_bayar,COALESCE($room_sql,'-') AS ruangan,t.biayaoperator1,t.biayaoperator2,t.biayaoperator3,t.biayadokter_anestesi,t.biayadokter_anak,t.biaya_dokter_umum,t.bagian_rs,t.biayaalat,t.biayasewaok,t.akomodasi,$total_op AS total_biaya FROM operasi t INNER JOIN reg_periksa rp ON rp.no_rawat=t.no_rawat INNER JOIN pasien p ON p.no_rkm_medis=rp.no_rkm_medis INNER JOIN paket_operasi po ON po.kode_paket=t.kode_paket INNER JOIN penjab pj ON pj.kd_pj=rp.kd_pj LEFT JOIN poliklinik pl ON pl.kd_poli=rp.kd_poli LEFT JOIN dokter d1 ON d1.kd_dokter=t.operator1 LEFT JOIN dokter da ON da.kd_dokter=t.dokter_anestesi $where ORDER BY t.tgl_operasi DESC LIMIT $limit";
} elseif (in_array($tab, ['radiologi', 'laboratorium', 'detail_laboratorium'], true)) {
    $is_detail = $tab === 'detail_laboratorium';
    $is_rad = $tab === 'radiologi';
    $columns = array_merge(jm_base_columns(), [
        jm_col('kd_tindakan', $is_detail ? 'Id Template' : 'Kd.Prk'),
        jm_col('nm_tindakan', $is_detail ? 'Pemeriksaan Detail' : 'Pemeriksaan'),
        jm_col('kd_dokter', $is_rad ? 'Kode P.J.' : 'Kode P.J.'),
        jm_col('nm_dokter', $is_rad ? 'Dokter P.J.Rad' : 'Dokter P.J.Lab'),
        jm_col('nip', 'NIP'),
        jm_col('nm_petugas', $is_rad ? 'Petugas Rad' : 'Petugas Lab'),
        jm_col('kode_perujuk', 'Kode Perujuk'),
        jm_col('dokter_perujuk', 'Dokter Perujuk'),
        jm_col('tanggal', 'Tanggal'),
        jm_col('jam', 'Jam'),
        jm_col('cara_bayar', 'Cara Bayar'),
        jm_col('ruangan', 'Ruangan'),
    ], jm_money_columns(true, true, $is_detail));

    $header_alias = $is_detail ? 'h' : 't';
    $date_expr = "CONCAT(t.tgl_periksa,' ',t.jam)";
    $where = "WHERE $date_expr BETWEEN ? AND ?" . jm_status_sql($status);
    if ($filters['kd_dokter'] !== '') {
        $where .= " AND $header_alias.kd_dokter = ?";
        $types .= 's';
        $params[] = $filters['kd_dokter'];
    }
    if ($filters['kd_petugas'] !== '') {
        $where .= " AND $header_alias.nip = ?";
        $types .= 's';
        $params[] = $filters['kd_petugas'];
    }
    $search_cols = ['t.no_rawat', 'rp.no_rkm_medis', 'p.nm_pasien', "$header_alias.kd_jenis_prw", 'd.nm_dokter', "$header_alias.nip", 'pt.nama', "$header_alias.dokter_perujuk", 'perujuk.nm_dokter', 'pj.png_jawab'];
    $search_cols[] = $is_detail ? 'tl.Pemeriksaan' : 'j.nm_perawatan';
    jm_add_common_filters($where, $types, $params, $filters, $search_cols);
    $room_sql = "IF($header_alias.status='Ralan',pl.nm_poli," . jm_room_ranap_sql($header_alias) . ")";
    if ($is_detail) {
        $sql = "SELECT t.no_rawat,rp.no_rkm_medis,p.nm_pasien,t.id_template AS kd_tindakan,tl.Pemeriksaan AS nm_tindakan,h.kd_dokter,d.nm_dokter,h.nip,pt.nama AS nm_petugas,h.dokter_perujuk AS kode_perujuk,perujuk.nm_dokter AS dokter_perujuk,t.tgl_periksa AS tanggal,t.jam,pj.png_jawab AS cara_bayar,COALESCE($room_sql,'-') AS ruangan,t.bagian_rs AS jasa_sarana,t.bhp AS paket_bhp,t.bagian_dokter AS jm_dokter,t.bagian_laborat AS jm_paramedis,t.bagian_perujuk,t.bagian_laborat,t.kso,t.menejemen,t.biaya_item AS total_biaya FROM detail_periksa_lab t INNER JOIN periksa_lab h ON h.no_rawat=t.no_rawat AND h.kd_jenis_prw=t.kd_jenis_prw AND h.tgl_periksa=t.tgl_periksa AND h.jam=t.jam INNER JOIN reg_periksa rp ON rp.no_rawat=t.no_rawat INNER JOIN pasien p ON p.no_rkm_medis=rp.no_rkm_medis INNER JOIN template_laboratorium tl ON tl.id_template=t.id_template INNER JOIN dokter d ON d.kd_dokter=h.kd_dokter INNER JOIN petugas pt ON pt.nip=h.nip INNER JOIN dokter perujuk ON perujuk.kd_dokter=h.dokter_perujuk INNER JOIN penjab pj ON pj.kd_pj=rp.kd_pj LEFT JOIN poliklinik pl ON pl.kd_poli=rp.kd_poli $where ORDER BY t.tgl_periksa DESC, t.jam DESC LIMIT $limit";
    } else {
        $table = $is_rad ? 'periksa_radiologi' : 'periksa_lab';
        $jns_table = $is_rad ? 'jns_perawatan_radiologi' : 'jns_perawatan_lab';
        $sql = "SELECT t.no_rawat,rp.no_rkm_medis,p.nm_pasien,t.kd_jenis_prw AS kd_tindakan,j.nm_perawatan AS nm_tindakan,t.kd_dokter,d.nm_dokter,t.nip,pt.nama AS nm_petugas,t.dokter_perujuk AS kode_perujuk,perujuk.nm_dokter AS dokter_perujuk,t.tgl_periksa AS tanggal,t.jam,pj.png_jawab AS cara_bayar,COALESCE($room_sql,'-') AS ruangan,t.bagian_rs AS jasa_sarana,t.bhp AS paket_bhp,t.tarif_tindakan_dokter AS jm_dokter,t.tarif_tindakan_petugas AS jm_paramedis,t.kso,t.menejemen,t.biaya AS total_biaya FROM $table t INNER JOIN $jns_table j ON j.kd_jenis_prw=t.kd_jenis_prw INNER JOIN reg_periksa rp ON rp.no_rawat=t.no_rawat INNER JOIN pasien p ON p.no_rkm_medis=rp.no_rkm_medis INNER JOIN dokter d ON d.kd_dokter=t.kd_dokter INNER JOIN petugas pt ON pt.nip=t.nip INNER JOIN dokter perujuk ON perujuk.kd_dokter=t.dokter_perujuk INNER JOIN penjab pj ON pj.kd_pj=rp.kd_pj LEFT JOIN poliklinik pl ON pl.kd_poli=rp.kd_poli $where ORDER BY t.tgl_periksa DESC, t.jam DESC LIMIT $limit";
    }
}

$stmt = $koneksi->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Query tidak dapat diproses: ' . $koneksi->error, 'data' => [], 'columns' => $columns]);
    exit;
}

jm_bind_params($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
$summary = ['total_rows' => 0, 'total_biaya' => 0, 'jm_dokter' => 0, 'jm_paramedis' => 0, 'jasa_sarana' => 0, 'paket_bhp' => 0, 'kso' => 0, 'menejemen' => 0];
$money_keys = array_values(array_filter(array_map(function ($col) {
    return !empty($col['money']) ? $col['data'] : null;
}, $columns)));
$no = 1;

while ($row = $result->fetch_assoc()) {
    $row['no'] = $no++;
    foreach ($money_keys as $key) {
        $value = (float)($row[$key] ?? 0);
        $row[$key] = $value;
        if (isset($summary[$key])) {
            $summary[$key] += $value;
        }
    }
    foreach ($columns as $col) {
        $key = $col['data'];
        if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            $row[$key] = !empty($col['money']) ? 0 : '-';
        }
    }
    $summary['total_rows']++;
    $data[] = $row;
}
$stmt->close();

echo json_encode([
    'success' => true,
    'title' => $tabs[$tab],
    'columns' => $columns,
    'data' => $data,
    'summary' => $summary,
    'periode' => $tgl_awal . ' s/d ' . $tgl_akhir,
], JSON_UNESCAPED_UNICODE);

