<?php
session_start();
require_once '../database/config.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $error = 'No admin account found with that email.';
        } else {
            $stmt->bind_result($id, $full_name, $hashed);
            $stmt->fetch();
            if (password_verify($password, $hashed)) {
                $_SESSION['admin_id']   = $id;
                $_SESSION['admin_name'] = $full_name;
                header('Location: dashboard.php'); exit;
            } else {
                $error = 'Incorrect password.';
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
  <title>Admin Login — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root { --red:#C33643; --green:#244441; --blue:#3F82E3; --bg:#F2F2F2; --white:#FFFFFF; }
    * { box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem; }

    .card {
      background:var(--white); border-radius:24px; padding:2.5rem;
      width:100%; max-width:420px;
      box-shadow:0 8px 40px rgba(0,0,0,0.08);
      animation: fadeUp 0.5s ease;
    }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

    .field-label { display:block; font-size:0.75rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#9ab0ae; margin-bottom:0.4rem; }
    .field-input {
      width:100%; padding:0.78rem 1rem; border:1.5px solid rgba(36,68,65,0.12);
      border-radius:12px; font-family:'DM Sans',sans-serif; font-size:0.93rem;
      color:var(--green); outline:none; transition:border-color 0.2s;
    }
    .field-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(63,130,227,0.1); }
    .pw-wrap { position:relative; }
    .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#9ab0ae; padding:0; }
    .pw-toggle:hover { color:var(--green); }
    .btn { width:100%; padding:0.9rem; border-radius:50px; background:var(--green); color:#fff; font-weight:700; font-size:0.95rem; border:none; cursor:pointer; transition:all 0.3s; margin-top:1.5rem; box-shadow:0 6px 20px rgba(36,68,65,0.25); }
    .btn:hover { background:#1a3330; transform:translateY(-2px); }
    .alert { background:rgba(195,54,67,0.08); border:1px solid rgba(195,54,67,0.2); color:var(--red); border-radius:12px; padding:0.75rem 1rem; font-size:0.86rem; margin-bottom:1.2rem; }
    .admin-badge { display:inline-flex; align-items:center; gap:0.4rem; background:rgba(36,68,65,0.08); border-radius:50px; padding:0.3rem 0.9rem; font-size:0.75rem; font-weight:700; color:var(--green); letter-spacing:0.06em; margin-bottom:1.5rem; }
  </style>
</head>
<body>
  <div class="card">
    <div style="text-align:center;margin-bottom:1.8rem;">
      <a href="../index.php" style="font-family:'Playfair Display',serif;font-size:1.7rem;font-weight:900;color:var(--green);text-decoration:none;">
        TELE<span style="color:var(--red)">-</span>CARE
      </a>
      <div style="margin-top:0.8rem;"><span class="admin-badge">🔒 Admin Portal</span></div>
    </div>

    <?php if($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
      <div style="margin-bottom:1rem;">
        <label class="field-label">Email Address</label>
        <input type="email" name="email" class="field-input" placeholder="admin@telecare.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
      <div>
        <label class="field-label">Password</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pw" class="field-input" placeholder="Your password" required style="padding-right:2.8rem;"/>
          <button type="button" class="pw-toggle" onclick="togglePw()">
            <svg id="eye-show" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <svg id="eye-hide" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn">Log In to Admin</button>
    </form>

    <p style="text-align:center;margin-top:1.5rem;font-size:0.82rem;color:#9ab0ae;">
      <a href="../index.php" style="color:#9ab0ae;text-decoration:none;">← Back to home</a>
    </p>
  </div>
  <script>
    function togglePw() {
      const f = document.getElementById('pw');
      const s = document.getElementById('eye-show');
      const h = document.getElementById('eye-hide');
      if (f.type==='password') { f.type='text'; s.style.display='none'; h.style.display='block'; }
      else { f.type='password'; s.style.display='block'; h.style.display='none'; }
    }
  </script>
</body>
</html>