<?php
// private_telecare/profile.php — "Settings" page
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/legal_policy_helper.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

// Make sure the extra columns this page needs (2FA flag, notification
// preferences, account status) exist before we read/write them.
tc_ensure_patient_settings_columns($conn);

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
        header('Location: router.php?page=profile&pwd_saved=1');
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
    header('Location: router.php?page=profile&saved=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Personal info + emergency contact are saved together from the
    // "Edit Profile" form. Email is intentionally left out — it's tied to
    // login, so it isn't editable from here.
    $fields = [
        'full_name',
        'phone_number',
        'date_of_birth',
        'gender',
        'home_address',
        'emergency_name','emergency_relationship','emergency_number'
    ];

    $sets  = [];
    $vals  = [];
    $types = '';

    foreach ($fields as $f) {
        $sets[]  = "$f = ?";
        $val     = trim($_POST[$f] ?? '');
        $vals[]  = ($f === 'date_of_birth' && $val === '') ? null : $val;
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

    header('Location: router.php?page=profile&saved=1');
    exit;
}

$p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
$age = tc_calculate_age($p['date_of_birth'] ?? null);

$legal_policy_slugs = [
  'data-privacy-notice' => 'Data Privacy Notice',
  'terms-and-conditions' => 'Terms and Conditions',
  'privacy-policy' => 'Privacy Policy',
];
$legal_policy_items = [];
$latest_legal_update = null;

foreach ($legal_policy_slugs as $slug => $label) {
  $policy = get_legal_policy($conn, $slug);
  $legal_policy_items[$slug] = [
    'label' => $label,
    'updated_at' => $policy['updated_at'] ?? null,
  ];

  if (!empty($policy['updated_at'])) {
    if ($latest_legal_update === null || strtotime($policy['updated_at']) > strtotime($latest_legal_update)) {
      $latest_legal_update = $policy['updated_at'];
    }
  }
}

$latest_legal_update_label = $latest_legal_update
  ? date('M j, Y g:i A', strtotime($latest_legal_update))
  : 'Unavailable';

$page_title = 'Settings — TELE-CARE';
$active_nav = 'profile';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
  html, body { overflow-x: hidden; }

  .page {
    width: min(100%, 1120px);
    max-width: 1120px;
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

  /* ── Page head ─────────────────────────────────────────────────────── */
  .settings-page-head { padding: 0.4rem 0.1rem 1.1rem; }
  .settings-page-head h1 { margin: 0; font-size: 1.5rem; font-weight: 800; color: var(--text); }
  .settings-page-head p { margin: 0.3rem 0 0; font-size: 0.86rem; color: var(--muted); }

  /* ── Compact identity / photo card ────────────────────────────────── */
  .profile-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
  }

  .photo-wrap {
    position: relative;
    width: 64px;
    height: 64px;
    flex-shrink: 0;
    cursor: pointer;
  }

  .photo-wrap img,
  .photo-wrap .avatar-lg {
    width: 64px;
    height: 64px;
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
    font-size: 1.25rem;
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
    font-size: 0.6rem;
    font-weight: 700;
    flex-direction: column;
    gap: 0.15rem;
  }

  .photo-wrap:hover .photo-overlay { opacity: 1; }
  .photo-overlay svg { width: 14px; height: 14px; }

  .profile-info { flex: 1; min-width: 0; }
  .profile-name { font-weight: 700; font-size: 0.98rem; color: var(--text); }
  .profile-email { font-size: 0.8rem; color: var(--muted); margin-top: 0.15rem; }
  .profile-tap-hint { font-size: 0.68rem; color: var(--muted); margin-top: 0.3rem; }

  /* ── Two-column settings grid (matches the Settings mockup) ─────────── */
  .settings-grid {
    display: grid;
    grid-template-columns: 1.65fr 1fr;
    gap: 1rem;
    align-items: start;
    margin-top: 1rem;
  }

  .settings-col { display: flex; flex-direction: column; gap: 1rem; }

  .settings-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
  }

  .settings-card-title { font-size: 1rem; font-weight: 800; color: var(--text); }
  .settings-card-sub   { font-size: 0.8rem; color: var(--muted); margin-top: 0.2rem; }

  .btn-outline-blue {
    border: 1px solid rgba(63,130,227,0.25);
    background: rgba(63,130,227,0.06);
    color: var(--blue);
    border-radius: 10px;
    padding: 0.55rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.2s;
  }
  .btn-outline-blue:hover { background: rgba(63,130,227,0.12); }

  .btn-outline-red {
    border: 1px solid rgba(195,54,67,0.2);
    background: rgba(195,54,67,0.07);
    color: var(--red);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-size: 0.86rem;
    font-weight: 700;
    width: 100%;
    cursor: pointer;
    transition: background 0.2s;
  }
  .btn-outline-red:hover { background: rgba(195,54,67,0.14); }

  .chevron-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    border: 1px solid rgba(26,47,94,0.12);
    background: #fff;
    color: var(--text);
    border-radius: 12px;
    padding: 0.8rem 1rem;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
  }
  .chevron-btn:hover { background: rgba(63,130,227,0.05); border-color: rgba(63,130,227,0.3); }
  .chevron-btn svg { width: 16px; height: 16px; color: var(--muted); }

  /* ── View field rows (Profile Information, view mode) ───────────────── */
  .view-field-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--muted);
    margin-bottom: 0.25rem;
  }
  .view-field-value {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--text);
    word-break: break-word;
  }

  /* ── Notification / toggle rows ──────────────────────────────────────── */
  .toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 0;
    border-top: 1px solid rgba(26,47,94,0.08);
  }
  .toggle-row:first-of-type { border-top: none; padding-top: 0; }
  .toggle-row-title { font-size: 0.86rem; font-weight: 700; color: var(--text); }
  .toggle-row-sub { font-size: 0.76rem; color: var(--muted); margin-top: 0.15rem; line-height: 1.4; }

  .tc-switch {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
  }
  .tc-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
  .tc-switch-track {
    position: absolute;
    inset: 0;
    background: #d7deee;
    border-radius: 50px;
    cursor: pointer;
    transition: background 0.2s;
  }
  .tc-switch-track::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    left: 3px;
    top: 3px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
  }
  .tc-switch input:checked + .tc-switch-track { background: var(--blue); }
  .tc-switch input:checked + .tc-switch-track::before { transform: translateX(18px); }
  .tc-switch input:disabled + .tc-switch-track { opacity: 0.55; cursor: not-allowed; }

  /* ── Edit Profile form ───────────────────────────────────────────────── */
  .edit-section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.86rem;
    font-weight: 800;
    color: var(--text);
    margin: 1.1rem 0 0.65rem;
  }
  .edit-section-title:first-child { margin-top: 0; }
  .edit-section-title svg { width: 16px; height: 16px; color: var(--blue); }

  .mini-security-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: rgba(63,130,227,0.05);
    border: 1px solid rgba(63,130,227,0.12);
    border-radius: 12px;
    padding: 0.8rem 1rem;
  }
  .mini-security-dots { font-size: 1rem; letter-spacing: 0.15em; color: var(--text); font-weight: 700; }

  .edit-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1.1rem;
  }
  .btn-cancel {
    background: none;
    border: none;
    color: var(--muted);
    font-weight: 700;
    font-size: 0.86rem;
    padding: 0.7rem 1rem;
    cursor: pointer;
  }
  .btn-cancel:hover { color: var(--text); }
  .btn-save-inline {
    background: var(--blue);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 0.7rem 1.4rem;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s, transform 0.2s;
  }
  .btn-save-inline:hover { background: var(--blue-dark); transform: translateY(-1px); }

  #profileEdit { display: none; }

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

  .legal-policy-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
  }

  .legal-policy-copy { min-width: 0; }

  .legal-policy-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 0.2rem;
  }

  .legal-policy-note {
    font-size: 0.8rem;
    color: var(--muted);
    line-height: 1.5;
  }

  .legal-policy-btn {
    border: 1px solid rgba(63,130,227,0.2);
    background: rgba(63,130,227,0.08);
    color: var(--blue);
    border-radius: 12px;
    padding: 0.78rem 1rem;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.2s, transform 0.2s;
  }

  .legal-policy-btn:hover {
    background: rgba(63,130,227,0.14);
    transform: translateY(-1px);
  }

  /* ── Modal overlay (shared: change password / deactivate / legal) ───── */
  .policy-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(10,14,20,0.55);
    backdrop-filter: blur(2px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1.1rem;
  }

  .policy-modal-overlay.open { display: flex; }

  .policy-modal-box {
    width: min(100%, 760px);
    max-height: 86vh;
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 30px 80px rgba(0,0,0,0.28);
  }

  .policy-modal-box.narrow { width: min(100%, 460px); }

  .policy-modal-head {
    padding: 1.1rem 1.3rem;
    border-bottom: 1px solid rgba(36,68,65,0.1);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-shrink: 0;
  }

  .policy-modal-head h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--text); }

  .policy-modal-subtitle { margin-top: 0.2rem; font-size: 0.8rem; color: var(--muted); line-height: 1.5; }

  .policy-modal-close {
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 8px;
    flex-shrink: 0;
  }

  .policy-modal-close:hover { background: rgba(36,68,65,0.06); color: var(--text); }

  .policy-modal-tabs {
    display: flex;
    gap: 0.35rem;
    padding: 0.8rem 1.3rem 0;
    border-bottom: 1px solid rgba(36,68,65,0.1);
    flex-shrink: 0;
    overflow-x: auto;
  }

  .policy-modal-tab {
    background: none;
    border: none;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--muted);
    padding: 0.55rem 0.85rem;
    border-radius: 10px 10px 0 0;
    white-space: nowrap;
  }

  .policy-modal-tab.active { color: var(--blue); background: rgba(63,130,227,0.08); }

  .policy-modal-body {
    padding: 1.2rem 1.3rem;
    overflow-y: auto;
    flex: 1;
    font-size: 0.88rem;
    line-height: 1.8;
    color: var(--text);
  }

  .policy-modal-section { display: none; }
  .policy-modal-section.active { display: block; }

  .policy-section-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.8rem;
  }

  .policy-section-head h4 { margin: 0; font-size: 0.98rem; font-weight: 800; }

  .policy-section-update { font-size: 0.76rem; color: var(--muted); white-space: nowrap; }

  .policy-modal-footer {
    padding: 0.95rem 1.3rem;
    border-top: 1px solid rgba(36,68,65,0.1);
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-shrink: 0;
  }

  .policy-footer-note { font-size: 0.78rem; color: var(--muted); }

  .policy-close-btn {
    border: 1px solid rgba(63,130,227,0.2);
    background: var(--blue);
    color: #fff;
    border-radius: 10px;
    padding: 0.7rem 1rem;
    font-size: 0.86rem;
    font-weight: 700;
    cursor: pointer;
  }

  .policy-close-btn:hover { background: var(--blue-dark); }

  .deactivate-warning {
    background: rgba(195,54,67,0.07);
    border: 1px solid rgba(195,54,67,0.18);
    color: var(--red);
    border-radius: 12px;
    padding: 0.8rem 1rem;
    font-size: 0.82rem;
    line-height: 1.5;
  }

  @media (max-width: 980px) {
    .settings-grid { grid-template-columns: 1fr; }
  }

  @media (max-width: 900px) {
    .page { width: 100%; max-width: 100%; padding: 0.9rem 0.9rem 6.2rem; }
    .page .card { border-radius: 14px; padding: 1rem !important; }
    .section-label { margin-top: 0.8rem; margin-bottom: 0.35rem; }
  }

  @media (max-width: 720px) {
    .page .grid-3 { grid-template-columns: 1fr 1fr; }
  }

  @media (max-width: 600px) {
    .page { padding: 0.7rem 0.72rem 6.4rem; }
    .page .grid-2, .page .grid-3 { grid-template-columns: 1fr; gap: 0.6rem; }
    .profile-header { gap: 0.8rem; padding: 0.85rem 1rem; }
    .photo-wrap, .photo-wrap img, .photo-wrap .avatar-lg { width: 56px; height: 56px; }
    .avatar-lg { font-size: 1.1rem; }
    .alert-error, .alert-success { font-size: 0.78rem; padding: 0.55rem 0.75rem; }
    .pw-toggle { right: 8px; }
    .mini-security-row { flex-direction: column; align-items: flex-start; }
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

  <div class="settings-page-head">
    <h1>Settings</h1>
    <p>Manage your account, security, notifications, and preferences.</p>
  </div>

  <!-- Photo / identity (kept from the previous profile page) -->
  <div class="card" style="padding:0;margin-bottom:1rem;">
    <form method="POST" enctype="multipart/form-data" id="photoForm">
      <input type="hidden" name="photo_only" value="1"/>
      <div class="profile-header">
        <div class="photo-wrap" onclick="document.getElementById('photoInput').click()">
          <?php if (!empty($p['profile_photo'])): ?>
            <img src="<?= htmlspecialchars($p['profile_photo']) ?>" alt="Profile" id="photoPreview"/>
          <?php else: ?>
            <div class="avatar-lg" id="photoPreview"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
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
          <div class="profile-tap-hint">Tap photo to change</div>
        </div>
      </div>
      <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;"
             onchange="previewAndSubmit(this)"/>
    </form>
  </div>

  <div class="settings-grid">
    <!-- ── Main column ─────────────────────────────────────────────── -->
    <div class="settings-col">

      <!-- Profile Information -->
      <div class="card">
        <div class="settings-card-head">
          <div>
            <div class="settings-card-title">Profile Information</div>
            <div class="settings-card-sub">Update your personal details and contact info.</div>
          </div>
          <button type="button" class="btn-outline-blue" id="editProfileBtn" onclick="toggleEditProfile(true)">Edit Profile</button>
        </div>

        <!-- View mode -->
        <div id="profileView">
          <div class="grid-2" style="margin-bottom:0.9rem;">
            <div>
              <div class="view-field-label">Full Name</div>
              <div class="view-field-value"><?= htmlspecialchars($p['full_name']) ?></div>
            </div>
            <div>
              <div class="view-field-label">Email Address</div>
              <div class="view-field-value"><?= htmlspecialchars($p['email']) ?></div>
            </div>
          </div>
          <div class="grid-2" style="margin-bottom:0.9rem;">
            <div>
              <div class="view-field-label">Phone Number</div>
              <div class="view-field-value"><?= htmlspecialchars($p['phone_number'] ?: 'Not set') ?></div>
            </div>
            <div>
              <div class="view-field-label">Date of Birth</div>
              <div class="view-field-value"><?= !empty($p['date_of_birth']) ? htmlspecialchars(date('M j, Y', strtotime($p['date_of_birth']))) : 'Not set' ?></div>
            </div>
          </div>
          <div class="grid-2" style="margin-bottom:0.9rem;">
            <div>
              <div class="view-field-label">Sex</div>
              <div class="view-field-value"><?= htmlspecialchars($p['gender'] ?: 'Not set') ?></div>
            </div>
            <div>
              <div class="view-field-label">Age</div>
              <div class="view-field-value"><?= $age !== null ? $age : 'Not set' ?></div>
            </div>
          </div>
          <div>
            <div class="view-field-label">Address</div>
            <div class="view-field-value"><?= htmlspecialchars($p['home_address'] ?: 'Not set') ?></div>
          </div>
        </div>

        <!-- Edit mode -->
        <form method="POST" id="profileEdit">
          <input type="hidden" name="update_profile"/>

          <div class="edit-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            Personal Information
          </div>

          <div class="grid-2" style="margin-bottom:0.75rem;">
            <div>
              <label class="field-label">Full Name</label>
              <input type="text" name="full_name" class="field-input" value="<?= htmlspecialchars($p['full_name']) ?>" required/>
            </div>
            <div>
              <label class="field-label">Date of Birth</label>
              <input type="date" name="date_of_birth" class="field-input" value="<?= !empty($p['date_of_birth']) ? htmlspecialchars(date('Y-m-d', strtotime($p['date_of_birth']))) : '' ?>"/>
            </div>
          </div>
          <div class="grid-2" style="margin-bottom:0.75rem;">
            <div>
              <label class="field-label">Sex</label>
              <?php $genderVal = $p['gender'] ?? ''; ?>
              <select name="gender" class="field-input">
                <option value="" <?= $genderVal === '' ? 'selected' : '' ?>>Select…</option>
                <option value="Female" <?= $genderVal === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Male" <?= $genderVal === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Other" <?= ($genderVal !== '' && !in_array($genderVal, ['Female','Male'])) ? 'selected' : '' ?>>Other / Prefer not to say</option>
              </select>
            </div>
            <div>
              <label class="field-label">Contact Number</label>
              <input type="tel" name="phone_number" class="field-input" value="<?= htmlspecialchars($p['phone_number'] ?? '') ?>"/>
            </div>
          </div>
          <div style="margin-bottom:0.75rem;">
            <label class="field-label">Email</label>
            <input type="email" class="field-input" value="<?= htmlspecialchars($p['email']) ?>" readonly style="background:rgba(36,68,65,0.04);cursor:not-allowed;"/>
          </div>
          <div style="margin-bottom:1rem;">
            <label class="field-label">Address</label>
            <textarea name="home_address" class="field-input" rows="2" style="resize:vertical;font-family:inherit;padding:0.7rem 0.85rem;border:1px solid rgba(63,130,227,0.18);border-radius:12px;background:var(--white);color:var(--text);"><?= htmlspecialchars($p['home_address'] ?? '') ?></textarea>
          </div>

          <div class="edit-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            Emergency Contact
          </div>
          <div class="grid-3" style="margin-bottom:0.5rem;">
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

          <div class="edit-section-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            Account Security
          </div>
          <div class="mini-security-row">
            <div>
              <div class="view-field-label" style="margin-bottom:0.3rem;">Password</div>
              <div class="mini-security-dots">••••••••</div>
            </div>
            <button type="button" class="btn-outline-blue" onclick="openPasswordModal()">Change Password</button>
          </div>

          <div class="edit-form-actions">
            <button type="button" class="btn-cancel" onclick="toggleEditProfile(false)">Cancel</button>
            <button type="submit" class="btn-save-inline">Save Changes</button>
          </div>
        </form>
      </div>

      <!-- Notification Preferences -->
      <div class="card">
        <div class="settings-card-head">
          <div>
            <div class="settings-card-title">Notification Preferences</div>
            <div class="settings-card-sub">Choose what updates you want to receive.</div>
          </div>
        </div>

        <div class="toggle-row">
          <div>
            <div class="toggle-row-title">Appointment Reminders</div>
            <div class="toggle-row-sub">Receive alerts before upcoming visits.</div>
          </div>
          <label class="tc-switch">
            <input type="checkbox" id="notif_appointment_reminders" <?= !empty($p['notif_appointment_reminders']) ? 'checked' : '' ?>
                   onchange="toggleNotifPref('appointment_reminders', this)"/>
            <span class="tc-switch-track"></span>
          </label>
        </div>

        <div class="toggle-row">
          <div>
            <div class="toggle-row-title">Payment Notifications</div>
            <div class="toggle-row-sub">Alerts for new bills and successful payments.</div>
          </div>
          <label class="tc-switch">
            <input type="checkbox" id="notif_payment_notifications" <?= !empty($p['notif_payment_notifications']) ? 'checked' : '' ?>
                   onchange="toggleNotifPref('payment_notifications', this)"/>
            <span class="tc-switch-track"></span>
          </label>
        </div>

        <div class="toggle-row">
          <div>
            <div class="toggle-row-title">Medical Record Updates</div>
            <div class="toggle-row-sub">Get notified when new test results are available.</div>
          </div>
          <label class="tc-switch">
            <input type="checkbox" id="notif_medical_record_updates" <?= !empty($p['notif_medical_record_updates']) ? 'checked' : '' ?>
                   onchange="toggleNotifPref('medical_record_updates', this)"/>
            <span class="tc-switch-track"></span>
          </label>
        </div>
      </div>

      <!-- Legal Policies -->
      <div class="card legal-policy-card">
        <div class="legal-policy-copy">
          <div class="legal-policy-title">View the current legal documents used by TELE-CARE.</div>
          <div class="legal-policy-note">Read the Data Privacy Notice, Terms and Conditions, and Privacy Policy. Latest update: <?= htmlspecialchars($latest_legal_update_label) ?>.</div>
        </div>
        <button type="button" class="legal-policy-btn" onclick="openLegalPolicies()">View Policies</button>
      </div>
    </div>

    <!-- ── Side column ─────────────────────────────────────────────── -->
    <div class="settings-col">

      <!-- Security -->
      <div class="card">
        <div class="settings-card-head" style="margin-bottom:0.85rem;">
          <div>
            <div class="settings-card-title">Security</div>
            <div class="settings-card-sub">Manage your account protection.</div>
          </div>
        </div>

        <button type="button" class="chevron-btn" onclick="openPasswordModal()">
          Change Password
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
        </button>

      </div>

      <!-- Account actions -->
      <div class="card" style="display:flex;flex-direction:column;gap:0.6rem;">
        <a href="auth/logout.php" class="logout-btn">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Log Out
        </a>
        <button type="button" class="btn-outline-red" onclick="openDeactivateModal()">Deactivate Account</button>
      </div>
    </div>
  </div>

</div>

<!-- Change Password Modal -->
<div class="policy-modal-overlay" id="passwordModal">
  <div class="policy-modal-box narrow">
    <div class="policy-modal-head">
      <div>
        <h3>Change Password</h3>
        <div class="policy-modal-subtitle">Choose a new password for your account.</div>
      </div>
      <button type="button" class="policy-modal-close" onclick="closePasswordModal()" aria-label="Close change password modal">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
    <form method="POST" style="padding:1.2rem 1.3rem;display:flex;flex-direction:column;gap:0.85rem;">
      <input type="hidden" name="change_password"/>
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
      <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:0.3rem;">
        <button type="button" class="btn-cancel" onclick="closePasswordModal()">Cancel</button>
        <button type="submit" class="btn-save-inline">Change Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Deactivate Account Modal -->
<div class="policy-modal-overlay" id="deactivateModal">
  <div class="policy-modal-box narrow">
    <div class="policy-modal-head">
      <div>
        <h3>Deactivate Account</h3>
        <div class="policy-modal-subtitle">This will sign you out and disable access to your account.</div>
      </div>
      <button type="button" class="policy-modal-close" onclick="closeDeactivateModal()" aria-label="Close deactivate account modal">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
    <div style="padding:1.2rem 1.3rem;display:flex;flex-direction:column;gap:0.9rem;">
      <div class="deactivate-warning">
        Are you sure you want to deactivate your TELE-CARE account? You won't be able to book or manage appointments until it's reactivated. Contact support if you need to undo this.
      </div>
      <div style="display:flex;justify-content:flex-end;gap:0.75rem;">
        <button type="button" class="btn-cancel" onclick="closeDeactivateModal()">Cancel</button>
        <button type="button" class="btn-outline-red" style="width:auto;" id="confirmDeactivateBtn" onclick="confirmDeactivate()">Yes, Deactivate</button>
      </div>
    </div>
  </div>
</div>

<!-- Legal Policies Modal -->
<div class="policy-modal-overlay" id="legalPoliciesModal">
  <div class="policy-modal-box">
    <div class="policy-modal-head">
      <div>
        <h3>TELE-CARE Legal Policies</h3>
        <div class="policy-modal-subtitle">Published policies below are loaded live from the Super Admin legal policies records.</div>
      </div>
      <button type="button" class="policy-modal-close" onclick="closeLegalPolicies()" aria-label="Close legal policies modal">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </div>
    <div class="policy-modal-tabs">
      <button type="button" class="policy-modal-tab" data-tab="data-privacy-notice" onclick="switchLegalPolicyTab('data-privacy-notice')">Data Privacy Notice</button>
      <button type="button" class="policy-modal-tab" data-tab="terms-and-conditions" onclick="switchLegalPolicyTab('terms-and-conditions')">Terms &amp; Conditions</button>
      <button type="button" class="policy-modal-tab" data-tab="privacy-policy" onclick="switchLegalPolicyTab('privacy-policy')">Privacy Policy</button>
    </div>
    <div class="policy-modal-body" id="legalPoliciesBody" onscroll="updateLegalPolicyScroll()">
      <?php foreach ($legal_policy_slugs as $slug => $label): ?>
        <div class="policy-modal-section" data-section="<?= htmlspecialchars($slug) ?>">
          <div class="policy-section-head">
            <h4><?= htmlspecialchars($label) ?></h4>
            <div class="policy-section-update">
              Last updated: <?= !empty($legal_policy_items[$slug]['updated_at']) ? htmlspecialchars(date('M j, Y g:i A', strtotime($legal_policy_items[$slug]['updated_at']))) : 'Unavailable' ?>
            </div>
          </div>
          <?= legal_policy_content($conn, $slug) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="policy-modal-footer">
      <div class="policy-footer-note">Latest policy update across all documents: <?= htmlspecialchars($latest_legal_update_label) ?></div>
      <button type="button" class="policy-close-btn" onclick="closeLegalPolicies()">Close</button>
    </div>
  </div>
</div>

<script>
function previewAndSubmit(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    const wrap = document.querySelector('.photo-wrap');
    wrap.innerHTML = wrap.innerHTML.replace(
      /<(img|div)[^>]*id="photoPreview"[^>]*>.*?(<\/div>)?/s,
      `<img src="${e.target.result}" id="photoPreview" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid rgba(63,130,227,0.2);"/>`
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

// ── Edit Profile toggle ─────────────────────────────────────────────────
function toggleEditProfile(showEdit) {
  document.getElementById('profileView').style.display   = showEdit ? 'none'  : 'block';
  document.getElementById('profileEdit').style.display    = showEdit ? 'block' : 'none';
  document.getElementById('editProfileBtn').style.display = showEdit ? 'none'  : 'inline-block';
}

// ── Change Password modal ───────────────────────────────────────────────
function openPasswordModal() {
  document.getElementById('passwordModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closePasswordModal() {
  document.getElementById('passwordModal').classList.remove('open');
  document.body.style.overflow = '';
}

// ── Deactivate Account modal + action ───────────────────────────────────
function openDeactivateModal() {
  document.getElementById('deactivateModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDeactivateModal() {
  document.getElementById('deactivateModal').classList.remove('open');
  document.body.style.overflow = '';
}
function confirmDeactivate() {
  const btn = document.getElementById('confirmDeactivateBtn');
  btn.disabled = true;
  btn.textContent = 'Deactivating…';
  fetch('includes/settings_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=deactivate_account'
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      window.location.href = 'auth/logout.php';
    } else {
      btn.disabled = false;
      btn.textContent = 'Yes, Deactivate';
      alert('Something went wrong. Please try again.');
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.textContent = 'Yes, Deactivate';
    alert('Something went wrong. Please try again.');
  });
}

// ── Notification preference + two-factor toggles (AJAX, live) ──────────
function toggleNotifPref(pref, checkbox) {
  const value = checkbox.checked ? '1' : '0';
  checkbox.disabled = true;
  fetch('includes/settings_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=toggle_notification&pref=' + encodeURIComponent(pref) + '&value=' + value
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) {
      checkbox.checked = !checkbox.checked; // revert on failure
    }
  })
  .catch(() => { checkbox.checked = !checkbox.checked; })
  .finally(() => { checkbox.disabled = false; });
}

// ── Legal policies modal ────────────────────────────────────────────────
const legalPolicyReadTabs = {
  'data-privacy-notice': false,
  'terms-and-conditions': false,
  'privacy-policy': false,
};
let currentLegalPolicyTab = 'data-privacy-notice';

function openLegalPolicies() {
  document.getElementById('legalPoliciesModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  switchLegalPolicyTab(currentLegalPolicyTab);
}

function closeLegalPolicies() {
  document.getElementById('legalPoliciesModal').classList.remove('open');
  document.body.style.overflow = '';
}

function switchLegalPolicyTab(tab) {
  currentLegalPolicyTab = tab;
  document.querySelectorAll('.policy-modal-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  document.querySelectorAll('.policy-modal-section').forEach(section => {
    section.classList.toggle('active', section.dataset.section === tab);
  });
  const body = document.getElementById('legalPoliciesBody');
  if (body) body.scrollTop = 0;
  updateLegalPolicyScroll();
}

function updateLegalPolicyScroll() {
  const body = document.getElementById('legalPoliciesBody');
  const currentSection = document.querySelector('.policy-modal-section.active');
  if (!body || !currentSection) return;

  const maxScroll = body.scrollHeight - body.clientHeight;
  const atBottom = maxScroll <= 0 || body.scrollTop >= maxScroll - 4;
  if (atBottom) {
    legalPolicyReadTabs[currentLegalPolicyTab] = true;
  }
}

document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    ['legalPoliciesModal', 'passwordModal', 'deactivateModal'].forEach(function(id) {
      const modal = document.getElementById(id);
      if (modal && modal.classList.contains('open')) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>
</body>
</html>