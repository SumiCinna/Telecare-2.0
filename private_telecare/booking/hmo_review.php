<?php
// private_telecare/booking/hmo_review.php — Page 4: Review & Submit HMO booking
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/hmo_patient_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? $_POST['appt_id'] ?? 0);
$case = hmo_patient_ensure_schema($conn) ? hmo_patient_case($conn, $appt_id, $patient_id) : null;
if (!$case) { $_SESSION['toast_error'] = 'HMO booking not found.'; header('Location: router.php?page=visits'); exit; }
if ($case['appt_status'] === 'Cancelled') { header('Location: router.php?page=booking/confirmed&appt_id=' . $appt_id); exit; }

$docs = hmo_docs_for($conn, $appt_id);
$hasCard = false; $hasLoaDoc = false;
foreach ($docs as $d) { if ($d['doc_type'] === 'card') $hasCard = true; if ($d['doc_type'] === 'loa') $hasLoaDoc = true; }
$hasLoa = trim((string)$case['loa_number']) !== '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if (!hmo_pat_csrf_ok()) $errors[] = 'Your session expired. Please try again.';
    elseif (!$hasCard)      $errors[] = 'Please upload your HMO card or membership proof first.';
    else {
        $u = $conn->prepare("UPDATE appointment_hmo SET patient_submitted_at = NOW(), patient_updated_at = NOW() WHERE appointment_id = ?");
        $u->bind_param('i', $appt_id); $u->execute();
        hmo_patient_event($conn, $appt_id, 'Patient submitted the booking for HMO verification.',
            count($docs) . ' document(s) on file' . ($hasLoa ? '; LOA number given' : '; no LOA yet') . (!empty($case['assistance_requested']) ? '; asked for help getting the LOA' : ''));
        header('Location: router.php?page=booking/hmo_status&appt_id=' . $appt_id . '&submitted=1'); exit;
    }
}

$state = booking_coverage_state($conn, $appt_id, 'HMO');
$csrf = hmo_pat_csrf();
$e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
$when = date('F j, Y', strtotime($case['appointment_date'])) . ' at ' . date('g:i A', strtotime($case['appointment_time']));

$page_title = 'Review HMO Booking — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
echo hmo_patient_css();
?>
<div class="hp-wrap">
  <div class="wiz-title">Review &amp; Submit</div>
  <div class="wiz-sub">Check your HMO booking before you send it to the clinic.</div>
  <div class="hp-steps"><span>1 Booking saved ✓</span><span>2 Documents ✓</span><span class="on">3 Review &amp; Submit</span><span>4 Status</span></div>

  <?php foreach ($errors as $m): ?><div class="hp-flash no"><?= $e($m) ?></div><?php endforeach; ?>

  <div class="hp-card">
    <h3>Appointment</h3>
    <div class="hp-kv">
      <div><div class="k">Reference</div><div class="v"><?= $e($case['reference_no']) ?></div></div>
      <div><div class="k">Doctor</div><div class="v">Dr. <?= $e($case['doctor_name']) ?></div></div>
      <div><div class="k">Date &amp; time</div><div class="v"><?= $e($when) ?></div></div>
      <div><div class="k">Consultation</div><div class="v"><?= $e($case['service_type']) ?></div></div>
      <div class="hp-full"><div class="k">Concern</div><div class="v"><?= $e($case['reason']) ?></div></div>
    </div>
  </div>

  <div class="hp-card">
    <h3>HMO details</h3>
    <div class="hp-kv">
      <div><div class="k">HMO provider</div><div class="v"><?= $e($case['hmo_provider']) ?></div></div>
      <div><div class="k">Member ID</div><div class="v"><?= $e(hmo_mask_id($case['hmo_member_id'])) ?></div></div>
      <div><div class="k">Member type</div><div class="v"><?= $e($case['member_type']) ?></div></div>
      <div><div class="k">Principal member</div><div class="v"><?= $e($case['principal_member_name']) ?></div></div>
      <div><div class="k">LOA / approval no.</div><div class="v"><?= $hasLoa ? $e($case['loa_number']) : 'None yet' ?></div></div>
      <div><div class="k">Plan</div><div class="v"><?= $case['hmo_plan'] ? $e($case['hmo_plan']) : '—' ?></div></div>
    </div>
    <div class="hp-actions"><a class="wiz-btn ghost" href="router.php?page=booking/hmo_resolve&amp;appt_id=<?= $appt_id ?>#edit">Edit Details</a></div>
  </div>

  <div class="hp-card">
    <h3>Documents (<?= count($docs) ?>)</h3>
    <?php if (!$docs): ?><div class="hp-muted">No documents uploaded.</div><?php else: ?>
    <ul class="hp-list"><?php foreach ($docs as $d): ?>
      <li><span><b><?= $e(HMO_DOC_TYPES[$d['doc_type']] ?? 'Document') ?></b> <span class="hp-muted">— <?= $e($d['original_name']) ?></span></span></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if (!$hasCard): ?><div class="hp-flash no" style="margin-top:.6rem">An HMO card or membership proof is required.</div><?php endif; ?>
    <?php if ($hasLoa && !$hasLoaDoc): ?><div class="hp-flash wait" style="margin-top:.6rem">You entered an LOA number. Uploading the LOA itself helps the clinic accept it faster.</div><?php endif; ?>
    <div class="hp-actions"><a class="wiz-btn ghost" href="router.php?page=booking/hmo_documents&amp;appt_id=<?= $appt_id ?>">Add or change documents</a></div>
  </div>

  <div class="hp-flash info">
    <b>Coverage is not guaranteed yet.</b>
    <?= $hasLoa ? 'The clinic checks your LOA and accepts it. Your appointment is confirmed after that.'
                : 'The clinic asks your HMO for approval. Your appointment is confirmed only after the HMO approves.' ?>
    You will get a notification when the status changes.
  </div>

  <form method="POST" class="hp-actions">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="submit"/>
    <button class="wiz-btn primary" type="submit" <?= $hasCard ? '' : 'disabled' ?>>Submit Booking</button>
    <a class="wiz-btn ghost" href="router.php?page=booking/hmo_resolve&amp;appt_id=<?= $appt_id ?>#edit">Edit Details</a>
  </form>
</div>
<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
