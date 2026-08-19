<?php
session_start();

$page = $_GET['page'] ?? '';

$publicPages = [
    'index', 'check_cdn', 'login', 'register', 'forgot_password', 'verify',
    'reset_password', 'resend_verification', 'check_email', 'termsandpolicy',
    'google-login', 'google-register', 'google-callback',
    'google-register-callback', 'google-register-finish',
    'google-doctor-login', 'google-doctor-callback', 'logout'
];

$privatePages = [
    'dashboard', 'visits', 'meds', 'profile', 'receipt', 'chat',
    'call_patient', 'process_consultation_v2', 'process_consultation',
    'check_summary', 'download_summary', 'auto_complete_appt',
    'pay', 'pay_success', 'pay_cancel', 'staffs_index', 'privacy-policy'
];

if (in_array($page, $publicPages)) {
    $file = __DIR__ . '/' . $page . '.php';
    if (file_exists($file)) {
        include $file;
        exit;
    }
}

require_once __DIR__ . '/includes/auth.php';

if (!in_array($page, $privatePages)) {
    header('Location: router.php?page=dashboard');
    exit;
}

$file = __DIR__ . '/private_telecare/' . $page . '.php';
if (!file_exists($file)) {
    header('Location: router.php?page=dashboard');
    exit;
}

include $file;
exit;
