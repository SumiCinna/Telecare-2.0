<?php
// $active_nav must be set before including this file
// e.g. $active_nav = 'dashboard';
?>

<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-user">
    <div class="sidebar-user-avatar">
      <?php if (!empty($staff['profile_photo'])): ?>
        <img src="../../<?= htmlspecialchars($staff['profile_photo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px;"/>
      <?php else: ?>
        <?= strtoupper(substr($staff['full_name'], 0, 2)) ?>
      <?php endif; ?>
    </div>
    <div style="min-width:0;">
      <div style="font-weight:700;color:#fff;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($staff['full_name']) ?></div>
      <div style="font-size:0.72rem;color:rgba(255,255,255,0.45);margin-top:0.1rem;"><?= $staff['role'] === 'senior_staff' ? 'Senior Staff' : 'Staff' ?></div>
    </div>
  </div>

  <nav class="nav-links">

    <a href="dashboard.php" class="nav-link <?= ($active_nav==='dashboard') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>

    <div class="nav-group-label">Scheduling</div>

    <a href="appointments.php" class="nav-link <?= ($active_nav==='appointments') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Appointments
    </a>

    <div class="nav-group-label">Patients</div>

    <a href="patients.php" class="nav-link <?= ($active_nav==='patients') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patient Management
    </a>

    <a href="records.php" class="nav-link <?= ($active_nav==='records') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Records Assistance
    </a>

    <div class="nav-group-label">Coordination</div>

    <a href="lab_requests.php" class="nav-link <?= ($active_nav==='lab') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
      Lab Requests
    </a>

    <a href="inventory.php" class="nav-link <?= ($active_nav==='inventory') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      Inventory
      <span class="nav-soon">Soon</span>
    </a>

    <div class="nav-group-label">Account</div>

    <a href="notifications.php" class="nav-link <?= ($active_nav==='notifications') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      Notifications
      <?php if ($unread_notifs > 0): ?>
      <span class="nav-badge"><?= $unread_notifs ?></span>
      <?php endif; ?>
    </a>

    <a href="profile.php" class="nav-link <?= ($active_nav==='profile') ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      My Profile
    </a>

  </nav>

  <div class="sidebar-logout">
    <a href="logout.php" class="logout-btn">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Log Out
    </a>
  </div>
</aside>

<script>
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (!href || href.startsWith('#')) return;
      e.preventDefault();

      // Show loader again on navigate
      const loader = document.createElement('div');
      loader.id = 'page-loader';
      loader.innerHTML = '<div class="loader-logo">TELE<span style="color:var(--red)">-</span>CARE</div><div class="loader-bar"><div class="loader-bar-fill"></div></div>';
      document.body.appendChild(loader);

      setTimeout(() => window.location.href = href, 400);
    });
  });
</script>