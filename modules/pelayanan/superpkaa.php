<?php
// modules/pelayanan/superpkaa.php - Penetapan & Penerbitan SUPERPKAA
$pageTitle = "Penetapan SUPERPKAA";

$selectedNopd = trim($_GET['nopd'] ?? $_POST['no_objek_pajak'] ?? '');
$kapal = null;
$perhitungan = null;
$pesan = '';
$tipePesan = '';

// Ambil data kapal jika NOPD terpilih
if (!empty($selectedNopd)) {
    $stmt = $pdo->prepare("
        SELECT k.*, f.nama_fungsi, f.tarif_persen, f.kategori_tarif, j.nama_jenis,
               kb.nama_kabupaten_kota
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
        $perhitungan = kalkulasiPajakPKAA($pdo, $kapal['total_njkb'], $kapal['kode_fungsi'], $kapal['tg_akhir_pajak'], date('Y-m-d'));
    } else {
        $pesan = "Data objek pajak dengan NOPD <strong>$selectedNopd</strong> tidak ditemukan!";
        $tipePesan = 'danger';
    }
}

// Proses Penerbitan SUPERPKAA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'terbitkan_superpkaa' && $kapal) {
    $noSuper = generateNextSUPER($pdo);
    $tglTerbit = date('Y-m-d');
    $masaAwal = trim($_POST['masa_pajak_awal'] ?? date('Y-m-d'));
    $masaAkhir = trim($_POST['masa_pajak_akhir'] ?? date('Y-m-d', strtotime('+1 year')));
    
    $njkbDasar = (float)$perhitungan['njkb_dasar'];
    $tarifPokok = (float)$perhitungan['tarif_pokok_persen'];
    $beaPokok = (float)$perhitungan['bea_pkaa_pokok'];
    $beaDenda = (float)$perhitungan['bea_pkaa_denda'];
    $opsenPersen = (float)$perhitungan['opsen_persen'];
    $opsenPokok = (float)$perhitungan['opsen_pkaa_pokok'];
    $opsenDenda = (float)$perhitungan['opsen_pkaa_denda'];
    $totalKetetapan = (float)$perhitungan['total_ketetapan'];
    $idPetugas = currentUser()['id'] ?? 1;

    try {
        $stmtIns = $pdo->prepare("
            INSERT INTO transaksi_superpkaa (
                no_superpkaa, no_objek_pajak, tgl_terbit, masa_pajak_awal, masa_pajak_akhir,
                njkb_dasar, tarif_pokok_persen, bea_pkaa_pokok, bea_pkaa_denda,
                opsen_persen, opsen_pkaa_pokok, opsen_pkaa_denda, total_ketetapan,
                status, id_petugas
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'TERBIT', ?
            )
        ");
        $stmtIns->execute([
            $noSuper, $kapal['no_objek_pajak'], $tglTerbit, $masaAwal, $masaAkhir,
            $njkbDasar, $tarifPokok, $beaPokok, $beaDenda,
            $opsenPersen, $opsenPokok, $opsenDenda, $totalKetetapan,
            $idPetugas
        ]);

        // Update status pada kendaraan
        $pdo->prepare("UPDATE kendaraan_air SET status_pajak = 'TERBIT_SUPER' WHERE no_objek_pajak = ?")
            ->execute([$kapal['no_objek_pajak']]);

        setFlash('success', 'SUPERPKAA Berhasil Diterbitkan', "Surat Pemberitahuan Pajak Kendaraan di Atas Air Nomor: <strong>$noSuper</strong> berhasil diterbitkan!");
        $pesan = "Surat Pemberitahuan Pajak Kendaraan di Atas Air (SUPERPKAA) Nomor <strong>$noSuper</strong> berhasil diterbitkan!";
        $tipePesan = 'success';
        $noSuperTerbit = $noSuper;
    } catch (Exception $e) {
        setFlash('error', 'Gagal Menerbitkan', $e->getMessage());
        $pesan = "Gagal menerbitkan SUPERPKAA: " . $e->getMessage();
        $tipePesan = 'danger';
    }
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('file-text', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Penetapan Surat Pemberitahuan Pajak Kendaraan di Atas Air (SUPERPKAA)</div>
      <div class="page-subtitle">Ketetapan Pokok PKAA, Opsen 66% (UU HKPD), dan Sanksi Administrasi Keterlambatan</div>
    </div>
  </div>
  <div class="page-header-actions">
    <button type="button" class="btn btn-secondary btn-sm" onclick="bukaLayarBantu('nopd_search')">
      <?= renderIcon('search', '', 14) ?> Cari Objek (Ctrl+B)
    </button>
  </div>
</div>

<?php if (!empty($pesan)): ?>
  <div class="alert alert-<?= $tipePesan ?>">
    <?= renderIcon($tipePesan === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
    <div style="display:flex; align-items:center; justify-content:space-between; width:100%;">
      <span><?= $pesan ?></span>
      <?php if (!empty($noSuperTerbit)): ?>
        <a href="cetak/superpkaa_print.php?no=<?= urlencode($noSuperTerbit) ?>" target="_blank" class="btn btn-primary btn-sm">
          <?= renderIcon('printer', '', 14) ?> Cetak Lembar SUPERPKAA
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Form Pencarian NOPD -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-body">
    <form method="GET" action="index.php" id="formCariKaa" style="display:flex; gap:10px; align-items:flex-end;">
      <input type="hidden" name="page" value="superpkaa">
      <div class="form-group" style="flex:1;">
        <label class="form-label" for="nopd_search">Masukkan Nomor Objek Pajak (NOPD)</label>
        <div class="input-group">
          <input type="text" name="nopd" id="nopd_search" class="form-control" value="<?= htmlspecialchars($selectedNopd) ?>" placeholder="Contoh: 133000054" required>
          <button type="button" class="btn btn-secondary" onclick="bukaLayarBantu('nopd_search')">
            <?= renderIcon('search', '', 14) ?> Layar Bantu (Ctrl+B)
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="height:32px;">
        <?= renderIcon('search', '', 14) ?> Cari & Hitung
      </button>
    </form>
  </div>
</div>

<?php if ($kapal && $perhitungan): ?>
  <!-- Panel Rincian Objek & Kalkulasi Pajak -->
  <form method="POST" action="index.php?page=superpkaa&nopd=<?= urlencode($kapal['no_objek_pajak']) ?>" class="panel">
    <input type="hidden" name="aksi" value="terbitkan_superpkaa">
    <input type="hidden" name="no_objek_pajak" value="<?= htmlspecialchars($kapal['no_objek_pajak']) ?>">

    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('ship', '', 16) ?>
        <span>DATA OBJEK PAJAK: <?= htmlspecialchars($kapal['nama_kapal']) ?> (NOPD: <?= htmlspecialchars($kapal['no_objek_pajak']) ?>)</span>
      </div>
      <span class="badge badge-info">Fungsi: <?= htmlspecialchars($kapal['nama_fungsi']) ?> (Tarif: <?= $kapal['tarif_persen'] ?>%)</span>
    </div>

    <div class="panel-body">
      <!-- Grid 2 Kolom: Detail Objek & Ketetapan -->
      <div class="form-grid-2">
        <!-- Kolom Kiri: Spesifikasi Objek -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">
          <div style="font-weight:700; color:#334155; margin-bottom:10px; font-size:12px; border-bottom:1px solid #cbd5e1; padding-bottom:4px;">
            IDENTITAS & SPESIFIKASI KAPAL
          </div>
          <table class="table-desktop" style="border:none;">
            <tbody>
              <tr><td width="140"><strong>Nopol KA</strong></td><td><strong style="color:#0284c7; font-family:var(--font-mono); font-size:13px;"><?= htmlspecialchars($kapal['no_objek_pajak']) ?></strong></td></tr>
              <tr><td><strong>Asal Kab/Kota NTT</strong></td><td><span class="badge badge-info"><?= htmlspecialchars($kapal['kode_kabkota'] ?: 'NTT') ?></span> <?= htmlspecialchars($kapal['nama_kabupaten_kota'] ?? '-') ?></td></tr>
              <tr><td><strong>Nama Kapal / SB</strong></td><td><strong><?= htmlspecialchars($kapal['nama_kapal']) ?></strong></td></tr>
              <tr><td><strong>Tanda Selar</strong></td><td><?= htmlspecialchars($kapal['tanda_selar'] ?: '-') ?></td></tr>
              <tr><td><strong>No. Ijin Operasi</strong></td><td><?= htmlspecialchars($kapal['no_ijin_operasi'] ?: '-') ?></td></tr>
              <tr><td><strong>Nama Pemilik</strong></td><td><?= htmlspecialchars($kapal['nama_pemilik']) ?></td></tr>
              <tr><td><strong>Alamat Pemilik</strong></td><td><?= htmlspecialchars($kapal['alamat_pemilik']) ?></td></tr>
              <tr><td><strong>Jenis & Bahan</strong></td><td><?= htmlspecialchars($kapal['nama_jenis']) ?> / <?= htmlspecialchars($kapal['bahan_kapal']) ?></td></tr>
              <tr><td><strong>Kapasitas & Mesin</strong></td><td><?= htmlspecialchars($kapal['kapasitas_gt']) ?> GT &bull; <?= $kapal['jumlah_mesin'] ?> Unit Mesin</td></tr>
              <tr><td><strong>Pelabuhan Pangkal</strong></td><td><?= htmlspecialchars($kapal['pelabuhan_pangkal']) ?></td></tr>
              <tr>
                <td><strong>Metode NJKB</strong></td>
                <td>
                  <?php if (($kapal['metode_njkb'] ?? '') === 'GABUNGAN'): ?>
                    <span class="badge badge-warning">1 Kesatuan (Body & Mesin Paket)</span>
                  <?php else: ?>
                    <span class="badge badge-neutral">Terpisah (Body & Mesin Sendiri-Sendiri)</span>
                  <?php endif; ?>
                </td>
              </tr>
              <tr><td><strong>Masa Pajak Berjalan</strong></td><td>s/d <?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?></td></tr>
              <tr style="background:#f1f5f9;">
                <td><strong>NJKB TOTAL</strong></td>
                <td><strong style="color:#0284c7; font-size:14px;"><?= formatRupiah($kapal['total_njkb']) ?></strong></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Kolom Kanan: Rincian Kalkulasi Ketetapan Pajak -->
        <div>
          <div class="form-grid-2" style="margin-bottom: 12px;">
            <div class="form-group">
              <label class="form-label">Masa Pajak Awal <span class="required">*</span></label>
              <input type="date" name="masa_pajak_awal" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Masa Pajak Akhir <span class="required">*</span></label>
              <input type="date" name="masa_pajak_akhir" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
            </div>
          </div>

          <div class="tax-calc-box">
            <div style="font-weight:700; color:#0f172a; margin-bottom:8px; font-size:12px; border-bottom:1px solid #cbd5e1; padding-bottom:4px;">
              RINCIAN PERHITUNGAN FISKAL (PERDA PROV. NTT & UU HKPD)
            </div>

            <div class="tax-calc-row">
              <span>Nilai Jual Kendaraan di Atas Air (NJKB):</span>
              <span class="tax-val"><?= formatRupiah($perhitungan['njkb_dasar']) ?></span>
            </div>

            <div class="tax-calc-row">
              <span>Tarif Pokok PKAA (Fungsi <?= htmlspecialchars($perhitungan['nama_fungsi']) ?>):</span>
              <span class="tax-val"><?= $perhitungan['tarif_pokok_persen'] ?>%</span>
            </div>

            <div class="tax-calc-row" style="background:#eff6ff; padding:4px 6px;">
              <span><strong>1. Bea PKAA Pokok:</strong></span>
              <span class="tax-val" style="color:#1d4ed8;"><?= formatRupiah($perhitungan['bea_pkaa_pokok']) ?></span>
            </div>

            <div class="tax-calc-row">
              <span>
                Sanksi Administrasi / Denda Keterlambatan:
                <?php if ($perhitungan['bulan_terlambat'] > 0): ?>
                  <span class="badge badge-danger"><?= $perhitungan['bulan_terlambat'] ?> Bulan &times; 1%</span>
                <?php else: ?>
                  <span class="badge badge-success">Tepat Waktu (0%)</span>
                <?php endif; ?>
              </span>
              <span class="tax-val" style="color:<?= $perhitungan['bea_pkaa_denda'] > 0 ? '#dc2626' : '#64748b' ?>;">
                <?= formatRupiah($perhitungan['bea_pkaa_denda']) ?>
              </span>
            </div>

            <div class="tax-calc-row" style="background:#f5f3ff; padding:4px 6px;">
              <span><strong>2. Opsen PKAA Pokok (66% dari Bea Pokok):</strong></span>
              <span class="tax-val" style="color:#6d28d9;"><?= formatRupiah($perhitungan['opsen_pkaa_pokok']) ?></span>
            </div>

            <?php if ($perhitungan['opsen_pkaa_denda'] > 0): ?>
              <div class="tax-calc-row">
                <span>Denda Opsen PKAA (66% dari Denda Pokok):</span>
                <span class="tax-val" style="color:#dc2626;"><?= formatRupiah($perhitungan['opsen_pkaa_denda']) ?></span>
              </div>
            <?php endif; ?>

            <div class="tax-calc-row total">
              <span>TOTAL KETETAPAN PAJAK (SUPERPKAA):</span>
              <span class="tax-val" style="color:#0f172a; font-size:16px;">
                <?= formatRupiah($perhitungan['total_ketetapan']) ?>
              </span>
            </div>
            <div style="font-size:10px; color:#64748b; margin-top:4px; font-style:italic;">
              Terbilang: <strong><?= terbilang($perhitungan['total_ketetapan']) ?> Rupiah</strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="panel-header" style="justify-content: flex-end; gap: 8px; border-top: 1px solid var(--border-medium); border-bottom: none;">
      <?php if (!empty($noSuperTerbit)): ?>
        <a href="index.php?page=superpkaa" class="btn btn-secondary">
          <?= renderIcon('plus', '', 14) ?> Buat Penetapan Baru
        </a>
        <a href="cetak/superpkaa_print.php?no=<?= urlencode($noSuperTerbit) ?>" target="_blank" class="btn btn-success" style="padding: 10px 22px; font-weight:700; font-size:14px; box-shadow:0 2px 8px rgba(16,185,129,0.3);">
          <?= renderIcon('printer', '', 16) ?>
          <span>CETAK LEMBAR SUPERPKAA SEKARANG</span>
        </a>
      <?php else: ?>
        <a href="index.php?page=pendaftaran" class="btn btn-secondary">Koreksi Data Kapal</a>
        <button type="submit" class="btn btn-primary" style="padding: 8px 18px;" onclick="return confirm('Apakah Anda yakin ingin menerbitkan SUPERPKAA ini?')">
          <?= renderIcon('file-text', '', 16) ?>
          <span>SIMPAN & TERBITKAN SUPERPKAA</span>
        </button>
      <?php endif; ?>
    </div>
  </form>
<?php endif; ?>
