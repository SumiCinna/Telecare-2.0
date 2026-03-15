<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_doctor'])) {
    $fn   = trim($_POST['full_name'] ?? '');
    $em   = trim($_POST['email'] ?? '');
    $spec = trim($_POST['specialty'] ?? '');
    $sub  = trim($_POST['subspecialty'] ?? '');
    if ($fn && $em) {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $stmt = $conn->prepare("INSERT INTO doctors (full_name, email, specialty, subspecialty, invite_token, invite_expires, status) VALUES (?,?,?,?,?,?,'pending')");
        $stmt->bind_param("ssssss", $fn, $em, $spec, $sub, $token, $expires);
        $stmt->execute();
        $_SESSION['toast']        = "Doctor account created! Invite link generated.";
        $_SESSION['invite_link']  = 'http://' . $_SERVER['HTTP_HOST'] . '/doctor/setup.php?token=' . $token;
        $_SESSION['invite_email'] = $em;
        $_SESSION['invite_name']  = $fn;
    }
    header('Location: doctors.php'); exit;
}

if (isset($_GET['toggle_doctor'])) {
    $did = (int)$_GET['toggle_doctor'];
    $conn->query("UPDATE doctors SET status = IF(status='active','inactive','active'), is_available = IF(status='inactive',1,0) WHERE id=$did");
    header('Location: doctors.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_doctor'])) {
    $did     = (int)$_POST['doctor_id'];
    $license = trim($_POST['license_number'] ?? '');
    $board   = trim($_POST['issuing_board'] ?? '');
    $now     = date('Y-m-d H:i:s');
    $license_file = null; $cert_file = null;
    $upload_dir = '../uploads/docs/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    foreach (['license_file' => 'license_file', 'board_cert_file' => 'board_cert_file'] as $input => $col) {
        if (!empty($_FILES[$input]['name'])) {
            $ext = pathinfo($_FILES[$input]['name'], PATHINFO_EXTENSION);
            $fname = uniqid("doc_{$did}_") . '.' . $ext;
            if (move_uploaded_file($_FILES[$input]['tmp_name'], $upload_dir . $fname)) { $$col = 'uploads/docs/' . $fname; }
        }
    }
    $stmt = $conn->prepare("UPDATE doctors SET license_number=?, issuing_board=?, license_file=COALESCE(?,license_file), board_cert_file=COALESCE(?,board_cert_file), is_verified=1, verified_at=?, verified_by=? WHERE id=?");
    $stmt->bind_param("sssssii", $license, $board, $license_file, $cert_file, $now, $admin_id, $did);
    $stmt->execute();
    $_SESSION['toast'] = "Doctor verified successfully.";
    header('Location: doctors.php'); exit;
}

$toast        = $_SESSION['toast'] ?? null;
$invite_link  = $_SESSION['invite_link'] ?? null;
$invite_email = $_SESSION['invite_email'] ?? null;
$invite_name  = $_SESSION['invite_name'] ?? null;
unset($_SESSION['toast'], $_SESSION['invite_link'], $_SESSION['invite_email'], $_SESSION['invite_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Doctors — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
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
    .btn-primary{display:inline-flex;align-items:center;gap:0.4rem;background:var(--red);color:#fff;padding:0.6rem 1.3rem;border-radius:50px;font-size:0.85rem;font-weight:600;border:none;cursor:pointer;transition:all 0.25s;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(195,54,67,0.25);text-decoration:none}
    .btn-primary:hover{background:#a82d38;transform:translateY(-1px)}
    .btn-sm{padding:0.4rem 0.9rem;border-radius:50px;font-size:0.78rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-decoration:none;display:inline-block}
    .btn-green{background:rgba(36,68,65,0.1);color:var(--green)}.btn-green:hover{background:var(--green);color:#fff}
    .btn-red{background:rgba(195,54,67,0.1);color:var(--red)}.btn-red:hover{background:var(--red);color:#fff}
    .btn-blue{background:rgba(63,130,227,0.1);color:var(--blue)}.btn-blue:hover{background:var(--blue);color:#fff}
    .doctor-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.2rem}
    .doctor-card{background:var(--white);border-radius:16px;padding:1.4rem;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);transition:transform 0.2s,box-shadow 0.2s}
    .doctor-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,0.08)}
    .doc-avatar{width:48px;height:48px;border-radius:14px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0}
    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-red{background:rgba(195,54,67,0.1);color:var(--red)}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-blue{background:rgba(63,130,227,0.1);color:var(--blue)}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1rem;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.3rem;margin-bottom:1.2rem}
    .field-label{display:block;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none;transition:border-color 0.2s}
    .field-input:focus{border-color:var(--blue)}
    select.field-input{cursor:pointer}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem}
    .form-field{margin-bottom:0.9rem}
    .btn-submit{width:100%;padding:0.85rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.93rem;border:none;cursor:pointer;margin-top:0.5rem;transition:all 0.25s;font-family:'DM Sans',sans-serif}
    .btn-submit:hover{background:#a82d38}
    .btn-cancel{width:100%;padding:0.7rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:0.88rem;border:1.5px solid rgba(36,68,65,0.15);cursor:pointer;margin-top:0.5rem;font-family:'DM Sans',sans-serif}
    .invite-box{background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);border-radius:14px;padding:1rem 1.2rem;margin-bottom:1.5rem}
    .invite-box p{font-size:0.78rem;color:var(--blue);font-weight:600;margin-bottom:0.5rem}
    .invite-box code{font-size:0.75rem;word-break:break-all;color:var(--green);background:rgba(36,68,65,0.06);padding:0.4rem 0.6rem;border-radius:8px;display:block}
    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    .empty-row{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}
    .spinner-inline{display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:5px}
    @keyframes spin{to{transform:rotate(360deg)}}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-admin">Admin Portal<br/><strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></strong></div>
  <nav class="nav-links">
    <a href="dashboard.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="doctors.php" class="nav-link active">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Doctors
    </a>
    <a href="patients.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patients
    </a>
    <a href="assignments.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Assignments
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
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Doctors</div>
    </div>
    <button class="btn-primary" onclick="openModal('modal-create-doctor')">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Add Doctor
    </button>
  </div>

  <div class="page-content">

    <!-- Initialize EmailJS globally -->
    <script>
      const EMAILJS_PUBLIC_KEY       = 'm-AvAiAdUDsgBbz6D';
      const EMAILJS_SERVICE_ID       = 'service_vr6ygvx';
      const EMAILJS_INVITE_TEMPLATE  = 'template_hv6nkmj';
      emailjs.init(EMAILJS_PUBLIC_KEY);
    </script>

    <?php if ($invite_link): ?>
    <div class="invite-box">
      <p>📧 Invite email sent to <strong><?= htmlspecialchars($invite_email) ?></strong> — link also shown below:</p>
      <code id="inviteCode"><?= htmlspecialchars($invite_link) ?></code>
      <button onclick="copyInvite()" style="margin-top:0.5rem;font-size:0.75rem;color:var(--blue);background:none;border:none;cursor:pointer;font-weight:600;">Copy link</button>
    </div>

    <!-- Auto-fire invite email -->
    <script>
      const doctorEmail = <?= json_encode($invite_email) ?>;
      const doctorName  = <?= json_encode($invite_name) ?>;
      const inviteURL   = <?= json_encode($invite_link) ?>;

      emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_INVITE_TEMPLATE, {
        to_email:    doctorEmail,
        doctor_name: doctorName,
        invite_link: inviteURL,
      }).then(() => {
        console.log('Doctor invite email sent to ' + doctorEmail);
      }).catch(err => {
        console.error('EmailJS invite error:', err);
      });
    </script>
    <?php endif; ?>

    <div class="doctor-grid">
    <?php
    $dres = $conn->query("SELECT d.*, (SELECT COUNT(*) FROM patient_doctors WHERE doctor_id=d.id) AS patient_count FROM doctors d ORDER BY d.created_at DESC");
    if ($dres && $dres->num_rows > 0):
      while ($d = $dres->fetch_assoc()):
        $initials = strtoupper(substr($d['full_name'],0,1).(strpos($d['full_name'],' ')!==false?substr($d['full_name'],strpos($d['full_name'],' ')+1,1):''));
    ?>
    <div class="doctor-card">
      <div style="display:flex;align-items:flex-start;gap:0.9rem;margin-bottom:1rem;">
        <div class="doc-avatar"><?= $initials ?></div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:0.95rem;">Dr. <?= htmlspecialchars($d['full_name']) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($d['specialty'] ?? 'General') ?></div>
          <?php if ($d['subspecialty']): ?><div style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($d['subspecialty']) ?></div><?php endif; ?>
        </div>
        <span class="badge <?= $d['status']==='active'?'badge-green':($d['status']==='pending'?'badge-orange':'badge-gray') ?>"><?= ucfirst($d['status']) ?></span>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        <span class="badge badge-blue">👥 <?= $d['patient_count'] ?> patients</span>
        <?php if ($d['is_verified']): ?><span class="badge badge-green">✓ Verified</span><?php endif; ?>
        <?php if (!$d['setup_complete']): ?><span class="badge badge-orange">Setup pending</span><?php endif; ?>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <a href="?toggle_doctor=<?= $d['id'] ?>" class="btn-sm <?= $d['status']==='active'?'btn-red':'btn-green' ?>"><?= $d['status']==='active'?'Deactivate':'Activate' ?></a>
        <?php if (!$d['is_verified']): ?>
        <button class="btn-sm btn-blue" onclick="openVerifyModal(<?= $d['id'] ?>, '<?= htmlspecialchars($d['full_name']) ?>')">Verify</button>
        <?php endif; ?>
        <?php if (!$d['setup_complete'] && $d['invite_token']): ?>
        <button class="btn-sm btn-green" onclick="resendInvite('<?= htmlspecialchars($d['invite_token']) ?>','<?= htmlspecialchars($d['email']) ?>','<?= htmlspecialchars($d['full_name']) ?>')" id="resend-<?= $d['id'] ?>">Resend Invite</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endwhile; else: ?>
    <div style="grid-column:1/-1;" class="table-wrap"><div class="empty-row">No doctors registered yet. Click "Add Doctor" to get started.</div></div>
    <?php endif; ?>
    </div>

  </div>
</div>

<!-- MODAL: Create Doctor -->
<div class="modal-overlay" id="modal-create-doctor">
  <div class="modal">
    <h3>Add Doctor — Phase 1</h3>
    <p style="font-size:0.82rem;color:#9ab0ae;margin-bottom:1.2rem;">Fill in the doctor's details. An invite link will be generated and emailed to them automatically.</p>
    <form method="POST">
      <div class="form-field"><label class="field-label">Full Name *</label><input type="text" name="full_name" class="field-input" placeholder="e.g. Maria Santos" required/></div>
      <div class="form-field"><label class="field-label">Email Address *</label><input type="email" name="email" class="field-input" placeholder="doctor@email.com" required/></div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Specialty</label><input type="text" name="specialty" class="field-input" placeholder="e.g. Cardiology"/></div>
        <div class="form-field"><label class="field-label">Subspecialty</label><input type="text" name="subspecialty" class="field-input" placeholder="Optional"/></div>
      </div>
      <button type="submit" name="create_doctor" class="btn-submit">Create &amp; Send Invite Email</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-create-doctor')">Cancel</button>
    </form>
  </div>
</div>

<!-- MODAL: Verify Doctor -->
<div class="modal-overlay" id="modal-verify-doctor">
  <div class="modal">
    <h3>Verify Doctor — Phase 2</h3>
    <p style="font-size:0.82rem;color:#9ab0ae;margin-bottom:1.2rem;">Log the doctor's license info and upload documents.</p>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="doctor_id" id="verify-doctor-id"/>
      <div class="form-field"><label class="field-label">Doctor</label><input type="text" id="verify-doctor-name" class="field-input" disabled/></div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">License Number</label><input type="text" name="license_number" class="field-input" placeholder="PRC / License No."/></div>
        <div class="form-field"><label class="field-label">Issuing Board</label><input type="text" name="issuing_board" class="field-input" placeholder="e.g. PRC, PMA"/></div>
      </div>
      <div class="form-field"><label class="field-label">License File <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(PDF/Image)</span></label><input type="file" name="license_file" class="field-input" accept=".pdf,.jpg,.jpeg,.png" style="padding:0.5rem;"/></div>
      <div class="form-field"><label class="field-label">Board Certification <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(PDF/Image)</span></label><input type="file" name="board_cert_file" class="field-input" accept=".pdf,.jpg,.jpeg,.png" style="padding:0.5rem;"/></div>
      <button type="submit" name="verify_doctor" class="btn-submit">Mark as Verified</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-verify-doctor')">Cancel</button>
    </form>
  </div>
</div>

<script>
  // ── Modal helpers ──
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
  });
  function openVerifyModal(id, name) {
    document.getElementById('verify-doctor-id').value = id;
    document.getElementById('verify-doctor-name').value = 'Dr. ' + name;
    openModal('modal-verify-doctor');
  }

  // ── Copy invite link ──
  function copyInvite() {
    const c = document.getElementById('inviteCode')?.textContent;
    if (c) { navigator.clipboard.writeText(c); alert('Invite link copied!'); }
  }

  // ── Resend invite email for existing doctor ──
  function resendInvite(token, email, name) {
    const btn = document.querySelector(`[onclick*="'${token}'"]`);
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-inline"></span>Sending...'; }

    const link = window.location.origin + '/doctor/setup.php?token=' + token;

    emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_INVITE_TEMPLATE, {
      to_email:    email,
      doctor_name: name,
      invite_link: link,
    }).then(() => {
      if (btn) { btn.disabled = false; btn.innerHTML = '✓ Sent!'; setTimeout(() => btn.innerHTML = 'Resend Invite', 2000); }
    }).catch(() => {
      if (btn) { btn.disabled = false; btn.innerHTML = 'Resend Invite'; alert('Failed to send. Check EmailJS keys.'); }
    });
  }

  setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);
</script>
</body>
</html>