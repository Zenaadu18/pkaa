<?php
// modules/dashboard/index.php - Beranda Operasional SIMPAB PKAA Tarakan
$pageTitle = "Beranda Operasional";

// Mengambil metrik statistik langsung dari database
$totalKapal = $pdo->query("SELECT COUNT(*) FROM kendaraan_air WHERE status_aktif = 1")->fetchColumn();
$totalSuper = $pdo->query("SELECT COUNT(*) FROM transaksi_superpkaa")->fetchColumn();
$penerimaanPkaa = $pdo->query("SELECT COALESCE(SUM(bea_pkaa_pokok_bayar + bea_pkaa_denda_bayar), 0) FROM transaksi_skpd")->fetchColumn();
$penerimaanOpsen = $pdo->query("SELECT COALESCE(SUM(opsen_pkaa_pokok_bayar + opsen_pkaa_denda_bayar), 0) FROM transaksi_skpd")->fetchColumn();
$totalPenerimaan = $penerimaanPkaa + $penerimaanOpsen;

$totalJatuhTempo = $pdo->query("SELECT COUNT(*) FROM kendaraan_air WHERE tg_akhir_pajak < CURDATE() AND status_pajak != 'LUNAS'")->fetchColumn();

// Transaksi Terbaru
$stmtRecent = $pdo->query("
    SELECT s.no_superpkaa, s.no_objek_pajak, s.tgl_terbit, s.total_ketetapan, s.status,
           k.nama_kapal, k.nama_pemilik, f.nama_fungsi, f.tarif_persen
    FROM transaksi_superpkaa s
    JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
    JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
    ORDER BY s.created_at DESC
    LIMIT 6
");
$recentTrans = $stmtRecent->fetchAll();

// Daftar Kendaraan Menunggak / Perlu Perhatian
$stmtTunggakan = $pdo->query("
    SELECT k.no_objek_pajak, k.nama_kapal, k.nama_pemilik, k.tg_akhir_pajak, k.total_njkb,
           f.nama_fungsi, f.tarif_persen, DATEDIFF(CURDATE(), k.tg_akhir_pajak) as hari_lewat
    FROM kendaraan_air k
    JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
    WHERE k.tg_akhir_pajak < CURDATE() AND k.status_pajak != 'LUNAS'
    ORDER BY k.tg_akhir_pajak ASC
    LIMIT 5
");
$tunggakanList = $stmtTunggakan->fetchAll();
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon" style="background:#e0f2fe; color:#0284c7; border-color:#bae6fd;">
      <?= renderIcon('anchor', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Beranda Operasional JANGKAR NTT</div>
      <div class="page-subtitle">Jaringan Administrasi Notifikasi & Ketetapan Pajak Kendaraan Air &bull; BPAD Prov. Nusa Tenggara Timur</div>
    </div>
  </div>
  <div class="page-header-actions">
    <button type="button" class="btn btn-secondary btn-sm" onclick="bukaLayarBantu()">
      <?= renderIcon('search', '', 14) ?> Layar Bantu NOPD (Ctrl+B)
    </button>
    <a href="index.php?page=pendaftaran" class="btn btn-primary btn-sm">
      <?= renderIcon('ship', '', 14) ?> Pendaftaran Baru (F2)
    </a>
  </div>
</div>

<!-- Grid Metrik Statistik -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
  <!-- Total Kapal -->
  <div class="panel" style="border-left: 4px solid #0284c7;">
    <div class="panel-body" style="padding: 12px 14px;">
      <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Objek Pajak KAA</div>
      <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 4px;">
        <span style="font-size: 22px; font-weight: 800; color: #0f172a;"><?= number_format($totalKapal) ?></span>
        <span class="badge badge-info"><?= renderIcon('ship', '', 12) ?> Unit Terdaftar</span>
      </div>
      <div style="font-size: 11px; color: #64748b; margin-top: 4px;">BPAD Provinsi Nusa Tenggara Timur</div>
    </div>
  </div>

  <!-- SUPERPKAA Terbit -->
  <div class="panel" style="border-left: 4px solid #d97706;">
    <div class="panel-body" style="padding: 12px 14px;">
      <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">SUPERPKAA Diterbitkan</div>
      <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 4px;">
        <span style="font-size: 22px; font-weight: 800; color: #0f172a;"><?= number_format($totalSuper) ?></span>
        <span class="badge badge-warning"><?= renderIcon('file-text', '', 12) ?> Ketetapan</span>
      </div>
      <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Tahun Berjalan 2026</div>
    </div>
  </div>

  <!-- Realisasi PKAA Pokok -->
  <div class="panel" style="border-left: 4px solid #059669;">
    <div class="panel-body" style="padding: 12px 14px;">
      <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Penerimaan Kas PKAA Pokok</div>
      <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 4px;">
        <span style="font-size: 18px; font-weight: 800; color: #059669;"><?= formatRupiah($penerimaanPkaa) ?></span>
        <span class="badge badge-success"><?= renderIcon('check-circle', '', 12) ?> Kasda Prov</span>
      </div>
      <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Termasuk Denda Keterlambatan</div>
    </div>
  </div>

  <!-- Realisasi Opsen PKAA -->
  <div class="panel" style="border-left: 4px solid #7c3aed;">
    <div class="panel-body" style="padding: 12px 14px;">
      <div style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Penerimaan Opsen PKAA (66%)</div>
      <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 4px;">
        <span style="font-size: 18px; font-weight: 800; color: #7c3aed;"><?= formatRupiah($penerimaanOpsen) ?></span>
        <span class="badge badge-neutral" style="background:#ede9fe; color:#6d28d9; border-color:#ddd6fe;">
          <?= renderIcon('coins', '', 11) ?> UU HKPD
        </span>
      </div>
      <div style="font-size: 11px; color: #64748b; margin-top: 4px;">Bagi Hasil 22 Kab/Kota Se-NTT</div>
    </div>
  </div>
</div>

<!-- Layout Dua Kolom: Transaksi Terakhir & Daftar Jatuh Tempo -->
<div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 14px;">
  <!-- Transaksi Terakhir -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('file-text', '', 16) ?>
        <span>Ketetapan SUPERPKAA & Transaksi Terkini</span>
      </div>
      <a href="index.php?page=superpkaa" class="btn btn-secondary btn-sm">Penetapan Baru</a>
    </div>
    <div class="panel-body" style="padding: 0;">
      <div class="table-responsive" style="border: none;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th>No. SUPERPKAA</th>
              <th>Nama Kapal / NOPD</th>
              <th>Fungsi / Tarif</th>
              <th class="text-right">Total Ketetapan</th>
              <th class="text-center">Status</th>
              <th class="text-center" width="70">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentTrans)): ?>
              <tr>
                <td colspan="6" class="text-center" style="padding: 20px; color: #94a3b8;">
                  Belum ada transaksi penetapan.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentTrans as $tr): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($tr['no_superpkaa']) ?></strong><br>
                    <span style="font-size: 10px; color: #64748b;"><?= formatTanggalIndo($tr['tgl_terbit'], false) ?></span>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($tr['nama_kapal']) ?></strong><br>
                    <span style="font-size: 11px; color: #475569;"><?= htmlspecialchars($tr['no_objek_pajak']) ?> &bull; <?= htmlspecialchars($tr['nama_pemilik']) ?></span>
                  </td>
                  <td>
                    <span class="badge badge-neutral"><?= htmlspecialchars($tr['nama_fungsi']) ?> (<?= $tr['tarif_persen'] ?>%)</span>
                  </td>
                  <td class="text-right">
                    <strong><?= formatRupiah($tr['total_ketetapan']) ?></strong>
                  </td>
                  <td class="text-center">
                    <?php if ($tr['status'] === 'LUNAS'): ?>
                      <span class="badge badge-success"><?= renderIcon('check', '', 12) ?> LUNAS</span>
                    <?php else: ?>
                      <span class="badge badge-info"><?= renderIcon('file', '', 12) ?> TERBIT</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <a href="cetak/superpkaa_print.php?no=<?= urlencode($tr['no_superpkaa']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Cetak Lembar SUPERPKAA">
                      <?= renderIcon('printer', '', 13) ?>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Kolom Kanan: Notifikasi KAA Jatuh Tempo / Nunggak -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title" style="color: #b91c1c;">
        <?= renderIcon('alert-triangle', '', 16) ?>
        <span>KAA Melewati Jatuh Tempo Pajak</span>
      </div>
      <span class="badge badge-danger"><?= count($tunggakanList) ?> Unit</span>
    </div>
    <div class="panel-body" style="padding: 0;">
      <div class="table-responsive" style="border: none;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th>Kapal / Pemilik</th>
              <th>Jatuh Tempo</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($tunggakanList)): ?>
              <tr>
                <td colspan="3" class="text-center" style="padding: 20px; color: #94a3b8;">
                  Tidak ada kendaraan air yang menunggak saat ini.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($tunggakanList as $tg): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($tg['nama_kapal']) ?></strong><br>
                    <span style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($tg['no_objek_pajak']) ?> &bull; <?= htmlspecialchars($tg['nama_pemilik']) ?></span>
                  </td>
                  <td>
                    <span style="color: #dc2626; font-weight: 700;"><?= formatTanggalIndo($tg['tg_akhir_pajak'], false) ?></span><br>
                    <span style="font-size: 10px; color: #dc2626;">Lewat <?= $tg['hari_lewat'] ?> hari</span>
                  </td>
                  <td class="text-center">
                    <a href="index.php?page=superpkaa&nopd=<?= urlencode($tg['no_objek_pajak']) ?>" class="btn btn-accent btn-sm" title="Terbitkan Ketetapan + Denda">
                      Tetapkan
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div style="padding: 8px 12px; background: #fffbeb; border-top: 1px solid #fef3c7; font-size: 11px; color: #92400e;">
      <?= renderIcon('info', '', 13) ?>
      Denda keterlambatan dihitung otomatis <strong>1% per bulan</strong> saat SUPERPKAA atau SKPD diterbitkan.
    </div>
  </div>
</div>
