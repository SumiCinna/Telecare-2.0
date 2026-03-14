<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

if (isset($_GET['unassign'])) {
    $pid = (int)$_GET['pid'];
    $did = (int)$_GET['did'];
    $conn->query("DELETE FROM patient_doctors WHERE patient_id=$pid AND doctor_id=$did");
    $_SESSION['toast'] = "Patient unassigned.";
    header('Location: assignments.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Assignments — TELE-CARE</title>
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
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem}
    .section-header h2{font-size:1.3rem}
    .doc-avatar{width:40px;height:40px;border-radius:14px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;flex-shrink:0}
    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
    .badge-blue{background:rgba(63,130,227,0.1);color:var(--blue)}
    .assign-card{background:var(--white);border-radius:16px;padding:1.3rem;border:1px solid rgba(36,68,65,0.07);margin-bottom:1rem;box-shadow:0 2px 8px rgba(0,0,0,0.03)}
    .patient-chip{display:inline-flex;align-items:center;gap:0.5rem;background:rgba(36,68,65,0.07);border-radius:50px;padding:0.3rem 0.7rem;font-size:0.78rem;font-weight:600;color:var(--green);margin:0.25rem}
    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    .empty-row{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-admin">Admin Portal<br/><strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></strong></div>
  <nav class="nav-links">
    <a href="dashboard.php" class="nav-link">
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
    <a href="assignments.php" class="nav-link active">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Assignments
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
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Assignments</div>
    </div>
    <span style="font-size:0.82rem;color:#9ab0ae;">Doctor-centric patient roster</span>
  </div>

  <div class="page-content">
    <?php
    $ares = $conn->query("SELECT * FROM doctors WHERE status='active' ORDER BY full_name");
    if ($ares && $ares->num_rows > 0):
      while ($doc = $ares->fetch_assoc()):
        $pts = $conn->query("SELECT p.id, p.full_name FROM patients p JOIN patient_doctors pd ON pd.patient_id=p.id WHERE pd.doctor_id={$doc['id']}");
        $cnt = $conn->query("SELECT COUNT(*) c FROM patient_doctors WHERE doctor_id={$doc['id']}")->fetch_assoc()['c'];
    ?>
    <div class="assign-card">
      <div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:0.9rem;">
        <div class="doc-avatar"><?= strtoupper(substr($doc['full_name'],0,2)) ?></div>
        <div style="flex:1;">
          <div style="font-weight:700;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
          <div style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($doc['specialty'] ?? '—') ?></div>
        </div>
        <span class="badge badge-blue"><?= $cnt ?> patient<?= $cnt!=1?'s':'' ?></span>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">
        <?php if ($pts && $pts->num_rows > 0): while ($pp = $pts->fetch_assoc()): ?>
        <span class="patient-chip">
          <?= htmlspecialchars($pp['full_name']) ?>
          <a href="?unassign=1&pid=<?= $pp['id'] ?>&did=<?= $doc['id'] ?>" onclick="return confirm('Remove patient?')" style="color:#9ab0ae;margin-left:0.2rem;text-decoration:none;">✕</a>
        </span>
        <?php endwhile; else: ?>
        <span style="font-size:0.8rem;color:#9ab0ae;">No patients assigned.</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endwhile; else: ?>
    <div class="table-wrap"><div class="empty-row">No active doctors yet.</div></div>
    <?php endif; ?>
  </div>
</div>

<script>
  setTimeout(() => { const t=document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>