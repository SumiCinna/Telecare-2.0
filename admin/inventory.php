<?php
// admin/inventory.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

function summarize($conn, $table) {
    $res = $conn->query("SELECT quantity, reorder_level, expiry_date FROM $table");
    $total = 0; $expired = 0; $soon = 0; $low = 0;
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $total++;
            $isExpired = strtotime($r['expiry_date']) < strtotime(date('Y-m-d'));
            $isSoon    = !$isExpired && strtotime($r['expiry_date']) <= strtotime('+30 days');
            $isLow     = (int)$r['quantity'] <= (int)($r['reorder_level'] ?? 0);
            if ($isExpired) $expired++;
            if ($isSoon) $soon++;
            if ($isLow) $low++;
        }
    }
    return compact('total', 'expired', 'soon', 'low');
}

$meds = summarize($conn, 'medicines');
$kits = summarize($conn, 'lab_test_kits');

$combined = [
    'total'   => $meds['total']   + $kits['total'],
    'expired' => $meds['expired'] + $kits['expired'],
    'soon'    => $meds['soon']    + $kits['soon'],
    'low'     => $meds['low']     + $kits['low'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inventory — TELE-CARE</title>
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

    .stat-row{display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap}
    .stat-chip{background:var(--white);border-radius:14px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:0.9rem 1.3rem;min-width:150px}
    .stat-chip .num{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;line-height:1}
    .stat-chip .lbl{font-size:0.74rem;color:#9ab0ae;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-top:0.2rem}
    .stat-chip.warn .num{color:#d97706}
    .stat-chip.danger .num{color:var(--red)}

    .module-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.3rem}
    @media(max-width:800px){.module-grid{grid-template-columns:1fr}}

    .module-card{background:var(--white);border-radius:18px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.6rem;text-decoration:none;color:var(--green);display:block;transition:transform 0.15s, box-shadow 0.15s}
    .module-card:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(0,0,0,0.08)}
    .module-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:1.1rem}
    .module-icon svg{width:24px;height:24px;stroke:#fff}
    .module-icon.pharmacy{background:linear-gradient(135deg,var(--blue),#2563C4)}
    .module-icon.labkits{background:linear-gradient(135deg,var(--green),#0f2624)}
    .module-title{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:900;margin-bottom:0.3rem}
    .module-sub{font-size:0.82rem;color:#9ab0ae;margin-bottom:1.1rem}
    .module-stats{display:flex;gap:0.6rem;flex-wrap:wrap;margin-bottom:1.1rem}
    .mini-badge{font-size:0.72rem;font-weight:700;padding:0.25rem 0.6rem;border-radius:50px}
    .mini-badge.ok{background:rgba(0,0,0,0.05);color:#7a8f8c}
    .mini-badge.warn{background:rgba(245,158,11,0.1);color:#d97706}
    .mini-badge.danger{background:rgba(195,54,67,0.1);color:var(--red)}
    .module-cta{font-size:0.82rem;font-weight:700;color:var(--blue);display:flex;align-items:center;gap:0.35rem}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<?php $activeNav = 'inventory'; include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Inventory Overview</div>
    </div>
    <span style="font-size:0.82rem;color:#9ab0ae;">Combined across all modules</span>
  </div>

  <div class="page-content">

    <div class="stat-row">
      <div class="stat-chip"><div class="num"><?= $combined['total'] ?></div><div class="lbl">Total Items</div></div>
      <div class="stat-chip warn"><div class="num"><?= $combined['soon'] ?></div><div class="lbl">Expiring ≤30 Days</div></div>
      <div class="stat-chip danger"><div class="num"><?= $combined['expired'] ?></div><div class="lbl">Expired</div></div>
      <div class="stat-chip warn"><div class="num"><?= $combined['low'] ?></div><div class="lbl">Low Stock</div></div>
    </div>

    <div class="module-grid">

      <a href="inventory_meds.php" class="module-card">
        <div class="module-icon pharmacy">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 4h4m-2-2v4M6 8h12l-1 12a2 2 0 01-2 2H9a2 2 0 01-2-2L6 8z"/></svg>
        </div>
        <div class="module-title">Pharmacy Medicines</div>
        <div class="module-sub"><?= $meds['total'] ?> items on record</div>
        <div class="module-stats">
          <?php if ($meds['expired'] > 0): ?><span class="mini-badge danger"><?= $meds['expired'] ?> expired</span><?php endif; ?>
          <?php if ($meds['low'] > 0): ?><span class="mini-badge warn"><?= $meds['low'] ?> low stock</span><?php endif; ?>
          <?php if ($meds['soon'] > 0): ?><span class="mini-badge warn"><?= $meds['soon'] ?> expiring soon</span><?php endif; ?>
          <?php if ($meds['expired'] == 0 && $meds['low'] == 0 && $meds['soon'] == 0): ?><span class="mini-badge ok">All stock healthy</span><?php endif; ?>
        </div>
        <div class="module-cta">View Medicine Inventory →</div>
      </a>

      <a href="inventory_labkits.php" class="module-card">
        <div class="module-icon labkits">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4l4 9a2 2 0 01-2 3H7a2 2 0 01-2-3l4-9V3z"/><path stroke-linecap="round" d="M8 14h8"/></svg>
        </div>
        <div class="module-title">Lab Test Kits</div>
        <div class="module-sub"><?= $kits['total'] ?> items across all test panels</div>
        <div class="module-stats">
          <?php if ($kits['expired'] > 0): ?><span class="mini-badge danger"><?= $kits['expired'] ?> expired</span><?php endif; ?>
          <?php if ($kits['low'] > 0): ?><span class="mini-badge warn"><?= $kits['low'] ?> low stock</span><?php endif; ?>
          <?php if ($kits['soon'] > 0): ?><span class="mini-badge warn"><?= $kits['soon'] ?> expiring soon</span><?php endif; ?>
          <?php if ($kits['expired'] == 0 && $kits['low'] == 0 && $kits['soon'] == 0): ?><span class="mini-badge ok">All stock healthy</span><?php endif; ?>
        </div>
        <div class="module-cta">View Lab Kits Inventory →</div>
      </a>

    </div>

  </div>
</div>

<script>
  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>