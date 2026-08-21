<?php
// private_telecare/booking/payment.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT a.*, d.full_name AS doctor_name, d.specialty, d.consultation_fee
     FROM appointments a JOIN doctors d ON d.id = a.doctor_id
     WHERE a.id=? AND a.patient_id=?"
);
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) { header('Location: ../visits.php'); exit; }

// Already paid? Don't let them pay twice — send straight to success/confirmed.
if ($appt['payment_status'] === 'Paid') {
    header('Location: confirmed.php?appt_id=' . $appt_id); exit;
}

$fee = (float)$appt['consultation_fee'];

$page_title = 'Payment — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.pay-card{max-width:480px;margin:0 auto}
.bill-row{display:flex;justify-content:space-between;font-size:0.87rem;padding:0.4rem 0}
.bill-total{display:flex;justify-content:space-between;font-family:'Playfair Display',serif;font-weight:900;font-size:1.3rem;color:var(--red);border-top:1px solid rgba(36,68,65,0.1);margin-top:0.6rem;padding-top:0.7rem}
</style>

<div class="wiz-page">
  <div class="wiz-title">Payment</div>
  <div class="wiz-sub">Reference <strong><?= htmlspecialchars($appt['reference_no']) ?></strong> &mdash; Dr. <?= htmlspecialchars($appt['doctor_name']) ?>, <?= (new DateTime($appt['appointment_date']))->format('M j, Y') ?> at <?= date('g:i A', strtotime($appt['appointment_time'])) ?>.</div>

  <div class="wiz-card pay-card">
    <h3>Billing Summary</h3>
    <div class="bill-row"><span>Consultation Fee</span><span><?= '&#8369;' . number_format($fee, 2) ?></span></div>
    <div class="bill-total"><span>Total Due</span><span><?= '&#8369;' . number_format($fee, 2) ?></span></div>

    <!--
      This hands off to your existing PayMongo integration (router.php?page=pay).
      Make sure that flow's success handler updates payment_status='Paid' for
      this appointment id and then redirects to:
        booking/success.php?appt_id=<?= $appt_id ?>
    -->
    <a href="../router.php?page=pay&appt_id=<?= $appt_id ?>" class="wiz-btn primary" style="width:100%;text-align:center;box-sizing:border-box;margin-top:1.2rem;">Proceed to Payment &rarr;</a>
    <a href="../visits.php" class="wiz-btn ghost" style="width:100%;text-align:center;box-sizing:border-box;margin-top:0.6rem;">Pay later from My Visits</a>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>