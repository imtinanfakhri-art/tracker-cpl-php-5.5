# PANDUAN DEPLOY SISTEM INFORMASI CPL UINSA (PHP 5.5 cPanel Edition)

Aplikasi ini telah dikonversi secara khusus agar **100% kompatibel dengan PHP versi 5.5, 5.6, 7.x, dan 8.x** serta web hosting berbasis **cPanel** (Apache + MySQL / MariaDB).

---

## 📋 DAFTAR FILE DALAM PAKET INI
1. `config.php` - Pengaturan database MySQL & helper sistem.
2. `database.sql` - Skema tabel & data awal (impor lewat phpMyAdmin).
3. `.htaccess` - Pengaturan server Apache & proteksi file sensitif.
4. `index.php` & `login.php` - Halaman login & otentikasi.
5. `dashboard.php` - Statistik capaian CPL, metrik terbobot, & kartu kriteria.
6. `input-nilai.php` - Form penilaian CPL dengan kalkulator otomatis live & tabel CRUD.
7. `import-nilai.php` - Fitur impor nilai massal via berkas spreadsheet CSV.
8. `mata-kuliah.php` - Manajemen mata kuliah & pemetaan 12 kriteria CPL.
9. `fakultas-prodi.php` - Manajemen master-detail Fakultas dan Program Studi.
10. `laporan.php` - Laporan ketercapaian kompetensi OBE.
11. `print-laporan.php` - Format resmi cetak kop surat & tanda tangan dekan/kaprodi.
12. `export-excel.php` - Ekspor data nilai ke format Excel (.csv).
13. `users.php` - Manajemen pengguna (RBAC: Admin, Kaprodi, Dosen).
14. `audit-log.php` - Jejak audit aktivitas perubahan data.
15. `reset-password.php` - Fitur atur ulang kata sandi dengan token kedaluwarsa.
16. `api.php` - Endpoint JSON untuk AJAX.

---

## 🚀 LANGKAH DEPLOYMENT DI CPANEL (5 MENIT)

### LANGKAH 1: Buat Database MySQL di cPanel
1. Masuk ke akun **cPanel** hosting Anda.
2. Buka menu **MySQL Databases** (atau **MySQL Database Wizard**).
3. Buat database baru, misalnya: `namacpanel_cpl`.
4. Buat user database baru, misalnya: `namacpanel_cpluser` dengan password kuat.
5. Hubungkan User ke Database tersebut, centang **ALL PRIVILEGES**, lalu klik **Make Changes**.

### LANGKAH 2: Impor `database.sql` via phpMyAdmin
1. Kembali ke halaman utama cPanel, klik menu **phpMyAdmin**.
2. Pilih nama database yang baru Anda buat di panel kiri (`namacpanel_cpl`).
3. Klik tab **Import** di bagian atas.
4. Klik **Choose File** dan pilih file `database.sql` dari paket ini.
5. Klik tombol **Go** (Kirim) di bagian bawah. Tunggu hingga muncul pesan hijau sukses (*Import has been successfully finished*).

### LANGKAH 3: Unggah File ke File Manager cPanel
1. Di cPanel, buka **File Manager**.
2. Masuk ke direktori `public_html` (atau buat subfolder, misal: `public_html/cpl`).
3. Klik tombol **Upload** di bagian atas, lalu unggah file zip `cpl-tracker-php55.zip`.
4. Setelah selesai, klik kanan file zip tersebut lalu pilih **Extract**.

### LANGKAH 4: Konfigurasi `config.php`
1. Di File Manager cPanel, klik kanan file `config.php` lalu pilih **Edit**.
2. Sesuaikan baris berikut dengan informasi database Anda pada Langkah 1:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'namacpanel_cpl');     // Nama database Anda di cPanel
   define('DB_USER', 'namacpanel_cpluser'); // Username database Anda di cPanel
   define('DB_PASS', 'PasswordDatabaseAnda123!'); // Password database Anda
   ```
3. Klik **Save Changes**.

### LANGKAH 5: Pastikan Versi PHP di MultiPHP Manager
1. Di cPanel, buka **MultiPHP Manager**.
2. Cari domain atau direktori web Anda.
3. Anda dapat memilih versi **PHP 5.5**, **PHP 5.6**, **PHP 7.4**, atau **PHP 8.x** (kode ini kompatibel dengan semua versi tersebut).
4. Klik **Apply**.

---

## 🔑 AKUN DEMO BAWAAN SETELAH IMPORT
Setelah mengimpor `database.sql`, sistem telah menyediakan 3 akun awal:

1. **Administrator (Akses Penuh):**
   - Email: `imtinan.fakhri@gmail.com`
   - Password: `admin123`

2. **Kaprodi Teknik Informatika:**
   - Email: `kaprodi.ti@uinsa.ac.id`
   - Password: `kaprodi123`

3. **Dosen Teknik Informatika:**
   - Email: `dosen.ti@uinsa.ac.id`
   - Password: `dosen123`

*Catatan: Segera ubah kata sandi di menu Pengguna atau profil setelah login pertama kali untuk keamanan.*

---

## 📐 FORMULA PERHITUNGAN CPL TERBOBOT
Sistem menggunakan formula Outcome-Based Education (OBE):
- **Sikap (20%):** Kriteria CPL-S1 s/d CPL-S3
- **Pengetahuan (10%):** Kriteria CPL-P1 s/d CPL-P4
- **Keterampilan Umum (30%):** Kriteria CPL-KU1 & CPL-KU2
- **Keterampilan Khusus (40%):** Kriteria CPL-KK1 s/d CPL-KK3

**Formula Renormalisasi:**
`Total Nilai = Σ (Rata-rata Kategori yang Ada × Bobot Kategori) / Σ (Bobot Kategori yang Ada)`

**Skala Predikat:**
- **>= 85.00:** Grade A (Sangat Memuaskan)
- **>= 75.00:** Grade B (Memuaskan)
- **>= 65.00:** Grade C (Cukup)
- **>= 50.00:** Grade D (Kurang)
- **< 50.00:** Grade E (Gagal)
