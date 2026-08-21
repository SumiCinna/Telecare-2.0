<?php
// includes/auth_any.php
// includes/auth_any.php
// Multi-role session guard: allows staff, doctor, OR patient sessions through.
// Use this (instead of includes/auth.php) on any endpoint that must be
// reachable by more than one role — e.g. process_consultation_v2.php,
// check_summary.php, download_summary.php.

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

require_once __DIR__ . '/../database/config.php'; // includes/ -> root -> database/config.php

$staff_id   = $_SESSION['staff_id']   ?? null;
$doctor_id  = $_SESSION['doctor_id']  ?? null;
$patient_id = $_SESSION['patient_id'] ?? null;

if (!$staff_id && !$doctor_id && !$patient_id) {
    header('Location: auth/login.php');
    exit;
}



