<?php
// private_telecare/staffs_index.php
// ONE shared login page for Super Admin, Admin, and Staff.
// User & Doctor keep their own separate logins (auth/login.php, doctor/login.php).
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../database/config.php';

// Already logged in on one of these roles? Skip straight to that dashboard.
if (isset($_SESSION['super_admin_id'])) { header('Location: ../super_admin/dashboard.php'); exit; }
if (isset($_SESSION['admin_id']))       { header('Location: ../admin/dashboard.php'); exit; }
if (isset($_SESSION['staff_id']))       { header('Location: ../staff/dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $matched = false;

        // 1) Super Admin
        $stmt = safe_prepare($conn, "SELECT id, full_name, password FROM super_admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['super_admin_id']   = $row['id'];
            $_SESSION['super_admin_name'] = $row['full_name'];
            header('Location: ../super_admin/dashboard.php'); exit;
        }

        // 2) Admin
        if (!$matched) {
            $stmt = safe_prepare($conn, "SELECT id, full_name, password FROM admins WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && password_verify($password, $row['password'])) {
                $_SESSION['admin_id']   = $row['id'];
                $_SESSION['admin_name'] = $row['full_name'];
                header('Location: ../admin/dashboard.php'); exit;
            }
        }

        // 3) Staff
        if (!$matched) {
            $stmt = safe_prepare($conn, "SELECT id, full_name, password, status, role FROM staff_accounts WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row && $row['status'] === 'active' && password_verify($password, $row['password'])) {
                $_SESSION['staff_id']   = $row['id'];
                $_SESSION['staff_name'] = $row['full_name'];
                $_SESSION['staff_role'] = $row['role'];
                header('Location: ../staff/dashboard.php'); exit;
            } elseif ($row && $row['status'] !== 'active') {
                $error = 'Your account is inactive. Contact your administrator.';
            }
        }

        if ($error === '') {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>TELE-CARE | Internal Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
  :root {
    --primary:#B31118;
    --primary-dark:#8A000B;
    --primary-soft:#FCEBED;
    --secondary:#0F9D95;
    --secondary-dark:#08756F;
    --secondary-soft:#E3F6F4;
    --text:#172033;
    --muted:#6B7280;
    --border:#E1E5EC;
    --bg:#F7F8FA;
    --white:#FFFFFF;
    --focus:rgba(179,17,24,.18);
    --radius:10px;
    --shadow:0 12px 30px rgba(23,32,51,.14);
  }

  * {
    box-sizing:border-box;
    margin:0;
    padding:0;
  }

  body {
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px;
    background:
      radial-gradient(circle at 10% 10%,rgba(179,17,24,.06),transparent 30%),
      radial-gradient(circle at 90% 90%,rgba(15,157,149,.07),transparent 32%),
      var(--bg);
    color:var(--text);
    font-family:'Inter',sans-serif;
  }

  button,input,select,textarea {
    font-family:inherit;
  }

  .card {
    width:100%;
    max-width:430px;
    padding:34px;
    background:var(--white);
    border:1px solid var(--border);
    border-radius:14px;
    box-shadow:var(--shadow);
  }

  /* Brand */

  .brand {
    margin-bottom:5px;
    color:var(--text);
    text-align:center;
    font-size:27px;
    font-weight:800;
    letter-spacing:-.5px;
  }

  .brand span {
    color:var(--primary);
  }

  .subtitle {
    margin-bottom:16px;
    color:var(--muted);
    text-align:center;
    font-size:13px;
    font-weight:500;
  }

  .badge {
    display:block;
    width:max-content;
    margin:0 auto 24px;
    padding:6px 12px;
    background:var(--secondary-soft);
    border:1px solid rgba(15,157,149,.2);
    border-radius:50px;
    color:var(--secondary-dark);
    font-size:10px;
    font-weight:700;
    letter-spacing:.05em;
    text-transform:uppercase;
  }

  /* Alert */

  .alert {
    margin-bottom:18px;
    padding:11px 13px;
    background:var(--primary-soft);
    border:1px solid rgba(179,17,24,.18);
    border-radius:var(--radius);
    color:var(--primary-dark);
    font-size:12px;
    line-height:1.5;
  }

  /* Form */

  .field-label {
    display:block;
    margin-bottom:7px;
    color:#46536A;
    font-size:11px;
    font-weight:700;
    letter-spacing:.04em;
    text-transform:uppercase;
  }

  .field-input {
    width:100%;
    padding:11px 13px;
    background:var(--white);
    border:1px solid var(--border);
    border-radius:var(--radius);
    color:var(--text);
    font-size:13px;
    outline:none;
    transition:.2s;
  }

  .field-input:hover {
    border-color:#C7CDD8;
  }

  .field-input:focus {
    border-color:var(--primary);
    box-shadow:0 0 0 3px var(--focus);
  }

  .field-input::placeholder {
    color:#9AA2B1;
  }

  /* Password */

  .pw-wrap {
    position:relative;
  }

  .pw-toggle {
    position:absolute;
    right:12px;
    top:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    width:28px;
    height:28px;
    padding:0;
    transform:translateY(-50%);
    background:none;
    border:0;
    border-radius:6px;
    color:var(--muted);
    cursor:pointer;
  }

  .pw-toggle:hover {
    background:var(--primary-soft);
    color:var(--primary);
  }

  /* Button */

  .btn {
    width:100%;
    margin-top:22px;
    padding:11px 16px;
    background:var(--primary);
    border:0;
    border-radius:var(--radius);
    color:#fff;
    font-size:13px;
    font-weight:700;
    cursor:pointer;
    box-shadow:0 4px 12px rgba(179,17,24,.18);
    transition:.2s;
  }

  .btn:hover {
    background:var(--primary-dark);
    transform:translateY(-1px);
    box-shadow:0 6px 16px rgba(179,17,24,.22);
  }

  .btn:active {
    transform:translateY(0);
  }

  /* Footer */

  .footer {
    display:flex;
    justify-content:center;
    margin-top:22px;
    padding-top:17px;
    border-top:1px solid #E7EAF0;
  }

  .back-link {
    color:var(--secondary-dark);
    text-decoration:none;
    font-size:12px;
    font-weight:600;
  }

  .back-link:hover {
    color:var(--primary);
  }

  :focus-visible {
    outline:3px solid var(--focus);
    outline-offset:2px;
  }

  /* Mobile */

  @media(max-width:520px) {
    body {
      padding:16px;
    }

    .card {
      padding:25px 20px;
      border-radius:12px;
    }

    .brand {
      font-size:24px;
    }

    .badge {
      font-size:9px;
    }
  }
  </style> 
</head>
<body>
  <main class="card">
    <div class="brand">TELE<span>-</span>CARE</div>
    <p class="subtitle">Internal staff sign-in</p>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
      <div style="margin-bottom:1rem;">
        <label class="field-label">Email Address</label>
        <input type="email" name="email" class="field-input" placeholder="you@telecare.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
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
      <button type="submit" class="btn">Sign In</button>
    </form>

    <div class="footer">
      <a class="back-link" href="../index.php"> Back to Home</a>
    </div>
  </main>

  <script>
    function togglePw() {
      const pw = document.getElementById('pw');
      const show = document.getElementById('eye-show');
      const hide = document.getElementById('eye-hide');
      const isPw = pw.type === 'password';
      pw.type = isPw ? 'text' : 'password';
      show.style.display = isPw ? 'none' : 'block';
      hide.style.display = isPw ? 'block' : 'none';
    }
  </script>
</body>
</html>