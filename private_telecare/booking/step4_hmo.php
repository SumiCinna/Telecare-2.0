<?php
// private_telecare/booking/step4_hmo.php
// HMO consultation details. Patient-known fields only; HMO coverage amount, patient share,
// diagnosis, claim reference/status and provider confirmation are filled in later by staff/doctor
// (columns exist in appointment_hmo).
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department', 'doctor_id', 'appt_date', 'appt_time']);
if (($_SESSION['booking']['payment_method'] ?? '') !== 'HMO') {
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

// HMO list = the providers the admin manages in Admin > HMO (active ones only).
// Falls back to a built-in list if that table is empty or missing. "Other" is always offered.
$providerList = [];
try {
    $pr = $conn->query("SELECT name FROM hmo_providers WHERE status='Active' ORDER BY name ASC");
    while ($pr && ($prow = $pr->fetch_assoc())) $providerList[] = $prow['name'];
} catch (Throwable $ignore) { /* table not there: use fallback */ }
if (!$providerList) $providerList = array_values(array_diff(BOOKING_HMO_PROVIDERS, ['Other']));
$providerList[] = 'Other';

// A provider that isn't in the list is stored as "Other: <name>".
$savedProvider = $saved['hmo_provider'] ?? '';
$providerSel = '';
$providerOther = '';
if ($savedProvider !== '') {
    if (in_array($savedProvider, $providerList, true)) {
        $providerSel = $savedProvider;
    } else {
        $providerSel = 'Other';
        $providerOther = preg_replace('/^Other:\s*/', '', $savedProvider);
    }
}

$v = [
    'hmo_member_id'         => $saved['hmo_member_id']         ?? '',
    'member_type'           => $saved['member_type']           ?? '',
    'principal_member_name' => $saved['principal_member_name'] ?? '',
    'company_employer'      => $saved['company_employer']      ?? '',
    'hmo_plan'              => $saved['hmo_plan']              ?? '',
    'contact_number'        => $saved['contact_number']        ?? ($p['phone_number'] ?? ''),
    'service_type'          => $saved['service_type']          ?? 'Online Consultation',
    'loa_number'            => $saved['loa_number']            ?? '',
    'consent'               => !empty($saved['consent']),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $providerSel   = $_POST['hmo_provider'] ?? '';
    $providerOther = booking_clean($_POST['hmo_provider_other'] ?? '', 90);
    $v['hmo_member_id']         = booking_clean($_POST['hmo_member_id'] ?? '', 60);
    $v['member_type']           = $_POST['member_type'] ?? '';
    $v['principal_member_name'] = booking_clean($_POST['principal_member_name'] ?? '', 150);
    $v['company_employer']      = booking_clean($_POST['company_employer'] ?? '', 150);
    $v['hmo_plan']              = booking_clean($_POST['hmo_plan'] ?? '', 100);
    $v['contact_number']        = booking_clean($_POST['contact_number'] ?? '', 20);
    $v['service_type']          = $_POST['service_type'] ?? '';
    $v['loa_number']            = booking_clean($_POST['loa_number'] ?? '', 60);
    $v['consent']               = !empty($_POST['consent']);

    if (!in_array($providerSel, $providerList, true))                      $errors[] = 'Select your HMO provider.';
    if ($providerSel === 'Other' && $providerOther === '')                $errors[] = 'Enter the name of your HMO provider.';
    if ($v['hmo_member_id'] === '')                                       $errors[] = 'HMO Member ID / Card No. is required.';
    if (!in_array($v['member_type'], ['Member', 'Dependent'], true))      $errors[] = 'Select whether the patient is a Member or a Dependent.';
    if ($v['principal_member_name'] === '')                               $errors[] = 'Principal member name is required.';
    if (!booking_valid_phone($v['contact_number']))                       $errors[] = 'Enter a valid contact number.';
    if (!in_array($v['service_type'], ['Online Consultation', 'Follow-up Consultation'], true)) $errors[] = 'Select the type of consultation.';
    if (!$v['consent'])                                                   $errors[] = 'Please confirm the statement to continue.';

    if (!$errors) {
        $_SESSION['booking']['coverage'] = [
            'method'                => 'HMO',
            'hmo_provider'          => $providerSel === 'Other' ? 'Other: ' . $providerOther : $providerSel,
            'hmo_member_id'         => $v['hmo_member_id'],
            'member_type'           => $v['member_type'],
            'principal_member_name' => $v['principal_member_name'],
            'company_employer'      => $v['company_employer'],
            'hmo_plan'              => $v['hmo_plan'],
            'contact_number'        => $v['contact_number'],
            'service_type'          => $v['service_type'],
            'loa_number'            => $v['loa_number'],
            'consent'               => 1,
        ];
        header('Location: router.php?page=booking/step4_review'); exit;
    }
}

$page_title = 'HMO Information — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
echo booking_form_css();
$e = fn($x) => htmlspecialchars((string)$x);
?>
<div class="wiz-page">
  <div class="wiz-title">HMO Information</div>
  <div class="wiz-sub">Please provide your HMO details so we can check your coverage and authorization.</div>

  <?php render_stepper(4); ?>

  <?php if ($errors): ?>
    <div class="wiz-err"><?php foreach ($errors as $er): ?><div><?= $e($er) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="POST" novalidate>
    <div class="wiz-card f-field">
      <div class="f-sec">HMO Details</div>
      <div class="f-grid">
        <div>
          <label class="f-lbl" for="prov">HMO Provider <span class="req">*</span></label>
          <select id="prov" name="hmo_provider">
            <option value="">Select your HMO</option>
            <?php foreach ($providerList as $hp): ?>
              <option value="<?= $e($hp) ?>" <?= $providerSel === $hp ? 'selected' : '' ?>><?= $e($hp) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="otherWrap" style="<?= $providerSel === 'Other' ? '' : 'display:none' ?>">
          <label class="f-lbl" for="provOther">HMO Name <span class="req">*</span></label>
          <input type="text" id="provOther" name="hmo_provider_other" maxlength="90" value="<?= $e($providerOther) ?>">
        </div>
        <div>
          <label class="f-lbl" for="mid">HMO Member ID / Card No. <span class="req">*</span></label>
          <input type="text" id="mid" name="hmo_member_id" maxlength="60" placeholder="Enter your HMO member ID" value="<?= $e($v['hmo_member_id']) ?>">
        </div>
        <div>
          <label class="f-lbl">Member Type <span class="req">*</span></label>
          <div class="f-choice">
            <label><input type="radio" name="member_type" value="Member"    <?= $v['member_type'] === 'Member'    ? 'checked' : '' ?>> Member</label>
            <label><input type="radio" name="member_type" value="Dependent" <?= $v['member_type'] === 'Dependent' ? 'checked' : '' ?>> Dependent</label>
          </div>
        </div>
        <div>
          <label class="f-lbl" for="pm">Principal Member <span class="req">*</span></label>
          <input type="text" id="pm" name="principal_member_name" maxlength="150" placeholder="Full name of principal member" value="<?= $e($v['principal_member_name']) ?>">
        </div>
        <div>
          <label class="f-lbl" for="comp">Company / Employer</label>
          <input type="text" id="comp" name="company_employer" maxlength="150" placeholder="Optional" value="<?= $e($v['company_employer']) ?>">
        </div>
        <div>
          <label class="f-lbl" for="plan">HMO Plan / Account Type</label>
          <input type="text" id="plan" name="hmo_plan" maxlength="100" placeholder="Optional" value="<?= $e($v['hmo_plan']) ?>">
        </div>
      </div>

      <div class="f-sec">Patient Information</div>
      <div class="f-grid">
        <div>
          <label class="f-lbl">Patient Name</label>
          <input type="text" value="<?= $e($p['full_name']) ?>" readonly>
        </div>
        <div>
          <label class="f-lbl">Date of Birth</label>
          <input type="text" value="<?= !empty($p['date_of_birth']) ? $e((new DateTime($p['date_of_birth']))->format('M j, Y')) : '' ?>" readonly>
        </div>
        <div>
          <label class="f-lbl" for="contact">Contact Number <span class="req">*</span></label>
          <input type="tel" id="contact" name="contact_number" placeholder="09XX XXX XXXX" value="<?= $e($v['contact_number']) ?>">
        </div>
      </div>

      <div class="f-sec">Authorization / Coverage</div>
      <div class="f-grid">
        <div>
          <label class="f-lbl" for="loa">LOA / Authorization No. <span style="font-weight:400;color:var(--muted)">(if available)</span></label>
          <input type="text" id="loa" name="loa_number" maxlength="60" placeholder="Enter LOA number" value="<?= $e($v['loa_number']) ?>">
        </div>
        <div>
          <label class="f-lbl">Coverage Status</label>
          <input type="text" value="Pending verification" readonly>
          <div class="f-hint">Your HMO coverage is confirmed after the request is reviewed.</div>
        </div>
      </div>

      <div class="f-sec">Consultation Information</div>
      <div class="f-grid">
        <div>
          <label class="f-lbl">Consultation Date</label>
          <input type="text" readonly value="<?= $e((new DateTime($b['appt_date']))->format('M j, Y')) ?> at <?= $e(date('g:i A', strtotime($b['appt_time']))) ?>">
        </div>
        <div>
          <label class="f-lbl">Doctor</label>
          <input type="text" readonly value="Dr. <?= $e($doctor['full_name']) ?>">
        </div>
        <div class="f-full">
          <label class="f-lbl">Chief Complaint</label>
          <input type="text" readonly value="<?= $e($reason_display) ?>">
        </div>
        <div class="f-full">
          <label class="f-lbl">Service <span class="req">*</span></label>
          <div class="f-choice">
            <label><input type="radio" name="service_type" value="Online Consultation"    <?= $v['service_type'] === 'Online Consultation'    ? 'checked' : '' ?>> Online Consultation</label>
            <label><input type="radio" name="service_type" value="Follow-up Consultation" <?= $v['service_type'] === 'Follow-up Consultation' ? 'checked' : '' ?>> Follow-up Consultation</label>
          </div>
        </div>
      </div>

      <div class="f-note" style="margin-top:1.1rem;">
        Diagnosis, prescription, supporting documents, HMO coverage amount, your share (co-payment) and the claim
        reference are added by the clinic after the consultation.
      </div>

      <label class="f-consent">
        <input type="checkbox" name="consent" value="1" <?= $v['consent'] ? 'checked' : '' ?>>
        <span>I confirm that the information provided is accurate and authorize processing and sharing of my information for HMO purposes.</span>
      </label>
    </div>

    <div class="wiz-actions">
      <a href="router.php?page=booking/step4_payment_method" class="wiz-btn ghost">Back</a>
      <button type="submit" class="wiz-btn primary">Continue to Review</button>
    </div>
  </form>
</div>

<script>
document.getElementById('prov').addEventListener('change', function () {
  document.getElementById('otherWrap').style.display = this.value === 'Other' ? '' : 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
