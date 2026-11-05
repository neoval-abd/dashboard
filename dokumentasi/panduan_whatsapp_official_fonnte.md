# 🏥 Panduan Pendaftaran WhatsApp Official API (WABA via Fonnte) & Fleksibilitas Gateway RS Assalam

Dokumentasi ini menjelaskan secara lengkap:
1. **Cara mendaftar WhatsApp Official Business API (WABA)** resmi dari Meta melalui **Fonnte** agar nomor RS Assalam **100% bebas dari pemblokiran/restriksi selamanya**.
2. **Cara menghubungkan Fonnte ke Dashboard RS Assalam**.
3. **Mekanisme peralihan fleksibel (*switching*)** antara **SaungWA** dan **Fonnte** secara instan kapan saja tanpa perlu merestart service di Linux Mint.
4. **Dokumentasi Perubahan Skema Database**.

---

## 1. Mengapa Rumah Sakit Membutuhkan WhatsApp Official API (WABA)?

Pada tanggal 05 November 2026, nomor WhatsApp RS Assalam sempat mengalami pembatasan (*restricted*) oleh Meta:
> *"Your account is restricted right now. Recent activity may be a sign of spam, automated or bulk messaging. You won't be able to start new chats."*

### Risiko WhatsApp Biasa (Scan QR / Unofficial):
* Menggunakan aplikasi WhatsApp Web di smartphone fisik.
* Jika mengirim pesan pengingat kontrol ke puluhan/ratusan pasien yang belum menyimpan kontak RS, algoritma anti-spam Meta akan menganggapnya *unsolicited message* dan menjatuhkan hukuman pembatasan sementara atau blokir permanen.

### Keunggulan WhatsApp Official API (WABA):
1. **0% Risiko Blokir:** Pesan dikirim melalui infrastruktur resmi Meta Cloud API (jalur resmi WhatsApp untuk instansi).
2. **Nama Resmi Instansi Muncul Otomatis:** Di layar HP pasien langsung muncul nama **"RS Assalam"**, meskipun pasien belum menyimpan nomor RS di kontak mereka.
3. **Tidak Butuh HP Fisik Nyala:** Pengiriman langsung dari server ke server (*cloud*), tidak tergantung pada baterai HP, sleep mode Android, atau jaringan Wi-Fi HP di ruangan IT.
4. **Dukungan Centang Hijau (*Official Green Badge*):** Dapat diajukan verifikasi lencana hijau resmi dari Meta.

---

## 2. Persyaratan Pendaftaran WhatsApp Official API untuk Rumah Sakit

Siapkan dokumen legalitas RS Assalam berikut sebelum memulai pendaftaran di Fonnte:

1. **Nomor Telepon Khusus:**
   * Nomor HP seluler (GSM) atau nomor telepon kantor/PSTN berkode area (contoh: `0281...` atau `08...`).
   * **PENTING:** Nomor ini **tidak boleh sedang aktif** di aplikasi WhatsApp biasa di smartphone. Jika nomor lama ingin dipakai, akun WhatsApp di HP harus di-*delete account* terlebih dahulu via *Setelan $\rightarrow$ Akun $\rightarrow$ Hapus Akun*.
2. **Dokumen Legalitas RS:**
   * NIB (Nomor Induk Berusaha) / Izin Operasional Rumah Sakit.
   * NPWP Badan Rumah Sakit.
   * Bukti Tagihan Utilitas (salah satu: Rekening Koran bank atas nama RS, Tagihan Listrik PLN, atau Tagihan Telkom/Indihome yang mencantumkan nama & alamat resmi RS).
3. **Website Resmi RS:**
   * Contoh: `https://rsassalam.com` (pastikan website aktif dan mencantumkan alamat serta kontak resmi RS).
4. **Akun Facebook Meta Business Manager (BM):**
   * Akun Facebook pengelola resmi RS Assalam untuk verifikasi bisnis di [business.facebook.com](https://business.facebook.com).

---

## 3. Langkah Demi Langkah Pendaftaran WABA melalui Fonnte

Fonnte adalah mitra resmi penyedia WhatsApp Business Platform (BSP) di Indonesia.

### Langkah 1: Registrasi Akun Fonnte
1. Buka website resmi [https://fonnte.com](https://fonnte.com).
2. Klik **Daftar / Register** menggunakan email resmi IT RS Assalam.
3. Lakukan verifikasi email dan login ke dashboard Fonnte.

### Langkah 2: Akses Menu WhatsApp Official
1. Pada menu navigasi sebelah kiri, klik menu **WhatsApp Official (WABA)**.
2. Klik tombol hijau **"Daftar WhatsApp Official / Connect with Facebook"**.

### Langkah 3: Integrasi dengan Meta Business Suite
1. Jendela pop-up Meta (Facebook) akan terbuka. Login menggunakan akun Facebook yang mengelola Meta Business Manager RS Assalam.
2. Pilih Akun Bisnis Meta RS Assalam (atau klik *Buat Akun Bisnis Baru* jika belum ada).
3. Masukkan data profil instansi:
   * **Nama Bisnis:** RS Assalam (sesuai NIB/NPWP)
   * **Kategori Bisnis:** *Medical & Health / Hospital*
   * **Website Bisnis:** URL website resmi RS Assalam.

### Langkah 4: Tentukan Display Name (Nama Pengirim di Layar Pasien)
1. Masukkan nama tampilan yang diinginkan, contoh: **RS Assalam** atau **RSU Assalam**.
2. *Tips Meta:* Display name harus mencerminkan nama resmi pada izin operasional RS agar verifikasi disetujui dalam 1x24 jam.

### Langkah 5: Registrasi Nomor & Verifikasi OTP
1. Masukkan nomor telepon pengirim yang sudah disiapkan.
2. Pilih metode verifikasi: **SMS** atau **Panggilan Suara (Voice Call)**.
3. Masukkan 6 digit kode OTP yang diterima.

### Langkah 6: Pembuatan Template Pesan Resmi (Utility)
WhatsApp Official mewajibkan pesan pengingat jadwal kontrol didaftarkan sebagai **Template Pesan Kategori Utility**:
1. Di menu WABA Fonnte, pilih **Template Management $\rightarrow$ Buat Template Baru**.
2. **Nama Template:** `reminder_kontrol_assalam`
3. **Kategori:** *UTILITY* (kategori konfirmasi/pengingat resmi dengan tarif termurah Meta).
4. **Isi Pesan:**
   ```text
   Assalamu’alaikum Wr. Wb.

   Yth. Bapak/Ibu {{1}},

   Kami dari Rumah Sakit Assalam mengingatkan jadwal kontrol Bapak/Ibu:

   📅 Tanggal: {{2}}
   ⏰ Jam: {{3}}
   👨‍⚕️ Dokter: {{4}}
   🏥 Poliklinik: {{5}}

   Mohon hadir sesuai jadwal yang telah ditentukan. Balas HADIR untuk konfirmasi kehadiran, atau hubungi kami di 0812-2345-7995 bila ada perubahan jadwal.

   Mohon memastikan:
   ✅ Surat rujukan masih berlaku pada tanggal kunjungan jika jaminan menggunakan BPJS Kesehatan
   ✅ Surat rujukan sesuai dengan poli tujuan.
   ✅ Mendaftar Melalui Aplikasi MJKN

   Terima kasih.
   Rumah Sakit Assalam
   Ihsan Dalam Pelayanan
   ```
5. Klik **Kirim untuk Persetujuan**. Template kategori utility biasanya disetujui otomatis oleh sistem AI Meta dalam 5–15 menit.

### Langkah 7: Ambil Token API Fonnte
1. Setelah perangkat WABA berstatus aktif di Fonnte, masuk ke menu **Device**.
2. Klik tombol **Salin Token** (token berupa kode acak, contoh: `1oFryxP8vVwKkaMWhaHM`).

---

## 4. Cara Menghubungkan & Berpindah Fleksibel di Dashboard RS Assalam

Sistem Dashboard RS Assalam telah diperbarui dengan fitur **Dual-Gateway Terpadu**. Anda dapat berpindah dari **SaungWA ke Fonnte** (atau sebaliknya) kapan saja dalam hitungan detik langsung dari halaman web.

### Langkah Beralih ke Fonnte:
1. Buka halaman **Patient Engagement $\rightarrow$ Reminder Kontrol** (`http://192.168.5.44/dashboardassalam/laporan_reminder_kontrol.php`).
2. Klik tombol status gateway di pojok kanan atas.
3. Pada dropdown **Pilih Gateway WhatsApp Aktif**, pilih:
   👉 **`Fonnte (Cadangan / WhatsApp Official API)`**
4. Kolom SaungWA akan otomatis disembunyikan dan berganti ke input **Token Fonnte**.
5. Masukkan Token Fonnte yang didapat dari Langkah 7.
6. Pada kotak **Uji Kirim Pesan Langsung**, masukkan nomor HP WA Anda untuk tes kirim.
7. Klik tombol biru **`Simpan & Kirim Pesan Tes (Fonnte)`**.
8. Jika pesan tes masuk ke nomor Anda, konfigurasi otomatis tersimpan ke database dan badge di kanan atas berubah hijau menjadi:
   `Gateway: Fonnte (Aktif)`

### Cara Beralih Kembali ke SaungWA Kapan Saja:
1. Klik kembali tombol status gateway di pojok kanan atas.
2. Ubah dropdown menjadi:
   👉 **`SaungWA (Utama / Scan QR)`**
3. Masukkan nomor tes lalu klik **`Simpan & Kirim Pesan Tes (SaungWA)`**.
4. Badge otomatis kembali menampilkan `SaungWA Aktif (089675463447)`.

---

## 5. Bagaimana Background Service di Linux Mint Merespons?

* **Tanpa Perlu Restart Service:**  
  Worker background antrean yang berjalan di Linux Mint (`assalam-wa-queue.service`) memeriksa antrean dan konfigurasi gateway secara langsung dari database MySQL pada **setiap siklus pengiriman (~5 menit)**.
* Saat Anda mengganti gateway di web dashboard:
  - Siklus berikutnya otomatis mengirim menggunakan provider yang baru dipilih (Fonnte atau SaungWA).
  - Terminal service akan langsung menampilkan log:
    ```bash
    [FONNTE][Reminder Kontrol] Terkirim ke PASIEN (081234 - 62812xxxx) | Sisa antrean: 4 kontrol
    ```
    atau jika kembali ke SaungWA:
    ```bash
    [SAUNGWA][Reminder Kontrol] Terkirim ke PASIEN (081234 - 62812xxxx) | Sisa antrean: 3 kontrol
    ```

---

## 6. 🗄️ Dokumentasi Penambahan Database (Wajib Diketahui)

Sesuai instruksi Anda, seluruh perubahan database telah didokumentasikan di folder **`database/`**:

* **File Dokumentasi SQL:** [`database/wa_gateway.sql`](file:///c:/Apache24/htdocs/dashboardassalam/database/wa_gateway.sql)
* **Penambahan Kolom:**
  Pada tabel `konfigurasi_wa_gateway`, ditambahkan 1 kolom baru untuk menyimpan Token Fonnte:
  ```sql
  ALTER TABLE `konfigurasi_wa_gateway` 
  ADD COLUMN `fonnte_token` VARCHAR(100) NOT NULL DEFAULT '' AFTER `auth_key`;
  ```
* **Keterangan:**
  Aplikasi web Dashboard RS Assalam telah dilengkapi fungsi `ensure_wa_gateway_config_table()` yang secara otomatis menambahkan kolom tersebut ke database MySQL server (`192.168.5.52:3382 / db-sim`) dengan aman tanpa menghapus data yang sudah ada. File `database/wa_gateway.sql` dapat digunakan oleh tim DBA jika ingin melakukan pengecekan manual atau backup skema.

