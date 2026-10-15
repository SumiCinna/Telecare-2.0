<?php
// private_telecare/booking/hmo_documents.php — Page 3: HMO documents upload (right after the HMO booking is saved)
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/hmo_patient_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? $_POST['appt_id'] ?? 0);
$case = hmo_patient_ensure_schema($conn) ? hmo_patient_case($conn, $appt_id, $patient_id) : null;
if (!$case) { $_SESSION['toast_error'] = 'HMO booking not found.'; header('Location: router.php?page=visits'); exit; }
if ($case['appt_status'] === 'Cancelled') { header('Location: router.php?page=booking/confirmed&appt_id=' . $appt_id); exit; }

$locked = $case['coverage_status'] === 'Approved';   // nothing to change once the clinic accepted it
$errors = []; $notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hmo_pat_csrf_ok()) { $errors[] = 'Your session expired. Please try again.'; }
    elseif ($locked)        { $errors[] = 'This booking is already approved, so documents can no longer be changed.'; }
    else {
        $act = (string)($_POST['action'] ?? '');
        if ($act === 'upload') {
            $r = hmo_save_uploads($conn, $appt_id, (string)($_POST['doc_type'] ?? ''), 'files');
            $errors = $r['errors'];
            if ($r['saved'] > 0) $notice = $r['saved'] . ' document' . ($r['saved'] > 1 ? 's' : '') . ' uploaded.';
        } elseif ($act === 'delete') {
            $did = (int)($_POST['doc_id'] ?? 0);
            $q = $conn->prepare("SELECT stored_name FROM appointment_hmo_docs WHERE id = ? AND appointment_id = ? AND uploaded_by = 'patient'");
            $q->bind_param('ii', $did, $appt_id); $q->execute();
            if ($row = $q->get_result()->fetch_assoc()) {
                $dl = $conn->prepare("DELETE FROM appointment_hmo_docs WHERE id = ? AND appointment_id = ?");
                $dl->bind_param('ii', $did, $appt_id); $dl->execute();
                @unlink(hmo_docs_dir() . '/' . basename($row['stored_name']));
                hmo_patient_event($conn, $appt_id, 'Patient removed a document.');
                $notice = 'Document removed.';
            }
        } elseif ($act === 'assist') {
            $want = !empty($_POST['assist']) ? 1 : 0;
            $note = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST['assist_note'] ?? ''))), 0, 255);
            $noteN = $note !== '' ? $note : null;
            $u = $conn->prepare("UPDATE appointment_hmo SET assistance_requested = ?, assistance_note = ? WHERE appointment_id = ?");
            $u->bind_param('isi', $want, $noteN, $appt_id); $u->execute();
            if ($want) hmo_patient_event($conn, $appt_id, 'Patient asked the clinic to help get the LOA / authorization.', $noteN);
            $notice = $want ? 'We told the clinic you need help with the authorization.' : 'Help request removed.';
        } elseif ($act === 'continue') {
            header('Location: router.php?page=booking/hmo_review&appt_id=' . $appt_id); exit;
        }
        $case = hmo_patient_case($conn, $appt_id, $patient_id);
    }
}

$docs = hmo_docs_for($conn, $appt_id);
$hasLoa = trim((string)$case['loa_number']) !== '';
$csrf = hmo_pat_csrf();
$e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);

$page_title = 'HMO Documents — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
echo hmo_patient_css();
?>
<div class="hp-wrap">
  <div class="wiz-title">HMO Documents</div>
  <div class="wiz-sub">Booking <?= $e($case['reference_no']) ?> &middot; <?= $e($case['hmo_provider']) ?></div>
  <div class="hp-steps"><span>1 Booking saved ✓</span><span class="on">2 Documents</span><span>3 Review &amp; Submit</span><span>4 Status</span></div>

  <?php foreach ($errors as $m): ?><div class="hp-flash no"><?= $e($m) ?></div><?php endforeach; ?>
  <?php if ($notice): ?><div class="hp-flash ok"><?= $e($notice) ?></div><?php endif; ?>
  <?php if ($locked): ?><div class="hp-flash ok">Your HMO booking is approved. Documents are locked.</div><?php endif; ?>

  <div class="hp-card">
    <h3>What the clinic needs</h3>
    <ul class="hp-muted" style="margin:0 0 0 1rem;padding:0">
      <li><b>HMO card / membership proof</b> (front and back) — required</li>
      <li><b>Valid ID</b>, if your HMO asks for it</li>
      <li><b>LOA / LOG / eLOA</b> — <?= $hasLoa ? 'you entered an LOA number, so please upload the LOA itself. The clinic checks it and accepts it; it does not need to ask your HMO again' : "if your HMO already gave you one. If you don't have it yet, the clinic asks your HMO for approval" ?></li>
      <li><b>Doctor's request / referral</b>, if your HMO needs one</li>
    </ul>
    <p class="hp-muted" style="margin:.6rem 0 0">JPG, PNG, WEBP or PDF, up to 5 MB each. Only you and the clinic staff can open them.</p>
  </div>

  <?php if (!$locked): ?>
  <div class="hp-card">
    <h3>Upload a document</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="upload"/>
      <label class="hp-lbl" for="dt">Type of document</label>
      <select class="hp-in" id="dt" name="doc_type" required>
        <?php foreach (HMO_DOC_TYPES as $k => $lbl): ?><option value="<?= $e($k) ?>"><?= $e($lbl) ?></option><?php endforeach; ?>
      </select>
      <label class="hp-lbl" for="fl">File(s)</label>
      <input class="hp-in" id="fl" type="file" name="files[]" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" multiple required/>
      <div class="hp-actions"><button class="wiz-btn primary" type="submit">Upload</button></div>
    </form>
  </div>
  <?php endif; ?>

  <div class="hp-card">
    <h3>Uploaded (<?= count($docs) ?>)</h3>
    <?php if (!$docs): ?><div class="hp-muted">Nothing uploaded yet.</div><?php else: ?>
    <ul class="hp-list">
      <?php foreach ($docs as $d): ?>
      <li>
        <span><b><?= $e(HMO_DOC_TYPES[$d['doc_type']] ?? 'Document') ?></b><br/><span class="hp-muted"><?= $e($d['original_name']) ?> &middot; <?= $e(number_format($d['size_bytes'] / 1024, 0)) ?> KB</span></span>
        <span style="display:flex;gap:.8rem;align-items:center">
          <a href="router.php?page=booking/hmo_file&amp;id=<?= (int)$d['id'] ?>" target="_blank" rel="noopener">View</a>
          <?php if (!$locked && $d['uploaded_by'] === 'patient'): ?>
          <form method="POST" style="margin:0" onsubmit="return confirm('Remove this document?')">
            <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="delete"/><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>"/>
            <button type="submit" style="background:none;border:0;color:var(--red);font-weight:700;cursor:pointer;font-size:.78rem">Remove</button>
          </form>
          <?php endif; ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <?php if (!$locked && !$hasLoa): ?>
  <div class="hp-card">
    <h3>No LOA yet?</h3>
    <p class="hp-muted">If your HMO has not given you an authorization yet, tick this and the clinic will help request it for you.</p>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="assist"/>
      <label style="font-size:.82rem"><input type="checkbox" name="assist" value="1" <?= !empty($case['assistance_requested']) ? 'checked' : '' ?>/> Please help me get the LOA / authorization</label>
      <label class="hp-lbl" for="an">Anything the clinic should know (optional)</label>
      <input class="hp-in" id="an" name="assist_note" maxlength="255" value="<?= $e($case['assistance_note'] ?? '') ?>"/>
      <div class="hp-actions"><button class="wiz-btn ghost" type="submit">Save</button></div>
    </form>
  </div>
  <?php endif; ?>

  <form method="POST" class="hp-actions">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/><input type="hidden" name="appt_id" value="<?= $appt_id ?>"/><input type="hidden" name="action" value="continue"/>
    <button class="wiz-btn primary" type="submit">Continue to review</button>
    <a class="wiz-btn ghost" href="router.php?page=booking/hmo_status&amp;appt_id=<?= $appt_id ?>">Skip for now</a>
  </form>
</div>
<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
