<?php
$page_title = 'Clinic Management'; $active_nav = 'clinics'; $heading = 'Clinic Management'; $subtitle = 'Review registered clinics and service coverage in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Clinic Management']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>Clinic Directory</h3><p>Frontend-only clinic overview. No clinic data is connected.</p></div><button class="btn btn-primary" type="button" data-confirm-action="Clinic creation is available in the backend version.">+ Add Clinic</button></div><div class="panel-body"><div class="empty-state"><h3>Clinics preview</h3><p>Connect a backend later to load and manage clinic records.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
