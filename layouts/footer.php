<?php
// layouts/footer.php - Footer Desktop Status Bar SIMPAB
?>
    </main>
  </div>

  <!-- Status Bar / Desktop Footer -->
  <footer class="desktop-footer">
    <div class="footer-shortcuts">
      <div class="shortcut-item">
        <span class="shortcut-key">Ctrl+B</span>
        <span>Layar Bantu NOPD</span>
      </div>
      <div class="shortcut-item">
        <span class="shortcut-key">F2</span>
        <span>Pendaftaran Baru</span>
      </div>
      <div class="shortcut-item">
        <span class="shortcut-key">F3</span>
        <span>Pembayaran SKPD</span>
      </div>
      <div class="shortcut-item">
        <span class="shortcut-key">F4</span>
        <span>Laporan</span>
      </div>
      <div class="shortcut-item">
        <span class="shortcut-key">Esc</span>
        <span>Tutup Jendela</span>
      </div>
    </div>

    <div style="display:flex; align-items:center; gap:12px;">
      <span style="display:inline-flex; align-items:center; gap:5px; color:#10b981;">
        <span style="width:7px; height:7px; background:#10b981; border-radius:50%; display:inline-block;"></span>
        Database Terhubung (db_pkaa)
      </span>
      <span>JANGKAR NTT v2.6.10 &bull; BPAD Prov. NTT</span>
    </div>
  </footer>
</div>

<!-- Modal Layar Bantu NOPD Global -->
<?php require_once __DIR__ . '/modal_layar_bantu.php'; ?>

<!-- Script Interaktif -->
<script src="assets/js/app.js"></script>
<script src="assets/js/shortcuts.js"></script>

<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    showToast(
        <?= json_encode($flash['type']) ?>,
        <?= json_encode($flash['title']) ?>,
        <?= json_encode($flash['message']) ?>
    );
});
</script>
<?php endif; ?>
</body>
</html>
