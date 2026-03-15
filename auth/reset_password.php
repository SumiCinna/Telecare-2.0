<?php
require_once '../database/config.php';

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = false;
$patient = null;

// Validate token
if (empty($token)) {
    $error = 'Invalid or missing reset link.';
} else {
    $stmt = $conn->prepare("SELECT id, full_name FROM patients WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result  = $stmt->get_result();
    $patient = $result->fetch_assoc();
    if (!$patient) $error = 'This reset link is invalid or has expired. Please request a new one.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new_password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE patients SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $upd->bind_param("si", $hashed, $patient['id']);
        $upd->execute();
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:linear-gradient(160deg,var(--green) 0%,#1a3330 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
    body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(63,130,227,0.06) 1px,transparent 1px),linear-gradient(90deg,rgba(63,130,227,0.06) 1px,transparent 1px);background-size:44px 44px;animation:gridMove 20s linear infinite;pointer-events:none;}
    @keyframes gridMove{from{transform:translateY(0)}to{transform:translateY(44px)}}
    .card{background:#fff;border-radius:24px;padding:2.5rem 2rem;width:100%;max-width:420px;position:relative;z-index:1;box-shadow:0 30px 80px rgba(0,0,0,0.25);animation:fadeUp 0.5s ease;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .logo{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--green);text-decoration:none;display:block;margin-bottom:2rem;}
    .logo span{color:var(--red)}
    .field-label{display:block;font-size:0.78rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#5a7a77;margin-bottom:0.45rem;}
    .field-input{width:100%;padding:0.8rem 1rem;border:1.5px solid rgba(36,68,65,0.15);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.95rem;background:var(--white);color:var(--green);outline:none;transition:border-color 0.25s,box-shadow 0.25s;}
    .field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,0.12);}
    .btn{width:100%;padding:0.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:600;font-size:0.95rem;border:none;cursor:pointer;transition:all 0.3s;box-shadow:0 6px 20px rgba(195,54,67,0.3);margin-top:1.2rem;font-family:'DM Sans',sans-serif;}
    .btn:hover{background:#a82d38;transform:translateY(-2px);}
    .alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.25);color:var(--red);border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;margin-bottom:1.2rem;}
    .pw-wrap{position:relative;}
    .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0;}
    .pw-toggle:hover{color:var(--green);}
    #pw-bar-wrap{height:3px;border-radius:2px;background:#e0e0e0;overflow:hidden;margin-top:0.4rem;}
    #pw-bar{height:100%;width:0;transition:width 0.3s,background 0.3s;}
    #pw-match{font-size:0.78rem;margin-top:0.3rem;}
  </style>
</head>
<body>
<div class="card">
  <a href="login.php" class="logo">TELE<span>-</span>CARE</a>

  <?php if ($success): ?>
    <!-- Success state -->
    <div style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:1rem;">✅</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--green);margin-bottom:0.5rem;">Password Updated!</h2>
      <p style="color:#6b8a87;font-size:0.9rem;line-height:1.7;margin-bottom:1.5rem;">Your password has been changed successfully. You can now log in with your new password.</p>
      <a href="login.php" style="display:inline-block;padding:0.9rem 2.5rem;border-radius:50px;background:var(--red);color:#fff;font-weight:600;text-decoration:none;box-shadow:0 6px 20px rgba(195,54,67,0.3);">Go to Login →</a>
    </div>

  <?php elseif ($error && !$patient): ?>
    <!-- Invalid token state -->
    <div style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:1rem;">❌</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--green);margin-bottom:0.5rem;">Link Expired</h2>
      <p style="color:#6b8a87;font-size:0.9rem;line-height:1.7;margin-bottom:1.5rem;"><?= htmlspecialchars($error) ?></p>
      <a href="forgot_password.php" style="display:inline-block;padding:0.9rem 2.5rem;border-radius:50px;background:var(--red);color:#fff;font-weight:600;text-decoration:none;box-shadow:0 6px 20px rgba(195,54,67,0.3);">Request New Link</a>
    </div>

  <?php else: ?>
    <!-- Reset form -->
    <div style="margin-bottom:1.8rem;">
      <div style="width:52px;height:52px;border-radius:14px;background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">🔑</div>
      <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--green);margin-bottom:0.3rem;">Set New Password</h2>
      <p style="color:#6b8a87;font-size:0.88rem;line-height:1.6;">Hi <strong><?= htmlspecialchars($patient['full_name']) ?></strong>, enter your new password below.</p>
    </div>

    <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>

    <form method="POST">
      <div style="margin-bottom:1rem;">
        <label class="field-label">New Password</label>
        <div class="pw-wrap">
          <input type="password" name="new_password" id="pw" class="field-input" placeholder="At least 8 characters" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pw','e1s','e1h')">
            <svg id="e1s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="e1h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
        <div id="pw-bar-wrap"><div id="pw-bar"></div></div>
      </div>

      <div style="margin-bottom:1rem;">
        <label class="field-label">Confirm New Password</label>
        <div class="pw-wrap">
          <input type="password" name="confirm_password" id="pw2" class="field-input" placeholder="Repeat password" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pw2','e2s','e2h')">
            <svg id="e2s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="e2h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
        <div id="pw-match"></div>
      </div>

      <button type="submit" class="btn">Update Password</button>
    </form>

    <p style="text-align:center;margin-top:1.5rem;font-size:0.85rem;color:#9ab0ae;">
      <a href="login.php" style="color:var(--green);font-weight:600;text-decoration:none;">← Back to Login</a>
    </p>
  <?php endif ?>
</div>

<script>
  function togglePw(id,s,h){
    const f=document.getElementById(id);
    if(f.type==='password'){f.type='text';document.getElementById(s).style.display='none';document.getElementById(h).style.display='block';}
    else{f.type='password';document.getElementById(s).style.display='block';document.getElementById(h).style.display='none';}
  }
  const pw=document.getElementById('pw');
  if(pw){
    pw.addEventListener('input',function(){
      const v=this.value,b=document.getElementById('pw-bar');
      let s=0;
      if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;
      b.style.width=['0%','30%','55%','80%','100%'][s];
      b.style.background=['','#e55','#f90','#3F82E3','#244441'][s];
    });
  }
  const pw2=document.getElementById('pw2');
  if(pw2){
    pw2.addEventListener('input',function(){
      const m=document.getElementById('pw-match');
      if(this.value===document.getElementById('pw').value){m.textContent='✓ Passwords match';m.style.color='var(--green)';}
      else{m.textContent='✗ Passwords do not match';m.style.color='var(--red)';}
    });
  }
</script>
</body>
</html>