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
        'pos-hmo'          => ['hmo.php', 'Providers'],
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
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    .main{flex:1;overflow-y:auto;margin-left:230px;min-width:0}
    .hx-tabs{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:1.2rem}
    .hx-tab{padding:.45rem .9rem;border-radius:50px;border:1.5px solid rgba(36,68,65,.12);font-size:.78rem;font-weight:700;color:var(--green);text-decoration:none;background:#fff}
    .hx-tab.active{background:var(--red);border-color:var(--red);color:#fff}
    .hx-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:.9rem;margin-bottom:1.3rem}
    .hx-kpi{background:#fff;border:1px solid rgba(36,68,65,.08);border-radius:16px;padding:1rem 1.1rem}
    .hx-kpi .n{font-size:1.7rem;font-weight:800;line-height:1.1}
    .hx-kpi .l{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9ab0ae;margin-top:.3rem}
    .hx-kpi .s{font-size:.72rem;color:#9ab0ae;margin-top:.25rem}
    .hx-card{background:#fff;border:1px solid rgba(36,68,65,.08);border-radius:16px;padding:1.2rem 1.3rem;margin-bottom:1.1rem}
    .hx-card h3{font-family:'DM Sans',sans-serif;font-size:.98rem;font-weight:800;margin-bottom:.3rem}
    .hx-card .sub{font-size:.76rem;color:#9ab0ae;margin-bottom:.9rem}
    .hx-grid2{display:grid;grid-template-columns:1fr 1fr;gap:.8rem 1rem}
    .hx-grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem 1rem}
    .hx-lbl{display:block;font-size:.72rem;font-weight:700;margin:.2rem 0 .3rem}
    .hx-in{width:100%;padding:.6rem .75rem;border:1.5px solid rgba(36,68,65,.14);border-radius:10px;font-family:inherit;font-size:.84rem;background:#fff;color:var(--green)}
    .hx-btn{display:inline-block;padding:.6rem 1.2rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:.82rem;border:0;cursor:pointer;text-decoration:none}
    .hx-btn.ghost{background:#fff;color:var(--green);border:1.5px solid rgba(36,68,65,.15)}
    .hx-checks{display:flex;flex-wrap:wrap;gap:.4rem 1.1rem;font-size:.82rem}
    .hx-filter{display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1rem}
    .hx-filter>div{min-width:140px}
    .hx-mini{width:100%;border-collapse:collapse;font-size:.8rem}
    .hx-mini th{text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:#9ab0ae;padding:.45rem .5rem;border-bottom:1px solid rgba(36,68,65,.1)}
    .hx-mini td{padding:.5rem;border-bottom:1px solid rgba(36,68,65,.06);vertical-align:top}
    .hx-bar{height:8px;border-radius:5px;background:rgba(36,68,65,.08);overflow:hidden;min-width:80px}.hx-bar i{display:block;height:100%;background:var(--red)}
    .hx-muted{color:#9ab0ae;font-size:.76rem}
    .hx-diff{font-size:.72rem;background:rgba(36,68,65,.05);border-radius:8px;padding:.35rem .5rem;white-space:pre-wrap;word-break:break-word;max-width:420px}
    @media(max-width:900px){.sidebar{display:none}.main{margin-left:0}.hx-grid2,.hx-grid3{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php if ($toast): ?><div class="toast">✓ <?= h($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast error">✕ <?= h($toast_error) ?></div><?php endif; ?>
<?php $activeNavForSidebar = $activeNav; $activeNav = $activeNavForSidebar; include __DIR__ . '/../sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div><div style="font-size:.75rem;color:#9ab0ae;font-weight:600;">Admin Portal · HMO</div><div style="font-size:.95rem;font-weight:700;"><?= h($title) ?></div></div>
    <div><?= $actions ?></div>
  </div>
  <div class="page-content">
    <div class="hx-tabs">
      <?php foreach (hmo_admin_nav() as $k => [$href, $label]): ?>
        <a class="hx-tab <?= $k === $activeNav ? 'active' : '' ?>" href="<?= h($href) ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </div>
<?php
}
function hmo_admin_foot(): void { ?>
  </div>
</div>
<script>setTimeout(function(){document.querySelectorAll('.toast').forEach(function(t){t.style.display='none'})},5000);</script>
</body></html>
<?php }
