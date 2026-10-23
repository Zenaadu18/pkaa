<?php
// modules/laporan/cetak_laporan.php - Format Lembar Resmi Cetak Laporan BPAD Provinsi NTT
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

$jenis = $_GET['jenis'] ?? 'lap_harian_penerimaan';
$tglAwal = $_GET['tgl_awal'] ?? date('Y-m-01');
$tglAkhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
$pejabatId = (int)($_GET['pejabat'] ?? 0);
$filterKab = trim($_GET['kd_wilayah'] ?? '');

// Ambil info nama kab/kota jika difilter
$namaFilterWilayah = "SELURUH WILAYAH PROVINSI NTT (22 KABUPATEN / KOTA)";
if (!empty($filterKab)) {
    $stK = $pdo->prepare("SELECT nama_kabupaten_kota FROM ref_kabupaten_kota WHERE kode_wilayah = ? LIMIT 1");
    $stK->execute([$filterKab]);
    $rk = $stK->fetch();
    if ($rk) {
        $namaFilterWilayah = "WILAYAH: [" . $filterKab . "] " . strtoupper($rk['nama_kabupaten_kota']);
    }
}

// Ambil Identitas Instansi dari database
$namaInstansi = getSetting($pdo, 'nama_instansi', 'BADAN PENDAPATAN DAN ASET DAERAH PROVINSI NUSA TENGGARA TIMUR');
$namaUpt = getSetting($pdo, 'nama_upt', 'UPTD PENDAPATAN DAERAH WILAYAH KOTA KUPANG');
$alamatKantor = getSetting($pdo, 'alamat_kantor', 'Jl. Polisi Militer No. 1, Kota Kupang, Nusa Tenggara Timur 85111');
$teleponKantor = getSetting($pdo, 'telepon_kantor', '(0380) 833124');

// Ambil Data Pejabat Penandatangan
$pejabatTtd = null;
if ($pejabatId > 0) {
    $stmtP = $pdo->prepare("SELECT * FROM pejabat WHERE id = ?");
    $stmtP->execute([$pejabatId]);
    $pejabatTtd = $stmtP->fetch();
}
if (!$pejabatTtd) {
    $pejabatTtd = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'LAPORAN' AND is_active = 1 LIMIT 1")->fetch();
}

// Kepala UPTD sebagai Mengetahui
$kepalaUpt = $pdo->query("SELECT * FROM pejabat WHERE jabatan LIKE '%Kepala%' AND is_active = 1 LIMIT 1")->fetch();

// Eksekusi Data Laporan
$reportData = [];
$reportTitle = '';
$subTitle = "PERIODE: " . strtoupper(formatTanggalIndo($tglAwal)) . " S/D " . strtoupper(formatTanggalIndo($tglAkhir));

if ($jenis === 'lap_harian_superpkaa' || $jenis === 'lap_bulanan_superpkaa') {
    $reportTitle = ($jenis === 'lap_bulanan_superpkaa') ? "LAPORAN BULANAN PENERBITAN SUPERPKAA" : "LAPORAN HARIAN PENERBITAN SUPERPKAA";
    $sql = "
        SELECT s.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, k.kode_kabkota, kb.nama_kabupaten_kota, f.nama_fungsi
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
} elseif ($jenis === 'lap_harian_penerimaan' || $jenis === 'lap_bulanan_penerimaan') {
    $reportTitle = ($jenis === 'lap_bulanan_penerimaan') ? "LAPORAN BULANAN PENERIMAAN PAJAK KENDARAAN DI ATAS AIR (PKAA)" : "LAPORAN HARIAN PENERIMAAN PAJAK KENDARAAN DI ATAS AIR (PKAA)";
    $sql = "
        SELECT sk.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, k.kode_kabkota, kb.nama_kabupaten_kota, f.nama_fungsi
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
} elseif ($jenis === 'lap_harian_opsen' || $jenis === 'lap_bulanan_opsen') {
    $reportTitle = ($jenis === 'lap_bulanan_opsen') ? "LAPORAN BULANAN PENERIMAAN OPSEN PKAA 66% (KABUPATEN / KOTA)" : "LAPORAN HARIAN PENERIMAAN OPSEN PKAA 66% (KABUPATEN / KOTA)";
    $sql = "
        SELECT sk.*, k.nama_kapal, k.nama_pemilik, k.tanda_selar, k.kode_kabkota, kb.nama_kabupaten_kota, f.nama_fungsi
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
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($reportTitle) ?> - Cetak Dokumen</title>
  <link rel="stylesheet" href="../../assets/css/print.css">
  <style>
    @page { size: landscape; margin: 12mm; }
  </style>
</head>
<body class="print-document">

<!-- Toolbar Cetak di Layar -->
<div class="print-toolbar">
  <div>
    <strong>JANGKAR NTT</strong> &bull; Pratinjau Cetak Lembar Resmi Laporan Pendapatan Pajak Air
  </div>
  <div style="display:flex; gap:10px;">
    <button type="button" class="btn-print" onclick="window.print()">
      CETAK SEKARANG (PRINT / PDF)
    </button>
    <button type="button" style="background:#475569; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;" onclick="window.close()">
      Tutup
    </button>
  </div>
</div>

<div class="print-container" style="max-width: 275mm;">
  <!-- KOP SURAT PEMPROV NTT -->
  <div class="kop-surat">
    <img src="../../assets/img/logontt.png" class="kop-logo" alt="Logo NTT">
    <h2>PEMERINTAH PROVINSI NUSA TENGGARA TIMUR</h2>
    <h1><?= htmlspecialchars($namaInstansi) ?></h1>
    <h3><?= htmlspecialchars($namaUpt) ?></h3>
    <p><?= htmlspecialchars($alamatKantor) ?> &bull; Telp: <?= htmlspecialchars($teleponKantor) ?></p>
  </div>

  <!-- Judul Laporan -->
  <div class="dokumen-header">
    <div class="dokumen-title"><?= htmlspecialchars($reportTitle) ?></div>
    <div class="dokumen-nomor"><?= htmlspecialchars($subTitle) ?> &bull; <?= htmlspecialchars($namaFilterWilayah) ?></div>
  </div>

  <!-- Tabel Data Cetak Sesuai Jenis Laporan -->
  <table class="table-cetak">
    <?php if ($jenis === 'lap_harian_superpkaa' || $jenis === 'lap_bulanan_superpkaa'): ?>
      <thead>
        <tr>
          <th width="28">NO</th>
          <th width="145">NO. SUPERPKAA</th>
          <th width="80">TGL TERBIT</th>
          <th>NOPD (NOPOL KA) / NAMA KAPAL</th>
          <th>ASAL KAB/KOTA</th>
          <th>NAMA WAJIB PAJAK</th>
          <th>FUNGSI</th>
          <th width="105">NJKB DASAR</th>
          <th width="95">POKOK PKAA</th>
          <th width="95">OPSEN 66%</th>
          <th width="105">TOTAL KETETAPAN</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          $no = 1; $totNjkb = 0; $totPokok = 0; $totOpsen = 0; $totGrand = 0;
        ?>
        <?php foreach ($reportData as $r): 
          $totNjkb += $r['njkb_dasar'];
          $totPokok += $r['bea_pkaa_pokok'];
          $totOpsen += $r['opsen_pkaa_pokok'];
          $totGrand += $r['total_ketetapan'];
        ?>
          <tr>
            <td align="center"><?= $no++ ?></td>
            <td><strong><?= htmlspecialchars($r['no_superpkaa']) ?></strong></td>
            <td align="center"><?= date('d-m-Y', strtotime($r['tgl_terbit'])) ?></td>
            <td>
              <strong><?= htmlspecialchars($r['no_objek_pajak']) ?></strong><br>
              <small><?= htmlspecialchars($r['nama_kapal']) ?></small>
            </td>
            <td><?= htmlspecialchars($r['nama_kabupaten_kota'] ?? $r['kode_kabkota']) ?></td>
            <td><?= htmlspecialchars($r['nama_pemilik']) ?></td>
            <td><?= htmlspecialchars($r['nama_fungsi']) ?> (<?= $r['tarif_pokok_persen'] ?>%)</td>
            <td align="right"><?= number_format($r['njkb_dasar'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($r['bea_pkaa_pokok'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($r['opsen_pkaa_pokok'], 0, ',', '.') ?></td>
            <td align="right"><strong><?= number_format($r['total_ketetapan'], 0, ',', '.') ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#f2f2f2; font-weight:bold;">
          <td colspan="7" align="right">JUMLAH KESELURUHAN (RP):</td>
          <td align="right"><?= number_format($totNjkb, 0, ',', '.') ?></td>
          <td align="right"><?= number_format($totPokok, 0, ',', '.') ?></td>
          <td align="right"><?= number_format($totOpsen, 0, ',', '.') ?></td>
          <td align="right"><?= number_format($totGrand, 0, ',', '.') ?></td>
        </tr>
      </tbody>

    <?php elseif ($jenis === 'lap_harian_penerimaan' || $jenis === 'lap_bulanan_penerimaan'): ?>
      <thead>
        <tr>
          <th width="28">NO</th>
          <th width="145">NO. SKPD</th>
          <th width="80">TGL BAYAR</th>
          <th>NOPD (NOPOL KA) / NAMA KAPAL</th>
          <th>ASAL KAB/KOTA</th>
          <th>NAMA WAJIB PAJAK</th>
          <th>METODE SETOR / RESI</th>
          <th width="115">POKOK PKAA</th>
          <th width="105">DENDA PKAA</th>
          <th width="125">PENERIMAAN PROV (RP)</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          $no = 1; $totPokok = 0; $totDenda = 0; $totGrand = 0;
        ?>
        <?php foreach ($reportData as $r): 
          $sub = $r['bea_pkaa_pokok_bayar'] + $r['bea_pkaa_denda_bayar'];
          $totPokok += $r['bea_pkaa_pokok_bayar'];
          $totDenda += $r['bea_pkaa_denda_bayar'];
          $totGrand += $sub;
        ?>
          <tr>
            <td align="center"><?= $no++ ?></td>
            <td><strong><?= htmlspecialchars($r['no_skpd']) ?></strong></td>
            <td align="center"><?= date('d-m-Y', strtotime($r['tgl_bayar'])) ?></td>
            <td>
              <strong><?= htmlspecialchars($r['no_objek_pajak']) ?></strong><br>
              <small><?= htmlspecialchars($r['nama_kapal']) ?></small>
            </td>
            <td><?= htmlspecialchars($r['nama_kabupaten_kota'] ?? $r['kode_kabkota']) ?></td>
            <td><?= htmlspecialchars($r['nama_pemilik']) ?></td>
            <td><?= htmlspecialchars($r['metode_bayar']) ?> &bull; <small><?= htmlspecialchars($r['no_resi_bukti'] ?: 'Kasda') ?></small></td>
            <td align="right"><?= number_format($r['bea_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($r['bea_pkaa_denda_bayar'], 0, ',', '.') ?></td>
            <td align="right"><strong><?= number_format($sub, 0, ',', '.') ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#f2f2f2; font-weight:bold;">
          <td colspan="7" align="right">TOTAL PENERIMAAN KAS PROVINSI NTT (RP):</td>
          <td align="right"><?= number_format($totPokok, 0, ',', '.') ?></td>
          <td align="right"><?= number_format($totDenda, 0, ',', '.') ?></td>
          <td align="right"><?= number_format($totGrand, 0, ',', '.') ?></td>
        </tr>
      </tbody>

    <?php elseif ($jenis === 'lap_harian_opsen' || $jenis === 'lap_bulanan_opsen'): ?>
      <thead>
        <tr>
          <th width="28">NO</th>
          <th width="145">NO. SKPD</th>
          <th width="80">TGL BAYAR</th>
          <th>NOPD (NOPOL KA) / NAMA KAPAL</th>
          <th>KAB/KOTA PENERIMA OPSEN</th>
          <th>NAMA WAJIB PAJAK</th>
          <th>METODE SETOR / RESI</th>
          <th width="115">POKOK OPSEN (66%)</th>
          <th width="105">DENDA OPSEN</th>
          <th width="125">TOTAL OPSEN KAB/KOTA</th>
        </tr>
      </thead>
      <tbody>
        <?php 
          $no = 1; $totPokokOpsen = 0; $totDendaOpsen = 0; $totGrandOpsen = 0;
        ?>
        <?php foreach ($reportData as $r): 
          $sub = $r['opsen_pkaa_pokok_bayar'] + $r['opsen_pkaa_denda_bayar'];
          $totPokokOpsen += $r['opsen_pkaa_pokok_bayar'];
          $totDendaOpsen += $r['opsen_pkaa_denda_bayar'];
          $totGrandOpsen += $sub;
        ?>
          <tr>
            <td align="center"><?= $no++ ?></td>
            <td><strong><?= htmlspecialchars($r['no_skpd']) ?></strong></td>
            <td align="center"><?= date('d-m-Y', strtotime($r['tgl_bayar'])) ?></td>
            <td>
              <strong><?= htmlspecialchars($r['no_objek_pajak']) ?></strong><br>
              <small><?= htmlspecialchars($r['nama_kapal']) ?></small>
            </td>
            <td><strong><?= htmlspecialchars($r['nama_kabupaten_kota'] ?? $r['kode_kabkota']) ?></strong></td>
            <td><?= htmlspecialchars($r['nama_pemilik']) ?></td>
            <td><?= htmlspecialchars($r['metode_bayar']) ?> &bull; <small><?= htmlspecialchars($r['no_resi_bukti'] ?: 'Kasda') ?></small></td>
            <td align="right"><?= number_format($r['opsen_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($r['opsen_pkaa_denda_bayar'], 0, ',', '.') ?></td>
            <td align="right"><strong><?= number_format($sub, 0, ',', '.') ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <tr style="background:#f2f2f2; font-weight:bold;">
          <td colspan="7" align="right">TOTAL PENERIMAAN OPSEN KABUPATEN/KOTA DI NTT (RP):</td>
          <td align="right"><?= number_format($totPokokOpsen, 0, ',', '.') ?></td>
          <td align="right"><?= number_format($totDendaOpsen, 0, ',', '.') ?></td>
          <td align="right"><?= number_format($totGrandOpsen, 0, ',', '.') ?></td>
        </tr>
      </tbody>
    <?php endif; ?>
  </table>

  <!-- Kolom Pengesahan Tanda Tangan -->
  <div class="ttd-wrapper">
    <div class="ttd-box">
      <div>Mengetahui,</div>
      <div><strong><?= htmlspecialchars($kepalaUpt['jabatan'] ?? 'KEPALA UPTD WILAYAH KOTA KUPANG') ?></strong></div>
      <div class="ttd-space"></div>
      <div class="ttd-nama"><?= htmlspecialchars($kepalaUpt['nama_pejabat'] ?? 'Drs. Alex Lakamana, M.Si.') ?></div>
      <div class="ttd-nip"><?= htmlspecialchars($kepalaUpt['pangkat_gol'] ?? 'Pembina Utama Muda (IV/c)') ?></div>
      <div class="ttd-nip">NIP. <?= htmlspecialchars($kepalaUpt['nip'] ?? '19710314 199602 1 002') ?></div>
    </div>

    <div class="ttd-box">
      <div>Kupang, <?= formatTanggalIndo(date('Y-m-d')) ?></div>
      <div><strong><?= htmlspecialchars($pejabatTtd['jabatan'] ?? 'BENDAHARA PENERIMAAN') ?></strong></div>
      <div class="ttd-space"></div>
      <div class="ttd-nama"><?= htmlspecialchars($pejabatTtd['nama_pejabat'] ?? 'Maria Yosefina Bere, SE.') ?></div>
      <div class="ttd-nip"><?= htmlspecialchars($pejabatTtd['pangkat_gol'] ?? 'Penata (III/c)') ?></div>
      <div class="ttd-nip">NIP. <?= htmlspecialchars($pejabatTtd['nip'] ?? '19920815 201701 2 003') ?></div>
    </div>
  </div>

  <div class="print-footnote">
    <span>Dicetak dari JANGKAR NTT pada <?= date('d/m/Y H:i:s') ?></span>
    <span>Dokumen Resmi Badan Pendapatan dan Aset Daerah Provinsi Nusa Tenggara Timur</span>
  </div>
</div>

</body>
</html>
