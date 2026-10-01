<?php
// auth/app_handoff.php
// Opened inside the Tele-Care app after Chrome finished the Google sign-in.
// Exchanges the one-time token for a normal logged-in session.
require_once '../database/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {    session_start();}
require_once 'app_handoff_lib.php';

$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';

try {
    $result = tc_app_handoff_consume($conn, $token);
} catch (Throwable $e) {
    $result = null;
}

if (!$result) {
    header('Location: login.php?error=google_token_failed');
    exit;
}

foreach ($result['session'] as $k => $v) {
    $_SESSION[$k] = $v;
}
session_regenerate_id(true);

header('Location: ' . $result['redirect']);
exit;
