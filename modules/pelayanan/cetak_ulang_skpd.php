<?php
// modules/pelayanan/cetak_ulang_skpd.php - Pelayanan Cetak & Arsip SKPD JANGKAR NTT
$pageTitle = "Cetak SKPD PKAA";

$search = trim($_GET['q'] ?? '');
$tab = trim($_GET['tab'] ?? 'siap_skpd'); // Default: Siap Cetak SKPD (SUPERPKAA sudah dicetak)

// Hitung total count untuk badge
$countSiapSkpd = (int)$pdo->query("SELECT COUNT(*) FROM transaksi_superpkaa WHERE status_cetak = 1 AND status != 'LUNAS'")->fetchColumn();
$countArsipSkpd = (int)$pdo->query("SELECT COUNT(*) FROM transaksi_skpd")->fetchColumn();

$listSiapSkpd = [];
$listArsipSkpd = [];

if ($tab === 'siap_skpd') {
    // Objek yang SUPERPKAA-nya SUDAH dicetak dan siap diproses / dicetak SKPD
    $sql = "
        SELECT s.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, f.nama_fungsi, j.nama_jenis
        FROM transaksi_superpkaa s
        JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        WHERE s.status_cetak = 1 AND s.status != 'LUNAS'
    ";
    $params = [];
    if (!empty($search)) {
        $sql .= " AND (s.no_superpkaa LIKE ? OR s.no_objek_pajak LIKE ? OR k.nama_kapal LIKE ? OR k.nama_pemilik LIKE ?) ";
        $paramLike = "%$search%";
        $params = [$paramLike, $paramLike, $paramLike, $paramLike];
    }
    $sql .= " ORDER BY s.tgl_cetak_pertama DESC, s.created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $listSiapSkpd = $stmt->fetchAll();
} else {
    // Objek yang SUDAH memiliki transaksi SKPD dan sah lunas
    $sql = "
        SELECT sk.*, k.nama_kapal, k.nama_pemilik, f.nama_fungsi, j.nama_jenis,
               p.nama_pejabat as nama_pengesah, u.nama_lengkap as nama_kasir
        FROM transaksi_skpd sk
        JOIN kendaraan_air k ON sk.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        LEFT JOIN pejabat p ON sk.pejabat_skpd_id = p.id
        LEFT JOIN users u ON sk.id_petugas_kasir = u.id
        WHERE 1=1
    ";
    $params = [];
    if (!empty($search)) {
        $sql .= " AND (sk.no_skpd LIKE ? OR sk.no_objek_pajak LIKE ? OR k.nama_kapal LIKE ? OR k.nama_pemilik LIKE ? OR sk.no_resi_bukti LIKE ?) ";
        $paramLike = "%$search%";
        $params = [$paramLike, $paramLike, $paramLike, $paramLike, $paramLike];
    }
    $sql .= " ORDER BY sk.tgl_bayar DESC, sk.created_at DESC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $listArsipSkpd = $stmt->fetchAll();
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('receipt', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Cetak Surat Ketetapan Pajak Daerah (SKPD PKAA)</div>
      <div class="page-subtitle">Pelayanan Penerbitan & Cetak Lembar Sah SKPD (Objek Muncul Setelah Cetak SUPERPKAA)</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="index.php?page=skpd" class="btn btn-primary btn-sm">
      <?= renderIcon('credit-card', '', 14) ?> Pembayaran Baru (F3)
    </a>
  </div>
</div>

<!-- Tabs Status SKPD -->
<div class="tabs-nav" style="display:flex; gap:8px; margin-bottom:14px; border-bottom: 2px solid var(--border-light); padding-bottom: 8px;">
  <a href="index.php?page=cetak_ulang_skpd&tab=siap_skpd<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
     class="btn btn-sm <?= $tab === 'siap_skpd' ? 'btn-primary' : 'btn-secondary' ?>" 
     style="font-weight: 700; display:flex; align-items:center; gap:6px;">
    <?= renderIcon('clock', '', 14) ?>
    <span>Siap Cetak / Terbitkan SKPD (SUPER Telah Dicetak)</span>
    <span class="badge" style="background: <?= $tab === 'siap_skpd' ? '#fff' : '#0284c7' ?>; color: <?= $tab === 'siap_skpd' ? 'var(--primary-700)' : '#fff' ?>; font-size:11px; padding:1px 7px; border-radius:10px;">
      <?= $countSiapSkpd ?>
    </span>
  </a>

  <a href="index.php?page=cetak_ulang_skpd&tab=arsip_skpd<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
     class="btn btn-sm <?= $tab === 'arsip_skpd' ? 'btn-primary' : 'btn-secondary' ?>" 
     style="font-weight: 700; display:flex; align-items:center; gap:6px;">
    <?= renderIcon('check-circle', '', 14) ?>
    <span>Arsip SKPD Diterbitkan (Sudah Dicetak SKPD)</span>
    <span class="badge" style="background: <?= $tab === 'arsip_skpd' ? '#fff' : '#10b981' ?>; color: <?= $tab === 'arsip_skpd' ? 'var(--primary-700)' : '#fff' ?>; font-size:11px; padding:1px 7px; border-radius:10px;">
      <?= $countArsipSkpd ?>
    </span>
  </a>
</div>

<!-- Form Filter & Pencarian -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-body">
    <form method="GET" action="index.php" style="display:flex; gap:10px;">
      <input type="hidden" name="page" value="cetak_ulang_skpd">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="form-group" style="flex:1;">
        <div class="input-group">
          <input type="text" name="q" class="form-control" placeholder="Cari berdasarkan No. SKPD, No. SUPER, NOPD, Nama Kapal, atau Pemilik..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-primary">
            <?= renderIcon('search', '', 14) ?> Cari Data SKPD
          </button>
        </div>
      </div>
      <?php if (!empty($search)): ?>
        <a href="index.php?page=cetak_ulang_skpd&tab=<?= htmlspecialchars($tab) ?>" class="btn btn-secondary">Reset</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($tab === 'siap_skpd'): ?>
  <!-- TAB 1: DAFTAR SIAP CETAK SKPD (SUPERPKAA SUDAH DICETAK) -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('receipt', '', 16) ?>
        <span>Daftar Objek Siap Penerbitan SKPD &mdash; SUPERPKAA Telah Dicetak (<?= count($listSiapSkpd) ?> Rekaman)</span>
      </div>
      <span class="badge badge-success"><?= renderIcon('shield-check', '', 12) ?> Syarat Cetak SUPERPKAA Terpenuhi</span>
    </div>

    <div class="panel-body" style="padding: 0;">
      <div class="table-responsive" style="border: none;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th width="150">No. SUPERPKAA</th>
              <th width="130">Waktu Cetak SUPER</th>
              <th>NOPD / Kapal</th>
              <th>Wajib Pajak</th>
              <th>Fungsi</th>
              <th class="text-right">Pokok PKAA</th>
              <th class="text-right">Opsen (66%)</th>
              <th class="text-right">Total Tagihan</th>
              <th class="text-center" width="160">Aksi SKPD</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($listSiapSkpd)): ?>
              <tr>
                <td colspan="9" class="text-center" style="padding: 32px; color: #94a3b8;">
                  <div style="font-size: 14px; font-weight: 600; color: #64748b; margin-bottom: 6px;">
                    Tidak ada antrean objek yang siap diterbitkan SKPD.
                  </div>
                  <div style="font-size: 12px;">
                    Objek baru akan otomatis muncul di sini <strong>setelah lembar SUPERPKAA-nya dicetak</strong> di menu <a href="index.php?page=cetak_ulang_superpkaa" style="color:var(--primary-600); font-weight:600;">Cetak SUPERPKAA</a>.
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($listSiapSkpd as $row): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($row['no_superpkaa']) ?></strong></td>
                  <td>
                    <span class="badge badge-success" style="font-size:10px;">
                      <?= renderIcon('check', '', 10) ?> <?= !empty($row['tgl_cetak_pertama']) ? date('d/m/Y H:i', strtotime($row['tgl_cetak_pertama'])) : formatTanggalIndo($row['tgl_terbit'], false) ?>
                    </span>
                  </td>
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
                    <a href="index.php?page=skpd&super=<?= urlencode($row['no_superpkaa']) ?>" class="btn btn-primary btn-sm" style="font-weight:700; padding:6px 14px; background:#0284c7; border-color:#0369a1;">
                      <?= renderIcon('credit-card', '', 14) ?> Bayar & Cetak SKPD
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

<?php else: ?>
  <!-- TAB 2: ARSIP SKPD DITERBITKAN (SUDAH DICETAK SKPD) -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('file-text', '', 16) ?>
        <span>Arsip Surat Ketetapan Pajak Daerah Diterbitkan (<?= count($listArsipSkpd) ?> Rekaman)</span>
      </div>
      <span class="badge badge-info">Dokumen Sah Kasda & Pembayaran Lunas</span>
    </div>

    <div class="panel-body" style="padding: 0;">
      <div class="table-responsive" style="border: none;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th width="180">No. SKPD</th>
              <th width="90">Tgl. Bayar</th>
              <th>NOPD / Kapal</th>
              <th>Wajib Pajak</th>
              <th>Metode / Resi</th>
              <th class="text-right">Pokok PKAA</th>
              <th class="text-right">Opsen (66%)</th>
              <th class="text-right">Total Disetor</th>
              <th class="text-center" width="130">Aksi Cetak</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($listArsipSkpd)): ?>
              <tr>
                <td colspan="9" class="text-center" style="padding: 32px; color: #94a3b8;">
                  Tidak ada riwayat penerbitan SKPD yang sesuai dengan kriteria pencarian.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($listArsipSkpd as $row): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($row['no_skpd']) ?></strong><br>
                    <span style="font-size: 10px; color: #64748b;">Ref: <?= htmlspecialchars($row['no_superpkaa']) ?></span>
                  </td>
                  <td><?= formatTanggalIndo($row['tgl_bayar'], false) ?></td>
                  <td>
                    <strong><?= htmlspecialchars($row['nama_kapal']) ?></strong><br>
                    <span style="font-size: 11px; color: #0284c7; font-weight:600;"><?= htmlspecialchars($row['no_objek_pajak']) ?></span>
                  </td>
                  <td><?= htmlspecialchars($row['nama_pemilik']) ?></td>
                  <td>
                    <span class="badge badge-info"><?= htmlspecialchars($row['metode_bayar']) ?></span><br>
                    <span style="font-size: 10px; color: #64748b;"><?= htmlspecialchars($row['no_resi_bukti'] ?: 'Loket Kasda') ?></span>
                  </td>
                  <td class="text-right"><?= formatRupiah($row['bea_pkaa_pokok_bayar']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['opsen_pkaa_pokok_bayar']) ?></td>
                  <td class="text-right"><strong style="color:#059669;"><?= formatRupiah($row['total_bayar']) ?></strong></td>
                  <td class="text-center">
                    <a href="cetak/skpd_print.php?no=<?= urlencode($row['no_skpd']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Cetak Ulang Lembar SKPD">
                      <?= renderIcon('printer', '', 13) ?> Cetak SKPD
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
<?php endif; ?>
