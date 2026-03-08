<?php
// includes/header.php
// $page_title must be set before including this.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title ?? 'TELE-CARE') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="includes/styles.css"/>
</head>
<body>

<div class="top-header">
  <div style="display:flex;align-items:center;gap:0.8rem;">
    <?php if (!empty($p['profile_photo'])): ?>
      <img src="<?= htmlspecialchars($p['profile_photo']) ?>"
           style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid rgba(63,130,227,0.2);"/>
    <?php else: ?>
      <div class="avatar-circle"><?= $initials ?></div>
    <?php endif; ?>
    <div>
      <div style="font-weight:700;font-size:0.95rem;color:var(--text);"><?= htmlspecialchars($p['full_name']) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($p['email']) ?></div>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:0.5rem;">
    <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;"></span>
    <span style="font-size:0.75rem;color:#22c55e;font-weight:700;">Active</span>
  </div>
</div>