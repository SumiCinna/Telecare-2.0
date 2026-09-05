<?php
// edit_policy.php
require_once 'includes/data.php'; // $policies

$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$policy = $id ? policy_lookup($policies, $id) : null;
$isEdit = $policy !== null;

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
	? 'Update the policy content, settings, or replace with a new version.'
	: 'Draft a new legal document and choose where it applies before publishing.';

$header_actions = $isEdit
	? '
	<a href="policy_history.php?id=' . $id . '" class="btn btn-secondary">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
		Version History
	</a>
	<button type="button" class="btn btn-secondary" data-open-modal="modal-preview-changes">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M2 12s3-5 10-5 10 5 10 5-3 5-10 5-10-5-10-5Z"/><circle cx="12" cy="12" r="2"/></svg>
		Preview Changes
	</button>'
	: '';

require_once 'includes/header.php';

// Defaults for create mode vs. pre-filled values for edit mode
$title       = $isEdit ? $policy['title'] : '';
$type        = $isEdit ? $policy['type'] : '';
$shortDesc   = $isEdit ? $policy['short_desc'] : '';
$status      = $isEdit ? $policy['status'] : 'Draft';
$effDate     = $isEdit ? $policy['effective_date'] : date('M j, Y');
$currentVer  = $isEdit ? $policy['version'] : null;
// naive "next version" bump for the demo
$targetVer   = $isEdit ? preg_replace_callback('/(\d+)$/', fn($m) => ((int)$m[1]) + 1, $policy['version']) : 'v1.0';
?>

<form class="form-grid" style="display:grid;grid-template-columns:minmax(0,2.1fr) minmax(280px,1fr);gap:24px;align-items:start;" onsubmit="return false;">

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
						<button type="button" title="Insert image"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 15-5-5L5 20"/></svg></button>
						<button type="button" title="View source"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m8 6-6 6 6 6M16 6l6 6-6 6"/></svg></button>
					</div>
					<div class="editor-body" contenteditable="true">
						<?php if ($isEdit && $policy['title'] === 'Privacy Policy'): ?>
							<h4>Privacy Policy</h4>
							<p>This Privacy Policy explains how TeleCare collects, uses, stores, and protects personal and health-related information...</p>
							<h4>1. Information We Collect</h4>
							<ul>
								<li>Personal information (name, contact details, identification)</li>
								<li>Medical information (consultation records, prescriptions, symptoms)</li>
								<li>Usage information (log data, device information, telemetry)</li>
							</ul>
							<h4>2. How We Use Information</h4>
							<ul>
								<li>To coordinate teleconsultations and doctor appointments</li>
								<li>To maintain electronic medical records securely</li>
								<li>To ensure statutory compliance with Data Privacy Act of 2012</li>
							</ul>
						<?php elseif ($isEdit): ?>
							<p><?= htmlspecialchars($policy['desc']) ?></p>
							<p>Add the full policy text here&hellip;</p>
						<?php else: ?>
							<p>Start writing the policy content here&hellip;</p>
						<?php endif; ?>
					</div>
				</div>
				<p class="hint">Formatting is applied live. Section headings should use bold text or a heading style for clarity in the reader view.</p>
			</div>

			<div class="page-actions" style="justify-content:flex-end;padding-top:4px;">
				<a href="legal_policies.php" class="btn btn-secondary" data-open-modal="modal-discard-changes" onclick="event.preventDefault();">Cancel</a>
				<button type="button" class="btn btn-primary" data-open-modal="modal-save-changes">
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
					<label><input type="checkbox" name="applicable[]" value="all" checked> All Users</label>
					<label><input type="checkbox" name="applicable[]" value="patients"> Patients</label>
					<label><input type="checkbox" name="applicable[]" value="professionals"> Healthcare Professionals</label>
					<label><input type="checkbox" name="applicable[]" value="staff"> Clinic Staff</label>
					<label><input type="checkbox" name="applicable[]" value="others"> Others</label>
				</div>
			</div>

			<div class="version-box">
				<div class="version-box-title">Version Control <span class="pill pill-green">Active</span></div>
				<div class="version-row"><span>Current Active Version:</span><strong><?= $isEdit ? htmlspecialchars($currentVer) : '&mdash;' ?></strong></div>
				<div class="version-row"><span>Target Version on Save:</span><strong><?= htmlspecialchars($targetVer) ?></strong></div>
				<div class="form-group" style="margin-bottom:0;margin-top:4px;">
					<label style="margin-bottom:6px;">Revision Notes <span class="req">*</span></label>
					<textarea class="form-control" name="revision_notes" placeholder="Describe what changed in this revision&hellip;"></textarea>
				</div>
			</div>
		</div>
	</div>
</form>

<!-- Modal: Save changes -->
<div class="modal-overlay" id="modal-save-changes">
	<div class="modal-box">
		<span class="modal-icon success"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg></span>
		<h3 class="modal-title">Save these changes?</h3>
		<p class="modal-text">
			This will save a new revision of <strong><?= htmlspecialchars($title ?: 'this policy') ?></strong>
			<?php if ($isEdit): ?> and bump the version from <strong><?= htmlspecialchars($currentVer) ?></strong> to <strong><?= htmlspecialchars($targetVer) ?></strong><?php else: ?> as <strong><?= htmlspecialchars($targetVer) ?></strong><?php endif; ?>.
			<?php if ($status === 'Published' || !$isEdit): ?>Selecting <strong>Published</strong> status makes it live immediately.<?php endif; ?>
		</p>
		<div class="modal-note">Make sure the revision notes describe what changed &mdash; they're shown in the audit trail.</div>
		<div class="modal-actions">
			<button type="button" class="btn btn-secondary" data-close-modal="modal-save-changes">Keep Editing</button>
			<button type="button" class="btn btn-primary" data-confirm-action="Changes saved.">Save Changes</button>
		</div>
	</div>
</div>

<!-- Modal: Discard changes (Cancel) -->
<div class="modal-overlay" id="modal-discard-changes">
	<div class="modal-box">
		<span class="modal-icon warning"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
		<h3 class="modal-title">Discard unsaved changes?</h3>
		<p class="modal-text">Leaving now will discard anything you've edited on this policy. This can't be undone.</p>
		<div class="modal-actions">
			<button type="button" class="btn btn-secondary" data-close-modal="modal-discard-changes">Keep Editing</button>
			<a href="legal_policies.php" class="btn btn-primary" style="text-align:center;">Discard &amp; Leave</a>
		</div>
	</div>
</div>

<!-- Modal: Preview changes -->
<div class="modal-overlay" id="modal-preview-changes">
	<div class="modal-box" style="max-width:460px;">
		<span class="modal-icon info"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M2 12s3-5 10-5 10 5 10 5-3 5-10 5-10-5-10-5Z"/><circle cx="12" cy="12" r="2"/></svg></span>
		<h3 class="modal-title">Preview before publishing</h3>
		<p class="modal-text">Open the full Version History &amp; Preview page to see exactly how this draft will render for patients and clinicians before you publish it.</p>
		<div class="modal-actions">
			<button type="button" class="btn btn-secondary" data-close-modal="modal-preview-changes">Not Yet</button>
			<a href="policy_history.php<?= $isEdit ? '?id=' . $id : '' ?>" class="btn btn-primary" style="text-align:center;">Open Preview</a>
		</div>
	</div>
</div>

<?php require_once 'includes/footer.php'; ?>