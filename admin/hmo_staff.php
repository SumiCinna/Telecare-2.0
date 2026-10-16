<?php
// admin/hmo_staff.php — 4. HMO Staff & Permissions (who may do what; no case assignment: every staff member sees every case)
require_once 'includes/hmo_admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hmo_admin_csrf_ok()) hmo_admin_back('hmo_staff.php', 'Your session expired. Please try again.', true);
    $sid = (int)($_POST['staff_id'] ?? 0);
    $st = $conn->prepare("SELECT id, full_name FROM staff_accounts WHERE id = ?"); $st->bind_param('i', $sid); $st->execute();
    $s = $st->get_result()->fetch_assoc();
    if (!$s) hmo_admin_back('hmo_staff.php', 'Staff member not found.', true);
    $old = []; $new = [];
    foreach (HMO_PERMS as $k => $_) {
        $old[$k] = hmo_staff_can($conn, $sid, $k) ? 1 : 0;
        $new[$k] = !empty($_POST[$k]) ? 1 : 0;
    }
    $q = $conn->prepare("INSERT INTO hmo_staff_permissions (staff_id, can_verify, can_update_status, can_view_documents, updated_by, updated_at) VALUES (?,?,?,?,?,NOW())
                         ON DUPLICATE KEY UPDATE can_verify=VALUES(can_verify), can_update_status=VALUES(can_update_status), can_view_documents=VALUES(can_view_documents), updated_by=VALUES(updated_by), updated_at=NOW()");
    $q->bind_param('iiiii', $sid, $new['can_verify'], $new['can_update_status'], $new['can_view_documents'], $admin_id);
    $q->execute();
    if ($old !== $new) hmo_admin_audit($conn, $admin_id, 'permissions', 'hmo_staff', $sid, $old, $new);
    hmo_admin_back('hmo_staff.php', 'Permissions saved for ' . $s['full_name'] . '.');
}

$csrf = hmo_admin_csrf();
$staff = $conn->query("SELECT s.id, s.full_name, s.email, s.status, s.role, p.can_verify, p.can_update_status, p.can_view_documents,
        (SELECT COUNT(*) FROM hmo_access_log l WHERE l.actor_type='staff' AND l.actor_id = s.id) AS n_access,
        (SELECT COUNT(*) FROM hmo_case_events e WHERE e.staff_id = s.id) AS n_actions,
        (SELECT MAX(x.t) FROM (SELECT staff_id, created_at AS t FROM hmo_case_events UNION ALL SELECT actor_id, created_at FROM hmo_access_log WHERE actor_type='staff') x WHERE x.staff_id = s.id) AS last_seen
    FROM staff_accounts s LEFT JOIN hmo_staff_permissions p ON p.staff_id = s.id ORDER BY s.full_name")->fetch_all(MYSQLI_ASSOC);

$recent = $conn->query("SELECT l.*, s.full_name AS who FROM hmo_access_log l LEFT JOIN staff_accounts s ON l.actor_type='staff' AND s.id = l.actor_id ORDER BY l.id DESC LIMIT 25")->fetch_all(MYSQLI_ASSOC);

hmo_admin_head('HMO Staff & Permissions', 'hmo-staff');
?>
<div class="hx-card"><h3>Permissions</h3>
  <div class="sub">Every staff member can see every HMO case. These switches control what they can <b>do</b>. Staff without a saved row can do everything. Every document view is logged below.</div>
  <?php if (!$staff): ?><div class="hx-muted">No staff accounts yet.</div><?php else: ?>
  <table class="hx-mini"><tr><th>Staff</th><?php foreach (HMO_PERMS as $l): ?><th><?= h($l) ?></th><?php endforeach; ?><th>Activity</th><th></th></tr>
  <?php foreach ($staff as $s): $pv = fn($k) => $s[$k] === null ? true : (int)$s[$k] === 1; ?>
    <tr><td><b><?= h($s['full_name']) ?></b><div class="hx-muted"><?= h($s['email']) ?><?= $s['status'] !== 'active' ? ' · inactive' : '' ?></div></td>
      <?php foreach (HMO_PERMS as $k => $_): ?><td><input type="checkbox" form="sf<?= (int)$s['id'] ?>" name="<?= $k ?>" value="1" <?= $pv($k) ? 'checked' : '' ?>/></td><?php endforeach; ?>
      <td class="hx-muted"><?= (int)$s['n_actions'] ?> case actions<br/><?= (int)$s['n_access'] ?> document views<br/><?= $s['last_seen'] ? 'Last: ' . h(date('M j, g:i A', strtotime($s['last_seen']))) : 'No activity' ?></td>
      <td><button class="btn-sm btn-edit" form="sf<?= (int)$s['id'] ?>" type="submit">Save</button></td></tr>
  <?php endforeach; ?></table>
  <?php foreach ($staff as $s): ?><form method="POST" id="sf<?= (int)$s['id'] ?>"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"/><input type="hidden" name="staff_id" value="<?= (int)$s['id'] ?>"/></form><?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="hx-card"><h3>Recent access history</h3><div class="sub">Document views and exports. Full history: <a href="hmo_audit.php">Audit Logs</a>.</div>
  <?php if (!$recent): ?><div class="hx-muted">Nothing yet.</div><?php else: ?>
  <table class="hx-mini"><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr>
  <?php foreach ($recent as $l): ?><tr><td><?= h(date('M j, Y g:i A', strtotime($l['created_at']))) ?></td><td><?= h($l['who'] ?: ucfirst($l['actor_type']) . ' #' . $l['actor_id']) ?></td><td><?= h($l['action']) ?></td><td><?= h($l['detail']) ?></td></tr><?php endforeach; ?></table><?php endif; ?>
</div>
<?php hmo_admin_foot();
