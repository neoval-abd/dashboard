<?php
require_once 'c:/xampp/htdocs/dashboard-keuangan/config/koneksi.php';
$sql = "SHOW COLUMNS FROM referensi_mobilejkn_bpjs_batal;";
$res = $koneksi->query($sql);
if (!$res) { echo $koneksi->error; exit; }
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
