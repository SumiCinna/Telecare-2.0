<?php
// admin/products.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];

$CATEGORIES = ['Medicine', 'Supplement', 'Equipment', 'Other'];
$UNITS      = ['Tablet', 'Capsule', 'Bottle', 'Box', 'Vial', 'Piece', 'Pack', 'Syrup'];

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
   ══════════════════════════════════════════════ */

// ── Add product ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name          = trim($_POST['name'] ?? '');
    $category      = $_POST['category'] ?? '';
    $description   = trim($_POST['description'] ?? '');
    $unit          = $_POST['unit'] ?? '';
    $price         = $_POST['price'] ?? '';
    $reorder_level = $_POST['reorder_level'] ?? 0;

    $errors = [];
    if (!$name) $errors[] = 'Product name is required.';
    if (!in_array($category, $CATEGORIES, true)) $errors[] = 'Please select a valid category.';
    if (!in_array($unit, $UNITS, true)) $errors[] = 'Please select a valid unit.';
    if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Selling price must be a valid positive number.';
    if (!ctype_digit((string)$reorder_level) || $reorder_level < 0) $errors[] = 'Reorder level must be a non-negative whole number.';

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO products (name, category, description, unit, price, reorder_level, status)
             VALUES (?, ?, ?, ?, ?, ?, 'Active')"
        );
        $stmt->bind_param("ssssdi", $name, $category, $description, $unit, $price, $reorder_level);
        $stmt->execute();
        $new_id = $conn->insert_id;
        log_audit($conn, $admin_id, 'create', 'product', $new_id, null, [
            'name' => $name, 'category' => $category, 'unit' => $unit, 'price' => $price,
        ]);
        $_SESSION['toast'] = 'Product added.';
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: products.php'); exit;
}

// ── Edit product ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id            = (int)($_POST['product_id'] ?? 0);
    $name          = trim($_POST['name'] ?? '');
    $category      = $_POST['category'] ?? '';
    $description   = trim($_POST['description'] ?? '');
    $unit          = $_POST['unit'] ?? '';
    $price         = $_POST['price'] ?? '';
    $reorder_level = $_POST['reorder_level'] ?? 0;

    $errors = [];
    if (!$name) $errors[] = 'Product name is required.';
    if (!in_array($category, $CATEGORIES, true)) $errors[] = 'Please select a valid category.';
    if (!in_array($unit, $UNITS, true)) $errors[] = 'Please select a valid unit.';
    if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Selling price must be a valid positive number.';
    if (!ctype_digit((string)$reorder_level) || $reorder_level < 0) $errors[] = 'Reorder level must be a non-negative whole number.';

    $old = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "UPDATE products SET name=?, category=?, description=?, unit=?, price=?, reorder_level=? WHERE id=?"
        );
        $stmt->bind_param("ssssdii", $name, $category, $description, $unit, $price, $reorder_level, $id);
        $stmt->execute();

        log_audit($conn, $admin_id, 'update', 'product', $id,
            ['name' => $old['name'] ?? null, 'price' => $old['price'] ?? null],
            ['name' => $name, 'price' => $price]);

        $_SESSION['toast'] = 'Product updated.';
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: products.php'); exit;
}

// ── Archive product ──
if (isset($_GET['archive_product'])) {
    $id = (int)$_GET['archive_product'];
    $conn->query("UPDATE products SET status='Archived' WHERE id=$id");
    log_audit($conn, $admin_id, 'archive', 'product', $id, ['status' => 'Active'], ['status' => 'Archived']);
    $_SESSION['toast'] = 'Product archived.';
    header('Location: products.php'); exit;
}

// ── Restore product ──
if (isset($_GET['restore_product'])) {
    $id = (int)$_GET['restore_product'];
    $conn->query("UPDATE products SET status='Active' WHERE id=$id");
    log_audit($conn, $admin_id, 'restore', 'product', $id, ['status' => 'Archived'], ['status' => 'Active']);
    $_SESSION['toast'] = 'Product restored.';
    header('Location: products.php'); exit;
}

$toast       = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

/* ══════════════════════════════════════════════
   FETCH PRODUCTS
   ══════════════════════════════════════════════ */
$allProducts = [];
$pres = $conn->query("SELECT * FROM products ORDER BY name ASC");
if ($pres) { while ($row = $pres->fetch_assoc()) { $allProducts[] = $row; } }

$activeNav = 'pos-products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Products — TELE-CARE</title>
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
    .nav-section-label{font-size:0.68rem;font-weight:700;letter-spacing:0.08em;color:rgba(255,255,255,0.3);text-transform:uppercase;margin:1.2rem 1.5rem 0.4rem}
    .main{flex:1;overflow-y:auto}
    .topbar{background:var(--white);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .page-content{padding:2rem}

    .btn-primary{display:inline-flex;align-items:center;gap:0.4rem;background:var(--red);color:#fff;padding:0.6rem 1.3rem;border-radius:50px;font-size:0.85rem;font-weight:600;border:none;cursor:pointer;transition:all 0.25s;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(195,54,67,0.25);text-decoration:none}
    .btn-primary:hover{background:#a82d38;transform:translateY(-1px)}
    .btn-sm{padding:0.38rem 0.85rem;border-radius:50px;font-size:0.76rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-decoration:none;display:inline-block;white-space:nowrap}
    .btn-green{background:rgba(36,68,65,0.1);color:var(--green)}.btn-green:hover{background:var(--green);color:#fff}
    .btn-red{background:rgba(195,54,67,0.1);color:var(--red)}.btn-red:hover{background:var(--red);color:#fff}
    .btn-edit{background:rgba(63,130,227,0.1);color:var(--blue)}.btn-edit:hover{background:var(--blue);color:#fff}
    .btn-activate{background:rgba(34,197,94,0.1);color:#16a34a}.btn-activate:hover{background:#16a34a;color:#fff}

    .controls-bar{display:flex;flex-wrap:wrap;align-items:center;gap:1rem;margin-bottom:1.5rem}
    .search-wrap{position:relative;flex:1;min-width:220px;max-width:340px}
    .search-wrap svg{position:absolute;left:0.85rem;top:50%;transform:translateY(-50%);color:#9ab0ae;width:16px;height:16px}
    .search-input{width:100%;padding:0.62rem 0.9rem 0.62rem 2.3rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:50px;font-family:'DM Sans',sans-serif;font-size:0.85rem;color:var(--green);outline:none;background:var(--white);transition:border-color 0.2s}
    .search-input:focus{border-color:var(--blue)}
    .filter-tabs{display:flex;gap:0.5rem;flex-wrap:wrap}
    .filter-tab{padding:0.5rem 1rem;border-radius:50px;font-size:0.8rem;font-weight:600;border:1.5px solid rgba(36,68,65,0.12);background:var(--white);color:var(--green);cursor:pointer;transition:all 0.2s;font-family:'DM Sans',sans-serif}
    .filter-tab:hover{border-color:var(--blue)}
    .filter-tab.active{background:var(--green);border-color:var(--green);color:#fff}

    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse}
    th{padding:0.9rem 1.2rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;text-align:left;background:rgba(36,68,65,0.03);border-bottom:1px solid rgba(36,68,65,0.07)}
    td{padding:0.9rem 1.2rem;font-size:0.88rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(36,68,65,0.02)}
    .role-pill{display:inline-block;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.03em;background:rgba(36,68,65,0.08);color:var(--green)}
    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-red{background:rgba(195,54,67,0.1);color:var(--red)}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}
    .empty-row{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}
    .actions-cell{display:flex;gap:0.4rem;flex-wrap:wrap}

    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1rem;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:460px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.3rem;margin-bottom:1.2rem}
    .details-grid{display:grid;grid-template-columns:1fr 1fr;gap:0.6rem}
    .detail-item{background:rgba(36,68,65,0.04);border-radius:10px;padding:0.7rem 0.9rem}
    .detail-item.full{grid-column:1/-1}
    .detail-item-label{font-size:0.68rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.2rem}
    .detail-item-value{font-size:0.88rem;font-weight:500;color:var(--green);word-break:break-word}

    .field-label{display:block;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none;transition:border-color 0.2s}
    .field-input:focus{border-color:var(--blue)}
    select.field-input{cursor:pointer}
    textarea.field-input{resize:vertical;min-height:70px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem}
    .form-field{margin-bottom:0.9rem}
    .btn-submit{width:100%;padding:0.85rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.93rem;border:none;cursor:pointer;margin-top:0.5rem;transition:all 0.25s;font-family:'DM Sans',sans-serif}
    .btn-submit:hover{background:#a82d38}
    .btn-cancel{width:100%;padding:0.7rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:0.88rem;border:1.5px solid rgba(36,68,65,0.15);cursor:pointer;margin-top:0.5rem;font-family:'DM Sans',sans-serif}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    .toast.error{background:var(--red)}

    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
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
      <div style="font-size:0.95rem;font-weight:700;">Products</div>
    </div>
    <div style="display:flex;gap:0.6rem;">
      <button class="btn-primary" onclick="openModal('modal-add-product')">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add Product
      </button>
    </div>
  </div>

  <div class="page-content">

    <div class="controls-bar">
      <div class="search-wrap">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Search product..." oninput="applyFilters()"/>
      </div>
      <div class="filter-tabs" id="filterTabs">
        <button class="filter-tab active" data-filter="all" onclick="setFilter('all')">All</button>
        <?php foreach ($CATEGORIES as $cat): ?>
          <button class="filter-tab" data-filter="<?= htmlspecialchars($cat) ?>" onclick="setFilter('<?= htmlspecialchars($cat) ?>')"><?= htmlspecialchars($cat) ?></button>
        <?php endforeach; ?>
        <button class="filter-tab" data-filter="lowstock" onclick="setFilter('lowstock')">Low Stock</button>
        <button class="filter-tab" data-filter="archived" onclick="setFilter('archived')">Archived</button>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Product</th><th>Category</th><th>Unit</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody id="productsTableBody"></tbody>
      </table>
    </div>
    <div id="emptyState" class="empty-row" style="display:none;">No products match your search/filter.</div>

  </div>
</div>

<!-- Add Product -->
<div class="modal-overlay" id="modal-add-product">
  <div class="modal">
    <h3>Add Product</h3>
    <form method="POST" id="add-product-form">
      <div class="form-field"><label class="field-label">Product Name *</label><input type="text" name="name" class="field-input" required/></div>
      <div class="form-field">
        <label class="field-label">Category *</label>
        <select name="category" class="field-input" required>
          <?php foreach ($CATEGORIES as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-field"><label class="field-label">Description</label><textarea name="description" class="field-input" placeholder="Pain reliever..."></textarea></div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">Unit *</label>
          <select name="unit" class="field-input" required>
            <?php foreach ($UNITS as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label class="field-label">Selling Price (₱) *</label><input type="number" step="0.01" min="0" name="price" class="field-input" required/></div>
      </div>
      <div class="form-field"><label class="field-label">Reorder Level *</label><input type="number" min="0" name="reorder_level" class="field-input" value="0" required/></div>
      <button type="submit" name="add_product" class="btn-submit">Add Product</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-add-product')">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Product -->
<div class="modal-overlay" id="modal-edit-product">
  <div class="modal">
    <h3>Edit Product</h3>
    <form method="POST" id="edit-product-form">
      <input type="hidden" name="product_id" id="edit-product-id"/>
      <div class="form-field"><label class="field-label">Product Name *</label><input type="text" name="name" id="edit-product-name" class="field-input" required/></div>
      <div class="form-field">
        <label class="field-label">Category *</label>
        <select name="category" id="edit-product-category" class="field-input" required>
          <?php foreach ($CATEGORIES as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
        </select>
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
      <div class="form-field"><label class="field-label">Reorder Level *</label><input type="number" min="0" name="reorder_level" id="edit-product-reorder" class="field-input" required/></div>
      <button type="submit" name="edit_product" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-product')">Cancel</button>
    </form>
  </div>
</div>

<!-- View Product -->
<div class="modal-overlay" id="modal-view-product">
  <div class="modal">
    <h3 id="vpr-title">Product Details</h3>
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
    if (!['all','archived','lowstock'].includes(currentFilter) && p.category !== currentFilter) return false;

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
      actions += `<a href="?archive_product=${p.id}" class="btn-sm btn-red" onclick="return confirm('Archive ${escAttr(p.name)}? It will no longer be available for new POS transactions.')">Archive</a>`;
    } else {
      actions += `<a href="?restore_product=${p.id}" class="btn-sm btn-activate" onclick="return confirm('Restore ${escAttr(p.name)}?')">Restore</a>`;
    }
    return `<tr>
      <td>${escHtml(p.name)}</td>
      <td><span class="role-pill">${escHtml(p.category)}</span></td>
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
  document.getElementById('edit-product-category').value = p.category;
  document.getElementById('edit-product-description').value = p.description || '';
  document.getElementById('edit-product-unit').value = p.unit;
  document.getElementById('edit-product-price').value = p.price;
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
</script>
</body>
</html>