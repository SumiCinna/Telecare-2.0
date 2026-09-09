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
.review-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(230px,.8fr);gap:.85rem;align-items:start}
.review-main{display:grid;gap:.65rem}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem 1.2rem}
.info-row{display:flex;flex-direction:column;gap:.2rem;padding:.2rem 0;font-size:.82rem}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--muted);font-size:.68rem;font-weight:600}
.info-val{color:var(--green);font-weight:700}
.reason-box{background:rgba(36,68,65,0.04);border-radius:8px;padding:.55rem .75rem;font-size:.78rem;color:var(--green);margin-top:.35rem}
.bill-row{display:flex;justify-content:space-between;font-size:.8rem;padding:.3rem 0}
.bill-total{display:flex;justify-content:space-between;font-family:'Playfair Display',serif;font-weight:900;font-size:1.15rem;color:var(--red);border-top:1px solid rgba(36,68,65,0.1);margin-top:.4rem;padding-top:.55rem}
.review-reminder{border-left:3px solid var(--blue);background:#f3f6ff;border-radius:8px;padding:.55rem .75rem;color:var(--green);font-size:.7rem;line-height:1.35}
.review-reminder strong{display:block;margin-bottom:.3rem;font-size:.82rem}
.wiz-page{max-width:1180px;padding:1rem 2rem .5rem}
.wiz-page .wiz-title{font-size:1.55rem}
.wiz-page .wiz-sub{margin:.2rem 0 .75rem;font-size:.78rem}
.wiz-page .stepper{padding:.65rem 1.2rem;margin-bottom:.85rem}
.wiz-page .wiz-card{padding:.9rem 1.1rem;margin-bottom:.65rem}
.wiz-page .wiz-card h3{font-size:.95rem;margin-bottom:.65rem}
.booking-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:.7rem 0 0;padding:.55rem 2rem;border-top:1px solid rgba(36,68,65,.1);color:var(--muted);font-size:.62rem}
.booking-footer-links{display:flex;align-items:center;gap:1rem}
.booking-footer a{color:var(--green);text-decoration:none}
.booking-footer a:hover{text-decoration:underline;color:var(--red)}
@media(max-width:800px){.review-grid{grid-template-columns:1fr}.info-grid{grid-template-columns:1fr}}
@media(max-width:600px){.booking-footer{align-items:flex-start;flex-direction:column;padding-left:1rem;padding-right:1rem}.booking-footer-links{flex-wrap:wrap;gap:.7rem}}
</style>

<div class="wiz-page">
  <div class="wiz-title">Review Appointment</div>
  <div class="wiz-sub">Check everything before you proceed to payment.</div>

  <?php render_stepper(4); ?>

  <div class="review-grid">
    <div class="review-main">
    <div class="wiz-card">
      <h3>Appointment Summary</h3>
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Department</span><span class="info-val"><?= htmlspecialchars($b['department']) ?></span></div>
        <div class="info-row"><span class="info-label">Doctor</span><span class="info-val">Dr. <?= htmlspecialchars($doctor['full_name']) ?></span></div>
        <div class="info-row"><span class="info-label">Consultation Type</span><span class="info-val">Teleconsultation (Video)</span></div>
        <div class="info-row"><span class="info-label">Date &amp; Time</span><span class="info-val"><?= (new DateTime($b['appt_date']))->format('M j, Y') ?> at <?= date('g:i A', strtotime($b['appt_time'])) ?></span></div>
      </div>
      <h3 style="margin-top:1.2rem;">Reason for Consultation</h3>
      <div class="reason-box"><?= htmlspecialchars($reason_display) ?></div>
      <?php if (!empty($b['attachment_path'])): ?>
        <div class="reason-box" style="margin-top:0.6rem;">📎 Document attached (scanned, on file for the doctor).</div>
      <?php endif; ?>
    </div>

    <div class="wiz-card">
      <h3>Patient Information</h3>
      <div class="info-grid">
        <div class="info-row"><span class="info-label">Name</span><span class="info-val"><?= htmlspecialchars($p['full_name']) ?></span></div>
        <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?= htmlspecialchars($p['email']) ?></span></div>
      </div>
    </div>
    <div class="review-reminder"><strong>Important Reminders</strong>Please join the consultation a few minutes before your scheduled time. Your appointment will be created after payment is completed.</div>
    </div>

    <div class="wiz-card">
      <h3>Billing Summary</h3>
      <div class="bill-row"><span>Consultation Fee</span><span><?= '&#8369;' . number_format($fee, 2) ?></span></div>
      <div class="bill-total"><span>Total Due</span><span><?= '&#8369;' . number_format($total, 2) ?></span></div>
      <form method="POST" action="router.php?page=booking/process_booking" style="margin-top:1.2rem;">
        <button type="submit" class="wiz-btn primary" style="width:100%;text-align:center;">Confirm &amp; Proceed to Payment</button>
      </form>
      <a href="router.php?page=booking/step3_schedule" class="wiz-btn ghost" style="width:100%;text-align:center;box-sizing:border-box;margin-top:0.6rem;">Back to Schedule</a>
    </div>
  </div>
</div>

<footer class="booking-footer">
  <span>&copy; <?= date('Y') ?> TELE-CARE. All rights reserved.</span>
  <div class="booking-footer-links">
    <a href="router.php?page=privacy-policy">Privacy Policy</a>
    <a href="auth/termsandpolicy.php">Terms of Service</a>
    <a href="mailto:support@telecare.ai">Contact Support</a>
  </div>
</footer>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>