<?php
// super_admin/legal_policies.php
require_once __DIR__ . '/../database/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Handle "Delete policy" (POST from the confirm modal below).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (isset($_SESSION['super_admin_id'])) {
        $del_id = (int)$_POST['delete_id'];

        $nameStmt = safe_prepare($conn, "SELECT title FROM legal_policies WHERE id = ?");
        $nameStmt->bind_param("i", $del_id);
        $nameStmt->execute();
        $delTitle = $nameStmt->get_result()->fetch_assoc()['title'] ?? 'Policy';
        $nameStmt->close();

        $stmt = safe_prepare($conn, "DELETE FROM legal_policies WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        $ok = $stmt->execute();
        $deleted = $ok && $stmt->affected_rows > 0;
        $stmt->close();

        $_SESSION['policy_toast'] = $deleted
            ? ['type' => 'success', 'title' => 'Policy deleted', 'message' => '"' . $delTitle . '" and its version history were permanently removed.']
            : ['type' => 'error',   'title' => 'Delete failed',  'message' => 'The policy could not be deleted. Please try again.'];
    } else {
        $_SESSION['policy_toast'] = ['type' => 'error', 'title' => 'Delete failed', 'message' => 'Your session expired. Please log in again.'];
    }
    header('Location: legal_policies.php');
    exit;
}

$page_title = 'Legal Policies';
$active_nav = 'legal_policies';
$breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Legal Policies']];
$header_icon = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6zM9 12h6M9 16h6M9 8h3"/></svg>';
$heading = 'Legal Policies';
$subtitle = "Manage the system's legal documents and ensure compliance with applicable laws and regulations.";
$header_actions = '<button type="button" class="btn btn-secondary" data-open-modal="modal-workflow-guide">Workflow Guide</button><a href="edit_policy.php" class="btn btn-primary">+ Create New Policy</a>';
require_once 'includes/header.php';

// ── Pull every policy from the database ─────────────────────────────────
$policies = [];
$result = $conn->query("SELECT id, slug, title, type, short_desc, version, status, updated_by, updated_at FROM legal_policies ORDER BY type, title");
if ($result) {
    while ($row = $result->fetch_assoc()) { $policies[] = $row; }
}

function policy_type_pill_class(string $type): string {
    switch ($type) {
        case 'Data & Privacy':     return 'pill-blue';
        case 'Terms & Agreements': return 'pill-purple';
        case 'Payments':           return 'pill-orange';
        default:                  return 'pill-outline';
    }
}
function policy_status_pill_class(string $status): string {
    return $status === 'Published' ? 'pill-green' : 'pill-outline';
}
function policy_row_icon(string $type): string {
    if ($type === 'Terms & Agreements') { return '<span class="row-icon" style="background:var(--purple-light);color:var(--purple);">DOC</span>'; }
    if ($type === 'Payments')           { return '<span class="row-icon" style="background:var(--orange-light);color:var(--orange);">PAY</span>'; }
    return '<span class="row-icon" style="background:var(--blue-light);color:var(--blue);"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6zM9 12h6M9 16h6M9 8h3"/></svg></span>';
}

// ── Flash message from edit_policy.php save or the delete action above ──
$policy_toast = $_SESSION['policy_toast'] ?? null;
unset($_SESSION['policy_toast']);
?>

<style>
.notice-banner{display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:13.5px;line-height:1.55;border:1px solid transparent;position:relative;animation:noticeIn .25s ease;}
.notice-banner.success{background:#f0fdf4;border-color:#bbf7d0;color:#15803d;}
.notice-banner.error{background:#fef2f2;border-color:#fecaca;color:#b91c1c;}
.notice-banner .notice-icon{width:20px;height:20px;flex-shrink:0;margin-top:1px;}
.notice-banner .notice-body{flex:1;}
.notice-banner .notice-title{font-weight:700;margin-bottom:2px;}
.notice-banner .notice-close{background:none;border:none;cursor:pointer;color:inherit;opacity:.55;padding:2px;flex-shrink:0;line-height:0;}
.notice-banner .notice-close:hover{opacity:1;}
.notice-banner.fade-out{animation:noticeOut .35s ease forwards;}
@keyframes noticeIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}
@keyframes noticeOut{from{opacity:1;max-height:120px;margin-bottom:20px;}to{opacity:0;max-height:0;margin-bottom:0;padding-top:0;padding-bottom:0;overflow:hidden;}}
</style>

<?php if ($policy_toast): ?>
<div class="notice-banner <?= $policy_toast['type'] === 'success' ? 'success' : 'error' ?>" id="policyToastBanner">
	<?php if ($policy_toast['type'] === 'success'): ?>
		<svg class="notice-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 3 3 5-6"/></svg>
	<?php else: ?>
		<svg class="notice-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5m0 3.5h.01"/></svg>
	<?php endif; ?>
	<div class="notice-body">
		<div class="notice-title"><?= htmlspecialchars($policy_toast['title']) ?></div>
		<?= htmlspecialchars($policy_toast['message']) ?>
	</div>
	<button type="button" class="notice-close" onclick="dismissPolicyToast()">
		<svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
	</button>
</div>
<?php endif; ?>

<div class="panel" style="margin-bottom:24px;">
	<div style="padding:22px 24px 0;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
		<div class="tabline" data-target="#policiesTable"><button type="button" class="active" data-filter="all">All Policies</button><button type="button" data-filter="Data &amp; Privacy">Data &amp; Privacy</button><button type="button" data-filter="Terms &amp; Agreements">Terms &amp; Agreements</button><button type="button" data-filter="Payments">Payments</button></div>
		<label class="search" style="width:240px;"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" id="policySearch" placeholder="Search policies..."></label>
	</div>
	<div class="panel-body">
		<table class="data-table" id="policiesTable"><thead><tr><th>Title</th><th>Type</th><th>Version</th><th>Last Updated</th><th>Status</th><th>Actions</th></tr></thead><tbody>
			<?php if (empty($policies)): ?>
			<tr><td colspan="6"><div class="empty-state"><h3>No policies yet</h3><p>Click "+ Create New Policy" to add your first legal document.</p></div></td></tr>
			<?php endif; ?>
			<?php foreach ($policies as $p): ?>
			<tr data-type="<?= htmlspecialchars($p['type']) ?>">
				<td><div class="row-title"><?= policy_row_icon($p['type']) ?><div><span class="policy-name"><?= htmlspecialchars($p['title']) ?></span><span class="policy-copy"><?= htmlspecialchars($p['short_desc'] ?? '') ?></span></div></div></td>
				<td><span class="pill <?= policy_type_pill_class($p['type']) ?>"><?= htmlspecialchars($p['type']) ?></span></td>
				<td><?= htmlspecialchars($p['version']) ?></td>
				<td><?= htmlspecialchars(date('M j, Y', strtotime($p['updated_at']))) ?><span class="updated-by">by <?= htmlspecialchars($p['updated_by'] ?? 'Super Admin') ?></span></td>
				<td><span class="pill <?= policy_status_pill_class($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
				<td><div class="row-actions">
					<a class="icon-action" href="policy_history.php?id=<?= (int)$p['id'] ?>" title="View version history">View</a>
					<a class="icon-action" href="edit_policy.php?id=<?= (int)$p['id'] ?>" title="Edit policy">Edit</a>
					<button type="button" class="icon-action kebab-btn" title="More actions">...</button>
					<div class="dropdown-menu">
						<button type="button" onclick="window.location='edit_policy.php?id=<?= (int)$p['id'] ?>'">Edit policy</button>
						<button type="button" onclick="window.location='policy_history.php?id=<?= (int)$p['id'] ?>'">Version history</button>
						<button type="button" class="danger" data-open-modal="modal-delete-policy" data-fill="<?= htmlspecialchars($p['title']) ?>" data-delete-id="<?= (int)$p['id'] ?>">Delete policy</button>
					</div>
				</div></td>
			</tr>
			<?php endforeach; ?>
		</tbody></table>
		<div class="pagination-bar"><span>Showing <?= count($policies) ?> of <?= count($policies) ?> active policies</span></div>
	</div>
</div>
<div class="panel steps-panel"><div class="panel-header"><div><h3>Policy Management Process</h3><p>Complete workflow cycle for creating, testing, and managing legally compliant documents.</p></div><span class="pill pill-green">System-wide Enforced</span></div><div class="steps-grid"><div class="step-card"><div class="step-title">1. Create</div><div class="step-desc">Add new policy and initialize as Draft.</div></div><div class="step-card"><div class="step-title">2. Edit</div><div class="step-desc">Modify content, clauses, or targeting.</div></div><div class="step-card"><div class="step-title">3. Preview</div><div class="step-desc">Review the end-user viewport.</div></div><div class="step-card active"><div class="step-title">4. Publish</div><div class="step-desc">Make policy live across client portals.</div></div><div class="step-card"><div class="step-title">5. Manage</div><div class="step-desc">Audit, restore, or archive versions.</div></div></div></div>

<!-- Delete confirmation now really deletes the row (POSTs delete_id back to this page) -->
<div class="modal-overlay" id="modal-delete-policy">
	<form method="POST">
		<div class="modal-box">
			<h3 class="modal-title">Delete this policy?</h3>
			<p class="modal-text"><strong data-fill-target>this policy</strong> and all of its version history will be permanently removed. This can't be undone, and any page that reads it (e.g. registration or payment) will show a "not available" message until you publish a replacement.</p>
			<input type="hidden" name="delete_id" id="deletePolicyId" value="">
			<div class="modal-actions">
				<button type="button" class="btn btn-secondary" data-close-modal="modal-delete-policy">Cancel</button>
				<button type="submit" class="btn btn-primary">Delete</button>
			</div>
		</div>
	</form>
</div>
<div class="modal-overlay" id="modal-workflow-guide"><div class="modal-box"><h3 class="modal-title">How the policy workflow works</h3><p class="modal-text">Create a policy, edit its content, and set its status to <strong>Published</strong> to make it live. Registration, payment, and any other page wired to <code>legal_policy_content()</code> will immediately reflect the published version.</p><div class="modal-actions"><button type="button" class="btn btn-primary btn-block" data-close-modal="modal-workflow-guide">Got it</button></div></div></div>
<script>
	// Wire the delete confirmation modal's hidden field to whichever row's "Delete policy" was clicked.
	document.addEventListener('click', function (e) {
		const btn = e.target.closest('[data-delete-id]');
		if (btn) {
			const idField = document.getElementById('deletePolicyId');
			if (idField) { idField.value = btn.getAttribute('data-delete-id'); }
		}
	});

	// Auto-dismiss the save/delete result banner after a few seconds; also closable by hand.
	function dismissPolicyToast() {
		const el = document.getElementById('policyToastBanner');
		if (!el) return;
		el.classList.add('fade-out');
		setTimeout(() => el.remove(), 350);
	}
	(function () {
		const banner = document.getElementById('policyToastBanner');
		if (banner) { setTimeout(dismissPolicyToast, 6000); }
	})();
</script>
<?php require_once 'includes/footer.php'; ?>