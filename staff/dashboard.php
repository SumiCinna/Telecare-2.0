<?php
// staff/dashboard.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!function_exists('fmt12Time')) {
    function fmt12Time(string $t): string {
        [$h, $m] = explode(':', $t);
        $h = (int)$h;
        $ap = $h >= 12 ? 'PM' : 'AM';
        $hr = $h % 12 ?: 12;
        return $hr . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ' ' . $ap;
    }
}

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
                        $end   = $slot['end'] . ':00';
                        $ins->bind_param('isss', $reqRow['doctor_id'], $slot['day'], $start, $end);
                        $ins->execute();
                    }
                }

                $upd = $conn->prepare("UPDATE doctor_schedule_requests SET status='Approved', reviewed_at=NOW(), reviewed_by=?, doctor_seen=0 WHERE id=?");
                $upd->bind_param('ii', $staff_id, $reqId);
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

        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'reject_schedule_request') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        $note  = trim((string)($_POST['staff_note'] ?? ''));
        $upd = $conn->prepare("UPDATE doctor_schedule_requests SET status='Rejected', reviewed_at=NOW(), reviewed_by=?, staff_note=?, doctor_seen=0 WHERE id=? AND status='Pending'");
        $upd->bind_param('isi', $staff_id, $note, $reqId);
        $upd->execute();
        $_SESSION['toast'] = $upd->affected_rows ? 'Schedule change rejected.' : 'Nothing to reject.';
        header('Location: dashboard.php');
        exit;
    }
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

$pendingScheduleRequests = [];
$psrRows = $conn->query("
    SELECT r.id, r.doctor_id, r.slots_json, r.submitted_at, d.full_name AS doctor_name
    FROM doctor_schedule_requests r
    JOIN doctors d ON d.id = r.doctor_id
    WHERE r.status = 'Pending'
    ORDER BY r.submitted_at ASC
");
if ($psrRows) {
    while ($row = $psrRows->fetch_assoc()) {
        $row['slots'] = json_decode($row['slots_json'], true) ?: [];
        $pendingScheduleRequests[] = $row;
    }
}

$stat_today            = $today_appts ? $today_appts->num_rows : 0;
$stat_schedule_pending = count($pendingScheduleRequests);
$stat_patients         = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$stat_doctors          = $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='active'")->fetch_assoc()['c'];
$stat_pending          = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];

$result = $conn->query("
    SELECT COALESCE(SUM(d.consultation_fee), 0) AS total
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.payment_status = 'Paid'
");
$consult_row = $result ? $result->fetch_assoc() : ['total' => 0];
$stat_consultation_collected = (float)$consult_row['total'];

$result = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total, COUNT(*) AS cnt
    FROM pos_sales
");
$pos_totals_row = $result ? $result->fetch_assoc() : ['total' => 0, 'cnt' => 0];
$stat_pos_collected = (float)$pos_totals_row['total'];
$stat_pos_count     = (int)$pos_totals_row['cnt'];

$stat_total_collected = $stat_consultation_collected + $stat_pos_collected;

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

$pos_bucket_expr = $range === 'year' ? "DATE_FORMAT(created_at, '%Y-%m')" : "DATE(created_at)";
$pos_daily_raw = $conn->query("
  SELECT {$pos_bucket_expr} AS bucket,
       COALESCE(SUM(total_amount), 0) AS total
  FROM pos_sales
  WHERE created_at >= '$range_start 00:00:00'
    AND created_at <= '$range_end 23:59:59'
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
      $daily_map[$key] += (float)$row['total'];
    }
  }
}
if ($pos_daily_raw) {
  while ($row = $pos_daily_raw->fetch_assoc()) {
    $key = $row['bucket'];
    if (array_key_exists($key, $daily_map)) {
      $daily_map[$key] += (float)$row['total'];
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

$recent_pos_sales = [];
$rps = $conn->query("
    SELECT s.id, s.patient_name, s.total_amount, s.created_at,
           st.full_name AS staff_name,
           (SELECT COUNT(*) FROM pos_sale_items i WHERE i.sale_id = s.id) AS item_count
    FROM pos_sales s
    LEFT JOIN staff_accounts st ON st.id = s.staff_id
    ORDER BY s.created_at DESC
    LIMIT 6
");
if ($rps) { while ($row = $rps->fetch_assoc()) { $recent_pos_sales[] = $row; } }

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
    <div class="stat-num" style="color:#d97706"><?= $stat_schedule_pending ?></div>
    <div class="stat-lbl">Schedule Change Requests</div>
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
    <div class="stat-lbl">Total Revenue (Consults + POS)</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#3F82E3">₱<?= number_format($stat_pos_collected, 2) ?></div>
    <div class="stat-lbl">POS Sales (<?= $stat_pos_count ?> transactions)</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem;">

  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">Daily Collections (Consults + POS)</h2>
      <span class="badge bg-blue"><?= htmlspecialchars($range_label) ?></span>
    </div>
    <div style="position:relative;width:100%;height:220px;">
      <canvas id="dailyChart"
        role="img"
        aria-label="Bar chart showing daily combined consultation and POS collections."
      >Daily collections: <?= implode(', ', array_map(fn($l,$v) => "$l ₱$v", $chart_labels, $chart_values)) ?>.</canvas>
    </div>
  </div>

  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">Appointments by Status</h2>
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

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem;">

  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">Today's Queue</h2>
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
      <h2 style="font-size:1rem">Schedule Change Requests</h2>
      <?php if ($stat_schedule_pending > 0): ?>
        <span class="badge bg-blue"><?= $stat_schedule_pending ?></span>
      <?php endif ?>
    </div>
    <div style="font-size:0.74rem;color:#3b82f6;font-weight:600;margin-bottom:0.75rem;">
      Doctors are requesting weekly schedule changes — approve to apply them immediately.
    </div>
    <?php if (!empty($pendingScheduleRequests)): ?>
      <div style="display:flex;flex-direction:column;gap:.7rem;max-height:340px;overflow-y:auto;">
        <?php foreach ($pendingScheduleRequests as $r): ?>
        <div style="padding:.75rem;border-radius:10px;border:1px solid #fed7aa;background:#fff7ed;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.5rem;">
            <div>
              <div style="font-weight:700;font-size:.87rem;">Dr. <?= htmlspecialchars($r['doctor_name']) ?></div>
              <div style="font-size:.72rem;color:var(--muted);">Submitted <?= date('M j, Y g:i A', strtotime($r['submitted_at'])) ?></div>
            </div>
            <a href="doctors.php?doctor_id=<?= (int)$r['doctor_id'] ?>" style="font-size:.7rem;font-weight:700;color:var(--blue);text-decoration:none;white-space:nowrap;">View →</a>
          </div>

          <div style="display:flex;flex-direction:column;gap:.3rem;margin-bottom:.65rem;">
            <?php if (empty($r['slots'])): ?>
              <div style="font-size:.76rem;color:#c2410c;">Requested to clear entire weekly schedule.</div>
            <?php else: foreach ($r['slots'] as $slot): ?>
              <div style="display:flex;justify-content:space-between;padding:.35rem .55rem;border-radius:7px;background:#fff;border:1px solid #fed7aa;font-size:.76rem;">
                <span style="font-weight:700;"><?= htmlspecialchars($slot['day']) ?></span>
                <span style="color:#c2410c;font-weight:600;"><?= fmt12Time($slot['start'] . ':00') ?> – <?= fmt12Time($slot['end'] . ':00') ?></span>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <div style="display:flex;gap:.4rem;">
            <form method="POST" onsubmit="return confirm('Approve this schedule change? It will replace the doctor\'s current live schedule immediately.');">
              <input type="hidden" name="action" value="approve_schedule_request">
              <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
              <button type="submit" class="btn-green btn-sm" style="font-weight:800;display:inline-flex;align-items:center;gap:4px;">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Approve &amp; Apply
              </button>
            </form>
            <button type="button" class="btn-red btn-sm" onclick="openModal('modal-reject-<?= (int)$r['id'] ?>')">Reject</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
    <div class="empty-row">All caught up! No schedule change requests pending.</div>
    <?php endif ?>
  </div>

</div>

<?php foreach ($pendingScheduleRequests as $r): ?>
<div class="modal-overlay" id="modal-reject-<?= (int)$r['id'] ?>">
  <div class="modal">
    <h3 style="margin-bottom:.6rem;">Reject Schedule Change</h3>
    <div style="font-size:.78rem;color:var(--muted);margin-bottom:.7rem;">Dr. <?= htmlspecialchars($r['doctor_name']) ?></div>
    <form method="POST">
      <input type="hidden" name="action" value="reject_schedule_request">
      <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
      <label style="display:block;font-size:.72rem;font-weight:700;margin-bottom:.35rem;">Reason (optional, shown to doctor)</label>
      <textarea name="staff_note" class="f-input" rows="3" style="width:100%;margin-bottom:.75rem;" placeholder="e.g. Conflicts with clinic hours"></textarea>
      <div style="display:flex;gap:.5rem;">
        <button type="submit" class="btn-primary btn-sm">Confirm Reject</button>
        <button type="button" class="btn-light btn-sm" onclick="closeModal('modal-reject-<?= (int)$r['id'] ?>')">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<div class="card">
  <div class="sec-head" style="margin-bottom:.8rem">
    <h2 style="font-size:1rem">Recent POS Sales</h2>
    <a href="pos_services.php" class="badge bg-blue" style="text-decoration:none;">Go to POS →</a>
  </div>
  <?php if (!empty($recent_pos_sales)): ?>
  <table style="width:100%;border-collapse:collapse;font-size:.83rem;">
    <thead>
      <tr style="text-align:left;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">
        <th style="padding:.4rem .3rem;">Receipt #</th>
        <th style="padding:.4rem .3rem;">Patient</th>
        <th style="padding:.4rem .3rem;">Items</th>
        <th style="padding:.4rem .3rem;">Staff</th>
        <th style="padding:.4rem .3rem;">Date</th>
        <th style="padding:.4rem .3rem;text-align:right;">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($recent_pos_sales as $s): ?>
      <tr style="border-top:1px solid rgba(36,68,65,.06);">
        <td style="padding:.5rem .3rem;font-weight:700;">#<?= (int)$s['id'] ?></td>
        <td style="padding:.5rem .3rem;"><?= htmlspecialchars($s['patient_name'] ?: 'Walk-in') ?></td>
        <td style="padding:.5rem .3rem;"><?= (int)$s['item_count'] ?> item<?= (int)$s['item_count'] === 1 ? '' : 's' ?></td>
        <td style="padding:.5rem .3rem;"><?= htmlspecialchars($s['staff_name'] ?? '—') ?></td>
        <td style="padding:.5rem .3rem;color:var(--muted);"><?= date('M j, g:i A', strtotime($s['created_at'])) ?></td>
        <td style="padding:.5rem .3rem;text-align:right;font-weight:700;color:#0d9488;">₱<?= number_format((float)$s['total_amount'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div class="empty-row">No POS sales yet.</div>
  <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
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