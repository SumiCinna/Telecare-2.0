<?php
require_once '../database/config.php';
session_start();

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

// ── 1. Exchange code for token ────────────────────────────────────────────────
$client_id     = $env_vars['GOOGLE_CLIENT_ID'] ?? '';
$client_secret = $env_vars['GOOGLE_CLIENT_SECRET'] ?? '';
$redirect_uri  = $env_vars['GOOGLE_REDIRECT_URI_REGISTER'] ?? '';

if (empty($client_id) || empty($client_secret) || empty($redirect_uri)) {
    die('Error: Google OAuth credentials not configured in .env file.');
}

if (empty($_GET['code'])) {
    header('Location: register.php?error=google_cancelled');
    exit;
}

$token_res = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]),
    ],
]));

if (!$token_res) {
    header('Location: register.php?error=google_token_failed');
    exit;
}

$token_data    = json_decode($token_res, true);
$access_token  = $token_data['access_token'] ?? null;

if (!$access_token) {
    header('Location: register.php?error=google_token_failed');
    exit;
}

// ── 2. Get Google profile ─────────────────────────────────────────────────────
$profile_res = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create([
    'http' => ['header' => 'Authorization: Bearer ' . $access_token],
]));

if (!$profile_res) {
    header('Location: register.php?error=google_profile_failed');
    exit;
}

$profile = json_decode($profile_res, true);
$google_id    = $profile['id']             ?? null;
$google_email = $profile['email']          ?? null;
$google_name  = $profile['name']           ?? '';
$google_pic   = $profile['picture']        ?? null;
$google_fname = $profile['given_name']     ?? '';
$google_lname = $profile['family_name']    ?? '';

if (!$google_id || !$google_email) {
    header('Location: register.php?error=google_no_email');
    exit;
}

// ── 3. Check if already registered ───────────────────────────────────────────
$chk = $conn->prepare("SELECT id, is_verified FROM patients WHERE email = ?");
$chk->bind_param("s", $google_email);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
    $chk->bind_result($pid, $is_verified);
    $chk->fetch();
    $chk->close();
    if ($is_verified) {
        header('Location: login.php?error=already_registered');
    } else {
        header('Location: login.php?error=verify_pending&email=' . urlencode($google_email));
    }
    exit;
}
$chk->close();

// ── 4. Store Google data in session, redirect to finish-registration form ─────
$_SESSION['google_reg'] = [
    'google_id'   => $google_id,
    'email'       => $google_email,
    'first_name'  => $google_fname,
    'last_name'   => $google_lname,
    'full_name'   => $google_name,
    'picture'     => $google_pic,
];

header('Location: google-register-finish.php');
exit;