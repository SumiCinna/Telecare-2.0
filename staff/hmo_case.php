<?php
// staff/hmo_case.php — one HMO case: verify, log contact with the HMO, record the result, see the history
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/hmo_helpers.php';

date_default_timezone_set('Asia/Manila');
$appt_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$appt_id || !hmo_ensure_schema($conn)) {
    $_SESSION['toast_error'] = 'HMO case not available.';
    header('Location: hmo_queue.php'); exit;
}

function hmo_load_case(mysqli $conn, int $appt_id): ?array {
    // license_no exists only after the HMO list update; fall back gracefully.
    $hasLic = false;
    $c = $conn->query("SHOW COLUMNS FROM hmo_providers LIKE 'license_no'");
    if ($c && $c->num_rows > 0) $hasLic = true;
    $lic = $hasLic ? 'hp.license_no AS hmo_license_no,' : "NULL AS hmo_license_no,";
    $st = $conn->prepare("
        SELECT h.*, a.id AS appt_id, a.reference_no, a.appointment_date, a.appointment_time, a.type AS appt_type,
               a.status AS appt_status, a.payment_status, a.patient_id, a.reason,
               p.full_name AS p_name, p.date_of_birth AS p_dob, p.email AS p_email, p.phone_number AS p_phone,
               d.full_name AS doctor_name, d.department AS doctor_dept, d.clinic_name,
               $lic hp.contact_person AS hmo_contact_person, hp.phone AS hmo_phone, hp.email AS hmo_email,
               (SELECT s2.full_name FROM hmo_case_events ev JOIN staff_accounts s2 ON s2.id = ev.staff_id WHERE ev.appointment_id = h.appointment_id AND ev.staff_id IS NOT NULL ORDER BY ev.id DESC LIMIT 1) AS staff_name
        FROM appointment_hmo h
        JOIN appointments a ON a.id = h.appointment_id
        JOIN patients p ON p.id = a.patient_id
        JOIN doctors d ON d.id = a.doctor_id
        LEFT JOIN hmo_providers hp ON hp.name = h.hmo_provider
        WHERE h.appointment_id = ?");
    $st->bind_param('i', $appt_id);
    $st->execute();
    return $st->get_result()->fetch_assoc() ?: null;
}

$case = hmo_load_case($conn, $appt_id);
if (!$case) {
    $_SESSION['toast_error'] = 'That HMO case was not found.';
    header('Location: hmo_queue.php'); exit;
}

$back = 'Location: hmo_case.php?id=' . $appt_id;
$fail = function (string $msg) use ($back) { $_SESSION['toast_error'] = $msg; header($back); exit; };
$ok   = function (string $msg) use ($back) { $_SESSION['toast'] = $msg; header($back); exit; };

// Dates from forms: accept only real Y-m-d.
$ymd = function ($v) {
    $v = trim((string)$v);
    if ($v === '') return null;
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : false;
};
$money = function ($v) {
    $v = trim((string)$v);
    if ($v === '') return null;
    return (is_numeric($v) && (float)$v >= 0 && (float)$v < 10000000) ? round((float)$v, 2) : false;
};
$txt = fn($v, $max) => mb_substr(preg_replace('/\s+/u', ' ', trim((string)$v)), 0, $max);

$isCancelled = $case['appt_status'] === 'Cancelled';
$onHold  = array_key_exists((string)$case['hmo_provider'], booking_hmo_holds($conn));   // clinic paused this HMO (e.g. unpaid bills)
$holdWhy = $onHold ? (booking_hmo_holds($conn)[$case['hmo_provider']] ?? '') : '';
$hasLoa  = trim((string)$case['loa_number']) !== '';
$canVerify = hmo_staff_can($conn, (int)$staff_id, 'can_verify');
$canStatus = hmo_staff_can($conn, (int)$staff_id, 'can_update_status');
$canDocs   = hmo_staff_can($conn, (int)$staff_id, 'can_view_documents');
$rules     = hmo_rules_for($conn, (string)$case['hmo_provider']);          // provider-specific rules from Admin > HMO > Rules
$ruleMethod = ['portal' => 'HMO portal', 'api' => 'Other', 'phone' => 'Phone call to HMO', 'email' => 'Email with HMO'][$rules['verification_method']] ?? '';
$defFrom  = $rules['validity_days'] ? date('Y-m-d') : '';
$defUntil = $rules['validity_days'] ? date('Y-m-d', strtotime('+' . (int)$rules['validity_days'] . ' days')) : '';
$agents = [];
try { $aq = $conn->prepare("SELECT c.* FROM hmo_provider_contacts c JOIN hmo_providers p ON p.id = c.provider_id WHERE p.name = ? ORDER BY c.is_primary DESC, c.agent_name");
      $aq->bind_param('s', $case['hmo_provider']); $aq->execute(); $agents = $aq->get_result()->fetch_all(MYSQLI_ASSOC); } catch (Throwable $ex) { }
$apptWhen = date('M j, Y', strtotime($case['appointment_date'])) . ' at ' . date('g:i A', strtotime($case['appointment_time']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hmo_csrf_ok()) $fail('Your session expired. Please try again.');
    $action = (string)($_POST['action'] ?? '');
    $cur    = (string)$case['coverage_status'];
    $sid    = (int)$staff_id;

    if ($isCancelled && $action !== 'note') $fail('This appointment was cancelled, so the HMO case is closed.');
    $needPerm = ['start' => 'can_verify', 'contact' => 'can_verify', 'instruct' => 'can_update_status', 'approve' => 'can_update_status',
                 'accept_loa' => 'can_update_status', 'reject' => 'can_update_status'];
    if (isset($needPerm[$action]) && !hmo_staff_can($conn, $sid, $needPerm[$action]))
        $fail('You do not have permission for this action (' . HMO_PERMS[$needPerm[$action]] . '). Ask an admin.');
    if ($onHold && in_array($action, ['approve', 'accept_loa'], true))
        $fail($case['hmo_provider'] . ' is on hold, so its HMO payment is not accepted. Use "Not approved" or "Ask patient to act" instead.');

    switch ($action) {

    case 'start':
        if ($cur !== 'Pending') $fail('This case has already been started.');
        $st = $conn->prepare("UPDATE appointment_hmo SET coverage_status='In Verification', status_updated_at=NOW() WHERE appointment_id = ?");
        $st->bind_param('i', $appt_id); $st->execute();
        hmo_add_event($conn, $appt_id, $sid, 'Status', 'Verification started.', null, $cur, 'In Verification');
        $ok('Verification started.');

    case 'contact': {
        $channel = (string)($_POST['channel'] ?? '');
        $detail  = $txt($_POST['contact_detail'] ?? '', 150);
        $ref     = $txt($_POST['hmo_reference_no'] ?? '', 80);
        $resp    = trim((string)($_POST['response'] ?? ''));
        $resp    = mb_substr($resp, 0, 2000);
        $follow  = $ymd($_POST['follow_up_date'] ?? '');
        $when    = DateTime::createFromFormat('Y-m-d\TH:i', (string)($_POST['contacted_at'] ?? ''));
        if (!in_array($channel, HMO_CONTACT_CHANNELS, true)) $fail('Choose how you contacted the HMO.');
        if (!$when)            $fail('Enter the date and time of contact.');
        if ($resp === '')      $fail('Write the response, or the reason for the delay.');
        if ($follow === false) $fail('The follow-up date is not valid.');
        $whenSql = $when->format('Y-m-d H:i:s');
        $refN = $ref !== '' ? $ref : null; $detN = $detail !== '' ? $detail : null;
        $st = $conn->prepare("INSERT INTO hmo_contact_log (appointment_id, staff_id, channel, contact_detail, contacted_at, hmo_reference_no, response, follow_up_date)
                              VALUES (?,?,?,?,?,?,?,?)");
        $st->bind_param('iissssss', $appt_id, $sid, $channel, $detN, $whenSql, $refN, $resp, $follow);
        $st->execute();
        if ($cur === 'Pending') {
            $st = $conn->prepare("UPDATE appointment_hmo SET coverage_status='In Verification', status_updated_at=NOW() WHERE appointment_id = ?");
            $st->bind_param('i', $appt_id); $st->execute();
        }
        hmo_add_event($conn, $appt_id, $sid, 'Contact', 'Contacted HMO by ' . strtolower($channel) . '.',
            $resp . ($refN ? "\nHMO reference: $refN" : '') . ($follow ? "\nFollow-up: $follow" : ''),
            $cur === 'Pending' ? 'Pending' : null, $cur === 'Pending' ? 'In Verification' : null);
        $ok('Contact saved.');
    }

    case 'instruct': {
        $ins = trim((string)($_POST['staff_instructions'] ?? ''));
        $ins = mb_substr($ins, 0, 1000);
        if ($ins === '') $fail('Write what the patient needs to do.');
        $st = $conn->prepare("UPDATE appointment_hmo SET coverage_status='Action Required', staff_instructions=?, status_updated_at=NOW() WHERE appointment_id = ?");
        $st->bind_param('si', $ins, $appt_id); $st->execute();
        hmo_add_event($conn, $appt_id, $sid, 'Status', 'Asked the patient to take action.', $ins, $cur, 'Action Required');
        hmo_notify_patient($conn, (int)$case['patient_id'], $appt_id, 'hmo_action_required', 'Action needed for your HMO booking',
            "We need something from you for your appointment on $apptWhen: " . $ins);
        $ok('Patient notified.');
    }

    case 'accept_loa':
        // The patient already has an LOA: the HMO approved it already, so the clinic only accepts it. No request to the HMO.
        if (!$hasLoa) $fail('The patient did not give an LOA number.');
        $_POST['verification_method'] = 'LOA/eLOA presented by patient';
        $_POST['approval_loa_no']     = $case['loa_number'];
        if (trim((string)($_POST['approved_service'] ?? '')) === '') $_POST['approved_service'] = (string)$case['service_type'];
        // no break: recorded exactly like an approval

    case 'approve': {
        $method  = (string)($_POST['verification_method'] ?? '');
        $loa     = $txt($_POST['approval_loa_no'] ?? '', 60);
        $from    = $ymd($_POST['valid_from'] ?? '');
        $until   = $ymd($_POST['valid_until'] ?? '');
        $service = $txt($_POST['approved_service'] ?? '', 150);
        $benefit = $txt($_POST['benefit_note'] ?? '', 255);
        $cover   = $money($_POST['hmo_coverage_amount'] ?? '');
        $copay   = $money($_POST['patient_share'] ?? '');
        if (!in_array($method, HMO_VERIFY_METHODS, true)) $fail('Choose how the coverage was verified.');
        if ($from === false || $until === false)          $fail('A date is not valid.');
        if ($from && $until && $until < $from)            $fail('The validity end date is before the start date.');
        if ($cover === false || $copay === false)         $fail('Amounts must be zero or more.');
        $loaN = $loa !== '' ? $loa : null; $svcN = $service !== '' ? $service : null; $benN = $benefit !== '' ? $benefit : null;
        $st = $conn->prepare("UPDATE appointment_hmo SET coverage_status='Approved', approval_loa_no=?, valid_from=?, valid_until=?,
              approved_service=?, benefit_note=?, hmo_coverage_amount=?, patient_share=?, verification_method=?,
              decision_reason=NULL, staff_instructions=NULL, decided_by=?, decided_at=NOW(), status_updated_at=NOW(),
              provider_confirmed=1, provider_confirmed_at=NOW()
              WHERE appointment_id = ?");
        $st->bind_param('sssssddsii', $loaN, $from, $until, $svcN, $benN, $cover, $copay, $method, $sid, $appt_id);
        $st->execute();
        hmo_add_event($conn, $appt_id, $sid, 'Authorization', 'HMO approved.',
            'Verified by: ' . $method . ($loaN ? "\nLOA / authorization no.: $loaN" : '') . ($until ? "\nValid until: $until" : '')
            . ($svcN ? "\nService: $svcN" : '') . ($benN ? "\nBenefit: $benN" : '') . ($copay !== null ? "\nPatient share: ₱" . number_format($copay, 2) : ''),
            $cur, 'Approved');
        $viaLoa = ($method === 'LOA/eLOA presented by patient');
        $msg = $viaLoa ? "The clinic accepted your LOA ({$case['hmo_provider']}). Your appointment on $apptWhen is confirmed."
                       : "Your HMO ({$case['hmo_provider']}) coverage was approved. Your appointment on $apptWhen is confirmed.";
        if ($copay !== null && $copay > 0) $msg .= ' Your share (co-pay) is ₱' . number_format($copay, 2) . '.';
        hmo_notify_patient($conn, (int)$case['patient_id'], $appt_id, 'hmo_approved', $viaLoa ? 'LOA accepted — appointment confirmed' : 'HMO approved — appointment confirmed', $msg);
        $ok($viaLoa ? 'LOA accepted. The patient was notified.' : 'Marked as approved. The patient was notified.');
    }

    case 'reject': {
        $method = (string)($_POST['verification_method'] ?? '');
        $reason = $txt($_POST['decision_reason'] ?? '', 255);
        if (!in_array($method, HMO_VERIFY_METHODS, true)) $fail('Choose how the coverage was verified.');
        if ($reason === '') $fail('Write the reason it was not approved.');
        $st = $conn->prepare("UPDATE appointment_hmo SET coverage_status='Not Approved', decision_reason=?, verification_method=?,
              decided_by=?, decided_at=NOW(), status_updated_at=NOW()
              WHERE appointment_id = ?");
        $st->bind_param('ssii', $reason, $method, $sid, $appt_id);
        $st->execute();
        hmo_add_event($conn, $appt_id, $sid, 'Authorization', 'HMO not approved.', 'Verified by: ' . $method . "\nReason: " . $reason, $cur, 'Not Approved');
        hmo_notify_patient($conn, (int)$case['patient_id'], $appt_id, 'hmo_not_approved', 'HMO coverage not approved',
            "Your HMO ({$case['hmo_provider']}) coverage was not approved for your appointment on $apptWhen. Reason: $reason. "
            . "Please choose another payment method or cancel the appointment.");
        $ok('Marked as not approved. The patient was notified.');
    }

    case 'note': {
        $note = trim((string)($_POST['note'] ?? ''));
        $note = mb_substr($note, 0, 1000);
        if ($note === '') $fail('Write a note first.');
        hmo_add_event($conn, $appt_id, $sid, 'Note', 'Staff note.', $note);
        $ok('Note added.');
    }

    default:
        $fail('Unknown action.');
    }
}

$toast       = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);
$active_page = 'HMO';
$csrf = hmo_csrf();
$e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);

$contacts = $conn->query("SELECT c.*, s.full_name AS staff_name FROM hmo_contact_log c LEFT JOIN staff_accounts s ON s.id = c.staff_id
                          WHERE c.appointment_id = " . (int)$appt_id . " ORDER BY c.contacted_at DESC, c.id DESC")->fetch_all(MYSQLI_ASSOC);
$events = $conn->query("SELECT ev.*, s.full_name AS staff_name FROM hmo_case_events ev LEFT JOIN staff_accounts s ON s.id = ev.staff_id
                        WHERE ev.appointment_id = " . (int)$appt_id . " ORDER BY ev.id DESC")->fetch_all(MYSQLI_ASSOC);
$nextFollow = null;
foreach ($contacts as $c) if ($c['follow_up_date'] && (!$nextFollow || $c['follow_up_date'] > $nextFollow)) $nextFollow = $c['follow_up_date'];

$docs  = hmo_docs_for($conn, $appt_id);
$cur   = (string)$case['coverage_status'];
$final = in_array($cur, ['Approved', 'Not Approved'], true);
require_once 'includes/header.php';
?>
<style>
.hc-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.hc-top h2{font-size:20px;font-weight:700}
.hc-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:18px;align-items:start}
.hc-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);padding:18px;margin-bottom:18px}
.hc-card h3{font-size:14px;font-weight:800;margin-bottom:12px;color:var(--neutral-900)}
.hc-card h4{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--secondary-dark);margin:14px 0 8px}
.hc-kv{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px}
.hc-kv .k{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}
.hc-kv .v{font-size:13px;font-weight:600;color:var(--neutral-900);word-break:break-word}
.hc-full{grid-column:1/-1}
.hc-flash{padding:12px 14px;border-radius:var(--radius-md);font-size:12.5px;margin-bottom:16px;line-height:1.5}
.hc-flash.ok{background:#ECFDF3;color:#067647;border:1px solid #ABEFC6}
.hc-flash.no{background:var(--primary-soft);color:var(--primary-dark);border:1px solid #F3B7BC}
.hc-flash.wait{background:#FFF8E8;color:#8a5a00;border:1px solid #F5D9A0}
.hc-actions{display:flex;gap:8px;flex-wrap:wrap}
.hc-row2{display:grid;grid-template-columns:1fr 1fr;gap:10px 12px}
.hc-form .f-input{margin-bottom:10px}
.hc-form textarea.f-input{min-height:70px;resize:vertical}
.hc-btn-green{background:#067647;color:#fff}.hc-btn-green:hover{background:#05603a}
.hc-btn-red{background:var(--primary)}
.hc-btn-orange{background:#B54708;color:#fff}
.hc-tabs{display:flex;gap:6px;margin-bottom:12px;flex-wrap:wrap}
.hc-tabbtn{padding:7px 12px;border:1px solid var(--border);border-radius:50px;background:#fff;font-size:12px;font-weight:700;cursor:pointer;color:var(--neutral-700)}
.hc-tabbtn.active{background:var(--primary);border-color:var(--primary);color:#fff}
.hc-pane{display:none}.hc-pane.active{display:block}
.hc-contact{border:1px solid var(--neutral-200);border-radius:var(--radius-md);padding:10px 12px;margin-bottom:8px;font-size:12px}
.hc-contact b{color:var(--neutral-900)}
.hc-tl{list-style:none;position:relative;padding-left:18px}
.hc-tl:before{content:'';position:absolute;left:5px;top:6px;bottom:6px;width:2px;background:var(--neutral-200)}
.hc-tl li{position:relative;padding:0 0 14px 10px}
.hc-tl li:before{content:'';position:absolute;left:-17px;top:4px;width:10px;height:10px;border-radius:50%;background:var(--secondary);border:2px solid #fff;box-shadow:0 0 0 1px var(--neutral-300)}
.hc-tl .t{font-size:12.5px;font-weight:700;color:var(--neutral-900)}
.hc-tl .m{font-size:11px;color:var(--muted);margin-top:1px}
.hc-tl .d{font-size:12px;color:var(--neutral-700);margin-top:4px;white-space:pre-line;background:var(--neutral-50);border-radius:8px;padding:7px 9px}
.hc-hint{font-size:11px;color:var(--muted);margin:-4px 0 10px}
@media(max-width:1000px){.hc-grid{grid-template-columns:1fr}}
@media(max-width:560px){.hc-kv,.hc-row2{grid-template-columns:1fr}}
</style>

<div class="hc-top">
  <div>
    <a href="hmo_queue.php" style="font-size:12px;color:var(--muted);text-decoration:none">&larr; Back to queue</a>
    <h2>HMO case <?= $e($case['reference_no'] ?: '#' . $appt_id) ?></h2>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <span class="badge <?= hmo_status_class($cur) ?>" style="font-size:12px;padding:6px 12px"><?= $e($cur) ?></span>
    <span style="font-size:12px;color:var(--muted)">Last handled by: <b style="color:var(--neutral-900)"><?= $case['staff_name'] ? $e($case['staff_name']) : 'Nobody yet' ?></b></span>
  </div>
</div>

<?php if ($onHold && !$isCancelled && $cur !== 'Approved'): ?>
  <div class="hc-flash no"><b><?= $e($case['hmo_provider']) ?> is ON HOLD.</b> The clinic is not accepting this HMO<?= $holdWhy ? ' (' . $e($holdWhy) . ')' : '' ?>, so this case cannot be approved<?= $hasLoa ? ', even with an LOA' : '' ?>.
    Mark it "Not approved" so the patient can choose another payment method, or ask the patient to act.
    <form method="POST" style="margin-top:8px"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/>
      <input type="hidden" name="action" value="reject"/><input type="hidden" name="verification_method" value="Other"/>
      <input type="hidden" name="decision_reason" value="<?= $e('The clinic is not accepting ' . $case['hmo_provider'] . ' at this time') ?>"/>
      <button class="btn-submit hc-btn-red" style="width:auto;padding:7px 14px" onclick="return confirm('Mark as NOT approved because the HMO is on hold and notify the patient?')">Not approved — HMO on hold</button></form></div>
<?php elseif ($onHold && $cur === 'Approved'): ?>
  <div class="hc-flash wait">Note: <?= $e($case['hmo_provider']) ?> was put on hold after this case was approved. The approval stays, but no new cases are accepted.</div>
<?php endif; ?>
<?php if (!$isCancelled && !$final && !empty($case['assistance_requested'])): ?>
  <div class="hc-flash wait"><b>The patient asked for help getting the LOA / authorization.</b> <?= $e($case['assistance_note'] ?? '') ?> Contact the HMO for them, log it under "Contact HMO", then approve.</div>
<?php endif; ?>
<?php if ($isCancelled): ?>
  <div class="hc-flash no">This appointment was cancelled, so there is nothing left to verify. You can still add notes.</div>
<?php elseif ($cur === 'Approved'): ?>
  <div class="hc-flash ok"><b>Approved</b><?= $case['decided_at'] ? ' on ' . $e(date('M j, Y g:i A', strtotime($case['decided_at']))) : '' ?>.
    <?= $case['approval_loa_no'] ? ' LOA / authorization no. <b>' . $e($case['approval_loa_no']) . '</b>.' : '' ?>
    <?= $case['valid_until'] ? ' Valid until ' . $e(date('M j, Y', strtotime($case['valid_until']))) . '.' : '' ?></div>
<?php elseif ($cur === 'Not Approved'): ?>
  <div class="hc-flash no"><b>Not approved.</b> <?= $e($case['decision_reason']) ?>. The patient was told to choose another payment method or cancel. The appointment stays booked until the patient decides.</div>
<?php elseif ($cur === 'Action Required'): ?>
  <div class="hc-flash wait"><b>Waiting for the patient.</b> <?= $e($case['staff_instructions']) ?></div>
<?php endif; ?>
<?php if ($nextFollow && !$final && !$isCancelled): ?>
  <div class="hc-flash wait">Follow up with the HMO by <b><?= $e(date('M j, Y', strtotime($nextFollow))) ?></b>.</div>
<?php endif; ?>

<div class="hc-grid">
<div>

  <!-- 3. Patient & HMO verification -->
  <div class="hc-card">
    <h3>Patient &amp; HMO verification</h3>
    <h4 style="margin-top:0">Patient</h4>
    <div class="hc-kv">
      <div><div class="k">Name</div><div class="v"><?= $e($case['p_name']) ?></div></div>
      <div><div class="k">Date of birth</div><div class="v"><?= $case['p_dob'] ? $e(date('M j, Y', strtotime($case['p_dob']))) : '—' ?></div></div>
      <div><div class="k">Contact number (as entered)</div><div class="v"><?= $e($case['contact_number']) ?></div></div>
      <div><div class="k">Email</div><div class="v"><?= $e($case['p_email']) ?></div></div>
    </div>
    <h4>HMO membership</h4>
    <div class="hc-kv">
      <div><div class="k">HMO provider</div><div class="v"><?= $e($case['hmo_provider']) ?></div></div>
      <div><div class="k">Insurance Commission license no.</div><div class="v"><?= $case['hmo_license_no'] ? $e($case['hmo_license_no']) : '—' ?></div></div>
      <div><div class="k">Member ID / card no.</div><div class="v"><?= $e($case['hmo_member_id']) ?></div></div>
      <div><div class="k">Member type</div><div class="v"><?= $e($case['member_type']) ?></div></div>
      <div><div class="k">Principal member</div><div class="v"><?= $e($case['principal_member_name']) ?></div></div>
      <div><div class="k">Company / employer</div><div class="v"><?= $case['company_employer'] ? $e($case['company_employer']) : '—' ?></div></div>
      <div><div class="k">Plan / account type</div><div class="v"><?= $case['hmo_plan'] ? $e($case['hmo_plan']) : '—' ?></div></div>
      <div><div class="k">LOA no. entered by patient</div><div class="v"><?= $case['loa_number'] ? $e($case['loa_number']) : 'None' ?></div></div>
    </div>
    <h4>Consultation</h4>
    <div class="hc-kv">
      <div><div class="k">Doctor</div><div class="v">Dr. <?= $e($case['doctor_name']) ?></div></div>
      <div><div class="k">Clinic / department</div><div class="v"><?= $e($case['clinic_name'] ?: $case['doctor_dept']) ?></div></div>
      <div><div class="k">Appointment</div><div class="v"><?= $e($apptWhen) ?></div></div>
      <div><div class="k">Consultation type</div><div class="v"><?= $e($case['appt_type']) ?> &middot; <?= $e($case['service_type']) ?></div></div>
      <div class="hc-full"><div class="k">Patient's concern</div><div class="v"><?= $e($case['reason']) ?></div></div>
    </div>
    <h4>Documents</h4>
    <?php if (!$canDocs): ?>
      <div style="font-size:12px;color:var(--muted)"><?= count($docs) ?> document(s) uploaded. You don't have permission to open patient documents. Ask an admin.</div>
    <?php elseif (!$docs): ?>
      <div style="font-size:12px;color:var(--muted)">The patient has not uploaded any documents<?= $case['patient_submitted_at'] ? '' : ' (and has not submitted the booking yet)' ?>. You can ask for them under "Ask patient to act".</div>
    <?php else: ?>
      <?php foreach ($docs as $d): ?>
        <div class="hc-contact"><b><?= $e(HMO_DOC_TYPES[$d['doc_type']] ?? 'Document') ?></b> &middot; <?= $e($d['original_name']) ?> &middot; <?= $e(number_format($d['size_bytes'] / 1024, 0)) ?> KB
          &middot; <?= $e(date('M j, g:i A', strtotime($d['created_at']))) ?>
          &middot; <a href="hmo_file.php?id=<?= (int)$d['id'] ?>" target="_blank" rel="noopener" style="color:var(--primary);font-weight:700">Open</a></div>
      <?php endforeach; ?>
      <div class="hc-hint">Booking submitted by patient: <?= $case['patient_submitted_at'] ? $e(date('M j, Y g:i A', strtotime($case['patient_submitted_at']))) : 'not yet' ?>.</div>
    <?php endif; ?>
  </div>

  <!-- 4 + 5. Contact + authorization -->
  <div class="hc-card">
    <h3>Work on this case</h3>
    <?php if ($isCancelled): ?>
      <div style="font-size:12px;color:var(--muted)">Closed: the appointment was cancelled.</div>
    <?php else: ?>
    <?php if ($cur === 'Pending' && $canVerify): ?>
      <form method="POST" style="margin-bottom:14px"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="start"/>
        <button class="btn-primary">Start verification</button>
        <span style="font-size:11.5px;color:var(--muted);margin-left:8px">Marks the case "In verification". Any staff member can work on any case; every action is recorded with the staff name.</span></form>
    <?php endif; ?>

    <div class="hc-tabs">
      <?php if ($hasLoa && $canStatus): ?><button type="button" class="hc-tabbtn active" data-pane="p-loa">Accept LOA</button><?php endif; ?>
      <?php if ($canVerify): ?><button type="button" class="hc-tabbtn <?= ($hasLoa && $canStatus) ? '' : 'active' ?>" data-pane="p-contact"><?= $hasLoa ? 'Contact HMO (if LOA is doubtful)' : 'Contact HMO' ?></button><?php endif; ?>
      <?php if ($canStatus): ?>
      <button type="button" class="hc-tabbtn" data-pane="p-approve">Approve</button>
      <button type="button" class="hc-tabbtn" data-pane="p-reject">Not approved</button>
      <button type="button" class="hc-tabbtn" data-pane="p-ask">Ask patient to act</button>
      <?php endif; ?>
      <button type="button" class="hc-tabbtn" data-pane="p-note">Note</button>
    </div>

    <!-- 4. Contact HMO -->
    <?php if (!$canVerify && !$canStatus): ?><div class="hc-hint">Your account can only add notes on HMO cases. Ask an admin for more permissions.</div><?php endif; ?>
    <?php if ($hasLoa && $canStatus): ?>
    <!-- LOA already given: the HMO approved it, the clinic only accepts it -->
    <div class="hc-pane active" id="p-loa">
      <div class="hc-hint">The patient already has an LOA (<b><?= $e($case['loa_number']) ?></b>), so there is nothing to request from the HMO.
        Check the LOA document and the member details, then accept it and go ahead with the consultation. If the LOA looks wrong or expired, use "Not approved".</div>
      <?php if ($onHold): ?>
        <div class="hc-flash no" style="margin:0">Cannot accept: this HMO is on hold.</div>
      <?php else: ?>
      <form method="POST" class="hc-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="accept_loa"/>
        <div class="hc-row2">
          <div><label class="f-label">Valid from (optional)</label><input class="f-input" type="date" name="valid_from" value="<?= $e($defFrom) ?>"/></div>
          <div><label class="f-label">Valid until (optional)</label><input class="f-input" type="date" name="valid_until" value="<?= $e($defUntil) ?>"/></div>
          <div><label class="f-label">Patient's share / co-pay ₱ (optional)</label><input class="f-input" type="number" step="0.01" min="0" name="patient_share"/></div>
          <div><label class="f-label">Approved service</label><input class="f-input" name="approved_service" maxlength="150" value="<?= $e($case['service_type']) ?>"/></div>
        </div>
        <div class="hc-hint">Recorded as "LOA/eLOA presented by patient" under <?= $e($staff_name) ?>.</div>
        <button class="btn-submit hc-btn-green" style="width:auto;padding:9px 18px">Accept LOA &amp; confirm appointment</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($canVerify): ?>
    <div class="hc-pane <?= ($hasLoa && $canStatus) ? '' : 'active' ?>" id="p-contact">
      <?php if ($agents): ?>
      <div class="hc-hint"><b>HMO agents on file:</b></div>
      <?php foreach ($agents as $ag): ?>
        <div class="hc-contact"><b><?= $e($ag['agent_name']) ?></b><?= $ag['role_title'] ? ' · ' . $e($ag['role_title']) : '' ?><?= $ag['is_primary'] ? ' · <span style="color:var(--primary);font-weight:700">Primary</span>' : '' ?><br/>
          <?= $e(implode(' · ', array_filter([$ag['phone'], $ag['email'], $ag['channel']]))) ?><?= $ag['notes'] ? '<br/><span style="color:var(--muted)">' . $e($ag['notes']) . '</span>' : '' ?></div>
      <?php endforeach; endif; ?>
      <div class="hc-hint">How this HMO is verified (set by admin): <b><?= $e(HMO_RULE_METHODS[$rules['verification_method']] ?? '') ?></b>.</div>
      <?php if ($case['hmo_contact_person'] || $case['hmo_phone'] || $case['hmo_email']): ?>
      <div class="hc-hint">HMO contact on file (Admin &gt; HMO):
        <?= $e(implode(' · ', array_filter([$case['hmo_contact_person'], $case['hmo_phone'], $case['hmo_email']]))) ?></div>
      <?php else: ?>
      <div class="hc-hint">No contact is saved for this HMO. An admin can add one in Admin &gt; HMO.</div>
      <?php endif; ?>
      <form method="POST" class="hc-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="contact"/>
        <div class="hc-row2">
          <div><label class="f-label">Contact channel</label>
            <select name="channel" class="f-input" required><?php foreach (HMO_CONTACT_CHANNELS as $ch): ?><option><?= $e($ch) ?></option><?php endforeach; ?></select></div>
          <div><label class="f-label">Number / email used</label><input class="f-input" name="contact_detail" maxlength="150" placeholder="e.g. HMO hotline"/></div>
          <div><label class="f-label">Date &amp; time of contact</label><input class="f-input" type="datetime-local" name="contacted_at" value="<?= $e(date('Y-m-d\TH:i')) ?>" required/></div>
          <div><label class="f-label">HMO request / reference no.</label><input class="f-input" name="hmo_reference_no" maxlength="80"/></div>
        </div>
        <label class="f-label">Response, or reason for delay</label>
        <textarea class="f-input" name="response" maxlength="2000" required placeholder="What the HMO said, or why there is no answer yet"></textarea>
        <label class="f-label">Follow-up date (optional)</label>
        <input class="f-input" type="date" name="follow_up_date" style="max-width:220px"/>
        <div class="hc-hint">Logged under <?= $e($staff_name) ?>.</div>
        <button class="btn-primary">Save contact</button>
      </form>
      <?php if ($contacts): ?>
        <h4>Contact attempts (<?= count($contacts) ?>)</h4>
        <?php foreach ($contacts as $c): ?>
          <div class="hc-contact">
            <b><?= $e($c['channel']) ?></b><?= $c['contact_detail'] ? ' · ' . $e($c['contact_detail']) : '' ?> &middot; <?= $e(date('M j, Y g:i A', strtotime($c['contacted_at']))) ?>
            &middot; <?= $e($c['staff_name'] ?: 'Staff') ?><br/>
            <?= $c['hmo_reference_no'] ? 'Ref: <b>' . $e($c['hmo_reference_no']) . '</b><br/>' : '' ?>
            <?= nl2br($e($c['response'])) ?>
            <?= $c['follow_up_date'] ? '<br/><span style="color:var(--primary);font-weight:700">Follow up: ' . $e(date('M j, Y', strtotime($c['follow_up_date']))) . '</span>' : '' ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php endif; ?>

    <!-- 5. Approve -->
    <?php if ($canStatus): ?>
    <div class="hc-pane" id="p-approve">
      <div class="hc-hint">Only mark this approved after the HMO confirmed it. The patient is notified.</div>
      <?php if ($onHold): ?><div class="hc-flash no" style="margin:0">Cannot approve: <?= $e($case['hmo_provider']) ?> is on hold.</div><?php else: ?>
      <form method="POST" class="hc-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="approve"/>
        <div class="hc-row2">
          <div><label class="f-label">Approval / LOA no.</label><input class="f-input" name="approval_loa_no" maxlength="60" value="<?= $e($case['approval_loa_no'] ?? '') ?>" placeholder="If one was issued"/></div>
          <div><label class="f-label">Approved service</label><input class="f-input" name="approved_service" maxlength="150" value="<?= $e($case['approved_service'] ?? $case['service_type']) ?>"/></div>
          <div><label class="f-label">Valid from</label><input class="f-input" type="date" name="valid_from" value="<?= $e($case['valid_from'] ?? $defFrom) ?>"/></div>
          <div><label class="f-label">Valid until</label><input class="f-input" type="date" name="valid_until" value="<?= $e($case['valid_until'] ?? $defUntil) ?>"/></div>
          <div><label class="f-label">HMO covers (₱)</label><input class="f-input" type="number" step="0.01" min="0" name="hmo_coverage_amount" value="<?= $e($case['hmo_coverage_amount'] ?? '') ?>"/></div>
          <div><label class="f-label">Patient's share / co-pay (₱)</label><input class="f-input" type="number" step="0.01" min="0" name="patient_share" value="<?= $e($case['patient_share'] ?? '') ?>"/></div>
        </div>
        <label class="f-label">Confirmed benefit (optional)</label>
        <input class="f-input" name="benefit_note" maxlength="255" value="<?= $e($case['benefit_note'] ?? '') ?>" placeholder="e.g. Teleconsultation covered, 1 visit"/>
        <label class="f-label">How was it verified?</label>
        <select name="verification_method" class="f-input" required><option value="">Choose…</option><?php foreach (HMO_VERIFY_METHODS as $m): ?><option <?= $m === $ruleMethod ? 'selected' : '' ?>><?= $e($m) ?></option><?php endforeach; ?></select>
        <div class="hc-hint">Recorded under <?= $e($staff_name) ?>.</div>
        <button class="btn-submit hc-btn-green" style="width:auto;padding:9px 18px">Mark as approved</button>
      </form>
      <?php endif; ?>
    </div>

    <!-- 5. Reject -->
    <div class="hc-pane" id="p-reject">
      <div class="hc-hint">The patient is told to choose another payment method or cancel. The appointment stays booked.</div>
      <form method="POST" class="hc-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="reject"/>
        <label class="f-label">Reason it was not approved</label>
        <input class="f-input" name="decision_reason" maxlength="255" required placeholder="e.g. Coverage expired, member not found"/>
        <label class="f-label">How was it verified?</label>
        <select name="verification_method" class="f-input" required><option value="">Choose…</option><?php foreach (HMO_VERIFY_METHODS as $m): ?><option <?= $m === $ruleMethod ? 'selected' : '' ?>><?= $e($m) ?></option><?php endforeach; ?></select>
        <button class="btn-submit hc-btn-red" style="width:auto;padding:9px 18px" onclick="return confirm('Mark as NOT approved and notify the patient?')">Mark as not approved</button>
      </form>
    </div>

    <!-- Ask patient -->
    <div class="hc-pane" id="p-ask">
      <div class="hc-hint">Use this when something is missing or wrong (member ID, a document, an LOA). The patient is notified with your message.</div>
      <form method="POST" class="hc-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="instruct"/>
        <label class="f-label">What does the patient need to do?</label>
        <textarea class="f-input" name="staff_instructions" maxlength="1000" required placeholder="e.g. Your member ID could not be found. Please send a clear photo of your HMO card."><?= $e($case['staff_instructions'] ?? '') ?></textarea>
        <button class="btn-submit hc-btn-orange" style="width:auto;padding:9px 18px">Notify patient</button>
      </form>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Note (always) -->
    <div class="hc-pane" id="p-note">
      <form method="POST" class="hc-form">
        <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="note"/>
        <label class="f-label">Internal note (the patient does not see this)</label>
        <textarea class="f-input" name="note" maxlength="1000" required></textarea>
        <button class="btn-primary">Add note</button>
      </form>
    </div>
  </div>
</div>

<div>
  <!-- 6. History -->
  <div class="hc-card">
    <h3>Case history</h3>
    <?php if (!$events): ?>
      <div style="font-size:12px;color:var(--muted)">Nothing recorded yet. The booking was submitted <?= $e(date('M j, Y g:i A', strtotime($case['created_at']))) ?>.</div>
    <?php else: ?>
    <ul class="hc-tl">
      <?php foreach ($events as $ev): ?>
        <li>
          <div class="t"><?= $e($ev['summary']) ?><?= ($ev['status_from'] && $ev['status_to']) ? ' <span style="font-weight:600;color:var(--muted)">(' . $e($ev['status_from']) . ' → ' . $e($ev['status_to']) . ')</span>' : '' ?></div>
          <div class="m"><?= $e($ev['staff_name'] ?: 'System') ?> &middot; <?= $e(date('M j, Y g:i A', strtotime($ev['created_at']))) ?></div>
          <?php if ($ev['detail']): ?><div class="d"><?= $e($ev['detail']) ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
      <li><div class="t">Booking submitted by patient</div><div class="m">Patient &middot; <?= $e(date('M j, Y g:i A', strtotime($case['created_at']))) ?></div></li>
    </ul>
    <?php endif; ?>
  </div>
</div>
</div>

<script>
document.querySelectorAll('.hc-tabbtn').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('.hc-tabbtn').forEach(function (x) { x.classList.toggle('active', x === b); });
    document.querySelectorAll('.hc-pane').forEach(function (p) { p.classList.toggle('active', p.id === b.dataset.pane); });
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>
