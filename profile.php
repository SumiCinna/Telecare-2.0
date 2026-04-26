<?php
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $password_error = '';

    if (!password_verify($current, $p['password'])) {
        $password_error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $password_error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $password_error = 'Password must contain at least 1 uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new)) {
        $password_error = 'Password must contain at least 1 lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new)) {
        $password_error = 'Password must contain at least 1 number.';
    } elseif ($new !== $confirm) {
        $password_error = 'New passwords do not match.';
    } else {
        $hashed = password_hash($new, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE patients SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $patient_id);
        $stmt->execute();
        $stmt->close();
        header('Location: profile.php?pwd_saved=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photo_only'])) {
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $dir = 'uploads/profiles/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext     = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed)) {
            $fname      = uniqid('patient_') . '.' . $ext;
            $fname_path = $dir . $fname;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $fname_path)) {
                $stmt = $conn->prepare("UPDATE patients SET profile_photo = ? WHERE id = ?");
                $stmt->bind_param("si", $fname_path, $patient_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    $p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
    header('Location: profile.php?saved=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fields = [
        'phone_number',
        'emergency_name','emergency_relationship','emergency_number'
    ];

    $sets  = [];
    $vals  = [];
    $types = '';

    foreach ($fields as $f) {
        $sets[]  = "$f = ?";
        $vals[]  = trim($_POST[$f] ?? '');
        $types  .= 's';
    }

    $vals[]  = $patient_id;
    $types  .= 'i';

    $sql  = "UPDATE patients SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("Query error: " . htmlspecialchars($conn->error) . "<br>SQL: " . htmlspecialchars($sql));
    }

    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();

    header('Location: profile.php?saved=1');
    exit;
}

$p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

$page_title = 'My Profile — TELE-CARE';
$active_nav = 'profile';
require_once 'includes/header.php';
?>

<style>
  html, body { overflow-x: hidden; }

  .page {
    width: min(100%, 980px);
    max-width: 980px;
    margin: 0 auto;
    padding: 1rem 1.1rem 6rem;
    box-sizing: border-box;
  }

  .page .card {
    width: 100%;
    box-sizing: border-box;
    overflow: hidden;
  }

  .page .field-input {
    width: 100%;
    box-sizing: border-box;
  }

  .page .grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    align-items: start;
  }

  .page .grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.75rem;
    align-items: start;
  }

  .section-label {
    margin-top: 0.9rem;
    margin-bottom: 0.4rem;
  }

  .btn-save {
    width: 100%;
    margin-top: 0.5rem;
  }

  .profile-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
  }

  .photo-wrap {
    position: relative;
    width: 72px;
    height: 72px;
    flex-shrink: 0;
    cursor: pointer;
  }

  .photo-wrap img,
  .photo-wrap .avatar-lg {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(63,130,227,0.2);
  }

  .avatar-lg {
    background: linear-gradient(135deg, var(--blue), var(--blue-dark));
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    font-weight: 700;
  }

  .photo-overlay {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    flex-direction: column;
    gap: 0.15rem;
  }

  .photo-wrap:hover .photo-overlay { opacity: 1; }
  .photo-overlay svg { width: 16px; height: 16px; }

  .profile-info { flex: 1; min-width: 0; }
  .profile-name { font-weight: 700; font-size: 1rem; color: var(--text); }
  .profile-email { font-size: 0.8rem; color: var(--muted); margin-top: 0.15rem; }
  .profile-badges { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem; }
  .profile-tap-hint { font-size: 0.68rem; color: var(--muted); margin-top: 0.35rem; }

  .logout-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(195,54,67,0.07);
    color: var(--red);
    border: 1px solid rgba(195,54,67,0.15);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.86rem;
    font-weight: 600;
    width: 100%;
    justify-content: center;
    margin-top: 1rem;
    text-decoration: none;
    transition: background 0.2s;
    box-sizing: border-box;
  }

  .logout-btn:hover { background: rgba(195,54,67,0.14); }

  .pw-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #9ab0ae;
    padding: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .pw-toggle:hover { color: var(--green); }

  .alert-error {
    background: rgba(195,54,67,0.08);
    border: 1px solid rgba(195,54,67,0.2);
    color: var(--red);
    border-radius: 12px;
    padding: 0.6rem 0.9rem;
    font-size: 0.84rem;
    margin-bottom: 0.75rem;
  }

  .alert-success {
    background: rgba(36,68,65,0.08);
    border: 1px solid rgba(36,68,65,0.2);
    color: var(--green);
    border-radius: 12px;
    padding: 0.6rem 0.9rem;
    font-size: 0.84rem;
    margin-bottom: 0.75rem;
  }

  .two-col-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.9rem;
    align-items: start;
  }

  @media (max-width: 720px) {
    .two-col-layout {
      grid-template-columns: 1fr;
    }

    .page .grid-3 {
      grid-template-columns: 1fr 1fr;
    }
  }

  @media (max-width: 900px) {
    .page {
      width: 100%;
      max-width: 100%;
      padding: 0.9rem 0.9rem 6.2rem;
    }

    .page .card {
      border-radius: 14px;
      padding: 1rem !important;
    }

    .section-label {
      margin-top: 0.8rem;
      margin-bottom: 0.35rem;
    }
  }

  @media (max-width: 600px) {
    .page {
      padding: 0.7rem 0.72rem 6.4rem;
    }

    .page .grid-2,
    .page .grid-3 {
      grid-template-columns: 1fr;
      gap: 0.6rem;
    }

    .profile-header {
      gap: 0.8rem;
      padding: 0.85rem 1rem;
    }

    .photo-wrap,
    .photo-wrap img,
    .photo-wrap .avatar-lg {
      width: 62px;
      height: 62px;
    }

    .avatar-lg { font-size: 1.25rem; }

    .alert-error,
    .alert-success {
      font-size: 0.78rem;
      padding: 0.55rem 0.75rem;
    }

    .pw-toggle { right: 8px; }
    .logout-btn { padding: 0.7rem 0.9rem; font-size: 0.82rem; }
  }
</style>

<div class="page">

  <?php if (isset($_GET['saved'])): ?>
  <div class="alert-success">✓ Profile updated successfully.</div>
  <?php endif; ?>
  <?php if (isset($_GET['pwd_saved'])): ?>
  <div class="alert-success">✓ Password changed successfully.</div>
  <?php endif; ?>
  <?php if (isset($password_error) && $password_error): ?>
  <div class="alert-error"><?= htmlspecialchars($password_error) ?></div>
  <?php endif; ?>

  <!-- Profile Header (horizontal) -->
  <div class="card" style="padding:0;">
    <form method="POST" enctype="multipart/form-data" id="photoForm">
      <input type="hidden" name="photo_only" value="1"/>
      <div class="profile-header">
        <div class="photo-wrap" onclick="document.getElementById('photoInput').click()">
          <?php if (!empty($p['profile_photo'])): ?>
            <img src="<?= htmlspecialchars($p['profile_photo']) ?>" alt="Profile" id="photoPreview"/>
          <?php else: ?>
            <div class="avatar-lg" id="photoPreview"><?= $initials ?></div>
          <?php endif; ?>
          <div class="photo-overlay">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0"/>
            </svg>
            Change
          </div>
        </div>
        <div class="profile-info">
          <div class="profile-name"><?= htmlspecialchars($p['full_name']) ?></div>
          <div class="profile-email"><?= htmlspecialchars($p['email']) ?></div>
          <div class="profile-badges">
            <?php if (!empty($p['gender'])): ?><span class="badge badge-blue"><?= htmlspecialchars($p['gender']) ?></span><?php endif; ?>
            <?php if (!empty($p['preferred_language'])): ?><span class="badge badge-blue"><?= htmlspecialchars($p['preferred_language']) ?></span><?php endif; ?>
          </div>
          <div class="profile-tap-hint">Tap photo to change</div>
        </div>
      </div>
      <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;"
             onchange="previewAndSubmit(this)"/>
    </form>
  </div>

  <!-- Main Edit Form -->
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="update_profile"/>

    <div class="section-label">Contact Information</div>
    <div class="card" style="display:flex;flex-direction:column;gap:0.75rem;">
      <div class="grid-2">
        <div>
          <label class="field-label">Phone Number</label>
          <input type="tel" name="phone_number" class="field-input" value="<?= htmlspecialchars($p['phone_number'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Home Address (View Only)</label>
          <input type="text" class="field-input" value="<?= htmlspecialchars($p['home_address'] ?? 'Not set') ?>" readonly style="background:rgba(36,68,65,0.04);cursor:not-allowed;"/>
        </div>
      </div>
      <div class="grid-2">
        <div>
          <label class="field-label">City (View Only)</label>
          <input type="text" class="field-input" value="<?= htmlspecialchars($p['city'] ?? 'Not set') ?>" readonly style="background:rgba(36,68,65,0.04);cursor:not-allowed;"/>
        </div>
        <div>
          <label class="field-label">Country (View Only)</label>
          <input type="text" class="field-input" value="<?= htmlspecialchars($p['country_region'] ?? 'Not set') ?>" readonly style="background:rgba(36,68,65,0.04);cursor:not-allowed;"/>
        </div>
      </div>
    </div>

    <div class="section-label">Emergency Contact</div>
    <div class="card" style="display:flex;flex-direction:column;gap:0.75rem;">
      <div class="grid-3">
        <div>
          <label class="field-label">Name</label>
          <input type="text" name="emergency_name" class="field-input" value="<?= htmlspecialchars($p['emergency_name'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Relationship</label>
          <input type="text" name="emergency_relationship" class="field-input" value="<?= htmlspecialchars($p['emergency_relationship'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Number</label>
          <input type="tel" name="emergency_number" class="field-input" value="<?= htmlspecialchars($p['emergency_number'] ?? '') ?>"/>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-save">Save Changes</button>
  </form>

  <!-- Change Password Section -->
  <div class="section-label">Security</div>
  <form method="POST" class="card" style="display:flex;flex-direction:column;gap:0.75rem;">
    <input type="hidden" name="change_password"/>
    <div class="grid-3">
      <div>
        <label class="field-label">Current Password</label>
        <div style="position:relative;">
          <input type="password" name="current_password" id="pwd_current" class="field-input" required placeholder="Current password"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pwd_current')">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
      </div>
      <div>
        <label class="field-label">New Password</label>
        <div style="position:relative;">
          <input type="password" name="new_password" id="pwd_new" class="field-input" required placeholder="Min 8 chars" oninput="validatePassword(this)"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pwd_new')">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <div style="margin-top:0.4rem;font-size:0.72rem;color:var(--muted);display:flex;flex-direction:column;gap:0.1rem;">
          <div id="check_len">✗ At least 8 characters</div>
          <div id="check_upper">✗ 1 uppercase letter (A-Z)</div>
          <div id="check_lower">✗ 1 lowercase letter (a-z)</div>
          <div id="check_number">✗ 1 number (0-9)</div>
        </div>
      </div>
      <div>
        <label class="field-label">Confirm New Password</label>
        <div style="position:relative;">
          <input type="password" name="confirm_password" id="pwd_confirm" class="field-input" required placeholder="Re-enter new password"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pwd_confirm')">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
    <button type="submit" class="btn-save">Change Password</button>
  </form>

  <a href="auth/logout.php" class="logout-btn">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
    </svg>
    Log Out
  </a>
  <div style="height:0.5rem;"></div>
</div>

<script>
function previewAndSubmit(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    const wrap = document.querySelector('.photo-wrap');
    wrap.innerHTML = wrap.innerHTML.replace(
      /<(img|div)[^>]*id="photoPreview"[^>]*>.*?(<\/div>)?/s,
      `<img src="${e.target.result}" id="photoPreview" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(63,130,227,0.2);"/>`
    );
  };
  reader.readAsDataURL(input.files[0]);
  document.getElementById('photoForm').submit();
}

function togglePw(fieldId) {
  const field = document.getElementById(fieldId);
  field.type = field.type === 'password' ? 'text' : 'password';
}

function validatePassword(field) {
  const pwd = field.value;
  const len    = pwd.length >= 8;
  const upper  = /[A-Z]/.test(pwd);
  const lower  = /[a-z]/.test(pwd);
  const number = /[0-9]/.test(pwd);

  const set = (id, ok, label) => {
    const el = document.getElementById(id);
    el.style.color = ok ? 'var(--green)' : 'var(--muted)';
    el.textContent = (ok ? '✓ ' : '✗ ') + label;
  };

  set('check_len',    len,    'At least 8 characters');
  set('check_upper',  upper,  '1 uppercase letter (A-Z)');
  set('check_lower',  lower,  '1 lowercase letter (a-z)');
  set('check_number', number, '1 number (0-9)');
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>