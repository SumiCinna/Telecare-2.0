<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';
// doctor/save_summary.php
// Saves doctor-edited summary, regenerates PDF, marks as reviewed

header('Content-Type: application/json');

$appt_id       = (int)($_POST['appt_id'] ?? 0);
$edited_summary = trim($_POST['summary'] ?? '');

if (!$appt_id || !$edited_summary) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

// Fetch appointment info for PDF
$row = $conn->query("
    SELECT a.appointment_date, a.appointment_time,
           a.consultation_transcript, a.chat_log,
           p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.id = $appt_id AND d.id = $doctor_id
")->fetch_assoc();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Appointment not found or access denied']);
    exit;
}

// ── Regenerate PDF with edited summary ───────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

function enc_save(string $s): string {
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

class SummaryPDF extends FPDF {
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
            'Reviewed and edited by attending physician. Page ' . $this->PageNo(),
            0, 0, 'C');
    }
}

$pdf = new SummaryPDF('P', 'mm', 'A4');
$pdf->SetMargins(20, 36, 20);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Info box
$pdf->SetFillColor(240, 247, 245);
$pdf->SetDrawColor(200, 220, 215);
$pdf->Rect(20, 36, 170, 38, 'DF');
$fields = [
    'Patient'   => enc_save($row['patient_name']),
    'Doctor'    => enc_save('Dr. ' . $row['doctor_name'] . ' - ' . ($row['specialty'] ?? '')),
    'Date/Time' => date('F j, Y', strtotime($row['appointment_date'])) . ' at ' . date('g:i A', strtotime($row['appointment_time'])),
    'Reviewed'  => date('F j, Y g:i A') . ' (doctor reviewed & edited)',
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

// "Doctor Reviewed" badge
$pdf->SetFillColor(34, 197, 94);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 6, enc_save('✓ Reviewed & Confirmed by Dr. ' . $row['doctor_name']), 0, 1, 'C', true);
$pdf->Ln(3);

// Summary heading
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(36, 68, 65);
$pdf->Cell(0, 8, 'Consultation Summary', 0, 1);
$pdf->SetDrawColor(36, 68, 65);
$pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(3);

// Parse and render summary sections
$lines = explode("\n", $edited_summary);
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
        $pdf->Cell(0, 7, enc_save($clean), 0, 1, 'L', true);
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
        $pdf->MultiCell(0, 5, enc_save($body), 0, 'L');
        $pdf->Ln(1);
    } else {
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(0, 5, enc_save($line), 0, 'L');
        $i++;
    }
}

// Voice transcript page
$full_transcript = trim($row['consultation_transcript'] ?? '');
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
            $pdf->Cell(0, 5, enc_save($tline), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->Ln(1);
        } else {
            $pdf->MultiCell(0, 5, enc_save($tline), 0, 'L');
        }
    }
}

// Chat log page
$full_chat = trim($row['chat_log'] ?? '');
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
            $pdf->Cell(0, 5, enc_save($cl), 0, 1, 'C');
            $pdf->Ln(1);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            continue;
        }
        if (preg_match('/^\[(.+?)\]\s(.+?):\s(.+)$/', $cl, $m)) {
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetTextColor(36, 68, 65);
            $pdf->Cell(28, 5, '[' . enc_save($m[1]) . ']', 0, 0);
            $pdf->SetTextColor(0, 80, 60);
            $pdf->Cell(50, 5, enc_save($m[2]) . ':', 0, 0);
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->MultiCell(0, 5, enc_save($m[3]), 0, 'L');
        } else {
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->MultiCell(0, 5, enc_save($cl), 0, 'L');
        }
    }
}

// Save PDF
$dir      = __DIR__ . '/../consultation_summaries/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$filename = "summary_{$appt_id}.pdf";
$pdf->Output('F', $dir . $filename);

// ── Save to DB — mark as edited ───────────────────────────────────────────
$stmt = $conn->prepare("
    UPDATE appointments
    SET consultation_summary = ?,
        summary_pdf_path     = ?,
        summary_edited       = 1,
        summary_reviewed_at  = NOW()
    WHERE id = ? AND doctor_id = ?
");
$stmt->bind_param('ssii', $edited_summary, $filename, $appt_id, $doctor_id);
$stmt->execute();

echo json_encode([
    'status'   => 'success',
    'message'  => 'Summary saved and PDF updated.',
    'pdf_url'  => '../download_summary.php?appt_id=' . $appt_id,
]);