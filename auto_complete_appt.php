<?php
/**
 * auto_complete_appt.php
 * Called via fetch() from call pages when both parties join during scheduled time.
 * Marks appointment as Completed only if it's within the actual call window.
 */
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');

session_start();
require_once 'database/config.php';

$appt_id = (int)($_POST['appt_id'] ?? 0);
$role    = trim($_POST['role'] ?? ''); // 'patient' or 'doctor'

if (!$appt_id || !in_array($role, ['patient','doctor'])) {
    echo json_encode(['ok'=>false,'msg'=>'Invalid request']); exit;
}

// Verify caller owns this appointment
if ($role === 'patient') {
    $uid = $_SESSION['patient_id'] ?? 0;
    $stmt = $conn->prepare("SELECT id, appointment_date, appointment_time, status FROM appointments WHERE id=? AND patient_id=? LIMIT 1");
} else {
    $uid = $_SESSION['doctor_id'] ?? 0;
    $stmt = $conn->prepare("SELECT id, appointment_date, appointment_time, status FROM appointments WHERE id=? AND doctor_id=? LIMIT 1");
}

if (!$uid) { echo json_encode(['ok'=>false,'msg'=>'Not authenticated']); exit; }
$stmt->bind_param("ii", $appt_id, $uid);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) { echo json_encode(['ok'=>false,'msg'=>'Appointment not found']); exit; }
if ($appt['status'] === 'Completed') { echo json_encode(['ok'=>true,'msg'=>'Already completed']); exit; }

// Only mark complete if we're at or past the scheduled start time
$appt_ts = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
if (time() < $appt_ts) {
    echo json_encode(['ok'=>false,'msg'=>'Not started yet']); exit;
}

// Mark as Completed
$upd = $conn->prepare("UPDATE appointments SET status='Completed' WHERE id=? AND status='Confirmed'");
$upd->bind_param("i", $appt_id);
$upd->execute();

echo json_encode(['ok'=>true,'msg'=>'Appointment marked as Completed','affected'=>$upd->affected_rows]);