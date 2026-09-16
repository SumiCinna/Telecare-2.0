<?php
// staff/includes/auth.php
if (session_status() !== PHP_SESSION_ACTIVE) {    session_start();}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: ' . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2) . 'staff/login.php');
    exit;
}

$staff_id   = $_SESSION['staff_id'];
$staff_name = $_SESSION['staff_name'] ?? 'Staff';
$staff_role = $_SESSION['staff_role'] ?? 'receptionist';

// Keep the notifications table in sync with live appointment data, then
// grab the current unread count/list for the bell in the topbar.
syncNotifications($conn);
$notif_unread_count = getUnreadNotificationCount($conn, $staff_id);
$notif_items         = getActiveNotifications($conn, $staff_id, 20);

