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
<<<<<<< HEAD
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
=======
  <title><?= htmlspecialchars($pageLabel) ?> — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:460px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}

    textarea.field-input{resize:vertical;min-height:70px}

    .back-link{display:inline-flex;align-items:center;gap:0.35rem;font-size:0.78rem;font-weight:600;color:#9ab0ae;text-decoration:none;margin-bottom:0.6rem}
    .back-link:hover{color:var(--dark,#151c27)}
    .type-pill{display:inline-flex;align-items:center;font-size:0.7rem;font-weight:700;letter-spacing:.02em;text-transform:uppercase;padding:0.2rem 0.6rem;border-radius:999px;margin-left:0.5rem}
    .type-pill.medicine{background:rgba(179,17,24,0.1);color:#B31118}
    .type-pill.testing{background:rgba(13,148,136,0.12);color:#0D9488}

    @media(max-width:900px){.sidebar{display:none}}
    @media(max-width:520px){.details-grid{grid-template-columns:1fr}.controls-bar{flex-direction:column;align-items:stretch}.search-wrap{max-width:none}}
>>>>>>> main
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<<<<<<< HEAD

<?php $activeNav = 'inventory'; include 'sidebar.php'; ?>
=======
<?php if ($toast_error): ?><div class="toast error">✕ <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>

<?php $activeNav = 'pos-products'; include 'sidebar.php'; ?>
>>>>>>> main

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
<<<<<<< HEAD
      <div style="font-size:0.95rem;font-weight:700;">Inventory Overview</div>
    </div>
    <span style="font-size:0.82rem;color:#9ab0ae;">Combined across all modules</span>
=======
      <div style="font-size:0.95rem;font-weight:700;">
        <?= htmlspecialchars($pageLabel) ?>
        <span class="type-pill <?= $currentCategory === 'Medicine' ? 'medicine' : 'testing' ?>"><?= htmlspecialchars($currentCategory) ?></span>
      </div>
    </div>
    <div style="display:flex;gap:0.6rem;">
      <button class="btn-primary" onclick="openModal('modal-add-product')">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add <?= $currentCategory === 'Medicine' ? 'Medicine' : 'Testing Kit' ?>
      </button>
    </div>
>>>>>>> main
  </div>

  <div class="page-content">

<<<<<<< HEAD
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
=======
    <a class="back-link" href="inventory_select.php">
      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      Back to Inventory Types
    </a>

    <div class="controls-bar">
      <div class="search-wrap">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Search <?= $currentCategory === 'Medicine' ? 'medicine' : 'testing kit' ?>..." oninput="applyFilters()"/>
      </div>
      <div class="filter-tabs" id="filterTabs">
        <button class="filter-tab active" data-filter="all" onclick="setFilter('all')">All</button>
        <button class="filter-tab" data-filter="lowstock" onclick="setFilter('lowstock')">Low Stock</button>
        <button class="filter-tab" data-filter="archived" onclick="setFilter('archived')">Archived</button>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Product</th><th>Unit</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody id="productsTableBody"></tbody>
      </table>
    </div>
    <div id="emptyState" class="empty-row" style="display:none;">No items match your search/filter.</div>
>>>>>>> main

  </div>
</div>

<<<<<<< HEAD
<script>
  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
=======
<!-- Add Product -->
<div class="modal-overlay" id="modal-add-product">
  <div class="modal">
    <h3>Add <?= $currentCategory === 'Medicine' ? 'Medicine' : 'Testing Kit' ?></h3>
    <form method="POST" id="add-product-form">
      <div class="form-field"><label class="field-label">Name *</label><input type="text" name="name" class="field-input" required/></div>
      <div class="form-field">
        <label class="field-label">Category</label>
        <input type="text" class="field-input" value="<?= htmlspecialchars($currentCategory) ?>" disabled/>
      </div>
      <div class="form-field"><label class="field-label">Description</label><textarea name="description" class="field-input" placeholder="<?= $currentCategory === 'Medicine' ? 'Pain reliever...' : 'Specimen container...' ?>"></textarea></div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">Unit *</label>
          <select name="unit" class="field-input" required>
            <?php foreach ($UNITS as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label class="field-label">Selling Price (₱) *</label><input type="number" step="0.01" min="0" name="price" class="field-input" required/></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Starting Quantity *</label><input type="number" min="0" name="stock_quantity" class="field-input" value="0" required/></div>
        <div class="form-field"><label class="field-label">Reorder Level *</label><input type="number" min="0" name="reorder_level" class="field-input" value="0" required/></div>
      </div>
      <button type="submit" name="add_product" class="btn-submit">Add <?= $currentCategory === 'Medicine' ? 'Medicine' : 'Testing Kit' ?></button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-add-product')">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Product -->
<div class="modal-overlay" id="modal-edit-product">
  <div class="modal">
    <h3>Edit <?= $currentCategory === 'Medicine' ? 'Medicine' : 'Testing Kit' ?></h3>
    <form method="POST" id="edit-product-form">
      <input type="hidden" name="product_id" id="edit-product-id"/>
      <div class="form-field"><label class="field-label">Name *</label><input type="text" name="name" id="edit-product-name" class="field-input" required/></div>
      <div class="form-field">
        <label class="field-label">Category</label>
        <input type="text" class="field-input" value="<?= htmlspecialchars($currentCategory) ?>" disabled/>
      </div>
      <div class="form-field"><label class="field-label">Description</label><textarea name="description" id="edit-product-description" class="field-input"></textarea></div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">Unit *</label>
          <select name="unit" id="edit-product-unit" class="field-input" required>
            <?php foreach ($UNITS as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label class="field-label">Selling Price (₱) *</label><input type="number" step="0.01" min="0" name="price" id="edit-product-price" class="field-input" required/></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Quantity *</label><input type="number" min="0" name="stock_quantity" id="edit-product-stock" class="field-input" required/></div>
        <div class="form-field"><label class="field-label">Reorder Level *</label><input type="number" min="0" name="reorder_level" id="edit-product-reorder" class="field-input" required/></div>
      </div>
      <button type="submit" name="edit_product" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-product')">Cancel</button>
    </form>
  </div>
</div>

<!-- View Product -->
<div class="modal-overlay" id="modal-view-product">
  <div class="modal">
    <h3 id="vpr-title">Item Details</h3>
    <div class="details-grid">
      <div class="detail-item"><div class="detail-item-label">Category</div><div class="detail-item-value" id="vpr-category"></div></div>
      <div class="detail-item"><div class="detail-item-label">Unit</div><div class="detail-item-value" id="vpr-unit"></div></div>
      <div class="detail-item"><div class="detail-item-label">Price</div><div class="detail-item-value" id="vpr-price"></div></div>
      <div class="detail-item"><div class="detail-item-label">Stock</div><div class="detail-item-value" id="vpr-stock"></div></div>
      <div class="detail-item"><div class="detail-item-label">Reorder Level</div><div class="detail-item-value" id="vpr-reorder"></div></div>
      <div class="detail-item"><div class="detail-item-label">Status</div><div class="detail-item-value" id="vpr-status"></div></div>
      <div class="detail-item full"><div class="detail-item-label">Description</div><div class="detail-item-value" id="vpr-description"></div></div>
    </div>
    <button class="btn-cancel" onclick="closeModal('modal-view-product')">Close</button>
  </div>
</div>

<script>
const ALL_PRODUCTS = <?= json_encode(array_values($allProducts), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentFilter = 'all';

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function setFilter(f) {
  currentFilter = f;
  document.querySelectorAll('.filter-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === f));
  applyFilters();
}

function applyFilters() {
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  let rows = ALL_PRODUCTS.filter(p => {
    if (currentFilter === 'archived') { if (p.status !== 'Archived') return false; }
    else if (p.status === 'Archived') return false;

    if (currentFilter === 'lowstock' && !(Number(p.stock_quantity) <= Number(p.reorder_level))) return false;

    if (q && !p.name.toLowerCase().includes(q)) return false;
    return true;
  });
  renderRows(rows);
}

function statusBadge(status) {
  return status === 'Active' ? `<span class="badge badge-green">Active</span>` : `<span class="badge badge-gray">Archived</span>`;
}

function renderRows(rows) {
  const tbody = document.getElementById('productsTableBody');
  const empty = document.getElementById('emptyState');

  if (rows.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  tbody.innerHTML = rows.map(p => {
    const low = Number(p.stock_quantity) <= Number(p.reorder_level);
    let actions = `<button class="btn-sm btn-green" onclick="openViewModal(${p.id})">View</button>`;
    if (p.status === 'Active') {
      actions += `<button class="btn-sm btn-edit" onclick="openEditModal(${p.id})">Edit</button>`;
      actions += `<a href="?type=<?= htmlspecialchars($typeSlug) ?>&archive_product=${p.id}" class="btn-sm btn-red" onclick="var el=this;event.preventDefault();showConfirm('Archive ${escAttr(p.name)}? It will no longer be available for new POS transactions.').then(function(ok){if(ok)window.location=el.href});return false;">Archive</a>`;
    } else {
      actions += `<a href="?type=<?= htmlspecialchars($typeSlug) ?>&restore_product=${p.id}" class="btn-sm btn-activate" onclick="var el=this;event.preventDefault();showConfirm('Restore ${escAttr(p.name)}?').then(function(ok){if(ok)window.location=el.href});return false;">Restore</a>`;
    }
    return `<tr>
      <td>${escHtml(p.name)}</td>
      <td>${escHtml(p.unit)}</td>
      <td>₱${Number(p.price).toFixed(2)}</td>
      <td>${p.stock_quantity}${low && p.status === 'Active' ? ' <span class="badge badge-orange">Low</span>' : ''}</td>
      <td>${statusBadge(p.status)}</td>
      <td><div class="actions-cell">${actions}</div></td>
    </tr>`;
  }).join('');
}

function openEditModal(id) {
  const p = ALL_PRODUCTS.find(x => x.id == id);
  if (!p) return;
  document.getElementById('edit-product-id').value = p.id;
  document.getElementById('edit-product-name').value = p.name;
  document.getElementById('edit-product-description').value = p.description || '';
  document.getElementById('edit-product-unit').value = p.unit;
  document.getElementById('edit-product-price').value = p.price;
  document.getElementById('edit-product-stock').value = p.stock_quantity;
  document.getElementById('edit-product-reorder').value = p.reorder_level;
  openModal('modal-edit-product');
}

function openViewModal(id) {
  const p = ALL_PRODUCTS.find(x => x.id == id);
  if (!p) return;
  document.getElementById('vpr-title').textContent = p.name;
  document.getElementById('vpr-category').textContent = p.category;
  document.getElementById('vpr-unit').textContent = p.unit;
  document.getElementById('vpr-price').textContent = '₱' + Number(p.price).toFixed(2);
  document.getElementById('vpr-stock').textContent = p.stock_quantity;
  document.getElementById('vpr-reorder').textContent = p.reorder_level;
  document.getElementById('vpr-status').textContent = p.status;
  document.getElementById('vpr-description').textContent = p.description || 'No description';
  openModal('modal-view-product');
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(str) { return escHtml(str).replace(/'/g, "\\'"); }

renderRows(ALL_PRODUCTS.filter(p => p.status === 'Active'));
setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);

let _confirmResolve = null;
function showConfirm(message) {
  return new Promise(function(resolve) {
    _confirmResolve = resolve;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmModal').classList.add('open');
  });
}
function confirmResolve(value) {
  document.getElementById('confirmModal').classList.remove('open');
  if (_confirmResolve) { _confirmResolve(value); _confirmResolve = null; }
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && document.getElementById('confirmModal').classList.contains('open')) {
    confirmResolve(false);
  }
});
</script>
<div id="confirmModal" class="confirm-overlay" onclick="if(event.target===this)confirmResolve(false)">
  <div class="confirm-box">
    <h3 id="confirmTitle">Confirm Action</h3>
    <p id="confirmMessage"></p>
    <div class="confirm-actions">
      <button class="btn-confirm-no" onclick="confirmResolve(false)">Cancel</button>
      <button class="btn-confirm-yes" onclick="confirmResolve(true)">Yes, Proceed</button>
    </div>
  </div>
</div>
>>>>>>> main
</body>
</html>