<?php
// private_telecare/booking/step4_yakap.php
// PhilHealth YAKAP details. Only the fields the PATIENT can know are asked here.
// Diagnosis / ICD-10, notes, prescription, PCU reference and provider confirmation
// are filled in by the provider after the consultation (columns exist in appointment_yakap).
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department', 'doctor_id', 'appt_date', 'appt_time']);
if (($_SESSION['booking']['payment_method'] ?? '') !== 'YAKAP') {
    header('Location: router.php?page=booking/step4_payment_method'); exit;
}

$b = $_SESSION['booking'];
$saved = $b['coverage'] ?? [];

$dstmt = $conn->prepare("SELECT full_name FROM doctors WHERE id=?");
$doctor_id = (int)$b['doctor_id'];
$dstmt->bind_param("i", $doctor_id);
$dstmt->execute();
$doctor = $dstmt->get_result()->fetch_assoc();
if (!$doctor) { header('Location: router.php?page=booking/step2_doctor'); exit; }

$reason_parts = $b['reasons'] ?? [];
if (!empty($b['reason_other'])) $reason_parts[] = $b['reason_other'];
$reason_display = $reason_parts ? implode(', ', $reason_parts) : 'Not specified';

$v = [
    'philhealth_pin'     => $saved['philhealth_pin']     ?? '',
    'member_type'        => $saved['member_type']        ?? '',
    'contact_number'     => $saved['contact_number']     ?? ($p['phone_number'] ?? ''),
    'address'            => $saved['address']            ?? ($p['address'] ?? ($p['home_address'] ?? '')),
    'yakap_clinic'       => $saved['yakap_clinic']       ?? '',
    'empanelment_status' => $saved['empanelment_status'] ?? '',
    'fpe_status'         => $saved['fpe_status']         ?? '',
    'consent'            => !empty($saved['consent']),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = preg_replace('/\D/', '', (string)($_POST['philhealth_pin'] ?? ''));
    $v['philhealth_pin']     = $pin;
    $v['member_type']        = $_POST['member_type'] ?? '';
    $v['contact_number']     = booking_clean($_POST['contact_number'] ?? '', 20);
    $v['address']            = booking_clean($_POST['address'] ?? '', 255);
    $v['yakap_clinic']       = booking_clean($_POST['yakap_clinic'] ?? '', 150);
    $v['empanelment_status'] = $_POST['empanelment_status'] ?? '';
    $v['fpe_status']         = $_POST['fpe_status'] ?? '';
    $v['consent']            = !empty($_POST['consent']);

    if (strlen($pin) !== 12)                                              $errors[] = 'PhilHealth PIN must be 12 digits.';
    if (!in_array($v['member_type'], ['Member', 'Dependent'], true))      $errors[] = 'Select whether the patient is a Member or a Dependent.';
    if (!booking_valid_phone($v['contact_number']))                       $errors[] = 'Enter a valid contact number.';
    if ($v['address'] === '')                                             $errors[] = 'Address is required.';
    if ($v['yakap_clinic'] === '')                                        $errors[] = 'Enter your YAKAP clinic.';
    if (!in_array($v['empanelment_status'], ['Empaneled', 'Not Yet Empaneled'], true)) $errors[] = 'Select your YES/MCA (empanelment) status.';
    if (!in_array($v['fpe_status'], ['Completed', 'Not Yet Completed'], true))         $errors[] = 'Select your First Patient Encounter (FPE) status.';
    if (!$v['consent'])                                                   $errors[] = 'Please confirm the consent statement to continue.';

    if (!$errors) {
        $_SESSION['booking']['coverage'] = [
            'method'             => 'YAKAP',
            'philhealth_pin'     => $v['philhealth_pin'],
            'member_type'        => $v['member_type'],
            'contact_number'     => $v['contact_number'],
            'address'            => $v['address'],
            'yakap_clinic'       => $v['yakap_clinic'],
            'empanelment_status' => $v['empanelment_status'],
            'fpe_status'         => $v['fpe_status'],
            'consent'            => 1,
        ];
        header('Location: router.php?page=booking/step4_review'); exit;
    }
}

$page_title = 'PhilHealth YAKAP — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
echo booking_form_css();
$e = fn($x) => htmlspecialchars((string)$x);
?>
<div class="wiz-page">
  <div class="wiz-title">PhilHealth YAKAP Information</div>
  <div class="wiz-sub">Please provide your PhilHealth YAKAP details.</div>

  <?php render_stepper(4); ?>

  <?php if ($errors): ?>
    <div class="wiz-err"><?php foreach ($errors as $er): ?><div><?= $e($er) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="wiz-card f-field">
      <div class="f-sec">Patient Information</div>
      <div class="f-grid">
        <div>
          <label class="f-lbl" for="pin">PhilHealth PIN <span class="req">*</span></label>
          <input type="text" id="pin" name="philhealth_pin" inputmode="numeric" autocomplete="off" maxlength="14"
                 placeholder="e.g. 12-345678901-2" value="<?= $e($v['philhealth_pin'] ? booking_format_pin($v['philhealth_pin']) : '') ?>">
        </div>
        <div>
          <label class="f-lbl">Patient Full Name</label>
          <input type="text" value="<?= $e($p['full_name']) ?>" readonly>
        </div>
        <div>
          <label class="f-lbl">Date of Birth</label>
          <input type="text" value="<?= !empty($p['date_of_birth']) ? $e((new DateTime($p['date_of_birth']))->format('M j, Y')) : '' ?>" readonly>
        </div>
        <div>
          <label class="f-lbl">Member Type <span class="req">*</span></label>
          <div class="f-choice">
            <label><input type="radio" name="member_type" value="Member"    <?= $v['member_type'] === 'Member'    ? 'checked' : '' ?>> Member</label>
            <label><input type="radio" name="member_type" value="Dependent" <?= $v['member_type'] === 'Dependent' ? 'checked' : '' ?>> Dependent</label>
          </div>
        </div>
        <div>
          <label class="f-lbl" for="contact">Contact Number <span class="req">*</span></label>
          <input type="tel" id="contact" name="contact_number" placeholder="09XX XXX XXXX" value="<?= $e($v['contact_number']) ?>">
        </div>
        <div>
          <label class="f-lbl" for="address">Address <span class="req">*</span></label>
          <input type="text" id="address" name="address" placeholder="Enter your complete address" value="<?= $e($v['address']) ?>">
        </div>
      </div>

      <div class="f-sec">YAKAP Details</div>
      <div class="f-grid">
        <div class="f-full">
          <label class="f-lbl" for="clinic">YAKAP Clinic <span class="req">*</span></label>
          <input type="text" id="clinic" name="yakap_clinic" placeholder="Your selected / accredited YAKAP clinic" value="<?= $e($v['yakap_clinic']) ?>">
        </div>
        <div>
          <label class="f-lbl">YES / MCA Status (Empanelment) <span class="req">*</span></label>
          <div class="f-choice">
            <label><input type="radio" name="empanelment_status" value="Empaneled"         <?= $v['empanelment_status'] === 'Empaneled'         ? 'checked' : '' ?>> Empaneled</label>
            <label><input type="radio" name="empanelment_status" value="Not Yet Empaneled" <?= $v['empanelment_status'] === 'Not Yet Empaneled' ? 'checked' : '' ?>> Not Yet Empaneled</label>
          </div>
        </div>
        <div>
          <label class="f-lbl">First Patient Encounter (FPE) <span class="req">*</span></label>
          <div class="f-choice">
            <label><input type="radio" name="fpe_status" value="Completed"         <?= $v['fpe_status'] === 'Completed'         ? 'checked' : '' ?>> Completed</label>
            <label><input type="radio" name="fpe_status" value="Not Yet Completed" <?= $v['fpe_status'] === 'Not Yet Completed' ? 'checked' : '' ?>> Not Yet Completed</label>
          </div>
        </div>
      </div>

      <div class="f-sec">Consultation Information</div>
      <div class="f-grid">
        <div>
          <label class="f-lbl">Consultation Date</label>
          <input type="text" readonly value="<?= $e((new DateTime($b['appt_date']))->format('M j, Y')) ?> at <?= $e(date('g:i A', strtotime($b['appt_time']))) ?>">
        </div>
        <div>
          <label class="f-lbl">Physician</label>
          <input type="text" readonly value="Dr. <?= $e($doctor['full_name']) ?>">
        </div>
        <div class="f-full">
          <label class="f-lbl">Chief Complaint</label>
          <input type="text" readonly value="<?= $e($reason_display) ?>">
        </div>
      </div>

      <div class="f-note" style="margin-top:1.1rem;">
        Your YAKAP details will be recorded with this appointment so the clinic can verify them.
        Diagnosis, prescription and PCU reference are added by your doctor after the consultation.
      </div>

      <label class="f-consent">
        <input type="checkbox" name="consent" value="1" <?= $v['consent'] ? 'checked' : '' ?>>
        <span>I confirm that the information provided is correct and authorize its processing for PhilHealth / YAKAP purposes.</span>
      </label>
    </div>

    <div class="wiz-actions">
      <a href="router.php?page=booking/step4_payment_method" class="wiz-btn ghost">Back</a>
      <button type="submit" class="wiz-btn primary">Continue to Review</button>
    </div>
  </form>
</div>

<script>
// Auto-format the PIN as 12-345678901-2 while typing.
(function () {
  var el = document.getElementById('pin');
  el.addEventListener('input', function () {
    var d = el.value.replace(/\D/g, '').slice(0, 12), out = d;
    if (d.length > 2)  out = d.slice(0, 2) + '-' + d.slice(2);
    if (d.length > 11) out = d.slice(0, 2) + '-' + d.slice(2, 11) + '-' + d.slice(11);
    el.value = out;
  });
})();
</script>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
