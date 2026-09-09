<?php
// includes/nav.php
// includes/nav.php
// $active_nav must be set before including: 'home' | 'visits' | 'meds' | 'billing' | 'profile'
$active_nav = $active_nav ?? 'home';

$nav_pages = [
    'home'    => ['href' => 'router.php?page=dashboard', 'label' => 'Dashboard',
            'icon' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>'],
    'visits'  => ['href' => 'router.php?page=visits',    'label' => 'Appointments',
            'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>'],
    'meds'    => ['href' => 'router.php?page=meds',      'label' => 'Records',
            'icon' => '<path d="M6 3h9l3 3v15H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M14 3v4h4M8 11h6M8 15h6M8 19h4"/>'],
    'billing' => ['href' => 'router.php?page=visits',    'label' => 'Billing',
            'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>'],
    'profile' => ['href' => 'router.php?page=profile',   'label' => 'Settings',
            'icon' => '<path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.64 5.64l1.42 1.42M16.94 16.94l1.42 1.42M5.64 18.36l1.42-1.42M16.94 7.06l1.42-1.42"/><circle cx="12" cy="12" r="4"/>'],
];
?>
<style>
:root{
  --tc-red:#B31118; --tc-red-dark:#8a000b; --tc-ink:#151c27; --tc-teal:#006a61; --tc-teal-light:#0D9488;
  --tc-line:rgba(21,28,39,0.08); --tc-muted:rgba(21,28,39,0.5);
}
.sidebar{
  position:fixed; top:0; left:0; bottom:0; width:190px; z-index:150;
  background:#f1f3fc; border-right:1px solid #dce1ef;
  display:flex; flex-direction:column; padding:1.2rem .85rem;
  font-family:'Inter',sans-serif;
}
.sidebar-brand{ display:flex; align-items:center; gap:.65rem; padding:0 .3rem; margin-bottom:2rem; }
.sidebar-brand-icon{
  width:32px; height:32px; border-radius:6px; background:var(--tc-red); color:#fff;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.sidebar-brand-icon svg{ width:17px; height:17px; }
.sidebar-brand-name{ font-weight:800; font-size:0.86rem; color:var(--tc-ink); line-height:1.15; letter-spacing:0; }
.sidebar-brand-sub{ font-size:0.6rem; color:var(--tc-muted); margin-top:0.1rem; }
.sidebar-nav{ display:flex; flex-direction:column; gap:.3rem; flex:1; }
.sidebar-footer{ border-top:1px solid var(--tc-line); padding-top:0.6rem; margin-top:0.6rem; }
.sidebar-logout{
  display:flex; align-items:center; gap:.55rem;
  padding:.65rem .7rem; border-radius:7px;
  color:var(--tc-muted); font-size:.78rem; font-weight:600;
  text-decoration:none; transition:all 0.2s; width:100%; background:none; border:none;
  cursor:pointer; font-family:'Inter',sans-serif;
}
.sidebar-logout:hover{ background:var(--tc-red-tint); color:var(--tc-red); }
.sidebar-logout .nav-icon{ width:19px; height:19px; flex-shrink:0; display:flex; }
.sidebar-logout .nav-icon svg{ width:100%; height:100%; stroke:currentColor; }
.sidebar-link{
  display:flex; align-items:center; gap:.55rem;
  padding:.65rem .7rem; border-radius:7px;
  color:var(--tc-muted); font-size:.78rem; font-weight:600;
  text-decoration:none; transition:all 0.2s;
}
.sidebar-link:hover{ background:rgba(21,28,39,0.05); color:var(--tc-ink); }
.sidebar-link.active{ background:var(--tc-red); color:#fff; box-shadow:0 3px 9px rgba(179,17,24,0.22); }
.sidebar-link .nav-icon{ width:17px; height:17px; flex-shrink:0; display:flex; }
.sidebar-link .nav-icon svg{ width:100%; height:100%; stroke:currentColor; }

body{ padding-left:190px; background:#f7f8fb; }

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
</aside>



