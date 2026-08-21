<?php
// doctor/setup.php
// doctor/setup.php
if (session_status() !== PHP_SESSION_ACTIVE) {    session_start();}
require_once '../database/config.php';

$token = trim($_GET['token'] ?? '');
$error = '';

$doctor = null;
if ($token) {
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE invite_token = ? AND invite_expires > NOW() AND setup_complete = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $doctor = $stmt->get_result()->fetch_assoc();
}

if (!$doctor) {
    $error = 'This setup link is invalid or has expired. Please contact your administrator for a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 50) {
        $error = 'Password must not exceed 50 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $photo_path = null;
        if (!empty($_FILES['profile_photo']['name'])) {
            $allowed_ext  = ['jpg', 'jpeg', 'png', 'webp'];
            $allowed_mime = ['image/jpeg', 'image/png', 'image/webp'];
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $tmp_name = $_FILES['profile_photo']['tmp_name'];

            if (!in_array($ext, $allowed_ext, true)) {
                $error = 'Profile photo must be a JPG, JPEG, PNG, or WEBP image.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = $finfo ? finfo_file($finfo, $tmp_name) : '';
                if ($finfo) finfo_close($finfo);

                if (!in_array($mime, $allowed_mime, true)) {
                    $error = 'Invalid image file content.';
                } else {
                    $dir = '../uploads/profiles/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $fname = uniqid('doc_') . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $dir . $fname)) {
                        $photo_path = 'uploads/profiles/' . $fname;
                    }
                }
            }
        }

        if (!$error) {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare(
                "UPDATE doctors SET
                    password       = ?,
                    profile_photo  = COALESCE(?, profile_photo),
                    invite_token   = NULL,
                    invite_expires = NULL,
                    setup_complete = 1
                 WHERE id = ?"
            );
            $stmt->bind_param("ssi", $hashed, $photo_path, $doctor['id']);

            if ($stmt->execute()) {
                // Auto-login: no separate login step required.
                $_SESSION['doctor_id']   = $doctor['id'];
                $_SESSION['doctor_name'] = $doctor['full_name'];
                header('Location: dashboard.php'); exit;
            } else {
                $error = 'Something went wrong saving your account. Please try again.';
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
  <title>Set Your Password — TELE-CARE</title>
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
    .right-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:3rem 4%}
    .form-wrap{max-width:440px;width:100%}
    .field-label{display:block;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#5a7a77;margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.78rem 1rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none;transition:border-color 0.2s;background:var(--white)}
    .field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,0.1)}
    .form-field{margin-bottom:1rem}
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0}
    .pw-toggle:hover{color:var(--green)}
    .btn-submit{width:100%;padding:0.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.95rem;border:none;cursor:pointer;transition:all 0.3s;box-shadow:0 6px 18px rgba(195,54,67,0.25);margin-top:0.5rem;font-family:'DM Sans',sans-serif}
    .btn-submit:hover{background:#a82d38;transform:translateY(-2px)}
    .alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.2);color:var(--red);border-radius:12px;padding:0.75rem 1rem;font-size:0.86rem;margin-bottom:1.2rem}
    .skip-note{font-size:0.78rem;color:#9ab0ae;text-align:center;margin-top:0.9rem;line-height:1.5}
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
      <h1 style="font-size:2rem;color:#fff;line-height:1.2;margin-bottom:0.8rem;">Welcome<?= $doctor ? ', Dr. ' . htmlspecialchars(explode(' ', $doctor['full_name'])[0]) : '' ?></h1>
      <p style="color:rgba(255,255,255,0.55);font-size:0.88rem;line-height:1.75;">Your profile has already been set up by the admin. Just choose a password to activate your account.</p>
    </div>
    <?php if ($doctor): ?>
    <div style="margin-top:2.5rem;background:rgba(255,255,255,0.07);border-radius:16px;padding:1.2rem;">
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.4);margin-bottom:0.5rem;">Your Account</div>
      <div style="font-weight:700;color:#fff;font-size:1rem;">Dr. <?= htmlspecialchars($doctor['full_name']) ?></div>
      <div style="font-size:0.8rem;color:rgba(255,255,255,0.5);margin-top:0.2rem;"><?= htmlspecialchars($doctor['email']) ?></div>
      <?php if (!empty($doctor['specialty'])): ?>
      <div style="margin-top:0.5rem;"><span style="background:rgba(255,255,255,0.1);border-radius:50px;padding:0.25rem 0.7rem;font-size:0.75rem;color:rgba(255,255,255,0.7);"><?= htmlspecialchars($doctor['specialty']) ?></span></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="right-panel">
  <div class="form-wrap">

    <?php if ($error && !$doctor): ?>
    <div style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:1rem;">⚠️</div>
      <h2 style="font-size:1.5rem;margin-bottom:0.6rem;">Invalid Setup Link</h2>
      <p style="color:#6b8a87;font-size:0.9rem;"><?= htmlspecialchars($error) ?></p>
      <a href="../index.php" style="display:inline-block;margin-top:1.5rem;font-size:0.85rem;color:var(--blue);">← Back to home</a>
    </div>

    <?php else: ?>

    <div style="margin-bottom:2rem;">
      <h2 style="font-size:1.6rem;margin-bottom:0.3rem;">Set Your Password</h2>
      <p style="color:#6b8a87;font-size:0.88rem;">This is the last step — you'll go straight to your dashboard after.</p>
    </div>

    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="setup-form">
      <div class="form-field">
        <label class="field-label">New Password *</label>
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

      <div class="form-field">
        <label class="field-label">Profile Photo <span style="font-weight:400;text-transform:none;font-size:0.7rem;">(optional — can be added later)</span></label>
        <input type="file" name="profile_photo" class="field-input" accept="image/*" style="padding:0.5rem;"/>
      </div>

      <button type="submit" class="btn-submit">Finish &amp; Go to Dashboard</button>
      <p class="skip-note">You can skip the profile photo for now — everything else in your profile has already been set up by the admin.</p>
    </form>
    <?php endif; ?>
  </div>
</div>

<script>
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
</script>
</body>
</html>




