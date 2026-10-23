<?php
// modules/laporan/index.php - Menu Laporan Pajak Kendaraan di Atas Air JANGKAR NTT
$pageTitle = "Menu Laporan PKAA NTT";

$jenisLaporan = $_GET['jenis_laporan'] ?? 'lap_harian_penerimaan';
$tglAwal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tglAkhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$filterKab = $_GET['kd_wilayah'] ?? '';
$pejabatId = (int)($_GET['pejabat_id'] ?? 0);

// Ambil daftar 22 kabupaten / kota di NTT
$kabKotaList = getKabKotaList($pdo);

// Ambil daftar pejabat laporan
$pejabatLaporanList = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'LAPORAN' AND is_active = 1 ORDER BY id ASC")->fetchAll();
if ($pejabatId === 0 && !empty($pejabatLaporanList)) {
    $pejabatId = $pejabatLaporanList[0]['id'];
}

// Eksekusi query sesuai jenis laporan
$reportData = [];
$reportTitle = '';

if ($jenisLaporan === 'lap_harian_superpkaa' || $jenisLaporan === 'lap_bulanan_superpkaa') {
    $isBulanan = ($jenisLaporan === 'lap_bulanan_superpkaa');
    $reportTitle = $isBulanan ? "LAPORAN BULANAN PENERBITAN SUPERPKAA (PROVINSI NTT)" : "LAPORAN HARIAN PENERBITAN SUPERPKAA (PROVINSI NTT)";

    $sql = "
        SELECT s.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, k.kode_kabkota, f.nama_fungsi, kb.nama_kabupaten_kota
        FROM transaksi_superpkaa s
        JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        WHERE s.tgl_terbit BETWEEN ? AND ?
    ";
    $params = [$tglAwal, $tglAkhir];
    if (!empty($filterKab)) {
        $sql .= " AND k.kode_kabkota = ? ";
        $params[] = $filterKab;
    }
    $sql .= " ORDER BY s.tgl_terbit ASC, s.no_superpkaa ASC ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
} elseif ($jenisLaporan === 'lap_harian_penerimaan' || $jenisLaporan === 'lap_bulanan_penerimaan') {
    $isBulanan = ($jenisLaporan === 'lap_bulanan_penerimaan');
    $reportTitle = $isBulanan ? "LAPORAN BULANAN PENERIMAAN PAJAK KENDARAAN DI ATAS AIR (PROVINSI NTT)" : "LAPORAN HARIAN PENERIMAAN PAJAK KENDARAAN DI ATAS AIR (PROVINSI NTT)";

    $sql = "
        SELECT sk.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, k.kode_kabkota, f.nama_fungsi,
               s.masa_pajak_awal, s.masa_pajak_akhir, s.njkb_dasar, kb.nama_kabupaten_kota
        FROM transaksi_skpd sk
        JOIN transaksi_superpkaa s ON sk.no_superpkaa = s.no_superpkaa
        JOIN kendaraan_air k ON sk.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        WHERE sk.tgl_bayar BETWEEN ? AND ?
    ";
    $params = [$tglAwal, $tglAkhir];
    if (!empty($filterKab)) {
        $sql .= " AND k.kode_kabkota = ? ";
        $params[] = $filterKab;
    }
    $sql .= " ORDER BY sk.tgl_bayar ASC, sk.no_skpd ASC ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
} elseif ($jenisLaporan === 'lap_harian_opsen' || $jenisLaporan === 'lap_bulanan_opsen') {
    $isBulanan = ($jenisLaporan === 'lap_bulanan_opsen');
    $reportTitle = $isBulanan ? "LAPORAN BULANAN PENERIMAAN OPSEN PKAA (66%) KABUPATEN/KOTA SE-NTT" : "LAPORAN HARIAN PENERIMAAN OPSEN PKAA (66%) KABUPATEN/KOTA SE-NTT";

    $sql = "
        SELECT sk.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, k.kode_kabkota, f.nama_fungsi, kb.nama_kabupaten_kota
        FROM transaksi_skpd sk
        JOIN kendaraan_air k ON sk.no_objek_pajak = k.no_objek_pajak
        JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        WHERE sk.tgl_bayar BETWEEN ? AND ?
    ";
    $params = [$tglAwal, $tglAkhir];
    if (!empty($filterKab)) {
        $sql .= " AND k.kode_kabkota = ? ";
        $params[] = $filterKab;
    }
    $sql .= " ORDER BY sk.tgl_bayar ASC, sk.no_skpd ASC ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reportData = $stmt->fetchAll();
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('printer', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Menu Laporan JANGKAR NTT</div>
      <div class="page-subtitle">Penerbitan SUPERPKAA, Penerimaan Kasda PKAA Pokok & Opsen 66% Kabupaten/Kota se-NTT</div>
    </div>
  </div>
</div>

<!-- Form Parameter Laporan -->
<form method="GET" action="index.php" class="panel" style="margin-bottom: 14px;">
  <input type="hidden" name="page" value="laporan">

  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('calendar', '', 16) ?>
      <span>PARAMETER FILTER LAPORAN FISKAL DAERAH</span>
    </div>
    <span class="badge badge-info">BPAD PROVINSI NUSA TENGGARA TIMUR</span>
  </div>

  <div class="panel-body">
    <div style="display: grid; grid-template-columns: 1.4fr 1fr 1fr 1.2fr; gap: 12px; align-items: flex-end;">
      <!-- Pilihan 6 Jenis Laporan -->
      <div class="form-group">
        <label class="form-label" for="jenis_laporan">Pilihan Format Laporan</label>
        <select name="jenis_laporan" id="jenis_laporan" class="form-control">
          <optgroup label="1. Penerbitan Ketetapan (SUPERPKAA)">
            <option value="lap_harian_superpkaa" <?= $jenisLaporan === 'lap_harian_superpkaa' ? 'selected' : '' ?>>A. Laporan Harian Penerbitan SUPERPKAA</option>
            <option value="lap_bulanan_superpkaa" <?= $jenisLaporan === 'lap_bulanan_superpkaa' ? 'selected' : '' ?>>B. Laporan Bulanan Penerbitan SUPERPKAA</option>
          </optgroup>
          <optgroup label="2. Kas Masuk Penerimaan PKAA (Provinsi)">
            <option value="lap_harian_penerimaan" <?= $jenisLaporan === 'lap_harian_penerimaan' ? 'selected' : '' ?>>C. Laporan Harian Penerimaan PKAA</option>
            <option value="lap_bulanan_penerimaan" <?= $jenisLaporan === 'lap_bulanan_penerimaan' ? 'selected' : '' ?>>D. Laporan Bulanan Penerimaan PKAA</option>
          </optgroup>
          <optgroup label="3. Kas Masuk Opsen PKAA 66% (Kabupaten/Kota)">
            <option value="lap_harian_opsen" <?= $jenisLaporan === 'lap_harian_opsen' ? 'selected' : '' ?>>E. Laporan Harian Penerimaan Opsen PKAA</option>
            <option value="lap_bulanan_opsen" <?= $jenisLaporan === 'lap_bulanan_opsen' ? 'selected' : '' ?>>F. Laporan Bulanan Penerimaan Opsen PKAA</option>
          </optgroup>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="tgl_awal">Tanggal Awal</label>
        <input type="date" name="tgl_awal" id="tgl_awal" class="form-control" value="<?= htmlspecialchars($tglAwal) ?>">
      </div>

      <div class="form-group">
        <label class="form-label" for="tgl_akhir">Tanggal Akhir</label>
        <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tglAkhir) ?>">
      </div>

      <!-- Filter Wilayah Kab/Kota NTT -->
      <div class="form-group">
        <label class="form-label" for="kd_wilayah">Wilayah Kab/Kota di NTT</label>
        <select name="kd_wilayah" id="kd_wilayah" class="form-control">
          <option value="">-- Semua 22 Kab/Kota NTT --</option>
          <?php foreach ($kabKotaList as $kb): ?>
            <option value="<?= $kb['kode_wilayah'] ?>" <?= $filterKab === $kb['kode_wilayah'] ? 'selected' : '' ?>>
              [<?= $kb['kode_wilayah'] ?>] <?= htmlspecialchars($kb['nama_kabupaten_kota']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div style="display: flex; gap: 12px; margin-top: 12px; align-items: flex-end;">
      <div class="form-group" style="flex: 1;">
        <label class="form-label" for="pejabat_id">Pejabat Penandatangan Laporan</label>
        <select name="pejabat_id" id="pejabat_id" class="form-control">
          <?php foreach ($pejabatLaporanList as $pj): ?>
            <option value="<?= $pj['id'] ?>" <?= $pejabatId == $pj['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($pj['nama_pejabat']) ?> &mdash; <?= htmlspecialchars($pj['jabatan']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="height: 32px;">
        <?= renderIcon('search', '', 14) ?> Tampilkan Pratinjau
      </button>

      <?php
      $printUrl = "modules/laporan/cetak_laporan.php?jenis=" . urlencode($jenisLaporan) .
                  "&tgl_awal=" . urlencode($tglAwal) .
                  "&tgl_akhir=" . urlencode($tglAkhir) .
                  "&kd_wilayah=" . urlencode($filterKab) .
                  "&pejabat=" . urlencode($pejabatId);
      ?>
      <a href="<?= $printUrl ?>" target="_blank" class="btn btn-accent" style="height: 32px;">
        <?= renderIcon('printer', '', 14) ?> Cetak Lembar Resmi Laporan
      </a>
    </div>
  </div>
</form>

<!-- Tabel Pratinjau Laporan -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('file-text', '', 16) ?>
      <span><?= htmlspecialchars($reportTitle) ?></span>
    </div>
    <span class="badge badge-neutral">Periode: <?= formatTanggalIndo($tglAwal, false) ?> s/d <?= formatTanggalIndo($tglAkhir, false) ?></span>
  </div>

  <div class="panel-body" style="padding: 0;">
    <div class="table-responsive" style="border: none;">
      <table class="table-desktop">
        <?php if ($jenisLaporan === 'lap_harian_superpkaa' || $jenisLaporan === 'lap_bulanan_superpkaa'): ?>
          <thead>
            <tr>
              <th width="40" class="text-center">No</th>
              <th width="150">No. SUPERPKAA</th>
              <th width="90">Tgl. Terbit</th>
              <th>Nopol KA / Nama Kapal</th>
              <th>Asal Kab/Kota</th>
              <th>Nama Pemilik</th>
              <th>Fungsi</th>
              <th class="text-right">NJKB Total</th>
              <th class="text-right">Pokok PKAA</th>
              <th class="text-right">Opsen (66%)</th>
              <th class="text-right">Total Ketetapan</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              $no = 1;
              $sumNjkb = 0; $sumPokok = 0; $sumOpsen = 0; $sumTotal = 0;
            ?>
            <?php if (empty($reportData)): ?>
              <tr><td colspan="11" class="text-center" style="padding: 24px; color:#94a3b8;">Tidak ada data penerbitan pada periode ini.</td></tr>
            <?php else: ?>
              <?php foreach ($reportData as $row): 
                $sumNjkb += $row['njkb_dasar'];
                $sumPokok += $row['bea_pkaa_pokok'];
                $sumOpsen += $row['opsen_pkaa_pokok'];
                $sumTotal += $row['total_ketetapan'];
              ?>
                <tr>
                  <td class="text-center"><?= $no++ ?></td>
                  <td><strong><?= htmlspecialchars($row['no_superpkaa']) ?></strong></td>
                  <td><?= formatTanggalIndo($row['tgl_terbit'], false) ?></td>
                  <td>
                    <strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($row['no_objek_pajak']) ?></strong><br>
                    <strong><?= htmlspecialchars($row['nama_kapal']) ?></strong>
                  </td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($row['kode_kabkota'] ?: 'NTT') ?></span> <?= htmlspecialchars($row['nama_kabupaten_kota'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['nama_pemilik']) ?></td>
                  <td><?= htmlspecialchars($row['nama_fungsi']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['njkb_dasar']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['bea_pkaa_pokok']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['opsen_pkaa_pokok']) ?></td>
                  <td class="text-right"><strong><?= formatRupiah($row['total_ketetapan']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <tr style="background:#f1f5f9; font-weight:700;">
                <td colspan="7" class="text-right">TOTAL KESELURUHAN:</td>
                <td class="text-right"><?= formatRupiah($sumNjkb) ?></td>
                <td class="text-right"><?= formatRupiah($sumPokok) ?></td>
                <td class="text-right"><?= formatRupiah($sumOpsen) ?></td>
                <td class="text-right" style="color:#0284c7;"><?= formatRupiah($sumTotal) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>

        <?php elseif ($jenisLaporan === 'lap_harian_penerimaan' || $jenisLaporan === 'lap_bulanan_penerimaan'): ?>
          <thead>
            <tr>
              <th width="40" class="text-center">No</th>
              <th width="150">No. SKPD</th>
              <th width="90">Tgl. Bayar</th>
              <th>Nopol KA / Nama Kapal</th>
              <th>Asal Kab/Kota</th>
              <th>Nama Pemilik</th>
              <th>Metode Setor</th>
              <th class="text-right">Pokok PKAA</th>
              <th class="text-right">Denda PKAA</th>
              <th class="text-right">Total Disetor (Prov NTT)</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              $no = 1;
              $sumPokok = 0; $sumDenda = 0; $sumTotal = 0;
            ?>
            <?php if (empty($reportData)): ?>
              <tr><td colspan="10" class="text-center" style="padding: 24px; color:#94a3b8;">Tidak ada data penerimaan PKAA pada periode ini.</td></tr>
            <?php else: ?>
              <?php foreach ($reportData as $row): 
                $subProv = $row['bea_pkaa_pokok_bayar'] + $row['bea_pkaa_denda_bayar'];
                $sumPokok += $row['bea_pkaa_pokok_bayar'];
                $sumDenda += $row['bea_pkaa_denda_bayar'];
                $sumTotal += $subProv;
              ?>
                <tr>
                  <td class="text-center"><?= $no++ ?></td>
                  <td><strong><?= htmlspecialchars($row['no_skpd']) ?></strong></td>
                  <td><?= formatTanggalIndo($row['tgl_bayar'], false) ?></td>
                  <td>
                    <strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($row['no_objek_pajak']) ?></strong><br>
                    <strong><?= htmlspecialchars($row['nama_kapal']) ?></strong>
                  </td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($row['kode_kabkota'] ?: 'NTT') ?></span> <?= htmlspecialchars($row['nama_kabupaten_kota'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['nama_pemilik']) ?></td>
                  <td><?= htmlspecialchars($row['metode_bayar']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['bea_pkaa_pokok_bayar']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['bea_pkaa_denda_bayar']) ?></td>
                  <td class="text-right"><strong><?= formatRupiah($subProv) ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <tr style="background:#f1f5f9; font-weight:700;">
                <td colspan="7" class="text-right">TOTAL PENERIMAAN KASDA PROVINSI NTT:</td>
                <td class="text-right"><?= formatRupiah($sumPokok) ?></td>
                <td class="text-right"><?= formatRupiah($sumDenda) ?></td>
                <td class="text-right" style="color:#059669;"><?= formatRupiah($sumTotal) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>

        <?php elseif ($jenisLaporan === 'lap_harian_opsen' || $jenisLaporan === 'lap_bulanan_opsen'): ?>
          <thead>
            <tr>
              <th width="40" class="text-center">No</th>
              <th width="150">No. SKPD</th>
              <th width="90">Tgl. Bayar</th>
              <th>Nopol KA / Nama Kapal</th>
              <th>Kabupaten/Kota Penerima</th>
              <th>Nama Pemilik</th>
              <th>Metode Setor</th>
              <th class="text-right">Pokok Opsen (66%)</th>
              <th class="text-right">Denda Opsen</th>
              <th class="text-right">Total Opsen (Kab/Kota)</th>
            </tr>
          </thead>
          <tbody>
            <?php 
              $no = 1;
              $sumPokokOpsen = 0; $sumDendaOpsen = 0; $sumTotalOpsen = 0;
            ?>
            <?php if (empty($reportData)): ?>
              <tr><td colspan="10" class="text-center" style="padding: 24px; color:#94a3b8;">Tidak ada data penerimaan Opsen pada periode ini.</td></tr>
            <?php else: ?>
              <?php foreach ($reportData as $row): 
                $subOpsen = $row['opsen_pkaa_pokok_bayar'] + $row['opsen_pkaa_denda_bayar'];
                $sumPokokOpsen += $row['opsen_pkaa_pokok_bayar'];
                $sumDendaOpsen += $row['opsen_pkaa_denda_bayar'];
                $sumTotalOpsen += $subOpsen;
              ?>
                <tr>
                  <td class="text-center"><?= $no++ ?></td>
                  <td><strong><?= htmlspecialchars($row['no_skpd']) ?></strong></td>
                  <td><?= formatTanggalIndo($row['tgl_bayar'], false) ?></td>
                  <td>
                    <strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($row['no_objek_pajak']) ?></strong><br>
                    <strong><?= htmlspecialchars($row['nama_kapal']) ?></strong>
                  </td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($row['kode_kabkota'] ?: 'NTT') ?></span> <strong><?= htmlspecialchars($row['nama_kabupaten_kota'] ?? '') ?></strong></td>
                  <td><?= htmlspecialchars($row['nama_pemilik']) ?></td>
                  <td><?= htmlspecialchars($row['metode_bayar']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['opsen_pkaa_pokok_bayar']) ?></td>
                  <td class="text-right"><?= formatRupiah($row['opsen_pkaa_denda_bayar']) ?></td>
                  <td class="text-right"><strong><?= formatRupiah($subOpsen) ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <tr style="background:#f1f5f9; font-weight:700;">
                <td colspan="7" class="text-right">TOTAL PENERIMAAN BAGI HASIL OPSEN KAB/KOTA SE-NTT:</td>
                <td class="text-right"><?= formatRupiah($sumPokokOpsen) ?></td>
                <td class="text-right"><?= formatRupiah($sumDendaOpsen) ?></td>
                <td class="text-right" style="color:#7c3aed;"><?= formatRupiah($sumTotalOpsen) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>
