<?php
// modules/pelayanan/verifikasi_perubahan.php - Panel Verifikasi & Persetujuan Admin Induk
$pageTitle = "Verifikasi & Validasi Admin Induk";
$currentUser = currentUser();

// Pastikan hanya role ADMIN yang dapat mengakses
if ($currentUser['role'] !== 'ADMIN') {
    die("Akses Terbatas: Halaman ini hanya dapat diakses oleh Admin Induk.");
}

// 1. Proses Approve / Reject Pengajuan Perubahan Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'proses_pengajuan') {
    $idPengajuan = (int)($_POST['id_pengajuan'] ?? 0);
    $statusAksi = $_POST['status_aksi'] ?? '';
    $catatan = trim($_POST['catatan_verifikasi'] ?? '');

    if ($idPengajuan > 0 && ($statusAksi === 'DISETUJUI' || $statusAksi === 'DITOLAK')) {
        try {
            $stmtP = $pdo->prepare("SELECT * FROM pengajuan_perubahan_kaa WHERE id = ?");
            $stmtP->execute([$idPengajuan]);
            $pengajuan = $stmtP->fetch();

            if ($pengajuan) {
                $pdo->beginTransaction();

                if ($statusAksi === 'DISETUJUI') {
                    $diff = json_decode($pengajuan['perubahan_data'], true);
                    $newPemilik = $diff['nama_pemilik']['baru'] ?? null;
                    $newAlamat = $diff['alamat_pemilik']['baru'] ?? null;
                    $newPelabuhan = $diff['pelabuhan_pangkal']['baru'] ?? null;
                    $newSelar = $diff['tanda_selar']['baru'] ?? null;

                    $stmtUpd = $pdo->prepare("
                        UPDATE kendaraan_air SET 
                            nama_pemilik = COALESCE(?, nama_pemilik),
                            alamat_pemilik = COALESCE(?, alamat_pemilik),
                            pelabuhan_pangkal = COALESCE(?, pelabuhan_pangkal),
                            tanda_selar = COALESCE(?, tanda_selar)
                        WHERE no_objek_pajak = ?
                    ");
                    $stmtUpd->execute([$newPemilik, $newAlamat, $newPelabuhan, $newSelar, $pengajuan['no_objek_pajak']]);
                }

                $stmtFinal = $pdo->prepare("
                    UPDATE pengajuan_perubahan_kaa SET
                        status = ?,
                        diverifikasi_oleh_id = ?,
                        catatan_verifikasi = ?,
                        tgl_verifikasi = NOW()
                    WHERE id = ?
                ");
                $stmtFinal->execute([$statusAksi, $currentUser['id'], $catatan, $idPengajuan]);

                $pdo->commit();
                setFlash('success', 'Verifikasi Selesai!', "Pengajuan No. {$pengajuan['no_pengajuan']} berhasil di-{$statusAksi}.");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Gagal Memproses!', $e->getMessage());
        }
    }
}

// 2. Proses Lengkapi NJKB untuk Pendaftaran Baru dari Operator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'lengkapi_njkb') {
    $nopd = trim($_POST['no_objek_pajak'] ?? '');
    $metode = trim($_POST['metode_njkb'] ?? 'TERPISAH');
    $totalNjkb = 0;
    $njBody = 0;
    $njGabungan = 0;
    $merekBody = trim($_POST['merek_body'] ?? 'Kapal Tradisional');
    $tahunBody = (int)($_POST['tahun_body'] ?? date('Y'));
    $merekGabungan = trim($_POST['merek_gabungan'] ?? '');
    $tahunBuat = (int)($_POST['tahun_buat'] ?? date('Y'));

    try {
        $pdo->beginTransaction();

        if ($metode === 'GABUNGAN') {
            $njGabungan = (float)preg_replace('/[^0-9]/', '', $_POST['njkb_gabungan'] ?? '0');
            $totalNjkb = $njGabungan;

            // Otomatis Simpan / Update ke ref_njkb_gabungan
            if (!empty($merekGabungan) && $njGabungan > 0) {
                $stmtUpsertGab = $pdo->prepare("
                    INSERT INTO ref_njkb_gabungan (merek_paket, merek_body, merek_mesin, tahun_buat, kapasitas_gt, nilai_jual, keterangan)
                    VALUES (?, 'Body Standar', 'Mesin Standar', ?, 15, ?, 'Direkam otomatis saat pengesahan NJKB')
                    ON DUPLICATE KEY UPDATE nilai_jual = VALUES(nilai_jual)
                ");
                $stmtUpsertGab->execute([$merekGabungan, $tahunBuat, $njGabungan]);
            }

            $stmtUp = $pdo->prepare("
                UPDATE kendaraan_air SET
                    metode_njkb = 'GABUNGAN',
                    merek_gabungan = ?,
                    njkb_gabungan = ?,
                    total_njkb = ?,
                    merek_body = NULL,
                    tahun_body = NULL,
                    nj_body_kapal = 0.00,
                    merek_mesin = NULL,
                    tahun_mesin_utama = NULL,
                    total_njkb_mesin = 0.00,
                    jumlah_mesin = 0,
                    status_verifikasi = 'VERIFIKASI'
                WHERE no_objek_pajak = ?
            ");
            $stmtUp->execute([$merekGabungan, $njGabungan, $totalNjkb, $nopd]);

            // Hapus rincian mesin kapal karena dinilai 1 harga paket utuh (GABUNGAN)
            $delM = $pdo->prepare("DELETE FROM mesin_kendaraan WHERE no_objek_pajak = ?");
            $delM->execute([$nopd]);
        } else {
            $njBody = (float)preg_replace('/[^0-9]/', '', $_POST['nj_body_kapal'] ?? '0');
            
            // Otomatis Simpan / Update ke ref_njkb_body
            if (!empty($merekBody) && $njBody > 0) {
                $stmtUpsertBody = $pdo->prepare("
                    INSERT INTO ref_njkb_body (merek, tipe_model, tahun_buat, nilai_jual, keterangan)
                    VALUES (?, 'Standar Verifikasi', ?, ?, 'Direkam otomatis saat pengesahan NJKB')
                    ON DUPLICATE KEY UPDATE nilai_jual = VALUES(nilai_jual)
                ");
                $stmtUpsertBody->execute([$merekBody, $tahunBody, $njBody]);
            }

            $totalMesin = 0;
            $merekMesinUtama = 'Yamaha Marine';
            $tahunMesinUtama = date('Y');

            for ($i = 1; $i <= 6; $i++) {
                if (!isset($_POST['nj_mesin_' . $i])) continue;
                $val = (float)preg_replace('/[^0-9]/', '', $_POST['nj_mesin_' . $i] ?? '0');
                $mrk = trim($_POST['merek_mesin_' . $i] ?? 'Yamaha Marine');
                $daya = (int)($_POST['daya_pk_' . $i] ?? 0);
                $thn = (int)($_POST['tahun_mesin_' . $i] ?? date('Y'));
                
                if ($i === 1) {
                    $merekMesinUtama = $mrk;
                    $tahunMesinUtama = $thn;
                }
                $totalMesin += $val;

                // Update data mesin kapal
                $stmtMesin = $pdo->prepare("
                    UPDATE mesin_kendaraan SET 
                        merek_mesin = ?,
                        daya_pk = ?,
                        tahun_mesin = ?,
                        nilai_jual_mesin = ? 
                    WHERE no_objek_pajak = ? AND mesin_ke = ?
                ");
                $stmtMesin->execute([$mrk, $daya, $thn, $val, $nopd, $i]);

                // Otomatis Simpan / Update ke ref_njkb_mesin
                if (!empty($mrk) && $val > 0) {
                    $stmtUpsertMesin = $pdo->prepare("
                        INSERT INTO ref_njkb_mesin (merek, tipe_tenaga, daya_pk, tahun_buat, nilai_jual, keterangan)
                        VALUES (?, 'Outboard / Inboard', ?, ?, ?, 'Direkam otomatis saat pengesahan NJKB')
                        ON DUPLICATE KEY UPDATE nilai_jual = VALUES(nilai_jual)
                    ");
                    $stmtUpsertMesin->execute([$mrk, $daya, $thn, $val]);
                }
            }

            $totalNjkb = $njBody + $totalMesin;

            $stmtUp = $pdo->prepare("
                UPDATE kendaraan_air SET
                    metode_njkb = 'TERPISAH',
                    merek_body = ?,
                    tahun_body = ?,
                    nj_body_kapal = ?,
                    merek_mesin = ?,
                    tahun_mesin_utama = ?,
                    total_njkb_mesin = ?,
                    merek_gabungan = NULL,
                    njkb_gabungan = 0.00,
                    total_njkb = ?,
                    status_verifikasi = 'VERIFIKASI'
                WHERE no_objek_pajak = ?
            ");
            $stmtUp->execute([$merekBody, $tahunBody, $njBody, $merekMesinUtama, $tahunMesinUtama, $totalMesin, $totalNjkb, $nopd]);
        }

        // Otomatis terbitkan transaksi_superpkaa jika belum ada agar langsung masuk antrean Cetak SUPERPKAA
        $stmtCekSuper = $pdo->prepare("SELECT no_superpkaa FROM transaksi_superpkaa WHERE no_objek_pajak = ?");
        $stmtCekSuper->execute([$nopd]);
        $existingSuper = $stmtCekSuper->fetchColumn();

        $noSuperGenerated = '';
        if (!$existingSuper && $totalNjkb > 0) {
            $stmtK = $pdo->prepare("SELECT kode_fungsi, tg_akhir_pajak FROM kendaraan_air WHERE no_objek_pajak = ?");
            $stmtK->execute([$nopd]);
            $kData = $stmtK->fetch();
            $kodeFungsi = $kData['kode_fungsi'] ?? '2';
            $tgAkhir = $kData['tg_akhir_pajak'] ?? date('Y-m-d', strtotime('+1 year'));

            $noSuperGenerated = generateNextSUPER($pdo);
            $pajak = kalkulasiPajakPKAA($pdo, $totalNjkb, $kodeFungsi, $tgAkhir, date('Y-m-d'));

            $stmtInsSuper = $pdo->prepare("
                INSERT INTO transaksi_superpkaa (
                    no_superpkaa, no_objek_pajak, tgl_terbit, masa_pajak_awal, masa_pajak_akhir,
                    njkb_dasar, tarif_pokok_persen, bea_pkaa_pokok, bea_pkaa_denda,
                    opsen_persen, opsen_pkaa_pokok, opsen_pkaa_denda, total_ketetapan,
                    status, status_cetak, id_petugas
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    'TERBIT', 0, ?
                )
            ");
            $stmtInsSuper->execute([
                $noSuperGenerated, $nopd, date('Y-m-d'), date('Y-m-d'), $tgAkhir,
                $pajak['njkb_dasar'], $pajak['tarif_pokok_persen'], $pajak['bea_pkaa_pokok'], $pajak['bea_pkaa_denda'],
                $pajak['opsen_persen'], $pajak['opsen_pkaa_pokok'], $pajak['opsen_pkaa_denda'], $pajak['total_ketetapan'],
                $currentUser['id'] ?? 1
            ]);
        }

        $pdo->commit();
        $msgSuper = $noSuperGenerated ? " dan dokumen SUPERPKAA (<strong>$noSuperGenerated</strong>) siap dicetak" : "";
        setFlash('success', 'Penetapan NJKB Selesai!', "Objek kapal <strong>$nopd</strong> telah disahkan NJKB sebesar Rp " . number_format($totalNjkb, 0, ',', '.') . "$msgSuper.");
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Gagal Menetapkan NJKB!', $e->getMessage());
    }
}

// Query data pending
$pendingDraft = $pdo->query("
    SELECT k.*, u.nama_lengkap as nama_operator 
    FROM kendaraan_air k 
    LEFT JOIN users u ON k.didaftarkan_oleh = u.id
    WHERE k.status_verifikasi = 'MENUNGGU_VERIFIKASI' 
    ORDER BY k.created_at ASC
")->fetchAll();

$draftMesin = [];
if (!empty($pendingDraft)) {
    $nopdList = array_map(function($d) { return "'" . addslashes($d['no_objek_pajak']) . "'"; }, $pendingDraft);
    $rowsM = $pdo->query("SELECT * FROM mesin_kendaraan WHERE no_objek_pajak IN (" . implode(',', $nopdList) . ") ORDER BY mesin_ke ASC")->fetchAll();
    foreach ($rowsM as $rm) {
        $draftMesin[$rm['no_objek_pajak']][] = $rm;
    }
}

$pendingPerubahan = $pdo->query("
    SELECT * FROM pengajuan_perubahan_kaa 
    WHERE status = 'MENUNGGU_VERIFIKASI' 
    ORDER BY tgl_pengajuan ASC
")->fetchAll();

// Referensi NJKB untuk autocomplete & live lookup
$refNjkbBody = $pdo->query("SELECT * FROM ref_njkb_body ORDER BY merek ASC, tahun_buat DESC")->fetchAll();
$refNjkbMesin = $pdo->query("SELECT * FROM ref_njkb_mesin ORDER BY merek ASC, daya_pk ASC, tahun_buat DESC")->fetchAll();
$refNjkbGabungan = $pdo->query("SELECT * FROM ref_njkb_gabungan ORDER BY merek_paket ASC, tahun_buat DESC")->fetchAll();
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('shield-check', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Panel Verifikasi & Validasi Admin Induk</div>
      <div class="page-subtitle">Pemeriksaan dan Penetapan NJKB Pendaftaran Baru serta Persetujuan Perubahan Data Objek Pajak</div>
    </div>
  </div>
</div>

<!-- Bagian 1: Pendaftaran Baru Menunggu Penetapan NJKB -->
<div class="panel" style="margin-bottom: 20px;">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('ship', '', 16) ?>
      <span>1. PENDAFTARAN OBJEK BARU MENUNGGU PENETAPAN NJKB (<?= count($pendingDraft) ?>)</span>
    </div>
    <span class="badge badge-warning">Perlu Dilengkapi Admin</span>
  </div>
  <div class="panel-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table-desktop">
        <thead>
          <tr>
            <th width="40">NO</th>
            <th>NOPD KA</th>
            <th>NAMA KAPAL</th>
            <th>PEMILIK / ALAMAT</th>
            <th>GT / TAHUN</th>
            <th>OPERATOR</th>
            <th>TGL DAFTAR</th>
            <th width="140" align="center">AKSI</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pendingDraft)): ?>
            <tr>
              <td colspan="8" align="center" style="padding:18px; color:#64748b;">
                Tidak ada pendaftaran baru yang menunggu penetapan NJKB.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($pendingDraft as $idx => $d): ?>
              <tr>
                <td align="center"><?= $idx + 1 ?></td>
                <td><strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($d['no_objek_pajak']) ?></strong></td>
                <td><strong><?= htmlspecialchars($d['nama_kapal']) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($d['nama_pemilik']) ?></strong><br>
                  <small style="color:#64748b;"><?= htmlspecialchars($d['alamat_pemilik']) ?></small>
                </td>
                <td><?= htmlspecialchars($d['kapasitas_gt']) ?> GT / <?= $d['tahun_buatan'] ?></td>
                <td><span class="badge badge-neutral"><?= htmlspecialchars($d['nama_operator'] ?: 'Operator') ?></span></td>
                <td><?= date('d/m/Y H:i', strtotime($d['created_at'])) ?></td>
                <td align="center">
                  <button type="button" class="btn btn-primary btn-sm" onclick="modalLengkapiNjkb('<?= htmlspecialchars($d['no_objek_pajak']) ?>', '<?= htmlspecialchars(addslashes($d['nama_kapal'])) ?>', '<?= htmlspecialchars(addslashes($d['nama_pemilik'])) ?>')">
                    <?= renderIcon('edit', '', 12) ?> Lengkapi NJKB
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Bagian 2: Pengajuan Perubahan Data Menunggu Persetujuan -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('edit', '', 16) ?>
      <span>2. PENGAJUAN PERUBAHAN DATA MENUNGGU PERSETUJUAN (<?= count($pendingPerubahan) ?>)</span>
    </div>
    <span class="badge badge-warning">Menunggu Keputusan</span>
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
            <th>DATA LAMA VS USULAN BARU</th>
            <th>ALASAN PENGAJUAN</th>
            <th>DIAJUKAN OLEH</th>
            <th width="160" align="center">KEPUTUSAN</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($pendingPerubahan)): ?>
            <tr>
              <td colspan="8" align="center" style="padding:18px; color:#64748b;">
                Tidak ada pengajuan perubahan data yang menunggu persetujuan.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($pendingPerubahan as $idx => $p): 
              $diff = json_decode($p['perubahan_data'], true);
            ?>
              <tr>
                <td align="center"><?= $idx + 1 ?></td>
                <td><strong><?= htmlspecialchars($p['no_pengajuan']) ?></strong></td>
                <td><strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($p['no_objek_pajak']) ?></strong></td>
                <td><strong><?= htmlspecialchars($p['nama_kapal']) ?></strong></td>
                <td style="font-size:11px;">
                  <?php if (!empty($diff)): ?>
                    <?php foreach ($diff as $k => $v): ?>
                      <?php if (!empty($v['baru']) && $v['baru'] !== $v['lama']): ?>
                        <div><strong><?= ucwords(str_replace('_', ' ', $k)) ?>:</strong> 
                          <span style="color:#ef4444; text-decoration:line-through;"><?= htmlspecialchars($v['lama']) ?></span> 
                          &rarr; <span style="color:#10b981; font-weight:bold;"><?= htmlspecialchars($v['baru']) ?></span>
                        </div>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?= htmlspecialchars($p['alasan_perubahan']) ?>
                  <?php if ($p['lampiran_dokumen']): ?>
                    <br><small style="color:#0369a1;">Ref Dok: <?= htmlspecialchars($p['lampiran_dokumen']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['diajukan_oleh_nama']) ?><br><small><?= date('d/m/Y H:i', strtotime($p['tgl_pengajuan'])) ?></small></td>
                <td align="center">
                  <div style="display:flex; gap:6px; justify-content:center;">
                    <form method="POST" action="index.php?page=verifikasi_perubahan" onsubmit="return confirm('Apakah Anda yakin menyetujui pengajuan perubahan ini? Data kapal akan otomatis diperbarui.')">
                      <input type="hidden" name="aksi" value="proses_pengajuan">
                      <input type="hidden" name="id_pengajuan" value="<?= $p['id'] ?>">
                      <input type="hidden" name="status_aksi" value="DISETUJUI">
                      <button type="submit" class="btn btn-success btn-sm">
                        <?= renderIcon('check', '', 12) ?> Setujui
                      </button>
                    </form>
                    <form method="POST" action="index.php?page=verifikasi_perubahan" onsubmit="return confirm('Apakah Anda yakin menolak pengajuan ini?')">
                      <input type="hidden" name="aksi" value="proses_pengajuan">
                      <input type="hidden" name="id_pengajuan" value="<?= $p['id'] ?>">
                      <input type="hidden" name="status_aksi" value="DITOLAK">
                      <button type="submit" class="btn btn-danger btn-sm">
                        <?= renderIcon('x', '', 12) ?> Tolak
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

<!-- Datalists Referensi NJKB untuk Modal -->
<datalist id="list_modal_body">
  <?php foreach ($refNjkbBody as $b): ?>
    <option value="<?= htmlspecialchars($b['merek']) ?>">
      Tahun <?= $b['tahun_buat'] ?> &bull; Rp <?= number_format($b['nilai_jual'], 0, ',', '.') ?> (<?= htmlspecialchars($b['tipe_model'] ?? 'Standar') ?>)
    </option>
  <?php endforeach; ?>
</datalist>

<datalist id="list_modal_mesin">
  <?php foreach ($refNjkbMesin as $m): ?>
    <option value="<?= htmlspecialchars($m['merek']) ?>">
      <?= $m['daya_pk'] ?> PK &bull; Tahun <?= $m['tahun_buat'] ?> &bull; Rp <?= number_format($m['nilai_jual'], 0, ',', '.') ?>
    </option>
  <?php endforeach; ?>
</datalist>

<datalist id="list_modal_gabungan">
  <?php foreach ($refNjkbGabungan as $g): ?>
    <option value="<?= htmlspecialchars($g['merek_paket']) ?>">
      Tahun <?= $g['tahun_buat'] ?> &bull; Rp <?= number_format($g['nilai_jual'], 0, ',', '.') ?>
    </option>
  <?php endforeach; ?>
</datalist>

<!-- Modal Dialog Lengkapi NJKB untuk Admin -->
<div id="modalLengkapi" class="desktop-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:8px; width:680px; max-width:96%; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 15px 35px rgba(0,0,0,0.3); overflow:hidden;">
    <div style="background:#0f172a; color:#fff; padding:12px 18px; display:flex; justify-content:space-between; align-items:center;">
      <div style="display:flex; align-items:center; gap:8px;">
        <?= renderIcon('shield-check', '', 18) ?>
        <strong id="modalTitle" style="font-size:14px;">Penetapan & Pengesahan NJKB Kapal</strong>
      </div>
      <button type="button" style="background:none; border:none; color:#cbd5e1; font-size:20px; cursor:pointer;" onclick="tutupModalLengkapi()">&times;</button>
    </div>
    
    <form method="POST" action="index.php?page=verifikasi_perubahan" style="padding:18px; overflow-y:auto; flex:1;">
      <input type="hidden" name="aksi" value="lengkapi_njkb">
      <input type="hidden" name="no_objek_pajak" id="modalNopd">
      <input type="hidden" name="tahun_buat" id="modalTahunBuat">
      
      <!-- Info Ringkas Kapal -->
      <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px; margin-bottom:14px; display:flex; justify-content:space-between; font-size:12px;">
        <div><strong>NOPD:</strong> <span id="infoModalNopd" style="color:#0284c7; font-weight:700;">-</span></div>
        <div><strong>Kapal:</strong> <span id="infoModalKapal" style="font-weight:700;">-</span></div>
        <div><strong>Pemilik:</strong> <span id="infoModalPemilik">-</span></div>
      </div>

      <div style="margin-bottom:14px;">
        <label class="form-label" style="font-weight:700;">Metode Penilaian NJKB:</label>
        <div style="display:flex; gap:20px; margin-top:4px;">
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
            <input type="radio" name="metode_njkb" value="TERPISAH" id="modalRadioTerpisah" checked onchange="toggleModalMetode(this.value)"> 
            <span>Terpisah (Body & Mesin)</span>
          </label>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
            <input type="radio" name="metode_njkb" value="GABUNGAN" id="modalRadioGabungan" onchange="toggleModalMetode(this.value)"> 
            <span>1 Kesatuan (Paket Utuh)</span>
          </label>
        </div>
      </div>

      <!-- FORM GABUNGAN -->
      <div id="modalSecGabungan" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:14px; margin-bottom:14px;">
        <div class="form-group" style="margin-bottom:10px;">
          <label class="form-label">Merek / Nama Paket 1 Kesatuan:</label>
          <input type="text" name="merek_gabungan" id="modalMerekGabungan" list="list_modal_gabungan" class="form-control" placeholder="Pilih atau ketik Merek Paket..." oninput="lookupModalGabungan()" onchange="lookupModalGabungan()">
          <span id="badgeModalGabungan" style="font-size:11px; color:#059669; font-weight:600; margin-top:3px; display:none;"></span>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Nilai Jual Paket 1 Kesatuan (Rp):</label>
          <div class="input-group">
            <span class="input-prefix">Rp</span>
            <input type="number" name="njkb_gabungan" id="modalNjkbGabungan" class="form-control" value="0" step="1000000" oninput="calcModalTotal()">
          </div>
        </div>
      </div>

      <!-- FORM TERPISAH -->
      <div id="modalSecTerpisah" style="margin-bottom:14px;">
        <!-- Body Kapal -->
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:12px; margin-bottom:12px;">
          <div style="font-weight:700; font-size:12px; color:#1e293b; margin-bottom:8px;">1. PENETAPAN NILAI JUAL BODY KAPAL</div>
          <div style="display:grid; grid-template-columns: 1.5fr 1fr 1.5fr; gap:10px;">
            <div>
              <label class="form-label">Merek / Pabrikan Body:</label>
              <input type="text" name="merek_body" id="modalMerekBody" list="list_modal_body" class="form-control" placeholder="Merek Body..." oninput="lookupModalBody()" onchange="lookupModalBody()">
              <span id="badgeModalBody" style="font-size:11px; color:#059669; font-weight:600; margin-top:3px; display:none;"></span>
            </div>
            <div>
              <label class="form-label">Tahun Body:</label>
              <input type="number" name="tahun_body" id="modalTahunBody" class="form-control" value="<?= date('Y') ?>" min="1950" max="<?= date('Y')+1 ?>" oninput="lookupModalBody()" onchange="lookupModalBody()">
            </div>
            <div>
              <label class="form-label">Nilai Jual Body (Rp):</label>
              <div class="input-group">
                <span class="input-prefix">Rp</span>
                <input type="number" name="nj_body_kapal" id="modalNjBody" class="form-control" value="0" step="1000000" oninput="calcModalTotal()">
              </div>
            </div>
          </div>
        </div>

        <!-- Mesin-Mesin Kapal Container -->
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:12px;">
          <div style="font-weight:700; font-size:12px; color:#1e293b; margin-bottom:8px;">2. PENETAPAN NILAI JUAL MESIN PENGGERAK</div>
          <div id="modalContainerMesin">
            <!-- Dinamis via Javascript -->
          </div>
        </div>
      </div>

      <!-- Total NJKB Box -->
      <div style="background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:6px; padding:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>
          <span style="font-size:11px; color:#1e40af; display:block; font-weight:600;">TOTAL PENETAPAN DASAR NJKB:</span>
          <span style="font-size:10.5px; color:#64748b;">Merek & harga baru akan otomatis tercatat ke tabel referensi NJKB</span>
        </div>
        <div>
          <strong id="displayModalTotal" style="font-size:18px; color:#0284c7;">Rp 0</strong>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px; border-top:1px solid #e2e8f0; padding-top:14px;">
        <button type="button" class="btn btn-secondary" onclick="tutupModalLengkapi()">Batal</button>
        <button type="submit" class="btn btn-primary" style="font-weight:700; background:#0284c7; border-color:#0369a1;">
          <?= renderIcon('check-circle', '', 14) ?> Sahkan & Simpan NJKB
        </button>
      </div>
    </form>
  </div>
</div>

<script>
var pendingDrafts = <?= json_encode($pendingDraft) ?>;
var draftMesinMap = <?= json_encode($draftMesin) ?>;
var refNjkbBody = <?= json_encode($refNjkbBody) ?>;
var refNjkbMesin = <?= json_encode($refNjkbMesin) ?>;
var refNjkbGabungan = <?= json_encode($refNjkbGabungan) ?>;

function modalLengkapiNjkb(nopd, namaKapal, namaPemilik) {
  var draft = pendingDrafts.find(function(d) { return d.no_objek_pajak === nopd; });
  if (!draft) return;

  document.getElementById('modalNopd').value = nopd;
  document.getElementById('infoModalNopd').textContent = nopd;
  document.getElementById('infoModalKapal').textContent = draft.nama_kapal;
  document.getElementById('infoModalPemilik').textContent = draft.nama_pemilik;
  document.getElementById('modalTahunBuat').value = draft.tahun_buatan || new Date().getFullYear();
  document.getElementById('modalTitle').textContent = 'Penetapan NJKB: ' + nopd + ' - ' + draft.nama_kapal;

  var isGabungan = (draft.metode_njkb === 'GABUNGAN');
  document.getElementById('modalRadioGabungan').checked = isGabungan;
  document.getElementById('modalRadioTerpisah').checked = !isGabungan;
  toggleModalMetode(isGabungan ? 'GABUNGAN' : 'TERPISAH');

  // Gabungan Fields
  document.getElementById('modalMerekGabungan').value = draft.merek_gabungan || (draft.nama_kapal + ' Paket');
  document.getElementById('modalNjkbGabungan').value = draft.njkb_gabungan || 0;
  if (!draft.njkb_gabungan) lookupModalGabungan();

  // Terpisah Fields
  document.getElementById('modalMerekBody').value = draft.merek_body || 'Phinisi Nusantara';
  document.getElementById('modalTahunBody').value = draft.tahun_body || draft.tahun_buatan || new Date().getFullYear();
  document.getElementById('modalNjBody').value = draft.nj_body_kapal || 0;
  if (!draft.nj_body_kapal) lookupModalBody();

  // Mesin Rows
  var container = document.getElementById('modalContainerMesin');
  container.innerHTML = '';
  var mesins = draftMesinMap[nopd] || [];
  var jmlMesin = parseInt(draft.jumlah_mesin) || 1;

  for (var i = 1; i <= jmlMesin; i++) {
    var mData = mesins.find(function(m) { return parseInt(m.mesin_ke) === i; }) || {};
    var mrk = mData.merek_mesin || 'Yamaha Marine';
    var daya = mData.daya_pk || 250;
    var thn = mData.tahun_mesin || draft.tahun_buatan || new Date().getFullYear();
    var val = mData.nilai_jual_mesin || 0;

    var rowDiv = document.createElement('div');
    rowDiv.style = "display:grid; grid-template-columns:80px 1.4fr 90px 90px 1.3fr; gap:8px; align-items:center; margin-bottom:8px; padding-bottom:8px; border-bottom:1px dashed #e2e8f0;";
    rowDiv.innerHTML = 
      '<div><strong>Mesin ' + i + '</strong></div>' +
      '<div>' +
        '<input type="text" name="merek_mesin_' + i + '" id="modal_merek_mesin_' + i + '" list="list_modal_mesin" class="form-control" value="' + mrk + '" placeholder="Merek..." oninput="lookupModalMesin(' + i + ')" onchange="lookupModalMesin(' + i + ')">' +
        '<span id="badge_modal_mesin_' + i + '" style="font-size:10px; color:#059669; font-weight:600; display:none;"></span>' +
      '</div>' +
      '<div><input type="number" name="daya_pk_' + i + '" id="modal_daya_pk_' + i + '" class="form-control" value="' + daya + '" placeholder="PK" oninput="lookupModalMesin(' + i + ')"></div>' +
      '<div><input type="number" name="tahun_mesin_' + i + '" id="modal_tahun_mesin_' + i + '" class="form-control" value="' + thn + '" placeholder="Tahun" oninput="lookupModalMesin(' + i + ')"></div>' +
      '<div>' +
        '<div class="input-group">' +
          '<span class="input-prefix">Rp</span>' +
          '<input type="number" name="nj_mesin_' + i + '" id="modal_nj_mesin_' + i + '" class="form-control modal-input-mesin" value="' + val + '" step="1000000" oninput="calcModalTotal()">' +
        '</div>' +
      '</div>';
    container.appendChild(rowDiv);

    if (val === 0) {
      lookupModalMesin(i);
    }
  }

  calcModalTotal();
  document.getElementById('modalLengkapi').style.display = 'flex';
}

function tutupModalLengkapi() {
  document.getElementById('modalLengkapi').style.display = 'none';
}

function toggleModalMetode(val) {
  if (val === 'GABUNGAN') {
    document.getElementById('modalSecGabungan').style.display = 'block';
    document.getElementById('modalSecTerpisah').style.display = 'none';
  } else {
    document.getElementById('modalSecGabungan').style.display = 'none';
    document.getElementById('modalSecTerpisah').style.display = 'block';
  }
  calcModalTotal();
}

function lookupModalBody() {
  var mrk = document.getElementById('modalMerekBody').value.trim().toLowerCase();
  var thn = parseInt(document.getElementById('modalTahunBody').value) || 0;
  var target = document.getElementById('modalNjBody');
  var badge = document.getElementById('badgeModalBody');

  var found = refNjkbBody.find(function(item) {
    return item.merek.toLowerCase() === mrk && parseInt(item.tahun_buat) === thn;
  });
  if (!found && mrk) {
    found = refNjkbBody.find(function(item) { return item.merek.toLowerCase() === mrk; });
  }

  if (found) {
    target.value = found.nilai_jual;
    if (badge) {
      badge.textContent = '✓ Standar NJKB: Rp ' + parseInt(found.nilai_jual).toLocaleString('id-ID');
      badge.style.display = 'block';
    }
  } else if (badge) {
    badge.style.display = 'none';
  }
  calcModalTotal();
}

function lookupModalMesin(idx) {
  var mrkElem = document.getElementById('modal_merek_mesin_' + idx);
  var dayaElem = document.getElementById('modal_daya_pk_' + idx);
  var thnElem = document.getElementById('modal_tahun_mesin_' + idx);
  var target = document.getElementById('modal_nj_mesin_' + idx);
  var badge = document.getElementById('badge_modal_mesin_' + idx);

  if (!mrkElem || !target) return;
  var mrk = mrkElem.value.trim().toLowerCase();
  var daya = parseInt(dayaElem.value) || 0;
  var thn = parseInt(thnElem.value) || 0;

  var found = refNjkbMesin.find(function(item) {
    return item.merek.toLowerCase() === mrk && parseInt(item.daya_pk) === daya && parseInt(item.tahun_buat) === thn;
  });
  if (!found && mrk) {
    found = refNjkbMesin.find(function(item) {
      return item.merek.toLowerCase() === mrk && parseInt(item.daya_pk) === daya;
    });
  }
  if (!found && mrk) {
    found = refNjkbMesin.find(function(item) { return item.merek.toLowerCase() === mrk; });
  }

  if (found) {
    target.value = found.nilai_jual;
    if (badge) {
      badge.textContent = '✓ Standar: Rp ' + parseInt(found.nilai_jual).toLocaleString('id-ID');
      badge.style.display = 'block';
    }
  } else if (badge) {
    badge.style.display = 'none';
  }
  calcModalTotal();
}

function lookupModalGabungan() {
  var mrk = document.getElementById('modalMerekGabungan').value.trim().toLowerCase();
  var target = document.getElementById('modalNjkbGabungan');
  var badge = document.getElementById('badgeModalGabungan');

  var found = refNjkbGabungan.find(function(item) {
    return item.merek_paket.toLowerCase() === mrk;
  });

  if (found) {
    target.value = found.nilai_jual;
    if (badge) {
      badge.textContent = '✓ Standar Paket: Rp ' + parseInt(found.nilai_jual).toLocaleString('id-ID');
      badge.style.display = 'block';
    }
  } else if (badge) {
    badge.style.display = 'none';
  }
  calcModalTotal();
}

function calcModalTotal() {
  var isGabungan = document.getElementById('modalRadioGabungan').checked;
  var total = 0;
  if (isGabungan) {
    total = parseFloat(document.getElementById('modalNjkbGabungan').value) || 0;
  } else {
    var body = parseFloat(document.getElementById('modalNjBody').value) || 0;
    var mesinTotal = 0;
    var mesinInputs = document.querySelectorAll('.modal-input-mesin');
    mesinInputs.forEach(function(inp) {
      mesinTotal += parseFloat(inp.value) || 0;
    });
    total = body + mesinTotal;
  }
  document.getElementById('displayModalTotal').textContent = 'Rp ' + total.toLocaleString('id-ID');
}
</script>
