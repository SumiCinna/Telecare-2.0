<?php
require_once 'includes/auth.php';

// ── Handle password change ──
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

// ── Handle photo-only upload ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photo_only'])) {
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $dir     = 'uploads/profiles/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext     = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed)) {
            $fname = uniqid('patient_') . '.' . $ext;
            $fname_path = $dir . $fname;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $fname_path)) {
                $stmt = $conn->prepare("UPDATE patients SET profile_photo = ? WHERE id = ?");
                $stmt->bind_param("si", $fname_path, $patient_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    // Re-fetch so the header shows the new photo immediately
    $p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
    header('Location: profile.php?saved=1');
    exit;
}

// ── Handle profile update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Only editable columns (city, country, address are now view-only)
    $fields = [
        'phone_number','insurance_provider','insurance_policy_no',
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

// Re-fetch fresh data after possible update
$p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

$page_title = 'My Profile — TELE-CARE';
$active_nav = 'profile';
require_once 'includes/header.php';
?>

<style>
  .photo-wrap {
    position:relative; width:88px; height:88px; margin:0 auto 0.8rem; cursor:pointer;
  }
  .photo-wrap img, .photo-wrap .avatar-lg {
    width:88px; height:88px; border-radius:50%; object-fit:cover;
    border:3px solid rgba(63,130,227,0.2);
  }
  .avatar-lg {
    background:linear-gradient(135deg,var(--blue),var(--blue-dark));
    color:#fff; display:flex; align-items:center; justify-content:center;
    font-size:1.6rem; font-weight:700;
  }
  .photo-overlay {
    position:absolute; inset:0; border-radius:50%;
    background:rgba(0,0,0,0.45); display:flex; align-items:center; justify-content:center;
    opacity:0; transition:opacity 0.2s; color:#fff;
    font-size:0.7rem; font-weight:700; flex-direction:column; gap:0.2rem;
  }
  .photo-wrap:hover .photo-overlay { opacity:1; }
  .photo-overlay svg { width:18px; height:18px; }

  .logout-btn {
    display:flex; align-items:center; gap:0.5rem;
    background:rgba(195,54,67,0.07); color:var(--red);
    border:1px solid rgba(195,54,67,0.15); border-radius:12px;
    padding:0.75rem 1rem; font-size:0.86rem; font-weight:600;
    width:100%; justify-content:center; margin-top:1rem;
    text-decoration:none; transition:background 0.2s;
  }
  .logout-btn:hover { background:rgba(195,54,67,0.14); }

  .pw-toggle {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:#9ab0ae;
    padding:0.5rem; display:flex; align-items:center; justify-content:center;
  }
  .pw-toggle:hover { color:var(--green); }

  .alert-error {
    background:rgba(195,54,67,0.08); border:1px solid rgba(195,54,67,0.2);
    color:var(--red); border-radius:12px; padding:0.75rem 1rem;
    font-size:0.86rem; margin-bottom:1rem;
  }

  .alert-success {
    background:rgba(36,68,65,0.08); border:1px solid rgba(36,68,65,0.2);
    color:var(--green); border-radius:12px; padding:0.75rem 1rem;
    font-size:0.86rem; margin-bottom:1rem;
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

  <!-- Profile Header -->
  <div class="card" style="text-align:center;padding:2rem;">
    <!-- Photo-only quick-upload form -->
    <form method="POST" enctype="multipart/form-data" id="photoForm">
      <input type="hidden" name="photo_only" value="1"/>
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
      <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;"
             onchange="previewAndSubmit(this)"/>
    </form>

    <div style="font-weight:700;font-size:1.1rem;color:var(--text);"><?= htmlspecialchars($p['full_name']) ?></div>
    <div style="font-size:0.82rem;color:var(--muted);margin-top:0.2rem;"><?= htmlspecialchars($p['email']) ?></div>
    <div style="display:flex;justify-content:center;gap:0.5rem;margin-top:0.8rem;">
      <?php if (!empty($p['gender'])): ?><span class="badge badge-blue"><?= htmlspecialchars($p['gender']) ?></span><?php endif; ?>
      <?php if (!empty($p['preferred_language'])): ?><span class="badge badge-blue"><?= htmlspecialchars($p['preferred_language']) ?></span><?php endif; ?>
    </div>
    <div style="font-size:0.72rem;color:var(--muted);margin-top:0.6rem;">Tap photo to change</div>
  </div>

  <!-- Main Edit Form -->
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="update_profile"/>

    <div class="section-label">Contact Information</div>
    <div class="card" style="display:flex;flex-direction:column;gap:0.9rem;">
      <div>
        <label class="field-label">Phone Number</label>
        <input type="tel" name="phone_number" class="field-input" value="<?= htmlspecialchars($p['phone_number'] ?? '') ?>"/>
      </div>
      <div>
        <label class="field-label">Home Address (View Only)</label>
        <input type="text" class="field-input" value="<?= htmlspecialchars($p['home_address'] ?? 'Not set') ?>" readonly style="background:rgba(36,68,65,0.04);cursor:not-allowed;"/>
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

    <div class="section-label">Health Insurance</div>
    <div class="card">
      <div class="grid-2">
        <div>
          <label class="field-label">Provider</label>
          <input type="text" name="insurance_provider" class="field-input" value="<?= htmlspecialchars($p['insurance_provider'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Policy No.</label>
          <input type="text" name="insurance_policy_no" class="field-input" value="<?= htmlspecialchars($p['insurance_policy_no'] ?? '') ?>"/>
        </div>
      </div>
    </div>

    <div class="section-label">Emergency Contact</div>
    <div class="card" style="display:flex;flex-direction:column;gap:0.9rem;">
      <div>
        <label class="field-label">Name</label>
        <input type="text" name="emergency_name" class="field-input" value="<?= htmlspecialchars($p['emergency_name'] ?? '') ?>"/>
      </div>
      <div class="grid-2">
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
  <form method="POST" class="card" style="display:flex;flex-direction:column;gap:0.9rem;">
    <input type="hidden" name="change_password"/>
    <div>
      <label class="field-label">Current Password</label>
      <div style="position:relative;">
        <input type="password" name="current_password" id="pwd_current" class="field-input" required placeholder="Enter your current password"/>
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
        <input type="password" name="new_password" id="pwd_new" class="field-input" required placeholder="Min 8 chars: 1 uppercase, 1 lowercase, 1 number" oninput="validatePassword(this)"/>
        <button type="button" class="pw-toggle" onclick="togglePw('pwd_new')">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
        </button>
      </div>
      <div style="margin-top:0.5rem;font-size:0.75rem;color:var(--muted);">
        <div id="check_len" style="color:var(--muted);">✗ At least 8 characters</div>
        <div id="check_upper" style="color:var(--muted);">✗ 1 uppercase letter (A-Z)</div>
        <div id="check_lower" style="color:var(--muted);">✗ 1 lowercase letter (a-z)</div>
        <div id="check_number" style="color:var(--muted);">✗ 1 number (0-9)</div>
      </div>
    </div>

    <div>
      <label class="field-label">Confirm New Password</label>
      <div style="position:relative;">
        <input type="password" name="confirm_password" id="pwd_confirm" class="field-input" required placeholder="Re-enter your new password"/>
        <button type="button" class="pw-toggle" onclick="togglePw('pwd_confirm')">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
        </button>
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
  // Show instant preview before upload
  const reader = new FileReader();
  reader.onload = function(e) {
    const wrap = document.querySelector('.photo-wrap');
    // Replace whatever is inside (img or avatar-lg div) with a preview img
    wrap.innerHTML = wrap.innerHTML.replace(
      /<(img|div)[^>]*id="photoPreview"[^>]*>.*?(<\/div>)?/s,
      `<img src="${e.target.result}" id="photoPreview" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid rgba(63,130,227,0.2);"/>`
    );
  };
  reader.readAsDataURL(input.files[0]);
  // Submit the form to save it
  document.getElementById('photoForm').submit();
}

function togglePw(fieldId) {
  const field = document.getElementById(fieldId);
  field.type = field.type === 'password' ? 'text' : 'password';
}

function validatePassword(field) {
  const pwd = field.value;
  const len = pwd.length >= 8;
  const upper = /[A-Z]/.test(pwd);
  const lower = /[a-z]/.test(pwd);
  const number = /[0-9]/.test(pwd);

  document.getElementById('check_len').style.color = len ? 'var(--green)' : 'var(--muted)';
  document.getElementById('check_len').textContent = len ? '✓ At least 8 characters' : '✗ At least 8 characters';

  document.getElementById('check_upper').style.color = upper ? 'var(--green)' : 'var(--muted)';
  document.getElementById('check_upper').textContent = upper ? '✓ 1 uppercase letter (A-Z)' : '✗ 1 uppercase letter (A-Z)';

  document.getElementById('check_lower').style.color = lower ? 'var(--green)' : 'var(--muted)';
  document.getElementById('check_lower').textContent = lower ? '✓ 1 lowercase letter (a-z)' : '✗ 1 lowercase letter (a-z)';

  document.getElementById('check_number').style.color = number ? 'var(--green)' : 'var(--muted)';
  document.getElementById('check_number').textContent = number ? '✓ 1 number (0-9)' : '✗ 1 number (0-9)';
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>