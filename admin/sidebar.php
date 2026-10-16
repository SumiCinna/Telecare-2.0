```php
<?php
// admin/sidebar.php

$activeNav = $activeNav ?? '';
?>

<style>
aside.sidebar {
  --tc-blue: #1478ee;
  --tc-navy: #12345d;
  --tc-ink: #183657;
  --tc-line: #dfeaf5;
  --tc-muted: #7188a4;

  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 256px;
  z-index: 150;
  background: #f6faff;
  border-right: 1px solid var(--tc-line);
  display: flex;
  flex-direction: column;
  padding: 1.35rem 1rem;
  font-family: 'Poppins', sans-serif;
  overflow-y: auto;
  box-sizing: border-box;
}

aside.sidebar .sidebar-logo {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 0 .45rem;
  margin-bottom: 1.35rem;
}

aside.sidebar .sidebar-logo-mark {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  overflow: hidden;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

aside.sidebar .sidebar-logo-mark img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}

aside.sidebar .sidebar-logo-name {
  font-weight: 800;
  font-size: 1rem;
  color: var(--tc-navy);
  line-height: 1.15;
  white-space: nowrap;
}

aside.sidebar .sidebar-logo-sub {
  font-size: .68rem;
  color: var(--tc-muted);
  margin-top: .18rem;
  font-weight: 500;
}

aside.sidebar .sidebar-admin {
  background: #fff;
  border: 1px solid var(--tc-line);
  border-radius: 12px;
  padding: .75rem .85rem;
  margin-bottom: 1.25rem;
  font-size: .72rem;
  color: var(--tc-muted);
  line-height: 1.5;
}

aside.sidebar .sidebar-admin strong {
  display: block;
  font-size: .9rem;
  color: var(--tc-ink);
  font-weight: 700;
}

aside.sidebar .nav-links {
  display: flex;
  flex-direction: column;
  gap: .35rem;
  flex: 1;
}

aside.sidebar .nav-link {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .75rem .8rem;
  border-radius: 10px;
  color: var(--tc-muted);
  font-size: .86rem;
  font-weight: 600;
  text-decoration: none;
  transition: all .2s;
  white-space: nowrap;
  background: none;
  border: none;
  box-sizing: border-box;
  width: 100%;
  font-family: inherit;
  cursor: pointer;
}

aside.sidebar .nav-link svg {
  width: 19px;
  height: 19px;
  flex-shrink: 0;
  stroke: currentColor;
}

aside.sidebar .nav-link:hover {
  background: #eaf3fd;
  color: var(--tc-navy);
}

aside.sidebar .nav-link.active {
  background: var(--tc-blue);
  color: #fff;
  box-shadow: 0 5px 14px rgba(23, 104, 216, .22);
}

aside.sidebar .nav-dropdown {
  margin: 0;
}

aside.sidebar .nav-dropdown summary {
  list-style: none;
  cursor: pointer;
}

aside.sidebar .nav-dropdown summary::-webkit-details-marker {
  display: none;
}

aside.sidebar .nav-dropdown .nav-chevron {
  width: 14px;
  height: 14px;
  margin-left: auto;
  transition: transform .2s;
}

aside.sidebar .nav-dropdown[open] .nav-chevron {
  transform: rotate(180deg);
}

aside.sidebar .nav-dropdown-items {
  display: flex;
  flex-direction: column;
  gap: .2rem;
  padding: .3rem 0 .15rem;
}

aside.sidebar .nav-dropdown-items .nav-link {
  font-size: .82rem;
  padding-left: 2.7rem;
}

aside.sidebar .nav-dropdown-items .nav-link.active {
  background: #e6f0fd;
  color: var(--tc-blue);
  box-shadow: none;
}

aside.sidebar .sidebar-logout {
  border-top: 1px solid var(--tc-line);
  padding-top: .6rem;
  margin-top: .6rem;
}

aside.sidebar .logout-btn {
  display: flex;
  align-items: center;
  gap: .55rem;
  width: 100%;
  box-sizing: border-box;
  padding: .75rem .8rem;
  border-radius: 10px;
  background: none;
  border: none;
  color: var(--tc-muted);
  font-size: .86rem;
  font-weight: 600;
  text-decoration: none;
  font-family: 'Poppins', sans-serif;
  cursor: pointer;
  transition: all .2s;
}

aside.sidebar .logout-btn:hover {
  background: #eaf3fd;
  color: var(--tc-blue);
}

aside.sidebar .logout-btn svg {
  flex-shrink: 0;
}
</style>

<aside class="sidebar">

  <!-- Logo -->
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">
      <img src="/telecarelogo.png" alt="TELE-CARE">
    </div>
    <div>
      <div class="sidebar-logo-name">TELE-CARE</div>
      <div class="sidebar-logo-sub">Admin Portal</div>
    </div>
  </div>

  <!-- Signed-in Admin -->
  <div class="sidebar-admin">
    Signed in as
    <strong>
      <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
    </strong>
  </div>

  <nav class="nav-links">

    <!-- Dashboard -->
    <a href="dashboard.php"
       class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
      </svg>
      Dashboard
    </a>

    <!-- User Management: Medical Team -->
    <a href="Users.php"
       class="nav-link <?= $activeNav === 'users' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
        <circle cx="10" cy="7" r="4"/>
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M20 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
      </svg>
      User Management
    </a>

    <!-- Assignments -->
    <a href="assignments.php"
       class="nav-link <?= $activeNav === 'assignments' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      Assignments
    </a>

    <!-- Templates -->
    <a href="templates.php"
       class="nav-link <?= $activeNav === 'pos-templates' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 4.5h6a2 2 0 012 2V19a2 2 0 01-2 2H9a2 2 0 01-2-2V6.5a2 2 0 012-2z"/>
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9.5 8.5h5M9.5 11.5h5M9.5 14.5h3"/>
      </svg>
      Templates
    </a>

    <!-- Inventory -->
    <a href="inventory.php"
       class="nav-link <?= $activeNav === 'pos-products' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
      </svg>
      Inventory
    </a>

    <!-- Services: Medical Cross -->
    <a href="services.php"
       class="nav-link <?= $activeNav === 'pos-services' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9v8m-4-4h8M8 3v2m8-2v2"/>
      </svg>
      Services
    </a>

    <!-- Discounts: Percentage -->
    <a href="discounts.php"
       class="nav-link <?= $activeNav === 'pos-discounts' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <circle cx="7" cy="7" r="2"/>
        <circle cx="17" cy="17" r="2"/>
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M19 5L5 19"/>
      </svg>
      Discounts
    </a>

    <!-- Prices: Philippine Peso -->
    <a href="prices.php"
       class="nav-link <?= $activeNav === 'pos-prices' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <text x="4" y="17"
              font-size="16"
              font-weight="700"
              font-family="Arial, sans-serif"
              fill="currentColor"
              stroke="none">₱</text>
      </svg>
      Prices
    </a>

    <!-- Receipt Configuration -->
    <a href="receipt_config.php"
       class="nav-link <?= $activeNav === 'pos-receipt' ? 'active' : '' ?>">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
           aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      Receipt Config
    </a>

    <!-- HMO Management: Healthcare Shield -->
    <details class="nav-dropdown"
      <?= strpos($activeNav, 'hmo') === 0 ? 'open' : '' ?>>

      <summary class="nav-link <?= strpos($activeNav, 'hmo') === 0 ? 'active' : '' ?>">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"
             aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 8v6m-3-3h6"/>
        </svg>

        HMO Management

        <svg class="nav-chevron" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2"
             aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M6 9l6 6 6-6"/>
        </svg>
      </summary>

      <div class="nav-dropdown-items">
        <a href="hmo.php"
           class="nav-link <?= $activeNav === 'hmo-dashboard' ? 'active' : '' ?>">
          HMO Dashboard
        </a>

        <a href="hmo_providers.php"
           class="nav-link <?= $activeNav === 'hmo-providers' ? 'active' : '' ?>">
          HMO Providers
        </a>

        <a href="hmo_transactions.php"
           class="nav-link <?= $activeNav === 'hmo-transactions' ? 'active' : '' ?>">
          Transactions
        </a>
      </div>
    </details>

  </nav>

  <!-- Logout -->
  <div class="sidebar-logout">
    <a href="logout.php" class="logout-btn">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24"
           stroke="currentColor" stroke-width="2"
           aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Log Out
    </a>
  </div>

</aside>
