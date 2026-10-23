<?php
// cetak/opsen_print.php - Format Cetak Lembar Resmi Penerimaan & Bagi Hasil Opsen PKAA 66% Kab/Kota di NTT
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

checkAuth();

$tglAwal = trim($_GET['tgl_awal'] ?? date('Y-m-01'));
$tglAkhir = trim($_GET['tgl_akhir'] ?? date('Y-m-d'));
$filterKab = trim($_GET['kd_wilayah'] ?? '');

$namaFilterWilayah = "SELURUH WILAYAH PROVINSI NTT (22 KABUPATEN / KOTA)";
if (!empty($filterKab)) {
    $stK = $pdo->prepare("SELECT nama_kabupaten_kota FROM ref_kabupaten_kota WHERE kode_wilayah = ? LIMIT 1");
    $stK->execute([$filterKab]);
    $rk = $stK->fetch();
    if ($rk) {
        $namaFilterWilayah = "WILAYAH: [" . $filterKab . "] " . strtoupper($rk['nama_kabupaten_kota']);
    }
}

$namaInstansi = getSetting($pdo, 'nama_instansi', 'BADAN PENDAPATAN DAN ASET DAERAH PROVINSI NUSA TENGGARA TIMUR');
$namaUpt = getSetting($pdo, 'nama_upt', 'UPTD PENDAPATAN DAERAH WILAYAH KOTA KUPANG');
$alamatKantor = getSetting($pdo, 'alamat_kantor', 'Jl. Polisi Militer No. 1, Kota Kupang, Nusa Tenggara Timur 85111');
$teleponKantor = getSetting($pdo, 'telepon_kantor', '(0380) 833124');

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
$opsenData = $stmt->fetchAll();

// Pejabat
$kepalaUpt = $pdo->query("SELECT * FROM pejabat WHERE jabatan LIKE '%Kepala%' AND is_active = 1 LIMIT 1")->fetch();
$pejabatTtd = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'LAPORAN' AND is_active = 1 LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Cetak Lembar Resmi Opsen PKAA - JANGKAR NTT</title>
  <link rel="stylesheet" href="../assets/css/print.css">
  <style>
    @page { size: landscape; margin: 12mm; }
  </style>
</head>
<body class="print-document">

<!-- Toolbar Cetak -->
<div class="print-toolbar">
  <div>
    <strong>JANGKAR NTT</strong> &bull; Cetak Lembar Resmi Rekapitulasi Penerimaan & Bagi Hasil Opsen PKAA (66%)
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
    <img src="../assets/img/logontt.png" class="kop-logo" alt="Logo NTT">
    <h2>PEMERINTAH PROVINSI NUSA TENGGARA TIMUR</h2>
    <h1><?= htmlspecialchars($namaInstansi) ?></h1>
    <h3><?= htmlspecialchars($namaUpt) ?></h3>
    <p><?= htmlspecialchars($alamatKantor) ?> &bull; Telp: <?= htmlspecialchars($teleponKantor) ?></p>
  </div>

  <!-- Judul Dokumen -->
  <div class="dokumen-header">
    <div class="dokumen-title">LAPORAN PENERIMAAN DAN BAGI HASIL OPSEN PAJAK KENDARAAN DI ATAS AIR (66%)</div>
    <div class="dokumen-nomor">
      PERIODE: <?= strtoupper(formatTanggalIndo($tglAwal)) ?> S/D <?= strtoupper(formatTanggalIndo($tglAkhir)) ?> &bull; <?= htmlspecialchars($namaFilterWilayah) ?>
    </div>
  </div>

  <table class="table-cetak">
    <thead>
      <tr>
        <th width="30">NO</th>
        <th width="145">NO. SKPD</th>
        <th width="80">TGL BAYAR</th>
        <th>NOPD (NOPOL KA) / NAMA KAPAL</th>
        <th>KAB/KOTA PENERIMA OPSEN</th>
        <th>NAMA WAJIB PAJAK</th>
        <th>METODE SETOR / RESI</th>
        <th width="115">POKOK OPSEN (66%)</th>
        <th width="105">DENDA OPSEN</th>
        <th width="125">TOTAL OPSEN (RP)</th>
      </tr>
    </thead>
    <tbody>
      <?php 
        $no = 1; $totPokokOpsen = 0; $totDendaOpsen = 0; $totGrand = 0;
      ?>
      <?php foreach ($opsenData as $r): 
        $sub = $r['opsen_pkaa_pokok_bayar'] + $r['opsen_pkaa_denda_bayar'];
        $totPokokOpsen += $r['opsen_pkaa_pokok_bayar'];
        $totDendaOpsen += $r['opsen_pkaa_denda_bayar'];
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
          <td><strong><?= htmlspecialchars($r['nama_kabupaten_kota'] ?? $r['kode_kabkota']) ?></strong></td>
          <td><?= htmlspecialchars($r['nama_pemilik']) ?></td>
          <td><?= htmlspecialchars($r['metode_bayar']) ?> &bull; <small><?= htmlspecialchars($r['no_resi_bukti'] ?: 'Kasda') ?></small></td>
          <td align="right"><?= number_format($r['opsen_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
          <td align="right"><?= number_format($r['opsen_pkaa_denda_bayar'], 0, ',', '.') ?></td>
          <td align="right"><strong><?= number_format($sub, 0, ',', '.') ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <tr style="background:#f2f2f2; font-weight:bold;">
        <td colspan="7" align="right">TOTAL PENERIMAAN OPSEN KABUPATEN / KOTA DI NTT (RP):</td>
        <td align="right"><?= number_format($totPokokOpsen, 0, ',', '.') ?></td>
        <td align="right"><?= number_format($totDendaOpsen, 0, ',', '.') ?></td>
        <td align="right" style="font-size:11pt;"><?= number_format($totGrand, 0, ',', '.') ?></td>
      </tr>
    </tbody>
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
    <span>Lembar Hak Bagi Hasil Opsen PKAA UU HKPD No. 1/2022 &bull; Dicetak dari JANGKAR NTT pada <?= date('d/m/Y H:i:s') ?></span>
    <span>Badan Pendapatan dan Aset Daerah Provinsi Nusa Tenggara Timur</span>
  </div>
</div>

</body>
</html>
