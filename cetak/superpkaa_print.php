<?php
// cetak/superpkaa_print.php - Format Cetak Lembar Resmi SUPERPKAA
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/helpers.php';

checkAuth();

$noSuper = trim($_GET['no'] ?? '');

if (empty($noSuper)) {
    die("Nomor SUPERPKAA tidak ditentukan.");
}

$stmt = $pdo->prepare("
    SELECT s.*, k.*, f.nama_fungsi, f.tarif_persen as tarif_master, j.nama_jenis,
           kb.nama_kabupaten_kota, kb.ibukota,
           u.nama_lengkap as nama_petugas
    FROM transaksi_superpkaa s
    JOIN kendaraan_air k ON s.no_objek_pajak = k.no_objek_pajak
    JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
    JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
    LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
    LEFT JOIN users u ON s.id_petugas = u.id
    WHERE s.no_superpkaa = ?
    LIMIT 1
");
$stmt->execute([$noSuper]);
$data = $stmt->fetch();

if (!$data) {
    die("Data SUPERPKAA tidak ditemukan.");
}

// Ambil rincian mesin kapal
$stmtMesin = $pdo->prepare("SELECT * FROM mesin_kendaraan WHERE no_objek_pajak = ? ORDER BY mesin_ke ASC");
$stmtMesin->execute([$data['no_objek_pajak']]);
$mesinList = $stmtMesin->fetchAll();

// Catat log cetak riwayat SUPERPKAA
$currentUser = currentUser();
logCetakSuperPKAA(
    $pdo,
    $data['no_superpkaa'],
    $data['no_objek_pajak'],
    $data['nama_kapal'],
    $data['nama_pemilik'],
    $currentUser['id'] ?? 1,
    $currentUser['nama_lengkap'] ?? 'Petugas Loket BPAD'
);

// Tandai status cetak SUPERPKAA menjadi SUDAH DICETAK agar dapat diteruskan ke Cetak SKPD
try {
    $pdo->prepare("
        UPDATE transaksi_superpkaa 
        SET status_cetak = 1,
            tgl_cetak_pertama = COALESCE(tgl_cetak_pertama, NOW())
        WHERE no_superpkaa = ?
    ")->execute([$data['no_superpkaa']]);

    $pdo->prepare("
        UPDATE kendaraan_air 
        SET status_pajak = CASE WHEN status_pajak = 'LUNAS' THEN 'LUNAS' ELSE 'TERBIT_SUPER' END
        WHERE no_objek_pajak = ?
    ")->execute([$data['no_objek_pajak']]);
} catch (Exception $e) {
    // Abaikan jika gagal update minor
}

// Kepala UPTD
$kepalaUpt = $pdo->query("SELECT * FROM pejabat WHERE tipe_pejabat = 'SKPD' AND is_active = 1 LIMIT 1")->fetch();

$namaInstansi = getSetting($pdo, 'nama_instansi', 'BADAN PENDAPATAN DAN ASET DAERAH PROVINSI NUSA TENGGARA TIMUR');
$namaUpt = getSetting($pdo, 'nama_upt', 'UPTD PENDAPATAN DAERAH WILAYAH KOTA KUPANG');
$alamatKantor = getSetting($pdo, 'alamat_kantor', 'Jl. Polisi Militer No. 1, Kota Kupang, Nusa Tenggara Timur 85111');
$teleponKantor = getSetting($pdo, 'telepon_kantor', '(0380) 833124');
$lokasiCetak = $data['ibukota'] ?? 'Kupang';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>SUPERPKAA - <?= htmlspecialchars($data['no_superpkaa']) ?></title>
  <link rel="stylesheet" href="../assets/css/print.css">
</head>
<body class="print-document">

<!-- Toolbar Layar -->
<div class="print-toolbar">
  <div>
    <strong>JANGKAR NTT</strong> &bull; Cetak Lembar Surat Pemberitahuan Pajak Kendaraan di Atas Air
  </div>
  <div style="display:flex; gap:10px;">
    <button type="button" class="btn-print" onclick="window.print()">
      CETAK DOKUMEN (PRINT)
    </button>
    <button type="button" style="background:#475569; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;" onclick="window.close()">
      Tutup
    </button>
  </div>
</div>

<div class="print-container">
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
    <div class="dokumen-title">SURAT PEMBERITAHUAN PAJAK KENDARAAN DI ATAS AIR</div>
    <div class="dokumen-nomor">NOMOR: <strong><?= htmlspecialchars($data['no_superpkaa']) ?></strong></div>
  </div>

  <!-- Identitas Objek & Wajib Pajak (Sesuai Format Khusus Permintaan Pengguna) -->
  <table class="table-clean" style="margin-bottom: 14px;">
    <tr>
      <td class="label" style="width: 250px;">NOMOR OBJEK PAJAK (NOPD)</td>
      <td class="sep">:</td>
      <td class="val"><strong style="font-size: 13pt; letter-spacing: 1px;"><?= htmlspecialchars($data['no_objek_pajak']) ?></strong></td>
    </tr>
    <tr>
      <td class="label">NAMA KAPAL / PERAHU / SPEED</td>
      <td class="sep">:</td>
      <td class="val"><strong><?= htmlspecialchars($data['nama_kapal']) ?></strong></td>
    </tr>
    <tr>
      <td class="label">TANDA SELAR / NO. PAS</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($data['tanda_selar'] ?: '-') ?></td>
    </tr>
    <tr>
      <td class="label">NO. IJIN OPERASI</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($data['no_ijin_operasi'] ?: '-') ?></td>
    </tr>
    <tr>
      <td class="label">NAMA WAJIB PAJAK / PEMILIK</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($data['nama_pemilik']) ?></td>
    </tr>
    <tr>
      <td class="label">ALAMAT WAJIB PAJAK</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($data['alamat_pemilik']) ?></td>
    </tr>
    <tr>
      <td colspan="3" style="padding: 4px 0;"></td>
    </tr>
    <tr>
      <td class="label">KAPASITAS GROSS TON (GT)</td>
      <td class="sep">:</td>
      <td class="val"><?= htmlspecialchars($data['kapasitas_gt']) ?> GT &bull; Tahun Buatan: <?= $data['tahun_buatan'] ?></td>
    </tr>
    <tr>
      <td class="label">PELABUHAN PANGKAL</td>
      <td class="sep">:</td>
      <td class="val"><?= strtoupper(htmlspecialchars($data['pelabuhan_pangkal'])) ?> (<?= strtoupper(htmlspecialchars($data['nama_kabupaten_kota'] ?? 'PROVINSI NUSA TENGGARA TIMUR')) ?>)</td>
    </tr>
    <tr>
      <td colspan="3" style="padding: 4px 0;"></td>
    </tr>
    <tr>
      <td class="label">MASA BERLAKU PAJAK</td>
      <td class="sep">:</td>
      <td class="val"><?= formatTanggalIndo($data['masa_pajak_awal']) ?> s/d <?= formatTanggalIndo($data['masa_pajak_akhir']) ?></td>
    </tr>
  </table>

  <!-- Rincian Nilai Jual Body & Mesin -->
  <div style="font-size: 10pt; font-weight: bold; margin-top: 10px; margin-bottom: 4px;">
    RINCIAN NILAI JUAL KENDARAAN DI ATAS AIR (NJKB):
  </div>
  <table class="table-cetak">
    <thead>
      <tr>
        <th width="35">NO</th>
        <th>KOMPONEN OBJEK PAJAK</th>
        <th width="100">TAHUN</th>
        <th width="160">NILAI JUAL OBJEK (RP)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (($data['metode_njkb'] ?? 'TERPISAH') === 'GABUNGAN'): ?>
        <tr>
          <td align="center">1</td>
          <td>
            <strong>NJKB Paket 1 Kesatuan (Body Kapal & Seluruh Mesin Penggerak)</strong><br>
            <small style="color:#555;">Penilaian dalam 1 kesatuan harga utuh kendaraan air</small>
          </td>
          <td align="center"><?= $data['tahun_buatan'] ?></td>
          <td align="right"><?= number_format($data['njkb_gabungan'] ?: $data['njkb_dasar'], 0, ',', '.') ?></td>
        </tr>
        <?php if (!empty($mesinList)): ?>
          <?php foreach ($mesinList as $idx => $m): ?>
            <tr style="color: #555; background: #fafafa;">
              <td align="center">-</td>
              <td>
                Mesin Ke-<?= $m['mesin_ke'] ?>: <?= htmlspecialchars($m['merek_mesin'] ?? '-') ?> (<?= htmlspecialchars($m['daya_pk'] ?? '-') ?> PK)
                <br><small>No. Mesin: <?= htmlspecialchars($m['nomor_mesin'] ?: '-') ?></small>
              </td>
              <td align="center"><?= $m['tahun_mesin'] ?></td>
              <td align="right"><em>(Termasuk dlm Paket)</em></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php else: ?>
        <tr>
          <td align="center">1</td>
          <td>Nilai Jual Body Kapal <?= !empty($data['merek_body']) ? '(' . htmlspecialchars($data['merek_body']) . ')' : '' ?></td>
          <td align="center"><?= $data['tahun_buatan'] ?></td>
          <td align="right"><?= number_format($data['nj_body_kapal'], 0, ',', '.') ?></td>
        </tr>
        <?php foreach ($mesinList as $idx => $m): ?>
          <tr>
            <td align="center"><?= $idx + 2 ?></td>
            <td>
              Mesin Penggerak Ke-<?= $m['mesin_ke'] ?>: <?= htmlspecialchars($m['merek_mesin'] ?? '-') ?> (<?= htmlspecialchars($m['daya_pk'] ?? '-') ?> PK)
              <?php if (!empty($m['nomor_mesin'])): ?>
                <br><small style="color:#666;">No. Mesin: <?= htmlspecialchars($m['nomor_mesin']) ?></small>
              <?php endif; ?>
            </td>
            <td align="center"><?= $m['tahun_mesin'] ?></td>
            <td align="right"><?= number_format($m['nilai_jual_mesin'], 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      <tr style="background:#f2f2f2; font-weight:bold;">
        <td colspan="3" align="right">TOTAL NILAI JUAL KENDARAAN (DASAR PENGENAAN NJKB):</td>
        <td align="right"><?= number_format($data['njkb_dasar'], 0, ',', '.') ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Kotak Perhitungan Fiskal Pajak -->
  <div class="box-ketetapan">
    <div class="box-ketetapan-title">BESARAN KETETAPAN PAJAK KENDARAAN DI ATAS AIR (PKAA) & OPSEN:</div>
    <table class="table-clean" style="margin:0;">
      <tr>
        <td class="label">1. Bea PKAA Pokok (Tarif <?= $data['tarif_pokok_persen'] ?>%)</td>
        <td class="sep">:</td>
        <td class="val" align="right"><?= formatRupiah($data['bea_pkaa_pokok']) ?></td>
      </tr>
      <tr>
        <td class="label">2. Sanksi Administrasi / Denda PKAA</td>
        <td class="sep">:</td>
        <td class="val" align="right"><?= formatRupiah($data['bea_pkaa_denda']) ?></td>
      </tr>
      <tr>
        <td class="label">3. Opsen PKAA Pokok (66% dari Bea Pokok)</td>
        <td class="sep">:</td>
        <td class="val" align="right"><?= formatRupiah($data['opsen_pkaa_pokok']) ?></td>
      </tr>
      <?php if ($data['opsen_pkaa_denda'] > 0): ?>
        <tr>
          <td class="label">4. Denda Opsen PKAA (66%)</td>
          <td class="sep">:</td>
          <td class="val" align="right"><?= formatRupiah($data['opsen_pkaa_denda']) ?></td>
        </tr>
      <?php endif; ?>
      <tr style="border-top:1.5px solid #000; font-size:11.5pt;">
        <td class="label" style="font-weight:bold;">JUMLAH YANG HARUS DIBAYAR</td>
        <td class="sep">:</td>
        <td class="val" align="right" style="color:#000; font-size:12pt;"><?= formatRupiah($data['total_ketetapan']) ?></td>
      </tr>
      <tr>
        <td colspan="3" style="font-style:italic; font-size:9.5pt; padding-top:4px;">
          Terbilang: <strong><?= terbilang($data['total_ketetapan']) ?> Rupiah</strong>
        </td>
      </tr>
    </table>
  </div>

  <!-- Catatan Penyetoran -->
  <div style="font-size: 9pt; border: 1px dashed #666; padding: 6px 10px; margin: 8px 0;">
    <strong>INFORMASI PEMBAYARAN:</strong><br>
    Pembayaran dapat disetor melalui Rekening Kas Umum Daerah Provinsi Nusa Tenggara Timur di <strong>Bank NTT</strong> atau loket pembayaran resmi Badan Pendapatan dan Aset Daerah (BPAD) Provinsi NTT / Kantor UPTD terdekat paling lambat 30 hari kalender sejak diterbitkannya surat ketetapan ini.
  </div>

  <!-- Kolom Tanda Tangan -->
  <div class="ttd-wrapper">
    <div class="ttd-box">
      <div>Wajib Pajak / Kuasa,</div>
      <div class="ttd-space"></div>
      <div class="ttd-nama"><?= htmlspecialchars($data['nama_pemilik']) ?></div>
      <div class="ttd-nip">Pemilik / Penanggung Jawab</div>
    </div>

    <div class="ttd-box">
      <div><?= htmlspecialchars($lokasiCetak) ?>, <?= formatTanggalIndo($data['tgl_terbit']) ?></div>
      <div><strong><?= htmlspecialchars($kepalaUpt['jabatan'] ?? 'KEPALA UPTD WILAYAH KOTA KUPANG') ?></strong></div>
      <div class="ttd-space"></div>
      <div class="ttd-nama"><?= htmlspecialchars($kepalaUpt['nama_pejabat'] ?? 'Drs. Alex Lakamana, M.Si.') ?></div>
      <div class="ttd-nip"><?= htmlspecialchars($kepalaUpt['pangkat_gol'] ?? 'Pembina Utama Muda (IV/c)') ?></div>
      <div class="ttd-nip">NIP. <?= htmlspecialchars($kepalaUpt['nip'] ?? '19710314 199602 1 002') ?></div>
    </div>
  </div>

  <div class="print-footnote">
    <span>Lembar 1: Wajib Pajak &bull; Lembar 2: Bank NTT &bull; Lembar 3: BPAD Provinsi NTT</span>
    <span>JANGKAR NTT &bull; Cetak: <?= date('d/m/Y H:i') ?></span>
  </div>
</div>

</body>
</html>
