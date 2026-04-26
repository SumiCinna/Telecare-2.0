<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$log_file = __DIR__ . '/logs/consultation_debug.log';
@mkdir(dirname($log_file), 0755, true);

function debug_log_v2($msg) {
    global $log_file;
    file_put_contents($log_file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

debug_log_v2('=== START PROCESS CONSULTATION V2 (GROQ) ===');

// ── CONFIG ────────────────────────────────────────────────────────────────
$GROQ_API_KEY = $_ENV['GROQ_API_KEY'] ?? '';

$appt_id  = (int)($_POST['appt_id'] ?? 0);
$new_chat = trim($_POST['chat_log'] ?? '');
$role     = trim($_POST['role'] ?? '');

debug_log_v2("appt_id=$appt_id, role=$role, chat_length=" . strlen($new_chat));

if (!$appt_id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Missing appointment ID']);
    exit;
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'processing']);

if (ob_get_level()) ob_end_flush();
flush();

ignore_user_abort(true);
set_time_limit(300);

// ── 1. Fetch appointment ──────────────────────────────────────────────────
$row = $conn->query("
    SELECT a.appointment_date, a.appointment_time,
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

if (!$row) {
    debug_log_v2("Appointment not found: {$appt_id}");
    exit;
}

// ── 2. Session deduplication ──────────────────────────────────────────────
$session_timeout = 15 * 60;
$current_session = (int)(floor(time() / $session_timeout) * $session_timeout);
$session_key     = $appt_id . '_' . $current_session;
$is_same_session = ($row['existing_session_key'] === $session_key);
$session_changed = ($row['existing_session_key'] !== $session_key);

// ── 3. Transcribe with Groq Whisper ──────────────────────────────────────
$new_transcript = '';

if (!empty($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
    $tmp = sys_get_temp_dir() . "/consult_{$appt_id}_" . time() . ".webm";

    if (move_uploaded_file($_FILES['audio']['tmp_name'], $tmp)) {
        debug_log_v2("Sending audio to Groq Whisper...");

        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $GROQ_API_KEY,
            ],
            CURLOPT_POSTFIELDS     => [
                'file'            => new CURLFile($tmp, 'audio/webm', 'consultation.webm'),
                'model'           => 'whisper-large-v3-turbo',
                'response_format' => 'json',
                'language'        => 'en',
                'temperature'     => '0',
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $resp      = curl_exec($ch);
        $curlError = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        debug_log_v2("Groq Whisper HTTP: $http_code, curl_error: $curlError");

        if (!$curlError && $http_code === 200 && $resp) {
            $decoded        = json_decode($resp, true);
            $new_transcript = trim($decoded['text'] ?? '');
            debug_log_v2("Transcribed " . strlen($new_transcript) . " chars");
        } else {
            debug_log_v2("Groq Whisper failed: HTTP $http_code — " . substr($resp ?? '', 0, 200));
        }
    }

    if (isset($tmp) && file_exists($tmp)) unlink($tmp);
}

// ── 4. Merge transcripts & chat ───────────────────────────────────────────
$sep_same = "\n";
$sep_new  = "\n\n[--- Rejoined Session — " . date('M j, Y g:i A') . " ---]\n";

$existing_transcript = trim($row['existing_transcript'] ?? '');
$existing_chat       = trim($row['existing_chat']       ?? '');

$full_transcript = $existing_transcript;
if ($new_transcript) {
    $trimmed_new = trim($new_transcript);
    if (!str_ends_with($existing_transcript, $trimmed_new)) {
        if (!$existing_transcript)          $full_transcript = $trimmed_new;
        elseif ($is_same_session)           $full_transcript = $existing_transcript . $sep_same . $trimmed_new;
        else                                $full_transcript = $existing_transcript . $sep_new  . $trimmed_new;
    }
}

$full_chat = $existing_chat;
if ($new_chat) {
    $trimmed_chat = trim($new_chat);
    if (!str_ends_with($existing_chat, $trimmed_chat)) {
        if (!$existing_chat)                $full_chat = $trimmed_chat;
        elseif ($is_same_session)           $full_chat = $existing_chat . $sep_same . $trimmed_chat;
        else                                $full_chat = $existing_chat . $sep_new  . $trimmed_chat;
    }
}

// ── 5. Save raw data ──────────────────────────────────────────────────────
$stmt = $conn->prepare("
    UPDATE appointments
    SET chat_log = ?, consultation_transcript = ?, summary_session_key = ?
    WHERE id = ?
");
$stmt->bind_param('sssi', $full_chat, $full_transcript, $session_key, $appt_id);
$stmt->execute();

// ── 6. Decide if we should regenerate summary ─────────────────────────────
$contextParts = [];
if ($full_transcript) $contextParts[] = "VOICE TRANSCRIPT:\n$full_transcript";
if ($full_chat)       $contextParts[] = "CHAT LOG:\n$full_chat";

$has_new_content   = (!empty($new_transcript)) || (!empty($new_chat));
$should_regenerate = $has_new_content || ($session_changed && !empty($contextParts));
$summary           = $row['consultation_summary'] ?? null;

// ── 7. Generate summary with Groq LLM ────────────────────────────────────
if ($should_regenerate && !empty($contextParts)) {

    if ($role !== 'doctor') {
        debug_log_v2("Skipping summary — only doctor role generates summary");
        $summary = !empty($row['consultation_summary'])
            ? $row['consultation_summary']
            : "Summary will be generated by doctor's session.";

    } else {
        $context = implode("\n\n", $contextParts);

        $prompt = "You are a medical documentation assistant for TELE-CARE, a teleconsultation platform. Your job is to produce complete, accurate, and professional medical summaries.

Doctor: Dr. {$row['doctor_name']} ({$row['specialty']})
Patient: {$row['patient_name']}
Date: " . date('F j, Y', strtotime($row['appointment_date'])) . "
Time: " . date('g:i A', strtotime($row['appointment_time'])) . "

--- CONSULTATION CONTENT ---
$context
--- END OF CONSULTATION CONTENT ---

INSTRUCTIONS:
You MUST write a complete structured medical summary covering ALL 6 sections below. Do NOT stop early. Do NOT truncate. Every section must be present and filled in.

Use this exact format — section title on its own line, followed by the content on the next line(s):

1. Chief Complaint
Write the patient's main reason for the visit based on the consultation content.

2. Symptoms Discussed
List all symptoms mentioned during the consultation.

3. Doctor's Assessment
Summarize the doctor's clinical observations, findings, and evaluation.

4. Diagnosis (if mentioned)
State any diagnosis given. If none was explicitly mentioned, write: Not discussed.

5. Treatment Plan / Prescriptions
List all medications prescribed, dosages if mentioned, and any treatments recommended. If none, write: Not discussed.

6. Follow-up Instructions
State any follow-up appointments, instructions, or advice given to the patient. If none, write: Not discussed.

RULES:
- You MUST complete all 6 sections before ending your response.
- If a section topic was not covered in the consultation, write: Not discussed.
- Do NOT use markdown formatting. No ##, ###, **, *, or any special symbols.
- Write in plain text only. Section titles should be written exactly as shown above (e.g. '1. Chief Complaint').
- Be concise but thorough. Do not invent information not present in the consultation.
- If there are multiple sessions separated by '[--- Rejoined Session ---]', consolidate all sessions into one unified summary.";

        debug_log_v2("Calling Groq LLM for summary...");

        $max_retries = 3;
        $summary     = '';
        $http_code   = 0;

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $GROQ_API_KEY,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => json_encode([
                    'model'       => 'llama-3.3-70b-versatile',
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.2,
                    'max_tokens'  => 2048,
                    'top_p'       => 0.8,
                ]),
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $resp       = curl_exec($ch);
            $curl_error = curl_error($ch);
            $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            debug_log_v2("Groq LLM attempt $attempt — HTTP: $http_code, curl_error: $curl_error");

            if ($curl_error) {
                debug_log_v2("CURL ERROR: $curl_error");
                break;
            }

            if ($http_code === 200 && $resp) {
                $decoded = json_decode($resp, true);
                $summary = trim($decoded['choices'][0]['message']['content'] ?? '');
                if ($summary !== '') {
                    debug_log_v2("Groq LLM summary OK, length=" . strlen($summary));
                    break;
                }
                debug_log_v2("WARNING: 200 OK but empty summary. Raw: " . substr($resp, 0, 300));
            }

            if (($http_code === 429 || $http_code === 503) && $attempt < $max_retries) {
                $wait = $attempt * 20;
                debug_log_v2("HTTP $http_code — waiting {$wait}s before retry...");
                sleep($wait);
            } else {
                break;
            }
        }

        if (!$summary) {
            debug_log_v2("No summary after $max_retries attempts. Last HTTP: $http_code. Raw: " . substr($resp ?? '', 0, 300));
            $summary = "Summary generation failed (HTTP {$http_code}). Please try ending the consultation again.";
        }
    }

} elseif (empty($contextParts) && !$summary) {
    $summary = 'No consultation content was captured for this session yet. A full summary will be generated once the consultation is completed.';
}

// ── 8. Generate PDF ───────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

function enc_v2(string $s): string {
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

function buildPDF(array $row, string $summary, string $full_transcript, string $full_chat): string {
    class ConsultationPDFV2 extends FPDF {
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
                'AI-generated summary — reviewed and confirmed by attending physician. Page ' . $this->PageNo(),
                0, 0, 'C');
        }
    }

    $pdf = new ConsultationPDFV2('P', 'mm', 'A4');
    $pdf->SetMargins(20, 36, 20);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // Info box
    $pdf->SetFillColor(240, 247, 245);
    $pdf->SetDrawColor(200, 220, 215);
    $pdf->Rect(20, 36, 170, 38, 'DF');
    $fields = [
        'Patient'   => enc_v2($row['patient_name']),
        'Doctor'    => enc_v2('Dr. ' . $row['doctor_name'] . ' - ' . ($row['specialty'] ?? '')),
        'Date/Time' => date('F j, Y', strtotime($row['appointment_date'])) . ' at ' . date('g:i A', strtotime($row['appointment_time'])),
        'Generated' => date('F j, Y g:i A') . ' (doctor reviewed)',
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

    // Parse summary sections
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
            $pdf->Cell(0, 7, enc_v2($clean), 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);

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
            $pdf->MultiCell(0, 5, enc_v2($body), 0, 'L');
            $pdf->Ln(1);
        } else {
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->MultiCell(0, 5, enc_v2($line), 0, 'L');
            $i++;
        }
    }

    // Voice transcript page
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
                $pdf->Cell(0, 5, enc_v2($tline), 0, 1, 'C');
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetTextColor(60, 60, 60);
                $pdf->Ln(1);
            } else {
                $pdf->MultiCell(0, 5, enc_v2($tline), 0, 'L');
            }
        }
    }

    // Chat log page
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
                $pdf->Cell(0, 5, enc_v2($cl), 0, 1, 'C');
                $pdf->Ln(1);
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(40, 40, 40);
                continue;
            }
            if (preg_match('/^\[(.+?)\]\s(.+?):\s(.+)$/', $cl, $m)) {
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetTextColor(36, 68, 65);
                $pdf->Cell(28, 5, '[' . enc_v2($m[1]) . ']', 0, 0);
                $pdf->SetTextColor(0, 80, 60);
                $pdf->Cell(50, 5, enc_v2($m[2]) . ':', 0, 0);
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(40, 40, 40);
                $pdf->MultiCell(0, 5, enc_v2($m[3]), 0, 'L');
            } else {
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(40, 40, 40);
                $pdf->MultiCell(0, 5, enc_v2($cl), 0, 'L');
            }
        }
    }

    return $pdf->Output('S'); // Return as string
}

// Build and save PDF
$dir = __DIR__ . '/consultation_summaries/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$filename = "summary_{$appt_id}.pdf";

if ($session_changed && file_exists($dir . $filename)) {
    @unlink($dir . $filename);
}

$pdfContent = buildPDF($row, $summary, $full_transcript, $full_chat);
file_put_contents($dir . $filename, $pdfContent);

// ── 9. Save summary + PDF path to DB ─────────────────────────────────────
$stmt2 = $conn->prepare("
    UPDATE appointments
    SET consultation_summary = ?,
        summary_pdf_path     = ?,
        summary_edited       = 0
    WHERE id = ?
");
$stmt2->bind_param('ssi', $summary, $filename, $appt_id);
$stmt2->execute();

debug_log_v2('=== END PROCESS CONSULTATION V2 (GROQ) SUCCESS ===');