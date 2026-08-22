<?php
// private_telecare/booking/success.php
// Point your PayMongo success handler here: success.php?appt_id=<id>
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT a.*, d.full_name AS doctor_name
     FROM appointments a JOIN doctors d ON d.id = a.doctor_id
     WHERE a.id=? AND a.patient_id=?"
);
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) { header('Location: ../router.php?page=visits'); exit; }

$page_title = 'Booking Confirmed — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.success-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;z-index:400;backdrop-filter:blur(4px);padding:1rem}
.success-modal{background:#fff;border-radius:20px;padding:2.2rem 2rem;max-width:420px;width:100%;text-align:center;animation:popIn .3s ease}
@keyframes popIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
.success-icon{width:64px;height:64px;border-radius:50%;background:rgba(34,197,94,0.12);color:#16a34a;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem}
.success-title{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--green);margin-bottom:0.5rem}
.success-sub{font-size:0.88rem;color:var(--muted);margin-bottom:1.4rem;line-height:1.5}
</style>

<!-- Faded page behind the modal so it reads like a confirmation overlay -->
<div class="wiz-page" style="filter:blur(1px);opacity:0.6;pointer-events:none;">
  <div class="wiz-title">Review Appointment</div>
  <div class="wiz-sub">Reference <?= htmlspecialchars($appt['reference_no']) ?></div>
</div>

<div class="success-overlay">
  <div class="success-modal">
    <div class="success-icon">
      <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
    </div>
    <div class="success-title">Appointment Booked Successfully!</div>
    <div class="success-sub">
      Your appointment with <strong>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></strong> has been confirmed for
      <strong><?= (new DateTime($appt['appointment_date']))->format('F j, Y') ?> at <?= date('g:i A', strtotime($appt['appointment_time'])) ?></strong>.
    </div>
<a href="router.php?page=booking/confirmed&appt_id=<?= $appt_id ?>" class="wiz-btn primary" style="width:100%;text-align:center;box-sizing:border-box;">View Appointment</a>
    <a href="router.php?page=visits" class="wiz-btn ghost" style="width:100%;text-align:center;box-sizing:border-box;margin-top:0.6rem;">Return to My Visits</a>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html> 