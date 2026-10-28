<?php
// admin/dashboard.php
if (session_status() !== PHP_SESSION_ACTIVE) {    session_start();}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$filter = $_GET['filter'] ?? 'month';
$validFilters = ['week','month','year'];
if (!in_array($filter, $validFilters)) $filter = 'month';

switch ($filter) {
    case 'week':  $dateExpr = "YEARWEEK(created_at,1)"; $curVal = date('oW'); $prevVal = date('oW', strtotime('-1 week')); $apptDateExpr = "YEARWEEK(appointment_date,1)"; break;
    case 'year':  $dateExpr = "YEAR(created_at)"; $curVal = date('Y'); $prevVal = date('Y')-1; $apptDateExpr = "YEAR(appointment_date)"; break;
    default:      $dateExpr = "DATE_FORMAT(created_at,'%Y%m')"; $curVal = date('Ym'); $prevVal = date('Ym', strtotime('-1 month')); $apptDateExpr = "DATE_FORMAT(appointment_date,'%Y%m')"; break;
}

$total_doctors  = $conn->query("SELECT COUNT(*) c FROM doctors")->fetch_assoc()['c'];
$total_patients = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];

$staff_q = $conn->query("SHOW TABLES LIKE 'staff'");
$total_staff = ($staff_q && $staff_q->num_rows > 0)
    ? $conn->query("SELECT COUNT(*) c FROM staff")->fetch_assoc()['c']
    : 0;

$cur_appts  = $conn->query("SELECT COUNT(*) c FROM appointments WHERE $apptDateExpr='$curVal'")->fetch_assoc()['c'];
$prev_appts = $conn->query("SELECT COUNT(*) c FROM appointments WHERE $apptDateExpr='$prevVal'")->fetch_assoc()['c'];

$cur_completed  = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Completed' AND $apptDateExpr='$curVal'")->fetch_assoc()['c'];
$prev_completed = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Completed' AND $apptDateExpr='$prevVal'")->fetch_assoc()['c'];

$cur_cancelled  = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Cancelled' AND $apptDateExpr='$curVal'")->fetch_assoc()['c'];
$prev_cancelled = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Cancelled' AND $apptDateExpr='$prevVal'")->fetch_assoc()['c'];

$cols_q = $conn->query("SHOW COLUMNS FROM appointments LIKE 'payment_status'");
$has_payment = ($cols_q && $cols_q->num_rows > 0);

$cur_rev  = 0; $prev_rev = 0;
if ($has_payment) {
    $rq = $conn->query("SELECT COALESCE(SUM(d.consultation_fee),0) s FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE a.payment_status='Paid' AND $apptDateExpr='$curVal'");
    if ($rq) $cur_rev = $rq->fetch_assoc()['s'];
    $rq2 = $conn->query("SELECT COALESCE(SUM(d.consultation_fee),0) s FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE a.payment_status='Paid' AND $apptDateExpr='$prevVal'");
    if ($rq2) $prev_rev = $rq2->fetch_assoc()['s'];
}

$prev_doctors  = $conn->query("SELECT COUNT(*) c FROM doctors WHERE $dateExpr='$prevVal'")->fetch_assoc()['c'];
$cur_doc_new   = $conn->query("SELECT COUNT(*) c FROM doctors WHERE $dateExpr='$curVal'")->fetch_assoc()['c'];
$prev_patients = $conn->query("SELECT COUNT(*) c FROM patients WHERE $dateExpr='$prevVal'")->fetch_assoc()['c'];
$cur_pat_new   = $conn->query("SELECT COUNT(*) c FROM patients WHERE $dateExpr='$curVal'")->fetch_assoc()['c'];

$prev_staff = 0; $cur_staff_new = 0;
if ($staff_q && $staff_q->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) c FROM staff WHERE $dateExpr='$prevVal'"); if($r) $prev_staff=$r->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(*) c FROM staff WHERE $dateExpr='$curVal'");  if($r) $cur_staff_new=$r->fetch_assoc()['c'];
}

switch ($filter) {
    case 'week':
        $periods = [];
        for ($i=6;$i>=0;$i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $periods[] = ['label'=>date('D',$d=strtotime("-$i days")),'date'=>date('Y-m-d',$d),'type'=>'day'];
        }
        $trendGroup = "DATE(appointment_date)"; $trendFormat = "Y-m-d";
        break;
    case 'year':
        $periods = [];
        for ($i=11;$i>=0;$i--) {
            $m = date('Y-m', strtotime("-$i months"));
            $periods[] = ['label'=>date('M',strtotime($m.'-01')),'date'=>$m,'type'=>'month'];
        }
        $trendGroup = "DATE_FORMAT(appointment_date,'%Y-%m')"; $trendFormat = "Y-m";
        break;
    default:
        $periods = [];
        $weeksInMonth = ceil(date('t')/7);
        for ($i=1;$i<=$weeksInMonth;$i++) $periods[] = ['label'=>"W$i",'week'=>$i,'type'=>'week'];
        $trendGroup = "WEEK(appointment_date,1)"; $trendFormat = "week";
        break;
}

$trendData = []; $trendCompleted = []; $trendPending=[]; $trendConfirmed=[]; $trendCancelled=[]; $trendRevenue=[];

if ($filter === 'week') {
    $tr = $conn->query("SELECT DATE(appointment_date) d, COUNT(*) c, SUM(status='Completed') co, SUM(status='Pending') pe, SUM(status='Confirmed') cf, SUM(status='Cancelled') ca FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY d");
    $raw = [];
    if ($tr) while($r=$tr->fetch_assoc()) $raw[$r['d']] = $r;
    foreach ($periods as $p) {
        $trendData[]      = $raw[$p['date']]['c']  ?? 0;
        $trendCompleted[] = $raw[$p['date']]['co'] ?? 0;
        $trendPending[]   = $raw[$p['date']]['pe'] ?? 0;
        $trendConfirmed[] = $raw[$p['date']]['cf'] ?? 0;
        $trendCancelled[] = $raw[$p['date']]['ca'] ?? 0;
    }
    if ($has_payment) {
        $rr = $conn->query("SELECT DATE(a.appointment_date) d, COALESCE(SUM(d2.consultation_fee),0) s FROM appointments a JOIN doctors d2 ON d2.id=a.doctor_id WHERE a.payment_status='Paid' AND a.appointment_date >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(a.appointment_date)");
        $rawRev = [];
        if ($rr) while($r=$rr->fetch_assoc()) $rawRev[$r['d']] = $r['s'];
        foreach ($periods as $p) $trendRevenue[] = $rawRev[$p['date']] ?? 0;
    }
} elseif ($filter === 'year') {
    $tr = $conn->query("SELECT DATE_FORMAT(appointment_date,'%Y-%m') d, COUNT(*) c, SUM(status='Completed') co, SUM(status='Pending') pe, SUM(status='Confirmed') cf, SUM(status='Cancelled') ca FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(),INTERVAL 11 MONTH) GROUP BY d");
    $raw = [];
    if ($tr) while($r=$tr->fetch_assoc()) $raw[$r['d']] = $r;
    foreach ($periods as $p) {
        $trendData[]      = $raw[$p['date']]['c']  ?? 0;
        $trendCompleted[] = $raw[$p['date']]['co'] ?? 0;
        $trendPending[]   = $raw[$p['date']]['pe'] ?? 0;
        $trendConfirmed[] = $raw[$p['date']]['cf'] ?? 0;
        $trendCancelled[] = $raw[$p['date']]['ca'] ?? 0;
    }
    if ($has_payment) {
        $rr = $conn->query("SELECT DATE_FORMAT(a.appointment_date,'%Y-%m') d, COALESCE(SUM(d2.consultation_fee),0) s FROM appointments a JOIN doctors d2 ON d2.id=a.doctor_id WHERE a.payment_status='Paid' AND a.appointment_date >= DATE_SUB(CURDATE(),INTERVAL 11 MONTH) GROUP BY d");
        $rawRev = [];
        if ($rr) while($r=$rr->fetch_assoc()) $rawRev[$r['d']] = $r['s'];
        foreach ($periods as $p) $trendRevenue[] = $rawRev[$p['date']] ?? 0;
    }
} else {
    $curYear = date('Y'); $curMonth = date('m');
    $tr = $conn->query("SELECT WEEK(appointment_date,1) w, COUNT(*) c, SUM(status='Completed') co, SUM(status='Pending') pe, SUM(status='Confirmed') cf, SUM(status='Cancelled') ca FROM appointments WHERE YEAR(appointment_date)=$curYear AND MONTH(appointment_date)=$curMonth GROUP BY w ORDER BY w");
    $raw = []; $wkIdx = 1;
    if ($tr) while($r=$tr->fetch_assoc()) { $raw[$wkIdx] = $r; $wkIdx++; }
    foreach ($periods as $idx=>$p) {
        $w = $idx+1;
        $trendData[]      = $raw[$w]['c']  ?? 0;
        $trendCompleted[] = $raw[$w]['co'] ?? 0;
        $trendPending[]   = $raw[$w]['pe'] ?? 0;
        $trendConfirmed[] = $raw[$w]['cf'] ?? 0;
        $trendCancelled[] = $raw[$w]['ca'] ?? 0;
    }
    if ($has_payment) {
        $rr = $conn->query("SELECT WEEK(a.appointment_date,1) w, COALESCE(SUM(d2.consultation_fee),0) s FROM appointments a JOIN doctors d2 ON d2.id=a.doctor_id WHERE a.payment_status='Paid' AND YEAR(a.appointment_date)=$curYear AND MONTH(a.appointment_date)=$curMonth GROUP BY w ORDER BY w");
        $rawRev = []; $wi=1;
        if ($rr) while($r=$rr->fetch_assoc()) { $rawRev[$wi]=$r['s']; $wi++; }
        foreach ($periods as $idx=>$p) $trendRevenue[] = $rawRev[$idx+1] ?? 0;
    }
}

$paid_count=0; $unpaid_count=0;
if ($has_payment) {
    $r=$conn->query("SELECT COUNT(*) c FROM appointments WHERE payment_status='Paid'"); if($r) $paid_count=$r->fetch_assoc()['c'];
    $r=$conn->query("SELECT COUNT(*) c FROM appointments WHERE payment_status='Unpaid'"); if($r) $unpaid_count=$r->fetch_assoc()['c'];
}

$docAppts = [];
$dr = $conn->query("SELECT d.full_name, COUNT(a.id) c FROM doctors d LEFT JOIN appointments a ON a.doctor_id=d.id GROUP BY d.id ORDER BY c DESC LIMIT 8");
if ($dr) while($r=$dr->fetch_assoc()) $docAppts[] = $r;

$trendLabels    = json_encode(array_column($periods,'label'));
$trendDataJS    = json_encode(array_values($trendData));
$trendCompJS    = json_encode(array_values($trendCompleted));
$trendPendJS    = json_encode(array_values($trendPending));
$trendConfJS    = json_encode(array_values($trendConfirmed));
$trendCancJS    = json_encode(array_values($trendCancelled));
$trendRevJS     = json_encode(array_values($trendRevenue));
$docNames       = json_encode(array_column($docAppts,'full_name'));
$docCounts      = json_encode(array_map(fn($r)=>(int)$r['c'],$docAppts));

function delta($cur,$prev,$prefix='',$suffix='') {
    $d = $cur - $prev;
    if ($d > 0) return "<span class='delta pos'>+{$prefix}{$d}{$suffix} vs prev</span>";
    if ($d < 0) return "<span class='delta neg'>{$prefix}{$d}{$suffix} vs prev</span>";
    return "<span class='delta neu'>+0 vs prev</span>";
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <link rel="icon" type="image/png" href="/telecarelogo.png">
  <link rel="shortcut icon" type="image/png" href="/telecarelogo.png">
  <link rel="apple-touch-icon" type="image/png" href="/telecarelogo.png">
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — TELE-CARE</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="assets/admin.css" rel="stylesheet"/>
<link href="assets/admin-dashboard.css" rel="stylesheet"/>
<script src="assets/admin-dashboard.js" defer></script>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<?php $activeNav = 'dashboard'; include 'sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Good morning, Admin</div>
      <div style="font-size:0.95rem;font-weight:700;">Here's what's happening in TELE-CARE today.</div>
    </div>
    
  </div>

  <div class="page-content">

    <div class="filter-bar">
      <span>Period:</span>
      <a href="?filter=week"  class="filter-btn <?= $filter==='week'?'active':'' ?>">Week</a>
      <a href="?filter=month" class="filter-btn <?= $filter==='month'?'active':'' ?>">Month</a>
      <a href="?filter=year"  class="filter-btn <?= $filter==='year'?'active':'' ?>">Year</a>
    </div>

    <!-- KPI Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="label">Total Doctors</div>
        <div class="value"><?= $total_doctors ?></div>
        <?= delta($cur_doc_new, $prev_doctors) ?>
      </div>
      <div class="stat-card">
        <div class="label">Total Patients</div>
        <div class="value"><?= $total_patients ?></div>
        <?= delta($cur_pat_new, $prev_patients) ?>
      </div>
      <div class="stat-card">
        <div class="label">Total Staff</div>
        <div class="value"><?= $total_staff ?></div>
        <?= delta($cur_staff_new, $prev_staff) ?>
      </div>
      <div class="stat-card">
        <div class="label">Appointments</div>
        <div class="value"><?= $cur_appts ?></div>
        <?= delta($cur_appts, $prev_appts) ?>
      </div>
      <div class="stat-card">
        <div class="label">Completed</div>
        <div class="value"><?= $cur_completed ?></div>
        <?= delta($cur_completed, $prev_completed) ?>
      </div>
      <div class="stat-card revenue-card">
        <div class="label">Revenue</div>
        <div class="value">₱<?= number_format($cur_rev) ?></div>
        <?= delta((int)$cur_rev, (int)$prev_rev, '₱') ?>
      </div>
    </div>

    <!-- Cancelled KPI (below, smaller) — only show if non-zero or always -->
    <!-- Trend Analysis + Stacked Bar -->
    <div class="charts-grid">
      <div class="chart-card">
        <div class="chart-title">Trend Analysis</div>
        <div class="chart-subtitle">Appointment volume - total bookings over selected period</div>
        <div class="chart-wrap" style="height:220px">
          <canvas id="lineChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Appointment Status</div>
        <div class="chart-subtitle">Distribution by status over selected period</div>
        <div class="chart-wrap" style="height:220px">
          <canvas id="stackedBar"></canvas>
        </div>
      </div>
    </div>

    <!-- Revenue + Payment Donut + Doctor Bar -->
    <div class="charts-grid-3">
      <div class="chart-card">
        <div class="chart-title">Revenue &amp; Income</div>
        <div class="chart-subtitle">Revenue trend (₱) - income from paid consultations</div>
        <div class="chart-wrap" style="height:200px">
          <canvas id="areaChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Operational Report</div>
        <div class="chart-subtitle">Payment status - paid vs unpaid appointments</div>
        <div class="chart-wrap" style="height:200px;display:flex;align-items:center;justify-content:center;">
          <canvas id="donutChart" style="max-height:200px;max-width:200px"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-title">Appointments by Doctor</div>
        <div class="chart-subtitle">Volume per physician</div>
        <div class="chart-wrap" style="height:200px">
          <canvas id="doctorBar"></canvas>
        </div>
      </div>
    </div>

    <!-- Doctor Overview Table -->
    <div class="section-header">
      <h2>Doctor Overview</h2>
<a href="users.php" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">View all</a>    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Doctor</th><th>Specialty</th><th>Status</th></tr></thead>
        <tbody>
        <?php
        $dres = $conn->query("SELECT * FROM doctors ORDER BY created_at DESC LIMIT 10");
        if ($dres && $dres->num_rows > 0): while ($d = $dres->fetch_assoc()): ?>
        <tr>
          <td><span style="font-weight:600;">Dr. <?= htmlspecialchars($d['full_name']) ?></span></td>
          <td style="color:#9ab0ae;font-size:0.82rem;"><?= htmlspecialchars($d['specialty'] ?? '—') ?></td>
          <td><span class="badge <?= $d['status']==='active'?'badge-green':($d['status']==='pending'?'badge-orange':'badge-gray') ?>"><?= ucfirst($d['status']) ?></span></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="3" class="empty-row">No doctors yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>


<script>
window.TELECARE_DASHBOARD_DATA = {
    labels: <?= $trendLabels ?: '[]' ?>,
    appointments: <?= $trendDataJS ?: '[]' ?>,
    completed: <?= $trendCompJS ?: '[]' ?>,
    pending: <?= $trendPendJS ?: '[]' ?>,
    confirmed: <?= $trendConfJS ?: '[]' ?>,
    cancelled: <?= $trendCancJS ?: '[]' ?>,
    revenue: <?= $trendRevJS ?: '[]' ?>,
    doctorNames: <?= $docNames ?: '[]' ?>,
    doctorCounts: <?= $docCounts ?: '[]' ?>,
    paidCount: <?= (int) $paid_count ?>,
    unpaidCount: <?= (int) $unpaid_count ?>
};
</script>

</body>
</html>




