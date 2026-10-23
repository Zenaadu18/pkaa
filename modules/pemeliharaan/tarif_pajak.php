<?php
// modules/pemeliharaan/tarif_pajak.php - Pengaturan Variabel Fiskal & Tarif Pajak PKAA
$pageTitle = "Pengaturan Tarif & Variabel";

$pesan = '';
$tipePesan = '';

// Proses Simpan Tarif Per Fungsi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'simpan_tarif_fungsi') {
        $tarifData = $_POST['tarif'] ?? [];
        try {
            $stmtUpd = $pdo->prepare("UPDATE ref_fungsi_tarif SET tarif_persen = ?, kategori_tarif = ? WHERE kode_fungsi = ?");
            foreach ($tarifData as $kode => $vals) {
                $persen = (float)($vals['persen'] ?? 0.50);
                $kategori = trim($vals['kategori'] ?? 'ANGKUTAN_UMUM');
                $stmtUpd->execute([$persen, $kategori, $kode]);
            }
            $pesan = 'Konfigurasi tarif pokok per fungsi kendaraan berhasil diperbarui!';
            $tipePesan = 'success';
        } catch (Exception $e) {
            $pesan = 'Gagal menyimpan tarif: ' . $e->getMessage();
            $tipePesan = 'danger';
        }
    } elseif ($_POST['aksi'] === 'simpan_pengaturan_sistem') {
        $settings = $_POST['setting'] ?? [];
        try {
            $stmtSet = $pdo->prepare("INSERT INTO ref_pengaturan_sistem (parameter, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            foreach ($settings as $param => $val) {
                $stmtSet->execute([$param, trim($val)]);
            }
            $pesan = 'Variabel sistem dan identitas instansi berhasil disimpan!';
            $tipePesan = 'success';
        } catch (Exception $e) {
            $pesan = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
            $tipePesan = 'danger';
        }
    }
}

// Ambil data fungsi dan tarif
$fungsiList = $pdo->query("SELECT * FROM ref_fungsi_tarif ORDER BY kode_fungsi ASC")->fetchAll();

// Ambil seluruh setting sistem
$stmtSettings = $pdo->query("SELECT parameter, nilai, keterangan FROM ref_pengaturan_sistem");
$settingsMap = [];
while ($row = $stmtSettings->fetch()) {
    $settingsMap[$row['parameter']] = $row['nilai'];
}
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('settings', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Panel Admin: Pengaturan Tarif & Variabel Perhitungan</div>
      <div class="page-subtitle">Kelola Persentase Tarif PKAA, Opsen UU HKPD 66%, Denda Keterlambatan, dan Identitas Cetak</div>
    </div>
  </div>
</div>

<?php if (!empty($pesan)): ?>
  <div class="alert alert-<?= $tipePesan ?>">
    <?= renderIcon($tipePesan === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
    <span><?= $pesan ?></span>
  </div>
<?php endif; ?>

<div style="display: flex; flex-direction: column; gap: 14px;">
  <!-- Panel 1: Tarif Pokok PKAA Berdasarkan Fungsi Kendaraan -->
  <form method="POST" action="index.php?page=tarif_pajak" class="panel">
    <input type="hidden" name="aksi" value="simpan_tarif_fungsi">

    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('credit-card', '', 16) ?>
        <span>TARIF POKOK PKAA BERDASARKAN KODE FUNGSI KENDARAAN</span>
      </div>
      <span class="badge badge-info">Angkutan Umum 0,5% &bull; Pemerintah 0,5% &bull; Pribadi/Badan 1,2%</span>
    </div>

    <div class="panel-body" style="padding: 0;">
      <div class="table-responsive" style="border: none;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th width="100">Kode</th>
              <th>Nama Fungsi Kendaraan</th>
              <th width="240">Kategori Perpajakan</th>
              <th width="180">Tarif Pokok PKAA (%)</th>
              <th>Keterangan Regulasi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($fungsiList as $f): ?>
              <tr>
                <td><strong>[<?= htmlspecialchars($f['kode_fungsi']) ?>]</strong></td>
                <td><strong><?= htmlspecialchars($f['nama_fungsi']) ?></strong></td>
                <td>
                  <select name="tarif[<?= $f['kode_fungsi'] ?>][kategori]" class="form-control" style="height:28px; font-size:11px;">
                    <option value="ANGKUTAN_UMUM" <?= $f['kategori_tarif'] === 'ANGKUTAN_UMUM' ? 'selected' : '' ?>>Angkutan Umum (0,5%)</option>
                    <option value="PEMERINTAH" <?= $f['kategori_tarif'] === 'PEMERINTAH' ? 'selected' : '' ?>>Pemerintah / Dinas (0,5%)</option>
                    <option value="PRIBADI_BADAN" <?= $f['kategori_tarif'] === 'PRIBADI_BADAN' ? 'selected' : '' ?>>Pribadi / Badan Usaha (1,2%)</option>
                  </select>
                </td>
                <td>
                  <div class="input-group">
                    <input type="number" step="0.01" name="tarif[<?= $f['kode_fungsi'] ?>][persen]" class="form-control" value="<?= htmlspecialchars($f['tarif_persen']) ?>" style="height:28px; text-align:right;" required>
                    <span class="input-suffix" style="height:28px;">%</span>
                  </div>
                </td>
                <td style="color: #64748b; font-size: 11px;">
                  <?= htmlspecialchars($f['keterangan'] ?: '-') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel-header" style="justify-content: flex-end; border-top: 1px solid var(--border-medium); border-bottom: none;">
      <button type="submit" class="btn btn-primary">
        <?= renderIcon('check', '', 14) ?> Simpan Perubahan Tarif Fungsi
      </button>
    </div>
  </form>

  <!-- Panel 2: Variabel Pajak & Identitas Instansi -->
  <form method="POST" action="index.php?page=tarif_pajak" class="panel">
    <input type="hidden" name="aksi" value="simpan_pengaturan_sistem">

    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('settings', '', 16) ?>
        <span>VARIABEL PERHITUNGAN OPSEN, DENDA & IDENTITAS DOKUMEN CETAK</span>
      </div>
      <span class="badge badge-neutral">Tersimpan di Tabel ref_pengaturan_sistem</span>
    </div>

    <div class="panel-body">
      <div class="form-grid-2">
        <!-- Kolom Kiri: Variabel Fiskal -->
        <div style="display:flex; flex-direction:column; gap:12px;">
          <div style="font-weight:700; color:#334155; font-size:12px; border-bottom:1px solid #cbd5e1; padding-bottom:4px;">
            PARAMETER FISKAL & SANKSI ADMINISTRASI
          </div>

          <div class="form-group">
            <label class="form-label">Persentase Opsen PKAA (UU No. 1/2022 HKPD)</label>
            <div class="input-group">
              <input type="number" step="0.01" name="setting[persentase_opsen]" class="form-control" value="<?= htmlspecialchars($settingsMap['persentase_opsen'] ?? '66.00') ?>" required>
              <span class="input-suffix">% dari Pokok PKAA</span>
            </div>
            <span style="font-size:10px; color:#64748b;">Standar regulasi nasional HKPD adalah 66%</span>
          </div>

          <div class="form-group">
            <label class="form-label">Persentase Sanksi Denda Keterlambatan per Bulan</label>
            <div class="input-group">
              <input type="number" step="0.01" name="setting[persentase_denda_per_bulan]" class="form-control" value="<?= htmlspecialchars($settingsMap['persentase_denda_per_bulan'] ?? '1.00') ?>" required>
              <span class="input-suffix">% per Bulan</span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Batas Maksimal Bulan Denda</label>
            <div class="input-group">
              <input type="number" name="setting[maks_bulan_denda]" class="form-control" value="<?= htmlspecialchars($settingsMap['maks_bulan_denda'] ?? '24') ?>" required>
              <span class="input-suffix">Bulan</span>
            </div>
          </div>

          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Kode Wilayah Pelayanan</label>
              <input type="text" name="setting[kd_wilayah_default]" class="form-control" value="<?= htmlspecialchars($settingsMap['kd_wilayah_default'] ?? '13') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Nama Wilayah Kerja</label>
              <input type="text" name="setting[nama_wilayah_default]" class="form-control" value="<?= htmlspecialchars($settingsMap['nama_wilayah_default'] ?? 'TARAKAN') ?>">
            </div>
          </div>
        </div>

        <!-- Kolom Kanan: Kop Surat & Identitas Cetak -->
        <div style="display:flex; flex-direction:column; gap:12px;">
          <div style="font-weight:700; color:#334155; font-size:12px; border-bottom:1px solid #cbd5e1; padding-bottom:4px;">
            IDENTITAS RESMI PADA KOP SURAT CETAK (SUPERPKAA, SKPD & LAPORAN)
          </div>

          <div class="form-group">
            <label class="form-label">Nama Instansi Induk</label>
            <input type="text" name="setting[nama_instansi]" class="form-control" value="<?= htmlspecialchars($settingsMap['nama_instansi'] ?? 'BADAN PENDAPATAN DAERAH PROVINSI KALIMANTAN UTARA') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Nama Unit Pelaksana Teknis (UPT)</label>
            <input type="text" name="setting[nama_upt]" class="form-control" value="<?= htmlspecialchars($settingsMap['nama_upt'] ?? 'UPTD WILAYAH XIII TARAKAN') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Alamat Kantor Lengkap</label>
            <textarea name="setting[alamat_kantor]" class="form-control" rows="2"><?= htmlspecialchars($settingsMap['alamat_kantor'] ?? 'Jl. P. Diponegoro No. 12, Tarakan, Kalimantan Utara 77113') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Nomor Telepon Kantor</label>
            <input type="text" name="setting[telepon_kantor]" class="form-control" value="<?= htmlspecialchars($settingsMap['telepon_kantor'] ?? '(0551) 21543') ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="panel-header" style="justify-content: flex-end; border-top: 1px solid var(--border-medium); border-bottom: none;">
      <button type="submit" class="btn btn-primary">
        <?= renderIcon('check', '', 14) ?> Simpan Parameter Sistem & Cetak
      </button>
    </div>
  </form>
</div>
