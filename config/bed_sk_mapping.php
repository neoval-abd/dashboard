<?php
/*
 * Mapping lokal bed sesuai SK untuk pelaporan BPJS.
 * Tidak mengubah master kamar Khanza.
 */

function getSkBedKamarList()
{
    return [
        'FD3-Bed 1',
        'FD3-Bed 2',
        'FD3-Bed 3',
        'FD3-Bed 4',
        'FD3B-Bed 1',
        'FD3B-Bed 2',
        'FD3B-Bed 3',
        'FD3B-Bed 4',
        'FD3B-Bed 5',
        'FD3B-Bed 6',
        'FDS1A-Bed 2',
        'FDS1A-Bed1',
        'FDS1B-Bed 1 ',
        'FDS1B-Bed 2 ',
        'FDS1C-Bed1',
        'FDS1C-Bed2',
        'FDS1D-Bed1',
        'FDS1D-Bed2',
        'FDS2A-Bed 1',
        'FDS2A-Bed 2',
        'FDS2A-Bed 3',
        'FDS2B1-Bed 1',
        'FDS2B1-Bed 2',
        'ICU-Bed 1',
        'ICU-Bed 2',
        'ICU-Bed 3',
        'INKB1-Bed1',
        'INKB1-Bed2',
        'INKB1-Bed3',
        'ISO-ICU',
        'MRW-Lt3-A',
        'MRW-Lt3-B',
        'MZM-VIP-A',
        'MZM-VIP-B',
        'NBW1A-Bed 1',
        'NBW1A-Bed 2',
        'NBW1B-Bed 1',
        'NBW1B-Bed 2',
        'NBW1C-Bed 1',
        'NBW1C-Bed 2',
        'NBW2-Bed 1',
        'NBW2-Bed 2',
        'NBW3A-Bed 1',
        'NBW3A-Bed 2',
        'NBW3A-Bed 3',
        'NBW3A-Bed 4',
        'NBW3B-Bed 1',
        'NBW3B-Bed 2',
        'NBW3B-Bed 3',
        'NBW3B-Bed 4',
        'NBW3B-Bed 5',
        'NBW3B-Bed 6',
        'PRN-INKB 1',
        'PRN-INKB 2',
        'PRN-INKB 3',
        'RDH-A',
        'RDH-B',
        'SFAA-Lt2',
        'SFAB-Lt2',
        'SFAC-Lt2',
    ];
}

function getSkBedCapacity()
{
    return 60;
}

function getSkBedInSql(mysqli $koneksi)
{
    $escaped = array_map(function ($kd_kamar) use ($koneksi) {
        return "'" . $koneksi->real_escape_string($kd_kamar) . "'";
    }, getSkBedKamarList());

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
