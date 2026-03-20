<?php
require_once 'includes/auth.php';
$page_title = 'Inventory — TELE-CARE Staff';
$active_nav = 'inventory';
require_once 'includes/head.php';
?>
<body>
<?php require_once 'includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.73rem;color:var(--muted);font-weight:600;">Staff Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Inventory Monitoring</div>
    </div>
  </div>

  <div class="page-content">
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;text-align:center;">
      <div style="width:80px;height:80px;border-radius:24px;background:rgba(63,130,227,0.08);display:flex;align-items:center;justify-content:center;margin-bottom:1.5rem;">
        <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="var(--blue)" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
        </svg>
      </div>
      <h2 style="font-size:1.6rem;margin-bottom:0.5rem;">Inventory Module</h2>
      <p style="color:var(--muted);font-size:0.9rem;max-width:380px;line-height:1.7;margin-bottom:1.5rem;">
        This module will allow you to monitor stock levels, track supplies, and report low inventory.
        It is currently under development and will be available soon.
      </p>
      <span style="background:rgba(63,130,227,0.1);color:var(--blue);border-radius:50px;padding:0.4rem 1.2rem;font-size:0.82rem;font-weight:700;letter-spacing:0.04em;">🚧 Coming Soon</span>
    </div>
  </div>
</div>
</body>
</html>