<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
  <title><?= $page_title ?? 'Doctor — TELE-CARE' ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --green:#244441; --green-dark:#1a3330;
      --red:#C33643;
      --blue:#3F82E3; --blue-dark:#2563C4;
      --bg:#F0F4F8; --white:#FFFFFF;
      --muted:#9ab0ae;
      --border:rgba(36,68,65,0.1);
    }
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--green); min-height:100vh; }
    h1,h2,h3 { font-family:'Playfair Display',serif; }

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
    .header-brand { font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:900; color:var(--green); }
    .header-brand span { color:var(--red); }
    .header-avatar {
      width:38px; height:38px; border-radius:10px;
      background:linear-gradient(135deg,var(--green),var(--green-dark));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:0.88rem; overflow:hidden; text-decoration:none;
    }
    .header-avatar img { width:100%; height:100%; object-fit:cover; }
    .header-center { font-size:0.95rem; font-weight:700; }

    /* PAGE - Responsive layout */
    @media (min-width: 768px) {
      .page { padding:1.5rem; max-width:calc(100% - 240px); margin-left:240px; }
      .sidebar-overlay { display:none !important; }
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

    /* APPOINTMENT ITEM */
    .appt-item { display:flex; align-items:center; gap:0.8rem; padding:0.7rem 0; border-bottom:1px solid var(--border); }
    .appt-item:last-child { border-bottom:none; padding-bottom:0; }
    .appt-date-box { background:rgba(63,130,227,0.08); border-radius:10px; padding:0.4rem 0.6rem; text-align:center; min-width:44px; }
    .appt-date-box .day { font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:900; color:var(--blue); line-height:1; }
    .appt-date-box .mon { font-size:0.6rem; font-weight:700; text-transform:uppercase; color:var(--muted); }

    /* PATIENT ITEM */
    .patient-item { display:flex; align-items:center; gap:0.9rem; padding:0.7rem 0; border-bottom:1px solid var(--border); }
    .patient-item:last-child { border-bottom:none; }
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
    .field-input { width:100%; padding:0.75rem 0.9rem; border:1.5px solid var(--border); border-radius:12px; font-family:'DM Sans',sans-serif; font-size:0.9rem; color:var(--green); outline:none; transition:border-color 0.2s; background:var(--white); }
    .field-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(63,130,227,0.1); }
    textarea.field-input { resize:vertical; min-height:80px; }
    select.field-input { cursor:pointer; }
    .form-field { margin-bottom:0.85rem; }
    .btn-submit { width:100%; padding:0.85rem; border-radius:50px; background:var(--green); color:#fff; font-weight:700; font-size:0.93rem; border:none; cursor:pointer; transition:all 0.25s; font-family:'DM Sans',sans-serif; }
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
  <a href="profile.php" class="header-avatar">
    <?php if (!empty($doc['profile_photo'])): ?>
      <img src="../../<?= htmlspecialchars($doc['profile_photo']) ?>" alt="photo"/>
    <?php else: ?>
      <?= strtoupper(substr($doc['full_name'], 0, 2)) ?>
    <?php endif; ?>
  </a>
</div>