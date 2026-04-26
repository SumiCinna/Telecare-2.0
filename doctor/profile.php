<?php
require_once 'includes/auth.php';

$fixed_clinic_name = 'EXCELLCARE MEDICAL SYSTEM INC.';

$success = '';
$error   = '';

if (isset($_GET['photo_saved'])) {
  $success = 'Profile photo updated successfully.';
}

// ── Update profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photo_only'])) {
  if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $upload = $_FILES['profile_photo'];
    $max_photo_size = 5 * 1024 * 1024; // 5MB

    if (($upload['size'] ?? 0) > $max_photo_size) {
      $error = 'Profile photo is too large. Max size is 5MB.';
    } else {
      $ext = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
      $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

      $mime = $upload['type'] ?? '';
      if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
          $detected = finfo_file($finfo, $upload['tmp_name']);
          if ($detected) $mime = $detected;
          finfo_close($finfo);
        }
      } elseif (function_exists('mime_content_type')) {
        $detected = mime_content_type($upload['tmp_name']);
        if ($detected) $mime = $detected;
      }

      $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

      if (!in_array($ext, $allowed_ext, true) || !in_array($mime, $allowed_mime, true)) {
        $error = 'Invalid profile photo format. Use JPG, PNG, WEBP, or GIF.';
      } else {
        $dir = '../uploads/profiles/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
          $error = 'Unable to create photo upload folder.';
        } else {
          $fname = uniqid('doc_') . '.' . $ext;
          if (move_uploaded_file($upload['tmp_name'], $dir . $fname)) {
            $photo_path = 'uploads/profiles/' . $fname;
            $stmt = $conn->prepare("UPDATE doctors SET profile_photo = ? WHERE id = ?");
            $stmt->bind_param("si", $photo_path, $doctor_id);
            if ($stmt->execute()) {
              header('Location: profile.php?photo_saved=1');
              exit;
            }
            $error = 'Could not update profile photo in database.';
          } else {
            $error = 'Failed to save profile photo. Please try again.';
          }
        }
      }
    }
  } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $error = 'Failed to upload profile photo. Please try again.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone   = trim($_POST['phone_number']      ?? '');
  $clinic  = $fixed_clinic_name;
    $langs   = trim($_POST['languages_spoken']  ?? '');
    $bio     = trim($_POST['bio']               ?? '');
    $avail   = isset($_POST['is_available'])    ? 1 : 0;

    // Validate phone number: must be 9XXXXXXXXX (exactly 10 digits starting with 9)
    if (!preg_match('/^9\d{9}$/', $phone)) {
        $error = 'Phone number must be 10 digits starting with 9 (e.g., 9123456789).';
    } elseif (!$error) {
        // Add +63 prefix for storage
        $phone = '+63' . $phone;
        $stmt = $conn->prepare("UPDATE doctors SET phone_number=?, clinic_name=?, languages_spoken=?, bio=?, is_available=? WHERE id=?");
        $stmt->bind_param("ssssii", $phone, $clinic, $langs, $bio, $avail, $doctor_id);

      if ($stmt && $stmt->execute()) {
        $success = 'Profile updated successfully.';
        $stmt2 = $conn->prepare("SELECT * FROM doctors WHERE id=? LIMIT 1");
        $stmt2->bind_param("i", $doctor_id);
        $stmt2->execute();
        $doc = $stmt2->get_result()->fetch_assoc();
      } else {
        $error = 'Could not update profile. Please try again.';
      }
    }
}

// ── Change password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $doc['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hashed = password_hash($new, PASSWORD_BCRYPT);
        $stmt   = $conn->prepare("UPDATE doctors SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed, $doctor_id);
        $stmt->execute();
        $success = 'Password changed successfully.';
    }
}

// ── Update schedules ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_schedules'])) {
  $error = 'Schedule updates are now managed by staff account.';
}

// ── Generate 30-min time options ──
function generateTimeOptions(): array {
    $opts = [];
    for ($h = 0; $h < 24; $h++) {
        foreach ([0, 30] as $m) {
            $val  = str_pad($h,2,'0',STR_PAD_LEFT).':'.str_pad($m,2,'0',STR_PAD_LEFT);
            $ap   = $h >= 12 ? 'PM' : 'AM';
            $hr   = $h % 12 ?: 12;
            $lbl  = $hr.':'.str_pad($m,2,'0',STR_PAD_LEFT).' '.$ap;
            $opts[] = ['val'=>$val,'lbl'=>$lbl];
        }
    }
    return $opts;
}

// ── Fetch schedules ──
$schedules = [];
$sres = $conn->query("SELECT * FROM doctor_schedules WHERE doctor_id=$doctor_id ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
if ($sres) while ($s = $sres->fetch_assoc()) $schedules[] = $s;

$page_title       = 'Profile — TELE-CARE';
$page_title_short = 'My Profile';
$active_nav       = 'profile';
require_once 'includes/header.php';

$days_of_week = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
?>

<style>
  .sched-row{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:0.6rem;align-items:center;margin-bottom:0.6rem;}
  .sched-input{padding:0.6rem 0.7rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:0.85rem;color:var(--green);outline:none;width:100%;background:#fff;transition:border-color .2s;}
  .sched-input:focus{border-color:var(--blue);}
  select.sched-input{cursor:pointer;}
  .sched-remove{background:rgba(195,54,67,0.08);border:none;border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--red);transition:all .2s;flex-shrink:0;}
  .sched-remove:hover{background:rgba(195,54,67,0.18);}
  .sched-chip{display:inline-flex;align-items:center;gap:0.5rem;background:rgba(36,68,65,0.07);border-radius:10px;padding:0.5rem 0.8rem;font-size:0.8rem;font-weight:600;color:var(--green);margin-bottom:0.4rem;}
  .sched-chip .day{font-weight:700;min-width:72px;}
  .sched-chip .time{color:var(--blue);font-size:0.78rem;}
  .no-sched{font-size:0.85rem;color:var(--muted);font-style:italic;padding:0.5rem 0;}
  .btn-add-sched{display:inline-flex;align-items:center;gap:0.4rem;background:rgba(63,130,227,0.1);color:var(--blue);border:none;border-radius:50px;padding:0.45rem 1rem;font-size:0.8rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;margin-top:0.4rem;}
  .btn-add-sched:hover{background:rgba(63,130,227,0.18);}

  /* Modal overlay */
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:flex-end;justify-content:center;z-index:300;backdrop-filter:blur(4px);}
  .modal-overlay.open{display:flex;}
  .modal-sheet{background:#fff;border-radius:24px 24px 0 0;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;padding:1.5rem 1.4rem 2rem;animation:slideUp .3s cubic-bezier(.16,1,.3,1);}
  @keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
  .modal-handle{width:40px;height:4px;background:rgba(0,0,0,0.1);border-radius:2px;margin:0 auto 1.2rem;}

  .section-label{font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.8rem;}
  .sched-header{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:0.6rem;margin-bottom:0.4rem;}
  .sched-header span{font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--muted);}

  .photo-wrap{position:relative;width:72px;height:72px;margin:0 auto 0.8rem;cursor:pointer;}
  .photo-wrap img,.photo-wrap .avatar-box{width:72px;height:72px;border-radius:18px;object-fit:cover;}
  .avatar-box{background:linear-gradient(135deg,var(--green),var(--green-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.4rem;}
  .photo-overlay{position:absolute;inset:0;border-radius:18px;background:rgba(0,0,0,0.42);display:flex;align-items:center;justify-content:center;flex-direction:column;gap:0.15rem;color:#fff;font-size:0.62rem;font-weight:700;opacity:0;transition:opacity .2s;}
  .photo-wrap:hover .photo-overlay{opacity:1;}
  .photo-overlay svg{width:14px;height:14px;}
</style>

<div class="page">

  <?php if ($success): ?><div class="alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert-error">  <?= htmlspecialchars($error)   ?></div><?php endif; ?>

  <!-- Profile Header -->
  <div class="card" style="text-align:center;padding:1.8rem 1rem;">
    <form method="POST" enctype="multipart/form-data" id="photoForm">
      <input type="hidden" name="photo_only" value="1"/>
      <div class="photo-wrap" onclick="document.getElementById('photoInput').click()">
        <?php if (!empty($doc['profile_photo'])): ?>
          <img src="../<?= htmlspecialchars($doc['profile_photo']) ?>" id="photoPreview" alt="Profile Photo"/>
        <?php else: ?>
          <div class="avatar-box" id="photoPreview"><?= strtoupper(substr($doc['full_name'],0,2)) ?></div>
        <?php endif; ?>
        <div class="photo-overlay">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0"/>
          </svg>
          Change
        </div>
      </div>
      <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="previewAndSubmit(this)"/>
    </form>
    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
    <div style="font-size:0.83rem;color:var(--muted);margin-top:0.2rem;"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
    <?php if (!empty($doc['subspecialty'])): ?>
    <div style="font-size:0.78rem;color:var(--muted);margin-top:0.1rem;font-style:italic;"><?= htmlspecialchars($doc['subspecialty']) ?></div>
    <?php endif; ?>
    <div style="margin-top:0.6rem;display:flex;justify-content:center;gap:0.5rem;flex-wrap:wrap;">
      <?php if ($doc['is_verified']): ?><span class="badge badge-green">✓ Verified</span><?php endif; ?>
      <span class="badge <?= $doc['is_available']?'badge-green':'badge-gray' ?>"><?= $doc['is_available']?'Available':'Unavailable' ?></span>
    </div>
  </div>

  <!-- ── MY SCHEDULE ── -->
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;">
      <div class="section-label" style="margin-bottom:0;">My Schedule</div>
      <span class="badge badge-blue" style="font-size:0.72rem;">Managed by Staff</span>
    </div>

    <?php if (empty($schedules)): ?>
      <div class="no-sched">No schedule set yet. Tap Edit Schedule to add your availability.</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0.4rem;">
        <?php
        function fmt12($t){ [$h,$m]=explode(':',$t); $ap=$h>=12?'PM':'AM'; $hr=$h%12?:12; return $hr.':'.str_pad($m,2,'0',STR_PAD_LEFT).' '.$ap; }
        foreach ($schedules as $s): ?>
        <div class="sched-chip">
          <span class="day"><?= htmlspecialchars($s['day_of_week']) ?></span>
          <span class="time"><?= fmt12($s['start_time']) ?> — <?= fmt12($s['end_time']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Edit Profile -->
  <div class="card">
    <div class="section-label">Edit Profile</div>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-field">
        <label class="field-label">Phone Number</label>
        <div style="display:flex;align-items:center;gap:0.5rem;">
          <span style="font-weight:600;color:var(--green);font-size:0.95rem;">+63</span>
          <input type="text" name="phone_number" id="phoneNumber" class="field-input" value="<?= htmlspecialchars(preg_replace('/^\+63/', '', $doc['phone_number'] ?? '')) ?>" placeholder="9XXXXXXXXX" maxlength="10" inputmode="numeric" style="flex:1;"/>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Clinic / Hospital</label>
        <input type="text" class="field-input" value="<?= htmlspecialchars($fixed_clinic_name) ?>" readonly/>
        <input type="hidden" name="clinic_name" value="<?= htmlspecialchars($fixed_clinic_name) ?>"/>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <div class="form-field">
          <label class="field-label">Consultation Fee (₱)</label>
          <input type="text" class="field-input" value="₱<?= number_format((float)($doc['consultation_fee'] ?? 0), 2) ?>" readonly/>
          <div style="font-size:0.75rem;color:var(--muted);margin-top:0.35rem;">Consultation fee updates are managed by staff account.</div>
        </div>
        <div class="form-field">
          <label class="field-label">Languages</label>
          <input type="text" name="languages_spoken" class="field-input" value="<?= htmlspecialchars($doc['languages_spoken'] ?? '') ?>" placeholder="e.g. English"/>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Bio</label>
        <textarea name="bio" class="field-input" rows="3" placeholder="Brief professional background..."><?= htmlspecialchars($doc['bio'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;align-items:center;gap:0.7rem;margin-bottom:1rem;">
        <input type="checkbox" name="is_available" id="avail" <?= $doc['is_available']?'checked':'' ?> style="width:18px;height:18px;accent-color:var(--green);"/>
        <label for="avail" style="font-size:0.88rem;font-weight:600;cursor:pointer;">Available for appointments</label>
      </div>
      <button type="submit" name="update_profile" class="btn-submit">Save Changes</button>
    </form>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="section-label">Change Password</div>
    <form method="POST">
      <div class="form-field">
        <label class="field-label">Current Password</label>
        <input type="password" name="current_password" class="field-input" required/>
      </div>
      <div class="form-field">
        <label class="field-label">New Password</label>
        <input type="password" name="new_password" class="field-input" placeholder="At least 8 characters" required/>
      </div>
      <div class="form-field">
        <label class="field-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="field-input" required/>
      </div>
      <button type="submit" name="change_password" class="btn-submit btn-red-submit">Change Password</button>
    </form>
  </div>

  <!-- Logout -->
  <div class="card" style="text-align:center;">
    <a href="logout.php" style="color:var(--red);font-weight:600;font-size:0.88rem;text-decoration:none;">Sign Out</a>
  </div>

</div>

<script>
function previewAndSubmit(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    const wrap = document.querySelector('.photo-wrap');
    const existing = document.getElementById('photoPreview');
    if (existing && existing.tagName.toLowerCase() === 'img') {
      existing.src = e.target.result;
    } else {
      const replacement = document.createElement('img');
      replacement.id = 'photoPreview';
      replacement.alt = 'Profile Photo';
      replacement.src = e.target.result;
      if (existing) {
        existing.replaceWith(replacement);
      } else if (wrap) {
        wrap.prepend(replacement);
      }
    }
  };
  reader.readAsDataURL(input.files[0]);
  document.getElementById('photoForm').submit();
}

// Phone number formatting for doctor profile
const phoneInput = document.getElementById('phoneNumber');
if (phoneInput) {
  phoneInput.addEventListener('input', function(e) {
    // Remove all non-digits
    e.target.value = e.target.value.replace(/\D/g, '');
    
    // Limit to 10 digits
    if (e.target.value.length > 10) {
      e.target.value = e.target.value.substring(0, 10);
    }
    
    // Enforce starts with 9
    if (e.target.value.length > 0 && !e.target.value.startsWith('9')) {
      e.target.value = e.target.value.replace(/^(?!9)/, '9');
    }
  });

  // Prevent non-numeric input
  phoneInput.addEventListener('keypress', function(e) {
    if (!/[0-9]/.test(e.key)) {
      e.preventDefault();
    }
  });
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>