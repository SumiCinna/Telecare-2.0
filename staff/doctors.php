<?php
// staff/doctors.php
require_once 'includes/auth.php';

function fmt12Time(string $t): string {
    [$h, $m] = explode(':', $t);
    $h = (int)$h;
    $ap = $h >= 12 ? 'PM' : 'AM';
    $hr = $h % 12 ?: 12;
    return $hr . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ' ' . $ap;
}

$active_page = 'doctors';

// NOTE: adjust $staffId below to whatever variable/session key includes/auth.php
// actually sets for the logged-in staff member (mirrors how doctor pages get $doctor_id).
$staffId = $staff_id ?? ($_SESSION['staff_id'] ?? ($_SESSION['user_id'] ?? null));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_schedule_request') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $rstmt = $conn->prepare("SELECT * FROM doctor_schedule_requests WHERE id=? AND status='Pending'");
        $rstmt->bind_param('i', $reqId);
        $rstmt->execute();
        $reqRow = $rstmt->get_result()->fetch_assoc();
        $rstmt->close();

        if ($reqRow) {
            $slots = json_decode($reqRow['slots_json'], true) ?: [];
            try {
                $conn->begin_transaction();

                $del = $conn->prepare("DELETE FROM doctor_schedules WHERE doctor_id=?");
                $del->bind_param('i', $reqRow['doctor_id']);
                $del->execute();

                if ($slots) {
                    $ins = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                    foreach ($slots as $slot) {
                        $start = $slot['start'] . ':00';
                        $end = $slot['end'] . ':00';
                        $ins->bind_param('isss', $reqRow['doctor_id'], $slot['day'], $start, $end);
                        $ins->execute();
                    }
                }

                $upd = $conn->prepare("UPDATE doctor_schedule_requests SET status='Approved', reviewed_at=NOW(), reviewed_by=?, doctor_seen=0 WHERE id=?");
                $upd->bind_param('ii', $staffId, $reqId);
                $upd->execute();

                $conn->commit();
                $_SESSION['toast'] = 'Schedule change approved and applied.';
            } catch (Throwable $e) {
                $conn->rollback();
                $_SESSION['toast_error'] = 'Failed to apply schedule change. Please try again.';
            }
        } else {
            $_SESSION['toast_error'] = 'This request is no longer pending.';
        }

        header('Location: doctors.php?doctor_id=' . (int)($_POST['doctor_id'] ?? 0)); exit;
    }

    if ($action === 'reject_schedule_request') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $note = trim((string)($_POST['staff_note'] ?? ''));
        $upd = $conn->prepare("UPDATE doctor_schedule_requests SET status='Rejected', reviewed_at=NOW(), reviewed_by=?, staff_note=?, doctor_seen=0 WHERE id=? AND status='Pending'");
        $upd->bind_param('isi', $staffId, $note, $reqId);
        $upd->execute();
        $_SESSION['toast'] = $upd->affected_rows ? 'Schedule change rejected.' : 'Nothing to reject.';
        header('Location: doctors.php?doctor_id=' . (int)($_POST['doctor_id'] ?? 0)); exit;
    }
}

$stat_pending = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];

$doctorRows = $conn->query("SELECT id, full_name, specialty, consultation_fee, status FROM doctors ORDER BY full_name ASC");
$doctors = [];
if ($doctorRows) {
    while ($r = $doctorRows->fetch_assoc()) {
        $doctors[] = $r;
    }
}

$selectedDoctorId = (int)($_GET['doctor_id'] ?? 0);
if ($selectedDoctorId <= 0 && !empty($doctors)) {
    $selectedDoctorId = (int)$doctors[0]['id'];
}

$selectedDoctor = null;
foreach ($doctors as $doc) {
    if ((int)$doc['id'] === $selectedDoctorId) {
        $selectedDoctor = $doc;
        break;
    }
}

$schedules = [];
if ($selectedDoctor) {
    $sstmt = $conn->prepare("SELECT day_of_week, TIME_FORMAT(start_time, '%H:%i') AS start_time, TIME_FORMAT(end_time, '%H:%i') AS end_time
                             FROM doctor_schedules
                             WHERE doctor_id=?
                             ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time");
    $sstmt->bind_param('i', $selectedDoctorId);
    $sstmt->execute();
    $sres = $sstmt->get_result();
    while ($s = $sres->fetch_assoc()) {
        $schedules[] = $s;
    }
}

// All pending schedule requests, across every doctor, so staff never miss one
// regardless of which doctor happens to be selected right now.
$pendingScheduleRequests = [];
$psrRows = $conn->query("SELECT r.id, r.doctor_id, r.slots_json, r.submitted_at, d.full_name doctor_name
                          FROM doctor_schedule_requests r
                          JOIN doctors d ON d.id = r.doctor_id
                          WHERE r.status='Pending'
                          ORDER BY r.submitted_at ASC");
if ($psrRows) {
    while ($row = $psrRows->fetch_assoc()) {
        $pendingScheduleRequests[] = $row;
    }
}

$selectedPendingRequest = null;
foreach ($pendingScheduleRequests as $r) {
    if ((int)$r['doctor_id'] === $selectedDoctorId) {
        $selectedPendingRequest = $r;
        break;
    }
}
$selectedPendingSlots = $selectedPendingRequest ? json_decode($selectedPendingRequest['slots_json'], true) : [];

$doctorAppointments = [];
if ($selectedDoctor) {
    $astmt = $conn->prepare("SELECT a.id, a.appointment_date, TIME_FORMAT(a.appointment_time,'%H:%i') appointment_time,
                                     a.type, a.status, p.full_name patient_name
                              FROM appointments a
                              JOIN patients p ON p.id = a.patient_id
                              WHERE a.doctor_id=? AND a.appointment_date >= CURDATE()
                              ORDER BY a.appointment_date, a.appointment_time");
    $astmt->bind_param('i', $selectedDoctorId);
    $astmt->execute();
    $ares = $astmt->get_result();
    while ($a = $ares->fetch_assoc()) {
        $doctorAppointments[] = $a;
    }
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

require_once 'includes/header.php';
?>

<div class="sec-head">
  <h2>Doctor Fees &amp; Schedule</h2>
</div>

<?php if (!empty($pendingScheduleRequests)): ?>
<div class="card" style="margin-bottom:1rem;border-left:4px solid #B54708;background:#fff7ed;">
  <div style="font-weight:700;font-size:.85rem;color:#B54708;margin-bottom:.6rem;">
    <?= count($pendingScheduleRequests) ?> schedule change request<?= count($pendingScheduleRequests) === 1 ? '' : 's' ?> awaiting your review
  </div>
  <div style="display:flex;flex-direction:column;gap:.35rem;">
    <?php foreach ($pendingScheduleRequests as $r): ?>
      <a href="?doctor_id=<?= (int)$r['doctor_id'] ?>" style="display:flex;justify-content:space-between;font-size:.78rem;color:#92400e;text-decoration:none;padding:.45rem .6rem;border-radius:7px;background:<?= (int)$r['doctor_id'] === $selectedDoctorId ? '#fed7aa' : '#fff' ?>;border:1px solid #fed7aa;">
        <span style="font-weight:700;">Dr. <?= htmlspecialchars($r['doctor_name']) ?></span>
        <span>Submitted <?= date('M d, Y g:i A', strtotime($r['submitted_at'])) ?> →</span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1rem;">
  <div style="font-size:.74rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.35rem;">Select Doctor</div>
  <form method="GET" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;">
    <select name="doctor_id" class="f-input" style="max-width:360px;margin-bottom:0;" onchange="this.form.submit()">
      <?php foreach ($doctors as $doc): ?>
      <option value="<?= (int)$doc['id'] ?>" <?= (int)$doc['id'] === $selectedDoctorId ? 'selected' : '' ?>>
        Dr. <?= htmlspecialchars($doc['full_name']) ?><?= !empty($doc['specialty']) ? ' — ' . htmlspecialchars($doc['specialty']) : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$selectedDoctor): ?>
  <div class="card"><div class="empty-row">No doctor records found.</div></div>
<?php else: ?>

<div style="display:grid;grid-template-columns:minmax(260px,360px) 1fr;gap:1rem;align-items:start;">
  <div class="card" style="margin-bottom:0;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.55rem;">
      <div style="font-size:.74rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Consultation Fee</div>
      <span class="badge-sm" style="display:inline-block;padding:.2rem .55rem;border-radius:50px;font-size:.64rem;font-weight:700;letter-spacing:.04em;background:rgba(63,130,227,.1);color:var(--blue);">View only</span>
    </div>
    <div style="font-weight:700;font-size:.92rem;margin-bottom:.3rem;">Dr. <?= htmlspecialchars($selectedDoctor['full_name']) ?></div>
    <div style="font-size:.77rem;color:var(--muted);margin-bottom:1.1rem;"><?= htmlspecialchars($selectedDoctor['specialty'] ?? 'General') ?></div>

    <div style="font-size:1.7rem;font-weight:800;">₱<?= number_format((float)$selectedDoctor['consultation_fee'], 2) ?></div>
    <div style="font-size:.76rem;color:var(--muted);margin-top:.7rem;">The doctor sets this fee from their own account. It's shown here for reference only.</div>
  </div>

  <div class="card" style="margin-bottom:0;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.7rem;">
      <div style="font-size:.74rem;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Weekly Schedule</div>
      <div style="display:flex;align-items:center;gap:.5rem;">
        <button type="button" class="btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:5px;" onclick="openModal('modal-doctor-calendar')">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          View Calendar
        </button>
        <span class="badge-sm" style="display:inline-block;padding:.2rem .55rem;border-radius:50px;font-size:.64rem;font-weight:700;letter-spacing:.04em;background:rgba(63,130,227,.1);color:var(--blue);">View only</span>
      </div>
    </div>

    <?php if ($selectedPendingRequest): ?>
    <div style="margin-bottom:1rem;padding:.85rem;border-radius:10px;border:1px solid #fed7aa;background:#fff7ed;">
      <div style="font-weight:700;font-size:.8rem;color:#c2410c;margin-bottom:.6rem;">
        Pending schedule change — submitted <?= date('M d, Y g:i A', strtotime($selectedPendingRequest['submitted_at'])) ?>
      </div>

      <div style="display:flex;flex-direction:column;gap:.4rem;margin-bottom:.85rem;">
        <?php if (empty($selectedPendingSlots)): ?>
          <div class="empty-row">Doctor requested to clear their entire weekly schedule.</div>
        <?php else: foreach ($selectedPendingSlots as $slot): ?>
          <div style="display:flex;justify-content:space-between;padding:.5rem .65rem;border-radius:8px;background:#fff;border:1px solid #fed7aa;font-size:.78rem;">
            <span style="font-weight:700;"><?= htmlspecialchars($slot['day']) ?></span>
            <span style="color:#c2410c;font-weight:600;"><?= fmt12Time($slot['start'] . ':00') ?> – <?= fmt12Time($slot['end'] . ':00') ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <form method="POST" onsubmit="return confirm('Approve this schedule change? It will replace the doctor\'s current live schedule immediately.');">
          <input type="hidden" name="action" value="approve_schedule_request">
          <input type="hidden" name="request_id" value="<?= (int)$selectedPendingRequest['id'] ?>">
          <input type="hidden" name="doctor_id" value="<?= (int)$selectedDoctorId ?>">
          <button type="submit" class="btn-primary btn-sm">Approve &amp; Apply</button>
        </form>
        <button type="button" class="btn-light btn-sm" onclick="openModal('modal-reject-<?= (int)$selectedPendingRequest['id'] ?>')">Reject</button>
      </div>
    </div>

    <div class="modal-overlay" id="modal-reject-<?= (int)$selectedPendingRequest['id'] ?>">
      <div class="modal">
        <h3 style="margin-bottom:.6rem;">Reject Schedule Change</h3>
        <form method="POST">
          <input type="hidden" name="action" value="reject_schedule_request">
          <input type="hidden" name="request_id" value="<?= (int)$selectedPendingRequest['id'] ?>">
          <input type="hidden" name="doctor_id" value="<?= (int)$selectedDoctorId ?>">
          <label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.35rem;">Reason (optional, shown to doctor)</label>
          <textarea name="staff_note" class="f-input" rows="3" style="width:100%;margin-bottom:.75rem;" placeholder="e.g. Conflicts with clinic hours"></textarea>
          <div style="display:flex;gap:.5rem;">
            <button type="submit" class="btn-primary btn-sm">Confirm Reject</button>
            <button type="button" class="btn-light btn-sm" onclick="closeModal('modal-reject-<?= (int)$selectedPendingRequest['id'] ?>')">Cancel</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div style="font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">
      Currently Live
    </div>
    <?php if (empty($schedules)): ?>
      <div class="empty-row">No schedule set yet.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:.5rem;">
        <?php foreach ($schedules as $s): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem .75rem;border-radius:10px;background:rgba(36,68,65,.05);">
          <span style="font-weight:700;font-size:.82rem;"><?= htmlspecialchars($s['day_of_week']) ?></span>
          <span style="font-size:.8rem;color:var(--blue);font-weight:600;"><?= fmt12Time($s['start_time']) ?> – <?= fmt12Time($s['end_time']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="font-size:.76rem;color:var(--muted);margin-top:1rem;">The doctor requests changes from their own account; changes only take effect once you approve them here.</div>
  </div>
</div>

<!-- Modal: Doctor Calendar -->
<div class="modal-overlay" id="modal-doctor-calendar">
  <div class="modal docal-modal">
    <div class="docal-head">
      <div>
        <h3 style="margin-bottom:.15rem;">Dr. <?= htmlspecialchars($selectedDoctor['full_name']) ?></h3>
        <div style="font-size:.76rem;color:var(--muted);"><?= htmlspecialchars($selectedDoctor['specialty'] ?? 'General') ?> &middot; upcoming appointments</div>
      </div>
      <button type="button" class="docal-close" onclick="closeModal('modal-doctor-calendar')" aria-label="Close">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="docal-legend">
      <span><i class="docal-dot" style="background:var(--secondary)"></i>Confirmed</span>
      <span><i class="docal-dot" style="background:#B54708"></i>Pending / Dr. Approved</span>
      <span><i class="docal-dot" style="background:var(--neutral-300)"></i>Cancelled</span>
    </div>

    <div class="calendar-nav">
      <button type="button" id="doc-cal-prev" aria-label="Previous month">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <h2 id="doc-cal-month"></h2>
      <button type="button" id="doc-cal-next" aria-label="Next month">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>
    <div class="calendar" id="doc-calendar"></div>
  </div>
</div>

<?php endif; ?>

<script>
const docSchedules    = <?= json_encode($schedules ?? [], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const docAppointments = <?= json_encode($doctorAppointments, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

(function () {
  let calMonth = new Date();
  const grid  = document.getElementById('doc-calendar');
  const label = document.getElementById('doc-cal-month');
  if (!grid) return;

  const key  = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  const time = v => { const [h,m] = v.split(':').map(Number); return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`; };
  const safe = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const statusClass = status => {
    if (status === 'Confirmed' || status === 'Completed') return 'status-confirmed';
    if (status === 'Cancelled') return 'status-cancelled';
    return 'status-pending'; // Pending / DoctorApproved
  };

  function render() {
    const year = calMonth.getFullYear(), month = calMonth.getMonth();
    label.textContent = calMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    grid.innerHTML = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d => `<div class="calendar-day">${d}</div>`).join('');

    const first = new Date(year, month, 1).getDay();
    const total = new Date(year, month + 1, 0).getDate();
    const today = key(new Date());

    for (let i = 0; i < first; i++) grid.innerHTML += '<div class="calendar-cell empty"></div>';

    for (let day = 1; day <= total; day++) {
      const date = new Date(year, month, day), dateKey = key(date);
      const events = docAppointments.filter(a => a.appointment_date === dateKey);
      let html = `<div class="calendar-cell ${dateKey === today ? 'today' : ''}"><span class="calendar-number ${dateKey === today ? 'today' : ''}">${day}</span>`;
      events.slice(0, 3).forEach(a => {
        html += `<div class="calendar-event ${statusClass(a.status)}" title="${safe(a.patient_name)} — ${a.status}">
          <strong>${time(a.appointment_time)}</strong>${safe(a.patient_name)}
        </div>`;
      });
      if (events.length > 3) html += `<div style="font-size:.55rem;color:var(--muted);margin-top:2px;">+${events.length - 3} more</div>`;
      grid.innerHTML += html + '</div>';
    }
  }

  document.getElementById('doc-cal-prev').onclick = () => { calMonth.setMonth(calMonth.getMonth() - 1); render(); };
  document.getElementById('doc-cal-next').onclick = () => { calMonth.setMonth(calMonth.getMonth() + 1); render(); };
  render();
})();
</script>

<?php require_once 'includes/footer.php'; ?>