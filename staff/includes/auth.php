<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['staff_id'])) {
    header('Location: ../login.php');
    exit;
}

$staff_id = (int)$_SESSION['staff_id'];

// Fetch fresh staff row every request
$s = $conn->prepare("SELECT * FROM staff WHERE id = ? AND is_active = 1 LIMIT 1");
$s->bind_param("i", $staff_id);
$s->execute();
$staff = $s->get_result()->fetch_assoc();

if (!$staff) {
    session_destroy();
    header('Location: ../login.php?reason=deactivated');
    exit;
}

// Unread notification count (used in sidebar badge)
$notif_r = $conn->query("SELECT COUNT(*) c FROM staff_notifications WHERE staff_id=$staff_id AND is_read=0");
$unread_notifs = $notif_r ? (int)$notif_r->fetch_assoc()['c'] : 0;