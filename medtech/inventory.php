<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kits Inventory — TELE-CARE</title>
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

    .stat-row{display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
    .stat-chip{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:0.9rem 1.3rem;min-width:150px}
    .stat-chip .num{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;line-height:1}
    .stat-chip .lbl{font-size:0.74rem;color:#9ab0ae;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-top:0.2rem}
    .stat-chip.warn .num{color:#d97706}

    .cat-card{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:1.2rem;overflow:hidden}
    .cat-header{display:flex;align-items:center;gap:1rem;padding:1.2rem 1.5rem;cursor:pointer;user-select:none}
    .cat-header:hover{background:rgba(36,68,65,0.02)}
    .cat-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--blue),#2563C4);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem}
    .chevron{margin-left:auto;transition:transform 0.25s;color:#9ab0ae}
    .cat-card.open .chevron{transform:rotate(180deg)}
    .cat-body{display:none;padding:0 1.5rem 1.2rem}
    .cat-card.open .cat-body{display:block}

    .item-row{display:flex;align-items:center;justify-content:space-between;padding:0.8rem 0;border-bottom:1px solid rgba(36,68,65,0.05)}
    .item-row:last-child{border-bottom:none}
    .item-info{flex:1}
    .item-name{font-weight:700;font-size:0.88rem}
    .item-sub{font-size:0.74rem;color:#9ab0ae;margin-top:0.2rem}
    .item-qty{display:flex;align-items:center;gap:0.4rem}
    .qty-val{font-weight:700;font-size:0.88rem}
    .badge{display:inline-block;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}

    .empty-state{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<aside class="sidebar"><div class="sidebar-logo">TELE<span>-</span>CARE</div><div class="sidebar-admin">Medtech Portal<br/><strong>Maria Santos</strong></div><nav class="nav-links"><a href="dashboard.php" class="nav-link">Dashboard</a><a href="notifications.php" class="nav-link">Notifications</a><a href="testlog.php" class="nav-link">Test Log</a><a href="inventory.php" class="nav-link active">Kits Inventory</a><a href="kits.php" class="nav-link">Test Kits</a></nav><div class="sidebar-logout"><a href="logout.php" class="logout-btn">Log Out</a></div></aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Resources</div>
      <div style="font-size:0.95rem;font-weight:700;">Lab Kits Inventory</div>
    </div>
    <span style="font-size:0.82rem;color:#9ab0ae;">Available items for test kits</span>
  </div>

  <div class="page-content">

    <div class="stat-row">
      <div class="stat-chip"><div class="num">12</div><div class="lbl">Available Items</div></div>
      <div class="stat-chip warn"><div class="num">4</div><div class="lbl">Low Stock Items</div></div>
    </div>

      <div class="cat-card open" id="cat-1">
        <div class="cat-header" onclick="toggleCat(1)">
          <div class="cat-icon">🧬</div>
          <div style="flex:1;">
            <div style="font-weight:700;font-size:0.96rem;">Collection Kits</div>
            <div style="font-size:0.77rem;color:#9ab0ae;">3 items</div>
          </div>
          <svg class="chevron" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
        <div class="cat-body">
          <div class="item-row">
              <div class="item-info">
                <div class="item-name">Urine Specimen Container</div><div class="item-sub">BioPlas · 15ml</div>
              </div>
              <div class="item-qty">
                <span class="qty-val">50</span>
                <span style="font-size:0.73rem;color:#9ab0ae;">available</span>
              </div>
            </div>
          </div>
          <div class="item-row"><div class="item-info"><div class="item-name">Blood Collection Tube (EDTA)</div><div class="item-sub">Greiner · 3ml</div></div><div class="item-qty"><span class="qty-val" style="color:#d97706">8</span><span style="font-size:0.73rem;color:#9ab0ae;">available</span><span class="badge badge-orange">Low</span></div></div>
          <div class="item-row"><div class="item-info"><div class="item-name">Stool Specimen Container</div><div class="item-sub">Generic · 30ml</div></div><div class="item-qty"><span class="qty-val">25</span><span style="font-size:0.73rem;color:#9ab0ae;">available</span></div></div>
        </div>
      </div>
      <div class="cat-card" id="cat-2"><div class="cat-header" onclick="toggleCat(2)"><div class="cat-icon">🧬</div><div style="flex:1;"><div style="font-weight:700;font-size:0.96rem;">Diagnostic Kits</div><div style="font-size:0.77rem;color:#9ab0ae;">3 items</div></div><svg class="chevron" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></div><div class="cat-body"><div class="item-row"><div class="item-info"><div class="item-name">COVID-19 Rapid Antigen Test</div><div class="item-sub">Abbott · Individual</div></div><div class="item-qty"><span class="qty-val" style="color:#d97706">30</span><span style="font-size:0.73rem;color:#9ab0ae;">available</span><span class="badge badge-orange">Low</span></div></div><div class="item-row"><div class="item-info"><div class="item-name">Rapid Strep Test Kit</div><div class="item-sub">BD · Individual</div></div><div class="item-qty"><span class="qty-val">15</span><span style="font-size:0.73rem;color:#9ab0ae;">available</span></div></div></div></div>
      <div class="cat-card" id="cat-3"><div class="cat-header" onclick="toggleCat(3)"><div class="cat-icon">🧬</div><div style="flex:1;"><div style="font-weight:700;font-size:0.96rem;">Test Strips</div><div style="font-size:0.77rem;color:#9ab0ae;">2 items</div></div><svg class="chevron" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg></div><div class="cat-body"><div class="item-row"><div class="item-info"><div class="item-name">Blood Glucose Strip</div><div class="item-sub">Roche · 50-strip</div></div><div class="item-qty"><span class="qty-val">40</span><span style="font-size:0.73rem;color:#9ab0ae;">available</span></div></div><div class="item-row"><div class="item-info"><div class="item-name">Urine Test Strip</div><div class="item-sub">Siemens · 100-strip</div></div><div class="item-qty"><span class="qty-val" style="color:#d97706">3</span><span style="font-size:0.73rem;color:#9ab0ae;">available</span><span class="badge badge-orange">Low</span></div></div></div></div>

  </div>
</div>

<script>
  function toggleCat(id){
    document.getElementById('cat-' + id).classList.toggle('open');
  }

  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>