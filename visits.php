<?php
require_once 'includes/auth.php';

// ── Handle new booking ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $did   = (int)($_POST['doctor_id']   ?? 0);
    $date  = trim($_POST['appt_date']    ?? '');
    $time  = trim($_POST['appt_time']    ?? '');
    $notes = trim($_POST['notes']        ?? '');

    if ($did && $date && $time) {
        // Validate doctor is active
        $doc_chk = $conn->prepare("SELECT id FROM doctors WHERE id=? AND status='active'");
        $doc_chk->bind_param("i", $did);
        $doc_chk->execute();
        $valid_doc = $doc_chk->get_result()->fetch_assoc();

        if ($valid_doc) {
            // Validate chosen date falls on a valid schedule day
            $chosen_day = date('l', strtotime($date));
            $sched_chk  = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id=? AND day_of_week=?");
            $sched_chk->bind_param("is", $did, $chosen_day);
            $sched_chk->execute();
            $valid_day = $sched_chk->get_result()->fetch_assoc();

            if ($valid_day) {
                // Check no duplicate booking same doctor+date+time
                $dup = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('Cancelled')");
                $dup->bind_param("iss", $did, $date, $time);
                $dup->execute();
                $duplicate = $dup->get_result()->fetch_assoc();

                if (!$duplicate) {
                    $db_type = 'Teleconsult';
                    $stmt    = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, type, notes, status, payment_status) VALUES (?,?,?,?,?,?,'Pending','Unpaid')");
                    if ($stmt === false) {
                        $_SESSION['toast_error'] = 'DB prepare error: ' . $conn->error;
                    } else {
                        $stmt->bind_param("iissss", $patient_id, $did, $date, $time, $db_type, $notes);
                        if ($stmt->execute()) {
                            $_SESSION['toast'] = "Appointment booked! Waiting for doctor confirmation.";
                        } else {
                            $_SESSION['toast_error'] = 'Booking failed: ' . $stmt->error;
                        }
                    }
                } else {
                    $_SESSION['toast_error'] = "That time slot is already taken. Please choose another.";
                }
            } else {
                $_SESSION['toast_error'] = "The selected date is not within the doctor's schedule.";
            }
        } else {
            $_SESSION['toast_error'] = "Invalid doctor selected.";
        }
    } else {
        $_SESSION['toast_error'] = "Invalid booking request.";
    }
    header('Location: visits.php'); exit;
}

// ── Fetch visits ──
$visits_upcoming = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC
");
$visits_past = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id AND a.appointment_date < CURDATE()
    ORDER BY a.appointment_date DESC
");

// ── Fetch ALL active doctors + their schedules ──
$all_doctors = [];
$dres = $conn->query("
    SELECT id, full_name, specialty, subspecialty,
           consultation_fee, profile_photo, is_available
    FROM doctors
    WHERE status = 'active'
    ORDER BY full_name ASC
");
if ($dres) {
    while ($dr = $dres->fetch_assoc()) {
        $sid  = (int)$dr['id'];
        $sres = $conn->query("SELECT day_of_week, start_time, end_time FROM doctor_schedules WHERE doctor_id=$sid");
        $dr['schedules'] = [];
        if ($sres) {
            while ($sr = $sres->fetch_assoc()) {
                $dr['schedules'][] = $sr;
            }
        }
        $all_doctors[] = $dr;
    }
}

$toast       = $_SESSION['toast']       ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$page_title = 'My Visits — TELE-CARE';
$active_nav = 'visits';
require_once 'includes/header.php';
?>

<style>
  .inner-tabs{display:flex;gap:0.5rem;margin-bottom:1.2rem;}
  .inner-tab{flex:1;padding:0.6rem;border-radius:50px;border:1.5px solid rgba(63,130,227,0.15);background:transparent;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:0.82rem;font-weight:600;color:var(--muted);transition:all 0.2s;}
  .inner-tab.active{background:var(--blue);color:#fff;border-color:var(--blue);}

  /* ── Booking Modal ── */
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:flex-end;justify-content:center;z-index:300;backdrop-filter:blur(4px);padding:0;}
  .modal-overlay.open{display:flex;}
  .modal-sheet{background:#fff;border-radius:24px 24px 0 0;width:100%;max-width:520px;max-height:92vh;overflow-y:auto;padding:1.6rem 1.4rem 2rem;animation:slideUp 0.3s cubic-bezier(.16,1,.3,1);}
  @keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
  .modal-handle{width:40px;height:4px;background:rgba(0,0,0,0.1);border-radius:2px;margin:0 auto 1.2rem;}
  .modal-title{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:800;margin-bottom:0.3rem;}
  .modal-sub{font-size:0.8rem;color:var(--muted);margin-bottom:1.4rem;}

  /* Doctor selector */
  .doc-search{width:100%;padding:0.65rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--green);outline:none;margin-bottom:0.8rem;transition:border-color 0.2s;}
  .doc-search:focus{border-color:var(--blue);}
  .doc-select-grid{display:flex;flex-direction:column;gap:0.6rem;margin-bottom:1.2rem;max-height:55vh;overflow-y:auto;}
  .doc-option{display:flex;align-items:center;gap:0.8rem;padding:0.8rem 1rem;border-radius:14px;border:1.5px solid rgba(36,68,65,0.1);cursor:pointer;transition:all 0.2s;background:#fff;}
  .doc-option:hover{border-color:var(--blue);background:rgba(63,130,227,0.04);}
  .doc-option.selected{border-color:var(--blue);background:rgba(63,130,227,0.08);}
  .doc-option.unavailable{opacity:0.45;cursor:not-allowed;}
  .doc-mini-avatar{width:40px;height:40px;border-radius:12px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;flex-shrink:0;overflow:hidden;}
  .doc-mini-avatar img{width:100%;height:100%;object-fit:cover;}

  /* Steps */
  .booking-step{display:none;}
  .booking-step.active{display:block;}
  .step-label{font-size:0.7rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin-bottom:0.6rem;}

  /* Calendar */
  .cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;}
  .cal-nav{background:none;border:none;cursor:pointer;color:var(--green);padding:0.3rem;border-radius:8px;transition:background 0.15s;}
  .cal-nav:hover{background:rgba(36,68,65,0.07);}
  .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}
  .cal-day-name{text-align:center;font-size:0.65rem;font-weight:700;color:var(--muted);padding:0.3rem 0;letter-spacing:0.05em;}
  .cal-cell{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:0.82rem;font-weight:500;cursor:default;color:rgba(36,68,65,0.3);}
  .cal-cell.available{color:var(--green);background:rgba(36,68,65,0.05);cursor:pointer;font-weight:600;}
  .cal-cell.available:hover{background:rgba(63,130,227,0.1);color:var(--blue);}
  .cal-cell.selected{background:var(--blue)!important;color:#fff!important;}
  .cal-cell.today{border:1.5px solid var(--blue);color:var(--blue);}
  .cal-cell.past{color:rgba(36,68,65,0.15);}

  /* Time slots */
  .time-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;margin-bottom:1rem;}
  .time-slot{padding:0.55rem 0;border-radius:10px;border:1.5px solid rgba(36,68,65,0.1);text-align:center;font-size:0.8rem;font-weight:600;cursor:pointer;color:var(--green);transition:all 0.15s;}
  .time-slot:hover{border-color:var(--blue);color:var(--blue);background:rgba(63,130,227,0.05);}
  .time-slot.selected{background:var(--blue);color:#fff;border-color:var(--blue);}
  .time-slot.booked{background:rgba(0,0,0,0.04);color:rgba(36,68,65,0.25);cursor:not-allowed;border-color:transparent;}

  /* Form fields */
  .bk-label{display:block;font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--muted);margin-bottom:0.4rem;}
  .bk-input{width:100%;padding:0.7rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--green);outline:none;transition:border-color 0.2s;background:#fff;}
  .bk-input:focus{border-color:var(--blue);}
  .bk-field{margin-bottom:0.8rem;}

  /* Buttons */
  .btn-book-main{width:100%;padding:0.85rem;border-radius:50px;background:var(--blue);color:#fff;font-weight:700;font-size:0.9rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.25s;box-shadow:0 4px 14px rgba(63,130,227,0.3);}
  .btn-book-main:hover{background:#2d6fd4;transform:translateY(-1px);}
  .btn-book-main:disabled{background:rgba(36,68,65,0.12);color:var(--muted);box-shadow:none;cursor:not-allowed;transform:none;}
  .btn-back{background:none;border:none;color:var(--muted);font-size:0.82rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;padding:0.5rem 0;display:inline-flex;align-items:center;gap:0.3rem;margin-bottom:0.8rem;}
  .btn-back:hover{color:var(--green);}

  /* Summary box */
  .summary-box{background:rgba(63,130,227,0.06);border:1px solid rgba(63,130,227,0.15);border-radius:14px;padding:1rem 1.1rem;margin-bottom:1rem;}
  .summary-row{display:flex;justify-content:space-between;align-items:center;font-size:0.83rem;padding:0.25rem 0;}
  .summary-row:not(:last-child){border-bottom:1px solid rgba(63,130,227,0.08);}
  .summary-label{color:var(--muted);font-weight:600;}
  .summary-val{color:var(--green);font-weight:700;text-align:right;}

  /* Toast */
  .toast-bar{position:fixed;bottom:5rem;left:50%;transform:translateX(-50%);z-index:400;padding:0.75rem 1.4rem;border-radius:16px;font-size:0.82rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.15);white-space:normal;max-width:88vw;text-align:center;animation:toastIn 0.3s ease;}
  .toast-bar.success{background:var(--green);color:#fff;animation:toastIn 0.3s ease,toastOut 0.4s 3s ease forwards;}
  .toast-bar.error{background:#C33643;color:#fff;}
  @keyframes toastIn{from{opacity:0;transform:translateX(-50%) translateY(12px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
  @keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}
</style>

<?php if ($toast): ?>
<div class="toast-bar success">✓ <?= htmlspecialchars($toast) ?></div>
<?php endif; ?>
<?php if ($toast_error): ?>
<div class="toast-bar error">✕ <?= htmlspecialchars($toast_error) ?></div>
<?php endif; ?>

<div class="page">
  <!-- Header row -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;">
    <h2 style="font-size:1.5rem;margin:0;">My Appointments</h2>
    <button onclick="openBookingModal()" style="display:inline-flex;align-items:center;gap:0.4rem;background:var(--blue);color:#fff;padding:0.55rem 1.1rem;border-radius:50px;font-size:0.8rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 12px rgba(63,130,227,0.25);">
      <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Book
    </button>
  </div>

  <div class="inner-tabs">
    <button class="inner-tab active" id="btn-upcoming" onclick="switchTab('upcoming')">Upcoming</button>
    <button class="inner-tab"        id="btn-past"     onclick="switchTab('past')">Past</button>
  </div>

  <!-- Upcoming -->
  <div id="visits-upcoming">
    <div class="card" style="padding:0.5rem 1.4rem;">
      <?php
      $has = false;
      if ($visits_upcoming && $visits_upcoming->num_rows > 0):
        while ($a = $visits_upcoming->fetch_assoc()):
          $has = true;
          $d   = new DateTime($a['appointment_date']);
      ?>
      <div class="appt-item">
        <div class="appt-date-box">
          <div class="day"><?= $d->format('d') ?></div>
          <div class="mon"><?= $d->format('M') ?></div>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:0.92rem;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($a['specialty'] ?? '') ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
          <?php if (!empty($a['notes'])): ?>
          <div style="font-size:0.78rem;color:#9ab0ae;margin-top:0.2rem;">📝 <?= htmlspecialchars($a['notes']) ?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
          <span class="badge <?= $a['status']==='Confirmed'?'badge-green':($a['status']==='Pending'?'badge-orange':'badge-red') ?>"><?= $a['status'] ?></span>
          <span class="badge <?= $a['payment_status']==='Paid'?'badge-green':'badge-red' ?>"><?= $a['payment_status'] ?></span>
        </div>
      </div>
      <?php endwhile; endif; ?>
      <?php if (!$has): ?>
      <div class="empty-state">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        No upcoming appointments.
        <button onclick="openBookingModal()" style="margin-top:0.8rem;padding:0.5rem 1.2rem;background:var(--blue);color:#fff;border:none;border-radius:50px;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;">Book Now</button>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Past -->
  <div id="visits-past" style="display:none;">
    <div class="card" style="padding:0.5rem 1.4rem;">
      <?php
      $has = false;
      if ($visits_past && $visits_past->num_rows > 0):
        while ($a = $visits_past->fetch_assoc()):
          $has = true;
          $d   = new DateTime($a['appointment_date']);
      ?>
      <div class="appt-item">
        <div class="appt-date-box" style="background:rgba(36,68,65,0.04);">
          <div class="day" style="color:#9ab0ae;"><?= $d->format('d') ?></div>
          <div class="mon"><?= $d->format('M') ?></div>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:0.92rem;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
        </div>
        <span class="badge <?= $a['status']==='Completed'?'badge-green':'badge-red' ?>"><?= $a['status'] ?></span>
      </div>
      <?php endwhile; endif; ?>
      <?php if (!$has): ?>
      <div class="empty-state">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        No past appointments.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════
     BOOKING MODAL
════════════════════════════════ -->
<div class="modal-overlay" id="booking-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>

    <!-- Step 1: Choose Doctor -->
    <div class="booking-step active" id="step-1">
      <div class="modal-title">Book a Teleconsultation</div>
      <div class="modal-sub">Choose a doctor to schedule your online consultation.</div>

      <input type="text" class="doc-search" id="doc-search-input" placeholder="Search by name or specialty…" oninput="filterDoctors(this.value)"/>

      <div class="doc-select-grid" id="doc-list">
        <?php foreach ($all_doctors as $dr):
          $initials    = strtoupper(substr($dr['full_name'],0,1).(strpos($dr['full_name'],' ')!==false ? substr($dr['full_name'],strpos($dr['full_name'],' ')+1,1) : ''));
          $unavailable = !$dr['is_available'] || empty($dr['schedules']);
          $specialty   = htmlspecialchars($dr['specialty'] ?? '');
          $subspecialty = !empty($dr['subspecialty']) ? htmlspecialchars($dr['subspecialty']) : '';
        ?>
        <div class="doc-option <?= $unavailable ? 'unavailable' : '' ?>"
             data-name="<?= strtolower(htmlspecialchars($dr['full_name'])) ?>"
             data-specialty="<?= strtolower($specialty) ?>"
             onclick="<?= $unavailable ? '' : "selectDoctor({$dr['id']})" ?>">
          <div class="doc-mini-avatar">
            <?php if (!empty($dr['profile_photo'])): ?>
              <img src="../<?= htmlspecialchars($dr['profile_photo']) ?>"/>
            <?php else: echo $initials; endif; ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Dr. <?= htmlspecialchars($dr['full_name']) ?></div>
            <div style="font-size:0.75rem;color:var(--muted);"><?= $specialty ?><?= $subspecialty ? ' · <em>'.$subspecialty.'</em>' : '' ?></div>
            <?php if (!empty($dr['consultation_fee'])): ?>
            <div style="font-size:0.73rem;color:var(--blue);font-weight:600;margin-top:0.1rem;">₱<?= number_format($dr['consultation_fee'], 2) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($unavailable): ?>
          <span style="font-size:0.68rem;color:var(--muted);font-weight:600;flex-shrink:0;">Unavailable</span>
          <?php else: ?>
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--muted);flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div id="doc-no-results" style="display:none;text-align:center;padding:1.5rem;font-size:0.85rem;color:var(--muted);">No doctors found.</div>
      </div>
      <button class="btn-book-main" style="background:transparent;color:var(--muted);box-shadow:none;border:1.5px solid rgba(36,68,65,0.12);" onclick="closeBookingModal()">Cancel</button>
    </div>

    <!-- Step 2: Pick Date -->
    <div class="booking-step" id="step-2">
      <button class="btn-back" onclick="goStep(1)">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back
      </button>
      <div class="modal-title" id="step2-title">Pick a Date</div>
      <div class="modal-sub" id="step2-sub"></div>

      <div class="step-label">Available Days</div>
      <div id="avail-days-chips" style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:1rem;"></div>

      <div style="background:rgba(36,68,65,0.03);border-radius:16px;padding:1rem;">
        <div class="cal-header">
          <button class="cal-nav" onclick="calNav(-1)">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <div style="font-weight:700;font-size:0.9rem;" id="cal-month-label"></div>
          <button class="cal-nav" onclick="calNav(1)">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
        <div class="cal-grid" id="cal-grid"></div>
      </div>

      <button class="btn-book-main" id="btn-to-step3" style="margin-top:1rem;" disabled onclick="goStep(3)">Continue to Time →</button>
    </div>

    <!-- Step 3: Pick Time -->
    <div class="booking-step" id="step-3">
      <button class="btn-back" onclick="goStep(2)">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back
      </button>
      <div class="modal-title">Pick a Time</div>
      <div class="modal-sub" id="step3-sub"></div>
      <div class="step-label">Available Slots</div>
      <div class="time-grid" id="time-grid"></div>
      <button class="btn-book-main" id="btn-to-step4" disabled onclick="goStep(4)">Continue →</button>
    </div>

    <!-- Step 4: Confirm -->
    <div class="booking-step" id="step-4">
      <button class="btn-back" onclick="goStep(3)">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back
      </button>
      <div class="modal-title">Confirm Booking</div>
      <div class="modal-sub">Review your teleconsultation request before sending.</div>

      <div class="summary-box" id="booking-summary"></div>

      <form method="POST" id="booking-form">
        <input type="hidden" name="book_appointment" value="1"/>
        <input type="hidden" name="doctor_id"  id="f-doctor-id"/>
        <input type="hidden" name="appt_date"  id="f-date"/>
        <input type="hidden" name="appt_time"  id="f-time"/>
        <div class="bk-field">
          <label class="bk-label">Consultation Type</label>
          <div style="display:flex;align-items:center;gap:0.6rem;padding:0.7rem 0.9rem;background:rgba(63,130,227,0.06);border:1.5px solid rgba(63,130,227,0.2);border-radius:12px;">
            <span style="font-size:1.1rem;">&#128249;</span>
            <div>
              <div style="font-weight:700;font-size:0.88rem;color:var(--blue);">Teleconsultation</div>
              <div style="font-size:0.72rem;color:var(--muted);margin-top:0.1rem;">Online video / virtual consultation</div>
            </div>
          </div>
        </div>
        <div class="bk-field">
          <label class="bk-label">Notes / Symptoms <span style="font-weight:400;text-transform:none;font-size:0.68rem;">(optional)</span></label>
          <textarea name="notes" class="bk-input" rows="3" placeholder="e.g. Fever for 3 days, headache..."></textarea>
        </div>
        <button type="submit" class="btn-book-main">📹 Confirm Teleconsultation Request</button>
      </form>
    </div>

  </div>
</div>

<!-- ── Doctor data for JS ── -->
<script>
const DOCTORS_JS = <?= json_encode(array_values($all_doctors), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

const BOOKED_SLOTS = {};
<?php foreach ($all_doctors as $dr):
  $did2  = (int)$dr['id'];
  $bres  = $conn->query("SELECT appointment_date, appointment_time FROM appointments WHERE doctor_id=$did2 AND status NOT IN ('Cancelled') AND appointment_date >= CURDATE()");
  $booked = [];
  if ($bres) { while ($b = $bres->fetch_assoc()) $booked[] = ['date'=>$b['appointment_date'],'time'=>$b['appointment_time']]; }
?>
BOOKED_SLOTS[<?= $did2 ?>] = <?= json_encode($booked) ?>;
<?php endforeach; ?>

// ── State ──
let selDoctor = null, selDate = null, selTime = null;
let calYear, calMonth;

// ── Tab switch ──
function switchTab(type) {
  document.getElementById('visits-upcoming').style.display = type==='upcoming' ? 'block' : 'none';
  document.getElementById('visits-past').style.display     = type==='past'     ? 'block' : 'none';
  document.getElementById('btn-upcoming').classList.toggle('active', type==='upcoming');
  document.getElementById('btn-past').classList.toggle('active',     type==='past');
}

// ── Doctor search filter ──
function filterDoctors(query) {
  const q     = query.toLowerCase().trim();
  const opts  = document.querySelectorAll('#doc-list .doc-option');
  let visible = 0;
  opts.forEach(el => {
    const name = el.dataset.name || '';
    const spec = el.dataset.specialty || '';
    const show = !q || name.includes(q) || spec.includes(q);
    el.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('doc-no-results').style.display = visible === 0 ? 'block' : 'none';
}

// ── Modal open/close ──
function openBookingModal() {
  document.getElementById('booking-modal').classList.add('open');
  document.getElementById('doc-search-input').value = '';
  filterDoctors('');
  goStep(1);
}
function closeBookingModal() {
  document.getElementById('booking-modal').classList.remove('open');
  resetBooking();
}
document.getElementById('booking-modal').addEventListener('click', e => {
  if (e.target.id === 'booking-modal') closeBookingModal();
});

function resetBooking() { selDoctor=null; selDate=null; selTime=null; }

// ── Step navigation ──
function goStep(n) {
  document.querySelectorAll('.booking-step').forEach(s => s.classList.remove('active'));
  document.getElementById('step-'+n).classList.add('active');
  if (n===2) renderCalendar();
  if (n===3) renderTimeSlots();
  if (n===4) renderSummary();
}

// ── Select doctor ──
function selectDoctor(id) {
  selDoctor = DOCTORS_JS.find(d => d.id == id);
  selDate   = null; selTime = null;
  document.querySelectorAll('.doc-option').forEach(el => el.classList.remove('selected'));
  event.currentTarget.classList.add('selected');

  document.getElementById('step2-title').textContent = 'Dr. ' + selDoctor.full_name;
  document.getElementById('step2-sub').textContent   =
    (selDoctor.specialty || '') +
    (selDoctor.consultation_fee ? ' · ₱' + parseFloat(selDoctor.consultation_fee).toLocaleString() : '');

  const chips = document.getElementById('avail-days-chips');
  chips.innerHTML = selDoctor.schedules.map(s =>
    `<span style="background:rgba(63,130,227,0.1);color:var(--blue);border-radius:50px;padding:0.25rem 0.7rem;font-size:0.72rem;font-weight:700;">${s.day_of_week} ${fmt12h(s.start_time)}–${fmt12h(s.end_time)}</span>`
  ).join('');

  const now = new Date();
  calYear = now.getFullYear(); calMonth = now.getMonth();
  document.getElementById('btn-to-step3').disabled = true;
  goStep(2);
}

// ── Calendar ──
const DAY_NAMES   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function calNav(dir) {
  calMonth += dir;
  if (calMonth > 11) { calMonth = 0; calYear++; }
  else if (calMonth < 0) { calMonth = 11; calYear--; }
  renderCalendar();
}

function renderCalendar() {
  if (!selDoctor) return;
  document.getElementById('cal-month-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
  const grid      = document.getElementById('cal-grid');
  const availDays = selDoctor.schedules.map(s => s.day_of_week);
  const today     = new Date(); today.setHours(0,0,0,0);
  const firstDay  = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth+1, 0).getDate();

  grid.innerHTML = DAY_NAMES.map(d => `<div class="cal-day-name">${d}</div>`).join('');
  for (let i = 0; i < firstDay; i++) grid.innerHTML += `<div class="cal-cell"></div>`;

  for (let day = 1; day <= daysInMonth; day++) {
    const d = new Date(calYear, calMonth, day); d.setHours(0,0,0,0);
    const dayName = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][d.getDay()];
    const dateStr = calYear + '-' + String(calMonth+1).padStart(2,'0') + '-' + String(day).padStart(2,'0');
    const isPast  = d < today;
    const isAvail = !isPast && availDays.includes(dayName);
    const isSel   = selDate === dateStr;
    const isToday = d.getTime() === today.getTime();

    let cls = 'cal-cell';
    if (isAvail) cls += ' available';
    else if (isPast) cls += ' past';
    if (isSel && isAvail) cls += ' selected';
    if (isToday && !isSel) cls += ' today';

    const onclick = isAvail ? `pickDate('${dateStr}','${dayName}')` : '';
    grid.innerHTML += `<div class="${cls}" ${onclick ? `onclick="${onclick}"` : ''}>${day}</div>`;
  }
}

function pickDate(dateStr, dayName) {
  selDate = dateStr; selTime = null;
  renderCalendar();
  document.getElementById('btn-to-step3').disabled = false;
  document.getElementById('step3-sub').textContent = formatDateDisplay(dateStr) + ' — choose a time';
}

// ── Time Slots ──
function renderTimeSlots() {
  if (!selDoctor || !selDate) return;
  const dayName = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][new Date(selDate+'T00:00:00').getDay()];
  const sched   = selDoctor.schedules.find(s => s.day_of_week === dayName);
  const booked  = (BOOKED_SLOTS[selDoctor.id] || []).filter(b => b.date === selDate).map(b => b.time.slice(0,5));
  const grid    = document.getElementById('time-grid');
  grid.innerHTML = '';

  if (!sched) {
    grid.innerHTML = '<div style="grid-column:1/-1;color:var(--muted);font-size:0.82rem;">No schedule for this day.</div>';
    return;
  }

  const slots = generateSlots(sched.start_time, sched.end_time);
  if (slots.length === 0) {
    grid.innerHTML = '<div style="grid-column:1/-1;color:var(--muted);font-size:0.82rem;">No time slots available.</div>';
    return;
  }

  slots.forEach(t => {
    const isBooked = booked.includes(t);
    const isSel    = selTime === t;
    const cls      = 'time-slot' + (isBooked ? ' booked' : '') + (isSel ? ' selected' : '');
    const onclick  = isBooked ? '' : `pickTime('${t}')`;
    grid.innerHTML += `<div class="${cls}" ${onclick ? `onclick="${onclick}"` : ''}>${fmt12h(t)}</div>`;
  });

  document.getElementById('btn-to-step4').disabled = !selTime;
}

function pickTime(t) {
  selTime = t;
  renderTimeSlots();
  document.getElementById('btn-to-step4').disabled = false;
}

function generateSlots(start, end) {
  const slots = [];
  let [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number);
  const endMins  = eh * 60 + em;
  while (sh * 60 + sm < endMins) {
    slots.push(String(sh).padStart(2,'0') + ':' + String(sm).padStart(2,'0'));
    sm += 60; // 1-hour slots
    if (sm >= 60) { sh++; sm -= 60; }
    if (sh >= 24) break;
  }
  return slots;
}

// ── Summary ──
function renderSummary() {
  if (!selDoctor || !selDate || !selTime) return;
  document.getElementById('f-doctor-id').value = selDoctor.id;
  document.getElementById('f-date').value       = selDate;
  document.getElementById('f-time').value       = selTime + ':00';

  const fee = selDoctor.consultation_fee
    ? '₱' + parseFloat(selDoctor.consultation_fee).toLocaleString()
    : 'To be confirmed';

  document.getElementById('booking-summary').innerHTML = `
    <div class="summary-row"><span class="summary-label">Doctor</span><span class="summary-val">Dr. ${escHtml(selDoctor.full_name)}</span></div>
    <div class="summary-row"><span class="summary-label">Type</span><span class="summary-val" style="color:var(--blue);">&#128249; Teleconsultation</span></div>
    <div class="summary-row"><span class="summary-label">Date</span><span class="summary-val">${formatDateDisplay(selDate)}</span></div>
    <div class="summary-row"><span class="summary-label">Time</span><span class="summary-val">${fmt12h(selTime)}</span></div>
    <div class="summary-row"><span class="summary-label">Fee</span><span class="summary-val">${fee}</span></div>
  `;
}

// ── Helpers ──
function fmt12h(t) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  const ampm = h >= 12 ? 'PM' : 'AM', hr = h % 12 || 12;
  return hr + ':' + (m || 0).toString().padStart(2,'0') + ' ' + ampm;
}
function formatDateDisplay(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', {
    weekday:'long', month:'long', day:'numeric', year:'numeric'
  });
}
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Toast auto-dismiss
document.querySelectorAll('.toast-bar.success').forEach(t => setTimeout(() => t.remove(), 3500));
document.querySelectorAll('.toast-bar.error').forEach(t => {
  t.title = 'Tap to dismiss';
  t.style.cursor = 'pointer';
  t.addEventListener('click', () => t.remove());
});
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>