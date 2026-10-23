<?php
// layouts/modal_layar_bantu.php - Komponen Layar Bantu NOPD (Ctrl+B) JANGKAR NTT
try {
    $stmtBantu = $pdo->query("
        SELECT k.no_objek_pajak, k.no_ijin_operasi, k.tanda_selar, k.nama_kapal, 
               k.nama_pemilik, k.total_njkb, k.kapasitas_gt, k.tg_akhir_pajak, k.status_pajak,
               k.kode_fungsi, k.kode_kabkota, k.metode_njkb,
               f.nama_fungsi, j.nama_jenis, kb.nama_kabupaten_kota
        FROM kendaraan_air k
        LEFT JOIN ref_fungsi_tarif f ON k.kode_fungsi = f.kode_fungsi
        LEFT JOIN ref_jenis_kendaraan j ON k.kode_jenis = j.kode_jenis
        LEFT JOIN ref_kabupaten_kota kb ON k.kode_kabkota = kb.kode_wilayah
        ORDER BY k.no_objek_pajak ASC
    ");
    $daftarKapalBantu = $stmtBantu->fetchAll();
} catch (Exception $e) {
    $daftarKapalBantu = [];
}
?>
<!-- Modal Layar Bantu NOPD -->
<div class="modal-overlay" id="modalLayarBantu">
  <div class="modal-container" style="max-width: 880px;">
    <div class="modal-header" style="background:#0f172a; border-bottom: 2px solid #0284c7;">
      <div class="modal-title">
        <?= renderIcon('search', '', 18) ?>
        <span>LAYAR BANTU NOPD / PENCARIAN DATA KAA (JANGKAR NTT)</span>
      </div>
      <button type="button" class="modal-close" onclick="tutupLayarBantu()" title="Tutup (Esc)">
        <?= renderIcon('x', '', 18) ?>
      </button>
    </div>

    <div class="modal-body">
      <div style="margin-bottom: 12px; display: flex; gap: 8px;">
        <input type="text" id="layarBantuSearch" class="form-control" placeholder="Ketik Nopol KA (misal KA 0001 MB), Nama Kapal, Pemilik, atau Kab/Kota..." oninput="filterLayarBantu(this.value)">
        <button type="button" class="btn btn-secondary btn-sm" onclick="filterLayarBantu(document.getElementById('layarBantuSearch').value)">
          <?= renderIcon('search', '', 14) ?> Cari
        </button>
      </div>

      <div class="table-responsive" style="max-height: 52vh;">
        <table class="table-desktop" id="tableLayarBantu">
          <thead>
            <tr>
              <th width="120">Nopol KA</th>
              <th>Nama Kapal / SB</th>
              <th>Asal Kab/Kota</th>
              <th>Nama Pemilik</th>
              <th>Fungsi</th>
              <th>Metode NJKB</th>
              <th class="text-right">Total NJKB</th>
              <th width="75" class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($daftarKapalBantu)): ?>
              <tr>
                <td colspan="8" class="text-center" style="padding: 20px; color: #94a3b8;">
                  Belum ada data kendaraan air terdaftar di NTT.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($daftarKapalBantu as $b): ?>
                <tr>
                  <td><strong style="color:#0284c7; font-family:var(--font-mono);"><?= htmlspecialchars($b['no_objek_pajak']) ?></strong></td>
                  <td><strong><?= htmlspecialchars($b['nama_kapal']) ?></strong></td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($b['kode_kabkota'] ?: 'NTT') ?></span> <?= htmlspecialchars($b['nama_kabupaten_kota'] ?? '') ?></td>
                  <td><?= htmlspecialchars($b['nama_pemilik']) ?></td>
                  <td><span class="badge badge-neutral"><?= htmlspecialchars($b['nama_fungsi'] ?? '-') ?></span></td>
                  <td>
                    <?php if (($b['metode_njkb'] ?? '') === 'GABUNGAN'): ?>
                      <span class="badge badge-warning">1 Kesatuan</span>
                    <?php else: ?>
                      <span class="badge badge-neutral">Terpisah</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-right"><strong><?= formatRupiah($b['total_njkb']) ?></strong></td>
                  <td class="text-center">
                    <button type="button" class="btn btn-primary btn-sm" 
                            onclick="pilihDariLayarBantu(
                              '<?= htmlspecialchars($b['no_objek_pajak']) ?>', 
                              '<?= htmlspecialchars(addslashes($b['nama_kapal'])) ?>', 
                              '<?= htmlspecialchars(addslashes($b['nama_pemilik'])) ?>', 
                              '<?= $b['total_njkb'] ?>', 
                              '<?= $b['kode_fungsi'] ?>', 
                              '<?= $b['tg_akhir_pajak'] ?>'
                            )">
                      Pilih
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="modal-footer">
      <span style="margin-right: auto; font-size: 11px; color: #64748b;">
        Pencarian data kapal seluruh perairan Nusa Tenggara Timur &bull; Tekan <kbd style="background:#e2e8f0; padding:2px 4px; border-radius:3px;">ESC</kbd> untuk menutup
      </span>
      <button type="button" class="btn btn-secondary" onclick="tutupLayarBantu()">Tutup</button>
    </div>
  </div>
</div>
