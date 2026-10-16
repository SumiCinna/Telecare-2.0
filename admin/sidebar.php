
<?php
// admin/sidebar.php

$activeNav = $activeNav ?? '';

// Keep compatibility with the previous HMO Providers active key.
if ($activeNav === 'pos-hmo') {
    $activeNav = 'hmo-providers';
}

$hmoNavItems = [
    'hmo-dashboard',
    'hmo-providers',
    'hmo-rules',
    'hmo-staff',
    'hmo-transactions',
    'hmo-reports',
    'hmo-audit',
];

$hmoActive = in_array($activeNav, $hmoNavItems, true);

$sidebarAdminName = htmlspecialchars(
    (string) ($_SESSION['admin_name'] ?? 'Admin'),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>

<style>
aside.sidebar {
    --tc-blue: #1478ee;
    --tc-navy: #12345d;
    --tc-ink: #183657;
    --tc-line: #dfeaf5;
    --tc-muted: #7188a4;
    --tc-background: #f6faff;
    --tc-scrollbar: #c6d6e8;
    --tc-scrollbar-hover: #91accb;

    position: fixed;
    inset: 0 auto 0 0;
    z-index: 150;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
    width: 256px;
    height: 100vh;
    height: 100dvh;
    padding: 1.35rem 1rem;
    overflow: hidden;
    background: var(--tc-background);
    border-right: 1px solid var(--tc-line);
    font-family: 'Poppins', sans-serif;
}

/* Logo */
aside.sidebar .sidebar-logo {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-shrink: 0;
    padding: 0 .45rem;
    margin-bottom: 1.35rem;
}

aside.sidebar .sidebar-logo-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    overflow: hidden;
    border-radius: 11px;
}

aside.sidebar .sidebar-logo-mark img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

aside.sidebar .sidebar-logo-name {
    color: var(--tc-navy);
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.15;
    white-space: nowrap;
}

aside.sidebar .sidebar-logo-sub {
    margin-top: .18rem;
    color: var(--tc-muted);
    font-size: .68rem;
    font-weight: 500;
}

/* Signed-in administrator */
aside.sidebar .sidebar-admin {
    flex-shrink: 0;
    padding: .75rem .85rem;
    margin-bottom: 1.1rem;
    background: #fff;
    border: 1px solid var(--tc-line);
    border-radius: 12px;
    color: var(--tc-muted);
    font-size: .72rem;
    line-height: 1.5;
}

aside.sidebar .sidebar-admin strong {
    display: block;
    overflow-wrap: anywhere;
    color: var(--tc-ink);
    font-size: .9rem;
    font-weight: 700;
}

/* Independently scrollable navigation */
aside.sidebar .nav-links {
    display: flex;
    flex: 1 1 0%;
    flex-direction: column;
    gap: .35rem;
    min-width: 0;
    min-height: 0;
    padding: 0 0 .75rem;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-width: thin;
    scrollbar-color: var(--tc-scrollbar) transparent;
}

aside.sidebar .nav-links::-webkit-scrollbar {
    width: 5px;
}

aside.sidebar .nav-links::-webkit-scrollbar-track {
    background: transparent;
}

aside.sidebar .nav-links::-webkit-scrollbar-thumb {
    background: var(--tc-scrollbar);
    border-radius: 999px;
}

aside.sidebar .nav-links::-webkit-scrollbar-thumb:hover {
    background: var(--tc-scrollbar-hover);
}

/* Navigation links */
aside.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: .65rem;
    box-sizing: border-box;
    width: 100%;
    min-height: 42px;
    flex-shrink: 0;
    padding: .7rem .8rem;
    border: 0;
    border-radius: 10px;
    background: transparent;
    color: var(--tc-muted);
    font-family: inherit;
    font-size: .84rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
    transition: background-color .2s, color .2s;
}

aside.sidebar .nav-link svg {
    display: block;
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
    box-shadow: 0 5px 14px rgba(23, 104, 216, .18);
}

aside.sidebar .nav-link:focus-visible,
aside.sidebar .logout-btn:focus-visible {
    outline: 3px solid rgba(20, 120, 238, .35);
    outline-offset: 2px;
}

/* Navigation section labels */
aside.sidebar .nav-section-label {
    flex-shrink: 0;
    padding: 1rem .8rem .35rem;
    color: var(--tc-muted);
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .09em;
    text-transform: uppercase;
}

/* HMO collapsible menu */
aside.sidebar .nav-dropdown {
    flex-shrink: 0;
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

aside.sidebar .nav-dropdown > summary.active {
    background: #e6f0fd;
    color: var(--tc-blue);
    box-shadow: none;
}

aside.sidebar .nav-dropdown-items {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    padding: .3rem 0 .15rem;
}

aside.sidebar .nav-dropdown-items .nav-link {
    min-height: 38px;
    padding: .6rem .65rem .6rem 2.65rem;
    font-size: .81rem;
}

aside.sidebar .nav-dropdown-items .nav-link.active {
    background: #e6f0fd;
    color: var(--tc-blue);
    box-shadow: none;
}

/* Logout remains at the bottom */
aside.sidebar .sidebar-logout {
    position: relative;
    z-index: 10;
    flex-shrink: 0;
    padding-top: .6rem;
    margin-top: .5rem;
    background: var(--tc-background);
    border-top: 1px solid var(--tc-line);
}

aside.sidebar .logout-btn {
    display: flex;
    align-items: center;
    gap: .65rem;
    box-sizing: border-box;
    width: 100%;
    padding: .75rem .8rem;
    border-radius: 10px;
    color: var(--tc-muted);
    font-size: .86rem;
    font-weight: 600;
    text-decoration: none;
    transition: background-color .2s, color .2s;
}

aside.sidebar .logout-btn:hover {
    background: #eaf3fd;
    color: var(--tc-blue);
}

aside.sidebar .logout-btn svg {
    width: 19px;
    height: 19px;
    flex-shrink: 0;
}

/* Smaller screens */
@media (max-width: 768px) {
    aside.sidebar {
        width: 256px;
        max-width: 85vw;
    }
}

@media (max-height: 600px) {
    aside.sidebar {
        padding-top: .8rem;
        padding-bottom: .8rem;
    }

    aside.sidebar .sidebar-logo {
        margin-bottom: .8rem;
    }

    aside.sidebar .sidebar-admin {
        margin-bottom: .7rem;
    }
}
</style>

<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-mark">
            <img src="/telecarelogo.png" alt="TELE-CARE">
        </div>
        <div>
            <div class="sidebar-logo-name">TELE-CARE</div>
            <div class="sidebar-logo-sub">Admin Portal</div>
        </div>
    </div>

    <div class="sidebar-admin">
        Signed in as
        <strong><?= $sidebarAdminName ?></strong>
    </div>

    <nav class="nav-links" aria-label="Admin navigation">
        <a href="dashboard.php"
           class="nav-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a href="Users.php"
           class="nav-link <?= $activeNav === 'users' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            User Management
        </a>

        <a href="assignments.php"
           class="nav-link <?= $activeNav === 'assignments' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Assignments
        </a>

        <a href="templates.php"
           class="nav-link <?= $activeNav === 'pos-templates' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 4.5h6a2 2 0 012 2V19a2 2 0 01-2 2H9a2 2 0 01-2-2V6.5a2 2 0 012-2z"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9.5 8.5h5M9.5 11.5h5M9.5 14.5h3"/>
            </svg>
            Templates
        </a>

        <a href="inventory.php"
           class="nav-link <?= $activeNav === 'pos-products' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Inventory
        </a>

        <a href="services.php"
           class="nav-link <?= $activeNav === 'pos-services' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <rect x="3" y="5" width="18" height="16" rx="2"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v8m-4-4h8M8 3v2m8-2v2"/>
            </svg>
            Services
        </a>

        <a href="discounts.php"
           class="nav-link <?= $activeNav === 'pos-discounts' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <circle cx="7" cy="7" r="2"/>
                <circle cx="17" cy="17" r="2"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 5L5 19"/>
            </svg>
            Discounts
        </a>

        <a href="prices.php"
           class="nav-link <?= $activeNav === 'pos-prices' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <text x="4" y="17" font-size="16" font-weight="700"
                      font-family="Arial, sans-serif" fill="currentColor" stroke="none">₱</text>
            </svg>
            Prices
        </a>

        <a href="receipt_config.php"
           class="nav-link <?= $activeNav === 'pos-receipt' ? 'active' : '' ?>">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Receipt Config
        </a>
        
        <details class="nav-dropdown" <?= $hmoActive ? 'open' : '' ?>>
            <summary class="nav-link <?= $hmoActive ? 'active' : '' ?>">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 8v6m-3-3h6"/>
                </svg>
                HMO Management
                <svg class="nav-chevron" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                </svg>
            </summary>

            <div class="nav-dropdown-items">
                <a href="hmo_dashboard.php"
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

                <a href="hmo_rules.php"
                   class="nav-link <?= $activeNav === 'hmo-rules' ? 'active' : '' ?>">
                    Coverage &amp; Rules
                </a>

                <a href="hmo_staff.php"
                   class="nav-link <?= $activeNav === 'hmo-staff' ? 'active' : '' ?>">
                    Staff &amp; Permissions
                </a>

                <a href="hmo_reports.php"
                   class="nav-link <?= $activeNav === 'hmo-reports' ? 'active' : '' ?>">
                    Reports
                </a>

                <a href="hmo_audit.php"
                   class="nav-link <?= $activeNav === 'hmo-audit' ? 'active' : '' ?>">
                    Audit Logs
                </a>
            </div>
        </details>
    </nav>

    <div class="sidebar-logout">
        <a href="logout.php" class="logout-btn">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"
                 stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Log Out
        </a>
    </div>
</aside>
