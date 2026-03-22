<?php
require_once 'includes/auth.php';

function logAction($conn, $appt_id, $staff_id, $action, $notes='') {
    $stmt = $conn->prepare("INSERT INTO appointment_logs (appointment_id,staff_id,action,notes) VALUES (?,?,?,?)");
    $stmt->bind_param("iiss", $appt_id, $staff_id, $action, $notes);
    $stmt->execute();
}

// ── Handle appointment actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Approve / Reject / Cancel / Complete
    if (isset($_POST['action'], $_POST['appt_id'])) {
        $aid    = (int)$_POST['appt_id'];
        $action = $_POST['action'];
        $notes  = trim($_POST['action_notes'] ?? '');
        $map    = ['approve'=>'Confirmed','reject'=>'Cancelled','cancel'=>'Cancelled','complete'=>'Completed'];
        if (isset($map[$action])) {
            $new_status = $map[$action];
            $conn->query("UPDATE appointments SET status='$new_status' WHERE id=$aid");
            logAction($conn, $aid, $staff_id, ucfirst($action).'d', $notes);
            $_SESSION['toast'] = "Appointment " . $map[$action] . " successfully.";
        }
        header('Location: dashboard.php'); exit;
    }

    // Create appointment manually
    if (isset($_POST['create_appt'])) {
        $pid   = (int)$_POST['patient_id'];
        $did   = (int)$_POST['doctor_id'];
        $date  = trim($_POST['appt_date'] ?? '');
        $time  = trim($_POST['appt_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($pid && $did && $date && $time) {
            $type = 'Teleconsult';
            $stmt = $conn->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,type,notes,status,payment_status) VALUES (?,?,?,?,?,?,'Confirmed','Unpaid')");
            $stmt->bind_param("iissss", $pid, $did, $date, $time, $type, $notes);
            $stmt->execute();
            $_SESSION['toast'] = "Appointment created successfully.";
        }
        header('Location: dashboard.php'); exit;
    }

    // Reschedule
    if (isset($_POST['reschedule'])) {
        $aid  = (int)$_POST['appt_id'];
        $date = trim($_POST['new_date'] ?? '');
        $time = trim($_POST['new_time'] ?? '');
        if ($aid && $date && $time) {
            $conn->query("UPDATE appointments SET appointment_date='$date', appointment_time='$time', status='Confirmed' WHERE id=$aid");
            $_SESSION['toast'] = "Appointment rescheduled.";
        }
        header('Location: dashboard.php'); exit;
    }

    // Update patient info
    if (isset($_POST['update_patient'])) {
        $pid   = (int)$_POST['patient_id'];
        $phone = trim($_POST['phone_number'] ?? '');
        $addr  = trim($_POST['home_address'] ?? '');
        $city  = trim($_POST['city'] ?? '');
        $stmt  = $conn->prepare("UPDATE patients SET phone_number=?,home_address=?,city=? WHERE id=?");
        $stmt->bind_param("sssi", $phone, $addr, $city, $pid);
        $stmt->execute();
        $_SESSION['toast'] = "Patient info updated.";
        header('Location: dashboard.php?tab=patients'); exit;
    }
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$active_tab = $_GET['tab'] ?? 'dashboard';

// ── Data fetches ──
$today = date('Y-m-d');

// Today's appointments
$today_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.phone_number,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.appointment_date = '$today'
    ORDER BY a.appointment_time ASC
");

// Pending appointments (queue for approval)
$pending_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.phone_number, p.email AS patient_email,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.status = 'Pending'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

// All appointments
$all_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 80
");

// All patients
$all_patients = $conn->query("
    SELECT p.*, 
           (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id) AS appt_count,
           (SELECT d.full_name FROM doctors d JOIN patient_doctors pd ON pd.doctor_id=d.id WHERE pd.patient_id=p.id LIMIT 1) AS doctor_name
    FROM patients p
    ORDER BY p.full_name ASC
");

// All active doctors (for create form)
$all_doctors = $conn->query("SELECT id, full_name, specialty FROM doctors WHERE status='active' ORDER BY full_name ASC");

// Stats
$stat_today    = $today_appts ? $today_appts->num_rows : 0;
$stat_pending  = $pending_appts ? $pending_appts->num_rows : 0;
$stat_patients = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$stat_doctors  = $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='active'")->fetch_assoc()['c'];

// Reset result pointers
if ($today_appts)   $today_appts->data_seek(0);
if ($pending_appts) $pending_appts->data_seek(0);
if ($all_appts)     $all_appts->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Staff Dashboard — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#EEF3FB;--white:#fff;--muted:#8fa3c8;--text:#1a2f5e}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}
    h1,h2,h3{font-family:'Playfair Display',serif}

    /* Sidebar */
    .sidebar{width:220px;min-width:220px;background:var(--green);display:flex;flex-direction:column;position:sticky;top:0;height:100vh}
    .sb-logo{padding:1.6rem 1.4rem 1.2rem;font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;color:#fff;border-bottom:1px solid rgba(255,255,255,.08)}
    .sb-logo span{color:var(--red)}
    .sb-badge{padding:.7rem 1.4rem;font-size:.72rem;color:rgba(255,255,255,.4);border-bottom:1px solid rgba(255,255,255,.08)}
    .sb-badge strong{color:rgba(255,255,255,.75);display:block;font-size:.85rem;margin-top:.1rem}
    .sb-nav{padding:.8rem 0;flex:1}
    .sb-link{display:flex;align-items:center;gap:.75rem;padding:.75rem 1.4rem;color:rgba(255,255,255,.5);font-size:.86rem;font-weight:500;cursor:pointer;border-left:3px solid transparent;transition:all .2s;text-decoration:none;background:none;border-right:none;border-top:none;border-bottom:none;width:100%;font-family:'DM Sans',sans-serif}
    .sb-link svg{width:17px;height:17px;stroke:currentColor;flex-shrink:0}
    .sb-link:hover{color:#fff;background:rgba(255,255,255,.06)}
    .sb-link.active{color:#fff;background:rgba(255,255,255,.1);border-left-color:var(--red)}
    .sb-foot{padding:1rem 1.4rem;border-top:1px solid rgba(255,255,255,.08)}
    .sb-foot a{display:flex;align-items:center;gap:.5rem;color:rgba(255,255,255,.4);font-size:.8rem;text-decoration:none;transition:color .2s}
    .sb-foot a:hover{color:var(--red)}

    /* Main */
    .main{flex:1;overflow-y:auto}
    .topbar{background:#fff;padding:.9rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,.07);position:sticky;top:0;z-index:50;box-shadow:0 1px 4px rgba(0,0,0,.04)}
    .page-wrap{padding:1.8rem 2rem}

    /* Cards */
    .card{background:#fff;border-radius:16px;padding:1.3rem;border:1px solid rgba(36,68,65,.06);box-shadow:0 2px 10px rgba(0,0,0,.04);margin-bottom:1.2rem}
    .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
    .stat-card{background:#fff;border-radius:14px;padding:1.1rem 1.2rem;border:1px solid rgba(36,68,65,.06);box-shadow:0 2px 8px rgba(0,0,0,.04)}
    .stat-num{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;line-height:1}
    .stat-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-top:.3rem}

    /* Tabs */
    .tab-content{display:none}.tab-content.active{display:block}

    /* Table */
    .tbl-wrap{background:#fff;border-radius:14px;overflow:hidden;border:1px solid rgba(36,68,65,.07);box-shadow:0 2px 8px rgba(0,0,0,.04)}
    table{width:100%;border-collapse:collapse}
    th{background:rgba(36,68,65,.04);padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid rgba(36,68,65,.07)}
    td{padding:.75rem 1rem;font-size:.85rem;border-bottom:1px solid rgba(36,68,65,.05);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(63,130,227,.02)}

    /* Badges */
    .badge{display:inline-block;padding:.2rem .65rem;border-radius:50px;font-size:.68rem;font-weight:700;letter-spacing:.03em}
    .bg-green{background:rgba(34,197,94,.1);color:#16a34a}
    .bg-orange{background:rgba(245,158,11,.1);color:#d97706}
    .bg-red{background:rgba(195,54,67,.1);color:var(--red)}
    .bg-blue{background:rgba(63,130,227,.1);color:var(--blue)}
    .bg-gray{background:rgba(0,0,0,.06);color:#888}

    /* Buttons */
    .btn-primary{background:var(--blue);color:#fff;padding:.5rem 1rem;border-radius:50px;font-size:.8rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;text-decoration:none;display:inline-block}
    .btn-primary:hover{background:#2d6fd4}
    .btn-green{background:rgba(34,197,94,.1);color:#16a34a;padding:.4rem .8rem;border-radius:50px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-green:hover{background:#16a34a;color:#fff}
    .btn-red{background:rgba(195,54,67,.1);color:var(--red);padding:.4rem .8rem;border-radius:50px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-red:hover{background:var(--red);color:#fff}
    .btn-orange{background:rgba(245,158,11,.1);color:#d97706;padding:.4rem .8rem;border-radius:50px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-orange:hover{background:#d97706;color:#fff}
    .btn-sm{padding:.35rem .8rem;border-radius:50px;font-size:.73rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}

    /* Modal */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1rem;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:20px;padding:1.8rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;animation:mUp .3s ease}
    @keyframes mUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .modal h3{font-size:1.2rem;margin-bottom:1rem}
    .f-label{display:block;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem}
    .f-input{width:100%;padding:.68rem .9rem;border:1.5px solid rgba(36,68,65,.12);border-radius:11px;font-family:'DM Sans',sans-serif;font-size:.88rem;color:var(--text);outline:none;transition:border-color .2s;background:#fff;margin-bottom:.8rem}
    .f-input:focus{border-color:var(--blue)}
    select.f-input{cursor:pointer}
    .f-row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
    .btn-submit{width:100%;padding:.8rem;border-radius:50px;background:var(--blue);color:#fff;font-weight:700;font-size:.9rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .25s;margin-top:.3rem}
    .btn-submit:hover{background:#2d6fd4}
    .btn-cancel-modal{width:100%;padding:.65rem;border-radius:50px;background:transparent;color:var(--text);font-weight:600;font-size:.85rem;border:1.5px solid rgba(36,68,65,.15);cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:.5rem}

    /* Queue card */
    .queue-item{display:flex;align-items:center;gap:.9rem;padding:.85rem 0;border-bottom:1px solid rgba(36,68,65,.06)}
    .queue-item:last-child{border-bottom:none}
    .queue-num{width:32px;height:32px;border-radius:50%;background:rgba(63,130,227,.1);color:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.85rem;flex-shrink:0}

    /* Toast */
    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:.85rem 1.4rem;border-radius:14px;font-size:.86rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,.15);animation:slideIn .4s ease,fadeOut .4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}

    /* Section header */
    .sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
    .sec-head h2{font-size:1.25rem}
    .empty-row{text-align:center;padding:2.5rem;color:var(--muted);font-size:.88rem}

    /* Search */
    .search-bar{padding:.6rem .9rem;border:1.5px solid rgba(36,68,65,.12);border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.85rem;color:var(--text);outline:none;width:220px;transition:border-color .2s}
    .search-bar:focus{border-color:var(--blue)}

    @media(max-width:900px){.sidebar{display:none}.stat-grid{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>

<?php if($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif ?>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sb-logo">TELE<span>-</span>CARE</div>
  <div class="sb-badge">Staff Portal<strong><?= htmlspecialchars($staff_name) ?></strong></div>
  <nav class="sb-nav">
    <?php
    $nav = [
      ['dashboard','Dashboard','<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
      ['appointments','Appointments','<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
      ['patients','Patients','<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
    ];
    foreach($nav as [$key,$label,$icon]): ?>
    <button class="sb-link <?= $active_tab===$key?'active':'' ?>" onclick="switchTab('<?= $key ?>')">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><?= $icon ?></svg>
      <?= $label ?>
    </button>
    <?php endforeach ?>
  </nav>
  <div class="sb-foot"><a href="logout.php"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Log Out</a></div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:.73rem;color:var(--muted);font-weight:600;">TELE-CARE Staff</div>
      <div id="topbar-title" style="font-size:.95rem;font-weight:700;">Dashboard</div>
    </div>
    <div style="display:flex;align-items:center;gap:.8rem;">
      <div style="font-size:.8rem;color:var(--muted);"><?= date('l, F j, Y') ?></div>
      <?php if($stat_pending > 0): ?>
      <div style="background:rgba(195,54,67,.1);color:var(--red);border-radius:50px;padding:.3rem .8rem;font-size:.75rem;font-weight:700;">⏳ <?= $stat_pending ?> pending</div>
      <?php endif ?>
    </div>
  </div>

  <div class="page-wrap">

    <!-- ══ DASHBOARD TAB ══ -->
    <div class="tab-content <?= $active_tab==='dashboard'?'active':'' ?>" id="tab-dashboard">
      <!-- Stats -->
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-num" style="color:var(--blue)"><?= $stat_today ?></div><div class="stat-lbl">Today's Appointments</div></div>
        <div class="stat-card"><div class="stat-num" style="color:#d97706"><?= $stat_pending ?></div><div class="stat-lbl">Pending Approval</div></div>
        <div class="stat-card"><div class="stat-num" style="color:var(--green)"><?= $stat_patients ?></div><div class="stat-lbl">Total Patients</div></div>
        <div class="stat-card"><div class="stat-num" style="color:var(--red)"><?= $stat_doctors ?></div><div class="stat-lbl">Active Doctors</div></div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;">

        <!-- Today's Queue -->
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
              <div style="font-size:.75rem;color:var(--muted)"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
            </div>
            <span class="badge <?= $a['status']==='Confirmed'?'bg-green':($a['status']==='Pending'?'bg-orange':'bg-gray') ?>"><?= $a['status'] ?></span>
          </div>
          <?php endwhile; else: ?>
          <div class="empty-row">No appointments today.</div>
          <?php endif ?>
        </div>

        <!-- Pending Approvals -->
        <div class="card">
          <div class="sec-head" style="margin-bottom:.8rem">
            <h2 style="font-size:1rem">⏳ Pending Approvals</h2>
            <?php if($stat_pending>0): ?><span class="badge bg-orange"><?= $stat_pending ?></span><?php endif ?>
          </div>
          <?php
          if ($pending_appts && $pending_appts->num_rows > 0):
            $pending_appts->data_seek(0);
            while ($a = $pending_appts->fetch_assoc()):
          ?>
          <div class="queue-item">
            <div style="flex:1">
              <div style="font-weight:700;font-size:.87rem"><?= htmlspecialchars($a['patient_name']) ?></div>
              <div style="font-size:.74rem;color:var(--muted)"><?= date('M j', strtotime($a['appointment_date'])) ?> <?= date('g:i A', strtotime($a['appointment_time'])) ?> · Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
            </div>
            <div style="display:flex;gap:.4rem">
              <button class="btn-green btn-sm" onclick="quickAction(<?= $a['id'] ?>,'approve')">✓</button>
              <button class="btn-red btn-sm"   onclick="quickAction(<?= $a['id'] ?>,'reject')">✕</button>
            </div>
          </div>
          <?php endwhile; else: ?>
          <div class="empty-row">All caught up! No pending requests.</div>
          <?php endif ?>
        </div>
      </div>
    </div>

    <!-- ══ APPOINTMENTS TAB ══ -->
    <div class="tab-content <?= $active_tab==='appointments'?'active':'' ?>" id="tab-appointments">
      <div class="sec-head">
        <h2>Appointment Management</h2>
        <div style="display:flex;gap:.6rem">
          <input class="search-bar" placeholder="Search patient or doctor…" oninput="filterTable('appt-tbody',this.value)"/>
          <button class="btn-primary" onclick="openModal('modal-create')">+ Create Appointment</button>
        </div>
      </div>

      <!-- Filter tabs -->
      <div style="display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap">
        <?php foreach(['All','Pending','Confirmed','Completed','Cancelled'] as $f): ?>
        <button class="btn-sm" style="background:rgba(36,68,65,.07);color:var(--text)" onclick="filterStatus('<?= $f ?>')" id="filter-<?= $f ?>"><?= $f ?></button>
        <?php endforeach ?>
      </div>

      <div class="tbl-wrap">
        <table>
          <thead>
            <tr>
              <th>Patient</th><th>Doctor</th><th>Date & Time</th><th>Type</th><th>Status</th><th>Payment</th><th>Actions</th>
            </tr>
          </thead>
          <tbody id="appt-tbody">
          <?php
          if ($all_appts && $all_appts->num_rows > 0):
            $all_appts->data_seek(0);
            while ($a = $all_appts->fetch_assoc()):
              $sc = $a['status']==='Confirmed'?'bg-green':($a['status']==='Pending'?'bg-orange':($a['status']==='Completed'?'bg-blue':'bg-red'));
              $pc = $a['payment_status']==='Paid'?'bg-green':'bg-red';
          ?>
          <tr data-status="<?= $a['status'] ?>" data-search="<?= strtolower($a['patient_name'].' '.$a['doctor_name']) ?>">
            <td><div style="font-weight:600"><?= htmlspecialchars($a['patient_name']) ?></div></td>
            <td>Dr. <?= htmlspecialchars($a['doctor_name']) ?><br/><span style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($a['specialty']??'') ?></span></td>
            <td><?= date('M j, Y', strtotime($a['appointment_date'])) ?><br/><span style="font-size:.78rem;color:var(--muted)"><?= date('g:i A', strtotime($a['appointment_time'])) ?></span></td>
            <td><?= htmlspecialchars($a['type']) ?></td>
            <td><span class="badge <?= $sc ?>"><?= $a['status'] ?></span></td>
            <td><span class="badge <?= $pc ?>"><?= $a['payment_status'] ?></span></td>
            <td>
              <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                <?php if($a['status']==='Pending'): ?>
                <button class="btn-green btn-sm" onclick="quickAction(<?= $a['id'] ?>,'approve')">Approve</button>
                <button class="btn-red btn-sm"   onclick="quickAction(<?= $a['id'] ?>,'reject')">Reject</button>
                <?php endif ?>
                <?php if($a['status']==='Confirmed'): ?>
                <button class="btn-orange btn-sm" onclick="openReschedule(<?= $a['id'] ?>,'<?= $a['appointment_date'] ?>','<?= substr($a['appointment_time'],0,5) ?>')">Reschedule</button>
                <button class="btn-red btn-sm"    onclick="quickAction(<?= $a['id'] ?>,'cancel')">Cancel</button>
                <button class="btn-green btn-sm"  onclick="quickAction(<?= $a['id'] ?>,'complete')">Complete</button>
                <?php endif ?>
              </div>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="7" class="empty-row">No appointments found.</td></tr>
          <?php endif ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ PATIENTS TAB ══ -->
    <div class="tab-content <?= $active_tab==='patients'?'active':'' ?>" id="tab-patients">
      <div class="sec-head">
        <h2>Patient Management</h2>
        <input class="search-bar" placeholder="Search patient…" oninput="filterTable('patients-tbody',this.value)"/>
      </div>
      <div class="tbl-wrap">
        <table>
          <thead>
            <tr><th>Patient</th><th>Contact</th><th>City</th><th>Doctor</th><th>Appointments</th><th>Actions</th></tr>
          </thead>
          <tbody id="patients-tbody">
          <?php
          if ($all_patients && $all_patients->num_rows > 0):
            while ($p = $all_patients->fetch_assoc()):
          ?>
          <tr data-search="<?= strtolower($p['full_name'].' '.($p['city']??'')) ?>">
            <td>
              <div style="display:flex;align-items:center;gap:.6rem">
                <?php if(!empty($p['profile_photo'])): ?>
                <img src="../<?= htmlspecialchars($p['profile_photo']) ?>" style="width:32px;height:32px;border-radius:8px;object-fit:cover"/>
                <?php else: ?>
                <div style="width:32px;height:32px;border-radius:8px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem"><?= strtoupper(substr($p['full_name'],0,2)) ?></div>
                <?php endif ?>
                <div>
                  <div style="font-weight:700;font-size:.87rem"><?= htmlspecialchars($p['full_name']) ?></div>
                  <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($p['email']) ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($p['phone_number'] ?? '—') ?></td>
            <td><?= htmlspecialchars($p['city'] ?? '—') ?></td>
            <td><?= $p['doctor_name'] ? 'Dr. '.htmlspecialchars($p['doctor_name']) : '<span style="color:var(--muted)">Unassigned</span>' ?></td>
            <td><span class="badge bg-blue"><?= $p['appt_count'] ?> total</span></td>
            <td style="display:flex;gap:.4rem">
              <button class="btn-sm" style="background:rgba(63,130,227,.1);color:var(--blue)" onclick="openEditPatient(<?= htmlspecialchars(json_encode($p)) ?>)">Edit Info</button>
              <button class="btn-sm" style="background:rgba(36,68,65,.07);color:var(--text)"  onclick="openHistory(<?= $p['id'] ?>,'<?= htmlspecialchars(addslashes($p['full_name'])) ?>')">History</button>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="6" class="empty-row">No patients found.</td></tr>
          <?php endif ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /page-wrap -->
</div><!-- /main -->

<!-- ══ MODALS ══ -->

<!-- Quick action (hidden form) -->
<form method="POST" id="quick-form" style="display:none">
  <input type="hidden" name="action"       id="qf-action"/>
  <input type="hidden" name="appt_id"      id="qf-appt-id"/>
  <input type="hidden" name="action_notes" id="qf-notes"/>
</form>

<!-- Create Appointment -->
<div class="modal-overlay" id="modal-create">
  <div class="modal">
    <h3>Create Appointment</h3>
    <form method="POST">
      <label class="f-label">Patient</label>
      <select name="patient_id" class="f-input" required>
        <option value="">Select patient…</option>
        <?php
        $conn->query("SELECT id,full_name FROM patients ORDER BY full_name ASC")->data_seek(0);
        $pres = $conn->query("SELECT id,full_name FROM patients ORDER BY full_name ASC");
        while($r=$pres->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></option>
        <?php endwhile ?>
      </select>
      <label class="f-label">Doctor</label>
      <select name="doctor_id" class="f-input" required>
        <option value="">Select doctor…</option>
        <?php $all_doctors->data_seek(0); while($r=$all_doctors->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>">Dr. <?= htmlspecialchars($r['full_name']) ?> — <?= htmlspecialchars($r['specialty']??'') ?></option>
        <?php endwhile ?>
      </select>
      <div class="f-row">
        <div><label class="f-label">Date</label><input type="date" name="appt_date" class="f-input" required min="<?= date('Y-m-d') ?>"/></div>
        <div><label class="f-label">Time</label><input type="time" name="appt_time" class="f-input" required/></div>
      </div>
      <label class="f-label">Notes</label>
      <textarea name="notes" class="f-input" rows="2" placeholder="Reason for visit…"></textarea>
      <button type="submit" name="create_appt" class="btn-submit">Create Appointment</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-create')">Cancel</button>
    </form>
  </div>
</div>

<!-- Reschedule -->
<div class="modal-overlay" id="modal-reschedule">
  <div class="modal">
    <h3>Reschedule Appointment</h3>
    <form method="POST">
      <input type="hidden" name="reschedule" value="1"/>
      <input type="hidden" name="appt_id"    id="rs-appt-id"/>
      <div class="f-row">
        <div><label class="f-label">New Date</label><input type="date" name="new_date" id="rs-date" class="f-input" required min="<?= date('Y-m-d') ?>"/></div>
        <div><label class="f-label">New Time</label><input type="time" name="new_time" id="rs-time" class="f-input" required/></div>
      </div>
      <button type="submit" class="btn-submit">Confirm Reschedule</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-reschedule')">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Patient -->
<div class="modal-overlay" id="modal-edit-patient">
  <div class="modal">
    <h3 id="edit-patient-title">Edit Patient Info</h3>
    <form method="POST">
      <input type="hidden" name="update_patient" value="1"/>
      <input type="hidden" name="patient_id" id="ep-id"/>
      <label class="f-label">Phone Number</label>
      <input type="tel" name="phone_number" id="ep-phone" class="f-input" placeholder="09XXXXXXXXX"/>
      <label class="f-label">Home Address</label>
      <input type="text" name="home_address" id="ep-addr" class="f-input" placeholder="Street, Barangay"/>
      <label class="f-label">City / Municipality</label>
      <input type="text" name="city" id="ep-city" class="f-input" placeholder="e.g. Quezon City"/>
      <button type="submit" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-edit-patient')">Cancel</button>
    </form>
  </div>
</div>

<!-- Patient History -->
<div class="modal-overlay" id="modal-history">
  <div class="modal">
    <h3 id="history-title">Appointment History</h3>
    <div id="history-body" style="max-height:55vh;overflow-y:auto;margin-top:.5rem"></div>
    <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-history')">Close</button>
  </div>
</div>

<!-- Patient history data -->
<script>
const PATIENT_HISTORY = {};
<?php
$hres = $conn->query("SELECT a.patient_id, a.appointment_date, a.appointment_time, a.status, a.type, d.full_name AS doctor_name FROM appointments a JOIN doctors d ON d.id=a.doctor_id ORDER BY a.appointment_date DESC");
$hist = [];
if($hres) while($h=$hres->fetch_assoc()) $hist[$h['patient_id']][] = $h;
foreach($hist as $pid=>$rows): ?>
PATIENT_HISTORY[<?= $pid ?>] = <?= json_encode($rows) ?>;
<?php endforeach ?>

// ── Tab switching ──
const TAB_LABELS = {dashboard:'Dashboard',appointments:'Appointments',patients:'Patients'};
function switchTab(name) {
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.sb-link').forEach(l=>l.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  document.querySelector(`.sb-link[onclick*="${name}"]`).classList.add('active');
  document.getElementById('topbar-title').textContent = TAB_LABELS[name] || name;
  history.replaceState(null,'','?tab='+name);
}

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));

// ── Quick action ──
function quickAction(id, action) {
  document.getElementById('qf-appt-id').value = id;
  document.getElementById('qf-action').value   = action;
  document.getElementById('qf-notes').value    = '';
  document.getElementById('quick-form').submit();
}

// ── Reschedule ──
function openReschedule(id, date, time) {
  document.getElementById('rs-appt-id').value = id;
  document.getElementById('rs-date').value    = date;
  document.getElementById('rs-time').value    = time;
  openModal('modal-reschedule');
}

// ── Edit patient ──
function openEditPatient(p) {
  document.getElementById('ep-id').value    = p.id;
  document.getElementById('ep-phone').value = p.phone_number || '';
  document.getElementById('ep-addr').value  = p.home_address || '';
  document.getElementById('ep-city').value  = p.city || '';
  document.getElementById('edit-patient-title').textContent = 'Edit — ' + p.full_name;
  openModal('modal-edit-patient');
}

// ── Patient history ──
function openHistory(pid, name) {
  const rows = PATIENT_HISTORY[pid] || [];
  document.getElementById('history-title').textContent = name + ' — History';
  if (rows.length === 0) {
    document.getElementById('history-body').innerHTML = '<div style="text-align:center;padding:2rem;color:#9ab0ae">No appointment history.</div>';
  } else {
    document.getElementById('history-body').innerHTML = rows.map(r => {
      const sc = r.status==='Completed'?'bg-green':r.status==='Confirmed'?'bg-blue':r.status==='Pending'?'bg-orange':'bg-red';
      return `<div style="display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;border-bottom:1px solid rgba(36,68,65,.06)">
        <div>
          <div style="font-weight:600;font-size:.87rem">${new Date(r.appointment_date+'T00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}</div>
          <div style="font-size:.75rem;color:#9ab0ae">Dr. ${r.doctor_name} · ${r.type}</div>
        </div>
        <span class="badge ${sc}">${r.status}</span>
      </div>`;
    }).join('');
  }
  openModal('modal-history');
}

// ── Filter table by search ──
function filterTable(tbodyId, query) {
  const q    = query.toLowerCase().trim();
  const rows = document.querySelectorAll('#'+tbodyId+' tr[data-search]');
  rows.forEach(r => r.style.display = (!q||r.dataset.search.includes(q)) ? '' : 'none');
}

// ── Filter appointments by status ──
function filterStatus(status) {
  const rows = document.querySelectorAll('#appt-tbody tr[data-status]');
  rows.forEach(r => r.style.display = (status==='All'||r.dataset.status===status) ? '' : 'none');
  document.querySelectorAll('[id^=filter-]').forEach(b=>{
    b.style.background = b.id==='filter-'+status ? 'var(--blue)' : 'rgba(36,68,65,.07)';
    b.style.color      = b.id==='filter-'+status ? '#fff'        : 'var(--text)';
  });
}

// Toast auto-dismiss
setTimeout(()=>{ const t=document.querySelector('.toast'); if(t)t.remove(); }, 3500);
</script>
</body>
</html>