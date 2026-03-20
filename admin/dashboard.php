<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$total_doctors  = $conn->query("SELECT COUNT(*) c FROM doctors")->fetch_assoc()['c'];
$total_patients = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$active_appts   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status IN ('Pending','Confirmed')")->fetch_assoc()['c'];
$completed_appts = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Completed'")->fetch_assoc()['c'];

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — TELE-CARE</title>
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
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.2rem;margin-bottom:2rem}
    .stat-card{background:var(--white);border-radius:16px;padding:1.4rem;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    .stat-card .label{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;margin-bottom:0.5rem}
    .stat-card .value{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;color:var(--green);line-height:1}
    .stat-card .sub{font-size:0.75rem;color:#9ab0ae;margin-top:0.3rem}
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem}
    .section-header h2{font-size:1.3rem}
    .btn-primary{display:inline-flex;align-items:center;gap:0.4rem;background:var(--red);color:#fff;padding:0.6rem 1.3rem;border-radius:50px;font-size:0.85rem;font-weight:600;border:none;cursor:pointer;transition:all 0.25s;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(195,54,67,0.25);text-decoration:none}
    .btn-primary:hover{background:#a82d38;transform:translateY(-1px)}
    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse}
    th{padding:0.9rem 1.2rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;text-align:left;background:rgba(36,68,65,0.03);border-bottom:1px solid rgba(36,68,65,0.07)}
    td{padding:0.9rem 1.2rem;font-size:0.88rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(36,68,65,0.02)}
    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}
    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    .empty-row{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}
    @media(max-width:900px){.sidebar{display:none}.stats-grid{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-admin">Admin Portal<br/><strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></strong></div>
  <nav class="nav-links">
    <a href="dashboard.php" class="nav-link active">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="doctors.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Doctors
    </a>
    <a href="patients.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patients
    </a>
    <a href="assignments.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Appointments
    </a>
  </nav>
  <div class="sidebar-logout">
    <a href="logout.php" class="logout-btn">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Log Out
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Good morning, Admin</div>
      <div style="font-size:0.95rem;font-weight:700;">Here's what's happening in TELE-CARE today.</div>
    </div>
    <a href="doctors.php" class="btn-primary">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Add Doctor
    </a>
  </div>

  <div class="page-content">
    <div class="stats-grid">
      <div class="stat-card"><div class="label">Total Doctors</div><div class="value"><?= $total_doctors ?></div><div class="sub">Registered</div></div>
      <div class="stat-card"><div class="label">Total Patients</div><div class="value"><?= $total_patients ?></div><div class="sub">Registered</div></div>
      <div class="stat-card"><div class="label">Active Appointments</div><div class="value"><?= $active_appts ?></div><div class="sub">Pending + Confirmed</div></div>
      <div class="stat-card"><div class="label">Completed</div><div class="value"><?= $completed_appts ?></div><div class="sub">All time</div></div>
    </div>

    <div class="section-header">
      <h2>Doctor Overview</h2>
      <a href="doctors.php" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Doctor</th><th>Specialty</th><th>Status</th></tr></thead>
        <tbody>
        <?php
        $dres = $conn->query("SELECT * FROM doctors ORDER BY created_at DESC LIMIT 10");
        if ($dres && $dres->num_rows > 0): while ($d = $dres->fetch_assoc()): ?>
        <tr>
          <td><span style="font-weight:600;">Dr. <?= htmlspecialchars($d['full_name']) ?></span></td>
          <td style="color:#9ab0ae;font-size:0.82rem;"><?= htmlspecialchars($d['specialty'] ?? '—') ?></td>
          <td><span class="badge <?= $d['status']==='active'?'badge-green':($d['status']==='pending'?'badge-orange':'badge-gray') ?>"><?= ucfirst($d['status']) ?></span></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="3" class="empty-row">No doctors yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>