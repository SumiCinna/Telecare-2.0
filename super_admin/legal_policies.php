<?php
// legal_policies.php
require_once 'includes/data.php'; // $policies

$page_title   = 'Legal Policies';
$active_nav   = 'legal_policies';
$breadcrumbs  = [ ['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Legal Policies'] ];
$header_icon  = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6zM9 12h6M9 16h4M9 8h3"/></svg>';
$heading      = 'Legal Policies';
$subtitle     = "Manage the system's legal documents and ensure compliance with applicable laws and regulations.";
$header_actions = '
	<button type="button" class="btn btn-secondary" data-open-modal="modal-workflow-guide">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4v16M4 4h11l-1.5 4L15 12H4"/></svg>
		Workflow Guide
	</button>
	<a href="edit_policy.php" class="btn btn-primary">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
		Create New Policy
	</a>
';
require_once 'includes/header.php';
?>

<div class="panel" style="margin-bottom:24px;">
	<div style="padding:22px 24px 0;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
		<div class="tabline" data-target="#policiesTable">
			<button type="button" class="active" data-filter="all">All Policies</button>
			<button type="button" data-filter="Data &amp; Privacy">Data &amp; Privacy</button>
			<button type="button" data-filter="Terms &amp; Agreements">Terms &amp; Agreements</button>
			<button type="button" data-filter="Payments">Payments</button>
		</div>
		<label class="search" style="width:240px;">
			<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
			<input type="search" id="policySearch" placeholder="Search policies...">
		</label>
	</div>

	<div class="panel-body">
		<table class="data-table" id="policiesTable">
			<thead>
				<tr><th>Title</th><th>Type</th><th>Version</th><th>Last Updated</th><th>Status</th><th>Actions</th></tr>
			</thead>
			<tbody>
				<?php foreach ($policies as $id => $p): ?>
				<tr data-type="<?= htmlspecialchars($p['type']) ?>">
					<td>
						<div class="row-title">
							<span class="row-icon" style="background:<?= $p['icon_bg'] ?>;color:<?= $p['icon_fg'] ?>;">
								<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6zM9 12h6M9 16h4M9 8h3"/></svg>
							</span>
							<div>
								<span class="policy-name"><?= htmlspecialchars($p['title']) ?></span>
								<span class="policy-copy"><?= htmlspecialchars($p['desc']) ?></span>
							</div>
						</div>
					</td>
					<td><span class="pill <?= $p['pill'] ?>"><?= htmlspecialchars($p['type']) ?></span></td>
					<td><?= htmlspecialchars($p['version']) ?></td>
					<td><?= htmlspecialchars($p['updated']) ?><span class="updated-by">by <?= htmlspecialchars($p['updated_by']) ?></span></td>
					<td><span class="pill pill-green"><?= htmlspecialchars($p['status']) ?></span></td>
					<td>
						<div class="row-actions">
							<a class="icon-action" href="policy_history.php?id=<?= $id ?>" title="View version history">
								<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M2 12s3-5 10-5 10 5 10 5-3 5-10 5-10-5-10-5Z"/><circle cx="12" cy="12" r="2"/></svg>
							</a>
							<a class="icon-action" href="edit_policy.php?id=<?= $id ?>" title="Edit policy">
								<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
							</a>
							<button type="button" class="icon-action kebab-btn" title="More actions">
								<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="12" cy="19" r="1.2"/></svg>
							</button>
							<div class="dropdown-menu">
								<button type="button" onclick="window.location='edit_policy.php?id=<?= $id ?>'">
									<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>Edit policy
								</button>
								<button type="button" onclick="window.location='policy_history.php?id=<?= $id ?>'">
									<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Version history
								</button>
								<button type="button" class="danger" data-open-modal="modal-delete-policy" data-fill="<?= htmlspecialchars($p['title']) ?>">
									<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>Delete policy
								</button>
							</div>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="pagination-bar">
			<span>Showing <?= count($policies) ?> of <?= count($policies) ?> active policies</span>
			<div class="pager">
				<button type="button" disabled>Previous</button>
				<button type="button" class="active">1</button>
				<button type="button">Next</button>
			</div>
		</div>
	</div>
</div>

<div class="panel steps-panel">
	<div class="panel-header">
		<div>
			<h3>Policy Management Process</h3>
			<p>Complete workflow cycle for creating, testing, and managing legally compliant documents.</p>
		</div>
		<span class="pill pill-green">System-wide Enforced</span>
	</div>
	<div class="steps-grid">
		<div class="step-card">
			<span class="step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span>
			<div class="step-title">1. Create</div>
			<div class="step-desc">Add new policy and initialize as Draft.</div>
		</div>
		<div class="step-card">
			<span class="step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg></span>
			<div class="step-title">2. Edit</div>
			<div class="step-desc">Modify content, clauses, or targeting.</div>
		</div>
		<div class="step-card">
			<span class="step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M2 12s3-5 10-5 10 5 10 5-3 5-10 5-10-5-10-5Z"/><circle cx="12" cy="12" r="2"/></svg></span>
			<div class="step-title">3. Preview</div>
			<div class="step-desc">Review end-user and patient viewport.</div>
		</div>
		<div class="step-card active">
			<span class="step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
			<div class="step-title">4. Publish</div>
			<div class="step-desc">Make policy live across all client portals.</div>
		</div>
		<div class="step-card">
			<span class="step-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span>
			<div class="step-title">5. Manage</div>
			<div class="step-desc">Audit log versions, restore, or archive.</div>
		</div>
	</div>
</div>

<!-- Modal: Delete policy -->
<div class="modal-overlay" id="modal-delete-policy">
	<div class="modal-box">
		<span class="modal-icon danger"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg></span>
		<h3 class="modal-title">Delete this policy?</h3>
		<p class="modal-text">
			<strong data-fill-target>this policy</strong> and all of its version history will be permanently removed and taken down from every portal where it's currently displayed. This can't be undone.
		</p>
		<div class="modal-actions">
			<button type="button" class="btn btn-secondary" data-close-modal="modal-delete-policy">Cancel</button>
			<button type="button" class="btn btn-primary" data-confirm-action="Policy deleted.">Delete policy</button>
		</div>
	</div>
</div>

<!-- Modal: Workflow guide -->
<div class="modal-overlay" id="modal-workflow-guide">
	<div class="modal-box" style="max-width:460px;">
		<span class="modal-icon info"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4v16M4 4h11l-1.5 4L15 12H4"/></svg></span>
		<h3 class="modal-title">How the policy workflow works</h3>
		<p class="modal-text">Every legal document moves through the same five stages before it reaches a user:</p>
		<div class="modal-note">
			<strong>1. Create</strong> a draft &rarr; <strong>2. Edit</strong> the content &rarr; <strong>3. Preview</strong> exactly how it will render &rarr; <strong>4. Publish</strong> the new version live &rarr; <strong>5. Manage</strong> by auditing, restoring, or archiving older versions.
		</div>
		<div class="modal-actions">
			<button type="button" class="btn btn-primary btn-block" data-close-modal="modal-workflow-guide">Got it</button>
		</div>
	</div>
</div>

<?php require_once 'includes/footer.php'; ?>