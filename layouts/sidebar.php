<?php
// layouts/sidebar.php - Menu Samping Navigasi JANGKAR NTT Desktop
$currentPage = $_GET['page'] ?? 'dashboard';
$userRole = currentUser()['role'] ?? 'OPERATOR';

$pendingCount = 0;
if ($userRole === 'ADMIN') {
    try {
        $p1 = $pdo->query("SELECT COUNT(*) FROM kendaraan_air WHERE status_verifikasi = 'MENUNGGU_VERIFIKASI'")->fetchColumn();
        $p2 = $pdo->query("SELECT COUNT(*) FROM pengajuan_perubahan_kaa WHERE status = 'MENUNGGU_VERIFIKASI'")->fetchColumn();
        $pendingCount = (int)$p1 + (int)$p2;
    } catch(Exception $e) {
        $pendingCount = 0;
    }
}
?>
<aside class="desktop-sidebar">
  <!-- Mobile Drawer Header -->
  <div class="sidebar-mobile-header">
    <div style="display:flex; align-items:center; gap:8px;">
      <img src="assets/img/logontt.png" alt="Logo NTT" style="height:26px; width:auto; filter:drop-shadow(0 1px 2px rgba(0,0,0,0.5));">
      <div>
        <div style="color:#ffffff; font-size:12.5px; font-weight:800; letter-spacing:0.5px;">JANGKAR NTT</div>
        <div style="color:#94a3b8; font-size:9.5px;">BPAD Prov. NTT</div>
      </div>
    </div>
    <button type="button" class="btn-sidebar-close" onclick="toggleSidebar(false)" title="Tutup Menu">&times;</button>
  </div>

  <!-- Section Dashboard -->
  <div class="nav-section-title">Menu Utama</div>
  <ul class="nav-menu">
    <li class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
      <a href="index.php?page=dashboard" data-title="Beranda / Dashboard">
        <?= renderIcon('home', '', 16) ?>
        <span>Beranda / Dashboard</span>
      </a>
    </li>
  </ul>

  <!-- Role: OPERATOR (Pendaftaran, Perubahan, Status, Cetak SUPERPKAA, Riwayat Cetak, Cetak SKPD, Cetak Opsen) -->
  <?php if ($userRole === 'OPERATOR'): ?>
    <div class="nav-section-title">A. Pelayanan Operator</div>
    <ul class="nav-menu">
      <li class="nav-item <?= $currentPage === 'pendaftaran' ? 'active' : '' ?>">
        <a href="index.php?page=pendaftaran" data-title="Pendaftaran Objek Baru (F2)">
          <?= renderIcon('ship', '', 16) ?>
          <span>Pendaftaran Objek Baru</span>
          <span class="shortcut-tag">F2</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'pembayaran_tahunan' ? 'active' : '' ?>">
        <a href="index.php?page=pembayaran_tahunan" data-title="Pembayaran PKAA Tahunan">
          <?= renderIcon('calendar', '', 16) ?>
          <span>Pembayaran PKAA Tahunan</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'ajukan_perubahan' ? 'active' : '' ?>">
        <a href="index.php?page=ajukan_perubahan" data-title="Ajukan Perubahan Data">
          <?= renderIcon('file-edit', '', 16) ?>
          <span>Ajukan Perubahan Data</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'status_pengajuan' ? 'active' : '' ?>">
        <a href="index.php?page=status_pengajuan" data-title="Status Pengajuan Saya">
          <?= renderIcon('file-clock', '', 16) ?>
          <span>Status Pengajuan Saya</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'cetak_ulang_superpkaa' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_ulang_superpkaa" data-title="Cetak SUPERPKAA">
          <?= renderIcon('file-text', '', 16) ?>
          <span>Cetak SUPERPKAA</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'riwayat_cetak_superpkaa' ? 'active' : '' ?>">
        <a href="index.php?page=riwayat_cetak_superpkaa" data-title="Riwayat Cetak SUPERPKAA">
          <?= renderIcon('history', '', 16) ?>
          <span>Riwayat Cetak SUPERPKAA</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'cetak_ulang_skpd' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_ulang_skpd" data-title="Cetak SKPD">
          <?= renderIcon('receipt', '', 16) ?>
          <span>Cetak SKPD</span>
        </a>
      </li>
    </ul>

    <!-- Section Laporan & Opsen untuk Operator -->
    <div class="nav-section-title">B. Laporan & Bantuan</div>
    <ul class="nav-menu">
      <li class="nav-item <?= $currentPage === 'cetak_opsen' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_opsen" data-title="Cetak Opsen PKAA (66%)">
          <?= renderIcon('pie-chart', '', 16) ?>
          <span>Cetak Opsen PKAA (66%)</span>
        </a>
      </li>
    </ul>

  <!-- Role: ADMIN INDUK (Semua Menu Lengkap + Verifikasi & Approval) -->
  <?php elseif ($userRole === 'ADMIN'): ?>
    <div class="nav-section-title">A. Pelayanan Pajak KAA</div>
    <ul class="nav-menu">
      <li class="nav-item <?= $currentPage === 'pendaftaran' ? 'active' : '' ?>">
        <a href="index.php?page=pendaftaran" data-title="Pendaftaran Wajib Pajak (F2)">
          <?= renderIcon('ship', '', 16) ?>
          <span>Pendaftaran Wajib Pajak</span>
          <span class="shortcut-tag">F2</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'pembayaran_tahunan' ? 'active' : '' ?>">
        <a href="index.php?page=pembayaran_tahunan" data-title="Pembayaran PKAA Tahunan">
          <?= renderIcon('calendar', '', 16) ?>
          <span>Pembayaran PKAA Tahunan</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'verifikasi_perubahan' ? 'active' : '' ?>">
        <a href="index.php?page=verifikasi_perubahan" data-title="Verifikasi & Validasi Admin">
          <?= renderIcon('shield-check', '', 16) ?>
          <span>Verifikasi & Validasi Admin</span>
          <?php if ($pendingCount > 0): ?>
            <span class="badge" style="background:#ef4444; color:#fff; font-size:10px; padding:1px 6px; border-radius:10px; margin-left:auto;"><?= $pendingCount ?></span>
          <?php endif; ?>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'superpkaa' ? 'active' : '' ?>">
        <a href="index.php?page=superpkaa" data-title="Penetapan (SUPERPKAA)">
          <?= renderIcon('file-text', '', 16) ?>
          <span>Penetapan (SUPERPKAA)</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'cetak_ulang_superpkaa' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_ulang_superpkaa" data-title="Cetak Ulang SUPERPKAA">
          <?= renderIcon('printer', '', 16) ?>
          <span>Cetak Ulang SUPERPKAA</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'riwayat_cetak_superpkaa' ? 'active' : '' ?>">
        <a href="index.php?page=riwayat_cetak_superpkaa" data-title="Riwayat Cetak SUPERPKAA">
          <?= renderIcon('history', '', 16) ?>
          <span>Riwayat Cetak SUPERPKAA</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'skpd' ? 'active' : '' ?>">
        <a href="index.php?page=skpd" data-title="Pembayaran & SKPD (F3)">
          <?= renderIcon('credit-card', '', 16) ?>
          <span>Pembayaran & SKPD</span>
          <span class="shortcut-tag">F3</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'cetak_ulang_skpd' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_ulang_skpd" data-title="Cetak Ulang SKPD">
          <?= renderIcon('receipt', '', 16) ?>
          <span>Cetak Ulang SKPD</span>
        </a>
      </li>
    </ul>

    <!-- Section Pemeliharaan Data -->
    <div class="nav-section-title">B. Pemeliharaan Data</div>
    <ul class="nav-menu">
      <li class="nav-item <?= $currentPage === 'master_kaa' ? 'active' : '' ?>">
        <a href="index.php?page=master_kaa" data-title="Tabel Master Data KAA">
          <?= renderIcon('anchor', '', 16) ?>
          <span>Tabel Master Data KAA</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'kabupaten_kota' ? 'active' : '' ?>">
        <a href="index.php?page=kabupaten_kota" data-title="Daftar Kab/Kota di NTT">
          <?= renderIcon('compass', '', 16) ?>
          <span>Daftar Kab/Kota di NTT</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'pejabat_laporan' ? 'active' : '' ?>">
        <a href="index.php?page=pejabat_laporan" data-title="Pejabat Laporan">
          <?= renderIcon('users', '', 16) ?>
          <span>Pejabat Laporan</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'pejabat_skpd' ? 'active' : '' ?>">
        <a href="index.php?page=pejabat_skpd" data-title="Pejabat SKPD">
          <?= renderIcon('user-check', '', 16) ?>
          <span>Pejabat SKPD</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'tarif_pajak' ? 'active' : '' ?>">
        <a href="index.php?page=tarif_pajak" data-title="Pengaturan Tarif & Sistem">
          <?= renderIcon('settings', '', 16) ?>
          <span>Pengaturan Tarif & Sistem</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'tabel_njkb' ? 'active' : '' ?>">
        <a href="index.php?page=tabel_njkb" data-title="Tabel Standar NJKB Air">
          <?= renderIcon('coins', '', 16) ?>
          <span>Tabel Standar NJKB Air</span>
        </a>
      </li>
    </ul>

    <!-- Section Laporan & Opsen -->
    <div class="nav-section-title">C. Menu Laporan & Opsen</div>
    <ul class="nav-menu">
      <li class="nav-item <?= $currentPage === 'laporan' ? 'active' : '' ?>">
        <a href="index.php?page=laporan" data-title="Cetak 6 Jenis Laporan (F4)">
          <?= renderIcon('file-spreadsheet', '', 16) ?>
          <span>Cetak 6 Jenis Laporan</span>
          <span class="shortcut-tag">F4</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'cetak_opsen' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_opsen" data-title="Cetak Opsen PKAA (66%)">
          <?= renderIcon('pie-chart', '', 16) ?>
          <span>Cetak Opsen PKAA (66%)</span>
        </a>
      </li>
    </ul>

  <!-- Role: KASIR -->
  <?php else: ?>
    <div class="nav-section-title">A. Kasir Kasda / Bank NTT</div>
    <ul class="nav-menu">
      <li class="nav-item <?= $currentPage === 'pembayaran_tahunan' ? 'active' : '' ?>">
        <a href="index.php?page=pembayaran_tahunan" data-title="Pembayaran PKAA Tahunan">
          <?= renderIcon('calendar', '', 16) ?>
          <span>Pembayaran PKAA Tahunan</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'skpd' ? 'active' : '' ?>">
        <a href="index.php?page=skpd" data-title="Pembayaran & SKPD (F3)">
          <?= renderIcon('credit-card', '', 16) ?>
          <span>Pembayaran & SKPD</span>
          <span class="shortcut-tag">F3</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'cetak_ulang_skpd' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_ulang_skpd" data-title="Cetak Ulang SKPD">
          <?= renderIcon('receipt', '', 16) ?>
          <span>Cetak Ulang SKPD</span>
        </a>
      </li>
      <li class="nav-item <?= $currentPage === 'cetak_opsen' ? 'active' : '' ?>">
        <a href="index.php?page=cetak_opsen" data-title="Cetak Opsen PKAA (66%)">
          <?= renderIcon('pie-chart', '', 16) ?>
          <span>Cetak Opsen PKAA (66%)</span>
        </a>
      </li>
    </ul>
  <?php endif; ?>

  <!-- Layar Bantu Shortcut in Nav -->
  <div class="nav-section-title" style="margin-top:auto;">Bantuan Cepat</div>
  <ul class="nav-menu" style="margin-bottom: 10px;">
    <li class="nav-item">
      <a href="javascript:void(0)" onclick="bukaLayarBantu()" data-title="Layar Bantu NOPD (Ctrl+B)">
        <?= renderIcon('search', '', 16) ?>
        <span>Layar Bantu NOPD</span>
        <span class="shortcut-tag">Ctrl+B</span>
      </a>
    </li>
  </ul>
</aside>
