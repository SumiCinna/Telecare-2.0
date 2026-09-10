<?php
// auth/google-callback.php
require_once '../database/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {    session_start();}

// Load environment variables
  $client_id     = getenv('GOOGLE_CLIENT_ID') ?: '';
  $client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
  $redirect_uri  = getenv('GOOGLE_REDIRECT_URI_LOGIN') ?: '';

if (empty($client_id) || empty($client_secret) || empty($redirect_uri)) {
    die('Error: Google OAuth credentials not configured in .env file.');
}

if (empty($_GET['code'])) {
    header('Location: login.php?error=google_cancelled');
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

$token_data   = json_decode($token_res, true);
$access_token = $token_data['access_token'] ?? null;

if (!$access_token) {
    header('Location: login.php?error=google_token_failed');
    exit;
}

$profile_res = file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false, stream_context_create([
    'http' => ['header' => 'Authorization: Bearer ' . $access_token],
]));

$profile      = json_decode($profile_res, true);
$google_email = $profile['email'] ?? null;

if (!$google_email) {
    header('Location: login.php?error=google_no_email');
    exit;
}

// Auto-detect account type: check the doctors table first, since a doctor's
// email will never also exist as a patient email.
$docStmt = $conn->prepare("SELECT id, full_name, status FROM doctors WHERE email = ? LIMIT 1");
$docStmt->bind_param("s", $google_email);
$docStmt->execute();
$doctor = $docStmt->get_result()->fetch_assoc();
$docStmt->close();

if ($doctor) {
    if ($doctor['status'] !== 'active') {
        header('Location: login.php?error=account_deactivated&email=' . urlencode($google_email));
        exit;
    }

    $_SESSION['doctor_id']   = $doctor['id'];
    $_SESSION['doctor_name'] = $doctor['full_name'];

    header('Location: ../doctor/dashboard.php');
    exit;
}

// Not a doctor - look up patient by email
$stmt = $conn->prepare("SELECT id, full_name, is_verified FROM patients WHERE email = ?");
$stmt->bind_param("s", $google_email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    header('Location: register.php?error=no_account&email=' . urlencode($google_email));
    exit;
}

$stmt->bind_result($patient_id, $full_name, $is_verified);
$stmt->fetch();
$stmt->close();

if (!$is_verified) {
    header('Location: login.php?error=not_verified&email=' . urlencode($google_email));
    exit;
}

$_SESSION['patient_id']   = $patient_id;
$_SESSION['patient_name'] = $full_name;
$_SESSION['patient_email']= $google_email;

header('Location: ../router.php?page=dashboard');
exit;