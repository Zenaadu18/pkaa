# ⚓ JANGKAR NTT
### Jaringan Administrasi Nyata Kendaraan Air Regional Nusa Tenggara Timur
**Aplikasi Pelayanan & Pemungutan Pajak Kendaraan di Atas Air (PKAA) Resmi Badan Pendapatan dan Aset Daerah (BPAD) Provinsi Nusa Tenggara Timur**

---

## 📌 Ringkasan Aplikasi
**JANGKAR NTT** adalah sistem informasi desktop-web enterprise terpadu yang dirancang khusus untuk memodernisasi tata kelola administrasi, pendataan objek, perhitungan nilai jual (NJKB), serta penetapan dan penagihan **Pajak Kendaraan di Atas Air (PKAA)** beserta **Opsen Pajak 66%** di seluruh 22 Kabupaten/Kota di wilayah Nusa Tenggara Timur.

---

## 🚀 Fitur Unggulan

### 1. Pelayanan Wajib Pajak & Objek Baru
- **Nomor Objek Pajak Daerah (NOPD) Otomatis**: Format resmi 3 kotak terkunci `KA [0001] [Wilayah]` yang secara otomatis mengkalkulasi nomor urut registrasi terbaru berdasarkan pilihan dari 22 Kabupaten/Kota di NTT.
- **Fleksibilitas Penilaian NJKB**:
  - **Metode Terpisah**: Penilaian harga body kapal dan rincian tiap mesin penggerak (1 s/d 6 mesin) secara individual.
  - **Metode 1 Harga (Kesatuan/Gabungan)**: Penilaian paket utuh kapal, dengan basis data yang terisolasi rapi dan bersih.
- **Mekanisme Auto-ACC vs Pengajuan Admin**: Jika merek dan tipe kendaraan telah terdaftar di database acuan NJKB, sistem langsung menyetujui pendaftaran (*Auto-ACC*) dan menerbitkan SUPERPKAA. Jika terdapat merek baru yang belum terstandarisasi, berkas otomatis diajukan ke panel Admin Induk untuk penaksiran nilai.

### 2. Dokumen Penetapan & Cetak Resmi
- **SUPERPKAA (Surat Pemberitahuan Pajak Kendaraan di Atas Air)**: Format cetak portrait formal lengkap dengan rincian komponen NJKB, tarif peruntukan (Pribadi/Badan 1.2%, Angkutan Umum 0.5%, Pemerintah 0.5%), bea pokok, opsen 66%, sanksi administrasi, dan kolom tanda tangan pejabat UPTD.
- **SKPD PKAA (Surat Ketetapan Pajak Daerah)**: Format cetak landscape (miring) presisi tinggi dengan barcode verifikasi, blok tabel hitam-putih khas dokumen kedinasan, rincian audit fiskal, dan kuitansi pembayaran resmi.
- **Cetak Lembar Alokasi Opsen (66%)**: Dokumentasi pembagian penerimaan pajak antara kas daerah provinsi dan kabupaten/kota asal kendaraan.
- **Log Riwayat Cetak Real-Time**: Audit trail pencetakan dokumen yang mencatat waktu, petugas loket, dan alamat IP.

### 3. Pemeliharaan Data Master & Tarif
- **Master Kendaraan di Atas Air**: Pencarian cepat, filter status pajak (Terdaftar, Terbit SUPER, Lunas, Tunggakan), dan riwayat wajib pajak.
- **Master Referensi 22 Kabupaten/Kota di NTT**: Kode wilayah plat, nama ibukota, dan pelabuhan pangkal default.
- **Master Standar NJKB**: Database benchmark nilai jual body kapal, mesin outboard/inboard, dan paket gabungan.
- **Master Tarif Pajak & Pejabat Penandatangan**: Konfigurasi dinamis tarif fungsi dan data pejabat penandatangan SKPD / Laporan.

### 4. Laporan Eksekutif & Statistik
- **Laporan Realisasi Penerimaan PKAA**: Rekapitulasi target vs realisasi berdasarkan rentang tanggal, fungsi kapal, dan wilayah.
- **Rekapitulasi Tunggakan Pajak**: Deteksi otomatis jatuh tempo pajak dengan status peringatan dini.
- **Grafik Tren & Distribusi**: Visualisasi komposisi objek pajak dan performa penerimaan daerah.

---

## 🛠️ Persyaratan Sistem
- **Web Server**: Apache (XAMPP / Laragon / Standalone Apache)
- **PHP**: Versi 8.0 atau lebih baru (Disarankan PHP 8.1 / 8.2) dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`
- **Database**: MySQL 5.7+ atau MariaDB 10.4+
- **Browser**: Google Chrome, Microsoft Edge, atau Mozilla Firefox

---

## 📥 Panduan Instalasi

1. **Clone Repository**:
   ```bash
   git clone https://github.com/Zenaadu18/pkaa.git
   cd pkaa
   ```
   *(Atau tempatkan folder proyek ke dalam direktori `c:/xampp/htdocs/PKAA`)*

2. **Konfigurasi Environment (`.env`)**:
   Salin file `.env.example` menjadi `.env`:
   ```bash
   cp .env.example .env
   ```
   Buka file `.env` dan sesuaikan koneksi database MySQL lokal Anda:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=db_pkaa
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Inisialisasi Database**:
   Aplikasi dilengkapi fitur auto-create database saat pertama kali diakses. Namun Anda juga dapat menjalankan rebuild database master langsung:
   - **Via Browser**: Buka `http://localhost/PKAA/database/rebuild_database.php`
   - **Via CLI**:
     ```bash
     php database/rebuild_database.php
     ```

4. **Akses Aplikasi**:
   Buka peramban dan akses:
   ```
   http://localhost/PKAA
   ```

---

## 🔐 Akun Default untuk Pengujian

| Peran (Role) | Username | Password | Deskripsi Hak Akses |
| :--- | :--- | :--- | :--- |
| **Admin Induk** | `admin` | `admin123` | Akses penuh seluruh modul, verifikasi perubahan data, penaksiran NJKB, cetak dokumen, dan laporan |
| **Operator Loket** | `operator` | `operator123` | Pendaftaran objek baru, pengajuan perubahan data, cetak SUPERPKAA, dan cetak SKPD |

---

## 📂 Struktur Direktori

```
PKAA/
├── assets/                  # File statis CSS, JavaScript, gambar, dan logo
│   ├── css/                 # Stylesheet antarmuka desktop, tema, & cetak dokumen
│   ├── img/                 # Logo resmi Pemprov NTT & lambang JANGKAR NTT
│   └── js/                  # Interaktivitas UI & helper kalkulasi
├── cetak/                   # Template cetak dokumen resmi (SUPERPKAA, SKPD, Opsen)
├── config/                  # Konfigurasi aplikasi, koneksi database PDO, auth, & helper
├── database/                # Skema database master (schema.sql) & migration script
├── layouts/                 # Shell antarmuka (header, sidebar desktop, topbar, footer)
├── modules/                 # Modul bisnis aplikasi
│   ├── auth/                # Login & logout sesi
│   ├── dashboard/           # Beranda & widget statistik ringkasan penerimaan
│   ├── pelayanan/           # Pendaftaran, verifikasi, SUPERPKAA, SKPD
│   ├── pemeliharaan/        # Master NJKB, tarif, kendaraan, wilayah NTT, pejabat
│   └── laporan/             # Rekapitulasi penerimaan, tunggakan, & ekspor cetak
├── .env.example             # Template konfigurasi environment
├── .gitignore               # Konfigurasi file yang diabaikan oleh Git
├── index.php                # Front controller / router tunggal aplikasi
└── README.md                # Dokumentasi proyek
```

---

## 🏛️ Lisensi & Hak Cipta
Hak Cipta &copy; 2026 Pemerintah Provinsi Nusa Tenggara Timur &mdash; Badan Pendapatan dan Aset Daerah (BPAD). Seluruh hak cipta dilindungi undang-undang.
