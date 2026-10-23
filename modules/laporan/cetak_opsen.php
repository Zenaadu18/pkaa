<?php
// modules/laporan/cetak_opsen.php - Modul Rekapitulasi & Cetak Dokumen Opsen PKAA 66% Kab/Kota di NTT
$pageTitle = "Cetak Opsen PKAA (66%)";

$tglAwal = trim($_GET['tgl_awal'] ?? date('Y-m-01'));
$tglAkhir = trim($_GET['tgl_akhir'] ?? date('Y-m-d'));
$filterKab = trim($_GET['kd_wilayah'] ?? '');
$kabKotaList = getKabKotaList($pdo);

$sql = "
    SELECT sk.*, k.nama_kapal, k.nama_pemilik, k.kode_kabkota, kb.nama_kabupaten_kota
    FROM transaksi_skpd sk
    JOIN kendaraan_air k ON sk.no_objek_pajak = k.no_objek_pajak
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
$opsenList = $stmt->fetchAll();

// Hitung Agregat
$totPokokPkaa = 0;
$totPokokOpsen = 0;
$totDendaOpsen = 0;
$totGrandOpsen = 0;

foreach ($opsenList as $row) {
    $totPokokPkaa += $row['bea_pkaa_pokok_bayar'];
    $totPokokOpsen += $row['opsen_pkaa_pokok_bayar'];
    $totDendaOpsen += $row['opsen_pkaa_denda_bayar'];
    $totGrandOpsen += ($row['opsen_pkaa_pokok_bayar'] + $row['opsen_pkaa_denda_bayar']);
}

$printUrl = "cetak/opsen_print.php?tgl_awal=" . urlencode($tglAwal) . "&tgl_akhir=" . urlencode($tglAkhir) . "&kd_wilayah=" . urlencode($filterKab);
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('pie-chart', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Penerimaan & Cetak Lembar Resmi Opsen PKAA (66%)</div>
      <div class="page-subtitle">Rekapitulasi Hak Bagi Hasil Kas Daerah 22 Kabupaten / Kota di NTT Berdasarkan UU HKPD No. 1/2022</div>
    </div>
  </div>
  <div class="page-header-actions">
    <a href="<?= $printUrl ?>" target="_blank" class="btn btn-accent btn-sm" style="background:#d97706; border-color:#b45309; color:#fff;">
      <?= renderIcon('printer', '', 14) ?> Cetak Lembar Resmi Opsen (Print / PDF)
    </a>
  </div>
</div>

<!-- Parameter Filter -->
<div class="panel" style="margin-bottom: 14px;">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('filter', '', 16) ?>
      <span>PARAMETER FILTER PERIODE & WILAYAH OPSEN KAB/KOTA</span>
    </div>
    <span class="badge badge-info">Porsi Opsen: 66% dari Bea Pokok PKAA</span>
  </div>
  <div class="panel-body">
    <form method="GET" action="index.php" style="display:grid; grid-template-columns: 1fr 1fr 1.5fr auto; gap:12px; align-items:flex-end;">
      <input type="hidden" name="page" value="cetak_opsen">

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" for="tgl_awal">Tanggal Setor Awal</label>
        <input type="date" name="tgl_awal" id="tgl_awal" class="form-control" value="<?= htmlspecialchars($tglAwal) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" for="tgl_akhir">Tanggal Setor Akhir</label>
        <input type="date" name="tgl_akhir" id="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tglAkhir) ?>">
      </div>

      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label" for="kd_wilayah">Wilayah Kab/Kota Penerima Opsen</label>
        <select name="kd_wilayah" id="kd_wilayah" class="form-control">
          <option value="">-- Seluruh 22 Kab/Kota se-NTT --</option>
          <?php foreach ($kabKotaList as $kb): ?>
            <option value="<?= $kb['kode_wilayah'] ?>" <?= $filterKab === $kb['kode_wilayah'] ? 'selected' : '' ?>>
              [<?= $kb['kode_wilayah'] ?>] <?= htmlspecialchars($kb['nama_kabupaten_kota']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="height:32px;">
        <?= renderIcon('search', '', 14) ?> Tampilkan Data
      </button>
    </form>
  </div>
</div>

<!-- Kartu Ringkasan Finansial Opsen -->
<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; margin-bottom: 14px;">
  <div class="stat-card" style="border-left: 4px solid #0284c7;">
    <div class="stat-card-label">Total Pokok PKAA (Provinsi)</div>
    <div class="stat-card-value" style="font-size:18px; color:#0369a1;"><?= formatRupiah($totPokokPkaa) ?></div>
    <div class="stat-card-hint">Dasar pengenaan opsen 100%</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #10b981;">
    <div class="stat-card-label">Total Pokok Opsen 66% (Kab/Kota)</div>
    <div class="stat-card-value" style="font-size:18px; color:#065f46;"><?= formatRupiah($totPokokOpsen) ?></div>
    <div class="stat-card-hint">66% dari pokok disetor ke kas kab/kota</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #f59e0b;">
    <div class="stat-card-label">Total Denda Opsen (Kab/Kota)</div>
    <div class="stat-card-value" style="font-size:18px; color:#b45309;"><?= formatRupiah($totDendaOpsen) ?></div>
    <div class="stat-card-hint">Sanksi keterlambatan opsen</div>
  </div>

  <div class="stat-card" style="border-left: 4px solid #8b5cf6;">
    <div class="stat-card-label">Grand Total Hak Kas Kab/Kota</div>
    <div class="stat-card-value" style="font-size:18px; color:#6d28d9;"><?= formatRupiah($totGrandOpsen) ?></div>
    <div class="stat-card-hint">Hak penerimaan pemerintah kab/kota</div>
  </div>
</div>

<!-- Tabel Rincian Data Opsen -->
<div class="panel">
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('list', '', 16) ?>
      <span>RINCIAN PENERIMAAN OPSEN PKAA PER KENDARAAN (<?= count($opsenList) ?> TRANSAKSI)</span>
    </div>
  </div>
  <div class="panel-body" style="padding:0;">
    <div class="table-responsive">
      <table class="table-desktop">
        <thead>
          <tr>
            <th width="40">NO</th>
            <th>NO. SKPD</th>
            <th>TGL BAYAR</th>
            <th>NOPD KA</th>
            <th>NAMA KAPAL</th>
            <th>KAB/KOTA PENERIMA OPSEN</th>
            <th>METODE / RESI</th>
            <th align="right">POKOK PKAA</th>
            <th align="right">POKOK OPSEN (66%)</th>
            <th align="right">DENDA OPSEN</th>
            <th align="right">TOTAL OPSEN DAERAH</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($opsenList)): ?>
            <tr>
              <td colspan="11" align="center" style="padding:24px; color:#64748b;">
                Tidak ada data setoran opsen pada periode yang dipilih.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($opsenList as $idx => $r): 
              $subTotalOpsen = $r['opsen_pkaa_pokok_bayar'] + $r['opsen_pkaa_denda_bayar'];
            ?>
              <tr>
                <td align="center"><?= $idx + 1 ?></td>
                <td><strong style="color:#0284c7;"><?= htmlspecialchars($r['no_skpd']) ?></strong></td>
                <td><?= date('d/m/Y', strtotime($r['tgl_bayar'])) ?></td>
                <td><strong style="font-family:var(--font-mono);"><?= htmlspecialchars($r['no_objek_pajak']) ?></strong></td>
                <td><?= htmlspecialchars($r['nama_kapal']) ?></td>
                <td><strong><?= htmlspecialchars($r['nama_kabupaten_kota'] ?? $r['kode_kabkota']) ?></strong></td>
                <td><?= htmlspecialchars($r['metode_bayar']) ?> &bull; <small><?= htmlspecialchars($r['no_resi_bukti'] ?: 'Kasda') ?></small></td>
                <td align="right"><?= number_format($r['bea_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
                <td align="right" style="font-weight:600; color:#065f46;"><?= number_format($r['opsen_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
                <td align="right" style="color:#b45309;"><?= number_format($r['opsen_pkaa_denda_bayar'], 0, ',', '.') ?></td>
                <td align="right" style="font-weight:700; color:#0369a1;"><?= number_format($subTotalOpsen, 0, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <tr style="background:#f8fafc; font-weight:800; border-top:2px solid #cbd5e1;">
              <td colspan="7" align="right">TOTAL PENERIMAAN OPSEN KABUPATEN / KOTA DI NTT (RP):</td>
              <td align="right"><?= number_format($totPokokPkaa, 0, ',', '.') ?></td>
              <td align="right" style="color:#065f46;"><?= number_format($totPokokOpsen, 0, ',', '.') ?></td>
              <td align="right" style="color:#b45309;"><?= number_format($totDendaOpsen, 0, ',', '.') ?></td>
              <td align="right" style="color:#0369a1; font-size:13px;"><?= number_format($totGrandOpsen, 0, ',', '.') ?></td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
