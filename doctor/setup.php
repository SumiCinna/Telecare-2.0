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

    // --- Schedule overlap validation (server-side) ---
    $schedule_error = '';
    if (!empty($_POST['day'])) {
        $slots = [];
        foreach ($_POST['day'] as $i => $day) {
            $start = $_POST['start_time'][$i] ?? '';
            $end   = $_POST['end_time'][$i]   ?? '';
            if (!$day || !$start || !$end) continue;

            if ($start >= $end) {
                $schedule_error = "Schedule error: Start time must be before end time for $day.";
                break;
            }

            foreach ($slots as $existing) {
                if ($existing['day'] === $day) {
                    // Overlap check: two ranges [s1,e1) and [s2,e2) overlap if s1 < e2 AND s2 < e1
                    if ($start < $existing['end'] && $existing['start'] < $end) {
                        $schedule_error = "Schedule conflict: $day has overlapping time slots ("
                            . date('h:i A', strtotime($start)) . "–" . date('h:i A', strtotime($end))
                            . " conflicts with "
                            . date('h:i A', strtotime($existing['start'])) . "–" . date('h:i A', strtotime($existing['end']))
                            . ").";
                        break 2;
                    }
                }
            }

            $slots[] = ['day' => $day, 'start' => $start, 'end' => $end];
        }
    }

    if ($schedule_error) {
        $error = $schedule_error;
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 50) {
        $error = 'Password must not exceed 50 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($confirm) > 50) {
        $error = 'Confirm password must not exceed 50 characters.';
    } elseif (!empty($phone) && !preg_match('/^\d{11}$/', $phone)) {
        $error = 'Phone number must be exactly 11 digits.';
    } elseif (strlen($clinic) > 100) {
        $error = 'Clinic name must not exceed 100 characters.';
    } elseif ($fee > 99999) {
        $error = 'Consultation fee must not exceed 99999.';
    } elseif (strlen($langs) > 50) {
        $error = 'Languages spoken must not exceed 50 characters.';
    } elseif (strlen($bio) > 100) {
        $error = 'Bio must not exceed 100 characters.';
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
            $error = 'Database prepare error: ' . $conn->error;
        } else {
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
    .schedule-row.conflict .field-input{border-color:var(--red)!important;background:rgba(195,54,67,0.04)}
    .btn-add-row{display:inline-flex;align-items:center;gap:0.3rem;font-size:0.8rem;color:var(--blue);font-weight:600;background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif}
    .btn-remove-row{background:rgba(195,54,67,0.1);color:var(--red);border:none;border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center}
    .schedule-conflict-msg{font-size:0.75rem;color:var(--red);margin-top:0.4rem;margin-bottom:0.6rem;display:none}
    .schedule-conflict-msg.visible{display:block}
    .consent-box{background:rgba(36,68,65,0.05);border:1px solid rgba(36,68,65,0.1);border-radius:14px;padding:1rem 1.2rem;margin:1rem 0;font-size:0.82rem;color:#6b8a87;line-height:1.65}
    input[type="number"]::-webkit-outer-spin-button,input[type="number"]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0;}
    input[type="number"]{-moz-appearance:textfield;}
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
      <a href="../doctor/login.php" style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--red);color:#fff;padding:0.9rem 2.5rem;border-radius:50px;font-weight:700;text-decoration:none;box-shadow:0 6px 20px rgba(195,54,67,0.3);">Go to Login →</a>
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

    <form method="POST" enctype="multipart/form-data" id="setup-form">

      <div class="section-label">Set Your Password</div>
      <div class="form-field">
        <label class="field-label">Password *</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pw" class="field-input" placeholder="At least 8 characters" maxlength="50" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pw','e1s','e1h')">
            <svg id="e1s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="e1h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.405 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Confirm Password *</label>
        <div class="pw-wrap">
          <input type="password" name="confirm_password" id="pw2" class="field-input" placeholder="Repeat password" maxlength="50" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pw2','e2s','e2h')">
            <svg id="e2s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="e2h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.405 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
        <div id="pw-match" style="font-size:0.75rem;margin-top:0.3rem;"></div>
      </div>

      <div class="section-label">Profile Info</div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Phone Number <span style="font-weight:400;font-size:0.65rem;">(11 digits)</span></label><input type="tel" name="phone_number" class="field-input" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric" onInput="this.value=this.value.replace(/[^0-9]/g,'')"/></div>
        <div class="form-field"><label class="field-label">Clinic / Hospital</label><input type="text" name="clinic_name" class="field-input" placeholder="Clinic name" maxlength="100"/></div>
      </div>
      <div class="form-row">
        <div class="form-field"><label class="field-label">Consultation Fee <span style="font-weight:400;font-size:0.65rem;">(₱)</span></label><input type="number" name="consultation_fee" class="field-input" placeholder="e.g. 500" min="0" max="99999" step="0.01" onInput="this.value=this.value.slice(0,5)"/></div>
        <div class="form-field"><label class="field-label">Languages Spoken</label><input type="text" name="languages_spoken" class="field-input" placeholder="e.g. English, Filipino" maxlength="50"/></div>
      </div>
      <div class="form-field"><label class="field-label">Profile Photo <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(optional)</span></label><input type="file" name="profile_photo" class="field-input" accept="image/*" style="padding:0.5rem;"/></div>
      <div class="form-field"><label class="field-label">Bio / About</label><textarea name="bio" class="field-input" placeholder="Brief professional background..." maxlength="100"></textarea></div>

      <div class="section-label">Availability &amp; Schedule</div>
      <p style="font-size:0.82rem;color:#6b8a87;margin-bottom:1rem;">
        Select your availability per day. Times are fixed to 1-hour intervals.
        You can add multiple slots for the same day as long as they don't overlap.
      </p>
      <div id="schedule-rows"></div>
      <div id="schedule-conflict-msg" class="schedule-conflict-msg"></div>
      <button type="button" class="btn-add-row" id="btn-add-row" onclick="addScheduleRow()">
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
  // ── Password toggle ────────────────────────────────────────────────────────
  function togglePw(fid, sid, hid) {
    const f = document.getElementById(fid),
          s = document.getElementById(sid),
          h = document.getElementById(hid);
    if (f.type === 'password') { f.type = 'text';     s.style.display = 'none';  h.style.display = 'block'; }
    else                       { f.type = 'password'; s.style.display = 'block'; h.style.display = 'none';  }
  }
  document.getElementById('pw2')?.addEventListener('input', function () {
    const m = document.getElementById('pw-match');
    if (this.value === document.getElementById('pw').value) {
      m.textContent = '✓ Passwords match'; m.style.color = 'var(--green)';
    } else {
      m.textContent = '✗ Does not match';  m.style.color = 'var(--red)';
    }
  });

  // ── Time slot helpers ──────────────────────────────────────────────────────
  const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

  function generateTimeOptions() {
    let html = '<option value="">Select time</option>';
    for (let h = 0; h < 24; h++) {
      const val     = String(h).padStart(2, '0') + ':00';
      const display = new Date(`2000-01-01T${val}:00`).toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
      html += `<option value="${val}">${display}</option>`;
    }
    return html;
  }
  const TIME_OPTIONS = generateTimeOptions();

  function fmt(t) {
    return new Date(`2000-01-01T${t}:00`).toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  }

  // ── Core validation ────────────────────────────────────────────────────────
  // Returns true if schedule is fully valid, false otherwise.
  // Also applies red highlights and sets the conflict message element.
  function revalidateSchedule() {
    const rows  = document.querySelectorAll('#schedule-rows .schedule-row');
    const msgEl = document.getElementById('schedule-conflict-msg');

    // Reset all highlights
    rows.forEach(r => r.classList.remove('conflict'));
    msgEl.textContent = '';
    msgEl.classList.remove('visible');

    const filled = []; // only rows that are fully filled AND individually valid

    // ── Pass 1: per-row check (end must be after start) ──────────────────────
    let hasRowError = false;
    rows.forEach((row, idx) => {
      const d = row.querySelector('[name="day[]"]').value;
      const s = row.querySelector('[name="start_time[]"]').value;
      const e = row.querySelector('[name="end_time[]"]').value;

      if (!d || !s || !e) return; // incomplete row — skip silently

      if (e <= s) {
        // End time is same as or before start time
        row.classList.add('conflict');
        if (!hasRowError) {
          msgEl.textContent = `⚠ End time must be after start time on ${d} (${fmt(s)} → ${fmt(e)} is invalid).`;
          msgEl.classList.add('visible');
          hasRowError = true;
        }
        return; // don't add to filled — can't be used in overlap check
      }

      filled.push({ day: d, start: s, end: e, idx });
    });

    if (hasRowError) return false;

    // ── Pass 2: overlap check across all valid filled rows ───────────────────
    for (let i = 0; i < filled.length; i++) {
      for (let j = i + 1; j < filled.length; j++) {
        const a = filled[i], b = filled[j];
        if (a.day === b.day && a.start < b.end && b.start < a.end) {
          rows[a.idx].classList.add('conflict');
          rows[b.idx].classList.add('conflict');
          msgEl.textContent = `⚠ Conflict on ${a.day}: ${fmt(a.start)}–${fmt(a.end)} overlaps with ${fmt(b.start)}–${fmt(b.end)}.`;
          msgEl.classList.add('visible');
          return false;
        }
      }
    }

    return true; // all good
  }

  // ── Add / remove schedule rows ─────────────────────────────────────────────
  function addScheduleRow() {
    const wrap = document.getElementById('schedule-rows');
    const row  = document.createElement('div');
    row.className = 'schedule-row';
    row.innerHTML = `
      <select name="day[]" class="field-input">
        <option value="">Day</option>
        ${DAYS.map(d => `<option value="${d}">${d}</option>`).join('')}
      </select>
      <select name="start_time[]" class="field-input">${TIME_OPTIONS}</select>
      <select name="end_time[]"   class="field-input">${TIME_OPTIONS}</select>
      <button type="button" class="btn-remove-row" onclick="removeRow(this)">✕</button>
    `;
    row.querySelectorAll('select').forEach(sel => sel.addEventListener('change', revalidateSchedule));
    wrap.appendChild(row);
  }

  function removeRow(btn) {
    btn.closest('.schedule-row').remove();
    revalidateSchedule();
  }

  // Init with one empty row
  addScheduleRow();
  document.querySelector('#schedule-rows .btn-remove-row').style.visibility = 'hidden';

  // ── Form submit guard ──────────────────────────────────────────────────────
  document.getElementById('setup-form').addEventListener('submit', function (e) {
    if (!revalidateSchedule()) {
      e.preventDefault();
      document.getElementById('schedule-conflict-msg').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
</script>
</body>
</html>