# 📘 Panduan Integrasi SaungWA — Menu Reminder Kontrol Pasien

Dokumen ini menjelaskan cara menyiapkan kredensial **SaungWA (`https://app.saungwa.com`)**, menguji koneksi pengiriman WhatsApp, serta menjalankan **Service Antrean Otomatis (~5 menit/pesan)** pada modul **Patient Engagement $\rightarrow$ Reminder Kontrol**.

---

## 1. Arsitektur Pengiriman Aman (Anti-Blokir WhatsApp)

Untuk menjaga nomor WhatsApp RS tetap awet dan terhindar dari deteksi spam untuk volume $\pm 60\text{–}70$ pasien/hari:
1. **Preview & Edit Sebelum Antre:** Petugas memilih jadwal kontrol (BPJS dari `bridging_surat_kontrol_bpjs`, Tunai & Asuransi dari `skdp_bpjs`), melakukan *preview* / edit teks seperlunya, lalu klik **Masukkan ke Antrean Service**.
2. **Tersimpan di Tabel Antrean (`antrean_reminder_kontrol`):** Pesan tidak ditembakkan serentak (*blast*), melainkan masuk ke antrean database dengan status `pending`.
3. **Worker Service (~5 Menit/Pesan + Jitter Acak):** Script worker `api/process_reminder_queue.php` mengambil **1 pesan** setiap $\pm 4{,}5\text{–}5{,}5$ menit (`270–330` detik) hanya pada jam sopan **`07:00 – 20:00 WIB`**. Untuk 70 pasien, seluruh pengiriman selesai dalam $\pm 5{,}8$ jam secara alami.

---

## 2. Persiapan Kredensial di Dashboard SaungWA

Endpoint API SaungWA (`POST https://app.saungwa.com/api/create-message`) membutuhkan **2 kunci autentikasi** (`authkey` dan `appkey`):

### A. Pastikan Perangkat WhatsApp Tersambung
1. Login ke `https://app.saungwa.com`.
2. Buka menu **Device Broadcast** di sidebar kiri.
3. Pastikan perangkat **RS ASSALAM (`6289675463447`)** berstatus hijau **`TERSAMBUNG`**. Jika terputus, klik ikon titik tiga ($\cdots$) $\rightarrow$ **Scan** dan tautkan ulang QR WhatsApp.

### B. Salin `Auth API Key` (`authkey`)
1. Klik profil di kanan atas $\rightarrow$ **Auth Key** (`https://app.saungwa.com/user/auth-key`).
2. Salin kode pada kolom **Auth API Key** (sudah terisi otomatis di sistem: `EWhw07nquml0NYpWifuoDdzfNDPY1L3r8cFVILNAtX38dNvrUO`).

### C. Buat & Salin `App Key` (`appkey`) di Menu Webhook App
1. Pada sidebar kiri dashboard SaungWA, klik menu **Webhook App**.
2. Klik tombol **Create App / Buat App**.
3. Isi formulir:
   - **Select Device / Select Number:** Pilih perangkat **`RS ASSALAM (6289675463447)`**.
   - **App Name:** `Dashboard Assalam`
   - **Website Link:** Isi URL berformat domain (ada `.com` / `.id`), misalnya `https://rsassalam.com` *(form SaungWA menolak kata `localhost` tanpa titik domain, namun kolom ini hanya label informasi — pengiriman dari server `localhost` / jaringan lokal tetap berjalan 100% normal)*.
4. Klik **Create Now**.
5. Pada kartu aplikasi yang baru dibuat, klik tulisan **`Integration ->`** lalu salin nilai **`appkey`** (berupa kode UUID, contoh: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`).

---

## 3. Cara Memasukkan Kredensial & Tes Kirim di Dashboard

Tersedia **2 cara** (pilih salah satu, keduanya otomatis tersinkron ke `config/saungwa.php`):

### Cara 1: Langsung dari UI Halaman Reminder Kontrol (Direkomendasikan)
1. Buka menu **Patient Engagement $\rightarrow$ Reminder Kontrol** (`laporan_reminder_kontrol.php`).
2. Klik tombol status **`SaungWA: Lengkapi AppKey`** di pojok kanan atas halaman.
3. Pada modal **Integrasi & Tes Koneksi SaungWA**:
   - Pastikan **Gateway Pengiriman Aktif** = `SaungWA (Utama)`.
   - **Nomor WA Perangkat Pengirim** = `089675463447`.
   - Tempelkan **`App Key`** hasil langkah **2.C** ke kolom **App Key (`appkey`)**.
   - Pastikan **`Auth API Key`** sudah terisi.
4. Masukkan nomor HP tujuan uji coba pada bagian **Uji Kirim Pesan Langsung**, lalu klik **Simpan & Kirim Pesan Tes**.
5. Jika berhasil, indikator di kanan atas halaman berubah menjadi hijau **`SaungWA Aktif (089675463447)`** dan pesan tes masuk ke WhatsApp tujuan.

### Cara 2: Edit Manual File Konfigurasi (`config/saungwa.php`)
Buka file `c:\Apache24\htdocs\dashboardassalam\config\saungwa.php` lalu sesuaikan konstanta berikut:

```php
define('WA_ACTIVE_PROVIDER', 'saungwa');
define('SAUNGWA_SENDER_PHONE', '6289675463447');
define('SAUNGWA_APP_KEY', 'ISI_APPKEY_UUID_DARI_MENU_WEBHOOK_APP');
define('SAUNGWA_AUTH_KEY', 'EWhw07nquml0NYpWifuoDdzfNDPY1L3r8cFVILNAtX38dNvrUO');
define('SAUNGWA_API_URL', 'https://app.saungwa.com/api/create-message');
```

> **Catatan:** File `config/saungwa.php` sudah dimasukkan ke `.gitignore` agar kunci rahasia tidak ikut ter-*commit* ke repositori Git. Template contoh tersedia di `config/saungwa.php.example`.

---

## 4. Menjalankan Service Antrean Otomatis (~5 Menit)

Agar pesan berstatus **Dalam Antrean** dikirim otomatis satu per satu di latar belakang, jalankan worker menggunakan salah satu metode berikut di server:

### Opsi A — Uji Kirim 1 Antrean via Terminal / CMD (Untuk Tes IT)
Buka Command Prompt / PowerShell pada folder proyek:

```powershell
cd C:\Apache24\htdocs\dashboardassalam
php api/process_reminder_queue.php --force
```
- Flag `--force` mengizinkan IT menguji pengiriman meskipun sedang di luar jam `07:00–20:00 WIB` atau melewati masa *cooldown*.
- Contoh output sukses:
  `[SAUNGWA][Reminder Kontrol] Terkirim ke NAMA PASIEN (086266 - 62821xxxxxxx) | Sisa antrean: 25 kontrol, 0 ultah`

### Opsi B — Menjalankan Loop Background Service (`process_reminder_queue.bat`)
Klik dua kali atau jalankan lewat CMD di server Windows:

```bat
C:\Apache24\htdocs\dashboardassalam\process_reminder_queue.bat
```
Script ini akan terus berjalan dan memanggil worker setiap **270 s/d 330 detik** ($\pm 5$ menit dengan *random jitter* agar pola pengiriman terlihat alami oleh WhatsApp).

### Opsi C — Otomatis Permanen via Windows Task Scheduler (Paling Stabil untuk Server RS)
Agar service tetap berjalan otomatis meskipun server *restart* tanpa harus membuka jendela CMD:
1. Tekan `Win + R`, ketik `taskschd.msc`, tekan **Enter**.
2. Klik **Create Task...** (bukan *Basic Task*) di panel kanan:
   - **Tab General:**
     - Name: `Assalam WA Reminder Queue Service`
     - Pilih **Run whether user is logged on or not** dan centang **Run with highest privileges**.
   - **Tab Triggers:** Klik **New...**
     - Begin the task: **Daily**, Start pukul `07:00:00`.
     - Centang **Repeat task every:** pilih **`5 minutes`**, for a duration of: **`Indefinitely`** (atau `13 hours`). Klik **OK**.
   - **Tab Actions:** Klik **New...**
     - Action: **Start a program**
     - Program/script: `php.exe` (atau path lengkap mis. `C:\php\php.exe`)
     - Add arguments: `api/process_reminder_queue.php`
     - Start in: `C:\Apache24\htdocs\dashboardassalam`
     - Klik **OK**.
   - **Tab Settings:**
     - Pastikan **If the task is already running, then the following rule applies:** diset ke **`Do not start a new instance`**.
3. Klik **OK** dan masukkan password akun administrator Windows server.

*(Untuk server Linux, gunakan `process_reminder_queue.sh` atau cron `*/5 7-19 * * * /usr/bin/php /path/dashboardassalam/api/process_reminder_queue.php >> /var/log/wa_reminder.log 2>&1`.)*

---

## 5. Daftar File Terkait Integrasi

| File | Fungsi |
| :--- | :--- |
| `config/saungwa.php` | Penyimpanan kredensial aktif SaungWA (`SAUNGWA_APP_KEY`, `SAUNGWA_AUTH_KEY`, pengaturan jeda & jam aman) |
| `config/saungwa.php.example` | Template contoh konfigurasi SaungWA |
| `api/saungwa_client.php` | Library cURL pengiriman pesan ke `https://app.saungwa.com/api/create-message` & pengunci *cooldown* |
| `api/reminder_kontrol.php` | Endpoint AJAX manajemen antrean (`enqueue`, `bulk_enqueue`, `cancel_queue`, simpan konfigurasi & tes kirim) |
| `api/process_reminder_queue.php` | Worker CLI pengirim otomatis antrean Reminder Kontrol (Prioritas 1) & Ulang Tahun (Prioritas 2) |
| `laporan_reminder_kontrol.php` | Antarmuka halaman Reminder Kontrol (BPJS/Tunai/Asuransi, Preview/Edit Pesan, & Modal Pengaturan SaungWA) |
| `process_reminder_queue.bat` | Runner loop otomatis 5 menit dengan *random jitter* untuk Windows |

---

## 6. Troubleshooting Kendala Umum

- **Pesan Error: `AppKey SaungWA belum diisi...`**
  $\rightarrow$ Anda baru memasukkan `AuthKey`. Buka dashboard SaungWA $\rightarrow$ menu **Webhook App** $\rightarrow$ buat App yang ditautkan ke perangkat `RS ASSALAM (6289675463447)`, lalu salin `appkey` ke modal pengaturan.
- **Pesan Error: `HTTP 401` dari SaungWA**
  $\rightarrow$ Pastikan `appkey` berasal dari **Webhook App** yang terhubung ke perangkat berstatus **`TERSAMBUNG`** (bukan perangkat yang sedang `TERPUTUS`), dan `authkey` belum pernah di-*Regenerate* ulang di SaungWA.
- **Status Kartu Masih `Dalam Antrean` Tidak Berubah menjadi `Terkirim`**
  $\rightarrow$ Pastikan `process_reminder_queue.bat` atau **Windows Task Scheduler** sudah berjalan di server, dan waktu server saat ini berada pada jam pengiriman aman (`07:00 – 20:00 WIB`). Gunakan `php api/process_reminder_queue.php --force` di CMD untuk melihat log pengiriman secara langsung.

