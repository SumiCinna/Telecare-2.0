<?php
// admin/includes/hmo_admin.php — shared boot + layout for the admin HMO pages
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../../database/config.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = (int)$_SESSION['admin_id'];

require_once __DIR__ . '/../../private_telecare/booking/hmo_config_helpers.php';
hmo_cfg_ensure_schema($conn);

function h($x): string { return htmlspecialchars((string)$x, ENT_QUOTES); }

function hmo_admin_csrf(): string {
    if (empty($_SESSION['hmo_admin_csrf'])) $_SESSION['hmo_admin_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['hmo_admin_csrf'];
}
function hmo_admin_csrf_ok(): bool {
    return !empty($_SESSION['hmo_admin_csrf']) && hash_equals($_SESSION['hmo_admin_csrf'], (string)($_POST['csrf'] ?? ''));
}
/** Redirect back with a toast. */
function hmo_admin_back(string $url, string $msg, bool $err = false): void {
    $_SESSION[$err ? 'toast_error' : 'toast'] = $msg;
    header('Location: ' . $url); exit;
}
/** Only real Y-m-d dates, otherwise null. */
function hmo_admin_date($v): ?string {
    $v = trim((string)$v); if ($v === '') return null;
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}
function hmo_admin_fmt_dur($minutes): string {
    if ($minutes === null) return '—';
    $m = (float)$minutes;
    if ($m < 60) return round($m) . ' min';
    if ($m < 1440) return round($m / 60, 1) . ' hr';
    return round($m / 1440, 1) . ' days';
}
/** Plain-language status of a provider: Active / Inactive / Suspended. */
function hmo_provider_state(array $p): string {
    if ((int)($p['on_hold'] ?? 0) === 1) return 'Suspended';
    return ($p['status'] ?? 'Active') === 'Active' ? 'Active' : 'Inactive';
}
function hmo_state_badge(string $s): string {
    $c = ['Active' => 'badge-green', 'Inactive' => 'badge-gray', 'Suspended' => 'badge-red'][$s] ?? 'badge-gray';
    return '<span class="badge ' . $c . '">' . h($s) . '</span>';
}
function hmo_case_badge(string $s): string {
    $c = ['Pending' => 'badge-gray', 'In Verification' => 'badge-blue', 'Action Required' => 'badge-orange', 'Approved' => 'badge-green',
          'Not Approved' => 'badge-red', 'Not Covered' => 'badge-red'][$s] ?? 'badge-gray';
    return '<span class="badge ' . $c . '">' . h($s) . '</span>';
}

/** Streams a CSV and ends the request. */
function hmo_admin_csv(string $filename, array $header, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $header);
    foreach ($rows as $r) fputcsv($o, array_map(fn($v) => preg_match('/^[=+\-@]/', (string)$v) ? "'" . $v : $v, $r));
    fclose($o); exit;
}

function hmo_admin_nav(): array {
    return [
        'hmo-dashboard'    => ['hmo_dashboard.php', 'Dashboard'],
    'pos-hmo'          => ['hmo_providers.php', 'Providers'],
        'hmo-rules'        => ['hmo_rules.php', 'Coverage & Rules'],
        'hmo-staff'        => ['hmo_staff.php', 'Staff & Permissions'],
        'hmo-transactions' => ['hmo_transactions.php', 'Transactions'],
        'hmo-reports'      => ['hmo_reports.php', 'Reports'],
        'hmo-audit'        => ['hmo_audit.php', 'Audit Logs'],
    ];
}

/** Prints <head>, the sidebar and the top of the page. Call hmo_admin_foot() at the end. */
function hmo_admin_head(string $title, string $activeNav, string $actions = ''): void {
    $toast = $_SESSION['toast'] ?? null; $toast_error = $_SESSION['toast_error'] ?? null;
    unset($_SESSION['toast'], $_SESSION['toast_error']);
    ?><!DOCTYPE html>
<html lang="en"><head>
  <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= h($title) ?> — TELE-CARE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <link href="assets/hmo.css" rel="stylesheet"/>
  <style>
    .hx-tabs{display:flex;gap:.5rem;flex-wrap:wrap;margin:0 0 1.1rem}
    .hx-tab{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:.5rem .95rem;border-radius:999px;border:1px solid var(--hmo-border);font-size:.82rem;font-weight:700;color:var(--hmo-muted);text-decoration:none;background:#fff;transition:all .2s}
    .hx-tab:hover{border-color:var(--hmo-blue);color:var(--hmo-blue)}
    .hx-tab.active{background:var(--hmo-blue);border-color:var(--hmo-blue);color:#fff;box-shadow:0 5px 12px rgba(20,120,238,.16)}
    .hx-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:18px}
    .hx-kpi{background:#fff;border:1px solid var(--hmo-border);border-radius:13px;padding:1rem 1.1rem;box-shadow:var(--hmo-shadow)}
    .hx-kpi .n{font-size:1.7rem;font-weight:800;line-height:1.1;color:var(--hmo-navy)}
    .hx-kpi .l{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#7197c6;margin-top:.35rem}
    .hx-kpi .s{font-size:.74rem;color:var(--hmo-muted);margin-top:.3rem}
    .hx-card{background:#fff;border:1px solid var(--hmo-border);border-radius:13px;padding:1.15rem 1.2rem;margin-bottom:1rem;box-shadow:var(--hmo-shadow)}
    .hx-card h3{margin:0 0 .25rem;color:var(--hmo-navy);font-size:1rem;font-weight:700}
    .hx-card .sub{font-size:.8rem;color:var(--hmo-muted);margin-bottom:.95rem;line-height:1.45}
    .hx-grid2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px}
    .hx-grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .hx-lbl{display:block;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#7197c6;margin:0 0 .35rem}
    .hx-in{width:100%;min-height:44px;padding:.68rem .85rem;border:1.5px solid var(--hmo-border);border-radius:10px;font-family:inherit;font-size:.88rem;background:#fff;color:var(--hmo-ink)}
    .hx-in:focus{outline:none;border-color:var(--hmo-blue)}
    .hx-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-height:44px;padding:.65rem 1.1rem;border-radius:999px;background:var(--hmo-blue);color:#fff;font-weight:700;font-size:.84rem;border:0;cursor:pointer;text-decoration:none;box-shadow:0 5px 12px rgba(20,120,238,.16)}
    .hx-btn:hover{background:var(--hmo-blue-dark)}
    .hx-btn.ghost{background:#fff;color:var(--hmo-navy);border:1px solid var(--hmo-border);box-shadow:none}
    .hx-checks{display:flex;flex-wrap:wrap;gap:.45rem 1rem;font-size:.84rem}
    .hx-filter{display:flex;gap:.7rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem}
    .hx-filter>div{min-width:160px}
    .hx-mini{width:100%;border-collapse:collapse;font-size:.85rem}
    .hx-mini th{text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#7197c6;padding:.55rem .6rem;border-bottom:1px solid var(--hmo-line)}
    .hx-mini td{padding:.7rem .6rem;border-bottom:1px solid #edf2f8;vertical-align:top;color:var(--hmo-ink)}
    .hx-bar{height:8px;border-radius:999px;background:#e8f2fd;overflow:hidden;min-width:80px}.hx-bar i{display:block;height:100%;background:var(--hmo-blue)}
    .hx-muted{color:var(--hmo-muted);font-size:.78rem}
    .hx-diff{font-size:.74rem;background:#f6faff;border:1px solid var(--hmo-line);border-radius:10px;padding:.4rem .55rem;white-space:pre-wrap;word-break:break-word;max-width:420px}
    .badge{display:inline-flex;align-items:center;padding:.24rem .66rem;border-radius:999px;font-size:.72rem;font-weight:700}
    .badge-green{background:#d9f5e9;color:#10a66a}
    .badge-red{background:#ffe0e3;color:#ef4444}
    .badge-blue{background:#dceeff;color:#1478ee}
    .badge-orange{background:#fff0c9;color:#d97706}
    .badge-gray{background:#eef3f8;color:#607d9d}
    .btn-sm{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:.42rem .82rem;border-radius:999px;border:1px solid var(--hmo-border);background:#fff;color:var(--hmo-navy);font-size:.8rem;font-weight:700;text-decoration:none;cursor:pointer}
    .btn-edit{background:rgba(20,120,238,.08);border-color:rgba(20,120,238,.12);color:var(--hmo-blue)}
    .btn-red{background:#fff1f2;border-color:#fecdd3;color:#dc2626}
    .table-wrap{background:#fff;border:1px solid var(--hmo-border);border-radius:13px;overflow:hidden;box-shadow:var(--hmo-shadow)}
    .empty-row{padding:2rem;text-align:center;color:var(--hmo-muted)}
    .main-shell{min-width:0;min-height:100vh;margin-left:256px}
    .main-shell .hmo-content{padding-bottom:28px}
    @media(max-width:900px){.sidebar{display:none}.main-shell{margin-left:0}.hx-grid2,.hx-grid3{grid-template-columns:1fr}.hx-filter>div{min-width:0;flex:1 1 180px}}
  </style>
</head>
<body>
<?php if ($toast): ?><div class="toast">✓ <?= h($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast error">✕ <?= h($toast_error) ?></div><?php endif; ?>
<?php $activeNavForSidebar = $activeNav; $activeNav = $activeNavForSidebar; include __DIR__ . '/../sidebar.php'; ?>
<main class="hmo-main main-shell">
  <header class="hmo-topbar">
    <button class="mobile-menu" type="button" aria-label="Toggle navigation">☰</button>
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <span>Admin</span><span class="breadcrumb-separator" aria-hidden="true">›</span><span>HMO Management</span><span class="breadcrumb-separator" aria-hidden="true">›</span><strong aria-current="page"><?= h($title) ?></strong>
    </nav>
    <div class="topbar-user">
      <span class="user-avatar" aria-hidden="true">A</span>
      <span class="user-identity"><strong><?= h($_SESSION['admin_name'] ?? 'Admin') ?></strong><small>Administrator</small></span>
      <span class="user-menu-caret" aria-hidden="true">⌄</span>
      <div><?= $actions ?></div>
    </div>
  </header>
  <div class="hmo-content">
<?php
}
function hmo_admin_foot(): void { ?>
  </div>
</main>
<script src="assets/hmo-dashboard.js" defer></script>
<script>setTimeout(function(){document.querySelectorAll('.toast').forEach(function(t){t.style.display='none'})},5000);</script>
</body></html>
<?php }
