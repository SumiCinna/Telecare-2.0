<?php
$page_title = 'Reports'; $active_nav = 'reports'; $heading = 'Reports'; $subtitle = 'Explore platform reporting options in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Reports']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>Report Center</h3><p>Frontend-only reporting workspace. No report data is connected.</p></div><button class="btn btn-primary" type="button" data-confirm-action="Report generation is available in the backend version.">Create Report</button></div><div class="panel-body"><div class="empty-state"><h3>Reports preview</h3><p>Connect a backend later to load reporting data.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
