<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

// pay_cancel.php — PayMongo redirects here when payment is cancelled or fails

$appt_id = (int)($_GET['appt_id'] ?? 0);
$_SESSION['toast_error'] = "Payment was cancelled or failed. Your appointment is still reserved — you can try paying again.";
header('Location: visits.php' . ($appt_id ? '#appt-' . $appt_id : ''));
exit;