<?php
session_start();
require_once '../database/config.php';

if (isset($_SESSION['staff_id'])) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT id, full_name, password, role, status FROM staff_accounts WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();

        if ($staff && $staff['status'] === 'active' && password_verify($password, $staff['password'])) {
            $_SESSION['staff_id']   = $staff['id'];
            $_SESSION['staff_name'] = $staff['full_name'];
            $_SESSION['staff_role'] = $staff['role'];
            header('Location: dashboard.php'); exit;
        } elseif ($staff && $staff['status'] !== 'active') {
            $error = 'Your account is inactive. Contact your administrator.';
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Staff Login — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F0F4F8;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
    h1,h2{font-family:'Playfair Display',serif}
    .card{background:var(--white);border-radius:24px;padding:2.2rem;width:100%;max-width:400px;box-shadow:0 8px 40px rgba(0,0,0,0.08)}
    .brand{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--green);margin-bottom:0.3rem}
    .brand span{color:var(--red)}
    .subtitle{font-size:0.8rem;color:#9ab0ae;margin-bottom:2rem}
    .field-label{display:block;font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.35rem}
    .field-input{width:100%;padding:0.78rem 1rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none;transition:border-color 0.2s;background:var(--white)}
    .field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,0.1)}
    .form-field{margin-bottom:0.9rem}
    .btn-submit{width:100%;padding:0.9rem;border-radius:50px;background:var(--green);color:#fff;font-weight:700;font-size:0.95rem;border:none;cursor:pointer;transition:all 0.25s;font-family:'DM Sans',sans-serif;margin-top:0.5rem}
    .btn-submit:hover{background:#1a3330}
    .alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.2);color:var(--red);border-radius:12px;padding:0.75rem 1rem;font-size:0.86rem;margin-bottom:1rem}
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae}
    .pw-toggle:hover{color:var(--green)}
    .footer-link{text-align:center;margin-top:1.2rem;font-size:0.82rem;color:#9ab0ae}
    .footer-link a{color:var(--blue);text-decoration:none;font-weight:600}
  </style>
</head>
<body>
<div class="card">
  <div class="brand">TELE<span>-</span>CARE</div>
  <div class="subtitle">Staff Portal — Sign in to continue</div>

  <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <div class="form-field">
      <label class="field-label">Email Address</label>
      <input type="email" name="email" class="field-input" placeholder="staff@telecare.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
    </div>
    <div class="form-field">
      <label class="field-label">Password</label>
      <div class="pw-wrap">
        <input type="password" name="password" id="pw" class="field-input" placeholder="Your password" required style="padding-right:2.8rem;"/>
        <button type="button" class="pw-toggle" onclick="const f=document.getElementById('pw');f.type=f.type==='password'?'text':'password'">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        </button>
      </div>
    </div>
    <button type="submit" class="btn-submit">Sign In</button>
  </form>

  <div class="footer-link">
    <a href="../index.php">← Back to Home</a>
  </div>
</div>
</body>
</html>