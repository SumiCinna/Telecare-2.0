<?php
// auth/register.php
require_once '../database/config.php';

$error        = '';
$show_verify  = false;
$verify_email = '';
$patient_name = '';
$verify_token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name       = trim($_POST['first_name'] ?? '');
    $middle_name      = trim($_POST['middle_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $date_of_birth    = $_POST['date_of_birth'] ?? '';
    $email            = trim($_POST['email'] ?? '');
    $phone_number     = trim($_POST['phone_number'] ?? ''); // full intl e.g. +639XXXXXXXXX, optional
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms      = isset($_POST['agree_terms']) && $_POST['agree_terms'] === '1';

    $full_name = $middle_name !== ''
        ? $first_name . ' ' . $middle_name . ' ' . $last_name
        : $first_name . ' ' . $last_name;

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($date_of_birth)) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($first_name) > 30) {
        $error = 'First name must not exceed 30 characters.';
    } elseif (preg_match('/\d/', $first_name)) {
        $error = 'First name cannot contain numbers.';
    } elseif ($middle_name !== '' && strlen($middle_name) > 30) {
        $error = 'Middle name must not exceed 30 characters.';
    } elseif ($middle_name !== '' && preg_match('/\d/', $middle_name)) {
        $error = 'Middle name cannot contain numbers.';
    } elseif (strlen($last_name) > 30) {
        $error = 'Last name must not exceed 30 characters.';
    } elseif (preg_match('/\d/', $last_name)) {
        $error = 'Last name cannot contain numbers.';
    } elseif (strlen($full_name) > 50) {
        $error = 'Full name (combined) must not exceed 50 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@[\w\.-]+\.\w+$/', $email)) {
        $error = 'Please enter a valid email address (e.g., user@example.com).';
    } elseif (strlen($email) > 100) {
        $error = 'Email address must not exceed 100 characters.';
    } elseif (!empty($phone_number) && !preg_match('/^\+639\d{9}$/', $phone_number)) {
        $error = 'Please enter a valid Philippine mobile number (e.g. +639XXXXXXXXX).';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 20) {
        $error = 'Password must not exceed 20 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!$agree_terms) {
        $error = 'You must read and agree to the Data Privacy Notice, Terms and Conditions, and Privacy Policy before creating an account.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM patients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed     = password_hash($password, PASSWORD_BCRYPT);
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $phone_val  = $phone_number !== '' ? $phone_number : null;

            $insert = $conn->prepare("
                INSERT INTO patients (
                    full_name, date_of_birth, email, phone_number,
                    password, is_verified, verification_token, token_expires_at
                ) VALUES (?,?,?,?,?,0,?,?)
            ");
            $insert->bind_param("sssssss",
                $full_name, $date_of_birth, $email, $phone_val,
                $hashed, $token, $expires_at
            );
            if ($insert->execute()) {
                $show_verify  = true;
                $verify_email = $email;
                $patient_name = $full_name;
                $verify_token = $token;
            } else {
                $error = 'Something went wrong. Please try again.';
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
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Create Account — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#B31118;--red-dark:#8a000b;--ink:#151c27;--teal:#006a61;--teal-light:#0D9488;--bg:#F5F6FA;--white:#FFFFFF}
    *{box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh}
    h1,h2{font-family:'Inter',sans-serif;font-weight:800}
    .left-panel{background:linear-gradient(160deg,var(--ink) 0%,#0a0e14 100%);position:relative;overflow:hidden}
    .left-panel::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(13,148,136,0.08) 1px,transparent 1px),linear-gradient(90deg,rgba(13,148,136,0.08) 1px,transparent 1px);background-size:44px 44px;animation:gridMove 20s linear infinite}
    @keyframes gridMove{from{transform:translateY(0)}to{transform:translateY(44px)}}
    .orb{position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;animation:pulse 6s ease-in-out infinite}
    @keyframes pulse{0%,100%{transform:scale(1);opacity:.7}50%{transform:scale(1.2);opacity:1}}
    .field-label{display:block;font-size:.78rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:rgba(21,28,39,0.55);margin-bottom:.45rem}
    .field-label .req{color:var(--red)}
    .field-input{width:100%;padding:.75rem 1rem;border:1.5px solid rgba(21,28,39,.15);border-radius:8px;font-family:'Inter',sans-serif;font-size:.95rem;background:var(--white);color:var(--ink);outline:none;transition:border-color .25s,box-shadow .25s}
    .field-input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,106,97,.12)}
    .field-input.has-error{border-color:var(--red)!important;box-shadow:0 0 0 3px rgba(179,17,24,.1)!important}
    .field-error{font-size:.76rem;color:var(--red);margin-top:.3rem;display:none}
    .field-error.visible{display:block}
    .btn-next{width:100%;padding:.9rem;border-radius:8px;background:var(--red);color:#fff;font-weight:600;font-size:.95rem;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(179,17,24,.3)}
    .btn-next:hover{background:var(--red-dark);transform:translateY(-2px)}
    .btn-next:disabled{background:#c9c9c9;box-shadow:none;cursor:not-allowed;transform:none}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
    @media(max-width:580px){.grid-2{grid-template-columns:1fr}.grid-3{grid-template-columns:1fr}}
    .alert-error{background:#FEF2F2;border:1px solid rgba(179,17,24,.25);color:var(--red);border-radius:12px;padding:.85rem 1rem;font-size:.88rem;margin-bottom:1.2rem}
    .optional-tag{font-size:.72rem;font-weight:400;color:#9ab0ae;text-transform:none;letter-spacing:0;margin-left:.3rem}
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0}
    .pw-toggle:hover{color:var(--ink)}
    .pw-criteria{background:#f8fffe;border:1.5px solid rgba(21,28,39,.1);border-radius:12px;padding:.85rem 1rem;margin-top:.6rem}
    .crit-row{display:flex;align-items:center;gap:.55rem;font-size:.8rem;color:#aabfbd;margin-bottom:.3rem;transition:color .2s}
    .crit-row:last-child{margin-bottom:0}
    .crit-row.met{color:var(--teal);font-weight:500}
    .crit-dot{width:7px;height:7px;border-radius:50%;background:#d4d4d4;flex-shrink:0;transition:background .2s}
    .crit-row.met .crit-dot{background:var(--teal)}
    .toast-wrap{position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
    .toast{background:#fff;border-radius:12px;padding:.75rem 1.1rem;font-size:.84rem;font-weight:500;box-shadow:0 8px 28px rgba(0,0,0,.13);border-left:4px solid var(--red);color:var(--ink);animation:toastIn .3s ease;pointer-events:auto;max-width:300px;line-height:1.4}
    .toast.ok{border-left-color:var(--teal)}
    @keyframes toastIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
    .resend-btn{background:none;border:none;color:var(--red);font-weight:600;font-size:.9rem;cursor:pointer;font-family:'Inter',sans-serif;text-decoration:underline;padding:0}
    .resend-btn:disabled{color:#9ab0ae;cursor:not-allowed;text-decoration:none}
    .spinner-inline{display:inline-block;width:14px;height:14px;border:2px solid rgba(179,17,24,.3);border-top-color:var(--red);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ── Phone combo ─────────────────────────────────────────────────────── */
    .phone-combo{display:flex;border:1.5px solid rgba(21,28,39,.15);border-radius:8px;overflow:hidden;background:var(--white);transition:border-color .25s,box-shadow .25s}
    .phone-combo:focus-within{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,106,97,.12)}
    .phone-combo.has-error{border-color:var(--red)!important;box-shadow:0 0 0 3px rgba(179,17,24,.1)!important}
    .phone-prefix-fixed{display:flex;align-items:center;justify-content:center;border-right:1.5px solid rgba(21,28,39,.1);background:rgba(21,28,39,.05);color:var(--ink);font-family:'Inter',sans-serif;font-size:.88rem;font-weight:700;padding:.75rem .85rem;min-width:64px;flex-shrink:0}
    .phone-number-input{border:none;outline:none;flex:1;padding:.75rem 1rem;font-family:'Inter',sans-serif;font-size:.95rem;color:var(--ink);background:transparent;min-width:0}
    .phone-number-input::placeholder{color:#b0c4c2}

    /* ── Terms agreement row ─────────────────────────────────────────────── */
    .terms-row{display:flex;align-items:flex-start;gap:.65rem;background:#f8fffe;border:1.5px solid rgba(21,28,39,.1);border-radius:12px;padding:.9rem 1rem;margin-bottom:.4rem}
    .terms-row.has-error{border-color:var(--red);background:#FEF2F2}
    .terms-checkbox{width:18px;height:18px;margin-top:1px;flex-shrink:0;accent-color:var(--teal);cursor:not-allowed}
    .terms-checkbox.unlocked{cursor:pointer}
    .terms-text{font-size:.85rem;line-height:1.55;color:var(--ink)}
    .terms-link{color:var(--teal);font-weight:600;text-decoration:underline;background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:.85rem;padding:0}
    .terms-hint{font-size:.74rem;color:#9ab0ae;margin-top:.35rem}

    /* ── Modal ────────────────────────────────────────────────────────────── */
    .modal-overlay{position:fixed;inset:0;background:rgba(10,14,20,.55);backdrop-filter:blur(2px);z-index:10000;display:none;align-items:center;justify-content:center;padding:1.5rem}
    .modal-overlay.open{display:flex}
    .modal-box{background:#fff;border-radius:18px;width:100%;max-width:640px;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 30px 80px rgba(0,0,0,.3);overflow:hidden}
    .modal-head{padding:1.3rem 1.6rem;border-bottom:1px solid rgba(21,28,39,.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
    .modal-head h3{font-size:1.15rem;font-weight:800;font-family:'Inter',sans-serif}
    .modal-close{background:none;border:none;cursor:pointer;color:#9ab0ae;padding:.3rem;border-radius:6px;transition:background .2s,color .2s}
    .modal-close:hover{background:rgba(21,28,39,.06);color:var(--ink)}
    .modal-tabs{display:flex;gap:.4rem;padding:.8rem 1.6rem 0;flex-shrink:0;border-bottom:1px solid rgba(21,28,39,.08)}
    .modal-tab{background:none;border:none;cursor:pointer;font-family:'Inter',sans-serif;font-size:.82rem;font-weight:600;color:#9ab0ae;padding:.55rem .9rem;border-radius:8px 8px 0 0;position:relative;top:1px}
    .modal-tab.active{color:var(--teal);border-bottom:2px solid var(--teal)}
    .modal-body{padding:1.4rem 1.6rem;overflow-y:auto;flex:1;font-size:.86rem;line-height:1.75;color:#3a4650}
    .modal-body h4{font-size:.95rem;font-weight:700;color:var(--ink);margin:1.1rem 0 .4rem}
    .modal-body h4:first-child{margin-top:0}
    .modal-body p{margin-bottom:.7rem}
    .modal-body ul{margin:0 0 .7rem 1.2rem}
    .modal-body li{margin-bottom:.35rem}
    .modal-section{display:none}
    .modal-section.active{display:block}
    .modal-foot{padding:1rem 1.6rem;border-top:1px solid rgba(21,28,39,.08);flex-shrink:0;display:flex;align-items:center;justify-content:space-between;gap:1rem;background:#fafbfc}
    .scroll-progress{font-size:.76rem;color:#9ab0ae;display:flex;align-items:center;gap:.5rem}
    .scroll-bar{width:90px;height:5px;border-radius:3px;background:rgba(21,28,39,.1);overflow:hidden}
    .scroll-bar-fill{height:100%;width:0%;background:var(--teal);transition:width .15s}
    .btn-accept{padding:.7rem 1.4rem;border-radius:8px;background:var(--teal);color:#fff;font-weight:600;font-size:.88rem;border:none;cursor:not-allowed;opacity:.5;transition:all .25s}
    .btn-accept.unlocked{cursor:pointer;opacity:1}
    .btn-accept.unlocked:hover{background:#005249}
  </style>
</head>
<body>

<div class="toast-wrap" id="toastWrap"></div>

<div style="display:flex;min-height:100vh">

  <!-- LEFT -->
  <div class="left-panel" style="width:42%;display:flex;flex-direction:column;justify-content:center;padding:3rem;position:sticky;top:0;height:100vh">
    <div class="orb" style="width:320px;height:320px;background:radial-gradient(circle,rgba(0,106,97,.25) 0%,transparent 70%);top:-60px;right:-60px"></div>
    <div class="orb" style="width:220px;height:220px;background:radial-gradient(circle,rgba(179,17,24,.2) 0%,transparent 70%);bottom:60px;left:20px;animation-delay:3s"></div>
    <div style="position:relative;z-index:2">
      <a href="../index.php" style="font-family:'Inter',sans-serif;font-size:1.6rem;font-weight:900;color:#fff;text-decoration:none;letter-spacing:.02em">TELE<span style="color:var(--red)">-</span>CARE</a>
      <div style="margin-top:3rem">
        <h1 style="font-size:2.2rem;color:#fff;line-height:1.2;margin-bottom:1rem">Create Your<br/>Patient Account</h1>
        <p style="color:rgba(255,255,255,.55);font-size:.95rem;line-height:1.75">Fill in your details below. All information is kept private and used only for your consultations.</p>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div style="flex:1;overflow-y:auto;padding:3rem 4%">
    <div style="max-width:520px;margin:0 auto">

    <?php if($show_verify): ?>
      <div style="text-align:center;padding:1rem 0;animation:fadeUp .6s ease">
        <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,rgba(0,106,97,.15),rgba(179,17,24,.08));display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;border:2px solid rgba(0,106,97,.15)">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
        </div>
        <h2 style="font-size:1.8rem;margin-bottom:.6rem">Check your email!</h2>
        <p style="color:rgba(21,28,39,0.55);font-size:.95rem;line-height:1.75;margin-bottom:.5rem">
          We sent an activation link to<br/>
          <strong style="color:var(--ink)"><?= htmlspecialchars($verify_email) ?></strong>
        </p>
        <p style="color:#9ab0ae;font-size:.82rem;margin-bottom:2rem">Click the link in the email to activate your account.<br/>The link expires in <strong>24 hours</strong>.</p>
        <div style="background:rgba(0,106,97,.05);border:1px solid rgba(0,106,97,.12);border-radius:16px;padding:1.2rem 1.5rem;text-align:left;margin-bottom:2rem">
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ab0ae;margin-bottom:.8rem">What to do next</div>
          <?php
          $svgInbox  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>';
          $svgSearch = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>';
          $svgLink   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.07 0l2.83-2.83a5 5 0 00-7.07-7.07l-1.5 1.5"/><path d="M14 11a5 5 0 00-7.07 0L4.1 13.83a5 5 0 007.07 7.07l1.5-1.5"/></svg>';
          $svgCheck  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>';
          foreach([[$svgInbox,'Open your email inbox'],[$svgSearch,'Look for an email from TELE-CARE'],[$svgLink,'Click the "Activate Account" link'],[$svgCheck,'Log in and start your consultation']] as [$icon,$text]): ?>
          <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:.6rem;font-size:.88rem;color:var(--ink)">
            <span style="width:18px;height:18px;flex-shrink:0;color:var(--teal);display:inline-flex;"><?= $icon ?></span>
            <?= $text ?>
          </div>
          <?php endforeach ?>
        </div>
        <p style="font-size:.85rem;color:#9ab0ae;margin-bottom:.5rem">Didn't receive the email?</p>
        <button class="resend-btn" id="resendBtn" onclick="resendEmail()">Resend activation email</button>
        <div id="resendMsg" style="font-size:.8rem;color:var(--ink);margin-top:.5rem;min-height:1.2rem"></div>
        <div style="margin-top:2rem"><a href="login.php" style="color:#9ab0ae;font-size:.85rem;text-decoration:none">Already activated? Log in →</a></div>
      </div>
      <script>
        const EMAILJS_PUBLIC_KEY  = 'm-AvAiAdUDsgBbz6D';
        const EMAILJS_SERVICE_ID  = 'service_vr6ygvx';
        const EMAILJS_TEMPLATE_ID = 'template_zhnltnl';
        const patientEmail  = <?= json_encode($verify_email) ?>;
        const patientName   = <?= json_encode($patient_name) ?>;
        const activationURL = <?= json_encode('http://'.$_SERVER['HTTP_HOST'].'/auth/verify.php?token='.urlencode($verify_token)) ?>;
        emailjs.init({ publicKey: EMAILJS_PUBLIC_KEY });
        function sendActivationEmail() {
          return emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_TEMPLATE_ID, { to_email: patientEmail, patient_name: patientName, activation_link: activationURL });
        }
        sendActivationEmail().then(() => console.log('Sent')).catch(e => console.error(e));
        let cooldown = 0;
        function resendEmail() {
          if (cooldown > 0) return;
          const btn = document.getElementById('resendBtn'), msg = document.getElementById('resendMsg');
          btn.disabled = true; btn.innerHTML = '<span class="spinner-inline"></span>Sending...';
          sendActivationEmail().then(() => {
            msg.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:4px;"><path d="M5 13l4 4L19 7"/></svg>Email resent successfully!'; msg.style.color = 'var(--teal)';
            cooldown = 60;
            const t = setInterval(() => { cooldown--; btn.innerHTML = 'Resend again in ' + cooldown + 's'; if (cooldown <= 0) { clearInterval(t); btn.disabled = false; btn.innerHTML = 'Resend activation email'; } }, 1000);
          }).catch(() => { msg.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:4px;"><path d="M6 6l12 12M18 6L6 18"/></svg>Failed. Please try again.'; msg.style.color = 'var(--red)'; btn.disabled = false; btn.innerHTML = 'Resend activation email'; });
        }
      </script>

    <?php else: ?>

      <h2 style="font-size:1.6rem;margin-bottom:.3rem">Create Account</h2>
      <p style="color:rgba(21,28,39,0.55);font-size:.9rem;margin-bottom:1.8rem">Tell us about yourself.</p>

      <?php if($error): ?><div class="alert-error" id="topFormError"><?= htmlspecialchars($error) ?></div><?php endif ?>

      <form method="POST" id="regForm" novalidate>

        <!-- Hidden combined phone field submitted to PHP -->
        <input type="hidden" name="phone_number" id="h_phone"/>
        <!-- Hidden agreement field submitted to PHP -->
        <input type="hidden" name="agree_terms" id="h_agree" value="0"/>

        <!-- Google Register Button -->
        <a href="google-register.php" style="
            display:flex;align-items:center;justify-content:center;gap:.75rem;
            width:100%;padding:.85rem 1rem;
            border:1.5px solid rgba(21,28,39,.18);
            border-radius:8px;
            background:#fff;
            color:#151c27;
            font-family:'Inter',sans-serif;font-size:.92rem;font-weight:600;
            text-decoration:none;
            transition:all .25s;
            box-shadow:0 2px 8px rgba(0,0,0,.07);
            margin-bottom:1.25rem;
        ">
          <svg width="18" height="18" viewBox="0 0 48 48">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.35-8.16 2.35-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
          </svg>
          Sign up with Google
        </a>

        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem">
          <div style="flex:1;height:1px;background:rgba(21,28,39,.1)"></div>
          <span style="font-size:.78rem;color:#9ab0ae;white-space:nowrap">or register manually</span>
          <div style="flex:1;height:1px;background:rgba(21,28,39,.1)"></div>
        </div>

        <div class="grid-3" style="margin-bottom:1rem">
          <div>
            <label class="field-label">First Name <span class="req">*</span></label>
            <input type="text" name="first_name" id="f_first_name" class="field-input" placeholder="Juan" maxlength="30"
              value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
              onInput="this.value=this.value.replace(/[0-9]/g,'');clearFieldErr(this)"/>
            <div class="field-error" id="e_first_name">First name is required.</div>
          </div>
          <div>
            <label class="field-label">Middle Name <span class="optional-tag">(opt.)</span></label>
            <input type="text" name="middle_name" class="field-input" placeholder="Santos" maxlength="30"
              value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>"
              onInput="this.value=this.value.replace(/[0-9]/g,'')"/>
          </div>
          <div>
            <label class="field-label">Last Name <span class="req">*</span></label>
            <input type="text" name="last_name" id="f_last_name" class="field-input" placeholder="Dela Cruz" maxlength="30"
              value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
              onInput="this.value=this.value.replace(/[0-9]/g,'');clearFieldErr(this)"/>
            <div class="field-error" id="e_last_name">Last name is required.</div>
          </div>
        </div>

        <div class="grid-2" style="margin-bottom:1rem">
          <div>
            <label class="field-label">Date of Birth <span class="req">*</span></label>
            <input type="date" name="date_of_birth" id="f_dob" class="field-input"
              value="<?= htmlspecialchars($_POST['date_of_birth']??'') ?>"
              onchange="clearFieldErr(this)"/>
            <div class="field-error" id="e_dob">Must be at least 18 years old.</div>
          </div>
          <div>
            <label class="field-label">Phone Number <span class="optional-tag">(opt.)</span></label>
            <div class="phone-combo" id="combo_phone">
              <div class="phone-prefix-fixed">+63</div>
              <input type="tel" class="phone-number-input" id="f_phone" placeholder="9XXXXXXXXX"
                maxlength="10" inputmode="numeric"
                onInput="this.value=this.value.replace(/[^0-9]/g,'');clearComboErr('phone')"/>
            </div>
            <div class="field-error" id="e_phone">Phone number must start with 9 and be exactly 10 digits.</div>
          </div>
        </div>

        <div style="margin-bottom:1.2rem">
          <label class="field-label">Email Address <span class="req">*</span></label>
          <input type="email" name="email" id="f_email" class="field-input" placeholder="you@email.com" maxlength="100"
            value="<?= htmlspecialchars($_POST['email']??'') ?>"
            onInput="clearFieldErr(this)"/>
          <div class="field-error" id="e_email">Valid email is required.</div>
        </div>

        <div style="margin-bottom:1.2rem">
          <label class="field-label">Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="password" id="f_pw" class="field-input" placeholder="Create a password" maxlength="20" style="padding-right:2.8rem"
              onInput="onPwInput();clearFieldErr(this)"/>
            <button type="button" class="pw-toggle" onclick="togglePw('f_pw','e1s','e1h')">
              <svg id="e1s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg id="e1h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
            </button>
          </div>
          <div class="field-error" id="e_pw">Password does not meet the requirements below.</div>
          <div class="pw-criteria">
            <div class="crit-row" id="c_len"><span class="crit-dot"></span>8 to 20 characters</div>
            <div class="crit-row" id="c_upper"><span class="crit-dot"></span>At least 1 uppercase letter (A–Z)</div>
            <div class="crit-row" id="c_lower"><span class="crit-dot"></span>At least 1 lowercase letter (a–z)</div>
            <div class="crit-row" id="c_num"><span class="crit-dot"></span>At least 1 number (0–9)</div>
          </div>
        </div>

        <div style="margin-bottom:1rem">
          <label class="field-label">Confirm Password <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="confirm_password" id="f_pw2" class="field-input" placeholder="Repeat your password" maxlength="20" style="padding-right:2.8rem"
              onInput="onPw2Input();clearFieldErr(this)"/>
            <button type="button" class="pw-toggle" onclick="togglePw('f_pw2','e2s','e2h')">
              <svg id="e2s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <svg id="e2h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
            </button>
          </div>
          <div id="pw_match_msg" style="font-size:.78rem;margin-top:.35rem;min-height:1.1rem"></div>
          <div class="field-error" id="e_pw2">Passwords do not match.</div>
        </div>

        <!-- ── Terms / Data Privacy agreement ─────────────────────────────── -->
        <div class="terms-row" id="terms_row">
          <input type="checkbox" class="terms-checkbox" id="f_agree" disabled
            onchange="onAgreeToggle()"/>
          <div>
            <div class="terms-text">
              I have read and agree to the
              <button type="button" class="terms-link" onclick="openModal('privacy')">Data Privacy Notice</button>,
              <button type="button" class="terms-link" onclick="openModal('terms')">Terms and Conditions</button>, and
              <button type="button" class="terms-link" onclick="openModal('policy')">Privacy Policy</button> of TELE-CARE.
            </div>
            <div class="terms-hint" id="terms_hint">You must open and read each document (scroll to the end) before you can check this box.</div>
          </div>
        </div>
        <div class="field-error" id="e_agree">You must agree to the Data Privacy Notice, Terms and Conditions, and Privacy Policy to continue.</div>

        <div style="margin-top:1.6rem">
          <button type="submit" class="btn-next" onclick="return combinePhoneAndValidate();">Create My Account</button>
        </div>
      </form>
      <p style="text-align:center;margin-top:2rem;font-size:.88rem;color:rgba(21,28,39,0.55)">Already have an account? <a href="login.php" style="color:var(--red);font-weight:600">Log in</a></p>

    <?php endif ?>
    </div>
  </div>
</div>

<!-- ── Legal Modal ──────────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="legalModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>TELE-CARE Legal Agreements</h3>
      <button type="button" class="modal-close" onclick="closeModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
    <div class="modal-tabs">
      <button type="button" class="modal-tab" data-tab="privacy" onclick="switchTab('privacy')">Data Privacy Notice</button>
      <button type="button" class="modal-tab" data-tab="terms" onclick="switchTab('terms')">Terms &amp; Conditions</button>
      <button type="button" class="modal-tab" data-tab="policy" onclick="switchTab('policy')">Privacy Policy</button>
    </div>
    <div class="modal-body" id="modalBody" onscroll="onModalScroll()">

      <!-- DATA PRIVACY NOTICE -->
      <div class="modal-section" data-section="privacy">
        <h4>1. Who We Are</h4>
        <p>This Data Privacy Notice is issued by TELE-CARE ("we," "us," or "our") for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>

        <h4>2. Personal Data We Collect</h4>
        <p>When you register and use TELE-CARE, we collect:</p>
        <ul>
          <li>Identity information — full name, date of birth, contact number, email address</li>
          <li>Account credentials — password (stored in encrypted/hashed form)</li>
          <li>Health information — medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>
          <li>Technical data — device information, IP address, browser type, and log data related to your use of the platform</li>
          <li>Communications — messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>
        </ul>

        <h4>3. Why We Collect Your Data</h4>
        <p>Your personal and health data are collected and processed to:</p>
        <ul>
          <li>Create and manage your patient account</li>
          <li>Facilitate teleconsultations between you and licensed healthcare providers</li>
          <li>Maintain accurate medical and consultation records</li>
          <li>Send appointment confirmations, reminders, and account-related notifications</li>
          <li>Process payments for consultations, where applicable</li>
          <li>Comply with legal, regulatory, and reporting obligations</li>
          <li>Improve the safety, security, and functionality of the platform</li>
        </ul>

        <h4>4. Sensitive Personal Information</h4>
        <p>Health-related data is classified as "sensitive personal information" under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>

        <h4>5. Data Sharing and Disclosure</h4>
        <p>We do not sell your personal data. Your information may only be shared with:</p>
        <ul>
          <li>Licensed doctors and staff directly involved in your consultation</li>
          <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>
          <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>
        </ul>

        <h4>6. Data Retention</h4>
        <p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>

        <h4>7. Your Rights</h4>
        <p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>

        <h4>8. Security Measures</h4>
        <p>We apply organizational, physical, and technical safeguards — including password hashing, encrypted connections, and access controls — to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>

        <h4>9. Consent</h4>
        <p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>
      </div>

      <!-- TERMS AND CONDITIONS -->
      <div class="modal-section" data-section="terms">
        <h4>1. Acceptance of Terms</h4>
        <p>These Terms and Conditions ("Terms") govern your access to and use of TELE-CARE, operated by TELE-CARE. By creating an account, you agree to be bound by these Terms. If you do not agree, do not use the platform.</p>

        <h4>2. Eligibility</h4>
        <p>You must be at least 18 years old to register a patient account. By registering, you represent that the information you provide is accurate, current, and complete, and that you will keep it updated.</p>

        <h4>3. Nature of the Service</h4>
        <p>TELE-CARE connects patients with licensed healthcare providers for remote consultations. TELE-CARE is a technology platform and does not itself practice medicine. Medical advice, diagnosis, and treatment are provided solely by the licensed healthcare professionals you consult through the platform.</p>

        <h4>4. Not for Emergency Use</h4>
        <p>TELE-CARE is not intended for medical emergencies. If you are experiencing a medical emergency, call your local emergency hotline or go to the nearest emergency room immediately. Do not rely on this platform for time-critical or life-threatening conditions.</p>

        <h4>5. Account Responsibilities</h4>
        <ul>
          <li>You are responsible for maintaining the confidentiality of your login credentials</li>
          <li>You are responsible for all activity that occurs under your account</li>
          <li>You must notify us immediately of any unauthorized use of your account</li>
          <li>Providing false medical or personal information may result in suspension or deactivation of your account</li>
        </ul>

        <h4>6. Consultations and Payments</h4>
        <p>Consultation fees, where applicable, will be disclosed prior to booking. Payment terms, cancellation policies, and refund conditions may be presented separately at the time of booking and form part of these Terms by reference.</p>

        <h4>7. Prohibited Conduct</h4>
        <p>You agree not to misuse the platform, including but not limited to: impersonating another person, attempting to access another user's account or medical records, uploading harmful code, or using the platform for any unlawful purpose.</p>

        <h4>8. Account Suspension and Termination</h4>
        <p>TELE-CARE reserves the right to suspend or deactivate accounts that violate these Terms, provide fraudulent information, or misuse the platform, with or without prior notice where warranted.</p>

        <h4>9. Limitation of Liability</h4>
        <p>To the extent permitted by law, TELE-CARE shall not be liable for indirect, incidental, or consequential damages arising from your use of the platform, technical interruptions, or reliance on information exchanged during a teleconsultation, except as required by applicable law.</p>

        <h4>10. Changes to These Terms</h4>
        <p>We may update these Terms from time to time. Continued use of TELE-CARE after changes are posted constitutes acceptance of the revised Terms.</p>

        <h4>11. Governing Law</h4>
        <p>These Terms are governed by the laws of the Republic of the Philippines.</p>
      </div>

      <!-- PRIVACY POLICY -->
      <div class="modal-section" data-section="policy">
        <h4>1. Overview</h4>
        <p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice.</p>

        <h4>2. Information We Collect Automatically</h4>
        <p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>

        <h4>3. Cookies and Similar Technologies</h4>
        <p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>

        <h4>4. How We Use Information</h4>
        <ul>
          <li>To operate, maintain, and secure the platform</li>
          <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>
          <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>
          <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>
        </ul>

        <h4>5. Third-Party Services</h4>
        <p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>

        <h4>6. Data Storage and International Transfers</h4>
        <p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>

        <h4>7. Children's Privacy</h4>
        <p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>

        <h4>8. Updates to This Policy</h4>
        <p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>

        <h4>9. Contact Us</h4>
        <p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>

        <p style="margin-top:1.2rem;color:#9ab0ae;font-size:.78rem">— End of document —</p>
      </div>

    </div>
    <div class="modal-foot">
      <div class="scroll-progress">
        <span id="scrollLabel">Scroll to read</span>
        <div class="scroll-bar"><div class="scroll-bar-fill" id="scrollFill"></div></div>
      </div>
      <button type="button" class="btn-accept" id="btnAccept" onclick="acceptFromModal()">I've Read This</button>
    </div>
  </div>
</div>

<script>
function combinePhoneFields() {
  const num = document.getElementById('f_phone').value.trim();
  document.getElementById('h_phone').value = num ? ('+63' + num) : '';
}

function clearComboErr(which) {
  document.getElementById('combo_phone').classList.remove('has-error');
  const err = document.getElementById('e_phone');
  if (err) err.classList.remove('visible');
  clearTopFormError();
}

// ── Toast ──────────────────────────────────────────────────────────────────────
function toast(msg) {
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div');
  t.className = 'toast';
  t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => {
    t.style.transition = 'opacity .3s,transform .3s';
    t.style.opacity = '0'; t.style.transform = 'translateX(16px)';
    setTimeout(() => t.remove(), 320);
  }, 3500);
}

function clearTopFormError() {
  const topErr = document.getElementById('topFormError');
  if (topErr) topErr.style.display = 'none';
}

// ── Field errors ───────────────────────────────────────────────────────────────
function showFieldErr(fieldId, msg) {
  const el  = document.getElementById('e_' + fieldId);
  const inp = document.getElementById('f_' + fieldId);
  if (el)  { if (msg) el.textContent = msg; el.classList.add('visible'); }
  if (inp) inp.classList.add('has-error');
}
function clearFieldErr(inp) {
  const key = inp.id.replace('f_', '');
  const el  = document.getElementById('e_' + key);
  if (el)  el.classList.remove('visible');
  inp.classList.remove('has-error');
  clearTopFormError();
}

// ── Legal modal + read-tracking ──────────────────────────────────────────────
const readTabs = { privacy: false, terms: false, policy: false };
let currentTab = 'privacy';

function openModal(startTab) {
  currentTab = startTab || 'privacy';
  document.getElementById('legalModal').classList.add('open');
  switchTab(currentTab);
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('legalModal').classList.remove('open');
  document.body.style.overflow = '';
}
function switchTab(tab) {
  currentTab = tab;
  document.querySelectorAll('.modal-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  document.querySelectorAll('.modal-section').forEach(s => s.classList.toggle('active', s.dataset.section === tab));
  const body = document.getElementById('modalBody');
  body.scrollTop = 0;
  updateScrollUI();
}
function onModalScroll() {
  updateScrollUI();
}
function updateScrollUI() {
  const body = document.getElementById('modalBody');
  const maxScroll = body.scrollHeight - body.clientHeight;
  const pct = maxScroll <= 0 ? 100 : Math.min(100, Math.round((body.scrollTop / maxScroll) * 100));
  document.getElementById('scrollFill').style.width = pct + '%';

  const atBottom = maxScroll <= 0 || body.scrollTop >= maxScroll - 4;
  if (atBottom) readTabs[currentTab] = true;

  const label = document.getElementById('scrollLabel');
  const btn   = document.getElementById('btnAccept');
  const allRead = readTabs.privacy && readTabs.terms && readTabs.policy;

  if (readTabs[currentTab]) {
    label.textContent = allRead ? 'All documents read' : 'Read — check the other tabs too';
  } else {
    label.textContent = 'Scroll to the bottom to mark as read (' + pct + '%)';
  }
  btn.classList.toggle('unlocked', allRead);
}
function acceptFromModal() {
  const allRead = readTabs.privacy && readTabs.terms && readTabs.policy;
  if (!allRead) {
    toast('Please scroll through all three documents first.');
    return;
  }
  const cb = document.getElementById('f_agree');
  cb.disabled = false;
  cb.classList.add('unlocked');
  cb.checked = true;
  document.getElementById('h_agree').value = '1';
  document.getElementById('terms_hint').textContent = 'Thank you — you may uncheck this box if you change your mind.';
  document.getElementById('terms_row').classList.remove('has-error');
  document.getElementById('e_agree').classList.remove('visible');
  closeModal();
}
function onAgreeToggle() {
  const cb = document.getElementById('f_agree');
  document.getElementById('h_agree').value = cb.checked ? '1' : '0';
}
function onAgreeToggle() {
function combinePhoneAndValidate() {
  let ok = true;
  const check = (id, cond, msg) => {
    const el = document.getElementById('f_' + id);
    if (!el) return;
    if (!cond(el.value)) { showFieldErr(id, msg); ok = false; }
    else clearFieldErr(el);
  };
  check('first_name', v => v.trim() !== '', 'First name is required.');
  check('last_name',  v => v.trim() !== '', 'Last name is required.');
  check('dob', v => {
    if (!v) return false;
    const age = (Date.now() - new Date(v).getTime()) / (365.25 * 86400000);
    return age >= 18;
  }, 'You must be at least 18 years old.');
  check('email', v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), 'Valid email is required.');

  // Phone is optional — only validate format if filled in
  const phoneNum = document.getElementById('f_phone').value.trim();
  if (phoneNum && !/^9\d{9}$/.test(phoneNum)) {
    document.getElementById('combo_phone').classList.add('has-error');
    document.getElementById('e_phone').classList.add('visible');
    ok = false;
  } else {
    clearComboErr('phone');
  }

  const pw  = document.getElementById('f_pw').value;
  const pw2 = document.getElementById('f_pw2').value;
  const critOk = pw.length >= 8 && pw.length <= 20 && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw);
  if (!critOk) { showFieldErr('pw', null); ok = false; }
  else clearFieldErr(document.getElementById('f_pw'));
  if (pw !== pw2 || pw2 === '') { showFieldErr('pw2', null); ok = false; }
  else clearFieldErr(document.getElementById('f_pw2'));

  // Terms agreement check
  if (document.getElementById('h_agree').value !== '1' || !document.getElementById('f_agree').checked) {
    document.getElementById('terms_row').classList.add('has-error');
    document.getElementById('e_agree').classList.add('visible');
    ok = false;
  } else {
    document.getElementById('terms_row').classList.remove('has-error');
    document.getElementById('e_agree').classList.remove('visible');
  }

  if (!ok) {
    toast('Please fill in all required fields correctly.');
    return false;
  }

  combinePhoneFields();
  return true;
}

// ── Password UI ────────────────────────────────────────────────────────────────
function onPwInput() {
  const v = document.getElementById('f_pw').value;
  const set = (id, met) => document.getElementById(id).classList.toggle('met', met);
  set('c_len',   v.length >= 8 && v.length <= 20);
  set('c_upper', /[A-Z]/.test(v));
  set('c_lower', /[a-z]/.test(v));
  set('c_num',   /[0-9]/.test(v));
  onPw2Input();
}
function onPw2Input() {
  const pw  = document.getElementById('f_pw').value;
  const pw2 = document.getElementById('f_pw2').value;
  const msg = document.getElementById('pw_match_msg');
  if (!pw2) { msg.textContent = ''; return; }
  if (pw === pw2) { msg.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:4px;"><path d="M5 13l4 4L19 7"/></svg>Passwords match'; msg.style.color = 'var(--teal)'; }
  else            { msg.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:4px;"><path d="M6 6l12 12M18 6L6 18"/></svg>Passwords do not match'; msg.style.color = 'var(--red)'; }
}
function togglePw(id, showId, hideId) {
  const f = document.getElementById(id);
  if (f.type === 'password') {
    f.type = 'text';
    document.getElementById(showId).style.display = 'none';
    document.getElementById(hideId).style.display = 'block';
  } else {
    f.type = 'password';
    document.getElementById(showId).style.display = 'block';
    document.getElementById(hideId).style.display = 'none';
  }
}

// ── DOB max ────────────────────────────────────────────────────────────────────
const dobEl = document.getElementById('f_dob');
if (dobEl) {
  const max = new Date();
  max.setFullYear(max.getFullYear() - 18);
  dobEl.max = max.toISOString().split('T')[0];
}
</script>
</body>
</html>


