<?php
// modules/pemeliharaan/kabupaten_kota.php - Daftar Wilayah Kabupaten / Kota di NTT
$pageTitle = "Daftar Kab/Kota di NTT";

$pesan = '';
$tipePesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'simpan_kabkota') {
        $kode = strtoupper(trim($_POST['kode_wilayah'] ?? ''));
        $nama = strtoupper(trim($_POST['nama_kabupaten_kota'] ?? ''));
        $tipe = trim($_POST['tipe'] ?? 'KABUPATEN');
        $pelabuhan = strtoupper(trim($_POST['pelabuhan_pangkal_default'] ?? ''));

        if (empty($kode) || empty($nama)) {
            $pesan = "Kode wilayah dan nama kabupaten/kota wajib diisi!";
            $tipePesan = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO ref_kabupaten_kota (kode_wilayah, nama_kabupaten_kota, tipe, pelabuhan_pangkal_default)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        nama_kabupaten_kota = VALUES(nama_kabupaten_kota),
                        tipe = VALUES(tipe),
                        pelabuhan_pangkal_default = VALUES(pelabuhan_pangkal_default)
                ");
                $stmt->execute([$kode, $nama, $tipe, $pelabuhan]);
                $pesan = "Data wilayah <strong>$nama ($kode)</strong> berhasil disimpan!";
                $tipePesan = "success";
            } catch (Exception $e) {
                $pesan = "Gagal menyimpan data wilayah: " . $e->getMessage();
                $tipePesan = "danger";
            }
        }
    }
}

// Ambil data 22 kabupaten / kota di NTT
$kabKotaList = $pdo->query("SELECT * FROM ref_kabupaten_kota ORDER BY tipe ASC, nama_kabupaten_kota ASC")->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('compass', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Daftar Wilayah Asal Kendaraan (Kabupaten / Kota di NTT)</div>
      <div class="page-subtitle">Daftar 22 Wilayah Pelayanan Administrasi & Kode NOPD Kendaraan Air (KA 0001 XX)</div>
    </div>
  </div>
</div>

<?php if (!empty($pesan)): ?>
  <div class="alert alert-<?= $tipePesan ?>">
    <?= renderIcon($tipePesan === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
    <span><?= $pesan ?></span>
  </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.8fr; gap: 14px;">
  <!-- Form Tambah / Edit Wilayah -->
  <form method="POST" action="index.php?page=kabupaten_kota" class="panel">
    <input type="hidden" name="aksi" value="simpan_kabkota">

    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('plus', '', 16) ?>
        <span>Tambah / Perbarui Wilayah NTT</span>
      </div>
    </div>

    <div class="panel-body" style="display: flex; flex-direction: column; gap: 12px;">
      <div class="form-group">
        <label class="form-label" for="kode_wilayah">Kode Wilayah (2 Huruf untuk NOPD) <span class="required">*</span></label>
        <input type="text" name="kode_wilayah" id="kode_wilayah" class="form-control" maxlength="5" placeholder="Contoh: KP, MB, FL, SK, AL, RD" style="text-transform: uppercase;" required>
        <span style="font-size:10px; color:#64748b;">Digunakan sebagai akhiran nomor nopol: <code>KA 0001 XX</code></span>
      </div>

      <div class="form-group">
        <label class="form-label" for="nama_kabupaten_kota">Nama Kabupaten / Kota <span class="required">*</span></label>
        <input type="text" name="nama_kabupaten_kota" id="nama_kabupaten_kota" class="form-control" placeholder="Contoh: KABUPATEN MANGGARAI BARAT" style="text-transform: uppercase;" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="tipe">Status Pemerintahan</label>
        <select name="tipe" id="tipe" class="form-control">
          <option value="KABUPATEN">KABUPATEN</option>
          <option value="KOTA">KOTA</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="pelabuhan_pangkal_default">Pelabuhan Pangkal Utama</label>
        <input type="text" name="pelabuhan_pangkal_default" id="pelabuhan_pangkal_default" class="form-control" placeholder="Contoh: LABUAN BAJO / TENAU KUPANG" style="text-transform: uppercase;" required>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-top: 6px;">
        <?= renderIcon('check', '', 14) ?> Simpan Wilayah NTT
      </button>
    </div>
  </form>

  <!-- Tabel Daftar 22 Kabupaten / Kota -->
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('database', '', 16) ?>
        <span>Wilayah Terdaftar di Provinsi Nusa Tenggara Timur (<?= count($kabKotaList) ?> Wilayah)</span>
      </div>
    </div>

    <div class="panel-body" style="padding: 0;">
      <div class="table-responsive" style="border: none; max-height: 70vh;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th width="80" class="text-center">Kode NOPD</th>
              <th>Nama Kabupaten / Kota</th>
              <th>Tipe</th>
              <th>Pelabuhan Pangkal Default</th>
              <th width="120">Format Plat KA</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($kabKotaList as $k): ?>
              <tr>
                <td class="text-center"><strong style="color:#0284c7; font-size:13px;"><?= htmlspecialchars($k['kode_wilayah']) ?></strong></td>
                <td><strong><?= htmlspecialchars($k['nama_kabupaten_kota']) ?></strong></td>
                <td><span class="badge <?= $k['tipe'] === 'KOTA' ? 'badge-info' : 'badge-neutral' ?>"><?= $k['tipe'] ?></span></td>
                <td><?= htmlspecialchars($k['pelabuhan_pangkal_default']) ?></td>
                <td><code>KA 0001 <?= htmlspecialchars($k['kode_wilayah']) ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
