<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// ── Toggle active status ──
if (isset($_GET['toggle_active'])) {
    $pid   = (int)$_GET['pid'];
    $cur   = (int)$_GET['active'];
    $new   = $cur ? 0 : 1;
    $conn->query("UPDATE patients SET is_active=$new WHERE id=$pid");
    $_SESSION['toast'] = $new ? 'Account activated.' : 'Account deactivated.';
    header('Location: patients.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Patients — TELE-CARE</title>
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
    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse}
    th{padding:0.9rem 1.2rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;text-align:left;background:rgba(36,68,65,0.03);border-bottom:1px solid rgba(36,68,65,0.07)}
    td{padding:0.9rem 1.2rem;font-size:0.88rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(36,68,65,0.02)}
    tr.inactive-row td{opacity:0.5}
    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-red{background:rgba(195,54,67,0.1);color:var(--red)}
    .btn-sm{padding:0.38rem 0.85rem;border-radius:50px;font-size:0.76rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-decoration:none;display:inline-block;white-space:nowrap}
    .btn-activate{background:rgba(34,197,94,0.1);color:#16a34a}.btn-activate:hover{background:#16a34a;color:#fff}
    .btn-deactivate{background:rgba(195,54,67,0.1);color:var(--red)}.btn-deactivate:hover{background:var(--red);color:#fff}
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
    <a href="patients.php" class="nav-link active">
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
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Patients</div>
    </div>
  </div>

  <div class="page-content">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Patient</th><th>Contact</th><th>Joined</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php
        $pres = $conn->query("SELECT * FROM patients ORDER BY created_at DESC");
        if ($pres && $pres->num_rows > 0): while ($pt = $pres->fetch_assoc()):
            $isActive = isset($pt['is_active']) ? (int)$pt['is_active'] : 1;
            $safeName = htmlspecialchars(addslashes($pt['full_name']));
        ?>
        <tr class="<?= !$isActive ? 'inactive-row' : '' ?>">
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($pt['full_name']) ?></div>
            <div style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($pt['email']) ?></div>
          </td>
          <td style="font-size:0.82rem;color:#9ab0ae;"><?= htmlspecialchars($pt['phone_number'] ?? '—') ?></td>
          <td style="font-size:0.78rem;color:#9ab0ae;"><?= date('M d, Y', strtotime($pt['created_at'])) ?></td>
          <td>
            <span class="badge <?= $isActive ? 'badge-green' : 'badge-red' ?>">
              <?= $isActive ? 'Active' : 'Deactivated' ?>
            </span>
          </td>
          <td>
            <?php if ($isActive): ?>
              <a href="?toggle_active=1&pid=<?= $pt['id'] ?>&active=1"
                 class="btn-sm btn-deactivate"
                 onclick="return confirm('Deactivate <?= $safeName ?>\'s account? They will no longer be able to log in.')">
                Deactivate
              </a>
            <?php else: ?>
              <a href="?toggle_active=1&pid=<?= $pt['id'] ?>&active=0"
                 class="btn-sm btn-activate"
                 onclick="return confirm('Activate <?= $safeName ?>\'s account?')">
                Activate
              </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" class="empty-row">No patients registered yet.</td></tr>
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