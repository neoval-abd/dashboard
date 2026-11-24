# 🚀 Panduan Lengkap Integrasi & Penggunaan RocketSend.io (WhatsApp Reminder)
**Rumah Sakit Assalam**  
*Tanggal: 23 November 2026*

---

## 📌 1. Memahami Cara Kerja RocketSend.io vs SaungWA vs Fonnte

Sebelum memulai, sangat penting untuk memahami perbedaan arsitektur ketiga tools ini:

| Karakteristik | SaungWA | Fonnte (Scan QR) | Fonnte WABA (Official) | RocketSend.io |
| :--- | :--- | :--- | :--- | :--- |
| **Bentuk Layanan** | Cloud API (Web-based) | Cloud API (Web-based) | Meta Cloud API Resmi | **Ekstensi Google Chrome** |
| **Cara Kerja** | Script server panggil API | Script server panggil API | Server panggil API Meta | **Otomatisasi browser WhatsApp Web** |
| **Kebutuhan Server** | Butuh AppKey & AuthKey | Butuh Token API | Butuh Token WABA Meta | **Tidak butuh API/Token server** |
| **Metode Kirim** | Background Worker / Cron | Background Worker / Cron | Background Worker / Cron | **Upload File CSV di Chrome** |
| **Risiko Blokir WA** | Tinggi jika frekuensi rapat | Tinggi jika frekuensi rapat | **0% (Resmi Meta, Anti-Blokir)** | **Sedang-Tinggi (Tergantung jeda)** |

> [!IMPORTANT]
> **Mengapa nomor WhatsApp sebelumnya terblokir?**  
> WhatsApp memiliki algoritma proteksi spam yang sangat ketat terhadap nomor WhatsApp Web yang mengirim pesan otomatis keluar (*outbound*) ke banyak kontak baru tanpa adanya interaksi balasan.  
> **RocketSend.io** juga bekerja di atas WhatsApp Web, sehingga **tetap wajib mengatur jeda waktu (delay random 15–30 detik per pesan)** agar tidak terdeteksi bot otomatis oleh WhatsApp.

---

## 🛠️ 2. Langkah-Langkah Pemasangan Ekstensi RocketSend

### Langkah 1: Pasang Ekstensi Chrome
1. Buka browser **Google Chrome** di komputer/laptop admin.
2. Buka situs resmi: **[https://www.rocketsend.io](https://www.rocketsend.io)** atau cari **RocketSend** di **Chrome Web Store**.
3. Klik tombol **"Add to Chrome"** (*Tambahkan ke Chrome*).
4. Konfirmasi dengan memilih **Add Extension**.
5. Klik ikon *Puzzle* (ekstensi) di pojok kanan atas Chrome, lalu klik ikon **Pin 📌** agar tombol RocketSend selalu terlihat.

### Langkah 2: Login / Daftar di `app.rocketsend.io`
1. Buka tab baru dan akses: **[https://app.rocketsend.io](https://app.rocketsend.io)**.
2. Daftar (*Sign Up*) menggunakan email atau login dengan akun Google Anda.
3. Setelah login, Anda akan masuk ke dashboard akun RocketSend untuk melihat status paket atau memasukkan lisensi (tersedia versi Gratis dan Pro).

### Langkah 3: Buka WhatsApp Web
1. Buka **[https://web.whatsapp.com](https://web.whatsapp.com)** di Google Chrome.
2. Hubungkan nomor WhatsApp RS Assalam dengan memindai kode QR (*QR Code*).
3. Setelah masuk, di sisi atas antarmuka WhatsApp Web akan muncul tombol/panel baru bertuliskan **Sender** atau ikon RocketSend.

---

## 📥 3. Cara Mengirim Reminder Kontrol Pasien dengan RocketSend

Dashboard Eksekutif RS Assalam telah menyediakan fitur **Ekspor CSV Otomatis** yang sudah diformat khusus agar langsung dikenali oleh RocketSend.

### Langkah 1: Ekspor CSV dari Dashboard
1. Buka menu **Patient Engagement &rarr; Reminder Kontrol Pasien** di Dashboard RS Assalam.
2. Pilih filter tanggal kontrol yang diinginkan (misal: klik tombol **Besok (H-1)**).
3. Klik tombol biru **"Ekspor CSV RocketSend"** yang berada di atas tabel pasien.
4. Browser akan otomatis mengunduh file berekstensi `.csv` (contoh: `RocketSend_Reminder_Kontrol_2026-11-24.csv`).

File CSV ini berisi kolom:
- `phone`: Nomor WhatsApp pasien dengan kode negara `628...`
- `name`: Nama lengkap pasien
- `message`: Teks pesan reminder lengkap beserta tanggal, jam, poliklinik, dan nama dokter
- `poli`: Nama poliklinik tujuan
- `dokter`: Nama dokter pemeriksa
- `tanggal`: Tanggal jadwal kontrol
- `jam`: Jam praktik dokter

### Langkah 2: Unggah CSV ke RocketSend di WhatsApp Web
1. Buka tab **WhatsApp Web** yang sudah aktif.
2. Klik tombol **Sender** (panel RocketSend) di pojok WhatsApp Web.
3. Klik tombol **Upload CSV** lalu pilih file CSV yang tadi diunduh dari dashboard.
4. RocketSend akan otomatis membaca kolom nomor telepon (`phone`).

### Langkah 3: Menentukan Format Pesan
Anda memiliki 2 pilihan:
- **Pilihan A (Paling Mudah):**  
  Pada kotak teks pesan RocketSend, cukup ketik: `{{message}}`.  
  *Sistem RocketSend akan otomatis mengambil isi teks pesan lengkap yang sudah disusun oleh dashboard RS Assalam.*
- **Pilihan B (Custom Template di RocketSend):**  
  Anda juga bisa membuat template sendiri dengan variabel, contoh:  
  ```text
  Halo Bapak/Ibu {{name}}, mengingatkan jadwal kontrol pada {{tanggal}} jam {{jam}} di poli {{poli}} bersama dokter {{dokter}}.
  ```

### Langkah 4: Pengaturan Keamanan Anti-Blokir (PENTING!)
Sebelum menekan tombol kirim:
1. Centang atau atur **Random Delay** di panel RocketSend.
2. Rekomendasi delay: **15 s/d 30 detik antar pesan** (jangan 1–2 detik karena akan langsung diblokir WhatsApp).
3. Jika total pasien banyak (>50 pasien), kirim secara bertahap (misal 20–25 pasien per sesi, lalu istirahat 30 menit).

### Langkah 5: Kirim Pesan
Klik tombol hijau **Send WhatsApp**. Ekstensi akan mengirimkan pesan satu per satu melalui browser Chrome Anda.

---

## 🔄 4. Mengubah Pengaturan Gateway di Dashboard RS Assalam

Di halaman **Reminder Kontrol Pasien**:
1. Klik tombol status gateway di pojok kanan atas:  
   - Jika saat ini SaungWA: `SaungWA Aktif`
   - Jika Fonnte: `Gateway: Fonnte`
2. Pada dropdown **Pilih Gateway WhatsApp Aktif**, pilih:  
   👉 **RocketSend.io (Ekstensi Chrome & Upload CSV)**
3. Klik **Simpan Konfigurasi**.
4. Badge status di header akan berubah menjadi:  
   `Gateway: RocketSend (Ekstensi Chrome)`

> **Efek saat RocketSend aktif:**  
> Worker background server (`process_reminder_queue.sh`) akan otomatis dinonaktifkan sementara agar tidak terjadi pengiriman ganda atau kegagalan API, karena seluruh pengiriman dialihkan melalui browser admin menggunakan file CSV.

---

## 💡 5. Rekomendasi Solusi Jangka Panjang Anti-Banned

Jika RS Assalam ingin sistem pengiriman reminder berjalan **100% otomatis** di latar belakang tanpa risiko nomor terblokir sama sekali:
1. Pertimbangkan beralih ke **Fonnte WhatsApp Official API (WABA)** menggunakan Meta Cloud API.
2. Jalur resmi Meta menggunakan template terverifikasi sehingga **nomor tidak akan pernah diblokir** oleh algoritma spam WhatsApp.
3. Panduan pendaftaran WABA Meta dapat dibaca pada file:  
   [`dokumentasi/panduan_whatsapp_official_fonnte.md`](file:///c:/Apache24/htdocs/dashboardassalam/dokumentasi/panduan_whatsapp_official_fonnte.md).
