<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';
// doctor/appointments.php

function isCallActive(string $date, string $time): bool {
    $appt = strtotime($date . ' ' . $time);
    $now  = time();
    return $now >= ($appt - 900) && $now <= ($appt + 3600);
}
function getJitsiRoom(int $appt_id, string $date): string {
    return 'telecare-appt-' . $appt_id . '-' . str_replace('-', '', $date);
}

// ── POST: Doctor accept/decline pending, or mark done/cancel confirmed ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $aid    = (int)$_POST['appointment_id'];
    $status = $_POST['status'] ?? '';

    $allowed = ['DoctorApproved', 'Cancelled', 'Completed'];
    if (in_array($status, $allowed)) {
        if ($status === 'DoctorApproved') {
            $stmt = $conn->prepare("UPDATE appointments SET status='DoctorApproved' WHERE id=? AND doctor_id=? AND status='Pending'");
            $stmt->bind_param("ii", $aid, $doctor_id);
            $stmt->execute();
            $_SESSION['toast'] = 'Appointment accepted. Waiting for staff to confirm.';
        } elseif ($status === 'Completed') {
            $stmt = $conn->prepare("UPDATE appointments SET status='Completed' WHERE id=? AND doctor_id=? AND status='Confirmed' AND payment_status='Paid'");
            $stmt->bind_param("ii", $aid, $doctor_id);
            $stmt->execute();
            $_SESSION['toast'] = 'Appointment marked as completed.';
        } elseif ($status === 'Cancelled') {
            $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND doctor_id=? AND status IN ('Pending','Confirmed','DoctorApproved')");
            $stmt->bind_param("ii", $aid, $doctor_id);
            $stmt->execute();
            $_SESSION['toast'] = 'Appointment cancelled.';
        }
    }
    header('Location: appointments.php' . (isset($_GET['filter']) ? '?filter='.$_GET['filter'] : '')); exit;
}

$filter            = $_GET['filter']     ?? 'upcoming';
$patient_id_filter = (int)($_GET['patient_id'] ?? 0);

$where = "a.doctor_id=$doctor_id AND a.status != 'Cancelled'";
if ($patient_id_filter) $where .= " AND a.patient_id=$patient_id_filter";

if ($filter === 'pending') {
    $where = "a.doctor_id=$doctor_id AND a.status='Pending'";
} elseif ($filter === 'today') {
    $where .= " AND a.appointment_date=CURDATE() AND a.status='Confirmed' AND a.payment_status='Paid'";
} elseif ($filter === 'completed') {
    $where = "a.doctor_id=$doctor_id AND a.status='Completed'";
} elseif ($filter === 'cancelled') {
    $where = "a.doctor_id=$doctor_id AND a.status='Cancelled'";
} else {
    $where = "a.doctor_id=$doctor_id AND a.appointment_date >= CURDATE() AND a.status='Confirmed' AND a.payment_status='Paid'";
}

$appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.profile_photo AS patient_photo,
           p.email AS patient_email, p.phone_number AS patient_phone,
           d.consultation_fee, d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE $where
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$pending_count = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='Pending'")->fetch_assoc()['c'];

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$page_title       = 'Schedule — TELE-CARE';
$page_title_short = 'Schedule';
$active_nav       = 'appointments';
require_once 'includes/header.php';
?>

<style>
  .join-call-btn{display:inline-flex;align-items:center;gap:0.45rem;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;padding:0.55rem 1rem;border-radius:50px;font-size:0.78rem;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(22,163,74,0.35);animation:callPulse 2s ease-in-out infinite;}
  @keyframes callPulse{0%,100%{box-shadow:0 4px 14px rgba(22,163,74,0.35)}50%{box-shadow:0 4px 20px rgba(22,163,74,0.6)}}
  .call-soon{font-size:0.72rem;color:#d97706;font-weight:600;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);border-radius:50px;padding:0.2rem 0.6rem;}
  .appt-card{background:#fff;border-radius:16px;margin-bottom:0.75rem;overflow:hidden;border:1.5px solid rgba(36,68,65,0.07);box-shadow:0 2px 8px rgba(0,0,0,0.04);transition:transform 0.15s,box-shadow 0.15s;}
  .appt-card:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,0.08);}
  .appt-card.pending-card{border-left:3px solid #f59e0b;}
  .appt-card.doctor-approved-card{border-left:3px solid #3F82E3;}
  .appt-card.confirmed-card{border-left:3px solid #16a34a;}
  .appt-card.completed-card{border-left:3px solid #3F82E3;}
  .appt-card.cancelled-card{border-left:3px solid #C33643;}
  .appt-card-body{padding:0.9rem 1rem;}
  .appt-time-strip{background:rgba(36,68,65,0.03);padding:0.45rem 1rem;display:flex;align-items:center;gap:0.5rem;font-size:0.73rem;font-weight:700;color:var(--muted);border-bottom:1px solid rgba(36,68,65,0.05);}
  .action-row{display:flex;gap:0.5rem;padding:0.7rem 1rem;border-top:1px solid rgba(36,68,65,0.05);background:rgba(36,68,65,0.015);}
  .act-btn{flex:1;padding:0.55rem;border-radius:10px;font-size:0.78rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:0.3rem;}
  .act-btn-accept{background:rgba(34,197,94,0.1);color:#16a34a;}
  .act-btn-accept:hover{background:rgba(34,197,94,0.2);}
  .act-btn-done{background:rgba(63,130,227,0.1);color:var(--blue);}
  .act-btn-done:hover{background:rgba(63,130,227,0.2);}
  .act-btn-cancel{background:rgba(195,54,67,0.08);color:#C33643;}
  .act-btn-cancel:hover{background:rgba(195,54,67,0.18);}
  .notes-pill{background:rgba(245,158,11,0.08);border-radius:10px;padding:0.5rem 0.7rem;font-size:0.78rem;color:#92400e;margin:0.6rem 0 0;display:flex;align-items:flex-start;gap:0.4rem;line-height:1.45;}
  .toast-bar{position:fixed;bottom:5rem;left:50%;transform:translateX(-50%);z-index:400;padding:0.75rem 1.4rem;border-radius:50px;font-size:0.85rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.15);white-space:nowrap;background:var(--green);color:#fff;animation:toastIn 0.3s ease,toastOut 0.4s 3s ease forwards;}
  @keyframes toastIn{from{opacity:0;transform:translateX(-50%) translateY(12px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
  @keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}
  .pending-badge-dot{background:#f59e0b;color:#fff;border-radius:50%;width:18px;height:18px;font-size:0.65rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;margin-left:0.35rem;vertical-align:middle;}

  /* Receipt button */
  .btn-receipt-sm{background:rgba(34,197,94,0.09);color:#15803d;border:1px solid rgba(34,197,94,0.2);border-radius:6px;padding:0.3rem 0.7rem;font-size:0.73rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:0.3rem;margin-top:0.5rem;}
  .btn-receipt-sm:hover{background:rgba(34,197,94,0.18);}

  /* Summary button */
  .btn-summary-sm{background:rgba(63,130,227,0.09);color:#2563eb;border:1px solid rgba(63,130,227,0.2);border-radius:6px;padding:0.3rem 0.7rem;font-size:0.73rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:0.3rem;margin-top:0.5rem;}
  .btn-summary-sm:hover{background:rgba(63,130,227,0.18);}

  /* Summary generating */
  .summary-generating-sm{display:inline-flex;align-items:center;gap:0.3rem;background:rgba(245,158,11,0.07);border:1px dashed rgba(245,158,11,0.3);color:#d97706;padding:0.25rem 0.65rem;border-radius:6px;font-size:0.7rem;font-weight:600;margin-top:0.5rem;}
  @keyframes spin{to{transform:rotate(360deg)}}
  .spin-icon{animation:spin 1.4s linear infinite;display:inline-block;}

  /* Receipt Modal */
  .receipt-modal-header{background:linear-gradient(135deg,#244441,#1a3533);padding:1.4rem 1.4rem 1.2rem;color:#fff;position:relative;}
  .receipt-modal-header::after{content:'';position:absolute;bottom:-10px;left:0;right:0;height:20px;
    background:repeating-linear-gradient(-45deg,#fff 0,#fff 7px,transparent 7px,transparent 14px),
               repeating-linear-gradient(45deg,#fff 0,#fff 7px,transparent 7px,transparent 14px);
    background-size:20px 20px;background-position:0 0,10px 0;z-index:1;}
  .receipt-detail-row{display:flex;justify-content:space-between;align-items:flex-start;padding:0.32rem 0;font-size:0.82rem;}
  .receipt-detail-label{color:var(--muted);font-weight:600;flex-shrink:0;}
  .receipt-detail-val{color:var(--green);font-weight:700;text-align:right;max-width:60%;}

  /* Modal overlay */
  .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:300;align-items:center;justify-content:center;padding:1rem;}
  .modal-overlay.active{display:flex;}
</style>

<?php if ($toast): ?>
<div class="toast-bar">✓ <?= htmlspecialchars($toast) ?></div>
<?php endif; ?>

<div class="page">

  <!-- Info banner -->
  <div style="background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.18);border-radius:14px;padding:0.7rem 1rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:0.6rem;font-size:0.78rem;color:#15803d;">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span><strong>Flow:</strong> You accept patient requests first → Staff confirms → Patient pays → Appointment appears in your Upcoming schedule.</span>
  </div>

  <!-- Filter Tabs -->
  <div style="display:flex;gap:0.5rem;margin-bottom:1rem;overflow-x:auto;padding-bottom:0.2rem;">
    <?php
    $tabs = [
        'upcoming'  => 'Upcoming',
        'pending'   => 'Pending',
        'today'     => 'Today',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];
    foreach ($tabs as $k=>$v):
    ?>
    <a href="?filter=<?= $k ?>" style="flex-shrink:0;padding:0.45rem 1rem;border-radius:50px;font-size:0.78rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;<?= $filter===$k?'background:var(--green);color:#fff;':'background:#fff;color:var(--muted);border:1px solid rgba(36,68,65,0.1);' ?>">
      <?= htmlspecialchars($v) ?>
      <?php if ($k === 'pending' && $pending_count > 0): ?><span class="pending-badge-dot"><?= $pending_count ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($appts && $appts->num_rows > 0):
    $shown_date = '';
    while ($a = $appts->fetch_assoc()):
      $appt_date_label = date('l, F j', strtotime($a['appointment_date']));
      if ($appt_date_label !== $shown_date): $shown_date = $appt_date_label; ?>
  <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin:1rem 0 0.5rem;padding-left:0.2rem;">
    <?= $appt_date_label === date('l, F j') ? '🗓 Today' : $appt_date_label ?>
  </div>
  <?php endif;
    $status    = $a['status'];
    $paid      = $a['payment_status'] === 'Paid';
    $cardClass = match($status) {
        'Pending'        => 'pending-card',
        'DoctorApproved' => 'doctor-approved-card',
        'Confirmed'      => 'confirmed-card',
        'Completed'      => 'completed-card',
        default          => 'cancelled-card',
    };
    $apptTs = strtotime($a['appointment_date'].' '.$a['appointment_time']);
    $now    = time();
    $active = $now >= ($apptTs - 900) && $now <= ($apptTs + 3600);
    $early  = $active && $now < $apptTs;
    $soon   = $now < ($apptTs - 900) && $now >= ($apptTs - 3600);

    // Summary availability
    $hasSummary   = !empty($a['summary_pdf_path']);
    // Only show "generating" if call happened AND there's actual content
    $hasContent   = !empty($a['chat_log']) || !empty($a['consultation_transcript']);
    $callHappened = $now >= ($apptTs - 900) && $hasContent;
    $callEnded    = $now >= $apptTs; // Call time has passed (appointment is over)

    // Receipt data
    $receipt_no  = $a['receipt_number'] ?? ('TC-' . strtoupper(substr(md5($a['id']), 0, 8)));
    $paid_at_val = $a['paid_at'] ?? '';
    $fee_val     = floatval($a['consultation_fee'] ?? 0);
  ?>

  <div class="appt-card <?= $cardClass ?>">
    <!-- Time strip -->
    <div class="appt-time-strip">
      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/></svg>
      <?= date('g:i A', strtotime($a['appointment_time'])) ?>
      &nbsp;·&nbsp;
      <?= htmlspecialchars($a['type'] ?? 'Consultation') ?>
      <span style="margin-left:auto;display:flex;align-items:center;gap:0.5rem;">
        <?php if ($status === 'Pending'): ?>
          <span class="badge badge-orange">New Request</span>
        <?php elseif ($status === 'DoctorApproved'): ?>
          <span class="badge badge-blue">Awaiting Staff</span>
        <?php elseif ($status === 'Confirmed'): ?>
          <span style="display:inline-flex;align-items:center;gap:0.3rem;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:50px;padding:0.15rem 0.6rem;font-size:0.65rem;font-weight:700;color:#16a34a;">✓ Staff Confirmed</span>
        <?php endif; ?>
        <span class="badge <?= $status==='Confirmed'?'badge-green':($status==='Pending'?'badge-orange':($status==='DoctorApproved'?'badge-blue':($status==='Completed'?'badge-blue':'badge-red'))) ?>"><?= $status ?></span>
        <?php if ($paid): ?><span class="badge badge-green">Paid</span><?php endif; ?>
      </span>
    </div>

    <!-- Patient info -->
    <div class="appt-card-body">
      <div style="display:flex;align-items:center;gap:0.8rem;">
        <div class="pat-avatar">
          <?php if (!empty($a['patient_photo'])): ?>
            <img src="../../<?= htmlspecialchars($a['patient_photo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"/>
          <?php else: echo strtoupper(substr($a['patient_name'],0,2)); endif; ?>
        </div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:0.92rem;"><?= htmlspecialchars($a['patient_name']) ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:0.8rem;margin-top:0.2rem;">
            <?php if (!empty($a['patient_phone'])): ?>
            <span style="font-size:0.73rem;color:var(--muted);">📞 <?= htmlspecialchars($a['patient_phone']) ?></span>
            <?php endif; ?>
            <?php if (!empty($a['patient_email'])): ?>
            <span style="font-size:0.73rem;color:var(--muted);">✉ <?= htmlspecialchars($a['patient_email']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!empty($a['notes'])): ?>
      <div class="notes-pill">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        <?= htmlspecialchars($a['notes']) ?>
      </div>
      <?php endif; ?>

      <!-- Video Call (only for Confirmed+Paid) -->
      <?php if ($status === 'Confirmed' && $paid): ?>
      <div style="margin-top:0.8rem;">
        <?php if ($active): ?>
          <a href="call.php?appt_id=<?= $a['id'] ?>" class="join-call-btn">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
            <?= $early ? '📹 Open Call Room Early' : '📹 Start Video Call' ?>
          </a>
        <?php elseif ($soon): ?>
          <span class="call-soon">🕐 Call opens at <?= date('g:i A', $apptTs - 900) ?></span>
        <?php else: ?>
          <span style="font-size:0.72rem;color:var(--muted);">📹 Video call available 15 min before appointment</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Awaiting payment note -->
      <?php if ($status === 'Confirmed' && !$paid): ?>
      <div style="margin-top:0.7rem;font-size:0.75rem;color:#d97706;background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:10px;padding:0.45rem 0.75rem;display:flex;align-items:center;gap:0.4rem;">
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Awaiting patient payment
      </div>
      <?php endif; ?>

      <!-- Buttons row -->
      <div style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;">
        <!-- Receipt button (paid appointments) -->
        <?php if ($paid): ?>
        <button class="btn-receipt-sm"
          data-appt-id="<?= $a['id'] ?>"
          data-patient="<?= htmlspecialchars($a['patient_name'], ENT_QUOTES) ?>"
          data-doctor="<?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?>"
          data-specialty="<?= htmlspecialchars($a['specialty'] ?? '', ENT_QUOTES) ?>"
          data-date="<?= $a['appointment_date'] ?>"
          data-time="<?= substr($a['appointment_time'], 0, 5) ?>"
          data-type="<?= htmlspecialchars($a['type'] ?? 'Teleconsult', ENT_QUOTES) ?>"
          data-fee="<?= $fee_val ?>"
          data-receipt="<?= htmlspecialchars($receipt_no, ENT_QUOTES) ?>"
          data-paid-at="<?= htmlspecialchars($paid_at_val, ENT_QUOTES) ?>"
          onclick="handleReceiptClick(this)">
          📄 View Receipt
        </button>
        <?php endif; ?>

        <?php
        // ── SUMMARY BUTTON ──
        // Show for: Confirmed (ongoing/upcoming), Completed — any status where call may have occurred.
        // FIX: was checking 'Upcoming' (not a real status) — now correctly checks 'Confirmed'.
        if (($status === 'Completed' || $status === 'Confirmed') && $hasSummary): ?>
        <a href="../download_summary.php?appt_id=<?= $a['id'] ?>" target="_blank" class="btn-summary-sm">
          📋 View Summary
        </a>
        <?php elseif (($status === 'Completed' || $status === 'Confirmed') && $hasContent && !$hasSummary): ?>
        <span class="summary-generating-sm"><span class="spin-icon">⏳</span> Generating summary…</span>
        <?php endif; ?>
      </div>

    </div>

    <!-- Action buttons -->
    <?php if ($status === 'Pending'): ?>
    <div class="action-row">
      <form method="POST" style="flex:1;display:contents;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="DoctorApproved"/>
        <button name="update_status" class="act-btn act-btn-accept">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Accept
        </button>
      </form>
      <form method="POST" style="flex:1;display:contents;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Cancelled"/>
        <button name="update_status" class="act-btn act-btn-cancel" onclick="return confirm('Decline this appointment request?')">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          Decline
        </button>
      </form>
    </div>
    <?php elseif ($status === 'Confirmed' && $paid): ?>
    <div class="action-row">
      <form method="POST" style="flex:1;display:contents;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Completed"/>
        <button name="update_status" class="act-btn act-btn-done">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Mark Done
        </button>
      </form>
      <form method="POST" style="flex:1;display:contents;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Cancelled"/>
        <button name="update_status" class="act-btn act-btn-cancel" onclick="return confirm('Cancel this confirmed appointment?')">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          Cancel
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <?php endwhile; else: ?>
  <div class="card"><div class="empty-state">
    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    <?php if ($filter === 'pending'): ?>No pending appointment requests.
    <?php elseif ($filter === 'upcoming'): ?>No upcoming confirmed & paid appointments.
    <?php else: ?>No <?= $filter ?> appointments.<?php endif; ?>
  </div></div>
  <?php endif; ?>

</div>

<!-- ══════════════════════════════════════════════════════════
     Receipt Modal
     ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-receipt" onclick="if(event.target===this)closeReceiptModal()">
  <div class="modal" style="min-width:550px; max-width:700px;padding:0;overflow-y:auto;border-radius:20px;background:#fff;max-height:90vh;">

    <div class="receipt-modal-header">
      <button onclick="closeReceiptModal()"
              style="position:absolute;top:0.9rem;right:0.9rem;background:rgba(255,255,255,0.15);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;z-index:2;line-height:1;">✕</button>
      <div style="font-size:0.7rem;font-weight:800;letter-spacing:0.12em;text-transform:uppercase;opacity:0.7;margin-bottom:0.4rem;">🏥 Tele-Care</div>
      <div style="font-family:'Playfair Display',Georgia,serif;font-size:1.5rem;font-weight:800;margin-bottom:0.2rem;">Payment Receipt</div>
      <div style="font-size:0.75rem;opacity:0.75;">Official Consultation Receipt</div>
    </div>

    <div style="padding:1.8rem 1.4rem 0;margin-top:10px;">
      <div style="display:flex;align-items:center;gap:0.6rem;background:rgba(34,197,94,0.08);border:1.5px solid rgba(34,197,94,0.25);border-radius:14px;padding:0.7rem 1rem;margin-bottom:1.1rem;">
        <div style="width:34px;height:34px;background:linear-gradient(135deg,#16a34a,#15803d);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div>
          <div style="font-weight:700;font-size:0.88rem;color:#15803d;">Payment Successful</div>
          <div id="rm-paid-at" style="font-size:0.72rem;color:#16a34a;opacity:0.85;"></div>
        </div>
      </div>

      <div style="text-align:center;margin-bottom:1rem;">
        <div style="font-size:0.63rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:0.2rem;">Receipt No.</div>
        <div id="rm-receipt-no" style="font-size:1.1rem;font-weight:800;color:var(--green);letter-spacing:0.05em;font-family:'DM Mono',monospace,sans-serif;"></div>
      </div>

      <hr style="border:none;border-top:1.5px dashed rgba(36,68,65,0.12);margin:0.8rem 0;"/>
      <div id="rm-rows"></div>

      <div style="background:rgba(36,68,65,0.04);border-radius:14px;padding:1rem;text-align:center;margin:1rem 0;">
        <div style="font-size:0.68rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin-bottom:0.3rem;">Total Amount Paid</div>
        <div id="rm-amount" style="font-size:2rem;font-weight:800;color:var(--green);"></div>
        <div style="margin-top:0.4rem;">
          <span style="display:inline-flex;align-items:center;gap:0.3rem;background:#16a34a;color:#fff;border-radius:50px;padding:0.2rem 0.8rem;font-size:0.7rem;font-weight:700;">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            PAID
          </span>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;font-size:0.73rem;padding:0.25rem 0;">
        <span style="color:var(--muted);font-weight:600;">Payment via</span>
        <span style="color:var(--green);font-weight:700;">PayMongo (GCash / Card)</span>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:0.73rem;padding:0.25rem 0 0.5rem;">
        <span style="color:var(--muted);font-weight:600;">Appointment ID</span>
        <span id="rm-appt-id" style="color:var(--green);font-weight:700;"></span>
      </div>
    </div>

    <div style="border-top:1.5px dashed rgba(36,68,65,0.12);margin:0 1.4rem;"></div>

    <div style="padding:0.9rem 1.4rem 1.4rem;text-align:center;">
      <div style="font-size:0.7rem;color:var(--muted);line-height:1.7;">
        Thank you for choosing TELE-CARE.<br/>
        <strong>This serves as official proof of payment.</strong>
      </div>
      <button onclick="printReceiptDoctor()"
              style="display:inline-flex;align-items:center;gap:0.4rem;background:var(--green);color:#fff;padding:0.6rem 1.4rem;border-radius:50px;font-size:0.8rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:0.85rem;">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print / Save PDF
      </button>
    </div>
  </div>
</div>

<script>
setTimeout(()=>{ const t=document.querySelector('.toast-bar'); if(t)t.remove(); }, 3500);

function handleReceiptClick(btn) {
  openReceiptModal(
    btn.dataset.apptId, btn.dataset.patient, btn.dataset.doctor,
    btn.dataset.specialty, btn.dataset.date, btn.dataset.time,
    btn.dataset.type, btn.dataset.fee, btn.dataset.receipt, btn.dataset.paidAt
  );
}

function openReceiptModal(apptId, patient, doctor, specialty, apptDate, apptTime, type, amount, receiptNo, paidAt) {
  document.getElementById('rm-receipt-no').textContent = receiptNo;
  const paidDate = paidAt ? new Date(paidAt) : new Date();
  document.getElementById('rm-paid-at').textContent = paidDate.toLocaleString('en-PH', {
    month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  });
  document.getElementById('rm-appt-id').textContent = '#' + apptId;
  document.getElementById('rm-amount').textContent = '₱' + parseFloat(amount).toLocaleString('en-PH', {
    minimumFractionDigits: 2, maximumFractionDigits: 2
  });
  const apptDateFmt = new Date(apptDate + 'T00:00:00').toLocaleDateString('en-PH', {
    month: 'long', day: 'numeric', year: 'numeric'
  });
  const [h, m]  = apptTime.split(':').map(Number);
  const timeFmt = `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
  const rows = [
    ['Patient',     patient],
    ['Doctor',      'Dr. ' + doctor],
    ['Specialty',   specialty || '—'],
    ['Appointment', apptDateFmt + ' · ' + timeFmt],
    ['Type',        '📹 ' + type],
  ];
  document.getElementById('rm-rows').innerHTML = rows.map(([label, val]) => `
    <div class="receipt-detail-row">
      <span class="receipt-detail-label">${label}</span>
      <span class="receipt-detail-val">${val}</span>
    </div>
  `).join('');
  document.getElementById('modal-receipt').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeReceiptModal() {
  document.getElementById('modal-receipt').classList.remove('active');
  document.body.style.overflow = '';
}

function printReceiptDoctor() {
  const receiptNo  = document.getElementById('rm-receipt-no').textContent;
  const paidAt     = document.getElementById('rm-paid-at').textContent;
  const apptId     = document.getElementById('rm-appt-id').textContent;
  const amount     = document.getElementById('rm-amount').textContent;
  const rowsHTML   = document.getElementById('rm-rows').innerHTML;

  const printWindow = window.open('', '_blank', 'width=500,height=780');
  printWindow.document.write(`<!DOCTYPE html>
<html>
<head>
  <title>Payment Receipt</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: #fff; display: flex; justify-content: center; padding: 20px; }
    .card { width: 420px; border-radius: 20px; overflow: hidden; border: 1px solid #e5e7eb; }
    .hdr { background: linear-gradient(135deg,#244441,#1a3533); padding: 1.4rem; color: #fff; position: relative; }
    .hdr::after { content:''; position:absolute; bottom:-10px; left:0; right:0; height:20px;
      background: repeating-linear-gradient(-45deg,#fff 0,#fff 7px,transparent 7px,transparent 14px),
                  repeating-linear-gradient(45deg,#fff 0,#fff 7px,transparent 7px,transparent 14px);
      background-size:20px 20px; background-position:0 0,10px 0; z-index:1; }
    .hdr .brand { font-size:.7rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; opacity:.7; margin-bottom:.4rem; }
    .hdr .title { font-size:1.5rem; font-weight:800; margin-bottom:.2rem; }
    .hdr .sub   { font-size:.75rem; opacity:.75; }
    .body { padding: 1.8rem 1.4rem 0; margin-top: 10px; }
    .success-bar { display:flex; align-items:center; gap:.6rem;
      background:rgba(34,197,94,.08); border:1.5px solid rgba(34,197,94,.25);
      border-radius:14px; padding:.7rem 1rem; margin-bottom:1.1rem; }
    .success-icon { width:34px; height:34px; background:linear-gradient(135deg,#16a34a,#15803d);
      border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .success-title { font-weight:700; font-size:.88rem; color:#15803d; }
    .success-date  { font-size:.72rem; color:#16a34a; opacity:.85; }
    .rcpt-no-wrap { text-align:center; margin-bottom:1rem; }
    .rcpt-no-label { font-size:.63rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; color:#7a9a97; margin-bottom:.2rem; }
    .rcpt-no-val   { font-size:1.1rem; font-weight:800; color:#244441; letter-spacing:.05em; font-family:monospace; }
    hr.dashed { border:none; border-top:1.5px dashed rgba(36,68,65,.12); margin:.8rem 0; }
    .detail-row { display:flex; justify-content:space-between; align-items:flex-start; padding:.32rem 0; font-size:.82rem; }
    .detail-label { color:#7a9a97; font-weight:600; flex-shrink:0; }
    .detail-val   { color:#244441; font-weight:700; text-align:right; max-width:60%; }
    .amount-box { background:rgba(36,68,65,.04); border-radius:14px; padding:1rem; text-align:center; margin:1rem 0; }
    .amount-label { font-size:.68rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#7a9a97; margin-bottom:.3rem; }
    .amount-val   { font-size:2rem; font-weight:800; color:#244441; }
    .paid-badge   { display:inline-flex; align-items:center; gap:.3rem;
      background:#16a34a; color:#fff; border-radius:50px; padding:.2rem .8rem;
      font-size:.7rem; font-weight:700; margin-top:.4rem; }
    .meta-row { display:flex; justify-content:space-between; font-size:.73rem; padding:.25rem 0; }
    .footer { border-top:1.5px dashed rgba(36,68,65,.12); margin:0 1.4rem; }
    .footer-inner { padding:.9rem 1.4rem 1.4rem; text-align:center; }
    .footer-text  { font-size:.7rem; color:#7a9a97; line-height:1.7; }
    button { display: none !important; }
    @media print { body { padding: 0; } .card { border: none; width: 100%; } }
  </style>
</head>
<body>
<div class="card">
  <div class="hdr">
    <div class="brand">🏥 Tele-Care</div>
    <div class="title">Payment Receipt</div>
    <div class="sub">Official Consultation Receipt</div>
  </div>
  <div class="body">
    <div class="success-bar">
      <div class="success-icon">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <div>
        <div class="success-title">Payment Successful</div>
        <div class="success-date">${paidAt}</div>
      </div>
    </div>
    <div class="rcpt-no-wrap">
      <div class="rcpt-no-label">Receipt No.</div>
      <div class="rcpt-no-val">${receiptNo}</div>
    </div>
    <hr class="dashed"/>
    <div id="detail-rows"></div>
    <div class="amount-box">
      <div class="amount-label">Total Amount Paid</div>
      <div class="amount-val">${amount}</div>
      <div>
        <span class="paid-badge">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          </svg>
          PAID
        </span>
      </div>
    </div>
    <div class="meta-row">
      <span style="color:#7a9a97;font-weight:600;">Payment via</span>
      <span style="color:#244441;font-weight:700;">PayMongo (GCash / Card)</span>
    </div>
    <div class="meta-row" style="margin-bottom:.5rem">
      <span style="color:#7a9a97;font-weight:600;">Appointment ID</span>
      <span style="color:#244441;font-weight:700;">${apptId}</span>
    </div>
  </div>
  <div class="footer">
    <div class="footer-inner">
      <div class="footer-text">
        Thank you for choosing TELE-CARE.<br/>
        <strong>This serves as official proof of payment.</strong>
      </div>
    </div>
  </div>
</div>
<script>
  const tmp = document.createElement('div');
  tmp.innerHTML = \`${rowsHTML.replace(/`/g, '\\`').replace(/<\/script>/g, '<\\/script>')}\`;
  tmp.querySelectorAll('.receipt-detail-row').forEach(row => {
    row.className = 'detail-row';
    const label = row.querySelector('.receipt-detail-label');
    const val   = row.querySelector('.receipt-detail-val');
    if (label) label.className = 'detail-label';
    if (val)   val.className   = 'detail-val';
  });
  document.getElementById('detail-rows').innerHTML = tmp.innerHTML;
  window.onload = function() { setTimeout(() => { window.print(); window.close(); }, 300); };
<\/script>
</body>
</html>`);
  printWindow.document.close();
}

// ── Auto-refresh summary status while generating ──────────────────────────
// REMOVED: 5-second auto-refresh was causing flickering
// Summary is now checked and updated via check_summary.php polling in endCall()
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>