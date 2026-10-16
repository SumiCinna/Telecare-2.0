<?php
// admin/hmo_audit.php — 7. Audit Logs & Configuration History
require_once 'includes/hmo_admin.php';

$tab = $_GET['tab'] ?? 'config';
if (!in_array($tab, ['config', 'status', 'access'], true)) $tab = 'config';
$from = hmo_admin_date($_GET['from'] ?? ''); $to = hmo_admin_date($_GET['to'] ?? '');
$qtxt = trim((string)($_GET['q'] ?? ''));
$hasHmo = $conn->query("SHOW TABLES LIKE 'appointment_hmo'")->num_rows > 0;

$where = ['1=1']; $types = ''; $args = []; $col = $tab === 'status' ? 'e.created_at' : ($tab === 'access' ? 'l.created_at' : 'l.created_at');
if ($from) { $where[] = "DATE($col) >= ?"; $types .= 's'; $args[] = $from; }
if ($to)   { $where[] = "DATE($col) <= ?"; $types .= 's'; $args[] = $to; }

$rows = [];
try {
    if ($tab === 'config') {
        $where[] = "l.entity_type IN ('hmo_provider','hmo_contact','hmo_rule','hmo_doctors','hmo_staff')";
        if ($qtxt !== '') { $where[] = "(l.action LIKE ? OR l.entity_type LIKE ? OR l.new_values LIKE ? OR l.old_values LIKE ?)"; $types .= 'ssss'; $l = '%' . $qtxt . '%'; array_push($args, $l, $l, $l, $l); }
        $sql = "SELECT l.created_at, COALESCE(a.full_name, CONCAT('Admin #', l.admin_id)) AS who, l.action, l.entity_type, l.entity_id, l.old_values, l.new_values,
                  COALESCE(p.name, '') AS provider FROM audit_logs l LEFT JOIN admins a ON a.id = l.admin_id
                  LEFT JOIN hmo_providers p ON p.id = l.entity_id AND l.entity_type IN ('hmo_provider','hmo_contact','hmo_rule','hmo_doctors')
                  WHERE " . implode(' AND ', $where) . " ORDER BY l.id DESC LIMIT 300";
    } elseif ($tab === 'status') {
        $where[] = "e.event_type IN ('Status','Authorization','Patient','Assigned','Contact')";
        if ($qtxt !== '') { $where[] = "(e.summary LIKE ? OR e.detail LIKE ? OR a.reference_no LIKE ?)"; $types .= 'sss'; $l = '%' . $qtxt . '%'; array_push($args, $l, $l, $l); }
        $sql = "SELECT e.created_at, COALESCE(s.full_name, IF(e.event_type='Patient','Patient','System')) AS who, e.event_type, e.status_from, e.status_to, e.summary, e.detail, a.reference_no, e.appointment_id
                FROM hmo_case_events e JOIN appointments a ON a.id = e.appointment_id LEFT JOIN staff_accounts s ON s.id = e.staff_id
                WHERE " . implode(' AND ', $where) . " ORDER BY e.id DESC LIMIT 300";
    } else {
        if ($qtxt !== '') { $where[] = "(l.action LIKE ? OR l.detail LIKE ?)"; $types .= 'ss'; $l = '%' . $qtxt . '%'; array_push($args, $l, $l); }
        $sql = "SELECT l.created_at, l.actor_type, l.action, l.entity, l.detail, l.ip, l.appointment_id,
                  CASE l.actor_type WHEN 'staff' THEN COALESCE(s.full_name, CONCAT('Staff #', l.actor_id)) ELSE COALESCE(a.full_name, CONCAT('Admin #', l.actor_id)) END AS who
                FROM hmo_access_log l LEFT JOIN staff_accounts s ON l.actor_type='staff' AND s.id = l.actor_id LEFT JOIN admins a ON l.actor_type='admin' AND a.id = l.actor_id
                WHERE " . implode(' AND ', $where) . " ORDER BY l.id DESC LIMIT 300";
    }
    if (!$hasHmo && $tab === 'status') { $rows = []; }
    else { $st = $conn->prepare($sql); if ($types !== '') $st->bind_param($types, ...$args); $st->execute(); $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC); }
} catch (Throwable $e) { error_log('hmo_audit: ' . $e->getMessage()); }

if (isset($_GET['export'])) {
    hmo_log_access($conn, 'admin', $admin_id, 'export', 'hmo_audit_' . $tab, null, null, count($rows) . ' rows');
    if ($tab === 'config') hmo_admin_csv('hmo_config_history.csv', ['When', 'Who', 'Action', 'Type', 'Provider', 'Previous', 'New'], array_map(fn($r) => [$r['created_at'], $r['who'], $r['action'], $r['entity_type'], $r['provider'], $r['old_values'], $r['new_values']], $rows));
    if ($tab === 'status') hmo_admin_csv('hmo_status_history.csv', ['When', 'Who', 'Booking ref', 'Type', 'From', 'To', 'What', 'Reason / detail'], array_map(fn($r) => [$r['created_at'], $r['who'], $r['reference_no'], $r['event_type'], $r['status_from'], $r['status_to'], $r['summary'], $r['detail']], $rows));
    hmo_admin_csv('hmo_access_log.csv', ['When', 'Who', 'Type', 'Action', 'What', 'Detail', 'IP'], array_map(fn($r) => [$r['created_at'], $r['who'], $r['actor_type'], $r['action'], $r['entity'], $r['detail'], $r['ip']], $rows));
}
$qs = http_build_query(array_filter(['tab' => $tab, 'from' => $from, 'to' => $to, 'q' => $qtxt]));

hmo_admin_head('Audit Logs & Configuration History', 'hmo-audit', '<a class="hx-btn ghost" href="?' . h($qs) . '&export=1">Export CSV</a>');
?>
<div class="hx-tabs">
  <?php foreach (['config' => 'Configuration changes', 'status' => 'Case status & actions', 'access' => 'Document access & exports'] as $k => $l): ?>
    <a class="hx-tab <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= h($l) ?></a><?php endforeach; ?>
</div>
<form class="hx-filter" method="GET"><input type="hidden" name="tab" value="<?= h($tab) ?>"/>
  <div><label class="hx-lbl">From</label><input class="hx-in" type="date" name="from" value="<?= h($from) ?>"/></div>
  <div><label class="hx-lbl">To</label><input class="hx-in" type="date" name="to" value="<?= h($to) ?>"/></div>
  <div><label class="hx-lbl">Search</label><input class="hx-in" name="q" value="<?= h($qtxt) ?>"/></div>
  <div><button class="hx-btn" type="submit">Filter</button></div>
</form>
<div class="table-wrap"><table>
<?php if ($tab === 'config'): ?>
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Provider / item</th><th>Previous values</th><th>New values</th></tr></thead><tbody>
  <?php if (!$rows) echo '<tr><td colspan="6" class="empty-row">No configuration changes recorded.</td></tr>'; foreach ($rows as $r): ?>
  <tr><td><?= h(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td><td><?= h($r['who']) ?></td><td><?= h($r['entity_type'] . ' · ' . $r['action']) ?></td><td><?= h($r['provider'] ?: ('#' . $r['entity_id'])) ?></td>
    <td><div class="hx-diff"><?= h($r['old_values'] ?? '—') ?></div></td><td><div class="hx-diff"><?= h($r['new_values'] ?? '—') ?></div></td></tr><?php endforeach; ?>
<?php elseif ($tab === 'status'): ?>
  <thead><tr><th>When</th><th>Who</th><th>Booking</th><th>What happened</th><th>Status</th><th>Reason / detail</th></tr></thead><tbody>
  <?php if (!$rows) echo '<tr><td colspan="6" class="empty-row">Nothing recorded.</td></tr>'; foreach ($rows as $r): ?>
  <tr><td><?= h(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td><td><?= h($r['who']) ?></td><td><a href="hmo_transactions.php?case=<?= (int)$r['appointment_id'] ?>"><?= h($r['reference_no'] ?: '#' . $r['appointment_id']) ?></a></td>
    <td><?= h($r['summary']) ?></td><td><?= $r['status_from'] && $r['status_to'] ? h($r['status_from'] . ' → ' . $r['status_to']) : '—' ?></td><td><?= $r['detail'] ? '<div class="hx-diff">' . h($r['detail']) . '</div>' : '—' ?></td></tr><?php endforeach; ?>
<?php else: ?>
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead><tbody>
  <?php if (!$rows) echo '<tr><td colspan="5" class="empty-row">No document access or exports recorded yet.</td></tr>'; foreach ($rows as $r): ?>
  <tr><td><?= h(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td><td><?= h($r['who']) ?> <span class="hx-muted">(<?= h($r['actor_type']) ?>)</span></td><td><?= h($r['action']) ?></td><td><?= h($r['detail']) ?></td><td class="hx-muted"><?= h($r['ip']) ?></td></tr><?php endforeach; ?>
<?php endif; ?>
</tbody></table></div>
<div class="hx-muted" style="margin-top:.6rem">Showing the latest 300 entries. Staff case actions record the staff name, the time and the reason entered.</div>
<?php hmo_admin_foot();
