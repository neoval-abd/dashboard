<?php
// Jalankan: php tests/berkas_pegawai_readonly.php. Hanya membaca DB, tidak ada INSERT/DELETE.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/config/koneksi.php';
require dirname(__DIR__) . '/includes/berkas_pegawai.php';
function check($ok, $label) { if (!$ok) throw new RuntimeException('GAGAL: ' . $label); echo 'PASS: ', $label, "\n"; }

$t = microtime(true);
$rows = kpb_summary($koneksi);
check(count($rows) > 0, 'summary berisi pegawai (' . count($rows) . ' baris, ' . round((microtime(true) - $t) * 1000) . ' ms)');
$with = array_values(array_filter($rows, function ($r) { return $r['files'] > 0; }));
check(count($with) > 0, 'ada pegawai dengan berkas (' . count($with) . ')');
foreach ($rows as $r) if (!array_key_exists($r['kategori'], kpb_categories())) throw new RuntimeException('Kategori tidak dikenal: ' . $r['kategori']);
check(true, 'semua kategori valid');
$sample = $with[0];
$detail = kpb_detail($koneksi, $sample['nik']);
check($detail['kategori'] === $sample['kategori'] && $detail['sumber'] === 'berkas', 'detail memakai kategori dari berkas');
$filled = 0; $files = 0; $items = 0;
foreach ($detail['groups'] as $g) foreach ($g['items'] as $i) { $items++; $files += count($i['files']); if (!$i['lainnya'] && $i['files']) $filled++; }
check($filled === $sample['filled'], "jumlah terisi cocok ($filled/{$sample['wajib']})");
check($files === $sample['kategori_berkas'][$sample['kategori']], 'jumlah file cocok (' . $files . ')');
check(count($detail['groups']) >= 9, 'kelompok terbentuk: ' . implode(', ', array_column($detail['groups'], 'label')));
$other = kpb_detail($koneksi, $sample['nik'], 'Tenaga Non Klinis');
check($other['sumber'] === 'pilihan', 'kategori bisa dipilih manual');
foreach ([null, '', str_repeat('9', 21)] as $bad) { try { kpb_detail($koneksi, $bad); throw new RuntimeException('NIP buruk lolos'); } catch (InvalidArgumentException $e) {} }
try { kpb_detail($koneksi, $sample['nik'], 'Ngawur'); throw new RuntimeException('kategori buruk lolos'); } catch (InvalidArgumentException $e) {}
check(true, 'validasi input detail');
foreach (['../config/koneksi.php', 'pages/berkaspegawai/berkas/../../../index.php', 'pages/berkaspegawai/berkas/..\\x.php', 'C:/Windows/win.ini', 'pages/berkaspegawai/berkas/'] as $bad) {
    if (kpb_file_path($bad) !== null) throw new RuntimeException('Path traversal lolos: ' . $bad);
}
check(true, 'path traversal ditolak');
check(kpb_guess_category(['jbtn' => 'Perawat Pelaksana']) === 'Tenaga klinis Perawat dan Bidan', 'perkiraan kategori perawat');
check(kpb_guess_category(['jbtn' => 'Staf IT']) === 'Tenaga Non Klinis', 'perkiraan kategori non klinis');
echo 'Folder berkas: ';
try { echo kpb_storage_dir(), (is_writable(kpb_storage_dir()) ? ' (bisa ditulis)' : ' (TIDAK bisa ditulis)'), "\n"; } catch (KpbStorageException $e) { echo $e->getMessage(), "\n"; }
