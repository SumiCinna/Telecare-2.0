<?php
// doctor/dashboard.php
require_once 'includes/auth.php';

$patient_result = $conn->query("SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id=$doctor_id");
$patient_count = $patient_result ? (int)$patient_result->fetch_assoc()['c'] : 0;
$today_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND appointment_date=CURDATE() AND status NOT IN ('Cancelled')");
$today_appts = $today_result ? (int)$today_result->fetch_assoc()['c'] : 0;
$pending_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status IN ('Pending','DoctorApproved')");
$pending_count = $pending_result ? (int)$pending_result->fetch_assoc()['c'] : 0;
$completed_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='Completed'");
$completed_count = $completed_result ? (int)$completed_result->fetch_assoc()['c'] : 0;

$today = $conn->query("SELECT a.*, p.full_name AS patient_name, p.profile_photo AS patient_photo FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.doctor_id=$doctor_id AND a.appointment_date=CURDATE() ORDER BY a.appointment_time ASC LIMIT 8");
$next_up = $conn->query("SELECT a.*, p.full_name AS patient_name FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.doctor_id=$doctor_id AND a.appointment_date=CURDATE() AND a.status IN ('Pending','DoctorApproved','Confirmed') AND a.appointment_time >= CURTIME() ORDER BY a.appointment_time ASC LIMIT 3");

$page_title = 'Dashboard — TELE-CARE';
$page_title_short = 'Dashboard';
$active_nav = 'home';
require_once 'includes/header.php';
$first_name = explode(' ', trim($doc['full_name']))[0] ?? 'Doctor';
?>

<style>
.doctor-dashboard { max-width: 1440px; }
.dashboard-heading { margin-bottom: 1.35rem; }
.dashboard-heading h1 { color:var(--neutral-900); font-size:clamp(1.45rem,2.5vw,2rem); margin-bottom:.3rem; }
.dashboard-heading p { color:var(--neutral-500); font-size:.82rem; }
.kpi-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.85rem; margin-bottom:1.1rem; }
.kpi-card { background:var(--surface); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:1rem 1.05rem; min-height:114px; box-shadow:var(--shadow-sm); }
.kpi-icon { width:34px; height:34px; display:grid; place-items:center; border-radius:var(--radius-sm); background:var(--secondary-soft); color:var(--secondary-dark); margin-bottom:.75rem; }
.kpi-icon svg { width:18px; height:18px; }
.kpi-value { color:var(--neutral-900); font-size:1.2rem; font-weight:700; line-height:1; }
.kpi-label { color:var(--neutral-700); font-size:.68rem; margin-top:.35rem; }
.dashboard-columns { display:grid; grid-template-columns:minmax(0,1.8fr) minmax(260px,.8fr); gap:1.1rem; align-items:start; }
.dashboard-panel { background:var(--surface); border:1px solid var(--border-color); border-radius:var(--radius-md); overflow:hidden; box-shadow:var(--shadow-sm); }
.panel-heading { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.05rem; }
.panel-heading h2 { color:var(--neutral-900); font-family:'Inter',sans-serif; font-size:.95rem; font-weight:700; }
.panel-link { color:var(--primary); font-size:.72rem; font-weight:700; text-decoration:none; }
.appointment-table { width:100%; border-collapse:collapse; table-layout:fixed; }
.appointment-table th { background:#f1f4ff; color:#34425a; font-size:.61rem; font-weight:700; letter-spacing:.05em; padding:.75rem 1rem; text-align:left; text-transform:uppercase; }
.appointment-table td { border-top:1px solid #e8ecf5; color:#27364d; font-size:.72rem; padding:.75rem 1rem; vertical-align:middle; }
.patient-cell { display:flex; align-items:center; gap:.55rem; font-weight:600; }
.table-avatar { width:24px; height:24px; border-radius:50%; background:#e7ecfa; color:#bd1d2b; display:grid; place-items:center; flex:none; font-size:.56rem; font-weight:700; }
.appointment-time { color:#34425a; white-space:nowrap; }
.status-pill { border-radius:20px; display:inline-block; font-size:.61rem; padding:.25rem .55rem; white-space:nowrap; }
.status-completed { background:#e7f8ef; color:#078447; } .status-cancelled { background:#ffe5e6; color:#c42d3a; }
.status-pending,.status-doctorapproved { background:#fff1dc; color:#a66300; } .status-confirmed { background:#e9efff; color:#3158a5; }
.table-action { color:#bd1d2b; font-size:.68rem; font-weight:700; text-decoration:none; }
.quick-panel { padding-bottom:1rem; } .quick-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.65rem; padding:0 1rem; }
.quick-action { align-items:center; background:#fff; border:1px solid #dfe5f2; border-radius:9px; color:#1d2b42; display:flex; flex-direction:column; gap:.45rem; justify-content:center; min-height:75px; padding:.65rem .3rem; text-align:center; text-decoration:none; font-size:.64rem; }
.quick-action:hover { border-color:#bd1d2b; color:#bd1d2b; } .quick-action svg { background:#e8edfb; border-radius:7px; color:#263f82; height:28px; padding:6px; width:28px; }
.next-panel { margin-top:1.1rem; padding-bottom:.5rem; } .next-item { border-left:2px solid #d8e0f2; margin:0 1rem; padding:0 0 .85rem 1rem; position:relative; }
.next-item::before { background:#fff; border:3px solid #198c83; border-radius:50%; content:''; height:8px; left:-6px; position:absolute; top:2px; width:8px; }
.next-time { color:#147d76; font-size:.65rem; font-weight:700; } .next-name { color:#24344b; font-size:.75rem; font-weight:700; margin-top:.35rem; } .next-type { color:#7a8495; font-size:.65rem; margin-top:.2rem; }
.empty-dashboard { color:#7a8495; font-size:.78rem; padding:1.5rem; text-align:center; }
@media (max-width:1050px) { .kpi-grid { grid-template-columns:repeat(2,1fr); } .dashboard-columns { grid-template-columns:1fr; } }
@media (max-width:650px) { .kpi-grid { gap:.6rem; } .kpi-card { min-height:100px; padding:.8rem; } .appointment-table th,.appointment-table td { padding:.65rem .55rem; } .appointment-table th:nth-child(3),.appointment-table td:nth-child(3) { display:none; } }
</style>

<main class="page doctor-dashboard">
  <section class="dashboard-heading"><h1>Good <?= date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') ?>, Dr. <?= htmlspecialchars($first_name) ?></h1><p>Here is a summary of your schedule today.</p></section>

  <section class="kpi-grid" aria-label="Dashboard statistics">
    <?php $kpis = [
      ['label'=>"Today's Appointments",'value'=>$today_appts,'icon'=>'<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/>'],
      ['label'=>'Pending Appointments','value'=>$pending_count,'icon'=>'<rect x="5" y="4" width="14" height="16" rx="2"/><path d="M9 4v3h6V4m-4 6h2m-2 4h2"/>'],
      ['label'=>'Total Patients','value'=>$patient_count,'icon'=>'<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0112 0m2-8a3 3 0 013 3m-3 5h5"/>'],
      ['label'=>'Completed Consultations','value'=>$completed_count,'icon'=>'<circle cx="12" cy="12" r="8"/><path d="m8.5 12 2.3 2.3 4.8-5"/>']
    ]; foreach ($kpis as $kpi): ?>
      <article class="kpi-card"><div class="kpi-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $kpi['icon'] ?></svg></div><div class="kpi-value"><?= $kpi['value'] ?></div><div class="kpi-label"><?= htmlspecialchars($kpi['label']) ?></div></article>
    <?php endforeach; ?>
  </section>

  <div class="dashboard-columns">
    <section class="dashboard-panel"><div class="panel-heading"><h2>Today's Appointments</h2><a class="panel-link" href="appointments.php">View All</a></div>
      <?php if ($today && $today->num_rows > 0): ?><div style="overflow-x:auto"><table class="appointment-table"><thead><tr><th>Patient Name</th><th>Time</th><th>Type</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php while ($appointment = $today->fetch_assoc()): $status_class = strtolower((string)$appointment['status']); $patient_initials = strtoupper(substr($appointment['patient_name'],0,1) . (strpos($appointment['patient_name'],' ') !== false ? substr(strrchr($appointment['patient_name'],' '),1,1) : '')); ?>
          <tr><td><div class="patient-cell"><span class="table-avatar"><?= htmlspecialchars($patient_initials) ?></span><span><?= htmlspecialchars($appointment['patient_name']) ?></span></div></td><td class="appointment-time"><?= date('h:i A',strtotime($appointment['appointment_time'])) ?></td><td><?= htmlspecialchars($appointment['type'] ?? 'Consultation') ?></td><td><span class="status-pill status-<?= htmlspecialchars($status_class) ?>"><?= htmlspecialchars($appointment['status']) ?></span></td><td><a class="table-action" href="appointments.php?patient_id=<?= (int)$appointment['patient_id'] ?>">View</a></td></tr>
        <?php endwhile; ?></tbody></table></div><?php else: ?><div class="empty-dashboard">No appointments scheduled for today.</div><?php endif; ?>
    </section>

    <aside><section class="dashboard-panel quick-panel"><div class="panel-heading"><h2>Quick Actions</h2></div><div class="quick-grid">
      <a class="quick-action" href="appointments.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/></svg>View Schedule</a>
      <a class="quick-action" href="availability.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 2v4m8-4v4M7 10h10M8 14h3m2 0h3"/></svg>Set Schedule</a>
      <a class="quick-action" href="appointments.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>View Appointments</a>
      <a class="quick-action" href="patients.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0112 0m1-8h5m-2-3v6"/></svg>Patient List</a>
    </div></section>
    <section class="dashboard-panel next-panel"><div class="panel-heading"><h2>Next Up</h2><a class="panel-link" href="appointments.php">See all</a></div>
      <?php if ($next_up && $next_up->num_rows > 0): while ($next = $next_up->fetch_assoc()): ?><div class="next-item"><div class="next-time"><?= date('h:i A',strtotime($next['appointment_time'])) ?></div><div class="next-name"><?= htmlspecialchars($next['patient_name']) ?></div><div class="next-type"><?= htmlspecialchars($next['type'] ?? 'Consultation') ?></div></div><?php endwhile; else: ?><div class="empty-dashboard">No teleconsultation scheduled today.</div><?php endif; ?>
    </section></aside>
  </div>
</main>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>
