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

$stmt = $conn->prepare("SELECT id, full_name, reset_expires FROM patients WHERE email = ? AND is_verified = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result  = $stmt->get_result();
$patient = $result->fetch_assoc();

// Return explicit error if email not registered
if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'No registered account found with that email address.']);
    exit;
}

// ── 3-minute cooldown check ──
if (!empty($patient['reset_expires'])) {
    $stmt2 = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, NOW(), reset_expires) as secs_left, TIMESTAMPDIFF(SECOND, NOW() - INTERVAL 1 HOUR + INTERVAL 3 MINUTE, reset_expires) as too_soon FROM patients WHERE id = ?");
    $stmt2->bind_param("i", $patient['id']);
    $stmt2->execute();
    $timing = $stmt2->get_result()->fetch_assoc();
    // Token was created less than 3 minutes ago if secs_left > 57 minutes
    if ($timing['secs_left'] > 3420) { // 3600 - 180 = 3420 seconds
        $wait = $timing['secs_left'] - 3420;
        $mins = ceil($wait / 60);
        echo json_encode(['success' => false, 'message' => "Please wait {$mins} minute(s) before requesting another reset link."]);
        exit;
    }
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