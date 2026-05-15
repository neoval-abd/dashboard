<?php
/*
 * File: api/update_sidebar_antrol.php (NEUTRALIZED)
 * =========================================================================
 * File ini dulunya adalah script utilitas one-time yang tidak sengaja
 * dibiarkan di dalam folder API publik. Fungsionalitasnya sudah tidak
 * relevan (struktur sidebar_menu.json sudah berbeda) dan berbahaya jika
 * diakses sembarang orang (bisa memodifikasi file konfigurasi sidebar).
 *
 * File ini DINETRALKAN secara aman (tidak dihapus) untuk menghindari
 * error 404 jika masih ada referensi ke endpoint ini dari kode lama.
 */

http_response_code(410); // 410 Gone — endpoint ini sudah tidak berlaku
header('Content-Type: application/json');
echo json_encode([
    'status'  => 'gone',
    'message' => 'Endpoint ini sudah tidak tersedia.'
]);
exit;
?>
