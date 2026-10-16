<?php
// admin/hmo_transactions.php — 5. HMO Transactions & Authorization Records (patient identifiers masked in the list)
require_once 'includes/hmo_admin.php';
require_once __DIR__ . '/../private_telecare/booking/hmo_patient_helpers.php';
hmo_patient_ensure_schema($conn);
$hasHmo = $conn->query("SHOW TABLES LIKE 'appointment_hmo'")->num_rows > 0;

// ---- one case in full (logged) ----
$caseId = (int)($_GET['case'] ?? 0);
if ($hasHmo && $caseId) {
    $st = $conn->prepare("SELECT h.*, a.reference_no, a.appointment_date, a.appointment_time, a.status AS appt_status, a.reason,
            p.full_name AS p_name, p.date_of_birth, p.email AS p_email, d.full_name AS doctor_name, d.clinic_name, hp.license_no
        FROM appointment_hmo h JOIN appointments a ON a.id = h.appointment_id JOIN patients p ON p.id = a.patient_id JOIN doctors d ON d.id = a.doctor_id
        LEFT JOIN hmo_providers hp ON hp.name = h.hmo_provider WHERE h.appointment_id = ?");
    $st->bind_param('i', $caseId); $st->execute();
    $c = $st->get_result()->fetch_assoc();
    if ($c) {
        hmo_log_access($conn, 'admin', $admin_id, 'view_case_details', 'hmo_case', $caseId, $caseId, 'Opened full details of ' . ($c['reference_no'] ?: '#' . $caseId));
        $docs = hmo_docs_for($conn, $caseId);
        $ev = $conn->query("SELECT e.*, s.full_name AS staff_name FROM hmo_case_events e LEFT JOIN staff_accounts s ON s.id = e.staff_id WHERE e.appointment_id = " . $caseId . " ORDER BY e.id DESC")->fetch_all(MYSQLI_ASSOC);
        hmo_admin_head('HMO case ' . ($c['reference_no'] ?: '#' . $caseId), 'hmo-transactions', '<a class="hx-btn ghost" href="hmo_transactions.php">← All transactions</a>');
        ?>
        <div class="hx-card"><h3>Case details <?= hmo_case_badge($c['coverage_status']) ?></h3><div class="sub">You opened full patient details. This view is recorded in the audit log.</div>
          <div class="hx-grid3">
            <?php foreach ([['Booking ref', $c['reference_no']], ['Patient', $c['p_name']], ['Date of birth', $c['date_of_birth']], ['Contact', $c['contact_number']], ['Email', $c['p_email']],
                 ['HMO', $c['hmo_provider'] . ($c['license_no'] ? ' (' . $c['license_no'] . ')' : '')], ['Member ID', $c['hmo_member_id']], ['Principal member', $c['principal_member_name']],
                 ['Doctor / clinic', 'Dr. ' . $c['doctor_name'] . ' · ' . ($c['clinic_name'] ?: 'Main clinic')], ['Appointment', date('M j, Y', strtotime($c['appointment_date'])) . ' ' . date('g:i A', strtotime($c['appointment_time']))],
                 ['LOA entered by patient', $c['loa_number'] ?: '—'], ['Approval / LOA no.', $c['approval_loa_no'] ?: '—'],
                 ['Valid', ($c['valid_from'] ?: '—') . ' to ' . ($c['valid_until'] ?: '—')], ['Verified by', $c['verification_method'] ?: '—'], ['Decision reason', $c['decision_reason'] ?: '—']] as [$k, $v]): ?>
              <div><div class="hx-muted"><?= h($k) ?></div><div style="font-weight:600;font-size:.86rem"><?= h($v) ?></div></div>
            <?php endforeach; ?>
          </div></div>
        <div class="hx-grid2">
          <div class="hx-card"><h3>Documents (<?= count($docs) ?>)</h3><div class="sub">Each open is logged.</div>
            <?php foreach ($docs as $d): ?><div style="margin-bottom:.4rem;font-size:.82rem"><?= h(HMO_DOC_TYPES[$d['doc_type']] ?? 'Document') ?> — <?= h($d['original_name']) ?> · <a href="hmo_file.php?id=<?= (int)$d['id'] ?>" target="_blank" rel="noopener">Open</a></div><?php endforeach; ?>
            <?php if (!$docs) echo '<div class="hx-muted">None.</div>'; ?></div>
          <div class="hx-card"><h3>History</h3>
            <?php foreach ($ev as $e): ?><div style="font-size:.8rem;margin-bottom:.5rem"><b><?= h($e['summary']) ?></b><div class="hx-muted"><?= h($e['staff_name'] ?: ($e['event_type'] === 'Patient' ? 'Patient' : 'System')) ?> · <?= h(date('M j, Y g:i A', strtotime($e['created_at']))) ?></div><?= $e['detail'] ? '<div class="hx-diff">' . h($e['detail']) . '</div>' : '' ?></div><?php endforeach; ?>
            <?php if (!$ev) echo '<div class="hx-muted">Nothing yet.</div>'; ?></div>
        </div>
        <?php hmo_admin_foot(); exit;
    }
}

// ---- list ----
$status = $_GET['status'] ?? ''; $prov = trim((string)($_GET['provider'] ?? '')); $q = trim((string)($_GET['q'] ?? ''));
$from = hmo_admin_date($_GET['from'] ?? ''); $to = hmo_admin_date($_GET['to'] ?? '');
$where = ['1=1']; $types = ''; $args = [];
if (in_array($status, ['Pending', 'In Verification', 'Action Required', 'Approved', 'Not Approved'], true)) { $where[] = 'h.coverage_status = ?'; $types .= 's'; $args[] = $status; }
if ($prov !== '') { $where[] = 'h.hmo_provider = ?'; $types .= 's'; $args[] = $prov; }
if ($from) { $where[] = 'a.appointment_date >= ?'; $types .= 's'; $args[] = $from; }
if ($to)   { $where[] = 'a.appointment_date <= ?'; $types .= 's'; $args[] = $to; }
if ($q !== '') { $where[] = '(a.reference_no LIKE ? OR h.approval_loa_no LIKE ? OR h.hmo_provider LIKE ?)'; $types .= 'sss'; $l = '%' . $q . '%'; array_push($args, $l, $l, $l); }
$rows = [];
if ($hasHmo) {
    $sql = "SELECT a.id, a.reference_no, a.appointment_date, a.appointment_time, a.status AS appt_status, p.full_name AS p_name, d.clinic_name,
        h.hmo_provider, h.hmo_member_id, h.coverage_status, h.loa_number, h.approval_loa_no, h.valid_from, h.valid_until, h.decision_reason, h.staff_instructions, h.decided_by,
        (SELECT c.hmo_reference_no FROM hmo_contact_log c WHERE c.appointment_id = h.appointment_id AND c.hmo_reference_no IS NOT NULL AND c.hmo_reference_no <> '' ORDER BY c.id DESC LIMIT 1) AS hmo_ref,
        COALESCE((SELECT s.full_name FROM staff_accounts s WHERE s.id = h.decided_by),
                 (SELECT s2.full_name FROM hmo_case_events ev JOIN staff_accounts s2 ON s2.id = ev.staff_id WHERE ev.appointment_id = h.appointment_id AND ev.staff_id IS NOT NULL ORDER BY ev.id DESC LIMIT 1)) AS staff_name
        FROM appointment_hmo h JOIN appointments a ON a.id = h.appointment_id JOIN patients p ON p.id = a.patient_id JOIN doctors d ON d.id = a.doctor_id
        WHERE " . implode(' AND ', $where) . " ORDER BY a.appointment_date DESC, a.id DESC LIMIT 500";
    $stm = $conn->prepare($sql); if ($types !== '') $stm->bind_param($types, ...$args); $stm->execute();
    $rows = $stm->get_result()->fetch_all(MYSQLI_ASSOC);
}
$reasonOf = function ($r) {
    if (in_array($r['coverage_status'], ['Not Approved', 'Not Covered'], true)) return $r['decision_reason'] ?: '';
    if ($r['coverage_status'] === 'Action Required') return $r['staff_instructions'] ?: 'Waiting for the patient';
    if (in_array($r['coverage_status'], ['Pending', 'In Verification'], true)) return trim((string)$r['loa_number']) !== '' ? 'LOA waiting to be accepted' : 'Waiting for HMO approval';
    return '';
};
$validity = fn($r) => ($r['valid_from'] || $r['valid_until']) ? (($r['valid_from'] ?: '…') . ' to ' . ($r['valid_until'] ?: '…')) : '—';

if (isset($_GET['export'])) {
    hmo_log_access($conn, 'admin', $admin_id, 'export', 'hmo_transactions', null, null, 'CSV export (masked), ' . count($rows) . ' rows');
    hmo_admin_csv('hmo_transactions_' . date('Ymd_His') . '.csv',
        ['Booking ref', 'HMO ref', 'Patient (masked)', 'HMO', 'Member ID (masked)', 'Appointment date', 'Clinic', 'Authorization/LOA no.', 'Validity', 'Status', 'Processed by', 'Reason / pending'],
        array_map(fn($r) => [$r['reference_no'], $r['hmo_ref'], hmo_mask_name($r['p_name']), $r['hmo_provider'], hmo_mask_member($r['hmo_member_id']), $r['appointment_date'],
            $r['clinic_name'] ?: 'Main clinic', $r['approval_loa_no'] ?: $r['loa_number'], $validity($r), $r['coverage_status'], $r['staff_name'], $reasonOf($r)], $rows));
}
$provNames = array_column($conn->query("SELECT DISTINCT hmo_provider FROM appointment_hmo ORDER BY hmo_provider")->fetch_all(MYSQLI_ASSOC) ?: [], 'hmo_provider');
$qs = http_build_query(array_filter(['status' => $status, 'provider' => $prov, 'q' => $q, 'from' => $from, 'to' => $to]));

hmo_admin_head('HMO Transactions & Authorization Records', 'hmo-transactions', '<a class="hx-btn ghost" href="?' . h($qs) . ($qs ? '&' : '') . 'export=1">Export CSV (masked)</a>');
?>
<form class="hx-filter" method="GET">
  <div><label class="hx-lbl">Status</label><select class="hx-in" name="status"><option value="">All</option><?php foreach (['Pending', 'In Verification', 'Action Required', 'Approved', 'Not Approved'] as $o): ?><option <?= $status === $o ? 'selected' : '' ?>><?= h($o) ?></option><?php endforeach; ?></select></div>
  <div><label class="hx-lbl">HMO</label><select class="hx-in" name="provider"><option value="">All</option><?php foreach ($provNames as $o): ?><option <?= $prov === $o ? 'selected' : '' ?>><?= h($o) ?></option><?php endforeach; ?></select></div>
  <div><label class="hx-lbl">Appointment from</label><input class="hx-in" type="date" name="from" value="<?= h($from) ?>"/></div>
  <div><label class="hx-lbl">to</label><input class="hx-in" type="date" name="to" value="<?= h($to) ?>"/></div>
  <div><label class="hx-lbl">Search ref / LOA</label><input class="hx-in" name="q" value="<?= h($q) ?>"/></div>
  <div><button class="hx-btn" type="submit">Filter</button></div>
</form>
<div class="table-wrap"><table>
  <thead><tr><th>Booking / HMO ref</th><th>Patient</th><th>HMO</th><th>Consultation</th><th>LOA / authorization</th><th>Status</th><th>Processed by</th><th>Reason / pending</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9" class="empty-row">No HMO transactions match.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?><tr>
    <td><b><?= h($r['reference_no'] ?: '#' . $r['id']) ?></b><div class="hx-muted"><?= h($r['hmo_ref'] ?: '—') ?></div></td>
    <td><?= h(hmo_mask_name($r['p_name'])) ?><div class="hx-muted"><?= h(hmo_mask_member($r['hmo_member_id'])) ?></div></td>
    <td><?= h($r['hmo_provider']) ?></td>
    <td><?= h(date('M j, Y', strtotime($r['appointment_date']))) ?><div class="hx-muted"><?= h($r['clinic_name'] ?: 'Main clinic') ?></div></td>
    <td><?= h($r['approval_loa_no'] ?: ($r['loa_number'] ?: '—')) ?><div class="hx-muted"><?= h($validity($r)) ?><?= ($r['coverage_status'] === 'Approved' && $r['valid_until'] && $r['valid_until'] < date('Y-m-d')) ? ' · <span style="color:var(--red)">expired</span>' : '' ?></div></td>
    <td><?= hmo_case_badge($r['coverage_status']) ?><?= $r['appt_status'] === 'Cancelled' ? '<div class="hx-muted">Appointment cancelled</div>' : '' ?></td>
    <td><?= h($r['staff_name'] ?: '—') ?></td><td><?= h($reasonOf($r)) ?></td>
    <td><a class="btn-sm btn-edit" href="?case=<?= (int)$r['id'] ?>" title="Opens full patient details (logged)">Details</a></td></tr><?php endforeach; ?>
  </tbody></table></div>
<div class="hx-muted" style="margin-top:.6rem">Patient names and member IDs are masked here. “Details” shows them in full and is recorded in the audit log. Showing up to 500 rows.</div>
<?php hmo_admin_foot();
