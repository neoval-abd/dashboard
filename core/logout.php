<?php
/*
 * File logout.php (PERBAIKAN)
 */

// 1. Panggil koneksi.php untuk memulai session dengan aman
require_once(dirname(__DIR__) . '/config/koneksi.php');

// 2. Hapus semua variabel session
session_unset();

// 3. Hancurkan session
session_destroy();

// 4. Redirect ke halaman login
header("Location: ../index.php");
exit;
?>