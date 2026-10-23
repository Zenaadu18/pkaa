-- =============================================================================
-- APLIKASI : JANGKAR NTT (Jaringan Administrasi Notifikasi & Ketetapan Pajak Kendaraan Air)
-- INSTANSI : BADAN PENDAPATAN DAN ASET DAERAH (BPAD) PROVINSI NUSA TENGGARA TIMUR
-- FILE     : database/schema.sql (MASTER DATABASE SCHEMA & INITIAL DATA)
-- VERSI    : 2.0 (LENGKAP: STANDAR NJKB BODY, MESIN, GABUNGAN, 22 KAB/KOTA NTT, ALUR CETAK)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- 0. INISIALISASI BASIS DATA
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `db_pkaa` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_pkaa`;

-- -----------------------------------------------------------------------------
-- PEMBERSIHAN TABEL LAMA (URUTAN AMAN)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `log_cetak_superpkaa`;
DROP TABLE IF EXISTS `pengajuan_perubahan_kaa`;
DROP TABLE IF EXISTS `transaksi_skpd`;
DROP TABLE IF EXISTS `transaksi_superpkaa`;
DROP TABLE IF EXISTS `mesin_kendaraan`;
DROP TABLE IF EXISTS `kendaraan_air`;
DROP TABLE IF EXISTS `pejabat`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `ref_njkb_gabungan`;
DROP TABLE IF EXISTS `ref_njkb_mesin`;
DROP TABLE IF EXISTS `ref_njkb_body`;
DROP TABLE IF EXISTS `ref_fungsi_tarif`;
DROP TABLE IF EXISTS `ref_jenis_kendaraan`;
DROP TABLE IF EXISTS `ref_kabupaten_kota`;
DROP TABLE IF EXISTS `ref_pengaturan_sistem`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. TABEL-TABEL REFERENSI SISTEM, WILAYAH, & TARIF
-- =============================================================================

-- 1.1 ref_pengaturan_sistem
CREATE TABLE `ref_pengaturan_sistem` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parameter` VARCHAR(100) NOT NULL UNIQUE,
  `nilai` TEXT NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.2 ref_kabupaten_kota (22 Kabupaten/Kota di Nusa Tenggara Timur)
CREATE TABLE `ref_kabupaten_kota` (
  `kode_wilayah` VARCHAR(5) PRIMARY KEY,
  `nama_kabupaten_kota` VARCHAR(100) NOT NULL,
  `tipe` ENUM('KOTA', 'KABUPATEN') NOT NULL DEFAULT 'KABUPATEN',
  `ibukota` VARCHAR(100) NOT NULL,
  `pelabuhan_pangkal_default` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.3 ref_jenis_kendaraan
CREATE TABLE `ref_jenis_kendaraan` (
  `kode_jenis` VARCHAR(10) PRIMARY KEY,
  `nama_jenis` VARCHAR(100) NOT NULL,
  `keterangan` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1.4 ref_fungsi_tarif
CREATE TABLE `ref_fungsi_tarif` (
  `kode_fungsi` VARCHAR(10) PRIMARY KEY,
  `nama_fungsi` VARCHAR(100) NOT NULL,
  `kategori_tarif` ENUM('ANGKUTAN_UMUM', 'PEMERINTAH', 'PRIBADI_BADAN') NOT NULL,
  `tarif_persen` DECIMAL(5,2) NOT NULL DEFAULT 0.50,
  `keterangan` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. TABEL STANDAR NILAI JUAL KENDARAAN DI ATAS AIR (NJKB)
-- =============================================================================

-- 2.1 ref_njkb_body (Nilai Jual Body/Lambung Kapal per Merek & Tahun Buat)
CREATE TABLE `ref_njkb_body` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `merek` VARCHAR(100) NOT NULL,
  `tipe_model` VARCHAR(100) NULL,
  `tahun_buat` INT NOT NULL,
  `panjang_meter` DECIMAL(6,2) NULL,
  `nilai_jual` DECIMAL(18,2) NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_body` (`merek`, `tahun_buat`, `tipe_model`),
  INDEX `idx_merek_body` (`merek`),
  INDEX `idx_tahun_body` (`tahun_buat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.2 ref_njkb_mesin (Nilai Jual Mesin Penggerak per Merek, Daya PK, & Tahun Buat)
CREATE TABLE `ref_njkb_mesin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `merek` VARCHAR(100) NOT NULL,
  `tipe_tenaga` VARCHAR(100) NULL,
  `daya_pk` INT NULL,
  `tahun_buat` INT NOT NULL,
  `nilai_jual` DECIMAL(18,2) NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_mesin` (`merek`, `daya_pk`, `tahun_buat`),
  INDEX `idx_merek_mesin` (`merek`),
  INDEX `idx_tahun_mesin` (`tahun_buat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.3 ref_njkb_gabungan (Nilai Jual 1 Kesatuan Paket Body + Mesin)
CREATE TABLE `ref_njkb_gabungan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `merek_paket` VARCHAR(150) NOT NULL,
  `merek_body` VARCHAR(100) NOT NULL,
  `merek_mesin` VARCHAR(100) NOT NULL,
  `tahun_buat` INT NOT NULL,
  `kapasitas_gt` DECIMAL(8,2) NULL,
  `nilai_jual` DECIMAL(18,2) NOT NULL,
  `keterangan` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_gabungan` (`merek_paket`, `tahun_buat`),
  INDEX `idx_merek_gabungan` (`merek_paket`),
  INDEX `idx_tahun_gabungan` (`tahun_buat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. TABEL PENGGUNA & PEJABAT
-- =============================================================================

-- 3.1 users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `nama_lengkap` VARCHAR(150) NOT NULL,
  `nip` VARCHAR(50) NULL,
  `role` ENUM('ADMIN', 'OPERATOR', 'KASIR', 'KEPALA_UPT') NOT NULL DEFAULT 'OPERATOR',
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.2 pejabat
CREATE TABLE `pejabat` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tipe_pejabat` ENUM('SKPD', 'LAPORAN') NOT NULL,
  `nama_pejabat` VARCHAR(150) NOT NULL,
  `nip` VARCHAR(50) NOT NULL,
  `pangkat_gol` VARCHAR(100) NOT NULL,
  `jabatan` VARCHAR(150) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. TABEL OBJEK KENDARAAN AIR & RINCIAN MESIN
-- =============================================================================

-- 4.1 kendaraan_air
CREATE TABLE `kendaraan_air` (
  `no_objek_pajak` VARCHAR(30) PRIMARY KEY,
  `kode_kabkota` VARCHAR(5) NOT NULL,
  `no_ijin_operasi` VARCHAR(100) NULL,
  `tanda_selar` VARCHAR(100) NULL,
  `nama_kapal` VARCHAR(150) NOT NULL,
  `bahan_kapal` VARCHAR(50) NOT NULL DEFAULT 'FIBER',
  `nama_pemilik` VARCHAR(150) NOT NULL,
  `alamat_pemilik` TEXT NOT NULL,
  `no_hp` VARCHAR(30) NULL,
  `kode_jenis` VARCHAR(10) NOT NULL,
  `kode_fungsi` VARCHAR(10) NOT NULL,
  `jumlah_mesin` INT NOT NULL DEFAULT 1,
  `metode_njkb` ENUM('TERPISAH', 'GABUNGAN') NOT NULL DEFAULT 'TERPISAH',
  `merek_body` VARCHAR(100) NULL,
  `tahun_body` INT NULL,
  `nj_body_kapal` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `merek_mesin` VARCHAR(100) NULL,
  `tahun_mesin_utama` INT NULL,
  `total_njkb_mesin` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `merek_gabungan` VARCHAR(150) NULL,
  `njkb_gabungan` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_njkb` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tahun_buatan` INT NOT NULL,
  `kapasitas_gt` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  `pelabuhan_pangkal` VARCHAR(100) NOT NULL,
  `tg_akhir_pajak` DATE NOT NULL,
  `status_pajak` ENUM('TERDAFTAR', 'TERBIT_SUPER', 'LUNAS', 'JATUH_TEMPO') DEFAULT 'TERDAFTAR',
  `status_verifikasi` ENUM('VERIFIKASI', 'MENUNGGU_VERIFIKASI') DEFAULT 'VERIFIKASI',
  `didaftarkan_oleh` INT NULL,
  `status_aktif` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_kaa_wilayah` FOREIGN KEY (`kode_kabkota`) REFERENCES `ref_kabupaten_kota` (`kode_wilayah`) ON UPDATE CASCADE,
  CONSTRAINT `fk_kaa_jenis` FOREIGN KEY (`kode_jenis`) REFERENCES `ref_jenis_kendaraan` (`kode_jenis`) ON UPDATE CASCADE,
  CONSTRAINT `fk_kaa_fungsi` FOREIGN KEY (`kode_fungsi`) REFERENCES `ref_fungsi_tarif` (`kode_fungsi`) ON UPDATE CASCADE,
  INDEX `idx_kaa_status` (`status_pajak`),
  INDEX `idx_kaa_verifikasi` (`status_verifikasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.2 mesin_kendaraan
CREATE TABLE `mesin_kendaraan` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `no_objek_pajak` VARCHAR(30) NOT NULL,
  `mesin_ke` INT NOT NULL,
  `merek_mesin` VARCHAR(100) NOT NULL,
  `nomor_mesin` VARCHAR(100) NULL,
  `daya_pk` INT NULL,
  `tahun_mesin` INT NOT NULL,
  `nilai_jual_mesin` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_mesin_kaa` FOREIGN KEY (`no_objek_pajak`) REFERENCES `kendaraan_air` (`no_objek_pajak`) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX `idx_mesin_merek` (`merek_mesin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. TABEL TRANSAKSI SUPERPKAA & SKPD
-- =============================================================================

-- 5.1 transaksi_superpkaa (Surat Pemberitahuan Pajak Kendaraan di Atas Air)
CREATE TABLE `transaksi_superpkaa` (
  `no_superpkaa` VARCHAR(50) PRIMARY KEY,
  `no_objek_pajak` VARCHAR(30) NOT NULL,
  `tgl_terbit` DATE NOT NULL,
  `masa_pajak_awal` DATE NOT NULL,
  `masa_pajak_akhir` DATE NOT NULL,
  `njkb_dasar` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `tarif_pokok_persen` DECIMAL(5,2) NOT NULL,
  `bea_pkaa_pokok` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `bea_pkaa_denda` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `opsen_persen` DECIMAL(5,2) NOT NULL DEFAULT 66.00,
  `opsen_pkaa_pokok` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `opsen_pkaa_denda` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_ketetapan` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('TERBIT', 'LUNAS', 'BATAL') DEFAULT 'TERBIT',
  `status_cetak` TINYINT(1) DEFAULT 0,
  `tgl_cetak_pertama` DATETIME NULL,
  `id_petugas` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_super_kaa` FOREIGN KEY (`no_objek_pajak`) REFERENCES `kendaraan_air` (`no_objek_pajak`) ON UPDATE CASCADE,
  INDEX `idx_super_nopd` (`no_objek_pajak`),
  INDEX `idx_super_tgl` (`tgl_terbit`),
  INDEX `idx_super_cetak` (`status_cetak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.2 transaksi_skpd (Surat Ketetapan Pajak Daerah & Pelunasan Kasda)
CREATE TABLE `transaksi_skpd` (
  `no_skpd` VARCHAR(50) PRIMARY KEY,
  `no_superpkaa` VARCHAR(50) NOT NULL,
  `no_objek_pajak` VARCHAR(30) NOT NULL,
  `tgl_bayar` DATE NOT NULL,
  `metode_bayar` VARCHAR(50) NOT NULL DEFAULT 'BANK NTT',
  `no_resi_bukti` VARCHAR(100) NULL,
  `bea_pkaa_pokok_bayar` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `bea_pkaa_denda_bayar` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `opsen_pkaa_pokok_bayar` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `opsen_pkaa_denda_bayar` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `total_bayar` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `pejabat_skpd_id` INT NOT NULL,
  `id_petugas_kasir` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_skpd_super` FOREIGN KEY (`no_superpkaa`) REFERENCES `transaksi_superpkaa` (`no_superpkaa`) ON UPDATE CASCADE,
  CONSTRAINT `fk_skpd_kaa` FOREIGN KEY (`no_objek_pajak`) REFERENCES `kendaraan_air` (`no_objek_pajak`) ON UPDATE CASCADE,
  INDEX `idx_skpd_nopd` (`no_objek_pajak`),
  INDEX `idx_skpd_tgl` (`tgl_bayar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. TABEL PELAYANAN OPERATOR & LOG ARSIP
-- =============================================================================

-- 6.1 pengajuan_perubahan_kaa (Pengajuan Perubahan Data dari Operator ke Admin Induk)
CREATE TABLE `pengajuan_perubahan_kaa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `no_pengajuan` VARCHAR(50) UNIQUE NOT NULL,
  `no_objek_pajak` VARCHAR(30) NOT NULL,
  `nama_kapal` VARCHAR(150) NOT NULL,
  `nama_pemilik` VARCHAR(150) NOT NULL,
  `perubahan_data` TEXT NOT NULL,
  `alasan_perubahan` TEXT NOT NULL,
  `lampiran_dokumen` VARCHAR(255) NULL,
  `status` ENUM('MENUNGGU_VERIFIKASI', 'DISETUJUI', 'DITOLAK') DEFAULT 'MENUNGGU_VERIFIKASI',
  `diajukan_oleh_id` INT NOT NULL,
  `diajukan_oleh_nama` VARCHAR(100) NOT NULL,
  `diverifikasi_oleh_id` INT NULL,
  `catatan_verifikasi` TEXT NULL,
  `tgl_pengajuan` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `tgl_verifikasi` DATETIME NULL,
  INDEX `idx_pengajuan_nopd` (`no_objek_pajak`),
  INDEX `idx_pengajuan_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6.2 log_cetak_superpkaa (Arsip Riwayat Cetak Dokumen SUPERPKAA)
CREATE TABLE `log_cetak_superpkaa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `no_superpkaa` VARCHAR(50) NOT NULL,
  `no_objek_pajak` VARCHAR(30) NOT NULL,
  `nama_kapal` VARCHAR(150) NOT NULL,
  `nama_pemilik` VARCHAR(150) NOT NULL,
  `dicetak_oleh_id` INT NOT NULL,
  `dicetak_oleh_nama` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(50) NULL,
  `tgl_cetak` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `keterangan` VARCHAR(255) DEFAULT 'Cetak Lembar Resmi SUPERPKAA',
  INDEX `idx_log_super` (`no_superpkaa`),
  INDEX `idx_log_nopd` (`no_objek_pajak`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. DATA MASTER AWAL (SEEDERS LENGKAP)
-- =============================================================================

-- 7.1 Parameter Pengaturan Sistem
INSERT INTO `ref_pengaturan_sistem` (`parameter`, `nilai`, `keterangan`) VALUES
('nama_aplikasi', 'JANGKAR NTT', 'Nama Resmi Aplikasi Sistem'),
('kepanjangan_aplikasi', 'Jaringan Administrasi Notifikasi & Ketetapan Pajak Kendaraan Air Nusa Tenggara Timur', 'Kepanjangan Singkatan JANGKAR NTT'),
('nama_instansi', 'BADAN PENDAPATAN DAN ASET DAERAH PROVINSI NUSA TENGGARA TIMUR', 'Nama Instansi Induk BPAD NTT'),
('nama_upt', 'UPTD PENDAPATAN DAERAH PROVINSI NUSA TENGGARA TIMUR', 'Nama Unit Pelaksana Teknis'),
('alamat_kantor', 'Jl. Polisi Militer No. 1, Oebobo, Kota Kupang, Nusa Tenggara Timur 85111', 'Alamat Kantor Induk BPAD NTT'),
('telepon_kantor', '(0380) 821035 / 821036', 'Nomor Telepon Kantor'),
('kd_wilayah_default', 'KP', 'Kode Wilayah Default (Kota Kupang)'),
('nama_wilayah_default', 'KOTA KUPANG', 'Nama Wilayah Default'),
('opsen_persen', '66.00', 'Tarif Opsen Pajak Kendaraan Air UU HKPD No. 1/2022');

-- 7.2 22 Kabupaten/Kota di Nusa Tenggara Timur
INSERT INTO `ref_kabupaten_kota` (`kode_wilayah`, `nama_kabupaten_kota`, `tipe`, `ibukota`, `pelabuhan_pangkal_default`) VALUES
('KP', 'KOTA KUPANG', 'KOTA', 'Kota Kupang', 'TENAU KUPANG'),
('KU', 'KABUPATEN KUPANG', 'KABUPATEN', 'Oelamasi', 'BOLOK KUPANG'),
('MB', 'KABUPATEN MANGGARAI BARAT', 'KABUPATEN', 'Labuan Bajo', 'LABUAN BAJO'),
('FL', 'KABUPATEN FLORES TIMUR', 'KABUPATEN', 'Larantuka', 'LARANTUKA'),
('SK', 'KABUPATEN SIKKA', 'KABUPATEN', 'Maumere', 'LORENS SAY MAUMERE'),
('AL', 'KABUPATEN ALOR', 'KABUPATEN', 'Kalabahi', 'KALABAHI'),
('RD', 'KABUPATEN ROTE NDAO', 'KABUPATEN', 'Ba\'a', 'BA\'A ROTE'),
('LM', 'KABUPATEN LEMBATA', 'KABUPATEN', 'Lewoleba', 'LEWOLEBA'),
('EN', 'KABUPATEN ENDE', 'KABUPATEN', 'Ende', 'IPI ENDE'),
('NG', 'KABUPATEN NGADA', 'KABUPATEN', 'Bajawa', 'AIMERE'),
('NA', 'KABUPATEN NAGEKEO', 'KABUPATEN', 'Mbay', 'MARAPOKOT'),
('MG', 'KABUPATEN MANGGARAI', 'KABUPATEN', 'Ruteng', 'REO'),
('MT', 'KABUPATEN MANGGARAI TIMUR', 'KABUPATEN', 'Borong', 'BORONG'),
('BL', 'KABUPATEN BELU', 'KABUPATEN', 'Atambua', 'TELUK GURITA ATAMBUA'),
('ML', 'KABUPATEN MALAKA', 'KABUPATEN', 'Betun', 'MOTAMASIN'),
('TT', 'KABUPATEN TIMOR TENGAH UTARA', 'KABUPATEN', 'Kefamenanu', 'WINI'),
('TS', 'KABUPATEN TIMOR TENGAH SELATAN', 'KABUPATEN', 'Soe', 'KOLBANO'),
('SB', 'KABUPATEN SUMBA BARAT', 'KABUPATEN', 'Waikabubak', 'WAIKELO'),
('SW', 'KABUPATEN SUMBA BARAT DAYA', 'KABUPATEN', 'Tambolaka', 'KAROSO'),
('ST', 'KABUPATEN SUMBA TENGAH', 'KABUPATEN', 'Waibakul', 'WAIBAKUL'),
('SD', 'KABUPATEN SUMBA TIMUR', 'KABUPATEN', 'Waingapu', 'WAINGAPU'),
('SR', 'KABUPATEN SABU RAIJUA', 'KABUPATEN', 'Seba', 'SEBA SABU');

-- 7.3 Jenis Kendaraan Air
INSERT INTO `ref_jenis_kendaraan` (`kode_jenis`, `nama_jenis`, `keterangan`) VALUES
('01', 'SPEEDBOAT / PERAHU MOTOR CEPAT', 'Perahu cepat berbahan fiber/kayu bermesin tempel outboard atau inboard'),
('02', 'KAPAL MOTOR PENUMPANG (KMP)', 'Kapal motor pengangkut penumpang umum antarpulau'),
('03', 'KAPAL PENANGKAP IKAN / NELAYAN', 'Kapal perikanan laut dan penangkap ikan komersial berbobot 5 - 30 GT'),
('04', 'KAPAL WISATA BAHARI / PHINISI', 'Kapal pesiar, phinisi wisata, dive boat di perairan NTT'),
('05', 'KAPAL TUG BOAT / TUNDA', 'Kapal tunda penarik tongkang dan pemandu kepelabuhanan'),
('06', 'TONGKANG BERMOTOR / BARGE', 'Tongkang bermesin untuk angkutan muatan curah, logistik, pasir'),
('07', 'KAPAL DINAS / PATROLI PEMERINTAH', 'Kapal patroli operasional kedinasan Pemprov NTT');

-- 7.4 Fungsi & Tarif Pajak
INSERT INTO `ref_fungsi_tarif` (`kode_fungsi`, `nama_fungsi`, `kategori_tarif`, `tarif_persen`, `keterangan`) VALUES
('1', 'PRIBADI / BADAN USAHA KOMERSIAL', 'PRIBADI_BADAN', 1.20, 'Tarif 1,2% untuk kepemilikan pribadi atau badan usaha non-angkutan umum'),
('2', 'ANGKUTAN UMUM ORANG / BARANG', 'ANGKUTAN_UMUM', 0.50, 'Tarif 0,5% untuk armada yang memiliki izin trayek operasional angkutan umum'),
('3', 'PEMERINTAH / DINAS OPERASIONAL', 'PEMERINTAH', 0.50, 'Tarif 0,5% untuk kapal kedinasan operasional instansi pemerintah / BUMD');

-- 7.5 Standar NJKB Body Kapal
INSERT INTO `ref_njkb_body` (`merek`, `tipe_model`, `tahun_buat`, `panjang_meter`, `nilai_jual`, `keterangan`) VALUES
('Phinisi Nusantara', 'Phinisi Tradisional Kayu Ulin', 2026, 32.00, 1400000000.00, 'Standar Phinisi Wisata Mewah Labuan Bajo'),
('Phinisi Nusantara', 'Phinisi Tradisional Kayu Ulin', 2025, 30.00, 1300000000.00, 'Standar Phinisi Wisata Mewah'),
('Phinisi Nusantara', 'Phinisi Tradisional Kayu Ulin', 2024, 28.00, 1200000000.00, 'Phinisi Standar Wisata Bahari'),
('Phinisi Nusantara', 'Phinisi Tradisional Kayu Ulin', 2022, 26.00, 1050000000.00, 'Phinisi Kayu Ulin Kelas Reguler'),
('Samudra Fiber Craft', 'Speedboat Fiber Glass Deep-V', 2026, 14.50, 480000000.00, 'Body Speedboat 3 Mesin'),
('Samudra Fiber Craft', 'Speedboat Fiber Glass Deep-V', 2025, 14.00, 450000000.00, 'Body Speedboat 3 Mesin'),
('Samudra Fiber Craft', 'Speedboat Fiber Glass Deep-V', 2024, 12.00, 380000000.00, 'Body Speedboat 2 Mesin'),
('Alumindo Marine Craft', 'Aluminium Fast Patrol / Ferry', 2026, 22.00, 2800000000.00, 'Body Aluminium Kapal Cepat Antarpulau'),
('Alumindo Marine Craft', 'Aluminium Fast Patrol / Ferry', 2024, 20.00, 2400000000.00, 'Body Aluminium Fast Ferry'),
('Bajo Woodcraft', 'Kapal Kayu Jati Bahari', 2026, 18.00, 520000000.00, 'Kapal Kayu Wisata & Ikan'),
('Bajo Woodcraft', 'Kapal Kayu Jati Bahari', 2024, 16.00, 420000000.00, 'Kapal Kayu Nelayan & Wisata'),
('Bajo Woodcraft', 'Kapal Kayu Jati Bahari', 2022, 15.00, 350000000.00, 'Kapal Kayu Standar Flores'),
('Larantuka Bahari', 'Kapal Motor Kayu Campur', 2025, 15.00, 380000000.00, 'Kapal Penyeberangan Solor-Adonara'),
('Larantuka Bahari', 'Kapal Motor Kayu Campur', 2023, 14.00, 310000000.00, 'Kapal Penyeberangan Tradisional'),
('Sumba Steel Marine', 'Tongkang Plat Baja Curah', 2025, 45.00, 1500000000.00, 'Tongkang Curah Logistik Sumba'),
('Sumba Steel Marine', 'Tongkang Plat Baja Curah', 2022, 40.00, 1200000000.00, 'Tongkang Curah Pasir & Sapi'),
('Kupang Fiber Marine', 'Speedboat Fiber Komuter', 2026, 10.50, 220000000.00, 'Speedboat Antarpulau Kupang-Semau'),
('Kupang Fiber Marine', 'Speedboat Fiber Komuter', 2024, 10.00, 185000000.00, 'Speedboat Reguler Semau');

-- 7.6 Standar NJKB Mesin Penggerak
INSERT INTO `ref_njkb_mesin` (`merek`, `tipe_tenaga`, `daya_pk`, `tahun_buat`, `nilai_jual`, `keterangan`) VALUES
('Yamaha Marine', 'Outboard 4-Tak V6', 250, 2026, 285000000.00, 'Mesin Tempel Bensin 250 PK'),
('Yamaha Marine', 'Outboard 4-Tak V6', 250, 2025, 260000000.00, 'Mesin Tempel Bensin 250 PK'),
('Yamaha Marine', 'Outboard 4-Tak V6', 250, 2024, 240000000.00, 'Mesin Tempel Bensin 250 PK'),
('Yamaha Marine', 'Outboard 4-Tak V6', 200, 2025, 210000000.00, 'Mesin Tempel Bensin 200 PK'),
('Yamaha Marine', 'Outboard 4-Tak V6', 200, 2023, 185000000.00, 'Mesin Tempel Bensin 200 PK'),
('Yamaha Marine', 'Outboard 2-Tak Enduro', 40, 2026, 52000000.00, 'Mesin Tempel Nelayan 40 PK'),
('Yamaha Marine', 'Outboard 2-Tak Enduro', 40, 2024, 45000000.00, 'Mesin Tempel Nelayan 40 PK'),
('Suzuki Marine', 'Outboard Lean Burn Dual Prop', 300, 2026, 320000000.00, 'Mesin Tempel Cepat 300 PK'),
('Suzuki Marine', 'Outboard 4-Tak', 200, 2025, 195000000.00, 'Mesin Tempel Speedboat 200 PK'),
('Suzuki Marine', 'Outboard 4-Tak', 200, 2023, 170000000.00, 'Mesin Tempel Speedboat 200 PK'),
('Honda Marine', 'Outboard VTEC 4-Tak', 250, 2026, 275000000.00, 'Mesin Tempel Bensin Ramah Lingkungan'),
('Honda Marine', 'Outboard VTEC 4-Tak', 225, 2024, 230000000.00, 'Mesin Tempel Speedboat 225 PK'),
('Yanmar Marine', 'Inboard Marine Diesel 6-Silinder', 450, 2026, 550000000.00, 'Mesin Kapal Motor Cepat Yanmar 450 HP'),
('Yanmar Marine', 'Inboard Marine Diesel 6-Silinder', 450, 2024, 480000000.00, 'Mesin Kapal Motor Yanmar 450 HP'),
('Yanmar Marine', 'Inboard Marine Diesel 6-Silinder', 350, 2025, 410000000.00, 'Mesin Kapal Nelayan & Wisata 350 HP'),
('Yanmar Marine', 'Inboard Marine Diesel 6-Silinder', 350, 2022, 330000000.00, 'Mesin Kapal Wisata 350 HP'),
('Caterpillar', 'Marine Engine CAT C18 Turbo', 715, 2026, 980000000.00, 'Mesin Kapal Tunda & Fast Ferry CAT 715 HP'),
('Caterpillar', 'Marine Engine CAT C18 Turbo', 715, 2023, 850000000.00, 'Mesin Kapal Tunda CAT 715 HP'),
('Cummins Marine', 'Inboard Diesel QSM11', 670, 2026, 880000000.00, 'Mesin Phinisi Mewah & Tunda Cummins 670 HP'),
('Cummins Marine', 'Inboard Diesel QSM11', 670, 2024, 760000000.00, 'Mesin Phinisi Mewah Cummins 670 HP'),
('Mitsubishi Marine', 'Inboard Marine Diesel S6B3', 500, 2025, 520000000.00, 'Mesin Kapal Kayu & Ikan Komersial 500 HP'),
('Mitsubishi Marine', 'Inboard Marine Diesel S6B3', 500, 2022, 420000000.00, 'Mesin Kapal Kayu 500 HP'),
('Mercury Marine', 'Outboard Verado Supercharged', 300, 2026, 340000000.00, 'Mesin Tempel Premium Verado 300 PK');

-- 7.7 Standar NJKB Paket 1 Kesatuan
INSERT INTO `ref_njkb_gabungan` (`merek_paket`, `merek_body`, `merek_mesin`, `tahun_buat`, `kapasitas_gt`, `nilai_jual`, `keterangan`) VALUES
('KM Express Antarpulau Paket Yanmar 450HP', 'Alumindo Fast Ferry', 'Yanmar Marine 450HP', 2026, 65.00, 4800000000.00, 'Paket Kapal Cepat Antarpulau 1 Kesatuan'),
('KM Express Antarpulau Paket Yanmar 450HP', 'Alumindo Fast Ferry', 'Yanmar Marine 450HP', 2024, 60.00, 4200000000.00, 'Paket Kapal Cepat Antarpulau 1 Kesatuan'),
('Speedboat Ti\'i Langga Paket Suzuki 200HP', 'Kupang Fiber Marine', 'Suzuki Marine 200HP', 2026, 12.00, 360000000.00, 'Paket Speedboat Wisata Rote Ndao 1 Kesatuan'),
('Speedboat Ti\'i Langga Paket Suzuki 200HP', 'Kupang Fiber Marine', 'Suzuki Marine 200HP', 2024, 10.00, 320000000.00, 'Paket Speedboat Wisata Rote Ndao 1 Kesatuan'),
('KM Maumere Jaya Ikan Paket Mitsubishi 500HP', 'Bajo Woodcraft', 'Mitsubishi Marine 500HP', 2025, 29.00, 850000000.00, 'Paket Kapal Tangkap Ikan Komersial 1 Kesatuan'),
('KM Maumere Jaya Ikan Paket Mitsubishi 500HP', 'Bajo Woodcraft', 'Mitsubishi Marine 500HP', 2023, 28.00, 750000000.00, 'Paket Kapal Ikan Komersial 1 Kesatuan'),
('Tongkang Sumba Indah Paket CAT C18', 'Sumba Steel Marine', 'Caterpillar CAT C18', 2025, 120.00, 2400000000.00, 'Paket Tongkang Bermotor Muatan Sumba Timur'),
('Tongkang Sumba Indah Paket CAT C18', 'Sumba Steel Marine', 'Caterpillar CAT C18', 2022, 110.00, 2200000000.00, 'Paket Tongkang Bermotor Muatan Sumba Timur'),
('Phinisi Pearl Explorer Paket Cummins 670HP', 'Phinisi Nusantara', 'Cummins Marine QSM11', 2026, 35.00, 2400000000.00, 'Paket Phinisi Pesiar Labuan Bajo 1 Kesatuan'),
('Phinisi Pearl Explorer Paket Cummins 670HP', 'Phinisi Nusantara', 'Cummins Marine QSM11', 2024, 32.00, 2100000000.00, 'Paket Phinisi Pesiar Labuan Bajo 1 Kesatuan');

-- 7.8 Pejabat Pengesah SKPD & Laporan
INSERT INTO `pejabat` (`id`, `tipe_pejabat`, `nama_pejabat`, `nip`, `pangkat_gol`, `jabatan`, `is_active`) VALUES
(1, 'SKPD', 'Drs. ALEXON LUMBANTOBING, M.Si', '19740512 199803 1 004', 'Pembina Tk. I (IV/b)', 'Kepala Bidang Pajak Daerah BPAD Provinsi NTT', 1),
(2, 'SKPD', 'MARIA YULIANA KLAU, SE, MM', '19810820 200604 2 011', 'Pembina (IV/a)', 'Kepala UPTD Pendapatan Daerah Wilayah Kota Kupang', 1),
(3, 'LAPORAN', 'Dr. YOHANES ADRIANUS, SH, MH', '19691104 199503 1 002', 'Pembina Utama Muda (IV/c)', 'Kepala Badan Pendapatan dan Aset Daerah Prov. NTT', 1),
(4, 'LAPORAN', 'FRANSISKUS XAVERIUS DETHAN, S.Sos', '19780215 200212 1 006', 'Penata Tk. I (III/d)', 'Kepala Seksi Penetapan dan Penagihan Pajak Air', 1);

-- 7.9 Pengguna Sistem (Default: admin123, operator123, kasir123)
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `nip`, `role`, `is_active`) VALUES
(1, 'admin', '$2y$10$j97j7lvxoPWWfMJmEuoeXeFtgS8xxeWKuCPcmVBSXObpb9.mxPnfu', 'Petugas Administrator Induk NTT', '19880415 201101 1 003', 'ADMIN', 1),
(2, 'operator', '$2y$10$KS3sifOlm1tGKE1PLaI.r.mnH/xk9PTFflSFPHt47EgTbWU1Zqr7S', 'Petugas Pelayanan Pendaftaran KAA', '19920722 201602 1 005', 'OPERATOR', 1),
(3, 'kasir', '$2y$10$UL5qEF7uBBI.Po05.SgWJuT1iCq7Z15FLd7mYjUllI2U8eb1crOgC', 'Kasir Bank NTT & Kasda Wilayah', '19941103 201803 2 001', 'KASIR', 1);

-- 7.10 Data Sampel Kendaraan Air di NTT
INSERT INTO `kendaraan_air` (
  `no_objek_pajak`, `kode_kabkota`, `no_ijin_operasi`, `tanda_selar`, `nama_kapal`, `bahan_kapal`,
  `nama_pemilik`, `alamat_pemilik`, `no_hp`, `kode_jenis`, `kode_fungsi`,
  `jumlah_mesin`, `metode_njkb`,
  `merek_body`, `tahun_body`, `nj_body_kapal`,
  `merek_mesin`, `tahun_mesin_utama`, `total_njkb_mesin`,
  `merek_gabungan`, `njkb_gabungan`, `total_njkb`,
  `tahun_buatan`, `kapasitas_gt`, `pelabuhan_pangkal`,
  `tg_akhir_pajak`, `status_pajak`, `status_verifikasi`, `didaftarkan_oleh`
) VALUES
('KA 0001 MB', 'MB', '503/088/DISHUB-MABAR/2025', 'GT.28 No. 112/KKa', 'SB. KOMODO PEARL EXPLORER', 'FIBER', 'PT. PESONA FLORES MARITIM', 'Jl. Soekarno Hatta No. 45, Kampung Ujung, Labuan Bajo', '081234567890', '04', '2', 3, 'TERPISAH', 'Phinisi Nusantara', 2022, 1200000000.00, 'Yamaha Marine', 2022, 750000000.00, NULL, 0.00, 1950000000.00, 2022, 28.00, 'LABUAN BAJO (KABUPATEN MANGGARAI BARAT)', '2027-10-23', 'LUNAS', 'VERIFIKASI', 1),
('KA 0002 KP', 'KP', '503/102/DISHUB-KPG/2024', 'GT.65 No. 418/PKa', 'KM. BAHARI EXPRESS NTT 01', 'ALUMINIUM', 'PT. PELAYARAN NASIONAL BAHARI NTT', 'Jl. Timor Raya No. 102, Pasir Panjang, Kota Kupang', '081398765432', '02', '2', 2, 'GABUNGAN', NULL, NULL, 0.00, NULL, NULL, 0.00, 'KM Express Antarpulau Paket Yanmar 450HP', 4800000000.00, 4800000000.00, 2024, 65.00, 'TENAU KUPANG (KOTA KUPANG)', '2027-06-15', 'LUNAS', 'VERIFIKASI', 1),
('KA 0003 FL', 'FL', '503/044/DISHUB-FLOTIM/2025', 'GT.15 No. 091/KKa', 'KM. ILE APE EXPRESS', 'KAYU', 'H. ISMAIL ABDULLAH', 'Kelurahan Ekasapta, Larantuka, Flores Timur', '082145678912', '02', '2', 1, 'TERPISAH', 'Larantuka Bahari', 2023, 380000000.00, 'Yanmar Marine', 2023, 180000000.00, NULL, 0.00, 560000000.00, 2023, 15.00, 'LARANTUKA (KABUPATEN FLORES TIMUR)', '2027-04-10', 'TERBIT_SUPER', 'VERIFIKASI', 1),
('KA 0004 AL', 'AL', '503/018/DISHUB-ALOR/2025', 'GT.12 No. 044/KKa', 'KM. PANTAR INDAH', 'KAYU', 'MARKUS KAFOLABANG', 'Jl. Jenderal Sudirman No. 12, Kalabahi, Alor', '085239018273', '02', '2', 1, 'TERPISAH', 'Bajo Woodcraft', 2022, 185000000.00, 'Yamaha Marine', 2024, 80000000.00, NULL, 0.00, 265000000.00, 2022, 12.00, 'KALABAHI (KABUPATEN ALOR)', '2027-08-20', 'TERDAFTAR', 'VERIFIKASI', 1),
('KA 0005 SK', 'SK', '503/055/DISHUB-SIKKA/2024', 'GT.29 No. 205/PKa', 'KM. MAUMERE JAYA', 'KAYU', 'CV. FLORES TANGGUH SEJAHTERA', 'Pelabuhan Wuring No. 8, Alok Barat, Maumere', '081239847162', '03', '1', 1, 'GABUNGAN', NULL, NULL, 0.00, NULL, NULL, 0.00, 'KM Maumere Jaya Ikan Paket Mitsubishi 500HP', 750000000.00, 750000000.00, 2023, 29.00, 'LORENS SAY MAUMERE (KABUPATEN SIKKA)', '2026-12-30', 'TERDAFTAR', 'VERIFIKASI', 1),
('KA 0006 RD', 'RD', '503/012/DISHUB-ROTE/2025', 'GT.10 No. 032/KKa', 'SPEEDBOAT TI\'I LANGGA', 'FIBER', 'YOHANIS NDOEN', 'Pantai Nemberala, Rote Barat, Rote Ndao', '081338271625', '01', '2', 1, 'GABUNGAN', NULL, NULL, 0.00, NULL, NULL, 0.00, 'Speedboat Ti\'i Langga Paket Suzuki 200HP', 320000000.00, 320000000.00, 2024, 10.00, 'BA\'A ROTE (KABUPATEN ROTE NDAO)', '2027-05-18', 'TERDAFTAR', 'VERIFIKASI', 1),
('KA 0007 EN', 'EN', '503/077/DISHUB-ENDE/2025', 'GT.24 No. 167/KKa', 'KM. KELIMUTU BAHARI', 'KAYU', 'PT. ENDE TRANS MARITIM', 'Jl. Ipi No. 22, Ende Selatan, Ende', '081238910293', '06', '1', 2, 'TERPISAH', 'Bajo Woodcraft', 2024, 520000000.00, 'Yanmar Marine', 2023, 300000000.00, NULL, 0.00, 820000000.00, 2023, 24.00, 'IPI ENDE (KABUPATEN ENDE)', '2027-07-25', 'TERDAFTAR', 'VERIFIKASI', 1),
('KA 0008 LM', 'LM', '503/029/DISHUB-LMB/2025', 'GT.18 No. 088/KKa', 'KM. LEWOLEBA JAYA', 'KAYU', 'LORENS MANUK', 'Kecamatan Nubatukan, Lewoleba, Lembata', '082146718293', '06', '2', 1, 'TERPISAH', 'Larantuka Bahari', 2023, 240000000.00, 'Yamaha Marine', 2023, 150000000.00, NULL, 0.00, 390000000.00, 2023, 18.00, 'LEWOLEBA (KABUPATEN LEMBATA)', '2026-11-20', 'TERDAFTAR', 'VERIFIKASI', 1),
('KA 0009 KP', 'KP', 'DINAS-NTT/PATROLI-01/2024', 'GT.22 No. 001/DINAS', 'KM. SASANDO PRAJA NTT', 'FIBER', 'DINAS KELAUTAN DAN PERIKANAN PROV. NTT', 'Jl. Polisi Militer No. 3, Oebobo, Kota Kupang', '0380821035', '07', '3', 2, 'TERPISAH', 'Samudra Fiber Craft', 2024, 450000000.00, 'Suzuki Marine', 2024, 400000000.00, NULL, 0.00, 850000000.00, 2024, 22.00, 'TENAU KUPANG (KOTA KUPANG)', '2027-09-01', 'LUNAS', 'VERIFIKASI', 1),
('KA 0010 SD', 'SD', '503/033/DISHUB-SUMTIM/2024', 'GT.110 No. 512/PKa', 'TK. SUMBA INDAH 08', 'BAJA', 'PT. SUMBA LOGISTIK NUSANTARA', 'Jl. Pelabuhan Waingapu No. 15, Sumba Timur', '08113829102', '06', '1', 1, 'GABUNGAN', NULL, NULL, 0.00, NULL, NULL, 0.00, 'Tongkang Sumba Indah Paket CAT C18', 2200000000.00, 2200000000.00, 2022, 110.00, 'WAINGAPU (KABUPATEN SUMBA TIMUR)', '2026-10-15', 'TERDAFTAR', 'VERIFIKASI', 1);

-- 7.11 Rincian Mesin Kendaraan Air
INSERT INTO `mesin_kendaraan` (`no_objek_pajak`, `mesin_ke`, `merek_mesin`, `nomor_mesin`, `daya_pk`, `tahun_mesin`, `nilai_jual_mesin`) VALUES
('KA 0001 MB', 1, 'Yamaha Marine', 'YMH-250-89102', 250, 2022, 250000000.00),
('KA 0001 MB', 2, 'Yamaha Marine', 'YMH-250-89103', 250, 2022, 250000000.00),
('KA 0001 MB', 3, 'Yamaha Marine', 'YMH-250-89104', 250, 2022, 250000000.00),
('KA 0003 FL', 1, 'Yanmar Marine', 'YNR-350-44120', 350, 2023, 180000000.00),
('KA 0004 AL', 1, 'Yamaha Marine', 'YMH-40-10928', 40, 2024, 80000000.00),
('KA 0007 EN', 1, 'Yanmar Marine', 'YNR-350-55101', 350, 2023, 150000000.00),
('KA 0007 EN', 2, 'Yanmar Marine', 'YNR-350-55102', 350, 2023, 150000000.00),
('KA 0008 LM', 1, 'Yamaha Marine', 'YMH-200-33190', 200, 2023, 150000000.00),
('KA 0009 KP', 1, 'Suzuki Marine', 'SZK-200-11201', 200, 2024, 200000000.00),
('KA 0009 KP', 2, 'Suzuki Marine', 'SZK-200-11202', 200, 2024, 200000000.00);

-- 7.12 Transaksi SUPERPKAA Awal
INSERT INTO `transaksi_superpkaa` (
  `no_superpkaa`, `no_objek_pajak`, `tgl_terbit`, `masa_pajak_awal`, `masa_pajak_akhir`,
  `njkb_dasar`, `tarif_pokok_persen`, `bea_pkaa_pokok`, `bea_pkaa_denda`,
  `opsen_persen`, `opsen_pkaa_pokok`, `opsen_pkaa_denda`, `total_ketetapan`,
  `status`, `status_cetak`, `tgl_cetak_pertama`, `id_petugas`
) VALUES
('SUPER-2026-0001', 'KA 0001 MB', '2026-10-23', '2026-10-23', '2027-10-23', 1950000000.00, 0.50, 9750000.00, 0.00, 66.00, 6435000.00, 0.00, 16185000.00, 'LUNAS', 1, '2026-10-23 10:15:00', 1),
('SUPER-2026-0002', 'KA 0002 KP', '2026-06-15', '2026-06-15', '2027-06-15', 4800000000.00, 0.50, 24000000.00, 0.00, 66.00, 15840000.00, 0.00, 39840000.00, 'LUNAS', 1, '2026-06-15 09:30:00', 1),
('SUPER-2026-0003', 'KA 0003 FL', '2026-10-23', '2026-10-23', '2027-10-23', 560000000.00, 0.50, 2800000.00, 0.00, 66.00, 1848000.00, 0.00, 4648000.00, 'TERBIT', 0, NULL, 1);

-- 7.13 Transaksi SKPD Awal (Sudah Bayar Kasda)
INSERT INTO `transaksi_skpd` (
  `no_skpd`, `no_superpkaa`, `no_objek_pajak`, `tgl_bayar`, `metode_bayar`,
  `no_resi_bukti`, `bea_pkaa_pokok_bayar`, `bea_pkaa_denda_bayar`,
  `opsen_pkaa_pokok_bayar`, `opsen_pkaa_denda_bayar`, `total_bayar`,
  `pejabat_skpd_id`, `id_petugas_kasir`
) VALUES
('SKPD-2026-0001', 'SUPER-2026-0001', 'KA 0001 MB', '2026-10-23', 'BANK NTT', 'NTT-BPD-2026-98124', 9750000.00, 0.00, 6435000.00, 0.00, 16185000.00, 1, 3),
('SKPD-2026-0002', 'SUPER-2026-0002', 'KA 0002 KP', '2026-06-15', 'BANK NTT', 'NTT-BPD-2026-77890', 24000000.00, 0.00, 15840000.00, 0.00, 39840000.00, 1, 3);

-- 7.14 Log Riwayat Cetak SUPERPKAA
INSERT INTO `log_cetak_superpkaa` (
  `no_superpkaa`, `no_objek_pajak`, `nama_kapal`, `nama_pemilik`, `dicetak_oleh_id`, `dicetak_oleh_nama`, `tgl_cetak`, `keterangan`
) VALUES
('SUPER-2026-0001', 'KA 0001 MB', 'SB. KOMODO PEARL EXPLORER', 'PT. PESONA FLORES MARITIM', 1, 'Petugas Administrator Induk NTT', '2026-10-23 10:15:00', 'Cetak Pertama Lembar Resmi SUPERPKAA'),
('SUPER-2026-0002', 'KA 0002 KP', 'KM. BAHARI EXPRESS NTT 01', 'PT. PELAYARAN NASIONAL BAHARI NTT', 1, 'Petugas Administrator Induk NTT', '2026-06-15 09:30:00', 'Cetak Pertama Lembar Resmi SUPERPKAA');

-- =============================================================================
-- SELESAI. BASIS DATA JANGKAR NTT SIAP DIGUNAKAN DI SELURUH KOMPUTER / SERVER
-- =============================================================================
