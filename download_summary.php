<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);
if (!$appt_id) { header('Location: /'); exit; }

// Allow both staff/doctor and patient to access
// Check if appointment belongs to this user
$row = null;

// Try staff/doctor lookup
if (isset($staff_id)) {
    $row = $conn->query("SELECT summary_pdf_path, consultation_summary FROM appointments WHERE id=$appt_id")->fetch_assoc();
} elseif (isset($doctor_id)) {
    $row = $conn->query("SELECT summary_pdf_path, consultation_summary FROM appointments WHERE id=$appt_id AND doctor_id=$doctor_id")->fetch_assoc();
} elseif (isset($patient_id)) {
    $row = $conn->query("SELECT summary_pdf_path, consultation_summary FROM appointments WHERE id=$appt_id AND patient_id=$patient_id")->fetch_assoc();
}

if (!$row || empty($row['summary_pdf_path'])) {
    echo '<p style="font-family:sans-serif;padding:2rem;color:#c33;">Summary not available yet. Please check back shortly after your consultation.</p>';
    exit;
}

$file = __DIR__ . '/consultation_summaries/' . $row['summary_pdf_path'];
if (!file_exists($file)) {
    echo '<p style="font-family:sans-serif;padding:2rem;color:#c33;">PDF file not found. Please contact support.</p>';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Teleconsultation_Summary_Report_' . $appt_id . '.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);