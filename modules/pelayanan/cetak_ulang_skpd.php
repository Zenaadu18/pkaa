<?php
// modules/pelayanan/cetak_ulang_skpd.php - Pelayanan Cetak & Arsip SKPD JANGKAR NTT
if (!defined('APP_INIT')) {
    header("Location: ../../index.php");
    exit();
}

$pageTitle = "Cetak & Arsip SKPD PKAA";

$user = currentUser();
$isAdmin = hasRole('ADMIN');

// Handle Batalkan SKPD (Hanya Role ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'batalkan_skpd') {
    if (!$isAdmin) {
        setFlash('error', 'Akses Ditolak', 'Hanya Administrator yang memiliki wewenang untuk membatalkan SKPD yang telah diterbitkan.');
        header("Location: index.php?page=cetak_ulang_skpd&tab=arsip_skpd");
        exit();
    }

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Validasi Keamanan Gagal (CSRF)', 'Sesi atau token keamanan Anda tidak valid / kedaluwarsa. Silakan muat ulang halaman.');
        header("Location: index.php?page=cetak_ulang_skpd&tab=arsip_skpd");
        exit();
    }

    $noSkpd = trim($_POST['no_skpd'] ?? '');
    $alasan = trim($_POST['alasan_batal'] ?? '');
    $noBa = trim($_POST['no_berita_acara'] ?? '');

    if (empty($noSkpd) || empty($alasan)) {
        setFlash('warning', 'Data Belum Lengkap', 'Nomor SKPD dan alasan pembatalan wajib diisi!');
        header("Location: index.php?page=cetak_ulang_skpd&tab=arsip_skpd");
        exit();
    }

    // Ambil data transaksi_skpd
    $stmtCek = $pdo->prepare("SELECT * FROM transaksi_skpd WHERE no_skpd = ? LIMIT 1");
    $stmtCek->execute([$noSkpd]);
    $skpdData = $stmtCek->fetch();

    if (!$skpdData) {
        setFlash('error', 'SKPD Tidak Ditemukan', "SKPD dengan nomor $noSkpd tidak ditemukan di dalam sistem.");
        header("Location: index.php?page=cetak_ulang_skpd&tab=arsip_skpd");
        exit();
    }

    if ($skpdData['status'] === 'BATAL') {
        setFlash('warning', 'Sudah Dibatalkan', "SKPD Nomor $noSkpd sudah berstatus BATAL sebelumnya.");
        header("Location: index.php?page=cetak_ulang_skpd&tab=arsip_skpd");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $alasanFull = $alasan;
        if (!empty($noBa)) {
            $alasanFull .= " [BA: $noBa]";
        }

        // 1. Update status transaksi_skpd menjadi BATAL
        $stmtBatal = $pdo->prepare("
            UPDATE transaksi_skpd 
            SET status = 'BATAL',
                alasan_batal = ?,
                dibatalkan_oleh_id = ?,
                tgl_batal = NOW()
            WHERE no_skpd = ?
        ");
        $stmtBatal->execute([$alasanFull, $user['id'] ?? 1, $noSkpd]);

        // 2. Revert status transaksi_superpkaa menjadi TERBIT (agar siap diproses ulang di loket operator)
        $stmtSuper = $pdo->prepare("
            UPDATE transaksi_superpkaa 
            SET status = 'TERBIT'
            WHERE no_superpkaa = ?
        ");
        $stmtSuper->execute([$skpdData['no_superpkaa']]);

        // 3. Rollback masa berlaku pajak kendaraan_air ke tanggal sebelum cetak SKPD ini (jika ada)
        // Dan kembalikan status_pajak ke TERBIT_SUPER
        if (!empty($skpdData['tg_akhir_sebelumnya'])) {
            $stmtKapal = $pdo->prepare("
                UPDATE kendaraan_air 
                SET status_pajak = 'TERBIT_SUPER',
                    tg_akhir_pajak = ?
                WHERE no_objek_pajak = ?
            ");
            $stmtKapal->execute([$skpdData['tg_akhir_sebelumnya'], $skpdData['no_objek_pajak']]);
        } else {
            $stmtKapal = $pdo->prepare("
                UPDATE kendaraan_air 
                SET status_pajak = 'TERBIT_SUPER'
                WHERE no_objek_pajak = ?
            ");
            $stmtKapal->execute([$skpdData['no_objek_pajak']]);
        }

        // 4. Log audit trail pembatalan
        logCetakSKPD($pdo, $noSkpd, 'SKPD', $user['id'] ?? 1, "PEMBATALAN RESMI SKPD: $alasanFull");

        $pdo->commit();

        setFlash('success', 'SKPD Berhasil Dibatalkan', "SKPD Nomor <strong>" . htmlspecialchars($noSkpd) . "</strong> resmi dibatalkan. Objek kapal telah dikembalikan ke antrean Siap Cetak SKPD.");
        header("Location: index.php?page=cetak_ulang_skpd&tab=siap_skpd");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', 'Gagal Membatalkan SKPD', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        header("Location: index.php?page=cetak_ulang_skpd&tab=arsip_skpd");
        exit();
    }
}

$search = trim($_GET['q'] ?? '');
$tab = trim($_GET['tab'] ?? 'siap_skpd'); // Default: Siap Cetak SKPD (SUPERPKAA sudah dicetak)

// Hitung total count untuk badge
$countSiapSkpd = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM transaksi_superpkaa s 
    WHERE s.status_cetak = 1 
      AND s.status != 'LUNAS'
      AND NOT EXISTS (
          SELECT 1 FROM transaksi_skpd sk 
          WHERE sk.no_superpkaa = s.no_superpkaa AND sk.status = 'SAH'
      )
")->fetchColumn();

$countArsipSkpd = (int)$pdo->query("SELECT COUNT(*) FROM transaksi_skpd")->fetchColumn();

$listSiapSkpd = [];
$listArsipSkpd = [];

if ($tab === 'siap_skpd') {
    // Objek yang SUPERPKAA-nya SUDAH dicetak dan siap diproses / dicetak SKPD
    // Mengecualikan objek yang sudah memiliki SKPD SAH aktif
    $sql = "
        SELECT s.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, f.nama_fungsi, j.nama_jenis
        FROM transaksi_superpkaa s
        JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        WHERE s.status_cetak = 1 
          AND s.status != 'LUNAS'
          AND NOT EXISTS (
              SELECT 1 FROM transaksi_skpd sk 
              WHERE sk.no_superpkaa = s.no_superpkaa AND sk.status = 'SAH'
          )
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
    // Objek yang SUDAH memiliki transaksi SKPD (Arsip SKPD SAH maupun BATAL)
    $sql = "
        SELECT sk.*, k.nama_kapal, k.nama_pemilik, f.nama_fungsi, j.nama_jenis,
               p.nama_pejabat as nama_pengesah, u.nama_lengkap as nama_kasir,
               ub.nama_lengkap as nama_pembatal
        FROM transaksi_skpd sk
        JOIN kendaraan_air k ON sk.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        LEFT JOIN pejabat p ON sk.pejabat_skpd_id = p.id
        LEFT JOIN users u ON sk.id_petugas_kasir = u.id
        LEFT JOIN users ub ON sk.dibatalkan_oleh_id = ub.id
        WHERE 1=1
    ";
    $params = [];
    if (!empty($search)) {
        $sql .= " AND (sk.no_skpd LIKE ? OR sk.no_objek_pajak LIKE ? OR k.nama_kapal LIKE ? OR k.nama_pemilik LIKE ? OR sk.no_resi_bukti LIKE ?) ";
        $paramLike = "%$search%";
        $params = [$paramLike, $paramLike, $paramLike, $paramLike, $paramLike];
    }
    $sql .= " ORDER BY sk.created_at DESC LIMIT 50";
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
              <th width="170">No. SKPD</th>
              <th width="90">Tgl. Bayar</th>
              <th>NOPD / Kapal</th>
              <th>Wajib Pajak</th>
              <th width="120" class="text-center">Status</th>
              <th>Metode / Resi</th>
              <th class="text-right">Pokok PKAA</th>
              <th class="text-right">Opsen (66%)</th>
              <th class="text-right">Total Disetor</th>
              <th class="text-center" width="150">Aksi Dokumen</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($listArsipSkpd)): ?>
              <tr>
                <td colspan="10" class="text-center" style="padding: 32px; color: #94a3b8;">
                  Tidak ada riwayat penerbitan SKPD yang sesuai dengan kriteria pencarian.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($listArsipSkpd as $row): ?>
                <?php 
                  $isBatal = ($row['status'] === 'BATAL');
                ?>
                <tr style="<?= $isBatal ? 'background:#fff1f2; opacity:0.85;' : '' ?>">
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
                  <td class="text-center">
                    <?php if ($isBatal): ?>
                      <span class="badge" style="background:#fee2e2; color:#b91c1c; border:1px solid #f87171; font-weight:700;">BATAL</span>
                      <div style="font-size:10px; color:#991b1b; margin-top:3px; line-height:1.2;">
                        Oleh: <?= htmlspecialchars($row['nama_pembatal'] ?: 'Admin') ?><br>
                        <span title="<?= htmlspecialchars($row['alasan_batal'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($row['alasan_batal'] ?? '', 0, 24, '...')) ?></span>
                      </div>
                    <?php else: ?>
                      <span class="badge" style="background:#dcfce7; color:#15803d; border:1px solid #86efac; font-weight:700;">SAH LUNAS</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge badge-info"><?= htmlspecialchars($row['metode_bayar']) ?></span><br>
                    <span style="font-size: 10px; color: #64748b;"><?= htmlspecialchars($row['no_resi_bukti'] ?: 'Loket Kasda') ?></span>
                  </td>
                  <td class="text-right"><?= formatRupiah($row['bea_pkaa_pokok_bayar']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['opsen_pkaa_pokok_bayar']) ?></td>
                  <td class="text-right"><strong style="color:<?= $isBatal ? '#991b1b' : '#059669' ?>;"><?= formatRupiah($row['total_bayar']) ?></strong></td>
                  <td class="text-center">
                    <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                      <a href="cetak/skpd_print.php?no=<?= urlencode($row['no_skpd']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="width:100%; justify-content:center;" title="Cetak Lembar SKPD">
                        <?= renderIcon('printer', '', 13) ?> <?= $isBatal ? 'Salinan (Batal)' : 'Cetak SKPD' ?>
                      </a>
                      
                      <?php if (!$isBatal && $isAdmin): ?>
                        <button type="button" class="btn btn-sm" style="width:100%; justify-content:center; background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; font-size:11px; font-weight:700;" onclick="bukaModalBatalSKPD('<?= htmlspecialchars($row['no_skpd'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($row['nama_kapal']), ENT_QUOTES) ?>', '<?= htmlspecialchars($row['no_objek_pajak'], ENT_QUOTES) ?>')">
                          <?= renderIcon('x-circle', '', 12) ?> Batalkan SKPD
                        </button>
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
<?php endif; ?>

<?php if ($isAdmin): ?>
<!-- Modal Konfirmasi Pembatalan SKPD (Khusus Admin) -->
<div id="modalBatalSKPD" class="desktop-modal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.65); z-index:9999; align-items:center; justify-content:center; backdrop-filter: blur(2px);">
  <div style="background:#fff; width:520px; max-width:92%; border-radius:10px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.3); overflow:hidden; border:1px solid #e2e8f0;">
    <div style="background:#ef4444; color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center;">
      <div style="display:flex; align-items:center; gap:8px;">
        <?= renderIcon('alert-triangle', '', 18) ?>
        <strong style="font-size:15px;">Pembatalan Dokumen SKPD</strong>
      </div>
      <button type="button" style="background:none; border:none; color:#fff; font-size:22px; cursor:pointer; line-height:1;" onclick="tutupModalBatalSKPD()">&times;</button>
    </div>
    
    <form method="POST" action="index.php?page=cetak_ulang_skpd" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan SKPD ini? Status keabsahan akan dicabut dan data kapal dikembalikan ke antrean cetak ulang.');">
      <input type="hidden" name="aksi" value="batalkan_skpd">
      <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
      <input type="hidden" name="no_skpd" id="batal_no_skpd" value="">

      <div style="padding:20px;">
        <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:6px; padding:12px; margin-bottom:16px; font-size:12px; color:#9f1239; line-height:1.5;">
          <strong>PERHATIAN ADMINISTRATOR:</strong><br>
          Pembatalan SKPD dilakukan bila terjadi <em>kerusakan cetak fisik/kertas</em> atau <em>kesalahan fatal penetapan</em>. Status SKPD akan menjadi <strong>BATAL</strong>, tanggal masa pajak kapal di-<em>rollback</em>, dan objek dikembalikan ke antrean loket operator agar dapat diproses/dicetak ulang dengan benar.
        </div>

        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">No. SKPD yang Dibatalkan</label>
          <input type="text" id="display_batal_no_skpd" class="form-control" readonly style="background:#f8fafc; font-weight:700; color:#0f172a;">
        </div>

        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Objek Pajak / Nama Kapal</label>
          <input type="text" id="display_batal_kapal" class="form-control" readonly style="background:#f8fafc; color:#475569;">
        </div>

        <div style="margin-bottom:12px;">
          <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Nomor Berita Acara (BA) / Surat Keterangan <small style="color:#64748b; font-weight:400;">(Opsional)</small></label>
          <input type="text" name="no_berita_acara" class="form-control" placeholder="Contoh: BA-BATAL/PKAA/2026/014">
        </div>

        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Alasan Resmi Pembatalan <span style="color:#ef4444;">*</span></label>
          <textarea name="alasan_batal" class="form-control" rows="3" required placeholder="Contoh: Kertas cetak printer macet dan sobek pada loket Kasda UPTD Kupang..."></textarea>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid #e2e8f0; padding-top:14px;">
          <button type="button" class="btn btn-secondary" onclick="tutupModalBatalSKPD()">Batal / Tutup</button>
          <button type="submit" class="btn btn-danger" style="background:#dc2626; border-color:#b91c1c;">
            <?= renderIcon('trash-2', '', 14) ?> Ya, Batalkan SKPD Ini
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function bukaModalBatalSKPD(noSkpd, namaKapal, nopd) {
  document.getElementById('batal_no_skpd').value = noSkpd;
  document.getElementById('display_batal_no_skpd').value = noSkpd;
  document.getElementById('display_batal_kapal').value = namaKapal + ' (' + nopd + ')';
  document.getElementById('modalBatalSKPD').style.display = 'flex';
}
function tutupModalBatalSKPD() {
  document.getElementById('modalBatalSKPD').style.display = 'none';
}
</script>
<?php endif; ?>
