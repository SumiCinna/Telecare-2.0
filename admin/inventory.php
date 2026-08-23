<?php
// admin/inventory.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];

/* ══════════════════════════════════════════════
   INVENTORY TYPE (Medicine vs Testing Kits)
   Everything below this block is scoped to ONE
   category only — chosen on inventory_select.php.
   ══════════════════════════════════════════════ */
$TYPE_MAP = [
    'medicine'      => 'Medicine',
    'testing-kits'  => 'Testing Kits',
];

// Allow the type to come from the query string, and remember it in session
// so form POSTs (which don't carry ?type=) still know the current context.
$typeSlug = $_GET['type'] ?? ($_SESSION['inventory_type'] ?? null);

if (!$typeSlug || !isset($TYPE_MAP[$typeSlug])) {
    header('Location: inventory_select.php'); exit;
}
$_SESSION['inventory_type'] = $typeSlug;
$currentCategory = $TYPE_MAP[$typeSlug]; // 'Medicine' or 'Testing Kits'

$UNITS = ['Tablet', 'Capsule', 'Bottle', 'Box', 'Vial', 'Piece', 'Pack', 'Syrup', 'Kit', 'Set'];

// ── Ensure audit_logs table exists (same fallback as Users.php) ──
$conn->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` int NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (!function_exists('log_audit')) {
    function log_audit($conn, $admin_id, $action, $entity_type, $entity_id, $old = null, $new = null) {
        $old_json = $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES);
        $new_json = $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES);
        $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $admin_id, $action, $entity_type, $entity_id, $old_json, $new_json);
        $stmt->execute();
    }
}

/* ══════════════════════════════════════════════
   ACTIONS
   (redirect_url always keeps ?type= so the admin
   lands back on the SAME inventory page)
   ══════════════════════════════════════════════ */
$redirect_url = 'inventory.php?type=' . urlencode($typeSlug);

// ── Add product ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name          = trim($_POST['name'] ?? '');
    $category      = $currentCategory; // locked to the page's current type
    $description   = trim($_POST['description'] ?? '');
    $unit          = $_POST['unit'] ?? '';
    $price         = $_POST['price'] ?? '';
    $reorder_level = $_POST['reorder_level'] ?? 0;
    $stock_quantity = $_POST['stock_quantity'] ?? 0;

    $errors = [];
    if (!$name) $errors[] = 'Product name is required.';
    if (!in_array($unit, $UNITS, true)) $errors[] = 'Please select a valid unit.';
    if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Selling price must be a valid positive number.';
    if (!ctype_digit((string)$reorder_level) || $reorder_level < 0) $errors[] = 'Reorder level must be a non-negative whole number.';
    if (!ctype_digit((string)$stock_quantity) || $stock_quantity < 0) $errors[] = 'Starting quantity must be a non-negative whole number.';

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO products (name, category, description, unit, price, reorder_level, stock_quantity, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')"
        );
        $stmt->bind_param("ssssdii", $name, $category, $description, $unit, $price, $reorder_level, $stock_quantity);
        $stmt->execute();
        $new_id = $conn->insert_id;
        log_audit($conn, $admin_id, 'create', 'product', $new_id, null, [
            'name' => $name, 'category' => $category, 'unit' => $unit, 'price' => $price, 'stock_quantity' => $stock_quantity,
        ]);
        $_SESSION['toast'] = ucfirst(strtolower($category)) . ' added.';
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: ' . $redirect_url); exit;
}

// ── Edit product ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id            = (int)($_POST['product_id'] ?? 0);
    $name          = trim($_POST['name'] ?? '');
    $category      = $currentCategory; // locked to the page's current type
    $description   = trim($_POST['description'] ?? '');
    $unit          = $_POST['unit'] ?? '';
    $price         = $_POST['price'] ?? '';
    $reorder_level = $_POST['reorder_level'] ?? 0;
    $stock_quantity = $_POST['stock_quantity'] ?? 0;

    $errors = [];
    if (!$name) $errors[] = 'Product name is required.';
    if (!in_array($unit, $UNITS, true)) $errors[] = 'Please select a valid unit.';
    if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Selling price must be a valid positive number.';
    if (!ctype_digit((string)$reorder_level) || $reorder_level < 0) $errors[] = 'Reorder level must be a non-negative whole number.';
    if (!ctype_digit((string)$stock_quantity) || $stock_quantity < 0) $errors[] = 'Quantity must be a non-negative whole number.';

    $old = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();

    // Safety: don't let an edit submitted from this page move an item into
    // a different category than the one it already belongs to.
    if ($old && $old['category'] !== $currentCategory) {
        $errors[] = 'This item does not belong to the current inventory type.';
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "UPDATE products SET name=?, category=?, description=?, unit=?, price=?, reorder_level=?, stock_quantity=? WHERE id=?"
        );
        $stmt->bind_param("ssssdiii", $name, $category, $description, $unit, $price, $reorder_level, $stock_quantity, $id);
        $stmt->execute();

        log_audit($conn, $admin_id, 'update', 'product', $id,
            ['name' => $old['name'] ?? null, 'price' => $old['price'] ?? null, 'stock_quantity' => $old['stock_quantity'] ?? null],
            ['name' => $name, 'price' => $price, 'stock_quantity' => $stock_quantity]);

        $_SESSION['toast'] = 'Item updated.';
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: ' . $redirect_url); exit;
}

// ── Archive product ──
if (isset($_GET['archive_product'])) {
    $id = (int)$_GET['archive_product'];
    $conn->query("UPDATE products SET status='Archived' WHERE id=$id");
    log_audit($conn, $admin_id, 'archive', 'product', $id, ['status' => 'Active'], ['status' => 'Archived']);
    $_SESSION['toast'] = 'Item archived.';
    header('Location: ' . $redirect_url); exit;
}

// ── Restore product ──
if (isset($_GET['restore_product'])) {
    $id = (int)$_GET['restore_product'];
    $conn->query("UPDATE products SET status='Active' WHERE id=$id");
    log_audit($conn, $admin_id, 'restore', 'product', $id, ['status' => 'Archived'], ['status' => 'Active']);
    $_SESSION['toast'] = 'Item restored.';
    header('Location: ' . $redirect_url); exit;
}

$toast       = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

/* ══════════════════════════════════════════════
   FETCH PRODUCTS — scoped to $currentCategory only
   ══════════════════════════════════════════════ */
$allProducts = [];
$pstmt = $conn->prepare("SELECT * FROM products WHERE category = ? ORDER BY name ASC");
$pstmt->bind_param("s", $currentCategory);
$pstmt->execute();
$pres = $pstmt->get_result();
if ($pres) { while ($row = $pres->fetch_assoc()) { $allProducts[] = $row; } }

$activeNav = 'pos-products';
$pageLabel = $currentCategory === 'Medicine' ? 'Medicine Inventory' : 'Testing Kits Inventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
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
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast error">✕ <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>

<?php $activeNav = 'pos-products'; include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
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
  </div>

  <div class="page-content">

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

  </div>
</div>

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
</body>
</html>