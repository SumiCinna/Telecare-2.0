<?php
// includes/sidebar.php
// Expects: $active_nav (e.g. 'dashboard', 'legal_policies', 'audit_logs', 'system_settings')
$active_nav = $active_nav ?? '';
function nav_active($key, $active_nav) { return $key === $active_nav ? ' active' : ''; }
?>
<aside class="sidebar">
	<div class="brand"><span class="brand-mark">+</span><div><span class="brand-name">TeleCare</span><span class="brand-role">SUPER ADMIN</span></div></div>
	<nav class="sidebar-nav">
		<div class="nav-label">Main Menu</div>
		<a class="nav-link<?= nav_active('dashboard', $active_nav) ?>" href="dashboard.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M3 11.5 12 4l9 7.5M5 10v10h14V10M9 20v-5h6v5"/></svg>Dashboard</a>
		<div class="nav-group">
			<div class="nav-label">System Management</div>
			<a class="nav-link<?= nav_active('users', $active_nav) ?>" href="users.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>User Management</a>
			<a class="nav-link<?= nav_active('clinics', $active_nav) ?>" href="clinics.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M4 21V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v16M4 21h16M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2"/></svg>Clinic Management</a>
			<a class="nav-link<?= nav_active('appointments', $active_nav) ?>" href="appointments.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>Appointments</a>
			<a class="nav-link<?= nav_active('consultations', $active_nav) ?>" href="consultations.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v13H4zM8 21h8M12 17v4"/></svg>Consultations</a>
			<a class="nav-link<?= nav_active('payments', $active_nav) ?>" href="prices.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M3 7h18M5 7v12h14V7M8 4h8M9 11h6M9 15h4"/></svg>Payments</a>
			<a class="nav-link<?= nav_active('reports', $active_nav) ?>" href="reports.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h16M8 16v-3M12 16V8M16 16v-5"/></svg>Reports</a>
		</div>
		<div class="nav-group">
			<div class="nav-label">Legal &amp; Compliance</div>
			<a class="nav-link<?= nav_active('legal_policies', $active_nav) ?>" href="legal_policies.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l3 3v15H6zM9 12h6M9 16h6M9 8h3"/></svg>Legal Policies</a>
			<a class="nav-link<?= nav_active('audit_logs', $active_nav) ?>" href="audit_logs.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Audit Logs</a>
			<a class="nav-link<?= nav_active('system_settings', $active_nav) ?>" href="system_settings.php"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 6v5c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6zM9 12l2 2 4-4"/></svg>System Settings</a>
		</div>
	</nav>
</aside>