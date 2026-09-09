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
.success-overlay{position:fixed;inset:0;background:rgba(15,25,24,.5);display:flex;align-items:center;justify-content:center;z-index:400;backdrop-filter:blur(4px);padding:1rem}
.success-modal{background:#fff;border:1px solid rgba(36,68,65,.12);border-radius:10px;padding:1.35rem 1.15rem;max-width:320px;width:100%;text-align:center;box-shadow:0 18px 45px rgba(0,0,0,.22);animation:popIn .3s ease}
@keyframes popIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
.success-icon{width:48px;height:48px;border-radius:50%;background:rgba(195,54,67,.1);color:var(--red);display:flex;align-items:center;justify-content:center;margin:0 auto .8rem}
.success-title{font-family:'DM Sans',sans-serif;font-size:1.05rem;font-weight:800;color:var(--green);margin-bottom:.45rem}
.success-sub{font-size:.7rem;color:var(--muted);margin-bottom:1rem;line-height:1.45}
.success-modal .wiz-btn{font-size:.68rem;padding:.65rem 1rem;border-radius:6px}
</style>

<!-- Faded page behind the modal so it reads like a confirmation overlay -->
<div class="wiz-page" style="filter:blur(1px);opacity:0.6;pointer-events:none;">
  <div class="wiz-title">Review Appointment</div>
  <div class="wiz-sub">Reference <?= htmlspecialchars($appt['reference_no']) ?></div>
</div>

<div class="success-overlay">
  <div class="success-modal">
    <div class="success-icon">
      <svg width="25" height="25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
    </div>
    <div class="success-title">Appointment Booked Successfully!</div>
    <div class="success-sub">
      Your appointment with <strong>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></strong> has been confirmed for
      <strong><?= (new DateTime($appt['appointment_date']))->format('F j, Y') ?> at <?= date('g:i A', strtotime($appt['appointment_time'])) ?></strong>.
    </div>
  <a href="router.php?page=booking/confirmed&amp;appt_id=<?= $appt_id ?>" class="wiz-btn primary" style="width:100%;text-align:center;box-sizing:border-box;">View Appointment</a>
  <a href="router.php?page=dashboard" class="wiz-btn ghost" style="width:100%;text-align:center;box-sizing:border-box;margin-top:.45rem;">Return to Dashboard</a>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html> 