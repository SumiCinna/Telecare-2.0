
</div><!-- /page-wrap -->
</div><!-- /main -->

<script>
// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Filter table by search query ──
function filterTable(tbodyId, query) {
  const q    = query.toLowerCase().trim();
  const rows = document.querySelectorAll('#' + tbodyId + ' tr[data-search]');
  rows.forEach(r => r.style.display = (!q || r.dataset.search.includes(q)) ? '' : 'none');
}

// ── Toast auto-dismiss ──
setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);

// ── Notification bell ──
(function initNotifications() {
  const btn   = document.getElementById('notif-bell-btn');
  const panel = document.getElementById('notif-panel');
  const badge = document.getElementById('notif-badge');
  const list  = document.getElementById('notif-list');
  const markAllBtn = document.getElementById('notif-mark-all');
  if (!btn || !panel) return;

  function setBadge(count) {
    if (count > 0) {
      badge.style.display = '';
      badge.textContent = count > 99 ? '99+' : count;
    } else {
      badge.style.display = 'none';
    }
  }

  function togglePanel(open) {
    const willOpen = open ?? !panel.classList.contains('open');
    panel.classList.toggle('open', willOpen);
    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  }

  btn.addEventListener('click', e => {
    e.stopPropagation();
    togglePanel();
  });

  document.addEventListener('click', e => {
    if (panel.classList.contains('open') && !panel.contains(e.target) && e.target !== btn) {
      togglePanel(false);
    }
  });

  list.addEventListener('click', e => {
    const item = e.target.closest('.notif-item');
    if (!item) return;
    const id = item.dataset.id;
    fetch('notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=mark_read&id=' + encodeURIComponent(id)
    }).then(r => r.json()).then(data => { if (data.ok) setBadge(data.count); }).catch(() => {});
    // let the link navigate normally
  });

  if (markAllBtn) {
    markAllBtn.addEventListener('click', e => {
      e.stopPropagation();
      fetch('notifications.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
      }).then(r => r.json()).then(data => {
        if (!data.ok) return;
        setBadge(0);
        list.innerHTML = '<div class="notif-empty">No new notifications right now.</div>';
      }).catch(() => {});
    });
  }
})();
</script>
</body>
</html>

