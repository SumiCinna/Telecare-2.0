<?php
// includes/nav.php
// $active_nav must be set before including: 'home' | 'visits' | 'meds' | 'profile'
$active_nav = $active_nav ?? 'home';

$nav_pages = [
    'home'    => ['href' => 'dashboard.php', 'label' => 'Home',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
    'visits'  => ['href' => 'visits.php',    'label' => 'Visits',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
    'meds'    => ['href' => 'meds.php',      'label' => 'Meds',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>'],
    'profile' => ['href' => 'profile.php',   'label' => 'Profile',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
];
?>
<style>
:root{
  --tc-red:#B31118; --tc-red-dark:#8a000b; --tc-ink:#151c27; --tc-teal:#006a61; --tc-teal-light:#0D9488;
  --tc-line:rgba(21,28,39,0.08); --tc-muted:rgba(21,28,39,0.5);
}
.sidebar{
  position:fixed; top:0; left:0; bottom:0; width:236px; z-index:150;
  background:#fff; border-right:1px solid var(--tc-line);
  display:flex; flex-direction:column; padding:1.5rem 1rem;
  font-family:'Inter',sans-serif;
}
.sidebar-brand{ display:flex; align-items:center; gap:0.7rem; padding:0 .4rem; margin-bottom:2.2rem; }
.sidebar-brand-icon{
  width:36px; height:36px; border-radius:10px; background:var(--tc-red); color:#fff;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.sidebar-brand-icon svg{ width:18px; height:18px; }
.sidebar-brand-name{ font-weight:800; font-size:0.98rem; color:var(--tc-ink); line-height:1.15; letter-spacing:0.01em; }
.sidebar-brand-sub{ font-size:0.66rem; color:var(--tc-muted); margin-top:0.1rem; }
.sidebar-nav{ display:flex; flex-direction:column; gap:0.3rem; flex:1; }
.sidebar-footer{ border-top:1px solid var(--tc-line); padding-top:0.6rem; margin-top:0.6rem; }
.sidebar-logout{
  display:flex; align-items:center; gap:0.75rem;
  padding:0.65rem 0.85rem; border-radius:10px;
  color:var(--tc-muted); font-size:0.87rem; font-weight:600;
  text-decoration:none; transition:all 0.2s; width:100%; background:none; border:none;
  cursor:pointer; font-family:'Inter',sans-serif;
}
.sidebar-logout:hover{ background:var(--tc-red-tint); color:var(--tc-red); }
.sidebar-logout .nav-icon{ width:19px; height:19px; flex-shrink:0; display:flex; }
.sidebar-logout .nav-icon svg{ width:100%; height:100%; stroke:currentColor; }
.sidebar-link{
  display:flex; align-items:center; gap:0.75rem;
  padding:0.65rem 0.85rem; border-radius:10px;
  color:var(--tc-muted); font-size:0.87rem; font-weight:600;
  text-decoration:none; transition:all 0.2s;
}
.sidebar-link:hover{ background:rgba(21,28,39,0.05); color:var(--tc-ink); }
.sidebar-link.active{ background:var(--tc-red); color:#fff; box-shadow:0 4px 14px rgba(179,17,24,0.28); }
.sidebar-link .nav-icon{ width:19px; height:19px; flex-shrink:0; display:flex; }
.sidebar-link .nav-icon svg{ width:100%; height:100%; stroke:currentColor; }

body{ padding-left:236px; background:#f7f8fb; }

@media (max-width:900px){
  body{ padding-left:0; padding-bottom:72px; }
  .sidebar{
    top:auto; bottom:0; left:0; right:0; width:100%; height:auto;
    flex-direction:row; align-items:center; justify-content:space-around;
    padding:0.5rem 0.4rem; border-right:none; border-top:1px solid var(--tc-line);
  }
  .sidebar-brand{ display:none; }
  .sidebar-nav{ flex-direction:row; width:auto; flex:1; justify-content:space-around; gap:0; }
  .sidebar-link{ flex-direction:column; gap:0.2rem; padding:0.4rem 0.6rem; font-size:0.63rem; border-radius:12px; }
  .sidebar-link.active{ box-shadow:none; }
  .sidebar-footer{ border-top:none; padding-top:0; margin-top:0; flex-shrink:0; }
  .sidebar-logout{ flex-direction:column; gap:0.2rem; padding:0.4rem 0.6rem; font-size:0.63rem; }
}
</style>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
    </div>
    <div>
      <div class="sidebar-brand-name">TELE-CARE</div>
      <div class="sidebar-brand-sub">Patient Portal</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav_pages as $key => $item): ?>
    <a href="<?= $item['href'] ?>" class="sidebar-link <?= $active_nav === $key ? 'active' : '' ?>" data-tab="<?= $key ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
          <?= $item['icon'] ?>
        </svg>
      </span>
      <?= $item['label'] ?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="sidebar-logout">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
      </span>
      Log Out
    </a>
  </div>
</aside>