<?php
$active_nav = $active_nav ?? '';
?>
<style>
:root{--sidebar-width:240px}
@media(min-width:768px){.page{margin-left:var(--sidebar-width)}.sidebar-nav{position:fixed;inset:0 auto 0 0;width:var(--sidebar-width);background:var(--surface,#fff);border-right:1px solid var(--border-color,#e5e7eb);z-index:100;padding:22px 0}.logo-section{padding:0 18px 22px;border-bottom:1px solid var(--border-color,#e5e7eb);margin-bottom:16px}.logo-section a{font-size:1.15rem;font-weight:800;color:var(--primary,#c1121f);text-decoration:none;letter-spacing:.03em}.nav-items-container{display:grid;gap:4px}.nav-item{display:flex;align-items:center;gap:12px;padding:13px 18px;border-left:3px solid transparent;color:var(--neutral-600,#667085);font-size:.84rem;font-weight:600;text-decoration:none}.nav-item svg{width:19px;height:19px;stroke:currentColor}.nav-item:hover{background:var(--neutral-50,#f8fafc);color:var(--primary,#c1121f)}.nav-item.active{background:var(--primary-soft,#fff1f2);border-left-color:var(--primary,#c1121f);color:var(--primary,#c1121f)}}
@media(max-width:767px){.page{margin-left:0;padding-bottom:82px!important}.sidebar-nav{position:fixed;inset:auto 0 0 0;background:#fff;border-top:1px solid var(--border-color,#e5e7eb);z-index:100}.logo-section{display:none}.nav-items-container{display:grid;grid-template-columns:repeat(5,1fr)}.nav-item{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;min-height:66px;color:var(--neutral-500,#667085);font-size:.62rem;font-weight:700;text-decoration:none}.nav-item svg{width:21px;height:21px;stroke:currentColor}.nav-item.active{color:var(--primary,#c1121f)}}
</style>

<nav class="sidebar-nav">

  <div class="logo-section">
    <a href="dashboard.php">TELE-CARE</a>
  </div>

  <div class="nav-items-container">

    <a href="dashboard.php" class="nav-item <?= $active_nav==='home'?'active':'' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5M5.5 10v10h13V10M9 20v-6h6v6"/>
      </svg>
      <span>Dashboard</span>
    </a>

    <a href="availability.php" class="nav-item <?= $active_nav==='schedule'?'active':'' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8">
        <rect x="3.5" y="5" width="17" height="15" rx="2"/>
        <path d="M8 3v4m8-4v4M3.5 10h17"/>
      </svg>
      <span>Schedule</span>
    </a>

    <a href="appointments.php" class="nav-item <?= $active_nav==='appointments'?'active':'' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8">
        <rect x="4" y="4" width="16" height="16" rx="2"/>
        <path d="M8 2v4m8-4v4M7 10h10M8 14h3m2 0h3"/>
      </svg>
      <span>Appointments</span>
    </a>

    <a href="patients.php" class="nav-item <?= $active_nav==='patients'?'active':'' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 18.5V20"/>
        <circle cx="10" cy="7.5" r="3.5"/>
        <path stroke-linecap="round" d="M16 4.5a3.5 3.5 0 0 1 0 6.8M17 14a4.5 4.5 0 0 1 3 4.5V20"/>
      </svg>
      <span>Patients</span>
    </a>

    <a href="credentials.php" class="nav-item <?= $active_nav==='credentials'?'active':'' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.8 2.9 8.1 7 10 4.1-1.9 7-5.2 7-10V6l-7-3Z"/>
        <path d="m9 12 2 2 4-4"/>
      </svg>
      <span>Credentials</span>
    </a>

  </div>

</nav>