<?php
/**
 * TELE-CARE Admin — HMO Dashboard
 *
 * Presentation-only overview. This page intentionally does not query HMO
 * transaction tables because the dashboard data source has not been wired up.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

if (empty($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}

$activeNav = 'hmo-dashboard';
$adminName = (string) ($_SESSION['admin_name'] ?? 'Admin');

/** Escape values rendered into HTML text or attributes. */
function hmoDashboardEscape(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$adminName = hmoDashboardEscape($adminName);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f4f8fd">
  <title>HMO Dashboard | TELE-CARE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/admin.css">
  <link rel="stylesheet" href="assets/hmo.css">
</head>
<body>
  <?php include __DIR__ . '/sidebar.php'; ?>

  <main class="hmo-main">
    <header class="hmo-topbar">
      <button
        class="mobile-menu"
        type="button"
        aria-label="Toggle navigation"
        aria-expanded="false"
      >
        <span aria-hidden="true">☰</span>
      </button>

      <nav class="breadcrumbs" aria-label="Breadcrumb">
        <span>Admin</span>
        <span class="breadcrumb-separator" aria-hidden="true">›</span>
        <span>HMO Management</span>
        <span class="breadcrumb-separator" aria-hidden="true">›</span>
        <strong aria-current="page">HMO Dashboard</strong>
      </nav>

      <div class="topbar-user">
        <button class="notification-button" type="button" aria-label="Notifications">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
            <path d="M10 21h4"></path>
          </svg>
          <span class="notification-count" aria-label="3 notifications">3</span>
        </button>

        <span class="user-avatar" aria-hidden="true">A</span>
        <span class="user-identity">
          <strong><?= $adminName ?></strong>
          <small>Administrator</small>
        </span>
        <span class="user-menu-caret" aria-hidden="true">⌄</span>
      </div>
    </header>

    <div class="hmo-content">
      <section class="page-heading" aria-labelledby="page-title">
        <div>
          <p class="eyebrow">HMO MANAGEMENT</p>
          <h1 id="page-title">HMO Dashboard</h1>
          <p class="page-description">Monitor your HMO network, authorizations, and claims activity.</p>
        </div>
        <a class="button button-primary" href="hmo_providers.php">
          <span aria-hidden="true">＋</span>
          Manage Providers
        </a>
      </section>
      <section class="metric-grid" aria-label="HMO summary metrics">
        <article class="metric-card metric-card-blue">
          <span class="metric-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 4v16M8 8h8M8 16h8"></path><circle cx="12" cy="12" r="8"></circle></svg>
          </span>
          <div class="metric-copy">
            <p>Total Providers</p>
            <strong class="metric-value">—</strong>
            <small>Provider data not connected</small>
          </div>
        </article>

        <article class="metric-card metric-card-green">
          <span class="metric-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg>
          </span>
          <div class="metric-copy">
            <p>Active Providers</p>
            <strong class="metric-value">—</strong>
            <small>Provider data not connected</small>
          </div>
        </article>

        <article class="metric-card metric-card-amber">
          <span class="metric-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"></circle><path d="M12 7v5l3 2"></path></svg>
          </span>
          <div class="metric-copy">
            <p>Pending Authorizations</p>
            <strong class="metric-value">—</strong>
            <small>Authorization data not connected</small>
          </div>
        </article>

        <article class="metric-card metric-card-purple">
          <span class="metric-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><rect x="5" y="5" width="14" height="14" rx="2"></rect><path d="M9 9h6v6H9z"></path></svg>
          </span>
          <div class="metric-copy">
            <p>Claims This Month</p>
            <strong class="metric-value">—</strong>
            <small>Claims data not connected</small>
          </div>
        </article>
      </section>

      <section class="dashboard-grid dashboard-grid-primary" aria-label="Authorization overview and quick actions">
        <article class="panel authorization-panel">
          <div class="panel-heading panel-heading-with-action">
            <div>
              <h2>Authorization Overview</h2>
              <p>Requests processed over the last 6 months</p>
            </div>
            <span class="select-display" aria-label="Reporting period">Last 6 months <span aria-hidden="true">⌄</span></span>
          </div>

          <div class="chart-empty-state">
            <svg class="chart-placeholder" viewBox="0 0 700 205" role="img" aria-label="Placeholder chart; authorization data is not connected">
              <g class="chart-grid-lines">
                <line x1="42" y1="20" x2="690" y2="20"></line>
                <line x1="42" y1="62" x2="690" y2="62"></line>
                <line x1="42" y1="104" x2="690" y2="104"></line>
                <line x1="42" y1="146" x2="690" y2="146"></line>
                <line x1="42" y1="188" x2="690" y2="188"></line>
              </g>
              <g class="chart-axis-labels">
                <text x="8" y="24">—</text><text x="8" y="66">—</text>
                <text x="8" y="108">—</text><text x="8" y="150">—</text>
                <text x="8" y="192">—</text>
              </g>
              <g class="chart-month-labels">
                <text x="42" y="204">May</text><text x="150" y="204">Jun</text>
                <text x="258" y="204">Jul</text><text x="366" y="204">Aug</text>
                <text x="474" y="204">Sep</text><text x="582" y="204">Oct</text>
                <text x="665" y="204">Nov</text>
              </g>
            </svg>
            <div class="chart-empty-message">
              <span class="empty-state-icon" aria-hidden="true">⌁</span>
              <strong>Authorization data will appear here</strong>
              <p>Connect the authorization records to display monthly activity.</p>
            </div>
          </div>
        </article>

        <article class="panel quick-actions-panel">
          <div class="panel-heading">
            <h2>Quick Actions</h2>
            <p>Common HMO tasks</p>
          </div>

          <div class="quick-action-list">
            <a class="quick-action" href="hmo_providers.php">
              <span class="quick-action-icon icon-blue" aria-hidden="true">♧</span>
              <span class="quick-action-copy">
                <strong>Manage Providers</strong>
                <small>View and update your HMO network</small>
              </span>
              <span class="quick-action-arrow" aria-hidden="true">›</span>
            </a>

            <a class="quick-action quick-action-disabled" href="#authorization-workflow" aria-disabled="true" tabindex="-1">
              <span class="quick-action-icon icon-green" aria-hidden="true">✓</span>
              <span class="quick-action-copy">
                <strong>Review Authorizations</strong>
                <small>Authorization workflow is not connected</small>
              </span>
              <span class="quick-action-arrow" aria-hidden="true">›</span>
            </a>

            <a class="quick-action quick-action-disabled" href="#claims-workflow" aria-disabled="true" tabindex="-1">
              <span class="quick-action-icon icon-purple" aria-hidden="true">▣</span>
              <span class="quick-action-copy">
                <strong>Process Claims</strong>
                <small>Claims workflow is not connected</small>
              </span>
              <span class="quick-action-arrow" aria-hidden="true">›</span>
            </a>

            <a class="quick-action quick-action-disabled" href="#hmo-reports" aria-disabled="true" tabindex="-1">
              <span class="quick-action-icon icon-teal" aria-hidden="true">↗</span>
              <span class="quick-action-copy">
                <strong>View Reports</strong>
                <small>HMO reports are not connected</small>
              </span>
              <span class="quick-action-arrow" aria-hidden="true">›</span>
            </a>
          </div>
        </article>
      </section>

      <section class="dashboard-grid dashboard-grid-secondary" aria-label="Recent activity and provider status">
        <article class="panel recent-activity-panel">
          <div class="panel-heading panel-heading-with-action">
            <div>
              <h2>Recent Activity</h2>
              <p>Latest HMO management updates</p>
            </div>
            <span class="text-link text-link-muted">No activity feed</span>
          </div>

          <div class="panel-empty-state">
            <span class="empty-state-icon" aria-hidden="true">◷</span>
            <div>
              <strong>No recent HMO activity available</strong>
              <p>Activity will appear after the audit and transaction feeds are connected.</p>
            </div>
          </div>
        </article>

        <article class="panel provider-status-panel">
          <div class="panel-heading">
            <h2>Provider Status</h2>
            <p>Current network breakdown</p>
          </div>

          <div class="provider-status-empty">
            <div class="donut-placeholder" aria-hidden="true"><span>—</span></div>
            <div class="status-legend">
              <div><span class="legend-dot legend-active"></span><span>Active</span><strong>—</strong></div>
              <div><span class="legend-dot legend-inactive"></span><span>Inactive</span><strong>—</strong></div>
              <div><span class="legend-dot legend-on-hold"></span><span>On hold</span><strong>—</strong></div>
            </div>
          </div>
          <p class="data-note">Counts will be populated from HMO provider records.</p>
        </article>
      </section>

      <p class="dashboard-footnote">
        <span aria-hidden="true">ⓘ</span>
        Dashboard layout is ready. Live metrics, charts, and activity remain intentionally unpopulated until their data sources are integrated.
      </p>
    </div>
  </main>

  <script src="assets/hmo-dashboard.js" defer></script>
</body>
</html>
