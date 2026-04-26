<?php
require_once __DIR__ . '/process_consultation_v2.php';
__halt_compiler();

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
           a.chat_log                AS existing_chat,
           a.consultation_transcript AS existing_transcript,
           a.summary_session_key     AS existing_session_key,
           a.consultation_summary    AS consultation_summary,
           p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.id = $appt_id
")->fetch_assoc();

if (!$row) exit;

// ── 2. Session-based deduplication ───────────────────────────────────────
$session_timeout = 15 * 60;
$current_session = (int)(floor(time() / $session_timeout) * $session_timeout);
$session_key     = $appt_id . '_' . $current_session;
$is_same_session = ($row['existing_session_key'] === $session_key);
$session_changed = ($row['existing_session_key'] !== $session_key);

// ── 3. Transcribe audio with Whisper ─────────────────────────────────────
$new_transcript = '';

if (!empty($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
    $tmp = sys_get_temp_dir() . "/consult_{$appt_id}_" . time() . ".webm";
    // ── 8. Save summary as doctor-review draft (not yet published) ────────────
    if ($role === 'doctor') {
        $dir = __DIR__ . '/consultation_summaries/';
        $old_file = $dir . "summary_{$appt_id}.pdf";
        if (file_exists($old_file)) @unlink($old_file);

        debug_log("Saving DRAFT summary (doctor review required), length=" . strlen($summary));
        $stmt2 = $conn->prepare("\n        UPDATE appointments\n        SET consultation_summary = ?, summary_pdf_path = NULL\n        WHERE id = ?\n    ");
        $stmt2->bind_param("si", $summary, $appt_id);
        $stmt2->execute();
    } else {
        $stmt2 = $conn->prepare("\n        UPDATE appointments\n        SET consultation_summary = ?\n        WHERE id = ?\n    ");
        $stmt2->bind_param("si", $summary, $appt_id);
        $stmt2->execute();
    }
}

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
           a.chat_log                AS existing_chat,
           a.consultation_transcript AS existing_transcript,
           a.summary_session_key     AS existing_session_key,
           a.consultation_summary    AS consultation_summary,
           p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.id = $appt_id
")->fetch_assoc();

if (!$row) exit;

// ── 2. Session-based deduplication ───────────────────────────────────────
$session_timeout = 15 * 60;
$current_session = (int)(floor(time() / $session_timeout) * $session_timeout);
$session_key     = $appt_id . '_' . $current_session;
$is_same_session = ($row['existing_session_key'] === $session_key);
$session_changed = ($row['existing_session_key'] !== $session_key);

// ── 3. Transcribe audio with Whisper ─────────────────────────────────────
$new_transcript = '';

if (!empty($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
    $tmp = sys_get_temp_dir() . "/consult_{$appt_id}_" . time() . ".webm";
// ── 8. Save summary as doctor-review draft (not yet published) ────────────
if ($role === 'doctor') {
    $dir = __DIR__ . '/consultation_summaries/';
    $old_file = $dir . "summary_{$appt_id}.pdf";
    if (file_exists($old_file)) @unlink($old_file);

    debug_log("Saving DRAFT summary (doctor review required), length=" . strlen($summary));
    $stmt2 = $conn->prepare("\n        UPDATE appointments\n        SET consultation_summary = ?, summary_pdf_path = NULL\n        WHERE id = ?\n    ");
    $stmt2->bind_param("si", $summary, $appt_id);
    $stmt2->execute();
} else {
    $stmt2 = $conn->prepare("\n        UPDATE appointments\n        SET consultation_summary = ?\n        WHERE id = ?\n    ");
    $stmt2->bind_param("si", $summary, $appt_id);
    $stmt2->execute();
}

            }

            if ($http_code === 404) {
                debug_log("ERROR 404: Model not found. Check model name in URL.");
                break;
            }

            if (($http_code === 429 || $http_code === 503) && $attempt < $max_retries) {
                $wait = $attempt * 15;
                debug_log("HTTP $http_code — waiting {$wait}s before retry...");
                sleep($wait);
            } else {
                break;
            }
        }

        if (!$summary) {
            debug_log("No summary from Gemini after $max_retries attempts. Last HTTP: $http_code");
            $summary = "Summary generation failed (HTTP $http_code). Please try ending the consultation again.";
        }
    }

} elseif (empty($contextParts) && !$summary) {
    debug_log("No context parts, skipping summary generation");
    $summary = "No consultation content was captured for this session yet. A full summary will be generated once the consultation is completed.";
}

// ── 8. Generate PDF ───────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

function enc(string $s): string {
    $s = preg_replace('/\*\*(.*?)\*\*/', '$1', $s);
    $s = preg_replace('/#{1,6}\s*/', '', $s);
    $s = str_replace(['—', '–', '−'], '-', $s);
    $s = str_replace(['á','à','â','ä'], 'a', $s);
    $s = str_replace(['é','è','ê','ë'], 'e', $s);
    $s = str_replace(['í','ì','î','ï'], 'i', $s);
    $s = str_replace(['ó','ò','ô','ö'], 'o', $s);
    $s = str_replace(['ú','ù','û','ü'], 'u', $s);
    $s = str_replace(['ñ','Ñ'], 'n', $s);
    $s = str_replace(['Á','É','Í','Ó','Ú'], ['A','E','I','O','U'], $s);
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
    'Patient'   => enc($row['patient_name']),
    'Doctor'    => enc('Dr. ' . $row['doctor_name'] . ' - ' . ($row['specialty'] ?? '')),
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

// Summary heading
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(36, 68, 65);
$pdf->Cell(0, 8, 'Consultation Summary', 0, 1);
$pdf->SetDrawColor(36, 68, 65);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(3);

// ── FIXED: Parse summary into header+body pairs ───────────────────────────
$lines = explode("\n", $summary);
$i = 0;
while ($i < count($lines)) {
    $line = trim($lines[$i]);
    if ($line === '') { $i++; continue; }

    $isHeader = preg_match('/^\d+\.\s+/', $line)
             || (str_starts_with($line, '**') && str_ends_with($line, '**'));

    if ($isHeader) {
        $pdf->Ln(2);
        $clean = trim(preg_replace('/^\d+\.\s+/', '', $line), '*');
        $pdf->SetFillColor(230, 242, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(36, 68, 65);
        $pdf->Cell(0, 7, enc($clean), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);

        // Collect all body lines under this header until the next numbered section
        $i++;
        $bodyLines = [];
        while ($i < count($lines)) {
            $next = trim($lines[$i]);
            if (preg_match('/^\d+\.\s+/', $next)) break;
            $bodyLines[] = $next;
            $i++;
        }
        $body = trim(implode("\n", $bodyLines));
        if ($body === '') $body = 'Not discussed.';

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(0, 5, enc($body), 0, 'L');
        $pdf->Ln(1);
    } else {
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(0, 5, enc($line), 0, 'L');
        $i++;
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
            $pdf->Cell(0, 5, enc($tline), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->Ln(1);
        } else {
            $pdf->MultiCell(0, 5, enc($tline), 0, 'L');
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
            $pdf->Cell(0, 5, enc($cl), 0, 1, 'C');
            $pdf->Ln(1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            continue;
        }
        if (preg_match('/^\[(.+?)\]\s(.+?):\s(.+)$/', $cl, $m)) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(36, 68, 65);
            $pdf->Cell(28, 5, '[' . enc($m[1]) . ']', 0, 0);
            $pdf->SetTextColor(0, 80, 60);
            $pdf->Cell(50, 5, enc($m[2]) . ':', 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->MultiCell(0, 5, enc($m[3]), 0, 'L');
        } else {
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->MultiCell(0, 5, enc($cl), 0, 'L');
        }
    }
}

// Fixed filename — overwrites previous PDF for same appointment
$dir = __DIR__ . '/consultation_summaries/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$filename = "summary_{$appt_id}.pdf";

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