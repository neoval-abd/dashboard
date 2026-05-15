<?php
// Komentar: File ini khusus untuk merender data BLOB logo dari database.

require_once(dirname(__DIR__) . '/config/koneksi.php');

$sql = "SELECT setting.logo FROM setting LIMIT 1";
$result = $koneksi->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $logo_data = $row['logo'];
    
    // Generate ETag based on the logo data
    $etag = '"' . md5($logo_data) . '"';
    
    // Check if the browser sent an If-None-Match header
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        // Logo hasn't changed, send 304 Not Modified
        header("HTTP/1.1 304 Not Modified");
        exit;
    }
    
    // Komentar: Mengatur header HTTP sebagai gambar PNG.
    // Jika logo Anda JPG, ganti menjadi 'image/jpeg'
    header("Content-type: image/png"); 
    
    // Cache control headers (cache for 1 day = 86400 seconds)
    header("Cache-Control: public, max-age=86400");
    header("ETag: $etag");
    
    // Komentar: Tampilkan data BLOB
    echo $logo_data;
} else {
    // Jika tidak ada logo, tampilkan gambar placeholder (opsional)
    // Untuk saat ini, kita biarkan kosong.
}

$koneksi->close();
?>