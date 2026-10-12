# 📖 Dokumentasi Arsitektur, Integrasi Server & Modul Baru
**Sistem Informasi Eksekutif Dashboard Assalam - RS Assalam**  
*Update Terakhir: Oktober 2026*

---

## 🏗️ 1. Topologi Jaringan & Server

Sistem Dashboard Assalam dirancang dengan arsitektur terdistribusi multi-server untuk memisahkan beban kerja pemrosesan dashboard dengan server database utama SIMRS Khanza:

```
[ Klien / Browser User ]
          │
          ▼ HTTP (Port 80/443)
┌────────────────────────────────────────┐
│  Server Dashboard (Linux Mint)        │
│  IP: 192.168.5.44                      │
│  Path: /var/www/html/dashboardassalam  │
│  Fungsi: UI/UX, Chart, Ekspor Data    │
└───────────────────┬────────────────────┘
                    │
         ┌──────────┴──────────┐
         │ MySQL: 3382         │ HTTP cURL: 8820
         ▼                     ▼
┌────────────────────────────────────────────────────────┐
│  Server Database & Core SIMRS Khanza (Linux Mint)      │
│  IP: 192.168.5.52                                      │
│  - Database: MySQL `db-sim` (Port 3382)                │
│  - Webapps / Storage: Apache (Port 8820)               │
│    Path Fisik:                                         │
│    /var/www/html/khanzaapps/penggajian/                │
│      └── pages/berkaspegawai/                          │
│            ├── upload_receiver.php (Bridge Receiver)   │
│            └── berkas/ (Penyimpanan Fisik Dokumen)     │
└────────────────────────────────────────────────────────┘
```

---

## 📂 2. Integrasi Penyimpanan Berkas Pegawai (Remote Bridge)

### A. Latar Belakang & Masalah
Dashboard dijalankan di server `192.168.5.44` (atau Windows localhost saat dev), sedangkan berkas fisik dokumen kepegawaian (KTP, SIP, Ijazah, SK, dsb.) harus tersimpan di folder webapps Khanza pada server `192.168.5.52` agar tetap tersinkronisasi dengan master data Khanza.

Karena kedua mesin terpisah jaringan, PHP di server Dashboard tidak dapat menjalankan fungsi `move_uploaded_file()` langsung ke filesystem server database.

### B. Mekanisme Kerja Bridge
1. **Form Upload Dashboard (`kepegawaian.php`):**
   * Pengguna mengunggah dokumen melalui modal modern.
   * File divalidasi tipe (JPG, PNG, PDF) dan ukuran (maks. 10 MB).
   * Nama file di-generate unik dengan pola:
     `{nik}_{kode_berkas}_{YmdHis}_{random_hash}.{ext}`
     *(Mencegah isu file saling tertimpa yang ada di modul webapps bawaan).*
2. **Pengiriman Antar Server (`includes/berkas_pegawai.php`):**
   * Dashboard mengirimkan file ke server `192.168.5.52:8820` menggunakan cURL multipart POST.
   * Menggunakan token pengaman `KP_BRIDGE_KEY` untuk mencegah akses ilegal.
3. **Penerima File (`tools/upload_receiver.php`):**
   * Diletakkan di: `/var/www/html/khanzaapps/penggajian/pages/berkaspegawai/upload_receiver.php` pada server `192.168.5.52`.
   * Memvalidasi token, ekstensi, dan menyimpan file langsung ke folder lokal `pages/berkaspegawai/berkas/`.
4. **Pencatatan Database:**
   * Path relatif disimpan ke tabel `berkas_pegawai`:
     `pages/berkaspegawai/berkas/{nama_file}` (100% kompatibel dengan schema SIMRS Khanza).

### C. Konfigurasi (`config/berkas_pegawai.php`)
```php
define('KP_BERKAS_HOST', '192.168.5.52'); // Host server database & khanzaapps
define('KP_BERKAS_PORT', '8820');        // Port Apache di server 52
define('KP_BRIDGE_KEY', 'assalam_berkas_secret_key_2026'); // Secret token
define('KP_BRIDGE_URL', 'http://192.168.5.52:8820/khanzaapps/penggajian/pages/berkaspegawai/upload_receiver.php');
```

---

## 🏥 3. Modul Kunjungan RS & Pasien Keluar Ranap (`laporan_kunjungan.php`)

Halaman laporan kunjungan rumah sakit menyediakan 3 tab pemantauan utama:

1. **Tab 1: Rawat Jalan (`ralan`):**
   * Menampilkan pasien registrasi poliklinik / rawat jalan pada rentang waktu yang dipilih (`reg_periksa.status_lanjut = 'Ralan'`).
2. **Tab 2: Rawat Inap (`ranap` - Pasien Masuk):**
   * Menampilkan pasien yang didaftarkan rawat inap pada rentang waktu yang dipilih (`reg_periksa.status_lanjut = 'Ranap'`).
   * Tersinkronisasi dengan SK Bed BPJS dan kamar awal masuk.
3. **Tab 3: Pasien Keluar Ranap (`ranap_pulang`):**
   * Menampilkan pasien yang **selesai dirawat / keluar / pulang** pada tanggal & jam filter (`CONCAT(ki.tgl_keluar, ' ', ki.jam_keluar) BETWEEN ? AND ?`).
   * Mengambil data riil dari tabel `kamar_inap`:
     * Menghitung lama perawatan (`ki.lama` hari).
     * Menampilkan Kamar Terakhir saat keluar (Bukan kamar awal jika pernah pindah kamar).
     * Dokter DPJP utama dari tabel `dpjp_ranap`.
     * Status Kepulangan dengan badge warna (Sembuh, Membaik, Persetujuan Dokter, APS, Dirujuk, Meninggal).
   * Fitur ekspor: **Excel**, **PDF**, dan **Print** via DataTables.

---

## 🛏️ 4. Monitoring Ketersediaan Bed Realtime (`dashboard.php`)

* **Pemetaan Bed SK BPJS (`config/bed_sk_mapping.php`):**
  * Memetakan seluruh bed rawat inap lantai per lantai (Lantai 2 & Lantai 3) sesuai SK Direktur untuk sinkronisasi BPJS Mobile JKN.
* **Tampilan Neon Mode Gelap:**
  * Chip ketersediaan bed menggunakan aksen neon bercahaya (*glowing borders & soft dark backgrounds*):
    * `badge-neon-cyan` / `badge-neon-emerald`: Kapasitas aman & tersedia.
    * `badge-neon-amber`: Okupansi waspada (> 75%).
    * `badge-neon-rose`: Bed penuh (100%).
  * Kontras tinggi, mudah dipantau di layar monitor sentral 24 jam.

---

## 📋 5. Ringkasan Lokasi File Penting

| Lokasi File | Fungsi |
| :--- | :--- |
| `config/koneksi.php` | Koneksi database MySQL (`192.168.5.52:3382`, DB `db-sim`). |
| `config/berkas_pegawai.php` | Konfigurasi host, URL, dan bridge transfer berkas remote. |
| `config/bed_sk_mapping.php` | Master mapping bed rawat inap sesuai SK rumah sakit. |
| `tools/upload_receiver.php` | Script receiver yang dipasang di server Linux Mint `192.168.5.52`. |
| `includes/berkas_pegawai.php` | Handler backend CRUD berkas kepegawaian & cURL uploader. |
| `laporan_kunjungan.php` | Laporan Kunjungan Ralan, Ranap Masuk, dan Pasien Keluar Ranap. |
| `dashboard.php` | Dashboard eksekutif utama & monitoring bed per lantai. |
| `dokumentasi/` | Folder seluruh dokumen teknis, SOP, dan panduan pengguna. |
