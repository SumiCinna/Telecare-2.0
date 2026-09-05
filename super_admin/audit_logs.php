<?php
$page_title = 'Audit Logs'; $active_nav = 'audit_logs'; $heading = 'Audit Logs'; $subtitle = 'Review administrative activity in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Audit Logs']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>Activity Log</h3><p>Frontend-only audit overview. No activity data is connected.</p></div><button class="btn btn-secondary" type="button" data-confirm-action="Audit export is available in the backend version.">Download</button></div><div class="panel-body"><div class="empty-state"><h3>Audit logs preview</h3><p>Connect a backend later to load audit records.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
