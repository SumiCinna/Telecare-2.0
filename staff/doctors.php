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
      <span class="badge-sm" style="display:inline-block;padding:.2rem .55rem;border-radius:50px;font-size:.64rem;font-weight:700;letter-spacing:.04em;background:rgba(63,130,227,.1);color:var(--blue);">View only</span>
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

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>