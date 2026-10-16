<?php
// admin/hmo_reports.php — 6. Reports & Analytics
require_once 'includes/hmo_admin.php';
require_once __DIR__ . '/../private_telecare/booking/hmo_patient_helpers.php';
hmo_patient_ensure_schema($conn);

$from = hmo_admin_date($_GET['from'] ?? '') ?: date('Y-m-01');
$to   = hmo_admin_date($_GET['to'] ?? '') ?: date('Y-m-d');
$ok = $conn->query("SHOW TABLES LIKE 'appointment_hmo'")->num_rows > 0;
$q = function (string $sql, string $types = '', array $args = []) use ($conn, $ok): array {
    if (!$ok) return [];
    try { $st = $conn->prepare($sql); if ($types !== '') $st->bind_param($types, ...$args); $st->execute(); return $st->get_result()->fetch_all(MYSQLI_ASSOC); }
    catch (Throwable $e) { error_log('hmo_reports: ' . $e->getMessage()); return []; }
};
// Cases whose APPOINTMENT date is in the range; cancelled appointments left out.
$J = "FROM appointment_hmo h JOIN appointments a ON a.id = h.appointment_id JOIN doctors d ON d.id = a.doctor_id WHERE a.status <> 'Cancelled' AND a.appointment_date BETWEEN ? AND ?";
$rng = ['ss', [$from, $to]];

$byProv = $q("SELECT h.hmo_provider AS k, COUNT(*) total, SUM(h.coverage_status='Approved') appr, SUM(h.coverage_status IN ('Not Approved','Not Covered')) rej,
              SUM(h.coverage_status IN ('Pending','In Verification','Action Required')) pend $J GROUP BY h.hmo_provider ORDER BY total DESC", ...$rng);
$byClinic = $q("SELECT COALESCE(NULLIF(d.clinic_name,''),'Main clinic') AS k, COUNT(*) total, SUM(h.coverage_status='Approved') appr, SUM(h.coverage_status IN ('Not Approved','Not Covered')) rej,
              SUM(h.coverage_status IN ('Pending','In Verification','Action Required')) pend $J GROUP BY k ORDER BY total DESC", ...$rng);
$aging = $q("SELECT a.id, a.reference_no, h.hmo_provider, h.coverage_status, TIMESTAMPDIFF(HOUR, h.created_at, NOW()) AS hrs $J AND h.coverage_status IN ('Pending','In Verification','Action Required') ORDER BY hrs DESC", ...$rng);
$bucket = ['< 24 hours' => 0, '1 to 3 days' => 0, '3 to 7 days' => 0, '> 7 days' => 0];
foreach ($aging as $r) { $x = (int)$r['hrs']; $bucket[$x < 24 ? '< 24 hours' : ($x < 72 ? '1 to 3 days' : ($x < 168 ? '3 to 7 days' : '> 7 days'))]++; }
$expired = $q("SELECT a.id, a.reference_no, h.hmo_provider, h.approval_loa_no, h.valid_until $J AND h.coverage_status='Approved' AND h.valid_until IS NOT NULL AND h.valid_until < CURDATE() ORDER BY h.valid_until", ...$rng);

// Missing-document trends: required documents (provider rules) that were not uploaded, for cases still open or decided
$missing = []; $casesChecked = 0;
foreach ($q("SELECT h.appointment_id, h.hmo_provider, (SELECT GROUP_CONCAT(DISTINCT x.doc_type) FROM appointment_hmo_docs x WHERE x.appointment_id = h.appointment_id) AS have $J", ...$rng) as $r) {
    $rl = hmo_rules_for($conn, $r['hmo_provider']); $have = hmo_csv_list($r['have']); $casesChecked++;
    foreach ($rl['required_docs'] as $k) if (!in_array($k, $have, true)) $missing[HMO_RULE_DOCS[$k] ?? $k] = ($missing[HMO_RULE_DOCS[$k] ?? $k] ?? 0) + 1;
}
arsort($missing);
$actionReq = $q("SELECT h.hmo_provider k, COUNT(*) n FROM hmo_case_events e JOIN appointment_hmo h ON h.appointment_id = e.appointment_id JOIN appointments a ON a.id = h.appointment_id JOIN doctors d ON d.id = a.doctor_id
                 WHERE e.status_to = 'Action Required' AND a.appointment_date BETWEEN ? AND ? GROUP BY h.hmo_provider ORDER BY n DESC", ...$rng);

$staffRows = $q("SELECT COALESCE(s.full_name, CONCAT('Staff #', e.staff_id)) AS k, COUNT(*) actions,
                   SUM(e.event_type = 'Authorization') decisions, SUM(e.event_type = 'Contact') contacts
                 FROM hmo_case_events e JOIN appointments a ON a.id = e.appointment_id LEFT JOIN staff_accounts s ON s.id = e.staff_id
                 WHERE e.staff_id IS NOT NULL AND a.appointment_date BETWEEN ? AND ? GROUP BY e.staff_id ORDER BY actions DESC", ...$rng);
$staffTurn = $q("SELECT s.full_name k, COUNT(*) n, AVG(TIMESTAMPDIFF(MINUTE, h.created_at, h.decided_at)) mins
                 FROM appointment_hmo h JOIN appointments a ON a.id = h.appointment_id JOIN staff_accounts s ON s.id = h.decided_by
                 WHERE a.status <> 'Cancelled' AND a.appointment_date BETWEEN ? AND ? AND h.decided_at IS NOT NULL GROUP BY h.decided_by", ...$rng);
$turnMap = []; foreach ($staffTurn as $t) $turnMap[$t['k']] = $t;

$money = $q("SELECT h.hmo_provider k, COUNT(*) n, SUM(h.hmo_coverage_amount) cov, SUM(h.patient_share) share, COUNT(h.hmo_coverage_amount) with_data
             $J AND h.coverage_status='Approved' GROUP BY h.hmo_provider ORDER BY n DESC", ...$rng);

$sec = $_GET['export'] ?? '';
if ($sec) {
    $tables = [
        'provider' => [['HMO', 'Bookings', 'Approved', 'Rejected', 'Pending', 'Approval rate %', 'Rejection rate %'], array_map(fn($r) => [$r['k'], $r['total'], $r['appr'], $r['rej'], $r['pend'], $r['appr'] + $r['rej'] ? round(100 * $r['appr'] / ($r['appr'] + $r['rej'])) : '', $r['appr'] + $r['rej'] ? round(100 * $r['rej'] / ($r['appr'] + $r['rej'])) : ''], $byProv)],
        'clinic'   => [['Clinic', 'Bookings', 'Approved', 'Rejected', 'Pending'], array_map(fn($r) => [$r['k'], $r['total'], $r['appr'], $r['rej'], $r['pend']], $byClinic)],
        'aging'    => [['Booking ref', 'HMO', 'Status', 'Hours open'], array_map(fn($r) => [$r['reference_no'], $r['hmo_provider'], $r['coverage_status'], $r['hrs']], $aging)],
        'expired'  => [['Booking ref', 'HMO', 'Authorization no.', 'Valid until'], array_map(fn($r) => [$r['reference_no'], $r['hmo_provider'], $r['approval_loa_no'], $r['valid_until']], $expired)],
        'staff'    => [['Staff', 'Actions', 'Decisions', 'HMO contacts', 'Decided cases', 'Avg turnaround (min)'], array_map(fn($r) => [$r['k'], $r['actions'], $r['decisions'], $r['contacts'], $turnMap[$r['k']]['n'] ?? 0, isset($turnMap[$r['k']]) ? round($turnMap[$r['k']]['mins']) : ''], $staffRows)],
        'benefit'  => [['HMO', 'Approved cases', 'HMO covers (sum)', 'Patient share (sum)'], array_map(fn($r) => [$r['k'], $r['n'], $r['cov'], $r['share']], $money)],
    ];
    if (isset($tables[$sec])) { hmo_log_access($conn, 'admin', $admin_id, 'export', 'hmo_report_' . $sec, null, null, "CSV export $from to $to"); hmo_admin_csv("hmo_{$sec}_{$from}_{$to}.csv", $tables[$sec][0], $tables[$sec][1]); }
}
$ex = fn($k) => '<a class="btn-sm btn-edit" href="?from=' . h($from) . '&to=' . h($to) . '&export=' . $k . '">Export CSV</a>';
$rate = fn($a, $b) => ($a + $b) ? round(100 * $a / ($a + $b)) . '%' : '—';
$maxP = max(1, ...array_map(fn($r) => (int)$r['total'], $byProv ?: [['total' => 1]]));

hmo_admin_head('Reports & Analytics', 'hmo-reports');
?>
<form class="hx-filter" method="GET">
  <div><label class="hx-lbl">Appointments from</label><input class="hx-in" type="date" name="from" value="<?= h($from) ?>"/></div>
  <div><label class="hx-lbl">to</label><input class="hx-in" type="date" name="to" value="<?= h($to) ?>"/></div>
  <div><button class="hx-btn" type="submit">Apply</button></div>
</form>
<div class="hx-card"><h3>HMO bookings by provider <span style="float:right"><?= $ex('provider') ?></span></h3><div class="sub">Approval rate = approved ÷ (approved + rejected). Cancelled appointments are left out.</div>
  <?php if (!$byProv): ?><div class="hx-muted">No HMO bookings in this period.</div><?php else: ?><table class="hx-mini"><tr><th>HMO</th><th>Bookings</th><th></th><th>Approved</th><th>Rejected</th><th>Pending</th><th>Approval rate</th><th>Rejection rate</th></tr>
  <?php foreach ($byProv as $r): ?><tr><td><?= h($r['k']) ?></td><td><?= (int)$r['total'] ?></td><td style="width:130px"><div class="hx-bar"><i style="width:<?= round(100 * $r['total'] / $maxP) ?>%"></i></div></td>
    <td><?= (int)$r['appr'] ?></td><td><?= (int)$r['rej'] ?></td><td><?= (int)$r['pend'] ?></td><td><?= $rate($r['appr'], $r['rej']) ?></td><td><?= $rate($r['rej'], $r['appr']) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>

<div class="hx-card"><h3>HMO bookings by clinic <span style="float:right"><?= $ex('clinic') ?></span></h3><div class="sub">Clinic = the doctor's clinic name.</div>
  <?php if (!$byClinic): ?><div class="hx-muted">No data.</div><?php else: ?><table class="hx-mini"><tr><th>Clinic</th><th>Bookings</th><th>Approved</th><th>Rejected</th><th>Pending</th></tr>
  <?php foreach ($byClinic as $r): ?><tr><td><?= h($r['k']) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['appr'] ?></td><td><?= (int)$r['rej'] ?></td><td><?= (int)$r['pend'] ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>

<div class="hx-grid2">
  <div class="hx-card"><h3>Pending cases & aging <span style="float:right"><?= $ex('aging') ?></span></h3><div class="sub">How long open cases have been waiting.</div>
    <table class="hx-mini"><?php foreach ($bucket as $l => $n): ?><tr><td><?= h($l) ?></td><td><b><?= $n ?></b></td></tr><?php endforeach; ?></table></div>
  <div class="hx-card"><h3>Expired authorizations <span style="float:right"><?= $ex('expired') ?></span></h3><div class="sub">Approved, but the validity date has passed.</div>
    <?php if (!$expired): ?><div class="hx-muted">None.</div><?php else: ?><table class="hx-mini"><tr><th>Ref</th><th>HMO</th><th>Auth no.</th><th>Expired</th></tr>
    <?php foreach (array_slice($expired, 0, 10) as $r): ?><tr><td><a href="hmo_transactions.php?case=<?= (int)$r['id'] ?>"><?= h($r['reference_no']) ?></a></td><td><?= h($r['hmo_provider']) ?></td><td><?= h($r['approval_loa_no']) ?></td><td><?= h($r['valid_until']) ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</div>

<div class="hx-grid2">
  <div class="hx-card"><h3>Missing-document trends</h3><div class="sub">Documents each HMO requires (Rules) that were never uploaded, across <?= (int)$casesChecked ?> case(s).</div>
    <?php if (!$missing): ?><div class="hx-muted">No missing required documents.</div><?php else: ?><table class="hx-mini"><?php foreach ($missing as $l => $n): ?><tr><td><?= h($l) ?></td><td><b><?= (int)$n ?></b> case(s)</td></tr><?php endforeach; ?></table><?php endif; ?>
    <?php if ($actionReq): ?><div style="margin-top:.8rem;font-weight:700;font-size:.8rem">Times staff asked the patient to act, by HMO</div><table class="hx-mini"><?php foreach ($actionReq as $r): ?><tr><td><?= h($r['k']) ?></td><td><?= (int)$r['n'] ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
  <div class="hx-card"><h3>Confirmed co-pay & benefit summary <span style="float:right"><?= $ex('benefit') ?></span></h3><div class="sub">Only from approved cases where staff entered amounts.</div>
    <?php if (!$money): ?><div class="hx-muted">No approved cases.</div><?php else: ?><table class="hx-mini"><tr><th>HMO</th><th>Approved</th><th>HMO covers</th><th>Patient share</th></tr>
    <?php foreach ($money as $r): ?><tr><td><?= h($r['k']) ?></td><td><?= (int)$r['n'] ?></td><td><?= (int)$r['with_data'] ? '₱' . number_format((float)$r['cov'], 2) : '<span class="hx-muted">no data</span>' ?></td><td><?= (int)$r['with_data'] ? '₱' . number_format((float)$r['share'], 2) : '<span class="hx-muted">no data</span>' ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
</div>

<div class="hx-card"><h3>Staff workload & turnaround <span style="float:right"><?= $ex('staff') ?></span></h3><div class="sub">Turnaround = booking to decision, for cases the person decided.</div>
  <?php if (!$staffRows): ?><div class="hx-muted">No staff activity in this period.</div><?php else: ?><table class="hx-mini"><tr><th>Staff</th><th>Actions</th><th>Decisions</th><th>HMO contacts</th><th>Decided cases</th><th>Avg turnaround</th></tr>
  <?php foreach ($staffRows as $r): ?><tr><td><?= h($r['k']) ?></td><td><?= (int)$r['actions'] ?></td><td><?= (int)$r['decisions'] ?></td><td><?= (int)$r['contacts'] ?></td><td><?= (int)($turnMap[$r['k']]['n'] ?? 0) ?></td><td><?= isset($turnMap[$r['k']]) ? h(hmo_admin_fmt_dur($turnMap[$r['k']]['mins'])) : '—' ?></td></tr><?php endforeach; ?></table><?php endif; ?></div>
<?php hmo_admin_foot();
