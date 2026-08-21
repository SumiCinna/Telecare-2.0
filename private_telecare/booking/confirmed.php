<?php
// private_telecare/booking/confirmed.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);

// ── Cancel action ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $c = $conn->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND patient_id=?");
    $c->bind_param("ii", $appt_id, $patient_id);
    $c->execute();
    $_SESSION['toast'] = 'Appointment cancelled.';
    header('Location: ../visits.php'); exit;
}

$stmt = $conn->prepare(
    "SELECT a.*, d.full_name AS doctor_name, d.specialty, d.profile_photo, d.consultation_fee
     FROM appointments a JOIN doctors d ON d.id = a.doctor_id
     WHERE a.id=? AND a.patient_id=?"
);
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) { header('Location: ../visits.php'); exit; }

$initials = strtoupper(substr($appt['doctor_name'],0,1).(strpos($appt['doctor_name'],' ')!==false ? substr($appt['doctor_name'],strpos($appt['doctor_name'],' ')+1,1) : ''));
$apptTs   = strtotime($appt['appointment_date'].' '.$appt['appointment_time']);
$canJoin  = time() >= ($apptTs - 900) && time() <= ($apptTs + 3600);

$page_title = 'Appointment Details — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.conf-page{max-width:1000px;margin:0 auto;padding:1.8rem 2rem 5rem}
.conf-banner{background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:18px;padding:1.4rem 1.6rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.4rem}
.conf-banner-left{display:flex;align-items:center;gap:0.9rem}
.conf-icon{width:48px;height:48px;border-radius:50%;background:rgba(34,197,94,0.12);color:#16a34a;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.conf-title{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:900;color:var(--green)}
.conf-sub{font-size:0.82rem;color:var(--muted)}
.ref-box{background:rgba(63,130,227,0.08);border-radius:12px;padding:0.6rem 1rem;text-align:center}
.ref-label{font-size:0.65rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--blue)}
.ref-val{font-family:'Playfair Display',serif;font-weight:900;color:var(--green)}
.conf-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:1.2rem}
.info-row{display:flex;align-items:flex-start;gap:0.6rem;padding:0.5rem 0;font-size:0.87rem}
.info-row svg{width:16px;height:16px;color:var(--muted);flex-shrink:0;margin-top:2px}
.info-lbl{color:var(--muted);font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em}
.info-v{color:var(--green);font-weight:600}
.join-box{background:rgba(34,197,94,0.06);border:1px dashed rgba(34,197,94,0.35);border-radius:14px;padding:1.4rem;text-align:center;color:var(--muted);font-size:0.85rem;margin-top:1rem}
.doc-card{text-align:center}
.doc-avatar{width:74px;height:74px;border-radius:18px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.4rem;margin:0 auto 0.7rem;overflow:hidden}
.doc-avatar img{width:100%;height:100%;object-fit:cover}
.pay-row{display:flex;justify-content:space-between;font-size:0.85rem;padding:0.4rem 0;border-bottom:1px solid rgba(36,68,65,0.06)}
.pay-row:last-child{border-bottom:none}
.conf-actions{display:flex;justify-content:space-between;margin-top:1.4rem;flex-wrap:wrap;gap:0.6rem}
@media(max-width:800px){.conf-grid{grid-template-columns:1fr}}
</style>

<div class="conf-page">
  <div class="conf-banner">
    <div class="conf-banner-left">
      <div class="conf-icon">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
      </div>
      <div>
        <div class="conf-title"><?= $appt['status'] === 'Cancelled' ? 'Appointment Cancelled' : 'Appointment Confirmed' ?></div>
        <div class="conf-sub"><?= $appt['payment_status']==='Paid' ? 'Your appointment has been successfully scheduled.' : 'Confirmed — payment still pending.' ?></div>
      </div>
    </div>
    <div class="ref-box">
      <div class="ref-label">Reference</div>
      <div class="ref-val"><?= htmlspecialchars($appt['reference_no'] ?: ('APT-' . $appt['id'])) ?></div>
    </div>
  </div>

  <div class="conf-grid">
    <div class="wiz-card">
      <h3>Appointment Information</h3>
      <div class="conf-grid" style="grid-template-columns:1fr 1fr;gap:0;">
        <div class="info-row"><div><div class="info-lbl">Doctor</div><div class="info-v">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div></div></div>
        <div class="info-row"><div><div class="info-lbl">Department</div><div class="info-v"><?= htmlspecialchars($appt['department'] ?: $appt['specialty']) ?></div></div></div>
        <div class="info-row"><div><div class="info-lbl">Date</div><div class="info-v"><?= (new DateTime($appt['appointment_date']))->format('F j, Y') ?></div></div></div>
        <div class="info-row"><div><div class="info-lbl">Time</div><div class="info-v"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></div></div></div>
        <div class="info-row"><div><div class="info-lbl">Type</div><div class="info-v">Online Consultation</div></div></div>
        <div class="info-row"><div><div class="info-lbl">Payment Status</div><div class="info-v"><?= htmlspecialchars($appt['payment_status']) ?></div></div></div>
      </div>
      <?php if (!empty($appt['reason'])): ?>
        <div class="info-lbl" style="margin-top:0.8rem;">Reason for Consultation</div>
        <div class="info-v" style="font-weight:500;"><?= htmlspecialchars($appt['reason']) ?></div>
      <?php endif; ?>

      <div class="join-box">
        <?php if ($appt['status']==='Cancelled'): ?>
          This appointment was cancelled.
        <?php elseif ($appt['payment_status'] !== 'Paid'): ?>
          Complete payment to unlock your consultation link.
        <?php elseif ($canJoin): ?>
          <a href="../router.php?page=call_patient&appt_id=<?= $appt_id ?>" class="wiz-btn primary">Join Consultation</a>
        <?php else: ?>
          Video link will be available here — opens 15 minutes before your scheduled time.
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="wiz-card doc-card">
        <div class="doc-avatar">
          <?php if (!empty($appt['profile_photo'])): ?><img src="../../<?= htmlspecialchars($appt['profile_photo']) ?>"/><?php else: echo $initials; endif; ?>
        </div>
        <div style="font-weight:700;color:var(--green);">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
        <div style="font-size:0.8rem;color:var(--red);font-weight:600;margin-bottom:0.8rem;"><?= htmlspecialchars($appt['specialty'] ?? '') ?></div>
      </div>
      <div class="wiz-card">
        <h3>Payment Summary</h3>
        <div class="pay-row"><span>Consultation Fee</span><span>&#8369;<?= number_format((float)$appt['consultation_fee'], 2) ?></span></div>
        <div class="pay-row"><span>Payment Status</span><span><?= htmlspecialchars($appt['payment_status']) ?></span></div>
      </div>
    </div>
  </div>

  <div class="conf-actions">
    <a href="../visits.php" class="wiz-btn ghost">&larr; Back to Appointments</a>
    <div style="display:flex;gap:0.6rem;">
      <?php if ($appt['status'] !== 'Cancelled' && $appt['payment_status'] !== 'Paid'): ?>
        <a href="payment.php?appt_id=<?= $appt_id ?>" class="wiz-btn primary">Pay Now</a>
      <?php endif; ?>
      <?php if ($appt['status'] !== 'Cancelled'): ?>
        <form method="POST" onsubmit="return confirm('Cancel this appointment?');">
          <button type="submit" name="cancel_appointment" class="wiz-btn ghost" style="border-color:rgba(195,54,67,0.3);color:var(--red);">Cancel Appointment</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>