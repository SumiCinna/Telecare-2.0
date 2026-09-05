<?php
$page_title = 'System Settings'; $active_nav = 'system_settings'; $heading = 'System Settings'; $subtitle = 'Review platform preferences in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'System Settings']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>Platform Settings</h3><p>Frontend-only settings overview. No configuration data is connected.</p></div><button class="btn btn-primary" type="button" data-confirm-action="Settings are available in the backend version.">Save Settings</button></div><div class="panel-body"><div class="empty-state"><h3>Settings preview</h3><p>Connect a backend later to load and manage system settings.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
