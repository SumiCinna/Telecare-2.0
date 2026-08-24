<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Medtech Dashboard — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);display:flex;min-height:100vh}
    h1,h2,h3{font-family:'Playfair Display',serif}

    .sidebar{width:230px;min-width:230px;background:var(--green);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-logo{padding:1.8rem 1.5rem 1.2rem;font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:#fff;border-bottom:1px solid rgba(255,255,255,0.08)}
    .sidebar-logo span{color:var(--red)}
    .sidebar-admin{padding:1rem 1.5rem;font-size:0.78rem;color:rgba(255,255,255,0.45);border-bottom:1px solid rgba(255,255,255,0.08)}
    .sidebar-admin strong{color:rgba(255,255,255,0.8);font-weight:600;display:block;font-size:0.88rem}
    .nav-links{padding:1rem 0;flex:1}
    .nav-link{display:flex;align-items:center;gap:0.8rem;padding:0.8rem 1.5rem;color:rgba(255,255,255,0.55);font-size:0.88rem;font-weight:500;width:100%;text-align:left;font-family:'DM Sans',sans-serif;transition:all 0.2s;border-left:3px solid transparent;text-decoration:none}
    .nav-link svg{width:18px;height:18px;stroke:currentColor;flex-shrink:0}
    .nav-link:hover{color:#fff;background:rgba(255,255,255,0.06)}
    .nav-link.active{color:#fff;background:rgba(255,255,255,0.1);border-left-color:var(--red)}
    .sidebar-logout{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,0.08)}
    .logout-btn{display:flex;align-items:center;gap:0.6rem;color:rgba(255,255,255,0.45);font-size:0.82rem;text-decoration:none;transition:color 0.2s}
    .logout-btn:hover{color:var(--red)}

    .main{flex:1;overflow-y:auto}
    .topbar{background:var(--white);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .notification-wrap{position:relative}
    .notification-button{width:38px;height:38px;border:0;border-radius:50%;background:#f5f7f7;color:var(--green);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;transition:background 0.2s}
    .notification-button:hover,.notification-button.active{background:#e8eeee}
    .notification-button svg{width:20px;height:20px}
    .notification-badge{position:absolute;top:-3px;right:-3px;min-width:18px;height:18px;padding:0 4px;border-radius:50px;background:var(--red);color:#fff;border:2px solid #fff;font-size:0.62rem;font-weight:700;display:flex;align-items:center;justify-content:center}
    .notification-panel{position:absolute;right:0;top:48px;width:340px;background:#fff;border:1px solid rgba(36,68,65,0.1);border-radius:12px;box-shadow:0 12px 35px rgba(36,68,65,0.18);display:none;overflow:hidden}
    .notification-panel.open{display:block;animation:notificationDrop 0.18s ease}
    @keyframes notificationDrop{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
    .notification-panel-head{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.1rem;border-bottom:1px solid rgba(36,68,65,0.08)}
    .notification-panel-head strong{font-size:0.95rem}
    .notification-panel-head a{font-size:0.72rem;color:var(--blue);font-weight:700;text-decoration:none}
    .notification-item{display:flex;gap:0.75rem;padding:0.8rem 1rem;border-bottom:1px solid rgba(36,68,65,0.06);text-decoration:none;color:var(--green);transition:background 0.2s}
    .notification-item:hover{background:#f6f9f9}
    .notification-item:last-child{border-bottom:0}
    .notification-dot{width:9px;height:9px;border-radius:50%;background:var(--blue);margin-top:0.35rem;flex-shrink:0}
    .notification-item strong{font-size:0.78rem;display:block}
    .notification-item span{font-size:0.72rem;color:#9ab0ae;display:block;margin-top:0.18rem}
    @media(max-width:500px){.notification-panel{position:fixed;right:1rem;left:1rem;top:62px;width:auto}.topbar{padding:1rem}}
    .page-content{padding:2rem}

    .kpi-grid{display:grid;grid-template-columns:repeat(4, 1fr);gap:1rem;margin-bottom:2rem}
    @media(max-width:1200px){.kpi-grid{grid-template-columns:repeat(2, 1fr)}}
    @media(max-width:768px){.kpi-grid{grid-template-columns:1fr}}

    .kpi-card{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.3rem;position:relative;overflow:hidden}
    .kpi-card::before{content:'';position:absolute;top:0;right:0;width:100px;height:100px;background:rgba(36,68,65,0.04);border-radius:50%;transform:translate(30%, -30%)}
    .kpi-card.accent-blue::before{background:rgba(63,130,227,0.08)}
    .kpi-card.accent-red::before{background:rgba(195,54,67,0.08)}
    .kpi-card.accent-orange::before{background:rgba(245,158,11,0.08)}

    .kpi-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;margin-bottom:0.8rem;flex-shrink:0}
    .kpi-icon.blue{background:rgba(63,130,227,0.1);color:var(--blue)}
    .kpi-icon.green{background:rgba(34,197,94,0.1);color:#16a34a}
    .kpi-icon.red{background:rgba(195,54,67,0.1);color:var(--red)}
    .kpi-icon.orange{background:rgba(245,158,11,0.1);color:#d97706}

    .kpi-value{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;line-height:1;color:var(--green)}
    .kpi-label{font-size:0.74rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#9ab0ae;margin-top:0.5rem}

    .chart-section{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.5rem;margin-bottom:1.5rem}
    .chart-title{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:900;margin-bottom:1rem;color:var(--green)}
    .chart-subtitle{font-size:0.75rem;color:#9ab0ae;margin-bottom:1.2rem}
    .volume-chart{height:180px;display:flex;align-items:flex-end;gap:1rem;padding:1rem 0 1.8rem;border-bottom:1px solid rgba(36,68,65,0.08)}
    .volume-bar-wrap{height:100%;flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:0.45rem}
    .volume-bar{width:100%;max-width:34px;background:var(--blue);border-radius:6px 6px 2px 2px;min-height:12px;transition:opacity 0.2s}
    .volume-bar:hover{opacity:0.75}
    .volume-count{font-size:0.7rem;color:var(--green);font-weight:700}
    .volume-day{font-size:0.68rem;color:#9ab0ae;white-space:nowrap}

    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem}
    @media(max-width:900px){.two-col{grid-template-columns:1fr}}

    .stat-box{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);padding:1.3rem}
    .stat-box h3{font-size:0.85rem;font-weight:700;color:#9ab0ae;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.9rem}
    .stat-item{display:flex;align-items:center;justify-content:space-between;padding:0.7rem 0;border-bottom:1px solid rgba(36,68,65,0.05)}
    .stat-item:last-child{border-bottom:none}
    .stat-name{font-size:0.87rem;font-weight:500}
    .stat-count{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;color:var(--blue)}

    .recent-tests{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);overflow:hidden}
    .recent-head{padding:1.3rem 1.5rem;border-bottom:1px solid rgba(36,68,65,0.07);display:flex;align-items:center;justify-content:space-between}
    .recent-head h3{font-size:0.95rem;font-weight:700}
    .recent-link{font-size:0.78rem;color:var(--blue);text-decoration:none;font-weight:700}
    .recent-body{padding:0}
    .test-row{display:flex;align-items:center;justify-content:space-between;padding:0.95rem 1.5rem;border-bottom:1px solid rgba(36,68,65,0.05)}
    .test-row:last-child{border-bottom:none}
    .test-info{flex:1}
    .test-receipt{font-weight:700;font-size:0.88rem;color:var(--green)}
    .test-service{font-size:0.75rem;color:#9ab0ae;margin-top:0.15rem}
    .test-by{font-size:0.73rem;color:#c0c7c6;margin-top:0.2rem}
    .badge{display:inline-block;padding:0.22rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;letter-spacing:0.04em;white-space:nowrap}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-blue{background:rgba(63,130,227,0.1);color:var(--blue)}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}

    .empty-state{text-align:center;padding:2rem;color:#9ab0ae;font-size:0.88rem}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-admin">Medtech Portal<br/><strong>Maria Santos</strong></div>
  <nav class="nav-links">
    <a href="dashboard.php" class="nav-link active">Dashboard</a>
    <a href="notifications.php" class="nav-link">Notifications</a>
    <a href="testlog.php" class="nav-link">Test Log</a>
    <a href="inventory.php" class="nav-link">Kits Inventory</a>
    <a href="kits.php" class="nav-link">Test Kits</a>
  </nav>
  <div class="sidebar-logout"><a href="logout.php" class="logout-btn">Log Out</a></div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Welcome back</div>
      <div style="font-size:0.95rem;font-weight:700;">Medtech Dashboard</div>
    </div>
    <div class="notification-wrap">
      <button class="notification-button" id="notificationButton" type="button" aria-label="Notifications" aria-expanded="false" onclick="toggleNotifications()">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span class="notification-badge">4</span>
      </button>
      <div class="notification-panel" id="notificationPanel">
        <div class="notification-panel-head"><strong>Notifications</strong><a href="notifications.php">See all</a></div>
        <a class="notification-item" href="notifications.php"><span class="notification-dot"></span><div><strong>New Complete Blood Count request</strong><span>John Doe · 15 minutes ago</span></div></a>
        <a class="notification-item" href="notifications.php"><span class="notification-dot"></span><div><strong>New Urinalysis request</strong><span>Jane Smith · 30 minutes ago</span></div></a>
        <a class="notification-item" href="notifications.php"><span class="notification-dot"></span><div><strong>New COVID-19 test request</strong><span>Robert Johnson · 45 minutes ago</span></div></a>
        <a class="notification-item" href="notifications.php"><span class="notification-dot"></span><div><strong>Blood Glucose test needs attention</strong><span>Maria Garcia · 1 hour ago</span></div></a>
      </div>
    </div>
  </div>

  <div class="page-content">

    <div class="kpi-grid">
      <div class="kpi-card accent-blue">
        <div class="kpi-icon blue">📊</div>
        <div class="kpi-value">48</div>
        <div class="kpi-label">Total Tests (30d)</div>
      </div>
      <div class="kpi-card accent-orange">
        <div class="kpi-icon orange">⏳</div>
        <div class="kpi-value">12</div>
        <div class="kpi-label">Pending / In Progress</div>
      </div>
      <div class="kpi-card accent-green">
        <div class="kpi-icon green">✓</div>
        <div class="kpi-value">35</div>
        <div class="kpi-label">Completed (30d)</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon blue">⏱</div>
        <div class="kpi-value">2.5</div>
        <div class="kpi-label">Avg Turnaround (hrs)</div>
      </div>
    </div>

    <div class="two-col">
      <div class="chart-section">
        <div class="chart-title">Test Volume — Last 7 Days</div>
        <div class="chart-subtitle">Daily test count trend</div>
        <div class="volume-chart" aria-label="Daily test counts for the last seven days">
          <div class="volume-bar-wrap"><span class="volume-count">6</span><div class="volume-bar" style="height:50%"></div><span class="volume-day">Aug 18</span></div>
          <div class="volume-bar-wrap"><span class="volume-count">8</span><div class="volume-bar" style="height:67%"></div><span class="volume-day">Aug 19</span></div>
          <div class="volume-bar-wrap"><span class="volume-count">5</span><div class="volume-bar" style="height:42%"></div><span class="volume-day">Aug 20</span></div>
          <div class="volume-bar-wrap"><span class="volume-count">12</span><div class="volume-bar" style="height:100%"></div><span class="volume-day">Aug 21</span></div>
          <div class="volume-bar-wrap"><span class="volume-count">9</span><div class="volume-bar" style="height:75%"></div><span class="volume-day">Aug 22</span></div>
          <div class="volume-bar-wrap"><span class="volume-count">7</span><div class="volume-bar" style="height:58%"></div><span class="volume-day">Aug 23</span></div>
          <div class="volume-bar-wrap"><span class="volume-count">3</span><div class="volume-bar" style="height:25%"></div><span class="volume-day">Aug 24</span></div>
        </div>
      </div>

      <div class="stat-box">
        <h3>Top Services (30d)</h3>
        <div class="stat-item"><div class="stat-name">Complete Blood Count (CBC)</div><div class="stat-count">18</div></div>
        <div class="stat-item"><div class="stat-name">Urinalysis</div><div class="stat-count">12</div></div>
        <div class="stat-item"><div class="stat-name">Blood Glucose (Fasting)</div><div class="stat-count">10</div></div>
        <div class="stat-item"><div class="stat-name">Lipid Panel</div><div class="stat-count">8</div></div>
        <div class="stat-item"><div class="stat-name">COVID-19 Rapid Antigen Test</div><div class="stat-count">6</div></div>
      </div>
    </div>

    <div class="recent-tests">
      <div class="recent-head">
        <h3>Today's Tests</h3>
        <a href="testlog.php" class="recent-link">View All →</a>
      </div>
      <div class="recent-body">
        <div class="test-row"><div class="test-info"><div class="test-receipt">LT-2026-001</div><div class="test-service">Complete Blood Count</div><div class="test-by">Requested by: Dr. Maria Santos</div></div><div style="display:flex;gap:0.5rem;align-items:center;"><span class="badge badge-blue">In Progress</span><span style="font-size:0.78rem;color:#9ab0ae;">9:15 AM</span></div></div>
        <div class="test-row"><div class="test-info"><div class="test-receipt">LT-2026-002</div><div class="test-service">Urinalysis</div><div class="test-by">Requested by: Dr. Juan Dela Cruz</div></div><div style="display:flex;gap:0.5rem;align-items:center;"><span class="badge badge-green">Completed</span><span style="font-size:0.78rem;color:#9ab0ae;">8:45 AM</span></div></div>
        <div class="test-row"><div class="test-info"><div class="test-receipt">LT-2026-003</div><div class="test-service">Blood Glucose Test</div><div class="test-by">Requested by: Dr. Maria Santos</div></div><div style="display:flex;gap:0.5rem;align-items:center;"><span class="badge badge-orange">Pending</span><span style="font-size:0.78rem;color:#9ab0ae;">8:30 AM</span></div></div>
        <div class="test-row"><div class="test-info"><div class="test-receipt">LT-2026-004</div><div class="test-service">Lipid Panel</div><div class="test-by">Requested by: Dr. Carlos Rivera</div></div><div style="display:flex;gap:0.5rem;align-items:center;"><span class="badge badge-green">Completed</span><span style="font-size:0.78rem;color:#9ab0ae;">8:00 AM</span></div></div>
        <div class="test-row"><div class="test-info"><div class="test-receipt">LT-2026-005</div><div class="test-service">COVID-19 Antigen Test</div><div class="test-by">Requested by: Dr. Maria Santos</div></div><div style="display:flex;gap:0.5rem;align-items:center;"><span class="badge badge-blue">In Progress</span><span style="font-size:0.78rem;color:#9ab0ae;">7:45 AM</span></div></div>
      </div>
    </div>

  </div>
</div>

<script>
  function toggleNotifications(){
    const panel = document.getElementById('notificationPanel');
    const button = document.getElementById('notificationButton');
    const isOpen = panel.classList.toggle('open');
    button.classList.toggle('active', isOpen);
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  }

  document.addEventListener('click', function(event){
    const wrapper = document.querySelector('.notification-wrap');
    if (!wrapper.contains(event.target)) {
      document.getElementById('notificationPanel').classList.remove('open');
      document.getElementById('notificationButton').classList.remove('active');
    }
  });
</script>
</body>
</html>