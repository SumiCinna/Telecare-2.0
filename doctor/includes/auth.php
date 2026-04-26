<?php
session_start();

require_once '../database/config.php';

if (!isset($_SESSION['doctor_id'])) {
    header('Location: ../login.php'); exit;
}

$doctor_id = $_SESSION['doctor_id'];

// Fetch full doctor record
$stmt = $conn->prepare("SELECT * FROM doctors WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();
$stmt->close();

if (!$doc) {
    session_destroy();
    header('Location: ../login.php'); exit;
}