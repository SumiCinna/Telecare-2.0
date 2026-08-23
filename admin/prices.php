<?php
// admin/prices.php — read-only combined price reference.
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$items = [];
$pres = $conn->query("SELECT name, category, price, 'Product' AS kind FROM products WHERE status='Active'");
if ($pres) { while ($row = $pres->fetch_assoc()) { $items[] = $row; } }
$sres = $conn->query("SELECT name, category, price, 'Service' AS kind FROM services WHERE status='Active'");
if ($sres) { while ($row = $sres->fetch_assoc()) { $items[] = $row; } }
usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));

$activeNav = 'pos-prices';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Prices — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
  
    .main{flex:1;overflow-y:auto;margin-left:230px}
    .topbar{background:var(--white);padding:1rem 2rem;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .page-content{padding:2rem}
    .note{background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);color:var(--blue);padding:0.8rem 1.1rem;border-radius:12px;font-size:0.82rem;font-weight:600;margin-bottom:1.5rem}

    .controls-bar{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;margin-bottom:1.5rem}
    .search-wrap{position:relative;flex:1;min-width:220px;max-width:340px}
    .search-wrap svg{position:absolute;left:0.85rem;top:50%;transform:translateY(-50%);color:#9ab0ae;width:16px;height:16px}
    .search-input{width:100%;padding:0.62rem 0.9rem 0.62rem 2.3rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:50px;font-family:'DM Sans',sans-serif;font-size:0.85rem;color:var(--green);outline:none;background:var(--white)}
    .filter-tabs{display:flex;gap:0.5rem;flex-wrap:wrap}
    .filter-tab{padding:0.5rem 1rem;border-radius:50px;font-size:0.8rem;font-weight:600;border:1.5px solid rgba(36,68,65,0.12);background:var(--white);color:var(--green);cursor:pointer}
    .filter-tab.active{background:var(--green);border-color:var(--green);color:#fff}

    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse}
    th{padding:0.9rem 1.2rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;text-align:left;background:rgba(36,68,65,0.03);border-bottom:1px solid rgba(36,68,65,0.07)}
    td{padding:0.9rem 1.2rem;font-size:0.88rem;border-bottom:1px solid rgba(36,68,65,0.05)}
    tr:last-child td{border-bottom:none}
    .role-pill{display:inline-block;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.7rem;font-weight:700;background:rgba(36,68,65,0.08);color:var(--green)}
    .kind-pill{display:inline-block;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.7rem;font-weight:700}
    .kind-Product{background:rgba(63,130,227,0.1);color:var(--blue)}
    .kind-Service{background:rgba(195,54,67,0.1);color:var(--red)}
    .empty-row{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
    <div style="font-size:0.95rem;font-weight:700;">Prices</div>
  </div>

  <div class="page-content">
    <div class="note">Read-only reference. To change a price, edit the item in Products or Services directly.</div>

    <div class="controls-bar">
      <div class="search-wrap">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Search product or service..." oninput="applyFilters()"/>
      </div>
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all" onclick="setFilter('all')">All</button>
        <button class="filter-tab" data-filter="Product" onclick="setFilter('Product')">Products</button>
        <button class="filter-tab" data-filter="Service" onclick="setFilter('Service')">Services</button>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Type</th><th>Category</th><th>Price</th></tr></thead>
        <tbody id="pricesTableBody"></tbody>
      </table>
    </div>
    <div id="emptyState" class="empty-row" style="display:none;">No items match your search/filter.</div>
  </div>
</div>

<script>
const ALL_ITEMS = <?= json_encode(array_values($items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentFilter = 'all';

function setFilter(f) {
  currentFilter = f;
  document.querySelectorAll('.filter-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === f));
  applyFilters();
}

function applyFilters() {
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  let rows = ALL_ITEMS.filter(i => {
    if (currentFilter !== 'all' && i.kind !== currentFilter) return false;
    if (q && !i.name.toLowerCase().includes(q)) return false;
    return true;
  });
  renderRows(rows);
}

function renderRows(rows) {
  const tbody = document.getElementById('pricesTableBody');
  const empty = document.getElementById('emptyState');
  if (rows.length === 0) { tbody.innerHTML = ''; empty.style.display = 'block'; return; }
  empty.style.display = 'none';
  tbody.innerHTML = rows.map(i => `<tr>
    <td>${escHtml(i.name)}</td>
    <td><span class="kind-pill kind-${i.kind}">${i.kind}</span></td>
    <td><span class="role-pill">${escHtml(i.category)}</span></td>
    <td>₱${Number(i.price).toFixed(2)}</td>
  </tr>`).join('');
}

function escHtml(str) { if (!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

renderRows(ALL_ITEMS);
</script>
</body>
</html>