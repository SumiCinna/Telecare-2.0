<?php
$page_title = 'Payments'; $active_nav = 'payments'; $heading = 'Payments'; $subtitle = 'Review payment activity in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'Payments']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>Payment Activity</h3><p>Frontend-only payment overview. No transaction data is connected.</p></div><button class="btn btn-secondary" type="button" data-confirm-action="Payment export is available in the backend version.">Export</button></div><div class="panel-body"><div class="empty-state"><h3>Payments preview</h3><p>Connect a backend later to load payment records.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
