<?php
require_once '../database/config.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
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

    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($date_of_birth) || empty($gender) || empty($phone_number)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check duplicate email
        $stmt = $conn->prepare("SELECT id FROM patients WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $hashed_answer = $security_answer ? password_hash(strtolower($security_answer), PASSWORD_BCRYPT) : null;

            // Handle profile photo upload
            $photo_path = null;
            if (!empty($_FILES['profile_photo']['name'])) {
                $upload_dir = '../uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('patient_') . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $filename)) {
                    $photo_path = 'uploads/profiles/' . $filename;
                }
            }

            $insert = $conn->prepare("
                INSERT INTO patients (
                    full_name, date_of_birth, gender, email, phone_number, profile_photo,
                    emergency_name, emergency_relationship, emergency_number,
                    password, security_question, security_answer,
                    home_address, city, country_region,
                    insurance_provider, insurance_policy_no, preferred_language
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $insert->bind_param(
                "ssssssssssssssssss",
                $full_name, $date_of_birth, $gender, $email, $phone_number, $photo_path,
                $emergency_name, $emergency_relationship, $emergency_number,
                $hashed, $security_question, $hashed_answer,
                $home_address, $city, $country_region,
                $insurance_provider, $insurance_policy_no, $preferred_language
            );

            if ($insert->execute()) {
                $success = 'Account created successfully! You can now <a href="login.php">log in</a>.';
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --red:   #C33643;
      --green: #244441;
      --blue:  #3F82E3;
      --bg:    #F2F2F2;
      --white: #FFFFFF;
    }
    * { box-sizing: border-box; }
    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--green); min-height: 100vh; }
    h1,h2 { font-family: 'Playfair Display', serif; }

    /* left panel */
    .left-panel {
      background: linear-gradient(160deg, var(--green) 0%, #1a3330 100%);
      position: relative; overflow: hidden;
    }
    .left-panel::before {
      content: '';
      position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(63,130,227,0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(63,130,227,0.08) 1px, transparent 1px);
      background-size: 44px 44px;
      animation: gridMove 20s linear infinite;
    }
    @keyframes gridMove { from { transform:translateY(0); } to { transform:translateY(44px); } }

    .orb {
      position: absolute; border-radius: 50%; filter: blur(70px); pointer-events:none;
      animation: pulse 6s ease-in-out infinite;
    }
    @keyframes pulse { 0%,100%{transform:scale(1);opacity:.7} 50%{transform:scale(1.2);opacity:1} }

    /* progress bar */
    .step-bar { display:flex; gap:0.5rem; margin-bottom:2rem; }
    .step-dot {
      flex:1; height:4px; border-radius:2px;
      background: rgba(255,255,255,0.15);
      transition: background 0.4s;
    }
    .step-dot.active  { background: var(--red); }
    .step-dot.done    { background: rgba(255,255,255,0.5); }

    /* step panels */
    .step-panel { display: none; animation: fadeUp 0.4s ease; }
    .step-panel.active { display: block; }
    @keyframes fadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }

    /* input styles */
    .field-label {
      display: block; font-size: 0.78rem; font-weight: 600;
      letter-spacing: 0.06em; text-transform: uppercase;
      color: #5a7a77; margin-bottom: 0.45rem;
    }
    .field-label .req { color: var(--red); }
    .field-input {
      width: 100%; padding: 0.75rem 1rem;
      border: 1.5px solid rgba(36,68,65,0.15);
      border-radius: 12px; font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem; background: var(--white);
      color: var(--green); outline: none;
      transition: border-color 0.25s, box-shadow 0.25s;
    }
    .field-input:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(63,130,227,0.12);
    }
    select.field-input { cursor: pointer; }

    /* buttons */
    .btn-next {
      width:100%; padding:0.9rem; border-radius:50px;
      background: var(--red); color:#fff; font-weight:600;
      font-size:0.95rem; border:none; cursor:pointer;
      transition: all 0.3s; box-shadow: 0 6px 20px rgba(195,54,67,0.3);
    }
    .btn-next:hover { background:#a82d38; transform:translateY(-2px); box-shadow:0 10px 28px rgba(195,54,67,0.4); }
    .btn-back {
      width:100%; padding:0.9rem; border-radius:50px;
      background:transparent; color:var(--green); font-weight:600;
      font-size:0.95rem; border:1.5px solid rgba(36,68,65,0.2);
      cursor:pointer; transition: all 0.3s; margin-bottom:0.75rem;
    }
    .btn-back:hover { background:rgba(36,68,65,0.06); }

    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    @media(max-width:500px){ .grid-2{grid-template-columns:1fr;} }

    .alert-error {
      background:rgba(195,54,67,0.08); border:1px solid rgba(195,54,67,0.25);
      color:var(--red); border-radius:12px; padding:0.85rem 1rem;
      font-size:0.88rem; margin-bottom:1.2rem;
    }
    .alert-success {
      background:rgba(36,68,65,0.08); border:1px solid rgba(36,68,65,0.25);
      color:var(--green); border-radius:12px; padding:0.85rem 1rem;
      font-size:0.88rem; margin-bottom:1.2rem;
    }
    .alert-success a { color:var(--red); font-weight:600; }

    .optional-tag {
      font-size:0.72rem; font-weight:400; color:#9ab0ae;
      text-transform:none; letter-spacing:0; margin-left:0.3rem;
    }

    .section-divider {
      font-size:0.75rem; font-weight:700; letter-spacing:0.1em;
      text-transform:uppercase; color:#9ab0ae;
      border-bottom:1px solid rgba(36,68,65,0.1);
      padding-bottom:0.5rem; margin:1.5rem 0 1rem;
    }
    .pw-wrap { position:relative; }
    .pw-toggle {
      position:absolute; right:14px; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer; color:#9ab0ae;
      font-size:1.1rem; line-height:1; padding:0;
    }
    .pw-toggle:hover { color:var(--green); }
  </style>
</head>
<body>

<div style="display:flex; min-height:100vh;">

  <!-- ── LEFT PANEL ── -->
  <div class="left-panel" style="width:42%; display:flex; flex-direction:column; justify-content:center; padding:3rem; position:sticky; top:0; height:100vh;">
    <div class="orb" style="width:320px;height:320px;background:radial-gradient(circle,rgba(63,130,227,0.2) 0%,transparent 70%);top:-60px;right:-60px;"></div>
    <div class="orb" style="width:220px;height:220px;background:radial-gradient(circle,rgba(195,54,67,0.15) 0%,transparent 70%);bottom:60px;left:20px;animation-delay:3s;"></div>

    <div style="position:relative;z-index:2;">
      <a href="../index.php" style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:#fff;text-decoration:none;letter-spacing:0.04em;">
        TELE<span style="color:var(--red)">-</span>CARE
      </a>

      <div style="margin-top:3rem;">
        <h1 style="font-size:2.2rem;color:#fff;line-height:1.2;margin-bottom:1rem;">
          Create Your<br/>Patient Account
        </h1>
        <p style="color:rgba(255,255,255,0.55);font-size:0.95rem;line-height:1.75;">
          Fill in your details across three short steps. All information is kept private and used only for your consultations.
        </p>
      </div>

      <!-- step labels -->
      <div style="margin-top:3rem;display:flex;flex-direction:column;gap:1.2rem;">
        <?php
        $steps_info = [
          ['01','Personal Information','Your basic details and emergency contact'],
          ['02','Security','Set up your password and recovery option'],
          ['03','Location & Coverage','Your address and health insurance info'],
        ];
        foreach($steps_info as $i => $s): ?>
        <div class="side-step" data-step="<?= $i+1 ?>" style="display:flex;gap:1rem;align-items:center;opacity:<?= $i===0?'1':'0.4' ?>;transition:opacity 0.4s;">
          <div style="width:36px;height:36px;border-radius:50%;border:2px solid rgba(255,255,255,0.25);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:#fff;flex-shrink:0;"><?= $s[0] ?></div>
          <div>
            <div style="font-weight:600;color:#fff;font-size:0.9rem;"><?= $s[1] ?></div>
            <div style="font-size:0.78rem;color:rgba(255,255,255,0.45);"><?= $s[2] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ── RIGHT PANEL ── -->
  <div style="flex:1; overflow-y:auto; padding:3rem 4%;">
    <div style="max-width:520px; margin:0 auto;">

      <!-- progress dots -->
      <div class="step-bar">
        <div class="step-dot active" id="dot-1"></div>
        <div class="step-dot"        id="dot-2"></div>
        <div class="step-dot"        id="dot-3"></div>
      </div>

      <!-- alerts -->
      <?php if($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <?php if($success): ?>
      <div style="text-align:center;padding:2rem 0;animation:fadeUp 0.6s ease;">
        <div style="width:80px;height:80px;border-radius:50%;background:rgba(36,68,65,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.2rem;">✅</div>
        <h2 style="font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:900;color:var(--green);margin-bottom:0.6rem;">You're all set!</h2>
        <p style="color:#6b8a87;font-size:0.95rem;line-height:1.7;margin-bottom:2rem;">
          Your account has been created successfully.<br/>You can now log in and start booking consultations.
        </p>
        <a href="login.php" style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--red);color:#fff;padding:0.9rem 2.5rem;border-radius:50px;font-weight:600;font-size:0.95rem;text-decoration:none;box-shadow:0 6px 20px rgba(195,54,67,0.3);transition:all 0.3s;">
          Go to Login →
        </a>
        <p style="margin-top:1.5rem;font-size:0.82rem;color:#9ab0ae;">
          <a href="../index.php" style="color:#9ab0ae;text-decoration:none;">← Back to home</a>
        </p>
      </div>
      <?php endif; ?>

      <?php if(!$success): ?>
      <form method="POST" enctype="multipart/form-data" id="regForm">

        <!-- ══ STEP 1: Personal Info ══ -->
        <div class="step-panel active" id="step-1">
          <h2 style="font-size:1.6rem;margin-bottom:0.3rem;">Personal Information</h2>
          <p style="color:#6b8a87;font-size:0.9rem;margin-bottom:1.8rem;">Tell us about yourself.</p>

          <div style="margin-bottom:1rem;">
            <label class="field-label">Full Name <span class="req">*</span></label>
            <input type="text" name="full_name" class="field-input" placeholder="e.g. Juan Dela Cruz" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"/>
          </div>

          <div class="grid-2">
            <div>
              <label class="field-label">Date of Birth <span class="req">*</span></label>
              <input type="date" name="date_of_birth" id="dob" class="field-input" required value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>"/>
            </div>
            <div>
              <label class="field-label">Gender <span class="req">*</span></label>
              <select name="gender" class="field-input" required>
                <option value="">Select gender</option>
                <?php foreach(['Male','Female','Prefer not to say'] as $g): ?>
                <option value="<?= $g ?>" <?= (($_POST['gender']??'')===$g)?'selected':'' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grid-2" style="margin-top:1rem;">
            <div>
              <label class="field-label">Email Address <span class="req">*</span></label>
              <input type="email" name="email" class="field-input" placeholder="you@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
            </div>
            <div>
              <label class="field-label">Phone Number <span class="req">*</span></label>
              <input type="tel" name="phone_number" class="field-input" placeholder="09XXXXXXXXX" required value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>"/>
            </div>
          </div>

          <div style="margin-top:1rem;">
            <label class="field-label">Profile Photo <span class="optional-tag">(optional)</span></label>
            <input type="file" name="profile_photo" class="field-input" accept="image/*" style="padding:0.6rem;"/>
          </div>

          <div class="section-divider">Emergency Contact <span class="optional-tag" style="text-transform:none;">(optional)</span></div>

          <div style="margin-bottom:1rem;">
            <label class="field-label">Contact Name</label>
            <input type="text" name="emergency_name" class="field-input" placeholder="Full name" value="<?= htmlspecialchars($_POST['emergency_name'] ?? '') ?>"/>
          </div>
          <div class="grid-2">
            <div>
              <label class="field-label">Relationship</label>
              <input type="text" name="emergency_relationship" class="field-input" placeholder="e.g. Mother, Sibling" value="<?= htmlspecialchars($_POST['emergency_relationship'] ?? '') ?>"/>
            </div>
            <div>
              <label class="field-label">Contact Number</label>
              <input type="tel" name="emergency_number" class="field-input" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['emergency_number'] ?? '') ?>"/>
            </div>
          </div>

          <div style="margin-top:2rem;">
            <button type="button" class="btn-next" onclick="goStep(2)">Continue to Security →</button>
          </div>
        </div>

        <!-- ══ STEP 2: Security ══ -->
        <div class="step-panel" id="step-2">
          <h2 style="font-size:1.6rem;margin-bottom:0.3rem;">Security</h2>
          <p style="color:#6b8a87;font-size:0.9rem;margin-bottom:1.8rem;">Set a strong password for your account.</p>

          <div style="margin-bottom:1rem;">
            <label class="field-label">Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="pw" class="field-input" placeholder="At least 8 characters" required style="padding-right:2.8rem;"/>
              <button type="button" class="pw-toggle" onclick="togglePw('pw','eye1show','eye1hide')">
                <svg id="eye1show" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg id="eye1hide" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/>
                </svg>
              </button>
            </div>
            <div id="pw-strength" style="height:3px;border-radius:2px;margin-top:0.4rem;background:#e0e0e0;overflow:hidden;">
              <div id="pw-bar" style="height:100%;width:0;transition:width 0.3s,background 0.3s;"></div>
            </div>
          </div>

          <div style="margin-bottom:1rem;">
            <label class="field-label">Confirm Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="confirm_password" id="pw2" class="field-input" placeholder="Repeat password" required style="padding-right:2.8rem;"/>
              <button type="button" class="pw-toggle" onclick="togglePw('pw2','eye2show','eye2hide')">
                <svg id="eye2show" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg id="eye2hide" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display:none;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.938 6.938A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.97 9.97 0 01-1.395 2.63M6.938 6.938L3 3m3.938 3.938l10.124 10.124M17.062 17.062L21 21"/>
                </svg>
              </button>
            </div>
            <div id="pw-match" style="font-size:0.78rem;margin-top:0.3rem;"></div>
          </div>

          <div class="section-divider">Recovery Option <span class="optional-tag" style="text-transform:none;">(optional)</span></div>

          <div style="margin-bottom:1rem;">
            <label class="field-label">Security Question</label>
            <select name="security_question" class="field-input">
              <option value="">Select a question</option>
              <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
              <option value="What is the name of your first pet?">What is the name of your first pet?</option>
              <option value="What city were you born in?">What city were you born in?</option>
              <option value="What is your elementary school's name?">What is your elementary school's name?</option>
            </select>
          </div>
          <div style="margin-bottom:1rem;">
            <label class="field-label">Your Answer</label>
            <input type="text" name="security_answer" class="field-input" placeholder="Your answer (case-insensitive)"/>
          </div>

          <div style="margin-top:2rem;">
            <button type="button" class="btn-back" onclick="goStep(1)">← Back</button>
            <button type="button" class="btn-next" onclick="goStep(3)">Continue to Location →</button>
          </div>
        </div>

        <!-- ══ STEP 3: Location & Coverage ══ -->
        <div class="step-panel" id="step-3">
          <h2 style="font-size:1.6rem;margin-bottom:0.3rem;">Location & Coverage</h2>
          <p style="color:#6b8a87;font-size:0.9rem;margin-bottom:1.8rem;">Help us match you with available doctors in your area.</p>

          <div style="margin-bottom:1rem;">
            <label class="field-label">Home Address <span class="optional-tag">(optional)</span></label>
            <input type="text" name="home_address" class="field-input" placeholder="Street, Barangay" value="<?= htmlspecialchars($_POST['home_address'] ?? '') ?>"/>
          </div>
          <div class="grid-2">
            <div>
              <label class="field-label">City / Municipality</label>
              <input type="text" name="city" class="field-input" placeholder="e.g. Quezon City" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"/>
            </div>
            <div>
              <label class="field-label">Country / Region</label>
              <select name="country_region" class="field-input">
                <option value="">Select country</option>
                <?php
                $countries = ['Philippines','Afghanistan','Albania','Algeria','Andorra','Angola','Argentina','Armenia','Australia','Austria','Azerbaijan','Bahrain','Bangladesh','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia and Herzegovina','Botswana','Brazil','Brunei','Bulgaria','Burkina Faso','Burundi','Cambodia','Cameroon','Canada','Chad','Chile','China','Colombia','Comoros','Congo','Costa Rica','Croatia','Cuba','Cyprus','Czech Republic','Denmark','Djibouti','Dominican Republic','Ecuador','Egypt','El Salvador','Eritrea','Estonia','Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Guatemala','Guinea','Haiti','Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kuwait','Kyrgyzstan','Laos','Latvia','Lebanon','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Mexico','Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar','Namibia','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','North Korea','Norway','Oman','Pakistan','Palestine','Panama','Papua New Guinea','Paraguay','Peru','Poland','Portugal','Qatar','Romania','Russia','Rwanda','Saudi Arabia','Senegal','Serbia','Sierra Leone','Singapore','Slovakia','Slovenia','Somalia','South Africa','South Korea','South Sudan','Spain','Sri Lanka','Sudan','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Togo','Tunisia','Turkey','Turkmenistan','Uganda','Ukraine','United Arab Emirates','United Kingdom','United States','Uruguay','Uzbekistan','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'];
                foreach($countries as $c): ?>
                <option value="<?= $c ?>" <?= (($_POST['country_region']??'')===$c)?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="section-divider">Health Insurance <span class="optional-tag" style="text-transform:none;">(optional)</span></div>

          <div class="grid-2">
            <div>
              <label class="field-label">Insurance Provider</label>
              <input type="text" name="insurance_provider" class="field-input" placeholder="e.g. PhilHealth" value="<?= htmlspecialchars($_POST['insurance_provider'] ?? '') ?>"/>
            </div>
            <div>
              <label class="field-label">Policy Number</label>
              <input type="text" name="insurance_policy_no" class="field-input" placeholder="Policy no." value="<?= htmlspecialchars($_POST['insurance_policy_no'] ?? '') ?>"/>
            </div>
          </div>

          <div style="margin-top:1rem;">
            <label class="field-label">Preferred Language</label>
            <select name="preferred_language" class="field-input">
              <?php foreach(['English','Filipino','Cebuano','Ilocano','Other'] as $lang): ?>
              <option value="<?= $lang ?>" <?= (($_POST['preferred_language']??'English')===$lang)?'selected':'' ?>><?= $lang ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div style="margin-top:2rem;">
            <button type="button" class="btn-back" onclick="goStep(2)">← Back</button>
            <button type="submit" class="btn-next">Create My Account</button>
          </div>
        </div>

      </form>

      <p style="text-align:center;margin-top:2rem;font-size:0.88rem;color:#6b8a87;">
        Already have an account? <a href="login.php" style="color:var(--red);font-weight:600;">Log in</a>
      </p>
      <?php endif; ?>

    </div>
  </div><!-- end right panel -->
</div>

<script>
  let current = 1;
  const total = 3;

  function goStep(n) {
    // Basic step-1 validation before moving forward
    if (n > current) {
      if (current === 1) {
        const req = ['full_name','date_of_birth','gender','email','phone_number'];
        for (let f of req) {
          const el = document.querySelector(`[name="${f}"]`);
          if (!el.value.trim()) { el.focus(); el.style.borderColor='var(--red)'; return; }
          el.style.borderColor='';
        }
      }
      if (current === 2) {
        const pw = document.getElementById('pw').value;
        const pw2 = document.getElementById('pw2').value;
        if (pw.length < 8) { document.getElementById('pw').focus(); return; }
        if (pw !== pw2)    { document.getElementById('pw2').focus(); return; }
      }
    }

    document.getElementById(`step-${current}`).classList.remove('active');
    document.getElementById(`dot-${current}`).classList.remove('active');
    document.getElementById(`dot-${current}`).classList.add('done');

    current = n;
    document.getElementById(`step-${current}`).classList.add('active');
    document.getElementById(`dot-${current}`).classList.add('active');

    // side steps opacity
    document.querySelectorAll('.side-step').forEach(el => {
      el.style.opacity = parseInt(el.dataset.step) === current ? '1' : '0.4';
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function togglePw(fieldId, showId, hideId) {
    const f = document.getElementById(fieldId);
    const showIcon = document.getElementById(showId);
    const hideIcon = document.getElementById(hideId);
    if (f.type === 'password') {
      f.type = 'text';
      showIcon.style.display = 'none';
      hideIcon.style.display = 'block';
    } else {
      f.type = 'password';
      showIcon.style.display = 'block';
      hideIcon.style.display = 'none';
    }
  }

  // ── Date of birth: must be at least 15 years old
  const dob = document.getElementById('dob');
  const maxDate = new Date();
  maxDate.setFullYear(maxDate.getFullYear() - 15);
  dob.max = maxDate.toISOString().split('T')[0];

  // Password strength indicator
  document.getElementById('pw').addEventListener('input', function(){
    const v = this.value;
    const bar = document.getElementById('pw-bar');
    let strength = 0;
    if (v.length >= 8) strength++;
    if (/[A-Z]/.test(v)) strength++;
    if (/[0-9]/.test(v)) strength++;
    if (/[^A-Za-z0-9]/.test(v)) strength++;
    const pct   = ['0%','30%','55%','80%','100%'][strength];
    const color = ['','#e55','#f90','#3F82E3','#244441'][strength];
    bar.style.width = pct;
    bar.style.background = color;
  });

  // Password match
  document.getElementById('pw2').addEventListener('input', function(){
    const match = document.getElementById('pw-match');
    if (this.value === document.getElementById('pw').value) {
      match.textContent = '✓ Passwords match';
      match.style.color = 'var(--green)';
    } else {
      match.textContent = '✗ Passwords do not match';
      match.style.color = 'var(--red)';
    }
  });
</script>
</body>
</html>