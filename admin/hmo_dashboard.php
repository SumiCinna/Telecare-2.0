<?php
// admin/hmo_dashboard.php — 1. HMO Dashboard
require_once 'includes/hmo_admin.php';

$one = function (string $sql) use ($conn) { try { $r = $conn->query($sql); $row = $r ? $r->fetch_row() : null; return $row ? $row[0] : null; } catch (Throwable $e) { return null; } };
$tables = $one("SHOW TABLES LIKE 'appointment_hmo'") !== null;

$total     = (int)$one("SELECT COUNT(*) FROM hmo_providers");
$suspended = (int)$one("SELECT COUNT(*) FROM hmo_providers WHERE on_hold = 1");
$inactive  = (int)$one("SELECT COUNT(*) FROM hmo_providers WHERE status <> 'Active' AND on_hold = 0");
$active    = $total - $suspended - $inactive;

$pending = $approved = $rejected = $expired = 0; $turn = null; $oldest = [];
if ($tables) {
    $base = "FROM appointment_hmo h JOIN appointments a ON a.id = h.appointment_id WHERE a.status <> 'Cancelled'";
    $pending  = (int)$one("SELECT COUNT(*) $base AND h.coverage_status IN ('Pending','In Verification','Action Required')");
    $approved = (int)$one("SELECT COUNT(*) $base AND h.coverage_status = 'Approved'");
    $rejected = (int)$one("SELECT COUNT(*) $base AND h.coverage_status IN ('Not Approved','Not Covered')");
    $expired  = (int)$one("SELECT COUNT(*) $base AND h.coverage_status = 'Approved' AND h.valid_until IS NOT NULL AND h.valid_until < CURDATE()");
    $turn     = $one("SELECT AVG(TIMESTAMPDIFF(MINUTE, h.created_at, h.decided_at)) $base AND h.decided_at IS NOT NULL");
    $rs = $conn->query("SELECT a.id, a.reference_no, h.hmo_provider, h.coverage_status, h.created_at, a.appointment_date
                        $base AND h.coverage_status IN ('Pending','In Verification','Action Required') ORDER BY h.created_at ASC LIMIT 6");
    $oldest = $rs ? $rs->fetch_all(MYSQLI_ASSOC) : [];
}
$susp = [];
$rs = $conn->query("SELECT name, hold_reason, hold_since FROM hmo_providers WHERE on_hold = 1 ORDER BY name");
if ($rs) $susp = $rs->fetch_all(MYSQLI_ASSOC);

hmo_admin_head('HMO Dashboard', 'hmo-dashboard');
?>
<div class="hx-kpis">
  <div class="hx-kpi"><div class="n"><?= $total ?></div><div class="l">Registered HMO providers</div><div class="s"><a href="hmo.php">Manage providers</a></div></div>
  <div class="hx-kpi"><div class="n" style="color:#16a34a"><?= $active ?></div><div class="l">Active providers</div></div>
  <div class="hx-kpi"><div class="n" style="color:#888"><?= $inactive ?></div><div class="l">Inactive providers</div></div>
  <div class="hx-kpi"><div class="n" style="color:var(--red)"><?= $suspended ?></div><div class="l">Suspended providers</div><div class="s">Not accepted for booking</div></div>
  <div class="hx-kpi"><div class="n" style="color:#d97706"><?= $pending ?></div><div class="l">Pending verification cases</div><div class="s">Pending, in verification, action required</div></div>
  <div class="hx-kpi"><div class="n" style="color:#16a34a"><?= $approved ?></div><div class="l">Approved cases</div></div>
  <div class="hx-kpi"><div class="n" style="color:var(--red)"><?= $rejected ?></div><div class="l">Rejected cases</div></div>
  <div class="hx-kpi"><div class="n" style="color:<?= $expired ? 'var(--red)' : 'inherit' ?>"><?= $expired ?></div><div class="l">Expired authorizations</div><div class="s">Approved, validity date passed</div></div>
  <div class="hx-kpi"><div class="n"><?= h(hmo_admin_fmt_dur($turn)) ?></div><div class="l">Avg verification turnaround</div><div class="s">Booking to approval / rejection</div></div>
</div>

<div class="hx-grid2">
  <div class="hx-card"><h3>Oldest open cases</h3><div class="sub">Waiting the longest for a decision.</div>
    <?php if (!$oldest): ?><div class="hx-muted">No open cases.</div><?php else: ?>
    <table class="hx-mini"><tr><th>Reference</th><th>HMO</th><th>Status</th><th>Waiting</th></tr>
    <?php foreach ($oldest as $r): ?>
      <tr><td><a href="hmo_transactions.php?case=<?= (int)$r['id'] ?>"><?= h($r['reference_no'] ?: '#' . $r['id']) ?></a></td><td><?= h($r['hmo_provider']) ?></td><td><?= hmo_case_badge($r['coverage_status']) ?></td>
      <td><?= h(hmo_admin_fmt_dur((time() - strtotime($r['created_at'])) / 60)) ?></td></tr>
    <?php endforeach; ?></table><?php endif; ?>
  </div>
  <div class="hx-card"><h3>Suspended providers</h3><div class="sub">New bookings with these HMOs are blocked; staff cannot approve their cases.</div>
    <?php if (!$susp): ?><div class="hx-muted">None. All active HMOs are accepted.</div><?php else: ?>
    <table class="hx-mini"><tr><th>HMO</th><th>Reason</th><th>Since</th></tr>
    <?php foreach ($susp as $s): ?><tr><td><?= h($s['name']) ?></td><td><?= h($s['hold_reason']) ?></td><td><?= $s['hold_since'] ? h(date('M j, Y', strtotime($s['hold_since']))) : '—' ?></td></tr><?php endforeach; ?></table><?php endif; ?>
  </div>
</div>
<?php hmo_admin_foot();
