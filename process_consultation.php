<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$appt_id  = (int)($_POST['appt_id']  ?? 0);
$new_chat = trim($_POST['chat_log']  ?? '');
$role     = trim($_POST['role']      ?? '');

if (!$appt_id) { http_response_code(400); exit; }

// ── Respond immediately so browser redirects without waiting ─────────────
// This runs the rest of the script in the background
http_response_code(200);
echo json_encode(['status' => 'processing']);

// Flush output to browser so it can redirect
if (ob_get_level()) ob_end_flush();
flush();

// Keep script running after browser disconnects
ignore_user_abort(true);
set_time_limit(300);

// ── 1. Fetch appointment details + existing accumulated data ──────────────
$row = $conn->query("
    SELECT a.appointment_date, a.appointment_time, a.type,
           a.chat_log               AS existing_chat,
           a.consultation_transcript AS existing_transcript,
           p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.id = $appt_id
")->fetch_assoc();

if (!$row) exit;

// ── 2. Transcribe audio with Whisper (tiny model = fast) ──────────────────
$new_transcript = '';

if (!empty($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
    $tmp = sys_get_temp_dir() . "/consult_{$appt_id}_" . time() . ".webm";
    move_uploaded_file($_FILES['audio']['tmp_name'], $tmp);

    $ch = curl_init('http://localhost:9000/transcribe');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['audio' => new CURLFile($tmp, 'audio/webm', 'consultation.webm')],
        CURLOPT_TIMEOUT        => 120,
    ]);
    $resp      = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!$curlError) {
        $decoded        = json_decode($resp, true);
        $new_transcript = trim($decoded['text'] ?? '');
    }

    if (file_exists($tmp)) unlink($tmp);
}

// ── 3. Append new data to existing (with session separator) ───────────────
$sep = "\n[--- New Session ---]\n";

$existing_transcript = trim($row['existing_transcript'] ?? '');
$existing_chat       = trim($row['existing_chat']       ?? '');

// Only append if new content exists and is different
$full_transcript = $existing_transcript;
if ($new_transcript && $new_transcript !== $existing_transcript) {
    $full_transcript = $existing_transcript
        ? $existing_transcript . $sep . $new_transcript
        : $new_transcript;
}

$full_chat = $existing_chat;
if ($new_chat && $new_chat !== $existing_chat) {
    $full_chat = $existing_chat
        ? $existing_chat . $sep . $new_chat
        : $new_chat;
}

// ── 4. Save accumulated raw data to DB immediately ────────────────────────
$stmt = $conn->prepare("
    UPDATE appointments
    SET chat_log = ?, consultation_transcript = ?
    WHERE id = ?
");
$stmt->bind_param("ssi", $full_chat, $full_transcript, $appt_id);
$stmt->execute();

// ── 5. Build prompt for Ollama ────────────────────────────────────────────
$contextParts = [];
if ($full_transcript) $contextParts[] = "VOICE TRANSCRIPT:\n$full_transcript";
if ($full_chat)       $contextParts[] = "CHAT LOG:\n$full_chat";

if (empty($contextParts)) exit;

$context = implode("\n\n", $contextParts);

$prompt = "You are a medical documentation assistant for TELE-CARE teleconsultation.

Doctor: Dr. {$row['doctor_name']} ({$row['specialty']})
Patient: {$row['patient_name']}
Date: " . date('F j, Y', strtotime($row['appointment_date'])) . "
Time: " . date('g:i A', strtotime($row['appointment_time'])) . "

$context

Write a structured medical summary with these sections:
1. Chief Complaint
2. Symptoms Discussed
3. Doctor's Assessment
4. Diagnosis (if mentioned)
5. Treatment Plan / Prescriptions
6. Follow-up Instructions

If not discussed, write: Not discussed. Be concise and professional.";

// ── 6. Summarize with Ollama ──────────────────────────────────────────────
$ch = curl_init('http://localhost:11434/api/generate');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        'model'  => 'gemma3:4b',
        'prompt' => $prompt,
        'stream' => false,
    ]),
    CURLOPT_TIMEOUT => 120,
]);
$resp    = curl_exec($ch);
curl_close($ch);

$decoded = json_decode($resp, true);
$summary = $decoded['response'] ?? 'Summary could not be generated.';

// ── 7. Generate PDF ───────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

class ConsultationPDF extends FPDF {
    function Header() {
        $this->SetFillColor(36, 68, 65);
        $this->Rect(0, 0, 210, 28, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 16);
        $this->SetY(7);
        $this->Cell(0, 8, 'TELE-CARE', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 6, 'Teleconsultation Summary Report', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetY(34);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10,
            'AI-generated summary — to be reviewed by the attending physician. Page ' . $this->PageNo(),
            0, 0, 'C');
    }
}

$pdf = new ConsultationPDF('P', 'mm', 'A4');
$pdf->SetMargins(20, 36, 20);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Info box
$pdf->SetFillColor(240, 247, 245);
$pdf->SetDrawColor(200, 220, 215);
$pdf->Rect(20, 36, 170, 38, 'DF');
$fields = [
    'Patient'   => $row['patient_name'],
    'Doctor'    => 'Dr. ' . $row['doctor_name'] . ' — ' . ($row['specialty'] ?? ''),
    'Date/Time' => date('F j, Y', strtotime($row['appointment_date'])) . ' at ' . date('g:i A', strtotime($row['appointment_time'])),
    'Updated'   => date('F j, Y g:i A'),
];
$y = 40;
foreach ($fields as $label => $value) {
    $pdf->SetXY(25, $y);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(36, 68, 65);
    $pdf->Cell(35, 6, $label . ':', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, $value, 0, 1);
    $y += 7;
}
$pdf->SetY($y + 4);

// Summary
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(36, 68, 65);
$pdf->Cell(0, 8, 'Consultation Summary', 0, 1);
$pdf->SetDrawColor(36, 68, 65);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(3);

foreach (explode("\n", $summary) as $line) {
    $line = trim($line);
    if ($line === '') { $pdf->Ln(2); continue; }
    $isHeader = preg_match('/^\d+\.\s+/', $line)
             || (str_starts_with($line, '**') && str_ends_with($line, '**'));
    if ($isHeader) {
        $pdf->Ln(2);
        $clean = trim(preg_replace('/^\d+\.\s+/', '', $line), '*');
        $pdf->SetFillColor(230, 242, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(36, 68, 65);
        $pdf->Cell(0, 7, $clean, 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(0, 5, $line, 0, 'L');
    }
}

// Voice transcript
if ($full_transcript) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(36, 68, 65);
    $pdf->Cell(0, 8, 'Voice Transcript', 0, 1);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(3);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->MultiCell(0, 5, $full_transcript, 0, 'L');
}

// Chat log
if ($full_chat) {
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(36, 68, 65);
    $pdf->Cell(0, 8, 'In-Call Chat Log', 0, 1);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(3);
    foreach (explode("\n", $full_chat) as $cl) {
        $cl = trim($cl);
        if ($cl === '') continue;
        if (str_starts_with($cl, '[---')) {
            $pdf->Ln(1);
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->Cell(0, 5, $cl, 0, 1, 'C');
            $pdf->Ln(1);
            continue;
        }
        if (preg_match('/^\[(.+?)\]\s(.+?):\s(.+)$/', $cl, $m)) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(36, 68, 65);
            $pdf->Cell(28, 5, '[' . $m[1] . ']', 0, 0);
            $pdf->SetTextColor(0, 80, 60);
            $pdf->Cell(50, 5, $m[2] . ':', 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->MultiCell(0, 5, $m[3], 0, 'L');
        } else {
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->MultiCell(0, 5, $cl, 0, 'L');
        }
    }
}

// Fixed filename — overwrites previous PDF for same appointment
$dir = __DIR__ . '/consultation_summaries/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$filename = "summary_{$appt_id}.pdf";
$pdf->Output('F', $dir . $filename);

// Save to DB
$stmt2 = $conn->prepare("
    UPDATE appointments
    SET consultation_summary = ?, summary_pdf_path = ?
    WHERE id = ?
");
$stmt2->bind_param("ssi", $summary, $filename, $appt_id);
$stmt2->execute();