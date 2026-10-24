<?php
// modules/pelayanan/skpd.php - Pembayaran & Penetapan SKPD PKAA
if (!defined('APP_INIT')) {
    http_response_code(403);
    die("Akses langsung ke modul ini tidak diizinkan.");
}

$pageTitle = "Pembayaran & Cetak SKPD PKAA";
$currentUser = currentUser();
$userRole = $currentUser['role'] ?? 'OPERATOR';

$selectedSuper = trim($_GET['super'] ?? $_POST['no_superpkaa'] ?? '');
$selectedNopd = trim($_GET['nopd'] ?? $_POST['no_objek_pajak'] ?? '');

$superData = null;
$kapal = null;
$skpdSahAktif = null;
$isPajakAktif = false;
$hariTersisa = 0;
$pesan = '';
$tipePesan = '';

// Ambil data pejabat SKPD aktif
$pejabatSkpdList = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'SKPD' AND is_active = 1 ORDER BY id ASC")->fetchAll();

// Jika ada parameter SUPERPKAA atau NOPD
if (!empty($selectedSuper)) {
    $stmt = $pdo->prepare("
        SELECT s.*, k.*, f.nama_fungsi, f.tarif_persen, j.nama_jenis, kb.nama_kabupaten_kota
        FROM transaksi_superpkaa s
        JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        WHERE s.no_superpkaa = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedSuper]);
    $superData = $stmt->fetch();
    if ($superData) {
        $kapal = $superData;
    }
} elseif (!empty($selectedNopd)) {
    $stmt = $pdo->prepare("
        SELECT s.*, k.*, f.nama_fungsi, f.tarif_persen, j.nama_jenis, kb.nama_kabupaten_kota
        FROM transaksi_superpkaa s
        JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        WHERE s.no_objek_pajak = ?
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$selectedNopd]);
    $superData = $stmt->fetch();
    if ($superData) {
        $kapal = $superData;
    } else {
        // Cek jika kapal ada tapi belum ada SUPERPKAA
        $stmtK = $pdo->prepare("SELECT * FROM kendaraan_air WHERE no_objek_pajak = ?");
        $stmtK->execute([$selectedNopd]);
        if ($stmtK->fetch()) {
            safeRedirect("index.php?page=superpkaa&nopd=" . urlencode($selectedNopd));
        }
    }
}

// Cek apakah ada SKPD sah aktif untuk objek ini & cek status keaktifan pajak
if ($superData) {
    $stmtSkpd = $pdo->prepare("
        SELECT sk.*, p.nama_pejabat as nama_pengesah, p.jabatan as jabatan_pengesah
        FROM transaksi_skpd sk
        LEFT JOIN pejabat p ON sk.pejabat_skpd_id = p.id
        WHERE sk.no_objek_pajak = ? AND sk.status = 'SAH'
        ORDER BY sk.created_at DESC LIMIT 1
    ");
    $stmtSkpd->execute([$superData['no_objek_pajak']]);
    $skpdSahAktif = $stmtSkpd->fetch();

    if (!empty($kapal['tg_akhir_pajak']) && $kapal['tg_akhir_pajak'] !== '0000-00-00') {
        $todayTs = strtotime(date('Y-m-d'));
        $expTs = strtotime($kapal['tg_akhir_pajak']);
        if ($expTs >= $todayTs && $kapal['status_pajak'] === 'LUNAS') {
            $isPajakAktif = true;
            $hariTersisa = (int)ceil(($expTs - $todayTs) / 86400);
        }
    }
}

// Proses Pembayaran & SKPD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'proses_skpd' && $superData) {
    // 1. Validasi Token CSRF
    if (!verifyCsrfToken()) {
        setFlash('error', 'Validasi Keamanan Gagal (CSRF)', 'Sesi atau token keamanan tidak valid. Silakan muat ulang halaman.');
        safeRedirect("index.php?page=skpd" . (!empty($selectedSuper) ? "&super=" . urlencode($selectedSuper) : ""));
    }

    // 2. Cegah Pendoubelan Transaksi jika pajak masih aktif atau sudah lunas
    if ($isPajakAktif || $superData['status'] === 'LUNAS') {
        setFlash('warning', 'Pajak Masih Aktif!', 'Pajak untuk kendaraan ini masih aktif dan SKPD sah telah terbit. Tombol penerbitan baru dinonaktifkan untuk mencegah pendoubelan.');
        safeRedirect("index.php?page=skpd" . (!empty($selectedSuper) ? "&super=" . urlencode($selectedSuper) : ""));
    }

    // 3. Validasi alur: Wajib Cetak SUPERPKAA terlebih dahulu
    if (empty($superData['status_cetak'])) {
        setFlash('error', 'Cetak SUPERPKAA Diperlukan!', 'Dokumen SUPERPKAA belum dicetak! Wajib mencetak lembar SUPERPKAA terlebih dahulu sebelum dapat memproses & mencetak SKPD.');
        safeRedirect("index.php?page=skpd&super=" . urlencode($superData['no_superpkaa']));
    }

    $noSkpd = generateNextSKPD($pdo);
    $tglBayar = trim($_POST['tgl_bayar'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglBayar) || strtotime($tglBayar) === false) {
        $tglBayar = date('Y-m-d');
    }

    $metodeBayar = trim(strip_tags($_POST['metode_bayar'] ?? 'BANK NTT (Kasda)'));
    $noResi = trim(strip_tags($_POST['no_resi_bukti'] ?? ''));
    $pejabatId = (int)($_POST['pejabat_skpd_id'] ?? 1);
    $kasirId = $currentUser['id'] ?? 1;

    $beaPokok = (float)$superData['bea_pkaa_pokok'];
    $beaDenda = (float)$superData['bea_pkaa_denda'];
    $opsenPokok = (float)$superData['opsen_pkaa_pokok'];
    $opsenDenda = (float)$superData['opsen_pkaa_denda'];
    $totalBayar = (float)$superData['total_ketetapan'];
    $tglLamaKapal = $kapal['tg_akhir_pajak'] ?? null;

    try {
        $pdo->beginTransaction();

        $stmtIns = $pdo->prepare("
            INSERT INTO transaksi_skpd (
                no_skpd, no_superpkaa, no_objek_pajak, tgl_bayar, metode_bayar,
                no_resi_bukti, bea_pkaa_pokok_bayar, bea_pkaa_denda_bayar,
                opsen_pkaa_pokok_bayar, opsen_pkaa_denda_bayar, total_bayar,
                status, tg_akhir_sebelumnya,
                pejabat_skpd_id, id_petugas_kasir, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                'SAH', ?,
                ?, ?, NOW()
            )
        ");
        $stmtIns->execute([
            $noSkpd, $superData['no_superpkaa'], $superData['no_objek_pajak'], $tglBayar, $metodeBayar,
            $noResi, $beaPokok, $beaDenda,
            $opsenPokok, $opsenDenda, $totalBayar,
            $tglLamaKapal,
            $pejabatId, $kasirId
        ]);

        // Update status transaksi superpkaa dan kendaraan_air
        $pdo->prepare("UPDATE transaksi_superpkaa SET status = 'LUNAS' WHERE no_superpkaa = ?")
            ->execute([$superData['no_superpkaa']]);

        $pdo->prepare("UPDATE kendaraan_air SET status_pajak = 'LUNAS', tg_akhir_pajak = ? WHERE no_objek_pajak = ?")
            ->execute([$superData['masa_pajak_akhir'], $superData['no_objek_pajak']]);

        // Audit Trail Pencetakan Lembar SKPD
        logCetakSKPD(
            $pdo,
            $noSkpd,
            $superData['no_superpkaa'],
            $superData['no_objek_pajak'],
            $superData['nama_kapal'],
            $superData['nama_pemilik'],
            $kasirId,
            $currentUser['nama_lengkap'] ?? 'Petugas Kasir Kasda',
            'Penerbitan & Pembayaran SKPD Kasda Sah'
        );

        $pdo->commit();

        setFlash('success', 'Pembayaran SKPD Berhasil', "Surat Ketetapan Pajak Daerah (SKPD) Nomor: <strong>$noSkpd</strong> telah berhasil disahkan & lunas!");
        $pesan = "Pembayaran Pajak Berhasil! Surat Ketetapan Pajak Daerah (SKPD) Nomor: <strong>$noSkpd</strong> telah diterbitkan secara sah!";
        $tipePesan = 'success';
        $noSkpdTerbit = $noSkpd;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Gagal proses SKPD: " . $e->getMessage());
        setFlash('error', 'Gagal Memproses SKPD', 'Terjadi kesalahan sistem saat memproses SKPD.');
        $pesan = "Gagal memproses pembayaran SKPD. Silakan coba kembali atau hubungi administrator.";
        $tipePesan = 'danger';
    }
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('credit-card', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Pembayaran & Penerbitan Surat Ketetapan Pajak Daerah (SKPD PKAA)</div>
      <div class="page-subtitle">Pelunasan Pajak Kendaraan Bermotor di Atas Air & Pencetakan Bukti Bayar Sah (SKPD 3 Rangkap)</div>
    </div>
  </div>
  <div class="page-header-actions">
    <button type="button" class="btn btn-secondary btn-sm" onclick="bukaLayarBantu('skpd_nopd_input')">
      <?= renderIcon('search', '', 14) ?> Layar Bantu NOPD (Ctrl+B)
    </button>
  </div>
</div>

<?php if (!empty($pesan)): ?>
  <div class="alert alert-<?= $tipePesan ?>">
    <?= renderIcon($tipePesan === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
    <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
      <span><?= $pesan ?></span>
      <?php if (!empty($noSkpdTerbit)): ?>
        <a href="cetak/skpd_print.php?no=<?= urlencode($noSkpdTerbit) ?>" target="_blank" class="btn btn-primary btn-sm">
          <?= renderIcon('printer', '', 14) ?> Cetak Lembar SKPD PKAA
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Form Pencarian NOPD / SUPER -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-body">
    <form method="GET" action="index.php" style="display:flex; gap:10px; align-items:flex-end;">
      <input type="hidden" name="page" value="skpd">
      <div class="form-group" style="flex:1;">
        <label class="form-label" for="skpd_nopd_input">Nomor Objek Pajak (NOPD) atau Nomor SUPERPKAA</label>
        <div class="input-group">
          <input type="text" name="nopd" id="skpd_nopd_input" class="form-control" value="<?= htmlspecialchars($selectedNopd ?: ($superData['no_objek_pajak'] ?? '')) ?>" placeholder="Contoh NOPD: 133000054 atau No. SUPER" required>
          <button type="button" class="btn btn-secondary" onclick="bukaLayarBantu('skpd_nopd_input')">
            <?= renderIcon('search', '', 14) ?> Layar Bantu (Ctrl+B)
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="height:32px;">
        <?= renderIcon('search', '', 14) ?> Muat Tagihan
      </button>
    </form>
  </div>
</div>

<?php if ($superData): ?>
  <?php if ($isPajakAktif || $superData['status'] === 'LUNAS'): ?>
    <div class="panel" style="background:#f0fdf4; border:2px solid #10b981; margin-bottom:14px; box-shadow:0 4px 12px rgba(16,185,129,0.15);">
      <div class="panel-body" style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:14px;">
          <div style="color:#059669;"><?= renderIcon('check-circle', '', 36) ?></div>
          <div>
            <div style="font-weight:800; color:#065f46; font-size:15px;">PAJAK KENDARAAN AIR MASIH AKTIF &bull; SKPD TELAH DISAHKAN</div>
            <div style="color:#047857; font-size:12.5px; margin-top:2px; line-height: 1.5;">
              Status pajak kendaraan <strong><?= htmlspecialchars($kapal['nama_kapal']) ?></strong> berstatus <strong>LUNAS</strong> sampai dengan <strong><?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?></strong> (Tersisa <?= $hariTersisa ?> hari lagi).<br>
              Dokumen SKPD Sah: <strong><?= htmlspecialchars($skpdSahAktif['no_skpd'] ?? '-') ?></strong> telah terbit pada <?= !empty($skpdSahAktif['tgl_bayar']) ? formatTanggalIndo($skpdSahAktif['tgl_bayar']) : '-' ?>.
              <div style="font-size:11.5px; color:#065f46; font-style:italic; margin-top:4px;">
                🔒 <strong>Tombol cetak baru dinonaktifkan</strong> untuk mencegah pendoubelan cetak. Jika dokumen fisik rusak/cacat cetak, hubungi Admin Induk untuk membatalkan SKPD lama terlebih dahulu.
              </div>
            </div>
          </div>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <?php if (!empty($skpdSahAktif['no_skpd'])): ?>
            <a href="cetak/skpd_print.php?no=<?= urlencode($skpdSahAktif['no_skpd']) ?>" target="_blank" class="btn btn-success" style="font-weight:700; padding:10px 18px; box-shadow:0 2px 8px rgba(16,185,129,0.3);">
              <?= renderIcon('printer', '', 16) ?> Cetak Salinan SKPD Sah
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php elseif (empty($superData['status_cetak'])): ?>
    <div class="panel" style="background:#fffbeb; border:2px solid #f59e0b; margin-bottom:14px; box-shadow:0 4px 12px rgba(245,158,11,0.15);">
      <div class="panel-body" style="padding:14px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:12px;">
          <div style="color:#d97706;"><?= renderIcon('alert-triangle', '', 32) ?></div>
          <div>
            <div style="font-weight:800; color:#92400e; font-size:14px;">PERHATIAN: DOKUMEN SUPERPKAA BELUM DICETAK!</div>
            <div style="color:#b45309; font-size:12px; margin-top:2px;">
              Sesuai ketentuan alur pelayanan JANGKAR NTT, Surat Pemberitahuan Pajak (SUPERPKAA) No. <strong><?= htmlspecialchars($superData['no_superpkaa']) ?></strong> harus dicetak terlebih dahulu sebelum memproses pembayaran & penerbitan SKPD.
            </div>
          </div>
        </div>
        <div>
          <a href="cetak/superpkaa_print.php?no=<?= urlencode($superData['no_superpkaa']) ?>" target="_blank" class="btn btn-accent" style="background:#059669; border-color:#047857; color:#fff; font-weight:700; padding:8px 16px;">
            <?= renderIcon('printer', '', 14) ?> Cetak SUPERPKAA Sekarang &rarr;
          </a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Form Pembayaran & Format PuTTY Asli -->
  <form method="POST" action="index.php?page=skpd" class="panel">
    <?= csrfField() ?>
    <input type="hidden" name="aksi" value="proses_skpd">
    <input type="hidden" name="no_superpkaa" value="<?= htmlspecialchars($superData['no_superpkaa']) ?>">
    <input type="hidden" name="no_objek_pajak" value="<?= htmlspecialchars($superData['no_objek_pajak']) ?>">

    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('file-text', '', 16) ?>
        <span>FORM PEMBAYARAN PAJAK KENDARAAN DI ATAS AIR (NOPD: <?= htmlspecialchars($superData['no_objek_pajak']) ?>)</span>
      </div>
      <div>
        <span class="badge badge-info">No. SUPER: <?= htmlspecialchars($superData['no_superpkaa']) ?></span>
        <?php if ($superData['status'] === 'LUNAS'): ?>
          <span class="badge badge-success"><?= renderIcon('check', '', 12) ?> SUDAH LUNAS</span>
        <?php elseif (empty($superData['status_cetak'])): ?>
          <span class="badge badge-warning"><?= renderIcon('clock', '', 12) ?> SUPERPKAA BELUM DICETAK</span>
        <?php else: ?>
          <span class="badge badge-success"><?= renderIcon('check', '', 12) ?> SIAP DIBAYAR</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel-body">
      <!-- Tampilan Berbaris Sesuai Terminal PuTTY Asli -->
      <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:14px; margin-bottom:14px;">
        <div class="form-grid-2" style="font-size:12px; line-height:1.7;">
          <div>
            <div><strong>NOPOL KENDARAAN AIR:</strong> <span style="font-family:var(--font-mono); font-weight:700; color:#0284c7; font-size:13px;"><?= htmlspecialchars($superData['no_objek_pajak']) ?></span></div>
            <div><strong>ASAL WILAYAH NTT:</strong> <span class="badge badge-info"><?= htmlspecialchars($superData['kode_kabkota'] ?: 'NTT') ?></span> <?= htmlspecialchars($superData['nama_kabupaten_kota'] ?? '-') ?></div>
            <div><strong>NO IJIN OPERASI:</strong> <?= htmlspecialchars($superData['no_ijin_operasi'] ?: '-') ?></div>
            <div><strong>TANDA SELAR:</strong> <?= htmlspecialchars($superData['tanda_selar'] ?: '-') ?></div>
            <div><strong>NAMA KAPAL / SB:</strong> <span style="font-weight:700;"><?= htmlspecialchars($superData['nama_kapal']) ?></span></div>
            <div><strong>BAHAN KAPAL:</strong> <?= htmlspecialchars($superData['bahan_kapal']) ?></div>
            <div><strong>NAMA PEMILIK:</strong> <?= htmlspecialchars($superData['nama_pemilik']) ?></div>
            <div><strong>ALAMAT PEMILIK:</strong> <?= htmlspecialchars($superData['alamat_pemilik']) ?></div>
          </div>
          <div>
            <div><strong>KODE JENIS:</strong> [<?= $superData['kode_jenis'] ?>] <?= htmlspecialchars($superData['nama_jenis']) ?></div>
            <div><strong>KODE FUNGSI:</strong> [<?= $superData['kode_fungsi'] ?>] <?= htmlspecialchars($superData['nama_fungsi']) ?> (<?= $superData['tarif_pokok_persen'] ?>%)</div>
            <div><strong>JUMLAH MESIN:</strong> <?= $superData['jumlah_mesin'] ?> Mesin &bull; <strong>KAPASITAS:</strong> <?= $superData['kapasitas_gt'] ?> GT</div>
            <div><strong>TAHUN BUATAN:</strong> <?= $superData['tahun_buatan'] ?> &bull; <strong>PEL. PANGKAL:</strong> <?= htmlspecialchars($superData['pelabuhan_pangkal']) ?></div>
            <div>
              <strong>METODE NJKB:</strong> 
              <?php if (($superData['metode_njkb'] ?? '') === 'GABUNGAN'): ?>
                <span class="badge badge-warning">1 Kesatuan (Body + Mesin Paket)</span>
              <?php else: ?>
                <span class="badge badge-neutral">Terpisah (Body & Mesin Sendiri-Sendiri)</span>
              <?php endif; ?>
            </div>
            <div style="margin-top:6px; padding:4px 8px; background:#eff6ff; border-radius:4px;">
              <strong>NJKB TOTAL:</strong> <span style="font-family:var(--font-mono); font-size:14px; font-weight:700; color:#1d4ed8;"><?= formatRupiah($superData['njkb_dasar']) ?></span>
            </div>
            <div style="margin-top:4px;">
              <strong>MASA BERLAKU PAJAK:</strong> <?= formatTanggalIndo($superData['masa_pajak_awal'], false) ?> s/d <?= formatTanggalIndo($superData['masa_pajak_akhir'], false) ?>
            </div>
          </div>
        </div>

        <!-- Garis Pemisah Sesuai Terminal -->
        <hr style="border:none; border-top:1px dashed #cbd5e1; margin:14px 0;">

        <!-- Rincian Bea & Opsen Terminal Layout -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <!-- BEA PKAA -->
          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:10px;">
            <div style="font-weight:700; color:#1e293b; font-size:12px; margin-bottom:6px;">BEA PKAA (KAS PROVINSI)</div>
            <div class="tax-calc-row">
              <span>Pokok PKAA:</span>
              <span class="tax-val"><?= formatRupiah($superData['bea_pkaa_pokok']) ?></span>
            </div>
            <div class="tax-calc-row">
              <span>Denda Keterlambatan:</span>
              <span class="tax-val" style="color:<?= $superData['bea_pkaa_denda'] > 0 ? '#dc2626' : '#64748b' ?>;">
                <?= formatRupiah($superData['bea_pkaa_denda']) ?>
              </span>
            </div>
            <div class="tax-calc-row" style="font-weight:700; border-top:1px solid #cbd5e1; padding-top:4px;">
              <span>Subtotal Bea PKAA:</span>
              <span class="tax-val" style="color:#1d4ed8;"><?= formatRupiah($superData['bea_pkaa_pokok'] + $superData['bea_pkaa_denda']) ?></span>
            </div>
          </div>

          <!-- OPSEN PKAA -->
          <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:10px;">
            <div style="font-weight:700; color:#1e293b; font-size:12px; margin-bottom:6px;">
              OPSEN PKAA 66% (KAS <?= htmlspecialchars($superData['nama_kabupaten_kota'] ?? 'KAB/KOTA NTT') ?>)
            </div>
            <div class="tax-calc-row">
              <span>Pokok Opsen (66%):</span>
              <span class="tax-val"><?= formatRupiah($superData['opsen_pkaa_pokok']) ?></span>
            </div>
            <div class="tax-calc-row">
              <span>Denda Opsen (66%):</span>
              <span class="tax-val" style="color:<?= $superData['opsen_pkaa_denda'] > 0 ? '#dc2626' : '#64748b' ?>;">
                <?= formatRupiah($superData['opsen_pkaa_denda']) ?>
              </span>
            </div>
            <div class="tax-calc-row" style="font-weight:700; border-top:1px solid #cbd5e1; padding-top:4px;">
              <span>Subtotal Opsen PKAA:</span>
              <span class="tax-val" style="color:#6d28d9;"><?= formatRupiah($superData['opsen_pkaa_pokok'] + $superData['opsen_pkaa_denda']) ?></span>
            </div>
          </div>
        </div>

        <!-- Total Grand Akhir -->
        <div style="margin-top:12px; padding:10px 14px; background:#0f172a; color:#ffffff; border-radius:6px; display:flex; align-items:center; justify-content:space-between;">
          <span style="font-size:13px; font-weight:700; letter-spacing:0.5px;">TOTAL PEMBAYARAN KETETAPAN (POKOK + OPSEN + DENDA):</span>
          <span style="font-family:var(--font-mono); font-size:18px; font-weight:800; color:#fbbf24;"><?= formatRupiah($superData['total_ketetapan']) ?></span>
        </div>
      </div>

      <!-- Form Eksekusi Pelunasan & Pengesahan SKPD -->
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label" for="tgl_bayar">Tanggal Pembayaran <span class="required">*</span></label>
          <input type="date" name="tgl_bayar" id="tgl_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="metode_bayar">Kanal / Metode Setor <span class="required">*</span></label>
          <select name="metode_bayar" id="metode_bayar" class="form-control" required>
            <option value="BANK NTT" selected>BANK NTT (Kas Daerah)</option>
            <option value="TUNAI">TUNAI (Loket Pembayaran Bapenda NTT)</option>
            <option value="TRANSFER KASDA NTT">TRANSFER KAS DAERAH PROV NTT</option>
            <option value="QRIS BANK NTT">QRIS BANK NTT</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="no_resi_bukti">No. Resi Setor / Kode Transaksi</label>
          <input type="text" name="no_resi_bukti" id="no_resi_bukti" class="form-control" placeholder="Contoh: NTT-BPD-2026-98124">
        </div>

        <div class="form-group">
          <label class="form-label" for="pejabat_skpd_id">Pejabat Pengesah SKPD <span class="required">*</span></label>
          <select name="pejabat_skpd_id" id="pejabat_skpd_id" class="form-control" required>
            <?php foreach ($pejabatSkpdList as $pj): ?>
              <option value="<?= $pj['id'] ?>">
                <?= htmlspecialchars($pj['nama_pejabat']) ?> &mdash; <?= htmlspecialchars($pj['jabatan']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="panel-header" style="justify-content: flex-end; gap: 8px; border-top: 1px solid var(--border-medium); border-bottom: none;">
      <?php if (!empty($noSkpdTerbit)): ?>
        <a href="index.php?page=skpd" class="btn btn-secondary">
          <?= renderIcon('plus', '', 14) ?> Transaksi SKPD Baru
        </a>
        <a href="cetak/skpd_print.php?no=<?= urlencode($noSkpdTerbit) ?>" target="_blank" class="btn btn-success" style="padding: 10px 24px; font-weight:700; font-size:14px; box-shadow:0 2px 8px rgba(16,185,129,0.3);">
          <?= renderIcon('printer', '', 16) ?>
          <span>CETAK SKPD SEKARANG (LANDSCAPE)</span>
        </a>
      <?php elseif ($isPajakAktif || $superData['status'] === 'LUNAS'): ?>
        <a href="index.php?page=skpd" class="btn btn-secondary">Transaksi SKPD Lain</a>
        <?php if (!empty($skpdSahAktif['no_skpd'])): ?>
          <a href="cetak/skpd_print.php?no=<?= urlencode($skpdSahAktif['no_skpd']) ?>" target="_blank" class="btn btn-success" style="padding: 8px 18px; font-weight:700;">
            <?= renderIcon('printer', '', 16) ?>
            <span>CETAK SALINAN SKPD (SUDAH LUNAS)</span>
          </a>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" disabled style="cursor:not-allowed; opacity:0.6; font-weight:700; background:#94a3b8; color:#fff;" title="Pajak masih aktif. Penerbitan SKPD baru dinonaktifkan untuk mencegah pendoubelan.">
          <?= renderIcon('x', '', 14) ?> Cetak SKPD Dinonaktifkan (Pajak Masih Aktif)
        </button>
      <?php elseif (empty($superData['status_cetak'])): ?>
        <a href="index.php?page=cetak_ulang_superpkaa" class="btn btn-secondary">Buka Menu Cetak SUPERPKAA</a>
        <a href="cetak/superpkaa_print.php?no=<?= urlencode($superData['no_superpkaa']) ?>" target="_blank" class="btn btn-warning" style="background:#d97706; border-color:#b45309; color:#fff; font-weight:700; padding: 8px 20px;">
          <?= renderIcon('printer', '', 16) ?>
          <span>Cetak Lembar SUPERPKAA Dahulu &rarr;</span>
        </a>
      <?php else: ?>
        <a href="index.php?page=cetak_ulang_superpkaa" class="btn btn-secondary">Kembali</a>
        <button type="submit" class="btn btn-primary" style="padding: 8px 24px; font-weight:700; background:#059669; border-color:#047857;" onclick="return confirm('Konfirmasi penerimaan pembayaran sebesar <?= formatRupiah($superData['total_ketetapan']) ?> dan sahkan SKPD?')">
          <?= renderIcon('check', '', 16) ?>
          <span>PROSES PEMBAYARAN & CETAK SKPD</span>
        </button>
      <?php endif; ?>
    </div>
  </form>
<?php endif; ?>
