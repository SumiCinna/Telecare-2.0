<?php
session_start();

// Prevent browser from caching this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once 'database/config.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: auth/login.php');
    exit;
}

$patient_id = $_SESSION['patient_id'];

// ── Fetch patient ──
$p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

// ── Fetch assigned doctor ──
$doc = null;
$dr = $conn->query("
    SELECT d.* FROM doctors d
    JOIN patient_doctors pd ON pd.doctor_id = d.id
    WHERE pd.patient_id = $patient_id
    LIMIT 1
");
if ($dr && $dr->num_rows > 0) $doc = $dr->fetch_assoc();

// ── Stats ──
$r = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$patient_id AND status IN ('Pending','Confirmed') AND appointment_date >= CURDATE()");
$upcoming_count = ($r && $row = $r->fetch_assoc()) ? $row['c'] : 0;

$r = $conn->query("SELECT COUNT(*) c FROM prescriptions WHERE patient_id=$patient_id AND status='Active'");
$prescription_count = ($r && $row = $r->fetch_assoc()) ? $row['c'] : 0;

$r = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$patient_id AND status='Completed'");
$completed_count = ($r && $row = $r->fetch_assoc()) ? $row['c'] : 0;

// ── Upcoming appointments (Home) ──
$upcoming = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id AND a.status IN ('Pending','Confirmed') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 3
");

// ── All appointments (Visits) ──
$visits_upcoming = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id=a.doctor_id
    WHERE a.patient_id=$patient_id AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC
");
$visits_past = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id=a.doctor_id
    WHERE a.patient_id=$patient_id AND a.appointment_date < CURDATE()
    ORDER BY a.appointment_date DESC
");

// ── Prescriptions ──
$meds = $conn->query("
    SELECT p.*, d.full_name AS doctor_name
    FROM prescriptions p JOIN doctors d ON d.id=p.doctor_id
    WHERE p.patient_id=$patient_id AND p.status='Active'
    ORDER BY p.prescribed_date DESC
");

// ── Chat messages ──
$chat_messages = null;
if ($doc) {
    $did = $doc['id'];
    $chat_messages = $conn->query("
        SELECT * FROM messages
        WHERE (sender_type='patient' AND sender_id=$patient_id AND receiver_id=$did)
           OR (sender_type='doctor'  AND sender_id=$did AND receiver_id=$patient_id)
        ORDER BY sent_at ASC
    ");
    // Mark as read
    $conn->query("UPDATE messages SET is_read=1 WHERE sender_type='doctor' AND sender_id=$did AND receiver_id=$patient_id AND is_read=0");
}

// ── Handle send message ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $doc) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message) VALUES ('patient',?,  'doctor',?, ?)");
        $stmt->bind_param("iis", $patient_id, $doc['id'], $msg);
        $stmt->execute();
    }
    header('Location: dashboard.php?tab=chat');
    exit;
}

// ── Handle profile update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fields = ['phone_number','home_address','city','country_region','insurance_provider','insurance_policy_no','allergies','current_medications','medical_condition','emergency_name','emergency_relationship','emergency_number'];
    $sets = []; $vals = []; $types = '';
    foreach ($fields as $f) {
        $sets[] = "$f=?"; $vals[] = trim($_POST[$f] ?? ''); $types .= 's';
    }
    // Handle profile photo upload
    if (!empty($_FILES['profile_photo']['name'])) {
        $dir = 'uploads/profiles/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext   = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $fname = uniqid('patient_') . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $fname)) {
            $sets[]  = "profile_photo=?";
            $vals[]  = $dir . $fname;
            $types  .= 's';
        }
    }
    $vals[] = $patient_id; $types .= 'i';
    $stmt = $conn->prepare("UPDATE patients SET ".implode(',',$sets)." WHERE id=?");
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    header('Location: dashboard.php?tab=profile&saved=1');
    exit;
}

$active_tab = $_GET['tab'] ?? 'home';
$initials = strtoupper(substr($p['full_name'], 0, 1) . (strpos($p['full_name'],' ')!==false ? substr($p['full_name'], strpos($p['full_name'],' ')+1, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --red:#C33643; --green:#244441; --blue:#3F82E3; --blue-dark:#2563C4;
      --blue-light:#EBF2FD; --bg:#EEF3FB; --white:#FFFFFF;
      --text:#1a2f5e; --muted:#8fa3c8;
    }
    * { box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); margin:0; padding-bottom:82px; }
    h1,h2,h3 { font-family:'Playfair Display',serif; }

    /* ── BOTTOM NAV ── */
    .bottom-nav {
      position:fixed; bottom:0; left:0; right:0; z-index:100;
      background:var(--white);
      border-top:1px solid rgba(0,0,0,0.06);
      display:flex; height:76px;
      box-shadow:0 -4px 20px rgba(0,0,0,0.07);
    }
    .nav-item {
      flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
      gap:3px; cursor:pointer; border:none; background:none;
      color:#aab8cc; font-size:0.58rem; font-weight:700; letter-spacing:0.05em;
      text-transform:uppercase; transition:all 0.2s; text-decoration:none;
      font-family:'DM Sans',sans-serif; position:relative;
    }
    .nav-icon {
      width:38px; height:32px; border-radius:20px;
      display:flex; align-items:center; justify-content:center;
      transition:all 0.25s;
    }
    .nav-item svg { width:20px; height:20px; fill:none; stroke-width:2; transition:all 0.2s; }

    /* individual icon colors */
    .nav-item[data-tab="home"]    svg { stroke:#5b8ff9; }
    .nav-item[data-tab="visits"]  svg { stroke:#3F82E3; }
    .nav-item[data-tab="meds"]    svg { stroke:#f4845f; }
    .nav-item[data-tab="chat"]    svg { stroke:#7ecad6; }
    .nav-item[data-tab="profile"] svg { stroke:#244441; }

    /* active pill */
    .nav-item[data-tab="home"].active    .nav-icon { background:rgba(91,143,249,0.13); }
    .nav-item[data-tab="visits"].active  .nav-icon { background:rgba(63,130,227,0.12); }
    .nav-item[data-tab="meds"].active    .nav-icon { background:rgba(244,132,95,0.12); }
    .nav-item[data-tab="chat"].active    .nav-icon { background:rgba(126,202,214,0.15); }
    .nav-item[data-tab="profile"].active .nav-icon { background:rgba(36,68,65,0.1); }

    /* active label colors */
    .nav-item[data-tab="home"].active    { color:#5b8ff9; }
    .nav-item[data-tab="visits"].active  { color:#3F82E3; }
    .nav-item[data-tab="meds"].active    { color:#f4845f; }
    .nav-item[data-tab="chat"].active    { color:#7ecad6; }
    .nav-item[data-tab="profile"].active { color:#244441; }

    .nav-item:hover .nav-icon { background:rgba(0,0,0,0.04); }

    /* ── TOP HEADER ── */
    .top-header {
      background:var(--white); padding:1rem 1.5rem;
      display:flex; align-items:center; justify-content:space-between;
      border-bottom:1px solid rgba(63,130,227,0.08);
      position:sticky; top:0; z-index:50;
      box-shadow:0 2px 12px rgba(63,130,227,0.07);
    }
    .avatar-circle {
      width:42px; height:42px; border-radius:50%;
      background:linear-gradient(135deg,var(--blue),var(--blue-dark));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:0.9rem; flex-shrink:0;
    }

    /* ── SECTIONS ── */
    .tab-section { display:none; padding:1.4rem; max-width:680px; margin:0 auto; }
    .tab-section.active { display:block; animation:fadeUp 0.35s ease; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

    /* ── CARDS ── */
    .card {
      background:var(--white); border-radius:18px; padding:1.4rem;
      margin-bottom:1.2rem; border:1px solid rgba(63,130,227,0.08);
      box-shadow:0 2px 14px rgba(63,130,227,0.07);
    }

    /* ── WELCOME BANNER ── */
    .welcome-banner {
      background:linear-gradient(135deg,var(--blue) 0%,var(--blue-dark) 100%);
      border-radius:20px; padding:1.6rem; margin-bottom:1.2rem;
      position:relative; overflow:hidden;
    }
    .welcome-banner::before {
      content:''; position:absolute; inset:0;
      background-image:radial-gradient(circle at 80% 20%, rgba(255,255,255,0.15) 0%, transparent 50%),
                       radial-gradient(circle at 20% 80%, rgba(255,255,255,0.08) 0%, transparent 40%);
    }
    .welcome-banner::after {
      content:''; position:absolute;
      right:-30px; top:-30px; width:160px; height:160px;
      border-radius:50%; background:rgba(255,255,255,0.07);
    }
    .welcome-banner h2 { font-size:1.5rem; color:#fff; margin-bottom:0.3rem; position:relative; z-index:1; }
    .welcome-banner p  { color:rgba(255,255,255,0.75); font-size:0.85rem; position:relative; z-index:1; margin-bottom:1.2rem; }
    .welcome-banner .btn-book {
      display:inline-flex; align-items:center; gap:0.4rem;
      background:rgba(255,255,255,0.2); color:#fff;
      backdrop-filter:blur(8px);
      border:1px solid rgba(255,255,255,0.3);
      padding:0.55rem 1.2rem; border-radius:50px;
      font-size:0.82rem; font-weight:600; text-decoration:none;
      position:relative; z-index:1; transition:background 0.25s;
    }
    .welcome-banner .btn-book:hover { background:rgba(255,255,255,0.3); }

    /* ── STAT PILLS ── */
    .stats-row { display:grid; grid-template-columns:repeat(3,1fr); gap:0.8rem; margin-bottom:1.2rem; }
    .stat-pill {
      background:var(--white); border-radius:16px; padding:1rem 0.8rem; text-align:center;
      border:1px solid rgba(63,130,227,0.08);
      box-shadow:0 2px 10px rgba(63,130,227,0.06);
    }
    .stat-pill .num { font-family:'Playfair Display',serif; font-size:1.8rem; font-weight:900; color:var(--blue); line-height:1; }
    .stat-pill .lbl { font-size:0.68rem; color:var(--muted); margin-top:0.3rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; }

    /* ── DOCTOR CARD ── */
    .doctor-avatar {
      width:48px; height:48px; border-radius:12px; flex-shrink:0;
      background:linear-gradient(135deg,var(--blue),var(--blue-dark));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:1rem;
    }

    /* ── BADGES ── */
    .badge { display:inline-block; padding:0.25rem 0.7rem; border-radius:50px; font-size:0.7rem; font-weight:700; letter-spacing:0.04em; }
    .badge-green  { background:rgba(34,197,94,0.1);   color:#16a34a; }
    .badge-red    { background:rgba(195,54,67,0.1);   color:var(--red); }
    .badge-orange { background:rgba(245,158,11,0.1);  color:#d97706; }
    .badge-blue   { background:var(--blue-light);     color:var(--blue); }
    .badge-gray   { background:rgba(0,0,0,0.06);      color:#888; }

    /* ── APPOINTMENT ITEM ── */
    .appt-item {
      display:flex; align-items:center; gap:1rem;
      padding:1rem 0; border-bottom:1px solid rgba(63,130,227,0.07);
    }
    .appt-item:last-child { border-bottom:none; padding-bottom:0; }
    .appt-date-box {
      width:48px; min-width:48px; height:52px; border-radius:12px;
      background:var(--blue-light); display:flex; flex-direction:column;
      align-items:center; justify-content:center; text-align:center;
    }
    .appt-date-box .day  { font-size:1.1rem; font-weight:900; color:var(--blue); line-height:1; }
    .appt-date-box .mon  { font-size:0.62rem; font-weight:700; color:var(--muted); text-transform:uppercase; }

    /* ── MED ICON ── */
    .med-icon {
      width:44px; height:44px; border-radius:12px; flex-shrink:0;
      background:var(--blue-light); color:var(--blue);
      display:flex; align-items:center; justify-content:center; font-size:1.3rem;
    }

    /* ── CHAT ── */
    .chat-wrap {
      display:flex; flex-direction:column; gap:0.8rem;
      max-height:55vh; overflow-y:auto; padding:0.5rem 0 1rem;
    }
    .bubble {
      max-width:75%; padding:0.75rem 1rem; border-radius:18px;
      font-size:0.88rem; line-height:1.5;
    }
    .bubble.me {
      background:var(--blue); color:#fff;
      border-bottom-right-radius:4px; align-self:flex-end;
    }
    .bubble.them {
      background:var(--blue-light); color:var(--text);
      border-bottom-left-radius:4px; align-self:flex-start;
    }
    .bubble-time { font-size:0.66rem; margin-top:0.2rem; }
    .bubble.me .bubble-time   { color:rgba(255,255,255,0.55); text-align:right; }
    .bubble.them .bubble-time { color:var(--muted); text-align:left; }
    .chat-input-row {
      display:flex; gap:0.7rem; align-items:center;
      background:var(--white); border:1.5px solid rgba(63,130,227,0.15);
      border-radius:50px; padding:0.5rem 0.5rem 0.5rem 1.2rem;
    }
    .chat-input-row input {
      flex:1; border:none; outline:none; font-family:'DM Sans',sans-serif;
      font-size:0.9rem; color:var(--text); background:transparent;
    }
    .chat-send {
      width:38px; height:38px; border-radius:50%;
      background:var(--blue); border:none; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      flex-shrink:0; transition:background 0.2s;
    }
    .chat-send:hover { background:var(--blue-dark); }

    /* ── PROFILE FORM ── */
    .field-label {
      display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.06em;
      text-transform:uppercase; color:var(--muted); margin-bottom:0.4rem;
    }
    .field-input {
      width:100%; padding:0.72rem 0.9rem; border:1.5px solid rgba(63,130,227,0.15);
      border-radius:12px; font-family:'DM Sans',sans-serif; font-size:0.9rem;
      color:var(--text); background:var(--white); outline:none;
      transition:border-color 0.2s;
    }
    .field-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(63,130,227,0.1); }
    textarea.field-input { resize:vertical; min-height:70px; }
    select.field-input { cursor:pointer; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; }

    .btn-save {
      width:100%; padding:0.85rem; border-radius:50px;
      background:var(--blue); color:#fff; font-weight:700;
      font-size:0.93rem; border:none; cursor:pointer;
      transition:all 0.3s; box-shadow:0 6px 18px rgba(63,130,227,0.3);
    }
    .btn-save:hover { background:var(--blue-dark); transform:translateY(-2px); }

    .section-label {
      font-size:0.7rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;
      color:var(--muted); border-bottom:1px solid rgba(63,130,227,0.1);
      padding-bottom:0.5rem; margin:1.4rem 0 1rem;
    }
    .alert-success {
      background:rgba(63,130,227,0.08); border:1px solid rgba(63,130,227,0.2);
      color:var(--blue); border-radius:12px; padding:0.75rem 1rem;
      font-size:0.86rem; margin-bottom:1rem;
    }
    .empty-state { text-align:center; padding:3rem 1rem; color:var(--muted); font-size:0.86rem; }
    .empty-state svg { width:44px; height:44px; stroke:#c5d5ef; margin:0 auto 1rem; display:block; }

    /* visits inner tabs */
    .inner-tabs { display:flex; gap:0.5rem; margin-bottom:1.2rem; }
    .inner-tab {
      flex:1; padding:0.6rem; border-radius:50px;
      border:1.5px solid rgba(63,130,227,0.15);
      background:transparent; cursor:pointer; font-family:'DM Sans',sans-serif;
      font-size:0.82rem; font-weight:600; color:var(--muted); transition:all 0.2s;
    }
    .inner-tab.active { background:var(--blue); color:#fff; border-color:var(--blue); }

    /* profile photo upload */
    .photo-wrap { position:relative; width:88px; height:88px; margin:0 auto 0.8rem; cursor:pointer; }
    .photo-wrap img,
    .photo-wrap .avatar-lg {
      width:88px; height:88px; border-radius:50%; object-fit:cover;
      border:3px solid rgba(63,130,227,0.2);
    }
    .avatar-lg {
      background:linear-gradient(135deg,var(--blue),var(--blue-dark));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-size:1.6rem; font-weight:700; font-family:'Playfair Display',serif;
    }
    .photo-overlay {
      position:absolute; inset:0; border-radius:50%;
      background:rgba(0,0,0,0.45); display:flex; align-items:center; justify-content:center;
      opacity:0; transition:opacity 0.2s; color:#fff; font-size:0.7rem; font-weight:700;
      flex-direction:column; gap:0.2rem;
    }
    .photo-wrap:hover .photo-overlay { opacity:1; }
    .photo-overlay svg { width:18px; height:18px; }

    .logout-btn {
      display:flex; align-items:center; gap:0.5rem;
      background:rgba(195,54,67,0.07); color:var(--red);
      border:1px solid rgba(195,54,67,0.15); border-radius:12px;
      padding:0.75rem 1rem; font-size:0.86rem; font-weight:600;
      cursor:pointer; font-family:'DM Sans',sans-serif;
      width:100%; justify-content:center; margin-top:1rem;
      text-decoration:none; transition:background 0.2s;
    }
    .logout-btn:hover { background:rgba(195,54,67,0.14); }

    .card-label {
      font-size:0.68rem; font-weight:700; text-transform:uppercase;
      letter-spacing:0.1em; color:var(--muted); margin-bottom:0.8rem;
    }
  </style>
</head>
<body>

<!-- ── TOP HEADER ── -->
<div class="top-header">
  <div style="display:flex;align-items:center;gap:0.8rem;">
    <?php if($p['profile_photo']): ?>
      <img src="<?= htmlspecialchars($p['profile_photo']) ?>" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid rgba(63,130,227,0.2);"/>
    <?php else: ?>
      <div class="avatar-circle"><?= $initials ?></div>
    <?php endif; ?>
    <div>
      <div style="font-weight:700;font-size:0.95rem;color:var(--text);"><?= htmlspecialchars($p['full_name']) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($p['email']) ?></div>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:0.5rem;">
    <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
    <span style="font-size:0.75rem;color:#22c55e;font-weight:700;">Active</span>
  </div>
</div>

<!-- ══════════════════════════════════════
     HOME TAB
═══════════════════════════════════════ -->
<div class="tab-section <?= $active_tab==='home'?'active':'' ?>" id="tab-home">

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <h2>Welcome back,<br/><?= htmlspecialchars(explode(' ',$p['full_name'])[0]) ?>.</h2>
    <p>
      <?php if($doc): ?>
        Your health is being looked after by Dr. <?= htmlspecialchars(explode(' ',$doc['full_name'])[count(explode(' ',$doc['full_name']))-1]) ?>.
      <?php else: ?>
        No doctor assigned yet. Contact your admin.
      <?php endif; ?>
    </p>
    <a href="?tab=visits" class="btn-book" onclick="switchTab('visits');return false;">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Book Appointment
    </a>
  </div>

  <!-- Quick Stats -->
  <div class="stats-row">
    <div class="stat-pill">
      <div class="num"><?= $upcoming_count ?></div>
      <div class="lbl">Upcoming</div>
    </div>
    <div class="stat-pill">
      <div class="num"><?= $prescription_count ?></div>
      <div class="lbl">Prescriptions</div>
    </div>
    <div class="stat-pill">
      <div class="num"><?= $completed_count ?></div>
      <div class="lbl">Consultations</div>
    </div>
  </div>

  <!-- Assigned Doctor -->
  <?php if($doc): ?>
  <div class="card">
    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#9ab0ae;margin-bottom:1rem;">My Doctor</div>
    <div style="display:flex;align-items:center;gap:1rem;">
      <div class="doctor-avatar">
        <?php if($doc['profile_photo']): ?>
          <img src="<?= htmlspecialchars($doc['profile_photo']) ?>" style="width:100%;height:100%;border-radius:12px;object-fit:cover;"/>
        <?php else: ?>
          <?= strtoupper(substr($doc['full_name'],0,2)) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:1rem;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
        <div style="font-size:0.82rem;color:#9ab0ae;"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
        <?php if($doc['clinic_name']): ?>
        <div style="font-size:0.78rem;color:#9ab0ae;margin-top:0.2rem;">📍 <?= htmlspecialchars($doc['clinic_name']) ?></div>
        <?php endif; ?>
      </div>
      <?php if($doc['rating'] > 0): ?>
      <div style="text-align:center;">
        <div style="font-weight:700;color:var(--green);">⭐ <?= number_format($doc['rating'],1) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:0.7rem;margin-top:1.2rem;">
      <a href="?tab=chat" onclick="switchTab('chat');return false;" style="flex:1;text-align:center;padding:0.6rem;border-radius:12px;background:var(--green);color:#fff;font-size:0.85rem;font-weight:600;text-decoration:none;">Message</a>
      <button onclick="switchTab('visits')" style="flex:1;padding:0.6rem;border-radius:12px;border:1.5px solid rgba(36,68,65,0.15);background:transparent;font-size:0.85rem;font-weight:600;color:var(--green);cursor:pointer;">Book Visit</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Upcoming Appointments -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#9ab0ae;">Upcoming Appointments</div>
      <a href="?tab=visits" onclick="switchTab('visits');return false;" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">See all</a>
    </div>
    <?php
    $has_upcoming = false;
    if($upcoming && $upcoming->num_rows > 0):
      while($a = $upcoming->fetch_assoc()):
        $has_upcoming = true;
        $d = new DateTime($a['appointment_date']);
    ?>
    <div class="appt-item">
      <div class="appt-date-box">
        <div class="day"><?= $d->format('d') ?></div>
        <div class="mon"><?= $d->format('M') ?></div>
      </div>
      <div style="flex:1;">
        <div style="font-weight:600;font-size:0.92rem;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
        <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
      </div>
      <span class="badge <?= $a['status']==='Confirmed'?'badge-green':($a['status']==='Pending'?'badge-orange':'badge-red') ?>">
        <?= $a['status'] ?>
      </span>
    </div>
    <?php endwhile; endif; ?>
    <?php if(!$has_upcoming): ?>
    <div class="empty-state" style="padding:1.5rem;">
      <div style="font-size:0.88rem;">No upcoming appointments.</div>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- ══════════════════════════════════════
     VISITS TAB
═══════════════════════════════════════ -->
<div class="tab-section <?= $active_tab==='visits'?'active':'' ?>" id="tab-visits">
  <h2 style="font-size:1.5rem;margin-bottom:1.2rem;">My Appointments</h2>

  <div class="inner-tabs">
    <button class="inner-tab active" id="vtab-upcoming" onclick="switchVisits('upcoming')">Upcoming</button>
    <button class="inner-tab"        id="vtab-past"     onclick="switchVisits('past')">Past</button>
  </div>

  <!-- Upcoming visits -->
  <div id="visits-upcoming">
    <div class="card" style="padding:0.5rem 1.4rem;">
      <?php
      $hu = false;
      if($visits_upcoming && $visits_upcoming->num_rows > 0):
        while($a = $visits_upcoming->fetch_assoc()):
          $hu = true;
          $d = new DateTime($a['appointment_date']);
      ?>
      <div class="appt-item">
        <div class="appt-date-box">
          <div class="day"><?= $d->format('d') ?></div>
          <div class="mon"><?= $d->format('M') ?></div>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:0.92rem;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($a['specialty'] ?? '') ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
          <?php if($a['notes']): ?><div style="font-size:0.78rem;color:#9ab0ae;margin-top:0.2rem;">📝 <?= htmlspecialchars($a['notes']) ?></div><?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
          <span class="badge <?= $a['status']==='Confirmed'?'badge-green':($a['status']==='Pending'?'badge-orange':'badge-red') ?>"><?= $a['status'] ?></span>
          <span class="badge <?= $a['payment_status']==='Paid'?'badge-green':'badge-red' ?>"><?= $a['payment_status'] ?></span>
        </div>
      </div>
      <?php endwhile; endif; ?>
      <?php if(!$hu): ?>
      <div class="empty-state"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>No upcoming appointments.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Past visits -->
  <div id="visits-past" style="display:none;">
    <div class="card" style="padding:0.5rem 1.4rem;">
      <?php
      $hp = false;
      if($visits_past && $visits_past->num_rows > 0):
        while($a = $visits_past->fetch_assoc()):
          $hp = true;
          $d = new DateTime($a['appointment_date']);
      ?>
      <div class="appt-item">
        <div class="appt-date-box" style="background:rgba(36,68,65,0.04);">
          <div class="day" style="color:#9ab0ae;"><?= $d->format('d') ?></div>
          <div class="mon"><?= $d->format('M') ?></div>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:0.92rem;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
        </div>
        <span class="badge <?= $a['status']==='Completed'?'badge-green':'badge-red' ?>"><?= $a['status'] ?></span>
      </div>
      <?php endwhile; endif; ?>
      <?php if(!$hp): ?><div class="empty-state"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>No past appointments.</div><?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════
     MEDS TAB
═══════════════════════════════════════ -->
<div class="tab-section <?= $active_tab==='meds'?'active':'' ?>" id="tab-meds">
  <h2 style="font-size:1.5rem;margin-bottom:1.2rem;">My Prescriptions</h2>

  <?php
  $hm = false;
  if($meds && $meds->num_rows > 0):
    while($m = $meds->fetch_assoc()):
      $hm = true;
  ?>
  <div class="card">
    <div style="display:flex;align-items:flex-start;gap:1rem;">
      <div class="med-icon">💊</div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:1rem;margin-bottom:0.3rem;"><?= htmlspecialchars($m['medication_name']) ?></div>
        <div style="font-size:0.82rem;color:#9ab0ae;margin-bottom:0.5rem;">
          <?= htmlspecialchars($m['dosage'] ?? '—') ?> &nbsp;·&nbsp; <?= htmlspecialchars($m['frequency'] ?? '—') ?>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
          <span class="badge badge-blue">Refills: <?= $m['refills_remaining'] ?></span>
          <?php if($m['expiry_date']): ?>
          <span class="badge badge-orange">Expires: <?= date('M d, Y', strtotime($m['expiry_date'])) ?></span>
          <?php endif; ?>
        </div>
        <?php if($m['notes']): ?>
        <div style="margin-top:0.7rem;font-size:0.82rem;color:#6b8a87;background:rgba(36,68,65,0.05);border-radius:10px;padding:0.6rem 0.8rem;">
          📝 <?= htmlspecialchars($m['notes']) ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:0.7rem;font-size:0.75rem;color:#9ab0ae;">
          Prescribed by Dr. <?= htmlspecialchars($m['doctor_name']) ?> on <?= date('M d, Y', strtotime($m['prescribed_date'])) ?>
        </div>
      </div>
    </div>
    <?php if($m['refills_remaining'] == 0): ?>
    <div style="margin-top:0.9rem;padding-top:0.9rem;border-top:1px solid rgba(36,68,65,0.08);font-size:0.82rem;color:var(--red);">
      ⚠️ No refills remaining — message your doctor to request more.
    </div>
    <?php endif; ?>
  </div>
  <?php endwhile; endif; ?>
  <?php if(!$hm): ?>
  <div class="card">
    <div class="empty-state">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
      No active prescriptions.
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════
     CHAT TAB
═══════════════════════════════════════ -->
<div class="tab-section <?= $active_tab==='chat'?'active':'' ?>" id="tab-chat">
  <?php if($doc): ?>
  <!-- Doctor info strip -->
  <div style="display:flex;align-items:center;gap:0.9rem;margin-bottom:1.2rem;">
    <div class="doctor-avatar" style="width:44px;height:44px;"><?= strtoupper(substr($doc['full_name'],0,2)) ?></div>
    <div>
      <div style="font-weight:700;font-size:0.95rem;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
      <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
    </div>
    <span style="margin-left:auto;" class="badge badge-green">
      <?= $doc['is_available'] ? '● Online' : '○ Offline' ?>
    </span>
  </div>

  <!-- Messages -->
  <div class="card" style="padding:1rem;">
    <div class="chat-wrap" id="chatWrap">
      <?php
      $hc = false;
      if($chat_messages && $chat_messages->num_rows > 0):
        while($msg = $chat_messages->fetch_assoc()):
          $hc = true;
          $is_me = ($msg['sender_type'] === 'patient');
      ?>
      <div style="display:flex;flex-direction:column;align-items:<?= $is_me?'flex-end':'flex-start' ?>;">
        <div class="bubble <?= $is_me?'me':'them' ?>">
          <?= nl2br(htmlspecialchars($msg['message'])) ?>
          <div class="bubble-time"><?= date('g:i A', strtotime($msg['sent_at'])) ?></div>
        </div>
      </div>
      <?php endwhile; endif; ?>
      <?php if(!$hc): ?>
      <div class="empty-state" style="padding:2rem;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:40px;height:40px;stroke:#c8d8d6;margin:0 auto 0.8rem;display:block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <div style="font-size:0.85rem;">No messages yet. Say hi to your doctor!</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Send -->
  <form method="POST">
    <div class="chat-input-row">
      <input type="text" name="message" placeholder="Type a message..." autocomplete="off" required/>
      <button type="submit" name="send_message" class="chat-send">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
      </button>
    </div>
  </form>

  <?php else: ?>
  <div class="card"><div class="empty-state">No doctor assigned yet. You'll be able to chat once a doctor is assigned to you.</div></div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════
     PROFILE TAB
═══════════════════════════════════════ -->
<div class="tab-section <?= $active_tab==='profile'?'active':'' ?>" id="tab-profile">

  <?php if(isset($_GET['saved'])): ?>
  <div class="alert-success">✓ Profile updated successfully.</div>
  <?php endif; ?>

  <!-- Profile header -->
  <div class="card" style="text-align:center;padding:2rem;">
    <form method="POST" enctype="multipart/form-data" id="photoForm">
      <div class="photo-wrap" onclick="document.getElementById('photoInput').click()">
        <?php if($p['profile_photo']): ?>
          <img src="<?= htmlspecialchars($p['profile_photo']) ?>" alt="Profile photo"/>
        <?php else: ?>
          <div class="avatar-lg"><?= $initials ?></div>
        <?php endif; ?>
        <div class="photo-overlay">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0"/></svg>
          Change
        </div>
      </div>
      <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="this.form.submit()"/>
      <input type="hidden" name="update_profile"/>
      <!-- carry over all other fields as hidden so the photo-only submit doesn't blank them -->
      <?php foreach(['phone_number','home_address','city','country_region','insurance_provider','insurance_policy_no','allergies','current_medications','medical_condition','emergency_name','emergency_relationship','emergency_number'] as $hf): ?>
      <input type="hidden" name="<?= $hf ?>" value="<?= htmlspecialchars($p[$hf] ?? '') ?>"/>
      <?php endforeach; ?>
    </form>
    <div style="font-weight:700;font-size:1.1rem;color:var(--text);"><?= htmlspecialchars($p['full_name']) ?></div>
    <div style="font-size:0.82rem;color:var(--muted);margin-top:0.2rem;"><?= htmlspecialchars($p['email']) ?></div>
    <div style="display:flex;justify-content:center;gap:0.5rem;margin-top:0.8rem;">
      <span class="badge badge-blue"><?= htmlspecialchars($p['gender']) ?></span>
      <?php if($p['preferred_language']): ?><span class="badge" style="background:var(--blue-light);color:var(--blue);"><?= htmlspecialchars($p['preferred_language']) ?></span><?php endif; ?>
    </div>
    <div style="font-size:0.72rem;color:var(--muted);margin-top:0.6rem;">Tap photo to change</div>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <!-- Contact -->
    <div class="section-label">Contact Information</div>
    <div class="card" style="display:flex;flex-direction:column;gap:0.9rem;">
      <div>
        <label class="field-label">Phone Number</label>
        <input type="tel" name="phone_number" class="field-input" value="<?= htmlspecialchars($p['phone_number']) ?>"/>
      </div>
      <div>
        <label class="field-label">Home Address</label>
        <input type="text" name="home_address" class="field-input" value="<?= htmlspecialchars($p['home_address'] ?? '') ?>"/>
      </div>
      <div class="grid-2">
        <div>
          <label class="field-label">City</label>
          <input type="text" name="city" class="field-input" value="<?= htmlspecialchars($p['city'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Country</label>
          <input type="text" name="country_region" class="field-input" value="<?= htmlspecialchars($p['country_region'] ?? '') ?>"/>
        </div>
      </div>
    </div>

    <!-- Medical -->
    <div class="section-label">Medical Details</div>
    <div class="card" style="display:flex;flex-direction:column;gap:0.9rem;">
      <div>
        <label class="field-label">Known Allergies</label>
        <textarea name="allergies" class="field-input"><?= htmlspecialchars($p['allergies'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="field-label">Current Medications</label>
        <textarea name="current_medications" class="field-input"><?= htmlspecialchars($p['current_medications'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="field-label">Medical Condition</label>
        <textarea name="medical_condition" class="field-input"><?= htmlspecialchars($p['medical_condition'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- Insurance -->
    <div class="section-label">Health Insurance</div>
    <div class="card">
      <div class="grid-2">
        <div>
          <label class="field-label">Provider</label>
          <input type="text" name="insurance_provider" class="field-input" value="<?= htmlspecialchars($p['insurance_provider'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Policy No.</label>
          <input type="text" name="insurance_policy_no" class="field-input" value="<?= htmlspecialchars($p['insurance_policy_no'] ?? '') ?>"/>
        </div>
      </div>
    </div>

    <!-- Emergency Contact -->
    <div class="section-label">Emergency Contact</div>
    <div class="card" style="display:flex;flex-direction:column;gap:0.9rem;">
      <div>
        <label class="field-label">Name</label>
        <input type="text" name="emergency_name" class="field-input" value="<?= htmlspecialchars($p['emergency_name'] ?? '') ?>"/>
      </div>
      <div class="grid-2">
        <div>
          <label class="field-label">Relationship</label>
          <input type="text" name="emergency_relationship" class="field-input" value="<?= htmlspecialchars($p['emergency_relationship'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Number</label>
          <input type="tel" name="emergency_number" class="field-input" value="<?= htmlspecialchars($p['emergency_number'] ?? '') ?>"/>
        </div>
      </div>
    </div>

    <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
  </form>

  <a href="auth/logout.php" class="logout-btn">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
    Log Out
  </a>
  <div style="height:0.5rem;"></div>
</div>

<!-- ══ BOTTOM NAV ══ -->
<nav class="bottom-nav">
  <button class="nav-item <?= $active_tab==='home'?'active':'' ?>" data-tab="home" onclick="switchTab('home')" id="nav-home">
    <div class="nav-icon"><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg></div>
    Home
  </button>
  <button class="nav-item <?= $active_tab==='visits'?'active':'' ?>" data-tab="visits" onclick="switchTab('visits')" id="nav-visits">
    <div class="nav-icon"><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
    Visits
  </button>
  <button class="nav-item <?= $active_tab==='meds'?'active':'' ?>" data-tab="meds" onclick="switchTab('meds')" id="nav-meds">
    <div class="nav-icon"><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg></div>
    Meds
  </button>
  <button class="nav-item <?= $active_tab==='chat'?'active':'' ?>" data-tab="chat" onclick="switchTab('chat')" id="nav-chat">
    <div class="nav-icon"><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></div>
    Chat
  </button>
  <button class="nav-item <?= $active_tab==='profile'?'active':'' ?>" data-tab="profile" onclick="switchTab('profile')" id="nav-profile">
    <div class="nav-icon"><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div>
    Profile
  </button>
</nav>

<script>
  const tabs = ['home','visits','meds','chat','profile'];

  function switchTab(name) {
    tabs.forEach(t => {
      document.getElementById('tab-'+t).classList.remove('active');
      document.getElementById('nav-'+t).classList.remove('active');
    });
    document.getElementById('tab-'+name).classList.add('active');
    document.getElementById('nav-'+name).classList.add('active');
    window.scrollTo({top:0, behavior:'smooth'});
    if (name === 'chat') scrollChat();
  }

  function switchVisits(type) {
    document.getElementById('visits-upcoming').style.display = type==='upcoming'?'block':'none';
    document.getElementById('visits-past').style.display     = type==='past'?'block':'none';
    document.getElementById('vtab-upcoming').classList.toggle('active', type==='upcoming');
    document.getElementById('vtab-past').classList.toggle('active',     type==='past');
  }

  function scrollChat() {
    const cw = document.getElementById('chatWrap');
    if(cw) setTimeout(()=>{ cw.scrollTop = cw.scrollHeight; }, 100);
  }

  // Scroll chat to bottom on load if active
  <?php if($active_tab==='chat'): ?>scrollChat();<?php endif; ?>
</script>
</body>
</html>