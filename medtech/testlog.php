<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Test Log — TELE-CARE</title>
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

    .filter-bar{display:flex;align-items:center;gap:0.8rem;margin-bottom:1.2rem;flex-wrap:wrap}
    .search-input{flex:1;min-width:220px;padding:0.6rem 1rem;border-radius:10px;border:1px solid rgba(36,68,65,0.15);font-family:'DM Sans',sans-serif;font-size:0.85rem;background:var(--white)}
    .search-input:focus{outline:none;border-color:var(--blue)}
    select.filter-select{padding:0.6rem 0.9rem;border-radius:10px;border:1px solid rgba(36,68,65,0.15);font-family:'DM Sans',sans-serif;font-size:0.83rem;background:var(--white);color:var(--green)}

    .table-card{background:var(--white);border-radius:18px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);overflow-x:auto}
    table{width:100%;border-collapse:collapse;min-width:920px}
    thead th{text-align:left;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#9ab0ae;padding:0.9rem 1rem;border-bottom:1px solid rgba(36,68,65,0.08);white-space:nowrap}
    tbody td{padding:0.85rem 1rem;font-size:0.86rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tbody tr:last-child td{border-bottom:none}
    tbody tr:hover{background:rgba(36,68,65,0.02)}
    .cell-receipt{font-weight:700}

    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em;white-space:nowrap}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-blue{background:rgba(63,130,227,0.1);color:var(--blue)}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}

    .empty-state{text-align:center;padding:3.5rem 1rem;color:#9ab0ae;font-size:0.88rem}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<aside class="sidebar"><div class="sidebar-logo">TELE<span>-</span>CARE</div><div class="sidebar-admin">Medtech Portal<br/><strong>Maria Santos</strong></div><nav class="nav-links"><a href="dashboard.php" class="nav-link">Dashboard</a><a href="notifications.php" class="nav-link">Notifications</a><a href="testlog.php" class="nav-link active">Test Log</a><a href="inventory.php" class="nav-link">Kits Inventory</a><a href="kits.php" class="nav-link">Test Kits</a></nav><div class="sidebar-logout"><a href="logout.php" class="logout-btn">Log Out</a></div></aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Records</div>
      <div style="font-size:0.95rem;font-weight:700;">Lab Test Log</div>
    </div>
  </div>

  <div class="page-content">

    <div class="filter-bar">
      <input type="text" id="searchInput" class="search-input" placeholder="Search receipt number or service..." oninput="filterTable()"/>
      <select id="statusFilter" class="filter-select" onchange="filterTable()">
        <option value="all">All Statuses</option>
        <option value="Pending">Pending</option>
        <option value="In Progress">In Progress</option>
        <option value="Completed">Completed</option>
        <option value="Cancelled">Cancelled</option>
      </select>
    </div>

    <div class="table-card">
      <table id="testTable">
        <thead>
          <tr>
            <th>Receipt No.</th>
            <th>Date / Time</th>
            <th>Service / Test</th>
            <th>Repeats</th>
            <th>Medtech Responsible</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr data-status="In Progress" data-search="lt-2026-001 complete blood count"><td><div class="cell-receipt">LT-2026-001</div></td><td>Aug 24, 2026 9:15 AM</td><td>Complete Blood Count</td><td>—</td><td>Maria Santos</td><td><span class="badge badge-blue">In Progress</span></td></tr>
          <tr data-status="Completed" data-search="lt-2026-002 urinalysis"><td><div class="cell-receipt">LT-2026-002</div></td><td>Aug 24, 2026 8:45 AM</td><td>Urinalysis</td><td>—</td><td>Maria Santos</td><td><span class="badge badge-green">Completed</span></td></tr>
          <tr data-status="Pending" data-search="lt-2026-003 blood glucose test"><td><div class="cell-receipt">LT-2026-003</div></td><td>Aug 24, 2026 8:30 AM</td><td>Blood Glucose Test</td><td>—</td><td>—</td><td><span class="badge badge-orange">Pending</span></td></tr>
          <tr data-status="Completed" data-search="lt-2026-004 lipid panel"><td><div class="cell-receipt">LT-2026-004</div></td><td>Aug 24, 2026 8:00 AM</td><td>Lipid Panel</td><td>—</td><td>Maria Santos</td><td><span class="badge badge-green">Completed</span></td></tr>
          <tr data-status="In Progress" data-search="lt-2026-005 covid-19 antigen test"><td><div class="cell-receipt">LT-2026-005</div></td><td>Aug 24, 2026 7:45 AM</td><td>COVID-19 Antigen Test</td><td>—</td><td>Maria Santos</td><td><span class="badge badge-blue">In Progress</span></td></tr>
          <tr data-status="Completed" data-search="lt-2026-006 thyroid function test"><td><div class="cell-receipt">LT-2026-006</div></td><td>Aug 23, 2026 4:20 PM</td><td>Thyroid Function Test</td><td>—</td><td>Maria Santos</td><td><span class="badge badge-green">Completed</span></td></tr>
          <tr data-status="Cancelled" data-search="lt-2026-007 pregnancy test"><td><div class="cell-receipt">LT-2026-007</div></td><td>Aug 23, 2026 2:10 PM</td><td>Pregnancy Test</td><td>—</td><td>—</td><td><span class="badge badge-gray">Cancelled</span></td></tr>
          <tr data-status="Completed" data-search="lt-2026-008 liver function test"><td><div class="cell-receipt">LT-2026-008</div></td><td>Aug 23, 2026 11:30 AM</td><td>Liver Function Test</td><td>2x</td><td>Maria Santos</td><td><span class="badge badge-green">Completed</span></td></tr>
        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
  function filterTable(){
    const q = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    document.querySelectorAll('#testTable tbody tr[data-status]').forEach(row => {
      const matchesSearch = row.dataset.search.includes(q);
      const matchesStatus = status === 'all' || row.dataset.status === status;
      row.style.display = (matchesSearch && matchesStatus) ? 'table-row' : 'none';
    });
  }

  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>