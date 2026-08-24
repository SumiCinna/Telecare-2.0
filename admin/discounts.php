<?php
// admin/discounts.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_discount'])) {
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';
    $conditions = trim($_POST['conditions_text'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    $errors = [];
    if (!$name) $errors[] = 'Discount name is required.';
    if (!in_array($type, ['Percentage', 'Fixed'], true)) $errors[] = 'Please select a valid discount type.';
    if ($value === '' || !is_numeric($value) || $value < 0) $errors[] = 'Discount value must be a valid positive number.';
    if ($type === 'Percentage' && is_numeric($value) && $value > 100) $errors[] = 'Percentage discount cannot exceed 100%.';
    if (!in_array($status, ['Active', 'Archived'], true)) $status = 'Active';

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO discounts (name, type, value, conditions_text, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $name, $type, $value, $conditions, $status);
        $stmt->execute();
        $new_id = $conn->insert_id;
        log_audit($conn, $admin_id, 'create', 'discount', $new_id, null, ['name' => $name, 'type' => $type, 'value' => $value]);
        $_SESSION['toast'] = 'Discount added.';
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: discounts.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_discount'])) {
    $id = (int)($_POST['discount_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';
    $conditions = trim($_POST['conditions_text'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    $errors = [];
    if (!$name) $errors[] = 'Discount name is required.';
    if (!in_array($type, ['Percentage', 'Fixed'], true)) $errors[] = 'Please select a valid discount type.';
    if ($value === '' || !is_numeric($value) || $value < 0) $errors[] = 'Discount value must be a valid positive number.';
    if ($type === 'Percentage' && is_numeric($value) && $value > 100) $errors[] = 'Percentage discount cannot exceed 100%.';
    if (!in_array($status, ['Active', 'Archived'], true)) $status = 'Active';

    $old = $conn->query("SELECT * FROM discounts WHERE id=$id")->fetch_assoc();

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE discounts SET name=?, type=?, value=?, conditions_text=?, status=? WHERE id=?");
        $stmt->bind_param("ssdssi", $name, $type, $value, $conditions, $status, $id);
        $stmt->execute();
        log_audit($conn, $admin_id, 'update', 'discount', $id,
            ['name' => $old['name'] ?? null, 'value' => $old['value'] ?? null],
            ['name' => $name, 'value' => $value]);
        $_SESSION['toast'] = 'Discount updated.';
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
    }
    header('Location: discounts.php'); exit;
}

if (isset($_GET['archive_discount'])) {
    $id = (int)$_GET['archive_discount'];
    $conn->query("UPDATE discounts SET status='Archived' WHERE id=$id");
    log_audit($conn, $admin_id, 'archive', 'discount', $id, ['status' => 'Active'], ['status' => 'Archived']);
    $_SESSION['toast'] = 'Discount archived.';
    header('Location: discounts.php'); exit;
}
if (isset($_GET['restore_discount'])) {
    $id = (int)$_GET['restore_discount'];
    $conn->query("UPDATE discounts SET status='Active' WHERE id=$id");
    log_audit($conn, $admin_id, 'restore', 'discount', $id, ['status' => 'Archived'], ['status' => 'Active']);
    $_SESSION['toast'] = 'Discount restored.';
    header('Location: discounts.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$allDiscounts = [];
$dres = $conn->query("SELECT * FROM discounts ORDER BY name ASC");
if ($dres) { while ($row = $dres->fetch_assoc()) { $allDiscounts[] = $row; } }

$activeNav = 'pos-discounts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Discounts — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    
    .main{flex:1;overflow-y:auto;margin-left:230px}




    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:440px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}

    @media(max-width:900px){.sidebar{display:none}}
    @media(max-width:520px){.controls-bar{flex-direction:column;align-items:stretch}.search-wrap{max-width:none}}
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
      <div style="font-size:0.95rem;font-weight:700;">Discounts</div>
    </div>
    <button class="btn-primary" onclick="openModal('modal-add-discount')">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Add Discount
    </button>
  </div>

  <div class="page-content">
    <div class="controls-bar">
      <div class="search-wrap">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Search discount..." oninput="applyFilters()"/>
      </div>
      <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all" onclick="setFilter('all')">All</button>
        <button class="filter-tab" data-filter="Percentage" onclick="setFilter('Percentage')">Percentage</button>
        <button class="filter-tab" data-filter="Fixed" onclick="setFilter('Fixed')">Fixed</button>
        <button class="filter-tab" data-filter="archived" onclick="setFilter('archived')">Archived</button>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Discount</th><th>Type</th><th>Value</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="discountsTableBody"></tbody>
      </table>
    </div>
    <div id="emptyState" class="empty-row" style="display:none;">No discounts match your search/filter.</div>
  </div>
</div>

<div class="modal-overlay" id="modal-add-discount">
  <div class="modal">
    <h3>Add Discount</h3>
    <form method="POST">
      <div class="form-field"><label class="field-label">Discount Name *</label><input type="text" name="name" class="field-input" placeholder="Senior Citizen" required/></div>
      <div class="form-field">
        <label class="field-label">Discount Type *</label>
        <select name="type" class="field-input" required onchange="toggleValueHint(this,'add-value-hint')">
          <option value="Percentage">Percentage (e.g. 50%)</option>
          <option value="Fixed">Fixed Amount (e.g. 50 pesos)</option>
        </select>
      </div>
      <div class="form-field">
        <label class="field-label">Discount Value *</label>
        <input type="number" step="0.01" min="0" name="value" class="field-input" required/>
        <div id="add-value-hint" style="font-size:0.72rem;color:#9ab0ae;margin-top:0.3rem;">Enter a percent (0-100)</div>
      </div>
      <div class="form-field"><label class="field-label">Conditions</label><textarea name="conditions_text" class="field-input" placeholder="e.g. Valid Senior Citizen ID required."></textarea></div>
      <div class="form-field">
        <label class="field-label">Status</label>
        <select name="status" class="field-input"><option value="Active" selected>Active</option><option value="Archived">Archived</option></select>
      </div>
      <button type="submit" name="add_discount" class="btn-submit">Add Discount</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-add-discount')">Cancel</button>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-edit-discount">
  <div class="modal">
    <h3>Edit Discount</h3>
    <form method="POST">
      <input type="hidden" name="discount_id" id="edit-discount-id"/>
      <div class="form-field"><label class="field-label">Discount Name *</label><input type="text" name="name" id="edit-discount-name" class="field-input" required/></div>
      <div class="form-field">
        <label class="field-label">Discount Type *</label>
        <select name="type" id="edit-discount-type" class="field-input" required>
          <option value="Percentage">Percentage (e.g. 50%)</option>
          <option value="Fixed">Fixed Amount (e.g. 50 pesos)</option>
        </select>
      </div>
      <div class="form-field"><label class="field-label">Discount Value *</label><input type="number" step="0.01" min="0" name="value" id="edit-discount-value" class="field-input" required/></div>
      <div class="form-field"><label class="field-label">Conditions</label><textarea name="conditions_text" id="edit-discount-conditions" class="field-input"></textarea></div>
      <div class="form-field">
        <label class="field-label">Status</label>
        <select name="status" id="edit-discount-status" class="field-input"><option value="Active">Active</option><option value="Archived">Archived</option></select>
      </div>
      <button type="submit" name="edit_discount" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-discount')">Cancel</button>
    </form>
  </div>
</div>

<script>
const ALL_DISCOUNTS = <?= json_encode(array_values($allDiscounts), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentFilter = 'all';

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function toggleValueHint(sel, hintId) {
  document.getElementById(hintId).textContent = sel.value === 'Percentage' ? 'Enter a percent (0-100)' : 'Enter a peso amount';
}

function setFilter(f) {
  currentFilter = f;
  document.querySelectorAll('.filter-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === f));
  applyFilters();
}

function applyFilters() {
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  let rows = ALL_DISCOUNTS.filter(d => {
    if (currentFilter === 'archived') { if (d.status !== 'Archived') return false; }
    else if (d.status === 'Archived') return false;
    if (!['all','archived'].includes(currentFilter) && d.type !== currentFilter) return false;
    if (q && !d.name.toLowerCase().includes(q)) return false;
    return true;
  });
  renderRows(rows);
}

function statusBadge(status) {
  return status === 'Active' ? `<span class="badge badge-green">Active</span>` : `<span class="badge badge-gray">Archived</span>`;
}

function renderRows(rows) {
  const tbody = document.getElementById('discountsTableBody');
  const empty = document.getElementById('emptyState');
  if (rows.length === 0) { tbody.innerHTML = ''; empty.style.display = 'block'; return; }
  empty.style.display = 'none';

  tbody.innerHTML = rows.map(d => {
    const valueDisplay = d.type === 'Percentage' ? `${Number(d.value)}%` : `₱${Number(d.value).toFixed(2)}`;
    let actions = `<button class="btn-sm btn-edit" onclick="openEditModal(${d.id})">Edit</button>`;
    actions += d.status === 'Active'
      ? `<a href="?archive_discount=${d.id}" class="btn-sm btn-red" onclick="var el=this;event.preventDefault();showConfirm('Archive ${escAttr(d.name)}?').then(function(ok){if(ok)window.location=el.href});return false;">Archive</a>`
      : `<a href="?restore_discount=${d.id}" class="btn-sm btn-activate" onclick="var el=this;event.preventDefault();showConfirm('Restore ${escAttr(d.name)}?').then(function(ok){if(ok)window.location=el.href});return false;">Restore</a>`;
    return `<tr>
      <td>${escHtml(d.name)}</td>
      <td><span class="role-pill">${escHtml(d.type)}</span></td>
      <td>${valueDisplay}</td>
      <td>${statusBadge(d.status)}</td>
      <td><div class="actions-cell">${actions}</div></td>
    </tr>`;
  }).join('');
}

function openEditModal(id) {
  const d = ALL_DISCOUNTS.find(x => x.id == id);
  if (!d) return;
  document.getElementById('edit-discount-id').value = d.id;
  document.getElementById('edit-discount-name').value = d.name;
  document.getElementById('edit-discount-type').value = d.type;
  document.getElementById('edit-discount-value').value = d.value;
  document.getElementById('edit-discount-conditions').value = d.conditions_text || '';
  document.getElementById('edit-discount-status').value = d.status;
  openModal('modal-edit-discount');
}

function escHtml(str) { if (!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(str) { return escHtml(str).replace(/'/g, "\\'"); }

renderRows(ALL_DISCOUNTS.filter(d => d.status === 'Active'));
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