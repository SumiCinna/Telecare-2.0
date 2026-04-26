</div><!-- /page-wrap -->
</div><!-- /main -->

<script>
// ── Sidebar toggle ──
// Desktop (>900px): expand/collapse the icon-rail via body class
// Mobile  (≤900px): slide-in drawer
function toggleSidebar() {
  const isMobile = window.innerWidth <= 900;
  const sb  = document.getElementById('sidebar');
  const hbg = document.getElementById('hamburger');
  const ov  = document.getElementById('drawerOverlay');

  if (isMobile) {
    const open = sb.classList.toggle('open');
    hbg.classList.toggle('open', open);
    ov.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  } else {
    document.body.classList.toggle('sb-expanded');
  }
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('hamburger').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
  document.body.style.overflow = '';
  // Do NOT remove sb-expanded on desktop — only close the mobile drawer
}
// Close mobile drawer on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && window.innerWidth <= 900) closeSidebar();
});

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); })
);

// ── Filter table by search query ──
function filterTable(tbodyId, query) {
  const q    = query.toLowerCase().trim();
  const rows = document.querySelectorAll('#' + tbodyId + ' tr[data-search]');
  rows.forEach(r => r.style.display = (!q || r.dataset.search.includes(q)) ? '' : 'none');
}

// ── Toast auto-dismiss ──
setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);
</script>
</body>
</html>