<?php
// private_telecare/booking/hmo_status.php — Page 5: HMO booking status
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/hmo_patient_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);
$case = hmo_patient_ensure_schema($conn) ? hmo_patient_case($conn, $appt_id, $patient_id) : null;
if (!$case) { $_SESSION['toast_error'] = 'HMO booking not found.'; header('Location: router.php?page=visits'); exit; }

$cancelled = $case['appt_status'] === 'Cancelled';
$state = booking_coverage_state($conn, $appt_id, 'HMO');
$onHold = booking_hmo_is_on_hold($conn, (string)$case['hmo_provider']);
$docsCount = count(hmo_docs_for($conn, $appt_id));
$submitted = !empty($case['patient_submitted_at']);
$last = $case['status_updated_at'] ?: ($case['patient_updated_at'] ?: ($case['patient_submitted_at'] ?: $case['created_at']));
$ev = $conn->query("SELECT summary, created_at FROM hmo_case_events WHERE appointment_id = " . (int)$appt_id .
      " AND event_type IN ('Status','Authorization','Patient') ORDER BY id DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
$when = date('F j, Y', strtotime($case['appointment_date'])) . ' at ' . date('g:i A', strtotime($case['appointment_time']));
$s = $state['status'];

$page_title = 'HMO Booking Status — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
echo hmo_patient_css();
?>
<div class="hp-wrap">
  <div class="wiz-title">HMO Booking Status</div>
  <div class="wiz-sub">Reference <?= $e($case['reference_no']) ?></div>

  <?php if (!empty($_GET['submitted'])): ?><div class="hp-flash ok">Submitted. The clinic will review your HMO booking.</div><?php endif; ?>

  <div class="hp-card">
    <div style="display:flex;justify-content:space-between;gap:.8rem;flex-wrap:wrap;align-items:center">
      <div><div class="k" style="font-size:.66rem;font-weight:700;text-transform:uppercase;color:var(--muted)">HMO verification status</div>
        <div style="font-size:1.1rem;font-weight:800;color:var(--green);margin-top:.2rem"><?= $e($cancelled ? 'Appointment cancelled' : $state['title']) ?></div></div>
      <span class="hp-pill <?= $cancelled ? 'no' : $e($state['tone']) ?>"><?= $e($cancelled ? 'Cancelled' : $state['badge']) ?></span>
    </div>
    <p class="hp-muted" style="margin:.7rem 0 0"><?= $e($cancelled ? 'This appointment was cancelled.' : $state['sub']) ?></p>
  </div>

  <?php if (!$cancelled && $s === 'Action Required' && $case['staff_instructions']): ?>
    <div class="hp-flash wait"><b>What the clinic needs from you:</b><br/><?= nl2br($e($case['staff_instructions'])) ?></div>
  <?php elseif (!$cancelled && ($s === 'Not Approved' || $s === 'Not Covered') && $case['decision_reason']): ?>
    <div class="hp-flash no"><b>Reason:</b> <?= $e($case['decision_reason']) ?></div>
  <?php endif; ?>
  <?php if (!$cancelled && $onHold && $s !== 'Approved'): ?>
    <div class="hp-flash no"><b>The clinic is not accepting <?= $e($case['hmo_provider']) ?> right now.</b> Please choose another payment method or cancel.</div>
  <?php endif; ?>
  <?php if (!$cancelled && $s === 'Approved'): ?>
    <div class="hp-flash ok">
      <?= $case['approval_loa_no'] ? 'LOA / authorization no. <b>' . $e($case['approval_loa_no']) . '</b>. ' : '' ?>
      <?= $case['valid_until'] ? 'Valid until ' . $e(date('M j, Y', strtotime($case['valid_until']))) . '. ' : '' ?>
      <?= ($case['patient_share'] !== null && (float)$case['patient_share'] > 0) ? 'Your share (co-pay): <b>₱' . $e(number_format((float)$case['patient_share'], 2)) . '</b>.' : '' ?>
    </div>
  <?php endif; ?>

  <div class="hp-card">
    <h3>Appointment</h3>
    <div class="hp-kv">
      <div><div class="k">Doctor</div><div class="v">Dr. <?= $e($case['doctor_name']) ?></div></div>
      <div><div class="k">Date &amp; time</div><div class="v"><?= $e($when) ?></div></div>
      <div><div class="k">HMO</div><div class="v"><?= $e($case['hmo_provider']) ?></div></div>
      <div><div class="k">Member ID</div><div class="v"><?= $e(hmo_mask_id($case['hmo_member_id'])) ?></div></div>
      <div><div class="k">Last update</div><div class="v"><?= $e(date('M j, Y g:i A', strtotime($last))) ?></div></div>
      <div><div class="k">Documents</div><div class="v"><?= $docsCount ?> uploaded<?= $submitted ? '' : ' · not submitted yet' ?></div></div>
    </div>
  </div>

  <div class="hp-card">
    <h3>Next steps</h3>
    <ul class="hp-muted" style="margin:0 0 0 1rem;padding:0">
    <?php if ($cancelled): ?>
      <li>Nothing more to do. You can book a new appointment any time.</li>
    <?php elseif ($s === 'Approved'): ?>
      <li>Join your consultation on <?= $e($when) ?> from your appointment page.</li>
    <?php elseif (!$submitted): ?>
      <li>Upload your documents and press <b>Submit Booking</b> so the clinic can start.</li>
    <?php elseif ($s === 'Action Required'): ?>
      <li>Follow the clinic's instructions above, then press <b>Resolve HMO issues</b>.</li>
    <?php elseif ($s === 'Not Approved' || $s === 'Not Covered'): ?>
      <li>Correct your details, ask the clinic for help, choose self-pay, or cancel — see <b>Resolve HMO issues</b>.</li>
    <?php elseif ($state['has_loa']): ?>
      <li>The clinic is checking your LOA. You do not need to do anything else.</li>
    <?php else: ?>
      <li>The clinic is waiting for your HMO to approve. You do not need to do anything else.</li>
    <?php endif; ?>
      <li>You get a notification each time the status changes.</li>
    </ul>
    <div class="hp-actions">
      <?php if (!$cancelled && $s !== 'Approved'): ?>
        <?php if (!$submitted): ?><a class="wiz-btn primary" href="router.php?page=booking/hmo_documents&amp;appt_id=<?= $appt_id ?>">Upload documents</a><?php endif; ?>
        <a class="wiz-btn <?= $submitted ? 'primary' : 'ghost' ?>" href="router.php?page=booking/hmo_resolve&amp;appt_id=<?= $appt_id ?>">Resolve HMO issues</a>
      <?php endif; ?>
      <a class="wiz-btn ghost" href="router.php?page=booking/confirmed&amp;appt_id=<?= $appt_id ?>">Appointment details</a>
    </div>
  </div>

  <?php if ($ev): ?>
  <div class="hp-card"><h3>Updates</h3>
    <ul class="hp-tl"><?php foreach ($ev as $x): ?>
      <li><?= $e($x['summary']) ?><div class="m"><?= $e(date('M j, Y g:i A', strtotime($x['created_at']))) ?></div></li>
    <?php endforeach; ?></ul>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
