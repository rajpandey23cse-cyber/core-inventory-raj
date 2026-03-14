/* CoreInventory – Main JS */

function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  if (window.innerWidth <= 900) {
    sb.classList.toggle('open');
  } else {
    sb.classList.toggle('collapsed');
    document.querySelector('.main-wrapper')?.classList.toggle('collapsed');
  }
}

function toggleUserMenu() {
  document.getElementById('userDropdown')?.classList.toggle('open');
}

document.addEventListener('click', function(e) {
  const dd = document.getElementById('userDropdown');
  if (dd && !e.target.closest('.topbar-user')) {
    dd.classList.remove('open');
  }
});

/* ── Modal helpers ── */
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

/* ── Alert auto-dismiss ── */
setTimeout(function() {
  document.querySelectorAll('.alert-auto').forEach(a => {
    a.style.transition = 'opacity .5s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 500);
  });
}, 3000);

/* ── API helper ── */
async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(data)
  });
  return res.json();
}
async function apiGet(url) {
  const res = await fetch(url);
  return res.json();
}

/* ── Confirm delete ── */
function confirmDelete(msg) {
  return confirm(msg || 'Are you sure you want to delete this?');
}

/* ── Table search ── */
function tableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

/* ── Number formatting ── */
function fmt(n) {
  return Number(n).toLocaleString();
}
function fmtCurrency(n) {
  return '$' + Number(n).toLocaleString('en', {minimumFractionDigits:2, maximumFractionDigits:2});
}
