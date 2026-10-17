<?php
// admin/login.php
// Admin no longer has its own login form — Super Admin, Admin, and Staff
// all sign in through one shared page. This file just forwards there
// (and straight to the dashboard if already logged in), so any existing
// links to admin/login.php keep working.
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

header('Location: ../router.php?page=staffs_index');
exit;
