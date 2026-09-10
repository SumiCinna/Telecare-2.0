<?php
// staff/includes/header.php
// staff/includes/header.php mga sidebar
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= ucfirst($active_page) ?> — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="includes/style.css"/>
</head>
<body>

<?php
  $global_toast = $toast ?? null;
  $global_toast_error = $toast_error ?? null;
  if ($global_toast_error):
?>
<div class="toast error">✕ <?= htmlspecialchars($global_toast_error) ?></div>
<?php elseif ($global_toast): ?>
<div class="toast">✓ <?= htmlspecialchars($global_toast) ?></div>
<?php endif ?>

<?php require_once 'sidebar.php'; ?>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="sb-toggle" onclick="toggleStaffSidebar()" aria-label="Toggle sidebar" title="Toggle sidebar">
        <svg viewBox="0 0 24 24">
          <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
      </button>
      <div>
      <div style="font-size:.73rem;color:var(--muted);font-weight:600;">TELE-CARE Staff</div>
      <div style="font-size:.95rem;font-weight:700;"><?= ucfirst($active_page) ?></div>
      </div>
    </div>
    <div class="topbar-right">
      <button type="button" class="notif-bell" title="Notifications">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span class="notif-badge">2</span>
      </button>
      <div class="profile-menu">
        <span class="profile-name"><?= htmlspecialchars($staff_name ?? 'Staff User') ?></span>
        <div class="profile-avatar"><?= strtoupper(substr($staff_name ?? 'S', 0, 1)) ?></div>
      </div>
    </div>
  </div>
  <div class="page-wrap">