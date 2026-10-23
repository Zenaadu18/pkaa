<?php
// modules/pelayanan/status_pengajuan.php - Status Pengajuan Perubahan Data & Pendaftaran Baru Operator
$pageTitle = "Status Pengajuan Operator";
$currentUser = currentUser();
$userRole = $currentUser['role'] ?? 'OPERATOR';

$userId = $currentUser['id'] ?? 0;

// Query pengajuan perubahan data
if ($userRole === 'ADMIN') {
    $stmtPerubahan = $pdo->query("SELECT * FROM pengajuan_perubahan_kaa ORDER BY tgl_pengajuan DESC");
    $stmtDraft = $pdo->query("SELECT * FROM kendaraan_air WHERE status_verifikasi = 'MENUNGGU_VERIFIKASI' ORDER BY created_at DESC");
} else {
    $stmtPerubahan = $pdo->prepare("SELECT * FROM pengajuan_perubahan_kaa WHERE diajukan_oleh_id = ? ORDER BY tgl_pengajuan DESC");
    $stmtPerubahan->execute([$userId]);
    $stmtDraft = $pdo->prepare("SELECT * FROM kendaraan_air WHERE didaftarkan_oleh = ? AND status_verifikasi = 'MENUNGGU_VERIFIKASI' ORDER BY created_at DESC");
    $stmtDraft->execute([$userId]);
}

$listPerubahan = $stmtPerubahan->fetchAll();
$listDraft = $stmtDraft->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('file-clock', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Status Pengajuan & Verifikasi Operator</div>
      <div class="page-subtitle">Pantau Status Persetujuan Admin Induk Terhadap Pendaftaran Baru dan Perubahan Data Objek Pajak</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="index.php?page=pendaftaran" class="btn btn-primary btn-sm">
      <?= renderIcon('ship', '', 14) ?> Pendaftaran Baru
    </a>
    <a href="index.php?page=ajukan_perubahan" class="btn btn-secondary btn-sm">
      <?= renderIcon('file-edit', '', 14) ?> Ajukan Perubahan Lainnya
    </a>
  </div>
</div>

<!-- Tabel 1: Pendaftaran Baru yang Menunggu Penetapan NJKB Admin Induk -->
<div class="panel" style="margin-bottom: 16px;">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('ship', '', 16) ?>
      <span>1. DAFTAR PENDAFTARAN OBJEK BARU MENUNGGU PENILAIAN NJKB ADMIN INDUK (<?= count($listDraft) ?>)</span>
    </div>
    <span class="badge badge-warning">Menunggu Penetapan NJKB</span>
  </div>
  <div class="panel-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table-desktop">
        <thead>
          <tr>
            <th width="40">NO</th>
            <th>NOPD (NOPOL KA)</th>
            <th>NAMA KAPAL</th>
            <th>NAMA PEMILIK</th>
            <th>WILAYAH KAB/KOTA</th>
            <th>GT / TAHUN</th>
            <th>TGL DAFTAR</th>
            <th>STATUS VERIFIKASI</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($listDraft)): ?>
            <tr>
              <td colspan="8" align="center" style="padding:20px; color:#64748b;">
                Tidak ada pendaftaran baru yang sedang menunggu verifikasi admin induk.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($listDraft as $idx => $d): ?>
              <tr>
                <td align="center"><?= $idx + 1 ?></td>
                <td><strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($d['no_objek_pajak']) ?></strong></td>
                <td><strong><?= htmlspecialchars($d['nama_kapal']) ?></strong></td>
                <td><?= htmlspecialchars($d['nama_pemilik']) ?></td>
                <td>[<?= htmlspecialchars($d['kode_kabkota']) ?>] <?= htmlspecialchars($d['pelabuhan_pangkal']) ?></td>
                <td><?= htmlspecialchars($d['kapasitas_gt']) ?> GT / <?= $d['tahun_buatan'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                <td>
                  <span class="badge badge-warning" style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                    <?= renderIcon('clock', '', 12) ?> MENUNGGU ADMIN INDUK
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Tabel 2: Riwayat Pengajuan Perubahan Data -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('file-text', '', 16) ?>
      <span>2. RIWAYAT PENGAJUAN PERUBAHAN DATA KAA (<?= count($listPerubahan) ?>)</span>
    </div>
  </div>
  <div class="panel-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table-desktop">
        <thead>
          <tr>
            <th width="40">NO</th>
            <th>NO. PENGAJUAN</th>
            <th>NOPD KA</th>
            <th>NAMA KAPAL</th>
            <th>ALASAN PENGAJUAN</th>
            <th>TGL DIAJUKAN</th>
            <th>STATUS</th>
            <th>CATATAN ADMIN</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($listPerubahan)): ?>
            <tr>
              <td colspan="8" align="center" style="padding:20px; color:#64748b;">
                Belum ada permohonan pengajuan perubahan data yang diajukan.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($listPerubahan as $idx => $p): ?>
              <tr>
                <td align="center"><?= $idx + 1 ?></td>
                <td><strong><?= htmlspecialchars($p['no_pengajuan']) ?></strong></td>
                <td><strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($p['no_objek_pajak']) ?></strong></td>
                <td><?= htmlspecialchars($p['nama_kapal']) ?></td>
                <td>
                  <?= htmlspecialchars($p['alasan_perubahan']) ?>
                  <?php if ($p['lampiran_dokumen']): ?>
                    <br><small style="color:#64748b;">Ref: <?= htmlspecialchars($p['lampiran_dokumen']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($p['tgl_pengajuan'])) ?></td>
                <td>
                  <?php if ($p['status'] === 'DISETUJUI'): ?>
                    <span class="badge badge-success" style="background:#d1fae5; color:#065f46; border:1px solid #34d399;">
                      <?= renderIcon('check-circle', '', 12) ?> DISETUJUI
                    </span>
                  <?php elseif ($p['status'] === 'DITOLAK'): ?>
                    <span class="badge badge-danger" style="background:#fee2e2; color:#991b1b; border:1px solid #f87171;">
                      <?= renderIcon('x-circle', '', 12) ?> DITOLAK
                    </span>
                  <?php else: ?>
                    <span class="badge badge-warning" style="background:#fef3c7; color:#92400e; border:1px solid #fcd34d;">
                      <?= renderIcon('clock', '', 12) ?> MENUNGGU VERIFIKASI
                    </span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['catatan_verifikasi'] ?: '-') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
