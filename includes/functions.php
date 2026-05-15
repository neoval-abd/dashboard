<?php
/*
 * File functions.php (UPDATE V2)
 * Berisi semua fungsi helper terpusat.
 */

// 1. Fungsi Format Rupiah
function formatRupiah($angka) {
    $hasil_rupiah = "Rp " . number_format($angka, 0, ',', '.');
    return $hasil_rupiah;
}

// 2. Fungsi Mengambil Jam Shift
function getShiftTimes($koneksi) {
    $shifts = [];
    $sql = "SELECT closing_kasir.shift, closing_kasir.jam_masuk, closing_kasir.jam_pulang FROM closing_kasir";
    $result = $koneksi->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $shifts[$row['shift']] = [
                'masuk' => $row['jam_masuk'],
                'pulang' => $row['jam_pulang']
            ];
        }
    }
    return $shifts;
}

// 3. Fungsi Menghitung Rentang DateTime untuk Kueri
function getShiftDateTimeRange($tanggal_str, $shift, $shift_times) {
    if (!isset($shift_times[$shift])) {
        return null; 
    }
    $jam_masuk = $shift_times[$shift]['masuk'];
    $jam_pulang = $shift_times[$shift]['pulang'];
    
    $dt_awal_str = $tanggal_str . ' ' . $jam_masuk;
    $dt_akhir_str = $tanggal_str . ' ' . $jam_pulang;

    // Logika lintas hari (Shift Malam)
    if (strtotime($jam_masuk) > strtotime($jam_pulang)) {
        $tanggal_obj = new DateTime($tanggal_str);
        $tanggal_obj->modify('+1 day');
        $tanggal_akhir_str = $tanggal_obj->format('Y-m-d');
        $dt_akhir_str = $tanggal_akhir_str . ' ' . $jam_pulang;
    }

    return [
        'start' => $dt_awal_str,
        'end' => $dt_akhir_str
    ];
}

// --- FUNGSI TAMBAHAN DARI KUNJUNGAN AKTIF ---

// 4. Cari Isi Angka (Meniru Sequel.java)
function cariIsiAngka($conn, $sql, $no_rawat) {
    $value = 0;
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $no_rawat);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array()) {
            $value = $row[0];
        }
        $stmt->close();
    }
    // Pastikan return 0 jika null
    return floatval($value);
}

// 5. Cari Isi String
function cariIsi($conn, $sql, $no_rawat) {
    $value = "";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $no_rawat);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_array()) {
            $value = $row[0];
        }
        $stmt->close();
    }
    return $value;
}

?>