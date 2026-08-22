<?php
// private_telecare/booking/confirmed.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);

// ── Auto-cancel any Pending/Unpaid appointment past its 10-minute payment window ──
$conn->query("UPDATE appointments SET status='Cancelled' WHERE status='Pending' AND payment_status='Unpaid' AND created_at < (NOW() - INTERVAL 10 MINUTE)");

// ── Cancel action ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $c = $conn->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND patient_id=?");
    $c->bind_param("ii", $appt_id, $patient_id);
    $c->execute();
    $_SESSION['toast'] = 'Appointment cancelled.';
    header('Location: ../router.php?page=visits'); exit;
}

$stmt = $conn->prepare(
    "SELECT a.*, d.full_name AS doctor_name, d.specialty, d.profile_photo, d.consultation_fee
     FROM appointments a JOIN doctors d ON d.id = a.doctor_id
     WHERE a.id=? AND a.patient_id=?"
);
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) { header('Location: ../router.php?page=visits'); exit; }

$initials = strtoupper(substr($appt['doctor_name'],0,1).(strpos($appt['doctor_name'],' ')!==false ? substr($appt['doctor_name'],strpos($appt['doctor_name'],' ')+1,1) : ''));
$apptTs   = strtotime($appt['appointment_date'].' '.$appt['appointment_time']);
$canJoin  = time() >= ($apptTs - 900) && time() <= ($apptTs + 3600);

$isPaid          = $appt['payment_status'] === 'Paid';
$isCancelled     = $appt['status'] === 'Cancelled';
$isPendingUnpaid = !$isPaid && !$isCancelled; // anything not paid/cancelled is awaiting payment

$deadlineTsMs = null;
$secondsLeft  = null;
if ($isPendingUnpaid && !empty($appt['created_at'])) {
    $deadlineTs  = strtotime($appt['created_at']) + 600; // 10 minutes
    $secondsLeft = max(0, $deadlineTs - time());
    $deadlineTsMs = $deadlineTs * 1000;
}

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
.conf-icon.pending{background:rgba(217,119,6,0.12);color:#d97706}
.conf-icon.cancelled{background:rgba(195,54,67,0.1);color:#C33643}
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
.join-box.pending{background:rgba(217,119,6,0.06);border-color:rgba(217,119,6,0.35)}
.doc-card{text-align:center}
.doc-avatar{width:74px;height:74px;border-radius:18px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.4rem;margin:0 auto 0.7rem;overflow:hidden}
.doc-avatar img{width:100%;height:100%;object-fit:cover}
.pay-row{display:flex;justify-content:space-between;font-size:0.85rem;padding:0.4rem 0;border-bottom:1px solid rgba(36,68,65,0.06)}
.pay-row:last-child{border-bottom:none}
.conf-actions{display:flex;justify-content:space-between;margin-top:1.4rem;flex-wrap:wrap;gap:0.6rem}

/* ── Status badge (Pending / Confirmed / Cancelled) ── */
.status-badge{
  display:inline-flex;align-items:center;gap:0.35rem;
  font-size:0.68rem;font-weight:800;letter-spacing:0.05em;text-transform:uppercase;
  padding:0.3rem 0.75rem;border-radius:50px;margin-top:0.3rem;
}
.status-badge.paid{background:rgba(34,197,94,0.12);color:#16a34a}
.status-badge.pending{background:rgba(217,119,6,0.12);color:#d97706}
.status-badge.cancelled{background:rgba(195,54,67,0.1);color:#C33643}

/* ── Countdown ── */
.countdown-wrap{
  display:flex;align-items:center;justify-content:center;gap:0.5rem;
  font-weight:800;font-size:1.05rem;color:#d97706;margin-top:0.5rem;
}
.countdown-wrap svg{width:16px;height:16px;flex-shrink:0}

/* ── Fixed action buttons: proper pill shape, centered icon+text ── */
.btn-fix{
  display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;
  padding:0.75rem 1.6rem;border-radius:50px;
  font-family:'DM Sans',sans-serif;font-size:0.88rem;font-weight:700;
  text-decoration:none;border:1.5px solid transparent;cursor:pointer;
  line-height:1;white-space:nowrap;transition:all .2s;
}
.btn-fix svg{width:16px;height:16px;flex-shrink:0;display:block}
.btn-fix.pay{
  background:linear-gradient(135deg,#C33643,#a82d38);color:#fff;
  box-shadow:0 4px 14px rgba(195,54,67,0.32);
}
.btn-fix.pay:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(195,54,67,0.4)}
.btn-fix.save{
  background:#fff;border-color:rgba(36,68,65,0.15);color:var(--green);
}
.btn-fix.save:hover{background:rgba(36,68,65,0.05)}
.btn-fix.danger-outline{
  background:#fff;border-color:rgba(195,54,67,0.3);color:#C33643;
}
.btn-fix.danger-outline:hover{background:rgba(195,54,67,0.06)}

@media(max-width:800px){.conf-grid{grid-template-columns:1fr}}

/* ── Modal (shared pattern) ── */
.tc-modal{display:none;position:fixed;inset:0;background:rgba(15,25,24,0.5);z-index:9998;align-items:center;justify-content:center;padding:1rem;}
.tc-modal.visible{display:flex}
.tc-modal-box{background:#fff;border-radius:18px;max-width:420px;width:100%;padding:1.8rem;box-shadow:0 20px 60px rgba(0,0,0,0.3);text-align:center;}
.tc-modal-icon{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;}
.tc-modal-icon.warn{background:rgba(217,119,6,0.12);color:#d97706}
.tc-modal-icon.danger{background:rgba(195,54,67,0.1);color:#C33643}
.tc-modal-title{font-family:'Playfair Display',serif;font-weight:900;font-size:1.15rem;color:var(--green);margin-bottom:0.5rem;}
.tc-modal-body{font-size:0.87rem;color:var(--muted);line-height:1.55;margin-bottom:1.4rem;}
.tc-modal-body strong{color:var(--green)}
.tc-modal-actions{display:flex;gap:0.7rem;}
.tc-modal-actions .btn-fix{flex:1;padding:0.7rem 1rem;}
.tc-btn-ghost{background:#fff;border:1.5px solid rgba(36,68,65,0.15);color:var(--green);}
.tc-btn-ghost:hover{background:rgba(36,68,65,0.05);}
.tc-btn-danger{background:linear-gradient(135deg,#C33643,#a82d38);color:#fff;border:none;}
.tc-btn-danger:hover{background:linear-gradient(135deg,#a82d38,#8f2530);}
.tc-btn-warnok{background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border:none;}
.tc-btn-warnok:hover{background:linear-gradient(135deg,#b45309,#92400e);}
</style>

<div class="conf-page">
  <div class="conf-banner">
    <div class="conf-banner-left">
      <div class="conf-icon <?= $isCancelled ? 'cancelled' : ($isPendingUnpaid ? 'pending' : '') ?>">
        <?php if ($isCancelled): ?>
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
        <?php elseif ($isPendingUnpaid): ?>
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        <?php else: ?>
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
        <?php endif; ?>
      </div>
      <div>
        <div class="conf-title">
          <?= $isCancelled ? 'Appointment Cancelled' : ($isPendingUnpaid ? 'Payment Pending' : 'Appointment Confirmed') ?>
        </div>
        <div class="conf-sub">
          <?php if ($isCancelled): ?>
            This appointment was cancelled and is no longer reserved.
          <?php elseif ($isPendingUnpaid): ?>
            Your slot is held temporarily. Complete payment before the timer runs out or it will be released.
          <?php else: ?>
            Your appointment has been successfully scheduled.
          <?php endif; ?>
        </div>
        <div class="status-badge <?= $isCancelled ? 'cancelled' : ($isPendingUnpaid ? 'pending' : 'paid') ?>">
          <?= $isCancelled ? 'Cancelled' : ($isPendingUnpaid ? 'Awaiting Payment' : 'Confirmed & Paid') ?>
        </div>
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

      <div class="join-box <?= $isPendingUnpaid ? 'pending' : '' ?>">
        <?php if ($isCancelled): ?>
          This appointment was cancelled.
        <?php elseif ($isPendingUnpaid): ?>
          <div>Complete payment to unlock your consultation link.</div>
          <?php if ($secondsLeft !== null): ?>
            <div class="countdown-wrap" id="countdown">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
              <span id="countdown-text">--:--</span>
            </div>
          <?php endif; ?>
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
    <a href="../router.php?page=visits" class="btn-fix save">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
      Back to Appointments
    </a>
    <div style="display:flex;gap:0.6rem;flex-wrap:wrap;">
      <?php if ($isPendingUnpaid): ?>
        
        <a href="../router.php?page=pay&appt_id=<?= $appt_id ?>" class="btn-fix pay">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          Pay Now
        </a>
      <?php endif; ?>
      <?php if (!$isCancelled): ?>
        <button type="button" class="btn-fix danger-outline" onclick="openCancelModal()">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
          Cancel Appointment
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Hidden cancel form -->
<form method="POST" id="cancel-form">
  <input type="hidden" name="cancel_appointment" value="1"/>
</form>

<!-- ── Cancel confirmation modal ── -->
<div class="tc-modal" id="cancel-modal">
  <div class="tc-modal-box">
    <div class="tc-modal-icon danger">
      <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
    </div>
    <div class="tc-modal-title">Cancel this appointment?</div>
    <div class="tc-modal-body">
      This will free up your slot with Dr. <?= htmlspecialchars($appt['doctor_name']) ?> on
      <strong><?= (new DateTime($appt['appointment_date']))->format('M j, Y') ?> at <?= date('g:i A', strtotime($appt['appointment_time'])) ?></strong>.
      This action cannot be undone.
    </div>
    <div class="tc-modal-actions">
      <button type="button" class="btn-fix tc-btn-ghost" onclick="closeCancelModal()">Keep Appointment</button>
      <button type="button" class="btn-fix tc-btn-danger" onclick="confirmCancel()">Yes, Cancel</button>
    </div>
  </div>
</div>

<!-- ── Save for now warning modal ── -->
<div class="tc-modal" id="save-modal">
  <div class="tc-modal-box">
    <div class="tc-modal-icon warn">
      <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
    </div>
    <div class="tc-modal-title">Your slot isn't secured yet</div>
    <div class="tc-modal-body">
      If payment isn't completed within <strong>10 minutes</strong> of booking, this schedule will automatically
      open up and become available to other patients again. You can come back and pay any time before then
      from <strong>My Visits</strong>.
    </div>
    <div class="tc-modal-actions">
      <button type="button" class="btn-fix tc-btn-ghost" onclick="closeSaveModal()">Stay & Pay Now</button>
      <button type="button" class="btn-fix tc-btn-warnok" onclick="location.href='../router.php?page=visits'">Got It, Leave</button>
    </div>
  </div>
</div>

<script>
function openCancelModal(){ document.getElementById('cancel-modal').classList.add('visible'); }
function closeCancelModal(){ document.getElementById('cancel-modal').classList.remove('visible'); }
function confirmCancel(){ document.getElementById('cancel-form').submit(); }

function openSaveModal(){ document.getElementById('save-modal').classList.add('visible'); }
function closeSaveModal(){ document.getElementById('save-modal').classList.remove('visible'); }

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeCancelModal(); closeSaveModal(); }
});

<?php if ($isPendingUnpaid && $deadlineTsMs !== null): ?>
(function(){
  const deadline = <?= (int)$deadlineTsMs ?>;
  const el = document.getElementById('countdown-text');
  const wrap = document.getElementById('countdown');
  function tick(){
    const diff = deadline - Date.now();
    if (diff <= 0) {
      if (el) el.textContent = 'Expired';
      if (wrap) wrap.style.color = '#C33643';
      clearInterval(timer);
      setTimeout(() => location.reload(), 1200);
      return;
    }
    const m = Math.floor(diff / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    if (el) el.textContent = m + ':' + String(s).padStart(2,'0') + ' remaining';
  }
  tick();
  const timer = setInterval(tick, 1000);
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>