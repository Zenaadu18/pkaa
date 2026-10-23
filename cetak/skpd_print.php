<?php
// cetak/skpd_print.php - Format Cetak Surat Ketetapan Pajak Daerah (SKPD PKAA) Cetak Miring Sesuai Acuan Resmi
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

checkAuth();

$noSkpd = trim($_GET['no'] ?? '');

if (empty($noSkpd)) {
    die("Nomor SKPD tidak ditentukan.");
}

$stmt = $pdo->prepare("
    SELECT sk.*, s.*, k.*, f.nama_fungsi, f.tarif_persen as tarif_master, j.nama_jenis,
           kb.nama_kabupaten_kota, kb.ibukota,
           p.nama_pejabat as nama_pengesah, p.nip as nip_pengesah, p.pangkat_gol as pangkat_pengesah, p.jabatan as jabatan_pengesah,
           u.nama_lengkap as nama_kasir, u.nip as nip_kasir
    FROM transaksi_skpd sk
    JOIN transaksi_superpkaa s ON sk.no_superpkaa = s.no_superpkaa
    JOIN kendaraan_air k ON sk.no_objek_pajak = k.no_objek_pajak
    JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
    JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
    LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
    LEFT JOIN pejabat p ON sk.pejabat_skpd_id = p.id
    LEFT JOIN users u ON sk.id_petugas_kasir = u.id
    WHERE sk.no_skpd = ?
    LIMIT 1
");
$stmt->execute([$noSkpd]);
$data = $stmt->fetch();

if (!$data) {
    die("Data SKPD tidak ditemukan.");
}

// Pejabat Penetapan (Kasi / Penetap)
$pejabatPenetap = $pdo->query("SELECT * FROM pejabat WHERE jabatan LIKE '%Penetapan%' AND is_active = 1 LIMIT 1")->fetch();

$namaInstansi = getSetting($pdo, 'nama_instansi', 'BADAN PENDAPATAN DAN ASET DAERAH PROVINSI NUSA TENGGARA TIMUR');
$namaUpt = getSetting($pdo, 'nama_upt', 'UPTD PENDAPATAN DAERAH WILAYAH KOTA KUPANG');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>SKPD - <?= htmlspecialchars($data['no_skpd']) ?></title>
  <link rel="stylesheet" href="../assets/css/print.css">
  <style>
    @page {
      size: landscape;
      margin: 10mm 15mm 10mm 15mm;
    }
    body.print-document {
      font-family: Arial, Helvetica, sans-serif;
      background: #ffffff;
      color: #000000;
      margin: 0;
      padding: 0;
    }
    .skpd-landscape-container {
      width: 100%;
      max-width: 270mm;
      margin: 0 auto;
      padding: 5px;
    }
    .skpd-main-title {
      font-size: 16pt;
      font-weight: 800;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
      color: #000000;
    }
    .skpd-top-box {
      display: flex;
      border: 2px solid #000000;
      margin-bottom: 16px;
    }
    .skpd-black-header {
      background: #000000;
      color: #ffffff;
      width: 230px;
      padding: 8px 14px;
      font-weight: 800;
      font-size: 11pt;
      line-height: 2.1;
      letter-spacing: 0.3px;
    }
    .skpd-white-content {
      flex: 1;
      padding: 8px 14px;
      font-size: 11pt;
      font-weight: 700;
      line-height: 2.1;
      background: #ffffff;
      color: #000000;
      border-left: 2px solid #000000;
    }
    .skpd-two-col {
      display: flex;
      gap: 24px;
      align-items: flex-start;
    }
    .skpd-col-left {
      flex: 1.1;
    }
    .skpd-col-right {
      flex: 1.4;
    }
    .skpd-spec-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11pt;
      font-weight: 700;
      line-height: 2.2;
    }
    .skpd-spec-table td {
      padding: 3px 0;
      vertical-align: top;
    }
    .skpd-spec-label {
      width: 210px;
      letter-spacing: 0.3px;
    }
    .skpd-spec-sep {
      width: 20px;
    }
    .skpd-tax-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10pt;
      border: 1.5px solid #000000;
    }
    .skpd-tax-table th {
      background: #000000;
      color: #ffffff;
      padding: 4px 6px;
      font-weight: 800;
      font-size: 10pt;
      border: 1px solid #000000;
    }
    .skpd-tax-table td {
      border: 1px solid #000000;
      padding: 4px 8px;
      font-size: 10pt;
    }
    .skpd-audit-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 6px;
      border: 1.5px solid #000000;
    }
    .skpd-audit-table th {
      background: #000000;
      color: #ffffff;
      padding: 4px 6px;
      font-weight: 800;
      font-size: 9.5pt;
      text-align: center;
      border: 1px solid #000000;
    }
    .skpd-audit-table td {
      border: 1px solid #000000;
      padding: 5px 8px;
      font-size: 9.5pt;
      text-align: center;
      font-weight: 600;
    }
    .skpd-sign-wrapper {
      margin-top: 30px;
      display: flex;
      justify-content: flex-end;
    }
    .skpd-sign-box {
      text-align: center;
      width: 440px;
    }
  </style>
</head>
<body class="print-document">

<!-- Toolbar Cetak di Layar -->
<div class="print-toolbar">
  <div>
    <strong>JANGKAR NTT</strong> &bull; Cetak Miring Surat Ketetapan Pajak Daerah (SKPD PKAA)
  </div>
  <div style="display:flex; gap:10px;">
    <button type="button" class="btn-print" onclick="window.print()">
      CETAK SKPD LANDSCAPE (PRINT)
    </button>
    <button type="button" style="background:#475569; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;" onclick="window.close()">
      Tutup
    </button>
  </div>
</div>

<div class="skpd-landscape-container">
  <!-- Judul Dokumen Sesuai Acuan Gambar -->
  <div class="skpd-main-title">SURAT KETETAPAN PAJAK DAERAH</div>

  <!-- Kotak Header Atas: NOPD / NAMA PEMILIK / PELABUHAN PANGKAL -->
  <div class="skpd-top-box">
    <div class="skpd-black-header">
      NOPD<br>
      NAMA PEMILIK<br>
      PELABUHAN PANGKAL
    </div>
    <div class="skpd-white-content">
      <?= htmlspecialchars($data['no_objek_pajak']) ?><br>
      <?= htmlspecialchars($data['nama_pemilik']) ?><br>
      <?= strtoupper(htmlspecialchars($data['pelabuhan_pangkal'])) ?> (<?= strtoupper(htmlspecialchars($data['nama_kabupaten_kota'] ?? 'PROVINSI NUSA TENGGARA TIMUR')) ?>)
    </div>
  </div>

  <!-- Kolom Kiri & Kolom Kanan Sesuai Gambar -->
  <div class="skpd-two-col">
    <!-- Kolom Kiri: Spesifikasi Teknis Kapal -->
    <div class="skpd-col-left">
      <table class="skpd-spec-table">
        <tr>
          <td class="skpd-spec-label">TAHUN PEMBUATAN</td>
          <td class="skpd-spec-sep">:</td>
          <td><?= $data['tahun_buatan'] ?></td>
        </tr>
        <tr>
          <td class="skpd-spec-label">GROSS TONNAGE</td>
          <td class="skpd-spec-sep">:</td>
          <td><?= htmlspecialchars($data['kapasitas_gt']) ?> GT</td>
        </tr>
        <tr>
          <td class="skpd-spec-label">JUMLAH MESIN</td>
          <td class="skpd-spec-sep">:</td>
          <td><?= htmlspecialchars($data['jumlah_mesin']) ?></td>
        </tr>
        <tr>
          <td class="skpd-spec-label">TANDA SELAR</td>
          <td class="skpd-spec-sep">:</td>
          <td><?= htmlspecialchars($data['tanda_selar'] ?: '-') ?></td>
        </tr>
        <tr>
          <td class="skpd-spec-label">BERLAKU SAMPAI</td>
          <td class="skpd-spec-sep">:</td>
          <td><?= formatTanggalIndo($data['masa_pajak_akhir']) ?></td>
        </tr>
      </table>
    </div>

    <!-- Kolom Kanan: Tabel Penetapan Pajak & Pejabat -->
    <div class="skpd-col-right">
      <!-- Tabel Pajak -->
      <table class="skpd-tax-table">
        <thead>
          <tr>
            <th colspan="3" style="text-align: center; letter-spacing: 0.5px;">
              JUMLAH YANG HARUS DIBAYAR (Rupiah)
            </th>
            <th rowspan="2" style="width: 120px; text-align: left; padding-left: 10px;"></th>
          </tr>
          <tr>
            <th style="width: 120px; text-align: center;">POKOK</th>
            <th style="width: 110px; text-align: center;">SANKSI ADM</th>
            <th style="width: 130px; text-align: center;">JUMLAH</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td align="right">-</td>
            <td align="right">-</td>
            <td align="right">-</td>
            <td style="font-weight: 800; padding-left: 10px;">BBNKB</td>
          </tr>
          <tr>
            <td align="right"><?= number_format($data['bea_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($data['bea_pkaa_denda_bayar'], 0, ',', '.') ?></td>
            <td align="right" style="font-weight: 800;"><?= number_format($data['bea_pkaa_pokok_bayar'] + $data['bea_pkaa_denda_bayar'], 0, ',', '.') ?></td>
            <td style="font-weight: 800; padding-left: 10px;">PKB</td>
          </tr>
          <tr>
            <td align="right">-</td>
            <td align="right">-</td>
            <td align="right">-</td>
            <td style="font-weight: 800; padding-left: 10px;">OPSEN BBNKB</td>
          </tr>
          <tr>
            <td align="right"><?= number_format($data['opsen_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($data['opsen_pkaa_denda_bayar'], 0, ',', '.') ?></td>
            <td align="right" style="font-weight: 800;"><?= number_format($data['opsen_pkaa_pokok_bayar'] + $data['opsen_pkaa_denda_bayar'], 0, ',', '.') ?></td>
            <td style="font-weight: 800; padding-left: 10px;">OPSEN PKB</td>
          </tr>
          <tr style="font-weight: 800;">
            <td align="right"><?= number_format($data['bea_pkaa_pokok_bayar'] + $data['opsen_pkaa_pokok_bayar'], 0, ',', '.') ?></td>
            <td align="right"><?= number_format($data['bea_pkaa_denda_bayar'] + $data['opsen_pkaa_denda_bayar'], 0, ',', '.') ?></td>
            <td align="right" style="font-size: 11pt;"><?= number_format($data['total_bayar'], 0, ',', '.') ?></td>
            <td style="font-weight: 800; padding-left: 10px;">JUMLAH</td>
          </tr>
        </tbody>
      </table>

      <!-- Tabel Pejabat Penetapan & Kasir Sesuai Gambar -->
      <table class="skpd-audit-table">
        <thead>
          <tr>
            <th style="width: 32%;">DITETAPKAN TGL</th>
            <th style="width: 38%;">PETUGAS PENETAPAN</th>
            <th style="width: 30%;">KASIR</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= date('d/m/Y', strtotime($data['tgl_bayar'])) ?></td>
            <td><?= htmlspecialchars($pejabatPenetap['nama_pejabat'] ?? 'Fransiskus Xaverius, SE., MM.') ?></td>
            <td><?= htmlspecialchars($data['nama_kasir'] ?? 'Maria Yosefina Bere, SE.') ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Kolom Pengesahan Tanda Tangan Sesuai Gambar -->
  <div class="skpd-sign-wrapper">
    <div class="skpd-sign-box">
      <div style="font-size: 11pt; font-weight: 800; letter-spacing: 0.3px;">KEPALA UPTD PENDAPATAN DAERAH</div>
      <div style="font-size: 10pt; font-weight: 800; letter-spacing: 0.3px;">ASAL KENDARAAN</div>
      <div style="font-size: 10pt; font-weight: 800; margin-bottom: 55px; letter-spacing: 0.3px;">
        WILAYAH <?= strtoupper(htmlspecialchars($data['nama_kabupaten_kota'] ?? 'KOTA KUPANG')) ?>
      </div>
      <div style="font-size: 11.5pt; font-weight: 800; text-decoration: underline;">
        <?= htmlspecialchars($data['nama_pengesah'] ?? 'Drs. Alex Lakamana, M.Si.') ?>
      </div>
      <div style="font-size: 9.5pt; margin-top: 3px;">
        NIP. <?= htmlspecialchars($data['nip_pengesah'] ?? '19710314 199602 1 002') ?>
      </div>
    </div>
  </div>

  <div style="font-size: 8pt; color: #666; margin-top: 20px; display: flex; justify-content: space-between; border-top: 1px dashed #999; padding-top: 4px;">
    <span>SKPD PKAA &bull; No: <?= htmlspecialchars($data['no_skpd']) ?> &bull; Ref SUPER: <?= htmlspecialchars($data['no_superpkaa']) ?> &bull; JANGKAR NTT BPAD Prov. NTT</span>
    <span>Tanggal Cetak: <?= date('d/m/Y H:i:s') ?></span>
  </div>
</div>

</body>
</html>
