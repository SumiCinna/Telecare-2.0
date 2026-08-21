<?php
// private_telecare/booking/step4_review.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department', 'doctor_id', 'appt_date', 'appt_time']);
$b = $_SESSION['booking'];

$doctor_id = (int)$b['doctor_id'];
$dstmt = $conn->prepare("SELECT * FROM doctors WHERE id=?");
$dstmt->bind_param("i", $doctor_id);
$dstmt->execute();
$doctor = $dstmt->get_result()->fetch_assoc();
if (!$doctor) { header('Location: step2_doctor.php'); exit; }

$reason_parts = $b['reasons'] ?? [];
if (!empty($b['reason_other'])) $reason_parts[] = $b['reason_other'];
$reason_display = $reason_parts ? implode(', ', $reason_parts) : 'Not specified';

$fee   = (float)($doctor['consultation_fee'] ?? 0);
$total = $fee; // extend here if you add a platform/service fee later

$page_title = 'Review Appointment — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.review-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:1.2rem}
.info-row{display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid rgba(36,68,65,0.06);font-size:0.87rem}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--muted);font-weight:600}
.info-val{color:var(--green);font-weight:700;text-align:right}
.reason-box{background:rgba(36,68,65,0.04);border-radius:10px;padding:0.8rem 1rem;font-size:0.85rem;color:var(--green);margin-top:0.6rem}
.bill-row{display:flex;justify-content:space-between;font-size:0.87rem;padding:0.4rem 0}
.bill-total{display:flex;justify-content:space-between;font-family:'Playfair Display',serif;font-weight:900;font-size:1.3rem;color:var(--red);border-top:1px solid rgba(36,68,65,0.1);margin-top:0.6rem;padding-top:0.7rem}
@media(max-width:800px){.review-grid{grid-template-columns:1fr}}
</style>

<div class="wiz-page">
  <div class="wiz-title">Review Appointment</div>
  <div class="wiz-sub">Check everything before you proceed to payment.</div>

  <?php render_stepper(4); ?>

  <div class="review-grid">
    <div class="wiz-card">
      <h3>Appointment Summary</h3>
      <div class="info-row"><span class="info-label">Department</span><span class="info-val"><?= htmlspecialchars($b['department']) ?></span></div>
      <div class="info-row"><span class="info-label">Doctor</span><span class="info-val">Dr. <?= htmlspecialchars($doctor['full_name']) ?></span></div>
      <div class="info-row"><span class="info-label">Consultation Type</span><span class="info-val">Teleconsultation (Video)</span></div>
      <div class="info-row"><span class="info-label">Date</span><span class="info-val"><?= (new DateTime($b['appt_date']))->format('F j, Y') ?></span></div>
      <div class="info-row"><span class="info-label">Time</span><span class="info-val"><?= date('g:i A', strtotime($b['appt_time'])) ?></span></div>
      <h3 style="margin-top:1.2rem;">Reason for Consultation</h3>
      <div class="reason-box"><?= htmlspecialchars($reason_display) ?></div>
      <?php if (!empty($b['attachment_path'])): ?>
        <div class="reason-box" style="margin-top:0.6rem;">📎 Document attached (scanned, on file for the doctor).</div>
      <?php endif; ?>
    </div>

    <div class="wiz-card">
      <h3>Billing Summary</h3>
      <div class="bill-row"><span>Consultation Fee</span><span><?= '&#8369;' . number_format($fee, 2) ?></span></div>
      <div class="bill-total"><span>Total Due</span><span><?= '&#8369;' . number_format($total, 2) ?></span></div>
      <form method="POST" action="router.php?page=booking/process_booking" style="margin-top:1.2rem;">
        <button type="submit" class="wiz-btn primary" style="width:100%;text-align:center;">Confirm &amp; Proceed to Payment &rarr;</button>
      </form>
      <a href="router.php?page=booking/step3_schedule" class="wiz-btn ghost" style="width:100%;text-align:center;box-sizing:border-box;margin-top:0.6rem;">&larr; Back to Schedule</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>