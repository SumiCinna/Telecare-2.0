<?php
require_once 'includes/auth.php';

// ── Stats ──
$patient_result = $conn->query("SELECT COUNT(*) c FROM patient_doctors WHERE doctor_id=$doctor_id");
$patient_count = $patient_result ? $patient_result->fetch_assoc()['c'] : 0;

$today_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND appointment_date=CURDATE() AND status IN ('Pending','Confirmed')");
$today_appts = $today_result ? $today_result->fetch_assoc()['c'] : 0;

$pending_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='Pending'");
$pending_count = $pending_result ? $pending_result->fetch_assoc()['c'] : 0;

// ── Today's schedule ──
$today = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.profile_photo AS patient_photo
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE a.doctor_id=$doctor_id AND a.appointment_date=CURDATE()
    AND a.status IN ('Pending','Confirmed')
    ORDER BY a.appointment_time ASC LIMIT 5
");

// ── Recent patients ──
$recent_patients = $conn->query("
    SELECT p.*
    FROM patients p
    JOIN patient_doctors pd ON pd.patient_id=p.id
    WHERE pd.doctor_id=$doctor_id
    LIMIT 4
");

$page_title       = 'Home — TELE-CARE';
$page_title_short = 'Dashboard';
$active_nav       = 'home';
require_once 'includes/header.php';
?>

<style>
/* Dashboard layout - responsive with sidebar/navbar */
.page {
  background: transparent !important;
  overflow-x: clip;
}

/* Desktop layout */
@media (min-width: 768px) {
  .page {
    max-width: calc(100% - 240px);
    margin-left: 240px;
    padding: 1.8rem 2rem 2rem !important;
  }
}

/* Tablet/Mobile layout */
@media (max-width: 767px) {
  .page {
    max-width: 100%;
    margin: 0 auto;
    padding: 1rem 1rem 80px !important;
  }
}

.db-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: auto auto;
  gap: 1.1rem;
}
.db-welcome { grid-column: 1 / -1; }
.db-stats   { grid-column: 1 / -1; }
.db-appts   { grid-column: 1 / 2; }
.db-doctors { grid-column: 2 / 3; }

@media (max-width: 900px) {
  .db-grid { grid-template-columns: 1fr; }
  .db-appts, .db-doctors { grid-column: 1 / -1; }
}

</style>

<div class="page">

  <!-- Welcome Banner -->
  <div style="background:linear-gradient(135deg,var(--green),var(--green-dark));border-radius:20px;padding:1.6rem;margin-bottom:1.2rem;position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background-image:radial-gradient(circle at 80% 20%,rgba(255,255,255,0.12) 0%,transparent 50%),radial-gradient(circle at 20% 80%,rgba(255,255,255,0.06) 0%,transparent 40%);pointer-events:none;"></div>
    <div style="position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none;"></div>
    <?php
      $firstName = explode(' ', $doc['full_name']);
      $firstName = $firstName[0];
    ?>
    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.45);margin-bottom:0.3rem;position:relative;z-index:1;">
      <?= date('l, F j') ?>
    </div>
    <h2 style="font-size:1.5rem;color:#fff;margin-bottom:0.3rem;position:relative;z-index:1;">
      Good <?= (date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening')) ?>,<br/>Dr. <?= htmlspecialchars($firstName) ?>.
    </h2>
    <p style="color:rgba(255,255,255,0.6);font-size:0.83rem;position:relative;z-index:1;margin-bottom:1.2rem;">
      You have <strong style="color:#fff;"><?= $today_appts ?></strong> appointment<?= $today_appts != 1 ? 's' : '' ?> today
      <?php if ($pending_count > 0): ?> and <strong style="color:#fbbf24;"><?= $pending_count ?> pending</strong> review<?php endif; ?>.
    </p>
    <a href="appointments.php" style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(255,255,255,0.15);color:#fff;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.25);padding:0.55rem 1.2rem;border-radius:50px;font-size:0.82rem;font-weight:600;text-decoration:none;position:relative;z-index:1;">
      View Schedule →
    </a>
  </div>

  <!-- Quick Stats -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.8rem;margin-bottom:1.2rem;">
    <?php
    $stats = [
      ['num' => $patient_count, 'lbl' => 'Patients',  'color' => 'var(--green)'],
      ['num' => $today_appts,   'lbl' => 'Today',      'color' => 'var(--blue)'],
      ['num' => $pending_count, 'lbl' => 'Pending',    'color' => '#d97706'],
    ];
    foreach ($stats as $s): ?>
    <div class="card" style="text-align:center;padding:1rem 0.8rem;margin-bottom:0;">
      <div style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:<?= $s['color'] ?>;line-height:1;"><?= $s['num'] ?></div>
      <div style="font-size:0.68rem;color:var(--muted);margin-top:0.3rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;"><?= $s['lbl'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Today's Schedule -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
      <div class="section-label" style="margin-bottom:0;">Today's Schedule</div>
      <a href="appointments.php" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">See all</a>
    </div>
    <?php if ($today && $today->num_rows > 0):
      while ($a = $today->fetch_assoc()):
    ?>
    <div class="appt-item">
      <div class="appt-date-box" style="background:rgba(36,68,65,0.07);">
        <div class="day" style="font-size:1rem;color:var(--green);"><?= date('h:i', strtotime($a['appointment_time'])) ?></div>
        <div class="mon"><?= date('A', strtotime($a['appointment_time'])) ?></div>
      </div>
      <div class="pat-avatar">
        <?php if (!empty($a['patient_photo'])): ?>
          <img src="../../<?= htmlspecialchars($a['patient_photo']) ?>"/>
        <?php else: ?>
          <?= strtoupper(substr($a['patient_name'], 0, 2)) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($a['patient_name']) ?></div>
        <div style="font-size:0.76rem;color:var(--muted);"><?= htmlspecialchars($a['type'] ?? 'Consultation') ?></div>
      </div>
      <span class="badge <?= $a['status']==='Confirmed'?'badge-green':'badge-orange' ?>"><?= $a['status'] ?></span>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-state">
      <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      No appointments today.
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Patients -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
      <div class="section-label" style="margin-bottom:0;">My Patients</div>
      <a href="patients.php" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">See all</a>
    </div>
    <?php if ($recent_patients && $recent_patients->num_rows > 0):
      while ($pt = $recent_patients->fetch_assoc()):
    ?>
    <div class="patient-item">
      <div class="pat-avatar">
        <?php if (!empty($pt['profile_photo'])): ?>
          <img src="../../<?= htmlspecialchars($pt['profile_photo']) ?>"/>
        <?php else: ?>
          <?= strtoupper(substr($pt['full_name'], 0, 2)) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($pt['full_name']) ?></div>
        <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($pt['email']) ?></div>
      </div>
      <a href="chat.php?patient_id=<?= $pt['id'] ?>" style="background:rgba(36,68,65,0.08);border-radius:10px;padding:0.4rem 0.7rem;font-size:0.75rem;font-weight:600;color:var(--green);text-decoration:none;">Message</a>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-state">No patients assigned yet.</div>
    <?php endif; ?>
  </div>

</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>