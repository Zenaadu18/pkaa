<?php
// modules/pelayanan/pendaftaran.php - Formulir Pendaftaran Objek & Wajib Pajak KAA JANGKAR NTT
$pageTitle = "Pendaftaran Wajib Pajak KAA";
$currentUser = currentUser();
$userRole = $currentUser['role'] ?? 'OPERATOR';
$isOperator = ($userRole === 'OPERATOR');

// Ambil referensi jenis, fungsi, dan daftar 22 Kabupaten/Kota di NTT
$jenisList = $pdo->query("SELECT * FROM ref_jenis_kendaraan ORDER BY kode_jenis ASC")->fetchAll();
$fungsiList = $pdo->query("SELECT * FROM ref_fungsi_tarif ORDER BY kode_fungsi ASC")->fetchAll();
$kabKotaList = getKabKotaList($pdo);

// Ambil master benchmark NJKB untuk autocomplete & rekomendasi harga
$njkbBodyList = $pdo->query("SELECT merek, tipe_model, tahun_buat, nilai_jual FROM ref_njkb_body ORDER BY merek ASC, tahun_buat DESC")->fetchAll();
$njkbMesinList = $pdo->query("SELECT merek, tipe_tenaga, daya_pk, tahun_buat, nilai_jual FROM ref_njkb_mesin ORDER BY merek ASC, tahun_buat DESC")->fetchAll();
$njkbGabunganList = $pdo->query("SELECT merek_paket, merek_body, merek_mesin, tahun_buat, nilai_jual FROM ref_njkb_gabungan ORDER BY merek_paket ASC, tahun_buat DESC")->fetchAll();

$distinctBodyBrands = $pdo->query("SELECT DISTINCT merek FROM ref_njkb_body ORDER BY merek ASC")->fetchAll(PDO::FETCH_COLUMN);
$distinctMesinBrands = $pdo->query("SELECT DISTINCT merek FROM ref_njkb_mesin ORDER BY merek ASC")->fetchAll(PDO::FETCH_COLUMN);
$distinctGabunganPackages = $pdo->query("SELECT DISTINCT merek_paket FROM ref_njkb_gabungan ORDER BY merek_paket ASC")->fetchAll(PDO::FETCH_COLUMN);

$defaultKab = 'KP';
$autoNOPDParts = generateNextNOPDParts($pdo, $defaultKab);
$allNextSequences = getAllNextNOPDMap($pdo);

$savedNopd = '';
$isSavedSuccess = false;

// Proses Simpan Pendaftaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'simpan_pendaftaran') {
    $kodeKabKota = strtoupper(trim($_POST['kode_kabkota'] ?? 'KP'));
    $nopd = strtoupper(trim($_POST['no_objek_pajak'] ?? ''));
    $noIjin = trim($_POST['no_ijin_operasi'] ?? '');
    $tandaSelar = trim($_POST['tanda_selar'] ?? '');
    $namaKapal = trim($_POST['nama_kapal'] ?? '');
    $bahanKapal = trim($_POST['bahan_kapal'] ?? 'FIBER');
    $namaPemilik = trim($_POST['nama_pemilik'] ?? '');
    $alamatPemilik = trim($_POST['alamat_pemilik'] ?? '');
    $noHp = trim($_POST['no_hp'] ?? '');
    $kodeJenis = trim($_POST['kode_jenis'] ?? '01');
    $kodeFungsi = trim($_POST['kode_fungsi'] ?? '2');
    $tahunBuatan = (int)($_POST['tahun_buatan'] ?? date('Y'));
    $kapasitasGt = (float)($_POST['kapasitas_gt'] ?? 0);
    $pelabuhanPangkal = trim($_POST['pelabuhan_pangkal'] ?? 'TENAU KUPANG');
    $tgAkhirPajak = trim($_POST['tg_akhir_pajak'] ?? date('Y-m-d', strtotime('+1 year')));
    $jumlahMesin = max(1, min(6, (int)($_POST['jumlah_mesin'] ?? 1)));
    
    // Validasi data penting (Required)
    if (empty($nopd) || empty($namaKapal) || empty($namaPemilik) || empty($alamatPemilik) || empty($pelabuhanPangkal) || $tahunBuatan < 1950) {
        setFlash('warning', 'Peringatan Validasi', 'Mohon lengkapi seluruh isian penting bertanda bintang (*), termasuk NOPD, Nama Kapal, Nama Pemilik, Alamat, dan Pelabuhan Pangkal!');
    } else {
        // Pilihan Metode NJKB (Terpisah vs 1 Kesatuan)
        $metodeNjkb = trim($_POST['metode_njkb'] ?? 'TERPISAH');
        
        $merekBody = null;
        $tahunBody = null;
        $njBodyKapal = 0.00;
        
        $merekMesinUtama = null;
        $tahunMesinUtama = null;
        $totalNjkbMesin = 0.00;
        
        $merekGabungan = null;
        $njkbGabungan = 0.00;
        $totalNjkb = 0.00;
        $mesinData = [];
        $statusVerifikasi = 'MENUNGGU_VERIFIKASI';
        $isAutoAcc = false;

        // EVALUASI ATURAN NJKB:
        // Jika data kendaraan baru sudah memiliki nilai di database acuan NJKB,
        // maka otomatis di-ACC (status: VERIFIKASI) tanpa perlu persetujuan admin lagi.
        // Jika belum ada di database, ajukan ke panel admin induk (status: MENUNGGU_VERIFIKASI).
        if ($metodeNjkb === 'GABUNGAN') {
            $merekGabungan = trim($_POST['merek_gabungan'] ?? '');
            $jumlahMesin = 0; // Untuk metode 1 harga gabungan, rincian mesin dipisah tidak dihitung
            
            $stmtCekG = $pdo->prepare("SELECT * FROM ref_njkb_gabungan WHERE LOWER(TRIM(merek_paket)) = LOWER(TRIM(?)) LIMIT 1");
            $stmtCekG->execute([$merekGabungan]);
            $rowGab = $stmtCekG->fetch();

            if ($rowGab && (float)$rowGab['nilai_jual'] > 0) {
                // Ada di database acuan NJKB -> AUTO ACC!
                $isAutoAcc = true;
                $statusVerifikasi = 'VERIFIKASI';
                $njkbGabungan = (float)$rowGab['nilai_jual'];
                $totalNjkb = $njkbGabungan;
            } else {
                // Belum ada di database -> Ajukan ke Admin
                $statusVerifikasi = 'MENUNGGU_VERIFIKASI';
                // Jika input manual oleh admin
                if (!$isOperator && isset($_POST['njkb_gabungan']) && (float)$_POST['njkb_gabungan'] > 0) {
                    $njkbGabungan = (float)$_POST['njkb_gabungan'];
                    $totalNjkb = $njkbGabungan;
                } else {
                    $njkbGabungan = 0.00;
                    $totalNjkb = 0.00;
                }
            }

            // Pastikan data rincian mesin BENAR-BENAR KOSONG dan TIDAK dimasukkan ke database
            $mesinData = [];
        } else {
            // Metode Terpisah: NJKB Gabungan dikosongkan
            $merekGabungan = null;
            $njkbGabungan = 0.00;
            $merekBody = trim($_POST['merek_body'] ?? '');
            $tahunBody = (int)($_POST['tahun_body'] ?? $tahunBuatan);
            $stmtCekB = $pdo->prepare("SELECT * FROM ref_njkb_body WHERE LOWER(TRIM(merek)) = LOWER(TRIM(?)) LIMIT 1");
            $stmtCekB->execute([$merekBody]);
            $rowBody = $stmtCekB->fetch();
            $bodyAdaDiDb = ($rowBody && (float)$rowBody['nilai_jual'] > 0);

            $semuaMesinAdaDiDb = true;
            $totalMesinHitung = 0;

            for ($i = 1; $i <= $jumlahMesin; $i++) {
                $mrk = trim($_POST['merek_mesin_' . $i] ?? '');
                $no = trim($_POST['nomor_mesin_' . $i] ?? '');
                $daya = (int)($_POST['daya_pk_' . $i] ?? 0);
                $thn = (int)($_POST['tahun_mesin_' . $i] ?? $tahunBuatan);

                $stmtCekM = $pdo->prepare("SELECT * FROM ref_njkb_mesin WHERE LOWER(TRIM(merek)) = LOWER(TRIM(?)) LIMIT 1");
                $stmtCekM->execute([$mrk]);
                $rowM = $stmtCekM->fetch();

                $valM = 0;
                if ($rowM && (float)$rowM['nilai_jual'] > 0) {
                    $valM = (float)$rowM['nilai_jual'];
                } else {
                    $semuaMesinAdaDiDb = false;
                }

                if ($i === 1) {
                    $merekMesinUtama = $mrk;
                    $tahunMesinUtama = $thn;
                }
                $totalMesinHitung += $valM;

                $mesinData[] = [
                    'mesin_ke' => $i,
                    'merek_mesin' => $mrk,
                    'nomor_mesin' => $no,
                    'daya_pk' => $daya,
                    'tahun_mesin' => $thn,
                    'nilai_jual_mesin' => $valM
                ];
            }

            if ($bodyAdaDiDb && $semuaMesinAdaDiDb) {
                // Semua ada di database acuan NJKB -> AUTO ACC!
                $isAutoAcc = true;
                $statusVerifikasi = 'VERIFIKASI';
                $njBodyKapal = (float)$rowBody['nilai_jual'];
                $totalNjkbMesin = $totalMesinHitung;
                $totalNjkb = $njBodyKapal + $totalNjkbMesin;
            } else {
                // Belum lengkap di database -> Ajukan ke Admin
                $statusVerifikasi = 'MENUNGGU_VERIFIKASI';
                $njBodyKapal = 0;
                $totalNjkbMesin = 0;
                $totalNjkb = 0;
                for ($k = 0; $k < count($mesinData); $k++) {
                    $mesinData[$k]['nilai_jual_mesin'] = 0.00;
                }
            }
        }

        try {
            $pdo->beginTransaction();

            // Insert atau Update Kendaraan Air
            $stmt = $pdo->prepare("
                INSERT INTO kendaraan_air (
                    no_objek_pajak, kode_kabkota, no_ijin_operasi, tanda_selar, nama_kapal, bahan_kapal,
                    nama_pemilik, alamat_pemilik, no_hp, kode_jenis, kode_fungsi,
                    jumlah_mesin, metode_njkb, 
                    merek_body, tahun_body, nj_body_kapal,
                    merek_mesin, tahun_mesin_utama, total_njkb_mesin,
                    merek_gabungan, njkb_gabungan, total_njkb,
                    tahun_buatan, kapasitas_gt, pelabuhan_pangkal, tg_akhir_pajak, status_pajak,
                    status_verifikasi, didaftarkan_oleh
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, 'TERDAFTAR',
                    ?, ?
                ) ON DUPLICATE KEY UPDATE
                    kode_kabkota = VALUES(kode_kabkota),
                    no_ijin_operasi = VALUES(no_ijin_operasi),
                    tanda_selar = VALUES(tanda_selar),
                    nama_kapal = VALUES(nama_kapal),
                    bahan_kapal = VALUES(bahan_kapal),
                    nama_pemilik = VALUES(nama_pemilik),
                    alamat_pemilik = VALUES(alamat_pemilik),
                    no_hp = VALUES(no_hp),
                    kode_jenis = VALUES(kode_jenis),
                    kode_fungsi = VALUES(kode_fungsi),
                    jumlah_mesin = VALUES(jumlah_mesin),
                    metode_njkb = VALUES(metode_njkb),
                    merek_body = VALUES(merek_body),
                    tahun_body = VALUES(tahun_body),
                    nj_body_kapal = VALUES(nj_body_kapal),
                    merek_mesin = VALUES(merek_mesin),
                    tahun_mesin_utama = VALUES(tahun_mesin_utama),
                    total_njkb_mesin = VALUES(total_njkb_mesin),
                    merek_gabungan = VALUES(merek_gabungan),
                    njkb_gabungan = VALUES(njkb_gabungan),
                    total_njkb = VALUES(total_njkb),
                    tahun_buatan = VALUES(tahun_buatan),
                    kapasitas_gt = VALUES(kapasitas_gt),
                    pelabuhan_pangkal = VALUES(pelabuhan_pangkal),
                    tg_akhir_pajak = VALUES(tg_akhir_pajak),
                    status_verifikasi = VALUES(status_verifikasi)
            ");

            $stmt->execute([
                $nopd, $kodeKabKota, $noIjin, $tandaSelar, $namaKapal, $bahanKapal,
                $namaPemilik, $alamatPemilik, $noHp, $kodeJenis, $kodeFungsi,
                $jumlahMesin, $metodeNjkb,
                $merekBody, $tahunBody, $njBodyKapal,
                $merekMesinUtama, $tahunMesinUtama, $totalNjkbMesin,
                $merekGabungan, $njkbGabungan, $totalNjkb,
                $tahunBuatan, $kapasitasGt, $pelabuhanPangkal, $tgAkhirPajak,
                $statusVerifikasi, $currentUser['id'] ?? 1
            ]);

            // Hapus mesin lama dan masukkan mesin baru hanya jika metode TERPISAH
            $delMesin = $pdo->prepare("DELETE FROM mesin_kendaraan WHERE no_objek_pajak = ?");
            $delMesin->execute([$nopd]);

            if ($metodeNjkb === 'TERPISAH' && !empty($mesinData)) {
                $insMesin = $pdo->prepare("
                    INSERT INTO mesin_kendaraan (no_objek_pajak, mesin_ke, merek_mesin, nomor_mesin, daya_pk, tahun_mesin, nilai_jual_mesin)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($mesinData as $m) {
                    $insMesin->execute([
                        $nopd, $m['mesin_ke'], $m['merek_mesin'], $m['nomor_mesin'], $m['daya_pk'], $m['tahun_mesin'], $m['nilai_jual_mesin']
                    ]);
                }
            }

            $noSuperGenerated = '';

            // JIKA AUTO-ACC (VERIFIKASI): Langsung buat transaksi SUPERPKAA agar seketika muncul di Cetak SUPERPKAA
            if ($isAutoAcc && $totalNjkb > 0) {
                $noSuperGenerated = generateNextSUPER($pdo);
                $pajak = kalkulasiPajakPKAA($pdo, $totalNjkb, $kodeFungsi, $tgAkhirPajak, date('Y-m-d'));

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
                    $noSuperGenerated, $nopd, date('Y-m-d'), date('Y-m-d'), date('Y-m-d', strtotime('+1 year')),
                    $pajak['njkb_dasar'], $pajak['tarif_pokok_persen'], $pajak['bea_pkaa_pokok'], $pajak['bea_pkaa_denda'],
                    $pajak['opsen_persen'], $pajak['opsen_pkaa_pokok'], $pajak['opsen_pkaa_denda'], $pajak['total_ketetapan'],
                    $currentUser['id'] ?? 1
                ]);
            }

            $pdo->commit();

            // REDIRECT AGAR FORM DI-RESET BERSIH SECARA ALAMI DAN NOPD BARU TERGENERATE (BEBAS DARI WARNING HEADER)
            if ($isAutoAcc) {
                setFlash('success', 'Pendaftaran Berhasil (AUTO-ACC)!', "Kapal <strong>$nopd</strong> ($namaKapal) otomatis disetujui karena merek terdaftar di database NJKB. Dokumen SUPERPKAA <strong>$noSuperGenerated</strong> telah siap dicetak!");
                safeRedirect("index.php?page=pendaftaran&success=auto_acc&nopd=" . urlencode($nopd) . "&nama=" . urlencode($namaKapal) . "&super=" . urlencode($noSuperGenerated));
            } else {
                setFlash('info', 'Pendaftaran Disimpan (Menunggu Admin)!', "Kapal <strong>$nopd</strong> ($namaKapal) berhasil disimpan. Karena merek belum ada di tabel standar NJKB, berkas diajukan ke Admin Induk untuk penetapan nilai jual.");
                safeRedirect("index.php?page=pendaftaran&success=pending&nopd=" . urlencode($nopd) . "&nama=" . urlencode($namaKapal));
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Gagal Menyimpan!', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }
}
?>

<!-- Header Halaman -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-icon">
      <?= renderIcon('ship', '', 20) ?>
    </div>
    <div>
      <div class="page-title">Pendaftaran Objek & Wajib Pajak Kendaraan di Atas Air</div>
      <div class="page-subtitle">Perekaman NOPD Otomatis Terkunci & Integrasi Standar Merek Nilai Jual (NJKB) JANGKAR NTT</div>
    </div>
  </div>
  <div class="page-header-actions">
    <button type="button" class="btn btn-secondary btn-sm" onclick="bukaLayarBantu()">
      <?= renderIcon('search', '', 14) ?> Layar Bantu NOPD (Ctrl+B)
    </button>
  </div>
</div>

<?php
$successType = $_GET['success'] ?? '';
$successNopd = $_GET['nopd'] ?? '';
$successNama = $_GET['nama'] ?? '';
$successSuper = $_GET['super'] ?? '';
?>

<?php if ($successType === 'auto_acc'): ?>
  <div class="panel" style="background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 2px solid #10b981; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);">
    <div class="panel-body" style="padding: 18px 24px;">
      <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <div style="display: flex; gap: 16px;">
          <div style="background: #10b981; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);">
            <?= renderIcon('check-circle', '', 26) ?>
          </div>
          <div>
            <div style="font-size: 16px; font-weight: 800; color: #065f46; margin-bottom: 4px;">
              PENDAFTARAN BERHASIL & DI-ACC OTOMATIS (AUTO-ACC)!
            </div>
            <div style="font-size: 13px; color: #047857; margin-bottom: 8px;">
              Data kapal <strong><?= htmlspecialchars($successNama) ?></strong> dengan NOPD <strong><?= htmlspecialchars($successNopd) ?></strong> berhasil disimpan dan disetujui otomatis karena merek telah terdaftar di basis data standar NJKB.
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
              <span class="badge badge-success" style="font-size: 12px; padding: 4px 10px;">
                <?= renderIcon('file-text', '', 12) ?> No. SUPERPKAA: <?= htmlspecialchars($successSuper) ?>
              </span>
              <span style="font-size: 12px; color: #047857;">&bull; Status: Dokumen Masuk Antrean Siap Cetak SUPERPKAA</span>
            </div>
          </div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-end;">
          <?php if (!empty($successSuper)): ?>
            <a href="cetak/superpkaa_print.php?no=<?= urlencode($successSuper) ?>" target="_blank" class="btn btn-accent" style="background: #059669; border-color: #047857; color: #fff; font-weight: 700; padding: 10px 18px; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);">
              <?= renderIcon('printer', '', 16) ?> CETAK SUPERPKAA SEKARANG
            </a>
          <?php endif; ?>
          <a href="index.php?page=cetak_ulang_superpkaa" class="btn btn-secondary btn-sm">
            <?= renderIcon('file-text', '', 13) ?> Buka Menu Cetak SUPERPKAA &rarr;
          </a>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($successType === 'pending'): ?>
  <div class="panel" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 2px solid #f59e0b; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);">
    <div class="panel-body" style="padding: 18px 24px;">
      <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
        <div style="display: flex; gap: 16px;">
          <div style="background: #f59e0b; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);">
            <?= renderIcon('clock', '', 26) ?>
          </div>
          <div>
            <div style="font-size: 16px; font-weight: 800; color: #92400e; margin-bottom: 4px;">
              PENDAFTARAN DISIMPAN — MENUNGGU PERSETUJUAN & PENETAPAN NJKB ADMIN!
            </div>
            <div style="font-size: 13px; color: #b45309; margin-bottom: 8px;">
              Data kapal <strong><?= htmlspecialchars($successNama) ?></strong> (NOPD: <strong><?= htmlspecialchars($successNopd) ?></strong>) berhasil direkam. Karena merek/pabrikan belum ada di basis data standar NJKB, berkas diajukan ke Admin Induk untuk peninjauan fisik dan pengesahan nilai jual.
            </div>
            <div style="font-size: 12px; color: #92400e;">
              Setelah Admin Induk menetapkan NJKB, dokumen SUPERPKAA akan otomatis terbit dan muncul di menu <strong>Cetak SUPERPKAA</strong>.
            </div>
          </div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-end;">
          <?php if (!$isOperator): ?>
            <a href="index.php?page=verifikasi_perubahan" class="btn btn-warning" style="background: #d97706; border-color: #b45309; color: #fff; font-weight: 700; padding: 10px 18px;">
              <?= renderIcon('shield-check', '', 16) ?> Buka Verifikasi Admin Induk &rarr;
            </a>
          <?php else: ?>
            <a href="index.php?page=status_pengajuan" class="btn btn-warning" style="background: #d97706; border-color: #b45309; color: #fff; font-weight: 700; padding: 10px 18px;">
              <?= renderIcon('file-clock', '', 16) ?> Lihat Status Pengajuan &rarr;
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($isOperator): ?>
  <!-- Banner Petunjuk Role Operator -->
  <div class="alert alert-warning" style="border-left: 4px solid #f59e0b; background: #fffbeb; color: #92400e;">
    <?= renderIcon('info', '', 18) ?>
    <div>
      <strong>Peran Operator Pendaftaran:</strong> Anda bertugas memasukkan data administratif fisik kapal, tanda selar, dan wajib pajak. Bagian nilai jual (NJKB) dan rincian rupiah mesin bersifat otomatis jika merek ada di database; jika belum ada, akan diajukan ke Tim Penilai / Admin Induk.
    </div>
  </div>
<?php endif; ?>

<!-- Datalist Rekomendasi Merek dari Database NJKB -->
<datalist id="list_merek_body">
  <?php foreach ($distinctBodyBrands as $br): ?>
    <option value="<?= htmlspecialchars($br) ?>">
  <?php endforeach; ?>
</datalist>

<datalist id="list_merek_mesin">
  <?php foreach ($distinctMesinBrands as $mr): ?>
    <option value="<?= htmlspecialchars($mr) ?>">
  <?php endforeach; ?>
</datalist>

<datalist id="list_merek_gabungan">
  <?php foreach ($distinctGabunganPackages as $pk): ?>
    <option value="<?= htmlspecialchars($pk) ?>">
  <?php endforeach; ?>
</datalist>

<!-- Formulir Pendaftaran -->
<form method="POST" action="index.php?page=pendaftaran" class="panel" id="formPendaftaran">
  <input type="hidden" name="aksi" value="simpan_pendaftaran">
  
  <div class="panel-header">
    <div class="panel-title">
      <?= renderIcon('ship', '', 16) ?>
      <span>IDENTITAS KENDARAAN AIR & ASAL WILAYAH KABUPATEN/KOTA DI NTT</span>
    </div>
    <span class="badge badge-info">Sistem JANGKAR NTT</span>
  </div>

  <div class="panel-body">
    <!-- Grid 2 Kolom: Identitas Objek & Wilayah -->
    <div class="form-grid-2">
      <!-- Kolom Kiri: NOPD Terpisah 3 Kotak & Asal Wilayah -->
      <div>
        <!-- PILIHAN KABUPATEN / KOTA -->
        <div class="form-group">
          <label class="form-label" for="kode_kabkota">
            Asal Kabupaten / Kota di NTT <span class="required">*</span>
          </label>
          <select name="kode_kabkota" id="kode_kabkota" class="form-control" onchange="onKabKotaChange(this)" required>
            <?php foreach ($kabKotaList as $kb): ?>
              <option value="<?= $kb['kode_wilayah'] ?>" 
                      data-pelabuhan="<?= htmlspecialchars($kb['pelabuhan_pangkal_default']) ?>"
                      <?= $defaultKab === $kb['kode_wilayah'] ? 'selected' : '' ?>>
                [<?= $kb['kode_wilayah'] ?>] <?= htmlspecialchars($kb['nama_kabupaten_kota']) ?> &mdash; Pelabuhan Default: <?= htmlspecialchars($kb['pelabuhan_pangkal_default']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span style="font-size: 11px; color: #64748b; margin-top: 3px; display: block;">
            Memilih wilayah akan otomatis menentukan nomor urut objek terbaru di Kabupaten/Kota tersebut.
          </span>
        </div>

        <!-- 3 KOTAK TERPISAH NOMOR OBJEK PAJAK (NOPD) NON-EDITABLE -->
        <div class="form-group">
          <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
            <span>Nomor Objek Pajak (NOPD) Otomatis <span class="required">*</span></span>
            <span class="badge badge-neutral" style="font-size:10px;">Terkunci Sesuai Nomor Urut</span>
          </label>
          
          <div class="nopd-box-container">
            <!-- Kotak 1: Kode Awalan KA -->
            <div class="nopd-box">
              <label>KODE AWAL</label>
              <input type="text" id="nopd_prefix" class="form-control" value="<?= htmlspecialchars($autoNOPDParts['prefix']) ?>" readonly tabindex="-1">
            </div>

            <div class="nopd-divider">&bull;</div>

            <!-- Kotak 2: Nomor Urut 4 Digit Terakhir -->
            <div class="nopd-box" style="flex: 1.4;">
              <label>NOMOR URUT</label>
              <input type="text" id="nopd_seq" class="form-control" value="<?= htmlspecialchars($autoNOPDParts['sequence']) ?>" readonly tabindex="-1">
            </div>

            <div class="nopd-divider">&bull;</div>

            <!-- Kotak 3: Kode Wilayah 2 Huruf -->
            <div class="nopd-box">
              <label>KODE WILAYAH</label>
              <input type="text" id="nopd_region" class="form-control" value="<?= htmlspecialchars($autoNOPDParts['region']) ?>" readonly tabindex="-1">
            </div>
          </div>

          <!-- Hidden Input untuk Nilai Lengkap NOPD yang Dikirim ke Database -->
          <input type="hidden" name="no_objek_pajak" id="no_objek_pajak" value="<?= htmlspecialchars($autoNOPDParts['full']) ?>">
          <span style="font-size: 11px; color: #0284c7; margin-top: 3px; display: block; font-weight:600;">
            Format Resmi: KA [Nomor Urut 4 Digit] [Kode Wilayah] &mdash; Tidak Dapat Diedit Manual
          </span>
        </div>

        <!-- NOMOR IJIN OPERASI -->
        <div class="form-group">
          <label class="form-label" for="no_ijin_operasi">No. Ijin Operasi / Izin Trayek <span class="required">*</span></label>
          <input type="text" name="no_ijin_operasi" id="no_ijin_operasi" class="form-control" placeholder="Contoh: 503/088/DISHUB-MABAR/2026" required value="">
        </div>

        <!-- TANDA SELAR / NO PAS -->
        <div class="form-group">
          <label class="form-label" for="tanda_selar">Tanda Selar / No. Pas Kapal <span class="required">*</span></label>
          <input type="text" name="tanda_selar" id="tanda_selar" class="form-control" placeholder="Contoh: GT.28 No. 112/KKa" required value="">
        </div>

        <!-- NAMA KAPAL / SB -->
        <div class="form-group">
          <label class="form-label" for="nama_kapal">Nama Kapal / Perahu / Speedboat <span class="required">*</span></label>
          <input type="text" name="nama_kapal" id="nama_kapal" class="form-control" placeholder="Contoh: SB. KOMODO PEARL EXPLORER" required value="">
        </div>

        <!-- BAHAN KAPAL & TAHUN PEMBUATAN -->
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="bahan_kapal">Bahan Konstruksi Kapal</label>
            <select name="bahan_kapal" id="bahan_kapal" class="form-control">
              <option value="FIBER" selected>FIBREGLASS</option>
              <option value="KAYU">KAYU ULIN / JATI BAHARI</option>
              <option value="ALUMINIUM">ALUMINIUM MARINE</option>
              <option value="BAJA">PLAT BAJA (STEEL)</option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="tahun_buatan">Tahun Pembuatan <span class="required">*</span></label>
            <input type="number" name="tahun_buatan" id="tahun_buatan" class="form-control" placeholder="Contoh: <?= date('Y') ?>" min="1950" max="<?= date('Y') + 1 ?>" required onchange="syncTahunBody(this.value)" value="">
          </div>
        </div>
      </div>

      <!-- Kolom Kanan: Identitas Pemilik & Fungsi Pajak -->
      <div>
        <!-- NAMA WAJIB PAJAK / PEMILIK -->
        <div class="form-group">
          <label class="form-label" for="nama_pemilik">Nama Wajib Pajak / Pemilik Kapal <span class="required">*</span></label>
          <input type="text" name="nama_pemilik" id="nama_pemilik" class="form-control" placeholder="Nama perseorangan atau Badan Usaha (PT/CV)" required value="">
        </div>

        <!-- ALAMAT PEMILIK -->
        <div class="form-group">
          <label class="form-label" for="alamat_pemilik">Alamat Lengkap Wajib Pajak <span class="required">*</span></label>
          <textarea name="alamat_pemilik" id="alamat_pemilik" class="form-control" rows="2" placeholder="Alamat jalan, kelurahan, kecamatan, kabupaten di NTT..." required></textarea>
        </div>

        <!-- NO TELEPON / HP -->
        <div class="form-group">
          <label class="form-label" for="no_hp">No. Handphone / WhatsApp</label>
          <input type="text" name="no_hp" id="no_hp" class="form-control" placeholder="Contoh: 081234567890" value="">
        </div>

        <!-- JENIS & FUNGSI KENDARAAN AIR -->
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="kode_jenis">Jenis Kendaraan Air <span class="required">*</span></label>
            <select name="kode_jenis" id="kode_jenis" class="form-control" required>
              <option value="" disabled selected>-- Pilih Jenis --</option>
              <?php foreach ($jenisList as $j): ?>
                <option value="<?= $j['kode_jenis'] ?>">
                  [<?= $j['kode_jenis'] ?>] <?= htmlspecialchars($j['nama_jenis']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="kode_fungsi">Fungsi / Peruntukan <span class="required">*</span></label>
            <select name="kode_fungsi" id="kode_fungsi" class="form-control" onchange="recalculateAllTax()" required>
              <option value="" disabled selected>-- Pilih Fungsi / Tarif --</option>
              <?php foreach ($fungsiList as $f): ?>
                <option value="<?= $f['kode_fungsi'] ?>" data-tarif="<?= $f['tarif_persen'] ?>">
                  [<?= $f['kode_fungsi'] ?>] <?= htmlspecialchars($f['nama_fungsi']) ?> (<?= $f['tarif_persen'] ?>%)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- KAPASITAS GT & PELABUHAN PANGKAL -->
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="kapasitas_gt">Kapasitas (Gross Tonnage / GT) <span class="required">*</span></label>
            <div class="input-group">
              <input type="number" step="0.01" name="kapasitas_gt" id="kapasitas_gt" class="form-control" placeholder="Contoh: 28.00" min="0" required value="">
              <span class="input-prefix" style="border-left:1px solid #cbd5e1; border-right:none;">GT</span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="pelabuhan_pangkal">Pelabuhan Pangkal <span class="required">*</span></label>
            <input type="text" name="pelabuhan_pangkal" id="pelabuhan_pangkal" class="form-control" placeholder="Contoh: LABUAN BAJO" required value="">
          </div>
        </div>

        <!-- MASA BERLAKU PAJAK -->
        <div class="form-group">
          <label class="form-label" for="tg_akhir_pajak">Masa Pajak Berlaku Sampai Dengan <span class="required">*</span></label>
          <input type="date" name="tg_akhir_pajak" id="tg_akhir_pajak" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
        </div>
      </div>
    </div>

    <!-- PENGATURAN NILAI JUAL (NJKB), MEREK, & MESIN -->
    <div style="margin-top: 18px; border-top: 1.5px solid var(--border-medium); padding-top: 14px;">
      <div style="font-weight: 700; font-size: 13px; color: var(--primary-900); margin-bottom: 10px; display:flex; align-items:center; justify-content:space-between;">
        <span style="display:flex; align-items:center; gap:6px;">
          <?= renderIcon('coins', '', 16) ?> PENETAPAN NILAI JUAL KENDARAAN DI ATAS AIR (NJKB & MEREK PABRIKAN)
        </span>
        <a href="index.php?page=tabel_njkb" target="_blank" class="btn btn-secondary btn-sm" style="font-size:11px;">
          <?= renderIcon('database', '', 12) ?> Lihat Database Standar NJKB
        </a>
      </div>

      <div class="form-grid-2">
        <!-- JUMLAH MESIN -->
        <div class="form-group">
          <label class="form-label" for="jumlah_mesin">Jumlah Mesin Penggerak <span class="required">*</span></label>
          <select name="jumlah_mesin" id="jumlah_mesin" class="form-control" onchange="onJumlahMesinChange()">
            <option value="1" selected>1 Mesin</option>
            <option value="2">2 Mesin</option>
            <option value="3">3 Mesin</option>
            <option value="4">4 Mesin</option>
            <option value="5">5 Mesin</option>
            <option value="6">6 Mesin</option>
          </select>
        </div>

          <!-- PILIHAN METODE NJKB -->
          <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 6px; padding: 12px;">
            <div style="font-weight: 700; font-size: 11.5px; color: #92400e; margin-bottom: 8px; text-transform: uppercase;">
              <?= renderIcon('credit-card', '', 14) ?> METODE PENILAIAN NJKB KENDARAAN:
            </div>
            
            <div style="display: flex; gap: 16px; margin-bottom: 8px;">
              <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">
                <input type="radio" name="metode_njkb" value="TERPISAH" id="radio_terpisah" checked onchange="toggleMetodeNjkb()">
                <span>Harga Mesin & Body Sendiri-Sendiri (Terpisah)</span>
              </label>
              <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 12px; font-weight: 600;">
                <input type="radio" name="metode_njkb" value="GABUNGAN" id="radio_gabungan" onchange="toggleMetodeNjkb()">
                <span>Kedua-duanya dalam 1 Harga (1 Kesatuan)</span>
              </label>
            </div>
            <span style="font-size: 11px; color: #78350f;">
              Pilih apakah harga dinilai terpisah (body + tiap mesin) atau merupakan 1 paket harga kesatuan kapal utuh. Jika merek ada di database, sistem langsung mengesahkan (Auto-ACC).
            </span>
          </div>
      </div>

      <!-- FORM 1: NJKB 1 KESATUAN (GABUNGAN) -->
      <div id="section_njkb_gabungan" style="display: none; background: #f8fafc; border: 1.5px solid #0284c7; border-radius: 6px; padding: 14px; margin-top: 10px;">
        <div class="form-grid-2">
          <div class="form-group">
            <label class="form-label" for="merek_gabungan" style="color:#0369a1; font-weight:700;">
              Merek / Nama Paket 1 Kesatuan <span class="required">*</span>
            </label>
            <input type="text" name="merek_gabungan" id="merek_gabungan" list="list_merek_gabungan" class="form-control" placeholder="Pilih atau ketik Merek Paket 1 Kesatuan..." value="" oninput="lookupNjkbGabungan()" onchange="lookupNjkbGabungan()">
            <span id="badge_gabungan_njkb" style="font-size:11px; color:#059669; font-weight:600; margin-top:4px; display:none;"></span>
          </div>
          <div class="form-group">
            <label class="form-label" for="njkb_gabungan" style="color: #0369a1; font-weight:700;">
              Nilai Jual Kendaraan 1 Kesatuan (Body + Seluruh Mesin Paket) (Rp) <span class="required">*</span>
            </label>
            <?php if ($isOperator): ?>
              <div class="form-control" style="background:#f1f5f9; color:#64748b; font-size:12px; font-weight:600; display:flex; align-items:center;">
                [ Otomatis dari Database / Menunggu Verifikasi Admin jika Merek Baru ]
              </div>
              <input type="hidden" name="njkb_gabungan" id="njkb_gabungan" value="0">
            <?php else: ?>
              <div class="input-group">
                <span class="input-prefix">Rp</span>
                <input type="number" name="njkb_gabungan" id="njkb_gabungan" class="form-control" value="" step="1000000" placeholder="0" oninput="recalculateAllTax()">
              </div>
            <?php endif; ?>
          </div>
        </div>
        <span style="font-size: 11px; color: #64748b; display: block;">
          Merek yang dipilih dari tabel NJKB akan langsung otomatis di-ACC. Jika mengetik merek baru di luar database, berkas akan diajukan ke Admin Induk.
        </span>
      </div>

      <!-- FORM 2: NJKB TERPISAH (BODY KAPAL) -->
      <div id="section_njkb_terpisah" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px; margin-top: 10px;">
        <div class="form-grid-3" style="display:grid; grid-template-columns: 1.5fr 1fr 1.5fr; gap:12px;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="merek_body">
              Merek / Pabrikan Body Kapal <span class="required">*</span>
            </label>
            <input type="text" name="merek_body" id="merek_body" list="list_merek_body" class="form-control" placeholder="Pilih atau ketik Merek Body..." value="" oninput="lookupNjkbBody()" onchange="lookupNjkbBody()">
            <span id="badge_body_njkb" style="font-size:11px; color:#059669; font-weight:600; margin-top:3px; display:none;"></span>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="tahun_body">Tahun Body</label>
            <input type="number" name="tahun_body" id="tahun_body" class="form-control" value="" placeholder="Tahun" min="1970" max="<?= date('Y') + 1 ?>" oninput="lookupNjkbBody()" onchange="lookupNjkbBody()">
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="nj_body_kapal">
              Nilai Jual Body Kapal (Tanpa Mesin) (Rp) <span class="required">*</span>
            </label>
            <?php if ($isOperator): ?>
              <div class="form-control" style="background:#f1f5f9; color:#64748b; font-size:12px; font-weight:600; display:flex; align-items:center;">
                [ Otomatis dari Database Standar NJKB ]
              </div>
              <input type="hidden" name="nj_body_kapal" id="nj_body_kapal" value="0">
            <?php else: ?>
              <div class="input-group">
                <span class="input-prefix">Rp</span>
                <input type="number" name="nj_body_kapal" id="nj_body_kapal" class="form-control" value="" placeholder="0" step="1000000" oninput="recalculateAllTax()">
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Sub-Form Rincian Mesin (HANYA DITAMPILKAN JIKA METODE TERPISAH) -->
      <div id="section_mesin_detail" style="margin-top: 14px; border-top: 1px solid var(--border-medium); padding-top: 12px;">
        <div style="font-weight: 700; font-size: 12.5px; color: var(--primary-900); margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
          <span style="display: flex; align-items: center; gap: 6px;">
            <?= renderIcon('cpu', '', 16) ?>
            RINCIAN MEREK, DAYA, & HARGA TIAP MESIN PENGGERAK
          </span>
          <span style="font-size: 11px; font-weight: normal; color: #64748b;">
            Ketik merek mesin untuk memuat standar harga otomatis dari tabel NJKB
          </span>
        </div>

        <div class="table-responsive">
          <table class="table-desktop">
            <thead>
              <tr>
                <th width="100">Mesin Ke</th>
                <th>Merek Pabrikan Mesin</th>
                <th width="180">No. Mesin / Seri</th>
                <th width="130">Daya (PK/HP)</th>
                <th width="130">Tahun Mesin</th>
                <th width="240">Nilai Jual Mesin (Rp)</th>
              </tr>
            </thead>
            <tbody>
              <?php for ($i = 1; $i <= 6; $i++): ?>
                <tr id="row_mesin_<?= $i ?>">
                  <td><strong>Mesin <?= $i ?></strong></td>
                  <td>
                    <input type="text" name="merek_mesin_<?= $i ?>" id="merek_mesin_<?= $i ?>" list="list_merek_mesin" class="form-control" value="" placeholder="Contoh: Yamaha Marine" oninput="lookupNjkbMesin(<?= $i ?>)" onchange="lookupNjkbMesin(<?= $i ?>)">
                    <span id="badge_mesin_njkb_<?= $i ?>" style="font-size:10px; color:#059669; font-weight:600; display:none;"></span>
                  </td>
                  <td>
                    <input type="text" name="nomor_mesin_<?= $i ?>" id="nomor_mesin_<?= $i ?>" class="form-control" placeholder="Contoh: YMH-250-<?= $i ?>" value="">
                  </td>
                  <td>
                    <input type="number" name="daya_pk_<?= $i ?>" id="daya_pk_<?= $i ?>" class="form-control" value="" placeholder="PK / HP" oninput="lookupNjkbMesin(<?= $i ?>)" onchange="lookupNjkbMesin(<?= $i ?>)">
                  </td>
                  <td>
                    <input type="number" name="tahun_mesin_<?= $i ?>" id="tahun_mesin_<?= $i ?>" class="form-control" value="" placeholder="Tahun" min="1970" max="<?= date('Y') + 1 ?>" oninput="lookupNjkbMesin(<?= $i ?>)" onchange="lookupNjkbMesin(<?= $i ?>)">
                  </td>
                  <td>
                    <?php if ($isOperator): ?>
                      <div class="form-control" style="background:#f1f5f9; color:#64748b; font-size:11px; font-weight:600; display:flex; align-items:center;">
                        [ Otomatis dari NJKB / Tim Penilai ]
                      </div>
                      <input type="hidden" name="nilai_jual_mesin_<?= $i ?>" id="nilai_jual_mesin_<?= $i ?>" value="0">
                    <?php else: ?>
                      <div class="input-group">
                        <span class="input-prefix">Rp</span>
                        <input type="number" name="nilai_jual_mesin_<?= $i ?>" id="nilai_jual_mesin_<?= $i ?>" class="form-control input-nilai-mesin" value="" placeholder="0" step="1000000" oninput="recalculateAllTax()">
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>

        <?php if ($isOperator): ?>
          <div style="margin-top: 14px; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 6px; padding: 12px; display:flex; align-items:center; gap:10px;">
            <div style="color:#16a34a;"><?= renderIcon('info', '', 20) ?></div>
            <div style="font-size:12px; color:#166534;">
              <strong>PENDAFTARAN OPERATOR:</strong> Jika merek body & mesin terdaftar di database, pendaftaran akan otomatis disetujui (AUTO-ACC) dan langsung muncul di Cetak SUPERPKAA. Jika belum ada di database, berkas otomatis diajukan ke Admin Induk.
            </div>
          </div>
        <?php else: ?>
          <!-- Ringkasan Perhitungan Fiskal (Rumus: NJ Body + Total NJ Mesin x Tarif) -->
          <div class="tax-calc-box" style="margin-top: 14px; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 6px; padding: 14px;">
            <div style="font-weight: 700; font-size: 11.5px; color: #166534; margin-bottom: 8px; text-transform: uppercase;">
              <?= renderIcon('coins', '', 14) ?> RUMUS PERHITUNGAN NJKB, PKAA, & OPSEN 66%:
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;">
              <div style="background:#fff; padding:8px 12px; border-radius:4px; border:1px solid #bbf7d0;">
                <span style="font-size:11px; color:#64748b; display:block;">Total Dasar NJKB</span>
                <strong id="display_total_njkb" style="font-size:16px; color:#065f46;">Rp 0</strong>
                <input type="hidden" name="total_njkb" id="total_njkb" value="0">
              </div>
              <div style="background:#fff; padding:8px 12px; border-radius:4px; border:1px solid #bbf7d0;">
                <span style="font-size:11px; color:#64748b; display:block;">Pokok PKAA (<span id="label_tarif_pokok">0%</span>)</span>
                <strong id="display_pokok_pkaa" style="font-size:16px; color:#0369a1;">Rp 0</strong>
              </div>
              <div style="background:#fff; padding:8px 12px; border-radius:4px; border:1px solid #bbf7d0;">
                <span style="font-size:11px; color:#64748b; display:block;">Opsen PKAA (66% dari Pokok)</span>
                <strong id="display_opsen_pkaa" style="font-size:16px; color:#b45309;">Rp 0</strong>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Panel Footer / Tombol Simpan & Cetak -->
  <div class="panel-header" id="panelActionFooter" style="justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border-medium); border-bottom: none;">
    <?php if ($isSavedSuccess && !empty($savedNopd)): ?>
      <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php?page=pendaftaran'">
        <?= renderIcon('plus', '', 14) ?> Daftarkan Objek Lainnya
      </button>

      <?php if (!$isOperator): ?>
        <a href="index.php?page=superpkaa&nopd=<?= urlencode($savedNopd) ?>" class="btn btn-accent btn-lg" style="background:#d97706; border-color:#b45309; color:#fff;">
          <?= renderIcon('printer', '', 16) ?> CETAK PENETAPAN (SUPERPKAA) <?= renderIcon('arrow-right', '', 14) ?>
        </a>
      <?php else: ?>
        <a href="index.php?page=status_pengajuan" class="btn btn-primary btn-lg">
          <?= renderIcon('file-clock', '', 16) ?> Lihat Status Pengajuan Saya
        </a>
      <?php endif; ?>
    <?php else: ?>
      <button type="reset" class="btn btn-secondary">Reset Formulir</button>
      <button type="submit" class="btn btn-primary" id="btnSubmitPendaftaran" style="padding: 8px 20px; background: #0284c7; border-color: #0369a1; font-weight:700;">
        <?= renderIcon('check-circle', '', 16) ?>
        <span><?= $isOperator ? 'KIRIM PENDAFTARAN KE ADMIN INDUK' : 'SIMPAN PENDAFTARAN KENDARAAN (F2)' ?></span>
      </button>
    <?php endif; ?>
  </div>
</form>

<script>
// Data pre-calculated sequence NOPD untuk seluruh 22 Kab/Kota di NTT
var nextSeqMap = <?= json_encode($allNextSequences) ?>;

// Master Data NJKB dari Database
var refNjkbBody = <?= json_encode($njkbBodyList) ?>;
var refNjkbMesin = <?= json_encode($njkbMesinList) ?>;
var refNjkbGabungan = <?= json_encode($njkbGabunganList) ?>;

// Sinkronisasi tahun body dengan tahun kapal
function syncTahunBody(val) {
  var thnBody = document.getElementById('tahun_body');
  if (thnBody) {
    thnBody.value = val;
    lookupNjkbBody();
  }
}

// Lookup NJKB Body berdasarkan Merek & Tahun
function lookupNjkbBody() {
  var merekInput = document.getElementById('merek_body');
  var tahunInput = document.getElementById('tahun_body');
  var hargaInput = document.getElementById('nj_body_kapal');
  var badge = document.getElementById('badge_body_njkb');
  
  if (!merekInput || !tahunInput || !hargaInput) return;
  
  var mVal = merekInput.value.trim().toLowerCase();
  var tVal = parseInt(tahunInput.value) || 0;
  
  var found = refNjkbBody.find(function(item) {
    return item.merek.toLowerCase() === mVal && parseInt(item.tahun_buat) === tVal;
  });

  if (!found && mVal) {
    // Cari merek yang cocok tanpa tahun
    found = refNjkbBody.find(function(item) {
      return item.merek.toLowerCase() === mVal;
    });
  }

  if (found) {
    hargaInput.value = found.nilai_jual;
    if (badge) {
      badge.style.display = 'inline';
      badge.textContent = '✓ Standar NJKB Database: ' + formatRupiahJs(found.nilai_jual);
    }
  } else {
    if (badge) badge.style.display = 'none';
  }
  recalculateAllTax();
}

// Lookup NJKB Mesin berdasarkan Merek, Daya, & Tahun pada baris ke-i
function lookupNjkbMesin(idx) {
  var merekInput = document.getElementById('merek_mesin_' + idx);
  var dayaInput = document.getElementById('daya_pk_' + idx);
  var tahunInput = document.getElementById('tahun_mesin_' + idx);
  var hargaInput = document.getElementById('nilai_jual_mesin_' + idx);
  var badge = document.getElementById('badge_mesin_njkb_' + idx);
  
  if (!merekInput || !hargaInput) return;
  
  var mVal = merekInput.value.trim().toLowerCase();
  var dVal = parseInt(dayaInput ? dayaInput.value : 0) || 0;
  var tVal = parseInt(tahunInput ? tahunInput.value : 0) || 0;
  
  var found = refNjkbMesin.find(function(item) {
    var matchMerek = item.merek.toLowerCase() === mVal;
    var matchDaya = dVal ? (parseInt(item.daya_pk) === dVal) : true;
    var matchTahun = tVal ? (parseInt(item.tahun_buat) === tVal) : true;
    return matchMerek && matchDaya && matchTahun;
  });

  if (!found && mVal) {
    found = refNjkbMesin.find(function(item) {
      return item.merek.toLowerCase() === mVal;
    });
  }

  if (found) {
    hargaInput.value = found.nilai_jual;
    if (badge) {
      badge.style.display = 'inline';
      badge.textContent = '✓ Standar NJKB: ' + formatRupiahJs(found.nilai_jual);
    }
  } else {
    if (badge) badge.style.display = 'none';
  }
  recalculateAllTax();
}

// Lookup NJKB 1 Kesatuan Paket
function lookupNjkbGabungan() {
  var paketInput = document.getElementById('merek_gabungan');
  var hargaInput = document.getElementById('njkb_gabungan');
  var badge = document.getElementById('badge_gabungan_njkb');
  
  if (!paketInput || !hargaInput) return;
  
  var pVal = paketInput.value.trim().toLowerCase();
  var found = refNjkbGabungan.find(function(item) {
    return item.merek_paket.toLowerCase() === pVal;
  });

  if (found) {
    hargaInput.value = found.nilai_jual;
    if (badge) {
      badge.style.display = 'inline';
      badge.textContent = '✓ Standar Paket NJKB: ' + formatRupiahJs(found.nilai_jual);
    }
  } else {
    if (badge) badge.style.display = 'none';
  }
  recalculateAllTax();
}

// Penanganan pergantian Kabupaten/Kota -> NOPD Terpisah 3 Kotak & Pelabuhan Pangkal
function onKabKotaChange(selectEl) {
  var selectedOption = selectEl.options[selectEl.selectedIndex];
  var kodeKab = selectEl.value;
  var pelabuhan = selectedOption.getAttribute('data-pelabuhan');
  
  var pelabuhanInput = document.getElementById('pelabuhan_pangkal');
  if (pelabuhanInput && pelabuhan) {
    pelabuhanInput.value = pelabuhan;
  }
  
  // Update 3 Kotak NOPD
  var dataSeq = nextSeqMap[kodeKab];
  if (dataSeq) {
    document.getElementById('nopd_prefix').value = dataSeq.prefix;
    document.getElementById('nopd_seq').value = dataSeq.sequence;
    document.getElementById('nopd_region').value = dataSeq.region;
    document.getElementById('no_objek_pajak').value = dataSeq.full;
  } else {
    document.getElementById('nopd_region').value = kodeKab;
    document.getElementById('no_objek_pajak').value = 'KA 0001 ' + kodeKab;
  }
}

// Toggle Metode NJKB (Terpisah vs 1 Kesatuan)
function toggleMetodeNjkb() {
  var radioGabungan = document.getElementById('radio_gabungan');
  var isGabungan = radioGabungan ? radioGabungan.checked : false;
  
  var secTerpisah = document.getElementById('section_njkb_terpisah');
  var secGabungan = document.getElementById('section_njkb_gabungan');
  var secMesinDetail = document.getElementById('section_mesin_detail');

  if (isGabungan) {
    if (secTerpisah) secTerpisah.style.display = 'none';
    if (secGabungan) secGabungan.style.display = 'block';
    if (secMesinDetail) secMesinDetail.style.display = 'none';
  } else {
    if (secTerpisah) secTerpisah.style.display = 'block';
    if (secGabungan) secGabungan.style.display = 'none';
    if (secMesinDetail) secMesinDetail.style.display = 'block';
  }
  recalculateAllTax();
}

function onJumlahMesinChange() {
  var jmlMesinSelect = document.getElementById('jumlah_mesin');
  var count = parseInt(jmlMesinSelect ? jmlMesinSelect.value : 1) || 1;
  for (var i = 1; i <= 6; i++) {
    var row = document.getElementById('row_mesin_' + i);
    if (row) {
      row.style.display = (i <= count) ? 'table-row' : 'none';
    }
  }
  recalculateAllTax();
}

// Hitung NJKB, Pokok PKAA (Tarif), dan Opsen 66%
function recalculateAllTax() {
  var radioGabungan = document.getElementById('radio_gabungan');
  var isGabungan = radioGabungan ? radioGabungan.checked : false;
  var totalNjkb = 0;

  if (isGabungan) {
    var njkbGabEl = document.getElementById('njkb_gabungan');
    totalNjkb = parseRupiah(njkbGabEl ? njkbGabEl.value : 0);
  } else {
    var njBodyEl = document.getElementById('nj_body_kapal');
    var bodyVal = parseRupiah(njBodyEl ? njBodyEl.value : 0);
    var engineTotal = 0;
    var jmlMesinSelect = document.getElementById('jumlah_mesin');
    var count = parseInt(jmlMesinSelect ? jmlMesinSelect.value : 1) || 1;
    
    for (var i = 1; i <= count; i++) {
      var valInput = document.getElementById('nilai_jual_mesin_' + i);
      if (valInput && document.getElementById('row_mesin_' + i).style.display !== 'none') {
        engineTotal += parseRupiah(valInput.value);
      }
    }
    totalNjkb = bodyVal + engineTotal;
  }

  // Update Display Total NJKB
  var totalNjkbInput = document.getElementById('total_njkb');
  var displayTotalNjkb = document.getElementById('display_total_njkb');
  if (totalNjkbInput) totalNjkbInput.value = totalNjkb;
  if (displayTotalNjkb) displayTotalNjkb.textContent = formatRupiahJs(totalNjkb);

  // Ambil Tarif Pokok dari Fungsi Terpilih
  var fungsiSelect = document.getElementById('kode_fungsi');
  var tarifPersen = 0.5;
  if (fungsiSelect) {
    var opt = fungsiSelect.options[fungsiSelect.selectedIndex];
    if (opt && opt.getAttribute('data-tarif')) {
      tarifPersen = parseFloat(opt.getAttribute('data-tarif')) || 0.5;
    }
  }
  var labelTarif = document.getElementById('label_tarif_pokok');
  if (labelTarif) labelTarif.textContent = tarifPersen + '%';

  // Rumus: Pokok = NJKB x Tarif%
  var pokokPkaa = Math.round(totalNjkb * (tarifPersen / 100));
  var opsenPkaa = Math.round(pokokPkaa * 0.66); // Opsen 66% dari pokok

  var dispPokok = document.getElementById('display_pokok_pkaa');
  if (dispPokok) dispPokok.textContent = formatRupiahJs(pokokPkaa);

  var dispOpsen = document.getElementById('display_opsen_pkaa');
  if (dispOpsen) dispOpsen.textContent = formatRupiahJs(opsenPkaa);
}

document.addEventListener('DOMContentLoaded', function() {
  onJumlahMesinChange();
  toggleMetodeNjkb();
  recalculateAllTax();
});
</script>
