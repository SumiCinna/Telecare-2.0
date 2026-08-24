<?php
// admin/hmo.php — template module: Providers + Coverage
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

/* ── Providers ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_provider'])) {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$name) {
        $_SESSION['toast_error'] = 'Provider name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO hmo_providers (name, contact_person, phone, email, status) VALUES (?,?,?,?,'Active')");
        $stmt->bind_param("ssss", $name, $contact, $phone, $email);
        $stmt->execute();
        $new_id = $conn->insert_id;
        log_audit($conn, $admin_id, 'create', 'hmo_provider', $new_id, null, ['name' => $name]);
        $_SESSION['toast'] = 'HMO provider added.';
    }
    header('Location: hmo.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_provider'])) {
    $id = (int)($_POST['provider_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    if (!$name) {
        $_SESSION['toast_error'] = 'Provider name is required.';
    } else {
        $stmt = $conn->prepare("UPDATE hmo_providers SET name=?, contact_person=?, phone=?, email=?, status=? WHERE id=?");
        $stmt->bind_param("sssssi", $name, $contact, $phone, $email, $status, $id);
        $stmt->execute();
        log_audit($conn, $admin_id, 'update', 'hmo_provider', $id, null, ['name' => $name]);
        $_SESSION['toast'] = 'Provider updated.';
    }
    header('Location: hmo.php'); exit;
}

if (isset($_GET['archive_provider'])) {
    $id = (int)$_GET['archive_provider'];
    $conn->query("UPDATE hmo_providers SET status='Archived' WHERE id=$id");
    log_audit($conn, $admin_id, 'archive', 'hmo_provider', $id, null, null);
    $_SESSION['toast'] = 'Provider archived.';
    header('Location: hmo.php'); exit;
}
if (isset($_GET['restore_provider'])) {
    $id = (int)$_GET['restore_provider'];
    $conn->query("UPDATE hmo_providers SET status='Active' WHERE id=$id");
    log_audit($conn, $admin_id, 'restore', 'hmo_provider', $id, null, null);
    $_SESSION['toast'] = 'Provider restored.';
    header('Location: hmo.php'); exit;
}

/* ── Coverage ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coverage'])) {
    $provider_id = (int)($_POST['provider_id'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $type = $_POST['coverage_type'] ?? 'Percentage';
    $value = $_POST['coverage_value'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (!$provider_id || !$desc || $value === '' || !is_numeric($value)) {
        $_SESSION['toast_error'] = 'Fill in the coverage description and a valid value.';
    } else {
        $stmt = $conn->prepare("INSERT INTO hmo_coverage (provider_id, description, coverage_type, coverage_value, notes) VALUES (?,?,?,?,?)");
        $stmt->bind_param("issds", $provider_id, $desc, $type, $value, $notes);
        $stmt->execute();
        $_SESSION['toast'] = 'Coverage item added.';
    }
    $_SESSION['open_coverage_for'] = $provider_id;
    header('Location: hmo.php'); exit;
}

if (isset($_GET['remove_coverage'])) {
    $cov_id = (int)$_GET['remove_coverage'];
    $provider_id = (int)($_GET['provider_id'] ?? 0);
    $conn->query("DELETE FROM hmo_coverage WHERE id=$cov_id");
    $_SESSION['toast'] = 'Coverage item removed.';
    $_SESSION['open_coverage_for'] = $provider_id;
    header('Location: hmo.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
$openCoverageFor = $_SESSION['open_coverage_for'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['open_coverage_for']);

$allProviders = [];
$pres = $conn->query("SELECT * FROM hmo_providers ORDER BY name ASC");
if ($pres) { while ($row = $pres->fetch_assoc()) { $allProviders[] = $row; } }

$coverageByProvider = [];
$cres = $conn->query("SELECT * FROM hmo_coverage ORDER BY id ASC");
if ($cres) { while ($row = $cres->fetch_assoc()) { $coverageByProvider[$row['provider_id']][] = $row; } }

$activeNav = 'pos-hmo';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HMO — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    
    .main{flex:1;overflow-y:auto;margin-left:230px}
    .template-note{background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);color:var(--blue);padding:0.8rem 1.1rem;border-radius:12px;font-size:0.82rem;font-weight:600;margin-bottom:1.5rem}



    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:440px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.3rem;margin-bottom:0.3rem}
    .modal .sub{font-size:0.8rem;color:#9ab0ae;margin-bottom:1.2rem}
    .field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none}
    .btn-submit{width:100%;padding:0.85rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.93rem;border:none;cursor:pointer;margin-top:0.5rem}
    .btn-cancel{width:100%;padding:0.7rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:0.88rem;border:1.5px solid rgba(36,68,65,0.15);cursor:pointer;margin-top:0.5rem}

    .req-row .req-notes{font-size:0.72rem;color:#9ab0ae}

    @media(max-width:900px){.sidebar{display:none}}
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
      <div style="font-size:0.95rem;font-weight:700;">HMO</div>
    </div>
    <button class="btn-primary" onclick="openModal('modal-add-provider')">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Add Provider
    </button>
  </div>

  <div class="page-content">
    <div class="template-note">Template module — basic Provider + Coverage tracking. Claims workflow, patient card verification, and billing integration are not built yet.</div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Provider</th><th>Contact Person</th><th>Phone</th><th>Coverage Items</th><th>Status</th><th>Action</th></tr></thead>
        <tbody id="providersTableBody"></tbody>
      </table>
    </div>
    <div id="emptyState" class="empty-row" style="display:none;">No providers yet.</div>
  </div>
</div>

<div class="modal-overlay" id="modal-add-provider">
  <div class="modal">
    <h3>Add HMO Provider</h3>
    <form method="POST">
      <div class="form-field"><label class="field-label">Provider Name *</label><input type="text" name="name" class="field-input" placeholder="Maxicare" required/></div>
      <div class="form-field"><label class="field-label">Contact Person</label><input type="text" name="contact_person" class="field-input"/></div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Phone</label><input type="text" name="phone" class="field-input"/></div>
        <div class="form-field"><label class="field-label">Email</label><input type="email" name="email" class="field-input"/></div>
      </div>
      <button type="submit" name="add_provider" class="btn-submit">Add Provider</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-add-provider')">Cancel</button>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-edit-provider">
  <div class="modal">
    <h3>Edit Provider</h3>
    <form method="POST">
      <input type="hidden" name="provider_id" id="edit-provider-id"/>
      <div class="form-field"><label class="field-label">Provider Name *</label><input type="text" name="name" id="edit-provider-name" class="field-input" required/></div>
      <div class="form-field"><label class="field-label">Contact Person</label><input type="text" name="contact_person" id="edit-provider-contact" class="field-input"/></div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Phone</label><input type="text" name="phone" id="edit-provider-phone" class="field-input"/></div>
        <div class="form-field"><label class="field-label">Email</label><input type="email" name="email" id="edit-provider-email" class="field-input"/></div>
      </div>
      <div class="form-field">
        <label class="field-label">Status</label>
        <select name="status" id="edit-provider-status" class="field-input"><option value="Active">Active</option><option value="Archived">Archived</option></select>
      </div>
      <button type="submit" name="edit_provider" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-provider')">Cancel</button>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-coverage">
  <div class="modal modal-wide">
    <h3>Coverage</h3>
    <div class="sub" id="coverage-provider-name">Coverage items for this provider.</div>
    <div class="req-list" id="coverage-list"></div>
    <form method="POST" class="add-req-row">
      <input type="hidden" name="provider_id" id="coverage-provider-id"/>
      <div class="form-field"><label class="field-label">Description</label><input type="text" name="description" class="field-input" placeholder="General Consultation" required/></div>
      <div class="form-field" style="flex:0 0 130px;">
        <label class="field-label">Type</label>
        <select name="coverage_type" class="field-input"><option value="Percentage">Percentage</option><option value="Fixed">Fixed</option></select>
      </div>
      <div class="form-field" style="flex:0 0 90px;"><label class="field-label">Value</label><input type="number" step="0.01" min="0" name="coverage_value" class="field-input" required/></div>
      <button type="submit" name="add_coverage">Add</button>
    </form>
    <button type="button" class="btn-cancel" onclick="closeModal('modal-coverage')">Done</button>
  </div>
</div>

<script>
const ALL_PROVIDERS = <?= json_encode(array_values($allProviders), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const COVERAGE_BY_PROVIDER = <?= json_encode($coverageByProvider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const OPEN_COVERAGE_FOR = <?= json_encode($openCoverageFor) ?>;

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function statusBadge(status) {
  return status === 'Active' ? `<span class="badge badge-green">Active</span>` : `<span class="badge badge-gray">Archived</span>`;
}

function renderRows() {
  const tbody = document.getElementById('providersTableBody');
  const empty = document.getElementById('emptyState');
  const rows = ALL_PROVIDERS;
  if (rows.length === 0) { tbody.innerHTML = ''; empty.style.display = 'block'; return; }
  empty.style.display = 'none';

  tbody.innerHTML = rows.map(p => {
    const items = COVERAGE_BY_PROVIDER[p.id] || [];
    let actions = `<button class="btn-sm btn-edit" onclick="openEditModal(${p.id})">Edit</button>`;
    actions += `<button class="btn-sm" style="background:rgba(63,130,227,0.1);color:var(--blue);" onclick="openCoverageModal(${p.id})">Coverage</button>`;
    actions += p.status === 'Active'
      ? `<a href="?archive_provider=${p.id}" class="btn-sm btn-red" onclick="var el=this;event.preventDefault();showConfirm('Archive ${escAttr(p.name)}?').then(function(ok){if(ok)window.location=el.href});return false;">Archive</a>`
      : `<a href="?restore_provider=${p.id}" class="btn-sm btn-activate" onclick="var el=this;event.preventDefault();showConfirm('Restore ${escAttr(p.name)}?').then(function(ok){if(ok)window.location=el.href});return false;">Restore</a>`;
    return `<tr>
      <td>${escHtml(p.name)}</td>
      <td>${escHtml(p.contact_person || '—')}</td>
      <td>${escHtml(p.phone || '—')}</td>
      <td>${items.length ? `<span class="badge badge-blue">${items.length} item${items.length>1?'s':''}</span>` : '<span style="color:#9ab0ae;">None</span>'}</td>
      <td>${statusBadge(p.status)}</td>
      <td><div class="actions-cell">${actions}</div></td>
    </tr>`;
  }).join('');
}

function openEditModal(id) {
  const p = ALL_PROVIDERS.find(x => x.id == id);
  if (!p) return;
  document.getElementById('edit-provider-id').value = p.id;
  document.getElementById('edit-provider-name').value = p.name;
  document.getElementById('edit-provider-contact').value = p.contact_person || '';
  document.getElementById('edit-provider-phone').value = p.phone || '';
  document.getElementById('edit-provider-email').value = p.email || '';
  document.getElementById('edit-provider-status').value = p.status;
  openModal('modal-edit-provider');
}

function openCoverageModal(id) {
  const p = ALL_PROVIDERS.find(x => x.id == id);
  if (!p) return;
  document.getElementById('coverage-provider-id').value = p.id;
  document.getElementById('coverage-provider-name').textContent = 'Coverage items for "' + p.name + '".';
  renderCoverageList(p.id);
  openModal('modal-coverage');
}

function renderCoverageList(providerId) {
  const items = COVERAGE_BY_PROVIDER[providerId] || [];
  const list = document.getElementById('coverage-list');
  list.innerHTML = items.length
    ? items.map(c => {
        const valueDisplay = c.coverage_type === 'Percentage' ? `${Number(c.coverage_value)}%` : `₱${Number(c.coverage_value).toFixed(2)}`;
        return `<div class="req-row">
          <div class="req-name">${escHtml(c.description)}<br/><span class="req-notes">${escHtml(c.notes || '')}</span></div>
          <div>${valueDisplay}</div>
          <a href="?remove_coverage=${c.id}&provider_id=${providerId}" class="btn-sm btn-red" onclick="return confirm('Remove ${escAttr(c.description)}?')">Remove</a>
        </div>`;
      }).join('')
    : `<div class="req-empty">No coverage items yet — add one below.</div>`;
}

function escHtml(str) { if (!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(str) { return escHtml(str).replace(/'/g, "\\'"); }

renderRows();
if (OPEN_COVERAGE_FOR) { openCoverageModal(OPEN_COVERAGE_FOR); }
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