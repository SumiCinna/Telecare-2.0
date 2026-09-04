<?php
// private_telecare/booking/process_booking.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department', 'doctor_id', 'appt_date', 'appt_time']);
$b = $_SESSION['booking'];

$doctor_id  = (int)$b['doctor_id'];
$department = $b['department'];
$date       = $b['appt_date'];
$time       = $b['appt_time'];

$reason = implode(', ', $b['reasons'] ?? []);
$notes  = trim($b['reason_other'] ?? '');

// Multiple images may have been uploaded in step1 (up to 5). The DB still
// has single string columns for attachment_path/type/ocr_text, so we pack
// the per-file values into those columns rather than changing the schema:
//   - paths  -> comma-separated relative paths
//   - types  -> comma-separated OCR-detected doc types
//   - ocr    -> each file's text joined with a separator
$attachment_paths = $b['attachment_paths']     ?? [];
$attachment_types = $b['attachment_types']     ?? [];
$attachment_ocrs  = $b['attachment_ocr_texts'] ?? [];

$attachment_path = $attachment_paths ? implode(',', $attachment_paths) : null;
$attachment_type = $attachment_types ? implode(',', array_map(fn($t) => $t ?? 'unknown', $attachment_types)) : null;
$attachment_ocr  = $attachment_ocrs
    ? implode("\n---\n", array_map(fn($t) => $t ?? '', $attachment_ocrs))
    : null;

// Re-validate doctor + slot are still valid/free (someone else may have
// booked it, or the doctor may have gone inactive, while the patient was
// clicking through the wizard).
$doc_chk = $conn->prepare("SELECT id FROM doctors WHERE id=? AND department=? AND status='active'");
$doc_chk->bind_param("is", $doctor_id, $department);
$doc_chk->execute();
if (!$doc_chk->get_result()->fetch_assoc()) {
    $_SESSION['toast_error'] = 'Selected doctor is no longer available.';
    header('Location: router.php?page=booking/step2_doctor'); exit;
}

$dup = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('Cancelled')");
$dup->bind_param("iss", $doctor_id, $date, $time);
$dup->execute();
if ($dup->get_result()->fetch_assoc()) {
    $_SESSION['toast_error'] = 'That time slot was just taken. Please pick another.';
    header('Location: router.php?page=booking/step3_schedule'); exit;
}

// ── Straight-to-payment flow ──
// No more Pending -> DoctorApproved -> Staff Confirmed chain. The
// appointment is created as Confirmed/Unpaid immediately and the patient
// is sent straight to payment.php.
$reference      = 'APT-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
$type           = 'Teleconsult';
$status         = 'Pending';   
$payment_status = 'Unpaid';

$stmt = $conn->prepare(
    "INSERT INTO appointments
        (patient_id, doctor_id, appointment_date, appointment_time, type, department,
         notes, reason, attachment_path, attachment_type, attachment_ocr_text,
         status, payment_status, reference_no)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$bindTypes = 'ii' . str_repeat('s', 12); // patient_id,doctor_id are int; the rest are strings
$stmt->bind_param(
    $bindTypes,
    $patient_id, $doctor_id, $date, $time, $type, $department,
    $notes, $reason, $attachment_path, $attachment_type, $attachment_ocr,
    $status, $payment_status, $reference
);

if (!$stmt->execute()) {
    $_SESSION['toast_error'] = 'Could not create the appointment: ' . $stmt->error;
    header('Location: router.php?page=booking/step4_review'); exit;
}

$appt_id = $conn->insert_id;
unset($_SESSION['booking']); // wizard state no longer needed

header('Location: ../router.php?page=pay&appt_id=' . $appt_id);

exit;