<?php
// includes/header.php
require_once __DIR__ . '/../../includes/breadcrumbs.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
if (!isset($_SESSION['super_admin_id'])) {
    header('Location: ../router.php?page=staffs_index');
    exit;
}
$super_admin_name = $_SESSION['super_admin_name'] ?? 'Super Admin';
// Expects (set before requiring this file):
//   $page_title       string  <title> tag text
//   $active_nav       string  which sidebar link is highlighted
//   $breadcrumbs      array   [['label'=>'Dashboard','href'=>'dashboard.php'], ['label'=>'Legal Policies']]
//   $header_icon      string  raw <svg>...</svg> for the small page icon box
//   $heading          string  H1 text
//   $heading_pill     string  optional raw HTML for a pill next to the H1 (e.g. "Active v1.3")
//   $subtitle         string  supporting copy under the H1
//   $header_actions   string  optional raw HTML for buttons in the top-right of the page heading
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($page_title) ?> | TELE-CARE</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="assets/js/css/style.css">
	<style>
		.tc-breadcrumb{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--faint);margin-bottom:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.tc-breadcrumb a{color:var(--faint);text-decoration:none}.tc-breadcrumb a:hover{color:var(--red)}
		.tc-breadcrumb-sep{color:#c7cfd9}.tc-breadcrumb-current{color:var(--ink);font-weight:600}
	</style>
</head>
<body>
<div class="admin-shell">
	<?php require __DIR__ . '/../sidebar.php'; ?>
	<main class="main">
		<header class="topbar">
			<label class="search">
				<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
				<input type="search" placeholder="Search anything..." aria-label="Search dashboard">
			</label>
			<div class="top-actions">
				<div class="notification" aria-label="Notifications"><b>3</b><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></div>
				<?php
					$sa_initials = '';
					foreach (preg_split('/\s+/', trim($super_admin_name)) as $part) {
						if ($part !== '') { $sa_initials .= strtoupper($part[0]); }
					}
					$sa_initials = substr($sa_initials, 0, 2) ?: 'SA';
				?>
				<a href="logout.php" class="icon-action" title="Log out" style="color:#91a0b1;text-decoration:none;">
					<svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
				</a>
				<div class="profile"><span class="profile-avatar"><?= htmlspecialchars($sa_initials) ?></span><div><strong><?= htmlspecialchars($super_admin_name) ?></strong><small>System Administrator</small></div></div>
			</div>
		</header>
		<section class="content">
			<?= tc_render_breadcrumbs('super_admin', $active_nav ?? 'dashboard', ['items' => $breadcrumbs ?? []]) ?>

			<div class="page-heading">
				<div class="page-heading-row">
					<?php if (!empty($header_icon)): ?><span class="page-icon"><?= $header_icon ?></span><?php endif; ?>
					<div>
						<div class="title-row">
							<h1><?= $heading ?? htmlspecialchars($page_title) ?></h1>
							<?php if (!empty($heading_pill)): ?><?= $heading_pill ?><?php endif; ?>
						</div>
						<?php if (!empty($subtitle)): ?><p class="subtitle"><?= $subtitle ?></p><?php endif; ?>
					</div>
				</div>
				<?php if (!empty($header_actions)): ?><div class="page-actions"><?= $header_actions ?></div><?php endif; ?>
			</div>