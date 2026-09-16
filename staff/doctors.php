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

    <div style="font-size:.76rem;color:var(--muted);margin-top:1rem;">The doctor manages their own availability from their account. Changes made there appear here automatically.</div>
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