<?php
/**
 * TELE-CARE Admin — HMO Providers
 *
 * Responsibilities:
 *  - Authenticate the administrator.
 *  - Preserve the existing HMO provider and coverage workflows.
 *  - Record provider changes in the existing audit_logs table.
 *  - Prepare provider/coverage data for the page UI.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_id = (int) $_SESSION['admin_id'];
$activeNav = 'pos-hmo';

/** Normalize Insurance Commission license values. */
function hmo_clean_license($value): string
{
    $value = strtoupper(trim((string) $value));
    return preg_replace('/[^A-Z0-9\-]/', '', $value) ?? '';
}

/** Ensure optional columns used by the existing provider workflow exist. */
function hmo_ensure_provider_columns(mysqli $conn): void
{
    $columns = [
        'license_no' => 'VARCHAR(30) NULL AFTER name',
        'on_hold' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'hold_reason' => 'VARCHAR(255) NULL',
        'hold_since' => 'DATETIME NULL',
    ];

    foreach ($columns as $column => $definition) {
        try {
            $result = $conn->query("SHOW COLUMNS FROM hmo_providers LIKE '{$column}'");
            if ($result && $result->num_rows === 0) {
                $conn->query("ALTER TABLE hmo_providers ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $exception) {
            // Keep the page available; database errors should be investigated in server logs.
            error_log('HMO provider schema check failed: ' . $exception->getMessage());
        }
    }
}

/** Create the audit table used by the original HMO module. */
function hmo_ensure_audit_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
        `id` INT NOT NULL AUTO_INCREMENT,
        `admin_id` INT NOT NULL,
        `action` VARCHAR(50) NOT NULL,
        `entity_type` VARCHAR(30) NOT NULL,
        `entity_id` INT NOT NULL,
        `old_values` JSON DEFAULT NULL,
        `new_values` JSON DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `admin_id` (`admin_id`),
        KEY `entity` (`entity_type`, `entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Record a provider/coverage operation in the existing audit log. */
if (!function_exists('log_audit')) {
    function log_audit($conn, $admin_id, $action, $entity_type, $entity_id, $old = null, $new = null): void
    {
        $old_json = $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES);
        $new_json = $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES);
        $stmt = $conn->prepare(
            'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_values, new_values)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if ($stmt) {
            $stmt->bind_param('ississ', $admin_id, $action, $entity_type, $entity_id, $old_json, $new_json);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/** Redirect back to this page after an action (Post/Redirect/Get). */
function hmo_redirect(): never
{
    header('Location: hmo_providers.php');
    exit;
}

/** Store a one-time message for the next page load. */
function hmo_flash(string $message, bool $isError = false): void
{
    $_SESSION[$isError ? 'toast_error' : 'toast'] = $message;
}

hmo_ensure_provider_columns($conn);
hmo_ensure_audit_table($conn);

// Add provider.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_provider'])) {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $license = hmo_clean_license($_POST['license_no'] ?? '');
    $license = $license !== '' ? substr($license, 0, 30) : null;

    if ($name === '') {
        hmo_flash('Provider name is required.', true);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO hmo_providers (name, license_no, contact_person, phone, email, status)
             VALUES (?, ?, ?, ?, ?, 'Active')"
        );
        $stmt->bind_param('sssss', $name, $license, $contact, $phone, $email);
        $stmt->execute();
        $new_id = (int) $conn->insert_id;
        $stmt->close();

        log_audit($conn, $admin_id, 'create', 'hmo_provider', $new_id, null, ['name' => $name]);
        hmo_flash('HMO provider added.');
    }
    hmo_redirect();
}

// Edit provider.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_provider'])) {
    $id = (int) ($_POST['provider_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = in_array($_POST['status'] ?? 'Active', ['Active', 'Inactive', 'Archived'], true)
        ? $_POST['status']
        : 'Active';
    $license = hmo_clean_license($_POST['license_no'] ?? '');
    $license = $license !== '' ? substr($license, 0, 30) : null;

    if ($id < 1 || $name === '') {
        hmo_flash('A valid provider and provider name are required.', true);
    } else {
        $stmt = $conn->prepare(
            'UPDATE hmo_providers
             SET name = ?, license_no = ?, contact_person = ?, phone = ?, email = ?, status = ?
             WHERE id = ?'
        );
        $stmt->bind_param('ssssssi', $name, $license, $contact, $phone, $email, $status, $id);
        $stmt->execute();
        $stmt->close();

        log_audit($conn, $admin_id, 'update', 'hmo_provider', $id, null, ['name' => $name, 'status' => $status]);
        hmo_flash('Provider updated.');
    }
    hmo_redirect();
}

// Put a provider on hold. Existing open HMO cases are counted for the warning message.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hold_provider'])) {
    $id = (int) ($_POST['provider_id'] ?? 0);
    $why = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['hold_reason'] ?? '')) ?? '');
    $why = mb_substr($why, 0, 255);

    if ($id < 1 || $why === '') {
        hmo_flash('Write a valid reason for the hold (for example: unpaid bills).', true);
        hmo_redirect();
    }

    $stmt = $conn->prepare('UPDATE hmo_providers SET on_hold = 1, hold_reason = ?, hold_since = NOW() WHERE id = ?');
    $stmt->bind_param('si', $why, $id);
    $stmt->execute();
    $stmt->close();

    $open = 0;
    try {
        $query = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM appointment_hmo h
             JOIN appointments a ON a.id = h.appointment_id
             JOIN hmo_providers p ON p.name = h.hmo_provider
             WHERE p.id = ?
               AND a.status <> 'Cancelled'
               AND h.coverage_status IN ('Pending', 'In Verification', 'Action Required')"
        );
        $query->bind_param('i', $id);
        $query->execute();
        $open = (int) ($query->get_result()->fetch_assoc()['total'] ?? 0);
        $query->close();
    } catch (Throwable $exception) {
        error_log('Unable to count open HMO cases: ' . $exception->getMessage());
    }

    log_audit($conn, $admin_id, 'hold', 'hmo_provider', $id, null, ['reason' => $why]);
    hmo_flash('HMO put on hold. It is no longer accepted for new bookings.' .
        ($open ? " {$open} open case(s) cannot be approved until it is released." : ''));
    hmo_redirect();
}

// Release a provider hold (kept compatible with the existing query parameter action).
if (isset($_GET['release_provider'])) {
    $id = (int) $_GET['release_provider'];
    $stmt = $conn->prepare('UPDATE hmo_providers SET on_hold = 0, hold_reason = NULL, hold_since = NULL WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    log_audit($conn, $admin_id, 'release', 'hmo_provider', $id);
    hmo_flash('HMO taken off hold. It is accepted again.');
    hmo_redirect();
}

// Archive a provider.
if (isset($_GET['archive_provider'])) {
    $id = (int) $_GET['archive_provider'];
    $stmt = $conn->prepare("UPDATE hmo_providers SET status = 'Archived' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    log_audit($conn, $admin_id, 'archive', 'hmo_provider', $id);
    hmo_flash('Provider archived.');
    hmo_redirect();
}

// Restore an archived provider.
if (isset($_GET['restore_provider'])) {
    $id = (int) $_GET['restore_provider'];
    $stmt = $conn->prepare("UPDATE hmo_providers SET status = 'Active' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    log_audit($conn, $admin_id, 'restore', 'hmo_provider', $id);
    hmo_flash('Provider restored.');
    hmo_redirect();
}

// Add a coverage rule for a provider.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coverage'])) {
    $provider_id = (int) ($_POST['provider_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $coverage_type = in_array($_POST['coverage_type'] ?? 'Percentage', ['Percentage', 'Fixed'], true)
        ? $_POST['coverage_type']
        : 'Percentage';
    $coverage_value = $_POST['coverage_value'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if ($provider_id < 1 || $description === '' || $coverage_value === '' || !is_numeric($coverage_value) || (float) $coverage_value < 0) {
        hmo_flash('Fill in the coverage description and a valid non-negative value.', true);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO hmo_coverage (provider_id, description, coverage_type, coverage_value, notes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $numeric_value = (float) $coverage_value;
        $stmt->bind_param('issds', $provider_id, $description, $coverage_type, $numeric_value, $notes);
        $stmt->execute();
        $stmt->close();
        hmo_flash('Coverage item added.');
    }

    $_SESSION['open_coverage_for'] = $provider_id;
    hmo_redirect();
}

// Remove a coverage rule (kept compatible with the existing query parameter action).
if (isset($_GET['remove_coverage'])) {
    $coverage_id = (int) $_GET['remove_coverage'];
    $provider_id = (int) ($_GET['provider_id'] ?? 0);
    $stmt = $conn->prepare('DELETE FROM hmo_coverage WHERE id = ?');
    $stmt->bind_param('i', $coverage_id);
    $stmt->execute();
    $stmt->close();
    hmo_flash('Coverage item removed.');
    $_SESSION['open_coverage_for'] = $provider_id;
    hmo_redirect();
}

// Read and clear flash messages after processing any submitted action.
$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
$openCoverageFor = $_SESSION['open_coverage_for'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['open_coverage_for']);

// Load providers and coverage rules for the interface.
$allProviders = [];
$providerResult = $conn->query('SELECT * FROM hmo_providers ORDER BY name ASC');
if ($providerResult) {
    while ($row = $providerResult->fetch_assoc()) {
        $allProviders[] = $row;
    }
}

$coverageByProvider = [];
$coverageResult = $conn->query('SELECT * FROM hmo_coverage ORDER BY id ASC');
if ($coverageResult) {
    while ($row = $coverageResult->fetch_assoc()) {
        $coverageByProvider[$row['provider_id']][] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HMO Providers | TELE-CARE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="assets/hmo-providers.css">
</head>
<body>
<?php if($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?><?php if($toast_error): ?><div class="toast error">! <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>
<?php include 'sidebar.php'; ?>
<main class="hmo-main"><header class="hmo-topbar"><button class="mobile-menu" type="button" aria-label="Toggle navigation">☰</button><div class="breadcrumbs"><span>Admin</span><b>›</b><span>HMO Management</span><b>›</b><strong>HMO Providers</strong></div><div class="topbar-user"><span class="notification">♧<i>3</i></span><span class="user-avatar">A</span><span><b><?= htmlspecialchars($_SESSION['admin_name']??'Admin') ?></b><small>Administrator</small></span><span>⌄</span></div></header>
<section class="hmo-content"><div class="page-heading"><div><div class="eyebrow">HMO MANAGEMENT</div><h1>HMO Providers</h1><p>Manage accredited HMO providers, contacts, and coverage rules.</p></div><button class="primary-btn" type="button" data-open-modal="modal-add-provider">＋ Add HMO Provider</button></div>
<div class="metric-grid" id="providerMetrics"><article class="metric-card blue"><span class="metric-icon">♧</span><div><small>Total Providers</small><strong id="totalCount">0</strong></div></article><article class="metric-card green"><span class="metric-icon">✓</span><div><small>Active Providers</small><strong id="activeCount">0</strong></div></article><article class="metric-card amber"><span class="metric-icon">◷</span><div><small>Inactive Providers</small><strong id="inactiveCount">0</strong></div></article><article class="metric-card red"><span class="metric-icon">−</span><div><small>Archived Providers</small><strong id="archivedCount">0</strong></div></article></div>
<div class="provider-layout"><section class="panel provider-table-panel"><div class="table-toolbar"><label class="search-box">⌕<input id="providerSearch" type="search" placeholder="Search provider name, license, or contact..."></label><select id="statusFilter" aria-label="Filter by status"><option value="">All Status</option><option>Active</option><option>Archived</option></select><select id="holdFilter" aria-label="Filter hold status"><option value="">All Providers</option><option value="hold">On Hold</option><option value="clear">Not on Hold</option></select></div><div class="table-scroll"><table><thead><tr><th>Provider Name</th><th>License No.</th><th>Contact Person</th><th>Phone</th><th>Coverage</th><th>Status</th><th>Actions</th></tr></thead><tbody id="providersTableBody"></tbody></table></div><div class="empty-row" id="emptyState" style="display:none">No providers match your search.</div><div class="table-footer"><span id="rowCount">Showing 0 providers</span><div class="provider-pagination"><button type="button" class="btn-sm btn-edit" id="providerPrevPage">Prev</button><span id="providerPageInfo">Page 1 of 1</span><button type="button" class="btn-sm btn-edit" id="providerNextPage">Next</button></div></div></section>
<aside class="panel provider-detail" id="providerDetail"><div class="detail-empty">Select a provider to view its details.</div></aside></div></section></main>
<div class="modal-backdrop" id="modal-add-provider"><form class="modal-card" method="POST"><button class="modal-close" type="button" data-close-modal>×</button><h2>Add HMO Provider</h2><p>Enter the provider information below.</p><div class="form-grid"><label class="full-field">Provider Name *<input name="name" required placeholder="Maxicare Healthcare Corp."></label><label>Insurance Commission License No.<input name="license_no" maxlength="30" placeholder="HMO-2023-07-R"></label><label>Contact Person<input name="contact_person" placeholder="Contact person"></label><label>Phone<input name="phone" placeholder="09XX XXX XXXX"></label><label>Email<input type="email" name="email" placeholder="provider@example.com"></label></div><div class="modal-actions"><button type="button" class="secondary-btn" data-close-modal>Cancel</button><button class="primary-btn" type="submit" name="add_provider">Save Provider</button></div></form></div>
<div class="modal-backdrop" id="modal-edit-provider"><form class="modal-card" method="POST"><button class="modal-close" type="button" data-close-modal>×</button><h2>Edit HMO Provider</h2><p>Update provider information and status.</p><input type="hidden" name="provider_id" id="edit-provider-id"><div class="form-grid"><label class="full-field">Provider Name *<input name="name" id="edit-provider-name" required></label><label>Insurance Commission License No.<input name="license_no" id="edit-provider-license" maxlength="30"></label><label>Contact Person<input name="contact_person" id="edit-provider-contact"></label><label>Phone<input name="phone" id="edit-provider-phone"></label><label>Email<input type="email" name="email" id="edit-provider-email"></label><label>Status<select name="status" id="edit-provider-status"><option value="Active">Active</option><option value="Archived">Archived</option></select></label></div><div class="modal-actions"><button type="button" class="secondary-btn" data-close-modal>Cancel</button><button class="primary-btn" type="submit" name="edit_provider">Save Changes</button></div></form></div>
<div class="modal-backdrop" id="modal-coverage"><div class="modal-card"><button class="modal-close" type="button" data-close-modal>×</button><h2>Service Coverage</h2><p id="coverage-provider-name">Coverage items for this provider.</p><div id="coverage-list"></div><form method="POST" class="form-grid" style="margin-top:18px"><input type="hidden" name="provider_id" id="coverage-provider-id"><label class="full-field">Description<input name="description" required placeholder="General Consultation"></label><label>Coverage Type<select name="coverage_type"><option value="Percentage">Percentage</option><option value="Fixed">Fixed Amount</option></select></label><label>Value<input type="number" step="0.01" min="0" name="coverage_value" required></label><label class="full-field">Notes<input name="notes" placeholder="Optional notes"></label><div class="full-field modal-actions"><button type="button" class="secondary-btn" data-close-modal>Done</button><button type="submit" name="add_coverage" class="primary-btn">＋ Add Coverage</button></div></form></div></div>
<form method="POST" id="hold-form" style="display:none"><input type="hidden" name="hold_provider" value="1"><input type="hidden" name="provider_id" id="hold-provider-id"><input type="hidden" name="hold_reason" id="hold-provider-reason"></form>
<script>
window.HMO_PROVIDER_DATA = <?= json_encode(array_values($allProviders), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.HMO_COVERAGE_DATA = <?= json_encode($coverageByProvider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.HMO_OPEN_COVERAGE_FOR = <?= json_encode($openCoverageFor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="assets/hmo-providers.js" defer></script>
</body>
</html>
