<?php
// auth/google-doctor-login.php
if (session_status() !== PHP_SESSION_ACTIVE) {    session_start();}
require_once '../database/config.php';

// Load environment variables
  $google_client_id = getenv('GOOGLE_CLIENT_ID') ?: '';
  $google_redirect_uri = getenv('GOOGLE_REDIRECT_URI_DOCTOR') ?: str_replace('auth/google-callback.php', 'auth/google-doctor-callback.php', (getenv('GOOGLE_REDIRECT_URI_LOGIN') ?: ''));

if (empty($google_client_id) || empty($google_redirect_uri)) {
    die('Error: Google OAuth credentials not configured in .env file.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $google_client_id,
    'redirect_uri'  => $google_redirect_uri,
    'response_type' => 'code',
    'scope'         => 'email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]));
exit;




