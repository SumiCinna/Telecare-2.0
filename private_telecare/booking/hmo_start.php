<?php
// private_telecare/booking/hmo_start.php — "Use HMO to Book": starts the booking flow with HMO pre-selected at payment.
require_once __DIR__ . '/../../includes/auth.php';
$_SESSION['booking'] = ['prefer_method' => 'HMO'];
header('Location: router.php?page=booking/step1_details');
exit;
