<?php
/*
 * File: api/data_detail_operasi.php (UPDATE V5 - PHP LOGIC FETCH)
 * Fungsi: Menampilkan rincian biaya operasi.
 * Perbaikan: Menggunakan logika PHP untuk mencocokkan Nama Dokter/Petugas
 * agar lebih tahan banting terhadap inkonsistensi data database.
 */

ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

$no_rawat = isset($_GET['no_rawat']) ? $_GET['no_rawat'] : '';
$tgl_operasi = isset($_GET['tgl_operasi']) ? $_GET['tgl_operasi'] : ''; 

if(empty($no_rawat)) { echo json_encode([]); exit; }

// 1. AMBIL DATA TRANSAKSI OPERASI (RAW)
$sql = "SELECT * FROM operasi WHERE no_rawat = ? AND tgl_operasi = ?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("ss", $no_rawat, $tgl_operasi);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if(!$row) { echo json_encode(null); exit; }

// 2. MAPPING KOLOM (Mana Kode, Mana Biaya, Tabel Referensinya Apa)
// Format: [Label, KolomKode, KolomBiaya, TabelRef]
$map = [
    // Dokter (Ref: dokter)
    ['Operator Utama', 'operator1', 'biayaoperator1', 'dokter'],
    ['Operator 2', 'operator2', 'biayaoperator2', 'dokter'],
    ['Operator 3', 'operator3', 'biayaoperator3', 'dokter'],
    ['Dokter Anestesi', 'dokter_anestesi', 'biayadokter_anestesi', 'dokter'],
    ['Dokter Anak', 'dokter_anak', 'biayadokter_anak', 'dokter'],
    ['Dokter Umum', 'dokter_umum', 'biaya_dokter_umum', 'dokter'],
    ['Dokter PJ Anak', 'dokter_pjanak', 'biaya_dokter_pjanak', 'dokter'],
    
    // Petugas (Ref: petugas)
    ['Asisten Op 1', 'asisten_operator1', 'biayaasisten_operator1', 'petugas'],
    ['Asisten Op 2', 'asisten_operator2', 'biayaasisten_operator2', 'petugas'],
    ['Asisten Op 3', 'asisten_operator3', 'biayaasisten_operator3', 'petugas'],
    ['Instrumen', 'instrumen', 'biayainstrumen', 'petugas'],
    ['Perawat Resus', 'perawaat_resusitas', 'biayaperawaat_resusitas', 'petugas'],
    ['Asisten Anest 1', 'asisten_anestesi', 'biayaasisten_anestesi', 'petugas'],
    ['Asisten Anest 2', 'asisten_anestesi2', 'biayaasisten_anestesi2', 'petugas'],
    ['Bidan 1', 'bidan', 'biayabidan', 'petugas'],
    ['Bidan 2', 'bidan2', 'biayabidan2', 'petugas'],
    ['Bidan 3', 'bidan3', 'biayabidan3', 'petugas'],
    ['Perawat Luar', 'perawat_luar', 'biayaperawat_luar', 'petugas'],
    ['Omloop 1', 'omloop', 'biaya_omloop', 'petugas'],
    ['Omloop 2', 'omloop2', 'biaya_omloop2', 'petugas'],
    ['Omloop 3', 'omloop3', 'biaya_omloop3', 'petugas'],
    ['Omloop 4', 'omloop4', 'biaya_omloop4', 'petugas'],
    ['Omloop 5', 'omloop5', 'biaya_omloop5', 'petugas'],
];

// 3. KUMPULKAN ID UNTUK DI-QUERY (Batching)
$dokter_ids = [];
$petugas_ids = [];

foreach ($map as $m) {
    $kode = trim($row[$m[1]]); // Bersihkan spasi
    $biaya = (float)$row[$m[2]];
    
    if ($biaya > 0 && !empty($kode) && $kode != '-') {
        if ($m[3] == 'dokter') $dokter_ids[] = "'$kode'";
        else $petugas_ids[] = "'$kode'";
    }
}

// 4. AMBIL NAMA DARI DATABASE (Sekaligus)
$master_nama = []; // Dictionary: [kode => nama]

// Cari Dokter
if (!empty($dokter_ids)) {
    $ids = implode(',', array_unique($dokter_ids));
    $q = $koneksi->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE kd_dokter IN ($ids)");
    while($r = $q->fetch_assoc()) {
        $master_nama[$r['kd_dokter']] = $r['nm_dokter'];
    }
}

// Cari Petugas
if (!empty($petugas_ids)) {
    $ids = implode(',', array_unique($petugas_ids));
    $q = $koneksi->query("SELECT nip, nama FROM petugas WHERE nip IN ($ids)");
    while($r = $q->fetch_assoc()) {
        $master_nama[$r['nip']] = $r['nama'];
    }
}

// 5. SUSUN HASIL AKHIR
$clean_details = [];
$total = 0;

// A. Loop Personel
foreach ($map as $m) {
    $biaya = (float)$row[$m[2]];
    if ($biaya > 0) {
        $kode = trim($row[$m[1]]);
        $penerima = 'Rumah Sakit'; // Default jika kode kosong tapi ada biaya
        
        if (!empty($kode) && $kode != '-') {
            // Cek di dictionary master_nama
            if (isset($master_nama[$kode])) {
                $penerima = $master_nama[$kode];
            } else {
                $penerima = $kode . " (Tanpa Nama)"; // Fallback: Tampilkan Kodenya
            }
        }
        
        $clean_details[] = [
            'komponen' => $m[0],
            'penerima' => $penerima,
            'nilai'    => $biaya
        ];
        $total += $biaya;
    }
}

// B. Loop Non-Personel (Sarpras RS)
$komponen_rs = [
    ['Sewa OK/VK', 'biayasewaok'],
    ['Alat & BHP', 'biayaalat'],
    ['Akomodasi', 'akomodasi'],
    ['Bagian RS', 'bagian_rs'],
    ['Sarpras', 'biayasarpras'],
];

foreach ($komponen_rs as $rs_item) {
    $biaya = (float)$row[$rs_item[1]];
    if ($biaya > 0) {
        $clean_details[] = [
            'komponen' => $rs_item[0],
            'penerima' => 'Rumah Sakit',
            'nilai'    => $biaya
        ];
        $total += $biaya;
    }
}

echo json_encode(['rincian' => $clean_details, 'total' => $total]);
?>