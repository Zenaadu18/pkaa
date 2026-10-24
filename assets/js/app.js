// assets/js/app.js - Logika Interaktif Aplikasi SIMPAB Desktop

/**
 * Format Angka ke Rupiah String
 */
function formatRupiahJs(angka) {
  var number_string = angka.toString().replace(/[^,\d]/g, ''),
      split = number_string.split(','),
      sisa = split[0].length % 3,
      rupiah = split[0].substr(0, sisa),
      ribuan = split[0].substr(sisa).match(/\d{3}/gi);

  if (ribuan) {
    var separator = sisa ? '.' : '';
    rupiah += separator + ribuan.join('.');
  }

  rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
  return 'Rp ' + (rupiah ? rupiah : '0');
}

/**
 * Parse Rupiah / Formatted Number to Float
 */
function parseRupiah(str) {
  if (!str) return 0;
  var cleaned = str.toString().replace(/[^0-9]/g, '');
  return parseFloat(cleaned) || 0;
}

/**
 * Live Clock pada Topbar Desktop
 */
function initClock() {
  var clockEl = document.getElementById('liveClock');
  if (!clockEl) return;
  
  function update() {
    var now = new Date();
    var hours = String(now.getHours()).padStart(2, '0');
    var minutes = String(now.getMinutes()).padStart(2, '0');
    var seconds = String(now.getSeconds()).padStart(2, '0');
    var day = String(now.getDate()).padStart(2, '0');
    var month = String(now.getMonth() + 1).padStart(2, '0');
    var year = now.getFullYear();
    clockEl.innerHTML = day + '/' + month + '/' + year + ' &nbsp;' + hours + ':' + minutes + ':' + seconds;
  }
  
  update();
  setInterval(update, 1000);
}

/**
 * Penanganan Sub-Form Mesin pada Halaman Pendaftaran
 */
function initEngineRows() {
  var jmlMesinSelect = document.getElementById('jumlah_mesin');
  var njBodyInput = document.getElementById('nj_body_kapal');
  var totalNjkbInput = document.getElementById('total_njkb');
  var displayTotalNjkb = document.getElementById('display_total_njkb');
  
  if (!jmlMesinSelect) return;

  function recalculateTotal() {
    var bodyVal = parseRupiah(njBodyInput ? njBodyInput.value : 0);
    var engineTotal = 0;
    
    for (var i = 1; i <= 6; i++) {
      var row = document.getElementById('row_mesin_' + i);
      var valInput = document.getElementById('nilai_jual_mesin_' + i);
      if (row && row.style.display !== 'none' && valInput) {
        engineTotal += parseRupiah(valInput.value);
      }
    }
    
    var total = bodyVal + engineTotal;
    if (totalNjkbInput) totalNjkbInput.value = total;
    if (displayTotalNjkb) displayTotalNjkb.textContent = formatRupiahJs(total);
  }

  function updateRows() {
    var count = parseInt(jmlMesinSelect.value) || 1;
    for (var i = 1; i <= 6; i++) {
      var row = document.getElementById('row_mesin_' + i);
      if (row) {
        if (i <= count) {
          row.style.display = 'table-row';
        } else {
          row.style.display = 'none';
        }
      }
    }
    recalculateTotal();
  }

  jmlMesinSelect.addEventListener('change', updateRows);
  if (njBodyInput) {
    njBodyInput.addEventListener('input', function() {
      recalculateTotal();
    });
  }

  // Bind input mesin
  for (var i = 1; i <= 6; i++) {
    var valInput = document.getElementById('nilai_jual_mesin_' + i);
    if (valInput) {
      valInput.addEventListener('input', recalculateTotal);
    }
  }

  updateRows();
}

/**
 * Penanganan Modal Layar Bantu NOPD (Ctrl + B)
 */
function bukaLayarBantu(targetFieldId) {
  var modal = document.getElementById('modalLayarBantu');
  if (modal) {
    modal.classList.add('active');
    modal.dataset.targetField = targetFieldId || 'no_objek_pajak';
    var searchInput = document.getElementById('layarBantuSearch');
    if (searchInput) {
      searchInput.value = '';
      searchInput.focus();
      filterLayarBantu('');
    }
  }
}

function tutupLayarBantu() {
  var modal = document.getElementById('modalLayarBantu');
  if (modal) {
    modal.classList.remove('active');
  }
}

function filterLayarBantu(keyword) {
  var table = document.getElementById('tableLayarBantu');
  if (!table) return;
  var rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
  keyword = keyword.toLowerCase();

  for (var i = 0; i < rows.length; i++) {
    var text = rows[i].textContent.toLowerCase();
    if (text.indexOf(keyword) > -1) {
      rows[i].style.display = '';
    } else {
      rows[i].style.display = 'none';
    }
  }
}

function pilihDariLayarBantu(nopd, namaKapal, namaPemilik, totalNjkb, kodeFungsi, tgAkhirPajak) {
  var modal = document.getElementById('modalLayarBantu');
  var targetId = (modal && modal.dataset.targetField) ? modal.dataset.targetField : 'no_objek_pajak';
  
  var targetEl = document.getElementById(targetId);
  if (targetEl) {
    targetEl.value = nopd;
    targetEl.dispatchEvent(new Event('change'));
  }

  // Jika form pencarian auto-submit / redirect
  var autoSubmitForm = document.getElementById('formCariKaa');
  if (autoSubmitForm) {
    autoSubmitForm.submit();
  }

  tutupLayarBantu();
}

/**
 * Modern Floating Toast Notification System
 * type: 'success' | 'warning' | 'error' | 'info'
 */
function showToast(type, title, message, duration) {
  duration = duration || 4200;
  var container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    document.body.appendChild(container);
  }

  var iconSvg = '';
  if (type === 'success') {
    iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>';
  } else if (type === 'warning') {
    iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  } else if (type === 'error' || type === 'danger') {
    iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
  } else {
    iconSvg = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
  }

  var safeTitle = String(title || '').replace(/[&<>"']/g, function(s) {
    return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[s];
  });

  var toast = document.createElement('div');
  toast.className = 'modern-toast toast-' + type;
  toast.innerHTML = 
    '<div class="modern-toast-icon">' + iconSvg + '</div>' +
    '<div class="modern-toast-content">' +
      '<div class="modern-toast-title">' + safeTitle + '</div>' +
      '<div class="modern-toast-message">' + message + '</div>' +
    '</div>' +
    '<button type="button" class="modern-toast-close" onclick="closeToast(this.parentElement)">&times;</button>' +
    '<div class="toast-progress"></div>';

  container.appendChild(toast);

  setTimeout(function() {
    closeToast(toast);
  }, duration);
}

function closeToast(toastEl) {
  if (!toastEl || toastEl.classList.contains('toast-hiding')) return;
  toastEl.classList.add('toast-hiding');
  setTimeout(function() {
    if (toastEl.parentElement) {
      toastEl.parentElement.removeChild(toastEl);
    }
  }, 250);
}

/**
 * Sidebar Minimize / Expand Toggle
 */
function toggleSidebar() {
  var appWrapper = document.querySelector('.app-wrapper');
  if (!appWrapper) return;
  
  var isCollapsed = appWrapper.classList.toggle('sidebar-collapsed');
  localStorage.setItem('jangkar_sidebar_collapsed', isCollapsed ? 'true' : 'false');
}

function initSidebarState() {
  var saved = localStorage.getItem('jangkar_sidebar_collapsed');
  var appWrapper = document.querySelector('.app-wrapper');
  if (saved === 'true' && appWrapper) {
    appWrapper.classList.add('sidebar-collapsed');
  }
}

// Inisialisasi saat halaman selesai dimuat
document.addEventListener('DOMContentLoaded', function() {
  initClock();
  initEngineRows();
  initSidebarState();
});
