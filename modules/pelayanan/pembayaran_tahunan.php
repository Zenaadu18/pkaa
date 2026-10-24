<?php
// modules/pelayanan/pembayaran_tahunan.php - Pembayaran Pajak Kendaraan di Atas Air (PKAA) Tahunan
if (!defined('APP_INIT')) {
    http_response_code(403);
    die("Akses langsung ke modul ini tidak diizinkan.");
}

$pageTitle = "Pembayaran PKAA Tahunan";
$currentUser = currentUser();
$userRole = $currentUser['role'] ?? 'OPERATOR';

// Validasi otorisasi peran pengguna (Hanya ADMIN, KASIR, dan OPERATOR)
$allowedRoles = ['ADMIN', 'KASIR', 'OPERATOR'];
if (!in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    die("Akses Terbatas: Peran Anda ($userRole) tidak memiliki izin untuk memproses transaksi pembayaran.");
}

// Tangkap input 3 kotak NOPD [xx] [xxxx] [xx] atau input gabungan
$nopd1 = strtoupper(trim($_GET['nopd_1'] ?? ''));
$nopd2 = strtoupper(trim($_GET['nopd_2'] ?? ''));
$nopd3 = strtoupper(trim($_GET['nopd_3'] ?? ''));

if ($nopd1 !== '' || $nopd2 !== '' || $nopd3 !== '') {
    $selectedNopd = trim("$nopd1 $nopd2 $nopd3");
} else {
    $selectedNopd = strtoupper(trim($_GET['nopd'] ?? $_POST['no_objek_pajak'] ?? ''));
}

// Normalisasi dan parsing NOPD ke dalam 3 kotak
$cleanNopd = str_replace(['-', '_', '.'], ' ', $selectedNopd);
$parts = preg_split('/\s+/', trim($cleanNopd));
if (count($parts) === 3) {
    $box1 = strtoupper($parts[0]);
    $box2 = strtoupper($parts[1]);
    $box3 = strtoupper($parts[2]);
    $selectedNopd = "$box1 $box2 $box3";
} elseif (preg_match('/^([A-Z]{2})(\d{1,4})([A-Z]{1,3})$/i', str_replace(' ', '', $selectedNopd), $m)) {
    $box1 = strtoupper($m[1]);
    $box2 = str_pad(strtoupper($m[2]), 4, '0', STR_PAD_LEFT);
    $box3 = strtoupper($m[3]);
    $selectedNopd = "$box1 $box2 $box3";
} else {
    $box1 = strtoupper($parts[0] ?? 'KA');
    $box2 = strtoupper($parts[1] ?? '');
    $box3 = strtoupper($parts[2] ?? '');
}

if ($box1 === '') {
    $box1 = 'KA';
}

$kapal = null;
$mesinList = [];
$perhitungan = null;
$suksesBayar = false;
$noSkpdTerbit = '';
$noSuperTerbit = '';
$isPajakAktif = false;
$hariTersisa = 0;

// Ambil daftar seluruh NOPD untuk datalist & dropdown pencarian cepat
$allNopdList = $pdo->query("SELECT no_objek_pajak, nama_kapal, nama_pemilik, tg_akhir_pajak, status_pajak FROM kendaraan_air ORDER BY no_objek_pajak ASC")->fetchAll();

// Ambil data pejabat SKPD aktif untuk penandatangan
$pejabatSkpdList = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'SKPD' AND is_active = 1 ORDER BY id ASC")->fetchAll();

// PROSES 1: Pencarian Objek Pajak berdasarkan NOPD
if (!empty($selectedNopd) && !empty($box2)) {
    $stmt = $pdo->prepare("
        SELECT k.*, f.nama_fungsi, f.tarif_persen, f.kategori_tarif, j.nama_jenis,
               kb.nama_kabupaten_kota, kb.ibukota
        FROM kendaraan_air k
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        WHERE k.no_objek_pajak = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedNopd]);
    $kapal = $stmt->fetch();

    if ($kapal) {
        // Ambil rincian mesin kapal jika metode TERPISAH
        if ($kapal['metode_njkb'] === 'TERPISAH') {
            $stmtM = $pdo->prepare("SELECT * FROM mesin_kendaraan WHERE no_objek_pajak = ? ORDER BY mesin_ke ASC");
            $stmtM->execute([$selectedNopd]);
            $mesinList = $stmtM->fetchAll();
        }

        // Tentukan status keaktifan pajak
        $todayDate = date('Y-m-d');
        $tglAkhirLama = $kapal['tg_akhir_pajak'];

        if (!empty($tglAkhirLama) && $tglAkhirLama !== '0000-00-00') {
            $todayTs = strtotime($todayDate);
            $expTs = strtotime($tglAkhirLama);
            if ($expTs >= $todayTs) {
                $isPajakAktif = true;
                $hariTersisa = (int)ceil(($expTs - $todayTs) / 86400);
            }
            // Masa Pajak Awal baru: 1 hari setelah masa berakhir pajak sebelumnya
            $masaAwalBaru = date('Y-m-d', strtotime('+1 day', strtotime($tglAkhirLama)));
        } else {
            $masaAwalBaru = date('Y-m-d');
        }

        // Masa Pajak Akhir: 1 tahun dari masa awal baru
        $masaAkhirBaru = date('Y-m-d', strtotime('+1 year', strtotime($masaAwalBaru)));

        // Notifikasi flash jika pajak masih aktif (sanitasi teks untuk mencegah XSS)
        if ($isPajakAktif && empty($_POST['aksi'])) {
            setFlash('info', 'Status Pajak Masih Aktif!', "Pajak untuk kendaraan <strong>" . htmlspecialchars($kapal['nama_kapal'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($selectedNopd, ENT_QUOTES, 'UTF-8') . ")</strong> MASIH AKTIF sampai dengan <strong>" . formatTanggalIndo($tglAkhirLama) . "</strong> (Tersisa <strong>$hariTersisa hari</strong> lagi).");
        }

        // Hitung kewajiban pajak dengan kalkulasi resmi BPAD NTT (Pokok + Denda Keterlambatan jika ada + Opsen 66%)
        $perhitungan = kalkulasiPajakPKAA($pdo, $kapal['total_njkb'], $kapal['kode_fungsi'], $kapal['tg_akhir_pajak'], date('Y-m-d'));
    } else {
        setFlash('warning', 'Objek Pajak Tidak Ditemukan', "Kendaraan dengan NOPD <strong>" . htmlspecialchars($selectedNopd, ENT_QUOTES, 'UTF-8') . "</strong> belum terdaftar dalam basis data sistem JANGKAR NTT.");
    }
}

// PROSES 2: Simpan & Eksekusi Pembayaran PKAA Tahunan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'proses_bayar_tahunan' && $kapal) {
    // 1. Validasi Token Keamanan CSRF
    if (!verifyCsrfToken()) {
        setFlash('error', 'Validasi Keamanan Gagal (CSRF)', 'Sesi atau token keamanan Anda tidak valid / kedaluwarsa. Silakan muat ulang halaman.');
        safeRedirect("index.php?page=pembayaran_tahunan&nopd=" . urlencode($selectedNopd));
    }

    // 2. Validasi Tanggal Pembayaran
    $tglBayar = trim($_POST['tgl_bayar'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglBayar) || strtotime($tglBayar) === false) {
        $tglBayar = date('Y-m-d');
    }

    // 3. Validasi & Rekalkulasi Masa Pajak di Sisi Server (Anti Parameter Tampering)
    $masaAwalInput = trim($_POST['masa_pajak_awal'] ?? $masaAwalBaru);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $masaAwalInput) || strtotime($masaAwalInput) === false) {
        $masaAwalFix = $masaAwalBaru;
    } else {
        $masaAwalFix = $masaAwalInput;
    }
    // Masa akhir selalu dikunci tepat 1 tahun dari masa awal oleh backend sistem
    $masaAkhirFix = date('Y-m-d', strtotime('+1 year', strtotime($masaAwalFix)));

    // 4. Pencegahan Duplikasi Pembayaran / Replay
    if (!empty($kapal['tg_akhir_pajak']) && $kapal['status_pajak'] === 'LUNAS' && strtotime($kapal['tg_akhir_pajak']) >= strtotime($masaAkhirFix)) {
        setFlash('warning', 'Pajak Sudah Lunas', "Kendaraan <strong>" . htmlspecialchars($kapal['nama_kapal'], ENT_QUOTES, 'UTF-8') . "</strong> sudah berstatus LUNAS sampai dengan <strong>" . formatTanggalIndo($kapal['tg_akhir_pajak']) . "</strong>. Transaksi tidak diproses ulang.");
        safeRedirect("index.php?page=pembayaran_tahunan&nopd=" . urlencode($selectedNopd));
    }

    // 5. Validasi Pejabat SKPD
    $pejabatId = (int)($_POST['pejabat_skpd_id'] ?? 0);
    $validPejabatIds = array_column($pejabatSkpdList, 'id');
    if (!in_array($pejabatId, $validPejabatIds)) {
        $pejabatId = $pejabatSkpdList[0]['id'] ?? 1;
    }

    $metodeBayar = trim(strip_tags($_POST['metode_bayar'] ?? 'BANK NTT (Kasda)'));
    $noResi = trim(strip_tags($_POST['no_resi_bukti'] ?? ''));

    // Nilai kalkulasi (dihitung langsung dari sistem fiskal resmi, bukan dari input POST)
    $njkbDasar = (float)$perhitungan['njkb_dasar'];
    $tarifPokok = (float)$perhitungan['tarif_pokok_persen'];
    $beaPokok = (float)$perhitungan['bea_pkaa_pokok'];
    $beaDenda = (float)$perhitungan['bea_pkaa_denda'];
    $opsenPersen = (float)$perhitungan['opsen_persen'];
    $opsenPokok = (float)$perhitungan['opsen_pkaa_pokok'];
    $opsenDenda = (float)$perhitungan['opsen_pkaa_denda'];
    $totalBayar = (float)$perhitungan['total_ketetapan'];

    try {
        $pdo->beginTransaction();

        // 1. Terbitkan Nomor SUPERPKAA Tahunan Baru
        $noSuper = generateNextSUPER($pdo);
        $stmtInsSuper = $pdo->prepare("
            INSERT INTO transaksi_superpkaa (
                no_superpkaa, no_objek_pajak, tgl_terbit, masa_pajak_awal, masa_pajak_akhir,
                njkb_dasar, tarif_pokok_persen, bea_pkaa_pokok, bea_pkaa_denda,
                opsen_persen, opsen_pkaa_pokok, opsen_pkaa_denda, total_ketetapan,
                status, status_cetak, tgl_cetak_pertama, id_petugas
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'LUNAS', 1, NOW(), ?
            )
        ");
        $stmtInsSuper->execute([
            $noSuper, $selectedNopd, $tglBayar, $masaAwalFix, $masaAkhirFix,
            $njkbDasar, $tarifPokok, $beaPokok, $beaDenda,
            $opsenPersen, $opsenPokok, $opsenDenda, $totalBayar,
            $currentUser['id'] ?? 1
        ]);

        // 2. Terbitkan Nomor SKPD Tahunan Baru
        $noSkpd = generateNextSKPD($pdo);
        $stmtInsSkpd = $pdo->prepare("
            INSERT INTO transaksi_skpd (
                no_skpd, no_superpkaa, no_objek_pajak, tgl_bayar, metode_bayar,
                no_resi_bukti, bea_pkaa_pokok_bayar, bea_pkaa_denda_bayar,
                opsen_pkaa_pokok_bayar, opsen_pkaa_denda_bayar, total_bayar,
                pejabat_skpd_id, id_petugas_kasir, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, NOW()
            )
        ");
        $stmtInsSkpd->execute([
            $noSkpd, $noSuper, $selectedNopd, $tglBayar, $metodeBayar,
            $noResi, $beaPokok, $beaDenda,
            $opsenPokok, $opsenDenda, $totalBayar,
            $pejabatId, $currentUser['id'] ?? 1
        ]);

        // 3. Perbarui Masa Pajak Kapal ke Masa Pajak Terbaru & Set Status LUNAS
        $stmtUpdKapal = $pdo->prepare("
            UPDATE kendaraan_air 
            SET tg_akhir_pajak = ?,
                status_pajak = 'LUNAS'
            WHERE no_objek_pajak = ?
        ");
        $stmtUpdKapal->execute([$masaAkhirFix, $selectedNopd]);

        // 4. Catat Log Riwayat Cetak SUPERPKAA
        logCetakSuperPKAA(
            $pdo,
            $noSuper,
            $selectedNopd,
            $kapal['nama_kapal'],
            $kapal['nama_pemilik'],
            $currentUser['id'] ?? 1,
            $currentUser['nama_lengkap'] ?? 'Petugas Loket BPAD',
            'Penerbitan & Pembayaran PKAA Tahunan'
        );

        $pdo->commit();

        $suksesBayar = true;
        $noSkpdTerbit = $noSkpd;
        $noSuperTerbit = $noSuper;

        // Ambil data kapal yang telah terupdate
        $kapal['tg_akhir_pajak'] = $masaAkhirFix;
        $kapal['status_pajak'] = 'LUNAS';

        setFlash('success', 'Pembayaran PKAA Tahunan Berhasil!', "Pajak tahunan untuk kapal <strong>" . htmlspecialchars($kapal['nama_kapal'], ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($selectedNopd, ENT_QUOTES, 'UTF-8') . ")</strong> berhasil dilunasi. Dokumen SKPD No. <strong>" . htmlspecialchars($noSkpd, ENT_QUOTES, 'UTF-8') . "</strong> dan SUPERPKAA No. <strong>" . htmlspecialchars($noSuper, ENT_QUOTES, 'UTF-8') . "</strong> telah diterbitkan.");
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error pembayaran PKAA: " . $e->getMessage());
        setFlash('error', 'Gagal Memproses Pembayaran', 'Terjadi kesalahan sistem saat memproses transaksi pembayaran. Silakan hubungi tim administrator.');
    }
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon" style="background:#e0f2fe; color:#0284c7;">
      <?= renderIcon('credit-card', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Pembayaran Pajak Kendaraan di Atas Air (PKAA) Tahunan</div>
      <div class="page-subtitle">Pengecekan Status Masa Pajak, Penetapan SUPERPKAA & Penerbitan SKPD Terpadu JANGKAR NTT</div>
    </div>
  </div>
  <div style="display:flex; gap:8px;">
    <button type="button" class="btn btn-secondary" onclick="bukaLayarBantu()">
      <?= renderIcon('search', '', 14) ?> Layar Bantu NOPD (Ctrl+B)
    </button>
  </div>
</div>

<!-- 1. Form Pencarian NOPD 3 Kotak [xx] [xxxx] [xx] -->
<div class="panel" style="margin-bottom: 20px; border: 1.5px solid #0284c7; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);">
  <div class="panel-header" style="background: #f0f9ff; border-bottom: 1.5px solid #bae6fd;">
    <div class="panel-title" style="color: #0369a1; font-weight: 700;">
      <?= renderIcon('search', '', 16) ?>
      <span>PENCARIAN NOPD KENDARAAN AIR [xx] [xxxx] [xx] (ENTER UNTUK CEK STATUS)</span>
    </div>
    <span class="badge badge-info">Daftar Ulang Tahunan</span>
  </div>
  <div class="panel-body" style="padding: 20px;">
    <form method="GET" action="index.php" id="formCariNopd" style="display: flex; flex-direction: column; gap: 14px;">
      <input type="hidden" name="page" value="pembayaran_tahunan">
      <input type="hidden" name="nopd" id="input_nopd_gabungan" value="<?= htmlspecialchars($selectedNopd) ?>">
      
      <div>
        <label class="form-label" style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 10px; display: block;">
          Format Nomor Objek Pajak Daerah (NOPD) &bull; 3 Kotak Input <span class="required">*</span>
        </label>
        
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
          <!-- Kotak 1: xx (2 Karakter Jenis) -->
          <div style="text-align: center;">
            <input type="text" name="nopd_1" id="input_nopd_1" maxlength="2"
                   class="form-control" style="width: 78px; height: 50px; font-size: 20px; font-weight: 800; font-family: 'Consolas', 'Courier New', monospace; text-align: center; text-transform: uppercase; letter-spacing: 2px;"
                   placeholder="KA" value="<?= htmlspecialchars($box1) ?>" required>
            <span style="font-size: 11px; color: #64748b; font-weight: 700; display: block; margin-top: 4px;">xx (Jenis)</span>
          </div>

          <span style="font-size: 24px; font-weight: 800; color: #94a3b8; padding-bottom: 18px;">&mdash;</span>

          <!-- Kotak 2: xxxx (4 Digit Angka Nomor Urut) -->
          <div style="text-align: center;">
            <input type="text" name="nopd_2" id="input_nopd_2" maxlength="4"
                   class="form-control" style="width: 130px; height: 50px; font-size: 20px; font-weight: 800; font-family: 'Consolas', 'Courier New', monospace; text-align: center; text-transform: uppercase; letter-spacing: 2px;"
                   placeholder="0001" value="<?= htmlspecialchars($box2) ?>" required autofocus>
            <span style="font-size: 11px; color: #64748b; font-weight: 700; display: block; margin-top: 4px;">xxxx (No. Urut)</span>
          </div>

          <span style="font-size: 24px; font-weight: 800; color: #94a3b8; padding-bottom: 18px;">&mdash;</span>

          <!-- Kotak 3: xx (2 Karakter Kode Wilayah Kab/Kota) -->
          <div style="text-align: center;">
            <input type="text" name="nopd_3" id="input_nopd_3" maxlength="3"
                   class="form-control" style="width: 88px; height: 50px; font-size: 20px; font-weight: 800; font-family: 'Consolas', 'Courier New', monospace; text-align: center; text-transform: uppercase; letter-spacing: 2px;"
                   placeholder="MB" value="<?= htmlspecialchars($box3) ?>" required>
            <span style="font-size: 11px; color: #64748b; font-weight: 700; display: block; margin-top: 4px;">xx (Wilayah)</span>
          </div>

          <!-- Tombol Aksi -->
          <div style="margin-left: 12px; padding-bottom: 18px; display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary" style="height: 50px; padding: 0 24px; font-weight: 700; font-size: 14px; background: #0284c7; border-color: #0369a1; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25);">
              <?= renderIcon('search', '', 18) ?>
              <span>CARI & CEK STATUS (ENTER)</span>
            </button>

            <?php if (!empty($selectedNopd)): ?>
              <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary" style="height: 50px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px;">
                <?= renderIcon('refresh', '', 14) ?> Reset
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Dropdown Pilih Cepat -->
      <div style="display: flex; align-items: center; gap: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 14px;">
        <span style="font-size: 12px; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 6px;">
          <?= renderIcon('ship', '', 14) ?> Atau Pilih Kendaraan Terdaftar:
        </span>
        <select id="select_cepat_kapal" class="form-control" style="font-size: 12.5px; height: 36px; max-width: 580px;" onchange="onSelectCepat(this.value)">
          <option value="">-- Klik untuk memilih NOPD & Nama Kapal --</option>
          <?php foreach ($allNopdList as $opt): ?>
            <?php 
              $isOptAktif = (!empty($opt['tg_akhir_pajak']) && strtotime($opt['tg_akhir_pajak']) >= strtotime(date('Y-m-d')));
              $tagAktif = $isOptAktif ? ' [AKTIF s.d ' . date('d/m/Y', strtotime($opt['tg_akhir_pajak'])) . ']' : ' [JATUH TEMPO ' . date('d/m/Y', strtotime($opt['tg_akhir_pajak'])) . ']';
            ?>
            <option value="<?= htmlspecialchars($opt['no_objek_pajak']) ?>" <?= ($selectedNopd === $opt['no_objek_pajak']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($opt['no_objek_pajak']) ?> &bull; <?= htmlspecialchars($opt['nama_kapal']) ?> (<?= htmlspecialchars($opt['nama_pemilik']) ?>) <?= $tagAktif ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
        💡 <em>Petunjuk: Masukkan 3 bagian NOPD (contoh: <code>KA</code> - <code>0001</code> - <code>MB</code>). Kursor akan berpindah otomatis antar kotak, dan menekan <strong>ENTER</strong> di kotak manapun akan langsung memeriksa status masa pajak dan memuat seluruh data. Anda juga dapat menempelkan (Paste) nomor lengkap.</em>
      </div>
    </form>
  </div>
</div>

<!-- 2. TAMPILAN JIKA PEMBAYARAN TAHUNAN BARU SAJA BERHASIL DIPROSES -->
<?php if ($suksesBayar && !empty($noSkpdTerbit)): ?>
  <div class="panel" style="border: 2px solid #10b981; background: #f0fdf4; margin-bottom: 24px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.15);">
    <div class="panel-body" style="padding: 24px; text-align: center;">
      <div style="width: 56px; height: 56px; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px auto;">
        <?= renderIcon('check-circle', '', 32) ?>
      </div>
      <h2 style="color: #065f46; margin: 0 0 6px 0; font-size: 20px; font-weight: 800;">
        PEMBAYARAN PKAA TAHUNAN BERHASIL DISAHKAN!
      </h2>
      <p style="color: #047857; margin: 0 0 16px 0; font-size: 13.5px;">
        Pajak tahunan kendaraan <strong><?= htmlspecialchars($kapal['nama_kapal']) ?> (<?= htmlspecialchars($selectedNopd) ?>)</strong> telah lunas.<br>
        Masa pajak telah diperpanjang sampai dengan <strong><?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?></strong>.
      </p>

      <div style="background: #fff; border: 1px solid #86efac; border-radius: 8px; padding: 12px 16px; display: inline-flex; gap: 24px; margin-bottom: 20px;">
        <div>
          <span style="font-size: 11px; color: #64748b; display: block;">Nomor SKPD Sah</span>
          <strong style="font-size: 15px; color: #0284c7;"><?= htmlspecialchars($noSkpdTerbit) ?></strong>
        </div>
        <div style="border-left: 1px solid #cbd5e1; padding-left: 24px;">
          <span style="font-size: 11px; color: #64748b; display: block;">Nomor SUPERPKAA Baru</span>
          <strong style="font-size: 15px; color: #059669;"><?= htmlspecialchars($noSuperTerbit) ?></strong>
        </div>
        <div style="border-left: 1px solid #cbd5e1; padding-left: 24px;">
          <span style="font-size: 11px; color: #64748b; display: block;">Total Pembayaran Lunas</span>
          <strong style="font-size: 15px; color: #b45309;"><?= formatRupiah($perhitungan['total_ketetapan']) ?></strong>
        </div>
      </div>

      <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
        <a href="cetak/skpd_print.php?no=<?= urlencode($noSkpdTerbit) ?>" target="_blank" class="btn btn-primary" style="background:#0284c7; border-color:#0369a1; padding: 10px 20px; font-weight:700;">
          <?= renderIcon('receipt', '', 16) ?> CETAK SKPD TAHUNAN (LANDSCAPE)
        </a>
        <a href="cetak/superpkaa_print.php?no=<?= urlencode($noSuperTerbit) ?>" target="_blank" class="btn btn-secondary" style="padding: 10px 20px; font-weight:700;">
          <?= renderIcon('file-text', '', 16) ?> CETAK SUPERPKAA TAHUNAN
        </a>
        <a href="cetak/opsen_print.php?no=<?= urlencode($noSkpdTerbit) ?>" target="_blank" class="btn btn-secondary" style="padding: 10px 18px; font-weight:700;">
          <?= renderIcon('pie-chart', '', 16) ?> CETAK BUKTI OPSEN 66%
        </a>
        <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary" style="background:#f1f5f9; padding: 10px 18px;">
          <?= renderIcon('plus', '', 14) ?> Bayar Kendaraan Lainnya
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- 3. TAMPILAN RINCIAN SELURUH DATA KAPAL, MASA PKAA, & FORM PEMBAYARAN -->
<?php if ($kapal && !$suksesBayar): ?>

  <!-- NOTIFIKASI KHUSUS STATUS MASA PAJAK -->
  <?php if ($isPajakAktif): ?>
    <div class="panel" style="border: 2px solid #0284c7; background: #f0f9ff; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.12);">
      <div class="panel-body" style="padding: 18px 22px; display: flex; align-items: flex-start; gap: 16px;">
        <div style="width: 46px; height: 46px; border-radius: 50%; background: #0284c7; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 8px rgba(2, 132, 199, 0.3);">
          <?= renderIcon('check-circle', '', 26) ?>
        </div>
        <div style="flex: 1;">
          <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap;">
            <h3 style="margin: 0; color: #0369a1; font-size: 16px; font-weight: 800;">
              NOTIFIKASI: PAJAK KENDARAAN AIR INI MASIH AKTIF!
            </h3>
            <span class="badge badge-success" style="font-size: 11px; padding: 3px 8px;">
              STATUS: AKTIF (<?= $hariTersisa ?> Hari Tersisa)
            </span>
          </div>
          <p style="margin: 0 0 10px 0; color: #0c4a6e; font-size: 13.5px; line-height: 1.5;">
            Ketetapan pajak untuk kapal <strong><?= htmlspecialchars($kapal['nama_kapal']) ?> (<?= htmlspecialchars($kapal['no_objek_pajak']) ?>)</strong> atas nama <strong><?= htmlspecialchars($kapal['nama_pemilik']) ?></strong> saat ini masih berstatus <strong>AKTIF</strong> dan belum jatuh tempo.<br>
            Masa berlaku ketetapan masih berjalan sampai dengan <strong><?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?></strong>.
          </p>
          <div style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 6px; padding: 8px 14px; font-size: 12.5px; color: #0369a1; display: inline-flex; align-items: center; gap: 8px;">
            <?= renderIcon('calendar', '', 15) ?>
            <span>Anda tetap dapat memproses pembayaran perpanjangan daftar ulang tahunan. Periode pajak baru akan otomatis diperpanjang mulai <strong><?= formatTanggalIndo($masaAwalBaru) ?></strong> s.d <strong><?= formatTanggalIndo($masaAkhirBaru) ?></strong>.</span>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="panel" style="border: 2px solid #ef4444; background: #fef2f2; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.12);">
      <div class="panel-body" style="padding: 18px 22px; display: flex; align-items: flex-start; gap: 16px;">
        <div style="width: 46px; height: 46px; border-radius: 50%; background: #ef4444; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);">
          <?= renderIcon('alert-triangle', '', 26) ?>
        </div>
        <div style="flex: 1;">
          <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap;">
            <h3 style="margin: 0; color: #991b1b; font-size: 16px; font-weight: 800;">
              PERINGATAN: MASA PAJAK KENDARAAN AIR TELAH JATUH TEMPO
            </h3>
            <span class="badge badge-danger" style="font-size: 11px; padding: 3px 8px;">Terlambat / Menunggak</span>
          </div>
          <p style="margin: 0 0 10px 0; color: #7f1d1d; font-size: 13.5px; line-height: 1.5;">
            Masa berlaku pajak untuk kapal <strong><?= htmlspecialchars($kapal['nama_kapal']) ?> (<?= htmlspecialchars($kapal['no_objek_pajak']) ?>)</strong> telah berakhir pada <strong><?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?></strong>.<br>
            Sistem secara otomatis mengenakan sanksi denda keterlambatan sesuai Perda Pajak Daerah NTT.
          </p>
          <div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; padding: 8px 14px; font-size: 12.5px; color: #991b1b; display: inline-flex; align-items: center; gap: 8px;">
            <?= renderIcon('clock', '', 15) ?>
            <span>Denda keterlambatan terhitung: <strong><?= formatRupiah($perhitungan['bea_pkaa_denda'] + $perhitungan['opsen_pkaa_denda']) ?></strong> (Keterlambatan: <?= $perhitungan['bulan_terlambat'] ?> bulan).</span>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Form Proses Pembayaran Tahunan -->
  <form method="POST" action="index.php?page=pembayaran_tahunan" id="formBayarTahunan">
    <?= csrfField() ?>
    <input type="hidden" name="aksi" value="proses_bayar_tahunan">
    <input type="hidden" name="no_objek_pajak" value="<?= htmlspecialchars($kapal['no_objek_pajak']) ?>">

    <div class="form-grid-2" style="align-items: start;">
      
      <!-- KOLOM KIRI: IDENTITAS LENGKAP KENDARAAN & NJKB -->
      <div>
        <!-- Panel 1: Identitas Kapal & Wajib Pajak -->
        <div class="panel" style="margin-bottom: 16px;">
          <div class="panel-header">
            <div class="panel-title">
              <?= renderIcon('ship', '', 16) ?>
              <span>IDENTITAS OBJEK & WAJIB PAJAK KAA</span>
            </div>
            <span class="badge badge-<?= $isPajakAktif ? 'success' : 'danger' ?>">
              Status: <?= $isPajakAktif ? 'PAJAK AKTIF' : 'JATUH TEMPO' ?>
            </span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <table class="table-clean" style="margin: 0;">
              <tr>
                <td class="label" style="width: 180px;">Nomor Objek Pajak (NOPD)</td>
                <td class="sep">:</td>
                <td class="val"><strong style="font-size: 14px; color: #0284c7;"><?= htmlspecialchars($kapal['no_objek_pajak']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">Nama Kapal / SB</td>
                <td class="sep">:</td>
                <td class="val"><strong style="font-size: 13.5px;"><?= htmlspecialchars($kapal['nama_kapal']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">Nama Pemilik / Badan</td>
                <td class="sep">:</td>
                <td class="val"><strong><?= htmlspecialchars($kapal['nama_pemilik']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">Alamat Pemilik</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($kapal['alamat_pemilik']) ?></td>
              </tr>
              <tr>
                <td class="label">No. Telepon / HP</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($kapal['no_hp'] ?: '-') ?></td>
              </tr>
              <tr>
                <td class="label">Asal Wilayah</td>
                <td class="sep">:</td>
                <td class="val">
                  <strong>[<?= $kapal['kode_kabkota'] ?>] <?= htmlspecialchars($kapal['nama_kabupaten_kota']) ?></strong>
                </td>
              </tr>
              <tr>
                <td class="label">Pelabuhan Pangkal</td>
                <td class="sep">:</td>
                <td class="val"><?= strtoupper(htmlspecialchars($kapal['pelabuhan_pangkal'])) ?></td>
              </tr>
              <tr>
                <td class="label">Jenis & Fungsi Pajak</td>
                <td class="sep">:</td>
                <td class="val">
                  <?= htmlspecialchars($kapal['nama_jenis']) ?> &mdash; 
                  <span class="badge badge-neutral"><?= htmlspecialchars($kapal['nama_fungsi']) ?> (<?= $kapal['tarif_persen'] ?>%)</span>
                </td>
              </tr>
              <tr>
                <td class="label">Spesifikasi Fisik</td>
                <td class="sep">:</td>
                <td class="val">
                  Bahan: <?= htmlspecialchars($kapal['bahan_kapal']) ?> &bull; 
                  Kapasitas: <?= htmlspecialchars($kapal['kapasitas_gt']) ?> GT &bull; 
                  Tahun Buat: <?= $kapal['tahun_buatan'] ?>
                </td>
              </tr>
              <tr>
                <td class="label">Tanda Selar / Izin Trayek</td>
                <td class="sep">:</td>
                <td class="val">
                  Selar: <?= htmlspecialchars($kapal['tanda_selar'] ?: '-') ?> &bull; 
                  Izin: <?= htmlspecialchars($kapal['no_ijin_operasi'] ?: '-') ?>
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Panel 2: Komponen Standar Nilai Jual (NJKB) -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <?= renderIcon('coins', '', 16) ?>
              <span>PENETAPAN NILAI JUAL (NJKB: <?= $kapal['metode_njkb'] ?>)</span>
            </div>
            <span class="badge badge-neutral">Dasar Pengenaan Pajak</span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <?php if ($kapal['metode_njkb'] === 'GABUNGAN'): ?>
              <div style="background: #f8fafc; border: 1.5px solid #0284c7; border-radius: 6px; padding: 12px;">
                <span style="font-size: 11px; color: #0284c7; font-weight: 700; display: block; text-transform: uppercase;">
                  Paket 1 Kesatuan (Body + Mesin Utuh):
                </span>
                <strong style="font-size: 13.5px; color: #0f172a; display: block; margin: 2px 0 4px 0;">
                  <?= htmlspecialchars($kapal['merek_gabungan'] ?: $kapal['nama_kapal']) ?>
                </strong>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px;">
                  <span style="font-size: 12px; color: #64748b;">Nilai Jual Paket Gabungan:</span>
                  <strong style="font-size: 15px; color: #0369a1;"><?= formatRupiah($kapal['njkb_gabungan']) ?></strong>
                </div>
              </div>
            <?php else: ?>
              <table class="table-desktop" style="margin: 0;">
                <thead>
                  <tr>
                    <th>Komponen Objek</th>
                    <th>Merek / Detail</th>
                    <th width="140" style="text-align: right;">Nilai Jual (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>Body Kapal</strong></td>
                    <td><?= htmlspecialchars($kapal['merek_body'] ?: 'Standar') ?> (Tahun: <?= $kapal['tahun_body'] ?: $kapal['tahun_buatan'] ?>)</td>
                    <td align="right"><?= formatRupiah($kapal['nj_body_kapal']) ?></td>
                  </tr>
                  <?php if (!empty($mesinList)): ?>
                    <?php foreach ($mesinList as $m): ?>
                      <tr>
                        <td>Mesin Ke-<?= $m['mesin_ke'] ?></td>
                        <td>
                          <?= htmlspecialchars($m['merek_mesin']) ?> (<?= htmlspecialchars($m['daya_pk']) ?> PK)
                          <?php if (!empty($m['nomor_mesin'])): ?>
                            <br><small style="color: #64748b;">No: <?= htmlspecialchars($m['nomor_mesin']) ?></small>
                          <?php endif; ?>
                        </td>
                        <td align="right"><?= formatRupiah($m['nilai_jual_mesin']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            <?php endif; ?>

            <div style="margin-top: 12px; background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 6px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
              <span style="font-size: 12px; font-weight: 700; color: #065f46;">TOTAL DASAR PENGENAAN NJKB:</span>
              <strong style="font-size: 17px; color: #065f46;"><?= formatRupiah($kapal['total_njkb']) ?></strong>
            </div>
          </div>
        </div>
      </div>

      <!-- KOLOM KANAN: MASA PKAA TERBARU, RUMUS PERHITUNGAN, & FORM PEMBAYARAN -->
      <div>
        <!-- Panel 3: Masa Pajak PKAA Mulai Dari dan S.D Pajak Terbaru -->
        <div class="panel" style="margin-bottom: 16px; border: 1.5px solid <?= $isPajakAktif ? '#0284c7' : '#f59e0b' ?>; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);">
          <div class="panel-header" style="background: <?= $isPajakAktif ? '#f0f9ff' : '#fffbeb' ?>; border-bottom: 1.5px solid <?= $isPajakAktif ? '#bae6fd' : '#fde68a' ?>;">
            <div class="panel-title" style="color: <?= $isPajakAktif ? '#0369a1' : '#b45309' ?>; font-weight: 700;">
              <?= renderIcon('calendar', '', 16) ?>
              <span>PERIODE MASA PAJAK PKAA TAHUNAN TERBARU</span>
            </div>
            <span class="badge badge-<?= $isPajakAktif ? 'success' : 'warning' ?>">
              <?= $isPajakAktif ? "Pajak Aktif ($hariTersisa Hari Lagi)" : "Jatuh Tempo" ?>
            </span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <!-- Informasi Masa Pajak Lama -->
            <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <span style="font-size: 11px; color: #64748b; display: block;">Masa Pajak Sebelumnya Berakhir Pada:</span>
                <strong style="font-size: 14px; color: <?= $isPajakAktif ? '#0284c7' : '#dc2626' ?>;">
                  <?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?>
                </strong>
              </div>
              <div>
                <?php if ($isPajakAktif): ?>
                  <span class="badge badge-success"><?= renderIcon('check', '', 12) ?> Masih Aktif (Perpanjangan Rutin)</span>
                <?php else: ?>
                  <span class="badge badge-danger"><?= renderIcon('alert-triangle', '', 12) ?> Lewat Jatuh Tempo</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Input Masa Pajak Mulai & Masa Pajak S.D Terbaru -->
            <div class="form-grid-2">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="masa_pajak_awal" style="color: #0369a1; font-weight: 700;">
                  Masa PKAA Mulai Dari <span class="required">*</span>
                </label>
                <input type="date" name="masa_pajak_awal" id="masa_pajak_awal" class="form-control" 
                       value="<?= htmlspecialchars($masaAwalBaru) ?>" required onchange="onMasaAwalChange(this.value)">
                <span style="font-size: 11px; color: #64748b; margin-top: 3px; display: block;">
                  Tanggal dimulainya tahun pajak baru (H+1)
                </span>
              </div>

              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="masa_pajak_akhir" style="color: #0369a1; font-weight: 700;">
                  Masa PKAA Sampai Dengan (s.d) <span class="required">*</span>
                </label>
                <input type="date" name="masa_pajak_akhir" id="masa_pajak_akhir" class="form-control" 
                       value="<?= htmlspecialchars($masaAkhirBaru) ?>" readonly style="background-color: #f1f5f9; cursor: not-allowed;" required>
                <span style="font-size: 11px; color: #64748b; margin-top: 3px; display: block;">
                  Ketetapan otomatis 1 tahun ke depan (dikunci oleh sistem)
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel 4: Perhitungan Ketetapan Fiskal & Denda -->
        <div class="panel" style="margin-bottom: 16px;">
          <div class="panel-header">
            <div class="panel-title">
              <?= renderIcon('receipt', '', 16) ?>
              <span>RINCIAN PERHITUNGAN PKAA & OPSEN 66%</span>
            </div>
            <span class="badge badge-neutral">Sistem Tarif Resmi</span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <table class="table-clean" style="margin: 0; font-size: 13px;">
              <tr>
                <td class="label">1. Dasar Pengenaan NJKB</td>
                <td class="sep">:</td>
                <td class="val" align="right"><strong><?= formatRupiah($perhitungan['njkb_dasar']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">2. Tarif Pokok PKAA (Fungsi: <?= htmlspecialchars($perhitungan['nama_fungsi']) ?>)</td>
                <td class="sep">:</td>
                <td class="val" align="right"><span class="badge badge-neutral"><?= $perhitungan['tarif_pokok_persen'] ?>%</span></td>
              </tr>
              <tr>
                <td class="label">3. Bea Pokok PKAA (NJKB &times; Tarif)</td>
                <td class="sep">:</td>
                <td class="val" align="right" style="color: #0284c7; font-weight: 700;"><?= formatRupiah($perhitungan['bea_pkaa_pokok']) ?></td>
              </tr>
              <tr>
                <td class="label">
                  4. Sanksi Administrasi / Denda Keterlambatan
                  <?php if ($perhitungan['bulan_terlambat'] > 0): ?>
                    <span class="badge badge-danger" style="font-size: 10px; margin-left: 4px;"><?= $perhitungan['bulan_terlambat'] ?> Bulan Terlambat</span>
                  <?php endif; ?>
                </td>
                <td class="sep">:</td>
                <td class="val" align="right" style="color: <?= $perhitungan['bea_pkaa_denda'] > 0 ? '#dc2626' : '#64748b' ?>; font-weight: 700;">
                  <?= formatRupiah($perhitungan['bea_pkaa_denda']) ?>
                </td>
              </tr>
              <tr>
                <td class="label">5. Opsen PKAA Pokok (66% dari Bea Pokok)</td>
                <td class="sep">:</td>
                <td class="val" align="right" style="color: #b45309; font-weight: 700;"><?= formatRupiah($perhitungan['opsen_pkaa_pokok']) ?></td>
              </tr>
              <?php if ($perhitungan['opsen_pkaa_denda'] > 0): ?>
                <tr>
                  <td class="label">6. Denda Opsen PKAA (66% dari Denda Pokok)</td>
                  <td class="sep">:</td>
                  <td class="val" align="right" style="color: #dc2626; font-weight: 700;"><?= formatRupiah($perhitungan['opsen_pkaa_denda']) ?></td>
                </tr>
              <?php endif; ?>
              <tr style="border-top: 2px solid #0f172a; background: #f8fafc;">
                <td class="label" style="font-size: 14px; font-weight: 800; color: #0f172a; padding: 10px 0;">TOTAL KETETAPAN PEMBAYARAN:</td>
                <td class="sep" style="padding: 10px 0;">:</td>
                <td class="val" align="right" style="padding: 10px 0;">
                  <strong style="font-size: 18px; color: #065f46;"><?= formatRupiah($perhitungan['total_ketetapan']) ?></strong>
                </td>
              </tr>
              <tr>
                <td colspan="3" style="font-size: 11.5px; font-style: italic; color: #475569; padding-top: 4px;">
                  Terbilang: <strong><?= terbilang($perhitungan['total_ketetapan']) ?> Rupiah</strong>
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Panel 5: Detail Penyetoran & Tombol Eksekusi Bayar -->
        <div class="panel" style="border: 1.5px solid #10b981;">
          <div class="panel-header" style="background: #f0fdf4; border-bottom: 1.5px solid #86efac;">
            <div class="panel-title" style="color: #166534; font-weight: 700;">
              <?= renderIcon('check-circle', '', 16) ?>
              <span>VALIDASI PELUNASAN KASDA & PENERBITAN SKPD</span>
            </div>
            <span class="badge badge-success">Kas Daerah NTT</span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <div class="form-grid-2">
              <div class="form-group">
                <label class="form-label" for="tgl_bayar">Tanggal Pembayaran / Validasi <span class="required">*</span></label>
                <input type="date" name="tgl_bayar" id="tgl_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="metode_bayar">Kanal Pembayaran <span class="required">*</span></label>
                <select name="metode_bayar" id="metode_bayar" class="form-control" required>
                  <option value="BANK NTT (Virtual Account / Teller)" selected>BANK NTT (Virtual Account / Teller)</option>
                  <option value="LOKET KASIR BPAD NTT (Tunai)">LOKET KASIR BPAD NTT (Tunai)</option>
                  <option value="QRIS KASDA PROVINSI NTT">QRIS KASDA PROVINSI NTT</option>
                  <option value="TRANSFER ANTAR BANK (ATM / Mobile Banking)">TRANSFER ANTAR BANK (ATM / Mobile)</option>
                </select>
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label class="form-label" for="no_resi_bukti">No. Resi Bukti Bayar / NTPN / Referensi</label>
                <input type="text" name="no_resi_bukti" id="no_resi_bukti" class="form-control" placeholder="Contoh: RESI-NTT-<?= date('Ymd') ?>-001" value="RESI-NTT-<?= date('Ymd') ?>-<?= rand(100, 999) ?>">
              </div>

              <div class="form-group">
                <label class="form-label" for="pejabat_skpd_id">Pejabat Penandatangan SKPD <span class="required">*</span></label>
                <select name="pejabat_skpd_id" id="pejabat_skpd_id" class="form-control" required>
                  <?php foreach ($pejabatSkpdList as $pj): ?>
                    <option value="<?= $pj['id'] ?>" selected>
                      <?= htmlspecialchars($pj['nama_pejabat']) ?> &mdash; <?= htmlspecialchars($pj['jabatan']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-top: 10px; display: flex; justify-content: flex-end; gap: 10px;">
              <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary" style="padding: 10px 18px;">
                Batal
              </a>
              <button type="submit" id="btnSubmitBayar" class="btn btn-primary" style="background: #059669; border-color: #047857; padding: 10px 28px; font-weight: 800; font-size: 14px; box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);">
                <?= renderIcon('check-circle', '', 18) ?>
                <span>PROSES & SAHKAN PEMBAYARAN TAHUNAN</span>
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </form>
<?php elseif (!$kapal && !empty($selectedNopd) && !empty($box2)): ?>
  <div class="panel" style="text-align: center; padding: 40px 20px;">
    <div style="color: #ef4444; margin-bottom: 12px;">
      <?= renderIcon('alert-triangle', '', 48) ?>
    </div>
    <h3 style="color: #0f172a; margin: 0 0 6px 0;">NOPD Tidak Ditemukan!</h3>
    <p style="color: #64748b; max-width: 480px; margin: 0 auto 18px auto;">
      Objek dengan nomor <strong><?= htmlspecialchars($selectedNopd) ?></strong> tidak terdaftar di sistem JANGKAR NTT. Pastikan penulisan 3 kotak nomor objek pajak sudah sesuai.
    </p>
    <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary">
      Kembali Cari NOPD Lain
    </a>
  </div>
<?php endif; ?>

<script>
// Penyesuaian otomatis Masa Akhir Pajak ketika Masa Awal diubah
function onMasaAwalChange(tglAwal) {
  if (!tglAwal) return;
  var d = new Date(tglAwal);
  d.setFullYear(d.getFullYear() + 1);
  
  var yyyy = d.getFullYear();
  var mm = String(d.getMonth() + 1).padStart(2, '0');
  var dd = String(d.getDate()).padStart(2, '0');
  
  var tglAkhirInput = document.getElementById('masa_pajak_akhir');
  if (tglAkhirInput) {
    tglAkhirInput.value = yyyy + '-' + mm + '-' + dd;
  }
}

// Interaksi Cerdas 3 Kotak NOPD: [xx] [xxxx] [xx]
document.addEventListener('DOMContentLoaded', function() {
  const box1 = document.getElementById('input_nopd_1');
  const box2 = document.getElementById('input_nopd_2');
  const box3 = document.getElementById('input_nopd_3');
  const form = document.getElementById('formCariNopd');
  const hiddenNopd = document.getElementById('input_nopd_gabungan');

  function updateHiddenNopd() {
    if (hiddenNopd && box1 && box2 && box3) {
      const b1 = box1.value.trim().toUpperCase();
      const b2 = box2.value.trim().toUpperCase();
      const b3 = box3.value.trim().toUpperCase();
      hiddenNopd.value = (b1 + ' ' + b2 + ' ' + b3).trim();
    }
  }

  // Auto focus cerdas: Jika box1 terisi (KA) dan box2 kosong, langsung fokus ke box2
  if (box1 && box1.value.trim().length === 2 && box2 && !box2.value.trim()) {
    box2.focus();
    box2.select();
  }

  // 1. Event Kotak 1 [xx]
  if (box1) {
    box1.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
      updateHiddenNopd();
      if (this.value.length >= 2 && box2) {
        box2.focus();
        box2.select();
      }
    });

    box1.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        updateHiddenNopd();
        form.submit();
      }
    });

    box1.addEventListener('paste', function(e) {
      e.preventDefault();
      const pasteText = (e.clipboardData || window.clipboardData).getData('text').trim().toUpperCase();
      parseAndDistributeNopd(pasteText);
    });
  }

  // 2. Event Kotak 2 [xxxx]
  if (box2) {
    box2.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
      updateHiddenNopd();
      if (this.value.length >= 4 && box3) {
        box3.focus();
        box3.select();
      }
    });

    box2.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        updateHiddenNopd();
        form.submit();
      } else if (e.key === 'Backspace' && this.value === '' && box1) {
        box1.focus();
      }
    });

    box2.addEventListener('paste', function(e) {
      e.preventDefault();
      const pasteText = (e.clipboardData || window.clipboardData).getData('text').trim().toUpperCase();
      parseAndDistributeNopd(pasteText);
    });
  }

  // 3. Event Kotak 3 [xx]
  if (box3) {
    box3.addEventListener('input', function() {
      this.value = this.value.toUpperCase();
      updateHiddenNopd();
    });

    box3.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        updateHiddenNopd();
        form.submit();
      } else if (e.key === 'Backspace' && this.value === '' && box2) {
        box2.focus();
      }
    });

    box3.addEventListener('paste', function(e) {
      e.preventDefault();
      const pasteText = (e.clipboardData || window.clipboardData).getData('text').trim().toUpperCase();
      parseAndDistributeNopd(pasteText);
    });
  }

  if (form) {
    form.addEventListener('submit', function() {
      updateHiddenNopd();
    });
  }

  // Handler proteksi double submission pada form pembayaran
  const formBayar = document.getElementById('formBayarTahunan');
  if (formBayar) {
    formBayar.addEventListener('submit', function(e) {
      const confirmed = confirm('Apakah Anda yakin data pembayaran sudah benar dan ingin mengesahkan SKPD PKAA Tahunan ini?');
      if (!confirmed) {
        e.preventDefault();
        return false;
      }
      const btn = document.getElementById('btnSubmitBayar');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>Sedang Memproses Pengesahan...</span>';
      }
    });
  }
});

// Helper untuk memecah string NOPD utuh ke dalam 3 kotak
function parseAndDistributeNopd(raw) {
  if (!raw) return;
  const box1 = document.getElementById('input_nopd_1');
  const box2 = document.getElementById('input_nopd_2');
  const box3 = document.getElementById('input_nopd_3');
  const form = document.getElementById('formCariNopd');
  const hiddenNopd = document.getElementById('input_nopd_gabungan');

  const cleaned = raw.replace(/[-_.]/g, ' ').replace(/\s+/g, ' ').trim().toUpperCase();
  const parts = cleaned.split(' ');

  if (parts.length >= 3) {
    if (box1) box1.value = parts[0];
    if (box2) box2.value = parts[1];
    if (box3) box3.value = parts[2];
  } else {
    const noSpace = raw.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    const match = noSpace.match(/^([A-Z]{2})(\d{1,4})([A-Z]{1,3})$/);
    if (match) {
      if (box1) box1.value = match[1];
      if (box2) box2.value = match[2].padStart(4, '0');
      if (box3) box3.value = match[3];
    } else {
      if (box1) box1.value = raw.substring(0, 2);
    }
  }

  if (hiddenNopd && box1 && box2 && box3) {
    hiddenNopd.value = (box1.value.trim() + ' ' + box2.value.trim() + ' ' + box3.value.trim()).trim();
  }

  if (form) {
    form.submit();
  }
}

// Helper untuk pilihan cepat dari dropdown
function onSelectCepat(val) {
  if (!val) return;
  parseAndDistributeNopd(val);
}
</script>
