<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appt_id'])) {
    $aid    = (int)$_POST['appt_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['action_notes'] ?? '');
    $map    = ['approve' => 'Confirmed', 'reject' => 'Cancelled'];

    if (isset($map[$action])) {
        $chk = $conn->prepare("SELECT status FROM appointments WHERE id=?");
        $chk->bind_param("i", $aid);
        $chk->execute();
        $cur = $chk->get_result()->fetch_assoc();

        if (!$cur || $cur['status'] !== 'DoctorApproved') {
            $_SESSION['toast_error'] = "Cannot act on this appointment — waiting for doctor's acceptance first.";
        } else {
            $new_status = $map[$action];
            $conn->query("UPDATE appointments SET status='$new_status' WHERE id=$aid");
            logAction($conn, $aid, $staff_id, ucfirst($action) . 'd', $notes);
            $_SESSION['toast'] = "Appointment " . $new_status . " successfully.";
        }
    }
    header('Location: dashboard.php');
    exit;
}

$toast       = $_SESSION['toast']       ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$active_page = 'dashboard';
$today       = date('Y-m-d');

$today_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name,
           d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.appointment_date = '$today'
    ORDER BY a.appointment_time ASC
");

$pending_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name,
           d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.status = 'DoctorApproved'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$stat_today           = $today_appts   ? $today_appts->num_rows   : 0;
$stat_doctor_approved = $pending_appts ? $pending_appts->num_rows : 0;
$stat_patients        = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$stat_doctors         = $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='active'")->fetch_assoc()['c'];
$stat_pending         = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];

$total_collected_row = $conn->query("
    SELECT COALESCE(SUM(d.consultation_fee), 0) AS total
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.payment_status = 'Paid'
")->fetch_assoc();
$stat_total_collected = (float)$total_collected_row['total'];

$range = $_GET['range'] ?? 'week';
if (!in_array($range, ['week', 'month', 'year'], true)) {
  $range = 'week';
}

$today_dt = new DateTime($today);
$range_label = 'Last 7 Days';
$bucket_expr = "DATE(a.appointment_date)";
$bucket_key_format = 'Y-m-d';

if ($range === 'week') {
  $range_start = (clone $today_dt)->modify('-6 days')->format('Y-m-d');
  $range_end   = (clone $today_dt)->format('Y-m-d');
  $range_label = 'Last 7 Days';
  $bucket_expr = "DATE(a.appointment_date)";
  $bucket_key_format = 'Y-m-d';
} elseif ($range === 'month') {
  $range_start = (clone $today_dt)->modify('first day of this month')->format('Y-m-d');
  $range_end   = (clone $today_dt)->modify('last day of this month')->format('Y-m-d');
  $range_label = date('F Y', strtotime($today));
  $bucket_expr = "DATE(a.appointment_date)";
  $bucket_key_format = 'Y-m-d';
} else {
  $range_start = (clone $today_dt)->modify('first day of January ' . date('Y'))->format('Y-m-d');
  $range_end   = (clone $today_dt)->modify('last day of December ' . date('Y'))->format('Y-m-d');
  $range_label = date('Y');
  $bucket_expr = "DATE_FORMAT(a.appointment_date, '%Y-%m')";
  $bucket_key_format = 'Y-m';
}

$daily_raw = $conn->query("
  SELECT {$bucket_expr} AS bucket,
       COALESCE(SUM(d.consultation_fee), 0) AS total
  FROM appointments a
  JOIN doctors d ON d.id = a.doctor_id
  WHERE a.payment_status = 'Paid'
    AND a.appointment_date >= '$range_start'
    AND a.appointment_date <= '$range_end'
  GROUP BY bucket
  ORDER BY bucket ASC
");

$daily_map = [];
if ($range === 'year') {
  $year = date('Y', strtotime($today));
  for ($m = 1; $m <= 12; $m++) {
    $key = $year . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
    $daily_map[$key] = 0;
  }
} else {
  $cursor = new DateTime($range_start);
  $end_dt = new DateTime($range_end);
  while ($cursor <= $end_dt) {
    $key = $cursor->format($bucket_key_format);
    $daily_map[$key] = 0;
    $cursor->modify('+1 day');
  }
}

if ($daily_raw) {
  while ($row = $daily_raw->fetch_assoc()) {
    $key = $row['bucket'];
    if (array_key_exists($key, $daily_map)) {
      $daily_map[$key] = (float)$row['total'];
    }
  }
}

$chart_labels = [];
$chart_values = [];
foreach ($daily_map as $bucket => $val) {
  if ($range === 'year') {
    $chart_labels[] = date('M', strtotime($bucket . '-01'));
  } else {
    $chart_labels[] = date('M j', strtotime($bucket));
  }
  $chart_values[] = $val;
}

$status_counts_raw = $conn->query("
  SELECT status, COUNT(*) AS cnt
  FROM appointments
  WHERE appointment_date >= '$range_start'
    AND appointment_date <= '$range_end'
  GROUP BY status
");
$status_map = [];
if ($status_counts_raw) {
    while ($row = $status_counts_raw->fetch_assoc()) {
        $status_map[$row['status']] = (int)$row['cnt'];
    }
}
$donut_labels = array_keys($status_map);
$donut_values = array_values($status_map);

require_once 'includes/header.php';
?>

<div style="display:flex;justify-content:flex-end;align-items:center;gap:0.6rem;margin-bottom:1rem;">
  <form method="GET" style="display:flex;align-items:center;gap:0.5rem;">
    <label for="graph-range" style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;">Graph Range</label>
    <select id="graph-range" name="range" onchange="this.form.submit()" style="padding:0.42rem 0.7rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:10px;font-size:0.82rem;font-weight:600;color:var(--green);background:#fff;outline:none;cursor:pointer;">
      <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>Week</option>
      <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>Month</option>
      <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>Year</option>
    </select>
  </form>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-num" style="color:var(--blue)"><?= $stat_today ?></div>
    <div class="stat-lbl">Today's Appointments</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#d97706"><?= $stat_doctor_approved ?></div>
    <div class="stat-lbl">Awaiting Your Confirmation</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--green)"><?= $stat_patients ?></div>
    <div class="stat-lbl">Total Patients</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--red)"><?= $stat_doctors ?></div>
    <div class="stat-lbl">Active Doctors</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#0d9488">₱<?= number_format($stat_total_collected, 2) ?></div>
    <div class="stat-lbl">Total Fees Collected</div>
  </div>
</div>

<?php if ($toast): ?>
<div class="toast-bar success">✓ <?= htmlspecialchars($toast) ?></div>
<?php endif; ?>
<?php if ($toast_error): ?>
<div class="toast-bar error">✕ <?= htmlspecialchars($toast_error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem;">

  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">💰 Daily Collections</h2>
      <span class="badge bg-blue"><?= htmlspecialchars($range_label) ?></span>
    </div>
    <div style="position:relative;width:100%;height:220px;">
      <canvas id="dailyChart"
        role="img"
        aria-label="Bar chart showing daily consultation fee collections over the last 7 days."
      >Daily collections: <?= implode(', ', array_map(fn($l,$v) => "$l ₱$v", $chart_labels, $chart_values)) ?>.</canvas>
    </div>
  </div>

  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">📊 Appointments by Status</h2>
      <span class="badge bg-blue"><?= htmlspecialchars($range_label) ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:1.2rem;">
      <div style="position:relative;width:160px;height:160px;flex-shrink:0;">
        <canvas id="statusChart"
          role="img"
          aria-label="Donut chart showing appointment counts grouped by status."
        >Appointment statuses: <?= implode(', ', array_map(fn($l,$v) => "$l: $v", $donut_labels, $donut_values)) ?>.</canvas>
      </div>
      <div id="status-legend" style="font-size:.75rem;display:flex;flex-direction:column;gap:6px;"></div>
    </div>
  </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;">

  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">📋 Today's Queue</h2>
      <span class="badge bg-blue"><?= date('M j') ?></span>
    </div>
    <?php
    $q = 0;
    if ($today_appts && $today_appts->num_rows > 0):
      $today_appts->data_seek(0);
      while ($a = $today_appts->fetch_assoc()):
        $q++;
    ?>
    <div class="queue-item">
      <div class="queue-num"><?= $q ?></div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:.88rem"><?= htmlspecialchars($a['patient_name']) ?></div>
        <div style="font-size:.75rem;color:var(--muted)">
          <?= date('g:i A', strtotime($a['appointment_time'])) ?> · Dr. <?= htmlspecialchars($a['doctor_name']) ?>
        </div>
      </div>
      <span class="badge <?= $a['status'] === 'Confirmed' ? 'bg-green' : ($a['status'] === 'Pending' ? 'bg-orange' : 'bg-gray') ?>">
        <?= $a['status'] ?>
      </span>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-row">No appointments today.</div>
    <?php endif ?>
  </div>

  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">⚡ Awaiting Your Confirmation</h2>
      <?php if ($stat_doctor_approved > 0): ?>
        <span class="badge bg-blue"><?= $stat_doctor_approved ?></span>
      <?php endif ?>
    </div>
    <div style="font-size:0.74rem;color:#3b82f6;font-weight:600;margin-bottom:0.75rem;">
      Doctor has accepted these — confirm to notify the patient to pay.
    </div>
    <?php
    if ($pending_appts && $pending_appts->num_rows > 0):
      $pending_appts->data_seek(0);
      while ($a = $pending_appts->fetch_assoc()):
    ?>
    <div class="queue-item">
      <div style="flex:1">
        <div style="font-weight:700;font-size:.87rem"><?= htmlspecialchars($a['patient_name']) ?></div>
        <div style="font-size:.74rem;color:var(--muted)">
          <?= date('M j', strtotime($a['appointment_date'])) ?>
          <?= date('g:i A', strtotime($a['appointment_time'])) ?>
          · Dr. <?= htmlspecialchars($a['doctor_name']) ?>
        </div>
      </div>
      <div style="display:flex;gap:.4rem">
        <button class="btn-green btn-sm" style="font-weight:800;" onclick="quickAction(<?= $a['id'] ?>, 'approve')">✓ Confirm</button>
        <button class="btn-red   btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'reject')">Reject</button>
      </div>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-row">All caught up! No appointments awaiting confirmation.</div>
    <?php endif ?>
  </div>

</div>

<form method="POST" id="quick-form" style="display:none">
  <input type="hidden" name="action"       id="qf-action"/>
  <input type="hidden" name="appt_id"      id="qf-appt-id"/>
  <input type="hidden" name="action_notes" id="qf-notes"/>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
function quickAction(id, action) {
  document.getElementById('qf-appt-id').value = id;
  document.getElementById('qf-action').value  = action;
  document.getElementById('qf-notes').value   = '';
  document.getElementById('quick-form').submit();
}

const dailyLabels = <?= json_encode($chart_labels) ?>;
const dailyValues = <?= json_encode($chart_values) ?>;

new Chart(document.getElementById('dailyChart'), {
  type: 'bar',
  data: {
    labels: dailyLabels,
    datasets: [{
      label: 'Collected (₱)',
      data: dailyValues,
      backgroundColor: 'rgba(13,148,136,0.18)',
      borderColor: '#0d9488',
      borderWidth: 2,
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => ' ₱' + Number(ctx.parsed.y).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})
        }
      }
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { font: { size: 11 }, color: '#9ca3af', autoSkip: false }
      },
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,0.05)' },
        ticks: {
          font: { size: 11 },
          color: '#9ca3af',
          callback: v => '₱' + Number(v).toLocaleString('en-PH')
        }
      }
    }
  }
});

const statusLabels = <?= json_encode($donut_labels) ?>;
const statusValues = <?= json_encode($donut_values) ?>;

const statusPalette = [
  '#0d9488','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#10b981','#6b7280','#ec4899'
];

const total = statusValues.reduce((a, b) => a + b, 0);
const legend = document.getElementById('status-legend');
statusLabels.forEach((lbl, i) => {
  const pct = total > 0 ? Math.round(statusValues[i] / total * 100) : 0;
  const el = document.createElement('span');
  el.style.cssText = 'display:flex;align-items:center;gap:6px;color:#374151;';
  el.innerHTML =
    '<span style="width:10px;height:10px;border-radius:2px;background:' + statusPalette[i % statusPalette.length] + ';flex-shrink:0;"></span>' +
    '<span style="font-size:.73rem;">' + lbl + ' <strong>' + statusValues[i] + '</strong> <span style="color:#9ca3af;">(' + pct + '%)</span></span>';
  legend.appendChild(el);
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: statusLabels,
    datasets: [{
      data: statusValues,
      backgroundColor: statusPalette.slice(0, statusLabels.length),
      borderWidth: 2,
      borderColor: '#ffffff',
      hoverOffset: 6,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' (' + (total > 0 ? Math.round(ctx.parsed / total * 100) : 0) + '%)'
        }
      }
    }
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>