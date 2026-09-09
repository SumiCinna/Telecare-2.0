<?php
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
            'icon' => '<path d="M12 8a4 4 0 100 8 4 4 0 000-8z"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09A1.65 1.65 0 0015 4.6a1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/>'],
];
?>
<style>
:root{
  --tc-red:#B31118; --tc-red-dark:#8a000b; --tc-ink:#151c27; --tc-teal:#006a61; --tc-teal-light:#0D9488;
  --tc-line:rgba(21,28,39,0.08); --tc-muted:rgba(21,28,39,0.5);
  --tc-sidebar-w:240px; --tc-sidebar-w-collapsed:72px;
}
.sidebar{
  position:fixed; top:0; left:0; bottom:0; width:var(--tc-sidebar-w); z-index:150;
  background:#f1f3fc; border-right:1px solid #dce1ef;
  display:flex; flex-direction:column; padding:1.2rem .85rem;
  font-family:'Inter',sans-serif;
  transition:width 0.2s ease;
}
.sidebar.collapsed{ width:var(--tc-sidebar-w-collapsed); padding:1.2rem .55rem; }

.sidebar-brand{ display:flex; align-items:center; gap:.65rem; padding:0 .3rem; margin-bottom:2rem; }
.sidebar-brand-icon{
  width:32px; height:32px; border-radius:6px; background:var(--tc-red); color:#fff;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.sidebar-brand-icon svg{ width:17px; height:17px; }
.sidebar-brand-text{ overflow:hidden; white-space:nowrap; }
.sidebar-brand-name{ font-weight:800; font-size:0.86rem; color:var(--tc-ink); line-height:1.15; letter-spacing:0; }
.sidebar-brand-sub{ font-size:0.6rem; color:var(--tc-muted); margin-top:0.1rem; }
.sidebar.collapsed .sidebar-brand{ justify-content:center; padding:0; }
.sidebar.collapsed .sidebar-brand-text{ display:none; }

.sidebar-toggle{
  display:flex; align-items:center; justify-content:center;
  width:28px; height:28px; border-radius:6px; border:none; background:none;
  color:var(--tc-muted); cursor:pointer; flex-shrink:0; margin-left:auto;
  transition:background 0.2s;
}
.sidebar-toggle:hover{ background:rgba(21,28,39,0.06); color:var(--tc-ink); }
.sidebar-toggle svg{ width:16px; height:16px; transition:transform 0.2s; }
.sidebar.collapsed .sidebar-toggle svg{ transform:rotate(180deg); }
.sidebar.collapsed .sidebar-toggle{ margin:0 auto; }
.sidebar-toggle-row{ display:flex; align-items:center; margin-bottom:.4rem; }
.sidebar.collapsed .sidebar-toggle-row{ justify-content:center; }

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
  white-space:nowrap; overflow:hidden;
}
.sidebar-link:hover{ background:rgba(21,28,39,0.05); color:var(--tc-ink); }
.sidebar-link.active{ background:var(--tc-red); color:#fff; box-shadow:0 3px 9px rgba(179,17,24,0.22); }
.sidebar-link .nav-icon{ width:18px; height:18px; flex-shrink:0; display:flex; }
.sidebar-link .nav-icon svg{ width:100%; height:100%; stroke:currentColor; }
.sidebar-link .nav-label{ overflow:hidden; text-overflow:ellipsis; }

.sidebar.collapsed .sidebar-link{ justify-content:center; padding:.65rem 0; }
.sidebar.collapsed .sidebar-link .nav-label{ display:none; }
.sidebar.collapsed .sidebar-logout{ justify-content:center; padding:.65rem 0; }
.sidebar.collapsed .sidebar-logout span:not(.nav-icon){ display:none; }

body{ padding-left:var(--tc-sidebar-w); transition:padding-left 0.2s ease; background:#f7f8fb; }
body.sidebar-collapsed{ padding-left:var(--tc-sidebar-w-collapsed); }

@media (max-width:900px){
  body, body.sidebar-collapsed{ padding-left:0; padding-bottom:72px; }
  .sidebar, .sidebar.collapsed{
    top:auto; bottom:0; left:0; right:0; width:100%; height:auto;
    flex-direction:row; align-items:center; justify-content:space-around;
    padding:0.5rem 0.4rem; border-right:none; border-top:1px solid var(--tc-line);
  }
  .sidebar-brand, .sidebar-toggle-row{ display:none; }
  .sidebar-nav{ flex-direction:row; width:auto; flex:1; justify-content:space-around; gap:0; }
  .sidebar-link, .sidebar.collapsed .sidebar-link{ flex-direction:column; gap:0.2rem; padding:0.4rem 0.6rem; font-size:0.63rem; border-radius:12px; justify-content:center; }
  .sidebar-link.active{ box-shadow:none; }
  .sidebar-link .nav-label{ display:block !important; }
  .sidebar-footer{ border-top:none; padding-top:0; margin-top:0; flex-shrink:0; }
  .sidebar-logout, .sidebar.collapsed .sidebar-logout{ flex-direction:column; gap:0.2rem; padding:0.4rem 0.6rem; font-size:0.63rem; justify-content:center; }
  .sidebar-logout span:not(.nav-icon){ display:block !important; }
}
</style>
<aside class="sidebar" id="tcSidebar">
  <div class="sidebar-toggle-row">
    <div class="sidebar-brand" style="margin-bottom:0;flex:1;">
      <div class="sidebar-brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
      </div>
      <div class="sidebar-brand-text">
        <div class="sidebar-brand-name">TELE-CARE</div>
        <div class="sidebar-brand-sub">Patient Portal</div>
      </div>
    </div>
    <button type="button" class="sidebar-toggle" id="tcSidebarToggle" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
    </button>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($nav_pages as $key => $item): ?>
    <a href="<?= $item['href'] ?>" class="sidebar-link <?= $active_nav === $key ? 'active' : '' ?>" data-tab="<?= $key ?>">
      <span class="nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
          <?= $item['icon'] ?>
        </svg>
      </span>
      <span class="nav-label"><?= $item['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
</aside>
<script>
(function(){
  var sidebar = document.getElementById('tcSidebar');
  var toggle = document.getElementById('tcSidebarToggle');
  var collapsed = localStorage.getItem('tcSidebarCollapsed') === '1';
  function apply(c){
    sidebar.classList.toggle('collapsed', c);
    document.body.classList.toggle('sidebar-collapsed', c);
  }
  apply(collapsed);
  toggle.addEventListener('click', function(){
    collapsed = !collapsed;
    localStorage.setItem('tcSidebarCollapsed', collapsed ? '1' : '0');
    apply(collapsed);
  });
})();
</script>