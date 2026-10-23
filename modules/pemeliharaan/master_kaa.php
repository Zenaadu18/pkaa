<?php
// modules/pemeliharaan/master_kaa.php - Tabel Master KAA JANGKAR NTT
$pageTitle = "Tabel Master KAA";

$search = trim($_GET['q'] ?? '');
$filterKab = trim($_GET['kab'] ?? '');
$filterJenis = trim($_GET['jenis'] ?? '');
$filterFungsi = trim($_GET['fungsi'] ?? '');
$pesan = '';
$tipePesan = '';

// Proses Hapus Data Kapal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'hapus_kaa') {
    $delNopd = trim($_POST['del_nopd'] ?? '');
    if (!empty($delNopd)) {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM kendaraan_air WHERE no_objek_pajak = ?");
            $stmtDel->execute([$delNopd]);
            $pesan = "Data Kapal Nopol KA: <strong>$delNopd</strong> berhasil dihapus.";
            $tipePesan = 'success';
        } catch (Exception $e) {
            $pesan = "Gagal menghapus data: " . $e->getMessage();
            $tipePesan = 'danger';
        }
    }
}

// Query data kapal
$sql = "
    SELECT k.*, f.nama_fungsi, f.tarif_persen, j.nama_jenis, kb.nama_kabupaten_kota
    FROM kendaraan_air k
    JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
    JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
    LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (k.no_objek_pajak LIKE ? OR k.nama_kapal LIKE ? OR k.nama_pemilik LIKE ? OR k.tanda_selar LIKE ?) ";
    $paramLike = "%$search%";
    $params = array_merge($params, [$paramLike, $paramLike, $paramLike, $paramLike]);
}
if (!empty($filterKab)) {
    $sql .= " AND k.kode_kabkota = ? ";
    $params[] = $filterKab;
}
if (!empty($filterJenis)) {
    $sql .= " AND k.kode_jenis = ? ";
    $params[] = $filterJenis;
}
if (!empty($filterFungsi)) {
    $sql .= " AND k.kode_fungsi = ? ";
    $params[] = $filterFungsi;
}

$sql .= " ORDER BY k.no_objek_pajak ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kapalList = $stmt->fetchAll();

// Referensi jenis, fungsi, dan 22 kab/kota
$jenisList = $pdo->query("SELECT * FROM ref_jenis_kendaraan ORDER BY kode_jenis ASC")->fetchAll();
$fungsiList = $pdo->query("SELECT * FROM ref_fungsi_tarif ORDER BY kode_fungsi ASC")->fetchAll();
$kabKotaList = getKabKotaList($pdo);
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('anchor', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Tabel Master Kendaraan di Atas Air (Master KAA NTT)</div>
      <div class="page-subtitle">Basis Data Registrasi Kapal, Asal Kab/Kota, Status NJKB & Riwayat Objek Pajak Seluruh NTT</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="index.php?page=pendaftaran" class="btn btn-primary btn-sm">
      <?= renderIcon('plus', '', 14) ?> Pendaftaran Baru (F2)
    </a>
  </div>
</div>

<?php if (!empty($pesan)): ?>
  <div class="alert alert-<?= $tipePesan ?>">
    <?= renderIcon($tipePesan === 'success' ? 'check-circle' : 'alert-triangle', '', 18) ?>
    <span><?= $pesan ?></span>
  </div>
<?php endif; ?>

<!-- Form Filter & Query -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('search', '', 16) ?>
      <span>PENCARIAN & FILTER KENDARAAN AIR NTT</span>
    </div>
  </div>
  <div class="panel-body">
    <form method="GET" action="index.php" style="display:grid; grid-template-columns: 1.5fr 1.2fr 1fr 1fr auto; gap:10px; align-items:flex-end;">
      <input type="hidden" name="page" value="master_kaa">
      
      <div class="form-group">
        <label class="form-label">Cari Nopol KA / Nama Kapal / Pemilik</label>
        <div class="input-group">
          <input type="text" name="q" class="form-control" placeholder="Contoh: KA 0001 MB..." value="<?= htmlspecialchars($search) ?>">
          <button type="button" class="btn btn-secondary" onclick="bukaLayarBantu('q')">Bantu (Ctrl+B)</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Kabupaten / Kota di NTT</label>
        <select name="kab" class="form-control">
          <option value="">-- Semua 22 Kab/Kota --</option>
          <?php foreach ($kabKotaList as $kb): ?>
            <option value="<?= $kb['kode_wilayah'] ?>" <?= $filterKab === $kb['kode_wilayah'] ? 'selected' : '' ?>>
              [<?= $kb['kode_wilayah'] ?>] <?= htmlspecialchars($kb['nama_kabupaten_kota']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Jenis Kendaraan</label>
        <select name="jenis" class="form-control">
          <option value="">-- Semua Jenis --</option>
          <?php foreach ($jenisList as $j): ?>
            <option value="<?= $j['kode_jenis'] ?>" <?= $filterJenis === $j['kode_jenis'] ? 'selected' : '' ?>>
              [<?= $j['kode_jenis'] ?>] <?= htmlspecialchars($j['nama_jenis']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Fungsi Kendaraan</label>
        <select name="fungsi" class="form-control">
          <option value="">-- Semua Fungsi --</option>
          <?php foreach ($fungsiList as $f): ?>
            <option value="<?= $f['kode_fungsi'] ?>" <?= $filterFungsi === $f['kode_fungsi'] ? 'selected' : '' ?>>
              [<?= $f['kode_fungsi'] ?>] <?= htmlspecialchars($f['nama_fungsi']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex; gap:6px;">
        <button type="submit" class="btn btn-primary" style="height:32px;">
          <?= renderIcon('search', '', 14) ?> Filter
        </button>
        <?php if (!empty($search) || !empty($filterKab) || !empty($filterJenis) || !empty($filterFungsi)): ?>
          <a href="index.php?page=master_kaa" class="btn btn-secondary" style="height:32px;">Reset</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<!-- Tabel Master KAA -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('database', '', 16) ?>
      <span>Data Kapal Terdaftar di Seluruh Wilayah NTT (Total: <?= count($kapalList) ?> Unit)</span>
    </div>
  </div>

  <div class="panel-body" style="padding: 0;">
    <div class="table-responsive" style="border: none;">
      <table class="table-desktop">
        <thead>
          <tr>
            <th width="125">Nopol KA</th>
            <th>Nama Kapal / SB</th>
            <th>Asal Wilayah NTT</th>
            <th>Nama Pemilik</th>
            <th>Fungsi / Tarif</th>
            <th>Metode NJKB</th>
            <th class="text-right">Total NJKB</th>
            <th>Jatuh Tempo</th>
            <th class="text-center" width="120">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kapalList)): ?>
            <tr>
              <td colspan="9" class="text-center" style="padding: 24px; color: #94a3b8;">
                Tidak ada data kapal yang sesuai kriteria pencarian.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($kapalList as $k): ?>
              <tr>
                <td>
                  <strong style="color:#0284c7; font-family:var(--font-mono); font-size:12.5px;"><?= htmlspecialchars($k['no_objek_pajak']) ?></strong>
                </td>
                <td>
                  <strong><?= htmlspecialchars($k['nama_kapal']) ?></strong><br>
                  <span style="font-size: 10px; color: #64748b;">Pelabuhan: <?= htmlspecialchars($k['pelabuhan_pangkal']) ?></span>
                </td>
                <td>
                  <span class="badge badge-info"><?= htmlspecialchars($k['kode_kabkota'] ?: 'NTT') ?></span><br>
                  <span style="font-size: 11px;"><?= htmlspecialchars($k['nama_kabupaten_kota'] ?? '-') ?></span>
                </td>
                <td>
                  <?= htmlspecialchars($k['nama_pemilik']) ?><br>
                  <span style="font-size: 10px; color: #64748b;"><?= htmlspecialchars($k['no_hp'] ?: '-') ?></span>
                </td>
                <td>
                  <span class="badge badge-neutral"><?= htmlspecialchars($k['nama_fungsi']) ?> (<?= $k['tarif_persen'] ?>%)</span><br>
                  <span style="font-size:10px; color:#64748b;"><?= htmlspecialchars($k['nama_jenis']) ?> &bull; <?= $k['kapasitas_gt'] ?> GT</span>
                </td>
                <td>
                  <?php if (($k['metode_njkb'] ?? '') === 'GABUNGAN'): ?>
                    <span class="badge badge-warning">1 Kesatuan Paket</span><br>
                    <small style="font-size:10px; color:#475569; font-weight:600;"><?= htmlspecialchars($k['merek_gabungan'] ?: 'Paket Standar') ?></small>
                  <?php else: ?>
                    <span class="badge badge-neutral">Body + <?= $k['jumlah_mesin'] ?> Mesin</span><br>
                    <small style="font-size:10px; color:#475569; font-weight:600;"><?= htmlspecialchars($k['merek_body'] ?: 'Body Standar') ?> &bull; <?= htmlspecialchars($k['merek_mesin'] ?: 'Mesin') ?></small>
                  <?php endif; ?>
                </td>
                <td class="text-right"><strong><?= formatRupiah($k['total_njkb']) ?></strong></td>
                <td>
                  <?php 
                    $isExpired = strtotime($k['tg_akhir_pajak']) < time();
                  ?>
                  <span style="color: <?= $isExpired ? '#dc2626' : '#059669' ?>; font-weight: <?= $isExpired ? '700' : 'normal' ?>;">
                    <?= formatTanggalIndo($k['tg_akhir_pajak'], false) ?>
                  </span>
                </td>
                <td class="text-center">
                  <div style="display:flex; justify-content:center; gap:4px;">
                    <a href="index.php?page=superpkaa&nopd=<?= urlencode($k['no_objek_pajak']) ?>" class="btn btn-secondary btn-sm" title="Tetapkan Pajak (SUPERPKAA)">
                      <?= renderIcon('file-text', '', 13) ?>
                    </a>
                    <form method="POST" action="index.php?page=master_kaa" onsubmit="return confirm('Hapus kapal <?= $k['no_objek_pajak'] ?> - <?= addslashes($k['nama_kapal']) ?>?');" style="margin:0;">
                      <input type="hidden" name="aksi" value="hapus_kaa">
                      <input type="hidden" name="del_nopd" value="<?= htmlspecialchars($k['no_objek_pajak']) ?>">
                      <button type="submit" class="btn btn-danger btn-sm" title="Hapus Objek">
                        <?= renderIcon('trash', '', 13) ?>
                      </button>
                    </form>
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
