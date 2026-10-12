# Dashboard Assalam - Sistem Informasi Eksekutif RS Assalam

Aplikasi Dashboard Monitoring dan Analitik Terintegrasi SIMRS Khanza untuk manajemen eksekutif, pelayanan, keuangan, dan kepegawaian RS Assalam.

---

## 📌 Ringkasan Arsitektur & Lingkungan

* **Server Web Dashboard:** Linux Mint (`192.168.5.44`) - Path: `/var/www/html/dashboardassalam`
* **Server Database & SIMRS Khanza:** Linux Mint (`192.168.5.52`)
  * Database: MySQL `db-sim` di Port `3382`
  * Webapps Khanza / Storage Berkas: Port `8820` di `/var/www/html/khanzaapps/penggajian`

---

## 📚 Dokumentasi Proyek

Seluruh dokumentasi lengkap telah tersimpan di dalam folder [`dokumentasi/`](file:///c:/Apache24/htdocs/dashboardassalam/dokumentasi):

1. **[Dokumentasi Arsitektur & Integrasi Server](file:///c:/Apache24/htdocs/dashboardassalam/dokumentasi/arsitektur_dan_integrasi_server.md):**
   * Topologi server multi-tier (`192.168.5.44` ke `192.168.5.52`).
   * Alur Remote Storage & Bridge Upload Berkas Pegawai (`upload_receiver.php`).
   * Konfigurasi modul Kunjungan RS (Rawat Jalan, Rawat Inap, Pasien Keluar Ranap).
   * Modul Bed SK BPJS Realtime & styling Neon Dark Mode.
2. **[Buku Sakti Panduan Pengguna (User Guide)](file:///c:/Apache24/htdocs/dashboardassalam/dokumentasi/dokumentasi_user.md):**
   * Panduan alur operasional menu bagi staf manajemen, direksi, dan keuangan.
   * Dapat juga diakses langsung dari aplikasi via menu sidebar **"Panduan Pengguna"**.
3. **[Dokumentasi Teknis Lengkap (.pdf / .docx)](file:///c:/Apache24/htdocs/dashboardassalam/dokumentasi/dokumentasi_teknis_aplikas_dashboard.pdf):**
   * Laporan teknis resmi untuk dokumentasi rumah sakit dan akreditasi.

---

## 🛠️ File Konfigurasi Inti

* `config/koneksi.php`: Konfigurasi koneksi MySQL `db-sim`.
* `config/berkas_pegawai.php`: Konfigurasi URL bridge dan storage berkas kepegawaian.
* `config/bed_sk_mapping.php`: Pemetaan bed rawat inap lantai 2 & 3 sesuai SK Rumah Sakit.
* `tools/upload_receiver.php`: Script penerima transfer file yang dipasang di server `192.168.5.52`.
