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
  <link rel="stylesheet" href="style.css"/>
  <link rel="icon" type="image/png" href="/telecarelogo.png">
<link rel="shortcut icon" type="image/png" href="/telecarelogo.png">
</head>
<body>

<?php
  $global_toast = $toast ?? null;
  $global_toast_error = $toast_error ?? null;
  if ($global_toast_error):
?>
<div class="toast error">
  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
  <?= htmlspecialchars($global_toast_error) ?>
</div>
<?php elseif ($global_toast): ?>
<div class="toast">
  <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
  <?= htmlspecialchars($global_toast) ?>
</div>
<?php endif ?>

<?php require_once 'sidebar.php'; ?>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="sb-toggle" onclick="toggleStaffSidebar()" aria-label="Toggle sidebar" title="Toggle sidebar">
        <svg class="icon-expanded" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3.5" y="4.5" width="17" height="15" rx="3.5"/>
          <path d="M9.5 4.5v15" stroke-linecap="round"/>
          <path d="M6.7 10.2l-1.7 1.8 1.7 1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <svg class="icon-collapsed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3.5" y="4.5" width="17" height="15" rx="3.5"/>
          <path d="M9.5 4.5v15" stroke-linecap="round"/>
          <path d="M6.3 10.2l1.7 1.8-1.7 1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <div>
      <div style="font-size:.73rem;color:var(--muted);font-weight:600;">TELE-CARE Staff</div>
      <div style="font-size:.95rem;font-weight:700;"><?= ucfirst($active_page) ?></div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="notif-wrap">
        <button type="button" class="notif-bell" id="notif-bell-btn" title="Notifications" aria-haspopup="true" aria-expanded="false">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span class="notif-badge" id="notif-badge" style="<?= $notif_unread_count > 0 ? '' : 'display:none' ?>">
            <?= $notif_unread_count > 99 ? '99+' : (int)$notif_unread_count ?>
          </span>
        </button>
        <div class="notif-panel" id="notif-panel">
          <div class="notif-panel-head">
            <span>Notifications</span>
            <button type="button" id="notif-mark-all" class="notif-mark-all">Mark all read</button>
          </div>
          <div class="notif-list" id="notif-list">
            <?php if (empty($notif_items)): ?>
              <div class="notif-empty">No new notifications right now.</div>
            <?php else: foreach ($notif_items as $n): ?>
              <a href="<?= htmlspecialchars($n['link'] ?: '#') ?>" class="notif-item" data-id="<?= (int)$n['id'] ?>">
                <span class="notif-item-dot"></span>
                <span class="notif-item-body">
                  <span class="notif-item-title"><?= htmlspecialchars($n['title']) ?></span>
                  <span class="notif-item-msg"><?= htmlspecialchars($n['message']) ?></span>
                  <span class="notif-item-time"><?= htmlspecialchars(timeAgo($n['created_at'])) ?></span>
                </span>
              </a>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
      <div class="profile-menu">
        <span class="profile-name"><?= htmlspecialchars($staff_name ?? 'Staff User') ?></span>
        <div class="profile-avatar"><?= strtoupper(substr($staff_name ?? 'S', 0, 1)) ?></div>
      </div>
    </div>
  </div>
  <div class="page-wrap">