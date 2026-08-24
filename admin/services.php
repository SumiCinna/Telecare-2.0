<?php
// admin/services.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];

$CATEGORIES = ['Laboratory', 'X-ray', 'Chemical', 'Consultation', 'Other'];

// ── Ensure audit_logs table exists (same fallback as Users.php / inventory.php) ──
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
   ACTIONS — Service (details)
   ══════════════════════════════════════════════ */

// ── Add service (step 1) — creates the service, then the page reopens the
//    Requirements modal for it so the admin can attach inventory items ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $category    = $_POST['category'] ?? '';
    $status      = $_POST['status'] ?? 'Active';

    $errors = [];
    if (!$name) $errors[] = 'Service name is required.';
    if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Service price must be a valid positive number.';
    if (!in_array($category, $CATEGORIES, true)) $errors[] = 'Please select a valid category.';
    if (!in_array($status, ['Active', 'Archived'], true)) $status = 'Active';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO services (name, description, price, category, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $name, $description, $price, $category, $status);
        $stmt->execute();
        $new_id = $conn->insert_id;
        log_audit($conn, $admin_id, 'create', 'service', $new_id, null, ['name' => $name, 'price' => $price, 'category' => $category]);
        $_SESSION['toast'] = 'Service created. Now attach the inventory items it uses.';
        $_SESSION['open_requirements_for'] = $new_id;
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: services.php'); exit;
}

// ── Edit service ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $id          = (int)($_POST['service_id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = $_POST['price'] ?? '';
    $category    = $_POST['category'] ?? '';
    $status      = $_POST['status'] ?? 'Active';

    $errors = [];
    if (!$name) $errors[] = 'Service name is required.';
    if ($price === '' || !is_numeric($price) || $price < 0) $errors[] = 'Service price must be a valid positive number.';
    if (!in_array($category, $CATEGORIES, true)) $errors[] = 'Please select a valid category.';
    if (!in_array($status, ['Active', 'Archived'], true)) $status = 'Active';

    $old = $conn->query("SELECT * FROM services WHERE id=$id")->fetch_assoc();

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE services SET name=?, description=?, price=?, category=?, status=? WHERE id=?");
        $stmt->bind_param("ssdssi", $name, $description, $price, $category, $status, $id);
        $stmt->execute();
        log_audit($conn, $admin_id, 'update', 'service', $id,
            ['name' => $old['name'] ?? null, 'price' => $old['price'] ?? null],
            ['name' => $name, 'price' => $price]);
        $_SESSION['toast'] = 'Service updated.';
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: services.php'); exit;
}

// ── Archive / Restore service ──
if (isset($_GET['archive_service'])) {
    $id = (int)$_GET['archive_service'];
    $conn->query("UPDATE services SET status='Archived' WHERE id=$id");
    log_audit($conn, $admin_id, 'archive', 'service', $id, ['status' => 'Active'], ['status' => 'Archived']);
    $_SESSION['toast'] = 'Service archived.';
    header('Location: services.php'); exit;
}
if (isset($_GET['restore_service'])) {
    $id = (int)$_GET['restore_service'];
    $conn->query("UPDATE services SET status='Active' WHERE id=$id");
    log_audit($conn, $admin_id, 'restore', 'service', $id, ['status' => 'Archived'], ['status' => 'Active']);
    $_SESSION['toast'] = 'Service restored.';
    header('Location: services.php'); exit;
}

/* ══════════════════════════════════════════════
   ACTIONS — Requirements (inventory items per service)
   ══════════════════════════════════════════════ */

// ── Add / update requirement (same form — DB upserts on the unique key) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_requirement'])) {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $qty        = (int)($_POST['quantity_used'] ?? 0);

    // Guard: the product being attached must actually be a Testing Kit.
    // (Medicine is handled separately by the pharmacist POS and must
    // never be attachable to a service requirement.)
    $validProduct = false;
    if ($product_id) {
        $chk = $conn->prepare("SELECT id FROM products WHERE id=? AND category='Testing Kits' AND status='Active'");
        $chk->bind_param("i", $product_id);
        $chk->execute();
        $validProduct = $chk->get_result()->num_rows > 0;
    }

    if ($service_id && $validProduct && $qty > 0) {
        $stmt = $conn->prepare(
            "INSERT INTO service_requirements (service_id, product_id, quantity_used)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity_used = VALUES(quantity_used)"
        );
        $stmt->bind_param("iii", $service_id, $product_id, $qty);
        $stmt->execute();
        log_audit($conn, $admin_id, 'set_requirement', 'service', $service_id, null, ['product_id' => $product_id, 'quantity_used' => $qty]);
        $_SESSION['toast'] = 'Testing kit item attached.';
    } else {
        $_SESSION['toast_error'] = 'Select a testing kit item and a quantity of at least 1.';
    }
    $_SESSION['open_requirements_for'] = $service_id;
    header('Location: services.php'); exit;
}

// ── Update requirement quantity ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_requirement'])) {
    $req_id     = (int)($_POST['requirement_id'] ?? 0);
    $service_id = (int)($_POST['service_id'] ?? 0);
    $qty        = (int)($_POST['quantity_used'] ?? 0);

    if ($req_id && $qty > 0) {
        $stmt = $conn->prepare("UPDATE service_requirements SET quantity_used=? WHERE id=?");
        $stmt->bind_param("ii", $qty, $req_id);
        $stmt->execute();
        $_SESSION['toast'] = 'Quantity updated.';
    } else {
        $_SESSION['toast_error'] = 'Quantity must be at least 1.';
    }
    $_SESSION['open_requirements_for'] = $service_id;
    header('Location: services.php'); exit;
}

// ── Remove requirement ──
if (isset($_GET['remove_requirement'])) {
    $req_id     = (int)$_GET['remove_requirement'];
    $service_id = (int)($_GET['service_id'] ?? 0);
    $conn->query("DELETE FROM service_requirements WHERE id=$req_id");
    log_audit($conn, $admin_id, 'remove_requirement', 'service', $service_id, ['requirement_id' => $req_id], null);
    $_SESSION['toast'] = 'Item removed from requirements.';
    $_SESSION['open_requirements_for'] = $service_id;
    header('Location: services.php'); exit;
}

$toast       = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
$openRequirementsFor = $_SESSION['open_requirements_for'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['open_requirements_for']);

/* ══════════════════════════════════════════════
   FETCH DATA
   ══════════════════════════════════════════════ */
$allServices = [];
$sres = $conn->query("SELECT * FROM services ORDER BY name ASC");
if ($sres) { while ($row = $sres->fetch_assoc()) { $allServices[] = $row; } }

$requirementsByService = [];
$rres = $conn->query(
    "SELECT r.id, r.service_id, r.product_id, r.quantity_used, p.name AS product_name, p.unit AS product_unit
     FROM service_requirements r
     JOIN products p ON p.id = r.product_id
     ORDER BY p.name ASC"
);
if ($rres) {
    while ($row = $rres->fetch_assoc()) {
        $requirementsByService[$row['service_id']][] = $row;
    }
}

// Services only draw from Testing Kits — medicine is handled separately
// by the pharmacist POS and should never be attachable to a service.
$activeProducts = [];
$prres = $conn->query("SELECT id, name, unit FROM products WHERE status='Active' AND category='Testing Kits' ORDER BY name ASC");
if ($prres) { while ($row = $prres->fetch_assoc()) { $activeProducts[] = $row; } }

$activeNav = 'pos-services';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Services — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    
    .main{flex:1;overflow-y:auto;margin-left:230px}




    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:460px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.3rem;margin-bottom:0.3rem}
    .modal .sub{font-size:0.8rem;color:#9ab0ae;margin-bottom:1.2rem}

    textarea.field-input{resize:vertical;min-height:70px}

    .req-row .req-unit{font-size:0.72rem;color:#9ab0ae}
    .add-req-row{display:flex;gap:0.5rem;align-items:flex-end}
    .add-req-row .form-field{margin-bottom:0;flex:1}
    .req-empty-hint{font-size:0.78rem;color:#9ab0ae;margin-top:0.4rem}


    @media(max-width:900px){.sidebar{display:none}}
    @media(max-width:520px){.details-grid{grid-template-columns:1fr}.controls-bar{flex-direction:column;align-items:stretch}.search-wrap{max-width:none}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast error">✕ <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Services</div>
    </div>
    <div style="display:flex;gap:0.6rem;">
      <button class="btn-primary" onclick="openModal('modal-add-service')">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add Service
      </button>
    </div>
  </div>

  <div class="page-content">

    <div class="controls-bar">
      <div class="search-wrap">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Search service..." oninput="applyFilters()"/>
      </div>
      <div class="filter-tabs" id="filterTabs">
        <button class="filter-tab active" data-filter="all" onclick="setFilter('all')">All</button>
        <?php foreach ($CATEGORIES as $cat): ?>
          <button class="filter-tab" data-filter="<?= htmlspecialchars($cat) ?>" onclick="setFilter('<?= htmlspecialchars($cat) ?>')"><?= htmlspecialchars($cat) ?></button>
        <?php endforeach; ?>
        <button class="filter-tab" data-filter="archived" onclick="setFilter('archived')">Archived</button>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Service</th><th>Price</th><th>Category</th><th>Inventory</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody id="servicesTableBody"></tbody>
      </table>
    </div>
    <div id="emptyState" class="empty-row" style="display:none;">No services match your search/filter.</div>

  </div>
</div>

<!-- Add Service (step 1) -->
<div class="modal-overlay" id="modal-add-service">
  <div class="modal">
    <h3>Add Clinic Service</h3>
    <div class="sub">When creating a service, you'll attach its required testing kit items next.</div>
    <form method="POST">
      <div class="form-field"><label class="field-label">Service Name *</label><input type="text" name="name" class="field-input" placeholder="CBC Test" required/></div>
      <div class="form-field"><label class="field-label">Description</label><textarea name="description" class="field-input" placeholder="Complete Blood Count test"></textarea></div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">Category *</label>
          <select name="category" class="field-input" required>
            <?php foreach ($CATEGORIES as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label class="field-label">Service Price (₱) *</label><input type="number" step="0.01" min="0" name="price" class="field-input" required/></div>
      </div>
      <div class="form-field">
        <label class="field-label">Status</label>
        <select name="status" class="field-input">
          <option value="Active" selected>Active</option>
          <option value="Archived">Archived</option>
        </select>
      </div>
      <button type="submit" name="add_service" class="btn-submit">Next: Requirements</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-add-service')">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Service -->
<div class="modal-overlay" id="modal-edit-service">
  <div class="modal">
    <h3>Edit Service</h3>
    <form method="POST">
      <input type="hidden" name="service_id" id="edit-service-id"/>
      <div class="form-field"><label class="field-label">Service Name *</label><input type="text" name="name" id="edit-service-name" class="field-input" required/></div>
      <div class="form-field"><label class="field-label">Description</label><textarea name="description" id="edit-service-description" class="field-input"></textarea></div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">Category *</label>
          <select name="category" id="edit-service-category" class="field-input" required>
            <?php foreach ($CATEGORIES as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-field"><label class="field-label">Service Price (₱) *</label><input type="number" step="0.01" min="0" name="price" id="edit-service-price" class="field-input" required/></div>
      </div>
      <div class="form-field">
        <label class="field-label">Status</label>
        <select name="status" id="edit-service-status" class="field-input">
          <option value="Active">Active</option>
          <option value="Archived">Archived</option>
        </select>
      </div>
      <button type="submit" name="edit_service" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-service')">Cancel</button>
    </form>
  </div>
</div>

<!-- View Service -->
<div class="modal-overlay" id="modal-view-service">
  <div class="modal">
    <h3 id="vsr-title">Service Details</h3>
    <div class="details-grid" style="margin-bottom:1rem;">
      <div class="detail-item"><div class="detail-item-label">Category</div><div class="detail-item-value" id="vsr-category"></div></div>
      <div class="detail-item"><div class="detail-item-label">Price</div><div class="detail-item-value" id="vsr-price"></div></div>
      <div class="detail-item"><div class="detail-item-label">Status</div><div class="detail-item-value" id="vsr-status"></div></div>
      <div class="detail-item full"><div class="detail-item-label">Description</div><div class="detail-item-value" id="vsr-description"></div></div>
    </div>
    <div class="detail-item-label" style="margin-bottom:0.5rem;">Required Testing Kit Items</div>
    <div class="req-list" id="vsr-req-list"></div>
    <button class="btn-cancel" onclick="closeModal('modal-view-service')">Close</button>
  </div>
</div>

<!-- Requirements (Required Testing Kit Items) -->
<div class="modal-overlay" id="modal-requirements">
  <div class="modal modal-wide">
    <h3>Service Requirements</h3>
    <div class="sub" id="req-service-name">Required testing kit items for this service.</div>

    <div class="req-list" id="req-list"></div>

    <form method="POST" class="add-req-row">
      <input type="hidden" name="service_id" id="req-service-id"/>
      <div class="form-field">
        <label class="field-label">Add Testing Kit Item</label>
        <select name="product_id" class="field-input" required>
          <option value="">— Select testing kit —</option>
          <?php foreach ($activeProducts as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['unit']) ?>)</option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($activeProducts)): ?>
          <div class="req-empty-hint">No active testing kits yet — add some in Inventory → Testing Kits first.</div>
        <?php endif; ?>
      </div>
      <div class="form-field qty-field">
        <label class="field-label">Qty</label>
        <input type="number" name="quantity_used" class="field-input" min="1" value="1" required/>
      </div>
      <button type="submit" name="add_requirement">Add</button>
    </form>

    <button type="button" class="btn-cancel" onclick="closeModal('modal-requirements')">Done</button>
  </div>
</div>

<script>
const ALL_SERVICES = <?= json_encode(array_values($allServices), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const REQUIREMENTS_BY_SERVICE = <?= json_encode($requirementsByService, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const OPEN_REQUIREMENTS_FOR = <?= json_encode($openRequirementsFor) ?>;

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
  let rows = ALL_SERVICES.filter(s => {
    if (currentFilter === 'archived') { if (s.status !== 'Archived') return false; }
    else if (s.status === 'Archived') return false;

    if (!['all','archived'].includes(currentFilter) && s.category !== currentFilter) return false;
    if (q && !s.name.toLowerCase().includes(q)) return false;
    return true;
  });
  renderRows(rows);
}

function statusBadge(status) {
  return status === 'Active' ? `<span class="badge badge-green">Active</span>` : `<span class="badge badge-gray">Archived</span>`;
}

function inventorySummary(serviceId) {
  const items = REQUIREMENTS_BY_SERVICE[serviceId] || [];
  if (items.length === 0) return `<span style="color:#9ab0ae;">None</span>`;
  return `<span class="badge badge-blue">${items.length} item${items.length > 1 ? 's' : ''}</span>`;
}

function renderRows(rows) {
  const tbody = document.getElementById('servicesTableBody');
  const empty = document.getElementById('emptyState');

  if (rows.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  tbody.innerHTML = rows.map(s => {
    let actions = `<button class="btn-sm btn-green" onclick="openViewModal(${s.id})">View</button>`;
    actions += `<button class="btn-sm btn-edit" onclick="openEditModal(${s.id})">Edit</button>`;
    actions += `<button class="btn-sm" style="background:rgba(63,130,227,0.1);color:var(--blue);" onclick="openRequirementsModal(${s.id})">Requirements</button>`;
    if (s.status === 'Active') {
      actions += `<a href="?archive_service=${s.id}" class="btn-sm btn-red" onclick="var el=this;event.preventDefault();showConfirm('Archive ${escAttr(s.name)}? It will no longer be available for new POS transactions.').then(function(ok){if(ok)window.location=el.href});return false;">Archive</a>`;
    } else {
      actions += `<a href="?restore_service=${s.id}" class="btn-sm btn-activate" onclick="var el=this;event.preventDefault();showConfirm('Restore ${escAttr(s.name)}?').then(function(ok){if(ok)window.location=el.href});return false;">Restore</a>`;
    }
    return `<tr>
      <td>${escHtml(s.name)}</td>
      <td>₱${Number(s.price).toFixed(2)}</td>
      <td><span class="role-pill">${escHtml(s.category)}</span></td>
      <td>${inventorySummary(s.id)}</td>
      <td>${statusBadge(s.status)}</td>
      <td><div class="actions-cell">${actions}</div></td>
    </tr>`;
  }).join('');
}

function openEditModal(id) {
  const s = ALL_SERVICES.find(x => x.id == id);
  if (!s) return;
  document.getElementById('edit-service-id').value = s.id;
  document.getElementById('edit-service-name').value = s.name;
  document.getElementById('edit-service-description').value = s.description || '';
  document.getElementById('edit-service-category').value = s.category;
  document.getElementById('edit-service-price').value = s.price;
  document.getElementById('edit-service-status').value = s.status;
  openModal('modal-edit-service');
}

function openViewModal(id) {
  const s = ALL_SERVICES.find(x => x.id == id);
  if (!s) return;
  document.getElementById('vsr-title').textContent = s.name;
  document.getElementById('vsr-category').textContent = s.category;
  document.getElementById('vsr-price').textContent = '₱' + Number(s.price).toFixed(2);
  document.getElementById('vsr-status').textContent = s.status;
  document.getElementById('vsr-description').textContent = s.description || 'No description';

  const items = REQUIREMENTS_BY_SERVICE[id] || [];
  const list = document.getElementById('vsr-req-list');
  list.innerHTML = items.length
    ? items.map(r => `<div class="req-row"><div class="req-name">${escHtml(r.product_name)} <span class="req-unit">(${escHtml(r.product_unit)})</span></div><div>${r.quantity_used}x</div></div>`).join('')
    : `<div class="req-empty">No testing kit items attached yet.</div>`;

  openModal('modal-view-service');
}

function openRequirementsModal(id) {
  const s = ALL_SERVICES.find(x => x.id == id);
  if (!s) return;
  document.getElementById('req-service-id').value = s.id;
  document.getElementById('req-service-name').textContent = 'Required testing kit items for "' + s.name + '".';
  renderRequirementsList(s.id);
  openModal('modal-requirements');
}

function renderRequirementsList(serviceId) {
  const items = REQUIREMENTS_BY_SERVICE[serviceId] || [];
  const list = document.getElementById('req-list');
  list.innerHTML = items.length
    ? items.map(r => `
        <div class="req-row">
          <div class="req-name">${escHtml(r.product_name)} <span class="req-unit">(${escHtml(r.product_unit)})</span></div>
          <form method="POST" style="display:flex;gap:0.4rem;align-items:center;">
            <input type="hidden" name="requirement_id" value="${r.id}"/>
            <input type="hidden" name="service_id" value="${serviceId}"/>
            <input type="number" name="quantity_used" min="1" value="${r.quantity_used}"/>
            <button type="submit" name="update_requirement" class="btn-sm btn-edit">Save</button>
          </form>
          <a href="?remove_requirement=${r.id}&service_id=${serviceId}" class="btn-sm btn-red" onclick="var el=this;event.preventDefault();showConfirm('Remove ${escAttr(r.product_name)} from this service\\'s requirements?').then(function(ok){if(ok)window.location=el.href});return false;">Remove</a>
        </div>`).join('')
    : `<div class="req-empty">No testing kit items attached yet — add one below.</div>`;
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(str) { return escHtml(str).replace(/'/g, "\\'"); }

renderRows(ALL_SERVICES.filter(s => s.status === 'Active'));
if (OPEN_REQUIREMENTS_FOR) { openRequirementsModal(OPEN_REQUIREMENTS_FOR); }
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