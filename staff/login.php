<?php
session_start();
require_once '../database/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, is_active FROM staff WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $error = 'No staff account found with that email.';
        } else {
            $stmt->bind_result($id, $full_name, $hashed, $is_active);
            $stmt->fetch();

            if (!password_verify($password, $hashed)) {
                $error = 'Incorrect password.';
            } elseif (!$is_active) {
                $error = 'Your account has been deactivated. Contact your administrator.';
            } else {
                $_SESSION['staff_id']   = $id;
                $_SESSION['staff_name'] = $full_name;
                // Update last login
                $conn->query("UPDATE staff SET last_login=NOW() WHERE id=$id");
                header('Location: dashboard.php');
                exit;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Staff Login — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);min-height:100vh;display:flex}
    .left-panel{width:45%;background:linear-gradient(160deg,var(--green) 0%,#1a3330 100%);display:flex;flex-direction:column;justify-content:center;padding:3rem;position:relative;overflow:hidden}
    .left-panel::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(63,130,227,0.08) 1px,transparent 1px),linear-gradient(90deg,rgba(63,130,227,0.08) 1px,transparent 1px);background-size:44px 44px;animation:gridMove 20s linear infinite}
    @keyframes gridMove{from{transform:translateY(0)}to{transform:translateY(44px)}}
    .orb{position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;animation:pulse 6s ease-in-out infinite}
    @keyframes pulse{0%,100%{transform:scale(1);opacity:.7}50%{transform:scale(1.2);opacity:1}}
    .right-panel{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem}
    .login-card{width:100%;max-width:400px;animation:fadeUp 0.6s ease}
    @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
    .field-label{display:block;font-size:0.78rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#5a7a77;margin-bottom:0.45rem}
    .field-input{width:100%;padding:0.8rem 1rem;border:1.5px solid rgba(36,68,65,0.15);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.95rem;background:var(--white);color:var(--green);outline:none;transition:border-color 0.25s,box-shadow 0.25s}
    .field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,0.12)}
    .btn-login{width:100%;padding:0.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:600;font-size:0.95rem;border:none;cursor:pointer;transition:all 0.3s;box-shadow:0 6px 20px rgba(195,54,67,0.3);margin-top:1.5rem;font-family:'DM Sans',sans-serif}
    .btn-login:hover{background:#a82d38;transform:translateY(-2px)}
    .alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.25);color:var(--red);border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;margin-bottom:1.2rem}
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0}
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
      <h1 style="font-family:'Playfair Display',serif;font-size:2.2rem;color:#fff;line-height:1.2;margin-bottom:0.8rem;">Staff<br/>Portal.</h1>
      <p style="color:rgba(255,255,255,0.55);font-size:0.9rem;line-height:1.75;">Manage appointments, patient records, lab requests, and clinic operations.</p>
    </div>
    <div style="margin-top:2.5rem;display:flex;flex-direction:column;gap:0.8rem;">
      <?php foreach([['📅','Appointment scheduling & approval'],['👥','Patient management & records'],['🧪','Lab request coordination'],['📦','Inventory monitoring']] as $f): ?>
      <div style="display:flex;align-items:center;gap:0.8rem;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:0.75rem 1rem;">
        <span style="font-size:1.1rem;"><?= $f[0] ?></span>
        <span style="font-size:0.84rem;color:rgba(255,255,255,0.65);"><?= $f[1] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="right-panel">
  <div class="login-card">
    <div style="margin-bottom:2rem;">
      <h2 style="font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:900;margin-bottom:0.3rem;">Staff Login</h2>
      <p style="color:#6b8a87;font-size:0.9rem;">Enter your credentials to access the staff portal.</p>
    </div>

    <?php if ($error): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['reason']) && $_GET['reason'] === 'deactivated'): ?>
    <div class="alert-error">Your account has been deactivated. Please contact your administrator.</div>
    <?php endif; ?>

    <form method="POST">
      <div style="margin-bottom:1rem;">
        <label class="field-label">Email Address</label>
        <input type="email" name="email" class="field-input" placeholder="staff@telecare.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
      <div>
        <label class="field-label">Password</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pwField" class="field-input" placeholder="Your password" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw()">
            <svg id="eye-show" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="eye-hide" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-login">Log In</button>
    </form>
    <p style="text-align:center;margin-top:2rem;font-size:0.78rem;">
      <a href="../index.php" style="color:#9ab0ae;text-decoration:none;">← Back to home</a>
    </p>
  </div>
</div>
<script>
  function togglePw(){
    const f=document.getElementById('pwField'),s=document.getElementById('eye-show'),h=document.getElementById('eye-hide');
    if(f.type==='password'){f.type='text';s.style.display='none';h.style.display='block';}
    else{f.type='password';s.style.display='block';h.style.display='none';}
  }
</script>
</body>
</html>