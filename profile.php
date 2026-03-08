<?php
require_once 'includes/auth.php';

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
    // Only columns that actually exist in the patients table
    $fields = [
        'phone_number','home_address','city','country_region',
        'insurance_provider','insurance_policy_no',
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

    // Handle profile photo upload
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $dir     = 'uploads/profiles/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext     = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed)) {
            $fname = uniqid('patient_') . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $fname)) {
                $sets[]  = "profile_photo = ?";
                $vals[]  = $dir . $fname;
                $types  .= 's';
            }
        }
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
</style>

<div class="page">

  <?php if (isset($_GET['saved'])): ?>
  <div class="alert-success">✓ Profile updated successfully.</div>
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
        <label class="field-label">Home Address</label>
        <input type="text" name="home_address" class="field-input" value="<?= htmlspecialchars($p['home_address'] ?? '') ?>"/>
      </div>
      <div class="grid-2">
        <div>
          <label class="field-label">City</label>
          <input type="text" name="city" class="field-input" value="<?= htmlspecialchars($p['city'] ?? '') ?>"/>
        </div>
        <div>
          <label class="field-label">Country</label>
          <input type="text" name="country_region" class="field-input" value="<?= htmlspecialchars($p['country_region'] ?? '') ?>"/>
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
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>