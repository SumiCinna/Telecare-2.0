<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// ── POST actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Approve / Reject / Cancel / Complete
    if (isset($_POST['action'], $_POST['appt_id'])) {
        $aid    = (int)$_POST['appt_id'];
        $action = $_POST['action'];
        $notes  = trim($_POST['action_notes'] ?? '');
        $tab    = trim($_POST['active_tab']   ?? 'All');
        $map    = ['approve' => 'Confirmed', 'reject' => 'Cancelled', 'cancel' => 'Cancelled', 'complete' => 'Completed'];

        if (isset($map[$action])) {
            $new_status = $map[$action];
            $conn->query("UPDATE appointments SET status='$new_status' WHERE id=$aid");
            logAction($conn, $aid, $staff_id, ucfirst($action) . 'd', $notes);
            $_SESSION['toast'] = "Appointment " . $map[$action] . " successfully.";
        }
        header('Location: appointments.php?tab=' . urlencode($tab));
        exit;
    }

    // Create appointment — server-side validation
    if (isset($_POST['create_appt'])) {
        $pid   = (int)$_POST['patient_id'];
        $did   = (int)$_POST['doctor_id'];
        $date  = trim($_POST['appt_date'] ?? '');
        $time  = trim($_POST['appt_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $ca_error = '';

        if ($pid && $did && $date && $time) {
            $appt_ts = strtotime("$date $time");
            if ($appt_ts <= time()) {
                $ca_error = 'Cannot book an appointment in the past.';
            } else {
                $day_of_week = date('l', $appt_ts);
                $t_start     = date('H:i:s', $appt_ts);
                $sched = $conn->prepare("
                    SELECT id FROM doctor_schedules
                    WHERE doctor_id   = ?
                      AND day_of_week = ?
                      AND start_time <= ?
                      AND end_time   >  ?
                    LIMIT 1
                ");
                $sched->bind_param("isss", $did, $day_of_week, $t_start, $t_start);
                $sched->execute();
                if (!$sched->get_result()->fetch_assoc()) {
                    $ca_error = "The doctor is not available on $day_of_week at that time.";
                }
            }

            if (!$ca_error) {
                $type = 'Teleconsult';
                $stmt = $conn->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,type,notes,status,payment_status) VALUES (?,?,?,?,?,?,'Confirmed','Unpaid')");
                $stmt->bind_param("iissss", $pid, $did, $date, $time, $type, $notes);
                $stmt->execute();
                $_SESSION['toast'] = "Appointment created successfully.";
                header('Location: appointments.php');
                exit;
            }
        }
        $_SESSION['ca_error'] = $ca_error ?: 'Please fill in all required fields.';
        header('Location: appointments.php?ca_open=1');
        exit;
    }

    // Reschedule — server-side validation
    if (isset($_POST['reschedule'])) {
        $aid  = (int)$_POST['appt_id'];
        $date = trim($_POST['new_date'] ?? '');
        $time = trim($_POST['new_time'] ?? '');
        $tab  = trim($_POST['active_tab'] ?? 'All');
        $rs_error = '';

        if ($aid && $date && $time) {
            $appt_ts = strtotime("$date $time");
            if ($appt_ts <= time()) {
                $rs_error = 'Cannot reschedule to a past date or time.';
            } else {
                $row = $conn->query("SELECT doctor_id FROM appointments WHERE id=$aid")->fetch_assoc();
                $did = (int)($row['doctor_id'] ?? 0);
                $day_of_week = date('l', $appt_ts);
                $t_start     = date('H:i:s', $appt_ts);
                $sched = $conn->prepare("
                    SELECT id FROM doctor_schedules
                    WHERE doctor_id   = ?
                      AND day_of_week = ?
                      AND start_time <= ?
                      AND end_time   >  ?
                    LIMIT 1
                ");
                $sched->bind_param("isss", $did, $day_of_week, $t_start, $t_start);
                $sched->execute();
                if (!$sched->get_result()->fetch_assoc()) {
                    $rs_error = "The doctor is not available on $day_of_week at that time.";
                }
            }

            if ($rs_error) {
                $_SESSION['rs_error']   = $rs_error;
                $_SESSION['rs_appt_id'] = $aid;
                header('Location: appointments.php?tab=' . urlencode($tab) . '&rs_open=' . $aid);
                exit;
            }

            $conn->query("UPDATE appointments SET appointment_date='$date', appointment_time='$time', status='Confirmed' WHERE id=$aid");
            $_SESSION['toast'] = "Appointment rescheduled.";
        }
        header('Location: appointments.php?tab=' . urlencode($tab));
        exit;
    }
}

$toast    = $_SESSION['toast']    ?? null;
$rs_error = $_SESSION['rs_error'] ?? null;
$ca_error = $_SESSION['ca_error'] ?? null;
$rs_open  = (int)($_GET['rs_open'] ?? 0);
$ca_open  = (int)($_GET['ca_open'] ?? 0);
unset($_SESSION['toast'], $_SESSION['rs_error'], $_SESSION['rs_appt_id'], $_SESSION['ca_error']);

$active_tab = htmlspecialchars($_GET['tab'] ?? 'All');
$active_page = 'appointments';

// ── Data ──
$stat_pending = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];

$all_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty, d.id AS doctor_id
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 80
");

$all_patients = $conn->query("SELECT id, full_name FROM patients ORDER BY full_name ASC");
$all_doctors  = $conn->query("SELECT id, full_name, specialty FROM doctors WHERE status='active' ORDER BY full_name ASC");

// ── Doctor schedules keyed by doctor_id ──
$sched_rows = $conn->query("SELECT doctor_id, day_of_week, start_time, end_time FROM doctor_schedules ORDER BY doctor_id, day_of_week, start_time");
$doctor_schedules = [];
if ($sched_rows) {
    while ($s = $sched_rows->fetch_assoc()) {
        $doctor_schedules[$s['doctor_id']][] = [
            'day'   => $s['day_of_week'],
            'start' => substr($s['start_time'], 0, 5),
            'end'   => substr($s['end_time'],   0, 5),
        ];
    }
}

require_once 'includes/header.php';
?>

<style>
/* ── Custom Calendar Picker ── */
.cal-wrap {
  background: rgba(36,68,65,.04);
  border: 1px solid rgba(36,68,65,.12);
  border-radius: 12px;
  padding: .75rem .9rem .9rem;
  margin: .3rem 0 .8rem;
  user-select: none;
}
.cal-wrap.cal-disabled {
  opacity: .5;
  pointer-events: none;
}
.cal-placeholder {
  text-align: center;
  color: var(--muted, #9ab0ae);
  font-size: .8rem;
  padding: .6rem 0 .3rem;
}
.cal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: .55rem;
}
.cal-header span {
  font-weight: 700;
  font-size: .82rem;
  color: var(--text);
}
.cal-nav {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text);
  font-size: 1.1rem;
  padding: 0 .35rem;
  border-radius: 6px;
  line-height: 1;
  transition: background .15s;
}
.cal-nav:hover { background: rgba(36,68,65,.1); }
.cal-nav:disabled { opacity: .25; cursor: default; }
.cal-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}
.cal-day-name {
  text-align: center;
  font-size: .67rem;
  font-weight: 700;
  color: #9ab0ae;
  padding: .15rem 0 .35rem;
  letter-spacing: .04em;
}
.cal-cell {
  text-align: center;
  padding: .32rem .1rem;
  border-radius: 7px;
  font-size: .8rem;
  line-height: 1.3;
  cursor: pointer;
  color: var(--text);
  transition: background .12s, color .12s;
}
.cal-cell.empty { cursor: default; }
.cal-cell.past {
  color: #ccc;
  cursor: not-allowed;
  background: transparent !important;
}
/* Blocked = doctor has no schedule on that day */
.cal-cell.blocked {
  color: #d0d8d8;
  cursor: not-allowed;
  background: transparent !important;
  text-decoration: line-through;
  text-decoration-color: #dde;
}
.cal-cell.available:hover {
  background: rgba(36,68,65,.12);
}
.cal-cell.today:not(.selected) {
  font-weight: 700;
  color: var(--blue, #2a5c9a);
}
.cal-cell.selected {
  background: var(--blue, #2a7a6e) !important;
  color: #fff !important;
  font-weight: 700;
}
.cal-legend {
  margin-top: .55rem;
  padding-top: .5rem;
  border-top: 1px solid rgba(36,68,65,.1);
  font-size: .72rem;
  color: #7a9a97;
  line-height: 1.8;
}
.cal-legend-title {
  font-weight: 700;
  font-size: .67rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #9ab0ae;
  margin-bottom: .15rem;
}
</style>

<div class="sec-head">
  <h2>Appointment Management</h2>
  <div style="display:flex;gap:.6rem">
    <input class="search-bar" placeholder="Search patient or doctor…" oninput="filterTable('appt-tbody', this.value)"/>
    <button class="btn-primary" onclick="openModal('modal-create')">+ Create Appointment</button>
  </div>
</div>

<div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap">
  <?php foreach (['All', 'Pending', 'Confirmed', 'Completed', 'Cancelled'] as $f): ?>
  <button class="btn-sm" id="filter-<?= $f ?>"
          style="background:rgba(36,68,65,.07);color:var(--text)"
          onclick="filterStatus('<?= $f ?>')">
    <?= $f ?>
  </button>
  <?php endforeach ?>
</div>

<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th>Patient</th><th>Doctor</th><th>Date & Time</th>
        <th>Type</th><th>Status</th><th>Payment</th><th>Actions</th>
      </tr>
    </thead>
    <tbody id="appt-tbody">
    <?php
    if ($all_appts && $all_appts->num_rows > 0):
      while ($a = $all_appts->fetch_assoc()):
        $sc = $a['status'] === 'Confirmed' ? 'bg-green'
            : ($a['status'] === 'Pending'   ? 'bg-orange'
            : ($a['status'] === 'Completed' ? 'bg-blue' : 'bg-red'));
        $pc = $a['payment_status'] === 'Paid' ? 'bg-green' : 'bg-red';
    ?>
    <tr data-status="<?= $a['status'] ?>"
        data-search="<?= strtolower($a['patient_name'] . ' ' . $a['doctor_name']) ?>">
      <td><div style="font-weight:600"><?= htmlspecialchars($a['patient_name']) ?></div></td>
      <td>
        Dr. <?= htmlspecialchars($a['doctor_name']) ?><br/>
        <span style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($a['specialty'] ?? '') ?></span>
      </td>
      <td>
        <?= date('M j, Y', strtotime($a['appointment_date'])) ?><br/>
        <span style="font-size:.78rem;color:var(--muted)"><?= date('g:i A', strtotime($a['appointment_time'])) ?></span>
      </td>
      <td><?= htmlspecialchars($a['type']) ?></td>
      <td><span class="badge <?= $sc ?>"><?= $a['status'] ?></span></td>
      <td><span class="badge <?= $pc ?>"><?= $a['payment_status'] ?></span></td>
      <td>
        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
          <?php if ($a['status'] === 'Pending'): ?>
          <button class="btn-green btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'approve')">Approve</button>
          <button class="btn-red   btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'reject')">Reject</button>
          <?php endif ?>
          <?php if ($a['status'] === 'Confirmed'): ?>
          <button class="btn-orange btn-sm"
                  onclick="openReschedule(<?= $a['id'] ?>, <?= (int)$a['doctor_id'] ?>, '<?= $a['appointment_date'] ?>', '<?= substr($a['appointment_time'],0,5) ?>')">
            Reschedule
          </button>
          <button class="btn-red   btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'cancel')">Cancel</button>
          <button class="btn-green btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'complete')">Complete</button>
          <?php endif ?>
        </div>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="7" class="empty-row">No appointments found.</td></tr>
    <?php endif ?>
    </tbody>
  </table>
</div>

<!-- Hidden quick-action form -->
<form method="POST" id="quick-form" style="display:none">
  <input type="hidden" name="action"       id="qf-action"/>
  <input type="hidden" name="appt_id"      id="qf-appt-id"/>
  <input type="hidden" name="action_notes" id="qf-notes"/>
  <input type="hidden" name="active_tab"   id="qf-tab"/>
</form>

<!-- ══════════════════════════════════════════════════════════
     Modal: Create Appointment
     ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-create">
  <div class="modal">
    <h3>Create Appointment</h3>

    <?php if ($ca_error): ?>
    <div style="background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);color:#c33643;
                border-radius:10px;padding:.65rem .9rem;font-size:.82rem;margin-bottom:.9rem">
      ⚠ <?= htmlspecialchars($ca_error) ?>
    </div>
    <?php endif ?>

    <form method="POST" id="ca-form">
      <label class="f-label">Patient</label>
      <select name="patient_id" class="f-input" required>
        <option value="">Select patient…</option>
        <?php
        $pts2 = $conn->query("SELECT id, full_name FROM patients ORDER BY full_name ASC");
        while ($r = $pts2->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></option>
        <?php endwhile ?>
      </select>

      <label class="f-label">Doctor</label>
      <select name="doctor_id" id="ca-doctor" class="f-input" required onchange="onCaDoctorChange()">
        <option value="">Select doctor…</option>
        <?php
        $docs2 = $conn->query("SELECT id, full_name, specialty FROM doctors WHERE status='active' ORDER BY full_name ASC");
        while ($r = $docs2->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>">Dr. <?= htmlspecialchars($r['full_name']) ?> — <?= htmlspecialchars($r['specialty'] ?? '') ?></option>
        <?php endwhile ?>
      </select>

      <!-- Hidden real date input for form submission -->
      <input type="hidden" name="appt_date" id="ca-date"/>

      <label class="f-label">Date
        <span id="ca-date-hint" style="font-weight:400;color:#9ab0ae;font-size:.75rem;margin-left:.4rem"></span>
      </label>
      <!-- Custom calendar picker -->
      <div id="ca-cal-wrap" class="cal-wrap cal-disabled">
        <div class="cal-placeholder">Select a doctor to see available dates</div>
      </div>

      <div id="ca-date-error"
           style="display:none;font-size:.75rem;color:#c33643;margin-top:-.4rem;margin-bottom:.5rem"></div>

      <label class="f-label">Time</label>
      <select name="appt_time" id="ca-time" class="f-input" required>
        <option value="">Select a date first…</option>
      </select>

      <!-- Client-side error -->
      <div id="ca-client-error"
           style="display:none;background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);
                  color:#c33643;border-radius:10px;padding:.6rem .85rem;font-size:.81rem;margin-bottom:.6rem">
      </div>

      <label class="f-label">Notes</label>
      <textarea name="notes" class="f-input" rows="2" placeholder="Reason for visit…"></textarea>

      <button type="submit" name="create_appt" class="btn-submit">Create Appointment</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-create')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     Modal: Reschedule
     ══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-reschedule">
  <div class="modal">
    <h3>Reschedule Appointment</h3>

    <?php if ($rs_error): ?>
    <div id="rs-server-error"
         style="background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);color:#c33643;
                border-radius:10px;padding:.65rem .9rem;font-size:.82rem;margin-bottom:.9rem">
      ⚠ <?= htmlspecialchars($rs_error) ?>
    </div>
    <?php endif ?>

    <form method="POST" id="rs-form">
      <input type="hidden" name="reschedule" value="1"/>
      <input type="hidden" name="appt_id"    id="rs-appt-id"/>
      <input type="hidden" name="active_tab" id="rs-tab"/>

      <!-- Hidden real date input for form submission -->
      <input type="hidden" name="new_date" id="rs-date"/>

      <label class="f-label">New Date
        <span id="rs-date-hint" style="font-weight:400;color:#9ab0ae;font-size:.75rem;margin-left:.4rem"></span>
      </label>
      <!-- Custom calendar picker -->
      <div id="rs-cal-wrap" class="cal-wrap">
        <div class="cal-placeholder">Loading schedule…</div>
      </div>

      <div id="rs-date-error"
           style="display:none;font-size:.75rem;color:#c33643;margin-top:-.4rem;margin-bottom:.5rem"></div>

      <label class="f-label">New Time</label>
      <select name="new_time" id="rs-time" class="f-input" required>
        <option value="">Pick a date first…</option>
      </select>

      <div id="rs-client-error"
           style="display:none;background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);
                  color:#c33643;border-radius:10px;padding:.6rem .85rem;font-size:.81rem;margin-bottom:.6rem">
      </div>

      <button type="submit" class="btn-submit">Confirm Reschedule</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-reschedule')">Cancel</button>
    </form>
  </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════════════════
   SHARED DATA
   ═══════════════════════════════════════════════════════════════════ */
const DOCTOR_SCHEDULES = <?= json_encode($doctor_schedules) ?>;
const DAY_NAMES   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTH_NAMES = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];
const TODAY_STR   = new Date().toISOString().slice(0, 10);

function fmt12(hhmm) {
  const [h, m] = hhmm.split(':').map(Number);
  return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
}

function hoursInRange(start, end) {
  const slots = [];
  let h = parseInt(start), eh = parseInt(end);
  for (; h < eh; h++) slots.push(String(h).padStart(2,'0') + ':00');
  return slots;
}

function availableDaySet(doctorId) {
  return new Set((DOCTOR_SCHEDULES[doctorId] || []).map(r => r.day));
}

function dateIsAllowed(dateStr, doctorId) {
  if (!doctorId || !dateStr || dateStr < TODAY_STR) return false;
  const dayName = DAY_NAMES[new Date(dateStr + 'T00:00:00').getDay()];
  return availableDaySet(doctorId).has(dayName);
}

function buildScheduleLegendHTML(doctorId) {
  const slots = DOCTOR_SCHEDULES[doctorId] || [];
  if (!slots.length) return '';
  const byDay = {};
  slots.forEach(s => (byDay[s.day] = byDay[s.day] || []).push(`${fmt12(s.start)} – ${fmt12(s.end)}`));
  return '<div class="cal-legend-title">Available Hours</div>' +
    Object.entries(byDay)
      .map(([day, times]) =>
        `<div><strong style="display:inline-block;min-width:2.6rem">${day.slice(0,3)}</strong>${times.join(' &nbsp;|&nbsp; ')}</div>`)
      .join('');
}

function populateTimeSelect(selEl, errEl, dateStr, doctorId, preselectHHMM = null) {
  selEl.innerHTML = '<option value="">Select a time…</option>';
  if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
  if (!dateStr || !doctorId) return 0;

  const dayName   = DAY_NAMES[new Date(dateStr + 'T00:00:00').getDay()];
  const schedules = (DOCTOR_SCHEDULES[doctorId] || []).filter(s => s.day === dayName);
  if (!schedules.length) return 0;

  const now        = new Date();
  const isToday    = dateStr === TODAY_STR;
  const cutoffHour = isToday ? now.getHours() + (now.getMinutes() > 0 ? 1 : 0) : -1;
  let added = 0;

  schedules.forEach(s => {
    hoursInRange(s.start, s.end).forEach(slot => {
      if (isToday && parseInt(slot) <= cutoffHour) return;
      const opt = document.createElement('option');
      opt.value       = slot + ':00';
      opt.textContent = fmt12(slot);
      if (preselectHHMM && slot === preselectHHMM) opt.selected = true;
      selEl.appendChild(opt);
      added++;
    });
  });

  if (added === 0 && errEl) {
    selEl.innerHTML = '<option value="">No slots remaining today</option>';
    errEl.textContent = 'All slots for this date have passed. Choose a future date.';
    errEl.style.display = 'block';
  }
  return added;
}

/* ═══════════════════════════════════════════════════════════════════
   CALENDAR PICKER CLASS
   Renders a month-grid calendar that visually blocks days the doctor
   has no schedule on. Past dates are grayed. Only valid days are
   clickable.
   ═══════════════════════════════════════════════════════════════════ */
class CalendarPicker {
  constructor(options) {
    // options: { wrapperId, hiddenInputId, timeSelId, errElId, onSelect, minDateStr }
    this.wrapper      = document.getElementById(options.wrapperId);
    this.hiddenInput  = document.getElementById(options.hiddenInputId);
    this.timeSel      = document.getElementById(options.timeSelId);
    this.errEl        = options.errElId ? document.getElementById(options.errElId) : null;
    this.onSelect     = options.onSelect || null;
    this.minDate      = options.minDateStr || TODAY_STR;

    this.doctorId     = null;
    this.selectedDate = null;
    const now = new Date();
    this.viewYear  = now.getFullYear();
    this.viewMonth = now.getMonth();

    // Store reference on DOM node so nav buttons can reach it
    this.wrapper._calPicker = this;
    this._render();
  }

  /** Call when doctor selection changes */
  setDoctor(doctorId, preselectDate = null) {
    this.doctorId     = doctorId || null;
    this.selectedDate = null;
    this.hiddenInput.value = '';
    if (this.timeSel) this.timeSel.innerHTML = '<option value="">Select a date first…</option>';
    if (this.errEl)   { this.errEl.textContent = ''; this.errEl.style.display = 'none'; }

    if (preselectDate) {
      const d = new Date(preselectDate + 'T00:00:00');
      this.viewYear  = d.getFullYear();
      this.viewMonth = d.getMonth();
    } else {
      const now = new Date();
      this.viewYear  = now.getFullYear();
      this.viewMonth = now.getMonth();
    }
    this._render();
  }

  /** Programmatically select a date (e.g. when reopening reschedule modal) */
  selectDate(dateStr, preselectTimHHMM = null) {
    if (!dateStr) return;
    const d = new Date(dateStr + 'T00:00:00');
    this.viewYear  = d.getFullYear();
    this.viewMonth = d.getMonth();
    this.selectedDate     = dateStr;
    this.hiddenInput.value = dateStr;
    this._render();
    if (this.timeSel) {
      populateTimeSelect(this.timeSel, this.errEl, dateStr, this.doctorId, preselectTimHHMM);
    }
    if (this.onSelect) this.onSelect(dateStr);
  }

  _clickDay(dateStr) {
    this.selectedDate      = dateStr;
    this.hiddenInput.value = dateStr;
    if (this.errEl) { this.errEl.textContent = ''; this.errEl.style.display = 'none'; }
    this._render();
    if (this.timeSel) {
      populateTimeSelect(this.timeSel, this.errEl, dateStr, this.doctorId);
    }
    if (this.onSelect) this.onSelect(dateStr);
  }

  prevMonth() {
    this.viewMonth--;
    if (this.viewMonth < 0) { this.viewMonth = 11; this.viewYear--; }
    this._render();
  }

  nextMonth() {
    this.viewMonth++;
    if (this.viewMonth > 11) { this.viewMonth = 0; this.viewYear++; }
    this._render();
  }

  _render() {
    const { viewYear: yr, viewMonth: mo, doctorId, selectedDate, minDate } = this;
    const availDays = doctorId ? availableDaySet(doctorId) : new Set();

    // Disable prev button if already at the min month
    const minD   = new Date(minDate + 'T00:00:00');
    const atMin  = yr === minD.getFullYear() && mo === minD.getMonth();

    // Calendar grid data
    const firstDow    = new Date(yr, mo, 1).getDay();   // 0=Sun
    const daysInMonth = new Date(yr, mo + 1, 0).getDate();
    const todayStr    = TODAY_STR;

    // Build cells HTML
    let cells = '';
    // Empty leading cells
    for (let i = 0; i < firstDow; i++) cells += `<div class="cal-cell empty"></div>`;

    for (let d = 1; d <= daysInMonth; d++) {
      const ds  = `${yr}-${String(mo+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dow = DAY_NAMES[new Date(ds + 'T00:00:00').getDay()];
      const isPast    = ds < minDate;
      const isBlocked = !doctorId || (!isPast && !availDays.has(dow));
      const isToday   = ds === todayStr;
      const isSel     = ds === selectedDate;

      let cls = 'cal-cell';
      if (isPast)         cls += ' past';
      else if (isBlocked) cls += ' blocked';
      else                cls += ' available';
      if (isToday)        cls += ' today';
      if (isSel)          cls += ' selected';

      const clickable = !isPast && !isBlocked;
      const onclick   = clickable
        ? `onclick="document.getElementById('${this.wrapper.id}')._calPicker._clickDay('${ds}')"`
        : '';

      // Tooltip on blocked cells to explain why
      let title = '';
      if (!isPast && isBlocked && doctorId) {
        const avail = [...availDays].join(', ') || 'none';
        title = `title="Doctor unavailable on ${dow}s. Available: ${avail}"`;
      }

      cells += `<div class="${cls}" ${onclick} ${title}>${d}</div>`;
    }

    // Legend HTML
    const legendHTML = doctorId ? buildScheduleLegendHTML(doctorId) : '';

    // Full calendar HTML
    this.wrapper.innerHTML = `
      <div class="cal-header">
        <button type="button" class="cal-nav" ${atMin ? 'disabled' : ''}
                onclick="document.getElementById('${this.wrapper.id}')._calPicker.prevMonth()">&#8249;</button>
        <span>${MONTH_NAMES[mo]} ${yr}</span>
        <button type="button" class="cal-nav"
                onclick="document.getElementById('${this.wrapper.id}')._calPicker.nextMonth()">&#8250;</button>
      </div>
      <div class="cal-grid">
        <div class="cal-day-name">Su</div>
        <div class="cal-day-name">Mo</div>
        <div class="cal-day-name">Tu</div>
        <div class="cal-day-name">We</div>
        <div class="cal-day-name">Th</div>
        <div class="cal-day-name">Fr</div>
        <div class="cal-day-name">Sa</div>
        ${cells}
      </div>
      ${legendHTML ? `<div class="cal-legend">${legendHTML}</div>` : ''}
    `;

    // Re-attach self reference after innerHTML wipe
    this.wrapper._calPicker = this;

    // Toggle disabled styling
    if (!doctorId) {
      this.wrapper.classList.add('cal-disabled');
    } else {
      this.wrapper.classList.remove('cal-disabled');
    }
  }
}

/* ═══════════════════════════════════════════════════════════════════
   INSTANTIATE PICKERS
   ═══════════════════════════════════════════════════════════════════ */
const caCal = new CalendarPicker({
  wrapperId:     'ca-cal-wrap',
  hiddenInputId: 'ca-date',
  timeSelId:     'ca-time',
  errElId:       'ca-date-error',
  minDateStr:    TODAY_STR
});

const rsCal = new CalendarPicker({
  wrapperId:     'rs-cal-wrap',
  hiddenInputId: 'rs-date',
  timeSelId:     'rs-time',
  errElId:       'rs-date-error',
  minDateStr:    TODAY_STR
});

/* ═══════════════════════════════════════════════════════════════════
   CREATE APPOINTMENT MODAL
   ═══════════════════════════════════════════════════════════════════ */
function onCaDoctorChange() {
  const sel = document.getElementById('ca-doctor');
  const did = parseInt(sel.value) || null;
  caCal.setDoctor(did);
  document.getElementById('ca-client-error').style.display = 'none';
}

document.getElementById('ca-form').addEventListener('submit', function(e) {
  const date  = document.getElementById('ca-date').value;
  const time  = document.getElementById('ca-time').value;
  const did   = parseInt(document.getElementById('ca-doctor').value) || null;
  const errEl = document.getElementById('ca-client-error');
  errEl.style.display = 'none';

  if (!did) {
    e.preventDefault(); errEl.textContent = 'Please select a doctor.'; errEl.style.display = 'block'; return;
  }
  if (!date) {
    e.preventDefault(); errEl.textContent = 'Please select a date from the calendar.'; errEl.style.display = 'block'; return;
  }
  if (!time) {
    e.preventDefault(); errEl.textContent = 'Please select a time slot.'; errEl.style.display = 'block'; return;
  }
  if (new Date(date + 'T' + time) <= new Date()) {
    e.preventDefault(); errEl.textContent = 'Cannot book an appointment in the past.'; errEl.style.display = 'block'; return;
  }
  if (!dateIsAllowed(date, did)) {
    e.preventDefault();
    errEl.textContent = "Selected date is not within the doctor's schedule.";
    errEl.style.display = 'block';
  }
});

/* ═══════════════════════════════════════════════════════════════════
   RESCHEDULE MODAL
   ═══════════════════════════════════════════════════════════════════ */
function openReschedule(apptId, doctorId, currentDate, currentTime) {
  document.getElementById('rs-appt-id').value = apptId;
  document.getElementById('rs-tab').value     = _activeTab;
  document.getElementById('rs-client-error').style.display = 'none';
  document.getElementById('rs-date-error').style.display   = 'none';

  const srvErr = document.getElementById('rs-server-error');
  if (srvErr) srvErr.style.display = 'none';

  // Set doctor on calendar (which re-renders with correct blocked days)
  rsCal.setDoctor(doctorId);
  // Pre-select the current date and time
  rsCal.selectDate(currentDate, currentTime.substring(0, 5));

  openModal('modal-reschedule');
}

document.getElementById('rs-form').addEventListener('submit', function(e) {
  const date  = document.getElementById('rs-date').value;
  const time  = document.getElementById('rs-time').value;
  const errEl = document.getElementById('rs-client-error');
  errEl.style.display = 'none';

  if (!date) {
    e.preventDefault(); errEl.textContent = 'Please select a date from the calendar.'; errEl.style.display = 'block'; return;
  }
  if (!time) {
    e.preventDefault(); errEl.textContent = 'Please select a time slot.'; errEl.style.display = 'block'; return;
  }
  if (new Date(date + 'T' + time) <= new Date()) {
    e.preventDefault(); errEl.textContent = 'Cannot reschedule to a past date or time.'; errEl.style.display = 'block'; return;
  }
});

/* ═══════════════════════════════════════════════════════════════════
   FILTER + QUICK ACTION
   ═══════════════════════════════════════════════════════════════════ */
let _activeTab = '<?= $active_tab ?>';

function quickAction(id, action) {
  document.getElementById('qf-appt-id').value = id;
  document.getElementById('qf-action').value  = action;
  document.getElementById('qf-notes').value   = '';
  document.getElementById('qf-tab').value     = _activeTab;
  document.getElementById('quick-form').submit();
}

function filterStatus(status) {
  _activeTab = status;
  document.querySelectorAll('#appt-tbody tr[data-status]').forEach(r => {
    r.style.display = (status === 'All' || r.dataset.status === status) ? '' : 'none';
  });
  document.querySelectorAll('[id^=filter-]').forEach(b => {
    const active = b.id === 'filter-' + status;
    b.style.background = active ? 'var(--blue)' : 'rgba(36,68,65,.07)';
    b.style.color      = active ? '#fff'        : 'var(--text)';
  });
}

filterStatus(_activeTab);

// Auto-reopen modals after server-side redirect errors
<?php if ($rs_open): ?>openModal('modal-reschedule');<?php endif ?>
<?php if ($ca_open): ?>openModal('modal-create');<?php endif ?>
</script>

<?php require_once 'includes/footer.php'; ?>