<?php
// private_telecare/download_summary.php
// download_summary.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/auth_any.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);
if (!$appt_id) { header('Location: /'); exit; }

// Allow both staff/doctor and patient to access
// Check if appointment belongs to this user
$row = null;

// Try staff/doctor lookup
if (isset($staff_id) && $staff_id) {
    $row = $conn->query("SELECT summary_pdf_path, consultation_summary FROM appointments WHERE id=$appt_id")->fetch_assoc();
} elseif (isset($doctor_id) && $doctor_id) {
    $row = $conn->query("SELECT summary_pdf_path, consultation_summary FROM appointments WHERE id=$appt_id AND doctor_id=$doctor_id")->fetch_assoc();
} elseif (isset($patient_id) && $patient_id) {
    $row = $conn->query("SELECT summary_pdf_path, consultation_summary FROM appointments WHERE id=$appt_id AND patient_id=$patient_id")->fetch_assoc();
}

if (!$row) {
    echo '<p style="font-family:sans-serif;padding:2rem;color:#c33;">Summary not available yet. Please check back shortly after your consultation.</p>';
    exit;
}

// ── Always prefer the real PDF on disk, regardless of what summary_pdf_path
// says in the DB. A stored value like 'TEXT_CONFIRMED' (set elsewhere when a
// doctor edits/confirms the summary text) is NOT a filename and must never
// stop us from serving the actual generated PDF if one exists — that PDF is
// what doctor and patient should both see. ──
$file = __DIR__ . '/../consultation_summaries/summary_' . $appt_id . '.pdf';

if (file_exists($file)) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="Teleconsultation_Summary_Report_' . $appt_id . '.pdf"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// No PDF on disk yet — fall back to plain text view of whatever summary text we have.
$safe = nl2br(htmlspecialchars($row['consultation_summary'] ?? 'No summary content.'));
$safe = preg_replace('/^(Filipino:.*?)(<br\s*\/?>)?\r?$/mi', '<span style="font-style:italic;color:#1e5a96;">$1</span>$2', $safe);
echo '<!DOCTYPE html><html><head>
  <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=2">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=2">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2"><meta charset="UTF-8"><title>Consultation Summary</title></head>';
echo '<body style="font-family:Arial,sans-serif;max-width:920px;margin:2rem auto;padding:0 1rem;color:#1f2937;line-height:1.65;">';
echo '<h2 style="margin-bottom:0.6rem;color:#244441;">Consultation Summary</h2>';
echo '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:1rem 1.1rem;">' . $safe . '</div>';
echo '</body></html>';