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
  <style>
    @keyframes carebotPulse {
      0%   { transform:scale(1);   opacity:0.5; }
      100% { transform:scale(1.9); opacity:0; }
    }
    .carebot-btn       { transition:transform 0.2s, box-shadow 0.2s; }
    .carebot-btn:hover { transform:scale(1.1) !important; box-shadow:0 10px 28px rgba(63,130,227,0.55) !important; }
  </style>
</head>
<body>

<!-- ── Top Header ── -->
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

<!-- ── CareBot Floating Widget ── -->
<div id="carebotWidget" style="
  position:fixed;
  bottom:90px;
  right:16px;
  z-index:999;
  display:flex;
  flex-direction:column;
  align-items:flex-end;
  gap:10px;
">

  <!-- Chat Popup Panel -->
  <div id="carebotPanel" style="
    width:340px;
    height:480px;
    background:#fff;
    border-radius:20px;
    box-shadow:0 16px 50px rgba(0,0,0,0.18);
    overflow:hidden;
    display:none;
    flex-direction:column;
    border:1px solid rgba(63,130,227,0.15);
    animation:carebotSlideUp 0.25s ease;
  ">
    <!-- Panel Header -->
    <div style="background:linear-gradient(135deg,#244441,#1a3330);padding:0.9rem 1.1rem;display:flex;align-items:center;gap:0.7rem;flex-shrink:0;">
      <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#3F82E3,#2563C4);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">🤖</div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:0.88rem;color:#fff;">CareBot</div>
        <div style="font-size:0.68rem;color:rgba(255,255,255,0.5);">AI Health Assistant</div>
      </div>
      <div style="display:flex;align-items:center;gap:0.35rem;margin-right:0.5rem;">
        <div style="width:6px;height:6px;border-radius:50%;background:#22c55e;"></div>
        <span style="font-size:0.65rem;color:#22c55e;font-weight:700;">Online</span>
      </div>
      <!-- Close button -->
      <button onclick="toggleCarebot()" style="background:rgba(255,255,255,0.1);border:none;border-radius:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;flex-shrink:0;">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <!-- Chatbase iframe -->
    <iframe
      src="https://www.chatbase.co/chatbot-iframe/HkPkNj6UCtO6aEae6tzHK"
      width="100%"
      style="flex:1;border:none;display:block;"
      frameborder="0">
    </iframe>
  </div>

  <!-- Floating Toggle Button -->
  <button onclick="toggleCarebot()" class="carebot-btn" style="
    height:48px;
    border-radius:50px;
    background:linear-gradient(135deg,#3F82E3,#2563C4);
    border:none;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:0.5rem;
    padding:0 1.1rem 0 0.8rem;
    box-shadow:0 6px 20px rgba(63,130,227,0.45);
    position:relative;
    flex-shrink:0;
  ">
    <div style="position:absolute;inset:0;border-radius:50px;border:2px solid rgba(63,130,227,0.5);animation:carebotPulse 2s ease-out infinite;pointer-events:none;"></div>
    <!-- Bot icon -->
    <div id="cbIconOpen" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">🤖</div>
    <div id="cbIconClose" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;display:none;">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </div>
    <!-- Label -->
    <span id="cbLabel" style="color:#fff;font-weight:700;font-size:0.82rem;white-space:nowrap;letter-spacing:0.01em;">Chat with CareBot</span>
  </button>
</div>

<style>
  @keyframes carebotPulse {
    0%   { transform:scale(1);   opacity:0.5; }
    100% { transform:scale(1.9); opacity:0; }
  }
  @keyframes carebotSlideUp {
    from { opacity:0; transform:translateY(16px); }
    to   { opacity:1; transform:translateY(0); }
  }
  .carebot-btn { transition:transform 0.2s,box-shadow 0.2s; }
  .carebot-btn:hover { transform:scale(1.1); box-shadow:0 10px 28px rgba(63,130,227,0.55) !important; }
</style>

<script>
  function toggleCarebot() {
    const panel     = document.getElementById('carebotPanel');
    const iconOpen  = document.getElementById('cbIconOpen');
    const iconClose = document.getElementById('cbIconClose');
    const label     = document.getElementById('cbLabel');
    const isOpen    = panel.style.display === 'flex';
    panel.style.display     = isOpen ? 'none'  : 'flex';
    iconOpen.style.display  = isOpen ? 'flex'  : 'none';
    iconClose.style.display = isOpen ? 'none'  : 'flex';
    label.textContent       = isOpen ? 'Chat with CareBot' : 'Close CareBot';
  }
</script>