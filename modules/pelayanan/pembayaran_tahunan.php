<?php
// modules/pelayanan/pembayaran_tahunan.php - Pembayaran Pajak Kendaraan di Atas Air (PKAA) Tahunan
$pageTitle = "Pembayaran PKAA Tahunan";
$currentUser = currentUser();
$userRole = $currentUser['role'] ?? 'OPERATOR';

$selectedNopd = strtoupper(trim($_GET['nopd'] ?? $_POST['no_objek_pajak'] ?? ''));

$kapal = null;
$mesinList = [];
$perhitungan = null;
$pesan = '';
$tipePesan = '';
$suksesBayar = false;
$noSkpdTerbit = '';
$noSuperTerbit = '';

// Ambil daftar seluruh NOPD untuk datalist pencarian cepat
$allNopdList = $pdo->query("SELECT no_objek_pajak, nama_kapal, nama_pemilik, tg_akhir_pajak, status_pajak FROM kendaraan_air ORDER BY no_objek_pajak ASC")->fetchAll();

// Ambil data pejabat SKPD aktif untuk penandatangan
$pejabatSkpdList = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'SKPD' AND is_active = 1 ORDER BY id ASC")->fetchAll();

// PROSES 1: Pencarian Objek Pajak berdasarkan NOPD
if (!empty($selectedNopd)) {
    $stmt = $pdo->prepare("
        SELECT k.*, f.nama_fungsi, f.tarif_persen, f.kategori_tarif, j.nama_jenis,
               kb.nama_kabupaten_kota, kb.ibukota
        FROM kendaraan_air k
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        WHERE k.no_objek_pajak = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedNopd]);
    $kapal = $stmt->fetch();

    if ($kapal) {
        // Ambil rincian mesin kapal jika metode TERPISAH
        if ($kapal['metode_njkb'] === 'TERPISAH') {
            $stmtM = $pdo->prepare("SELECT * FROM mesin_kendaraan WHERE no_objek_pajak = ? ORDER BY mesin_ke ASC");
            $stmtM->execute([$selectedNopd]);
            $mesinList = $stmtM->fetchAll();
        }

        // Tentukan Masa Pajak Terbaru Otomatis:
        // Masa Pajak Awal: 1 hari setelah masa berakhir pajak sebelumnya (atau hari ini jika kosong/rusak)
        $tglAkhirLama = $kapal['tg_akhir_pajak'];
        if (!empty($tglAkhirLama) && $tglAkhirLama !== '0000-00-00') {
            $masaAwalBaru = date('Y-m-d', strtotime('+1 day', strtotime($tglAkhirLama)));
        } else {
            $masaAwalBaru = date('Y-m-d');
        }

        // Masa Pajak Akhir: 1 tahun dari masa awal baru
        $masaAkhirBaru = date('Y-m-d', strtotime('+1 year', strtotime($masaAwalBaru)));

        // Hitung kewajiban pajak dengan kalkulasi resmi BPAD NTT (Pokok + Denda Keterlambatan jika ada + Opsen 66%)
        $perhitungan = kalkulasiPajakPKAA($pdo, $kapal['total_njkb'], $kapal['kode_fungsi'], $kapal['tg_akhir_pajak'], date('Y-m-d'));
    } else {
        setFlash('warning', 'Objek Pajak Tidak Ditemukan', "Kendaraan dengan NOPD <strong>$selectedNopd</strong> belum terdaftar dalam basis data sistem JANGKAR NTT.");
    }
}

// PROSES 2: Simpan & Eksekusi Pembayaran PKAA Tahunan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'proses_bayar_tahunan' && $kapal) {
    $tglBayar = trim($_POST['tgl_bayar'] ?? date('Y-m-d'));
    $masaAwalFix = trim($_POST['masa_pajak_awal'] ?? date('Y-m-d'));
    $masaAkhirFix = trim($_POST['masa_pajak_akhir'] ?? date('Y-m-d', strtotime('+1 year')));
    $metodeBayar = trim($_POST['metode_bayar'] ?? 'BANK NTT (Kasda)');
    $noResi = trim($_POST['no_resi_bukti'] ?? '');
    $pejabatId = (int)($_POST['pejabat_skpd_id'] ?? ($pejabatSkpdList[0]['id'] ?? 1));

    // Nilai kalkulasi
    $njkbDasar = (float)$perhitungan['njkb_dasar'];
    $tarifPokok = (float)$perhitungan['tarif_pokok_persen'];
    $beaPokok = (float)$perhitungan['bea_pkaa_pokok'];
    $beaDenda = (float)$perhitungan['bea_pkaa_denda'];
    $opsenPersen = (float)$perhitungan['opsen_persen'];
    $opsenPokok = (float)$perhitungan['opsen_pkaa_pokok'];
    $opsenDenda = (float)$perhitungan['opsen_pkaa_denda'];
    $totalBayar = (float)$perhitungan['total_ketetapan'];

    try {
        $pdo->beginTransaction();

        // 1. Terbitkan Nomor SUPERPKAA Tahunan Baru
        $noSuper = generateNextSUPER($pdo);
        $stmtInsSuper = $pdo->prepare("
            INSERT INTO transaksi_superpkaa (
                no_superpkaa, no_objek_pajak, tgl_terbit, masa_pajak_awal, masa_pajak_akhir,
                njkb_dasar, tarif_pokok_persen, bea_pkaa_pokok, bea_pkaa_denda,
                opsen_persen, opsen_pkaa_pokok, opsen_pkaa_denda, total_ketetapan,
                status, status_cetak, tgl_cetak_pertama, id_petugas
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                'LUNAS', 1, NOW(), ?
            )
        ");
        $stmtInsSuper->execute([
            $noSuper, $selectedNopd, $tglBayar, $masaAwalFix, $masaAkhirFix,
            $njkbDasar, $tarifPokok, $beaPokok, $beaDenda,
            $opsenPersen, $opsenPokok, $opsenDenda, $totalBayar,
            $currentUser['id'] ?? 1
        ]);

        // 2. Terbitkan Nomor SKPD Tahunan Baru
        $noSkpd = generateNextSKPD($pdo);
        $stmtInsSkpd = $pdo->prepare("
            INSERT INTO transaksi_skpd (
                no_skpd, no_superpkaa, no_objek_pajak, tgl_bayar, metode_bayar,
                no_resi_bukti, bea_pkaa_pokok_bayar, bea_pkaa_denda_bayar,
                opsen_pkaa_pokok_bayar, opsen_pkaa_denda_bayar, total_bayar,
                pejabat_skpd_id, id_petugas_kasir, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, NOW()
            )
        ");
        $stmtInsSkpd->execute([
            $noSkpd, $noSuper, $selectedNopd, $tglBayar, $metodeBayar,
            $noResi, $beaPokok, $beaDenda,
            $opsenPokok, $opsenDenda, $totalBayar,
            $pejabatId, $currentUser['id'] ?? 1
        ]);

        // 3. Perbarui Masa Pajak Kapal ke Masa Pajak Terbaru & Set Status LUNAS
        $stmtUpdKapal = $pdo->prepare("
            UPDATE kendaraan_air 
            SET tg_akhir_pajak = ?,
                status_pajak = 'LUNAS'
            WHERE no_objek_pajak = ?
        ");
        $stmtUpdKapal->execute([$masaAkhirFix, $selectedNopd]);

        // 4. Catat Log Riwayat Cetak SUPERPKAA
        logCetakSuperPKAA(
            $pdo,
            $noSuper,
            $selectedNopd,
            $kapal['nama_kapal'],
            $kapal['nama_pemilik'],
            $currentUser['id'] ?? 1,
            $currentUser['nama_lengkap'] ?? 'Petugas Loket BPAD',
            'Penerbitan & Pembayaran PKAA Tahunan'
        );

        $pdo->commit();

        $suksesBayar = true;
        $noSkpdTerbit = $noSkpd;
        $noSuperTerbit = $noSuper;

        // Ambil data kapal yang telah terupdate
        $kapal['tg_akhir_pajak'] = $masaAkhirFix;
        $kapal['status_pajak'] = 'LUNAS';

        setFlash('success', 'Pembayaran PKAA Tahunan Berhasil!', "Pajak tahunan untuk kapal <strong>{$kapal['nama_kapal']} ($selectedNopd)</strong> berhasil dilunasi. Dokumen SKPD No. <strong>$noSkpd</strong> dan SUPERPKAA No. <strong>$noSuper</strong> telah diterbitkan.");
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Gagal Memproses Pembayaran', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    }
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon" style="background:#e0f2fe; color:#0284c7;">
      <?= renderIcon('credit-card', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Pembayaran Pajak Kendaraan di Atas Air (PKAA) Tahunan</div>
      <div class="page-subtitle">Pelayanan Perpanjangan Masa Berlaku Pajak Kapal, Penetapan SUPERPKAA & SKPD Terpadu JANGKAR NTT</div>
    </div>
  </div>
  <div style="display:flex; gap:8px;">
    <button type="button" class="btn btn-secondary" onclick="bukaLayarBantu()">
      <?= renderIcon('search', '', 14) ?> Layar Bantu NOPD (Ctrl+B)
    </button>
  </div>
</div>

<!-- Datalist Autocomplete NOPD -->
<datalist id="datalist_nopd_tahunan">
  <?php foreach ($allNopdList as $opt): ?>
    <option value="<?= htmlspecialchars($opt['no_objek_pajak']) ?>">
      <?= htmlspecialchars($opt['nama_kapal']) ?> &bull; <?= htmlspecialchars($opt['nama_pemilik']) ?> [Berlaku s.d: <?= formatTanggalIndo($opt['tg_akhir_pajak'], false) ?>]
    </option>
  <?php endforeach; ?>
</datalist>

<!-- 1. Form Pencarian NOPD Cepat -->
<div class="panel" style="margin-bottom: 20px; border: 1.5px solid #0284c7; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);">
  <div class="panel-header" style="background: #f0f9ff; border-bottom: 1.5px solid #bae6fd;">
    <div class="panel-title" style="color: #0369a1; font-weight: 700;">
      <?= renderIcon('search', '', 16) ?>
      <span>PENCARIAN KENDARAAN AIR BERDASARKAN NOPD (ENTER UNTUK CARI)</span>
    </div>
    <span class="badge badge-info">Daftar Ulang Tahunan</span>
  </div>
  <div class="panel-body" style="padding: 18px 20px;">
    <form method="GET" action="index.php" id="formCariNopd" style="display: flex; gap: 12px; align-items: flex-end;">
      <input type="hidden" name="page" value="pembayaran_tahunan">
      
      <div style="flex: 1;">
        <label class="form-label" for="input_nopd_cari" style="font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 6px;">
          Nomor Objek Pajak Daerah (NOPD) <span class="required">*</span>
        </label>
        <div style="position: relative;">
          <input type="text" name="nopd" id="input_nopd_cari" list="datalist_nopd_tahunan" 
                 class="form-control" style="font-size: 16px; font-weight: 700; letter-spacing: 1px; padding: 10px 14px; text-transform: uppercase;"
                 placeholder="Ketik NOPD (contoh: KA 0001 MB) lalu tekan ENTER..." 
                 value="<?= htmlspecialchars($selectedNopd) ?>" required autofocus>
          <span style="position: absolute; right: 12px; top: 11px; font-size: 11px; color: #64748b; font-weight: 600;">
            Tekan [ENTER]
          </span>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: 700; font-size: 14px; background: #0284c7; border-color: #0369a1;">
        <?= renderIcon('search', '', 16) ?>
        <span>Tampilkan Data Kapal</span>
      </button>

      <?php if (!empty($selectedNopd)): ?>
        <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary" style="padding: 10px 16px;">
          <?= renderIcon('refresh', '', 14) ?> Reset
        </a>
      <?php endif; ?>
    </form>
    <div style="font-size: 11.5px; color: #64748b; margin-top: 8px;">
      💡 <em>Ketik NOPD dan tekan <strong>ENTER</strong> pada keyboard untuk langsung menampilkan seluruh rincian kapal, riwayat masa pajak, dan perhitungan PKAA tahunan secara otomatis.</em>
    </div>
  </div>
</div>

<!-- 2. TAMPILAN JIKA PEMBAYARAN TAHUNAN BARU SAJA BERHASIL DIPROSES -->
<?php if ($suksesBayar && !empty($noSkpdTerbit)): ?>
  <div class="panel" style="border: 2px solid #10b981; background: #f0fdf4; margin-bottom: 24px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.15);">
    <div class="panel-body" style="padding: 24px; text-align: center;">
      <div style="width: 56px; height: 56px; border-radius: 50%; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px auto;">
        <?= renderIcon('check-circle', '', 32) ?>
      </div>
      <h2 style="color: #065f46; margin: 0 0 6px 0; font-size: 20px; font-weight: 800;">
        PEMBAYARAN PKAA TAHUNAN BERHASIL DISAHKAN!
      </h2>
      <p style="color: #047857; margin: 0 0 16px 0; font-size: 13.5px;">
        Pajak tahunan kendaraan <strong><?= htmlspecialchars($kapal['nama_kapal']) ?> (<?= htmlspecialchars($selectedNopd) ?>)</strong> telah lunas.<br>
        Masa pajak telah diperpanjang sampai dengan <strong><?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?></strong>.
      </p>

      <div style="background: #fff; border: 1px solid #86efac; border-radius: 8px; padding: 12px 16px; display: inline-flex; gap: 24px; margin-bottom: 20px;">
        <div>
          <span style="font-size: 11px; color: #64748b; display: block;">Nomor SKPD Sah</span>
          <strong style="font-size: 15px; color: #0284c7;"><?= htmlspecialchars($noSkpdTerbit) ?></strong>
        </div>
        <div style="border-left: 1px solid #cbd5e1; padding-left: 24px;">
          <span style="font-size: 11px; color: #64748b; display: block;">Nomor SUPERPKAA Baru</span>
          <strong style="font-size: 15px; color: #059669;"><?= htmlspecialchars($noSuperTerbit) ?></strong>
        </div>
        <div style="border-left: 1px solid #cbd5e1; padding-left: 24px;">
          <span style="font-size: 11px; color: #64748b; display: block;">Total Pembayaran Lunas</span>
          <strong style="font-size: 15px; color: #b45309;"><?= formatRupiah($perhitungan['total_ketetapan']) ?></strong>
        </div>
      </div>

      <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
        <a href="cetak/skpd_print.php?no=<?= urlencode($noSkpdTerbit) ?>" target="_blank" class="btn btn-primary" style="background:#0284c7; border-color:#0369a1; padding: 10px 20px; font-weight:700;">
          <?= renderIcon('receipt', '', 16) ?> CETAK SKPD TAHUNAN (LANDSCAPE)
        </a>
        <a href="cetak/superpkaa_print.php?no=<?= urlencode($noSuperTerbit) ?>" target="_blank" class="btn btn-secondary" style="padding: 10px 20px; font-weight:700;">
          <?= renderIcon('file-text', '', 16) ?> CETAK SUPERPKAA TAHUNAN
        </a>
        <a href="cetak/opsen_print.php?no=<?= urlencode($noSkpdTerbit) ?>" target="_blank" class="btn btn-secondary" style="padding: 10px 18px; font-weight:700;">
          <?= renderIcon('pie-chart', '', 16) ?> CETAK BUKTI OPSEN 66%
        </a>
        <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary" style="background:#f1f5f9; padding: 10px 18px;">
          <?= renderIcon('plus', '', 14) ?> Bayar Kendaraan Lainnya
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- 3. TAMPILAN RINCIAN SELURUH DATA KAPAL, MASA PKAA, & FORM PEMBAYARAN -->
<?php if ($kapal && !$suksesBayar): ?>
  <!-- Form Proses Pembayaran Tahunan -->
  <form method="POST" action="index.php?page=pembayaran_tahunan" id="formBayarTahunan">
    <input type="hidden" name="aksi" value="proses_bayar_tahunan">
    <input type="hidden" name="no_objek_pajak" value="<?= htmlspecialchars($kapal['no_objek_pajak']) ?>">

    <div class="form-grid-2" style="align-items: start;">
      
      <!-- KOLOM KIRI: IDENTITAS LENGKAP KENDARAAN & NJKB -->
      <div>
        <!-- Panel 1: Identitas Kapal & Wajib Pajak -->
        <div class="panel" style="margin-bottom: 16px;">
          <div class="panel-header">
            <div class="panel-title">
              <?= renderIcon('ship', '', 16) ?>
              <span>IDENTITAS OBJEK & WAJIB PAJAK KAA</span>
            </div>
            <span class="badge badge-<?= $kapal['status_pajak'] === 'LUNAS' ? 'success' : ($kapal['status_pajak'] === 'JATUH_TEMPO' ? 'danger' : 'info') ?>">
              Status: <?= $kapal['status_pajak'] ?>
            </span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <table class="table-clean" style="margin: 0;">
              <tr>
                <td class="label" style="width: 180px;">Nomor Objek Pajak (NOPD)</td>
                <td class="sep">:</td>
                <td class="val"><strong style="font-size: 14px; color: #0284c7;"><?= htmlspecialchars($kapal['no_objek_pajak']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">Nama Kapal / SB</td>
                <td class="sep">:</td>
                <td class="val"><strong style="font-size: 13.5px;"><?= htmlspecialchars($kapal['nama_kapal']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">Nama Pemilik / Badan</td>
                <td class="sep">:</td>
                <td class="val"><strong><?= htmlspecialchars($kapal['nama_pemilik']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">Alamat Pemilik</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($kapal['alamat_pemilik']) ?></td>
              </tr>
              <tr>
                <td class="label">No. Telepon / HP</td>
                <td class="sep">:</td>
                <td class="val"><?= htmlspecialchars($kapal['no_hp'] ?: '-') ?></td>
              </tr>
              <tr>
                <td class="label">Asal Wilayah</td>
                <td class="sep">:</td>
                <td class="val">
                  <strong>[<?= $kapal['kode_kabkota'] ?>] <?= htmlspecialchars($kapal['nama_kabupaten_kota']) ?></strong>
                </td>
              </tr>
              <tr>
                <td class="label">Pelabuhan Pangkal</td>
                <td class="sep">:</td>
                <td class="val"><?= strtoupper(htmlspecialchars($kapal['pelabuhan_pangkal'])) ?></td>
              </tr>
              <tr>
                <td class="label">Jenis & Fungsi Pajak</td>
                <td class="sep">:</td>
                <td class="val">
                  <?= htmlspecialchars($kapal['nama_jenis']) ?> &mdash; 
                  <span class="badge badge-neutral"><?= htmlspecialchars($kapal['nama_fungsi']) ?> (<?= $kapal['tarif_persen'] ?>%)</span>
                </td>
              </tr>
              <tr>
                <td class="label">Spesifikasi Fisik</td>
                <td class="sep">:</td>
                <td class="val">
                  Bahan: <?= htmlspecialchars($kapal['bahan_kapal']) ?> &bull; 
                  Kapasitas: <?= htmlspecialchars($kapal['kapasitas_gt']) ?> GT &bull; 
                  Tahun Buat: <?= $kapal['tahun_buatan'] ?>
                </td>
              </tr>
              <tr>
                <td class="label">Tanda Selar / Izin Trayek</td>
                <td class="sep">:</td>
                <td class="val">
                  Selar: <?= htmlspecialchars($kapal['tanda_selar'] ?: '-') ?> &bull; 
                  Izin: <?= htmlspecialchars($kapal['no_ijin_operasi'] ?: '-') ?>
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Panel 2: Komponen Standar Nilai Jual (NJKB) -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <?= renderIcon('coins', '', 16) ?>
              <span>PENETAPAN NILAI JUAL (NJKB: <?= $kapal['metode_njkb'] ?>)</span>
            </div>
            <span class="badge badge-neutral">Dasar Pengenaan Pajak</span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <?php if ($kapal['metode_njkb'] === 'GABUNGAN'): ?>
              <div style="background: #f8fafc; border: 1.5px solid #0284c7; border-radius: 6px; padding: 12px;">
                <span style="font-size: 11px; color: #0284c7; font-weight: 700; display: block; text-transform: uppercase;">
                  Paket 1 Kesatuan (Body + Mesin Utuh):
                </span>
                <strong style="font-size: 13.5px; color: #0f172a; display: block; margin: 2px 0 4px 0;">
                  <?= htmlspecialchars($kapal['merek_gabungan'] ?: $kapal['nama_kapal']) ?>
                </strong>
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #cbd5e1; padding-top: 6px; margin-top: 6px;">
                  <span style="font-size: 12px; color: #64748b;">Nilai Jual Paket Gabungan:</span>
                  <strong style="font-size: 15px; color: #0369a1;"><?= formatRupiah($kapal['njkb_gabungan']) ?></strong>
                </div>
              </div>
            <?php else: ?>
              <table class="table-desktop" style="margin: 0;">
                <thead>
                  <tr>
                    <th>Komponen Objek</th>
                    <th>Merek / Detail</th>
                    <th width="140" style="text-align: right;">Nilai Jual (Rp)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><strong>Body Kapal</strong></td>
                    <td><?= htmlspecialchars($kapal['merek_body'] ?: 'Standar') ?> (Tahun: <?= $kapal['tahun_body'] ?: $kapal['tahun_buatan'] ?>)</td>
                    <td align="right"><?= formatRupiah($kapal['nj_body_kapal']) ?></td>
                  </tr>
                  <?php if (!empty($mesinList)): ?>
                    <?php foreach ($mesinList as $m): ?>
                      <tr>
                        <td>Mesin Ke-<?= $m['mesin_ke'] ?></td>
                        <td>
                          <?= htmlspecialchars($m['merek_mesin']) ?> (<?= htmlspecialchars($m['daya_pk']) ?> PK)
                          <?php if (!empty($m['nomor_mesin'])): ?>
                            <br><small style="color: #64748b;">No: <?= htmlspecialchars($m['nomor_mesin']) ?></small>
                          <?php endif; ?>
                        </td>
                        <td align="right"><?= formatRupiah($m['nilai_jual_mesin']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            <?php endif; ?>

            <div style="margin-top: 12px; background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 6px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center;">
              <span style="font-size: 12px; font-weight: 700; color: #065f46;">TOTAL DASAR PENGENAAN NJKB:</span>
              <strong style="font-size: 17px; color: #065f46;"><?= formatRupiah($kapal['total_njkb']) ?></strong>
            </div>
          </div>
        </div>
      </div>

      <!-- KOLOM KANAN: MASA PKAA TERBARU, RUMUS PERHITUNGAN, & FORM PEMBAYARAN -->
      <div>
        <!-- Panel 3: Masa Pajak PKAA Mulai Dari dan S.D Pajak Terbaru -->
        <div class="panel" style="margin-bottom: 16px; border: 1.5px solid #f59e0b; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.08);">
          <div class="panel-header" style="background: #fffbeb; border-bottom: 1.5px solid #fde68a;">
            <div class="panel-title" style="color: #b45309; font-weight: 700;">
              <?= renderIcon('calendar', '', 16) ?>
              <span>PERIODE MASA PAJAK PKAA TAHUNAN TERBARU</span>
            </div>
            <span class="badge badge-warning">Penetapan 1 Tahun Berjalan</span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <!-- Informasi Masa Pajak Lama -->
            <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center;">
              <div>
                <span style="font-size: 11px; color: #64748b; display: block;">Masa Pajak Sebelumnya Berakhir Pada:</span>
                <strong style="font-size: 14px; color: <?= (strtotime(date('Y-m-d')) > strtotime($kapal['tg_akhir_pajak'])) ? '#dc2626' : '#0284c7' ?>;">
                  <?= formatTanggalIndo($kapal['tg_akhir_pajak']) ?>
                </strong>
              </div>
              <div>
                <?php if (strtotime(date('Y-m-d')) > strtotime($kapal['tg_akhir_pajak'])): ?>
                  <span class="badge badge-danger">Lewat Jatuh Tempo (Terkena Denda)</span>
                <?php else: ?>
                  <span class="badge badge-success">Masih Berlaku (Perpanjangan Rutin)</span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Input Masa Pajak Mulai & Masa Pajak S.D Terbaru -->
            <div class="form-grid-2">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="masa_pajak_awal" style="color: #b45309; font-weight: 700;">
                  Masa PKAA Mulai Dari <span class="required">*</span>
                </label>
                <input type="date" name="masa_pajak_awal" id="masa_pajak_awal" class="form-control" 
                       value="<?= htmlspecialchars($masaAwalBaru) ?>" required onchange="onMasaAwalChange(this.value)">
                <span style="font-size: 11px; color: #64748b; margin-top: 3px; display: block;">
                  Tanggal dimulainya tahun pajak baru
                </span>
              </div>

              <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="masa_pajak_akhir" style="color: #b45309; font-weight: 700;">
                  Masa PKAA Sampai Dengan (s.d) <span class="required">*</span>
                </label>
                <input type="date" name="masa_pajak_akhir" id="masa_pajak_akhir" class="form-control" 
                       value="<?= htmlspecialchars($masaAkhirBaru) ?>" required>
                <span style="font-size: 11px; color: #64748b; margin-top: 3px; display: block;">
                  Otomatis 1 tahun ke depan
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Panel 4: Perhitungan Ketetapan Fiskal & Denda -->
        <div class="panel" style="margin-bottom: 16px;">
          <div class="panel-header">
            <div class="panel-title">
              <?= renderIcon('receipt', '', 16) ?>
              <span>RINCIAN PERHITUNGAN PKAA & OPSEN 66%</span>
            </div>
            <span class="badge badge-neutral">Sistem Tarif Resmi</span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <table class="table-clean" style="margin: 0; font-size: 13px;">
              <tr>
                <td class="label">1. Dasar Pengenaan NJKB</td>
                <td class="sep">:</td>
                <td class="val" align="right"><strong><?= formatRupiah($perhitungan['njkb_dasar']) ?></strong></td>
              </tr>
              <tr>
                <td class="label">2. Tarif Pokok PKAA (Fungsi: <?= htmlspecialchars($perhitungan['nama_fungsi']) ?>)</td>
                <td class="sep">:</td>
                <td class="val" align="right"><span class="badge badge-neutral"><?= $perhitungan['tarif_pokok_persen'] ?>%</span></td>
              </tr>
              <tr>
                <td class="label">3. Bea Pokok PKAA (NJKB &times; Tarif)</td>
                <td class="sep">:</td>
                <td class="val" align="right" style="color: #0284c7; font-weight: 700;"><?= formatRupiah($perhitungan['bea_pkaa_pokok']) ?></td>
              </tr>
              <tr>
                <td class="label">
                  4. Sanksi Administrasi / Denda Keterlambatan
                  <?php if ($perhitungan['bulan_terlambat'] > 0): ?>
                    <span class="badge badge-danger" style="font-size: 10px; margin-left: 4px;"><?= $perhitungan['bulan_terlambat'] ?> Bulan Terlambat</span>
                  <?php endif; ?>
                </td>
                <td class="sep">:</td>
                <td class="val" align="right" style="color: <?= $perhitungan['bea_pkaa_denda'] > 0 ? '#dc2626' : '#64748b' ?>; font-weight: 700;">
                  <?= formatRupiah($perhitungan['bea_pkaa_denda']) ?>
                </td>
              </tr>
              <tr>
                <td class="label">5. Opsen PKAA Pokok (66% dari Bea Pokok)</td>
                <td class="sep">:</td>
                <td class="val" align="right" style="color: #b45309; font-weight: 700;"><?= formatRupiah($perhitungan['opsen_pkaa_pokok']) ?></td>
              </tr>
              <?php if ($perhitungan['opsen_pkaa_denda'] > 0): ?>
                <tr>
                  <td class="label">6. Denda Opsen PKAA (66% dari Denda Pokok)</td>
                  <td class="sep">:</td>
                  <td class="val" align="right" style="color: #dc2626; font-weight: 700;"><?= formatRupiah($perhitungan['opsen_pkaa_denda']) ?></td>
                </tr>
              <?php endif; ?>
              <tr style="border-top: 2px solid #0f172a; background: #f8fafc;">
                <td class="label" style="font-size: 14px; font-weight: 800; color: #0f172a; padding: 10px 0;">TOTAL KETETAPAN PEMBAYARAN:</td>
                <td class="sep" style="padding: 10px 0;">:</td>
                <td class="val" align="right" style="padding: 10px 0;">
                  <strong style="font-size: 18px; color: #065f46;"><?= formatRupiah($perhitungan['total_ketetapan']) ?></strong>
                </td>
              </tr>
              <tr>
                <td colspan="3" style="font-size: 11.5px; font-style: italic; color: #475569; padding-top: 4px;">
                  Terbilang: <strong><?= terbilang($perhitungan['total_ketetapan']) ?> Rupiah</strong>
                </td>
              </tr>
            </table>
          </div>
        </div>

        <!-- Panel 5: Detail Penyetoran & Tombol Eksekusi Bayar -->
        <div class="panel" style="border: 1.5px solid #10b981;">
          <div class="panel-header" style="background: #f0fdf4; border-bottom: 1.5px solid #86efac;">
            <div class="panel-title" style="color: #166534; font-weight: 700;">
              <?= renderIcon('check-circle', '', 16) ?>
              <span>VALIDASI PELUNASAN KASDA & PENERBITAN SKPD</span>
            </div>
            <span class="badge badge-success">Kas Daerah NTT</span>
          </div>
          <div class="panel-body" style="padding: 16px;">
            <div class="form-grid-2">
              <div class="form-group">
                <label class="form-label" for="tgl_bayar">Tanggal Pembayaran / Validasi <span class="required">*</span></label>
                <input type="date" name="tgl_bayar" id="tgl_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="metode_bayar">Kanal Pembayaran <span class="required">*</span></label>
                <select name="metode_bayar" id="metode_bayar" class="form-control" required>
                  <option value="BANK NTT (Virtual Account / Teller)" selected>BANK NTT (Virtual Account / Teller)</option>
                  <option value="LOKET KASIR BPAD NTT (Tunai)">LOKET KASIR BPAD NTT (Tunai)</option>
                  <option value="QRIS KASDA PROVINSI NTT">QRIS KASDA PROVINSI NTT</option>
                  <option value="TRANSFER ANTAR BANK (ATM / Mobile Banking)">TRANSFER ANTAR BANK (ATM / Mobile)</option>
                </select>
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label class="form-label" for="no_resi_bukti">No. Resi Bukti Bayar / NTPN / Referensi</label>
                <input type="text" name="no_resi_bukti" id="no_resi_bukti" class="form-control" placeholder="Contoh: RESI-NTT-<?= date('Ymd') ?>-001" value="RESI-NTT-<?= date('Ymd') ?>-<?= rand(100, 999) ?>">
              </div>

              <div class="form-group">
                <label class="form-label" for="pejabat_skpd_id">Pejabat Penandatangan SKPD <span class="required">*</span></label>
                <select name="pejabat_skpd_id" id="pejabat_skpd_id" class="form-control" required>
                  <?php foreach ($pejabatSkpdList as $pj): ?>
                    <option value="<?= $pj['id'] ?>" selected>
                      <?= htmlspecialchars($pj['nama_pejabat']) ?> &mdash; <?= htmlspecialchars($pj['jabatan']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div style="margin-top: 10px; display: flex; justify-content: flex-end; gap: 10px;">
              <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary" style="padding: 10px 18px;">
                Batal
              </a>
              <button type="submit" class="btn btn-primary" style="background: #059669; border-color: #047857; padding: 10px 28px; font-weight: 800; font-size: 14px; box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);" onclick="return confirm('Apakah Anda yakin data pembayaran sudah benar dan ingin mengesahkan SKPD PKAA Tahunan ini?')">
                <?= renderIcon('check-circle', '', 18) ?>
                <span>PROSES & SAHKAN PEMBAYARAN TAHUNAN</span>
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </form>
<?php elseif (!$kapal && !empty($selectedNopd)): ?>
  <div class="panel" style="text-align: center; padding: 40px 20px;">
    <div style="color: #ef4444; margin-bottom: 12px;">
      <?= renderIcon('alert-triangle', '', 48) ?>
    </div>
    <h3 style="color: #0f172a; margin: 0 0 6px 0;">NOPD Tidak Ditemukan!</h3>
    <p style="color: #64748b; max-width: 480px; margin: 0 auto 18px auto;">
      Objek dengan nomor <strong><?= htmlspecialchars($selectedNopd) ?></strong> tidak terdaftar di sistem JANGKAR NTT. Pastikan penulisan nomor objek pajak sudah sesuai.
    </p>
    <a href="index.php?page=pembayaran_tahunan" class="btn btn-secondary">
      Kembali Cari NOPD Lain
    </a>
  </div>
<?php endif; ?>

<script>
// Penyesuaian otomatis Masa Akhir Pajak ketika Masa Awal diubah
function onMasaAwalChange(tglAwal) {
  if (!tglAwal) return;
  var d = new Date(tglAwal);
  d.setFullYear(d.getFullYear() + 1);
  
  var yyyy = d.getFullYear();
  var mm = String(d.getMonth() + 1).padStart(2, '0');
  var dd = String(d.getDate()).padStart(2, '0');
  
  var tglAkhirInput = document.getElementById('masa_pajak_akhir');
  if (tglAkhirInput) {
    tglAkhirInput.value = yyyy + '-' + mm + '-' + dd;
  }
}

// Listener keyboard shortcut: Tekan Enter pada input NOPD langsung submit
document.addEventListener('DOMContentLoaded', function() {
  var inp = document.getElementById('input_nopd_cari');
  if (inp) {
    inp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('formCariNopd').submit();
      }
    });
  }
});
</script>
