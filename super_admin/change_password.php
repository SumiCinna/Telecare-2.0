<?php
// super_admin/change_password.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['super_admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

// Uses the project's shared mysqli connection ($conn), matching the rest of TELE-CARE's admin side.
// Adjust this path if your db config lives somewhere else.
require_once __DIR__ . '/../database/config.php';

$super_admin_id = $_SESSION['super_admin_id'];
$old_password     = $_POST['old_password'] ?? '';
$new_password     = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($old_password === '' || $new_password === '' || $confirm_password === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
    exit;
}

$meetsLength = strlen($new_password) >= 8;
$meetsUpper  = preg_match('/[A-Z]/', $new_password);
$meetsLower  = preg_match('/[a-z]/', $new_password);
$meetsNumber = preg_match('/[0-9]/', $new_password);

if (!($meetsLength && $meetsUpper && $meetsLower && $meetsNumber)) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters and include 1 uppercase letter, 1 lowercase letter, and 1 number.']);
    exit;
}

$stmt = $conn->prepare('SELECT password FROM super_admins WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $super_admin_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Account not found.']);
    exit;
}

if (!password_verify($old_password, $row['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
    exit;
}

if (password_verify($new_password, $row['password'])) {
    echo json_encode(['success' => false, 'message' => 'New password must be different from your current password.']);
    exit;
}

$newHash = password_hash($new_password, PASSWORD_BCRYPT);

$update = $conn->prepare('UPDATE super_admins SET password = ? WHERE id = ?');
$update->bind_param('si', $newHash, $super_admin_id);
$success = $update->execute();
$update->close();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not update password. Please try again.']);
}