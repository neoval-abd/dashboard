# 🗄️ Dokumentasi Database Tambahan — Dashboard Eksekutif RS Assalam

Folder ini berisi seluruh file skema SQL (`.sql`) untuk tabel-tabel tambahan yang dibuat khusus untuk fitur **Dashboard Eksekutif RS Assalam** di atas database standar SIMRS Khanza (`db-sim`):

---

## 📋 Daftar File Skema Database Tambahan

| File SQL | Nama Tabel | Deskripsi & Fungsi | Fitur Terkait |
| :--- | :--- | :--- | :--- |
| **[`wa_gateway.sql`](file:///c:/Apache24/htdocs/dashboardassalam/database/wa_gateway.sql)** | `konfigurasi_wa_gateway`<br>`antrean_reminder_kontrol`<br>`antrean_ucapan_ulang_tahun` | Kredensial gateway (SaungWA & Fonnte), antrean pesan reminder kontrol pasien BPJS/Umum, serta antrean ucapan ulang tahun pasien. | Patient Engagement $\rightarrow$ Reminder Kontrol & Ucapan Ulang Tahun |
| **[`kendali_biaya.sql`](file:///c:/Apache24/htdocs/dashboardassalam/database/kendali_biaya.sql)** | `plafon_ranap` *(db: kendali_biaya)* | Menyimpan nominal batas pagu / plafon biaya pasien rawat inap untuk monitoring kendali biaya. | Kendali Biaya $\rightarrow$ Plafon Ranap |
| **[`roles.sql`](file:///c:/Apache24/htdocs/dashboardassalam/database/roles.sql)** | `roles` | Pemetaan peran pengguna (*Super Admin, Manajemen, Keuangan, Kepegawaian, Pelayanan*) di luar tabel standar Khanza `user`. | Super Admin $\rightarrow$ Manajemen Pengguna |
| **[`generate_nomor_dok.sql`](file:///c:/Apache24/htdocs/dashboardassalam/database/generate_nomor_dok.sql)** | `generate_nomor_dok` | Riwayat penomoran otomatis dokumen regulasi akreditasi RS (SK, SPO, Pedoman, Panduan, Program Kerja Pokja). | Akreditasi $\rightarrow$ Generator Nomor Dokumen |

---

## 🛠️ Catatan untuk Tim IT & Database Administrator (DBA)

1. **Auto-Create pada Aplikasi:**  
   Kode aplikasi PHP Dashboard Assalam sudah dilengkapi fungsi pemeriksa keberadaan tabel (`CREATE TABLE IF NOT EXISTS`) dan migrasi kolom otomatis (`ALTER TABLE ... ADD COLUMN ...`). Jika tabel belum ada di server, aplikasi akan mencoba membuatnya secara otomatis saat modul dibuka.
2. **Eksekusi Manual (Bila Akses Akun Web Terbatas):**  
   Jika pengguna MySQL yang dikonfigurasi di `config/koneksi.php` berstatus *restricted/read-only*, file-file `.sql` di folder ini dapat diimport langsung oleh DBA menggunakan phpMyAdmin, DBeaver, HeidiSQL, atau terminal MySQL server (`192.168.5.52:3382`).

