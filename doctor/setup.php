<?php
require_once '../database/config.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

$doctor = null;
if ($token) {
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE invite_token = ? AND invite_expires > NOW() AND setup_complete = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

if (!$doctor) {
    $error = 'This invite link is invalid or has expired. Please contact your administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $bio      = trim($_POST['bio'] ?? '');
    $fee      = floatval($_POST['consultation_fee'] ?? 0);
    $langs    = trim($_POST['languages_spoken'] ?? '');
    $clinic   = trim($_POST['clinic_name'] ?? '');
    $phone    = trim($_POST['phone_number'] ?? '');
    $consent  = isset($_POST['consent']) ? 1 : 0;

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$consent) {
        $error = 'You must agree to the telehealth consent agreement.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $photo_path = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $dir = '../uploads/profiles/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext   = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $fname = uniqid('doc_') . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $fname)) {
                $photo_path = 'uploads/profiles/' . $fname;
            }
        }

        // FIX 1: wrap prepare() in a false-check so you see the real DB error
        $stmt = $conn->prepare(
            "UPDATE doctors SET
                password         = ?,
                bio              = ?,
                consultation_fee = ?,
                languages_spoken = ?,
                clinic_name      = ?,
                phone_number     = ?,
                profile_photo    = COALESCE(?, profile_photo),
                consent_signed   = ?,
                invite_token     = NULL,
                setup_complete   = 1,
                status           = 'active',
                is_available     = 1
             WHERE id = ?"
        );

        if ($stmt === false) {
            // FIX 2: expose the real MySQL error instead of crashing
            $error = 'Database prepare error: ' . $conn->error;
        } else {
            // FIX 3: corrected type string — photo_path is 's' not 'i'
            // old: "ssdsssiii"  (wrong: last 3 as iii but photo_path is string)
            // new: "ssdssssii"  (s=hashed s=bio d=fee s=langs s=clinic s=phone s=photo i=consent i=id)
            $stmt->bind_param("ssdssssii",
                $hashed, $bio, $fee, $langs, $clinic, $phone,
                $photo_path, $consent, $doctor['id']
            );

            if ($stmt->execute()) {
                if (!empty($_POST['day'])) {
                    foreach ($_POST['day'] as $i => $day) {
                        $start = $_POST['start_time'][$i] ?? '';
                        $end   = $_POST['end_time'][$i]   ?? '';
                        if ($day && $start && $end) {
                            $ss = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time) VALUES (?,?,?,?)");
                            if ($ss) {
                                $ss->bind_param("isss", $doctor['id'], $day, $start, $end);
                                $ss->execute();
                            }
                        }
                    }
                }
                $success = true;
            } else {
                $error = 'Execute error: ' . $stmt->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Complete Your Profile — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);min-height:100vh;display:flex}
    h1,h2{font-family:'Playfair Display',serif}
    .left-panel{width:40%;background:linear-gradient(160deg,var(--green) 0%,#1a3330 100%);display:flex;flex-direction:column;justify-content:center;padding:3rem;position:sticky;top:0;height:100vh;overflow:hidden}
    .left-panel::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(63,130,227,0.08) 1px,transparent 1px),linear-gradient(90deg,rgba(63,130,227,0.08) 1px,transparent 1px);background-size:44px 44px;animation:gridMove 20s linear infinite}
    @keyframes gridMove{from{transform:translateY(0)}to{transform:translateY(44px)}}
    .orb{position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;animation:pulse 6s ease-in-out infinite}
    @keyframes pulse{0%,100%{transform:scale(1);opacity:.7}50%{transform:scale(1.2);opacity:1}}
    .right-panel{flex:1;overflow-y:auto;padding:3rem 4%}
    .form-wrap{max-width:520px;margin:0 auto}
    .field-label{display:block;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#5a7a77;margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.78rem 1rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none;transition:border-color 0.2s;background:var(--white)}
    .field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,0.1)}
    textarea.field-input{resize:vertical;min-height:80px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:0.8rem}
    .form-field{margin-bottom:0.9rem}
    .section-label{font-size:0.72rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#9ab0ae;border-bottom:1px solid rgba(36,68,65,0.1);padding-bottom:0.5rem;margin:1.5rem 0 1rem}
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0}
    .pw-toggle:hover{color:var(--green)}
    .btn-submit{width:100%;padding:0.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.95rem;border:none;cursor:pointer;transition:all 0.3s;box-shadow:0 6px 18px rgba(195,54,67,0.25);margin-top:0.5rem;font-family:'DM Sans',sans-serif}
    .btn-submit:hover{background:#a82d38;transform:translateY(-2px)}
    .alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.2);color:var(--red);border-radius:12px;padding:0.75rem 1rem;font-size:0.86rem;margin-bottom:1.2rem}
    .schedule-row{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:0.6rem;align-items:center;margin-bottom:0.6rem}
    .btn-add-row{display:inline-flex;align-items:center;gap:0.3rem;font-size:0.8rem;color:var(--blue);font-weight:600;background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif}
    .btn-remove-row{background:rgba(195,54,67,0.1);color:var(--red);border:none;border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center}
    .consent-box{background:rgba(36,68,65,0.05);border:1px solid rgba(36,68,65,0.1);border-radius:14px;padding:1rem 1.2rem;margin:1rem 0;font-size:0.82rem;color:#6b8a87;line-height:1.65}
    @media(max-width:768px){.left-panel{display:none}}
  </style>
</head>
<body>

<div class="left-panel">
  <div class="orb" style="width:300px;height:300px;background:radial-gradient(circle,rgba(63,130,227,0.2) 0%,transparent 70%);top:-60px;right:-60px;"></div>
  <div class="orb" style="width:200px;height:200px;background:radial-gradient(circle,rgba(195,54,67,0.15) 0%,transparent 70%);bottom:60px;left:20px;animation-delay:3s;"></div>
  <div style="position:relative;z-index:2;">
    <div style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:#fff;letter-spacing:0.04em;">TELE<span style="color:var(--red)">-</span>CARE</div>
    <div style="margin-top:3rem;">
      <h1 style="font-size:2rem;color:#fff;line-height:1.2;margin-bottom:0.8rem;">Complete Your<br/>Doctor Profile</h1>
      <p style="color:rgba(255,255,255,0.55);font-size:0.88rem;line-height:1.75;">You've been invited to join TELE-CARE. Set your password, fill in your profile, and configure your availability to go live.</p>
    </div>
    <?php if ($doctor): ?>
    <div style="margin-top:2.5rem;background:rgba(255,255,255,0.07);border-radius:16px;padding:1.2rem;">
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.4);margin-bottom:0.5rem;">Your Account</div>
      <div style="font-weight:700;color:#fff;font-size:1rem;">Dr. <?= htmlspecialchars($doctor['full_name']) ?></div>
      <div style="font-size:0.8rem;color:rgba(255,255,255,0.5);margin-top:0.2rem;"><?= htmlspecialchars($doctor['email']) ?></div>
      <?php if ($doctor['specialty']): ?>
      <div style="margin-top:0.5rem;"><span style="background:rgba(255,255,255,0.1);border-radius:50px;padding:0.25rem 0.7rem;font-size:0.75rem;color:rgba(255,255,255,0.7);"><?= htmlspecialchars($doctor['specialty']) ?></span></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="right-panel">
  <div class="form-wrap">

    <?php if ($success): ?>
    <div style="text-align:center;padding:3rem 1rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">🎉</div>
      <h2 style="font-size:1.8rem;margin-bottom:0.6rem;">You're all set!</h2>
      <p style="color:#6b8a87;font-size:0.95rem;line-height:1.7;margin-bottom:2rem;">Your profile is complete and your account is now active.</p>
      <a href="../auth/login.php" style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--red);color:#fff;padding:0.9rem 2.5rem;border-radius:50px;font-weight:700;text-decoration:none;box-shadow:0 6px 20px rgba(195,54,67,0.3);">Go to Login →</a>
    </div>

    <?php elseif ($error && !$doctor): ?>
    <div style="text-align:center;padding:3rem 1rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">⚠️</div>
      <h2 style="font-size:1.5rem;margin-bottom:0.6rem;">Invalid Invite</h2>
      <p style="color:#6b8a87;font-size:0.9rem;"><?= htmlspecialchars($error) ?></p>
      <a href="../index.php" style="display:inline-block;margin-top:1.5rem;font-size:0.85rem;color:var(--blue);">← Back to home</a>
    </div>

    <?php else: ?>

    <div style="margin-bottom:2rem;">
      <h2 style="font-size:1.6rem;margin-bottom:0.3rem;">Set Up Your Profile</h2>
      <p style="color:#6b8a87;font-size:0.88rem;">Fill in everything below. Fields marked * are required.</p>
    </div>

    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

      <div class="section-label">Set Your Password</div>
      <div class="form-field">
        <label class="field-label">Password *</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pw" class="field-input" placeholder="At least 8 characters" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pw','e1s','e1h')">
            <svg id="e1s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="e1h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Confirm Password *</label>
        <div class="pw-wrap">
          <input type="password" name="confirm_password" id="pw2" class="field-input" placeholder="Repeat password" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pw2','e2s','e2h')">
            <svg id="e2s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="e2h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
        <div id="pw-match" style="font-size:0.75rem;margin-top:0.3rem;"></div>
      </div>

      <div class="section-label">Profile Info</div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Phone Number</label><input type="tel" name="phone_number" class="field-input" placeholder="09XXXXXXXXX"/></div>
        <div class="form-field"><label class="field-label">Clinic / Hospital</label><input type="text" name="clinic_name" class="field-input" placeholder="Clinic name"/></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Consultation Fee (₱)</label><input type="number" name="consultation_fee" class="field-input" placeholder="e.g. 500" min="0" step="0.01"/></div>
        <div class="form-field"><label class="field-label">Languages Spoken</label><input type="text" name="languages_spoken" class="field-input" placeholder="e.g. English, Filipino"/></div>
      </div>
      <div class="form-field"><label class="field-label">Profile Photo <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(optional)</span></label><input type="file" name="profile_photo" class="field-input" accept="image/*" style="padding:0.5rem;"/></div>
      <div class="form-field"><label class="field-label">Bio / About</label><textarea name="bio" class="field-input" placeholder="Brief professional background..."></textarea></div>

      <div class="section-label">Availability &amp; Schedule</div>
      <div id="schedule-rows">
        <div class="schedule-row">
          <select name="day[]" class="field-input">
            <option value="">Day</option>
            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?>
            <option value="<?= $d ?>"><?= $d ?></option>
            <?php endforeach; ?>
          </select>
          <input type="time" name="start_time[]" class="field-input"/>
          <input type="time" name="end_time[]"   class="field-input"/>
          <div></div>
        </div>
      </div>
      <button type="button" class="btn-add-row" onclick="addScheduleRow()">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Add another day
      </button>

      <div class="section-label">Platform Consent</div>
      <div class="consent-box">
        By checking the box below, I agree to provide telehealth consultation services through TELE-CARE in accordance with applicable medical practice standards, patient privacy regulations, and the platform's terms of service. I confirm that all information I have provided is accurate.
      </div>
      <div style="display:flex;align-items:flex-start;gap:0.7rem;margin-bottom:1.5rem;">
        <input type="checkbox" name="consent" id="consent" style="width:18px;height:18px;margin-top:2px;accent-color:var(--green);flex-shrink:0;" required/>
        <label for="consent" style="font-size:0.85rem;color:var(--green);cursor:pointer;line-height:1.5;">I have read and agree to the telehealth consent agreement and platform terms.</label>
      </div>

      <button type="submit" class="btn-submit">Complete My Profile</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<script>
  function togglePw(fid,sid,hid){const f=document.getElementById(fid),s=document.getElementById(sid),h=document.getElementById(hid);if(f.type==='password'){f.type='text';s.style.display='none';h.style.display='block';}else{f.type='password';s.style.display='block';h.style.display='none';}}
  document.getElementById('pw2')?.addEventListener('input',function(){const m=document.getElementById('pw-match');if(this.value===document.getElementById('pw').value){m.textContent='✓ Passwords match';m.style.color='var(--green)';}else{m.textContent='✗ Does not match';m.style.color='var(--red)';}});
  const days=['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
  function addScheduleRow(){const wrap=document.getElementById('schedule-rows');const row=document.createElement('div');row.className='schedule-row';row.innerHTML=`<select name="day[]" class="field-input"><option value="">Day</option>${days.map(d=>`<option value="${d}">${d}</option>`).join('')}</select><input type="time" name="start_time[]" class="field-input"/><input type="time" name="end_time[]" class="field-input"/><button type="button" class="btn-remove-row" onclick="this.parentElement.remove()">✕</button>`;wrap.appendChild(row);}
</script>
</body>
</html>