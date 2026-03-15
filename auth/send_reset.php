<?php
/**
 * auth/send_reset.php
 * Called via fetch() from forgot_password.php
 * Generates a password reset token and returns JSON for EmailJS
 */
require_once '../database/config.php';
header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name FROM patients WHERE email = ? AND is_verified = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result  = $stmt->get_result();
$patient = $result->fetch_assoc();

// Always return success to prevent email enumeration
if (!$patient) {
    echo json_encode(['success' => true]); // silent — don't reveal if email exists
    exit;
}

// Generate token — let MySQL handle the timestamp to avoid timezone mismatch
$token = bin2hex(random_bytes(32));

$upd = $conn->prepare("UPDATE patients SET reset_token = ?, reset_expires = NOW() + INTERVAL 1 HOUR WHERE id = ?");
$upd->bind_param("si", $token, $patient['id']);
$upd->execute();

$link = 'http://' . $_SERVER['HTTP_HOST'] . '/auth/reset_password.php?token=' . urlencode($token);

echo json_encode([
    'success' => true,
    'email'   => $email,
    'name'    => $patient['full_name'],
    'link'    => $link,
]);