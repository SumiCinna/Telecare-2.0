<?php
// private_telecare/visits.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/auth.php';
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
    header('Location: ../visits.php'); exit;
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

// ── Stat counts (additive only — used for the summary cards, does not affect existing queries above) ──
$stat_upcoming  = $visits_upcoming ? $visits_upcoming->num_rows : 0;
$stat_completed = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$patient_id AND status='Completed'")->fetch_assoc()['c'] ?? 0;
$stat_cancelled = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$patient_id AND status='Cancelled'")->fetch_assoc()['c'] ?? 0;

$toast       = $_SESSION['toast']       ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$page_title = 'My Visits — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../includes/header.php';

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
  flex-wrap: wrap; gap: 1rem;
}
.visits-title {
  font-family: 'Playfair Display', serif;
  font-size: 1.9rem; font-weight: 900; color: #244441; line-height: 1;
}
.visits-title span {
  display: block; font-family: 'DM Sans', sans-serif;
  font-size: 0.85rem; font-weight: 500; color: #9ab0ae;
  margin-top: 0.4rem; letter-spacing: 0;
}
.book-btn {
  display: inline-flex; align-items: center; gap: 0.45rem;
  background: #C33643; color: #fff;
  padding: 0.72rem 1.5rem; border-radius: 50px;
  font-size: 0.85rem; font-weight: 700;
  border: none; cursor: pointer;
  font-family: 'DM Sans', sans-serif;
  box-shadow: 0 4px 16px rgba(195,54,67,0.28);
  transition: all 0.25s cubic-bezier(0.16,1,0.3,1);
  text-decoration: none;
}
.book-btn:hover { background: #a82d38; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(195,54,67,0.38); }
.book-btn svg { flex-shrink: 0; }

/* ── STAT CARDS ── */
.stat-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1.4rem;
}
.stat-card {
  background: #fff;
  border: 1px solid rgba(36,68,65,0.08);
  border-radius: 16px;
  padding: 1.1rem 1.3rem;
  display: flex; align-items: center; gap: 0.9rem;
  box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}
.stat-icon {
  width: 44px; height: 44px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-icon svg { width: 20px; height: 20px; }
.stat-icon.upcoming  { background: rgba(63,130,227,0.1);  color: #3F82E3; }
.stat-icon.completed { background: rgba(195,54,67,0.08);  color: #C33643; }
.stat-icon.cancelled { background: rgba(195,54,67,0.08);  color: #C33643; }
.stat-label {
  font-size: 0.68rem; font-weight: 800; letter-spacing: 0.08em;
  text-transform: uppercase; color: #9ab0ae; margin-bottom: 0.15rem;
}
.stat-value { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 900; color: #244441; }

/* ── SEARCH + FILTER BAR ── */
.controls-bar {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.7rem;
  margin-bottom: 1.3rem;
}
.search-wrap { position: relative; flex: 1; min-width: 220px; }
.search-wrap svg {
  position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%);
  color: #9ab0ae; width: 15px; height: 15px;
}
.search-input {
  width: 100%; padding: 0.68rem 1rem 0.68rem 2.4rem;
  border: 1.5px solid rgba(36,68,65,0.1); border-radius: 50px;
  font-family: 'DM Sans', sans-serif; font-size: 0.85rem; color: #244441;
  outline: none; background: #fff; transition: border-color 0.2s;
}
.search-input:focus { border-color: #3F82E3; }
.control-select {
  padding: 0.68rem 1rem; border-radius: 50px;
  border: 1.5px solid rgba(36,68,65,0.1); background: #fff;
  font-family: 'DM Sans', sans-serif; font-size: 0.83rem; font-weight: 600;
  color: #244441; cursor: pointer; outline: none;
}

/* ── TABS ── */
.inner-tabs {
  display: flex; gap: 0; margin-bottom: 1.2rem;
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

/* ── APPOINTMENT ROW CARD ── */
.appt-card {
  background: #fff;
  border: 1px solid rgba(36,68,65,0.08);
  border-radius: 16px;
  margin-bottom: 0.85rem;
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
}
.appt-card:hover { box-shadow: 0 8px 28px rgba(36,68,65,0.08); transform: translateY(-1px); }

.appt-card-inner {
  display: grid;
  grid-template-columns: 52px 1fr auto;
  gap: 1rem;
  padding: 1.15rem 1.3rem;
  align-items: flex-start;
}

/* Avatar */
.appt-avatar {
  width: 52px; height: 52px; border-radius: 14px;
  background: #3F82E3; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 1rem; flex-shrink: 0;
  overflow: hidden;
}
.appt-avatar img { width: 100%; height: 100%; object-fit: cover; }
.appt-avatar.past { background: rgba(36,68,65,0.15); color: #244441; }

/* Center info */
.appt-main-info {}
.appt-name-row {
  display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap;
  margin-bottom: 0.28rem;
}
.appt-doctor-name { font-weight: 700; font-size: 0.98rem; color: #244441; }

.appt-sub {
  font-size: 0.82rem; color: #9ab0ae; margin-bottom: 0.45rem;
}
.appt-meta-row {
  display: flex; align-items: center; gap: 0.45rem;
  font-size: 0.79rem; color: #6b8886; margin-bottom: 0.5rem; flex-wrap: wrap;
}
.appt-meta-row svg { width: 13px; height: 13px; flex-shrink: 0; }
.appt-meta-sep { color: rgba(36,68,65,0.15); }

.appt-notes {
  display: flex; align-items: flex-start; gap: 0.35rem;
  font-size: 0.76rem; color: #9ab0ae;
  background: rgba(36,68,65,0.03);
  border-radius: 8px; padding: 0.4rem 0.65rem;
  margin-bottom: 0.55rem; width: fit-content; max-width: 100%;
}
.appt-notes svg { width: 11px; height: 11px; flex-shrink: 0; margin-top: 1px; }

.flow-note {
  display: inline-flex; align-items: center; gap: 0.35rem;
  font-size: 0.76rem; font-weight: 600; margin-top: 0.1rem;
}
.flow-note svg { width: 12px; height: 12px; flex-shrink: 0; }
.flow-note.pending  { color: #d97706; }
.flow-note.approved { color: #2563eb; }
.flow-note.unpaid   { color: #7c3aed; }
.flow-note.soon     { color: #d97706; }
.flow-note.info     { color: #9ab0ae; font-weight: 500; }

/* Right side: status badge + action buttons, stacked */
.appt-side {
  display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;
  flex-shrink: 0;
}
.appt-actions-col {
  display: flex; flex-direction: column; align-items: flex-end; gap: 0.4rem;
}

/* ── BADGES ── */
.badge {
  font-size: 0.64rem; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase; padding: 0.24rem 0.65rem; border-radius: 50px;
  white-space: nowrap;
}
.badge-green  { background: rgba(34,197,94,0.12);  color: #16a34a; }
.badge-orange { background: rgba(245,158,11,0.12); color: #ca8a04; }
.badge-red    { background: rgba(195,54,67,0.1);   color: #C33643; }
.badge-blue   { background: rgba(63,130,227,0.1);  color: #2563eb; }
.badge-purple { background: rgba(124,58,237,0.1);  color: #7c3aed; }
.badge-gray   { background: rgba(36,68,65,0.08);   color: #6b8886; }

/* ── ACTION BUTTONS (screenshot-style: solid primary / bordered secondary) ── */
.btn-pill {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0.55rem 1.05rem; border-radius: 50px;
  font-size: 0.78rem; font-weight: 700; text-decoration: none;
  border: 1.5px solid transparent; cursor: pointer;
  font-family: 'DM Sans', sans-serif; transition: all 0.2s; white-space: nowrap;
}
.btn-pill svg { width: 13px; height: 13px; flex-shrink: 0; }
.btn-pill.solid-green  { background: linear-gradient(135deg,#16a34a,#15803d); color:#fff; box-shadow:0 4px 14px rgba(22,163,74,0.32); animation: callPulse 2s ease-in-out infinite; }
.btn-pill.solid-purple { background: linear-gradient(135deg,#7c3aed,#6d28d9); color:#fff; box-shadow:0 4px 14px rgba(124,58,237,0.28); }
.btn-pill.solid-purple:hover { background: linear-gradient(135deg,#6d28d9,#5b21b6); transform: translateY(-1px); }
.btn-pill.outline-green  { background:#fff; border-color: rgba(34,197,94,0.3);  color:#15803d; }
.btn-pill.outline-green:hover  { background: rgba(34,197,94,0.08); }
.btn-pill.outline-blue   { background:#fff; border-color: rgba(63,130,227,0.3); color:#2563eb; }
.btn-pill.outline-blue:hover   { background: rgba(63,130,227,0.08); }
.btn-pill.outline-neutral{ background:#fff; border-color: rgba(36,68,65,0.15); color:#244441; }
.btn-pill.outline-neutral:hover{ background: rgba(36,68,65,0.05); }

@keyframes callPulse {
  0%,100% { box-shadow: 0 4px 14px rgba(22,163,74,0.32); }
  50%      { box-shadow: 0 4px 22px rgba(22,163,74,0.55); }
}

.summary-generating {
  display: inline-flex; align-items: center; gap: 0.38rem;
  background: rgba(245,158,11,0.07); border: 1px dashed rgba(245,158,11,0.32);
  color: #d97706; padding: 0.4rem 0.85rem; border-radius: 50px;
  font-size: 0.74rem; font-weight: 600;
}
@keyframes spin { to { transform: rotate(360deg); } }
.spin-icon { animation: spin 1.4s linear infinite; display: inline-block; }

/* ── PAYMENT NOTICE STRIP ── */
.pay-notice {
  background: linear-gradient(135deg, rgba(124,58,237,0.06), rgba(63,130,227,0.05));
  border-top: 1px solid rgba(124,58,237,0.12);
  padding: 0.85rem 1.3rem 0.85rem 4.85rem;
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  flex-wrap: wrap;
}
.pay-notice-text {
  font-size: 0.82rem; font-weight: 600; color: #5b21b6;
  display: flex; align-items: center; gap: 0.5rem;
}
.pay-notice-text svg { width: 14px; height: 14px; flex-shrink: 0; color: #7c3aed; }

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

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
  .page { padding: 1rem 1rem 5rem !important; }
  .stat-row { grid-template-columns: 1fr; }
  .appt-card-inner { grid-template-columns: 44px 1fr; }
  .appt-side { grid-column: 1 / -1; flex-direction: row; align-items: center; justify-content: space-between; padding-top: 0.6rem; }
  .appt-actions-col { flex-direction: row; flex-wrap: wrap; }
  .pay-notice  { padding-left: 1.4rem; }
}
@media (max-width: 600px) {
  .appt-card-inner { grid-template-columns: 1fr; gap: 0.7rem; }
  .visits-title { font-size: 1.5rem; }
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
      Appointments
      <span>Book, view, and manage your medical appointments.</span>
    </div>
    <a href="router.php?page=booking/step1_details" class="book-btn">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
      Book Appointment
    </a>
  </div>

  <!-- ══ STAT CARDS ══ -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-icon upcoming">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      </div>
      <div>
        <div class="stat-label">Upcoming</div>
        <div class="stat-value"><?= (int)$stat_upcoming ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon completed">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/></svg>
      </div>
      <div>
        <div class="stat-label">Completed</div>
        <div class="stat-value"><?= (int)$stat_completed ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon cancelled">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5l5 5m0-5l-5 5"/></svg>
      </div>
      <div>
        <div class="stat-label">Cancelled</div>
        <div class="stat-value"><?= (int)$stat_cancelled ?></div>
      </div>
    </div>
  </div>

  <!-- ══ SEARCH BAR (client-side filter on doctor name, purely visual/additive — does not touch booking or status logic) ══ -->
  <div class="controls-bar">
    <div class="search-wrap">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
      <input type="text" id="visitSearchInput" class="search-input" placeholder="Search doctor or specialty…" oninput="filterVisitRows(this.value)"/>
    </div>
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
        $initials = strtoupper(substr($a['doctor_name'],0,1) . (strpos($a['doctor_name'],' ')!==false ? substr($a['doctor_name'],strpos($a['doctor_name'],' ')+1,1) : ''));
    ?>
    <div class="appt-card" id="appt-<?= $a['id'] ?>" data-search="<?= strtolower(htmlspecialchars($a['doctor_name'].' '.($a['specialty']??''))) ?>">
      <div class="appt-card-inner">

        <!-- Avatar -->
        <div class="appt-avatar"><?= $initials ?></div>

        <!-- Main info -->
        <div class="appt-main-info">
          <div class="appt-name-row">
            <span class="appt-doctor-name">Dr. <?= htmlspecialchars($a['doctor_name']) ?></span>
            <span class="badge <?= $sclass ?>"><?= $slabel ?></span>
            <span class="badge <?= $paid ? 'badge-green' : 'badge-gray' ?>"><?= $a['payment_status'] ?></span>
          </div>
          <div class="appt-sub">
            <?= !empty($a['specialty']) ? htmlspecialchars($a['specialty']).' · ' : '' ?><?= htmlspecialchars($a['type']) ?>
          </div>
          <div class="appt-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <?= $d->format('M j, Y') ?>
            <span class="appt-meta-sep">&middot;</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <?= date('g:i A', strtotime($a['appointment_time'])) ?>
            <span class="appt-meta-sep">&middot;</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87V15.13a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
            Online
          </div>

          <?php if (!empty($a['notes'])): ?>
          <div class="appt-notes">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <?= htmlspecialchars($a['notes']) ?>
          </div>
          <?php endif; ?>

          <!-- ── FLOW STATE (informational line) ── -->
          <?php if ($status === 'Confirmed' && !$paid): ?>
            <div class="flow-note unpaid">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
              Payment required to unlock this appointment
            </div>
          <?php elseif ($status === 'Confirmed' && $paid && !$active && $soon): ?>
            <div class="flow-note soon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              Opens at <?= date('g:i A', $apptTs - 900) ?>
            </div>
          <?php elseif ($status === 'Confirmed' && $paid && !$active && !$soon): ?>
            <div class="flow-note info">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
              Video call opens 15 min before
            </div>
          <?php endif; ?>
        </div>

        <!-- Right side: action buttons -->
        <div class="appt-side">
          <div class="appt-actions-col">
            <a href="router.php?page=booking/confirmed&appt_id=<?= $a['id'] ?>" class="btn-pill outline-neutral">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Appointment Details
            </a>
            <?php if ($status === 'Confirmed' && $paid): ?>
              <?php if ($active): ?>
                <a href="router.php?page=call_patient&appt_id=<?= $a['id'] ?>" class="btn-pill solid-green">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                  <?= $early ? 'Join Early' : 'Join Consultation' ?>
                </a>
              <?php endif; ?>
              <a href="router.php?page=receipt&appt_id=<?= $a['id'] ?>" class="btn-pill outline-neutral">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Receipt
              </a>
              <?php if ($hasSummary): ?>
                <a href="router.php?page=download_summary&appt_id=<?= $a['id'] ?>" target="_blank" class="btn-pill outline-blue">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  View Summary
                </a>
              <?php elseif ($hasContent && !$hasSummary): ?>
                <span class="summary-generating">
                  <svg class="spin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
                  Generating summary
                </span>
              <?php endif; ?>
            <?php elseif ($status === 'Confirmed' && !$paid): ?>
              <a href="router.php?page=booking/payment&appt_id=<?= $a['id'] ?>" class="btn-pill solid-purple">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Pay Now<?php if ($fee > 0): ?> &mdash; &#8369;<?= number_format($fee, 2) ?><?php endif; ?>
              </a>
            <?php endif; ?>
          </div>
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
      <a href="router.php?page=booking/step1_details" style="margin-top:0.5rem;padding:0.55rem 1.4rem;background:#3F82E3;color:#fff;border:none;border-radius:50px;font-size:0.82rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(63,130,227,0.28);text-decoration:none;">
        Book Now
      </a>
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
        $initials = strtoupper(substr($a['doctor_name'],0,1) . (strpos($a['doctor_name'],' ')!==false ? substr($a['doctor_name'],strpos($a['doctor_name'],' ')+1,1) : ''));
    ?>
    <div class="appt-card" style="opacity:0.9;" data-search="<?= strtolower(htmlspecialchars($a['doctor_name'].' '.($a['specialty']??''))) ?>">
      <div class="appt-card-inner">
        <div class="appt-avatar past"><?= $initials ?></div>
        <div class="appt-main-info">
          <div class="appt-name-row">
            <span class="appt-doctor-name">Dr. <?= htmlspecialchars($a['doctor_name']) ?></span>
            <span class="badge <?= $a['status']==='Completed'?'badge-green':'badge-red' ?>"><?= $a['status'] ?></span>
            <span class="badge <?= $paid?'badge-green':'badge-gray' ?>"><?= $a['payment_status'] ?></span>
          </div>
          <div class="appt-sub">
            <?= !empty($a['specialty']) ? htmlspecialchars($a['specialty']).' · ' : '' ?><?= htmlspecialchars($a['type']) ?>
          </div>
          <div class="appt-meta-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <?= $d->format('M j, Y') ?>
            <span class="appt-meta-sep">&middot;</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            <?= date('g:i A', strtotime($a['appointment_time'])) ?>
          </div>
        </div>
        <div class="appt-side">
          <div class="appt-actions-col">
            <a href="router.php?page=booking/confirmed&appt_id=<?= $a['id'] ?>" class="btn-pill outline-neutral">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              Details
            </a>
            <?php if ($paid && $a['status'] === 'Completed'): ?>
            <a href="router.php?page=receipt&appt_id=<?= $a['id'] ?>" class="btn-pill outline-neutral">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              View Receipt
            </a>
            <?php endif; ?>
            <?php if ($hasSummary): ?>
            <a href="router.php?page=download_summary&appt_id=<?= $a['id'] ?>" target="_blank" class="btn-pill outline-blue">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              View Summary
            </a>
            <?php elseif ($a['status'] === 'Completed' && $hasContent): ?>
            <span class="summary-generating">
              <svg class="spin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>
              Generating
            </span>
            <?php endif; ?>
          </div>
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

<script>
function switchTab(type) {
  document.getElementById('visits-upcoming').style.display = type === 'upcoming' ? 'block' : 'none';
  document.getElementById('visits-past').style.display     = type === 'past'     ? 'block' : 'none';
  document.getElementById('btn-upcoming').classList.toggle('active', type === 'upcoming');
  document.getElementById('btn-past').classList.toggle('active',     type === 'past');
}

// ── Visit list search filter (additive UI-only helper — filters visible appt-cards by doctor/specialty) ──
function filterVisitRows(query) {
  const q = query.toLowerCase().trim();
  document.querySelectorAll('#visits-upcoming .appt-card, #visits-past .appt-card').forEach(card => {
    const hay = card.dataset.search || '';
    card.style.display = (!q || hay.includes(q)) ? '' : 'none';
  });
}

document.querySelectorAll('.toast-bar.success').forEach(t => setTimeout(() => t.remove(), 3500));
document.querySelectorAll('.toast-bar.error').forEach(t => { t.style.cursor='pointer'; t.addEventListener('click', () => t.remove()); });
</script>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>
</body>
</html>