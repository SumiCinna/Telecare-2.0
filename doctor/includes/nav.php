// doctor/includes/nav.php
<style>
  :root {
    --sidebar-width: 240px;
  }

  /* ── DESKTOP: Collapsible Sidebar with Hamburger ── */
  @media (min-width: 768px) {
    .page {
      margin-left: var(--sidebar-width);
      transition: margin-left 0.3s;
    }

    .page.sidebar-closed {
      margin-left: 80px;
      max-width: calc(100% - 80px);
    }

    .sidebar-nav {
      position: fixed;
      left: 0;
      top: 0;
      width: var(--sidebar-width);
      height: 100vh;
      background: var(--white);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      padding: 1.5rem 0;
      z-index: 100;
      overflow-y: auto;
      overflow-x: hidden;
      transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .sidebar-nav.mobile-open {
      width: 80px;
    }

    .sidebar-nav .logo-section {
      padding: 0 1rem 1.5rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1.5rem;
      transition: all 0.3s;
    }

    .sidebar-nav.mobile-open .logo-section {
      padding: 0.5rem;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1rem;
      text-align: center;
    }

    .sidebar-nav .logo-section a {
      font-family: 'Inter', sans-serif;
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--green);
      text-decoration: none;
      letter-spacing: 0.04em;
      white-space: nowrap;
      transition: font-size 0.3s;
    }

    .sidebar-nav.mobile-open .logo-section a {
      font-size: 0.7rem;
    }

    .sidebar-nav .logo-section a span {
      color: var(--red);
    }

    .nav-items-container {
      display: flex;
      flex-direction: column;
      gap: 0;
      flex: 1;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 1rem 1rem;
      text-decoration: none;
      color: var(--muted);
      font-size: 0.9rem;
      font-weight: 600;
      transition: all 0.3s;
      border-left: 3px solid transparent;
      white-space: nowrap;
    }

    .sidebar-nav.mobile-open .nav-item {
      padding: 0.8rem 0.5rem;
      justify-content: center;
      gap: 0;
      font-size: 0;
    }

    .sidebar-nav.mobile-open .nav-item span {
      display: none;
    }

    .nav-item svg {
      width: 20px;
      height: 20px;
      stroke: currentColor;
      flex-shrink: 0;
      transition: width 0.3s, height 0.3s;
    }

    .sidebar-nav.mobile-open .nav-item svg {
      width: 24px;
      height: 24px;
    }

    .nav-item:hover {
      background: rgba(36, 68, 65, 0.05);
      color: var(--green);
    }

    .nav-item.active {
      background: rgba(36, 68, 65, 0.08);
      color: var(--green);
      border-left-color: var(--green);
    }

    .sidebar-nav.mobile-open .nav-item.active {
      border-left: none;
      background: rgba(36, 68, 65, 0.12);
      border-radius: 8px;
      margin: 0 0.3rem;
    }

    .nav-item.active svg {
      stroke: var(--green);
    }

    .sidebar-overlay {
      display: none !important;
    }
  }

  /* ── MOBILE: Bottom Nav ── */
  @media (max-width: 767px) {
    .page {
      margin-left: 0;
      padding-bottom: 100px !important;
    }

    /* Sidebar overlay for mobile */
    .sidebar-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      display: none;
      z-index: 98;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .sidebar-overlay.active {
      display: block;
      opacity: 1;
    }

    /* Sidebar converts to bottom nav on mobile */
    .sidebar-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      width: 100%;
      height: auto;
      background: var(--white);
      border-top: 1px solid var(--border);
      display: flex;
      flex-direction: row;
      padding: 0.6rem 0;
      z-index: 100;
      padding-bottom: calc(0.6rem + env(safe-area-inset-bottom));
      transform: translateY(0);
      transition: none;
    }

    .sidebar-nav .logo-section {
      display: none;
    }

    .nav-items-container {
      display: flex;
      flex-direction: row;
      width: 100%;
      gap: 0;
      overflow-x: auto;
    }

    .nav-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 0.7rem 0.5rem;
      text-decoration: none;
      color: var(--muted);
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      transition: color 0.2s;
      gap: 0.35rem;
      border-left: none;
      text-align: center;
      min-height: 70px;
      min-width: 82px;
    }

    .nav-item svg {
      width: 26px;
      height: 26px;
      stroke: currentColor;
      flex-shrink: 0;
    }

    .nav-item:hover {
      background: transparent;
    }

    .nav-item.active {
      background: transparent;
      color: var(--green);
      border-left: none;
    }

    .nav-item.active svg {
      stroke: var(--green);
    }

  }

</style>

<!-- Sidebar Overlay (for mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Responsive Sidebar/Bottom Nav -->
<nav class="sidebar-nav" id="sidebarNav">
  <div class="logo-section">
    <a href="dashboard.php">TELE<span>-</span>CARE</a>
  </div>

  <div class="nav-items-container">
    <a href="dashboard.php" class="nav-item <?= ($active_nav ?? '') === 'home' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
      </svg>
      <span>Dashboard</span>
    </a>

    <a href="appointments.php" class="nav-item <?= ($active_nav ?? '') === 'appointments' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <span>Schedule</span>
    </a>

    <a href="patients.php" class="nav-item <?= ($active_nav ?? '') === 'patients' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <span>Patients</span>
    </a>

    <a href="availability.php" class="nav-item <?= ($active_nav ?? '') === 'availability' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>Schedule</span>
    </a>

    <a href="review_summary.php" class="nav-item <?= ($active_nav ?? '') === 'consultations' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m7-2a8 8 0 11-16 0c0 1.3.31 2.53.87 3.62L4 20l4.38-1.87A8 8 0 0120 12z" />
      </svg>
      <span>Consultations</span>
    </a>

    <a href="patient-records.php" class="nav-item <?= ($active_nav ?? '') === 'records' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 4h12a2 2 0 012 2v14H4V6a2 2 0 012-2Zm3 0v4h6V4M8 13h8M8 17h5" />
      </svg>
      <span>Medical Records</span>
    </a>

    <a href="credentials.php" class="nav-item <?= ($active_nav ?? '') === 'credentials' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
      </svg>
      <span>Credentials</span>
    </a>

    <a href="profile.php" class="nav-item <?= ($active_nav ?? '') === 'profile' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
      <span>Profile</span>
    </a>
  </div>


<script>
// Sidebar toggle for hamburger button (desktop collapse + mobile bottom nav)
document.addEventListener('DOMContentLoaded', function() {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebarNav');
  const overlay = document.getElementById('sidebarOverlay');
  const page = document.querySelector('.page');

  if (!toggleBtn || !sidebar) return;

  function toggleSidebar() {
    const isMobile = window.innerWidth <= 767;
    
    if (isMobile) {
      // Mobile: toggle bottom nav visibility
      sidebar.classList.toggle('mobile-open');
      overlay.classList.toggle('active');
    } else {
      // Desktop: toggle sidebar collapse (width)
      sidebar.classList.toggle('mobile-open');
      page.classList.toggle('sidebar-closed');
    }
  }

  function closeSidebar() {
    sidebar.classList.remove('mobile-open');
    overlay.classList.remove('active');
    page.classList.remove('sidebar-closed');
  }

  // Hamburger button click
  toggleBtn.addEventListener('click', toggleSidebar);
  
  // Overlay click (mobile)
  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }
  
  // Close sidebar when clicking a nav item (mobile only)
  const navItems = sidebar.querySelectorAll('.nav-item:not(.logout)');
  navItems.forEach(item => {
    item.addEventListener('click', function() {
      if (window.innerWidth <= 767) {
        closeSidebar();
      }
    });
  });

  // Handle window resize
  window.addEventListener('resize', function() {
    const isMobile = window.innerWidth <= 767;
    if (!isMobile) {
      closeSidebar();
    }
  });
});
</script>