<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';
// visits.php (for patients)
// ── Handle new booking ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $did   = (int)($_POST['doctor_id']   ?? 0);
    $date  = trim($_POST['appt_date']    ?? '');
    $time  = trim($_POST['appt_time']    ?? '');
    $notes = trim($_POST['notes']        ?? '');

    if ($did && $date && $time) {
        $doc_chk = $conn->prepare("SELECT id FROM doctors WHERE id=? AND status='active'");
        $doc_chk->bind_param("i", $did);
        $doc_chk->execute();
        $valid_doc = $doc_chk->get_result()->fetch_assoc();

        if ($valid_doc) {
            $chosen_day = date('l', strtotime($date));
            $sched_chk  = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id=? AND day_of_week=?");
            $sched_chk->bind_param("is", $did, $chosen_day);
            $sched_chk->execute();
            $valid_day = $sched_chk->get_result()->fetch_assoc();

            if ($valid_day) {
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
                            $_SESSION['toast'] = "Appointment requested! Waiting for doctor's acceptance.";
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
    SELECT a.*, d.full_name AS doctor_name, d.specialty, d.consultation_fee
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id
      AND a.appointment_date >= CURDATE()
      AND a.status NOT IN ('Cancelled', 'Completed')
    ORDER BY a.appointment_date ASC
");
$visits_past = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id
    AND (a.appointment_date < CURDATE() OR a.status = 'Completed')
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

// ── Fetch ALL active doctors + their schedules ──
$all_doctors = [];
$dres = $conn->query("SELECT id, full_name, specialty, subspecialty, consultation_fee, profile_photo, is_available FROM doctors WHERE status = 'active' ORDER BY full_name ASC");
if ($dres) {
    while ($dr = $dres->fetch_assoc()) {
        $sid  = (int)$dr['id'];
        $sres = $conn->query("SELECT day_of_week, start_time, end_time FROM doctor_schedules WHERE doctor_id=$sid");
        $dr['schedules'] = [];
        if ($sres) { while ($sr = $sres->fetch_assoc()) $dr['schedules'][] = $sr; }
        $all_doctors[] = $dr;
    }
}

$toast       = $_SESSION['toast']       ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$page_title = 'My Visits — TELE-CARE';
$active_nav = 'visits';
require_once 'includes/header.php';

function isCallActive(string $date, string $time): bool {
    $appt = strtotime($date . ' ' . $time);
    $now  = time();
    return $now >= ($appt - 900) && $now <= ($appt + 3600);
}
?>

<style>
/* ── PAGE LAYOUT ── */
.page {
  max-width: 1160px !important;
  margin: 0 auto !important;
  padding: 1.8rem 2rem 5rem !important;
  background: transparent !important;
}

/* ── PAGE HEADER ── */
.visits-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.4rem;
  padding-bottom: 1.2rem;
  border-bottom: 1px solid rgba(36,68,65,0.08);
}
.visits-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.75rem; font-weight: 900; color: #244441; line-height: 1;
}
.visits-title span {
  display: block; font-family: 'DM Sans', sans-serif;
  font-size: 0.78rem; font-weight: 500; color: #9ab0ae;
  margin-top: 0.3rem; letter-spacing: 0;
}
.book-btn {
  display: inline-flex; align-items: center; gap: 0.45rem;
  background: #3F82E3; color: #fff;
  padding: 0.65rem 1.4rem; border-radius: 50px;
  font-size: 0.84rem; font-weight: 700;
  border: none; cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  box-shadow: 0 4px 16px rgba(63,130,227,0.3);
  transition: all 0.25s cubic-bezier(0.16,1,0.3,1);
}
.book-btn:hover { background: #2d6fd4; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(63,130,227,0.4); }
.book-btn svg { flex-shrink: 0; }

/* ── TABS ── */
.inner-tabs {
  display: flex; gap: 0; margin-bottom: 1.4rem;
  background: rgba(36,68,65,0.05); border-radius: 12px; padding: 4px;
  width: fit-content;
}
.inner-tab {
  padding: 0.52rem 1.5rem; border-radius: 9px;
  border: none; background: transparent;
  cursor: pointer; font-family: 'DM Sans', sans-serif;
  font-size: 0.84rem; font-weight: 600;
  color: #9ab0ae; transition: all 0.2s;
}
.inner-tab.active {
  background: #fff; color: #244441;
  box-shadow: 0 2px 8px rgba(36,68,65,0.1);
}

/* ── APPOINTMENT CARD ── */
.appt-card {
  background: #fff;
  border: 1px solid rgba(36,68,65,0.08);
  border-radius: 16px;
  margin-bottom: 0.9rem;
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
  position: relative;
}
.appt-card::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
}
.appt-card.status-pending::before    { background: #f59e0b; }
.appt-card.status-approved::before   { background: #3F82E3; }
.appt-card.status-confirmed::before  { background: #7c3aed; }
.appt-card.status-confirmed-paid::before { background: #16a34a; }
.appt-card.status-completed::before  { background: #9ab0ae; }
.appt-card.status-cancelled::before  { background: #C33643; }
.appt-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(36,68,65,0.09); }

.appt-card-inner {
  display: grid;
  grid-template-columns: 68px 1fr auto;
  gap: 1.2rem;
  padding: 1.2rem 1.4rem;
  align-items: start;
}

/* Date box */
.appt-date-box {
  width: 56px; height: 64px;
  background: rgba(63,130,227,0.07);
  border: 1.5px solid rgba(63,130,227,0.15);
  border-radius: 14px;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  flex-shrink: 0;
}
.appt-date-box .day {
  font-family: 'Playfair Display', serif;
  font-size: 1.4rem; font-weight: 900;
  color: #3F82E3; line-height: 1.1;
}
.appt-date-box .mon {
  font-size: 0.6rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #9ab0ae;
}
.appt-date-box.past-box {
  background: rgba(36,68,65,0.04);
  border-color: rgba(36,68,65,0.1);
}
.appt-date-box.past-box .day { color: #9ab0ae; }

/* Center info */
.appt-main-info {}
.appt-doctor-name {
  font-weight: 700; font-size: 0.98rem; color: #244441; margin-bottom: 0.18rem;
}
.appt-meta-row {
  display: flex; align-items: center; gap: 0.5rem;
  font-size: 0.78rem; color: #9ab0ae; margin-bottom: 0.5rem; flex-wrap: wrap;
}
.appt-meta-row svg { width: 12px; height: 12px; flex-shrink: 0; }
.appt-meta-sep { color: rgba(36,68,65,0.15); }
.appt-notes {
  display: flex; align-items: flex-start; gap: 0.35rem;
  font-size: 0.76rem; color: #9ab0ae;
  background: rgba(36,68,65,0.03);
  border-radius: 8px; padding: 0.4rem 0.65rem;
  margin-bottom: 0.6rem; width: fit-content; max-width: 100%;
}
.appt-notes svg { width: 11px; height: 11px; flex-shrink: 0; margin-top: 1px; }

/* Actions area */
.appt-actions {
  display: flex; flex-wrap: wrap; gap: 0.45rem;
  align-items: center; margin-top: 0.5rem;
}

/* Right badges column */
.appt-badges {
  display: flex; flex-direction: column; gap: 0.35rem;
  align-items: flex-end; flex-shrink: 0; padding-top: 0.1rem;
}

/* ── PAYMENT NOTICE STRIP ── */
.pay-notice {
  background: linear-gradient(135deg, rgba(124,58,237,0.06), rgba(63,130,227,0.05));
  border-top: 1px solid rgba(124,58,237,0.12);
  padding: 0.85rem 1.4rem 0.85rem 5.4rem;
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  flex-wrap: wrap;
}
.pay-notice-text {
  font-size: 0.82rem; font-weight: 600; color: #5b21b6;
  display: flex; align-items: center; gap: 0.5rem;
}
.pay-notice-text svg { width: 14px; height: 14px; flex-shrink: 0; color: #7c3aed; }

/* ── BADGES ── */
.badge {
  font-size: 0.63rem; font-weight: 700; letter-spacing: 0.06em;
  text-transform: uppercase; padding: 0.22rem 0.62rem; border-radius: 50px;
  white-space: nowrap;
}
.badge-green  { background: rgba(34,197,94,0.12);  color: #16a34a; }
.badge-orange { background: rgba(245,158,11,0.12); color: #ca8a04; }
.badge-red    { background: rgba(195,54,67,0.1);   color: #C33643; }
.badge-blue   { background: rgba(63,130,227,0.1);  color: #2563eb; }
.badge-purple { background: rgba(124,58,237,0.1);  color: #7c3aed; }

/* ── ACTION BUTTONS ── */
.join-call-btn {
  display: inline-flex; align-items: center; gap: 0.42rem;
  background: linear-gradient(135deg, #16a34a, #15803d);
  color: #fff; padding: 0.52rem 1.1rem; border-radius: 50px;
  font-size: 0.78rem; font-weight: 700; text-decoration: none;
  box-shadow: 0 4px 14px rgba(22,163,74,0.35);
  animation: callPulse 2s ease-in-out infinite;
}
@keyframes callPulse {
  0%,100% { box-shadow: 0 4px 14px rgba(22,163,74,0.35); }
  50%      { box-shadow: 0 4px 22px rgba(22,163,74,0.6); }
}
.call-soon {
  display: inline-flex; align-items: center; gap: 0.38rem;
  font-size: 0.75rem; color: #d97706; font-weight: 600;
  background: rgba(245,158,11,0.09); border: 1px solid rgba(245,158,11,0.2);
  border-radius: 50px; padding: 0.28rem 0.75rem;
}
.pay-now-btn {
  display: inline-flex; align-items: center; gap: 0.42rem;
  background: linear-gradient(135deg, #7c3aed, #6d28d9);
  color: #fff; padding: 0.52rem 1.1rem; border-radius: 50px;
  font-size: 0.8rem; font-weight: 700; text-decoration: none;
  box-shadow: 0 4px 14px rgba(124,58,237,0.3);
  transition: all 0.2s;
}
.pay-now-btn:hover { background: linear-gradient(135deg,#6d28d9,#5b21b6); transform: translateY(-1px); }
.receipt-btn {
  display: inline-flex; align-items: center; gap: 0.38rem;
  background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.22);
  color: #15803d; padding: 0.48rem 0.95rem; border-radius: 50px;
  font-size: 0.78rem; font-weight: 700; text-decoration: none; transition: all 0.2s;
}
.receipt-btn:hover { background: rgba(34,197,94,0.15); }
.summary-btn {
  display: inline-flex; align-items: center; gap: 0.38rem;
  background: rgba(63,130,227,0.08); border: 1px solid rgba(63,130,227,0.2);
  color: #2563eb; padding: 0.48rem 0.95rem; border-radius: 50px;
  font-size: 0.78rem; font-weight: 700; text-decoration: none; transition: all 0.2s;
}
.summary-btn:hover { background: rgba(63,130,227,0.15); }
.summary-generating {
  display: inline-flex; align-items: center; gap: 0.38rem;
  background: rgba(245,158,11,0.07); border: 1px dashed rgba(245,158,11,0.32);
  color: #d97706; padding: 0.38rem 0.82rem; border-radius: 50px;
  font-size: 0.73rem; font-weight: 600;
}
.call-window-note {
  display: inline-flex; align-items: center; gap: 0.38rem;
  font-size: 0.73rem; color: #9ab0ae; font-weight: 500;
}
.call-window-note svg { width: 12px; height: 12px; }

@keyframes spin { to { transform: rotate(360deg); } }
.spin-icon { animation: spin 1.4s linear infinite; display: inline-block; }

/* ── FLOW PILLS ── */
.flow-pill {
  display: inline-flex; align-items: center; gap: 0.32rem;
  border-radius: 50px; padding: 0.24rem 0.7rem;
  font-size: 0.7rem; font-weight: 700; border: 1px solid;
}
.flow-pending          { background:rgba(245,158,11,0.08);  border-color:rgba(245,158,11,0.25);  color:#d97706; }
.flow-doctor-approved  { background:rgba(63,130,227,0.08);  border-color:rgba(63,130,227,0.25);  color:#2563eb; }
.flow-confirmed-unpaid { background:rgba(124,58,237,0.08);  border-color:rgba(124,58,237,0.25);  color:#7c3aed; }
.flow-confirmed-paid   { background:rgba(34,197,94,0.08);   border-color:rgba(34,197,94,0.25);   color:#16a34a; }

/* ── BOOKING FLOW STRIP ── */
.flow-strip {
  display: flex; align-items: center; gap: 0.3rem;
  flex-wrap: wrap; row-gap: 0.3rem;
}
.flow-step {
  padding: 0.2rem 0.55rem; border-radius: 50px;
  font-size: 0.72rem; font-weight: 700;
}
.flow-arrow { color: #9ab0ae; font-size: 0.7rem; }

/* ── EMPTY STATE ── */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; text-align: center;
  padding: 3.5rem 2rem; color: #b8cccb; gap: 0.8rem;
}
.empty-state svg { width: 42px; height: 42px; opacity: 0.3; }
.empty-state p { font-size: 0.88rem; font-weight: 500; }

/* ── TOAST ── */
.toast-bar {
  position: fixed; bottom: 5.5rem; left: 50%;
  transform: translateX(-50%); z-index: 400;
  padding: 0.78rem 1.5rem; border-radius: 16px;
  font-size: 0.84rem; font-weight: 600;
  box-shadow: 0 8px 28px rgba(0,0,0,0.15);
  max-width: 88vw; text-align: center;
  display: flex; align-items: center; gap: 0.5rem;
  white-space: nowrap;
}
.toast-bar.success { background: #244441; color: #fff; animation: toastIn 0.3s ease, toastOut 0.4s 3s ease forwards; }
.toast-bar.error   { background: #C33643; color: #fff; }
@keyframes toastIn  { from { opacity:0; transform:translateX(-50%) translateY(12px); } to { opacity:1; transform:translateX(-50%) translateY(0); } }
@keyframes toastOut { from { opacity:1; } to { opacity:0; pointer-events:none; } }

/* ── MODAL ── */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: none; align-items: flex-end; justify-content: center;
  z-index: 300; backdrop-filter: blur(6px); padding: 0;
}
.modal-overlay.open { display: flex; }
.modal-sheet {
  background: #fff; border-radius: 24px 24px 0 0;
  width: 100%; max-width: 540px; max-height: 92vh;
  overflow-y: auto; padding: 1.6rem 1.5rem 2rem;
  animation: slideUp 0.32s cubic-bezier(.16,1,.3,1);
}
@keyframes slideUp { from { transform:translateY(100%); } to { transform:translateY(0); } }
.modal-handle { width: 40px; height: 4px; background: rgba(0,0,0,0.1); border-radius: 2px; margin: 0 auto 1.2rem; }
.modal-title  { font-family: 'Playfair Display', serif; font-size: 1.25rem; font-weight: 800; margin-bottom: 0.3rem; color: #244441; }
.modal-sub    { font-size: 0.8rem; color: #9ab0ae; margin-bottom: 1.4rem; }
.doc-search {
  width: 100%; padding: 0.65rem 0.9rem;
  border: 1.5px solid rgba(36,68,65,0.12); border-radius: 12px;
  font-family: 'DM Sans', sans-serif; font-size: 0.88rem;
  color: #244441; outline: none; margin-bottom: 0.8rem; transition: border-color 0.2s;
}
.doc-search:focus { border-color: #3F82E3; }
.doc-select-grid { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.2rem; max-height: 55vh; overflow-y: auto; }
.doc-option {
  display: flex; align-items: center; gap: 0.8rem;
  padding: 0.8rem 1rem; border-radius: 14px;
  border: 1.5px solid rgba(36,68,65,0.1);
  cursor: pointer; transition: all 0.2s; background: #fff;
}
.doc-option:hover  { border-color: #3F82E3; background: rgba(63,130,227,0.04); }
.doc-option.selected { border-color: #3F82E3; background: rgba(63,130,227,0.08); }
.doc-option.unavailable { opacity: 0.42; cursor: not-allowed; }
.doc-mini-avatar {
  width: 42px; height: 42px; border-radius: 12px;
  background: #3F82E3; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.9rem; flex-shrink: 0; overflow: hidden;
}
.doc-mini-avatar img { width: 100%; height: 100%; object-fit: cover; }
.booking-step { display: none; }
.booking-step.active { display: block; }
.step-label { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: #9ab0ae; margin-bottom: 0.6rem; }
.cal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.8rem; }
.cal-nav { background: none; border: none; cursor: pointer; color: #244441; padding: 0.3rem 0.5rem; border-radius: 8px; transition: background 0.15s; font-size: 1.1rem; line-height: 1; }
.cal-nav:hover { background: rgba(36,68,65,0.08); }
.cal-nav:disabled { opacity: 0.25; cursor: default; }
.cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; }
.cal-day-name { text-align: center; font-size: 0.65rem; font-weight: 700; color: #9ab0ae; padding: 0.3rem 0; letter-spacing: 0.05em; }
.cal-cell { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 0.82rem; font-weight: 500; cursor: default; transition: background 0.12s, color 0.12s; }
.cal-cell.past     { color: rgba(36,68,65,0.18); }
.cal-cell.blocked  { color: rgba(36,68,65,0.2); cursor: not-allowed; text-decoration: line-through; text-decoration-color: rgba(36,68,65,0.15); }
.cal-cell.available { color: #244441; background: rgba(36,68,65,0.05); cursor: pointer; font-weight: 600; }
.cal-cell.available:hover { background: rgba(63,130,227,0.1); color: #3F82E3; }
.cal-cell.today.available { border: 1.5px solid #3F82E3; color: #3F82E3; }
.cal-cell.selected { background: #3F82E3 !important; color: #fff !important; font-weight: 700; }
.cal-legend { margin-top: 0.75rem; padding-top: 0.65rem; border-top: 1px solid rgba(36,68,65,0.08); }
.cal-legend-title { font-size: 0.62rem; font-weight: 800; letter-spacing: 0.07em; text-transform: uppercase; color: #9ab0ae; margin-bottom: 0.3rem; }
.cal-legend-row { display: flex; align-items: baseline; gap: 0.5rem; font-size: 0.72rem; color: #5a7a77; line-height: 1.85; }
.cal-legend-day { font-weight: 700; min-width: 2.4rem; color: #244441; }
.time-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 0.5rem; margin-bottom: 1rem; }
.time-slot { padding: 0.55rem 0; border-radius: 10px; border: 1.5px solid rgba(36,68,65,0.1); text-align: center; font-size: 0.8rem; font-weight: 600; cursor: pointer; color: #244441; transition: all 0.15s; }
.time-slot:hover  { border-color: #3F82E3; color: #3F82E3; background: rgba(63,130,227,0.05); }
.time-slot.selected { background: #3F82E3; color: #fff; border-color: #3F82E3; }
.time-slot.booked   { background: rgba(0,0,0,0.04); color: rgba(36,68,65,0.25); cursor: not-allowed; border-color: transparent; }
.bk-label { display: block; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #9ab0ae; margin-bottom: 0.4rem; }
.bk-input { width: 100%; padding: 0.7rem 0.9rem; border: 1.5px solid rgba(36,68,65,0.12); border-radius: 12px; font-family: 'DM Sans', sans-serif; font-size: 0.88rem; color: #244441; outline: none; transition: border-color 0.2s; background: #fff; }
.bk-input:focus { border-color: #3F82E3; }
.bk-field { margin-bottom: 0.8rem; }
.btn-book-main { width: 100%; padding: 0.85rem; border-radius: 50px; background: #3F82E3; color: #fff; font-weight: 700; font-size: 0.9rem; border: none; cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all 0.25s; box-shadow: 0 4px 14px rgba(63,130,227,0.3); }
.btn-book-main:hover { background: #2d6fd4; transform: translateY(-1px); }
.btn-book-main:disabled { background: rgba(36,68,65,0.12); color: #9ab0ae; box-shadow: none; cursor: not-allowed; transform: none; }
.btn-back { background: none; border: none; color: #9ab0ae; font-size: 0.82rem; font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif; padding: 0.5rem 0; display: inline-flex; align-items: center; gap: 0.3rem; margin-bottom: 0.8rem; }
.btn-back:hover { color: #244441; }
.summary-box { background: rgba(63,130,227,0.05); border: 1px solid rgba(63,130,227,0.14); border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 1rem; }
.summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.83rem; padding: 0.28rem 0; }
.summary-row:not(:last-child) { border-bottom: 1px solid rgba(63,130,227,0.08); }
.summary-label { color: #9ab0ae; font-weight: 600; }
.summary-val   { color: #244441; font-weight: 700; text-align: right; }
.cal-hint { display: flex; align-items: center; gap: 0.4rem; font-size: 0.7rem; color: #9ab0ae; margin-top: 0.55rem; }
.cal-hint-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }

/* Teleconsult type box */
.teleconsult-type-box {
  display: flex; align-items: center; gap: 0.7rem;
  padding: 0.75rem 0.95rem;
  background: rgba(63,130,227,0.06); border: 1.5px solid rgba(63,130,227,0.2);
  border-radius: 12px;
}
.tc-icon { width: 34px; height: 34px; border-radius: 9px; background: rgba(63,130,227,0.12); color: #3F82E3; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tc-icon svg { width: 16px; height: 16px; }

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
  .page { padding: 1rem 1rem 5rem !important; }
  .appt-card-inner { grid-template-columns: 56px 1fr; }
  .appt-badges { flex-direction: row; padding-top: 0.6rem; }
  .pay-notice  { padding-left: 1.4rem; }
}
@media (max-width: 600px) {
  .appt-card-inner { grid-template-columns: 1fr; gap: 0.8rem; }
  .appt-date-box { width: 100%; height: auto; flex-direction: row; padding: 0.5rem 0.8rem; gap: 0.5rem; border-radius: 10px; }
  .appt-date-box .day { font-size: 1rem; }
  .visits-title { font-size: 1.4rem; }
}
</style>

<?php if ($toast): ?>
<div class="toast-bar success">
  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
  <?= htmlspecialchars($toast) ?>
</div>
<?php endif; ?>
<?php if ($toast_error): ?>
<div class="toast-bar error">
  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>
  <?= htmlspecialchars($toast_error) ?>
</div>
<?php endif; ?>

<div class="page">

  <!-- ══ PAGE HEADER ══ -->
  <div class="visits-header">
    <div class="visits-title">
      My Appointments
      <span>Track, manage, and book your teleconsultations</span>
    </div>
    <button onclick="openBookingModal()" class="book-btn">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
      Book Appointment
    </button>
  </div>

  <!-- ══ TABS ══ -->
  <div class="inner-tabs">
    <button class="inner-tab active" id="btn-upcoming" onclick="switchTab('upcoming')">Upcoming</button>
    <button class="inner-tab"        id="btn-past"     onclick="switchTab('past')">Past</button>
  </div>

  <!-- ══ UPCOMING ══ -->
  <div id="visits-upcoming">
    <?php
    $has = false;
    if ($visits_upcoming && $visits_upcoming->num_rows > 0):
      while ($a = $visits_upcoming->fetch_assoc()):
        $has      = true;
        $d        = new DateTime($a['appointment_date']);
        $apptTs   = strtotime($a['appointment_date'].' '.$a['appointment_time']);
        $now      = time();
        $active   = $now >= ($apptTs - 900) && $now <= ($apptTs + 3600);
        $early    = $active && $now < $apptTs;
        $soon     = !$active && $now >= ($apptTs - 3600);
        $status   = $a['status'];
        $paid     = $a['payment_status'] === 'Paid';
        $fee      = floatval($a['consultation_fee'] ?? 0);
        $hasSummary  = !empty($a['summary_pdf_path']);
        $hasContent  = !empty($a['chat_log']) || !empty($a['consultation_transcript']);
        $cardClass   = match($status) {
          'Pending'        => 'status-pending',
          'DoctorApproved' => 'status-approved',
          'Confirmed'      => $paid ? 'status-confirmed-paid' : 'status-confirmed',
          'Completed'      => 'status-completed',
          default          => 'status-cancelled',
        };
        $sclass = match($status) {
          'Confirmed'      => 'badge-green',
          'Pending'        => 'badge-orange',
          'DoctorApproved' => 'badge-blue',
          default          => 'badge-red',
        };
        $slabel = match($status) {
          'DoctorApproved' => 'Dr. Accepted',
          default          => $status,
        };
    ?>
    <div class="appt-card <?= $cardClass ?>" id="appt-<?= $a['id'] ?>">
      <div class="appt-card-inner">

        <!-- Date box -->
        <div class="appt-date-box">
          <div class="day"><?= $d->format('d') ?></div>
          <div class="mon"><?= $d->format('M') ?></div>
        </div>

        <!-- Main info + actions -->
        <div class="appt-main-info">
          <div class="appt-doctor-name">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
          <div class="appt-meta-row">
            <?php if (!empty($a['specialty'])): ?>
            <span><?= htmlspecialchars($a['specialty']) ?></span>
            <span class="appt-meta-sep">&middot;</span>
            <?php endif; ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <?= date('g:i A', strtotime($a['appointment_time'])) ?>
            <span class="appt-meta-sep">&middot;</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:12px;height:12px;"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87V15.13a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
            <?= htmlspecialchars($a['type']) ?>
          </div>

          <?php if (!empty($a['notes'])): ?>
          <div class="appt-notes">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <?= htmlspecialchars($a['notes']) ?>
          </div>
          <?php endif; ?>

          <!-- ── FLOW STATE ── -->
          <div class="appt-actions">

            <?php if ($status === 'Pending'): ?>
              <span class="flow-pill flow-pending">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Awaiting doctor acceptance
              </span>

            <?php elseif ($status === 'DoctorApproved'): ?>
              <span class="flow-pill flow-doctor-approved">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                Doctor accepted &mdash; staff reviewing
              </span>

            <?php elseif ($status === 'Confirmed' && !$paid): ?>
              <span class="flow-pill flow-confirmed-unpaid">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Payment required to confirm slot
              </span>

            <?php elseif ($status === 'Confirmed' && $paid): ?>
              <?php if ($active): ?>
                <a href="call_patient.php?appt_id=<?= $a['id'] ?>" class="join-call-btn">
                  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                  <?= $early ? 'Join Early' : 'Join Video Call' ?>
                </a>
              <?php elseif ($soon): ?>
                <span class="call-soon">
                  <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  Opens at <?= date('g:i A', $apptTs - 900) ?>
                </span>
              <?php else: ?>
                <span class="call-window-note">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                  Video call opens 15 min before
                </span>
              <?php endif; ?>
              <a href="receipt.php?appt_id=<?= $a['id'] ?>" class="receipt-btn">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Receipt
              </a>
              <?php if ($hasSummary): ?>
                <a href="download_summary.php?appt_id=<?= $a['id'] ?>" target="_blank" class="summary-btn">
                  <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  View Summary
                </a>
              <?php elseif ($hasContent && !$hasSummary): ?>
                <span class="summary-generating">
                  <svg class="spin-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
                  Generating summary
                </span>
              <?php endif; ?>

            <?php elseif ($status === 'Completed'): ?>
              <a href="receipt.php?appt_id=<?= $a['id'] ?>" class="receipt-btn">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                View Receipt
              </a>
              <?php if ($hasSummary): ?>
              <a href="download_summary.php?appt_id=<?= $a['id'] ?>" target="_blank" class="summary-btn">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                View Summary
              </a>
              <?php elseif ($hasContent): ?>
              <span class="summary-generating">
                <svg class="spin-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
                Generating summary
              </span>
              <?php endif; ?>
            <?php endif; ?>

          </div>
        </div>

        <!-- Badges -->
        <div class="appt-badges">
          <span class="badge <?= $sclass ?>"><?= $slabel ?></span>
          <span class="badge <?= $paid ? 'badge-green' : 'badge-red' ?>"><?= $a['payment_status'] ?></span>
        </div>

      </div>

      <!-- Pay notice strip for confirmed+unpaid -->
      <?php if ($status === 'Confirmed' && !$paid): ?>
      <div class="pay-notice">
        <div class="pay-notice-text">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          Appointment confirmed &mdash; complete payment to secure your slot
          <?php if ($fee > 0): ?><strong style="color:#5b21b6;">&nbsp;&middot;&nbsp;&#8369;<?= number_format($fee, 2) ?></strong><?php endif; ?>
        </div>
        <a href="pay.php?appt_id=<?= $a['id'] ?>" class="pay-now-btn">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          Pay Now<?php if ($fee > 0): ?> &mdash; &#8369;<?= number_format($fee, 2) ?><?php endif; ?>
        </a>
      </div>
      <?php endif; ?>

    </div>
    <?php endwhile; endif; ?>

    <?php if (!$has): ?>
    <div class="empty-state">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <p>No upcoming appointments.</p>
      <button onclick="openBookingModal()" style="margin-top:0.5rem;padding:0.55rem 1.4rem;background:#3F82E3;color:#fff;border:none;border-radius:50px;font-size:0.82rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(63,130,227,0.28);">
        Book Now
      </button>
    </div>
    <?php endif; ?>
  </div>

  <!-- ══ PAST ══ -->
  <div id="visits-past" style="display:none;">
    <?php
    $has = false;
    if ($visits_past && $visits_past->num_rows > 0):
      while ($a = $visits_past->fetch_assoc()):
        $has  = true;
        $d    = new DateTime($a['appointment_date']);
        $paid = $a['payment_status'] === 'Paid';
        $hasSummary = !empty($a['summary_pdf_path']);
        $hasContent = !empty($a['chat_log']) || !empty($a['consultation_transcript']);
    ?>
    <div class="appt-card <?= $a['status']==='Completed' ? 'status-completed' : 'status-cancelled' ?>" style="opacity:0.88;">
      <div class="appt-card-inner">
        <div class="appt-date-box past-box">
          <div class="day"><?= $d->format('d') ?></div>
          <div class="mon"><?= $d->format('M') ?></div>
        </div>
        <div class="appt-main-info">
          <div class="appt-doctor-name">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
          <div class="appt-meta-row">
            <?php if (!empty($a['specialty'])): ?>
            <span><?= htmlspecialchars($a['specialty']) ?></span>
            <span class="appt-meta-sep">&middot;</span>
            <?php endif; ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <?= date('g:i A', strtotime($a['appointment_time'])) ?>
            <span class="appt-meta-sep">&middot;</span>
            <?= htmlspecialchars($a['type']) ?>
          </div>
          <div class="appt-actions">
            <?php if ($paid && $a['status'] === 'Completed'): ?>
            <a href="receipt.php?appt_id=<?= $a['id'] ?>" class="receipt-btn" style="font-size:0.75rem;padding:0.38rem 0.82rem;">
              <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              View Receipt
            </a>
            <?php endif; ?>
            <?php if ($hasSummary): ?>
            <a href="download_summary.php?appt_id=<?= $a['id'] ?>" target="_blank" class="summary-btn" style="font-size:0.75rem;padding:0.38rem 0.82rem;">
              <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              View Summary
            </a>
            <?php elseif ($a['status'] === 'Completed' && $hasContent): ?>
            <span class="summary-generating" style="font-size:0.72rem;">
              <svg class="spin-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
              Generating
            </span>
            <?php endif; ?>
          </div>
        </div>
        <div class="appt-badges">
          <span class="badge <?= $a['status']==='Completed'?'badge-green':'badge-red' ?>"><?= $a['status'] ?></span>
          <span class="badge <?= $paid?'badge-green':'badge-red' ?>"><?= $a['payment_status'] ?></span>
        </div>
      </div>
    </div>
    <?php endwhile; endif; ?>
    <?php if (!$has): ?>
    <div class="empty-state">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      <p>No past appointments yet.</p>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /page -->


<!-- ══════════════════════════════════════════
     BOOKING MODAL (unchanged logic)
════════════════════════════════════════════ -->
<div class="modal-overlay" id="booking-modal">
  <div class="modal-sheet">
    <div class="modal-handle"></div>

    <!-- Step 1: Choose doctor -->
    <div class="booking-step active" id="step-1">
      <div class="modal-title">Book a Teleconsultation</div>
      <div class="modal-sub">Choose a doctor to schedule your online consultation.</div>
      <input type="text" class="doc-search" id="doc-search-input" placeholder="Search by name or specialty…" oninput="filterDoctors(this.value)"/>
      <div class="doc-select-grid" id="doc-list">
        <?php foreach ($all_doctors as $dr):
          $initials    = strtoupper(substr($dr['full_name'],0,1).(strpos($dr['full_name'],' ')!==false ? substr($dr['full_name'],strpos($dr['full_name'],' ')+1,1) : ''));
          $unavailable = !$dr['is_available'] || empty($dr['schedules']);
          $specialty   = htmlspecialchars($dr['specialty'] ?? '');
          $subspecialty= !empty($dr['subspecialty']) ? htmlspecialchars($dr['subspecialty']) : '';
        ?>
        <div class="doc-option <?= $unavailable ? 'unavailable' : '' ?>"
             data-name="<?= strtolower(htmlspecialchars($dr['full_name'])) ?>"
             data-specialty="<?= strtolower($specialty) ?>"
             onclick="<?= $unavailable ? '' : "selectDoctor({$dr['id']})" ?>">
          <div class="doc-mini-avatar">
            <?php if (!empty($dr['profile_photo'])): ?><img src="../<?= htmlspecialchars($dr['profile_photo']) ?>"/><?php else: echo $initials; endif; ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:0.9rem;color:#244441;">Dr. <?= htmlspecialchars($dr['full_name']) ?></div>
            <div style="font-size:0.75rem;color:#9ab0ae;"><?= $specialty ?><?= $subspecialty ? ' &middot; <em>'.$subspecialty.'</em>' : '' ?></div>
            <?php if (!empty($dr['consultation_fee'])): ?>
            <div style="font-size:0.73rem;color:#3F82E3;font-weight:600;margin-top:0.12rem;">&#8369;<?= number_format($dr['consultation_fee'], 2) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($unavailable): ?>
          <span style="font-size:0.68rem;color:#9ab0ae;font-weight:600;flex-shrink:0;">Unavailable</span>
          <?php else: ?>
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:#9ab0ae;flex-shrink:0;" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div id="doc-no-results" style="display:none;text-align:center;padding:1.5rem;font-size:0.85rem;color:#9ab0ae;">No doctors found.</div>
      </div>
      <button class="btn-book-main" style="background:transparent;color:#9ab0ae;box-shadow:none;border:1.5px solid rgba(36,68,65,0.12);" onclick="closeBookingModal()">Cancel</button>
    </div>

    <!-- Step 2: Pick a date -->
    <div class="booking-step" id="step-2">
      <button class="btn-back" onclick="goStep(1)">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>Back
      </button>
      <div class="modal-title" id="step2-title">Pick a Date</div>
      <div class="modal-sub" id="step2-sub"></div>
      <div style="background:rgba(36,68,65,0.03);border-radius:16px;padding:1rem;">
        <div class="cal-header">
          <button class="cal-nav" id="cal-prev" onclick="calNav(-1)">&#8249;</button>
          <div style="font-weight:700;font-size:0.9rem;color:#244441;" id="cal-month-label"></div>
          <button class="cal-nav" onclick="calNav(1)">&#8250;</button>
        </div>
        <div class="cal-grid" id="cal-grid"></div>
        <div class="cal-legend" id="cal-legend" style="display:none;">
          <div class="cal-legend-title">Doctor's Available Hours</div>
          <div id="cal-legend-body"></div>
        </div>
      </div>
      <div style="display:flex;gap:1rem;margin-top:0.6rem;flex-wrap:wrap;">
        <div class="cal-hint"><div class="cal-hint-dot" style="background:rgba(36,68,65,0.07);border:1.5px solid rgba(36,68,65,0.15);"></div>Available</div>
        <div class="cal-hint"><div class="cal-hint-dot" style="background:transparent;border:1px dashed rgba(36,68,65,0.2);"></div>No schedule</div>
        <div class="cal-hint"><div class="cal-hint-dot" style="background:#3F82E3;"></div>Selected</div>
      </div>
      <button class="btn-book-main" id="btn-to-step3" style="margin-top:1rem;" disabled onclick="goStep(3)">Continue to Time</button>
    </div>

    <!-- Step 3: Pick a time -->
    <div class="booking-step" id="step-3">
      <button class="btn-back" onclick="goStep(2)">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>Back
      </button>
      <div class="modal-title">Pick a Time</div>
      <div class="modal-sub" id="step3-sub"></div>
      <div class="step-label">Available Slots</div>
      <div class="time-grid" id="time-grid"></div>
      <button class="btn-book-main" id="btn-to-step4" disabled onclick="goStep(4)">Continue</button>
    </div>

    <!-- Step 4: Confirm -->
    <div class="booking-step" id="step-4">
      <button class="btn-back" onclick="goStep(3)">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>Back
      </button>
      <div class="modal-title">Confirm Booking</div>
      <div class="modal-sub">Your request will be sent to the doctor for acceptance first.</div>

      <!-- Flow preview -->
      <div style="background:rgba(63,130,227,0.04);border:1px solid rgba(63,130,227,0.12);border-radius:14px;padding:0.85rem 1rem;margin-bottom:1rem;">
        <div style="font-size:0.67rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.6rem;">Booking Flow</div>
        <div class="flow-strip">
          <span class="flow-step" style="background:rgba(245,158,11,0.12);color:#d97706;">1. You Book</span>
          <span class="flow-arrow">&rarr;</span>
          <span class="flow-step" style="background:rgba(63,130,227,0.1);color:#3F82E3;">2. Doctor Accepts</span>
          <span class="flow-arrow">&rarr;</span>
          <span class="flow-step" style="background:rgba(36,68,65,0.08);color:#244441;">3. Staff Confirms</span>
          <span class="flow-arrow">&rarr;</span>
          <span class="flow-step" style="background:rgba(124,58,237,0.1);color:#7c3aed;">4. You Pay</span>
          <span class="flow-arrow">&rarr;</span>
          <span class="flow-step" style="background:rgba(34,197,94,0.1);color:#16a34a;">5. All Set</span>
        </div>
      </div>

      <div class="summary-box" id="booking-summary"></div>
      <form method="POST" id="booking-form">
        <input type="hidden" name="book_appointment" value="1"/>
        <input type="hidden" name="doctor_id" id="f-doctor-id"/>
        <input type="hidden" name="appt_date"  id="f-date"/>
        <input type="hidden" name="appt_time"  id="f-time"/>
        <div class="bk-field">
          <label class="bk-label">Consultation Type</label>
          <div class="teleconsult-type-box">
            <div class="tc-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
            </div>
            <div>
              <div style="font-weight:700;font-size:0.88rem;color:#3F82E3;">Teleconsultation</div>
              <div style="font-size:0.72rem;color:#9ab0ae;margin-top:0.1rem;">Online video &mdash; virtual consultation</div>
            </div>
          </div>
        </div>
        <div class="bk-field">
          <label class="bk-label">Notes / Symptoms <span style="font-weight:400;text-transform:none;font-size:0.68rem;">(optional)</span></label>
          <textarea name="notes" class="bk-input" rows="3" placeholder="e.g. Fever for 3 days, headache…"></textarea>
        </div>
        <button type="submit" class="btn-book-main">Send Booking Request</button>
      </form>
    </div>
  </div>
</div>

<script>
const DOCTORS_JS = <?= json_encode(array_values($all_doctors), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const BOOKED_SLOTS = {};
<?php foreach ($all_doctors as $dr):
  $did2   = (int)$dr['id'];
  $bres   = $conn->query("SELECT appointment_date, appointment_time FROM appointments WHERE doctor_id=$did2 AND status NOT IN ('Cancelled') AND appointment_date >= CURDATE()");
  $booked = [];
  if ($bres) { while ($b = $bres->fetch_assoc()) $booked[] = ['date'=>$b['appointment_date'],'time'=>$b['appointment_time']]; }
?>
BOOKED_SLOTS[<?= $did2 ?>] = <?= json_encode($booked) ?>;
<?php endforeach; ?>

const DAY_NAMES_SHORT = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const DAY_NAMES_LONG  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTH_NAMES     = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const TODAY_STR       = new Date().toLocaleDateString('en-CA');

function fmt12h(t) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number), ampm = h >= 12 ? 'PM' : 'AM', hr = h % 12 || 12;
  return hr + ':' + (m||0).toString().padStart(2,'0') + ' ' + ampm;
}
function formatDateDisplay(dateStr) {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-PH', {weekday:'long',month:'long',day:'numeric',year:'numeric'});
}
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function generateSlots(start, end) {
  const slots = [];
  let [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number), endMins = eh*60+em;
  while (sh*60+sm < endMins) {
    slots.push(String(sh).padStart(2,'0') + ':' + String(sm).padStart(2,'0'));
    sm += 30; if (sm >= 60) { sh++; sm -= 60; } if (sh >= 24) break;
  }
  return slots;
}
function switchTab(type) {
  document.getElementById('visits-upcoming').style.display = type === 'upcoming' ? 'block' : 'none';
  document.getElementById('visits-past').style.display     = type === 'past'     ? 'block' : 'none';
  document.getElementById('btn-upcoming').classList.toggle('active', type === 'upcoming');
  document.getElementById('btn-past').classList.toggle('active',     type === 'past');
}
function filterDoctors(query) {
  const q = query.toLowerCase().trim();
  const opts = document.querySelectorAll('#doc-list .doc-option');
  let v = 0;
  opts.forEach(el => {
    const show = !q || el.dataset.name.includes(q) || el.dataset.specialty.includes(q);
    el.style.display = show ? '' : 'none';
    if (show) v++;
  });
  document.getElementById('doc-no-results').style.display = v === 0 ? 'block' : 'none';
}
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
function resetBooking() { selDoctor = null; selDate = null; selTime = null; }
let selDoctor = null, selDate = null, selTime = null, calYear, calMonth;
function goStep(n) {
  document.querySelectorAll('.booking-step').forEach(s => s.classList.remove('active'));
  document.getElementById('step-' + n).classList.add('active');
  if (n === 2) renderCalendar();
  if (n === 3) renderTimeSlots();
  if (n === 4) renderSummary();
}
function selectDoctor(id) {
  selDoctor = DOCTORS_JS.find(d => d.id == id);
  selDate = null; selTime = null;
  document.querySelectorAll('.doc-option').forEach(el => el.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('step2-title').textContent = 'Dr. ' + selDoctor.full_name;
  document.getElementById('step2-sub').textContent =
    (selDoctor.specialty || '') +
    (selDoctor.consultation_fee ? ' \u00b7 \u20b1' + parseFloat(selDoctor.consultation_fee).toLocaleString() : '');
  const now = new Date(); calYear = now.getFullYear(); calMonth = now.getMonth();
  document.getElementById('btn-to-step3').disabled = true;
  goStep(2);
}
function calNav(dir) {
  calMonth += dir;
  if (calMonth > 11) { calMonth = 0; calYear++; } else if (calMonth < 0) { calMonth = 11; calYear--; }
  renderCalendar();
}
function renderCalendar() {
  if (!selDoctor) return;
  document.getElementById('cal-month-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
  const now = new Date();
  const atMin = calYear === now.getFullYear() && calMonth === now.getMonth();
  document.getElementById('cal-prev').disabled = atMin;
  const grid = document.getElementById('cal-grid');
  const availDays = selDoctor.schedules.map(s => s.day_of_week);
  const today = new Date(); today.setHours(0,0,0,0);
  const nowMins = new Date().getHours()*60 + new Date().getMinutes();
  const firstDay = new Date(calYear, calMonth, 1).getDay();
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
  grid.innerHTML = DAY_NAMES_SHORT.map(d => `<div class="cal-day-name">${d}</div>`).join('');
  for (let i = 0; i < firstDay; i++) grid.innerHTML += `<div class="cal-cell"></div>`;
  for (let day = 1; day <= daysInMonth; day++) {
    const d = new Date(calYear, calMonth, day); d.setHours(0,0,0,0);
    const longDay = DAY_NAMES_LONG[d.getDay()];
    const dateStr = calYear + '-' + String(calMonth+1).padStart(2,'0') + '-' + String(day).padStart(2,'0');
    const isPast = d < today, isToday = d.getTime() === today.getTime();
    const isInSched = availDays.includes(longDay);
    let isAvail = !isPast && isInSched;
    if (isAvail && isToday) {
      const slots = selDoctor.schedules.filter(s => s.day_of_week === longDay).flatMap(s => generateSlots(s.start_time, s.end_time));
      isAvail = slots.some(t => { const [h, m] = t.split(':').map(Number); return h*60+m > nowMins; });
    }
    let cls = 'cal-cell', title = '';
    if (isPast) cls += ' past';
    else if (!isInSched) { cls += ' blocked'; }
    else if (!isAvail) cls += ' past';
    else cls += ' available';
    if (selDate === dateStr && isAvail) cls += ' selected';
    if (isToday) cls += ' today';
    const onclick = isAvail ? `onclick="pickDate('${dateStr}','${longDay}')"` : '';
    grid.innerHTML += `<div class="${cls}" ${onclick}>${day}</div>`;
  }
  renderCalLegend();
}
function renderCalLegend() {
  if (!selDoctor || !selDoctor.schedules.length) { document.getElementById('cal-legend').style.display='none'; return; }
  const byDay = {};
  selDoctor.schedules.forEach(s => { if (!byDay[s.day_of_week]) byDay[s.day_of_week]=[]; byDay[s.day_of_week].push(fmt12h(s.start_time)+' \u2013 '+fmt12h(s.end_time)); });
  document.getElementById('cal-legend-body').innerHTML = Object.entries(byDay).map(([day,times]) =>
    `<div class="cal-legend-row"><span class="cal-legend-day">${day.slice(0,3)}</span><span>${times.join(' \u00a0|\u00a0 ')}</span></div>`
  ).join('');
  document.getElementById('cal-legend').style.display='block';
}
function pickDate(dateStr, dayName) {
  selDate = dateStr; selTime = null;
  renderCalendar();
  document.getElementById('btn-to-step3').disabled = false;
  document.getElementById('step3-sub').textContent = formatDateDisplay(dateStr) + ' \u2014 choose a time';
}
function renderTimeSlots() {
  if (!selDoctor || !selDate) return;
  const dayName = DAY_NAMES_LONG[new Date(selDate + 'T00:00:00').getDay()];
  const scheds  = selDoctor.schedules.filter(s => s.day_of_week === dayName);
  const grid    = document.getElementById('time-grid');
  grid.innerHTML = '';
  if (!scheds.length) { grid.innerHTML = '<div style="grid-column:1/-1;color:#9ab0ae;font-size:0.82rem;">No schedule for this day.</div>'; return; }
  let allSlots = [...new Set(scheds.flatMap(s => generateSlots(s.start_time, s.end_time)))].sort();
  const rawBooked = (BOOKED_SLOTS[selDoctor.id] || []).filter(b => b.date === selDate).map(b => b.time.slice(0,5));
  const APPT_DURATION_MINS = 60;
  const booked = allSlots.filter(slot => {
    const [sh, sm] = slot.split(':').map(Number), slotMins = sh*60+sm;
    return rawBooked.some(b => { const [bh, bm] = b.split(':').map(Number), bm2 = bh*60+bm; return slotMins >= bm2 && slotMins < bm2 + APPT_DURATION_MINS; });
  });
  const isToday = selDate === TODAY_STR, nowMins = isToday ? new Date().getHours()*60+new Date().getMinutes() : -1;
  let anyAvail = false;
  allSlots.forEach(t => {
    const [h, m] = t.split(':').map(Number), slotMins = h*60+m;
    const isPast = isToday && slotMins <= nowMins, isBooked = booked.includes(t), isSel = selTime === t;
    const disabled = isPast || isBooked;
    if (!disabled) anyAvail = true;
    const cls = 'time-slot' + (disabled ? ' booked' : '') + (isSel ? ' selected' : '');
    grid.innerHTML += `<div class="${cls}" ${!disabled ? `onclick="pickTime('${t}')"` : ''}>${fmt12h(t)}</div>`;
  });
  if (!anyAvail) grid.innerHTML = '<div style="grid-column:1/-1;color:#9ab0ae;font-size:0.82rem;text-align:center;padding:1rem;">No available slots for this date.</div>';
  document.getElementById('btn-to-step4').disabled = !selTime;
}
function pickTime(t) { selTime = t; renderTimeSlots(); document.getElementById('btn-to-step4').disabled = false; }
function renderSummary() {
  if (!selDoctor || !selDate || !selTime) return;
  document.getElementById('f-doctor-id').value = selDoctor.id;
  document.getElementById('f-date').value = selDate;
  document.getElementById('f-time').value = selTime + ':00';
  const fee = selDoctor.consultation_fee ? '\u20b1' + parseFloat(selDoctor.consultation_fee).toLocaleString() : 'To be confirmed';
  document.getElementById('booking-summary').innerHTML = `
    <div class="summary-row"><span class="summary-label">Doctor</span><span class="summary-val">Dr. ${escHtml(selDoctor.full_name)}</span></div>
    <div class="summary-row"><span class="summary-label">Type</span><span class="summary-val" style="color:#3F82E3;">Teleconsultation</span></div>
    <div class="summary-row"><span class="summary-label">Date</span><span class="summary-val">${formatDateDisplay(selDate)}</span></div>
    <div class="summary-row"><span class="summary-label">Time</span><span class="summary-val">${fmt12h(selTime)}</span></div>
    <div class="summary-row"><span class="summary-label">Fee</span><span class="summary-val">${fee}</span></div>
  `;
}
document.querySelectorAll('.toast-bar.success').forEach(t => setTimeout(() => t.remove(), 3500));
document.querySelectorAll('.toast-bar.error').forEach(t => { t.style.cursor='pointer'; t.addEventListener('click', () => t.remove()); });
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>