<style>
  .bottom-nav {
    position:fixed; bottom:0; left:0; right:0;
    background:var(--white); border-top:1px solid var(--border);
    display:flex; z-index:100; padding-bottom:env(safe-area-inset-bottom);
  }
  .nav-item {
    flex:1; display:flex; flex-direction:column; align-items:center;
    padding:0.6rem 0.3rem; text-decoration:none;
    color:var(--muted); font-size:0.6rem; font-weight:700;
    text-transform:uppercase; letter-spacing:0.06em;
    transition:color 0.2s; gap:0.25rem;
  }
  .nav-item svg { width:22px; height:22px; stroke:currentColor; }
  .nav-item.active { color:var(--green); }
  .nav-item.active svg { stroke:var(--green); }
</style>

<nav class="bottom-nav">
  <a href="dashboard.php" class="nav-item <?= ($active_nav ?? '')==='home'?'active':'' ?>">
    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
    Home
  </a>
  <a href="patients.php" class="nav-item <?= ($active_nav ?? '')==='patients'?'active':'' ?>">
    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    Patients
  </a>
  <a href="appointments.php" class="nav-item <?= ($active_nav ?? '')==='appointments'?'active':'' ?>">
    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    Schedule
  </a>
  <a href="chat.php" class="nav-item <?= ($active_nav ?? '')==='chat'?'active':'' ?>">
    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    Chat
  </a>
  <a href="profile.php" class="nav-item <?= ($active_nav ?? '')==='profile'?'active':'' ?>">
    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
    Profile
  </a>
</nav>