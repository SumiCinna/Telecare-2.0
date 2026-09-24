<?php
// doctor/review_summary.php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$appt_id = (int)($_GET['appt_id'] ?? $_POST['appt_id'] ?? 0);
$success = null;
$error   = null;

if (!$appt_id) { header('Location: appointments.php'); exit; }

// ── Handle Save Draft / Confirm & Publish ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action'] ?? '';
    $summary_text = trim($_POST['summary_text'] ?? '');

    if ($summary_text === '') {
        $error = 'Summary text cannot be empty.';
    } else {
        // Confirm the doctor owns this appointment before writing to it
        $chk = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ?");
        $chk->bind_param('ii', $appt_id, $doctor_id);
        $chk->execute();
        $owns = $chk->get_result()->fetch_assoc();

        if (!$owns) {
            $error = 'Appointment not found.';
        } elseif ($action === 'publish') {
            $stmt = $conn->prepare("
                UPDATE appointments
                SET consultation_summary = ?,
                    summary_edited       = 1,
                    summary_reviewed_at  = NOW()
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->bind_param('sii', $summary_text, $appt_id, $doctor_id);
            $stmt->execute();
            $success = 'Summary published — the patient can now view it.';
        } elseif ($action === 'save_draft') {
            $stmt = $conn->prepare("
                UPDATE appointments
                SET consultation_summary = ?,
                    summary_edited       = 1
                WHERE id = ? AND doctor_id = ?
            ");
            $stmt->bind_param('sii', $summary_text, $appt_id, $doctor_id);
            $stmt->execute();
            $success = 'Draft saved.';
        }
    }
}

// ── Fetch current appointment + summary ──
$stmt = $conn->prepare("
    SELECT a.*, p.full_name AS patient_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE a.id = ? AND a.doctor_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $appt_id, $doctor_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) { header('Location: appointments.php'); exit; }

$page_title = 'Review Summary — TELE-CARE';
$active_nav = 'appointments';
require_once 'includes/header.php';
?>

<div class="page">
  <?php if ($success): ?><div class="alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <div class="section-label">Consultation Summary Review</div>
    <div style="font-size:0.84rem;color:var(--muted);margin-bottom:0.8rem;line-height:1.6;">
      Patient: <strong style="color:var(--green)"><?= htmlspecialchars($appt['patient_name']) ?></strong><br>
      Schedule: <?= date('F j, Y', strtotime($appt['appointment_date'])) ?> · <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
    </div>
    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.22);color:#92400e;border-radius:12px;padding:0.65rem 0.8rem;font-size:0.78rem;margin-bottom:0.9rem;">
      Review carefully. You may edit any section before publishing to the patient side.
    </div>

    <form method="POST">
      <input type="hidden" name="appt_id" value="<?= $appt_id ?>">
      <div class="form-field">
        <label class="field-label">Editable Summary Text</label>
        <textarea class="field-input" name="summary_text" rows="16" required style="font-family:'DM Sans',sans-serif;line-height:1.55;"><?= htmlspecialchars($appt['consultation_summary'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.4rem;">
        <button type="submit" name="action" value="save_draft" class="btn-submit" style="flex:1;min-width:180px;background:var(--blue);">Save Draft</button>
        <button type="submit" name="action" value="publish" class="btn-submit" style="flex:1;min-width:180px;">Confirm & Publish</button>
      </div>
    </form>

    <a href="appointments.php" style="display:inline-flex;margin-top:0.9rem;color:var(--muted);text-decoration:none;font-weight:600;font-size:0.82rem;">← Back to Appointments</a>
  </div>
</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>