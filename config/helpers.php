<?php
// config/helpers.php - Fungsi Bantuan SIMPAB PKAA Tarakan

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

/**
 * Format angka ke format mata uang Rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

/**
 * Format angka desimal standar ribuan
 */
function formatRibuan($angka) {
    return number_format((float)$angka, 0, ',', '.');
}

/**
 * Konversi tanggal MySQL (YYYY-MM-DD) ke format Indonesia (e.g. 23 Oktober 2026 atau 23-10-2026)
 */
function formatTanggalIndo($tanggal, $lengkap = true) {
    if (empty($tanggal) || $tanggal === '0000-00-00') {
        return '-';
    }
    
    $bulanNama = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $time = strtotime($tanggal);
    if (!$time) return $tanggal;
    
    $d = date('d', $time);
    $m = (int)date('m', $time);
    $y = date('Y', $time);
    
    if ($lengkap) {
        return $d . ' ' . $bulanNama[$m] . ' ' . $y;
    } else {
        return date('d-m-Y', $time);
    }
}

/**
 * Konversi angka ke kalimat terbilang Rupiah untuk kuitansi resmi
 */
function terbilang($angka) {
    $angka = abs((float)$angka);
    $baca = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    $terbilang = "";
    
    if ($angka < 12) {
        $terbilang = " " . $baca[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . " Belas";
    } elseif ($angka < 100) {
        $terbilang = terbilang(floor($angka / 10)) . " Puluh" . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = " Seratus" . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang(floor($angka / 100)) . " Ratus" . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = " Seribu" . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang(floor($angka / 1000)) . " Ribu" . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang(floor($angka / 1000000)) . " Juta" . terbilang($angka % 1000000);
    } elseif ($angka < 1000000000000) {
        $terbilang = terbilang(floor($angka / 1000000000)) . " Milyar" . terbilang(fmod($angka, 1000000000));
    } elseif ($angka < 1000000000000000) {
        $terbilang = terbilang(floor($angka / 1000000000000)) . " Triliun" . terbilang(fmod($angka, 1000000000000));
    }
    
    return trim($terbilang);
}

/**
 * Mengambil parameter dari database `ref_pengaturan_sistem`
 */
function getSetting($pdo, $parameter, $default = '') {
    $stmt = $pdo->prepare("SELECT nilai FROM ref_pengaturan_sistem WHERE parameter = ? LIMIT 1");
    $stmt->execute([$parameter]);
    $row = $stmt->fetch();
    return $row ? $row['nilai'] : $default;
}

/**
 * Menghasilkan NOPD berikutnya beserta bagian terpisahnya:
 * prefix (KA), sequence (0001), region (KP/MB/dll.), dan full string
 */
function generateNextNOPDParts($pdo, $kodeKabKota = 'KP') {
    $kodeKabKota = strtoupper(trim($kodeKabKota ?: 'KP'));
    
    // Ambil seluruh NOPD untuk wilayah ini untuk menemukan angka urut tertinggi
    $stmt = $pdo->prepare("
        SELECT no_objek_pajak 
        FROM kendaraan_air 
        WHERE kode_kabkota = ? OR no_objek_pajak LIKE ?
    ");
    $stmt->execute([$kodeKabKota, "% $kodeKabKota"]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $maxSeq = 0;
    foreach ($rows as $nopd) {
        if (preg_match('/KA\s+(\d{1,5})\s+' . preg_quote($kodeKabKota, '/') . '/i', $nopd, $matches)) {
            $seq = (int)$matches[1];
            if ($seq > $maxSeq) {
                $maxSeq = $seq;
            }
        }
    }
    
    $nextSeq = $maxSeq + 1;
    $seqStr = sprintf('%04d', $nextSeq);
    
    return [
        'prefix'   => 'KA',
        'sequence' => $seqStr,
        'region'   => $kodeKabKota,
        'full'     => sprintf('KA %04d %s', $nextSeq, $kodeKabKota)
    ];
}

/**
 * Menghasilkan NOPD berikutnya string lengkap: KA 0001 XX
 */
function generateNextNOPD($pdo, $kodeKabKota = 'KP') {
    $parts = generateNextNOPDParts($pdo, $kodeKabKota);
    return $parts['full'];
}

/**
 * Pre-generate pemetaan sequence NOPD berikutnya untuk seluruh 22 Kab/Kota di NTT
 */
function getAllNextNOPDMap($pdo) {
    $kabList = getKabKotaList($pdo);
    $map = [];
    foreach ($kabList as $k) {
        $map[$k['kode_wilayah']] = generateNextNOPDParts($pdo, $k['kode_wilayah']);
    }
    return $map;
}

/**
 * Flash message untuk modern toast
 */
function setFlash($type, $title, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_toast'] = [
        'type'    => $type, // success, warning, error, info
        'title'   => $title,
        'message' => $message,
        'time'    => time()
    ];
}

function getFlash() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash_toast'])) {
        $toast = $_SESSION['flash_toast'];
        unset($_SESSION['flash_toast']);
        return $toast;
    }
    return null;
}

/**
 * Pengalihan halaman yang aman (Buffer-aware & fallback script)
 */
function safeRedirect($url) {
    if (!headers_sent()) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header("Location: " . $url);
        exit;
    } else {
        echo "<script>window.location.replace(" . json_encode($url) . ");</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
        exit;
    }
}

/**
 * Pencatatan Riwayat Cetak SUPERPKAA
 */
function logCetakSuperPKAA($pdo, $noSuper, $noObjekPajak, $namaKapal, $namaPemilik, $userId = null, $userName = null, $keterangan = 'Cetak Lembar Resmi SUPERPKAA') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("
            INSERT INTO log_cetak_superpkaa 
            (no_superpkaa, no_objek_pajak, nama_kapal, nama_pemilik, dicetak_oleh_id, dicetak_oleh_nama, ip_address, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $noSuper,
            $noObjekPajak,
            $namaKapal,
            $namaPemilik,
            $userId ?: 1,
            $userName ?: 'Petugas Cetak',
            $ip,
            $keterangan
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mengambil daftar 22 Kabupaten/Kota di NTT
 */
function getKabKotaList($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM ref_kabupaten_kota ORDER BY tipe ASC, nama_kabupaten_kota ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}


/**
 * Menghasilkan Nomor SUPERPKAA berikutnya
 */
function generateNextSUPER($pdo) {
    $year = date('Y');
    $month = date('m');
    $prefix = "SUPER/PKAA/$year/$month/";
    $stmt = $pdo->prepare("SELECT no_superpkaa FROM transaksi_superpkaa WHERE no_superpkaa LIKE ? ORDER BY no_superpkaa DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $row = $stmt->fetch();
    
    if ($row) {
        $lastSeq = (int)substr($row['no_superpkaa'], -4);
        $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '0001';
    }
    return $prefix . $nextSeq;
}

/**
 * Menghasilkan Nomor SKPD berikutnya
 */
function generateNextSKPD($pdo) {
    $year = date('Y');
    $prefix = "SKPD/PKAA/$year/";
    $stmt = $pdo->prepare("SELECT no_skpd FROM transaksi_skpd WHERE no_skpd LIKE ? ORDER BY no_skpd DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $row = $stmt->fetch();
    
    if ($row) {
        $lastSeq = (int)substr($row['no_skpd'], -6);
        $nextSeq = str_pad($lastSeq + 1, 6, '0', STR_PAD_LEFT);
    } else {
        $nextSeq = '000055';
    }
    return $prefix . $nextSeq;
}

/**
 * Kalkulasi Perpajakan PKAA, Opsen 66%, dan Denda Keterlambatan
 */
function kalkulasiPajakPKAA($pdo, $totalNJKB, $kodeFungsi, $tgAkhirPajak = null, $tglBayar = null) {
    if (!$tglBayar) {
        $tglBayar = date('Y-m-d');
    }
    
    // Ambil tarif pokok berdasarkan fungsi
    $stmt = $pdo->prepare("SELECT tarif_persen, nama_fungsi, kategori_tarif FROM ref_fungsi_tarif WHERE kode_fungsi = ? LIMIT 1");
    $stmt->execute([$kodeFungsi]);
    $fungsiData = $stmt->fetch();
    $tarifPokokPersen = $fungsiData ? (float)$fungsiData['tarif_persen'] : 0.50;
    
    // Ambil persentase opsen & denda dari database
    $opsenPersen = (float)getSetting($pdo, 'persentase_opsen', '66.00');
    $dendaPerBulan = (float)getSetting($pdo, 'persentase_denda_per_bulan', '1.00');
    $maksBulanDenda = (int)getSetting($pdo, 'maks_bulan_denda', '24');
    
    // Pokok PKAA
    $beaPkaaPokok = round(($totalNJKB * ($tarifPokokPersen / 100)), 0);
    
    // Hitung denda keterlambatan jika tanggal bayar > tanggal akhir pajak
    $bulanTerlambat = 0;
    $beaPkaaDenda = 0;
    $opsenPkaaDenda = 0;
    
    if ($tgAkhirPajak && strtotime($tglBayar) > strtotime($tgAkhirPajak)) {
        $diffYears = (int)date('Y', strtotime($tglBayar)) - (int)date('Y', strtotime($tgAkhirPajak));
        $diffMonths = (int)date('m', strtotime($tglBayar)) - (int)date('m', strtotime($tgAkhirPajak));
        $diffDays = (int)date('d', strtotime($tglBayar)) - (int)date('d', strtotime($tgAkhirPajak));
        
        $totalMonths = ($diffYears * 12) + $diffMonths;
        if ($diffDays > 0) {
            $totalMonths += 1; // pembulatan ke atas jika lewat hari
        }
        $bulanTerlambat = max(1, min($totalMonths, $maksBulanDenda));
        
        $beaPkaaDenda = round($beaPkaaPokok * ($dendaPerBulan * $bulanTerlambat / 100), 0);
    }
    
    // Pokok Opsen PKAA (66% dari pokok PKAA)
    $opsenPkaaPokok = round($beaPkaaPokok * ($opsenPersen / 100), 0);
    
    // Denda Opsen PKAA (66% dari denda PKAA)
    if ($beaPkaaDenda > 0) {
        $opsenPkaaDenda = round($beaPkaaDenda * ($opsenPersen / 100), 0);
    }
    
    $totalKetetapan = ($beaPkaaPokok + $beaPkaaDenda) + ($opsenPkaaPokok + $opsenPkaaDenda);
    
    return [
        'njkb_dasar'         => $totalNJKB,
        'tarif_pokok_persen' => $tarifPokokPersen,
        'bea_pkaa_pokok'     => $beaPkaaPokok,
        'bea_pkaa_denda'     => $beaPkaaDenda,
        'opsen_persen'       => $opsenPersen,
        'opsen_pkaa_pokok'   => $opsenPkaaPokok,
        'opsen_pkaa_denda'   => $opsenPkaaDenda,
        'bulan_terlambat'    => $bulanTerlambat,
        'total_ketetapan'    => $totalKetetapan,
        'nama_fungsi'        => $fungsiData ? $fungsiData['nama_fungsi'] : '',
        'kategori_tarif'     => $fungsiData ? $fungsiData['kategori_tarif'] : ''
    ];
}

/**
 * Render Modern SVG Icon (Lucide compatible, offline zero-dependency)
 */
function renderIcon($name, $class = '', $size = 18) {
    $icons = [
        'ship' => '<path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"/><path d="M12 10v4"/><path d="M12 2v3"/>',
        'anchor' => '<circle cx="12" cy="5" r="3"/><line x1="12" y1="22" x2="12" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/>',
        'waves' => '<path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/>',
        'compass' => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
        'life-buoy' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/><line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/><line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/><line x1="4.93" y1="19.07" x2="9.17" y2="14.83"/>',
        'navigation' => '<polygon points="3 11 22 2 13 21 11 13 3 11"/>',
        'map-pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/><path d="M16 8h-8"/><path d="M16 12h-8"/><path d="M16 16h-4"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'file-check' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="m9 15 2 2 4-4"/>',
        'file-clock' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><circle cx="12" cy="14" r="3"/><polyline points="12 12.5 12 14 13 15"/>',
        'file-edit' => '<path d="M4 13.5V4a2 2 0 0 1 2-2h8.5L20 7.5V20a2 2 0 0 1-2 2h-5.5"/><polyline points="14 2 14 8 20 8"/><path d="M10.42 12.61a2.1 2.1 0 1 1 2.97 2.97L7.95 21 4 22l1-3.95 5.42-5.44Z"/>',
        'file-spreadsheet' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M8 13h2"/><path d="M14 13h2"/><path d="M8 17h2"/><path d="M14 17h2"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'credit-card' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><polyline points="3 3 3 8 8 8"/><polyline points="12 7 12 12 15 15"/>',
        'pie-chart' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
        'landmark' => '<line x1="3" y1="22" x2="21" y2="22"/><line x1="6" y1="18" x2="6" y2="11"/><line x1="10" y1="18" x2="10" y2="11"/><line x1="14" y1="18" x2="14" y2="11"/><line x1="18" y1="18" x2="18" y2="11"/><polygon points="12 2 20 7 4 7"/>',
        'menu' => '<line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="18" x2="20" y2="18"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'cpu' => '<rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'refresh-cw' => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'file' => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'key' => '<path d="m21 2-2 2m-1.5 1.5L14 9l-4-4-6 6a4.24 4.24 0 0 0 6 6l5-5 2 2 2-2 2 2 2-2z"/>'
    ];
    
    $body = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide-icon lucide-'.$name.' '.$class.'">'.$body.'</svg>';
}
