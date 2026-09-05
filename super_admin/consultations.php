<?php
$page_title = 'Consultations'; $active_nav = 'consultations'; $heading = 'Consultations'; $subtitle = 'Review consultation activity in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Consultations']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>Consultation Activity</h3><p>Frontend-only consultation overview. No consultation data is connected.</p></div><button class="btn btn-secondary" type="button" data-confirm-action="Report generation is available in the backend version.">Generate Report</button></div><div class="panel-body"><div class="empty-state"><h3>Consultations preview</h3><p>Connect a backend later to load consultation records.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
