<?php
// super_admin/edit_policy.php
require_once __DIR__ . '/../database/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$policy = null;

if ($isEdit) {
    $stmt = safe_prepare($conn, "SELECT * FROM legal_policies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $policy = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$policy) { header('Location: legal_policies.php'); exit; }
}

$errors = [];

// ── Handle Save ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $type        = trim($_POST['type'] ?? '');
    $shortDesc   = trim($_POST['short_description'] ?? '');
    $content     = $_POST['content'] ?? '';
    $status      = in_array($_POST['status'] ?? '', ['Published', 'Draft', 'Archived'], true) ? $_POST['status'] : 'Draft';
    $effDateRaw  = trim($_POST['effective_date'] ?? '');
    $applicable  = isset($_POST['applicable']) && is_array($_POST['applicable']) ? implode(',', $_POST['applicable']) : 'all';
    $revNotes    = trim($_POST['revision_notes'] ?? '');
    $updated_by  = $_SESSION['super_admin_name'] ?? 'Super Admin';

    $effDate = null;
    if ($effDateRaw !== '') {
        $ts = strtotime($effDateRaw);
        if ($ts) { $effDate = date('Y-m-d', $ts); }
    }

    if ($title === '')   { $errors[] = 'Policy title is required.'; }
    if ($type === '')    { $errors[] = 'Policy type is required.'; }
    if (trim(strip_tags($content)) === '') { $errors[] = 'Policy content cannot be empty.'; }
    if (!$isEdit && $revNotes === '') { $revNotes = 'Initial version.'; }
    if ($revNotes === '') { $errors[] = 'Revision notes are required.'; }
    if (!isset($_SESSION['super_admin_id'])) { $errors[] = 'Your session expired. Please log in again.'; }

    if (empty($errors)) {
        if ($isEdit) {
            $targetVer = preg_replace_callback('/(\d+)$/', fn($m) => ((int)$m[1]) + 1, $policy['version']);
            $stmt = safe_prepare($conn, "UPDATE legal_policies SET title=?, type=?, short_desc=?, content=?, version=?, status=?, applicable_to=?, effective_date=?, updated_by=? WHERE id=?");
            $stmt->bind_param("sssssssssi", $title, $type, $shortDesc, $content, $targetVer, $status, $applicable, $effDate, $updated_by, $id);
            $stmt->execute();
            $stmt->close();
            $savedId = $id;
        } else {
            $targetVer = 'v1.0';
            // Slug from title, made unique if needed.
            $baseSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'policy';
            $slug = $baseSlug;
            $n = 2;
            while (true) {
                $chk = safe_prepare($conn, "SELECT id FROM legal_policies WHERE slug = ?");
                $chk->bind_param("s", $slug);
                $chk->execute();
                $exists = $chk->get_result()->fetch_assoc();
                $chk->close();
                if (!$exists) break;
                $slug = $baseSlug . '-' . $n;
                $n++;
            }
            $stmt = safe_prepare($conn, "INSERT INTO legal_policies (slug, title, type, short_desc, content, version, status, applicable_to, effective_date, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssssss", $slug, $title, $type, $shortDesc, $content, $targetVer, $status, $applicable, $effDate, $updated_by);
            $stmt->execute();
            $savedId = $stmt->insert_id;
            $stmt->close();
        }

        // Log this save into the version history table.
        $ins = safe_prepare($conn, "INSERT INTO legal_policy_versions (policy_id, version, content, status, revision_notes, updated_by) VALUES (?,?,?,?,?,?)");
        $ins->bind_param("isssss", $savedId, $targetVer, $content, $status, $revNotes, $updated_by);
        $ins->execute();
        $ins->close();

        header('Location: legal_policies.php');
        exit;
    }
}

$page_title  = $isEdit ? 'Edit Policy' : 'Create New Policy';
$active_nav  = 'legal_policies';
$breadcrumbs = [
	['label' => 'Dashboard', 'href' => 'dashboard.php'],
	['label' => 'Legal Policies', 'href' => 'legal_policies.php'],
	['label' => $isEdit ? 'Edit Policy' : 'Create New Policy'],
];
$header_icon = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>';
$heading     = $isEdit ? 'Edit Policy' : 'Create New Policy';
$subtitle    = $isEdit
	? 'Update the policy content, settings, or save as a new version.'
	: 'Draft a new legal document and choose where it applies before publishing.';

$header_actions = $isEdit
	? '
	<a href="policy_history.php?id=' . $id . '" class="btn btn-secondary">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
		Version History
	</a>'
	: '';

require_once 'includes/header.php';

// Defaults for create mode vs. pre-filled values for edit mode
$title       = $isEdit ? $policy['title'] : ($_POST['title'] ?? '');
$type        = $isEdit ? $policy['type'] : ($_POST['type'] ?? '');
$shortDesc   = $isEdit ? $policy['short_desc'] : ($_POST['short_description'] ?? '');
$status      = $isEdit ? $policy['status'] : 'Draft';
$effDate     = $isEdit && $policy['effective_date'] ? date('M j, Y', strtotime($policy['effective_date'])) : date('M j, Y');
$currentVer  = $isEdit ? $policy['version'] : null;
$targetVer   = $isEdit ? preg_replace_callback('/(\d+)$/', fn($m) => ((int)$m[1]) + 1, $policy['version']) : 'v1.0';
$editorContent = $isEdit ? $policy['content'] : '<p>Start writing the policy content here&hellip;</p>';
$applicableTo = $isEdit ? explode(',', $policy['applicable_to'] ?? 'all') : ['all'];
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error" style="margin-bottom:18px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 16px;border-radius:10px;font-size:13px;">
	<?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<form class="form-grid" style="display:grid;grid-template-columns:minmax(0,2.1fr) minmax(280px,1fr);gap:24px;align-items:start;" method="POST" id="policyForm">

	<div class="panel">
		<div class="panel-body" style="padding:26px;display:flex;flex-direction:column;gap:22px;">

			<div class="form-group">
				<label>Policy Title <span class="req">*</span></label>
				<input type="text" class="form-control" name="title" placeholder="e.g. Privacy Policy" value="<?= htmlspecialchars($title) ?>" required>
			</div>

			<div class="form-group">
				<label>Policy Type <span class="req">*</span></label>
				<select class="form-control" name="type" required>
					<option value="" <?= $type === '' ? 'selected' : '' ?> disabled>Select a type&hellip;</option>
					<option <?= $type === 'Data & Privacy' ? 'selected' : '' ?>>Data &amp; Privacy</option>
					<option <?= $type === 'Terms & Agreements' ? 'selected' : '' ?>>Terms &amp; Agreements</option>
					<option <?= $type === 'Payments' ? 'selected' : '' ?>>Payments</option>
					<option <?= $type === 'Other' ? 'selected' : '' ?>>Other</option>
				</select>
			</div>

			<div class="form-group">
				<label>Short Description <span class="req">*</span></label>
				<input type="text" class="form-control" name="short_description" placeholder="One sentence users will see in the policy list" value="<?= htmlspecialchars($shortDesc) ?>" required>
			</div>

			<div class="form-group">
				<label>Content <span class="req">*</span></label>
				<div class="editor">
					<div class="editor-toolbar">
						<button type="button" data-cmd="bold" title="Bold"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="M6 4h7a4 4 0 0 1 0 8H6zM6 12h8a4 4 0 0 1 0 8H6z"/></svg></button>
						<button type="button" data-cmd="italic" title="Italic"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path d="M10 4h6M8 20h6M14 4 10 20"/></svg></button>
						<button type="button" data-cmd="underline" title="Underline"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 4v7a6 6 0 0 0 12 0V4M4 20h16"/></svg></button>
						<span class="sep"></span>
						<button type="button" data-cmd="insertUnorderedList" title="Bullet list"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/><path d="M9 6h11M9 12h11M9 18h11"/></svg></button>
						<button type="button" data-cmd="insertOrderedList" title="Numbered list"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 6h11M9 12h11M9 18h11M4 6h1M4 10v-.5A1.5 1.5 0 0 1 5.5 8v0A1.5 1.5 0 0 1 7 9.5v0c0 .7-.4 1.1-1 1.5l-2 1.5h3M4 18h2v-4H4"/></svg></button>
						<span class="sep"></span>
						<button type="button" data-cmd="createLink" title="Insert link"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m10 14 4-4M8 16l-2 2a3 3 0 0 1-4-4l4-4M16 8l2-2a3 3 0 1 1 4 4l-4 4"/></svg></button>
					</div>
					<div class="editor-body" id="editorBody" contenteditable="true"><?= $editorContent ?></div>
				</div>
				<input type="hidden" name="content" id="contentField">
				<p class="hint">Formatting is applied live. Section headings should use bold text or a heading style for clarity in the reader view.</p>
			</div>

			<div class="page-actions" style="justify-content:flex-end;padding-top:4px;">
				<a href="legal_policies.php" class="btn btn-secondary">Cancel</a>
				<button type="submit" class="btn btn-primary">
					<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>
					Save Changes
				</button>
			</div>
		</div>
	</div>

	<div class="panel settings-panel">
		<div class="panel-header">
			<h3><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>Settings</h3>
		</div>
		<div class="settings-body">

			<div class="form-group" style="margin-bottom:0;">
				<label>Status</label>
				<div class="status-select-wrap">
					<span class="status-dot"></span>
					<select class="form-control" id="statusSelect" name="status">
						<option <?= $status === 'Published' ? 'selected' : '' ?>>Published</option>
						<option <?= $status === 'Draft' ? 'selected' : '' ?>>Draft</option>
						<option <?= $status === 'Archived' ? 'selected' : '' ?>>Archived</option>
					</select>
				</div>
			</div>

			<div class="form-group" style="margin-bottom:0;">
				<label>Effective Date</label>
				<input type="text" class="form-control" name="effective_date" value="<?= htmlspecialchars($effDate) ?>" placeholder="Select a date">
			</div>

			<div class="form-group" style="margin-bottom:0;">
				<label>Applicable To</label>
				<div class="check-list">
					<label><input type="checkbox" name="applicable[]" value="all" <?= in_array('all', $applicableTo) ? 'checked' : '' ?>> All Users</label>
					<label><input type="checkbox" name="applicable[]" value="patients" <?= in_array('patients', $applicableTo) ? 'checked' : '' ?>> Patients</label>
					<label><input type="checkbox" name="applicable[]" value="professionals" <?= in_array('professionals', $applicableTo) ? 'checked' : '' ?>> Healthcare Professionals</label>
					<label><input type="checkbox" name="applicable[]" value="staff" <?= in_array('staff', $applicableTo) ? 'checked' : '' ?>> Clinic Staff</label>
					<label><input type="checkbox" name="applicable[]" value="others" <?= in_array('others', $applicableTo) ? 'checked' : '' ?>> Others</label>
				</div>
			</div>

			<div class="version-box">
				<div class="version-box-title">Version Control <span class="pill pill-green">Active</span></div>
				<div class="version-row"><span>Current Active Version:</span><strong><?= $isEdit ? htmlspecialchars($currentVer) : '&mdash;' ?></strong></div>
				<div class="version-row"><span>Target Version on Save:</span><strong><?= htmlspecialchars($targetVer) ?></strong></div>
				<div class="form-group" style="margin-bottom:0;margin-top:4px;">
					<label style="margin-bottom:6px;">Revision Notes <span class="req">*</span></label>
					<textarea class="form-control" name="revision_notes" placeholder="Describe what changed in this revision&hellip;" required></textarea>
				</div>
			</div>
		</div>
	</div>
</form>

<script>
	// Sync the contenteditable editor into the hidden "content" field right before submit.
	document.getElementById('policyForm').addEventListener('submit', function () {
		document.getElementById('contentField').value = document.getElementById('editorBody').innerHTML;
	});

	const audienceCheckboxes = document.querySelectorAll('input[name="applicable[]"]');
	const allUsersCheckbox = document.querySelector('input[name="applicable[]"][value="all"]');

	audienceCheckboxes.forEach(function (checkbox) {
		checkbox.addEventListener('change', function () {
			if (this.value === 'all' && this.checked) {
				audienceCheckboxes.forEach(function (audienceCheckbox) {
					if (audienceCheckbox.value !== 'all') audienceCheckbox.checked = false;
				});
			} else if (this.value !== 'all' && this.checked && allUsersCheckbox) {
				allUsersCheckbox.checked = false;
			}
		});
	});
</script>

<?php require_once 'includes/footer.php'; ?>
