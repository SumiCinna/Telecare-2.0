<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications — TELE-CARE</title>
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
    .page-content{padding:2rem}

    .stat-chip{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);display:inline-block;padding:0.7rem 1.2rem;margin-bottom:1.5rem}
    .stat-chip .num{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--red)}
    .stat-chip .lbl{font-size:0.73rem;color:#9ab0ae;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-top:0.2rem}

    .notif-card{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.4rem;margin-bottom:1rem;display:flex;gap:1.2rem;align-items:flex-start}
    .notif-badge{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--blue),#2563C4);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.3rem}
    .notif-content{flex:1}
    .notif-header{display:flex;align-items:flex-start;justify-content:space-between;gap:0.8rem;margin-bottom:0.6rem}
    .notif-title{font-weight:700;font-size:0.96rem}
    .notif-meta{display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;margin-bottom:0.6rem}
    .notif-meta span{font-size:0.75rem;color:#9ab0ae}
    .notif-action{display:flex;gap:0.6rem}
    .btn-accept{background:var(--green);color:#fff;border:none;padding:0.5rem 1rem;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer;transition:opacity 0.2s}
    .btn-accept:hover{opacity:0.88}
    .btn-dismiss{background:none;border:1px solid rgba(36,68,65,0.15);color:var(--green);padding:0.5rem 1rem;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:0.8rem;font-weight:700;cursor:pointer}

    .empty-state{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.9rem}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<aside class="sidebar"><div class="sidebar-logo">TELE<span>-</span>CARE</div><div class="sidebar-admin">Medtech Portal<br/><strong>Maria Santos</strong></div><nav class="nav-links"><a href="dashboard.php" class="nav-link">Dashboard</a><a href="notifications.php" class="nav-link active">Notifications</a><a href="testlog.php" class="nav-link">Test Log</a><a href="inventory.php" class="nav-link">Kits Inventory</a><a href="kits.php" class="nav-link">Test Kits</a></nav><div class="sidebar-logout"><a href="logout.php" class="logout-btn">Log Out</a></div></aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Notifications</div>
      <div style="font-size:0.95rem;font-weight:700;">Lab Test Requests</div>
    </div>
  </div>

  <div class="page-content">
    
    <div class="stat-chip">
      <div class="num">4</div>
      <div class="lbl">Pending Requests</div>
    </div>

      <div class="notif-card">
        <div class="notif-badge">🧪</div>
        <div class="notif-content">
          <div class="notif-header">
            <div>
              <div class="notif-title">Complete Blood Count</div>
              <div class="notif-meta">
                <span>Receipt: LT-2026-010</span><span>Patient: John Doe</span><span>Aug 24, 9:45 AM</span>
              </div>
            </div>
          </div>
          <div class="notif-meta" style="margin-bottom:1rem;border-top:1px solid rgba(36,68,65,0.06);padding-top:0.6rem;">
            <span style="color:#7a8f8c;">Requested by: Dr. Maria Santos</span>
          </div>
          <div class="notif-action">
            <button class="btn-accept" onclick="acceptTest(this)">Accept & Start</button>
            <button class="btn-dismiss" onclick="dismissNotif(this)">Dismiss</button>
          </div>
        </div>
      </div>
      <div class="notif-card"><div class="notif-badge">🧪</div><div class="notif-content"><div class="notif-header"><div><div class="notif-title">Urinalysis</div><div class="notif-meta"><span>Receipt: LT-2026-011</span><span>Patient: Jane Smith</span><span>Aug 24, 9:30 AM</span></div></div></div><div class="notif-meta" style="margin-bottom:1rem;border-top:1px solid rgba(36,68,65,0.06);padding-top:0.6rem;"><span style="color:#7a8f8c;">Requested by: Dr. Juan Dela Cruz</span></div><div class="notif-action"><button class="btn-accept" onclick="acceptTest(this)">Accept & Start</button><button class="btn-dismiss" onclick="dismissNotif(this)">Dismiss</button></div></div></div>
      <div class="notif-card"><div class="notif-badge">🧪</div><div class="notif-content"><div class="notif-header"><div><div class="notif-title">COVID-19 Rapid Antigen Test</div><div class="notif-meta"><span>Receipt: LT-2026-012</span><span>Patient: Robert Johnson</span><span>Aug 24, 9:00 AM</span></div></div></div><div class="notif-meta" style="margin-bottom:1rem;border-top:1px solid rgba(36,68,65,0.06);padding-top:0.6rem;"><span style="color:#7a8f8c;">Requested by: Dr. Maria Santos</span></div><div class="notif-action"><button class="btn-accept" onclick="acceptTest(this)">Accept & Start</button><button class="btn-dismiss" onclick="dismissNotif(this)">Dismiss</button></div></div></div>
      <div class="notif-card"><div class="notif-badge">🧪</div><div class="notif-content"><div class="notif-header"><div><div class="notif-title">Blood Glucose Test</div><div class="notif-meta"><span>Receipt: LT-2026-013</span><span>Patient: Maria Garcia</span><span>Aug 24, 8:15 AM</span><span style="color:var(--red);">Repeats: 2</span></div></div></div><div class="notif-meta" style="margin-bottom:1rem;border-top:1px solid rgba(36,68,65,0.06);padding-top:0.6rem;"><span style="color:#7a8f8c;">Requested by: Dr. Carlos Rivera</span></div><div class="notif-action"><button class="btn-accept" onclick="acceptTest(this)">Accept & Start</button><button class="btn-dismiss" onclick="dismissNotif(this)">Dismiss</button></div></div></div>

  </div>
</div>

<script>
  function acceptTest(btn){
    const card = btn.closest('.notif-card');
    const receiptNo = card.querySelector('.notif-header .notif-title').textContent;
    
    // Animate accept
    card.style.opacity = '0';
    card.style.transition = 'opacity 0.3s ease';
    
    setTimeout(() => {
      card.remove();
      // Show toast
      const toast = document.createElement('div');
      toast.className = 'toast';
      toast.textContent = '✓ Test accepted! Status: In Progress';
      document.body.appendChild(toast);
      setTimeout(() => toast.remove(), 3500);
    }, 300);
  }
  
  function dismissNotif(btn){
    const card = btn.closest('.notif-card');
    card.style.opacity = '0';
    card.style.transition = 'opacity 0.3s ease';
    setTimeout(() => card.remove(), 300);
  }

  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>