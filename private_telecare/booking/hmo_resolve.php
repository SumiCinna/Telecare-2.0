<?php
// private_telecare/booking/hmo_resolve.php — Page 6: Resolve HMO issues
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/hmo_patient_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? $_POST['appt_id'] ?? 0);
$case = hmo_patient_ensure_schema($conn) ? hmo_patient_case($conn, $appt_id, $patient_id) : null;
if (!$case) { $_SESSION['toast_error'] = 'HMO booking not found.'; header('Location: router.php?page=visits'); exit; }
if ($case['appt_status'] === 'Cancelled') { header('Location: router.php?page=booking/confirmed&appt_id=' . $appt_id); exit; }

$s = (string)$case['coverage_status'];
$locked = ($s === 'Approved');
$holds = booking_hmo_holds($conn);
$provMap = booking_hmo_providers($conn);
$errors = []; $notice = '';
$self = fn($m) => $m;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    if (!hmo_pat_csrf_ok()) { $errors[] = 'Your session expired. Please try again.'; }
    elseif ($locked)        { $errors[] = 'This booking is already approved, so it can no longer be changed.'; }
    else switch ($act) {

    case 'save_info': {
        $sel   = (string)($_POST['hmo_provider'] ?? '');
        $other = booking_clean($_POST['hmo_provider_other'] ?? '', 90);
        $prov  = ($sel === 'Other') ? 'Other: ' . $other : $sel;
        $mid   = booking_clean($_POST['hmo_member_id'] ?? '', 60);
        $pri   = booking_clean($_POST['principal_member_name'] ?? '', 150);
        $comp  = booking_clean($_POST['company_employer'] ?? '', 150);
        $plan  = booking_clean($_POST['hmo_plan'] ?? '', 100);
        $loa   = booking_clean($_POST['loa_number'] ?? '', 60);
        $mtype = (string)($_POST['member_type'] ?? '');
        $contact = booking_clean($_POST['contact_number'] ?? '', 20);
        if (!in_array($sel, booking_hmo_provider_list($conn), true)) $errors[] = 'Select your HMO provider.';
        if ($sel === 'Other' && $other === '') $errors[] = 'Enter the name of your HMO provider.';
        if ($prov !== $case['hmo_provider'] && isset($holds[$sel])) $errors[] = $sel . ' is not accepted by the clinic at this time.';
        if ($mid === '') $errors[] = 'HMO Member ID is required.';
        if ($pri === '') $errors[] = 'Principal member name is required.';
        if (!in_array($mtype, ['Member', 'Dependent'], true)) $errors[] = 'Select Member or Dependent.';
        if (!booking_valid_phone($contact)) $errors[] = 'Enter a valid contact number.';
        if ($errors) break;
        $compN = $comp !== '' ? $comp : null; $planN = $plan !== '' ? $plan : null; $loaN = $loa !== '' ? $loa : null;
        // A correction after "Action Required" or "Not Approved" sends the case back to the clinic for a fresh check.
        $reopen = in_array($s, ['Action Required', 'Not Approved', 'Not Covered'], true);
        $newStatus = $reopen ? 'Pending' : $s;
        $u = $conn->prepare("UPDATE appointment_hmo SET hmo_provider=?, hmo_member_id=?, principal_member_name=?, company_employer=?, hmo_plan=?, loa_number=?,
              member_type=?, contact_number=?, coverage_status=?, patient_updated_at=NOW(), status_updated_at=IF(?, NOW(), status_updated_at),
              decision_reason=IF(?, NULL, decision_reason) WHERE appointment_id=?");
        $ri = $reopen ? 1 : 0;
        $u->bind_param('sssssssssiii', $prov, $mid, $pri, $compN, $planN, $loaN, $mtype, $contact, $newStatus, $ri, $ri, $appt_id);
        $u->execute();
        $chg = [];
        foreach (['hmo_provider' => [$prov, 'HMO'], 'hmo_member_id' => [$mid, 'Member ID'], 'principal_member_name' => [$pri, 'Principal member'],
                  'loa_number' => [(string)$loaN, 'LOA no.']] as $col => [$new, $lbl]) {
            if ((string)$case[$col] !== (string)$new) $chg[] = $lbl . ': ' . ($case[$col] !== '' ? $case[$col] : '—') . ' → ' . ($new !== '' ? $new : '—');
        }
        hmo_patient_event($conn, $appt_id, 'Patient corrected their HMO details.' . ($reopen ? ' Sent back for verification.' : ''), $chg ? implode("\n", $chg) : 'No visible field changes.');
        booking_notify_patient($conn, $patient_id, $appt_id, 'hmo_pending', $loaN ? 'LOA received' : 'Waiting for HMO approval',
            'Your corrected HMO details were sent to the clinic.');
        $notice = 'Details saved and sent to the clinic.';
        break;
    }

    case 'upload': {
        $r = hmo_save_uploads($conn, $appt_id, (string)($_POST['doc_type'] ?? ''), 'files');
        $errors = $r['errors'];
        if ($r['saved'] > 0) { $notice = $r['saved'] . ' document' . ($r['saved'] > 1 ? 's' : '') . ' uploaded.';
            $conn->query("UPDATE appointment_hmo SET patient_updated_at = NOW() WHERE appointment_id = " . (int)$appt_id); }
        break;
    }

    case 'resubmit': {
        // "I'm done": hand an Action Required case back to the clinic.
        if ($s === 'Action Required') {
            $u = $conn->prepare("UPDATE appointment_hmo SET coverage_status = 'In Verification', status_updated_at = NOW(), patient_updated_at = NOW() WHERE appointment_id = ?");
            $u->bind_param('i', $appt_id); $u->execute();
            hmo_patient_event($conn, $appt_id, 'Patient says they did what was asked and sent the case back.');
            $notice = 'Sent back to the clinic.';
        }
        break;
    }

    case 'assist': {
        $note = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST['assist_note'] ?? ''))), 0, 255);
        $noteN = $note !== '' ? $note : null;
        $u = $conn->prepare("UPDATE appointment_hmo SET assistance_requested = 1, assistance_note = ?, patient_updated_at = NOW() WHERE appointment_id = ?");
        $u->bind_param('si', $noteN, $appt_id); $u->execute();
        hmo_patient_event($conn, $appt_id, 'Patient asked the clinic to help get the LOA / authorization.', $noteN);
        $notice = 'The clinic knows you need help.';
        break;
    }

    case 'selfpay': {
        if (empty($_POST['accept_selfpay'])) { $errors[] = 'Please tick the box to confirm you will pay the full fee yourself.'; break; }
        if (!in_array($s, ['Not Approved', 'Not Covered'], true) && !isset($holds[$case['hmo_provider']])) { $errors[] = 'Self-pay is only offered when the HMO was not approved or is not accepted.'; break; }
        try {
            $conn->begin_transaction();
            $u = $conn->prepare("UPDATE appointments SET status='Pending', payment_status='Unpaid', payment_method='Regular', created_at=NOW()
                                 WHERE id=? AND patient_id=? AND status NOT IN ('Cancelled','Completed')");
            $u->bind_param('ii', $appt_id, $patient_id); $u->execute();
            if ($u->affected_rows !== 1) throw new RuntimeException('closed');
            hmo_patient_event($conn, $appt_id, 'Patient chose self-pay instead of ' . $case['hmo_provider'] . '.', 'Patient accepted paying the full consultation fee. HMO case closed.');
            $d = $conn->prepare("DELETE FROM appointment_hmo WHERE appointment_id = ?");
            $d->bind_param('i', $appt_id); $d->execute();
            $conn->commit();
        } catch (Throwable $ex) { try { $conn->rollback(); } catch (Throwable $i) {} $errors[] = 'Could not switch to self-pay. Please contact the clinic.'; break; }
        header('Location: router.php?page=pay&appt_id=' . $appt_id); exit;
    }
    }
    $case = hmo_patient_case($conn, $appt_id, $patient_id) ?: $case;
    $s = (string)$case['coverage_status'];
}

$docs = hmo_docs_for($conn, $appt_id);
$onHold = isset($holds[$case['hmo_provider']]);
$hasLoa = trim((string)$case['loa_number']) !== '';
$csrf = hmo_pat_csrf();
$e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
$known = isset($provMap[$case['hmo_provider']]);
$selVal = $known ? $case['hmo_provider'] : 'Other';
$otherVal = $known ? '' : preg_replace('/^Other:\s*/', '', (string)$case['hmo_provider']);
$offerSelfPay = in_array($s, ['Not Approved', 'Not Covered'], true) || $onHold;

$page_title = 'Resolve HMO Issues — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
echo hmo_patient_css();
?>
<div class="hp-wrap">
  <div class="wiz-title">Resolve HMO Issues</div>
  <div class="wiz-sub">Booking <?= $e($case['reference_no']) ?> &middot; <?= $e($case['hmo_provider']) ?></div>

  <?php foreach ($errors as $m): ?><div class="hp-flash no"><?= $e($m) ?></div><?php endforeach; ?>
  <?php if ($notice): ?><div class="hp-flash ok"><?= $e($notice) ?></div><?php endif; ?>
  <?php if ($locked): ?><div class="hp-flash ok">This booking is approved. Nothing to resolve.</div><?php endif; ?>
  <?php if ($s === 'Action Required' && $case['staff_instructions']): ?>
    <div class="hp-flash wait"><b>The clinic asked:</b><br/><?= nl2br($e($case['staff_instructions'])) ?></div>
  <?php elseif (in_array($s, ['Not Approved', 'Not Covered'], true)): ?>
    <div class="hp-flash no"><b>Not approved.</b> <?= $e($case['decision_reason'] ?: '') ?></div>
  <?php endif; ?>
  <?php if ($onHold && !$locked): ?>
    <div class="hp-flash no"><b>The clinic is not accepting <?= $e($case['hmo_provider']) ?> right now.</b> You can switch to self-pay below, or cancel.</div>
  <?php endif; ?>

  <?php if (!$locked): ?>
  <div class="hp-card" id="edit">
    <h3>1. Correct your member information</h3>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="save_info"/>
      <label class="hp-lbl" for="rp">HMO provider</label>
      <select class="hp-in" id="rp" name="hmo_provider" onchange="document.getElementById('ro').style.display=this.value==='Other'?'block':'none'">
        <?php foreach (array_keys($provMap) as $pn): $held = isset($holds[$pn]) && $pn !== $case['hmo_provider']; ?>
          <option value="<?= $e($pn) ?>" <?= $selVal === $pn ? 'selected' : '' ?> <?= $held ? 'disabled' : '' ?>><?= $e($pn) ?><?= $held ? ' — not accepted right now' : '' ?></option>
        <?php endforeach; ?>
        <option value="Other" <?= $selVal === 'Other' ? 'selected' : '' ?>>Other</option>
      </select>
      <div id="ro" style="display:<?= $selVal === 'Other' ? 'block' : 'none' ?>"><label class="hp-lbl">HMO name</label>
        <input class="hp-in" name="hmo_provider_other" maxlength="90" value="<?= $e($otherVal) ?>"/></div>
      <div class="hp-kv" style="margin-top:.2rem">
        <div><label class="hp-lbl">Member ID / card no.</label><input class="hp-in" name="hmo_member_id" maxlength="60" value="<?= $e($case['hmo_member_id']) ?>" required/></div>
        <div><label class="hp-lbl">Member type</label><select class="hp-in" name="member_type"><?php foreach (['Member', 'Dependent'] as $mt): ?><option <?= $case['member_type'] === $mt ? 'selected' : '' ?>><?= $mt ?></option><?php endforeach; ?></select></div>
        <div><label class="hp-lbl">Principal member name</label><input class="hp-in" name="principal_member_name" maxlength="150" value="<?= $e($case['principal_member_name']) ?>" required/></div>
        <div><label class="hp-lbl">Company / employer</label><input class="hp-in" name="company_employer" maxlength="150" value="<?= $e($case['company_employer']) ?>"/></div>
        <div><label class="hp-lbl">Plan / account type</label><input class="hp-in" name="hmo_plan" maxlength="100" value="<?= $e($case['hmo_plan']) ?>"/></div>
        <div><label class="hp-lbl">LOA / approval no.</label><input class="hp-in" name="loa_number" maxlength="60" value="<?= $e($case['loa_number']) ?>" placeholder="If you have one"/></div>
        <div><label class="hp-lbl">Contact number</label><input class="hp-in" name="contact_number" maxlength="20" value="<?= $e($case['contact_number']) ?>" required/></div>
      </div>
      <div class="hp-actions"><button class="wiz-btn primary" type="submit">Save &amp; send to clinic</button></div>
    </form>
  </div>

  <div class="hp-card">
    <h3>2. Upload missing documents</h3>
    <?php if ($docs): ?><ul class="hp-list"><?php foreach ($docs as $d): ?>
      <li><span><b><?= $e(HMO_DOC_TYPES[$d['doc_type']] ?? 'Document') ?></b> <span class="hp-muted">— <?= $e($d['original_name']) ?></span></span>
        <a href="router.php?page=booking/hmo_file&amp;id=<?= (int)$d['id'] ?>" target="_blank" rel="noopener">View</a></li>
    <?php endforeach; ?></ul><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="upload"/>
      <label class="hp-lbl">Type of document</label>
      <select class="hp-in" name="doc_type"><?php foreach (HMO_DOC_TYPES as $k => $lbl): ?><option value="<?= $e($k) ?>"><?= $e($lbl) ?></option><?php endforeach; ?></select>
      <label class="hp-lbl">File(s) — JPG, PNG, WEBP or PDF, 5 MB each</label>
      <input class="hp-in" type="file" name="files[]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" multiple required/>
      <div class="hp-actions"><button class="wiz-btn primary" type="submit">Upload</button></div>
    </form>
    <?php if ($s === 'Action Required'): ?>
    <form method="POST" class="hp-actions" style="margin-top:.8rem">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="resubmit"/>
      <button class="wiz-btn ghost" type="submit">I did what the clinic asked — send back</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="hp-card">
    <h3>3. Getting an LOA</h3>
    <ol class="hp-muted" style="margin:0 0 .6rem 1.1rem;padding:0">
      <li>Contact your HMO (hotline, portal or app) and ask for an authorization / LOA for a teleconsultation at this clinic.</li>
      <li>Give them your member ID, the doctor's name and the appointment date.</li>
      <li>When they send the LOA, enter its number in step 1 and upload it in step 2. The clinic then only has to accept it.</li>
    </ol>
    <?php if (!empty($case['assistance_requested'])): ?>
      <div class="hp-flash info" style="margin:0">You asked the clinic to help with the authorization. They will follow up.</div>
    <?php else: ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="assist"/>
      <label class="hp-lbl">Need help? Tell the clinic (optional)</label>
      <input class="hp-in" name="assist_note" maxlength="255"/>
      <div class="hp-actions"><button class="wiz-btn ghost" type="submit">Ask the clinic to help me get the LOA</button></div>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="hp-card">
    <h3><?= $locked ? '' : '4. ' ?>Contact the clinic, reschedule or cancel</h3>
    <p class="hp-muted">Questions about your HMO booking? Contact the clinic staff through the clinic's usual contact details. To change the time, cancel this booking and book a new schedule.</p>
    <div class="hp-actions">
      <form method="POST" action="router.php?page=booking/confirmed&amp;appt_id=<?= $appt_id ?>" onsubmit="return confirm('Cancel this appointment?')">
        <input type="hidden" name="cancel_appointment" value="1"/>
        <button class="wiz-btn ghost" type="submit">Cancel appointment</button>
      </form>
      <a class="wiz-btn ghost" href="router.php?page=booking/step1_details">Book a new schedule</a>
    </div>
  </div>

  <?php if ($offerSelfPay && !$locked): ?>
  <div class="hp-card">
    <h3>5. Pay by yourself instead</h3>
    <p class="hp-muted">You can keep this appointment and pay the full consultation fee yourself (GCash). Your HMO will not be billed.</p>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="selfpay"/>
      <label style="font-size:.82rem"><input type="checkbox" name="accept_selfpay" value="1"/> I understand I will pay the full consultation fee myself.</label>
      <div class="hp-actions"><button class="wiz-btn primary" type="submit">Continue to payment</button></div>
      <p class="hp-muted">Payment must be completed within 10 minutes or the slot is released.</p>
    </form>
  </div>
  <?php endif; ?>

  <div class="hp-actions"><a class="wiz-btn ghost" href="router.php?page=booking/hmo_status&amp;appt_id=<?= $appt_id ?>">Back to status</a></div>
</div>
<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
