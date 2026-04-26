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

$isTextPublished = ($row['summary_pdf_path'] === 'TEXT_CONFIRMED');

if ($isTextPublished) {
    $safe = nl2br(htmlspecialchars($row['consultation_summary'] ?? 'No summary content.'));
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Consultation Summary</title></head>';
    echo '<body style="font-family:Arial,sans-serif;max-width:920px;margin:2rem auto;padding:0 1rem;color:#1f2937;line-height:1.65;">';
    echo '<h2 style="margin-bottom:0.6rem;color:#244441;">Consultation Summary</h2>';
    echo '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:1rem 1.1rem;">' . $safe . '</div>';
    echo '</body></html>';
    exit;
}

$file = __DIR__ . '/consultation_summaries/' . $row['summary_pdf_path'];
if (!file_exists($file)) {
    $safe = nl2br(htmlspecialchars($row['consultation_summary'] ?? 'No summary content.'));
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Consultation Summary</title></head>';
    echo '<body style="font-family:Arial,sans-serif;max-width:920px;margin:2rem auto;padding:0 1rem;color:#1f2937;line-height:1.65;">';
    echo '<h2 style="margin-bottom:0.6rem;color:#244441;">Consultation Summary</h2>';
    echo '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:1rem 1.1rem;">' . $safe . '</div>';
    echo '</body></html>';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Teleconsultation_Summary_Report_' . $appt_id . '.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);