<?php
require_once 'includes/auth.php';

$today     = date('Y-m-d');
$todayLabel = date('l, F j, Y');

// ── Stats ──
$today_total    = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date='$today'")->fetch_assoc()['c'];
$today_pending  = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date='$today' AND status='Pending'")->fetch_assoc()['c'];
$today_confirmed= $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date='$today' AND status='Confirmed'")->fetch_assoc()['c'];
$today_done     = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date='$today' AND status='Completed'")->fetch_assoc()['c'];

// ── Today's appointments (full list) ──
$appts_today = $conn->query("
    SELECT a.*, p.full_name AS patient_name, d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.appointment_date = '$today'
    ORDER BY a.appointment_time ASC
");

// ── Patient queue — Confirmed today, ordered by time ──
$queue = $conn->query("
    SELECT a.*, p.full_name AS patient_name, d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.appointment_date = '$today' AND a.status = 'Confirmed'
    ORDER BY a.appointment_time ASC
    LIMIT 10
");

// ── Latest unread notifications ──
$notifs = $conn->query("
    SELECT * FROM staff_notifications
    WHERE staff_id = $staff_id
    ORDER BY is_read ASC, created_at DESC
    LIMIT 8
");

$page_title = 'Dashboard — TELE-CARE Staff';
$active_nav = 'dashboard';
require_once 'includes/head.php';
?>
<body>
<?php require_once 'includes/sidebar.php'; ?>

<div class="main">
  <!-- Topbar -->
  <div class="topbar">
    <div>
      <div style="font-size:0.73rem;color:var(--muted);font-weight:600;"><?= $todayLabel ?></div>
      <div style="font-size:0.95rem;font-weight:700;">Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars(explode(' ', $staff['full_name'])[0]) ?> 👋</div>
    </div>
    <a href="appointments.php" class="btn-primary">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Manage Appointments
    </a>
  </div>

  <div class="page-content">

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="s-label">Today's Total</div>
        <div class="s-value"><?= $today_total ?></div>
        <div class="s-sub">Appointments today</div>
      </div>
      <div class="stat-card">
        <div class="s-label">Pending</div>
        <div class="s-value" style="color:var(--red);"><?= $today_pending ?></div>
        <div class="s-sub">Awaiting approval</div>
      </div>
      <div class="stat-card">
        <div class="s-label">Confirmed</div>
        <div class="s-value" style="color:#16a34a;"><?= $today_confirmed ?></div>
        <div class="s-sub">Ready to go</div>
      </div>
      <div class="stat-card">
        <div class="s-label">Completed</div>
        <div class="s-value" style="color:var(--blue);"><?= $today_done ?></div>
        <div class="s-sub">Done today</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:1.5rem;">

      <!-- Left: Daily appointments table -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.9rem;">
          <div style="font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:700;">Today's Appointments</div>
          <a href="appointments.php" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">View all</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php
            if ($appts_today && $appts_today->num_rows > 0):
              while ($a = $appts_today->fetch_assoc()):
            ?>
            <tr>
              <td style="font-weight:700;color:var(--blue);white-space:nowrap;"><?= date('g:i A', strtotime($a['appointment_time'])) ?></td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($a['patient_name']) ?></div>
                <div style="font-size:0.73rem;color:var(--muted);"><?= htmlspecialchars($a['type']) ?></div>
              </td>
              <td style="font-size:0.83rem;color:var(--muted);">Dr. <?= htmlspecialchars($a['doctor_name']) ?></td>
              <td>
                <span class="badge <?= match($a['status']) {
                  'Confirmed'  => 'badge-green',
                  'Pending'    => 'badge-orange',
                  'Completed'  => 'badge-blue',
                  default      => 'badge-red'
                } ?>">
                  <?= $a['status'] ?>
                </span>
              </td>
              <td>
                <?php if ($a['status'] === 'Pending'): ?>
                  <a href="appointments.php?quick_approve=<?= $a['id'] ?>" class="btn-sm btn-green">Approve</a>
                <?php elseif ($a['status'] === 'Confirmed'): ?>
                  <a href="appointments.php?id=<?= $a['id'] ?>" class="btn-sm btn-blue">View</a>
                <?php else: ?>
                  <span style="font-size:0.75rem;color:var(--muted);">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5" class="empty-row">No appointments today.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Right column: Queue + Notifications -->
      <div style="display:flex;flex-direction:column;gap:1.2rem;">

        <!-- Patient Queue -->
        <div class="card" style="margin-bottom:0;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;">
            <div class="section-label" style="margin-bottom:0;">Patient Queue</div>
            <span class="badge badge-blue"><?= $queue ? $queue->num_rows : 0 ?> confirmed</span>
          </div>
          <?php
          $q_num = 1;
          $q_has = false;
          if ($queue && $queue->num_rows > 0):
            while ($q = $queue->fetch_assoc()):
              $q_has = true;
          ?>
          <div class="queue-item">
            <div class="queue-num"><?= $q_num++ ?></div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:600;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($q['patient_name']) ?></div>
              <div style="font-size:0.73rem;color:var(--muted);">Dr. <?= htmlspecialchars($q['doctor_name']) ?></div>
            </div>
            <div style="font-size:0.78rem;font-weight:700;color:var(--blue);white-space:nowrap;"><?= date('g:i A', strtotime($q['appointment_time'])) ?></div>
          </div>
          <?php endwhile; endif; ?>
          <?php if (!$q_has): ?>
          <div style="text-align:center;padding:1.2rem 0;font-size:0.83rem;color:var(--muted);">No patients in queue.</div>
          <?php endif; ?>
        </div>

        <!-- Notifications -->
        <div class="card" style="margin-bottom:0;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;">
            <div class="section-label" style="margin-bottom:0;">Notifications</div>
            <?php if ($unread_notifs > 0): ?>
            <span class="badge badge-red"><?= $unread_notifs ?> new</span>
            <?php endif; ?>
          </div>
          <?php
          $n_has = false;
          if ($notifs && $notifs->num_rows > 0):
            while ($n = $notifs->fetch_assoc()):
              $n_has = true;
          ?>
          <div class="notif-item">
            <div class="notif-dot <?= $n['is_read'] ? 'read' : '' ?>"></div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:<?= $n['is_read'] ? '500' : '700' ?>;font-size:0.85rem;"><?= htmlspecialchars($n['title']) ?></div>
              <div style="font-size:0.75rem;color:var(--muted);margin-top:0.1rem;line-height:1.4;"><?= htmlspecialchars($n['message']) ?></div>
              <div style="font-size:0.7rem;color:var(--muted);margin-top:0.2rem;"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></div>
            </div>
          </div>
          <?php endwhile; endif; ?>
          <?php if (!$n_has): ?>
          <div style="text-align:center;padding:1.2rem 0;font-size:0.83rem;color:var(--muted);">No notifications yet.</div>
          <?php endif; ?>
          <a href="notifications.php" style="display:block;text-align:center;font-size:0.78rem;color:var(--blue);font-weight:600;margin-top:0.6rem;text-decoration:none;">See all notifications →</a>
        </div>

      </div>
    </div><!-- /grid -->

  </div>
</div>

<script>
  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>