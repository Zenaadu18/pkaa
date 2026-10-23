<?php
// modules/pemeliharaan/pejabat_skpd.php - Master Pejabat Pengesah SKPD PKAA
$pageTitle = "Pejabat SKPD";

$pesan = '';
$tipePesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'simpan_pejabat_skpd') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_pejabat'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $pangkat = trim($_POST['pangkat_gol'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (empty($nama) || empty($nip)) {
            $pesan = 'Nama Pejabat dan NIP wajib diisi!';
            $tipePesan = 'danger';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE pejabat SET nama_pejabat = ?, nip = ?, pangkat_gol = ?, jabatan = ?, is_active = ? WHERE id = ? AND tipe_pejabat = 'SKPD'");
                    $stmt->execute([$nama, $nip, $pangkat, $jabatan, $isActive, $id]);
                    $pesan = 'Data Pejabat SKPD berhasil diperbarui.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO pejabat (tipe_pejabat, nama_pejabat, nip, pangkat_gol, jabatan, is_active) VALUES ('SKPD', ?, ?, ?, ?, ?)");
                    $stmt->execute([$nama, $nip, $pangkat, $jabatan, $isActive]);
                    $pesan = 'Pejabat SKPD baru berhasil ditambahkan.';
                }
                $tipePesan = 'success';
            } catch (Exception $e) {
                $pesan = 'Gagal menyimpan: ' . $e->getMessage();
                $tipePesan = 'danger';
            }
        }
    } elseif ($_POST['aksi'] === 'hapus_pejabat_skpd') {
        $id = (int)($_POST['del_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM pejabat WHERE id = ? AND tipe_pejabat = 'SKPD'")->execute([$id]);
            $pesan = 'Data Pejabat SKPD berhasil dihapus.';
            $tipePesan = 'success';
        }
    }
}

$pejabatList = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'SKPD' ORDER BY is_active DESC, id ASC")->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('user', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Master Pejabat Pengesah SKPD PKAA</div>
      <div class="page-subtitle">Pengaturan Nama, NIP, Pangkat & Jabatan Pengesah Surat Ketetapan Pajak Daerah</div>
    </div>
  </div>
</div>

<?php if (!empty($pesan)): ?>
  <div class="alert alert-<?= $tipePesan ?>">
    <?= renderIcon($tipePesan === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
    <span><?= $pesan ?></span>
  </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 14px;">
  <!-- Form Tambah Pejabat -->
  <form method="POST" action="index.php?page=pejabat_skpd" class="panel">
    <input type="hidden" name="aksi" value="simpan_pejabat_skpd">
    <input type="hidden" name="id" value="0">

    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('plus', '', 16) ?>
        <span>Tambah Pejabat Pengesah SKPD</span>
      </div>
    </div>

    <div class="panel-body" style="display: flex; flex-direction: column; gap: 12px;">
      <div class="form-group">
        <label class="form-label" for="nama_pejabat">Nama Lengkap & Gelar <span class="required">*</span></label>
        <input type="text" name="nama_pejabat" id="nama_pejabat" class="form-control" placeholder="Contoh: H. ACHMAD RUSLAN, S.Sos., M.Si." required>
      </div>

      <div class="form-group">
        <label class="form-label" for="nip">NIP Pejabat <span class="required">*</span></label>
        <input type="text" name="nip" id="nip" class="form-control" placeholder="Contoh: 19740512 199803 1 004" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="pangkat_gol">Pangkat / Golongan</label>
        <input type="text" name="pangkat_gol" id="pangkat_gol" class="form-control" placeholder="Contoh: Pembina Tk. I (IV/b)">
      </div>

      <div class="form-group">
        <label class="form-label" for="jabatan">Jabatan Kedinasan</label>
        <input type="text" name="jabatan" id="jabatan" class="form-control" placeholder="Contoh: Kepala UPTD Wilayah XIII Tarakan">
      </div>

      <div class="form-group">
        <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; cursor: pointer;">
          <input type="checkbox" name="is_active" value="1" checked>
          <span>Pejabat Aktif (Muncul di dropdown pengesah SKPD)</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top: 6px;">
        <?= renderIcon('check', '', 14) ?> Simpan Data Pejabat SKPD
      </button>
    </div>
  </form>

  <!-- Tabel Pejabat SKPD -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('database', '', 16) ?>
        <span>Daftar Pejabat SKPD (<?= count($pejabatList) ?> Orang)</span>
      </div>
    </div>

    <div class="panel-body" style="padding: 0;">
      <div class="table-responsive" style="border: none;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th>Nama Pejabat & NIP</th>
              <th>Pangkat / Gol</th>
              <th>Jabatan</th>
              <th class="text-center">Status</th>
              <th class="text-center" width="80">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($pejabatList)): ?>
              <tr>
                <td colspan="5" class="text-center" style="padding: 20px; color: #94a3b8;">
                  Belum ada data pejabat SKPD.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($pejabatList as $p): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($p['nama_pejabat']) ?></strong><br>
                    <span style="font-size: 11px; color: #64748b;">NIP. <?= htmlspecialchars($p['nip']) ?></span>
                  </td>
                  <td><?= htmlspecialchars($p['pangkat_gol']) ?></td>
                  <td><?= htmlspecialchars($p['jabatan']) ?></td>
                  <td class="text-center">
                    <?php if ($p['is_active']): ?>
                      <span class="badge badge-success">AKTIF</span>
                    <?php else: ?>
                      <span class="badge badge-neutral">NONAKTIF</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <form method="POST" action="index.php?page=pejabat_skpd" onsubmit="return confirm('Hapus pejabat SKPD ini?');" style="margin:0;">
                      <input type="hidden" name="aksi" value="hapus_pejabat_skpd">
                      <input type="hidden" name="del_id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">
                        <?= renderIcon('trash', '', 12) ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
