<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php'); exit;
}
$admin_id   = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// ────────────────────────────────────
// ACTIONS
// ────────────────────────────────────

// Create doctor + generate invite token
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_doctor'])) {
    $fn   = trim($_POST['full_name'] ?? '');
    $em   = trim($_POST['email'] ?? '');
    $spec = trim($_POST['specialty'] ?? '');
    $sub  = trim($_POST['subspecialty'] ?? '');
    $acc  = $_POST['access_level'] ?? 'junior';

    if ($fn && $em) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $stmt = $conn->prepare("INSERT INTO doctors (full_name, email, specialty, subspecialty, access_level, invite_token, invite_expires, status) VALUES (?,?,?,?,?,?,?,'pending')");
        $stmt->bind_param("sssssss", $fn, $em, $spec, $sub, $acc, $token, $expires);
        $stmt->execute();
        $_SESSION['toast'] = "Doctor account created! Invite link generated.";
        $_SESSION['invite_link'] = '../doctor/setup.php?token='.$token;
    }
    header('Location: dashboard.php?tab=doctors'); exit;
}

// Toggle doctor active/inactive
if (isset($_GET['toggle_doctor'])) {
    $did = (int)$_GET['toggle_doctor'];
    $conn->query("UPDATE doctors SET status = IF(status='active','inactive','active'), is_available = IF(status='inactive',1,0) WHERE id=$did");
    header('Location: dashboard.php?tab=doctors'); exit;
}

// Verify doctor (Phase 2)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['verify_doctor'])) {
    $did     = (int)$_POST['doctor_id'];
    $license = trim($_POST['license_number'] ?? '');
    $board   = trim($_POST['issuing_board'] ?? '');
    $now     = date('Y-m-d H:i:s');

    // Handle file uploads
    $license_file = null;
    $cert_file    = null;
    $upload_dir   = '../uploads/docs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    foreach (['license_file' => 'license_file', 'board_cert_file' => 'board_cert_file'] as $input => $col) {
        if (!empty($_FILES[$input]['name'])) {
            $ext  = pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION);
            $fname = uniqid("doc_{$did}_") . '.' . $ext;
            if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_dir . $fname)) {
                $$col = 'uploads/docs/' . $fname;
            }
        }
    }

    $stmt = $conn->prepare("UPDATE doctors SET license_number=?, issuing_board=?, license_file=COALESCE(?,license_file), board_cert_file=COALESCE(?,board_cert_file), is_verified=1, verified_at=?, verified_by=? WHERE id=?");
    $stmt->bind_param("sssssii", $license, $board, $license_file, $cert_file, $now, $admin_id, $did);
    $stmt->execute();
    $_SESSION['toast'] = "Doctor verified successfully.";
    header('Location: dashboard.php?tab=doctors'); exit;
}

// Assign patient to doctor
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['assign_patient'])) {
    $pid = (int)$_POST['patient_id'];
    $did = (int)$_POST['doctor_id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO patient_doctors (patient_id, doctor_id) VALUES (?,?)");
    $stmt->bind_param("ii", $pid, $did);
    $stmt->execute();
    $_SESSION['toast'] = "Patient assigned.";
    header('Location: dashboard.php?tab=patients'); exit;
}

// Unassign patient
if (isset($_GET['unassign'])) {
    $pid = (int)$_GET['pid'];
    $did = (int)$_GET['did'];
    $conn->query("DELETE FROM patient_doctors WHERE patient_id=$pid AND doctor_id=$did");
    $_SESSION['toast'] = "Patient unassigned.";
    header('Location: dashboard.php?tab=assignments'); exit;
}

// ────────────────────────────────────
// FETCH DATA
// ────────────────────────────────────
$total_doctors  = $conn->query("SELECT COUNT(*) c FROM doctors")->fetch_assoc()['c'];
$total_patients = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$active_appts   = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status IN ('Pending','Confirmed')")->fetch_assoc()['c'];
$avg_load_r     = $conn->query("SELECT AVG(cnt) avg FROM (SELECT COUNT(*) cnt FROM patient_doctors GROUP BY doctor_id) t");
$avg_load       = $avg_load_r ? number_format($avg_load_r->fetch_assoc()['avg'] ?? 0, 1) : '0.0';

$doctors  = $conn->query("SELECT d.*, (SELECT COUNT(*) FROM patient_doctors WHERE doctor_id=d.id) AS patient_count FROM doctors d ORDER BY d.created_at DESC");
$patients = $conn->query("SELECT p.*, d.full_name AS doctor_name, pd.doctor_id FROM patients p LEFT JOIN patient_doctors pd ON pd.patient_id=p.id LEFT JOIN doctors d ON d.id=pd.doctor_id ORDER BY p.created_at DESC");
$unassigned = $conn->query("SELECT * FROM patients WHERE id NOT IN (SELECT patient_id FROM patient_doctors) ORDER BY created_at DESC");
$all_doctors_active = $conn->query("SELECT id, full_name, specialty FROM doctors WHERE status='active' ORDER BY full_name");
$assignments = $conn->query("SELECT d.id, d.full_name, d.specialty, d.status, (SELECT COUNT(*) FROM patient_doctors WHERE doctor_id=d.id) AS patient_count FROM doctors d ORDER BY patient_count DESC");

$active_tab   = $_GET['tab'] ?? 'dashboard';
$toast        = $_SESSION['toast'] ?? null;
$invite_link  = $_SESSION['invite_link'] ?? null;
unset($_SESSION['toast'], $_SESSION['invite_link']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root { --red:#C33643; --green:#244441; --blue:#3F82E3; --bg:#F2F2F2; --white:#FFFFFF; }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--green); display:flex; min-height:100vh; }
    h1,h2,h3 { font-family:'Playfair Display',serif; }

    /* ── SIDEBAR ── */
    .sidebar {
      width:230px; min-width:230px; background:var(--green);
      display:flex; flex-direction:column;
      position:sticky; top:0; height:100vh; overflow-y:auto;
    }
    .sidebar-logo {
      padding:1.8rem 1.5rem 1.2rem;
      font-family:'Playfair Display',serif; font-size:1.4rem; font-weight:900;
      color:#fff; border-bottom:1px solid rgba(255,255,255,0.08);
    }
    .sidebar-logo span { color:var(--red); }
    .sidebar-admin { padding:1rem 1.5rem; font-size:0.78rem; color:rgba(255,255,255,0.45); border-bottom:1px solid rgba(255,255,255,0.08); }
    .sidebar-admin strong { color:rgba(255,255,255,0.8); font-weight:600; display:block; font-size:0.88rem; }

    .nav-links { padding:1rem 0; flex:1; }
    .nav-link {
      display:flex; align-items:center; gap:0.8rem;
      padding:0.8rem 1.5rem; cursor:pointer; border:none; background:none;
      color:rgba(255,255,255,0.55); font-size:0.88rem; font-weight:500;
      width:100%; text-align:left; font-family:'DM Sans',sans-serif;
      transition:all 0.2s; border-left:3px solid transparent;
    }
    .nav-link svg { width:18px; height:18px; stroke:currentColor; flex-shrink:0; }
    .nav-link:hover { color:#fff; background:rgba(255,255,255,0.06); }
    .nav-link.active { color:#fff; background:rgba(255,255,255,0.1); border-left-color:var(--red); }

    .sidebar-logout {
      padding:1rem 1.5rem; margin-top:auto;
      border-top:1px solid rgba(255,255,255,0.08);
    }
    .logout-btn {
      display:flex; align-items:center; gap:0.6rem;
      color:rgba(255,255,255,0.45); font-size:0.82rem; text-decoration:none;
      transition:color 0.2s;
    }
    .logout-btn:hover { color:var(--red); }

    /* ── MAIN ── */
    .main { flex:1; overflow-y:auto; }
    .topbar {
      background:var(--white); padding:1rem 2rem;
      display:flex; align-items:center; justify-content:space-between;
      border-bottom:1px solid rgba(36,68,65,0.07);
      position:sticky; top:0; z-index:50;
    }
    .page-content { padding:2rem; }

    /* ── TABS ── */
    .tab-section { display:none; animation:fadeUp 0.3s ease; }
    .tab-section.active { display:block; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

    /* ── STAT CARDS ── */
    .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1.2rem; margin-bottom:2rem; }
    .stat-card {
      background:var(--white); border-radius:16px; padding:1.4rem;
      border:1px solid rgba(36,68,65,0.07); box-shadow:0 2px 10px rgba(0,0,0,0.04);
    }
    .stat-card .label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#9ab0ae; margin-bottom:0.5rem; }
    .stat-card .value { font-family:'Playfair Display',serif; font-size:2.2rem; font-weight:900; color:var(--green); line-height:1; }
    .stat-card .sub   { font-size:0.75rem; color:#9ab0ae; margin-top:0.3rem; }

    /* ── SECTION HEADER ── */
    .section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.2rem; }
    .section-header h2 { font-size:1.3rem; }
    .btn-primary {
      display:inline-flex; align-items:center; gap:0.4rem;
      background:var(--red); color:#fff; padding:0.6rem 1.3rem;
      border-radius:50px; font-size:0.85rem; font-weight:600;
      border:none; cursor:pointer; transition:all 0.25s;
      font-family:'DM Sans',sans-serif;
      box-shadow:0 4px 14px rgba(195,54,67,0.25);
    }
    .btn-primary:hover { background:#a82d38; transform:translateY(-1px); }
    .btn-sm {
      padding:0.4rem 0.9rem; border-radius:50px; font-size:0.78rem;
      font-weight:600; border:none; cursor:pointer; font-family:'DM Sans',sans-serif;
      transition:all 0.2s;
    }
    .btn-green  { background:rgba(36,68,65,0.1);  color:var(--green); }
    .btn-green:hover { background:var(--green); color:#fff; }
    .btn-red    { background:rgba(195,54,67,0.1); color:var(--red);   }
    .btn-red:hover   { background:var(--red);   color:#fff; }
    .btn-blue   { background:rgba(63,130,227,0.1);color:var(--blue);  }
    .btn-blue:hover  { background:var(--blue);  color:#fff; }

    /* ── TABLE ── */
    .table-wrap { background:var(--white); border-radius:16px; overflow:hidden; border:1px solid rgba(36,68,65,0.07); box-shadow:0 2px 10px rgba(0,0,0,0.04); }
    table { width:100%; border-collapse:collapse; }
    th { padding:0.9rem 1.2rem; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#9ab0ae; text-align:left; background:rgba(36,68,65,0.03); border-bottom:1px solid rgba(36,68,65,0.07); }
    td { padding:0.9rem 1.2rem; font-size:0.88rem; border-bottom:1px solid rgba(36,68,65,0.05); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(36,68,65,0.02); }

    /* ── DOCTOR GRID ── */
    .doctor-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1.2rem; }
    .doctor-card {
      background:var(--white); border-radius:16px; padding:1.4rem;
      border:1px solid rgba(36,68,65,0.07); box-shadow:0 2px 10px rgba(0,0,0,0.04);
      transition:transform 0.2s, box-shadow 0.2s;
    }
    .doctor-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,0.08); }
    .doc-avatar {
      width:48px; height:48px; border-radius:14px; background:var(--blue);
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:1rem; flex-shrink:0;
    }

    /* ── BADGES ── */
    .badge { display:inline-block; padding:0.22rem 0.65rem; border-radius:50px; font-size:0.7rem; font-weight:700; letter-spacing:0.04em; }
    .badge-green  { background:rgba(34,197,94,0.1);  color:#16a34a; }
    .badge-red    { background:rgba(195,54,67,0.1);  color:var(--red); }
    .badge-orange { background:rgba(245,158,11,0.1); color:#d97706; }
    .badge-blue   { background:rgba(63,130,227,0.1); color:var(--blue); }
    .badge-gray   { background:rgba(0,0,0,0.06);     color:#888; }

    /* ── MODAL ── */
    .modal-overlay {
      position:fixed; inset:0; background:rgba(0,0,0,0.45);
      display:none; align-items:center; justify-content:center;
      z-index:200; padding:1rem; backdrop-filter:blur(4px);
    }
    .modal-overlay.open { display:flex; }
    .modal {
      background:var(--white); border-radius:20px; padding:2rem;
      width:100%; max-width:500px; max-height:90vh; overflow-y:auto;
      animation:fadeUp 0.3s ease;
    }
    .modal h3 { font-size:1.3rem; margin-bottom:1.2rem; }
    .field-label { display:block; font-size:0.72rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#9ab0ae; margin-bottom:0.4rem; }
    .field-input {
      width:100%; padding:0.72rem 0.9rem; border:1.5px solid rgba(36,68,65,0.12);
      border-radius:12px; font-family:'DM Sans',sans-serif; font-size:0.9rem;
      color:var(--green); outline:none; transition:border-color 0.2s;
    }
    .field-input:focus { border-color:var(--blue); }
    select.field-input { cursor:pointer; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; }
    .form-field { margin-bottom:0.9rem; }
    .btn-submit { width:100%; padding:0.85rem; border-radius:50px; background:var(--red); color:#fff; font-weight:700; font-size:0.93rem; border:none; cursor:pointer; margin-top:0.5rem; transition:all 0.25s; font-family:'DM Sans',sans-serif; }
    .btn-submit:hover { background:#a82d38; }
    .btn-cancel { width:100%; padding:0.7rem; border-radius:50px; background:transparent; color:var(--green); font-weight:600; font-size:0.88rem; border:1.5px solid rgba(36,68,65,0.15); cursor:pointer; margin-top:0.5rem; font-family:'DM Sans',sans-serif; }

    /* ── TOAST ── */
    .toast {
      position:fixed; bottom:2rem; right:2rem; z-index:300;
      background:var(--green); color:#fff; padding:0.9rem 1.5rem;
      border-radius:14px; font-size:0.88rem; font-weight:600;
      box-shadow:0 8px 30px rgba(0,0,0,0.15);
      animation:slideIn 0.4s ease, fadeOut 0.4s 3s ease forwards;
    }
    @keyframes slideIn  { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
    @keyframes fadeOut  { from{opacity:1} to{opacity:0;pointer-events:none} }

    /* ── INVITE LINK BOX ── */
    .invite-box {
      background:rgba(63,130,227,0.08); border:1px solid rgba(63,130,227,0.2);
      border-radius:14px; padding:1rem 1.2rem; margin-top:1rem;
    }
    .invite-box p { font-size:0.78rem; color:var(--blue); font-weight:600; margin-bottom:0.5rem; }
    .invite-box code { font-size:0.75rem; word-break:break-all; color:var(--green); background:rgba(36,68,65,0.06); padding:0.4rem 0.6rem; border-radius:8px; display:block; }

    /* ── ASSIGNMENT ROW ── */
    .assign-card { background:var(--white); border-radius:16px; padding:1.3rem; border:1px solid rgba(36,68,65,0.07); margin-bottom:1rem; box-shadow:0 2px 8px rgba(0,0,0,0.03); }
    .patient-chip {
      display:inline-flex; align-items:center; gap:0.5rem;
      background:rgba(36,68,65,0.07); border-radius:50px;
      padding:0.3rem 0.7rem; font-size:0.78rem; font-weight:600; color:var(--green);
      margin:0.25rem;
    }
    .patient-chip button { background:none; border:none; cursor:pointer; color:#9ab0ae; font-size:0.85rem; line-height:1; padding:0; margin-left:0.2rem; }
    .patient-chip button:hover { color:var(--red); }

    .empty-row { text-align:center; padding:3rem; color:#9ab0ae; font-size:0.88rem; }

    @media(max-width:900px){
      .sidebar { display:none; }
      .stats-grid { grid-template-columns:repeat(2,1fr); }
    }
  </style>
</head>
<body>

<?php if($toast): ?>
<div class="toast">✓ <?= htmlspecialchars($toast) ?></div>
<?php endif; ?>

<!-- ── SIDEBAR ── -->
<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-admin">
    Admin Portal<br/>
    <strong><?= htmlspecialchars($admin_name) ?></strong>
  </div>
  <nav class="nav-links">
    <?php
    $navs = [
      ['dashboard','Dashboard','M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
      ['doctors','Doctors','M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
      ['patients','Patients','M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
      ['assignments','Assignments','M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
    ];
    foreach($navs as [$key,$label,$path]):
    ?>
    <button class="nav-link <?= $active_tab===$key?'active':'' ?>" onclick="switchTab('<?= $key ?>')">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/></svg>
      <?= $label ?>
    </button>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-logout">
    <a href="logout.php" class="logout-btn">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Log Out
    </a>
  </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Good morning, Admin</div>
      <div style="font-size:0.95rem;font-weight:700;">Here's what's happening in TELE-CARE today.</div>
    </div>
    <button class="btn-primary" onclick="openModal('modal-create-doctor')">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Add Doctor
    </button>
  </div>

  <div class="page-content">

    <!-- ══ DASHBOARD TAB ══ -->
    <div class="tab-section <?= $active_tab==='dashboard'?'active':'' ?>" id="tab-dashboard">

      <div class="stats-grid">
        <div class="stat-card">
          <div class="label">Total Doctors</div>
          <div class="value"><?= $total_doctors ?></div>
          <div class="sub">Registered</div>
        </div>
        <div class="stat-card">
          <div class="label">Total Patients</div>
          <div class="value"><?= $total_patients ?></div>
          <div class="sub">Registered</div>
        </div>
        <div class="stat-card">
          <div class="label">Active Appointments</div>
          <div class="value"><?= $active_appts ?></div>
          <div class="sub">Pending + Confirmed</div>
        </div>
        <div class="stat-card">
          <div class="label">Avg Doctor Load</div>
          <div class="value"><?= $avg_load ?></div>
          <div class="sub">Patients per doctor</div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
        <!-- Doctor Overview -->
        <div>
          <div class="section-header">
            <h2>Doctor Overview</h2>
            <a href="#" onclick="switchTab('doctors');return false;" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">View all</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Doctor</th><th>Specialty</th><th>Status</th></tr></thead>
              <tbody>
              <?php
              $dres = $conn->query("SELECT * FROM doctors ORDER BY created_at DESC LIMIT 5");
              if($dres && $dres->num_rows > 0):
                while($d = $dres->fetch_assoc()):
              ?>
              <tr>
                <td><span style="font-weight:600;">Dr. <?= htmlspecialchars($d['full_name']) ?></span></td>
                <td style="color:#9ab0ae;font-size:0.82rem;"><?= htmlspecialchars($d['specialty'] ?? '—') ?></td>
                <td>
                  <span class="badge <?= $d['status']==='active'?'badge-green':($d['status']==='pending'?'badge-orange':'badge-gray') ?>">
                    <?= ucfirst($d['status']) ?>
                  </span>
                </td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="3" class="empty-row">No doctors yet.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Unassigned Patients -->
        <div>
          <div class="section-header">
            <h2>Unassigned Patients</h2>
            <a href="#" onclick="switchTab('patients');return false;" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">View all</a>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Patient</th><th>Action</th></tr></thead>
              <tbody>
              <?php
              $ures = $conn->query("SELECT * FROM patients WHERE id NOT IN (SELECT patient_id FROM patient_doctors) LIMIT 5");
              if($ures && $ures->num_rows > 0):
                while($pt = $ures->fetch_assoc()):
              ?>
              <tr>
                <td><span style="font-weight:600;"><?= htmlspecialchars($pt['full_name']) ?></span><br/><span style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($pt['email']) ?></span></td>
                <td><button class="btn-sm btn-blue" onclick="openAssignModal(<?= $pt['id'] ?>, '<?= htmlspecialchars($pt['full_name']) ?>')">Assign</button></td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="2" class="empty-row">All patients are assigned.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ══ DOCTORS TAB ══ -->
    <div class="tab-section <?= $active_tab==='doctors'?'active':'' ?>" id="tab-doctors">
      <div class="section-header">
        <h2>Doctors</h2>
        <button class="btn-primary" onclick="openModal('modal-create-doctor')">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Add Doctor
        </button>
      </div>

      <?php if($invite_link): ?>
      <div class="invite-box" style="margin-bottom:1.5rem;">
        <p>📧 Share this invite link with the doctor (expires in 7 days):</p>
        <code id="inviteCode"><?= htmlspecialchars($invite_link) ?></code>
        <button onclick="copyInvite()" style="margin-top:0.5rem;font-size:0.75rem;color:var(--blue);background:none;border:none;cursor:pointer;font-weight:600;">Copy link</button>
      </div>
      <?php endif; ?>

      <div class="doctor-grid">
        <?php
        $dres2 = $conn->query("SELECT d.*, (SELECT COUNT(*) FROM patient_doctors WHERE doctor_id=d.id) AS patient_count FROM doctors d ORDER BY d.created_at DESC");
        if($dres2 && $dres2->num_rows > 0):
          while($d = $dres2->fetch_assoc()):
            $initials = strtoupper(substr($d['full_name'],0,1).(strpos($d['full_name'],' ')!==false?substr($d['full_name'],strpos($d['full_name'],' ')+1,1):''));
        ?>
        <div class="doctor-card">
          <div style="display:flex;align-items:flex-start;gap:0.9rem;margin-bottom:1rem;">
            <div class="doc-avatar"><?= $initials ?></div>
            <div style="flex:1;">
              <div style="font-weight:700;font-size:0.95rem;">Dr. <?= htmlspecialchars($d['full_name']) ?></div>
              <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($d['specialty'] ?? 'General') ?></div>
              <?php if($d['subspecialty']): ?><div style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($d['subspecialty']) ?></div><?php endif; ?>
            </div>
            <span class="badge <?= $d['status']==='active'?'badge-green':($d['status']==='pending'?'badge-orange':'badge-gray') ?>">
              <?= ucfirst($d['status']) ?>
            </span>
          </div>

          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
            <span class="badge badge-blue">👥 <?= $d['patient_count'] ?> patients</span>
            <span class="badge <?= $d['access_level']==='consultant'?'badge-green':($d['access_level']==='senior'?'badge-blue':'badge-gray') ?>"><?= ucfirst($d['access_level']) ?></span>
            <?php if($d['is_verified']): ?><span class="badge badge-green">✓ Verified</span><?php endif; ?>
            <?php if(!$d['setup_complete']): ?><span class="badge badge-orange">Setup pending</span><?php endif; ?>
          </div>

          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="?toggle_doctor=<?= $d['id'] ?>&tab=doctors" class="btn-sm <?= $d['status']==='active'?'btn-red':'btn-green' ?>">
              <?= $d['status']==='active'?'Deactivate':'Activate' ?>
            </a>
            <?php if(!$d['is_verified']): ?>
            <button class="btn-sm btn-blue" onclick="openVerifyModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['full_name']) ?>')">Verify</button>
            <?php endif; ?>
            <?php if(!$d['setup_complete'] && $d['invite_token']): ?>
            <button class="btn-sm btn-green" onclick="copyToken('<?= htmlspecialchars($d['invite_token']) ?>')">Copy Invite</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; else: ?>
        <div style="grid-column:1/-1;" class="table-wrap"><div class="empty-row">No doctors registered yet. Click "Add Doctor" to get started.</div></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ PATIENTS TAB ══ -->
    <div class="tab-section <?= $active_tab==='patients'?'active':'' ?>" id="tab-patients">
      <div class="section-header"><h2>Patients</h2></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Patient</th>
              <th>Contact</th>
              <th>Joined</th>
              <th>Assigned Doctor</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $pres = $conn->query("SELECT p.*, d.full_name AS doctor_name, pd.doctor_id FROM patients p LEFT JOIN patient_doctors pd ON pd.patient_id=p.id LEFT JOIN doctors d ON d.id=pd.doctor_id ORDER BY p.created_at DESC");
          if($pres && $pres->num_rows > 0):
            while($pt = $pres->fetch_assoc()):
          ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($pt['full_name']) ?></div>
              <div style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($pt['email']) ?></div>
            </td>
            <td style="font-size:0.82rem;color:#9ab0ae;"><?= htmlspecialchars($pt['phone_number']) ?></td>
            <td style="font-size:0.78rem;color:#9ab0ae;"><?= date('M d, Y', strtotime($pt['created_at'])) ?></td>
            <td>
              <?php if($pt['doctor_name']): ?>
                <span class="badge badge-green">Dr. <?= htmlspecialchars($pt['doctor_name']) ?></span>
              <?php else: ?>
                <span class="badge badge-orange">Unassigned</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if(!$pt['doctor_id']): ?>
              <button class="btn-sm btn-blue" onclick="openAssignModal(<?= $pt['id'] ?>, '<?= htmlspecialchars($pt['full_name']) ?>')">Assign</button>
              <?php else: ?>
              <a href="?unassign=1&pid=<?= $pt['id'] ?>&did=<?= $pt['doctor_id'] ?>&tab=patients" class="btn-sm btn-red" onclick="return confirm('Unassign this patient?')">Unassign</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="5" class="empty-row">No patients registered yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ ASSIGNMENTS TAB ══ -->
    <div class="tab-section <?= $active_tab==='assignments'?'active':'' ?>" id="tab-assignments">
      <div class="section-header"><h2>Assignments</h2><span style="font-size:0.82rem;color:#9ab0ae;">Doctor-centric patient roster</span></div>
      <?php
      $ares = $conn->query("SELECT * FROM doctors WHERE status='active' ORDER BY full_name");
      if($ares && $ares->num_rows > 0):
        while($doc = $ares->fetch_assoc()):
          $pts = $conn->query("SELECT p.id, p.full_name, p.email FROM patients p JOIN patient_doctors pd ON pd.patient_id=p.id WHERE pd.doctor_id={$doc['id']}");
      ?>
      <div class="assign-card">
        <div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:0.9rem;">
          <div class="doc-avatar" style="width:40px;height:40px;font-size:0.85rem;"><?= strtoupper(substr($doc['full_name'],0,2)) ?></div>
          <div style="flex:1;">
            <div style="font-weight:700;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
            <div style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($doc['specialty'] ?? '—') ?></div>
          </div>
          <span class="badge badge-blue">
            <?php $cnt = $conn->query("SELECT COUNT(*) c FROM patient_doctors WHERE doctor_id={$doc['id']}")->fetch_assoc()['c']; ?>
            <?= $cnt ?> patient<?= $cnt!=1?'s':'' ?>
          </span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">
          <?php
          if($pts && $pts->num_rows > 0):
            while($pp = $pts->fetch_assoc()):
          ?>
          <span class="patient-chip">
            <?= htmlspecialchars($pp['full_name']) ?>
            <a href="?unassign=1&pid=<?= $pp['id'] ?>&did=<?= $doc['id'] ?>&tab=assignments" onclick="return confirm('Remove patient?')" style="color:#9ab0ae;margin-left:0.2rem;text-decoration:none;">✕</a>
          </span>
          <?php endwhile; else: ?>
          <span style="font-size:0.8rem;color:#9ab0ae;">No patients assigned.</span>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; else: ?>
      <div class="table-wrap"><div class="empty-row">No active doctors yet.</div></div>
      <?php endif; ?>
    </div>

  </div><!-- end page-content -->
</div><!-- end main -->

<!-- ══ MODAL: Create Doctor ══ -->
<div class="modal-overlay" id="modal-create-doctor">
  <div class="modal">
    <h3>Add Doctor — Phase 1</h3>
    <p style="font-size:0.82rem;color:#9ab0ae;margin-bottom:1.2rem;">Fill in the doctor's details. An invite link will be generated for them to complete their profile.</p>
    <form method="POST">
      <div class="form-field">
        <label class="field-label">Full Name *</label>
        <input type="text" name="full_name" class="field-input" placeholder="e.g. Maria Santos" required/>
      </div>
      <div class="form-field">
        <label class="field-label">Email Address *</label>
        <input type="email" name="email" class="field-input" placeholder="doctor@email.com" required/>
      </div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">Specialty</label>
          <input type="text" name="specialty" class="field-input" placeholder="e.g. Cardiology"/>
        </div>
        <div class="form-field">
          <label class="field-label">Subspecialty</label>
          <input type="text" name="subspecialty" class="field-input" placeholder="Optional"/>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Access Level</label>
        <select name="access_level" class="field-input">
          <option value="junior">Junior</option>
          <option value="senior">Senior</option>
          <option value="consultant">Consultant</option>
        </select>
      </div>
      <button type="submit" name="create_doctor" class="btn-submit">Create & Generate Invite</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-create-doctor')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══ MODAL: Verify Doctor ══ -->
<div class="modal-overlay" id="modal-verify-doctor">
  <div class="modal">
    <h3>Verify Doctor — Phase 2</h3>
    <p style="font-size:0.82rem;color:#9ab0ae;margin-bottom:1.2rem;">Log the doctor's license info and upload documents. Marking as verified allows them to go live.</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="doctor_id" id="verify-doctor-id"/>
      <div class="form-field">
        <label class="field-label">Doctor</label>
        <input type="text" id="verify-doctor-name" class="field-input" disabled/>
      </div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">License Number</label>
          <input type="text" name="license_number" class="field-input" placeholder="PRC / License No."/>
        </div>
        <div class="form-field">
          <label class="field-label">Issuing Board</label>
          <input type="text" name="issuing_board" class="field-input" placeholder="e.g. PRC, PMA"/>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">License File <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(PDF/Image)</span></label>
        <input type="file" name="license_file" class="field-input" accept=".pdf,.jpg,.jpeg,.png" style="padding:0.5rem;"/>
      </div>
      <div class="form-field">
        <label class="field-label">Board Certification <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(PDF/Image)</span></label>
        <input type="file" name="board_cert_file" class="field-input" accept=".pdf,.jpg,.jpeg,.png" style="padding:0.5rem;"/>
      </div>
      <button type="submit" name="verify_doctor" class="btn-submit">Mark as Verified</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-verify-doctor')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══ MODAL: Assign Patient ══ -->
<div class="modal-overlay" id="modal-assign-patient">
  <div class="modal">
    <h3>Assign Patient to Doctor</h3>
    <form method="POST">
      <input type="hidden" name="patient_id" id="assign-patient-id"/>
      <div class="form-field">
        <label class="field-label">Patient</label>
        <input type="text" id="assign-patient-name" class="field-input" disabled/>
      </div>
      <div class="form-field">
        <label class="field-label">Assign to Doctor *</label>
        <select name="doctor_id" class="field-input" required>
          <option value="">Select a doctor</option>
          <?php
          $adocs = $conn->query("SELECT id, full_name, specialty FROM doctors WHERE status='active' ORDER BY full_name");
          if($adocs): while($ad = $adocs->fetch_assoc()): ?>
          <option value="<?= $ad['id'] ?>">Dr. <?= htmlspecialchars($ad['full_name']) ?> — <?= htmlspecialchars($ad['specialty'] ?? 'General') ?></option>
          <?php endwhile; endif; ?>
        </select>
      </div>
      <button type="submit" name="assign_patient" class="btn-submit">Assign Patient</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-assign-patient')">Cancel</button>
    </form>
  </div>
</div>

<script>
  const tabs = ['dashboard','doctors','patients','assignments'];
  function switchTab(name) {
    tabs.forEach(t => {
      document.getElementById('tab-'+t).classList.remove('active');
      document.querySelectorAll('.nav-link').forEach(n => n.classList.remove('active'));
    });
    document.getElementById('tab-'+name).classList.add('active');
    document.querySelectorAll('.nav-link').forEach(n => {
      if(n.getAttribute('onclick')?.includes("'"+name+"'")) n.classList.add('active');
    });
  }

  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }

  // Close modal on overlay click
  document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); });
  });

  function openVerifyModal(id, name) {
    document.getElementById('verify-doctor-id').value = id;
    document.getElementById('verify-doctor-name').value = 'Dr. ' + name;
    openModal('modal-verify-doctor');
  }

  function openAssignModal(id, name) {
    document.getElementById('assign-patient-id').value = id;
    document.getElementById('assign-patient-name').value = name;
    openModal('modal-assign-patient');
  }

  function copyInvite() {
    const code = document.getElementById('inviteCode')?.textContent;
    if(code) { navigator.clipboard.writeText(code); alert('Invite link copied!'); }
  }

  function copyToken(token) {
    const url = window.location.origin + '/Telecare 2.0/doctor/setup.php?token=' + token;
    navigator.clipboard.writeText(url).then(() => alert('Invite link copied!'));
  }

  // Auto-dismiss toast
  setTimeout(() => {
    const t = document.querySelector('.toast');
    if(t) t.remove();
  }, 3500);
</script>
</body>
</html>