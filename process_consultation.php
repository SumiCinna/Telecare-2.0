<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';
// process_consultation.php

// ── Enable error logging ─────────────────────────────────────────────────
$log_file = __DIR__ . '/logs/consultation_debug.log';
@mkdir(dirname($log_file), 0755, true);

function debug_log($msg) {
    global $log_file;
    file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

debug_log("=== START PROCESS CONSULTATION ===");

$appt_id  = (int)($_POST['appt_id']  ?? 0);
$new_chat = trim($_POST['chat_log']  ?? '');
$role     = trim($_POST['role']      ?? '');

debug_log("appt_id=$appt_id, role=$role, chat_length=" . strlen($new_chat));

if (!$appt_id) { 
    debug_log("ERROR: No appointment ID");
    http_response_code(400); 
    exit; 
}

// ── Respond immediately so browser redirects without waiting ─────────────
http_response_code(200);
echo json_encode(['status' => 'processing']);

if (ob_get_level()) ob_end_flush();
flush();

ignore_user_abort(true);
set_time_limit(300);

// ── 1. Fetch appointment details + existing accumulated data ──────────────
$row = $conn->query("
    SELECT a.appointment_date, a.appointment_time, a.type,
           a.chat_log               AS existing_chat,
           a.consultation_transcript AS existing_transcript,
           a.summary_session_key    AS existing_session_key,
           p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.id = $appt_id
")->fetch_assoc();

if (!$row) exit;

// ── 2. Session-based deduplication ───────────────────────────────────────
// Use a unique session key for EACH call/rejoin based on appointment + connection time.
// If user disconnects and reconnects within 15 minutes, it's same session (no separator).
// If they rejoin after 15 minutes, it's a new session (add separator + regenerate summary).
$session_timeout = 15 * 60; // 15 minutes to rejoin same session
$current_session  = (int)(floor(time() / $session_timeout) * $session_timeout);
$session_key      = $appt_id . '_' . $current_session;
$is_same_session  = ($row['existing_session_key'] === $session_key);

// ── 3. Transcribe audio with Whisper ─────────────────────────────────────
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

    if (!$curlError && $resp) {
        $decoded        = json_decode($resp, true);
        $new_transcript = trim($decoded['text'] ?? '');
    }

    if (file_exists($tmp)) unlink($tmp);
}

// ── 4. Merge new data into existing ──────────────────────────────────────
// Same-session (doctor + patient leaving same call): merge with a newline.
// New session (genuine rejoin): add a visible separator.
$sep_same    = "\n";
$sep_new     = "\n\n[--- Rejoined Session — " . date('M j, Y g:i A') . " ---]\n";

$existing_transcript = trim($row['existing_transcript'] ?? '');
$existing_chat       = trim($row['existing_chat']       ?? '');

// Merge transcript
$full_transcript = $existing_transcript;
if ($new_transcript) {
    // Avoid exact duplicate (e.g. same audio file submitted twice)
    $trimmed_new = trim($new_transcript);
    if (!str_ends_with($existing_transcript, $trimmed_new)) {
        if (!$existing_transcript) {
            $full_transcript = $trimmed_new;
        } elseif ($is_same_session) {
            $full_transcript = $existing_transcript . $sep_same . $trimmed_new;
        } else {
            $full_transcript = $existing_transcript . $sep_new . $trimmed_new;
        }
    }
}

// Merge chat
$full_chat = $existing_chat;
if ($new_chat) {
    $trimmed_chat = trim($new_chat);
    if (!str_ends_with($existing_chat, $trimmed_chat)) {
        if (!$existing_chat) {
            $full_chat = $trimmed_chat;
        } elseif ($is_same_session) {
            $full_chat = $existing_chat . $sep_same . $trimmed_chat;
        } else {
            $full_chat = $existing_chat . $sep_new . $trimmed_chat;
        }
    }
}

// ── 5. Save raw data + session key to DB immediately ─────────────────────
$stmt = $conn->prepare("
    UPDATE appointments
    SET chat_log = ?, consultation_transcript = ?, summary_session_key = ?
    WHERE id = ?
");
$stmt->bind_param("sssi", $full_chat, $full_transcript, $session_key, $appt_id);
$stmt->execute();

// ── 6. Build Ollama prompt ────────────────────────────────────────────────
$contextParts = [];
if ($full_transcript) $contextParts[] = "VOICE TRANSCRIPT:\n$full_transcript";
if ($full_chat)       $contextParts[] = "CHAT LOG:\n$full_chat";


$session_changed = ($row['existing_session_key'] !== $session_key);


$has_new_content = (!empty($new_transcript)) || (!empty($new_chat));
$should_regenerate = $has_new_content || ($session_changed && !empty($contextParts));

$summary = $row['consultation_summary'] ?? null; 

if ($should_regenerate && !empty($contextParts)) {
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

If a section was not discussed, write: Not discussed. Be concise and professional.
If there are multiple sessions (separated by '--- Rejoined Session ---'), consolidate the information across all sessions into one coherent summary.";

    // ── 7. Summarize with Ollama ──────────────────────────────────────────
    debug_log("Calling Ollama at localhost:11434...");
    
    // Try to connect to Ollama with longer timeout
    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'model'  => 'qwen2.5:7b',
            'prompt' => $prompt,
            'stream' => false,
        ]),
        CURLOPT_TIMEOUT => 180, // Increased to 3 minutes
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp    = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    debug_log("Ollama HTTP code: $http_code, error: $curl_error, response_len: " . strlen($resp ?? ''));
    
    if ($curl_error) {
        debug_log("CURL ERROR: $curl_error - Ollama may not be running!");
    }

    $summary = '';
    if (!$curl_error && $http_code == 200 && $resp) {
        $decoded = json_decode($resp, true);
        $summary = trim($decoded['response'] ?? '');
        if ($summary) {
            debug_log("SUCCESS: Summary generated, length: " . strlen($summary));
        }
    }
    
    if (!$summary) {
        debug_log("No summary from Ollama, will retry/generate later");
        $summary = "Summary generation is in progress. Please check back shortly.";
    }
} elseif (empty($contextParts) && !$summary) {
    // Only show placeholder if there's no existing summary AND no content at all
    debug_log("No context parts, skipping summary generation");
    $summary = "No consultation content was captured for this session yet. A full summary will be generated once the consultation is completed.";
}

// ── 6b. Track if new data was added (transcript OR chat is new) ───────────
$has_new_data = (!empty($new_transcript) && $new_transcript !== trim($row['existing_transcript'] ?? '')) 
             || (!empty($new_chat) && $new_chat !== trim($row['existing_chat'] ?? ''));

// ── 8. Generate PDF ───────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

function enc(string $s): string {
    // Strip markdown bold (**text**) and convert UTF-8 to FPDF-compatible Latin-1
    $s = preg_replace('/\*\*(.*?)\*\*/', '$1', $s);
    return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
}

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
    'Generated' => date('F j, Y g:i A') . ' (latest session)',
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
    foreach (explode("\n", $full_transcript) as $tline) {
        $tline = trim($tline);
        if ($tline === '') { $pdf->Ln(1); continue; }
        if (str_starts_with($tline, '[---')) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'BI', 8);
            $pdf->SetTextColor(36, 68, 65);
            $pdf->Cell(0, 5, $tline, 0, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->Ln(1);
        } else {
            $pdf->MultiCell(0, 5, $tline, 0, 'L');
        }
    }
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
            $pdf->SetFont('Arial', 'BI', 8);
            $pdf->SetTextColor(36, 68, 65);
            $pdf->Cell(0, 5, $cl, 0, 1, 'C');
            $pdf->Ln(1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
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

// Fixed filename — overwrites previous PDF for same appointment (always latest)
$dir = __DIR__ . '/consultation_summaries/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$filename = "summary_{$appt_id}.pdf";

// Delete old PDF if session changed (force regeneration on rejoin)
if ($session_changed) {
    $old_file = $dir . $filename;
    if (file_exists($old_file)) @unlink($old_file);
}

$pdf->Output('F', $dir . $filename);

// ── 9. Save summary + PDF path to DB ─────────────────────────────────────
debug_log("Saving summary to DB, filename=$filename, summary_length=" . strlen($summary));
$stmt2 = $conn->prepare("
    UPDATE appointments
    SET consultation_summary = ?, summary_pdf_path = ?
    WHERE id = ?
");
$stmt2->bind_param("ssi", $summary, $filename, $appt_id);
$stmt2->execute();
debug_log("=== END PROCESS CONSULTATION SUCCESS ===");