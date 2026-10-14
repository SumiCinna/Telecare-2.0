<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HMO Dashboard | TELE-CARE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/hmo.css">
</head>
<body>
<?php include 'sidebar.php'; ?>
<main class="hmo-main">
  <header class="hmo-topbar">
    <button class="mobile-menu" type="button" aria-label="Toggle navigation">☰</button>
    <div class="breadcrumbs"><span>Admin</span><b>›</b><span>HMO Management</span><b>›</b><strong>HMO Dashboard</strong></div>
    <div class="topbar-user"><span class="notification">♧<i>3</i></span><span class="user-avatar">A</span><span><b>Admin</b><small>Administrator</small></span><span>⌄</span></div>
  </header>
  <section class="hmo-content">
    <div class="page-heading">
      <div><div class="eyebrow">HMO MANAGEMENT</div><h1>HMO Dashboard</h1><p>Monitor your HMO network, authorizations, and claims activity.</p></div>
      <a class="primary-btn" href="hmo_providers.php">＋ Manage Providers</a>
    </div>
    <div class="metric-grid">
      <article class="metric-card blue"><span class="metric-icon">♧</span><div><small>Total Providers</small><strong>8</strong><em>+2 this month</em></div></article>
      <article class="metric-card green"><span class="metric-icon">✓</span><div><small>Active Providers</small><strong>6</strong><em>75% of network</em></div></article>
      <article class="metric-card amber"><span class="metric-icon">◷</span><div><small>Pending Authorizations</small><strong>24</strong><em>8 require attention</em></div></article>
      <article class="metric-card violet"><span class="metric-icon">▣</span><div><small>Claims This Month</small><strong>186</strong><em>+12.5% vs last month</em></div></article>
    </div>
    <div class="dashboard-grid">
      <section class="panel chart-panel">
        <div class="panel-heading"><div><h2>Authorization Overview</h2><p>Requests processed over the last 6 months</p></div><select aria-label="Chart period"><option>Last 6 months</option><option>This year</option></select></div>
        <div class="chart"><div class="y-axis"><span>80</span><span>60</span><span>40</span><span>20</span><span>0</span></div><div class="chart-area"><div class="grid-lines"></div><svg viewBox="0 0 620 210" preserveAspectRatio="none" aria-label="Authorization trend chart"><path class="chart-fill" d="M0 172 L100 142 L200 151 L300 102 L400 116 L500 70 L620 43 L620 210 L0 210Z"/><path class="chart-line" d="M0 172 L100 142 L200 151 L300 102 L400 116 L500 70 L620 43"/></svg><div class="x-axis"><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span><span>Sep</span><span>Oct</span><span>Nov</span></div></div></div>
      </section>
      <section class="panel">
        <div class="panel-heading"><div><h2>Quick Actions</h2><p>Common HMO tasks</p></div></div>
        <div class="quick-actions">
          <a href="hmo_providers.php"><b>♧</b><span><strong>Manage Providers</strong><small>View and update your HMO network</small></span><i>›</i></a>
          <a href="#"><b>✓</b><span><strong>Review Authorizations</strong><small>8 requests need your attention</small></span><i>›</i></a>
          <a href="#"><b>▣</b><span><strong>Process Claims</strong><small>12 claims are ready for review</small></span><i>›</i></a>
          <a href="#"><b>↗</b><span><strong>View Reports</strong><small>Download HMO performance reports</small></span><i>›</i></a>
        </div>
      </section>
    </div>
    <div class="dashboard-grid lower-grid">
      <section class="panel">
        <div class="panel-heading"><div><h2>Recent Activity</h2><p>Latest HMO management updates</p></div><a class="text-link" href="#">View all</a></div>
        <div class="activity-list">
          <div><span class="activity-dot green-dot">✓</span><p><strong>Maxicare</strong> provider accreditation renewed<small>Today, 10:24 AM</small></p></div>
          <div><span class="activity-dot blue-dot">▣</span><p>Authorization <strong>#AUTH-10482</strong> was approved<small>Today, 9:15 AM</small></p></div>
          <div><span class="activity-dot amber-dot">!</span><p>New claim received from <strong>Intellicare</strong><small>Yesterday, 4:42 PM</small></p></div>
          <div><span class="activity-dot violet-dot">＋</span><p><strong>Pacific Cross</strong> added as a provider<small>Yesterday, 1:08 PM</small></p></div>
        </div>
      </section>
      <section class="panel status-panel"><div class="panel-heading"><div><h2>Provider Status</h2><p>Current network breakdown</p></div></div><div class="donut-wrap"><div class="donut"><strong>8<small>Total</small></strong></div><div class="legend"><span><i class="dot active-dot"></i>Active <b>6</b></span><span><i class="dot inactive-dot"></i>Inactive <b>1</b></span><span><i class="dot suspended-dot"></i>Suspended <b>1</b></span></div></div></section>
    </div>
  </section>
</main>
<script>
document.querySelector('.mobile-menu')?.addEventListener('click', () => document.querySelector('.sidebar')?.classList.toggle('mobile-open'));
</script>
</body>
</html>
