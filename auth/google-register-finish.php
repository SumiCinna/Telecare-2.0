<?php
require_once '../database/config.php';
session_start();
// auth/google-register-finish.php
if (empty($_SESSION['google_reg'])) {
    header('Location: register.php');
    exit;
}

$g            = $_SESSION['google_reg'];
$error        = '';
$show_verify  = false;
$verify_email = '';
$patient_name = '';
$verify_token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number           = trim($_POST['phone_number'] ?? '');
    $date_of_birth          = $_POST['date_of_birth'] ?? '';
    $gender                 = $_POST['gender'] ?? '';
    $emergency_name         = trim($_POST['emergency_name'] ?? '');
    $emergency_relationship = trim($_POST['emergency_relationship'] ?? '');
    $emergency_number       = trim($_POST['emergency_number'] ?? '');
    $security_question      = trim($_POST['security_question'] ?? '');
    $security_answer        = trim($_POST['security_answer'] ?? '');
    $country_region         = 'Philippines';
    $ph_city                = trim($_POST['ph_city'] ?? '');
    $home_address           = trim($_POST['home_address'] ?? '');
    $insurance_provider     = trim($_POST['insurance_provider'] ?? '');
    $insurance_policy_no    = trim($_POST['insurance_policy_no'] ?? '');
    $preferred_language     = trim($_POST['preferred_language'] ?? 'English');
    $other_language         = trim($_POST['other_language'] ?? '');

    $full_name    = $g['full_name'];
    $email        = $g['email'];
    $google_id    = $g['google_id'];

    if (empty($phone_number) || empty($date_of_birth) || empty($gender)) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^\+639\d{9}$/', $phone_number)) {
        $error = 'Please enter a valid Philippine mobile number (e.g. +639XXXXXXXXX).';
  } elseif (strlen($home_address) > 100) {
        $error = 'Home address must not exceed 100 characters.';
    } elseif (strlen($ph_city) > 50) {
        $error = 'City must not exceed 50 characters.';
    } elseif (strlen($insurance_provider) > 20) {
        $error = 'Insurance provider must not exceed 20 characters.';
    } elseif (strlen($insurance_policy_no) > 20) {
        $error = 'Policy number must not exceed 20 characters.';
    } elseif ($preferred_language === 'Other' && empty($other_language)) {
        $error = 'Please specify your language.';
    } else {
        $final_language = ($preferred_language === 'Other') ? $other_language : $preferred_language;

        // Check email not already taken (race condition guard)
        $chk = $conn->prepare("SELECT id FROM patients WHERE email = ?");
        $chk->bind_param("s", $email);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = 'An account with this email already exists.';
            $chk->close();
        } else {
            $chk->close();

            // Handle profile photo from Google or upload
            $photo_path = null;

            // Try to use Google profile picture if no upload
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $allowed_image_types = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                $tmp_name = $_FILES['profile_photo']['tmp_name'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = $finfo ? finfo_file($finfo, $tmp_name) : false;
                if ($finfo) finfo_close($finfo);
                if ($mime && isset($allowed_image_types[$mime]) && getimagesize($tmp_name) !== false) {
                    $upload_dir = '../uploads/profiles/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = uniqid('patient_', true) . '.' . $allowed_image_types[$mime];
                    if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                        $photo_path = 'uploads/profiles/' . $filename;
                    }
                }
            } elseif (!empty($g['picture'])) {
                // Download Google profile picture
                $pic_data = @file_get_contents($g['picture']);
                if ($pic_data) {
                    $upload_dir = '../uploads/profiles/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = uniqid('patient_g_', true) . '.jpg';
                    file_put_contents($upload_dir . $filename, $pic_data);
                    $photo_path = 'uploads/profiles/' . $filename;
                }
            }

            $hashed_answer = $security_answer ? password_hash(strtolower($security_answer), PASSWORD_BCRYPT) : null;
            // Google users don't have a password — set a random one
            $hashed_pw = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $insert = $conn->prepare("
                INSERT INTO patients (
                    full_name, date_of_birth, gender, email, phone_number, profile_photo,
                    emergency_name, emergency_relationship, emergency_number,
                    password, security_question, security_answer,
                    home_address, city, country_region,
                    insurance_provider, insurance_policy_no, preferred_language,
                    google_id, auth_provider,
                    is_verified, verification_token, token_expires_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)
            ");
            $auth_provider = 'google';
            $insert->bind_param("sssssssssssssssssssssss",
                $full_name, $date_of_birth, $gender, $email, $phone_number, $photo_path,
                $emergency_name, $emergency_relationship, $emergency_number,
                $hashed_pw, $security_question, $hashed_answer,
                $home_address, $ph_city, $country_region,
                $insurance_provider, $insurance_policy_no, $final_language,
                $google_id, $auth_provider,
                $token, $expires_at
            );

            if ($insert->execute()) {
                $show_verify  = true;
                $verify_email = $email;
                $patient_name = $full_name;
                $verify_token = $token;
                unset($_SESSION['google_reg']);
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <link rel="icon" type="image/x-icon" href="/uploads/logo/favicon.ico" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Complete Your Registration — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);min-height:100vh}
    h1,h2{font-family:'Playfair Display',serif}

    .page-wrap{display:flex;min-height:100vh}
    .left-panel{background:linear-gradient(160deg,var(--green) 0%,#1a3330 100%);position:sticky;top:0;height:100vh;width:42%;flex-shrink:0;display:flex;flex-direction:column;justify-content:center;padding:3rem;overflow:hidden}
    .left-panel::before{content:'';position:absolute;inset:0;background-image:linear-gradient(rgba(63,130,227,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(63,130,227,.08) 1px,transparent 1px);background-size:44px 44px;animation:gridMove 20s linear infinite}
    @keyframes gridMove{from{transform:translateY(0)}to{transform:translateY(44px)}}
    .orb{position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;animation:pulse 6s ease-in-out infinite}
    @keyframes pulse{0%,100%{transform:scale(1);opacity:.7}50%{transform:scale(1.2);opacity:1}}

    .right-panel{flex:1;overflow-y:auto;padding:3rem 4%}

    .mobile-header{display:none;background:linear-gradient(135deg,var(--green) 0%,#1a3330 100%);padding:1.25rem 1.5rem;position:sticky;top:0;z-index:100;align-items:center;justify-content:space-between}
    .mobile-header-logo{font-family:'Playfair Display',serif;font-size:1.35rem;font-weight:900;color:#fff;text-decoration:none;letter-spacing:.04em}

    .step-bar{display:flex;gap:.5rem;margin-bottom:2rem}
    .step-dot{flex:1;height:4px;border-radius:2px;background:rgba(36,68,65,.12);transition:background .4s}
    .step-dot.active{background:var(--red)}.step-dot.done{background:rgba(36,68,65,.35)}

    .step-panel{display:none;animation:fadeUp .4s ease}.step-panel.active{display:block}
    @keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}

    .field-label{display:block;font-size:.78rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#5a7a77;margin-bottom:.45rem}
    .field-label .req{color:var(--red)}
    .field-input{width:100%;padding:.75rem 1rem;border:1.5px solid rgba(36,68,65,.15);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:16px;background:var(--white);color:var(--green);outline:none;transition:border-color .25s,box-shadow .25s}
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

    .alert-error{background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.25);color:var(--red);border-radius:12px;padding:.85rem 1rem;font-size:.88rem;margin-bottom:1.2rem}
    .optional-tag{font-size:.72rem;font-weight:400;color:#9ab0ae;text-transform:none;letter-spacing:0;margin-left:.3rem}
    .section-divider{font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ab0ae;border-bottom:1px solid rgba(36,68,65,.1);padding-bottom:.5rem;margin:1.5rem 0 1rem}

    .toast-wrap{position:fixed;top:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
    .toast{background:#fff;border-radius:12px;padding:.75rem 1.1rem;font-size:.84rem;font-weight:500;box-shadow:0 8px 28px rgba(0,0,0,.13);border-left:4px solid var(--red);color:var(--green);animation:toastIn .3s ease;pointer-events:auto;max-width:300px;line-height:1.4}
    @keyframes toastIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}

    .resend-btn{background:none;border:none;color:var(--red);font-weight:600;font-size:.9rem;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:underline;padding:0}
    .resend-btn:disabled{color:#9ab0ae;cursor:not-allowed;text-decoration:none}

    .spinner-inline{display:inline-block;width:14px;height:14px;border:2px solid rgba(195,54,67,.3);border-top-color:var(--red);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
    .spinner-sm{display:inline-block;width:11px;height:11px;border:2px solid rgba(36,68,65,.15);border-top-color:var(--green);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:4px}
    @keyframes spin{to{transform:rotate(360deg)}}

    .phone-combo{display:flex;border:1.5px solid rgba(36,68,65,.15);border-radius:12px;overflow:hidden;background:var(--white);transition:border-color .25s,box-shadow .25s}
    .phone-combo:focus-within{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,.12)}
    .phone-combo.has-error{border-color:var(--red)!important;box-shadow:0 0 0 3px rgba(195,54,67,.1)!important}
    .phone-prefix-fixed{display:flex;align-items:center;justify-content:center;border-right:1.5px solid rgba(36,68,65,.1);background:rgba(36,68,65,.05);color:var(--green);font-family:'DM Sans',sans-serif;font-size:.88rem;font-weight:700;padding:.75rem .85rem;min-width:64px;flex-shrink:0}
    .phone-number-input{border:none;outline:none;flex:1;padding:.75rem 1rem;font-family:'DM Sans',sans-serif;font-size:16px;color:var(--green);background:transparent;min-width:0}
    .phone-number-input::placeholder{color:#b0c4c2}

    .google-card{display:flex;align-items:center;gap:1rem;background:rgba(63,130,227,.06);border:1.5px solid rgba(63,130,227,.15);border-radius:16px;padding:1rem 1.2rem;margin-bottom:1.5rem}
    .google-avatar{width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid rgba(63,130,227,.2);flex-shrink:0}
    .google-avatar-placeholder{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--green),#1a3330);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;color:#fff;flex-shrink:0}

    @media (max-width: 768px) {
      .left-panel{display:none!important}
      .mobile-header{display:flex!important}
      .right-panel{padding:1.25rem 1rem 2rem;width:100%}
      .page-wrap{flex-direction:column}
      .toast-wrap{top:auto;bottom:1rem;right:1rem;left:1rem}
      .toast{max-width:100%}
    }
  </style>
</head>
<body>
<div class="toast-wrap" id="toastWrap"></div>

<header class="mobile-header">
  <a href="../index.php" class="mobile-header-logo">TELE<span style="color:var(--red)">-</span>CARE</a>
  <span style="font-size:.75rem;color:rgba(255,255,255,.6)" id="mobileStepInfo">Step 1 of 2</span>
</header>

<div class="page-wrap">
  <div class="left-panel">
    <div class="orb" style="width:320px;height:320px;background:radial-gradient(circle,rgba(63,130,227,.2) 0%,transparent 70%);top:-60px;right:-60px"></div>
    <div class="orb" style="width:220px;height:220px;background:radial-gradient(circle,rgba(195,54,67,.15) 0%,transparent 70%);bottom:60px;left:20px;animation-delay:3s"></div>
    <div style="position:relative;z-index:2">
      <a href="../index.php" style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:#fff;text-decoration:none;letter-spacing:.04em">TELE<span style="color:var(--red)">-</span>CARE</a>
      <div style="margin-top:3rem">
        <h1 style="font-size:2.2rem;color:#fff;line-height:1.2;margin-bottom:1rem">Almost There!</h1>
        <p style="color:rgba(255,255,255,.55);font-size:.95rem;line-height:1.75">We got your Google info. Just fill in a few more details to complete your patient account.</p>
      </div>
      <div style="margin-top:3rem;display:flex;flex-direction:column;gap:1.2rem">
  <?php foreach([['01','Your Details','Phone, DOB & profile details'],['02','Location & Coverage','Address and health insurance']] as $i=>$s): ?>
        <div class="side-step" data-step="<?= $i+1 ?>" style="display:flex;gap:1rem;align-items:center;opacity:<?= $i===0?'1':'0.4' ?>;transition:opacity .4s">
          <div style="width:36px;height:36px;border-radius:50%;border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0"><?= $s[0] ?></div>
          <div><div style="font-weight:600;color:#fff;font-size:.9rem"><?= $s[1] ?></div><div style="font-size:.78rem;color:rgba(255,255,255,.45)"><?= $s[2] ?></div></div>
        </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <div class="right-panel">
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
          <?php foreach([['📬','Open your email inbox'],['🔍','Look for an email from TELE-CARE'],['🔗','Click the "Activate Account" link'],['✅','Log in with Google and start your consultation']] as [$icon,$text]): ?>
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
        const activationURL = <?= json_encode('https://'.$_SERVER['HTTP_HOST'].'/auth/verify.php?token='.urlencode($verify_token)) ?>;
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
      </div>
      <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>

      <!-- Google Profile Card -->
      <div class="google-card">
        <?php if(!empty($g['picture'])): ?>
          <img src="<?= htmlspecialchars($g['picture']) ?>" class="google-avatar" alt="Google photo"/>
        <?php else: ?>
          <div class="google-avatar-placeholder"><?= strtoupper(substr($g['first_name'],0,1)) ?></div>
        <?php endif ?>
        <div>
          <div style="font-weight:600;font-size:1rem;color:var(--green)"><?= htmlspecialchars($g['full_name']) ?></div>
          <div style="font-size:.82rem;color:#6b8a87"><?= htmlspecialchars($g['email']) ?></div>
          <div style="font-size:.75rem;color:#9ab0ae;margin-top:.2rem;display:flex;align-items:center;gap:.3rem">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="#244441"/></svg>
            Connected via Google
          </div>
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data" id="gForm" novalidate>
        <input type="hidden" name="phone_number" id="h_phone"/>

        <!-- STEP 1 -->
        <div class="step-panel active" id="step-1">
          <h2 style="font-size:1.5rem;margin-bottom:.3rem">Your Details</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.5rem">A few more things we need.</p>

          <div style="margin-bottom:1rem">
            <label class="field-label">Phone Number <span class="req">*</span></label>
            <div class="phone-combo" id="combo_phone">
              <div class="phone-prefix-fixed">+63</div>
              <input type="tel" class="phone-number-input" id="f_phone" placeholder="9XXXXXXXXX"
                maxlength="10" inputmode="numeric"
                onInput="this.value=this.value.replace(/[^0-9]/g,'');clearComboErr('phone')"/>
            </div>
            <div class="field-error" id="e_phone">Valid phone number required.</div>
          </div>

          <div style="margin-bottom:1rem">
            <label class="field-label">Date of Birth <span class="req">*</span></label>
            <input type="date" name="date_of_birth" id="f_dob" class="field-input" onchange="clearFieldErr(this)"/>
            <div class="field-error" id="e_dob">Must be at least 18 years old.</div>
          </div>

          <div style="margin-bottom:1rem">
            <label class="field-label">Gender <span class="req">*</span></label>
            <select name="gender" id="f_gender" class="field-input" onchange="clearFieldErr(this)">
              <option value="">Select gender</option>
              <option>Male</option><option>Female</option><option>Prefer not to say</option>
            </select>
            <div class="field-error" id="e_gender">Please select a gender.</div>
          </div>

          <div style="margin-bottom:1rem">
            <label class="field-label">Profile Photo <span class="optional-tag">(optional — uses Google photo if blank)</span></label>
            <input type="file" name="profile_photo" id="f_profile_photo" class="field-input" accept="image/jpeg,image/png,image/gif,image/webp" style="padding:.6rem"/>
          </div>

          <div style="margin-top:2rem">
            <button type="button" class="btn-next" onclick="goStep(2)">Continue to Location →</button>
          </div>
        </div>

        <!-- STEP 2 -->
        <div class="step-panel" id="step-2">
          <h2 style="font-size:1.5rem;margin-bottom:.3rem">Location & Coverage</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.5rem">Help us match you with doctors in your area.</p>

          <input type="hidden" name="country_region" value="Philippines"/>
          <div style="margin-bottom:1rem">
            <label class="field-label">Country</label>
            <input type="text" class="field-input" value="Philippines" disabled/>
          </div>

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

          <div style="margin-bottom:1rem">
            <label class="field-label">Home Address <span class="optional-tag">(optional)</span></label>
            <input type="text" name="home_address" class="field-input" placeholder="e.g. Blk 4 Lot 7, Sampaguita St." maxlength="100"/>
          </div>

          <div class="section-divider">Health Insurance <span class="optional-tag" style="text-transform:none">(optional)</span></div>
          <div style="margin-bottom:1rem">
            <label class="field-label">Insurance Provider</label>
            <input type="text" name="insurance_provider" class="field-input" placeholder="e.g. PhilHealth" maxlength="20"/>
          </div>
          <div style="margin-bottom:1rem">
            <label class="field-label">Policy Number</label>
            <input type="text" name="insurance_policy_no" class="field-input" placeholder="Policy no." maxlength="20"/>
          </div>

          <div style="margin-bottom:1rem">
            <label class="field-label">Preferred Language</label>
            <select name="preferred_language" class="field-input" onchange="toggleOtherLang()">
              <option value="English" selected>English</option>
              <option value="Filipino">Filipino</option>
              <option value="Cebuano">Cebuano</option>
              <option value="Ilocano">Ilocano</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div id="other_lang_div" style="margin-bottom:1rem;display:none">
            <label class="field-label">Specify Language</label>
            <input type="text" name="other_language" class="field-input" placeholder="e.g. Spanish" maxlength="20"/>
          </div>

          <div style="margin-top:2rem">
            <button type="button" class="btn-back" onclick="goStep(1)">← Back</button>
            <button type="submit" class="btn-next" onclick="combinePhoneFields()">Create My Account</button>
          </div>
        </div>
      </form>

    <?php endif ?>
    </div>
  </div>
</div>

<script>
let currentStep = 1;
const PSGC_BASE = 'https://psgc.cloud/api';

function updateMobileStepInfo() {
  const el = document.getElementById('mobileStepInfo');
  if (el) el.textContent = 'Step ' + currentStep + ' of 2';
}

function combinePhoneFields() {
  const num = document.getElementById('f_phone').value.trim();
  document.getElementById('h_phone').value = num ? ('+63' + num) : '';
}

function clearComboErr(which) {
  document.getElementById('combo_' + which).classList.remove('has-error');
  const err = document.getElementById('e_' + which);
  if (err) err.classList.remove('visible');
}

function toast(msg) {
  const wrap = document.getElementById('toastWrap');
  const t = document.createElement('div');
  t.className = 'toast'; t.textContent = msg;
  wrap.appendChild(t);
  setTimeout(() => { t.style.transition='opacity .3s'; t.style.opacity='0'; setTimeout(()=>t.remove(),320); }, 3500);
}

function showFieldErr(id, msg) {
  const el = document.getElementById('e_' + id);
  const inp = document.getElementById('f_' + id);
  if (el) { if (msg) el.textContent = msg; el.classList.add('visible'); }
  if (inp) inp.classList.add('has-error');
}

function clearFieldErr(inp) {
  const key = inp.id.replace('f_','');
  const el = document.getElementById('e_' + key);
  if (el) el.classList.remove('visible');
  inp.classList.remove('has-error');
}

function goStep(n) {
  if (n > currentStep && !validateStep1()) return;
  document.getElementById('step-'+currentStep).classList.remove('active');
  document.getElementById('dot-'+currentStep).classList.remove('active');
  document.getElementById('dot-'+currentStep).classList.add('done');
  currentStep = n;
  document.getElementById('step-'+currentStep).classList.add('active');
  document.getElementById('dot-'+currentStep).classList.add('active');
  document.querySelectorAll('.side-step').forEach(el =>
    el.style.opacity = parseInt(el.dataset.step) === currentStep ? '1' : '0.4'
  );
  updateMobileStepInfo();
  window.scrollTo({top:0,behavior:'smooth'});
}

function validateStep1() {
  let ok = true;
  const phoneNum = document.getElementById('f_phone').value.trim();
  if (!/^9\d{9}$/.test(phoneNum)) {
    document.getElementById('combo_phone').classList.add('has-error');
    document.getElementById('e_phone').textContent = 'Phone must start with 9 and be 10 digits.';
    document.getElementById('e_phone').classList.add('visible');
    ok = false;
  } else { clearComboErr('phone'); }

  const dob = document.getElementById('f_dob').value;
  if (!dob || (Date.now() - new Date(dob).getTime()) / (365.25*86400000) < 18) {
    showFieldErr('dob', 'You must be at least 18 years old.');
    ok = false;
  } else { clearFieldErr(document.getElementById('f_dob')); }

  const gender = document.getElementById('f_gender').value;
  if (!gender) { showFieldErr('gender', 'Please select a gender.'); ok = false; }
  else { clearFieldErr(document.getElementById('f_gender')); }

  if (!ok) toast('Please fill in all required fields correctly.');
  return ok;
}

function toggleOtherLang() {
  const v = document.querySelector('[name="preferred_language"]').value;
  document.getElementById('other_lang_div').style.display = v === 'Other' ? 'block' : 'none';
}

async function loadRegions() {
  const sel = document.getElementById('sel_region');
  const lbl = document.getElementById('lbl_region');
  lbl.style.display = 'block'; sel.disabled = true;
  try {
    const res = await fetch(PSGC_BASE + '/regions/');
    const data = await res.json();
    data.sort((a,b) => a.name.localeCompare(b.name)).forEach(r => {
      const o = document.createElement('option');
      o.value = r.code; o.textContent = r.name; sel.appendChild(o);
    });
    sel.disabled = false;
  } catch { sel.innerHTML = '<option value="">Could not load regions</option>'; }
  lbl.style.display = 'none';
}

async function onRegionChange() {
  const code = document.getElementById('sel_region').value;
  const sel = document.getElementById('sel_city');
  const lbl = document.getElementById('lbl_city');
  sel.innerHTML = '<option value="">Select city / municipality</option>';
  sel.disabled = true;
  if (!code) return;
  lbl.style.display = 'block';
  try {
    const res = await fetch(PSGC_BASE + '/regions/' + code + '/cities-municipalities/');
    const data = await res.json();
    data.sort((a,b) => a.name.localeCompare(b.name)).forEach(c => {
      const o = document.createElement('option');
      o.value = c.name; o.textContent = c.name; sel.appendChild(o);
    });
    sel.disabled = false;
  } catch { sel.innerHTML = '<option value="">Could not load cities</option>'; }
  lbl.style.display = 'none';
}

const dobEl = document.getElementById('f_dob');
if (dobEl) { const mx = new Date(); mx.setFullYear(mx.getFullYear()-18); dobEl.max = mx.toISOString().split('T')[0]; }

window.addEventListener('load', () => { loadRegions(); updateMobileStepInfo(); });
</script>
</body>
</html>