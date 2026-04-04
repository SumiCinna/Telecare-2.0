<?php
require_once '../database/config.php';

header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');

if ($email === '') {
    echo json_encode(['ok' => false, 'message' => 'Email is required.', 'exists' => false]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid email format.', 'exists' => false]);
    exit;
}

$stmt = $conn->prepare('SELECT id FROM patients WHERE email = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'Failed to prepare query.', 'exists' => false]);
    exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

$exists = $stmt->num_rows > 0;

$stmt->close();

echo json_encode(['ok' => true, 'exists' => $exists]);
