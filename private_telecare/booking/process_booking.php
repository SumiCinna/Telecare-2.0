<?php
// private_telecare/booking/process_booking.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department', 'doctor_id', 'appt_date', 'appt_time']);
$b = $_SESSION['booking'];

// ── Payment method chosen in step 4 (Regular / YAKAP / HMO) ──
$payMethod = $b['payment_method'] ?? '';
$cov       = $b['coverage'] ?? [];
if (!in_array($payMethod, ['Regular', 'YAKAP', 'HMO'], true)) {
    header('Location: router.php?page=booking/step4_payment_method'); exit;
}
$isCoverage = $payMethod !== 'Regular';
if ($isCoverage && (($cov['method'] ?? '') !== $payMethod || empty($cov['consent']))) {
    header('Location: router.php?page=booking/step4_payment_method'); exit;
}
$hasPM = booking_ensure_payment_schema($conn); // adds payment_method column + coverage tables if missing
if ($isCoverage && !$hasPM) {
    $_SESSION['toast_error'] = 'YAKAP / HMO booking is not set up on the database yet. Please contact support.';
    header('Location: router.php?page=booking/step4_review'); exit;
}

$doctor_id  = (int)$b['doctor_id'];
$department = $b['department'];
$date       = $b['appt_date'];
$time       = $b['appt_time'];

$conn->query("CREATE TABLE IF NOT EXISTS doctor_schedule_settings (
    doctor_id INT NOT NULL,
    consultation_duration SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    appointment_interval SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    break_start TIME NULL,
    break_end TIME NULL,
    consultation_types VARCHAR(255) NOT NULL DEFAULT 'In-person',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (doctor_id),
    CONSTRAINT doctor_schedule_settings_doctor_fk FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

$date_obj = DateTime::createFromFormat('!Y-m-d', $date);
$time_value = substr($time, 0, 5);
$scheduleSettings = ['consultation_duration' => 30, 'break_start' => null, 'break_end' => null];
$settingsStmt = $conn->prepare('SELECT consultation_duration, break_start, break_end FROM doctor_schedule_settings WHERE doctor_id=?');
$settingsStmt->bind_param('i', $doctor_id);
$settingsStmt->execute();
if ($savedSettings = $settingsStmt->get_result()->fetch_assoc()) {
    $scheduleSettings = array_merge($scheduleSettings, $savedSettings);
}
$schedule_ok = $date_obj && $date_obj->format('Y-m-d') === $date && preg_match('/^\d{2}:\d{2}$/', $time_value) === 1;
if ($schedule_ok) {
    $day_name = $date_obj->format('l');
    $schedule_stmt = $conn->prepare("SELECT start_time, end_time FROM doctor_schedules WHERE doctor_id=? AND day_of_week=?");
    $schedule_stmt->bind_param('is', $doctor_id, $day_name);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    $schedule_ok = false;
    while ($schedule = $schedule_result->fetch_assoc()) {
        $slotStart = strtotime($date . ' ' . $time_value);
        $slotEnd = $slotStart + ((int)$scheduleSettings['consultation_duration'] * 60);
        $scheduleEnd = strtotime($date . ' ' . substr($schedule['end_time'], 0, 5));
        $breakStart = $scheduleSettings['break_start'] ? strtotime($date . ' ' . substr($scheduleSettings['break_start'], 0, 5)) : null;
        $breakEnd = $scheduleSettings['break_end'] ? strtotime($date . ' ' . substr($scheduleSettings['break_end'], 0, 5)) : null;
        $inBreak = $breakStart && $breakEnd && $slotStart < $breakEnd && $slotEnd > $breakStart;
        if ($time_value >= substr($schedule['start_time'], 0, 5) && $slotEnd <= $scheduleEnd && !$inBreak) {
            $schedule_ok = true;
            break;
        }
    }
    $schedule_stmt->close();
    if ($date < date('Y-m-d') || ($date === date('Y-m-d') && $time_value <= date('H:i'))) $schedule_ok = false;
}
if (!$schedule_ok) {
    $_SESSION['toast_error'] = 'Selected date and time are not available for this doctor.';
    header('Location: router.php?page=booking/step3_schedule'); exit;
}

$dup = $conn->prepare("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND appointment_time=? AND status NOT IN ('Cancelled')");
$dup->bind_param("iss", $doctor_id, $date, $time);
$dup->execute();
if ($dup->get_result()->fetch_assoc()) {
    $_SESSION['toast_error'] = 'That time slot was just taken. Please pick another.';
    header('Location: router.php?page=booking/step3_schedule'); exit;
}

// ── Create the appointment ──
//  Regular   : Pending/Unpaid, patient continues to PayMongo (pay.php).
//  YAKAP/HMO : no online payment. Created as Confirmed right away; the coverage details
//              are saved with it (appointment_yakap / appointment_hmo) for the clinic to verify.
$reference      = 'APT-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
$type           = 'Teleconsult';
$status         = $isCoverage ? 'Confirmed' : 'Pending';
$payment_status = 'Unpaid';

$cols  = 'patient_id, doctor_id, appointment_date, appointment_time, type, department, notes, reason, attachment_path, attachment_type, attachment_ocr_text, status, payment_status, reference_no';
$marks = '?,?,?,?,?,?,?,?,?,?,?,?,?,?';
$vals  = [$patient_id, $doctor_id, $date, $time, $type, $department,
          $notes, $reason, $attachment_path, $attachment_type, $attachment_ocr,
          $status, $payment_status, $reference];
$bindTypes = 'ii' . str_repeat('s', 12); // patient_id, doctor_id are int; the rest are strings
if ($hasPM) {
    $cols .= ', payment_method'; $marks .= ',?'; $vals[] = $payMethod; $bindTypes .= 's';
}

$patient_name = $p['full_name'] ?? '';
$patient_dob  = !empty($p['date_of_birth']) ? $p['date_of_birth'] : null;

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("INSERT INTO appointments ($cols) VALUES ($marks)");
    $stmt->bind_param($bindTypes, ...$vals);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error);
    $appt_id = (int)$conn->insert_id;

    if ($payMethod === 'YAKAP') {
        $pin     = preg_replace('/\D/', '', (string)($cov['philhealth_pin'] ?? ''));
        $member  = (string)($cov['member_type'] ?? '');
        $contact = (string)($cov['contact_number'] ?? '');
        $addr    = (string)($cov['address'] ?? '');
        $clinic  = (string)($cov['yakap_clinic'] ?? '');
        $emp     = (string)($cov['empanelment_status'] ?? '');
        $fpe     = (string)($cov['fpe_status'] ?? '');
        $ys = $conn->prepare("INSERT INTO appointment_yakap
            (appointment_id, philhealth_pin, patient_name, date_of_birth, member_type, contact_number, address, yakap_clinic, empanelment_status, fpe_status, consent, consent_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,1,NOW())");
        $ys->bind_param('isssssssss', $appt_id, $pin, $patient_name, $patient_dob, $member, $contact, $addr, $clinic, $emp, $fpe);
        if (!$ys->execute()) throw new RuntimeException($ys->error);
    } elseif ($payMethod === 'HMO') {
        $prov    = (string)($cov['hmo_provider'] ?? '');
        $mid     = (string)($cov['hmo_member_id'] ?? '');
        $member  = (string)($cov['member_type'] ?? '');
        $princ   = (string)($cov['principal_member_name'] ?? '');
        $comp    = ($cov['company_employer'] ?? '') !== '' ? $cov['company_employer'] : null;
        $plan    = ($cov['hmo_plan'] ?? '') !== '' ? $cov['hmo_plan'] : null;
        $contact = (string)($cov['contact_number'] ?? '');
        $service = (string)($cov['service_type'] ?? '');
        $loa     = ($cov['loa_number'] ?? '') !== '' ? $cov['loa_number'] : null;
        $hs = $conn->prepare("INSERT INTO appointment_hmo
            (appointment_id, hmo_provider, hmo_member_id, member_type, principal_member_name, company_employer, hmo_plan, patient_name, date_of_birth, contact_number, service_type, loa_number, consent, consent_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,NOW())");
        $hs->bind_param('isssssssssss', $appt_id, $prov, $mid, $member, $princ, $comp, $plan, $patient_name, $patient_dob, $contact, $service, $loa);
        if (!$hs->execute()) throw new RuntimeException($hs->error);
    }

    $conn->commit();
} catch (Throwable $ex) {
    try { $conn->rollback(); } catch (Throwable $ignore) {}
    error_log('process_booking failed: ' . $ex->getMessage());
    $_SESSION['toast_error'] = 'Could not create the appointment. Please try again.';
    header('Location: router.php?page=booking/step4_review'); exit;
}

unset($_SESSION['booking']); // wizard state no longer needed

if ($isCoverage) {
    header('Location: ../router.php?page=booking/success&appt_id=' . $appt_id);
} else {
    header('Location: ../router.php?page=pay&appt_id=' . $appt_id);
}
exit;
