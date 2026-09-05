<?php
// Frontend-only version history and policy preview screen.
$id = 2;
$policy = [
	'title' => 'Privacy Policy',
	'version' => 'v1.3',
	'updated' => 'Aug 10, 2026',
	'updated_by' => 'Super Admin',
	'status' => 'Published',
	'effective_date' => 'Aug 10, 2026',
];
$versions = [
	['version' => 'v1.3', 'date' => 'Aug 10, 2026', 'by' => 'Super Admin', 'status' => 'Published', 'current' => true],
	['version' => 'v1.2', 'date' => 'Jul 15, 2026', 'by' => 'Super Admin', 'status' => 'Published', 'current' => false],
	['version' => 'v1.1', 'date' => 'Jun 20, 2026', 'by' => 'Super Admin', 'status' => 'Published', 'current' => false],
	['version' => 'v1.0', 'date' => 'May 1, 2026', 'by' => 'Super Admin', 'status' => 'Archived', 'current' => false],
];
$changes = [
	'from' => 'v1.2', 'to' => 'v1.3', 'date' => 'Aug 10, 2026, 11:20 AM',
	'items' => [
		['title' => 'Added Section 1.9: Telehealth Encryption Protocols', 'copy' => 'Complies with Data Privacy Act of 2012 (RA 10173) and end-to-end WebRTC security mandates.'],
		['title' => 'Updated Data Retention Period', 'copy' => 'Electronic Medical Records (EMR) retention specified to mandatory minimum of 10 years per clinical liability.'],
		['title' => 'Super Admin Signature Attached', 'copy' => 'Digitally countersigned by Compliance Officer (ID: 4CO-8212).'],
	],
];

$page_title  = 'Version History & Preview';
$active_nav  = 'legal_policies';
$breadcrumbs = [
	['label' => 'Dashboard', 'href' => 'dashboard.php'],
	['label' => 'Legal Policies', 'href' => 'legal_policies.php'],
	['label' => $policy['title']],
];
$header_icon  = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5"/></svg>';
$heading      = htmlspecialchars($policy['title']) . ' &ndash; Versions &amp; Live Preview';
$heading_pill = '<span class="pill pill-blue">Active ' . htmlspecialchars($policy['version']) . '</span>';
$subtitle     = 'Inspect historical revisions, rollback previous versions, or verify how the policy renders across patient and clinician touchpoints.';
$header_actions = '
	<a href="legal_policies.php" class="btn btn-secondary">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 6 9 12l6 6"/></svg>
		Back to Policies
	</a>
	<a href="edit_policy.php?id=' . $id . '" class="btn btn-secondary">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
		Edit Current Draft
	</a>
	<button type="button" class="btn btn-primary" data-open-modal="modal-publish-revision">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
		Publish New Revision
	</button>
';
require_once 'includes/header.php';
?>

<div class="two-col-grid">
	<div style="display:flex;flex-direction:column;gap:24px;">

		<div class="panel">
			<div class="panel-header">
				<div>
					<h3><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Version History</h3>
					<p>View and restore previous versions of this policy.</p>
				</div>
				<span class="pill pill-outline">Current: <?= htmlspecialchars($policy['version']) ?></span>
			</div>
			<div class="panel-body">
				<table class="data-table">
					<thead><tr><th>Version</th><th>Date Updated</th><th>Updated By</th><th>Status</th><th>Actions</th></tr></thead>
					<tbody>
						<?php foreach ($versions as $v): ?>
						<tr>
							<td>
								<?php if (!empty($v['current'])): ?><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--blue);margin-right:7px;"></span><?php endif; ?>
								<strong style="color:var(--ink);"><?= htmlspecialchars($v['version']) ?></strong>
							</td>
							<td><?= htmlspecialchars($v['date']) ?></td>
							<td><?= htmlspecialchars($v['by']) ?></td>
							<td><span class="pill <?= $v['status'] === 'Published' ? 'pill-green' : 'pill-outline' ?>"><?= htmlspecialchars($v['status']) ?></span></td>
							<td>
								<div class="row-actions" style="justify-content:flex-start;">
									<a href="#" style="color:var(--blue);font-weight:600;font-size:11.5px;">View</a>
									<?php if (empty($v['current'])): ?>
										<button type="button" style="background:none;border:0;color:var(--red);font-weight:600;font-size:11.5px;cursor:pointer;padding:0;"
											data-open-modal="modal-restore-version" data-fill="<?= htmlspecialchars($v['version']) ?>">Restore</button>
									<?php endif; ?>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="pagination-bar" style="border-top:none;padding-top:10px;">
					<span>Showing <?= count($versions) ?> of <?= count($versions) ?> historical releases</span>
					<a href="#" style="color:var(--blue);font-weight:600;">Download Audit Trail &darr;</a>
				</div>
			</div>
		</div>

		<?php if ($changes): ?>
		<div class="panel">
			<div class="panel-header">
				<div>
					<h3><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>Change Summary: <?= htmlspecialchars($changes['from']) ?> &rarr; <?= htmlspecialchars($changes['to']) ?></h3>
					<p><?= htmlspecialchars($changes['date']) ?></p>
				</div>
			</div>
			<div class="panel-body">
				<div class="change-list">
					<?php foreach ($changes['items'] as $item): ?>
					<div class="change-item">
						<span class="change-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="m5 12 4 4 10-10"/></svg></span>
						<div>
							<div class="change-title"><?= htmlspecialchars($item['title']) ?></div>
							<div class="change-copy"><?= htmlspecialchars($item['copy']) ?></div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="enforce-banner">
			<span class="enforce-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m12 3 8 4v5c0 4.5-3.5 7.8-8 9-4.5-1.2-8-4.5-8-9V7z"/><path d="m9 12 2 2 4-4"/></svg></span>
			<div>
				<strong>Automated Legal Enforceability Active</strong>
				<span>All registered clinics and practitioners have accepted version <?= htmlspecialchars($policy['version']) ?>. Node cluster: PH-MNL-01.</span>
			</div>
		</div>
	</div>

	<div style="display:flex;flex-direction:column;gap:24px;">
		<div class="panel">
			<div class="panel-header">
				<div>
					<h3>Preview Policy</h3>
				</div>
				<span class="pill pill-live">Live User View</span>
			</div>
			<div style="display:flex;gap:8px;padding:0 22px 14px;flex-wrap:wrap;">
				<a href="policy_history.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Back</a>
				<a href="edit_policy.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">Edit</a>
				<button type="button" class="btn btn-danger-outline btn-sm" data-open-modal="modal-unpublish">Unpublish</button>
			</div>
			<div class="panel-body" style="padding-top:0;">
				<div class="preview-doc">
					<h4><?= htmlspecialchars($policy['title']) ?></h4>
					<div class="eff-date">Effective Date: <?= htmlspecialchars($policy['effective_date']) ?> &middot; <span style="color:#07966c;">Published and visible to users</span> &middot; Version <?= htmlspecialchars(str_replace('v', '', $policy['version'])) ?></div>
					<h5>1. Introduction</h5>
					<p>This Privacy Policy explains how TeleCare Health Solutions Inc. ("TeleCare", "we", "us," or "our") collects, uses, stores, and protects personal and health-related information of users, healthcare professionals, and partner clinics when utilizing our teleconsulting platform, mobile endpoints, and administrative portals.</p>
					<h5>2. Information We Collect</h5>
					<ul>
						<li><strong>Personal Identity Data:</strong> Full name, date of birth, government-issued IDs, contact number, and residential address.</li>
						<li><strong>Protected Health Information (PHI):</strong> Consultation transcripts, digital triage notes, lab requisitions, e-prescriptions, and past medical history.</li>
					</ul>
					<h5>8. Where the Policies Will Be Displayed (User Side)</h5>
					<p>The published policy automatically appears in the following areas of the system:</p>
				</div>
			</div>
			<div class="preview-doc-foot">
				<span>Synchronized across Web, iOS Patient App, and Android Clinician App</span>
				<a href="#">Open in Fullscreen Reader &nearr;</a>
			</div>
		</div>

		<div class="panel">
			<div class="panel-header"><h3>Where the Policies Will Be Displayed</h3></div>
			<div class="panel-body">
				<div class="placement-list">
					<div class="placement-row">
						<div>
							<div class="t">User Registration</div>
							<div class="d">Checkbox to agree to Terms of Use and Privacy Policy.</div>
							<div class="check-ok" style="margin-top:6px;"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4 10-10"/></svg>Live on registration form</div>
						</div>
						<div class="mini-browser">
							<div class="mini-browser-bar">LIVE UI PREVIEW</div>
							<div class="mini-browser-body">
								<input type="checkbox" checked disabled>
								<span>I agree to the <u>Terms of Use</u> and <u>Privacy Policy</u>.</span>
							</div>
						</div>
					</div>
					<div class="placement-row">
						<div>
							<div class="t">Login Page</div>
							<div class="d">Link to view policies in patient portal login.</div>
							<div class="check-ok" style="margin-top:6px;"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4 10-10"/></svg>Live on login footer</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Modal: Publish new revision -->
<div class="modal-overlay" id="modal-publish-revision">
	<div class="modal-box">
		<span class="modal-icon success"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg></span>
		<h3 class="modal-title">Publish this revision?</h3>
		<p class="modal-text">
			This makes the current draft of <strong><?= htmlspecialchars($policy['title']) ?></strong> the active, legally binding version across every client portal, replacing <strong><?= htmlspecialchars($policy['version']) ?></strong>.
		</p>
		<div class="modal-note">Users won't be asked to re-accept unless "Applicable To" scope has changed.</div>
		<div class="modal-actions">
			<button type="button" class="btn btn-secondary" data-close-modal="modal-publish-revision">Cancel</button>
			<button type="button" class="btn btn-primary" data-confirm-action="New revision published.">Publish Revision</button>
		</div>
	</div>
</div>

<!-- Modal: Restore version -->
<div class="modal-overlay" id="modal-restore-version">
	<div class="modal-box">
		<span class="modal-icon warning"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/></svg></span>
		<h3 class="modal-title">Restore version <span data-fill-target><?= htmlspecialchars($versions[0]['version']) ?></span>?</h3>
		<p class="modal-text">This creates a new draft based on version <strong data-fill-target><?= htmlspecialchars($versions[0]['version']) ?></strong>'s content. Your current draft, if unsaved, will not be affected until you publish.</p>
		<div class="modal-actions">
			<button type="button" class="btn btn-secondary" data-close-modal="modal-restore-version">Cancel</button>
			<button type="button" class="btn btn-primary" data-confirm-action="Version restored as a new draft.">Restore Version</button>
		</div>
	</div>
</div>

<!-- Modal: Unpublish -->
<div class="modal-overlay" id="modal-unpublish">
	<div class="modal-box">
		<span class="modal-icon danger"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A9.6 9.6 0 0 1 12 4c5 0 8.5 3.5 10 8-.5 1.4-1.2 2.6-2.1 3.7M6.2 6.2C4.3 7.5 2.9 9.5 2 12c1 2.8 3 5 5.6 6.4"/></svg></span>
		<h3 class="modal-title">Unpublish this policy?</h3>
		<p class="modal-text">
			<strong><?= htmlspecialchars($policy['title']) ?></strong> will be taken down from all live portals immediately. Users will no longer be able to view or accept it until it's published again.
		</p>
		<div class="modal-actions">
			<button type="button" class="btn btn-secondary" data-close-modal="modal-unpublish">Cancel</button>
			<button type="button" class="btn btn-primary" data-confirm-action="Policy unpublished.">Unpublish</button>
		</div>
	</div>
</div>

<?php require_once 'includes/footer.php'; ?>