<?php
session_start();
require_once '../database/config.php';

// Load environment variables
$env_file = '../.env';
$env_vars = [];
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $env_vars[trim($key)] = trim($value);
        }
    }
}

$google_client_id = $env_vars['GOOGLE_CLIENT_ID'] ?? '';
$google_redirect_uri = $env_vars['GOOGLE_REDIRECT_URI_REGISTER'] ?? '';

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