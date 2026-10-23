<?php
// modules/pelayanan/cetak_ulang_superpkaa.php - Pelayanan Cetak & Arsip SUPERPKAA JANGKAR NTT
$pageTitle = "Cetak Dokumen SUPERPKAA";

$search = trim($_GET['q'] ?? '');
$tab = trim($_GET['tab'] ?? 'siap_cetak'); // Default langsung fokus ke data yang siap dicetak

// Hitung total siap cetak vs sudah dicetak untuk badge tabs
$countSiapCetak = (int)$pdo->query("SELECT COUNT(*) FROM transaksi_superpkaa WHERE status_cetak = 0")->fetchColumn();
$countSudahCetak = (int)$pdo->query("SELECT COUNT(*) FROM transaksi_superpkaa WHERE status_cetak = 1")->fetchColumn();
$countSemua = $countSiapCetak + $countSudahCetak;

// Bangun query filter
$sql = "
    SELECT s.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, f.nama_fungsi, j.nama_jenis
    FROM transaksi_superpkaa s
    JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
    JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
    JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
    WHERE 1=1
";

$params = [];

if ($tab === 'siap_cetak') {
    $sql .= " AND s.status_cetak = 0 ";
} elseif ($tab === 'sudah_cetak') {
    $sql .= " AND s.status_cetak = 1 ";
}

if (!empty($search)) {
    $sql .= " AND (s.no_superpkaa LIKE ? OR s.no_objek_pajak LIKE ? OR k.nama_kapal LIKE ? OR k.nama_pemilik LIKE ?) ";
    $paramLike = "%$search%";
    $params = [$paramLike, $paramLike, $paramLike, $paramLike];
}

$sql .= " ORDER BY s.status_cetak ASC, s.tgl_terbit DESC, s.created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listSuper = $stmt->fetchAll();
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('file-text', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Cetak Surat Pemberitahuan Pajak KAA (SUPERPKAA)</div>
      <div class="page-subtitle">Pencetakan Ketetapan Pokok Pajak KAA & Opsen 66% (Wajib Dicetak Sebelum Penerbitan SKPD)</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="index.php?page=pendaftaran" class="btn btn-secondary btn-sm">
      <?= renderIcon('plus', '', 14) ?> Pendaftaran Baru
    </a>
  </div>
</div>

<!-- Tabs Status Cetak -->
<div class="tabs-nav" style="display:flex; gap:8px; margin-bottom:14px; border-bottom: 2px solid var(--border-light); padding-bottom: 8px;">
  <a href="index.php?page=cetak_ulang_superpkaa&tab=siap_cetak<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
     class="btn btn-sm <?= $tab === 'siap_cetak' ? 'btn-primary' : 'btn-secondary' ?>" 
     style="font-weight: 700; display:flex; align-items:center; gap:6px;">
    <?= renderIcon('printer', '', 14) ?>
    <span>Siap Cetak (Belum Dicetak)</span>
    <span class="badge" style="background: <?= $tab === 'siap_cetak' ? '#fff' : '#ef4444' ?>; color: <?= $tab === 'siap_cetak' ? 'var(--primary-700)' : '#fff' ?>; font-size:11px; padding:1px 7px; border-radius:10px;">
      <?= $countSiapCetak ?>
    </span>
  </a>

  <a href="index.php?page=cetak_ulang_superpkaa&tab=sudah_cetak<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
     class="btn btn-sm <?= $tab === 'sudah_cetak' ? 'btn-primary' : 'btn-secondary' ?>" 
     style="font-weight: 700; display:flex; align-items:center; gap:6px;">
    <?= renderIcon('check-circle', '', 14) ?>
    <span>Sudah Dicetak (Riwayat / Cetak Ulang)</span>
    <span class="badge" style="background: <?= $tab === 'sudah_cetak' ? '#fff' : '#64748b' ?>; color: <?= $tab === 'sudah_cetak' ? 'var(--primary-700)' : '#fff' ?>; font-size:11px; padding:1px 7px; border-radius:10px;">
      <?= $countSudahCetak ?>
    </span>
  </a>

  <a href="index.php?page=cetak_ulang_superpkaa&tab=semua<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
     class="btn btn-sm <?= $tab === 'semua' ? 'btn-primary' : 'btn-secondary' ?>" 
     style="display:flex; align-items:center; gap:6px;">
    <?= renderIcon('layers', '', 14) ?>
    <span>Semua Rekaman (<?= $countSemua ?>)</span>
  </a>
</div>

<!-- Form Filter & Pencarian -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-body">
    <form method="GET" action="index.php" style="display:flex; gap:10px;">
      <input type="hidden" name="page" value="cetak_ulang_superpkaa">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="form-group" style="flex:1;">
        <div class="input-group">
          <input type="text" name="q" class="form-control" placeholder="Cari berdasarkan No. SUPERPKAA, NOPD, Nama Kapal, atau Pemilik..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-primary">
            <?= renderIcon('search', '', 14) ?> Cari Ketetapan
          </button>
        </div>
      </div>
      <?php if (!empty($search)): ?>
        <a href="index.php?page=cetak_ulang_superpkaa&tab=<?= htmlspecialchars($tab) ?>" class="btn btn-secondary">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Tabel Hasil Arsip SUPERPKAA -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('file-text', '', 16) ?>
      <span>
        <?= $tab === 'siap_cetak' ? 'Daftar SUPERPKAA Siap Dicetak' : ($tab === 'sudah_cetak' ? 'Daftar SUPERPKAA Sudah Dicetak' : 'Seluruh Ketetapan SUPERPKAA') ?> 
        (<?= count($listSuper) ?> Rekaman)
      </span>
    </div>
    <span class="badge badge-info">Alur Pelayanan: Pendaftaran &rarr; Cetak SUPERPKAA &rarr; Cetak SKPD</span>
  </div>

  <div class="panel-body" style="padding: 0;">
    <div class="table-responsive" style="border: none;">
      <table class="table-desktop">
        <thead>
          <tr>
            <th width="150">No. SUPERPKAA</th>
            <th width="90">Tgl. Terbit</th>
            <th>NOPD / Kapal</th>
            <th>Wajib Pajak</th>
            <th>Fungsi</th>
            <th class="text-right">Pokok PKAA</th>
            <th class="text-right">Opsen (66%)</th>
            <th class="text-right">Total Ketetapan</th>
            <th class="text-center" width="120">Status Dokumen</th>
            <th class="text-center" width="180">Aksi Pelayanan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($listSuper)): ?>
            <tr>
              <td colspan="10" class="text-center" style="padding: 32px; color: #94a3b8;">
                <div style="font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 6px;">
                  Tidak ada data SUPERPKAA pada tab <?= htmlspecialchars($tab) ?>.
                </div>
                <div style="font-size: 12px;">
                  Semua kendaraan baru yang terdaftar & di-ACC otomatis akan langsung muncul di tab <strong>Siap Cetak</strong>.
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($listSuper as $row): ?>
              <tr style="<?= empty($row['status_cetak']) ? 'background: #f0fdf4;' : '' ?>">
                <td><strong><?= htmlspecialchars($row['no_superpkaa']) ?></strong></td>
                <td><?= formatTanggalIndo($row['tgl_terbit'], false) ?></td>
                <td>
                  <strong><?= htmlspecialchars($row['nama_kapal']) ?></strong><br>
                  <span style="font-size: 11px; color: #0284c7; font-weight:600;"><?= htmlspecialchars($row['no_objek_pajak']) ?></span>
                </td>
                <td><?= htmlspecialchars($row['nama_pemilik']) ?></td>
                <td><span class="badge badge-neutral"><?= htmlspecialchars($row['nama_fungsi']) ?> (<?= $row['tarif_pokok_persen'] ?>%)</span></td>
                <td class="text-right"><?= formatRupiah($row['bea_pkaa_pokok']) ?></td>
                <td class="text-right"><?= formatRupiah($row['opsen_pkaa_pokok']) ?></td>
                <td class="text-right"><strong><?= formatRupiah($row['total_ketetapan']) ?></strong></td>
                
                <td class="text-center">
                  <?php if (!empty($row['status_cetak'])): ?>
                    <span class="badge badge-success" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-size:11px;">
                      <?= renderIcon('check', '', 12) ?> Sudah Dicetak
                    </span>
                    <?php if (!empty($row['tgl_cetak_pertama'])): ?>
                      <span style="font-size:10px; color:#64748b; display:block; margin-top:2px;">
                        <?= date('d/m/Y H:i', strtotime($row['tgl_cetak_pertama'])) ?>
                      </span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge badge-warning" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; font-size:11px;">
                      <?= renderIcon('clock', '', 12) ?> BELUM DICETAK
                    </span>
                  <?php endif; ?>
                </td>

                <td class="text-center">
                  <div style="display:flex; justify-content:center; gap:6px; flex-wrap:wrap;">
                    <?php if (empty($row['status_cetak'])): ?>
                      <!-- Tombol Utama Cetak Pertama Kali -->
                      <a href="cetak/superpkaa_print.php?no=<?= urlencode($row['no_superpkaa']) ?>" target="_blank" class="btn btn-sm btn-primary" style="background:#059669; border-color:#047857; font-weight:700; padding:5px 12px;" title="Cetak Lembar Penetapan SUPERPKAA">
                        <?= renderIcon('printer', '', 14) ?> Cetak SUPER
                      </a>
                    <?php else: ?>
                      <!-- Tombol Cetak Ulang & Lanjut SKPD -->
                      <a href="cetak/superpkaa_print.php?no=<?= urlencode($row['no_superpkaa']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Cetak Ulang Lembar SUPERPKAA">
                        <?= renderIcon('printer', '', 13) ?> Salinan
                      </a>
                      <?php if ($row['status'] !== 'LUNAS'): ?>
                        <a href="index.php?page=skpd&super=<?= urlencode($row['no_superpkaa']) ?>" class="btn btn-primary btn-sm" title="Lanjut Pembayaran SKPD">
                          <?= renderIcon('credit-card', '', 13) ?> Ke SKPD &rarr;
                        </a>
                      <?php else: ?>
                        <span class="badge badge-success" style="font-size:10px;"><?= renderIcon('check', '', 10) ?> LUNAS</span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
