// assets/js/shortcuts.js - Pintasan Keyboard Global SIMPAB Desktop

document.addEventListener('keydown', function(e) {
  // Ctrl + B: Buka Layar Bantu NOPD
  if (e.ctrlKey && (e.key === 'b' || e.key === 'B')) {
    e.preventDefault();
    if (typeof bukaLayarBantu === 'function') {
      bukaLayarBantu();
    }
  }

  // Escape: Tutup Modal
  if (e.key === 'Escape') {
    if (typeof tutupLayarBantu === 'function') {
      tutupLayarBantu();
    }
  }

  // F2: Pendaftaran Baru
  if (e.key === 'F2') {
    e.preventDefault();
    window.location.href = 'index.php?page=pendaftaran';
  }

  // F3: Pembayaran SKPD
  if (e.key === 'F3') {
    e.preventDefault();
    window.location.href = 'index.php?page=skpd';
  }

  // F4: Laporan
  if (e.key === 'F4') {
    e.preventDefault();
    window.location.href = 'index.php?page=laporan';
  }
});
