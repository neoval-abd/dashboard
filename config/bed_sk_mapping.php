<?php
/*
 * Mapping lokal bed sesuai SK untuk pelaporan BPJS.
 * Tidak mengubah master kamar Khanza.
 */

function getSkBedFloorMapping()
{
    return [
        // =====================================================================
        // RAWAT INAP LANTAI 2 (Total 26 Bed Rawat Inap + 3 Bed NICU = 29 Bed)
        // =====================================================================
        // VIP
        'MZM-VIP-A'   => ['lantai' => 2, 'ruangan' => 'Multazam VIP A', 'ruangan_group' => 'Multazam VIP', 'kelas' => 'VIP', 'kris' => true, 'kapasitas_ruang' => 1],
        'MZM-VIP-B'   => ['lantai' => 2, 'ruangan' => 'Multazam VIP B', 'ruangan_group' => 'Multazam VIP', 'kelas' => 'VIP', 'kris' => false, 'kapasitas_ruang' => 1],
        // Kelas 1
        'NBW1A-Bed 1' => ['lantai' => 2, 'ruangan' => 'Nabawi 1 A', 'ruangan_group' => 'Nabawi 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'NBW1A-Bed 2' => ['lantai' => 2, 'ruangan' => 'Nabawi 1 A', 'ruangan_group' => 'Nabawi 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'NBW1B-Bed 1' => ['lantai' => 2, 'ruangan' => 'Nabawi 1 B', 'ruangan_group' => 'Nabawi 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'NBW1B-Bed 2' => ['lantai' => 2, 'ruangan' => 'Nabawi 1 B', 'ruangan_group' => 'Nabawi 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'NBW1C-Bed 1' => ['lantai' => 2, 'ruangan' => 'Nabawi 1 C', 'ruangan_group' => 'Nabawi 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'NBW1C-Bed 2' => ['lantai' => 2, 'ruangan' => 'Nabawi 1 C', 'ruangan_group' => 'Nabawi 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        // Kelas 2
        'NBW2-Bed 1'  => ['lantai' => 2, 'ruangan' => 'Nabawi 2', 'ruangan_group' => 'Nabawi 2', 'kelas' => 'Kelas 2', 'kris' => true, 'kapasitas_ruang' => 2],
        'NBW2-Bed 2'  => ['lantai' => 2, 'ruangan' => 'Nabawi 2', 'ruangan_group' => 'Nabawi 2', 'kelas' => 'Kelas 2', 'kris' => true, 'kapasitas_ruang' => 2],
        // Kelas 3
        'NBW3A-Bed 1' => ['lantai' => 2, 'ruangan' => 'Nabawi 3A', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'NBW3A-Bed 2' => ['lantai' => 2, 'ruangan' => 'Nabawi 3A', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'NBW3A-Bed 3' => ['lantai' => 2, 'ruangan' => 'Nabawi 3A', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'NBW3A-Bed 4' => ['lantai' => 2, 'ruangan' => 'Nabawi 3A', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'NBW3B-Bed 1' => ['lantai' => 2, 'ruangan' => 'Nabawi 3B', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'NBW3B-Bed 2' => ['lantai' => 2, 'ruangan' => 'Nabawi 3B', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'NBW3B-Bed 3' => ['lantai' => 2, 'ruangan' => 'Nabawi 3B', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'NBW3B-Bed 4' => ['lantai' => 2, 'ruangan' => 'Nabawi 3B', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'NBW3B-Bed 5' => ['lantai' => 2, 'ruangan' => 'Nabawi 3B', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'NBW3B-Bed 6' => ['lantai' => 2, 'ruangan' => 'Nabawi 3B', 'ruangan_group' => 'Nabawi 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        // Non Kelas (Perinatologi level 2)
        'INKB1-Bed1'  => ['lantai' => 2, 'ruangan' => 'Perinatologi level 2', 'ruangan_group' => 'Perinatologi', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        'INKB1-Bed2'  => ['lantai' => 2, 'ruangan' => 'Perinatologi level 2', 'ruangan_group' => 'Perinatologi', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        'INKB1-Bed3'  => ['lantai' => 2, 'ruangan' => 'Perinatologi level 2', 'ruangan_group' => 'Perinatologi', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        // Isolasi Safa
        'SFAA-Lt2'    => ['lantai' => 2, 'ruangan' => 'Safa A', 'ruangan_group' => 'Safa Isolasi', 'kelas' => 'Isolasi', 'kris' => false, 'kapasitas_ruang' => 1],
        'SFAB-Lt2'    => ['lantai' => 2, 'ruangan' => 'Safa B', 'ruangan_group' => 'Safa Isolasi', 'kelas' => 'Isolasi', 'kris' => false, 'kapasitas_ruang' => 1],
        'SFAC-Lt2'    => ['lantai' => 2, 'ruangan' => 'Safa C', 'ruangan_group' => 'Safa Isolasi', 'kelas' => 'Isolasi', 'kris' => false, 'kapasitas_ruang' => 1],
        // Ruang Intensif: NICU (Lantai 2 sesuai instruksi mapping)
        'PRN-INKB 1'  => ['lantai' => 2, 'ruangan' => 'NICU', 'ruangan_group' => 'NICU', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        'PRN-INKB 2'  => ['lantai' => 2, 'ruangan' => 'NICU', 'ruangan_group' => 'NICU', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        'PRN-INKB 3'  => ['lantai' => 2, 'ruangan' => 'NICU', 'ruangan_group' => 'NICU', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],

        // =====================================================================
        // RAWAT INAP LANTAI 3 (Total 27 Bed Rawat Inap + 4 Bed ICU = 31 Bed)
        // =====================================================================
        // VIP
        'RDH-A'       => ['lantai' => 3, 'ruangan' => 'Raudhah VIP A', 'ruangan_group' => 'Raudhah VIP', 'kelas' => 'VIP', 'kris' => false, 'kapasitas_ruang' => 1],
        'RDH-B'       => ['lantai' => 3, 'ruangan' => 'Raudhah VIP B', 'ruangan_group' => 'Raudhah VIP', 'kelas' => 'VIP', 'kris' => false, 'kapasitas_ruang' => 1],
        // Kelas 1
        'FDS1A-Bed1'  => ['lantai' => 3, 'ruangan' => 'Firdaus 1A', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'FDS1A-Bed 2' => ['lantai' => 3, 'ruangan' => 'Firdaus 1A', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'FDS1B-Bed 1 '=> ['lantai' => 3, 'ruangan' => 'Firdaus 1B', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'FDS1B-Bed 2 '=> ['lantai' => 3, 'ruangan' => 'Firdaus 1B', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'FDS1C-Bed1'  => ['lantai' => 3, 'ruangan' => 'Firdaus 1C', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => false, 'kapasitas_ruang' => 2],
        'FDS1C-Bed2'  => ['lantai' => 3, 'ruangan' => 'Firdaus 1C', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => false, 'kapasitas_ruang' => 2],
        'FDS1D-Bed1'  => ['lantai' => 3, 'ruangan' => 'Firdaus 1D', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        'FDS1D-Bed2'  => ['lantai' => 3, 'ruangan' => 'Firdaus 1D', 'ruangan_group' => 'Firdaus 1', 'kelas' => 'Kelas 1', 'kris' => true, 'kapasitas_ruang' => 2],
        // Kelas 2
        'FDS2A-Bed 1' => ['lantai' => 3, 'ruangan' => 'Firdaus II A 1', 'ruangan_group' => 'Firdaus 2', 'kelas' => 'Kelas 2', 'kris' => true, 'kapasitas_ruang' => 3],
        'FDS2A-Bed 2' => ['lantai' => 3, 'ruangan' => 'Firdaus II A 1', 'ruangan_group' => 'Firdaus 2', 'kelas' => 'Kelas 2', 'kris' => true, 'kapasitas_ruang' => 3],
        'FDS2A-Bed 3' => ['lantai' => 3, 'ruangan' => 'Firdaus II A 1', 'ruangan_group' => 'Firdaus 2', 'kelas' => 'Kelas 2', 'kris' => true, 'kapasitas_ruang' => 3],
        'FDS2B1-Bed 1'=> ['lantai' => 3, 'ruangan' => 'Firdaus II B 1', 'ruangan_group' => 'Firdaus 2', 'kelas' => 'Kelas 2', 'kris' => true, 'kapasitas_ruang' => 2],
        'FDS2B1-Bed 2'=> ['lantai' => 3, 'ruangan' => 'Firdaus II B 1', 'ruangan_group' => 'Firdaus 2', 'kelas' => 'Kelas 2', 'kris' => true, 'kapasitas_ruang' => 2],
        // Kelas 3
        'FD3-Bed 1'   => ['lantai' => 3, 'ruangan' => 'Firdaus III A', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'FD3-Bed 2'   => ['lantai' => 3, 'ruangan' => 'Firdaus III A', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'FD3-Bed 3'   => ['lantai' => 3, 'ruangan' => 'Firdaus III A', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'FD3-Bed 4'   => ['lantai' => 3, 'ruangan' => 'Firdaus III A', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => true, 'kapasitas_ruang' => 4],
        'FD3B-Bed 1'  => ['lantai' => 3, 'ruangan' => 'Firdaus III B', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'FD3B-Bed 2'  => ['lantai' => 3, 'ruangan' => 'Firdaus III B', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'FD3B-Bed 3'  => ['lantai' => 3, 'ruangan' => 'Firdaus III B', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'FD3B-Bed 4'  => ['lantai' => 3, 'ruangan' => 'Firdaus III B', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'FD3B-Bed 5'  => ['lantai' => 3, 'ruangan' => 'Firdaus III B', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        'FD3B-Bed 6'  => ['lantai' => 3, 'ruangan' => 'Firdaus III B', 'ruangan_group' => 'Firdaus 3', 'kelas' => 'Kelas 3', 'kris' => false, 'kapasitas_ruang' => 6],
        // Isolasi Marwah
        'MRW-Lt3-A'   => ['lantai' => 3, 'ruangan' => 'Marwah A', 'ruangan_group' => 'Marwah Isolasi', 'kelas' => 'Isolasi', 'kris' => false, 'kapasitas_ruang' => 1],
        'MRW-Lt3-B'   => ['lantai' => 3, 'ruangan' => 'Marwah B', 'ruangan_group' => 'Marwah Isolasi', 'kelas' => 'Isolasi', 'kris' => false, 'kapasitas_ruang' => 1],
        // Ruang Intensif: ICU & Isolasi ICU (Lantai 3 sesuai instruksi mapping)
        'ICU-Bed 1'   => ['lantai' => 3, 'ruangan' => 'ICU', 'ruangan_group' => 'ICU', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        'ICU-Bed 2'   => ['lantai' => 3, 'ruangan' => 'ICU', 'ruangan_group' => 'ICU', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        'ICU-Bed 3'   => ['lantai' => 3, 'ruangan' => 'ICU', 'ruangan_group' => 'ICU', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 3],
        'ISO-ICU'     => ['lantai' => 3, 'ruangan' => 'Isolasi ICU', 'ruangan_group' => 'ICU', 'kelas' => 'Non Kelas', 'kris' => false, 'kapasitas_ruang' => 1],
    ];
}

function getSkBedKamarList()
{
    return array_keys(getSkBedFloorMapping());
}

function getSkBedCapacity()
{
    return 60;
}

function getSkBedInSql(mysqli $koneksi)
{
    $bed_list = getSkBedKamarList();
    if (empty($bed_list)) {
        return '';
    }

    $escaped = array_map(function ($kd_kamar) use ($koneksi) {
        return "'" . $koneksi->real_escape_string($kd_kamar) . "'";
    }, $bed_list);

    return implode(',', $escaped);
}

function calculateSkHariPerawatan(mysqli $koneksi, $tgl_awal, $tgl_akhir, $kd_bangsal = '')
{
    $bed_in = getSkBedInSql($koneksi);
    if ($bed_in === '') {
        return 0;
    }

    $where_bangsal = '';
    if ($kd_bangsal !== '') {
        $where_bangsal = " AND k.kd_bangsal = '" . $koneksi->real_escape_string($kd_bangsal) . "'";
    }

    $awal_sql = $koneksi->real_escape_string($tgl_awal);
    $akhir_sql = $koneksi->real_escape_string($tgl_akhir);
    $sql = "
        SELECT ki.kd_kamar, k.kd_bangsal, ki.tgl_masuk,
               IF(ki.tgl_keluar IS NULL OR ki.tgl_keluar = '0000-00-00' OR ki.stts_pulang = '-', '$akhir_sql', ki.tgl_keluar) AS tgl_keluar_hitung
        FROM kamar_inap ki
        INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
        WHERE ki.kd_kamar IN ($bed_in)
          AND ki.tgl_masuk <= '$akhir_sql'
          AND (
              ki.tgl_keluar >= '$awal_sql'
              OR ki.tgl_keluar IS NULL
              OR ki.tgl_keluar = '0000-00-00'
              OR ki.stts_pulang = '-'
          )
          $where_bangsal
    ";

    $occupied = [];
    $res = $koneksi->query($sql);
    if (!$res) {
        return 0;
    }

    while ($row = $res->fetch_assoc()) {
        $start = max($row['tgl_masuk'], $tgl_awal);
        $end = min($row['tgl_keluar_hitung'], $tgl_akhir);
        if ($end < $start) {
            continue;
        }

        $period = new DatePeriod(
            new DateTime($start),
            new DateInterval('P1D'),
            (new DateTime($end))->modify('+1 day')
        );

        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            if (!isset($occupied[$key])) {
                $occupied[$key] = [];
            }
            $occupied[$key][$row['kd_kamar']] = true;
        }
    }

    $total = 0;
    foreach ($occupied as $beds) {
        $total += count($beds);
    }

    return $total;
}

function countSkBeds(mysqli $koneksi, $kd_bangsal = '')
{
    if ($kd_bangsal === '') {
        return getSkBedCapacity();
    }

    $bed_in = getSkBedInSql($koneksi);
    $kd_bangsal_sql = $koneksi->real_escape_string($kd_bangsal);
    $sql = "
        SELECT COUNT(*) AS total
        FROM kamar
        WHERE kd_kamar IN ($bed_in)
          AND kd_bangsal = '$kd_bangsal_sql'
          AND statusdata = '1'
    ";
    $res = $koneksi->query($sql);
    return ($res && $row = $res->fetch_assoc()) ? (int)$row['total'] : 0;
}
?>
