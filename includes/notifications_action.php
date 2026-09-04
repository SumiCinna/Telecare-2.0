<?php
error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors into JSON output
ob_start(); // catch any stray output from auth.php

require_once __DIR__ . '/auth.php';

ob_end_clean(); // discard anything auth.php printed
header('Content-Type: application/json');

if (!isset($conn, $patient_id)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'mark_read') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND patient_id = ?");
    $stmt->bind_param('ii', $id, $patient_id);
    $stmt->execute();
    $stmt->close();
} elseif ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE patient_id = ? AND is_read = 0");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_action']);
    exit;
}

$stmt = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE patient_id = ? AND is_read = 0");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$unread_count = (int) $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

echo json_encode(['success' => true, 'unread_count' => $unread_count]);