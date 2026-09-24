<?php
// staff/includes/sidebar.php
// Sidebar component with styles and markup
?>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-mark" aria-hidden="true">
      <img src="/telecarelogo.png" alt="" style="width:100%;height:100%;object-fit:contain;border-radius:inherit;display:block;"/>
    </div>
    <div class="sb-logo-text">
      <div class="sb-logo-title">TELE<b>CARE</b></div>
      <div class="sb-logo-sub">Staff Portal</div>
    </div>
  </div>
  <div class="sb-badge">Staff Portal<strong><?= htmlspecialchars($staff_name) ?></strong></div>
  <nav class="sb-nav">
    <?php
    $nav = [
      // Dashboard — grid / panels
      ['dashboard.php',   'dashboard',    'Dashboard',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M4 5a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM13 5a1 1 0 011-1h5a1 1 0 011 1v3a1 1 0 01-1 1h-5a1 1 0 01-1-1V5zM13 14a1 1 0 011-1h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1v-5zM4 16a1 1 0 011-1h5a1 1 0 011 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-3z"/>'],

      // Services POS — receipt with lines
      ['pos_services.php','pos',          'Services POS',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12a1 1 0 011 1v16.2a.8.8 0 01-1.2.7L15 19.3l-2.4 1.5a1 1 0 01-1.1 0L9 19.3l-2.8 1.6A.8.8 0 015 20.2V4a1 1 0 011-1z"/><path stroke-linecap="round" d="M9 8h6M9 12h6"/>'],

      // Appointments — calendar
      ['appointments.php','appointments', 'Appointments',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],

      // Doctors — stethoscope
      ['doctors.php',     'doctors',      'Doctors',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M6 3v5a4 4 0 008 0V3"/><path stroke-linecap="round" d="M6 3H4.5M14 3h1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 12v2a5 5 0 0010 0v-1"/><circle cx="20" cy="10" r="2"/>'],

      // Patients — two people
      ['patients.php',    'patients',     'Patients',
        '<path stroke-linecap="round" stroke-linejoin="round" d="M14 8a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zM3.5 20a7 7 0 0114 0"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 8.5a2.5 2.5 0 100-5M18 14.5a5.5 5.5 0 013 4.9"/>'],
    ];
    foreach ($nav as [$href, $key, $label, $icon]): ?>
    <a href="<?= $href ?>" class="sb-link <?= $active_page === $key ? 'active' : '' ?>">
      <svg class="sb-link-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="1.8" aria-hidden="true"><?= $icon ?></svg>
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