<?php
require_once 'includes/auth.php';

$patient_result = $conn->query("SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id=$doctor_id");
$patient_count = $patient_result ? (int)$patient_result->fetch_assoc()['c'] : 0;

$today_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND appointment_date=CURDATE() AND status NOT IN ('Cancelled')");
$today_appts = $today_result ? (int)$today_result->fetch_assoc()['c'] : 0;

$pending_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status IN ('Pending','DoctorApproved')");
$pending_count = $pending_result ? (int)$pending_result->fetch_assoc()['c'] : 0;

$completed_result = $conn->query("SELECT COUNT(*) c FROM appointments WHERE doctor_id=$doctor_id AND status='Completed'");
$completed_count = $completed_result ? (int)$completed_result->fetch_assoc()['c'] : 0;

$today = $conn->query("
    SELECT a.*, p.full_name patient_name, p.profile_photo patient_photo
    FROM appointments a
    JOIN patients p ON p.id=a.patient_id
    WHERE a.doctor_id=$doctor_id AND a.appointment_date=CURDATE()
    ORDER BY a.appointment_time ASC LIMIT 8
");

$next_up = $conn->query("
    SELECT a.*, p.full_name patient_name
    FROM appointments a
    JOIN patients p ON p.id=a.patient_id
    WHERE a.doctor_id=$doctor_id
    AND a.appointment_date=CURDATE()
    AND a.status IN ('Pending','DoctorApproved','Confirmed')
    AND a.appointment_time>=CURTIME()
    ORDER BY a.appointment_time ASC LIMIT 3
");

$page_title = 'Dashboard — TELE-CARE';
$page_title_short = 'Dashboard';
$active_nav = 'home';

require_once 'includes/header.php';

$first_name = explode(' ', trim($doc['full_name'] ?? 'Doctor'))[0];
$hour = (int)date('H');
$greeting = $hour < 12 ? 'Morning' : ($hour < 18 ? 'Afternoon' : 'Evening');
?>

<style>
.doctor-dashboard {
    width:calc(100% - 240px);
    margin-left:240px;
    padding:24px 24px 32px;
    box-sizing:border-box;
    overflow-x:hidden;
}

.doctor-dashboard * { box-sizing:border-box; }

.dashboard-heading { margin-bottom:20px; }
.dashboard-heading h1 {
    margin:0 0 4px;
    color:var(--neutral-900);
    font-size:clamp(1.5rem,2.5vw,2rem);
}
.dashboard-heading p {
    margin:0;
    color:var(--neutral-500);
    font-size:.82rem;
}

.kpi-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:18px;
}

.kpi-card {
    min-width:0;
    min-height:112px;
    padding:16px;
    background:var(--surface);
    border:1px solid var(--border-color);
    border-radius:var(--radius-md);
    box-shadow:var(--shadow-sm);
}

.kpi-icon {
    width:36px;
    height:36px;
    display:grid;
    place-items:center;
    margin-bottom:10px;
    border-radius:8px;
    background:var(--secondary-soft);
    color:var(--secondary-dark);
}

.kpi-icon svg { width:18px;height:18px; }

.kpi-value {
    color:var(--neutral-900);
    font-size:1.2rem;
    font-weight:700;
}

.kpi-label {
    margin-top:4px;
    color:var(--neutral-700);
    font-size:.7rem;
}

.dashboard-columns {
    display:grid;
    grid-template-columns:minmax(0,1fr) 310px;
    gap:18px;
    align-items:start;
}

.dashboard-panel {
    min-width:0;
    background:var(--surface);
    border:1px solid var(--border-color);
    border-radius:var(--radius-md);
    box-shadow:var(--shadow-sm);
    overflow:hidden;
}

.panel-heading {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:16px;
}

.panel-heading h2 {
    margin:0;
    color:var(--neutral-900);
    font-size:.95rem;
}

.panel-link {
    color:var(--primary);
    font-size:.72rem;
    font-weight:700;
    text-decoration:none;
}

.appointment-table-wrapper {
    width:100%;
    overflow-x:auto;
}

.appointment-table {
    width:100%;
    min-width:580px;
    border-collapse:collapse;
}

.appointment-table th {
    padding:11px 16px;
    background:#f1f4ff;
    color:#34425a;
    font-size:.61rem;
    text-align:left;
    text-transform:uppercase;
}

.appointment-table td {
    padding:11px 16px;
    border-top:1px solid #e8ecf5;
    color:#27364d;
    font-size:.72rem;
}

.patient-cell {
    display:flex;
    align-items:center;
    gap:8px;
    min-width:0;
    font-weight:600;
}

.table-avatar {
    width:28px;
    height:28px;
    flex:none;
    display:grid;
    place-items:center;
    border-radius:50%;
    background:#e7ecfa;
    color:#bd1d2b;
    font-size:.56rem;
    font-weight:700;
}

.status-pill {
    display:inline-block;
    padding:5px 9px;
    border-radius:20px;
    font-size:.61rem;
    white-space:nowrap;
}

.status-completed { background:#e7f8ef;color:#078447; }
.status-cancelled { background:#ffe5e6;color:#c42d3a; }
.status-pending,
.status-doctorapproved { background:#fff1dc;color:#a66300; }
.status-confirmed { background:#e9efff;color:#3158a5; }

.table-action {
    color:#bd1d2b;
    font-size:.68rem;
    font-weight:700;
    text-decoration:none;
}

.quick-panel { padding-bottom:16px; }

.quick-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
    padding:0 16px;
}

.quick-action {
    min-height:78px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:7px;
    border:1px solid #dfe5f2;
    border-radius:9px;
    color:#1d2b42;
    font-size:.64rem;
    text-decoration:none;
}

.quick-action:hover {
    border-color:#bd1d2b;
    color:#bd1d2b;
}

.quick-action svg {
    width:28px;
    height:28px;
    padding:6px;
    border-radius:7px;
    background:#e8edfb;
    color:#263f82;
}

.next-panel {
    margin-top:16px;
    padding-bottom:8px;
}

.next-item {
    position:relative;
    margin:0 16px;
    padding:0 0 14px 16px;
    border-left:2px solid #d8e0f2;
}

.next-item::before {
    content:"";
    position:absolute;
    top:2px;
    left:-6px;
    width:8px;
    height:8px;
    border:3px solid #198c83;
    border-radius:50%;
    background:#fff;
}

.next-time {
    color:#147d76;
    font-size:.65rem;
    font-weight:700;
}

.next-name {
    margin-top:5px;
    color:#24344b;
    font-size:.75rem;
    font-weight:700;
}

.next-type {
    margin-top:3px;
    color:#7a8495;
    font-size:.65rem;
}

.empty-dashboard {
    padding:24px;
    color:#7a8495;
    font-size:.78rem;
    text-align:center;
}

@media (max-width:1150px) {
    .kpi-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .dashboard-columns { grid-template-columns:minmax(0,1fr) 280px; }
}

@media (max-width:900px) {
    .dashboard-columns { grid-template-columns:1fr; }
    .dashboard-columns aside {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:16px;
    }
    .next-panel { margin-top:0; }
}

@media (max-width:767px) {
    .doctor-dashboard {
        width:100%;
        margin-left:0;
        padding:16px 12px 80px;
    }

    .kpi-grid {
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .kpi-card { min-height:100px;padding:12px; }

    .dashboard-columns { grid-template-columns:1fr; }

    .dashboard-columns aside { display:block; }

    .next-panel { margin-top:10px; }

    .panel-heading { padding:14px; }

    .appointment-table { min-width:560px; }
}

@media (max-width:480px) {
    .doctor-dashboard { padding-left:10px;padding-right:10px; }

    .kpi-grid { gap:8px; }

    .kpi-card { padding:10px; }

    .kpi-label { font-size:.64rem; }

    .quick-grid { padding:0 10px; }
}
</style>

<main class="page doctor-dashboard">

    <section class="dashboard-heading">
        <h1>
            Good <?= htmlspecialchars($greeting) ?>,
            Dr. <?= htmlspecialchars($first_name) ?>
        </h1>
        <p>Here is a summary of your schedule today.</p>
    </section>

    <section class="kpi-grid">

        <article class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <rect x="4" y="5" width="16" height="15" rx="2"/>
                    <path d="M8 3v4m8-4v4M4 10h16"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $today_appts ?></div>
            <div class="kpi-label">Today's Appointments</div>
        </article>

        <article class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <rect x="5" y="4" width="14" height="16" rx="2"/>
                    <path d="M9 4v3h6V4m-4 6h2m-2 4h2"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $pending_count ?></div>
            <div class="kpi-label">Pending Appointments</div>
        </article>

        <article class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <circle cx="9" cy="8" r="3"/>
                    <path d="M3 20a6 6 0 0112 0m2-8a3 3 0 013 3m-3 5h5"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $patient_count ?></div>
            <div class="kpi-label">Total Patients</div>
        </article>

        <article class="kpi-card">
            <div class="kpi-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <circle cx="12" cy="12" r="8"/>
                    <path d="m8.5 12 2.3 2.3 4.8-5"/>
                </svg>
            </div>
            <div class="kpi-value"><?= $completed_count ?></div>
            <div class="kpi-label">Completed Consultations</div>
        </article>

    </section>

    <div class="dashboard-columns">

        <section class="dashboard-panel">

            <div class="panel-heading">
                <h2>Today's Appointments</h2>
                <a class="panel-link" href="appointments.php">View All</a>
            </div>

            <?php if ($today && $today->num_rows): ?>

                <div class="appointment-table-wrapper">

                    <table class="appointment-table">

                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php while ($appointment = $today->fetch_assoc()): ?>

                            <?php
                            $name = $appointment['patient_name'];
                            $parts = preg_split('/\s+/', trim($name));
                            $initials = strtoupper(
                                substr($parts[0],0,1) .
                                (count($parts) > 1 ? substr(end($parts),0,1) : '')
                            );
                            $status = (string)$appointment['status'];
                            $status_class = strtolower(
                                preg_replace('/[^a-z0-9]+/i','',$status)
                            );
                            ?>

                            <tr>

                                <td>
                                    <div class="patient-cell">
                                        <span class="table-avatar">
                                            <?= htmlspecialchars($initials) ?>
                                        </span>
                                        <span><?= htmlspecialchars($name) ?></span>
                                    </div>
                                </td>

                                <td>
                                    <?= !empty($appointment['appointment_time'])
                                        ? date('h:i A',strtotime($appointment['appointment_time']))
                                        : '—' ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($appointment['type'] ?? 'Consultation') ?>
                                </td>

                                <td>
                                    <span class="status-pill status-<?= htmlspecialchars($status_class) ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        class="table-action"
                                        href="appointments.php?patient_id=<?= (int)$appointment['patient_id'] ?>"
                                    >
                                        View
                                    </a>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-dashboard">
                    No appointments scheduled for today.
                </div>

            <?php endif; ?>

        </section>

        <aside>

            <section class="dashboard-panel quick-panel">

                <div class="panel-heading">
                    <h2>Quick Actions</h2>
                </div>

                <div class="quick-grid">

                    <a class="quick-action" href="appointments.php">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="5" width="16" height="15" rx="2"/>
                            <path d="M8 3v4m8-4v4M4 10h16"/>
                        </svg>
                        View Schedule
                    </a>

                    <a class="quick-action" href="availability.php">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="4" width="16" height="16" rx="2"/>
                            <path d="M8 2v4m8-4v4M7 10h10M8 14h3m2 0h3"/>
                        </svg>
                        Set Schedule
                    </a>

                    <a class="quick-action" href="appointments.php">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="5" y="4" width="14" height="16" rx="2"/>
                            <path d="M8 8h8M8 12h8M8 16h5"/>
                        </svg>
                        View Appointments
                    </a>

                    <a class="quick-action" href="patients.php">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="9" cy="8" r="3"/>
                            <path d="M3 20a6 6 0 0112 0m1-8h5m-2-3v6"/>
                        </svg>
                        Patient List
                    </a>

                </div>

            </section>

            <section class="dashboard-panel next-panel">

                <div class="panel-heading">
                    <h2>Next Up</h2>
                    <a class="panel-link" href="appointments.php">See all</a>
                </div>

                <?php if ($next_up && $next_up->num_rows): ?>

                    <?php while ($next = $next_up->fetch_assoc()): ?>

                        <div class="next-item">

                            <div class="next-time">
                                <?= date('h:i A',strtotime($next['appointment_time'])) ?>
                            </div>

                            <div class="next-name">
                                <?= htmlspecialchars($next['patient_name']) ?>
                            </div>

                            <div class="next-type">
                                <?= htmlspecialchars($next['type'] ?? 'Consultation') ?>
                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="empty-dashboard">
                        No teleconsultation scheduled today.
                    </div>

                <?php endif; ?>

            </section>

        </aside>

    </div>

</main>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>