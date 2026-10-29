<?php
// includes/nav.php
// $active_nav must be set before including: 'home' | 'visits' | 'meds' | 'billing' | 'profile'
$active_nav = $active_nav ?? 'home';

// The Android app (Capacitor) appends "TeleCareApp" to the user agent.
$tcIsApp = stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'TeleCareApp') !== false;

$nav_pages = [
    'home'    => ['href' => 'router.php?page=dashboard', 'label' => 'Dashboard',
            'icon' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>'],
    'visits'  => ['href' => 'router.php?page=visits',    'label' => 'Appointments',
            'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>'],
    'records'  => ['href' => 'router.php?page=records',   'label' => 'Records',
            'icon' => '<path d="M6 3h9l3 3v15H6a2 2 0 01-2-2V5a2 2 0 012-2z"/><path d="M14 3v4h4M8 11h6M8 15h6M8 19h4"/>'],
    'billing' => ['href' => 'router.php?page=billings',    'label' => 'Billing',
            'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>'],
];
// NOTE: The "Settings" / profile link was intentionally removed from the
// sidebar - it's already reachable from the profile dropdown in the header
// (includes/header.php), so keeping it here just duplicated the entry.
?>
<script>
/* Browser-tab icon: use telecarelogo.png on every page that includes this nav */
(function(){
  var href = '/telecarelogo.png?v=telecare-blue-1';
  var head = document.head || document.getElementsByTagName('head')[0];
  if (!head) return;
  Array.prototype.slice.call(head.querySelectorAll('link[rel~="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]')).forEach(function(l){ l.parentNode.removeChild(l); });
  ['icon', 'shortcut icon', 'apple-touch-icon'].forEach(function(rel){
    var l = document.createElement('link');
    l.rel = rel; l.type = 'image/png'; l.href = href;
    head.appendChild(l);
  });
})();
</script>
<style>
:root{
  --tc-red:#B31118; --tc-red-dark:#8a000b; --tc-blue:#1478ee; --tc-navy:#12345d; --tc-ink:#151c27; --tc-teal:#006a61; --tc-teal-light:#0D9488;
  --tc-line:rgba(21,28,39,0.08); --tc-muted:rgba(21,28,39,0.5);
  --tc-sidebar-w:240px; --tc-sidebar-w-collapsed:72px;
}
.sidebar{
  position:fixed; top:0; left:0; bottom:0; width:var(--tc-sidebar-w); z-index:150;
  background:#f1f3fc; border-right:1px solid #dce1ef;
  display:flex; flex-direction:column; padding:1.2rem .85rem;
  font-family:'Poppins',sans-serif;
  transition:width 0.2s ease;
}
.sidebar.collapsed{ width:var(--tc-sidebar-w-collapsed); padding:1.2rem .55rem; }

.sidebar-brand{ display:flex; align-items:center; gap:.65rem; padding:0 .3rem; margin-bottom:2rem; }
.sidebar-brand-icon{
  width:36px; height:36px; border-radius:8px; overflow:hidden; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
}
.sidebar-brand-icon img{ width:100%; height:100%; object-fit:contain; display:block; }
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
  cursor:pointer; font-family:'Poppins',sans-serif;
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
.sidebar-link.active{ background:var(--tc-blue); color:#fff; box-shadow:0 5px 14px rgba(23,104,216,.22); }
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
        <img src="/telecarelogo.png" alt="TELE-CARE"/>
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

<style>
:root{--tc-sidebar-w:256px;--tc-blue:#1478ee;--tc-navy:#12345d;--tc-ink:#183657;--tc-muted:#7188a4;--tc-line:#dfeaf5}
.sidebar{width:var(--tc-sidebar-w);background:#f6faff;border-right-color:var(--tc-line);padding:1.35rem 1rem}
.sidebar-brand{gap:.75rem;margin-bottom:1.35rem}
.sidebar-brand-icon{width:42px;height:42px;border-radius:11px}
.sidebar-brand-name{font-size:1rem;color:var(--tc-navy)}
.sidebar-brand-sub{font-size:.68rem;color:var(--tc-muted)}
.sidebar-link{padding:.75rem .8rem;border-radius:10px;color:var(--tc-muted);font-size:.86rem}
.sidebar-link:hover{background:#eaf3fd;color:var(--tc-navy)}
.sidebar-link.active{background:var(--tc-blue);color:#fff;box-shadow:0 5px 14px rgba(23,104,216,.22)}
.sidebar-link .nav-icon{width:19px;height:19px}
body{padding-left:var(--tc-sidebar-w);background:#f5f9fd}
@media(max-width:900px){body{padding-left:0}}
</style>


<style>
/* ── Mobile bottom bar fix: full width (the 256px desktop width was leaking in), items share the row evenly ── */
@media(max-width:900px){
  .sidebar,.sidebar.collapsed{left:0!important;right:0!important;bottom:0!important;top:auto!important;width:100%!important;max-width:100vw;min-width:0!important;box-sizing:border-box;height:auto;flex-direction:row!important;padding:.35rem .25rem calc(.35rem + env(safe-area-inset-bottom,0px))!important;background:#fff!important;border-right:0!important;border-top:1px solid #dfeaf5!important;box-shadow:0 -4px 16px rgba(18,52,93,.08);z-index:1000}
  .sidebar-nav{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));gap:2px!important;width:100%;flex:1}
  .sidebar-link,.sidebar.collapsed .sidebar-link{flex-direction:column!important;align-items:center;justify-content:center;gap:3px!important;min-width:0;padding:.45rem .1rem!important;border-radius:12px!important;font-size:.66rem!important;line-height:1.1;text-align:center;white-space:nowrap}
  .sidebar-link .nav-label{max-width:100%;overflow:hidden;text-overflow:ellipsis}
  .sidebar-link .nav-icon{width:22px!important;height:22px!important}
  .sidebar-link.active{background:#eaf3fd!important;color:var(--tc-blue,#1478ee)!important;box-shadow:none!important}
  body,body.sidebar-collapsed{padding-left:0!important}
}
</style>

<?php if ($tcIsApp): ?>
<style>
/* ── Android app only: keep the UI clear of the status bar and the gesture/nav bar ── */
body.tc-app{ --tc-safe-top:max(env(safe-area-inset-top, 0px), 32px); --tc-safe-bottom:max(env(safe-area-inset-bottom, 0px), 16px); padding-top:var(--tc-safe-top); }
body.tc-app::before{ content:''; position:fixed; top:0; left:0; right:0; height:var(--tc-safe-top); background:#fff; z-index:10000; }
@media(max-width:900px){
  body.tc-app, body.tc-app.sidebar-collapsed{ padding-bottom:calc(72px + var(--tc-safe-bottom)); }
  body.tc-app .sidebar, body.tc-app .sidebar.collapsed{ padding-bottom:calc(0.5rem + var(--tc-safe-bottom)); }
}
</style>
<script>
(function(){
  document.body.classList.add('tc-app');
  // env(safe-area-inset-*) only reports values when the viewport allows drawing edge to edge.
  var m = document.querySelector('meta[name="viewport"]');
  if (m && m.content.indexOf('viewport-fit') === -1) { m.content += ', viewport-fit=cover'; }
})();
</script>
<?php endif; ?>