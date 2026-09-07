<?php
// super_admin/policy_history.php
require_once __DIR__ . '/../database/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: legal_policies.php'); exit; }

// ── Handle actions (publish / unpublish / restore) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['super_admin_id'])) {
    $action = $_POST['action'] ?? '';
    $updated_by = $_SESSION['super_admin_name'] ?? 'Super Admin';

    if ($action === 'publish' || $action === 'unpublish') {
        $newStatus = $action === 'publish' ? 'Published' : 'Draft';
        $stmt = $conn->prepare("UPDATE legal_policies SET status = ?, updated_by = ? WHERE id = ?");
        $stmt->bind_param("ssi", $newStatus, $updated_by, $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'restore' && isset($_POST['version_id'])) {
        $version_id = (int)$_POST['version_id'];
        $stmt = $conn->prepare("SELECT version, content FROM legal_policy_versions WHERE id = ? AND policy_id = ?");
        $stmt->bind_param("ii", $version_id, $id);
        $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($old) {
            // Bump to a new version number based on the current one.
            $curStmt = $conn->prepare("SELECT version FROM legal_policies WHERE id = ?");
            $curStmt->bind_param("i", $id);
            $curStmt->execute();
            $cur = $curStmt->get_result()->fetch_assoc();
            $curStmt->close();
            $targetVer = preg_replace_callback('/(\d+)$/', fn($m) => ((int)$m[1]) + 1, $cur['version'] ?? 'v1.0');

            $upd = $conn->prepare("UPDATE legal_policies SET content = ?, version = ?, status = 'Draft', updated_by = ? WHERE id = ?");
            $upd->bind_param("sssi", $old['content'], $targetVer, $updated_by, $id);
            $upd->execute();
            $upd->close();

            $notes = "Restored from {$old['version']}.";
            $ins = $conn->prepare("INSERT INTO legal_policy_versions (policy_id, version, content, status, revision_notes, updated_by) VALUES (?, ?, ?, 'Draft', ?, ?)");
            $ins->bind_param("issss", $id, $targetVer, $old['content'], $notes, $updated_by);
            $ins->execute();
            $ins->close();
        }
    }
    header('Location: policy_history.php?id=' . $id);
    exit;
}

// ── Load policy + version history ───────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM legal_policies WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$policy = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$policy) { header('Location: legal_policies.php'); exit; }

$versions = [];
$vstmt = $conn->prepare("SELECT id, version, status, updated_by, created_at FROM legal_policy_versions WHERE policy_id = ? ORDER BY created_at DESC, id DESC");
$vstmt->bind_param("i", $id);
$vstmt->execute();
$vres = $vstmt->get_result();
while ($row = $vres->fetch_assoc()) { $versions[] = $row; }
$vstmt->close();

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
$subtitle     = 'Inspect historical revisions, restore previous versions, or verify how the policy renders across patient touchpoints.';
$header_actions = '
	<a href="legal_policies.php" class="btn btn-secondary">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 6 9 12l6 6"/></svg>
		Back to Policies
	</a>
	<a href="edit_policy.php?id=' . $id . '" class="btn btn-secondary">
		<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>
		Edit Current Draft
	</a>
	' . ($policy['status'] === 'Published'
		? '<button type="button" class="btn btn-danger-outline" data-open-modal="modal-unpublish">Unpublish</button>'
		: '<button type="button" class="btn btn-primary" data-open-modal="modal-publish-revision">Publish</button>');
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
						<?php if (empty($versions)): ?>
						<tr><td colspan="5"><div class="empty-state"><h3>No history yet</h3><p>Save a change on this policy to start its version trail.</p></div></td></tr>
						<?php endif; ?>
						<?php foreach ($versions as $i => $v): $isCurrent = $v['version'] === $policy['version']; ?>
						<tr>
							<td>
								<?php if ($isCurrent): ?><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--blue);margin-right:7px;"></span><?php endif; ?>
								<strong style="color:var(--ink);"><?= htmlspecialchars($v['version']) ?></strong>
							</td>
							<td><?= htmlspecialchars(date('M j, Y', strtotime($v['created_at']))) ?></td>
							<td><?= htmlspecialchars($v['updated_by'] ?? 'Super Admin') ?></td>
							<td><span class="pill <?= $v['status'] === 'Published' ? 'pill-green' : 'pill-outline' ?>"><?= htmlspecialchars($v['status']) ?></span></td>
							<td>
								<div class="row-actions" style="justify-content:flex-start;">
									<?php if (!$isCurrent): ?>
									<form method="POST" style="display:inline;" onsubmit="return confirm('Restore version <?= htmlspecialchars($v['version']) ?> as a new draft?');">
										<input type="hidden" name="action" value="restore">
										<input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
										<button type="submit" style="background:none;border:0;color:var(--red);font-weight:600;font-size:11.5px;cursor:pointer;padding:0;">Restore</button>
									</form>
									<?php else: ?>
									<span style="color:var(--muted);font-size:11.5px;">Current</span>
									<?php endif; ?>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="pagination-bar" style="border-top:none;padding-top:10px;">
					<span>Showing <?= count($versions) ?> of <?= count($versions) ?> historical releases</span>
				</div>
			</div>
		</div>

		<div class="enforce-banner">
			<span class="enforce-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m12 3 8 4v5c0 4.5-3.5 7.8-8 9-4.5-1.2-8-4.5-8-9V7z"/><path d="m9 12 2 2 4-4"/></svg></span>
			<div>
				<strong><?= $policy['status'] === 'Published' ? 'Live and Enforced' : 'Not Currently Published' ?></strong>
				<span><?= $policy['status'] === 'Published'
					? 'This is the version shown on registration, payment, and any other page wired to this policy.'
					: 'This policy is in Draft/Archived status and will not appear on live pages until published.' ?></span>
			</div>
		</div>
	</div>

	<div style="display:flex;flex-direction:column;gap:24px;">
		<div class="panel">
			<div class="panel-header">
				<div>
					<h3>Preview Policy</h3>
				</div>
				<span class="pill <?= $policy['status'] === 'Published' ? 'pill-live' : 'pill-outline' ?>"><?= $policy['status'] === 'Published' ? 'Live User View' : htmlspecialchars($policy['status']) ?></span>
			</div>
			<div class="panel-body" style="padding-top:14px;">
				<div class="preview-doc">
					<h4><?= htmlspecialchars($policy['title']) ?></h4>
					<div class="eff-date">Effective Date: <?= htmlspecialchars($policy['effective_date'] ? date('M j, Y', strtotime($policy['effective_date'])) : '—') ?> &middot;
						<span style="color:<?= $policy['status'] === 'Published' ? '#07966c' : '#94a3b8' ?>;"><?= htmlspecialchars($policy['status']) ?></span> &middot;
						Version <?= htmlspecialchars(str_replace('v', '', $policy['version'])) ?>
					</div>
					<?= $policy['content'] ?>
				</div>
			</div>
		</div>

		<div class="panel">
			<div class="panel-header"><h3>Where This Policy Appears</h3></div>
			<div class="panel-body">
				<div class="placement-list">
					<div class="placement-row">
						<div>
							<div class="t">Registration &amp; Payment Pages</div>
							<div class="d">Any page calling <code>legal_policy_content($conn, '<?= htmlspecialchars($policy['slug']) ?>')</code> shows this content live.</div>
							<?php if ($policy['status'] === 'Published'): ?>
							<div class="check-ok" style="margin-top:6px;"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4 10-10"/></svg>Currently live</div>
							<?php else: ?>
							<div style="margin-top:6px;color:#94a3b8;font-size:11.5px;">Not live &mdash; publish to display this content</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Modal: Publish -->
<div class="modal-overlay" id="modal-publish-revision">
	<form method="POST">
		<input type="hidden" name="action" value="publish">
		<div class="modal-box">
			<span class="modal-icon success"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg></span>
			<h3 class="modal-title">Publish this policy?</h3>
			<p class="modal-text">This makes <strong><?= htmlspecialchars($policy['title']) ?></strong> (<?= htmlspecialchars($policy['version']) ?>) the live, active version shown on registration, payment, and any other connected page.</p>
			<div class="modal-actions">
				<button type="button" class="btn btn-secondary" data-close-modal="modal-publish-revision">Cancel</button>
				<button type="submit" class="btn btn-primary">Publish</button>
			</div>
		</div>
	</form>
</div>

<!-- Modal: Unpublish -->
<div class="modal-overlay" id="modal-unpublish">
	<form method="POST">
		<input type="hidden" name="action" value="unpublish">
		<div class="modal-box">
			<span class="modal-icon danger"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 4.2A9.6 9.6 0 0 1 12 4c5 0 8.5 3.5 10 8-.5 1.4-1.2 2.6-2.1 3.7M6.2 6.2C4.3 7.5 2.9 9.5 2 12c1 2.8 3 5 5.6 6.4"/></svg></span>
			<h3 class="modal-title">Unpublish this policy?</h3>
			<p class="modal-text"><strong><?= htmlspecialchars($policy['title']) ?></strong> will be taken down from all connected pages immediately &mdash; they'll show a "not available" message until it's published again.</p>
			<div class="modal-actions">
				<button type="button" class="btn btn-secondary" data-close-modal="modal-unpublish">Cancel</button>
				<button type="submit" class="btn btn-primary">Unpublish</button>
			</div>
		</div>
	</form>
</div>

<?php require_once 'includes/footer.php'; ?>
