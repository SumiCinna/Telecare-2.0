<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

header('Content-Type: application/json');

$appt_id = (int)($_GET['appt_id'] ?? 0);

if (!$appt_id) {
    http_response_code(400);
    echo json_encode(['done' => false, 'error' => 'Missing appointment ID']);
    exit;
}

// Check if summary exists
$stmt = $conn->prepare("
    SELECT consultation_summary, summary_pdf_path 
    FROM appointments 
    WHERE id = ?
");
$stmt->bind_param("i", $appt_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo json_encode(['done' => false, 'error' => 'Appointment not found']);
    exit;
}

// Also check if there's actual content (not just placeholder text)
$has_real_summary = !empty($row['consultation_summary']) 
    && $row['consultation_summary'] !== 'Summary generation is in progress. Please check back shortly.'
    && strpos($row['consultation_summary'], 'No consultation content') === false;

// Summary is done if PDF has been generated and path exists.
$is_done = !empty($row['summary_pdf_path']);

echo json_encode([
    'done' => $is_done,
    'has_summary' => $has_real_summary,
    'has_pdf' => $is_done
]);
?>
