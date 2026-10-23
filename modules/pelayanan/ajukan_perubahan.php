<?php
// modules/pelayanan/ajukan_perubahan.php - Formulir Pengajuan Perubahan Data KAA oleh Operator
$pageTitle = "Ajukan Perubahan Data KAA";
$currentUser = currentUser();

$selectedNopd = trim($_GET['nopd'] ?? '');
$targetKaa = null;

if (!empty($selectedNopd)) {
    $stmt = $pdo->prepare("SELECT * FROM kendaraan_air WHERE no_objek_pajak = ? LIMIT 1");
    $stmt->execute([$selectedNopd]);
    $targetKaa = $stmt->fetch();
}

// Proses Simpan Pengajuan Perubahan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_pengajuan') {
    $nopd = strtoupper(trim($_POST['no_objek_pajak'] ?? ''));
    $namaKapal = trim($_POST['nama_kapal'] ?? '');
    $namaPemilikLama = trim($_POST['nama_pemilik_lama'] ?? '');
    $namaPemilikBaru = trim($_POST['nama_pemilik_baru'] ?? '');
    $alamatLama = trim($_POST['alamat_lama'] ?? '');
    $alamatBaru = trim($_POST['alamat_baru'] ?? '');
    $pelabuhanLama = trim($_POST['pelabuhan_lama'] ?? '');
    $pelabuhanBaru = trim($_POST['pelabuhan_baru'] ?? '');
    $tandaSelarLama = trim($_POST['tanda_selar_lama'] ?? '');
    $tandaSelarBaru = trim($_POST['tanda_selar_baru'] ?? '');
    $alasan = trim($_POST['alasan_perubahan'] ?? '');
    $noSurat = trim($_POST['lampiran_dokumen'] ?? '');

    if (empty($nopd) || empty($alasan) || (empty($namaPemilikBaru) && empty($alamatBaru) && empty($pelabuhanBaru) && empty($tandaSelarBaru))) {
        setFlash('warning', 'Peringatan', 'Harap pilih NOPD kapal dan isi setidaknya satu data yang diubah beserta alasan pengajuan!');
    } else {
        try {
            $year = date('Y');
            $month = date('m');
            $prefix = "PGB/KAA/$year/$month/";
            $stmtC = $pdo->prepare("SELECT no_pengajuan FROM pengajuan_perubahan_kaa WHERE no_pengajuan LIKE ? ORDER BY id DESC LIMIT 1");
            $stmtC->execute([$prefix . '%']);
            $lastP = $stmtC->fetchColumn();
            $seq = 1;
            if ($lastP && preg_match('/(\d{4})$/', $lastP, $m)) {
                $seq = (int)$m[1] + 1;
            }
            $noPengajuan = sprintf('%s%04d', $prefix, $seq);

            $diffData = [
                'nama_pemilik' => ['lama' => $namaPemilikLama, 'baru' => $namaPemilikBaru ?: $namaPemilikLama],
                'alamat_pemilik' => ['lama' => $alamatLama, 'baru' => $alamatBaru ?: $alamatLama],
                'pelabuhan_pangkal' => ['lama' => $pelabuhanLama, 'baru' => $pelabuhanBaru ?: $pelabuhanLama],
                'tanda_selar' => ['lama' => $tandaSelarLama, 'baru' => $tandaSelarBaru ?: $tandaSelarLama],
            ];

            $stmtIns = $pdo->prepare("
                INSERT INTO pengajuan_perubahan_kaa (
                    no_pengajuan, no_objek_pajak, nama_kapal, nama_pemilik,
                    perubahan_data, alasan_perubahan, lampiran_dokumen, status,
                    diajukan_oleh_id, diajukan_oleh_nama
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'MENUNGGU_VERIFIKASI', ?, ?)
            ");
            $stmtIns->execute([
                $noPengajuan,
                $nopd,
                $namaKapal,
                $namaPemilikLama,
                json_encode($diffData, JSON_PRETTY_PRINT),
                $alasan,
                $noSurat,
                $currentUser['id'] ?? 1,
                $currentUser['nama_lengkap'] ?? 'Operator'
            ]);

            setFlash('success', 'Pengajuan Terkirim!', "Pengajuan perubahan data untuk <strong>$nopd</strong> ($namaKapal) dengan Nomor <strong>$noPengajuan</strong> berhasil dikirim ke Admin Induk.");
            safeRedirect("index.php?page=status_pengajuan");
        } catch (Exception $e) {
            setFlash('error', 'Gagal Mengajukan', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('file-edit', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Formulir Pengajuan Perubahan Data Kendaraan Air</div>
      <div class="page-subtitle">Pengajuan Mutasi Pemilik, Perubahan Alamat, atau Pelabuhan Pangkal untuk Diverifikasi Admin Induk</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="index.php?page=status_pengajuan" class="btn btn-secondary btn-sm">
      <?= renderIcon('clock', '', 14) ?> Lihat Riwayat Status Pengajuan
    </a>
  </div>
</div>

<!-- Pencarian Objek Pajak yang Akan Diubah -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('search', '', 16) ?>
      <span>PILIH OBJEK PAJAK KENDARAAN (NOPD) YANG AKAN DIUBAH</span>
    </div>
  </div>
  <div class="panel-body">
    <form method="GET" action="index.php" style="display:flex; gap:10px; align-items:flex-end;">
      <input type="hidden" name="page" value="ajukan_perubahan">
      <div class="form-group" style="flex:1; margin-bottom:0;">
        <label class="form-label" for="input_nopd_cari">Masukkan NOPD Kapal (KA 0001 XX) <span class="required">*</span></label>
        <div class="input-group">
          <input type="text" name="nopd" id="input_nopd_cari" class="form-control" value="<?= htmlspecialchars($selectedNopd) ?>" placeholder="Contoh: KA 0001 MB" required style="font-weight:700; font-family:var(--font-mono);">
          <button type="button" class="btn btn-secondary" onclick="bukaLayarBantu('input_nopd_cari')">
            <?= renderIcon('search', '', 14) ?> Layar Bantu
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="height:32px;">
        <?= renderIcon('check', '', 14) ?> Muat Data Kapal
      </button>
    </form>
  </div>
</div>

<?php if ($targetKaa): ?>
  <!-- Formulir Isian Perubahan Data -->
  <form method="POST" action="index.php?page=ajukan_perubahan" class="panel">
    <input type="hidden" name="aksi" value="simpan_pengajuan">
    <input type="hidden" name="no_objek_pajak" value="<?= htmlspecialchars($targetKaa['no_objek_pajak']) ?>">
    <input type="hidden" name="nama_kapal" value="<?= htmlspecialchars($targetKaa['nama_kapal']) ?>">
    <input type="hidden" name="nama_pemilik_lama" value="<?= htmlspecialchars($targetKaa['nama_pemilik']) ?>">
    <input type="hidden" name="alamat_lama" value="<?= htmlspecialchars($targetKaa['alamat_pemilik']) ?>">
    <input type="hidden" name="pelabuhan_lama" value="<?= htmlspecialchars($targetKaa['pelabuhan_pangkal']) ?>">
    <input type="hidden" name="tanda_selar_lama" value="<?= htmlspecialchars($targetKaa['tanda_selar']) ?>">

    <div class="panel-header">
      <div class="panel-title">
        <?= renderIcon('file-text', '', 16) ?>
        <span>PERBANDINGAN DATA LAMA & DATA USULAN PERUBAHAN: <strong><?= htmlspecialchars($targetKaa['no_objek_pajak']) ?></strong> (<?= htmlspecialchars($targetKaa['nama_kapal']) ?>)</span>
      </div>
      <span class="badge badge-info">Status Saat Ini: <?= $targetKaa['status_pajak'] ?></span>
    </div>

    <div class="panel-body">
      <div class="table-responsive" style="margin-bottom: 16px;">
        <table class="table-desktop">
          <thead>
            <tr>
              <th width="180">Elemen Data</th>
              <th width="380">Data Terdaftar Saat Ini (Lama)</th>
              <th>Data Usulan Baru (Kosongkan jika tidak berubah)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Nama Pemilik / Wajib Pajak</strong></td>
              <td><span style="color:#475569; font-weight:600;"><?= htmlspecialchars($targetKaa['nama_pemilik']) ?></span></td>
              <td>
                <input type="text" name="nama_pemilik_baru" class="form-control" placeholder="Isi nama pemilik baru jika balik nama / mutasi">
              </td>
            </tr>
            <tr>
              <td><strong>Alamat Pemilik</strong></td>
              <td><span style="color:#475569;"><?= htmlspecialchars($targetKaa['alamat_pemilik']) ?></span></td>
              <td>
                <textarea name="alamat_baru" class="form-control" rows="2" placeholder="Isi alamat baru lengkap jika berpindah domisili"></textarea>
              </td>
            </tr>
            <tr>
              <td><strong>Pelabuhan Pangkal</strong></td>
              <td><span style="color:#475569; font-weight:600;"><?= htmlspecialchars($targetKaa['pelabuhan_pangkal']) ?></span></td>
              <td>
                <input type="text" name="pelabuhan_baru" class="form-control" placeholder="Isi pelabuhan pangkal baru jika mutasi pelabuhan">
              </td>
            </tr>
            <tr>
              <td><strong>Tanda Selar / No. Pas</strong></td>
              <td><span style="color:#475569;"><?= htmlspecialchars($targetKaa['tanda_selar'] ?: '-') ?></span></td>
              <td>
                <input type="text" name="tanda_selar_baru" class="form-control" placeholder="Isi tanda selar baru jika ada pembaruan ukur ulang">
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Alasan & Dokumen Pendukung -->
      <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:14px;">
        <div class="form-grid-2">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="alasan_perubahan">Alasan Pengajuan Perubahan Data <span class="required">*</span></label>
            <textarea name="alasan_perubahan" id="alasan_perubahan" class="form-control" rows="2" required placeholder="Contoh: Balik nama kepemilikan jual beli kapal akta notaris No. 12/2026 atau mutasi pelabuhan pangkal"></textarea>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="lampiran_dokumen">No. Dokumen / Surat Keterangan Pendukung <span class="required">*</span></label>
            <input type="text" name="lampiran_dokumen" id="lampiran_dokumen" class="form-control" required placeholder="Contoh: Akta Jual Beli No. 44/2026 / Surat Keterangan Dishub">
          </div>
        </div>
      </div>
    </div>

    <div class="panel-header" style="justify-content:flex-end; gap:8px; border-top:1px solid var(--border-medium); border-bottom:none;">
      <a href="index.php?page=ajukan_perubahan" class="btn btn-secondary">Batal</a>
      <button type="submit" class="btn btn-primary" style="background:#0284c7; border-color:#0369a1; font-weight:700;">
        <?= renderIcon('send', '', 14) ?> KIRIM PENGAJUAN PERUBAHAN KE ADMIN INDUK
      </button>
    </div>
  </form>
<?php endif; ?>
