<?php
/*
 * Lokasi folder webapps "penggajian" Khanza (folder yang berisi pages/berkaspegawai/berkas).
 * Kolom berkas_pegawai.berkas menyimpan path relatif terhadap folder ini, sehingga file yang
 * diunggah dari dashboard tetap bisa dibuka dari aplikasi Khanza, begitu pula sebaliknya.
 *
 * Lokal  : C:/Apache24/htdocs/khanzaapps/penggajian
 * Server : sesuaikan dengan folder webapps produksi (bisa juga path share, mis. //server/htdocs/webapps/penggajian)
 */
define('KP_BERKAS_ROOT', 'C:/Apache24/htdocs/khanzaapps/penggajian');
