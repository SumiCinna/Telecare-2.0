<?php
require_once '../database/config.php';

$error        = '';
$show_verify  = false;
$verify_email = '';
$patient_name = '';
$verify_token = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name              = trim($_POST['full_name'] ?? '');
    $date_of_birth          = $_POST['date_of_birth'] ?? '';
    $gender                 = $_POST['gender'] ?? '';
    $email                  = trim($_POST['email'] ?? '');
    $phone_number           = trim($_POST['phone_number'] ?? '');
    $emergency_name         = trim($_POST['emergency_name'] ?? '');
    $emergency_relationship = trim($_POST['emergency_relationship'] ?? '');
    $emergency_number       = trim($_POST['emergency_number'] ?? '');
    $password               = $_POST['password'] ?? '';
    $confirm_password       = $_POST['confirm_password'] ?? '';
    $security_question      = trim($_POST['security_question'] ?? '');
    $security_answer        = trim($_POST['security_answer'] ?? '');
    $home_address           = trim($_POST['home_address'] ?? '');
    $city                   = trim($_POST['city'] ?? '');
    $country_region         = trim($_POST['country_region'] ?? '');
    $insurance_provider     = trim($_POST['insurance_provider'] ?? '');
    $insurance_policy_no    = trim($_POST['insurance_policy_no'] ?? '');
    $preferred_language     = trim($_POST['preferred_language'] ?? 'English');

    if (empty($full_name)||empty($email)||empty($password)||empty($date_of_birth)||empty($gender)||empty($phone_number)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
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
            if (!empty($_FILES['profile_photo']['name'])) {
                $upload_dir = '../uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('patient_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir.$filename))
                    $photo_path = 'uploads/profiles/'.$filename;
            }

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
                $full_name,$date_of_birth,$gender,$email,$phone_number,$photo_path,
                $emergency_name,$emergency_relationship,$emergency_number,
                $hashed,$security_question,$hashed_answer,
                $home_address,$city,$country_region,
                $insurance_provider,$insurance_policy_no,$preferred_language,
                $token,$expires_at
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
    select.field-input{cursor:pointer}
    .btn-next{width:100%;padding:.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:600;font-size:.95rem;border:none;cursor:pointer;transition:all .3s;box-shadow:0 6px 20px rgba(195,54,67,.3)}
    .btn-next:hover{background:#a82d38;transform:translateY(-2px)}
    .btn-back{width:100%;padding:.9rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:.95rem;border:1.5px solid rgba(36,68,65,.2);cursor:pointer;transition:all .3s;margin-bottom:.75rem}
    .btn-back:hover{background:rgba(36,68,65,.06)}
    .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    @media(max-width:500px){.grid-2{grid-template-columns:1fr}}
    .alert-error{background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.25);color:var(--red);border-radius:12px;padding:.85rem 1rem;font-size:.88rem;margin-bottom:1.2rem}
    .optional-tag{font-size:.72rem;font-weight:400;color:#9ab0ae;text-transform:none;letter-spacing:0;margin-left:.3rem}
    .section-divider{font-size:.75rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#9ab0ae;border-bottom:1px solid rgba(36,68,65,.1);padding-bottom:.5rem;margin:1.5rem 0 1rem}
    .pw-wrap{position:relative}
    .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0}
    .pw-toggle:hover{color:var(--green)}
    .resend-btn{background:none;border:none;color:var(--red);font-weight:600;font-size:.9rem;cursor:pointer;font-family:'DM Sans',sans-serif;text-decoration:underline;padding:0}
    .resend-btn:disabled{color:#9ab0ae;cursor:not-allowed;text-decoration:none}
    .spinner-inline{display:inline-block;width:14px;height:14px;border:2px solid rgba(195,54,67,.3);border-top-color:var(--red);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px}
    @keyframes spin{to{transform:rotate(360deg)}}
  </style>
</head>
<body>
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
      <!-- EMAIL VERIFICATION SCREEN -->
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
        // ══════════════════════════════════════════════════
        // REPLACE THESE 3 VALUES WITH YOURS FROM EMAILJS
        
const EMAILJS_PUBLIC_KEY  = 'm-AvAiAdUDsgBbz6D';
        const EMAILJS_SERVICE_ID  = 'service_vr6ygvx';
        const EMAILJS_TEMPLATE_ID = 'template_zhnltnl';
        // ══════════════════════════════════════════════════

        const patientEmail  = <?= json_encode($verify_email) ?>;
        const patientName   = <?= json_encode($patient_name) ?>;
        const activationURL = <?= json_encode('http://'.$_SERVER['HTTP_HOST'].'/auth/verify.php?token='.urlencode($verify_token)) ?>;

        emailjs.init(EMAILJS_PUBLIC_KEY);

        function sendActivationEmail() {
          return emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_TEMPLATE_ID, {
            to_email:        patientEmail,
            patient_name:    patientName,
            activation_link: activationURL,
          });
        }

        // Auto-send on page load
        sendActivationEmail()
          .then(() => console.log('Activation email sent!'))
          .catch(err => console.error('EmailJS error:', err));

        // Resend with 60s cooldown
        let cooldown = 0;
        function resendEmail() {
          if (cooldown > 0) return;
          const btn = document.getElementById('resendBtn');
          const msg = document.getElementById('resendMsg');
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-inline"></span>Sending...';
          sendActivationEmail().then(() => {
            msg.textContent = '✓ Email resent successfully!';
            msg.style.color = 'var(--green)';
            cooldown = 60;
            const t = setInterval(() => {
              cooldown--;
              btn.innerHTML = 'Resend again in '+cooldown+'s';
              if (cooldown <= 0) { clearInterval(t); btn.disabled=false; btn.innerHTML='Resend activation email'; }
            }, 1000);
          }).catch(() => {
            msg.textContent = '✗ Failed to send. Please try again.';
            msg.style.color = 'var(--red)';
            btn.disabled = false;
            btn.innerHTML = 'Resend activation email';
          });
        }
      </script>

    <?php else: ?>

      <!-- REGISTRATION FORM -->
      <div class="step-bar">
        <div class="step-dot active" id="dot-1"></div>
        <div class="step-dot" id="dot-2"></div>
        <div class="step-dot" id="dot-3"></div>
      </div>
      <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif ?>

      <form method="POST" enctype="multipart/form-data" id="regForm">

        <!-- STEP 1: Personal -->
        <div class="step-panel active" id="step-1">
          <h2 style="font-size:1.6rem;margin-bottom:.3rem">Personal Information</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.8rem">Tell us about yourself.</p>
          <div style="margin-bottom:1rem">
            <label class="field-label">Full Name <span class="req">*</span></label>
            <input type="text" name="full_name" class="field-input" placeholder="e.g. Juan Dela Cruz" value="<?= htmlspecialchars($_POST['full_name']??'') ?>"/>
          </div>
          <div class="grid-2">
            <div>
              <label class="field-label">Date of Birth <span class="req">*</span></label>
              <input type="date" name="date_of_birth" id="dob" class="field-input" value="<?= htmlspecialchars($_POST['date_of_birth']??'') ?>"/>
            </div>
            <div>
              <label class="field-label">Gender <span class="req">*</span></label>
              <select name="gender" class="field-input">
                <option value="">Select gender</option>
                <?php foreach(['Male','Female','Prefer not to say'] as $g): ?><option value="<?= $g ?>" <?= (($_POST['gender']??'')===$g)?'selected':'' ?>><?= $g ?></option><?php endforeach ?>
              </select>
            </div>
          </div>
          <div class="grid-2" style="margin-top:1rem">
            <div>
              <label class="field-label">Email Address <span class="req">*</span></label>
              <input type="email" name="email" class="field-input" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email']??'') ?>"/>
            </div>
            <div>
              <label class="field-label">Phone Number <span class="req">*</span></label>
              <input type="tel" name="phone_number" class="field-input" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['phone_number']??'') ?>"/>
            </div>
          </div>
          <div style="margin-top:1rem">
            <label class="field-label">Profile Photo <span class="optional-tag">(optional)</span></label>
            <input type="file" name="profile_photo" class="field-input" accept="image/*" style="padding:.6rem"/>
          </div>
          <div class="section-divider">Emergency Contact <span class="optional-tag" style="text-transform:none">(optional)</span></div>
          <div style="margin-bottom:1rem">
            <label class="field-label">Contact Name</label>
            <input type="text" name="emergency_name" class="field-input" placeholder="Full name" value="<?= htmlspecialchars($_POST['emergency_name']??'') ?>"/>
          </div>
          <div class="grid-2">
            <div>
              <label class="field-label">Relationship</label>
              <input type="text" name="emergency_relationship" class="field-input" placeholder="e.g. Mother" value="<?= htmlspecialchars($_POST['emergency_relationship']??'') ?>"/>
            </div>
            <div>
              <label class="field-label">Contact Number</label>
              <input type="tel" name="emergency_number" class="field-input" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['emergency_number']??'') ?>"/>
            </div>
          </div>
          <div style="margin-top:2rem"><button type="button" class="btn-next" onclick="goStep(2)">Continue to Security →</button></div>
        </div>

        <!-- STEP 2: Security -->
        <div class="step-panel" id="step-2">
          <h2 style="font-size:1.6rem;margin-bottom:.3rem">Security</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.8rem">Set a strong password for your account.</p>
          <div style="margin-bottom:1rem">
            <label class="field-label">Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="pw" class="field-input" placeholder="At least 8 characters" style="padding-right:2.8rem"/>
              <button type="button" class="pw-toggle" onclick="togglePw('pw','e1s','e1h')">
                <svg id="e1s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg id="e1h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
              </button>
            </div>
            <div id="pw-strength" style="height:3px;border-radius:2px;margin-top:.4rem;background:#e0e0e0;overflow:hidden"><div id="pw-bar" style="height:100%;width:0;transition:width .3s,background .3s"></div></div>
          </div>
          <div style="margin-bottom:1rem">
            <label class="field-label">Confirm Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="confirm_password" id="pw2" class="field-input" placeholder="Repeat password" style="padding-right:2.8rem"/>
              <button type="button" class="pw-toggle" onclick="togglePw('pw2','e2s','e2h')">
                <svg id="e2s" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg id="e2h" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/></svg>
              </button>
            </div>
            <div id="pw-match" style="font-size:.78rem;margin-top:.3rem"></div>
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
            <input type="text" name="security_answer" class="field-input" placeholder="Your answer (case-insensitive)"/>
          </div>
          <div style="margin-top:2rem">
            <button type="button" class="btn-back" onclick="goStep(1)">← Back</button>
            <button type="button" class="btn-next" onclick="goStep(3)">Continue to Location →</button>
          </div>
        </div>

        <!-- STEP 3: Location -->
        <div class="step-panel" id="step-3">
          <h2 style="font-size:1.6rem;margin-bottom:.3rem">Location & Coverage</h2>
          <p style="color:#6b8a87;font-size:.9rem;margin-bottom:1.8rem">Help us match you with available doctors in your area.</p>
          <div style="margin-bottom:1rem">
            <label class="field-label">Home Address <span class="optional-tag">(optional)</span></label>
            <input type="text" name="home_address" class="field-input" placeholder="Street, Barangay" value="<?= htmlspecialchars($_POST['home_address']??'') ?>"/>
          </div>
          <div class="grid-2">
            <div>
              <label class="field-label">City / Municipality</label>
              <input type="text" name="city" class="field-input" placeholder="e.g. Quezon City" value="<?= htmlspecialchars($_POST['city']??'') ?>"/>
            </div>
            <div>
              <label class="field-label">Country / Region</label>
              <select name="country_region" class="field-input">
                <option value="">Select country</option>
                <?php foreach(['Philippines','Afghanistan','Albania','Algeria','Andorra','Angola','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahrain','Bangladesh','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina Faso','Burundi','Cambodia','Cameroon','Canada','Chad','Chile','China','Colombia','Comoros','Congo','Costa Rica','Croatia','Cuba','Cyprus','Czech Republic','Denmark','Djibouti','Dominican Republic','Ecuador','Egypt','El Salvador','Eritrea','Estonia','Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Guatemala','Guinea','Haiti','Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Mexico','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar','Namibia','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','North Korea','Norway','Oman','Pakistan','Palestine','Panama','Papua New Guinea','Paraguay','Peru','Poland','Portugal','Qatar','Romania','Russia','Rwanda','Saudi Arabia','Senegal','Serbia','Sierra Leone','Singapore','Slovakia','Slovenia','Somalia','South Africa','South Korea','South Sudan','Spain','Sri Lanka','Sudan','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Togo','Tunisia','Turkey','Turkmenistan','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'] as $c): ?><option value="<?= $c ?>" <?= (($_POST['country_region']??'')===$c)?'selected':'' ?>><?= $c ?></option><?php endforeach ?>
              </select>
            </div>
          </div>
          <div class="section-divider">Health Insurance <span class="optional-tag" style="text-transform:none">(optional)</span></div>
          <div class="grid-2">
            <div>
              <label class="field-label">Insurance Provider</label>
              <input type="text" name="insurance_provider" class="field-input" placeholder="e.g. PhilHealth" value="<?= htmlspecialchars($_POST['insurance_provider']??'') ?>"/>
            </div>
            <div>
              <label class="field-label">Policy Number</label>
              <input type="text" name="insurance_policy_no" class="field-input" placeholder="Policy no." value="<?= htmlspecialchars($_POST['insurance_policy_no']??'') ?>"/>
            </div>
          </div>
          <div style="margin-top:1rem">
            <label class="field-label">Preferred Language</label>
            <select name="preferred_language" class="field-input">
              <?php foreach(['English','Filipino','Cebuano','Ilocano','Other'] as $lang): ?><option value="<?= $lang ?>" <?= (($_POST['preferred_language']??'English')===$lang)?'selected':'' ?>><?= $lang ?></option><?php endforeach ?>
            </select>
          </div>
          <div style="margin-top:2rem">
            <button type="button" class="btn-back" onclick="goStep(2)">← Back</button>
            <button type="submit" class="btn-next">Create My Account</button>
          </div>
        </div>

      </form>
      <p style="text-align:center;margin-top:2rem;font-size:.88rem;color:#6b8a87">Already have an account? <a href="login.php" style="color:var(--red);font-weight:600">Log in</a></p>

    <?php endif ?>
    </div>
  </div>
</div>

<script>
  let current=1;
  function goStep(n){
    if(n>current){
      if(current===1){
        const req=['full_name','date_of_birth','gender','email','phone_number'];
        for(let f of req){const el=document.querySelector('[name="'+f+'"]');if(!el.value.trim()){el.focus();el.style.borderColor='var(--red)';return;}el.style.borderColor='';}
      }
      if(current===2){
        const pw=document.getElementById('pw').value,pw2=document.getElementById('pw2').value;
        if(pw.length<8){document.getElementById('pw').focus();return;}
        if(pw!==pw2){document.getElementById('pw2').focus();return;}
      }
    }
    document.getElementById('step-'+current).classList.remove('active');
    document.getElementById('dot-'+current).classList.remove('active');
    document.getElementById('dot-'+current).classList.add('done');
    current=n;
    document.getElementById('step-'+current).classList.add('active');
    document.getElementById('dot-'+current).classList.add('active');
    document.querySelectorAll('.side-step').forEach(el=>el.style.opacity=parseInt(el.dataset.step)===current?'1':'0.4');
    window.scrollTo({top:0,behavior:'smooth'});
  }
  function togglePw(id,s,h){
    const f=document.getElementById(id);
    if(f.type==='password'){f.type='text';document.getElementById(s).style.display='none';document.getElementById(h).style.display='block';}
    else{f.type='password';document.getElementById(s).style.display='block';document.getElementById(h).style.display='none';}
  }
  const dob=document.getElementById('dob');
  if(dob){const m=new Date();m.setFullYear(m.getFullYear()-15);dob.max=m.toISOString().split('T')[0];}
  const pw=document.getElementById('pw');
  if(pw){pw.addEventListener('input',function(){const v=this.value,b=document.getElementById('pw-bar');let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;b.style.width=['0%','30%','55%','80%','100%'][s];b.style.background=['','#e55','#f90','#3F82E3','#244441'][s];});}
  const pw2=document.getElementById('pw2');
  if(pw2){pw2.addEventListener('input',function(){const m=document.getElementById('pw-match');if(this.value===document.getElementById('pw').value){m.textContent='✓ Passwords match';m.style.color='var(--green)';}else{m.textContent='✗ Passwords do not match';m.style.color='var(--red)';}});}
</script>
</body>
</html>