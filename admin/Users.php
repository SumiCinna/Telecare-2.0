<?php
// admin/Users.php
if (session_status() !== PHP_SESSION_ACTIVE) {    session_start();}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';
require_once __DIR__ . '/../private_telecare/booking/booking_helpers.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];

// ── Ensure audit_logs table exists (fallback if the SQL file was not run) ──
$conn->query("CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` int NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Audit logging helper ──
function log_audit($conn, $admin_id, $action, $entity_type, $entity_id, $old = null, $new = null) {
    $old_json = $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES);
    $new_json = $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES);
    $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $admin_id, $action, $entity_type, $entity_id, $old_json, $new_json);
    $stmt->execute();
}

/* ══════════════════════════════════════════════
   ACTIONS
   ══════════════════════════════════════════════ */

// ── Create doctor (simple account — full profile filled in by the doctor later) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_doctor'])) {
        $fn   = trim($_POST['full_name'] ?? '');
    $em   = trim($_POST['email'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $spec = trim($_POST['specialty'] ?? '');
    $sub  = trim($_POST['subspecialty'] ?? '');

    if (!$fn || !$em || !array_key_exists($dept, BOOKING_DEPARTMENTS)) {
        $_SESSION['toast_error'] = 'Full name, email, and a valid department are required.';
        header('Location: users.php'); exit;
    }

    $chk = $conn->prepare("SELECT id FROM doctors WHERE email=?");
    $chk->bind_param("s", $em);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $_SESSION['toast_error'] = 'A doctor with that email already exists.';
        header('Location: users.php'); exit;
    }

    // No password is set yet — the doctor authenticates via the emailed setup
    // link (token below), not by typing a password anywhere. A random,
    // never-shared placeholder hash just satisfies the NOT NULL column.
    $placeholder = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

        $stmt = $conn->prepare(
        "INSERT INTO doctors (full_name, email, department, specialty, subspecialty, password, invite_token, invite_expires, status, is_available, setup_complete)
         VALUES (?,?,?,?,?,?,?,?,'active',1,0)"
    );
    $stmt->bind_param("ssssssss", $fn, $em, $dept, $spec, $sub, $placeholder, $token, $expires);
    $stmt->execute();
    $new_id = $conn->insert_id;
        log_audit($conn, $admin_id, 'create', 'doctor', $new_id, null, [
        'full_name'=>$fn,'email'=>$em,'department'=>$dept,'specialty'=>$spec,'subspecialty'=>$sub
    ]);

    $_SESSION['toast']       = "Doctor account created! Setup link emailed.";
    $_SESSION['invite_link']  = BASE_URL . '/doctor/setup.php?token=' . $token;
    $_SESSION['invite_email'] = $em;
    $_SESSION['invite_name']  = $fn;
    header('Location: users.php'); exit;
}

// ── Resend a doctor's setup link (regenerates the token) ──
if (isset($_GET['resend_invite'])) {
    $did     = (int)$_GET['resend_invite'];
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $conn->prepare("UPDATE doctors SET invite_token=?, invite_expires=? WHERE id=?");
    $stmt->bind_param("ssi", $token, $expires, $did);
    $stmt->execute();

    $dq = $conn->prepare("SELECT full_name, email FROM doctors WHERE id=?");
    $dq->bind_param("i", $did);
    $dq->execute();
    $d = $dq->get_result()->fetch_assoc();

    if ($d) {
        $_SESSION['toast']       = 'New setup link generated and emailed.';
        $_SESSION['invite_link']  = 'http://' . $_SERVER['HTTP_HOST'] . '/doctor/setup.php?token=' . $token;
        $_SESSION['invite_email'] = $d['email'];
        $_SESSION['invite_name']  = $d['full_name'];
    }
    header('Location: users.php'); exit;
}

// ── Toggle doctor active/inactive ──
if (isset($_GET['toggle_doctor'])) {
    $did = (int)$_GET['toggle_doctor'];
    $oldRow = $conn->query("SELECT status FROM doctors WHERE id=$did")->fetch_assoc();
    $oldStatus = $oldRow['status'] ?? 'unknown';
    $newStatus = $oldStatus === 'active' ? 'inactive' : 'active';
    $conn->query("UPDATE doctors SET status = IF(status='active','inactive','active'), is_available = IF(status='inactive',1,0) WHERE id=$did");
    log_audit($conn, $admin_id, 'toggle', 'doctor', $did, ['status'=>$oldStatus], ['status'=>$newStatus]);
    header('Location: users.php'); exit;
}

// ── Toggle patient active/inactive ──
if (isset($_GET['toggle_patient'])) {
    $pid = (int)$_GET['toggle_patient'];
    $cur = (int)($_GET['active'] ?? 1);
    $new = $cur ? 0 : 1;
    $conn->query("UPDATE patients SET is_active=$new WHERE id=$pid");
    log_audit($conn, $admin_id, 'toggle', 'patient', $pid, ['is_active'=>$cur], ['is_active'=>$new]);
    $_SESSION['toast'] = $new ? 'Account activated.' : 'Account deactivated.';
    header('Location: users.php'); exit;
}

// ── Toggle staff active/inactive ──
if (isset($_GET['toggle_staff'])) {
    $sid = (int)$_GET['toggle_staff'];
    $oldRow = $conn->query("SELECT status FROM staff_accounts WHERE id=$sid")->fetch_assoc();
    $oldStatus = $oldRow['status'] ?? 'unknown';
    $newStatus = $oldStatus === 'active' ? 'inactive' : 'active';
    $conn->query("UPDATE staff_accounts SET status = IF(status='active','inactive','active') WHERE id=$sid");
    log_audit($conn, $admin_id, 'toggle', 'staff', $sid, ['status'=>$oldStatus], ['status'=>$newStatus]);
    $_SESSION['toast'] = 'Staff account updated.';
    header('Location: users.php'); exit;
}

// ── Create staff account ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_staff'])) {
    $fn   = trim($_POST['staff_full_name'] ?? '');
    $em   = trim($_POST['staff_email'] ?? '');
    $pw   = $_POST['staff_password'] ?? '';

    if (!$fn || !$em || strlen($pw) < 8) {
        $_SESSION['toast_error'] = 'Please fill in all staff fields (password must be at least 8 characters).';
        header('Location: users.php'); exit;
    }

    $chk = $conn->prepare("SELECT id FROM staff_accounts WHERE email=?");
    $chk->bind_param("s", $em);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $_SESSION['toast_error'] = 'A staff account with that email already exists.';
        header('Location: users.php'); exit;
    }

    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO staff_accounts (full_name, email, password, status) VALUES (?,?,?,'active')");
    $stmt->bind_param("sss", $fn, $em, $hash);
    $stmt->execute();
    $new_id = $conn->insert_id;
    log_audit($conn, $admin_id, 'create', 'staff', $new_id, null, ['full_name'=>$fn,'email'=>$em]);

    $_SESSION['toast'] = 'Staff account created.';
    header('Location: users.php'); exit;
}

// ── Verify doctor ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_doctor'])) {
    $did     = (int)$_POST['doctor_id'];
    $license = strtoupper(trim($_POST['license_number'] ?? ''));
    $board   = trim($_POST['issuing_board'] ?? '');

    if (!preg_match('/^[A-Z0-9][A-Z0-9\-\/]{4,24}$/', $license)) {
        $_SESSION['toast_error'] = 'Invalid license number format. Use 5-25 chars: letters, numbers, dash or slash only.';
        header('Location: users.php'); exit;
    }
    if (!preg_match('/^[A-Za-z][A-Za-z .,&()\-]{2,59}$/', $board)) {
        $_SESSION['toast_error'] = 'Invalid issuing board format. Use letters and basic punctuation only (3-60 chars).';
        header('Location: users.php'); exit;
    }

    $now     = date('Y-m-d H:i:s');
    $license_file = null; $cert_file = null;
    $upload_dir = '../uploads/docs/';
    $allowed_ext  = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    foreach (['license_file' => 'license_file', 'board_cert_file' => 'board_cert_file'] as $input => $col) {
        if (!empty($_FILES[$input]['name'])) {
            $ext = strtolower(pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION));
            $tmp_name = $_FILES[$input]['tmp_name'];

            if (!in_array($ext, $allowed_ext, true)) {
                $_SESSION['toast_error'] = 'Only PDF or image files (JPG, JPEG, PNG, WEBP) are allowed.';
                header('Location: users.php'); exit;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? finfo_file($finfo, $tmp_name) : '';
            if ($finfo) finfo_close($finfo);

            if (!in_array($mime, $allowed_mime, true)) {
                $_SESSION['toast_error'] = 'Invalid file content detected. Please upload a real PDF or image file.';
                header('Location: users.php'); exit;
            }

            $fname = uniqid("doc_{$did}_") . '.' . $ext;
            if (move_uploaded_file($tmp_name, $upload_dir . $fname)) { $$col = 'uploads/docs/' . $fname; }
        }
    }
    $stmt = $conn->prepare("UPDATE doctors SET license_number=?, issuing_board=?, license_file=COALESCE(?,license_file), board_cert_file=COALESCE(?,board_cert_file), is_verified=1, verified_at=?, verified_by=? WHERE id=?");
    $stmt->bind_param("sssssii", $license, $board, $license_file, $cert_file, $now, $admin_id, $did);
    $stmt->execute();
    $_SESSION['toast'] = "Doctor verified successfully.";
    log_audit($conn, $admin_id, 'verify', 'doctor', $did, null, ['license_number'=>$license,'issuing_board'=>$board,'has_license_file'=>!empty($license_file),'has_cert_file'=>!empty($cert_file)]);
    header('Location: users.php'); exit;
}

// ── Update doctor ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doctor'])) {
        $did = (int)$_POST['doctor_id'];
    $fn = trim($_POST['full_name'] ?? '');
    $em = trim($_POST['email'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $spec = trim($_POST['specialty'] ?? '');
    $sub = trim($_POST['subspecialty'] ?? '');
    $clinic = trim($_POST['clinic_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $fee = trim($_POST['consultation_fee'] ?? '0');
    $langs = trim($_POST['languages_spoken'] ?? '');

    $errors = [];
    if (!$fn) $errors[] = 'Full name is required';
    if (!$em) $errors[] = 'Email is required';
    if ($em && !filter_var($em, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (!array_key_exists($dept, BOOKING_DEPARTMENTS)) $errors[] = 'Please select a valid department';
    if ($fee !== '' && !is_numeric($fee)) $errors[] = 'Consultation fee must be a number';

    $old = $conn->query("SELECT * FROM doctors WHERE id=$did")->fetch_assoc();

    if (empty($errors)) {
                $stmt = $conn->prepare("UPDATE doctors SET full_name=?, email=?, department=?, specialty=?, subspecialty=?, clinic_name=?, phone_number=?, consultation_fee=?, languages_spoken=? WHERE id=?");
        $stmt->bind_param("sssssssssi", $fn, $em, $dept, $spec, $sub, $clinic, $phone, $fee, $langs, $did);
        $stmt->execute();

        $new = $conn->query("SELECT * FROM doctors WHERE id=$did")->fetch_assoc();
        log_audit($conn, $admin_id, 'update', 'doctor', $did,
            ['full_name'=>$old['full_name'],'email'=>$old['email']],
            ['full_name'=>$fn,'email'=>$em]);

        $_SESSION['toast'] = 'Doctor updated successfully.';
    } else {
        $_SESSION['toast_error'] = implode(', ', $errors);
    }
    header('Location: users.php'); exit;
}

// ── Update patient ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $pid = (int)$_POST['patient_id'];
    $fn = trim($_POST['full_name'] ?? '');
    $em = trim($_POST['email'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['home_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $region = trim($_POST['country_region'] ?? '');
    $lang = trim($_POST['preferred_language'] ?? '');
    $active = trim($_POST['is_active'] ?? '1');

    $errors = [];
    if (!$fn) $errors[] = 'Full name is required';
    if (!$em) $errors[] = 'Email is required';
    if ($em && !filter_var($em, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';

    $old = $conn->query("SELECT * FROM patients WHERE id=$pid")->fetch_assoc();

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE patients SET full_name=?, email=?, date_of_birth=?, gender=?, phone_number=?, home_address=?, city=?, country_region=?, preferred_language=?, is_active=? WHERE id=?");
        $stmt->bind_param("sssssssssii", $fn, $em, $dob, $gender, $phone, $address, $city, $region, $lang, $active, $pid);
        $stmt->execute();

        $new = $conn->query("SELECT * FROM patients WHERE id=$pid")->fetch_assoc();
        log_audit($conn, $admin_id, 'update', 'patient', $pid,
            ['full_name'=>$old['full_name'],'email'=>$old['email'],'is_active'=>$old['is_active']],
            ['full_name'=>$fn,'email'=>$em,'is_active'=>$active]);

        $_SESSION['toast'] = 'Patient updated successfully.';
    } else {
        $_SESSION['toast_error'] = implode(', ', $errors);
    }
    header('Location: users.php'); exit;
}

// ── Update staff ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $sid = (int)$_POST['staff_id'];
    $fn = trim($_POST['full_name'] ?? '');
    $em = trim($_POST['email'] ?? '');

    $errors = [];
    if (!$fn) $errors[] = 'Full name is required';
    if (!$em) $errors[] = 'Email is required';
    if ($em && !filter_var($em, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';

    $old = $conn->query("SELECT * FROM staff_accounts WHERE id=$sid")->fetch_assoc();

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE staff_accounts SET full_name=?, email=? WHERE id=?");
        $stmt->bind_param("ssi", $fn, $em, $sid);
        $stmt->execute();

        log_audit($conn, $admin_id, 'update', 'staff', $sid,
            ['full_name'=>$old['full_name'],'email'=>$old['email']],
            ['full_name'=>$fn,'email'=>$em]);

        $_SESSION['toast'] = 'Staff updated successfully.';
    } else {
        $_SESSION['toast_error'] = implode(', ', $errors);
    }
    header('Location: users.php'); exit;
}

$toast        = $_SESSION['toast'] ?? null;
$toast_error  = $_SESSION['toast_error'] ?? null;
$invite_link  = $_SESSION['invite_link'] ?? null;
$invite_email = $_SESSION['invite_email'] ?? null;
$invite_name  = $_SESSION['invite_name'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['invite_link'], $_SESSION['invite_email'], $_SESSION['invite_name']);

/* ══════════════════════════════════════════════
   FETCH DOCTORS (+ schedules)
   ══════════════════════════════════════════════ */
$dres = $conn->query("SELECT d.* FROM doctors d ORDER BY d.created_at DESC");
$doctors = [];
if ($dres) { while ($row = $dres->fetch_assoc()) $doctors[] = $row; }

$dayOrder = ['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>7];
$scheduleMap = [];
if (count($doctors) > 0) {
    $ids = implode(',', array_map(fn($d) => (int)$d['id'], $doctors));
    $sres = $conn->query("SELECT doctor_id, day_of_week, start_time, end_time FROM doctor_schedules WHERE doctor_id IN ($ids)");
    if ($sres) {
        while ($srow = $sres->fetch_assoc()) {
            $scheduleMap[(int)$srow['doctor_id']][] = [
                'day_of_week' => $srow['day_of_week'],
                'start_time'  => $srow['start_time'],
                'end_time'    => $srow['end_time'],
            ];
        }
        foreach ($scheduleMap as &$rows) {
            usort($rows, fn($a,$b) => ($dayOrder[$a['day_of_week']]??9) - ($dayOrder[$b['day_of_week']]??9));
        }
        unset($rows);
    }
}
foreach ($doctors as &$doc) { $doc['_schedules'] = $scheduleMap[(int)$doc['id']] ?? []; $doc['_type'] = 'doctor'; }
unset($doc);

/* ══════════════════════════════════════════════
   FETCH PATIENTS
   ══════════════════════════════════════════════ */
$pres = $conn->query("SELECT * FROM patients ORDER BY created_at DESC");
$patients = [];
if ($pres) { while ($row = $pres->fetch_assoc()) { $row['_type'] = 'patient'; $patients[] = $row; } }

/* ══════════════════════════════════════════════
   FETCH STAFF
   ══════════════════════════════════════════════ */
$sres = $conn->query("SELECT id, full_name, email, status, created_at FROM staff_accounts ORDER BY created_at DESC");
$staff = [];
if ($sres) { while ($row = $sres->fetch_assoc()) { $row['_type'] = 'staff'; $staff[] = $row; } }

/* ══════════════════════════════════════════════
   UNIFIED LIST FOR THE TABLE / JS
   ══════════════════════════════════════════════ */
$allUsers = [];
foreach ($doctors as $d) {
    $status = $d['status'] === 'active' ? 'active' : ($d['status'] === 'pending' ? 'pending' : 'inactive');
    $allUsers[] = [
        'id' => $d['id'], 'type' => 'doctor', 'role_label' => 'Doctor',
        'full_name' => $d['full_name'], 'email' => $d['email'],
        'status' => $status, 'raw' => $d,
    ];
}
foreach ($patients as $p) {
    $isActive = isset($p['is_active']) ? (int)$p['is_active'] : 1;
    $allUsers[] = [
        'id' => $p['id'], 'type' => 'patient', 'role_label' => 'Patient',
        'full_name' => $p['full_name'], 'email' => $p['email'],
        'status' => $isActive ? 'active' : 'inactive', 'raw' => $p,
    ];
}
foreach ($staff as $s) {
    $allUsers[] = [
        'id' => $s['id'], 'type' => 'staff', 'role_label' => 'Staff',
        'full_name' => $s['full_name'], 'email' => $s['email'],
        'status' => $s['status'] === 'active' ? 'active' : 'inactive', 'raw' => $s,
    ];
}

/* ══════════════════════════════════════════════
   FETCH AUDIT LOGS (account change history)
   ══════════════════════════════════════════════ */
$auditLogs = [];
$alog = $conn->query(
    "SELECT a.*, adm.full_name AS admin_name
     FROM audit_logs a
     LEFT JOIN admins adm ON adm.id = a.admin_id
     ORDER BY a.created_at DESC
     LIMIT 200"
);
if ($alog) { while ($row = $alog->fetch_assoc()) $auditLogs[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Management — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>


    /* ── Controls bar: search + filters ── */

    .role-pill{display:inline-block;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.03em}


    .modal-details{max-width:640px}
    .details-header{display:flex;align-items:center;gap:1.2rem;margin-bottom:1.5rem;padding-bottom:1.2rem;border-bottom:1px solid rgba(36,68,65,0.08)}
    .details-avatar{width:64px;height:64px;border-radius:18px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.4rem;flex-shrink:0}
    .details-section{margin-bottom:1.4rem}
    .details-section-title{font-size:0.68rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem}
    .details-section-title::after{content:'';flex:1;height:1px;background:rgba(36,68,65,0.08)}
    .schedule-table{width:100%;border-collapse:collapse}
    .schedule-table th{font-size:0.68rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#9ab0ae;padding:0.4rem 0.6rem;text-align:left;border-bottom:1px solid rgba(36,68,65,0.07)}
    .schedule-table td{font-size:0.83rem;padding:0.5rem 0.6rem;border-bottom:1px solid rgba(36,68,65,0.04);color:var(--green)}
    .schedule-table tr:last-child td{border-bottom:none}
    .sched-day{font-weight:600;width:110px}
    .sched-off{color:#c0cece;font-style:italic}
    .doc-link{color:var(--blue);text-decoration:none;font-size:0.82rem;font-weight:600;display:inline-flex;align-items:center;gap:0.3rem}
    .doc-link:hover{text-decoration:underline}

    .invite-box{background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);border-radius:14px;padding:1rem 1.2rem;margin-bottom:1.5rem}
    .invite-box p{font-size:0.78rem;color:var(--blue);font-weight:600;margin-bottom:0.5rem}
    .invite-box code{font-size:0.75rem;word-break:break-all;color:var(--green);background:rgba(36,68,65,0.06);padding:0.4rem 0.6rem;border-radius:8px;display:block}

    .role-select{display:flex;gap:0.5rem;margin-bottom:1.2rem}
    .role-select button{flex:1;padding:0.6rem;border-radius:12px;border:1.5px solid rgba(36,68,65,0.12);background:var(--white);color:var(--green);font-weight:600;font-size:0.82rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s}
    .role-select button.active{background:var(--green);border-color:var(--green);color:#fff}
    .role-select button:disabled{opacity:0.4;cursor:not-allowed}
    .role-note{font-size:0.75rem;color:#9ab0ae;margin-bottom:1rem;line-height:1.4}


    @media(max-width:900px){.sidebar{display:none}}
    @media(max-width:520px){.details-grid{grid-template-columns:1fr}.controls-bar{flex-direction:column;align-items:stretch}.search-wrap{max-width:none}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast error">✕ <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>

<?php $activeNav = 'users'; include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">User Management</div>
    </div>
    <div style="display:flex;gap:0.6rem;">
      <button class="btn-primary" onclick="openModal('modal-create-doctor')">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add Doctor
      </button>
      <button class="btn-primary-alt" onclick="openModal('modal-create-staff')">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add Staff
      </button>
      <button class="btn-blue btn-sm" style="padding:0.6rem 1rem;border-radius:50px;border:1.5px solid rgba(63,130,227,0.2);background:rgba(63,130,227,0.1);font-weight:600;cursor:pointer;" onclick="openAuditLogs()">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Log History
      </button>
    </div>
  </div>

  <div class="page-content">

    <script>
      const EMAILJS_PUBLIC_KEY       = 'm-AvAiAdUDsgBbz6D';
      const EMAILJS_SERVICE_ID       = 'service_vr6ygvx';
      const EMAILJS_INVITE_TEMPLATE  = 'template_hv6nkmj';
      emailjs.init(EMAILJS_PUBLIC_KEY);
    </script>

    <?php if ($invite_link): ?>
    <div class="invite-box">
      <p>📧 Setup email sent to <strong><?= htmlspecialchars($invite_email) ?></strong> — link also shown below:</p>
      <code id="inviteCode"><?= htmlspecialchars($invite_link) ?></code>
      <button onclick="copyInvite()" style="margin-top:0.5rem;font-size:0.75rem;color:var(--blue);background:none;border:none;cursor:pointer;font-weight:600;">Copy link</button>
    </div>
    <script>
      const doctorEmail = <?= json_encode($invite_email) ?>;
      const doctorName  = <?= json_encode($invite_name) ?>;
      const setupLink   = <?= json_encode($invite_link) ?>;
      emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_INVITE_TEMPLATE, {
        to_email: doctorEmail, doctor_name: doctorName, invite_link: setupLink,
      }).then(() => console.log('Doctor setup email sent to ' + doctorEmail))
        .catch(err => console.error('EmailJS setup email error:', err));
    </script>
    <?php endif; ?>

    <!-- ── Search + Filter Controls ── -->
    <div class="controls-bar">
      <div class="search-wrap">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Search by name or email..." oninput="applyFilters()"/>
      </div>
      <div class="filter-tabs" id="filterTabs">
        <button class="filter-tab active" data-filter="all" onclick="setFilter('all')">All</button>
        <button class="filter-tab" data-filter="patient" onclick="setFilter('patient')">Patients</button>
        <button class="filter-tab" data-filter="doctor" onclick="setFilter('doctor')">Doctors</button>
        <button class="filter-tab" data-filter="staff" onclick="setFilter('staff')">Staff</button>
        <button class="filter-tab" data-filter="inactive" onclick="setFilter('inactive')">Inactive</button>
      </div>
    </div>

    <!-- ── Unified User Table ── -->
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Name</th><th>Role</th><th>Email</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody id="usersTableBody">
          <!-- rows rendered by JS from ALL_USERS -->
        </tbody>
      </table>
    </div>
    <div id="emptyState" class="empty-row" style="display:none;">No users match your search/filter.</div>

  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: Create Account
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-create-doctor">
  <div class="modal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;">
      <h3 style="margin-bottom:0;">Add Doctor</h3>
      <button type="button" onclick="closeModal('modal-create-doctor')" style="background:none;border:none;cursor:pointer;font-size:1.3rem;color:#9ab0ae;line-height:1;">&times;</button>
    </div>
    <p style="font-size:0.82rem;color:#9ab0ae;margin-bottom:1.2rem;">Fill in the doctor's basic details. A setup link will be emailed to them automatically — they click it, set their own password, and fill in the rest of their profile themselves.</p>
    <form method="POST" id="create-doctor-form">
      <div class="form-field"><label class="field-label">Full Name *</label><input type="text" name="full_name" class="field-input" placeholder="e.g. Maria Santos" required/></div>
      <div class="form-field"><label class="field-label">Email Address *</label><input type="email" name="email" class="field-input" placeholder="doctor@email.com" required/></div>
            <div class="form-field">
        <label class="field-label">Department *</label>
        <select name="department" class="field-input" required>
          <option value="">— Select —</option>
          <?php foreach (array_keys(BOOKING_DEPARTMENTS) as $dep): ?>
            <option value="<?= htmlspecialchars($dep) ?>"><?= htmlspecialchars($dep) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Specialty</label><input type="text" name="specialty" class="field-input" placeholder="e.g. Cardiology"/></div>
        <div class="form-field"><label class="field-label">Subspecialty</label><input type="text" name="subspecialty" class="field-input" placeholder="Optional"/></div>
      </div>
      <button type="submit" name="create_doctor" class="btn-submit">Create &amp; Send Setup Link</button>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modal-create-staff">
  <div class="modal">
    <h3>Add Staff</h3>
    <form method="POST" id="create-staff-form">
      <div class="form-field"><label class="field-label">Full Name *</label><input type="text" name="staff_full_name" class="field-input" placeholder="e.g. Ana Reyes" required/></div>
      <div class="form-field"><label class="field-label">Email Address *</label><input type="email" name="staff_email" class="field-input" placeholder="staff@telecare.com" required/></div>
      <div class="form-field"><label class="field-label">Password *</label><input type="password" name="staff_password" class="field-input" placeholder="Min. 8 characters" minlength="8" required/></div>
      <button type="submit" name="create_staff" class="btn-submit">Create Staff Account</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-create-staff')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: View Doctor Details
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-view-doctor">
  <div class="modal modal-details">
    <div class="details-header">
      <div class="details-avatar" id="vd-avatar"></div>
      <div style="flex:1;">
        <div style="font-size:1.15rem;font-weight:800;" id="vd-name"></div>
        <div style="font-size:0.82rem;color:#9ab0ae;margin-top:0.2rem;" id="vd-specialty"></div>
        <div style="margin-top:0.5rem;display:flex;gap:0.4rem;flex-wrap:wrap;" id="vd-badges"></div>
      </div>
    </div>

    <div class="details-section">
      <div class="details-section-title">Contact Information</div>
      <div class="details-grid">
        <div class="detail-item"><div class="detail-item-label">Email</div><div class="detail-item-value" id="vd-email"></div></div>
        <div class="detail-item"><div class="detail-item-label">Phone Number</div><div class="detail-item-value" id="vd-phone"></div></div>
        <div class="detail-item full"><div class="detail-item-label">Address / Clinic</div><div class="detail-item-value" id="vd-address"></div></div>
      </div>
    </div>

    <div class="details-section">
      <div class="details-section-title">Professional Information</div>
      <div class="details-grid">
        <div class="detail-item"><div class="detail-item-label">License Number</div><div class="detail-item-value" id="vd-license"></div></div>
        <div class="detail-item"><div class="detail-item-label">Issuing Board</div><div class="detail-item-value" id="vd-board"></div></div>
        <div class="detail-item"><div class="detail-item-label">Years of Experience</div><div class="detail-item-value" id="vd-experience"></div></div>
        <div class="detail-item"><div class="detail-item-label">Consultation Fee</div><div class="detail-item-value" id="vd-fee"></div></div>
        <div class="detail-item full"><div class="detail-item-label">Bio / About</div><div class="detail-item-value" id="vd-bio"></div></div>
      </div>
    </div>

    <div class="details-section">
      <div class="details-section-title">Weekly Schedule</div>
      <div style="background:rgba(36,68,65,0.04);border-radius:12px;overflow:hidden;">
        <table class="schedule-table">
          <thead><tr><th>Day</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
          <tbody id="vd-schedule-body">
            <tr><td colspan="4" style="padding:1rem;text-align:center;color:#c0cece;font-style:italic;font-size:0.82rem;">No schedule data</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="details-section">
      <div class="details-section-title">Uploaded Documents</div>
      <div class="details-grid">
        <div class="detail-item"><div class="detail-item-label">License File</div><div class="detail-item-value" id="vd-license-file"></div></div>
        <div class="detail-item"><div class="detail-item-label">Board Certificate</div><div class="detail-item-value" id="vd-cert-file"></div></div>
        <div class="detail-item"><div class="detail-item-label">Profile Photo</div><div class="detail-item-value" id="vd-photo"></div></div>
        <div class="detail-item"><div class="detail-item-label">Verified At</div><div class="detail-item-value" id="vd-verified-at"></div></div>
      </div>
    </div>

    <div class="details-section">
      <div class="details-section-title">Account</div>
      <div class="details-grid">
        <div class="detail-item"><div class="detail-item-label">Registered</div><div class="detail-item-value" id="vd-created"></div></div>
        <div class="detail-item"><div class="detail-item-label">Setup Complete</div><div class="detail-item-value" id="vd-setup"></div></div>
        <div class="detail-item"><div class="detail-item-label">Availability</div><div class="detail-item-value" id="vd-available"></div></div>
      </div>
    </div>

    <button class="btn-cancel" onclick="closeModal('modal-view-doctor')">Close</button>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: View Patient Details (lightweight)
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-view-patient">
  <div class="modal">
    <h3 id="vp-title">Patient Details</h3>
    <div class="details-grid">
      <div class="detail-item"><div class="detail-item-label">Email</div><div class="detail-item-value" id="vp-email"></div></div>
      <div class="detail-item"><div class="detail-item-label">Phone Number</div><div class="detail-item-value" id="vp-phone"></div></div>
      <div class="detail-item"><div class="detail-item-label">Joined</div><div class="detail-item-value" id="vp-created"></div></div>
      <div class="detail-item"><div class="detail-item-label">Status</div><div class="detail-item-value" id="vp-status"></div></div>
    </div>
    <button class="btn-cancel" onclick="closeModal('modal-view-patient')">Close</button>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: View Staff Details (lightweight)
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-view-staff">
  <div class="modal">
    <h3 id="vs-title">Staff Details</h3>
    <div class="details-grid">
      <div class="detail-item"><div class="detail-item-label">Email</div><div class="detail-item-value" id="vs-email"></div></div>
      <div class="detail-item"><div class="detail-item-label">Joined</div><div class="detail-item-value" id="vs-created"></div></div>
      <div class="detail-item"><div class="detail-item-label">Status</div><div class="detail-item-value" id="vs-status"></div></div>
    </div>
    <button class="btn-cancel" onclick="closeModal('modal-view-staff')">Close</button>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: Verify Doctor
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-verify-doctor">
  <div class="modal">
    <h3>Verify Doctor</h3>
    <p style="font-size:0.82rem;color:#9ab0ae;margin-bottom:1.2rem;">Log the doctor's license info and upload documents.</p>
    <div id="verify-live-warning" class="live-warning"></div>
    <form method="POST" enctype="multipart/form-data" id="verify-form">
      <input type="hidden" name="doctor_id" id="verify-doctor-id"/>
      <div class="form-field"><label class="field-label">Doctor</label><input type="text" id="verify-doctor-name" class="field-input" disabled/></div>
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">License Number</label>
          <input type="text" name="license_number" id="verify-license-number" class="field-input" placeholder="e.g. 1234567 or ABC-12345" required maxlength="25" pattern="[A-Z0-9][A-Z0-9\-/]{4,24}" title="Use 5-25 chars: uppercase letters, numbers, dash or slash only." oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9\-\/]/g,'').slice(0,25)"/>
          <div class="field-warning" id="warn-license">Invalid license format. Use 5-25 chars: A-Z, 0-9, dash or slash only.</div>
        </div>
        <div class="form-field">
          <label class="field-label">Issuing Board</label>
          <input type="text" name="issuing_board" id="verify-issuing-board" class="field-input" placeholder="e.g. PRC, PMA" required maxlength="60" pattern="[A-Za-z][A-Za-z .,&()\-]{2,59}" title="Use 3-60 chars: letters, spaces, and . , & ( ) - only." oninput="this.value=this.value.replace(/[^A-Za-z .,&()\-]/g,'').slice(0,60)"/>
          <div class="field-warning" id="warn-board">Invalid issuing board format. Use 3-60 chars with letters and basic punctuation only.</div>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">License File <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(PDF/Image only)</span></label>
        <input type="file" name="license_file" id="verify-license-file" class="field-input" accept="application/pdf,image/*,.pdf,.jpg,.jpeg,.png,.webp" style="padding:0.5rem;"/>
        <div class="field-warning" id="warn-license-file">Invalid file. Upload PDF/JPG/JPEG/PNG/WEBP only.</div>
      </div>
      <div class="form-field">
        <label class="field-label">Board Certification <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(PDF/Image only)</span></label>
        <input type="file" name="board_cert_file" id="verify-board-file" class="field-input" accept="application/pdf,image/*,.pdf,.jpg,.jpeg,.png,.webp" style="padding:0.5rem;"/>
        <div class="field-warning" id="warn-board-file">Invalid file. Upload PDF/JPG/JPEG/PNG/WEBP only.</div>
      </div>
      <button type="submit" name="verify_doctor" class="btn-submit">Mark as Verified</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-verify-doctor')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: Edit Doctor
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-doctor">
  <div class="modal">
    <h3>Edit Doctor</h3>
    <div id="edit-doctor-live-warning" class="live-warning"></div>
    <form method="POST" id="edit-doctor-form" onsubmit="return validateEditDoctor()">
      <input type="hidden" name="doctor_id" id="edit-doctor-id"/>
      <div class="form-field"><label class="field-label">Full Name *</label><input type="text" name="full_name" id="edit-doctor-name" class="field-input" required/><div class="field-warning" id="edit-warn-doctor-name">Full name is required.</div></div>
            <div class="form-field"><label class="field-label">Email Address *</label><input type="email" name="email" id="edit-doctor-email" class="field-input" required/><div class="field-warning" id="edit-warn-doctor-email">Enter a valid email address.</div></div>
      <div class="form-field">
        <label class="field-label">Department *</label>
        <select name="department" id="edit-doctor-department" class="field-input" required>
          <option value="">— Select —</option>
          <?php foreach (array_keys(BOOKING_DEPARTMENTS) as $dep): ?>
            <option value="<?= htmlspecialchars($dep) ?>"><?= htmlspecialchars($dep) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Specialty</label><input type="text" name="specialty" id="edit-doctor-specialty" class="field-input"/></div>
        <div class="form-field"><label class="field-label">Subspecialty</label><input type="text" name="subspecialty" id="edit-doctor-subspecialty" class="field-input"/></div>
      </div>
      <div class="form-field"><label class="field-label">Clinic Name</label><input type="text" name="clinic_name" id="edit-doctor-clinic" class="field-input"/></div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Phone Number</label><input type="text" name="phone_number" id="edit-doctor-phone" class="field-input"/></div>
        <div class="form-field"><label class="field-label">Consultation Fee (₱)</label><input type="number" step="0.01" min="0" name="consultation_fee" id="edit-doctor-fee" class="field-input"/></div>
      </div>
      <div class="form-field"><label class="field-label">Languages Spoken</label><input type="text" name="languages_spoken" id="edit-doctor-langs" class="field-input" placeholder="e.g. English, Tagalog"/></div>
      <button type="submit" name="update_doctor" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-doctor')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: Edit Patient
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-patient">
  <div class="modal">
    <h3>Edit Patient</h3>
    <div id="edit-patient-live-warning" class="live-warning"></div>
    <form method="POST" id="edit-patient-form" onsubmit="return validateEditPatient()">
      <input type="hidden" name="patient_id" id="edit-patient-id"/>
      <div class="form-field"><label class="field-label">Full Name *</label><input type="text" name="full_name" id="edit-patient-name" class="field-input" required/><div class="field-warning" id="edit-warn-patient-name">Full name is required.</div></div>
      <div class="form-field"><label class="field-label">Email Address *</label><input type="email" name="email" id="edit-patient-email" class="field-input" required/><div class="field-warning" id="edit-warn-patient-email">Enter a valid email address.</div></div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Date of Birth</label><input type="date" name="date_of_birth" id="edit-patient-dob" class="field-input"/></div>
        <div class="form-field">
          <label class="field-label">Gender</label>
          <select name="gender" id="edit-patient-gender" class="field-input">
            <option value="">—</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>
      <div class="form-field"><label class="field-label">Phone Number</label><input type="text" name="phone_number" id="edit-patient-phone" class="field-input"/></div>
      <div class="form-field"><label class="field-label">Home Address</label><input type="text" name="home_address" id="edit-patient-address" class="field-input"/></div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">City</label><input type="text" name="city" id="edit-patient-city" class="field-input"/></div>
        <div class="form-field"><label class="field-label">Country / Region</label><input type="text" name="country_region" id="edit-patient-region" class="field-input"/></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Preferred Language</label><input type="text" name="preferred_language" id="edit-patient-lang" class="field-input"/></div>
        <div class="form-field">
          <label class="field-label">Status</label>
          <select name="is_active" id="edit-patient-active" class="field-input">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <button type="submit" name="update_patient" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-patient')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: Edit Staff
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-staff">
  <div class="modal">
    <h3>Edit Staff</h3>
    <div id="edit-staff-live-warning" class="live-warning"></div>
    <form method="POST" id="edit-staff-form" onsubmit="return validateEditStaff()">
      <input type="hidden" name="staff_id" id="edit-staff-id"/>
      <div class="form-field"><label class="field-label">Full Name *</label><input type="text" name="full_name" id="edit-staff-name" class="field-input" required/><div class="field-warning" id="edit-warn-staff-name">Full name is required.</div></div>
      <div class="form-field"><label class="field-label">Email Address *</label><input type="email" name="email" id="edit-staff-email" class="field-input" required/><div class="field-warning" id="edit-warn-staff-email">Enter a valid email address.</div></div>
      <button type="submit" name="update_staff" class="btn-submit">Save Changes</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-edit-staff')">Cancel</button>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════
     MODAL: Audit Log History
     ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-audit-logs" style="align-items:flex-start;padding-top:2rem;padding-bottom:2rem;">
  <div class="modal" style="max-width:480px;max-height:none;overflow:visible;padding:1.4rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;">
      <h3 style="margin-bottom:0;">Account Change History</h3>
    </div>
    <div id="audit-log-body"></div>
    <div id="audit-log-pages" style="display:flex;align-items:center;justify-content:center;gap:0.6rem;margin-top:1rem;"></div>
    <button class="btn-cancel" onclick="closeModal('modal-audit-logs')">Close</button>
  </div>
</div>

<!-- ── Data for JS ── -->
<script>
const ALL_USERS = <?= json_encode(array_values($allUsers), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const AUDIT_LOGS = <?= json_encode($auditLogs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

let currentFilter = 'all';

// ── Modal helpers ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Filters ──
function setFilter(f) {
  currentFilter = f;
  document.querySelectorAll('.filter-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === f));
  applyFilters();
}

function applyFilters() {
  const q = document.getElementById('searchInput').value.trim().toLowerCase();
  let rows = ALL_USERS.filter(u => {
    if (currentFilter === 'patient' && u.type !== 'patient') return false;
    if (currentFilter === 'doctor' && u.type !== 'doctor') return false;
    if (currentFilter === 'staff' && u.type !== 'staff') return false;
    if (currentFilter === 'inactive' && !(u.status === 'inactive' || u.status === 'pending')) return false;
    if (q) {
      const hay = (u.full_name + ' ' + u.email).toLowerCase();
      if (!hay.includes(q)) return false;
    }
    return true;
  });
  renderRows(rows);
}

function statusBadge(status) {
  if (status === 'active')  return `<span class="badge badge-green">Active</span>`;
  if (status === 'pending') return `<span class="badge badge-orange">Pending</span>`;
  return `<span class="badge badge-red">Inactive</span>`;
}

function rolePill(type) {
  if (type === 'doctor')  return `<span class="role-pill role-doctor">Doctor</span>`;
  if (type === 'patient') return `<span class="role-pill role-patient">Patient</span>`;
  return `<span class="role-pill role-staff">Staff</span>`;
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
}

function renderRows(rows) {
  const tbody = document.getElementById('usersTableBody');
  const empty = document.getElementById('emptyState');

  if (rows.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  tbody.innerHTML = rows.map(u => {
    let actions = '';
    if (u.type === 'doctor') {
      const d = u.raw;
      actions += `<button class="btn-sm btn-teal" onclick="openDetailsModal(${d.id})">View</button>`;
      actions += `<button class="btn-sm btn-edit" onclick="openEditDoctorModal(${d.id})">Edit</button>`;
      actions += `<a href="?toggle_doctor=${d.id}" class="btn-sm ${d.status==='active'?'btn-red':'btn-activate'}">${d.status==='active'?'Deactivate':'Activate'}</a>`;
      if (!d.is_verified) {
        actions += `<button class="btn-sm btn-blue" onclick="openVerifyModal(${d.id}, '${escAttr(d.full_name)}')">Verify</button>`;
      }
      if (!d.setup_complete) {
        actions += `<a href="?resend_invite=${d.id}" class="btn-sm btn-green" onclick="return confirm('Send a new setup link to ${escAttr(d.email)}?')">Resend Setup Link</a>`;
      }
    } else if (u.type === 'patient') {
      const p = u.raw;
      const isActive = u.status === 'active';
      actions += `<button class="btn-sm btn-teal" onclick="openPatientModal(${p.id})">View</button>`;
      actions += `<button class="btn-sm btn-edit" onclick="openEditPatientModal(${p.id})">Edit</button>`;
      actions += `<a href="?toggle_patient=${p.id}&active=${isActive?1:0}" class="btn-sm ${isActive?'btn-red':'btn-activate'}" onclick="return confirm('${isActive?'Deactivate':'Activate'} ${escAttr(p.full_name)}\\'s account?')">${isActive?'Deactivate':'Activate'}</a>`;
    } else if (u.type === 'staff') {
      const s = u.raw;
      const isActive = u.status === 'active';
      actions += `<button class="btn-sm btn-teal" onclick="openStaffModal(${s.id})">View</button>`;
      actions += `<button class="btn-sm btn-edit" onclick="openEditStaffModal(${s.id})">Edit</button>`;
      actions += `<a href="?toggle_staff=${s.id}" class="btn-sm ${isActive?'btn-red':'btn-activate'}" onclick="return confirm('${isActive?'Deactivate':'Activate'} ${escAttr(s.full_name)}\\'s account?')">${isActive?'Deactivate':'Activate'}</a>`;
    } else {
      actions += `<span style="color:#c0cece;font-size:0.78rem;font-style:italic;">No actions</span>`;
    }

    return `<tr>
      <td><span class="row-avatar">${initials(u.full_name)}</span>${escHtml(u.full_name)}</td>
      <td>${rolePill(u.type)}</td>
      <td style="color:#9ab0ae;">${escHtml(u.email || '—')}</td>
      <td>${statusBadge(u.status)}</td>
      <td><div class="actions-cell">${actions}</div></td>
    </tr>`;
  }).join('');
}

// ── Verify modal ──
function openVerifyModal(id, name) {
  const form = document.getElementById('verify-form');
  if (form) form.reset();
  document.getElementById('verify-doctor-id').value = id;
  document.getElementById('verify-doctor-name').value = 'Dr. ' + name;
  clearVerifyWarnings();
  openModal('modal-verify-doctor');
}

function setFieldWarn(inputEl, warnEl, show, msg = '') {
  if (!inputEl || !warnEl) return;
  inputEl.classList.toggle('invalid', !!show);
  warnEl.classList.toggle('show', !!show);
  if (show && msg) warnEl.textContent = msg;
}

function clearVerifyWarnings() {
  setLiveWarn('');
  ['verify-license-number','verify-issuing-board','verify-license-file','verify-board-file'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('invalid');
  });
  ['warn-license','warn-board','warn-license-file','warn-board-file'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
  });
}

function validateLicenseField() {
  const input = document.getElementById('verify-license-number');
  const warn  = document.getElementById('warn-license');
  if (!input) return true;
  const ok = /^[A-Z0-9][A-Z0-9\-/]{4,24}$/.test((input.value || '').trim());
  setFieldWarn(input, warn, !ok && input.value.trim() !== '');
  return ok;
}

function validateBoardField() {
  const input = document.getElementById('verify-issuing-board');
  const warn  = document.getElementById('warn-board');
  if (!input) return true;
  const ok = /^[A-Za-z][A-Za-z .,&()\-]{2,59}$/.test((input.value || '').trim());
  setFieldWarn(input, warn, !ok && input.value.trim() !== '');
  return ok;
}

function validateVerifyFileInput(inputId, warnId) {
  const input = document.getElementById(inputId);
  const warn  = document.getElementById(warnId);
  if (!input) return true;
  const file = input.files && input.files[0];
  if (!file) { setFieldWarn(input, warn, false); return true; }

  const allowedExt = ['pdf','jpg','jpeg','png','webp'];
  const allowedMime = ['application/pdf','image/jpeg','image/png','image/webp'];
  const ext = (file.name.split('.').pop() || '').toLowerCase();
  const mime = (file.type || '').toLowerCase();
  const extOk = allowedExt.includes(ext);
  const mimeOk = !mime || allowedMime.includes(mime);
  const ok = extOk && mimeOk;

  setFieldWarn(input, warn, !ok, 'Invalid file. Upload PDF/JPG/JPEG/PNG/WEBP only.');
  return ok;
}

const verifyForm = document.getElementById('verify-form');
if (verifyForm) {
  const lic = document.getElementById('verify-license-number');
  const board = document.getElementById('verify-issuing-board');
  const licFile = document.getElementById('verify-license-file');
  const boardFile = document.getElementById('verify-board-file');

  if (lic) lic.addEventListener('input', validateLicenseField);
  if (board) board.addEventListener('input', validateBoardField);
  if (licFile) licFile.addEventListener('change', () => validateVerifyFileInput('verify-license-file', 'warn-license-file'));
  if (boardFile) boardFile.addEventListener('change', () => validateVerifyFileInput('verify-board-file', 'warn-board-file'));

  verifyForm.addEventListener('submit', (e) => {
    const licenseOk = validateLicenseField();
    const boardOk = validateBoardField();
    const licenseFileOk = validateVerifyFileInput('verify-license-file', 'warn-license-file');
    const boardFileOk = validateVerifyFileInput('verify-board-file', 'warn-board-file');

    if (!licenseOk || !boardOk || !licenseFileOk || !boardFileOk) {
      e.preventDefault();
      setLiveWarn('Please fix the highlighted fields. Invalid file types are not allowed.');
      openModal('modal-verify-doctor');
      return;
    }
    setLiveWarn('');
  });
}

// ── Edit Doctor modal ──
function openEditDoctorModal(id) {
  const u = ALL_USERS.find(x => x.type === 'doctor' && x.id == id);
  if (!u) return;
  const d = u.raw;
  document.getElementById('edit-doctor-id').value      = d.id;
  document.getElementById('edit-doctor-name').value    = d.full_name || '';
  document.getElementById('edit-doctor-email').value   = d.email || '';
   document.getElementById('edit-doctor-department').value  = d.department || '';
  document.getElementById('edit-doctor-specialty').value   = d.specialty || '';
  document.getElementById('edit-doctor-subspecialty').value = d.subspecialty || '';
  document.getElementById('edit-doctor-clinic').value  = d.clinic_name || '';
  document.getElementById('edit-doctor-phone').value   = d.phone_number || '';
  document.getElementById('edit-doctor-fee').value     = d.consultation_fee ?? '';
   document.getElementById('edit-doctor-langs').value   = d.languages_spoken || '';
   clearEditWarnings('edit-doctor');
   openModal('modal-edit-doctor');
}

function validateEditDoctor() {
  let ok = true;
  const name  = document.getElementById('edit-doctor-name');
  const email = document.getElementById('edit-doctor-email');
  const fee   = document.getElementById('edit-doctor-fee');
  if (!name.value.trim()) { setFieldWarn(name, document.getElementById('edit-warn-doctor-name'), true); ok = false; }
  else setFieldWarn(name, document.getElementById('edit-warn-doctor-name'), false);
  if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    setFieldWarn(email, document.getElementById('edit-warn-doctor-email'), true); ok = false;
  } else setFieldWarn(email, document.getElementById('edit-warn-doctor-email'), false);
  if (fee.value.trim() !== '' && isNaN(parseFloat(fee.value))) {
    setLiveWarn('Consultation fee must be a number.', 'edit-doctor'); ok = false;
  }
  if (!ok) setLiveWarn('Please fix the highlighted fields.', 'edit-doctor');
  return ok;
}

// ── Edit Patient modal ──
function openEditPatientModal(id) {
  const u = ALL_USERS.find(x => x.type === 'patient' && x.id == id);
  if (!u) return;
  const p = u.raw;
  document.getElementById('edit-patient-id').value       = p.id;
  document.getElementById('edit-patient-name').value     = p.full_name || '';
  document.getElementById('edit-patient-email').value    = p.email || '';
  document.getElementById('edit-patient-dob').value      = p.date_of_birth || '';
  document.getElementById('edit-patient-gender').value   = p.gender || '';
  document.getElementById('edit-patient-phone').value    = p.phone_number || '';
  document.getElementById('edit-patient-address').value  = p.home_address || '';
  document.getElementById('edit-patient-city').value     = p.city || '';
  document.getElementById('edit-patient-region').value   = p.country_region || '';
  document.getElementById('edit-patient-lang').value     = p.preferred_language || '';
  document.getElementById('edit-patient-active').value   = p.is_active ? '1' : '0';
  clearEditWarnings('edit-patient');
  openModal('modal-edit-patient');
}

function validateEditPatient() {
  let ok = true;
  const name  = document.getElementById('edit-patient-name');
  const email = document.getElementById('edit-patient-email');
  if (!name.value.trim()) { setFieldWarn(name, document.getElementById('edit-warn-patient-name'), true); ok = false; }
  else setFieldWarn(name, document.getElementById('edit-warn-patient-name'), false);
  if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    setFieldWarn(email, document.getElementById('edit-warn-patient-email'), true); ok = false;
  } else setFieldWarn(email, document.getElementById('edit-warn-patient-email'), false);
  if (!ok) setLiveWarn('Please fix the highlighted fields.', 'edit-patient');
  return ok;
}

// ── Edit Staff modal ──
function openEditStaffModal(id) {
  const u = ALL_USERS.find(x => x.type === 'staff' && x.id == id);
  if (!u) return;
  const s = u.raw;
  document.getElementById('edit-staff-id').value     = s.id;
  document.getElementById('edit-staff-name').value   = s.full_name || '';
   document.getElementById('edit-staff-email').value  = s.email || '';
   clearEditWarnings('edit-staff');
   openModal('modal-edit-staff');
}

function validateEditStaff() {
  let ok = true;
  const name  = document.getElementById('edit-staff-name');
  const email = document.getElementById('edit-staff-email');
  if (!name.value.trim()) { setFieldWarn(name, document.getElementById('edit-warn-staff-name'), true); ok = false; }
  else setFieldWarn(name, document.getElementById('edit-warn-staff-name'), false);
  if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    setFieldWarn(email, document.getElementById('edit-warn-staff-email'), true); ok = false;
  } else setFieldWarn(email, document.getElementById('edit-warn-staff-email'), false);
  if (!ok) setLiveWarn('Please fix the highlighted fields.', 'edit-staff');
  return ok;
}

function setLiveWarn(msg = '', prefix = '') {
  const box = document.getElementById(prefix ? prefix + '-live-warning' : 'verify-live-warning');
  if (!box) return;
  if (msg) { box.textContent = msg; box.classList.add('show'); }
  else { box.textContent = ''; box.classList.remove('show'); }
}

function clearEditWarnings(prefix) {
  setLiveWarn('', prefix);
  const map = {
    'edit-doctor': ['edit-doctor-name','edit-doctor-email'],
    'edit-patient': ['edit-patient-name','edit-patient-email'],
    'edit-staff': ['edit-staff-name','edit-staff-email'],
  };
  (map[prefix] || []).forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('invalid');
  });
  const warnMap = {
    'edit-doctor': ['edit-warn-doctor-name','edit-warn-doctor-email'],
    'edit-patient': ['edit-warn-patient-name','edit-warn-patient-email'],
    'edit-staff': ['edit-warn-staff-name','edit-warn-staff-email'],
  };
  (warnMap[prefix] || []).forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
  });
}

// ── Audit log history ──
let auditPage = 0;
const AUDIT_PAGE_SIZE = 4;

function openAuditLogs() {
  auditPage = 0;
  renderAuditLogs();
  openModal('modal-audit-logs');
}

function renderAuditLogs() {
  const body = document.getElementById('audit-log-body');
  const pages = document.getElementById('audit-log-pages');
  if (!AUDIT_LOGS.length) {
    body.innerHTML = `<div class="empty-row" style="padding:2rem;">No account changes logged yet.</div>`;
    pages.innerHTML = '';
    return;
  }

  const totalPages = Math.ceil(AUDIT_LOGS.length / AUDIT_PAGE_SIZE);
  const start = auditPage * AUDIT_PAGE_SIZE;
  const pageItems = AUDIT_LOGS.slice(start, start + AUDIT_PAGE_SIZE);

  body.innerHTML = pageItems.map(function (l) {
    const admin = l.admin_name || 'System';
    const action = ucFirst(l.action);
    const entity = ucFirst(l.entity_type) + ' #' + l.id;
    const oldVals = l.old_values ? JSON.parse(l.old_values) : null;
    const newVals = l.new_values ? JSON.parse(l.new_values) : null;
    let diff = '';

        if (oldVals && newVals) {
      diff = '<div style="margin-top:0.5rem;font-size:0.78rem;color:#9ab0ae;">';
      for (const k in newVals) {
        if (String(oldVals[k]) !== String(newVals[k])) {
          diff += `<div><span style="color:var(--red);">− ${escHtml(fieldLabel(k))}:</span> ${escHtml(oldVals[k] ?? '')}</div>`;
          diff += `<div><span style="color:#16a34a;">+ ${escHtml(fieldLabel(k))}:</span> ${escHtml(newVals[k] ?? '')}</div>`;
        }
      }
      diff += '</div>';
    } else if (newVals) {
      diff = '<div style="margin-top:0.5rem;font-size:0.78rem;color:#9ab0ae;">';
      for (const k in newVals) {
        diff += `<div><span style="color:#16a34a;">+ ${escHtml(fieldLabel(k))}:</span> ${escHtml(newVals[k] ?? '')}</div>`;
      }
      diff += '</div>';
    }

    const badgeCls = l.action === 'create' ? 'badge-green' : (l.action === 'delete' ? 'badge-red' : (l.action === 'toggle' ? 'badge-orange' : 'badge-blue'));
    return `<div style="padding:0.5rem 0;border-bottom:1px solid rgba(36,68,65,0.06);">
      <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
        <span class="badge ${badgeCls}">${escHtml(action)}</span>
        <strong style="font-size:0.85rem;">${escHtml(entity)}</strong>
        <span style="margin-left:auto;font-size:0.74rem;color:#9ab0ae;">${fmtDate(l.created_at)} ${escHtml((l.created_at||'').slice(11))}</span>
      </div>
      <div style="font-size:0.82rem;margin-top:0.15rem;color:var(--green);">by <strong>${escHtml(admin)}</strong></div>
      ${diff}
    </div>`;
  }).join('');

  let pagesHtml = '';
  if (totalPages > 1) {
    pagesHtml += `<button class="btn-sm" style="background:rgba(36,68,65,0.1);color:var(--green);${auditPage > 0 ? '' : 'opacity:0.4;cursor:not-allowed;'}" ${auditPage > 0 ? '' : 'disabled'} onclick="changeAuditPage(-1)">Prev</button>`;
    pagesHtml += `<span style="font-size:0.78rem;color:#9ab0ae;font-weight:600;">Page ${auditPage + 1} of ${totalPages}</span>`;
    pagesHtml += `<button class="btn-sm" style="background:rgba(36,68,65,0.1);color:var(--green);${auditPage < totalPages - 1 ? '' : 'opacity:0.4;cursor:not-allowed;'}" ${auditPage < totalPages - 1 ? '' : 'disabled'} onclick="changeAuditPage(1)">Next</button>`;
  }
  pages.innerHTML = pagesHtml;
}

function changeAuditPage(delta) {
  auditPage += delta;
  renderAuditLogs();
}

// ── Copy setup link ──
function copyInvite() {
  const c = document.getElementById('inviteCode')?.textContent;
  if (c) { navigator.clipboard.writeText(c); alert('Setup link copied!'); }
}

// ── Doctor details modal ──
function openDetailsModal(id) {
  const u = ALL_USERS.find(x => x.type === 'doctor' && x.id == id);
  if (!u) return;
  const d = u.raw;

  const val = (v, fallback='—') => (v && String(v).trim() !== '') ? v : fallback;
  const emptySpan = (v, fallback='Not provided') =>
    (v && String(v).trim() !== '') ? `<span>${escHtml(v)}</span>` : `<span class="empty">${fallback}</span>`;

  document.getElementById('vd-avatar').textContent  = initials(d.full_name);
  document.getElementById('vd-name').textContent    = 'Dr. ' + val(d.full_name, 'Unknown');
  document.getElementById('vd-specialty').textContent = [d.specialty, d.subspecialty].filter(Boolean).join(' · ') || 'Specialty not set';

  const badgeEl = document.getElementById('vd-badges');
  let badges = '';
  const statusCls = d.status==='active'?'badge-green':d.status==='pending'?'badge-orange':'badge-gray';
  badges += `<span class="badge ${statusCls}">${ucFirst(d.status)}</span>`;
  if (d.is_verified)     badges += `<span class="badge badge-green">✓ Verified</span>`;
  if (!d.setup_complete) badges += `<span class="badge badge-orange">Setup pending</span>`;
  if (d.is_available)    badges += `<span class="badge badge-blue">Available</span>`;
  badgeEl.innerHTML = badges;

  document.getElementById('vd-email').innerHTML   = emptySpan(d.email);
  document.getElementById('vd-phone').innerHTML   = emptySpan(d.phone_number || d.phone);
  document.getElementById('vd-address').innerHTML = emptySpan(d.clinic_address || d.address);

  document.getElementById('vd-license').innerHTML    = emptySpan(d.license_number);
  document.getElementById('vd-board').innerHTML      = emptySpan(d.issuing_board);
  document.getElementById('vd-experience').innerHTML = d.years_experience ? `<span>${escHtml(d.years_experience)} year(s)</span>` : `<span class="empty">Not provided</span>`;
  document.getElementById('vd-fee').innerHTML = d.consultation_fee ? `<span>₱ ${parseFloat(d.consultation_fee).toLocaleString()}</span>` : `<span class="empty">Not set</span>`;
  document.getElementById('vd-bio').innerHTML = emptySpan(d.bio || d.about, 'No bio provided');

  const schedRows = d._schedules || [];
  let schedHtml = '';
  if (schedRows.length > 0) {
    schedRows.forEach(row => {
      const start = row.start_time || row.start || '';
      const end   = row.end_time   || row.end   || '';
      schedHtml += `<tr>
        <td class="sched-day">${escHtml(row.day_of_week || row.day || '')}</td>
        <td>${start ? escHtml(fmt12h(start)) : '<span class="sched-off">—</span>'}</td>
        <td>${end   ? escHtml(fmt12h(end))   : '<span class="sched-off">—</span>'}</td>
        <td><span class="badge badge-green" style="font-size:0.65rem;">Available</span></td>
      </tr>`;
    });
  }
  document.getElementById('vd-schedule-body').innerHTML =
    schedHtml || `<tr><td colspan="4" style="padding:1rem;text-align:center;color:#c0cece;font-style:italic;font-size:0.82rem;">No schedule set yet</td></tr>`;

  document.getElementById('vd-license-file').innerHTML =
    d.license_file ? `<a href="/${d.license_file}" target="_blank" class="doc-link">📄 View File</a>` : `<span class="empty">Not uploaded</span>`;
  document.getElementById('vd-cert-file').innerHTML =
    d.board_cert_file ? `<a href="/${d.board_cert_file}" target="_blank" class="doc-link">📄 View File</a>` : `<span class="empty">Not uploaded</span>`;
  document.getElementById('vd-photo').innerHTML =
    (d.profile_photo || d.photo) ? `<a href="/${d.profile_photo||d.photo}" target="_blank" class="doc-link">🖼 View Photo</a>` : `<span class="empty">Not uploaded</span>`;
  document.getElementById('vd-verified-at').innerHTML =
    d.verified_at ? `<span>${fmtDate(d.verified_at)}</span>` : `<span class="empty">Not yet verified</span>`;

  document.getElementById('vd-created').innerHTML   = `<span>${fmtDate(d.created_at)}</span>`;
  document.getElementById('vd-setup').innerHTML     = d.setup_complete ? `<span style="color:#16a34a;font-weight:700;">✓ Complete</span>` : `<span style="color:#d97706;font-weight:700;">Pending</span>`;
  document.getElementById('vd-available').innerHTML = d.is_available ? `<span style="color:#16a34a;font-weight:700;">Available</span>` : `<span style="color:#d97706;">Unavailable</span>`;

  openModal('modal-view-doctor');
}

// ── Patient details modal ──
function openPatientModal(id) {
  const u = ALL_USERS.find(x => x.type === 'patient' && x.id == id);
  if (!u) return;
  const p = u.raw;
  document.getElementById('vp-title').textContent = p.full_name || 'Patient Details';
  document.getElementById('vp-email').textContent = p.email || '—';
  document.getElementById('vp-phone').textContent = p.phone_number || '—';
  document.getElementById('vp-created').textContent = fmtDate(p.created_at);
  const isActive = u.status === 'active';
  document.getElementById('vp-status').innerHTML = isActive
    ? `<span style="color:#16a34a;font-weight:700;">Active</span>`
    : `<span style="color:var(--red);font-weight:700;">Deactivated</span>`;
  openModal('modal-view-patient');
}

// ── Staff details modal ──
function openStaffModal(id) {
  const u = ALL_USERS.find(x => x.type === 'staff' && x.id == id);
  if (!u) return;
  const s = u.raw;
  document.getElementById('vs-title').textContent = s.full_name || 'Staff Details';
  document.getElementById('vs-email').textContent = s.email || '—';
  document.getElementById('vs-created').textContent = fmtDate(s.created_at);
  const isActive = u.status === 'active';
  document.getElementById('vs-status').innerHTML = isActive
    ? `<span style="color:#16a34a;font-weight:700;">Active</span>`
    : `<span style="color:var(--red);font-weight:700;">Deactivated</span>`;
  openModal('modal-view-staff');
}

// ── Helpers ──
const FIELD_LABELS = {
  full_name: 'Full Name',
  email: 'Email',
  specialty: 'Specialty',
  subspecialty: 'Subspecialty',
  status: 'Status',
  is_active: 'Active Status',
  is_verified: 'Verified',
  license_number: 'License Number',
  issuing_board: 'Issuing Board',
  has_license_file: 'License File',
  has_cert_file: 'Certificate File',
};
function fieldLabel(key) {
  if (FIELD_LABELS[key]) return FIELD_LABELS[key];
  return key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(str) { return escHtml(str).replace(/'/g, "\\'"); }
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function fmtDate(s) {
  if (!s) return '—';
  const d = new Date(s);
  if (isNaN(d)) return s;
  return d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}
function fmt12h(t) {
  if (!t) return '';
  const [h,m] = t.split(':').map(Number);
  if (isNaN(h)) return t;
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hr = h % 12 || 12;
  return `${hr}:${String(m||0).padStart(2,'0')} ${ampm}`;
}

// ── Init ──
renderRows(ALL_USERS);
setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);
</script>
</body>
</html>