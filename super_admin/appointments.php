<?php
$page_title = 'Appointments'; $active_nav = 'appointments'; $heading = 'Appointments'; $subtitle = 'Monitor appointment activity in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Appointments']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>Appointment Activity</h3><p>Frontend-only schedule overview. No appointment data is connected.</p></div><button class="btn btn-secondary" type="button" data-confirm-action="Appointment export is available in the backend version.">Export</button></div><div class="panel-body"><div class="empty-state"><h3>Appointments preview</h3><p>Connect a backend later to load appointment records.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
