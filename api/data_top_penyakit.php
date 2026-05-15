<?php
/*
 * File: api/data_top_penyakit.php (UPDATE V2)
 * Fungsi: Agregasi Top 10 Penyakit + Filter Status Lanjut.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once(dirname(__DIR__) . '/config/koneksi.php'); 

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error' => 'Akses ditolak.']); exit;
}

// 1. Ambil Parameter
$tgl_awal = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$status_lanjut = isset($_GET['status_lanjut']) ? $_GET['status_lanjut'] : ''; // Filter Baru

// 2. Query Agregasi
$where_tambahan = "";
$params = [$tgl_awal, $tgl_akhir];
$types = "ss";

if (!empty($status_lanjut)) {
    $where_tambahan = " AND reg_periksa.status_lanjut = ? ";
    $params[] = $status_lanjut;
    $types .= "s";
}

$sql = "
    SELECT 
        diagnosa_pasien.kd_penyakit,
        penyakit.nm_penyakit,
        COUNT(diagnosa_pasien.no_rawat) as jumlah_kasus
    FROM diagnosa_pasien
    INNER JOIN reg_periksa ON diagnosa_pasien.no_rawat = reg_periksa.no_rawat
    INNER JOIN penyakit ON diagnosa_pasien.kd_penyakit = penyakit.kd_penyakit
    WHERE 
        reg_periksa.tgl_registrasi BETWEEN ? AND ?
        AND reg_periksa.stts != 'Batal'
        AND diagnosa_pasien.prioritas = '1' -- Fokus diagnosa primer
        $where_tambahan
    GROUP BY diagnosa_pasien.kd_penyakit
    ORDER BY jumlah_kasus DESC
    LIMIT 10
";

$stmt = $koneksi->prepare($sql);

if ($stmt) {
    // Bind param dinamis
    $bind_names[] = $types;
    for ($i=0; $i<count($params);$i++) { $bind_names[] = &$params[$i]; }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);

    $stmt->execute();
    $result = $stmt->get_result();
    
    $labels = [];
    $data = [];
    $details = [];
    
    while ($row = $result->fetch_assoc()) {
        $nama_pendek = strlen($row['nm_penyakit']) > 30 ? substr($row['nm_penyakit'], 0, 30) . '...' : $row['nm_penyakit'];
        
        $labels[] = $row['kd_penyakit'] . ' - ' . $nama_pendek;
        $data[] = (int)$row['jumlah_kasus'];
        
        $details[] = [
            'kode' => $row['kd_penyakit'],
            'nama' => $row['nm_penyakit'],
            'jumlah' => (int)$row['jumlah_kasus']
        ];
    }
    
    echo json_encode([
        'chart' => ['labels' => $labels, 'data' => $data],
        'table' => $details
    ]);
    
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => $koneksi->error]);
}
$koneksi->close();
?>