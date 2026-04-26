<?php
session_start();
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
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Dashboard — TELE-CARE</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<style>
:root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF;--teal:#0d9488}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);display:flex;min-height:100vh}
h1,h2,h3{font-family:'Playfair Display',serif}
.sidebar{width:230px;min-width:230px;background:var(--green);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-logo{padding:1.8rem 1.5rem 1.2rem;font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:#fff;border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-logo span{color:var(--red)}
.sidebar-admin{padding:1rem 1.5rem;font-size:0.78rem;color:rgba(255,255,255,0.45);border-bottom:1px solid rgba(255,255,255,0.08)}
.sidebar-admin strong{color:rgba(255,255,255,0.8);font-weight:600;display:block;font-size:0.88rem}
.nav-links{padding:1rem 0;flex:1}
.nav-link{display:flex;align-items:center;gap:0.8rem;padding:0.8rem 1.5rem;color:rgba(255,255,255,0.55);font-size:0.88rem;font-weight:500;width:100%;text-align:left;font-family:'DM Sans',sans-serif;transition:all 0.2s;border-left:3px solid transparent;text-decoration:none}
.nav-link svg{width:18px;height:18px;stroke:currentColor;flex-shrink:0}
.nav-link:hover{color:#fff;background:rgba(255,255,255,0.06)}
.nav-link.active{color:#fff;background:rgba(255,255,255,0.1);border-left-color:var(--red)}
.sidebar-logout{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,0.08)}
.logout-btn{display:flex;align-items:center;gap:0.6rem;color:rgba(255,255,255,0.45);font-size:0.82rem;text-decoration:none;transition:color 0.2s}
.logout-btn:hover{color:var(--red)}
.main{flex:1;overflow-y:auto}
.topbar{background:var(--white);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
.page-content{padding:2rem}
.filter-bar{display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem}
.filter-bar span{font-size:0.78rem;color:#9ab0ae;font-weight:600;margin-right:0.3rem}
.filter-btn{padding:0.35rem 0.9rem;border-radius:50px;font-size:0.78rem;font-weight:600;border:1.5px solid rgba(36,68,65,0.15);background:var(--white);color:#9ab0ae;cursor:pointer;transition:all 0.2s;text-decoration:none}
.filter-btn.active{background:var(--green);color:#fff;border-color:var(--green)}
.filter-btn:hover:not(.active){border-color:var(--green);color:var(--green)}
.stats-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem}
.stat-card{background:var(--white);border-radius:16px;padding:1.2rem 1rem;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
.stat-card .label{font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;margin-bottom:0.4rem}
.stat-card .value{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:900;color:var(--green);line-height:1}
.stat-card.revenue-card .value{font-size:1.3rem}
.delta{font-size:0.7rem;font-weight:600;display:block;margin-top:0.3rem}
.delta.pos{color:#16a34a}.delta.neg{color:var(--red)}.delta.neu{color:#9ab0ae}
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}
.charts-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}
.chart-card{background:var(--white);border-radius:16px;padding:1.4rem;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
.chart-card.full{grid-column:1/-1}
.chart-title{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:#9ab0ae;margin-bottom:0.2rem}
.chart-subtitle{font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;color:var(--green);margin-bottom:1rem}
.chart-wrap{position:relative;width:100%}
.chart-wrap canvas{max-width:100%}
.btn-primary{display:inline-flex;align-items:center;gap:0.4rem;background:var(--red);color:#fff;padding:0.6rem 1.3rem;border-radius:50px;font-size:0.85rem;font-weight:600;border:none;cursor:pointer;transition:all 0.25s;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(195,54,67,0.25);text-decoration:none}
.btn-primary:hover{background:#a82d38;transform:translateY(-1px)}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem}
.section-header h2{font-size:1.3rem}
.table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:1.2rem}
table{width:100%;border-collapse:collapse}
th{padding:0.9rem 1.2rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;text-align:left;background:rgba(36,68,65,0.03);border-bottom:1px solid rgba(36,68,65,0.07)}
td{padding:0.9rem 1.2rem;font-size:0.88rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(36,68,65,0.02)}
.badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
.badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
.badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
.badge-gray{background:rgba(0,0,0,0.06);color:#888}
.toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
@keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
.empty-row{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}
@media(max-width:1200px){.stats-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.sidebar{display:none}.stats-grid{grid-template-columns:repeat(2,1fr)}.charts-grid,.charts-grid-3{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-admin">Admin Portal<br/><strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></strong></div>
  <nav class="nav-links">
    <a href="dashboard.php" class="nav-link active">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="doctors.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Doctors
    </a>
    <a href="patients.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patients
    </a>
    <a href="assignments.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Appointments
    </a>
  </nav>
  <div class="sidebar-logout">
    <a href="logout.php" class="logout-btn">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Log Out
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Good morning, Admin</div>
      <div style="font-size:0.95rem;font-weight:700;">Here's what's happening in TELE-CARE today.</div>
    </div>
    <a href="doctors.php" class="btn-primary">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Add Doctor
    </a>
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
      <a href="doctors.php" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">View all</a>
    </div>
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
const labels    = <?= $trendLabels ?>;
const dataAppts = <?= $trendDataJS ?>;
const dataComp  = <?= $trendCompJS ?>;
const dataPend  = <?= $trendPendJS ?>;
const dataConf  = <?= $trendConfJS ?>;
const dataCanc  = <?= $trendCancJS ?>;
const dataRev   = <?= $trendRevJS ?>;
const docNames  = <?= $docNames ?>;
const docCounts = <?= $docCounts ?>;
const paidCnt   = <?= $paid_count ?>;
const unpaidCnt = <?= $unpaid_count ?>;

const gridColor = 'rgba(36,68,65,0.07)';
const fontColor = '#9ab0ae';

const baseOpts = {
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{ labels:{ font:{family:"'DM Sans',sans-serif",size:11}, color:'#244441', boxWidth:10, padding:12 }}},
  scales:{
    x:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'DM Sans',sans-serif"}} },
    y:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'DM Sans',sans-serif"},precision:0} }
  }
};

// LINE CHART
new Chart(document.getElementById('lineChart'), {
  type:'line',
  data:{
    labels,
    datasets:[
      { label:'Appointments', data:dataAppts, borderColor:'#3F82E3', backgroundColor:'rgba(63,130,227,0.08)', tension:0.4, fill:true, pointRadius:3, borderWidth:2 },
      { label:'Completed',    data:dataComp,  borderColor:'#0d9488', backgroundColor:'rgba(13,148,136,0.06)', tension:0.4, fill:true, pointRadius:3, borderWidth:2, borderDash:[4,3] }
    ]
  },
  options:{...baseOpts, plugins:{...baseOpts.plugins}}
});

// STACKED BAR
new Chart(document.getElementById('stackedBar'), {
  type:'bar',
  data:{
    labels,
    datasets:[
      { label:'Pending',   data:dataPend, backgroundColor:'#3F82E3', stack:'s' },
      { label:'Completed', data:dataComp, backgroundColor:'#0d9488', stack:'s' },
      { label:'Confirmed', data:dataConf, backgroundColor:'#f59e0b', stack:'s' },
      { label:'Cancelled', data:dataCanc, backgroundColor:'#ef4444', stack:'s' }
    ]
  },
  options:{
    ...baseOpts,
    scales:{
      x:{ stacked:true, grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'DM Sans',sans-serif"}} },
      y:{ stacked:true, grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'DM Sans',sans-serif"},precision:0} }
    }
  }
});

// AREA LINE (Revenue)
new Chart(document.getElementById('areaChart'), {
  type:'line',
  data:{
    labels,
    datasets:[{
      label:'Revenue',
      data:dataRev,
      borderColor:'#0d9488',
      backgroundColor:'rgba(13,148,136,0.12)',
      tension:0.4, fill:true, pointRadius:3, borderWidth:2
    }]
  },
  options:{
    ...baseOpts,
    plugins:{...baseOpts.plugins, legend:{display:true, labels:{font:{family:"'DM Sans',sans-serif",size:11},color:'#244441',boxWidth:10}}},
    scales:{
      x:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'DM Sans',sans-serif"}} },
      y:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'DM Sans',sans-serif"},callback:v=>'₱'+v.toLocaleString()} }
    }
  }
});

// DONUT
new Chart(document.getElementById('donutChart'), {
  type:'doughnut',
  data:{
    labels:['Paid','Unpaid'],
    datasets:[{ data:[paidCnt,unpaidCnt], backgroundColor:['#0d9488','#ef4444'], borderWidth:0, hoverOffset:4 }]
  },
  options:{
    responsive:true, maintainAspectRatio:false, cutout:'65%',
    plugins:{
      legend:{ position:'bottom', labels:{font:{family:"'DM Sans',sans-serif",size:11},color:'#244441',boxWidth:10,padding:10} },
      tooltip:{ callbacks:{ label:ctx=>` ${ctx.label}: ${ctx.parsed} (${Math.round(ctx.parsed/(paidCnt+unpaidCnt||1)*100)}%)` } }
    }
  }
});

// DOCTOR BAR
new Chart(document.getElementById('doctorBar'), {
  type:'bar',
  data:{
    labels:docNames.map(n=>'Dr. '+n),
    datasets:[{ label:'Appointments', data:docCounts, backgroundColor:'#7c6ef7', borderRadius:6, borderSkipped:false }]
  },
  options:{
    ...baseOpts,
    plugins:{...baseOpts.plugins, legend:{display:false}},
    scales:{
      x:{ grid:{display:false}, ticks:{color:fontColor,font:{size:9,family:"'DM Sans',sans-serif"},maxRotation:30} },
      y:{ grid:{color:gridColor}, ticks:{color:fontColor,font:{size:10,family:"'DM Sans',sans-serif"},precision:0} }
    }
  }
});
</script>

<script>
setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>