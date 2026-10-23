# Panduan Database JANGKAR NTT
**Aplikasi:** JANGKAR NTT (Jaringan Administrasi Notifikasi & Ketetapan Pajak Kendaraan Air)  
**Instansi:** Badan Pendapatan dan Aset Daerah (BPAD) Provinsi Nusa Tenggara Timur  

---

## 📁 Struktur Folder Database
Folder ini telah dirapikan dan dikompilasi menjadi **1 file skema master tunggal** yang bersih, lengkap, dan siap dijalankan di komputer atau server lain:

1. **`schema.sql`** *(Master Schema & Seeders)*
   - Berisi seluruh perintah DDL pembentukan 15 tabel, indeks, relasi Foreign Key (dengan ON UPDATE CASCADE), serta DML pengisian data master seeder (22 Kabupaten/Kota NTT, Standar NJKB Body, Standar NJKB Mesin, Standar Paket 1 Kesatuan, Pengaturan Sistem, Pejabat, Akun Pengguna, dan Data Sampel Kapal).
2. **`rebuild_database.php`** *(Otomatisasi Eksekutor)*
   - Skrip PHP untuk membangun atau mereset basis data secara otomatis dalam 1 klik baik via terminal (CLI) maupun web browser.

---

## 🚀 Cara Menjalankan di Komputer Lain

Anda dapat memilih salah satu dari 3 cara praktis berikut saat memasang aplikasi ini di komputer lain:

### Cara 1: Otomatis via Web Browser (Paling Praktis)
1. Pastikan XAMPP (Apache & MySQL) sudah berjalan.
2. Buka web browser (Chrome / Edge), lalu akses URL:
   ```
   http://localhost/PKAA/database/rebuild_database.php
   ```
3. Sistem akan otomatis membuat basis data `db_pkaa`, mengeksekusi `schema.sql`, dan menampilkan kartu laporan sukses.

### Cara 2: Import via phpMyAdmin
1. Buka phpMyAdmin di browser: `http://localhost/phpmyadmin`
2. Klik tab **Import** (Impor).
3. Pilih file: `c:/xampp/htdocs/PKAA/database/schema.sql`
4. Klik tombol **Import / Kirim**. Seluruh basis data `db_pkaa` dan 15 tabel beserta isinya akan langsung terbuat.

### Cara 3: Melalui Terminal / Command Line (CLI)
Buka Command Prompt atau PowerShell di folder proyek:
```bash
# Opsi A: Menggunakan PHP Runner
php database/rebuild_database.php

# Opsi B: Menggunakan MySQL Client Langsung
mysql -u root < database/schema.sql
```

---

## 🔑 Akun Pengguna Bawaan (Default Login)
Semua akun telah dienkripsi dengan algoritma aman `BCRYPT`:

| Role / Peran | Username | Password | Keterangan |
| :--- | :--- | :--- | :--- |
| **Administrator Induk** | `admin` | `admin123` | Akses penuh, verifikasi perubahan, pengaturan master NJKB |
| **Operator Pendaftaran** | `operator` | `operator123` | Pendaftaran KAA baru, cetak SUPERPKAA, cetak SKPD |
| **Kasir Kasda** | `kasir` | `kasir123` | Layanan pelunasan SKPD Bank NTT & Rekonsiliasi |

---

## 📊 Daftar Tabel Basis Data (15 Tabel)
1. `ref_pengaturan_sistem` : Parameter konfigurasi nama UPTD, alamat, dan opsen 66%.
2. `ref_kabupaten_kota` : Daftar 22 Kabupaten/Kota di NTT, tipe, ibukota, dan pelabuhan default.
3. `ref_jenis_kendaraan` : Klasifikasi jenis kendaraan air (speedboat, KMP, nelayan, phinisi, tunda, tongkang, dinas).
4. `ref_fungsi_tarif` : Fungsi peruntukan kapal & tarif (Pribadi 1,2%, Angkutan Umum 0,5%, Dinas 0,5%).
5. `ref_njkb_body` : Standar harga pasar lambung/body kapal per merek & tahun buat.
6. `ref_njkb_mesin` : Standar harga pasar mesin kapal per merek, daya PK, dan tahun buat.
7. `ref_njkb_gabungan` : Standar harga paket 1 kesatuan (body + seluruh mesin).
8. `users` : Data akun pengguna berjenjang role.
9. `pejabat` : Data pejabat penandatangan SKPD dan laporan resmi.
10. `kendaraan_air` : Master data kapal terdaftar di NTT.
11. `mesin_kendaraan` : Rincian data nomor seri, daya PK, dan nilai mesin kapal.
12. `transaksi_superpkaa` : Surat Pemberitahuan Pajak KAA (dengan tracking `status_cetak`).
13. `transaksi_skpd` : Bukti setor sah pelunasan SKPD 3 rangkap.
14. `pengajuan_perubahan_kaa` : Pengajuan koreksi data kapal dari operator ke admin induk.
15. `log_cetak_superpkaa` : Buku register pencatatan cetak fisik dokumen penetapan.
