# 📘 Buku Sakti Pengguna: Dashboard Eksekutif v3.6.0+
*Panduan canggih buat bos-bos, manajemen, sampe tim IT yang mau tau rahasia dapur keuangan RS.*

---

## 🚀 1. Persiapan Masuk (Login & Keamanan)

Halo! Sebelum masuk ke dunia grafik yang warna-warni, pastiin dulu kamu punya akses ya. Dashboard ini udah kami suntik proteksi **Zero-Trust**. 

![Tampilan Layar Login](1. login.jpg)

*   **Username & Password**: Pake akun SIMKES Khanza kamu.
*   **Hak Akses (Otoritas Khusus)**: GAK SEMUA ORANG bisa intip menu "piutang" atau "pihak ketiga". Cuma kamu yang akun Khanza-nya dicentang **'Harian Menejemen'** dan **'Bulanan Menejemen'** oleh tim IT yang bisa masuk. (Hubungi IT bila kamu belum punya akses ini). 
*   **Keamanan Ekstra**: Kalo salah password 6 kali, kamu bakal kena *lockout* (dikunci) selama 60 detik. Jadi, jangan asal tebak ya!

---

## 🧭 2. Menjelajahi Menu (Sidebar Masterclass)

Kita udah pecah menu-menu ini jadi kategori biar gak pusing carinya. Gunakan tombol hamburger `☰` di pojok kiri atas untuk menyembunyikan sidebar agar layar grafik jadi lebih luas.

![Dashboard Utama & Sidebar](2. Dashboard Utama.jpg)

---

## 💼 3. Kendali Biaya (Biaya Riil vs Plafon)
*Bagian ini fokus pada pencegahan kebocoran biaya saat pasien masih dirawat.*

*   **Plafon Ranap**: Pantau biaya pasien yang opname secara *realtime*. Di sini kamu bisa lihat apakah billing pasien sudah mendekati plafon BPJS atau asuransi tertentu. Gak perlu nunggu pasien pulang buat tau billingnya sudah bengkak atau belum.
*   **Ketersediaan Bed**: Pantau okupansi bed dan kunjungan poli secara langsung untuk manajemen antrean.

![Monitoring Plafon Rawat Inap](4. Plafon Ranap.jpg)
![Ketersediaan Bed & Poli](3. Ketersediaan Bed dan kunjungan poliklinik.jpg)

---

## 💰 4. Laporan Keuangan (Audit Omzet & Kas)
*Bagian ini digunakan untuk audit transaksi yang sudah terjadi (Billing selesai).*

*   **Laporan Kas**: Rekapitulasi uang yang benar-benar ada di tangan (tunai di brankas/kasir).
![Laporan Kas](5. Laporan Kas.jpg)

*   **Laporan Billing Kasir**: Audit omzet yang sudah ditransaksikan oleh kasir. Cek rincian nota untuk memastikan tidak ada kesalahan input tindakan.
![Laporan Billing Kasir](6. Laporan Billing kasir.jpg)
![Detail Billing Kasir](7. Detail billing kasir.jpg)
![Detail Isi Nota](8. Detail Isi Nota.jpg)

*   **Laporan Piutang**: Menu favorit Direktur! Di sini kelihatan semua **"Uang Kita di Luar"**. Berapa klaim asuransi yang belum cair atau bon pasien yang belum lunas.
![Laporan Harian Piutang](9. Laporan harian Piutang.jpg)
![Detail Piutang](11. Detail nota Laporan Harian Piutang.jpg)

*   **Laporan Tunai**: Rekapitulasi pemasukan kasir harian per shift. Sangat berguna untuk sinkronisasi setoran harian.
![Laporan Tunai](12. Laporan Tunai (sama seperti piutang).jpg)

*   **Analisa Tindakan**: Ketahui unit atau dokter mana yang paling produktif dalam memberikan tindakan medis.
![Dashboard Analisa Tindakan](13. Dashboard Analisa tindakan.jpg)

*   **Jasa Medis**: Monitor pembagian hak jasa medis dokter dan perawat secara transparan.
![Dashboard Jasa Medis](15. Dashboard jasmed.jpg)
![Rincian Jasmed Dokter](16. Rincian Jasmed Dokter.jpg)

---

## 📅 5. Administrasi & HRD (Monitoring Pegawai)

*   **Laporan Absensi**: Pantau kedisiplinan pegawai, dari jam masuk, keterlambatan, hingga rekapitulasi ketidakhadiran secara visual.
![Dashboard Analytics Absensi](19. Dashboard analitics Absensi.jpg)
![Rekap Keterlambatan](18. Rekap keterlambatan Pegawai.jpg)

---

## 📊 6. Statistik & Indikator (Data Akreditasi)

*   **Kunjungan RS**: Dashboard interaktif untuk melihat tren kunjungan pasien harian, mingguan, hingga tahunan.
![Dashboard Analytics Kunjungan](20. Dashboard Analitics kunjungan.jpg)

*   **BOR LOS TOI**: Indikator efisiensi pemakaian tempat tidur rawat inap. 
![Dashboard Data BOR LOS TOI](22. Dashboard Data BOR LOS TOI.jpg)
![Detail BOR per Bangsal](23. Detail per bangsal BoR Los Toi.jpg)

*   **Laporan Penyakit**: Visualisasi 10 besar diagnosa penyakit (ICD-10) untuk perencanaan stok obat dan alat kesehatan.
![Rangking Penyakit Terbanyak](24. Sepuluh besar penyakit terbanyak.jpg)

*   **Waktu Tunggu (TAT)**: Pantau kecepatan pelayanan dari pendaftaran hingga pasien menerima obat. Crucial untuk kepuasan pasien!
![Dashboard Waktu Tunggu](26. Lama Pelayanan (waktu tunggu).jpg)

*   **Demografi Pasien**: Pemetaan wilayah asal pasien dan segmentasi umur pengguna layanan RS.
![Pemetaan Kunjungan Pasien](28. Dashboard analitics pemetaan kunjungan pasien.jpg)

*   **Kepatuhan Satu Sehat & Antrol**: Monitoring integrasi data ke Kemenkes dan validitas Task ID antrean online BPJS.
![Dashboard Satu Sehat](30. Dashboard Kepatuhan Pengiriman aliran data satu sehat.jpg)
![Dashboard Antrol BPJS](32. Dashboard laporan Antrol.jpg)

---

## 🏆 7. Key Performance (KPI & Kinerja)

*   **Kinerja Dokter**: Evaluasi produktivitas dokter berdasarkan volume pasien dan revenue yang dihasilkan.
![Analisis Kinerja Dokter](34. Analisis Kinerjaj Dokter.jpg)
![Ranking Pasien Dokter](35. Ranking kinerja dokter berdasarkan jumlah pasien.jpg)

*   **Laporan Operasi**: Rekapitulasi tindakan bedah berdasarkan tingkat kesulitan dan performa operator.
![Dashboard Analytics Laporan Operasi](37. Dashboard Analitics laporan operasi.jpg)
![Rincian Operasi](38. Data rincian tindakan operasi.jpg)

---

## 💊 8. Manajemen Farmasi (Gudang Cuan)

*   **Monitoring Stok**: Pantau stok berjalan di gudang farmasi dan apotek secara real-time.
![Dashboard Stok Farmasi](40. Dashboard Stok Berjalan Farmasi.jpg)

*   **Profit Farmasi**: Analisa keuntungan dari penjualan obat dan pemberian resep.
![Dashboard Profit Farmasi](44. Dashboard Proyeksi Keuntungan Farmasi.jpg)
![Detail Margin Obat](45. Detail Proyeksi Keuntungan jual dan beri obat.jpg)

*   **Hutang Obat**: Reminder jatuh tempo pembayaran kepada supplier/PBF.
![Dashboard Manajemen Hutang](46. Dashboard manajemen hutang obat.jpg)

*   **Dead Stock / Slow Moving**: Identifikasi obat yang tidak laku/lambat bergeraknya untuk mencegah kerugian expired.
![Analisa Obat Slow Moving](49. Analisa data dead stock obat slow moving .jpg)

---

## 📝 9. Kelengkapan ERM (Audit Mutu)

*   **Audit Kepatuhan ERM**: Sistem cerdas yang mengaudit kelengkapan pengisian Rekam Medis Elektronik oleh para PPA (Dokter/Perawat).
![Audit Kepatuhan ERM](52. Audit Kepatuhan Kelengkapan ERM.jpg)

---

## 🎨 10. Fitur Premium Dashboard

Aplikasi ini gak cuma pinter, tapi juga ganteng!
*   **Sistem Tema**: Ganti suasana hati di pojok kanan atas (Bright, Glass Solid, Glass Animated).
*   **Mode Glass**: Mode futuristik tembus pandang yang nyaman di mata.
*   **Grafik Interaktif**: Klik legenda di grafik (Chart.js) buat memfilter data secara dinamis tanpa reload.

---

## 🛠️ 11. Pojok IT (Onboarding)

Buat bang admin IT yang baru pasang:
1.  **Aktivasi Database**: Jika instalasi awal, pastikan file `config/koneksi.php` sudah terhubung ke database Khanza.
2.  **Copyright Protection**: Jangan menghapus atau menyembunyikan link developer di footer (Rule #17) atau aplikasi akan masuk ke mode *Self-Destruct* (Blank Page).
3.  **Donasi**: Klik nama developer di bawah buat kirim apresiasi kopi agar fitur baru terus rilis!

---

*Tertanda,*
*Ichsan Leonhart (Developer & Teman Curhat Sistem)*

> *"Data itu kayak perasaan, kalo gak dirawat bisa ilang pelan-pelan."*
