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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="includes/styles.css"/>
  <style>
    :root{
      --tc-red:#B31118; --tc-red-dark:#8a000b;
      --tc-teal:#006a61; --tc-teal-light:#0D9488;
      --tc-ink:#151c27; --tc-muted:rgba(21,28,39,0.55);
      --tc-line:rgba(21,28,39,0.08);
      --tc-red-tint:#FEF2F2; --tc-teal-tint:#ECFDF5;
    }
    body{ font-family:'Inter',sans-serif; }

    /* ── SITE TOPBAR (sidebar-aware, shows on every page using header.php) ── */
    .site-topbar{
      position:sticky; top:0; z-index:120;
      background:rgba(247,248,251,0.92);
      backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
      border-bottom:1px solid var(--tc-line);
      display:flex; align-items:center; justify-content:space-between;
      gap:1rem; padding:1.1rem 2rem;
    }
    .site-topbar-actions{ display:flex; align-items:center; gap:0.6rem; flex-shrink:0; }
    .site-topbar-icon{
      width:38px; height:38px; border-radius:10px; border:1px solid var(--tc-line);
      background:#fff; display:flex; align-items:center; justify-content:center;
      color:var(--tc-ink); cursor:pointer; transition:all 0.2s; flex-shrink:0;
    }
    .site-topbar-icon:hover{ background:rgba(21,28,39,0.05); }
    .site-topbar-icon svg{ width:17px; height:17px; }
    .site-topbar-identity{ display:flex; align-items:center; gap:0.65rem; flex-shrink:0; }
    .site-topbar .avatar-circle{
      width:38px; height:38px; border-radius:10px;
      background:linear-gradient(135deg,var(--tc-teal),var(--tc-teal-light));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:800; font-size:0.85rem; flex-shrink:0; overflow:hidden;
    }
    .site-topbar-photo{
      width:38px; height:38px; border-radius:10px; object-fit:cover; flex-shrink:0;
      border:1px solid var(--tc-line);
    }
    .site-topbar-name{ font-weight:700; font-size:0.85rem; color:var(--tc-ink); line-height:1.2; }
    .site-topbar-status{ display:flex; align-items:center; gap:0.35rem; font-size:0.7rem; color:var(--tc-teal); font-weight:700; margin-top:0.1rem; }
    .site-topbar-status-dot{ width:6px; height:6px; border-radius:50%; background:var(--tc-teal-light); flex-shrink:0; }
    .site-topbar-identity-text{ display:none; }
    @media (min-width:1080px){ .site-topbar-identity-text{ display:block; } }

    @media (max-width:900px){
      .site-topbar{ padding:1rem 1.1rem; }
    }

    /* ── CAREBOT WIDGET ── */
    .carebot-widget{
      position:fixed; bottom:24px; right:24px; z-index:999;
      display:flex; flex-direction:column; align-items:flex-end; gap:10px;
    }
    @media (max-width:900px){
      .carebot-widget{ bottom:90px; right:16px; }
    }
    @keyframes carebotPulse {
      0%   { transform:scale(1);   opacity:0.5; }
      100% { transform:scale(1.9); opacity:0; }
    }
    @keyframes carebotSlideUp {
      from { opacity:0; transform:translateY(16px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .carebot-btn       { transition:transform 0.2s, box-shadow 0.2s; }
    .carebot-btn:hover { transform:scale(1.1) !important; box-shadow:0 10px 28px rgba(179,17,24,0.4) !important; }
  </style>
</head>
<body>

<!-- ── Site Topbar ── -->
<div class="site-topbar" style="justify-content:flex-end;">
  <div class="site-topbar-actions">
    <button type="button" class="site-topbar-icon" aria-label="Notifications">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
    </button>
    <button type="button" class="site-topbar-icon" aria-label="Help">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 015 .5c0 1.5-2.5 1.5-2.5 3.5"/><path d="M12 17h.01"/></svg>
    </button>
    <div class="site-topbar-identity" title="<?= htmlspecialchars($p['email']) ?>">
      <?php if (!empty($p['profile_photo'])): ?>
        <img src="<?= htmlspecialchars($p['profile_photo']) ?>" class="site-topbar-photo" alt=""/>
      <?php else: ?>
        <div class="avatar-circle"><?= $initials ?></div>
      <?php endif; ?>
      <div class="site-topbar-identity-text">
        <div class="site-topbar-name"><?= htmlspecialchars($p['full_name']) ?></div>
        <div class="site-topbar-status">
          <span class="site-topbar-status-dot"></span>
          Active
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── CareBot Floating Widget ── -->
<div id="carebotWidget" class="carebot-widget">

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
    border:1px solid rgba(0,106,97,0.15);
    animation:carebotSlideUp 0.25s ease;
  ">
    <!-- Panel Header -->
    <div style="background:linear-gradient(135deg,#151c27,#0a0e14);padding:0.9rem 1.1rem;display:flex;align-items:center;gap:0.7rem;flex-shrink:0;">
      <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#006a61,#0D9488);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M9 4h6"/><circle cx="9" cy="14" r="1.2" fill="#fff" stroke="none"/><circle cx="15" cy="14" r="1.2" fill="#fff" stroke="none"/><path d="M9 18h6"/></svg>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:0.88rem;color:#fff;">CareBot</div>
        <div style="font-size:0.68rem;color:rgba(255,255,255,0.5);">AI Health Assistant</div>
      </div>
      <div style="display:flex;align-items:center;gap:0.35rem;margin-right:0.5rem;">
        <div style="width:6px;height:6px;border-radius:50%;background:#0D9488;"></div>
        <span style="font-size:0.65rem;color:#0D9488;font-weight:700;">Online</span>
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
    background:linear-gradient(135deg,#B31118,#8a000b);
    border:none;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:0.5rem;
    padding:0 1.1rem 0 0.8rem;
    box-shadow:0 6px 20px rgba(179,17,24,0.4);
    position:relative;
    flex-shrink:0;
  ">
    <div style="position:absolute;inset:0;border-radius:50px;border:2px solid rgba(179,17,24,0.5);animation:carebotPulse 2s ease-out infinite;pointer-events:none;"></div>
    <!-- Bot icon -->
    <div id="cbIconOpen" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M9 4h6"/><circle cx="9" cy="14" r="1.2" fill="#fff" stroke="none"/><circle cx="15" cy="14" r="1.2" fill="#fff" stroke="none"/><path d="M9 18h6"/></svg>
    </div>
    <div id="cbIconClose" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;display:none;">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </div>
    <!-- Label -->
    <span id="cbLabel" style="color:#fff;font-weight:700;font-size:0.82rem;white-space:nowrap;letter-spacing:0.01em;">Chat with CareBot</span>
  </button>
</div>

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