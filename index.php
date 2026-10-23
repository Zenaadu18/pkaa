<?php
// index.php - Router Utama Aplikasi JANGKAR NTT
ob_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/auth.php';

$page = $_GET['page'] ?? 'dashboard';

// Rute Publik (Tanpa Header/Footer Aplikasi Utama)
if ($page === 'login') {
    require_once __DIR__ . '/modules/auth/login.php';
    exit;
}

if ($page === 'logout') {
    require_once __DIR__ . '/modules/auth/logout.php';
    exit;
}

// Seluruh Halaman Selain Login Membutuhkan Autentikasi Sesi
checkAuth();

// Pemetaan Halaman Modul
$moduleMap = [
    'dashboard'              => __DIR__ . '/modules/dashboard/index.php',
    'pendaftaran'            => __DIR__ . '/modules/pelayanan/pendaftaran.php',
    'ajukan_perubahan'       => __DIR__ . '/modules/pelayanan/ajukan_perubahan.php',
    'verifikasi_perubahan'   => __DIR__ . '/modules/pelayanan/verifikasi_perubahan.php',
    'status_pengajuan'       => __DIR__ . '/modules/pelayanan/status_pengajuan.php',
    'superpkaa'              => __DIR__ . '/modules/pelayanan/superpkaa.php',
    'pembayaran_tahunan'     => __DIR__ . '/modules/pelayanan/pembayaran_tahunan.php',
    'cetak_ulang_superpkaa'  => __DIR__ . '/modules/pelayanan/cetak_ulang_superpkaa.php',
    'riwayat_cetak_superpkaa'=> __DIR__ . '/modules/pelayanan/riwayat_cetak_superpkaa.php',
    'skpd'                   => __DIR__ . '/modules/pelayanan/skpd.php',
    'cetak_ulang_skpd'       => __DIR__ . '/modules/pelayanan/cetak_ulang_skpd.php',
    'master_kaa'             => __DIR__ . '/modules/pemeliharaan/master_kaa.php',
    'kabupaten_kota'         => __DIR__ . '/modules/pemeliharaan/kabupaten_kota.php',
    'pejabat_laporan'        => __DIR__ . '/modules/pemeliharaan/pejabat_laporan.php',
    'pejabat_skpd'           => __DIR__ . '/modules/pemeliharaan/pejabat_skpd.php',
    'tarif_pajak'            => __DIR__ . '/modules/pemeliharaan/tarif_pajak.php',
    'tabel_njkb'             => __DIR__ . '/modules/pemeliharaan/tabel_njkb.php',
    'laporan'                => __DIR__ . '/modules/laporan/index.php',
    'cetak_opsen'            => __DIR__ . '/modules/laporan/cetak_opsen.php',
];

$targetModule = $moduleMap[$page] ?? $moduleMap['dashboard'];

// Render Layout Desktop Terpadu
require_once __DIR__ . '/layouts/header.php';
require_once $targetModule;
require_once __DIR__ . '/layouts/footer.php';

if (ob_get_level() > 0) {
    ob_end_flush();
}
