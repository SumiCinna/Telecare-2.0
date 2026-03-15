<?php
/**
 * resend_verification.php
 * Called via fetch() from login.php and register.php
 * Regenerates the verification token and returns JSON for EmailJS to send
 */
require_once '../database/config.php';
header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

// Find unverified patient
$stmt = $conn->prepare("SELECT id, full_name, is_verified FROM patients WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
    exit;
}

if ($patient['is_verified']) {
    echo json_encode(['success' => false, 'message' => 'This account is already verified.']);
    exit;
}

// Generate fresh token
$token      = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

$upd = $conn->prepare("UPDATE patients SET verification_token = ?, token_expires_at = ? WHERE id = ?");
$upd->bind_param("ssi", $token, $expires_at, $patient['id']);
$upd->execute();

$link = 'http://' . $_SERVER['HTTP_HOST'] . '/auth/verify.php?token=' . urlencode($token);

echo json_encode([
    'success' => true,
    'email'   => $email,
    'name'    => $patient['full_name'],
    'link'    => $link,
]);