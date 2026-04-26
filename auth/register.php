<?php
require_once '../database/config.php';

$error        = '';
$show_verify  = false;
$verify_email = '';
$patient_name = '';
$verify_token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name             = trim($_POST['first_name'] ?? '');
    $middle_name            = trim($_POST['middle_name'] ?? '');
    $last_name              = trim($_POST['last_name'] ?? '');
    $date_of_birth          = $_POST['date_of_birth'] ?? '';
    $gender                 = $_POST['gender'] ?? '';
    $email                  = trim($_POST['email'] ?? '');
    $phone_number           = trim($_POST['phone_number'] ?? '');   // full intl e.g. +639XXXXXXXXX
    $emergency_name         = trim($_POST['emergency_name'] ?? '');
    $emergency_relationship = trim($_POST['emergency_relationship'] ?? '');
    $emergency_number       = trim($_POST['emergency_number'] ?? ''); // full intl
    $password               = $_POST['password'] ?? '';
    $confirm_password       = $_POST['confirm_password'] ?? '';
    $security_question      = trim($_POST['security_question'] ?? '');
    $security_answer        = trim($_POST['security_answer'] ?? '');
    $country_region         = trim($_POST['country_region'] ?? '');
    $ph_city                = trim($_POST['ph_city'] ?? '');
    $city_manual            = trim($_POST['city_manual'] ?? '');
    $home_address           = trim($_POST['home_address'] ?? '');
    $insurance_provider     = trim($_POST['insurance_provider'] ?? '');
    $insurance_policy_no    = trim($_POST['insurance_policy_no'] ?? '');
    $preferred_language     = trim($_POST['preferred_language'] ?? 'English');
    $other_language         = trim($_POST['other_language'] ?? '');

    $full_name = $middle_name !== ''
        ? $first_name . ' ' . $middle_name . ' ' . $last_name
        : $first_name . ' ' . $last_name;

    $city = ($country_region === 'Philippines') ? $ph_city : $city_manual;

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($date_of_birth) || empty($gender) || empty($phone_number)) {
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
  } elseif (!preg_match('/^\+639\d{9}$/', $phone_number)) {
    $error = 'Please enter a valid Philippine mobile number (e.g. +639XXXXXXXXX).';
    } elseif (!empty($emergency_relationship) && strlen($emergency_relationship) > 20) {
        $error = 'Emergency relationship must not exceed 20 characters.';
    } elseif (!empty($emergency_relationship) && preg_match('/\d/', $emergency_relationship)) {
        $error = 'Emergency relationship cannot contain numbers.';
  } elseif (!empty($emergency_number) && !preg_match('/^\+639\d{9}$/', $emergency_number)) {
    $error = 'Emergency number must be a valid Philippine mobile number (e.g. +639XXXXXXXXX).';
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
    } elseif (!empty($security_answer) && strlen($security_answer) > 50) {
        $error = 'Security answer must not exceed 50 characters.';
    } elseif (strlen($home_address) > 100) {
        $error = 'Home address must not exceed 100 characters.';
    } elseif (strlen($city) > 50) {
        $error = 'City must not exceed 50 characters.';
    } elseif (strlen($insurance_provider) > 20) {
        $error = 'Insurance provider must not exceed 20 characters.';
    } elseif (strlen($insurance_policy_no) > 20) {
        $error = 'Policy number must not exceed 20 characters.';
    } elseif ($preferred_language === 'Other' && empty($other_language)) {
        $error = 'Please specify your language.';
    } elseif ($preferred_language === 'Other' && strlen($other_language) > 20) {
        $error = 'Language name must not exceed 20 characters.';
    } else {
        $final_language = ($preferred_language === 'Other') ? $other_language : $preferred_language;

        $stmt = $conn->prepare("SELECT id FROM patients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed        = password_hash($password, PASSWORD_BCRYPT);
            $hashed_answer = $security_answer ? password_hash(strtolower($security_answer), PASSWORD_BCRYPT) : null;
            $token         = bin2hex(random_bytes(32));
            $expires_at    = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $photo_path = null;
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Failed to upload profile photo. Please try again.';
                } else {
                    $allowed_image_types = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/gif'  => 'gif',
                        'image/webp' => 'webp'
                    ];
                    $tmp_name = $_FILES['profile_photo']['tmp_name'];
                    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                    $mime     = $finfo ? finfo_file($finfo, $tmp_name) : false;
                    if ($finfo) finfo_close($finfo);
                    if (!$mime || !isset($allowed_image_types[$mime]) || getimagesize($tmp_name) === false) {
                        $error = 'Profile photo must be a valid image file (JPG, PNG, GIF, or WEBP).';
                    } else {
                        $upload_dir = '../uploads/profiles/';
                        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                        $filename = uniqid('patient_', true) . '.' . $allowed_image_types[$mime];
                        if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                            $photo_path = 'uploads/profiles/' . $filename;
                        } else {
                            $error = 'Failed to save profile photo. Please try again.';
                        }
                    }
                }
            }

            if ($error === '') {
                $insert = $conn->prepare("
                    INSERT INTO patients (
                        full_name,date_of_birth,gender,email,phone_number,profile_photo,
                        emergency_name,emergency_relationship,emergency_number,
                        password,security_question,security_answer,
                        home_address,city,country_region,
                        insurance_provider,insurance_policy_no,preferred_language,
                        is_verified,verification_token,token_expires_at
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)
                ");
                $insert->bind_param("ssssssssssssssssssss",
                    $full_name, $date_of_birth, $gender, $email, $phone_number, $photo_path,
                    $emergency_name, $emergency_relationship, $emergency_number,
                    $hashed, $security_question, $hashed_answer,
                    $home_address, $city, $country_region,
                    $insurance_provider, $insurance_policy_no, $final_language,
                    $token, $expires_at
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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);min-height:100vh}
    h1,h2{font-family:'Playfair Display',serif}
    .left-panel{background:linear-gradient(160deg,var(--green) 0%,#1a3330 100%);position:relative;overflow:hidden}
    .left-panel::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(63,130,227,0.08) 1px,transparent 1px),linear-gradient(90deg,rgba(63,130,227,0.08) 1px,transparent 1px);background-size:44px 44px;animation:gridMove 20s linear infinite}
    @keyframes gridMove{from{transform:translateY(0)}to{transform:translateY(44px)}}
    .orb{position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;animation:pulse 6s ease-in-out infinite}
    @keyframes pulse{0%,100%{transform:scale(1);opacity:.7}50%{transform:scale(1.2);opacity:1}}
    .step-bar{display:flex;gap:.5rem;margin-bottom:2rem}
    .step-dot{flex:1;height:4px;border-radius:2px;background:rgba(255,255,255,.15);transition:background .4s}
    .step-dot.active{background:var(--red)}.step-dot.done{background:rgba(255,255,255,.5)}
    .step-panel{display:none;animation:fadeUp .4s ease}.step-panel.active{display:block}
    @keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
    .field-label{display:block;font-size:.78rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#5a7a77;margin-bottom:.45rem}
    .field-label .req{color:var(--red)}
    .field-input{width:100%;padding:.75rem 1rem;border:1.5px solid rgba(36,68,65,.15);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:.95rem;background:var(--white);color:var(--green);outline:none;transition:border-color .25s,box-shadow .25s}
    .field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,.12)}
    .field-input.has-error{border-color:var(--red)!important;box-shadow:0 0 0 3px rgba(195,54,67,.1)!important}
    .field-input:disabled{background:#f0f0f0;color:#aaa;cursor:not-allowed}
    select.field-input{cursor:pointer}
    .field-error{font-size:.76rem;color:var(--red);margin-top:.3rem;display:none}
    .field-error.visible{display:block}
    .btn-next{width:100%;padding:.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:600;font-size:.95rem;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(195,54,67,.3)}
    .btn-next:hover{background:#a82d38;transform:translateY(-2px)}
    .btn-back{width:100%;padding:.9rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:.95rem;border:1.5px solid rgba(36,68,65,.2);cursor:pointer;transition:all .3s;margin-bottom:.75rem}
    .btn-back:hover{background:rgba(36,68,65,.06)}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
    @media(max-width:580px){.grid-2{grid-template-columns:1fr}.grid-3{grid-template-columns:1fr}}
    .alert-error{background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.25);color:var(--red);border-radius:12px;padding:.85rem 1rem;font-size:.88rem;margin-bottom:1.2rem}
    .optional-tag{font-size:.72rem;font-weight:400;color:#9ab0ae;text-transform:none;letter-spacing:0;margin-left:.3rem}
    .section-divider{font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ab0ae;border-bottom:1px solid rgba(36,68,65,.1);padding-bottom:.5rem;margin:1.5rem 0 1rem}
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0}
    .pw-toggle:hover{color:var(--green)}
    .pw-criteria{background:#f8fffe;border:1.5px solid rgba(36,68,65,.1);border-radius:12px;padding:.85rem 1rem;margin-top:.6rem}
    .crit-row{display:flex;align-items:center;gap:.55rem;font-size:.8rem;color:#aabfbd;margin-bottom:.3rem;transition:color .2s}
    .crit-row:last-child{margin-bottom:0}
    .crit-row.met{color:var(--green);font-weight:500}
    .crit-dot{width:7px;height:7px;border-radius:50%;background:#d4d4d4;flex-shrink:0;transition:background .2s}
    .crit-row.met .crit-dot{background:var(--green)}
    .toast-wrap{position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
    .toast{background:#fff;border-radius:12px;padding:.75rem 1.1rem;font-size:.84rem;font-weight:500;box-shadow:0 8px 28px rgba(0,0,0,.13);border-left:4px solid var(--red);color:var(--green);animation:toastIn .3s ease;pointer-events:auto;max-width:300px;line-height:1.4}
    .toast.ok{border-left-color:var(--green)}
    @keyframes toastIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}
    .resend-btn{background:none;border:none;color:var(--red);font-weight:600;font-size:.9rem;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:underline;padding:0}
    .resend-btn:disabled{color:#9ab0ae;cursor:not-allowed;text-decoration:none}
    .spinner-inline{display:inline-block;width:14px;height:14px;border:2px solid rgba(195,54,67,.3);border-top-color:var(--red);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
    .spinner-sm{display:inline-block;width:11px;height:11px;border:2px solid rgba(36,68,65,.15);border-top-color:var(--green);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:4px}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ── Phone combo ─────────────────────────────────────────────────────── */
    .phone-combo{display:flex;border:1.5px solid rgba(36,68,65,.15);border-radius:12px;overflow:hidden;background:var(--white);transition:border-color .25s,box-shadow .25s}
    .phone-combo:focus-within{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,.12)}
    .phone-combo.has-error{border-color:var(--red)!important;box-shadow:0 0 0 3px rgba(195,54,67,.1)!important}
  .phone-prefix-fixed{display:flex;align-items:center;justify-content:center;border-right:1.5px solid rgba(36,68,65,.1);background:rgba(36,68,65,.05);color:var(--green);font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:700;padding:.75rem .85rem;min-width:64px;flex-shrink:0}
    .phone-number-input{border:none;outline:none;flex:1;padding:.75rem 1rem;font-family:'DM Sans',sans-serif;font-size:.95rem;color:var(--green);background:transparent;min-width:0}
    .phone-number-input::placeholder{color:#b0c4c2}
  </style>
</head>
<body>

<div class="toast-wrap" id="toastWrap"></div>

<div style="display:flex;min-height:100vh">

  <!-- LEFT -->
  <div class="left-panel" style="width:42%;display:flex;flex-direction:column;justify-content:center;padding:3rem;position:sticky;top:0;height:100vh">
    <div class="orb" style="width:320px;height:320px;background:radial-gradient(circle,rgba(63,130,227,.2) 0%,transparent 70%);top:-60px;right:-60px"></div>
    <div class="orb" style="width:220px;height:220px;background:radial-gradient(circle,rgba(195,54,67,.15) 0%,transparent 70%);bottom:60px;left:20px;animation-delay:3s"></div>
    <div style="position:relative;z-index:2">
      <a href="../index.php" style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:#fff;text-decoration:none;letter-spacing:.04em">TELE<span style="color:var(--red)">-</span>CARE</a>
      <div style="margin-top:3rem">
        <h1 style="font-size:2.2rem;color:#fff;line-height:1.2;margin-bottom:1rem">Create Your<br/>Patient Account</h1>
        <p style="color:rgba(255,255,255,.55);font-size:.95rem;line-height:1.75">Fill in your details across three short steps. All information is kept private and used only for your consultations.</p>
      </div>
      <div style="margin-top:3rem;display:flex;flex-direction:column;gap:1.2rem">
        <?php foreach([['01','Personal Information','Your basic details and emergency contact'],['02','Security','Set up your password and recovery option'],['03','Location & Coverage','Your address and health insurance info']] as $i=>$s): ?>
        <div class="side-step" data-step="<?= $i+1 ?>" style="display:flex;gap:1rem;align-items:center;opacity:<?= $i===0?'1':'0.4' ?>;transition:opacity .4s">
          <div style="width:36px;height:36px;border-radius:50%;border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0"><?= $s[0] ?></div>
          <div><div style="font-weight:600;color:#fff;font-size:.9rem"><?= $s[1] ?></div><div style="font-size:.78rem;color:rgba(255,255,255,.45)"><?= $s[2] ?></div></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <!-- RIGHT -->
  <div style="flex:1;overflow-y:auto;padding:3rem 4%">
    <div style="max-width:520px;margin:0 auto">

    <?php if($show_verify): ?>
      <div style="text-align:center;padding:1rem 0;animation:fadeUp .6s ease">
        <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,rgba(63,130,227,.15),rgba(36,68,65,.1));display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem;border:2px solid rgba(63,130,227,.15)">📧</div>
        <h2 style="font-size:1.8rem;margin-bottom:.6rem">Check your email!</h2>
        <p style="color:#6b8a87;font-size:.95rem;line-height:1.75;margin-bottom:.5rem">
          We sent an activation link to<br/>
          <strong style="color:var(--green)"><?= htmlspecialchars($verify_email) ?></strong>
        </p>
        <p style="color:#9ab0ae;font-size:.82rem;margin-bottom:2rem">Click the link in the email to activate your account.<br/>The link expires in <strong>24 hours</strong>.</p>
        <div style="background:rgba(63,130,227,.05);border:1px solid rgba(63,130,227,.12);border-radius:16px;padding:1.2rem 1.5rem;text-align:left;margin-bottom:2rem">
          <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ab0ae;margin-bottom:.8rem">What to do next</div>
          <?php foreach([['📬','Open your email inbox'],['🔍','Look for an email from TELE-CARE'],['🔗','Click the "Activate Account" link'],['✅','Log in and start your consultation']] as [$icon,$text]): ?>
          <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:.6rem;font-size:.88rem;color:var(--green)"><span><?= $icon ?></span><?= $text ?></div>
          <?php endforeach ?>
        </div>
        <p style="font-size:.85rem;color:#9ab0ae;margin-bottom:.5rem">Didn't receive the email?</p>
        <button class="resend-btn" id="resendBtn" onclick="resendEmail()">Resend activation email</button>
        <div id="resendMsg" style="font-size:.8rem;color:var(--green);margin-top:.5rem;min-height:1.2rem"></div>
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
            msg.textContent = '✓ Email resent successfully!'; msg.style.color = 'var(--green)';
            cooldown = 60;
            const t = setInterval(() => { cooldown--; btn.innerHTML = 'Resend again in ' + cooldown + 's'; if (cooldown <= 0) { clearInterval(t); btn.disabled = false; btn.innerHTML = 'Resend activation email'; } }, 1000);
          }).catch(() => { msg.textContent = '✗ Failed. Please try again.'; msg.style.color = 'var(--red)'; btn.disabled = false; btn.innerHTML = 'Resend activation email'; });
        }
      </script>

    <?php else: ?>

      <div class="step-bar">
        <div class="step-dot active" id="dot-1"></div>
        <div class="step-dot" id="dot-2"></div>
        <div class="step-dot" id="dot-3"></div>
      </div>
  <?php if($error): ?><div class="alert-error" id="topFormError"><?= htmlspecialchars($error) ?></div><?php endif ?>

      <form method="POST" enctype="multipart/form-data" id="regForm" novalidate>

        <!-- Hidden combined phone fields submitted to PHP -->
        <input type="hidden" name="phone_number"   id="h_phone"/>
        <input type="hidden" name="emergency_number" id="h_emergency_number"/>

        <!-- ── STEP 1 ── -->
        <div class="step-panel active" id="step-1">
          <h2 style="font-size:1.6rem;margin-bottom:.3rem">Personal Information</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.8rem">Tell us about yourself.</p>

          <!-- Google Register Button -->
          <a href="google-register.php" style="
              display:flex;align-items:center;justify-content:center;gap:.75rem;
              width:100%;padding:.85rem 1rem;
              border:1.5px solid rgba(36,68,65,.18);
              border-radius:50px;
              background:#fff;
              color:#244441;
              font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:600;
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
            <div style="flex:1;height:1px;background:rgba(36,68,65,.1)"></div>
            <span style="font-size:.78rem;color:#9ab0ae;white-space:nowrap">or register manually</span>
            <div style="flex:1;height:1px;background:rgba(36,68,65,.1)"></div>
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
              <label class="field-label">Gender <span class="req">*</span></label>
              <select name="gender" id="f_gender" class="field-input" onchange="clearFieldErr(this)">
                <option value="">Select gender</option>
                <?php foreach(['Male','Female','Prefer not to say'] as $g): ?>
                <option value="<?= $g ?>" <?= (($_POST['gender']??'')===$g)?'selected':'' ?>><?= $g ?></option>
                <?php endforeach ?>
              </select>
              <div class="field-error" id="e_gender">Please select a gender.</div>
            </div>
          </div>

          <div class="grid-2" style="margin-bottom:1rem">
            <div>
              <label class="field-label">Email Address <span class="req">*</span></label>
              <input type="email" name="email" id="f_email" class="field-input" placeholder="you@email.com" maxlength="100"
                value="<?= htmlspecialchars($_POST['email']??'') ?>"
                onInput="clearFieldErr(this)"/>
              <div class="field-error" id="e_email">Valid email is required.</div>
            </div>
            <div>
              <label class="field-label">Phone Number <span class="req">*</span></label>
              <div class="phone-combo" id="combo_phone">
                <div class="phone-prefix-fixed">+63</div>
                <input type="tel" class="phone-number-input" id="f_phone" placeholder="9XXXXXXXXX"
                  maxlength="10" inputmode="numeric"
                  onInput="this.value=this.value.replace(/[^0-9]/g,'');clearComboErr('phone')"/>
              </div>
              <div class="field-error" id="e_phone">Valid phone number required.</div>
            </div>
          </div>

          <div style="margin-bottom:1rem">
            <label class="field-label">Profile Photo <span class="optional-tag">(optional)</span></label>
            <input type="file" name="profile_photo" id="f_profile_photo" class="field-input" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp" style="padding:.6rem"/>
          </div>

          <div class="section-divider">Emergency Contact <span class="optional-tag" style="text-transform:none">(optional)</span></div>
          <div style="margin-bottom:1rem">
            <label class="field-label">Contact Name</label>
            <input type="text" name="emergency_name" class="field-input" placeholder="Full name" maxlength="50"
              value="<?= htmlspecialchars($_POST['emergency_name']??'') ?>"
              onInput="this.value=this.value.replace(/[0-9]/g,'')"/>
          </div>
          <div class="grid-2">
            <div>
              <label class="field-label">Relationship</label>
              <input type="text" name="emergency_relationship" class="field-input" placeholder="e.g. Mother" maxlength="20"
                value="<?= htmlspecialchars($_POST['emergency_relationship']??'') ?>"
                onInput="this.value=this.value.replace(/[0-9]/g,'')"/>
            </div>
            <div>
              <label class="field-label">Contact Number</label>
              <div class="phone-combo" id="combo_emergency">
                <div class="phone-prefix-fixed">+63</div>
                <input type="tel" class="phone-number-input" id="f_emergency_number" placeholder="9XXXXXXXXX"
                  maxlength="10" inputmode="numeric"
                  onInput="this.value=this.value.replace(/[^0-9]/g,'')"/>
              </div>
            </div>
          </div>

          <div style="margin-top:2rem">
            <button type="button" class="btn-next" onclick="goStep(2)">Continue to Security →</button>
          </div>
        </div>

        <!-- ── STEP 2 ── -->
        <div class="step-panel" id="step-2">
          <h2 style="font-size:1.6rem;margin-bottom:.3rem">Security</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.8rem">Set a strong password for your account.</p>

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

          <div class="section-divider">Recovery Option <span class="optional-tag" style="text-transform:none">(optional)</span></div>
          <div style="margin-bottom:1rem">
            <label class="field-label">Security Question</label>
            <select name="security_question" class="field-input">
              <option value="">Select a question</option>
              <option>What is your mother's maiden name?</option>
              <option>What is the name of your first pet?</option>
              <option>What city were you born in?</option>
              <option>What is your elementary school's name?</option>
            </select>
          </div>
          <div style="margin-bottom:1rem">
            <label class="field-label">Your Answer</label>
            <input type="text" name="security_answer" class="field-input" placeholder="Your answer (case-insensitive)" maxlength="50"/>
          </div>

          <div style="margin-top:2rem">
            <button type="button" class="btn-back" onclick="goStep(1)">← Back</button>
            <button type="button" class="btn-next" onclick="goStep(3)">Continue to Location →</button>
          </div>
        </div>

        <!-- ── STEP 3 ── -->
        <div class="step-panel" id="step-3">
          <h2 style="font-size:1.6rem;margin-bottom:.3rem">Location & Coverage</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.8rem">Help us match you with available doctors in your area.</p>

          <input type="hidden" name="country_region" value="Philippines"/>

          <div style="margin-bottom:1rem;">
            <label class="field-label">Country</label>
            <input type="text" class="field-input" value="Philippines" disabled/>
          </div>

          <div id="ph_cascade" style="display:block">
            <div style="margin-bottom:1rem">
              <label class="field-label">Region</label>
              <select name="ph_region" id="sel_region" class="field-input" onchange="onRegionChange()">
                <option value="">Select region</option>
              </select>
              <div id="lbl_region" style="font-size:.75rem;color:#9ab0ae;margin-top:.3rem;display:none"><span class="spinner-sm"></span>Loading regions…</div>
            </div>
            <div style="margin-bottom:1rem">
              <label class="field-label">City / Municipality</label>
              <select name="ph_city" id="sel_city" class="field-input" disabled>
                <option value="">Select city / municipality</option>
              </select>
              <div id="lbl_city" style="font-size:.75rem;color:#9ab0ae;margin-top:.3rem;display:none"><span class="spinner-sm"></span>Loading cities…</div>
            </div>
          </div>

          <input type="hidden" name="city_manual" value=""/>

          <div style="margin-bottom:1rem">
            <label class="field-label">House No. / Unit / Block No. <span class="optional-tag">(optional)</span></label>
            <input type="text" name="home_address" class="field-input"
              placeholder="e.g. Blk 4 Lot 7, Sampaguita St., Brgy. San Jose" maxlength="100"
              value="<?= htmlspecialchars($_POST['home_address']??'') ?>"/>
          </div>

          <div style="margin-bottom:1rem">
            <label class="field-label">Additional Address Info <span class="optional-tag">(optional)</span></label>
            <input type="text" name="address_extra" class="field-input"
              placeholder="Landmark, subdivision, floor, building name, etc." maxlength="100"
              value="<?= htmlspecialchars($_POST['address_extra']??'') ?>"/>
          </div>

          <div class="section-divider">Health Insurance <span class="optional-tag" style="text-transform:none">(optional)</span></div>
          <div class="grid-2">
            <div>
              <label class="field-label">Insurance Provider</label>
              <input type="text" name="insurance_provider" class="field-input" placeholder="e.g. PhilHealth" maxlength="20"
                value="<?= htmlspecialchars($_POST['insurance_provider']??'') ?>"/>
            </div>
            <div>
              <label class="field-label">Policy Number</label>
              <input type="text" name="insurance_policy_no" class="field-input" placeholder="Policy no." maxlength="20"
                value="<?= htmlspecialchars($_POST['insurance_policy_no']??'') ?>"/>
            </div>
          </div>

          <div style="margin-top:1rem">
            <label class="field-label">Preferred Language</label>
            <select name="preferred_language" class="field-input" onchange="toggleOtherLang()">
              <?php foreach(['English','Filipino','Cebuano','Ilocano','Other'] as $lang): ?>
              <option value="<?= $lang ?>" <?= (($_POST['preferred_language']??'English')===$lang)?'selected':'' ?>><?= $lang ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div id="other_lang_div" style="margin-top:1rem;display:none">
            <label class="field-label">Specify Language</label>
            <input type="text" name="other_language" class="field-input" placeholder="e.g. Spanish" maxlength="20"
              value="<?= htmlspecialchars($_POST['other_language']??'') ?>"/>
          </div>

          <div style="margin-top:2rem">
            <button type="button" class="btn-back" onclick="goStep(2)">← Back</button>
            <button type="submit" class="btn-next" onclick="combinePhoneFields()">Create My Account</button>
          </div>
        </div>

      </form>
      <p style="text-align:center;margin-top:2rem;font-size:.88rem;color:#6b8a87">Already have an account? <a href="login.php" style="color:var(--red);font-weight:600">Log in</a></p>

    <?php endif ?>
    </div>
  </div>
</div>

<script>
let currentStep = 1;
const PSGC_BASE = 'https://psgc.cloud/api';

function combinePhoneFields() {
  const num    = document.getElementById('f_phone').value.trim();
  document.getElementById('h_phone').value = num ? ('+63' + num) : '';

  const eNum    = document.getElementById('f_emergency_number').value.trim();
  document.getElementById('h_emergency_number').value = eNum ? ('+63' + eNum) : '';
}

function clearComboErr(which) {
  const comboId = which === 'phone' ? 'combo_phone' : 'combo_emergency';
  const errId   = which === 'phone' ? 'e_phone' : 'e_emergency';
  document.getElementById(comboId).classList.remove('has-error');
  const err = document.getElementById(errId);
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

// ── Step nav ───────────────────────────────────────────────────────────────────
async function goStep(n) {
  if (n > currentStep) {
    if (currentStep === 1 && !(await validateStep1())) return;
    if (currentStep === 2 && !validateStep2()) return;
  }
  document.getElementById('step-' + currentStep).classList.remove('active');
  document.getElementById('dot-'  + currentStep).classList.remove('active');
  document.getElementById('dot-'  + currentStep).classList.add('done');
  currentStep = n;
  document.getElementById('step-' + currentStep).classList.add('active');
  document.getElementById('dot-'  + currentStep).classList.add('active');
  document.querySelectorAll('.side-step').forEach(el =>
    el.style.opacity = parseInt(el.dataset.step) === currentStep ? '1' : '0.4'
  );
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function validateStep1() {
  let ok = true;
  let emailExistsError = false;
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
  check('gender',  v => v !== '', 'Please select a gender.');
  check('email',   v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v), 'Valid email is required.');

  // Validate phone combo
  const phoneNum   = document.getElementById('f_phone').value.trim();
  if (!/^9\d{9}$/.test(phoneNum)) {
    document.getElementById('combo_phone').classList.add('has-error');
    const phoneErr = document.getElementById('e_phone');
    if (phoneErr) phoneErr.textContent = 'Phone number must start with 9 and be exactly 10 digits.';
    document.getElementById('e_phone').classList.add('visible');
    ok = false;
  } else {
    clearComboErr('phone');
  }

  const emergencyNum = document.getElementById('f_emergency_number').value.trim();
  if (emergencyNum && !/^9\d{9}$/.test(emergencyNum)) {
    document.getElementById('combo_emergency').classList.add('has-error');
    ok = false;
  } else {
    document.getElementById('combo_emergency').classList.remove('has-error');
  }

  if (ok) {
    const emailInput = document.getElementById('f_email');
    const emailValue = emailInput ? emailInput.value.trim() : '';
    if (emailValue !== '') {
      const emailExists = await checkEmailExists(emailValue);
      if (emailExists) {
        showFieldErr('email', 'An account with this email already exists.');
        toast('This email is already registered. Please log in or use another email.');
        emailExistsError = true;
        ok = false;
      }
    }
  }

  if (!ok && !emailExistsError) {
    toast('Please fill in all required fields correctly.');
  }
  if (ok) {
    clearTopFormError();
  }
  return ok;
}

async function checkEmailExists(email) {
  try {
    const res = await fetch('check_email.php?email=' + encodeURIComponent(email), {
      method: 'GET',
      headers: { 'Accept': 'application/json' }
    });

    if (!res.ok) return false;
    const data = await res.json();
    return !!(data && data.ok && data.exists === true);
  } catch {
    return false;
  }
}

function validateStep2() {
  let ok = true;
  const pw  = document.getElementById('f_pw').value;
  const pw2 = document.getElementById('f_pw2').value;
  const critOk = pw.length >= 8 && pw.length <= 20 && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw);
  if (!critOk) { showFieldErr('pw', null); ok = false; }
  else clearFieldErr(document.getElementById('f_pw'));
  if (pw !== pw2 || pw2 === '') { showFieldErr('pw2', null); ok = false; }
  else clearFieldErr(document.getElementById('f_pw2'));
  if (!ok) toast('Please fix your password before continuing.');
  return ok;
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
  if (pw === pw2) { msg.textContent = '✓ Passwords match'; msg.style.color = 'var(--green)'; }
  else            { msg.textContent = '✗ Passwords do not match'; msg.style.color = 'var(--red)'; }
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

// ── PSGC Cascade ──────────────────────────────────────────────────────────────
function onCountryChange() {
  const ph  = document.getElementById('ph_cascade');
  ph.style.display = 'block';
  if (document.getElementById('sel_region').options.length <= 1) loadRegions();
}
async function loadRegions() {
  const sel = document.getElementById('sel_region');
  const lbl = document.getElementById('lbl_region');
  lbl.style.display = 'block'; sel.disabled = true;
  try {
    const res  = await fetch(PSGC_BASE + '/regions/');
    const data = await res.json();
    data.sort((a, b) => a.name.localeCompare(b.name)).forEach(r => {
      const o = document.createElement('option');
      o.value = r.code; o.textContent = r.name;
      sel.appendChild(o);
    });
    sel.disabled = false;
  } catch { sel.innerHTML = '<option value="">Could not load regions</option>'; }
  lbl.style.display = 'none';
}
async function onRegionChange() {
  const code = document.getElementById('sel_region').value;
  const sel  = document.getElementById('sel_city');
  const lbl  = document.getElementById('lbl_city');
  sel.innerHTML = '<option value="">Select city / municipality</option>';
  sel.disabled  = true;
  if (!code) return;
  lbl.style.display = 'block';
  try {
    const res  = await fetch(PSGC_BASE + '/regions/' + code + '/cities-municipalities/');
    const data = await res.json();
    data.sort((a, b) => a.name.localeCompare(b.name)).forEach(c => {
      const o = document.createElement('option');
      o.value = c.name; o.textContent = c.name;
      sel.appendChild(o);
    });
    sel.disabled = false;
  } catch { sel.innerHTML = '<option value="">Could not load cities</option>'; }
  lbl.style.display = 'none';
}

// ── Other language toggle ──────────────────────────────────────────────────────
function toggleOtherLang() {
  const v = document.querySelector('[name="preferred_language"]').value;
  const d = document.getElementById('other_lang_div');
  d.style.display = v === 'Other' ? 'block' : 'none';
  if (v === 'Other') d.querySelector('input').focus();
}

// ── Profile photo validation ───────────────────────────────────────────────────
function validateProfilePhotoSelection() {
  const input = document.getElementById('f_profile_photo');
  if (!input || !input.files || input.files.length === 0) return true;
  const file = input.files[0];
  const allowedMimes = ['image/jpeg','image/png','image/gif','image/webp'];
  const allowedExts  = ['jpg','jpeg','png','gif','webp'];
  const ext = ((file.name.split('.').pop()) || '').toLowerCase();
  if (!allowedMimes.includes(file.type) || !allowedExts.includes(ext)) {
    input.value = '';
    toast('Profile photo must be JPG, PNG, GIF, or WEBP only.');
    return false;
  }
  return true;
}

// ── DOB max ────────────────────────────────────────────────────────────────────
const dobEl = document.getElementById('f_dob');
if (dobEl) {
  const max = new Date();
  max.setFullYear(max.getFullYear() - 18);
  dobEl.max = max.toISOString().split('T')[0];
}

// ── Init ───────────────────────────────────────────────────────────────────────
window.addEventListener('load', () => {
  const langSel = document.querySelector('[name="preferred_language"]');
  if (langSel && langSel.value === 'Other')
    document.getElementById('other_lang_div').style.display = 'block';

  const photoInput = document.getElementById('f_profile_photo');
  if (photoInput) photoInput.addEventListener('change', validateProfilePhotoSelection);

  onCountryChange();
});
</script>
</body>
</html>