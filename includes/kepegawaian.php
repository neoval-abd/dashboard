<?php
// Definisi field mengikuti penggajian/pages/pegawai/inputpegawai.php Khanza RS.
function kp_fields()
{
    return [
        'nik' => ['NIP / NIK Pegawai', 'text', 20, true],
        'nama' => ['Nama', 'text', 50, true],
        'jk' => ['Jenis Kelamin', 'select', ['Pria', 'Wanita'], true],
        'jbtn' => ['Jabatan', 'text', 25, true],
        'jnj_jabatan' => ['Jenjang Jabatan', 'master', ['jnj_jabatan', 'kode', 'nama'], true],
        'kode_kelompok' => ['Kelompok Jabatan', 'master', ['kelompok_jabatan', 'kode_kelompok', 'nama_kelompok'], true],
        'departemen' => ['Departemen', 'master', ['departemen', 'dep_id', 'nama'], true],
        'bidang' => ['Bagian / Bidang', 'master', ['bidang', 'nama', 'nama'], true],
        'kode_resiko' => ['Risiko Kerja', 'master', ['resiko_kerja', 'kode_resiko', 'nama_resiko'], true],
        'kode_emergency' => ['Tingkat Emergency', 'master', ['emergency_index', 'kode_emergency', 'nama_emergency'], true],
        'stts_wp' => ['Status WP', 'master', ['stts_wp', 'stts', 'ktg'], true],
        'stts_kerja' => ['Status Karyawan', 'master', ['stts_kerja', 'stts', 'ktg'], true],
        'npwp' => ['NPWP', 'text', 15, false],
        'pendidikan' => ['Pendidikan', 'master', ['pendidikan', 'tingkat', 'tingkat'], true],
        'tmp_lahir' => ['Tempat Lahir', 'text', 20, false],
        'tgl_lahir' => ['Tanggal Lahir', 'date', null, true],
        'alamat' => ['Alamat', 'text', 60, false],
        'kota' => ['Kota', 'text', 20, false],
        'mulai_kerja' => ['Mulai Kerja', 'date', null, true],
        'ms_kerja' => ['Kode Masa Kerja', 'select', ['<1', 'PT', 'FT>1'], true],
        'indexins' => ['Kode Index Insentif', 'master', ['indexins', 'dep_id', 'persen'], true],
        'bpd' => ['Bank', 'master', ['bank', 'namabank', 'namabank'], true],
        'rekening' => ['Nomor Rekening', 'text', 25, false],
        'stts_aktif' => ['Status Aktif', 'select', ['AKTIF', 'CUTI', 'KELUAR', 'TENAGA LUAR', 'NON AKTIF'], true],
        'wajibmasuk' => ['Wajib Masuk', 'number', [-5, 127], true],
        'mulai_kontrak' => ['Mulai Kontrak', 'date', null, false],
        'no_ktp' => ['Nomor KTP', 'text', 20, false],
    ];
}

function kp_query($db, $sql, $values = [])
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Prepare gagal: ' . $db->error, $db->errno);
    if ($values) $stmt->bind_param(str_repeat('s', count($values)), ...$values);
    if (!$stmt->execute()) throw new RuntimeException('Execute gagal: ' . $stmt->error, $stmt->errno);
    return $stmt;
}

function kp_rows($db, $sql, $values = [])
{
    $stmt = kp_query($db, $sql, $values);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function kp_can_access($db)
{
    if (empty($_SESSION['user_id'])) return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'Super Admin') return true;
    $rows = kp_rows($db, "SELECT pegawai_admin FROM user WHERE AES_DECRYPT(id_user, 'nur') = ?", [$_SESSION['user_id']]);
    return isset($rows[0]['pegawai_admin']) && $rows[0]['pegawai_admin'] === 'true';
}

function kp_can_write($db)
{
    return kp_can_access($db);
}

function kp_filter_sidebar($menus, $can_access)
{
    if (!is_array($menus)) return [];
    if ($can_access) return $menus;
    $visible = [];
    foreach ($menus as $menu) {
        $path = parse_url($menu['url'] ?? '', PHP_URL_PATH);
        if (basename((string) $path) === 'kepegawaian.php') continue;
        if (!empty($menu['is_group']) && isset($menu['items']) && is_array($menu['items'])) {
            $menu['items'] = kp_filter_sidebar($menu['items'], false);
            if (!$menu['items']) continue;
        }
        $visible[] = $menu;
    }
    return $visible;
}

function kp_options($db)
{
    $options = [];
    foreach (kp_fields() as $field => $def) {
        if ($def[1] === 'master') {
            list($table, $key, $label) = $def[2];
            $options[$field] = kp_rows($db, "SELECT `$key` AS value, `$label` AS label FROM `$table` ORDER BY `$label`");
        } elseif ($def[1] === 'select') {
            $options[$field] = array_map(function ($v) { return ['value' => $v, 'label' => $v]; }, $def[2]);
        }
    }
    return $options;
}

function kp_valid_date($value)
{
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return $d && $d->format('Y-m-d') === $value;
}

function kp_reference_date($db)
{
    // Dashboard menampilkan kondisi terkini, bukan periode penggajian yang tersimpan.
    // Tanggal historis tetap dapat dipilih secara eksplisit melalui filter.
    return date('Y-m-d');
}

function kp_validate_employee($input, $options)
{
    $data = [];
    foreach (kp_fields() as $field => $def) {
        if (isset($input[$field]) && !is_scalar($input[$field])) throw new InvalidArgumentException($def[0] . ' tidak valid.');
        $value = trim((string) ($input[$field] ?? ''));
        if ($def[3] && $value === '') throw new InvalidArgumentException($def[0] . ' wajib diisi.');
        if ($def[1] === 'text') {
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
            if ($length > $def[2] || preg_match('/[\x00-\x1f\x7f]/', $value)) throw new InvalidArgumentException($def[0] . ' tidak valid atau terlalu panjang (maks. ' . $def[2] . ').');
        } elseif ($def[1] === 'master' || $def[1] === 'select') {
            if (!in_array($value, array_map('strval', array_column($options[$field], 'value')), true)) throw new InvalidArgumentException('Pilihan ' . $def[0] . ' tidak terdaftar.');
        } elseif ($def[1] === 'date') {
            if ($value !== '' && !kp_valid_date($value)) throw new InvalidArgumentException($def[0] . ' tidak valid.');
            if ($value === '') $value = null;
        } elseif ($def[1] === 'number') {
            if (!preg_match('/^-?\d+$/', $value) || (int) $value < $def[2][0] || (int) $value > $def[2][1]) throw new InvalidArgumentException($def[0] . ' di luar batas.');
        }
        $data[$field] = $value;
    }
    return $data;
}

function kp_save_employee($db, $input, $options)
{
    $data = kp_validate_employee($input, $options);
    $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($id === false) throw new InvalidArgumentException('ID pegawai tidak valid.');
    if (!$db->begin_transaction()) throw new RuntimeException('Transaksi tidak dapat dimulai.');
    try {
        if ($id) {
            $old = kp_rows($db, 'SELECT nik FROM pegawai WHERE id = ? FOR UPDATE', [$id]);
            if (!$old) throw new InvalidArgumentException('Pegawai tidak ditemukan.');
            if ($old[0]['nik'] !== $data['nik']) throw new InvalidArgumentException('NIP pegawai yang sudah tersimpan tidak dapat diganti melalui dashboard.');
        }
        $duplicates = kp_rows($db, 'SELECT id FROM pegawai WHERE nik = ? AND id <> ?', [$data['nik'], $id]);
        if ($duplicates) throw new InvalidArgumentException('NIP / NIK pegawai sudah digunakan.');
        if ($id) {
            $assignments = array_map(function ($key) { return '`' . $key . '` = ?'; }, array_keys($data));
            kp_query($db, 'UPDATE pegawai SET ' . implode(', ', $assignments) . ' WHERE id = ?', array_merge(array_values($data), [$id]))->close();
            // Sama dengan formulir Khanza: perubahan biodata diteruskan ke master terkait.
            $sex = $data['jk'] === 'Pria' ? 'L' : 'P';
            $sync = [$data['nama'], $sex, $data['tmp_lahir'], $data['tgl_lahir'], $data['alamat'], $data['nik']];
            kp_query($db, 'UPDATE dokter SET nm_dokter=?, jk=?, tmp_lahir=?, tgl_lahir=?, almt_tgl=? WHERE kd_dokter=?', $sync)->close();
            kp_query($db, 'UPDATE petugas SET nama=?, jk=?, tmp_lahir=?, tgl_lahir=?, alamat=? WHERE nip=?', $sync)->close();
        } else {
            $data += ['gapok' => 0, 'pengurang' => 0, 'indek' => 0, 'cuti_diambil' => 0, 'dankes' => 0, 'photo' => null];
            $columns = '`' . implode('`,`', array_keys($data)) . '`';
            kp_query($db, 'INSERT INTO pegawai (' . $columns . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data))->close();
            $id = $db->insert_id;
        }
        if (!$db->commit()) throw new RuntimeException('Commit gagal.');
        return $id;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

function kp_save_index($db, $input)
{
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) throw new InvalidArgumentException('Pilih pegawai terlebih dahulu.');
    $data = [];
    foreach (['indek' => [0, 127, true], 'pengurang' => [0, 100, false], 'cuti_diambil' => [0, 2147483647, true], 'dankes' => [0, 1000000000000, false]] as $field => $limits) {
        $value = $input[$field] ?? '';
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float) $value) || $value < $limits[0] || $value > $limits[1] || ($limits[2] && !preg_match('/^\d+$/', (string) $value))) throw new InvalidArgumentException('Nilai ' . $field . ' tidak valid.');
        $data[] = $value;
    }
    if (!kp_rows($db, 'SELECT id FROM pegawai WHERE id=?', [$id])) throw new InvalidArgumentException('Pegawai tidak ditemukan.');
    kp_query($db, 'UPDATE pegawai SET indek=?, pengurang=?, cuti_diambil=?, dankes=? WHERE id=?', array_merge($data, [$id]))->close();
}

function kp_service_period($start, $reference)
{
    if (!$start || !kp_valid_date($start) || !kp_valid_date($reference)) return null;
    return (new DateTimeImmutable($start))->diff(new DateTimeImmutable($reference));
}

function kp_duration($start, $reference)
{
    $period = kp_service_period($start, $reference);
    if (!$period) return '-';
    return $period->invert ? 'Belum mulai' : $period->y . ' Tahun ' . $period->m . ' Bulan';
}

function kp_employee_list($db, $status, $keyword, $department, $reference)
{
    if (!kp_valid_date($reference)) throw new InvalidArgumentException('Tanggal acuan tidak valid.');
    $where = [];
    $params = [];
    if ($status !== '') {
        if (!in_array($status, kp_fields()['stts_aktif'][2], true)) throw new InvalidArgumentException('Status tidak valid.');
        $where[] = 'p.stts_aktif=?'; $params[] = $status;
    }
    if ($department !== '') { $where[] = 'p.departemen=?'; $params[] = $department; }
    if ($keyword !== '') {
        $where[] = '(p.nik LIKE ? OR p.nama LIKE ? OR p.jbtn LIKE ? OR p.pendidikan LIKE ? OR d.nama LIKE ?)';
        $escaped = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $where[count($where) - 1] = str_replace('LIKE ?', "LIKE ? ESCAPE '!'", end($where));
        for ($i = 0; $i < 5; $i++) $params[] = $escaped;
    }
    $year = substr($reference, 0, 4);
    // JOIN kiri mempertahankan pegawai meski referensi master belum lengkap.
    $sql = "SELECT p.*, j.nama AS jenjang, k.nama_kelompok AS kelompok, d.nama AS departemen_nama,
        r.nama_resiko AS risiko, e.nama_emergency AS emergency, w.ktg AS status_wp, s.ktg AS status_kerja,
        j.indek AS index_jabatan, k.indek AS index_kelompok, r.indek AS index_resiko,
        e.indek AS index_emergency, pd.indek AS index_pendidikan, s.indek AS index_status,
        s.hakcuti, pd.gapok1, pd.kenaikan, pd.maksimal,
        (SELECT ev.indek FROM evaluasi_kinerja_pegawai ep JOIN evaluasi_kinerja ev ON ev.kode_evaluasi=ep.kode_evaluasi WHERE ep.id=p.id ORDER BY ep.tahun, ep.bulan DESC LIMIT 1) AS index_evaluasi,
        (SELECT pc.indek FROM pencapaian_kinerja_pegawai pp JOIN pencapaian_kinerja pc ON pc.kode_pencapaian=pp.kode_pencapaian WHERE pp.id=p.id ORDER BY pp.tahun, pp.bulan DESC LIMIT 1) AS index_pencapaian,
        (SELECT SUM(jml) FROM ketidakhadiran WHERE id=p.id AND tgl LIKE ? AND jns='C') AS cuti_lampiran,
        (SELECT SUM(jumlah) FROM pengajuan_cuti WHERE nik=p.nik AND tanggal_awal LIKE ? AND status='Disetujui') AS cuti_pengajuan,
        (SELECT SUM(dankes) FROM ambil_dankes WHERE id=p.id AND tanggal LIKE ?) AS dankes_diambil
        FROM pegawai p
        LEFT JOIN jnj_jabatan j ON j.kode=p.jnj_jabatan
        LEFT JOIN kelompok_jabatan k ON k.kode_kelompok=p.kode_kelompok
        LEFT JOIN departemen d ON d.dep_id=p.departemen
        LEFT JOIN resiko_kerja r ON r.kode_resiko=p.kode_resiko
        LEFT JOIN emergency_index e ON e.kode_emergency=p.kode_emergency
        LEFT JOIN stts_wp w ON w.stts=p.stts_wp
        LEFT JOIN stts_kerja s ON s.stts=p.stts_kerja
        LEFT JOIN pendidikan pd ON pd.tingkat=p.pendidikan";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $rows = kp_rows($db, $sql . ' ORDER BY p.id ASC LIMIT 5001', array_merge([$year . '%', $year . '%', $year . '%'], $params));
    if (count($rows) > 5000) throw new InvalidArgumentException('Data terlalu banyak. Persempit filter departemen atau kata kunci.');
    foreach ($rows as &$row) {
        $period = kp_service_period($row['mulai_kerja'], $reference);
        $years = $period && !$period->invert ? $period->y : 0;
        $row['index_masa_kerja'] = min(14, $years * 2);
        $row['lama_kerja'] = kp_duration($row['mulai_kerja'], $reference);
        $row['lama_kontrak'] = kp_duration($row['mulai_kontrak'], $reference);
        $base = (float) $row['indek'] + $row['index_masa_kerja'];
        foreach (['index_pendidikan', 'index_status', 'index_jabatan', 'index_kelompok', 'index_resiko', 'index_emergency', 'index_evaluasi', 'index_pencapaian'] as $field) {
            $row[$field] = (float) ($row[$field] ?? 0);
            $base += $row[$field];
        }
        // Khanza memakai persentase pengali ketika pengurang > 0.
        $row['total_index'] = (float) $row['pengurang'] > 0 ? $base * (float) $row['pengurang'] / 100 : $base;
        $contract = $row['mulai_kontrak'] && kp_valid_date($row['mulai_kontrak']) ? (new DateTime($row['mulai_kontrak']))->diff(new DateTime($reference)) : null;
        $contractYears = $contract && !$contract->invert ? $contract->days / 365 : 0;
        $row['gaji_pokok'] = (float) $row['gapok1'] + (float) $row['kenaikan'] * ($contractYears < (float) $row['maksimal'] ? round($contractYears) : (float) $row['maksimal']);
        $row['total_cuti_diambil'] = (float) $row['cuti_diambil'] + (float) $row['cuti_lampiran'] + (float) $row['cuti_pengajuan'];
        $row['sisa_cuti'] = (float) $row['hakcuti'] - $row['total_cuti_diambil'];
        $row['sisa_dankes'] = (float) $row['dankes'] - (float) $row['dankes_diambil'];
    }
    unset($row);
    return $rows;
}
