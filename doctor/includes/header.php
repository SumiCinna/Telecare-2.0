
<!DOCTYPE html>

<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
  <title><?= $page_title ?? 'Doctor — TELE-CARE' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="includes/design-system.css" rel="stylesheet"/>
  <style>
    /* doctor header */
    * { box-sizing:border-box; margin:0; padding:0; }
    body { min-height:100vh; }
    h1,h2,h3 { font-family:'Inter',sans-serif; }

    /* TOP HEADER */
    .top-header {
      background:var(--white); padding:0.9rem 1.2rem;
      display:flex; align-items:center; justify-content:space-between;
      border-bottom:1px solid var(--border);
      position:sticky; top:0; z-index:99;
    }
    .header-left { display:flex; align-items:center; gap:1rem; }
    .hamburger-btn {
      display:flex; align-items:center; justify-content:center;
      background:none; border:none; cursor:pointer;
      color:var(--green); font-size:1.3rem; padding:0.4rem;
      transition:transform 0.2s;
    }
    .hamburger-btn:active { transform:scale(0.95); }
    @media (max-width: 767px) {
      .hamburger-btn { display:none !important; }
    }
    @media (min-width: 768px) {
      .hamburger-btn { display:flex; }
    }
    .header-brand { font-family:'Inter',sans-serif; font-size:1.1rem; font-weight:700; color:var(--green); }
    .header-brand span { color:var(--red); }
    .header-avatar {
      width:38px; height:38px; border-radius:10px;
      background:linear-gradient(135deg,var(--green),var(--green-dark));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:0.88rem; overflow:hidden; text-decoration:none;
    }
    .header-avatar img { width:100%; height:100%; object-fit:cover; }
    .header-center { font-size:0.95rem; font-weight:700; }
    .header-profile { position:relative; }
    .header-profile-trigger { align-items:center; background:none; border:0; color:var(--green); cursor:pointer; display:flex; gap:.55rem; padding:0; text-align:left; }
    .header-profile-copy { display:flex; flex-direction:column; line-height:1.2; }
    .header-profile-name { font-size:.72rem; font-weight:700; }
    .header-profile-role { color:var(--muted); font-size:.62rem; margin-top:.15rem; }
    .header-profile-chevron { height:14px; width:14px; }
    .profile-menu { background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 12px 30px rgba(19,36,59,.14); display:none; min-width:180px; padding:.4rem; position:absolute; right:0; top:calc(100% + .7rem); z-index:120; }
    .profile-menu.open { display:block; }
    .profile-menu a { align-items:center; border-radius:7px; color:var(--green); display:flex; font-size:.78rem; gap:.55rem; padding:.65rem .7rem; text-decoration:none; }
    .profile-menu a:hover { background:#f1f4ff; }
    .profile-menu a:last-child { color:var(--red); }
    .profile-menu svg { height:16px; width:16px; }
    @media (max-width:767px) { .header-profile-copy,.header-profile-chevron { display:none; } .header-profile-trigger { gap:0; } }

    /* PAGE - Responsive layout */
    @media (min-width: 768px) {
      .page { padding:1.5rem; max-width:calc(100% - 240px); margin-left:240px; }
    }
    @media (max-width: 767px) {
      .page { padding:1rem; max-width:100%; margin:0 auto; padding-bottom:100px; }
    }

    /* CARDS */
    .card {
      background:var(--white); border-radius:16px;
      padding:1.2rem; margin-bottom:0.9rem;
      border:1px solid var(--border);
      box-shadow:0 2px 8px rgba(0,0,0,0.04);
    }

    /* BADGES */
    .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:50px; font-size:0.68rem; font-weight:700; letter-spacing:0.04em; }
    .badge-green  { background:rgba(34,197,94,0.1);  color:#16a34a; }
    .badge-red    { background:rgba(195,54,67,0.1);  color:var(--red); }
    .badge-orange { background:rgba(245,158,11,0.1); color:#d97706; }
    .badge-blue   { background:rgba(63,130,227,0.1); color:var(--blue); }
    .badge-gray   { background:rgba(0,0,0,0.06);     color:#888; }

    /* PATIENT AVATAR */
    .pat-avatar { width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg,#e8f4f3,#c8e6e3); color:var(--green); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.85rem; flex-shrink:0; overflow:hidden; }
    .pat-avatar img { width:100%; height:100%; object-fit:cover; }

    /* EMPTY STATE */
    .empty-state { text-align:center; padding:2rem; color:var(--muted); font-size:0.85rem; }
    .empty-state svg { margin:0 auto 0.5rem; display:block; opacity:0.3; }

    /* SECTION LABEL */
    .section-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--muted); margin-bottom:0.8rem; }

    /* ALERT */
    .alert-error { background:rgba(195,54,67,0.08); border:1px solid rgba(195,54,67,0.2); color:var(--red); border-radius:12px; padding:0.75rem 1rem; font-size:0.86rem; margin-bottom:1rem; }
    .alert-success { background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.2); color:#16a34a; border-radius:12px; padding:0.75rem 1rem; font-size:0.86rem; margin-bottom:1rem; }

    /* FORM */
    .field-label { display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--muted); margin-bottom:0.35rem; }
    .field-input { width:100%; padding:0.75rem 0.9rem; border:1.5px solid var(--border); border-radius:12px; font-family:inherit; font-size:0.9rem; color:var(--green); outline:none; transition:border-color 0.2s; background:var(--white); }
    .field-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(63,130,227,0.1); }
    textarea.field-input { resize:vertical; min-height:80px; }
    select.field-input { cursor:pointer; }
    .form-field { margin-bottom:0.85rem; }
    .btn-submit { width:100%; padding:0.85rem; border-radius:50px; background:var(--green); color:#fff; font-weight:700; font-size:0.93rem; border:none; cursor:pointer; transition:all 0.25s; font-family:inherit; }
    .btn-submit:hover { background:var(--green-dark); }
    .btn-red-submit { background:var(--red); }
    .btn-red-submit:hover { background:#a82d38; }
  </style>
</head>
<body>

<div class="top-header">
  <div class="header-left">
    <button class="hamburger-btn" id="sidebarToggle" title="Toggle sidebar">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    <div class="header-brand">TELE<span>-</span>CARE</div>
  </div>
  <div class="header-center"><?= $page_title_short ?? '' ?></div>
  <div class="header-profile">
    <button class="header-profile-trigger" id="profileMenuToggle" type="button" aria-expanded="false" aria-controls="profileMenu">
      <span class="header-avatar">
    <?php if (!empty($doc['profile_photo'])): ?>
      <img src="../../<?= htmlspecialchars($doc['profile_photo']) ?>" alt="photo"/>
    <?php else: ?>
      <?= strtoupper(substr($doc['full_name'], 0, 2)) ?>
    <?php endif; ?>
      </span>
      <span class="header-profile-copy"><span class="header-profile-name">Dr. <?= htmlspecialchars($doc['full_name']) ?></span><span class="header-profile-role"><?= htmlspecialchars($doc['specialty'] ?? 'Doctor') ?></span></span>
      <svg class="header-profile-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="profile-menu" id="profileMenu">
      <a href="profile.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19a6 6 0 0 0-6 0m3-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 1a8 8 0 0 1-16 0 8 8 0 0 1 16 0Z"/></svg>Profile Settings</a>
      <a href="logout.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4m8-10V5a2 2 0 0 0-2-2H9"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 19v-1a2 2 0 0 0-2 2"/></svg>Sign Out</a>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const toggle = document.getElementById('profileMenuToggle');
  const menu = document.getElementById('profileMenu');
  if (!toggle || !menu) return;
  toggle.addEventListener('click', function(event) {
    event.stopPropagation();
    const open = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('click', function(event) {
    if (!menu.contains(event.target) && !toggle.contains(event.target)) {
      menu.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
});
</script>


