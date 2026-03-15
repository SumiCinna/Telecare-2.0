<?php
require_once 'includes/auth.php';

$success = '';
$error   = '';

// ── Update profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $phone   = trim($_POST['phone_number']      ?? '');
    $clinic  = trim($_POST['clinic_name']       ?? '');
    $fee     = floatval($_POST['consultation_fee'] ?? 0);
    $langs   = trim($_POST['languages_spoken']  ?? '');
    $bio     = trim($_POST['bio']               ?? '');
    $avail   = isset($_POST['is_available'])    ? 1 : 0;

    $photo_path = null;
    if (!empty($_FILES['profile_photo']['name'])) {
        $dir = '../uploads/profiles/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext   = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $fname = uniqid('doc_') . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $fname)) {
            $photo_path = 'uploads/profiles/' . $fname;
        }
    }

    if ($photo_path) {
        $stmt = $conn->prepare("UPDATE doctors SET phone_number=?, clinic_name=?, consultation_fee=?, languages_spoken=?, bio=?, is_available=?, profile_photo=? WHERE id=?");
        $stmt->bind_param("ssdssisi", $phone, $clinic, $fee, $langs, $bio, $avail, $photo_path, $doctor_id);
    } else {
        $stmt = $conn->prepare("UPDATE doctors SET phone_number=?, clinic_name=?, consultation_fee=?, languages_spoken=?, bio=?, is_available=? WHERE id=?");
        $stmt->bind_param("ssdssi", $phone, $clinic, $fee, $langs, $bio, $avail, $doctor_id);
    }
    $stmt->execute();
    $success = 'Profile updated successfully.';

    // Refresh doc data
    $stmt2 = $conn->prepare("SELECT * FROM doctors WHERE id=? LIMIT 1");
    $stmt2->bind_param("i", $doctor_id);
    $stmt2->execute();
    $doc = $stmt2->get_result()->fetch_assoc();
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

$page_title       = 'Profile — TELE-CARE';
$page_title_short = 'My Profile';
$active_nav       = 'profile';
require_once 'includes/header.php';
?>

<div class="page">

  <?php if ($success): ?><div class="alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert-error">  <?= htmlspecialchars($error)   ?></div><?php endif; ?>

  <!-- Profile Header -->
  <div class="card" style="text-align:center;padding:1.8rem 1rem;">
    <div style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,var(--green),var(--green-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.4rem;margin:0 auto 0.8rem;overflow:hidden;">
      <?php if (!empty($doc['profile_photo'])): ?>
        <img src="../<?= htmlspecialchars($doc['profile_photo']) ?>" style="width:100%;height:100%;object-fit:cover;"/>
      <?php else: echo strtoupper(substr($doc['full_name'],0,2)); endif; ?>
    </div>
    <div style="font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
    <div style="font-size:0.83rem;color:var(--muted);margin-top:0.2rem;"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
    <div style="margin-top:0.6rem;display:flex;justify-content:center;gap:0.5rem;flex-wrap:wrap;">

      <?php if ($doc['is_verified']): ?><span class="badge badge-green">✓ Verified</span><?php endif; ?>
      <span class="badge <?= $doc['is_available']?'badge-green':'badge-gray' ?>"><?= $doc['is_available']?'Available':'Unavailable' ?></span>
    </div>
  </div>

  <!-- Edit Profile -->
  <div class="card">
    <div class="section-label">Edit Profile</div>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-field">
        <label class="field-label">Profile Photo</label>
        <input type="file" name="profile_photo" class="field-input" accept="image/*" style="padding:0.5rem;"/>
      </div>
      <div class="form-field">
        <label class="field-label">Phone Number</label>
        <input type="tel" name="phone_number" class="field-input" value="<?= htmlspecialchars($doc['phone_number'] ?? '') ?>" placeholder="09XXXXXXXXX"/>
      </div>
      <div class="form-field">
        <label class="field-label">Clinic / Hospital</label>
        <input type="text" name="clinic_name" class="field-input" value="<?= htmlspecialchars($doc['clinic_name'] ?? '') ?>" placeholder="e.g. St. Luke's"/>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <div class="form-field">
          <label class="field-label">Consultation Fee (₱)</label>
          <input type="number" name="consultation_fee" class="field-input" value="<?= $doc['consultation_fee'] ?? 0 ?>" min="0" step="0.01"/>
        </div>
        <div class="form-field">
          <label class="field-label">Languages</label>
          <input type="text" name="languages_spoken" class="field-input" value="<?= htmlspecialchars($doc['languages_spoken'] ?? '') ?>" placeholder="e.g. English"/>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Bio</label>
        <textarea name="bio" class="field-input" placeholder="Brief professional background..."><?= htmlspecialchars($doc['bio'] ?? '') ?></textarea>
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

<?php require_once 'includes/nav.php'; ?>
</body>
</html>