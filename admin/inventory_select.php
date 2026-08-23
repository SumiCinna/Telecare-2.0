<?php
// admin/inventory_select.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

// Quick counts so the cards show live numbers (nice-to-have, safe if it fails)
$medicineCount = 0;
$testingKitCount = 0;
$r = $conn->query("SELECT category, COUNT(*) c FROM products WHERE status='Active' GROUP BY category");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['category'] === 'Medicine')      $medicineCount   = (int)$row['c'];
        if ($row['category'] === 'Testing Kits')  $testingKitCount = (int)$row['c'];
    }
}

$activeNav = 'pos-products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Inventory — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    .select-wrap{max-width:900px}
    .select-title{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--dark, #151c27);margin-bottom:0.35rem}
    .select-sub{color:#9ab0ae;font-size:0.9rem;margin-bottom:2rem}
    .type-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem}
    .type-card{background:var(--white,#fff);border:1px solid #e7edec;border-radius:18px;padding:1.75rem;text-decoration:none;color:inherit;display:block;transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
    .type-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(21,28,39,0.08);border-color:#d8e2e0}
    .type-icon{width:56px;height:56px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin-bottom:1.1rem}
    .type-icon.medicine{background:rgba(179,17,24,0.1);color:#B31118}
    .type-icon.testing{background:rgba(13,148,136,0.12);color:#0D9488}
    .type-card h3{font-size:1.15rem;font-weight:700;margin-bottom:0.4rem}
    .type-card p{font-size:0.85rem;color:#7d8f8d;margin-bottom:0.9rem}
    .type-count{font-size:0.75rem;font-weight:600;color:#9ab0ae}
    @media(max-width:900px){.sidebar{display:none}}
    @media(max-width:640px){.type-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Inventory</div>
    </div>
  </div>

  <div class="page-content">
    <div class="select-wrap">
      <div class="select-title">Select Inventory Type</div>
      <div class="select-sub">Choose which inventory you want to manage. Medicine and testing kits are tracked separately.</div>

      <div class="type-grid">
        <a class="type-card" href="inventory.php?type=medicine">
          <div class="type-icon medicine">
            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5l6 6m-9 5l6-6 3.5 3.5a3 3 0 01-4.24 4.24L4 12"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 4.5a3 3 0 014.24 4.24l-1.24 1.26-4.24-4.24 1.24-1.26z"/>
            </svg>
          </div>
          <h3>Medicine</h3>
          <p>Manage pharmacy stock — tablets, capsules, syrups, and other medicines.</p>
          <div class="type-count"><?= $medicineCount ?> active item<?= $medicineCount === 1 ? '' : 's' ?></div>
        </a>

        <a class="type-card" href="inventory.php?type=testing-kits">
          <div class="type-icon testing">
            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 2h6M10 2v6.5L4.8 17a2 2 0 001.7 3h11a2 2 0 001.7-3L14 8.5V2"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 14h11"/>
            </svg>
          </div>
          <h3>Testing Kits</h3>
          <p>Manage lab/testing supplies — containers, sticks, and other kit components.</p>
          <div class="type-count"><?= $testingKitCount ?> active item<?= $testingKitCount === 1 ? '' : 's' ?></div>
        </a>
      </div>
    </div>
  </div>
</div>

</body>
</html>