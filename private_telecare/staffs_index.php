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
      --red: #E05663;
      --green: #8fd1c9;
      --blue: #6fa8ff;
      --bg: #0b1016;
      --surface: #121923;
      --surface-2: #182231;
      --text: #e8f0ff;
      --muted: #9fb0c9;
      --line: rgba(159, 176, 201, 0.22);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background:
        radial-gradient(circle at 12% 14%, rgba(224,86,99,0.16), transparent 35%),
        radial-gradient(circle at 85% 10%, rgba(111,168,255,0.18), transparent 38%),
        radial-gradient(circle at 65% 78%, rgba(143,209,201,0.10), transparent 42%),
        var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }

    .card {
      width: 100%;
      max-width: 420px;
      background: linear-gradient(180deg, var(--surface), var(--surface-2));
      border-radius: 24px;
      padding: 2.4rem;
      border: 1px solid var(--line);
      box-shadow: 0 16px 50px rgba(0, 0, 0, 0.42);
    }

    .brand {
      font-family: 'Playfair Display', serif;
      font-size: 1.65rem;
      font-weight: 900;
      color: var(--text);
      margin-bottom: 0.2rem;
      text-align: center;
    }

    .brand span { color: var(--red); }

    .subtitle {
      color: var(--muted);
      font-size: 0.9rem;
      margin-bottom: 1.6rem;
      text-align: center;
    }

    .badge {
      display: block;
      width: fit-content;
      margin: 0 auto 1.6rem;
      background: rgba(111,168,255,0.12);
      border: 1px solid rgba(111,168,255,0.35);
      color: var(--blue);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 0.35rem 0.9rem;
      border-radius: 50px;
    }

    .alert {
      background: rgba(224,86,99,0.12);
      border: 1px solid rgba(224,86,99,0.35);
      color: #ffb3ba;
      border-radius: 12px;
      padding: 0.75rem 1rem;
      font-size: 0.86rem;
      margin-bottom: 1.2rem;
    }

    .field-label {
      display: block;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 0.4rem;
    }

    .field-input {
      width: 100%;
      padding: 0.78rem 1rem;
      border: 1.5px solid var(--line);
      border-radius: 12px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.93rem;
      color: var(--text);
      background: rgba(11,16,22,0.55);
      outline: none;
      transition: border-color 0.2s;
    }
    .field-input:focus { border-color: var(--blue); }
    .field-input::placeholder { color: rgba(159,176,201,0.55); }

    .pw-wrap { position: relative; }
    .pw-toggle {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: var(--muted); padding: 0;
    }
    .pw-toggle:hover { color: var(--text); }

    .btn {
      width: 100%;
      padding: 0.9rem;
      border-radius: 50px;
      background: var(--red);
      color: #fff;
      font-weight: 700;
      font-size: 0.95rem;
      border: none;
      cursor: pointer;
      transition: all 0.3s;
      margin-top: 1.6rem;
      box-shadow: 0 6px 20px rgba(224,86,99,0.28);
    }
    .btn:hover { background: #c9424e; transform: translateY(-2px); }

    .footer {
      display: flex;
      justify-content: center;
      margin-top: 1.6rem;
      padding-top: 1.2rem;
      border-top: 1px solid var(--line);
    }

    .back-link { color: var(--blue); text-decoration: none; font-weight: 600; font-size: 0.85rem; }
  </style>
</head>
<body>
  <main class="card">
    <div class="brand">TELE<span>-</span>CARE</div>
    <p class="subtitle">Internal staff sign-in</p>
    <span class="badge">🔒 Super Admin · Admin · Staff</span>

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
      <a class="back-link" href="../index.php">← Back to Home</a>
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
