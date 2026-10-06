<?php
// Data pasien tetap menggunakan db-sim; input plafon disimpan terpisah.
function kendali_biaya_table() {
    return '`kendali_biaya`.`plafon_ranap`';
}

function kendali_biaya_plafon($koneksi, $no_rawat) {
    $stmt = $koneksi->prepare('SELECT nominal FROM ' . kendali_biaya_table() . ' WHERE no_rawat = ?');
    if (!$stmt) throw new RuntimeException('Penyimpanan plafon belum tersedia.');
    $stmt->bind_param('s', $no_rawat);
    if (!$stmt->execute()) throw new RuntimeException('Plafon gagal dibaca.');
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (float) $row['nominal'] : null;
}
