<?php
// modules/pelayanan/riwayat_cetak_superpkaa.php - Menu Riwayat Cetak Lembar Resmi SUPERPKAA
$pageTitle = "Riwayat Cetak SUPERPKAA";

$search = trim($_GET['q'] ?? '');
$tglAwal = trim($_GET['tgl_awal'] ?? date('Y-m-01'));
$tglAkhir = trim($_GET['tgl_akhir'] ?? date('Y-m-d'));

$sql = "
    SELECT l.*, s.total_ketetapan, s.tgl_terbit, k.kode_kabkota, kb.nama_kabupaten_kota
    FROM log_cetak_superpkaa l
    LEFT JOIN transaksi_superpkaa s ON l.no_superpkaa = s.no_superpkaa
    LEFT JOIN kendaraan_air k ON l.no_objek_pajak = k.no_objek_pajak
    LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
    WHERE DATE(l.tgl_cetak) BETWEEN ? AND ?
";
$params = [$tglAwal, $tglAkhir];

if (!empty($search)) {
    $sql .= " AND (l.no_superpkaa LIKE ? OR l.no_objek_pajak LIKE ? OR l.nama_kapal LIKE ? OR l.nama_pemilik LIKE ?) ";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

$sql .= " ORDER BY l.tgl_cetak DESC, l.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('history', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Riwayat Cetak Lembar Surat Pemberitahuan (SUPERPKAA)</div>
      <div class="page-subtitle">Daftar Audit Trail & Arsip Cetak Lembar Resmi Penetapan Pajak Kendaraan di Atas Air JANGKAR NTT</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="index.php?page=superpkaa" class="btn btn-primary btn-sm">
      <?= renderIcon('file-text', '', 14) ?> Buat Penetapan Baru
    </a>
  </div>
</div>

<!-- Filter Panel -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('filter', '', 16) ?>
      <span>FILTER ARSIP RIWAYAT CETAK</span>
    </div>
    <span class="badge badge-info">Total Dokumen: <?= count($logs) ?> Cetakan</span>
  </div>
  <div class="panel-body">
    <form method="GET" action="index.php" style="display:grid; grid-template-columns: 1fr 1fr 1.5fr auto; gap:12px; align-items:flex-end;">
      <input type="hidden" name="page" value="riwayat_cetak_superpkaa">
      
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" for="tgl_awal">Tanggal Cetak Awal</label>
        <input type="date" name="tgl_awal" id="tgl_awal" class="form-control" value="<?= htmlspecialchars($tglAwal) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" for="tgl_akhir">Tanggal Cetak Akhir</label>
        <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tglAkhir) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" for="search_q">Cari NOPD / Nama Kapal / No. SUPER</label>
        <input type="text" name="q" id="search_q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Contoh: KA 0001 MB atau KOMODO">
      </div>

      <div style="display:flex; gap:6px;">
        <button type="submit" class="btn btn-primary" style="height:32px;">
          <?= renderIcon('search', '', 14) ?> Tampilkan
        </button>
        <a href="index.php?page=riwayat_cetak_superpkaa" class="btn btn-secondary" style="height:32px;">
          Reset
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Tabel Riwayat Cetak -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('printer', '', 16) ?>
      <span>DAFTAR LOG RIWAYAT CETAK SUPERPKAA</span>
    </div>
  </div>
  <div class="panel-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table-desktop">
        <thead>
          <tr>
            <th width="40">NO</th>
            <th>WAKTU CETAK</th>
            <th>NO. SUPERPKAA</th>
            <th>NOPD (NOPOL KA)</th>
            <th>NAMA KAPAL</th>
            <th>WAJIB PAJAK</th>
            <th>WILAYAH KAB/KOTA</th>
            <th align="right">NILAI KETETAPAN</th>
            <th>PETUGAS PENCETAK</th>
            <th width="120" align="center">CETAK ULANG</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="10" align="center" style="padding:24px; color:#64748b;">
                Tidak ada data riwayat cetak yang sesuai dengan parameter filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $idx => $l): ?>
              <tr>
                <td align="center"><?= $idx + 1 ?></td>
                <td style="font-size:11.5px; font-family:var(--font-mono);">
                  <?= date('d/m/Y H:i', strtotime($l['tgl_cetak'])) ?>
                </td>
                <td><strong style="color:#0284c7;"><?= htmlspecialchars($l['no_superpkaa']) ?></strong></td>
                <td><strong style="font-family:var(--font-mono);"><?= htmlspecialchars($l['no_objek_pajak']) ?></strong></td>
                <td><strong><?= htmlspecialchars($l['nama_kapal']) ?></strong></td>
                <td><?= htmlspecialchars($l['nama_pemilik']) ?></td>
                <td>
                  <span class="badge badge-info" style="font-size:10px;">
                    <?= htmlspecialchars($l['nama_kabupaten_kota'] ?? $l['kode_kabkota'] ?? 'NTT') ?>
                  </span>
                </td>
                <td align="right" style="font-weight:700; color:#065f46;">
                  <?= formatRupiah($l['total_ketetapan'] ?? 0) ?>
                </td>
                <td>
                  <span class="badge badge-neutral" style="font-size:11px;">
                    <?= htmlspecialchars($l['dicetak_oleh_nama']) ?>
                  </span>
                </td>
                <td align="center">
                  <a href="cetak/superpkaa_print.php?no=<?= urlencode($l['no_superpkaa']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:4px;">
                    <?= renderIcon('printer', '', 12) ?> Cetak
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
