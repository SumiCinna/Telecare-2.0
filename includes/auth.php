<?php
// includes/auth.php
// Include at the top of every patient page.

session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../database/config.php'; // goes up from includes/ to root, then into database/

if (!isset($_SESSION['patient_id'])) {
    header('Location: auth/login.php');
    exit;
}

$patient_id = $_SESSION['patient_id'];

$p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();

$name_parts = explode(' ', $p['full_name']);
$initials   = strtoupper(
    substr($name_parts[0], 0, 1) .
    (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : '')
);