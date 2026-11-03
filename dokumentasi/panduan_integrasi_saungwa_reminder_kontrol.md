# 📘 Panduan Integrasi SaungWA — Menu Reminder Kontrol Pasien (Linux Mint & Windows)

Dokumen ini menjelaskan cara menyiapkan kredensial **SaungWA (`https://app.saungwa.com`)**, menguji koneksi pengiriman WhatsApp, serta menjalankan **Service Antrean Otomatis (~5 menit/pesan)** pada server **Linux Mint (`192.168.5.44`)** maupun **Windows**.

---

## 1. Arsitektur Pengiriman Aman & Bebas Masalah Izin Folder (`sudo`)

1. **Penyimpanan Konfigurasi di Database (`konfigurasi_wa_gateway`):**
   - Seluruh pengaturan SaungWA (`AppKey`, `AuthKey`, Nomor Pengirim, dan Provider Aktif) kini disimpan langsung ke tabel MySQL **`konfigurasi_wa_gateway`**.
   - **Keuntungan di Linux Mint:** Saat kode di-*push* dan di-*pull* ke `/var/www/html/dashboardassalam`, Anda **tidak perlu** melakukan `chmod 777` atau `sudo nano` pada folder `config/`. Cukup simpan dari browser, maka aplikasi Web maupun Worker CLI otomatis membaca konfigurasi yang sama dari database!
2. **Preview & Edit Sebelum Antre:** Petugas memilih jadwal kontrol (BPJS dari `bridging_surat_kontrol_bpjs`, Tunai & Asuransi dari `skdp_bpjs`), melakukan *preview* / edit teks seperlunya, lalu klik **Masukkan ke Antrean Service**.
3. **Worker Service (~5 Menit/Pesan + Jitter Acak):** Script worker `api/process_reminder_queue.php` mengambil **1 pesan** per siklus ($\pm 4{,}5\text{–}5{,}5$ menit) hanya pada jam sopan **`07:00 – 20:00 WIB`** agar nomor WA aman dari blokir.

---

## 2. Cara Input Kredensial & Tes Kirim dari Browser (Tanpa `sudo`)

1. Buka halaman **Patient Engagement $\rightarrow$ Reminder Kontrol** (`http://192.168.5.44/dashboardassalam/laporan_reminder_kontrol.php`).
2. Klik tombol status **`SaungWA`** di pojok kanan atas halaman.
3. Pastikan kolom terisi:
   - **Gateway Pengiriman Aktif:** `SaungWA (Utama)`
   - **Nomor WA Perangkat Pengirim:** `089675463447`
   - **App Key (`appkey`):** Kode UUID dari menu **Webhook App** SaungWA (contoh: `2403ea28-ec52-4a9b-8d95-2d8bcacd123d`)
   - **Auth API Key (`authkey`):** Kode dari menu **Edit Profile $\rightarrow$ Auth Key** SaungWA
4. Klik **`Simpan Konfigurasi`** (atau **`Simpan & Kirim Pesan Tes`**). Data langsung tersimpan permanen di tabel database `konfigurasi_wa_gateway` tanpa error izin tulis file.

---

## 3. Menjalankan Service Otomatis 5 Menit di Server **Linux Mint** (`192.168.5.44`)

Pilih **salah satu** dari dua cara paling praktis di Linux Mint berikut (cukup disetup **1 kali saja**, selanjutnya berjalan otomatis di belakang layar tanpa perlu ketik password `sudo` lagi setiap hari):

### Cara A (Paling Mudah): Menggunakan `crontab` Linux Mint (Setiap 5 Menit)
Buka Terminal di server Linux Mint, lalu jalankan:

```bash
crontab -e
```
*(Jika diminta memilih editor, ketik angka untuk `nano` lalu Enter).*
Tambahkan **1 baris** berikut di bagian paling bawah (sesuaikan `/var/www/html/dashboardassalam` jika letak folder berbeda):

```cron
*/5 7-19 * * * /usr/bin/php /var/www/html/dashboardassalam/api/process_reminder_queue.php >> /tmp/wa_reminder_assalam.log 2>&1
```
Simpan dengan menekan `Ctrl + O` $\rightarrow$ `Enter`, lalu keluar dengan `Ctrl + X`.
- Cek log pengiriman kapan saja tanpa `sudo`:
  ```bash
  tail -f /tmp/wa_reminder_assalam.log
  ```

### Cara B (Alternatif dengan Jitter Acak 270–330 Detik): Menggunakan `systemd` Service
Jika ingin memakai `process_reminder_queue.sh` yang memiliki jeda acak (`270–330` detik) dan otomatis hidup saat komputer Linux Mint menyala:

1. Buat file service satu kali saja dengan `sudo`:
   ```bash
   sudo tee /etc/systemd/system/assalam-wa-queue.service > /dev/null << 'EOF'
   [Unit]
   Description=RSU Assalam WhatsApp Reminder Queue Worker
   After=network.target mysql.service apache2.service

   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/var/www/html/dashboardassalam
   ExecStart=/bin/bash /var/www/html/dashboardassalam/process_reminder_queue.sh
   Restart=always
   RestartSec=15

   [Install]
   WantedBy=multi-user.target
   EOF
   ```
2. Aktifkan & jalankan service:
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable --now assalam-wa-queue.service
   ```
3. Cek status service:
   ```bash
   systemctl status assalam-wa-queue.service
   ```

### Uji Kirim Manual 1 Antrean dari Terminal Linux Mint:
```bash
php /var/www/html/dashboardassalam/api/process_reminder_queue.php --force
```
*(Pastikan paket `php-curl` dan `php-mysql` sudah terpasang di Linux Mint. Jika belum: `sudo apt install php-curl php-mysql`).*

---

## 4. Menjalankan Service Otomatis 5 Menit di Server **Windows**

- **Uji Manual via CMD/PowerShell:**
  ```powershell
  cd C:\Apache24\htdocs\dashboardassalam
  php api/process_reminder_queue.php --force
  ```
- **Background Loop (`.bat`):** Jalankan `C:\Apache24\htdocs\dashboardassalam\process_reminder_queue.bat`.
- **Otomatis Permanen (Windows Task Scheduler):**
  Buat Task harian mulai pukul `07:00`, **Repeat task every: `5 minutes`**, Action: `php.exe` dengan argumen `api/process_reminder_queue.php` dan *Start in* `C:\Apache24\htdocs\dashboardassalam`.

---

## 5. Daftar File & Tabel Terkait Integrasi

| Komponen | Keterangan |
| :--- | :--- |
| Tabel `konfigurasi_wa_gateway` | Penyimpanan utama kredensial SaungWA di database (bebas kendala permission Linux) |
| Tabel `antrean_reminder_kontrol` | Antrean pesan & riwayat status (`pending`, `processing`, `sent`, `failed`) |
| `api/saungwa_client.php` | Client cURL ke `https://app.saungwa.com/api/create-message` + pembaca konfigurasi DB |
| `api/reminder_kontrol.php` | Endpoint AJAX antrean, simpan konfigurasi DB, & uji kirim pesan |
| `api/process_reminder_queue.php` | Worker CLI pengirim otomatis antrean Reminder Kontrol & Ulang Tahun |
| `process_reminder_queue.sh` / `.bat` | Script runner loop dengan *random jitter* (`270–330` detik) untuk Linux Mint & Windows |
| `.gitattributes` | Menjaga baris akhir `process_reminder_queue.sh` tetap berformat Unix (`LF`) saat di-*push* dari Windows ke Linux Mint |
