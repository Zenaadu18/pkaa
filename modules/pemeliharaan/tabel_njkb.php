<?php
// modules/pemeliharaan/tabel_njkb.php - Master Data & Referensi Nilai Jual (NJKB) JANGKAR NTT
$pageTitle = "Tabel Referensi NJKB Kendaraan Air";

$activeTab = $_GET['tab'] ?? 'body';
$search = trim($_GET['q'] ?? '');

// Handle Tambah / Edit Benchmark NJKB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    if ($aksi === 'simpan_njkb_body') {
        $merek = trim($_POST['merek'] ?? '');
        $tipeModel = trim($_POST['tipe_model'] ?? 'Standar');
        $tahun = (int)($_POST['tahun_buat'] ?? date('Y'));
        $panjang = (float)($_POST['panjang_meter'] ?? 0);
        $nilai = (float)preg_replace('/[^0-9]/', '', $_POST['nilai_jual'] ?? '0');
        $ket = trim($_POST['keterangan'] ?? '');

        if (!empty($merek) && $tahun > 1950 && $nilai > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO ref_njkb_body (merek, tipe_model, tahun_buat, panjang_meter, nilai_jual, keterangan)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    tipe_model = VALUES(tipe_model),
                    panjang_meter = VALUES(panjang_meter),
                    nilai_jual = VALUES(nilai_jual),
                    keterangan = VALUES(keterangan)
            ");
            $stmt->execute([$merek, $tipeModel, $tahun, $panjang, $nilai, $ket]);
            setFlash('success', 'NJKB Body Disimpan', "Standar nilai jual body kapal <strong>$merek ($tahun)</strong> berhasil disimpan.");
            safeRedirect("index.php?page=tabel_njkb&tab=body");
        } else {
            setFlash('warning', 'Data Belum Lengkap', 'Merek, tahun pembuatan, dan nilai jual rupiah wajib diisi!');
        }
    } elseif ($aksi === 'simpan_njkb_mesin') {
        $merek = trim($_POST['merek'] ?? '');
        $tipeTenaga = trim($_POST['tipe_tenaga'] ?? 'Outboard');
        $dayaPk = (int)($_POST['daya_pk'] ?? 0);
        $tahun = (int)($_POST['tahun_buat'] ?? date('Y'));
        $nilai = (float)preg_replace('/[^0-9]/', '', $_POST['nilai_jual'] ?? '0');
        $ket = trim($_POST['keterangan'] ?? '');

        if (!empty($merek) && $tahun > 1950 && $nilai > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO ref_njkb_mesin (merek, tipe_tenaga, daya_pk, tahun_buat, nilai_jual, keterangan)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    tipe_tenaga = VALUES(tipe_tenaga),
                    nilai_jual = VALUES(nilai_jual),
                    keterangan = VALUES(keterangan)
            ");
            $stmt->execute([$merek, $tipeTenaga, $dayaPk, $tahun, $nilai, $ket]);
            setFlash('success', 'NJKB Mesin Disimpan', "Standar nilai jual mesin <strong>$merek $dayaPk PK ($tahun)</strong> berhasil disimpan.");
            safeRedirect("index.php?page=tabel_njkb&tab=mesin");
        } else {
            setFlash('warning', 'Data Belum Lengkap', 'Merek, daya PK, tahun buat, dan nilai jual mesin wajib diisi!');
        }
    } elseif ($aksi === 'simpan_njkb_gabungan') {
        $merekPaket = trim($_POST['merek_paket'] ?? '');
        $merekBody = trim($_POST['merek_body'] ?? 'Body Standar');
        $merekMesin = trim($_POST['merek_mesin'] ?? 'Mesin Standar');
        $tahun = (int)($_POST['tahun_buat'] ?? date('Y'));
        $gt = (float)($_POST['kapasitas_gt'] ?? 0);
        $nilai = (float)preg_replace('/[^0-9]/', '', $_POST['nilai_jual'] ?? '0');
        $ket = trim($_POST['keterangan'] ?? '');

        if (!empty($merekPaket) && $tahun > 1950 && $nilai > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO ref_njkb_gabungan (merek_paket, merek_body, merek_mesin, tahun_buat, kapasitas_gt, nilai_jual, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    merek_body = VALUES(merek_body),
                    merek_mesin = VALUES(merek_mesin),
                    kapasitas_gt = VALUES(kapasitas_gt),
                    nilai_jual = VALUES(nilai_jual),
                    keterangan = VALUES(keterangan)
            ");
            $stmt->execute([$merekPaket, $merekBody, $merekMesin, $tahun, $gt, $nilai, $ket]);
            setFlash('success', 'NJKB 1 Kesatuan Disimpan', "Paket 1 Kesatuan <strong>$merekPaket ($tahun)</strong> berhasil disimpan.");
            safeRedirect("index.php?page=tabel_njkb&tab=gabungan");
        } else {
            setFlash('warning', 'Data Belum Lengkap', 'Nama merek paket, tahun buat, dan nilai jual wajib diisi!');
        }
    } elseif ($aksi === 'hapus_njkb') {
        $tipe = $_POST['tipe'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            if ($tipe === 'body') {
                $pdo->prepare("DELETE FROM ref_njkb_body WHERE id = ?")->execute([$id]);
            } elseif ($tipe === 'mesin') {
                $pdo->prepare("DELETE FROM ref_njkb_mesin WHERE id = ?")->execute([$id]);
            } elseif ($tipe === 'gabungan') {
                $pdo->prepare("DELETE FROM ref_njkb_gabungan WHERE id = ?")->execute([$id]);
            }
            setFlash('success', 'Data Dihapus', 'Referensi nilai jual berhasil dihapus.');
            safeRedirect("index.php?page=tabel_njkb&tab=" . urlencode($tipe));
        }
    }
}

// Data Queries
$bodyList = [];
$mesinList = [];
$gabunganList = [];

if ($activeTab === 'body') {
    $sql = "SELECT * FROM ref_njkb_body WHERE 1=1 ";
    $p = [];
    if (!empty($search)) {
        $sql .= " AND (merek LIKE ? OR tipe_model LIKE ? OR tahun_buat LIKE ?) ";
        $p = ["%$search%", "%$search%", "%$search%"];
    }
    $sql .= " ORDER BY merek ASC, tahun_buat DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $bodyList = $stmt->fetchAll();
} elseif ($activeTab === 'mesin') {
    $sql = "SELECT * FROM ref_njkb_mesin WHERE 1=1 ";
    $p = [];
    if (!empty($search)) {
        $sql .= " AND (merek LIKE ? OR tipe_tenaga LIKE ? OR tahun_buat LIKE ?) ";
        $p = ["%$search%", "%$search%", "%$search%"];
    }
    $sql .= " ORDER BY merek ASC, daya_pk DESC, tahun_buat DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $mesinList = $stmt->fetchAll();
} else {
    $sql = "SELECT * FROM ref_njkb_gabungan WHERE 1=1 ";
    $p = [];
    if (!empty($search)) {
        $sql .= " AND (merek_paket LIKE ? OR merek_body LIKE ? OR merek_mesin LIKE ? OR tahun_buat LIKE ?) ";
        $p = ["%$search%", "%$search%", "%$search%", "%$search%"];
    }
    $sql .= " ORDER BY merek_paket ASC, tahun_buat DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    $gabunganList = $stmt->fetchAll();
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('coins', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Tabel Referensi Nilai Jual Objek Pajak (NJKB Air)</div>
      <div class="page-subtitle">Basis Data Standar Harga Body Kapal, Mesin Penggerak, dan Paket 1 Kesatuan &bull; BPAD Prov. NTT</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="index.php?page=pendaftaran" class="btn btn-secondary btn-sm">
      <?= renderIcon('ship', '', 14) ?> Pendaftaran KAA
    </a>
  </div>
</div>

<!-- Navigasi Tab -->
<div style="display:flex; gap:8px; margin-bottom: 14px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px;">
  <a href="index.php?page=tabel_njkb&tab=body" class="btn btn-sm <?= $activeTab === 'body' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 4px 4px 0 0;">
    <?= renderIcon('ship', '', 14) ?> 1. Nilai Jual Body Kapal (<?= $pdo->query("SELECT COUNT(*) FROM ref_njkb_body")->fetchColumn() ?>)
  </a>
  <a href="index.php?page=tabel_njkb&tab=mesin" class="btn btn-sm <?= $activeTab === 'mesin' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 4px 4px 0 0;">
    <?= renderIcon('cpu', '', 14) ?> 2. Nilai Jual Mesin Penggerak (<?= $pdo->query("SELECT COUNT(*) FROM ref_njkb_mesin")->fetchColumn() ?>)
  </a>
  <a href="index.php?page=tabel_njkb&tab=gabungan" class="btn btn-sm <?= $activeTab === 'gabungan' ? 'btn-primary' : 'btn-secondary' ?>" style="border-radius: 4px 4px 0 0;">
    <?= renderIcon('anchor', '', 14) ?> 3. Nilai Jual 1 Kesatuan (Body+Mesin) (<?= $pdo->query("SELECT COUNT(*) FROM ref_njkb_gabungan")->fetchColumn() ?>)
  </a>
</div>

<!-- Layout 2 Kolom: Form Input Standar Baru & Tabel Daftar -->
<div style="display: grid; grid-template-columns: 340px 1fr; gap: 14px;">

  <!-- Form Input Standar NJKB Baru -->
  <div class="panel" style="align-self: start;">
    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('plus-circle', '', 16) ?>
        <span>Tambah / Perbarui Standar NJKB</span>
      </div>
    </div>
    <div class="panel-body">
      <?php if ($activeTab === 'body'): ?>
        <form method="POST" action="index.php?page=tabel_njkb&tab=body">
          <input type="hidden" name="aksi" value="simpan_njkb_body">
          <div class="form-group">
            <label class="form-label">Merek / Pembuat Body <span class="required">*</span></label>
            <input type="text" name="merek" class="form-control" placeholder="Contoh: Phinisi Nusantara" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tipe / Model Desain</label>
            <input type="text" name="tipe_model" class="form-control" placeholder="Contoh: Speedboat Fiber Deep-V">
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Tahun Pembuatan <span class="required">*</span></label>
              <input type="number" name="tahun_buat" class="form-control" value="<?= date('Y') ?>" min="1970" max="<?= date('Y') + 1 ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Panjang (Meter)</label>
              <input type="number" step="0.1" name="panjang_meter" class="form-control" placeholder="Contoh: 14.5">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nilai Jual Standar (Rp) <span class="required">*</span></label>
            <div class="input-group">
              <span class="input-prefix">Rp</span>
              <input type="number" step="1000000" name="nilai_jual" class="form-control" placeholder="450000000" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan Tambahan</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Catatan spesifikasi material">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">
            <?= renderIcon('check', '', 14) ?> Simpan Standar Body
          </button>
        </form>

      <?php elseif ($activeTab === 'mesin'): ?>
        <form method="POST" action="index.php?page=tabel_njkb&tab=mesin">
          <input type="hidden" name="aksi" value="simpan_njkb_mesin">
          <div class="form-group">
            <label class="form-label">Merek Pabrikan Mesin <span class="required">*</span></label>
            <input type="text" name="merek" class="form-control" placeholder="Contoh: Yamaha Marine / Yanmar" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tipe / Konfigurasi Tenaga</label>
            <input type="text" name="tipe_tenaga" class="form-control" placeholder="Contoh: Outboard 4-Tak V6">
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Daya Mesin (PK/HP) <span class="required">*</span></label>
              <input type="number" name="daya_pk" class="form-control" placeholder="250" required>
            </div>
            <div class="form-group">
              <label class="form-label">Tahun Pembuatan <span class="required">*</span></label>
              <input type="number" name="tahun_buat" class="form-control" value="<?= date('Y') ?>" min="1970" max="<?= date('Y') + 1 ?>" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nilai Jual Standar (Rp) <span class="required">*</span></label>
            <div class="input-group">
              <span class="input-prefix">Rp</span>
              <input type="number" step="1000000" name="nilai_jual" class="form-control" placeholder="260000000" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan Mesin</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Bensin / Solar Marine Diesel">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">
            <?= renderIcon('check', '', 14) ?> Simpan Standar Mesin
          </button>
        </form>

      <?php else: ?>
        <form method="POST" action="index.php?page=tabel_njkb&tab=gabungan">
          <input type="hidden" name="aksi" value="simpan_njkb_gabungan">
          <div class="form-group">
            <label class="form-label">Merek / Nama Paket 1 Kesatuan <span class="required">*</span></label>
            <input type="text" name="merek_paket" class="form-control" placeholder="Contoh: Speedboat Ti'i Langga Paket Suzuki" required>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Merek Body</label>
              <input type="text" name="merek_body" class="form-control" placeholder="Kupang Fiber">
            </div>
            <div class="form-group">
              <label class="form-label">Merek Mesin</label>
              <input type="text" name="merek_mesin" class="form-control" placeholder="Suzuki 200HP">
            </div>
          </div>
          <div class="form-grid-2">
            <div class="form-group">
              <label class="form-label">Tahun Pembuatan <span class="required">*</span></label>
              <input type="number" name="tahun_buat" class="form-control" value="<?= date('Y') ?>" min="1970" max="<?= date('Y') + 1 ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Kapasitas (GT)</label>
              <input type="number" step="0.1" name="kapasitas_gt" class="form-control" placeholder="12.0">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nilai Jual Paket 1 Kesatuan (Rp) <span class="required">*</span></label>
            <div class="input-group">
              <span class="input-prefix">Rp</span>
              <input type="number" step="1000000" name="nilai_jual" class="form-control" placeholder="360000000" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan Paket</label>
            <input type="text" name="keterangan" class="form-control" placeholder="Paket lengkap body + mesin">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px;">
            <?= renderIcon('check', '', 14) ?> Simpan Paket 1 Kesatuan
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabel Daftar NJKB Aktif -->
  <div class="panel">
    <div class="panel-header" style="justify-content: space-between;">
      <div class="panel-title">
        <?= renderIcon('list', '', 16) ?>
        <span>Daftar Standar NJKB di Database (Otomatis Tersambung ke Form Pendaftaran)</span>
      </div>
      <form method="GET" action="index.php" style="display:flex; gap:6px;">
        <input type="hidden" name="page" value="tabel_njkb">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
        <input type="text" name="q" class="form-control" style="width:200px; height:28px; font-size:11px;" placeholder="Cari merek / tahun..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-secondary btn-sm" style="height:28px;">
          <?= renderIcon('search', '', 12) ?> Cari
        </button>
      </form>
    </div>
    <div class="panel-body" style="padding:0;">
      <div class="table-responsive">
        <?php if ($activeTab === 'body'): ?>
          <table class="table-desktop">
            <thead>
              <tr>
                <th width="40">NO</th>
                <th>MEREK BODY KAPAL</th>
                <th>TIPE / MODEL</th>
                <th class="text-center">TAHUN</th>
                <th class="text-center">PANJANG</th>
                <th class="text-right">NILAI JUAL STANDAR (Rp)</th>
                <th width="60" class="text-center">AKSI</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($bodyList)): ?>
                <tr><td colspan="7" class="text-center" style="padding:20px; color:#94a3b8;">Tidak ada data NJKB body.</td></tr>
              <?php else: ?>
                <?php $no = 1; foreach ($bodyList as $r): ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><strong><?= htmlspecialchars($r['merek']) ?></strong></td>
                    <td><?= htmlspecialchars($r['tipe_model'] ?: '-') ?></td>
                    <td class="text-center"><span class="badge badge-info"><?= $r['tahun_buat'] ?></span></td>
                    <td class="text-center"><?= $r['panjang_meter'] ? $r['panjang_meter'] . ' M' : '-' ?></td>
                    <td class="text-right"><strong style="color:#0284c7;"><?= formatRupiah($r['nilai_jual']) ?></strong></td>
                    <td class="text-center">
                      <form method="POST" action="index.php?page=tabel_njkb&tab=body" onsubmit="return confirm('Hapus referensi NJKB ini?')">
                        <input type="hidden" name="aksi" value="hapus_njkb">
                        <input type="hidden" name="tipe" value="body">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding:2px 6px;">
                          <?= renderIcon('trash', '', 12) ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

        <?php elseif ($activeTab === 'mesin'): ?>
          <table class="table-desktop">
            <thead>
              <tr>
                <th width="40">NO</th>
                <th>MEREK MESIN</th>
                <th>TIPE / KONFIGURASI</th>
                <th class="text-center">DAYA (PK/HP)</th>
                <th class="text-center">TAHUN</th>
                <th class="text-right">NILAI JUAL STANDAR (Rp)</th>
                <th width="60" class="text-center">AKSI</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($mesinList)): ?>
                <tr><td colspan="7" class="text-center" style="padding:20px; color:#94a3b8;">Tidak ada data NJKB mesin.</td></tr>
              <?php else: ?>
                <?php $no = 1; foreach ($mesinList as $r): ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><strong><?= htmlspecialchars($r['merek']) ?></strong></td>
                    <td><?= htmlspecialchars($r['tipe_tenaga'] ?: '-') ?></td>
                    <td class="text-center"><span class="badge badge-neutral"><?= $r['daya_pk'] ? $r['daya_pk'] . ' PK' : '-' ?></span></td>
                    <td class="text-center"><span class="badge badge-info"><?= $r['tahun_buat'] ?></span></td>
                    <td class="text-right"><strong style="color:#059669;"><?= formatRupiah($r['nilai_jual']) ?></strong></td>
                    <td class="text-center">
                      <form method="POST" action="index.php?page=tabel_njkb&tab=mesin" onsubmit="return confirm('Hapus referensi NJKB ini?')">
                        <input type="hidden" name="aksi" value="hapus_njkb">
                        <input type="hidden" name="tipe" value="mesin">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding:2px 6px;">
                          <?= renderIcon('trash', '', 12) ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

        <?php else: ?>
          <table class="table-desktop">
            <thead>
              <tr>
                <th width="40">NO</th>
                <th>NAMA MEREK PAKET 1 KESATUAN</th>
                <th>KOMPOSISI BODY + MESIN</th>
                <th class="text-center">TAHUN</th>
                <th class="text-center">GT</th>
                <th class="text-right">NILAI JUAL 1 KESATUAN (Rp)</th>
                <th width="60" class="text-center">AKSI</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($gabunganList)): ?>
                <tr><td colspan="7" class="text-center" style="padding:20px; color:#94a3b8;">Tidak ada data NJKB 1 kesatuan.</td></tr>
              <?php else: ?>
                <?php $no = 1; foreach ($gabunganList as $r): ?>
                  <tr>
                    <td><?= $no++ ?></td>
                    <td><strong><?= htmlspecialchars($r['merek_paket']) ?></strong></td>
                    <td><?= htmlspecialchars($r['merek_body']) ?> + <?= htmlspecialchars($r['merek_mesin']) ?></td>
                    <td class="text-center"><span class="badge badge-info"><?= $r['tahun_buat'] ?></span></td>
                    <td class="text-center"><?= $r['kapasitas_gt'] ? $r['kapasitas_gt'] . ' GT' : '-' ?></td>
                    <td class="text-right"><strong style="color:#d97706;"><?= formatRupiah($r['nilai_jual']) ?></strong></td>
                    <td class="text-center">
                      <form method="POST" action="index.php?page=tabel_njkb&tab=gabungan" onsubmit="return confirm('Hapus referensi NJKB ini?')">
                        <input type="hidden" name="aksi" value="hapus_njkb">
                        <input type="hidden" name="tipe" value="gabungan">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" style="padding:2px 6px;">
                          <?= renderIcon('trash', '', 12) ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
