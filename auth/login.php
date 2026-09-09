<?php
// auth/login.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once '../database/config.php';
require_once '../includes/legal_policy_helper.php';

$error = '';
$rememberedEmail = $_COOKIE['telecare_remember_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password, is_verified, is_active FROM patients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $error = 'No account found with that email.';
        } else {
            $stmt->bind_result($id, $full_name, $hashed, $is_verified, $is_active);
            $stmt->fetch();

            if (!password_verify($password, $hashed)) {
                $error = 'Incorrect password. Please try again.';
            } elseif (!$is_verified) {
                $error = 'account_not_verified';
            } elseif (isset($is_active) && !$is_active) {
                $error = 'account_deactivated';
            } else {
                $_SESSION['patient_id'] = $id;
                $_SESSION['patient_name'] = $full_name;
                if ($rememberMe) {
                    setcookie('telecare_remember_email', $email, [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                } else {
                    setcookie('telecare_remember_email', '', time() - 3600, '/');
                }
                header('Location: ../router.php?page=dashboard');
                exit;
            }
        }
        $stmt->close();
    }
}

$emailValue = $_POST['email'] ?? $rememberedEmail;
$rememberChecked = isset($_POST['remember_me']) ? $_POST['remember_me'] === '1' : $rememberedEmail !== '';
$privacyPolicy = get_legal_policy($conn, 'privacy-policy');
$dataPolicy = get_legal_policy($conn, 'data-privacy-notice');
$termsPolicy = get_legal_policy($conn, 'terms-and-conditions');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Log In - TELE-CARE AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
  <style>
    :root { --red:#bd0f18; --red-dark:#a20c14; --ink:#101827; --line:#efc9c9; --panel:#fff; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; font-family:'DM Sans',sans-serif; color:var(--ink); background:linear-gradient(125deg,#eef1ff 0%,#faf4f8 52%,#dff8ff 100%); }
    .page { width:min(100%, 420px); text-align:center; }
    .brand-mark { width:48px; height:48px; margin:0 auto .65rem; display:grid; place-items:center; border-radius:50%; background:var(--red); color:#fff; font-weight:800; font-size:1.1rem; }
    .brand-name { margin:0; color:#a70009; font-family:'Plus Jakarta Sans',sans-serif; font-size:1.55rem; letter-spacing:-.03em; }
    .brand-subtitle { margin:.25rem 0 1.55rem; color:#62353b; font-size:.76rem; }
    .login-card { overflow:hidden; text-align:left; background:var(--panel); border:1px solid var(--line); border-radius:12px; box-shadow:0 10px 18px rgba(62,32,42,.12); }
    .card-body { padding:1.55rem 1.45rem 1.4rem; }
    h1 { margin:0 0 1.4rem; text-align:center; font-size:1.1rem; font-weight:700; }
    .field { margin-bottom:.85rem; }
    label { display:block; margin-bottom:.3rem; color:#8d1c25; font-size:.64rem; font-weight:600; }
    input[type=email], input[type=password] { width:100%; height:32px; padding:0 .75rem; border:1px solid var(--line); border-radius:6px; background:#fbfaff; color:var(--ink); font:inherit; font-size:.72rem; outline:none; }
    input:focus { border-color:var(--red); box-shadow:0 0 0 3px rgba(189,15,24,.1); }
    .password-wrap { position:relative; }
    .password-wrap input { padding-right:2.5rem; }
    .password-toggle { position:absolute; top:50%; right:.65rem; transform:translateY(-50%); padding:0; border:0; background:none; color:#8c5360; cursor:pointer; }
    .password-toggle svg { width:15px; height:15px; }
    .form-options { display:flex; align-items:center; justify-content:space-between; margin:.85rem 0 1.25rem; font-size:.68rem; }
    .remember { display:flex; align-items:center; gap:.4rem; color:#663d45; cursor:pointer; }
    .remember input { width:12px; height:12px; margin:0; accent-color:var(--red); }
    a { color:var(--red); text-decoration:none; }
    a:hover, button.policy-button:hover { text-decoration:underline; }
    .btn-login { width:100%; height:31px; border:0; border-radius:6px; background:var(--red); color:#fff; font-size:.68rem; font-weight:700; cursor:pointer; }
    .btn-login:hover { background:var(--red-dark); }
    .error { margin-bottom:1rem; padding:.65rem .75rem; border:1px solid #efb7b7; border-radius:6px; background:#fff3f3; color:#a20c14; font-size:.72rem; }
    .alert { margin-bottom:1rem; padding:.75rem; border-radius:7px; font-size:.72rem; line-height:1.5; }
    .alert-unverified { border:1px solid #b6ddd8; background:#effaf8; color:#12685f; }
    .alert-deactivated { border:1px solid #efb7b7; background:#fff3f3; color:#a20c14; }
    .card-footer { padding:.78rem 1rem; border-top:1px solid #eadada; background:#fcfbff; text-align:center; font-size:.7rem; color:#663d45; }
    .legal-links { display:flex; justify-content:center; gap:1.35rem; margin-top:1.55rem; font-size:.68rem; }
    .policy-button { padding:0; border:0; background:none; color:#62353b; font:inherit; cursor:pointer; }
    .policy-button:hover { color:var(--red); }
    .modal-backdrop { display:none; position:fixed; inset:0; z-index:10; align-items:center; justify-content:center; padding:1rem; background:rgba(24,22,31,.55); }
    .modal-backdrop.open { display:flex; }
    .policy-modal { width:min(100%,680px); max-height:88vh; overflow:hidden; border-radius:12px; background:#fff; box-shadow:0 18px 50px rgba(0,0,0,.25); }
    .policy-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:1rem 1.2rem; border-bottom:1px solid #eadada; }
    .policy-header h2 { margin:0; font-size:1rem; }
    .policy-close { border:0; background:none; color:#62353b; font-size:1.35rem; cursor:pointer; }
    .policy-content { max-height:calc(88vh - 68px); overflow:auto; padding:1.2rem 1.35rem; color:#3e4653; font-size:.82rem; line-height:1.65; }
    .policy-content h1, .policy-content h2, .policy-content h3 { text-align:left; color:var(--ink); }
    @media (max-width:480px) { body { padding:1.25rem .75rem; } .legal-links { gap:.8rem; } }
  </style>
</head>
<body>
  <main class="page">
    <div class="brand-mark">TC</div>
    <p class="brand-name">Tele-Care AI</p>
    <p class="brand-subtitle">Patient Portal Login</p>

    <section class="login-card">
      <div class="card-body">
        <h1>Welcome Back</h1>
        <?php if ($error === 'account_not_verified'): ?>
          <div class="alert alert-unverified">Your account has not been activated yet. Check your inbox for the activation link or <button class="policy-button" id="resendBtn" type="button" onclick="resendVerification()">resend it now</button>.<div id="resendMsg"></div></div>
        <?php elseif ($error === 'account_deactivated'): ?>
          <div class="alert alert-deactivated">Your account has been deactivated by an administrator. Please contact your clinic or administrator for assistance.</div>
        <?php elseif ($error): ?>
          <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="field">
            <label for="email">Email Address or Patient ID</label>
            <input id="email" type="email" name="email" placeholder="Enter your email or ID" value="<?= htmlspecialchars($emailValue) ?>" required autocomplete="username"/>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <div class="password-wrap">
              <input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password"/>
              <button class="password-toggle" type="button" onclick="togglePassword()" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></button>
            </div>
          </div>
          <div class="form-options">
            <label class="remember"><input type="checkbox" name="remember_me" value="1" <?= $rememberChecked ? 'checked' : '' ?>/> Remember Me</label>
            <a href="forgot_password.php">Forgot Password?</a>
          </div>
          <button class="btn-login" type="submit">Login</button>
        </form>
      </div>
      <div class="card-footer">New patient? <a href="register.php">Register here</a></div>
    </section>

    <nav class="legal-links" aria-label="Legal links">
      <button class="policy-button" type="button" onclick="openPolicy('privacy')">Privacy Policy</button>
      <button class="policy-button" type="button" onclick="openPolicy('terms')">Terms of Service</button>
      <button class="policy-button" type="button" onclick="openPolicy('data')">Data Policy</button>
    </nav>
  </main>

  <div class="modal-backdrop" id="policyModal" role="dialog" aria-modal="true" aria-labelledby="policyTitle" onclick="closePolicy(event)">
    <section class="policy-modal">
      <header class="policy-header"><h2 id="policyTitle"></h2><button class="policy-close" type="button" onclick="closePolicy()" aria-label="Close">&times;</button></header>
      <div class="policy-content" id="policyContent"></div>
    </section>
  </div>

  <script>
    const policies = {
      privacy: { title: <?= json_encode($privacyPolicy['title'] ?? 'Privacy Policy') ?>, content: <?= json_encode($privacyPolicy['content'] ?? '<p>This policy is not currently available.</p>') ?> },
      terms: { title: <?= json_encode($termsPolicy['title'] ?? 'Terms of Service') ?>, content: <?= json_encode($termsPolicy['content'] ?? '<p>This policy is not currently available.</p>') ?> },
      data: { title: <?= json_encode($dataPolicy['title'] ?? 'Data Policy') ?>, content: <?= json_encode($dataPolicy['content'] ?? '<p>This policy is not currently available.</p>') ?> }
    };
    function openPolicy(type) { document.getElementById('policyTitle').textContent = policies[type].title; document.getElementById('policyContent').innerHTML = policies[type].content; document.getElementById('policyModal').classList.add('open'); }
    function closePolicy(event) { if (!event || event.target === document.getElementById('policyModal')) document.getElementById('policyModal').classList.remove('open'); }
    function togglePassword() { const field = document.getElementById('password'); field.type = field.type === 'password' ? 'text' : 'password'; }
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closePolicy(); });

    <?php if ($error === 'account_not_verified'): ?>
    function resendVerification() {
      const button = document.getElementById('resendBtn');
      const message = document.getElementById('resendMsg');
      button.disabled = true;
      button.textContent = 'Sending...';
      fetch('resend_verification.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'email=' + encodeURIComponent(<?= json_encode($_POST['email'] ?? '') ?>) })
        .then(response => response.json()).then(data => { message.textContent = data.success ? ' Activation email sent. Check your inbox.' : ' ' + data.message; button.textContent = data.success ? 'Sent' : 'resend it now'; button.disabled = data.success; })
        .catch(() => { message.textContent = ' Unable to send the activation email.'; button.disabled = false; button.textContent = 'resend it now'; });
    }
    <?php endif; ?>
  </script>
</body>
</html>
