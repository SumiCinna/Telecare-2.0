<?php
$page_title = 'User Management'; $active_nav = 'users'; $heading = 'User Management'; $subtitle = 'Review platform accounts and access roles in this frontend preview.'; $breadcrumbs = [['label' => 'Dashboard', 'href' => 'dashboard.php'], ['label' => 'User Management']]; require_once 'includes/header.php';
?>
<div class="panel"><div class="panel-header"><div><h3>User Directory</h3><p>Frontend-only account overview. No account data is connected.</p></div><button class="btn btn-primary" type="button" data-confirm-action="User creation is available in the backend version.">+ Add User</button></div><div class="panel-body"><div class="empty-state"><h3>Users preview</h3><p>Connect a backend later to load and manage user records.</p></div></div></div>
<?php require_once 'includes/footer.php'; ?>
