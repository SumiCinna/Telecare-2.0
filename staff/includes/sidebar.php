<?php
// staff/includes/sidebar.php
// Sidebar component with styles and markup
?>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-text">
      <div class="sb-logo-title">TELE<b>CARE</b></div>
      <div class="sb-logo-sub">Staff Portal</div>
    </div>
  </div>
  <div class="sb-badge">Staff Portal<strong><?= htmlspecialchars($staff_name) ?></strong></div>
  <nav class="sb-nav">
    <?php
    $nav = [
      ['dashboard.php',   'dashboard',    'Dashboard',    '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
      ['pos_services.php','pos',          'Services POS', '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m-10 0a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4z"/>'],
      ['appointments.php','appointments', 'Appointments', '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
      ['doctors.php',     'doctors',      'Doctors',      '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 9.5c0 5.302-7.5 10.5-7.5 10.5S4.5 14.802 4.5 9.5a7.5 7.5 0 1115 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5m-2.5-2.5h5"/>'],
      ['patients.php',    'patients',     'Patients',     '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
    ];
    foreach ($nav as [$href, $key, $label, $icon]): ?>
    <a href="<?= $href ?>" class="sb-link <?= $active_page === $key ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><?= $icon ?></svg>
      <span class="sb-link-label"><?= $label ?></span>
    </a>
    <?php endforeach ?>
  </nav>
  <div class="sb-foot">
    <a href="logout.php">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Log Out
    </a>
  </div>
</aside>

<script>
  (function initSidebarState() {
    const key = 'staff_sidebar_collapsed';
    const saved = localStorage.getItem(key);
    if (saved === '1') {
      document.body.classList.add('sidebar-collapsed');
    }

    window.toggleStaffSidebar = function toggleStaffSidebar() {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem(key, collapsed ? '1' : '0');
    };
  })();
</script>
