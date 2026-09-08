<?php
// doctor/send_document.php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/document_delivery.php';
require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';

telecare_document_delivery_schema($conn);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$appt_id = (int)($_GET['appt_id'] ?? $_POST['appt_id'] ?? 0);
$doc_type = $_GET['doc_type'] ?? $_POST['doc_type'] ?? '';
$allowed = ['prescription', 'lab_request', 'med_cert'];

if (!$appt_id || !in_array($doc_type, $allowed, true)) {
    header('Location: patients.php');
    exit;
}

$stmt = $conn->prepare("SELECT a.*, p.full_name AS patient_name, p.address AS patient_address, p.date_of_birth, p.gender, p.email AS patient_email, p.phone_number AS patient_phone FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ? AND a.doctor_id = ? LIMIT 1");
$stmt->bind_param('ii', $appt_id, $doctor_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    header('Location: patients.php');
    exit;
}

if (!telecare_document_send_window_open($appt['completed_at'] ?? null)) {
    $_SESSION['toast_error'] = 'Documents can only be sent within 1 hour after the teleconsultation is completed.';
    header('Location: patients.php?patient_id=' . (int)$appt['patient_id']);
    exit;
}

$template = telecare_get_document_template($conn, $doc_type);
$draftRow = telecare_get_document_draft($conn, $appt_id, $doc_type);
$summary = trim((string)($appt['consultation_summary'] ?? ''));
$defaultDraft = $draftRow['draft_text'] ?? telecare_document_draft_text($doc_type, $appt, $doc, $template, $summary);
$patientInfo = [
  'name' => (string)($appt['patient_name'] ?? ''),
  'address' => (string)($appt['patient_address'] ?? ''),
  'age' => !empty($appt['date_of_birth']) ? (string)floor((time() - strtotime($appt['date_of_birth'])) / 31556926) : '',
  'sex' => ucfirst((string)($appt['gender'] ?? '')),
  'date' => date('F j, Y'),
];
$remaining = telecare_document_send_window_remaining($appt['completed_at'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_document'])) {
  $finalText = trim($_POST['document_text'] ?? '');
  if ($finalText === '') {
        $error = 'Document text cannot be empty.';
    } else {
        $uploadsDir = __DIR__ . '/../uploads/generated_documents/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
    $docFileName = 'document_' . $appt_id . '_' . $doc_type . '_' . date('Ymd_His') . '.pdf';
        $docPath = $uploadsDir . $docFileName;
        $clinicName = htmlspecialchars((string)($template['clinic_name'] ?? 'TELE-CARE'));
        $clinicAddress = htmlspecialchars((string)($template['address'] ?? ''));
        $clinicContact = htmlspecialchars((string)($template['contact_no'] ?? ''));
        $doctorName = htmlspecialchars(trim((string)($doc['full_name'] ?? '')));
        $doctorCredentials = htmlspecialchars(trim((string)($doc['credentials'] ?? '')));
        $doctorLicense = htmlspecialchars(trim((string)($doc['license_number'] ?? '')));
        $doctorPtr = htmlspecialchars(trim((string)($doc['ptr_number'] ?? '')));
        $patientName = htmlspecialchars((string)($appt['patient_name'] ?? ''));
        $patientAge = '';
        if (!empty($appt['date_of_birth'])) {
            $patientAge = (string)floor((time() - strtotime($appt['date_of_birth'])) / 31556926);
        }
        $patientSex = htmlspecialchars(ucfirst((string)($appt['gender'] ?? '')));
        $patientAddress = htmlspecialchars((string)($appt['patient_address'] ?? ''));
        $bodyHtml = nl2br(htmlspecialchars($finalText));
        $footerNote = htmlspecialchars((string)($template['footer_note'] ?? ''));
        $docTitle = htmlspecialchars(telecare_document_type_title($doc_type));
        $clinicContactLine = $clinicContact !== '' ? 'Contact No.: ' . $clinicContact : '';
        $footerNoteHtml = $footerNote !== '' ? '<div class="foot">' . $footerNote . '</div>' : '';
        function enc_doc_text(string $s): string {
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

        class TelecareDocumentPDF extends FPDF {
    public array $meta = [];

    public function Header() {
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 7, $this->meta['clinic_name'] ?? 'TELE-CARE', 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        if (!empty($this->meta['clinic_address'])) {
            $this->Cell(0, 5, $this->meta['clinic_address'], 0, 1, 'C');
        }
        if (!empty($this->meta['clinic_contact'])) {
            $this->Cell(0, 5, 'Contact No.: ' . $this->meta['clinic_contact'], 0, 1, 'C');
        }
        $this->Ln(2);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.4);
        $this->Line(20, $this->GetY(), 190, $this->GetY());
        $this->Ln(4);
    }

    public function Footer() {
        // signature block is drawn manually in the body, right before Output(),
        // so this stays empty — plain documents don't repeat a signature on
        // every page.
    }
}

$pdf = new TelecareDocumentPDF('P', 'mm', 'A4');
$pdf->meta = [
    'clinic_name'    => $clinicName,
    'clinic_address' => $clinicAddress,
    'clinic_contact' => $clinicContact,
];
$pdf->SetMargins(20, 20, 20);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, enc_doc_text($docTitle), 0, 1, 'C');
$pdf->Ln(3);

// Patient / date line — plain text, no box
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, enc_doc_text("Patient's Name: " . $patientInfo['name']), 0, 0);
$pdf->Cell(0, 6, enc_doc_text("Date: " . $patientInfo['date']), 0, 1);
$pdf->Cell(95, 6, enc_doc_text("Age: " . $patientInfo['age'] . "   Sex: " . $patientInfo['sex']), 0, 1);
$pdf->Cell(0, 6, enc_doc_text("Address: " . $patientInfo['address']), 0, 1);
$pdf->Ln(4);

// Body — the doctor's editable text
$pdf->SetFont('Arial', '', 10);
foreach (preg_split('/\R/', $finalText) as $line) {
    $line = trim($line);
    if ($line === '') { $pdf->Ln(1.2); continue; }
    $pdf->MultiCell(0, 5.5, enc_doc_text($line), 0, 'L');
}

// Footer note (med cert only)
if ($footerNote !== '') {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->MultiCell(0, 5, enc_doc_text($footerNote), 0, 'L');
}

// Signature block — plain, left-aligned, at end of content (not page footer)
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, enc_doc_text($doctorName . ($doctorCredentials !== '' ? ', ' . $doctorCredentials : '')), 0, 1);
$pdf->SetFont('Arial', '', 9);
if ($doctorLicense !== '') $pdf->Cell(0, 5, 'License No.: ' . enc_doc_text($doctorLicense), 0, 1);
if ($doctorPtr !== '')     $pdf->Cell(0, 5, 'PTR No.: ' . enc_doc_text($doctorPtr), 0, 1);

$pdf->Output('F', $docPath);

        $insert = $conn->prepare("INSERT INTO lab_results (patient_id, file_path, doc_type, doc_label, extracted_text, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $filePathDb = 'uploads/generated_documents/' . $docFileName;
        $docLabel = telecare_document_type_label($doc_type) . ' - Dr. ' . trim((string)($doc['full_name'] ?? ''));
        $insert->bind_param('issss', $appt['patient_id'], $filePathDb, $doc_type, $docLabel, $finalText);
        $insert->execute();
        $insert->close();

        $_SESSION['toast'] = 'Document sent to patient records.';
        header('Location: patient-records.php?patient_id=' . (int)$appt['patient_id']);
        exit;
    }
}

$page_title = telecare_document_type_label($doc_type) . ' Review — TELE-CARE';
$page_title_short = 'Document Review';
$active_nav = 'patients';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page" style="max-width:1100px;margin:0 auto;">
  <?php if (!empty($error)): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:1rem;">
    <div class="section-label"><?= htmlspecialchars(telecare_document_type_label($doc_type)) ?></div>
    <div style="font-size:0.84rem;color:var(--muted);line-height:1.6;">
      Patient: <strong style="color:var(--green)"><?= htmlspecialchars($appt['patient_name'] ?? '') ?></strong><br>
      Completed: <?= !empty($appt['completed_at']) ? date('F j, Y g:i A', strtotime($appt['completed_at'])) : 'Not recorded' ?><br>
      Send window: <?= max(1, (int)ceil($remaining / 60)) ?> minute<?= $remaining === 60 ? '' : 's' ?> left
    </div>
    <div style="margin-top:0.75rem;background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.18);color:#244441;border-radius:12px;padding:0.8rem 0.9rem;font-size:0.8rem;line-height:1.55;">
      The clinic header comes from the admin template, while your name, credentials, license number, and PTR number are pulled from your doctor account automatically. The patient details come from the selected appointment.
    </div>
  </div>

  <div class="card">
    <form method="POST">
      <input type="hidden" name="appt_id" value="<?= $appt_id ?>">
      <input type="hidden" name="doc_type" value="<?= htmlspecialchars($doc_type) ?>">
      <div class="card" style="background:rgba(63,130,227,0.05);border:1px solid rgba(63,130,227,0.16);margin-bottom:1rem;">
        <div style="font-size:0.78rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.6rem;">Auto-filled patient details</div>
        <div style="font-size:0.84rem;line-height:1.65;color:var(--green);">
          <div><strong>Patient:</strong> <?= htmlspecialchars($patientInfo['name']) ?></div>
          <div><strong>Age:</strong> <?= htmlspecialchars($patientInfo['age']) ?></div>
          <div><strong>Sex:</strong> <?= htmlspecialchars($patientInfo['sex']) ?></div>
          <div><strong>Address:</strong> <?= htmlspecialchars($patientInfo['address']) ?></div>
          <div><strong>Date:</strong> <?= htmlspecialchars($patientInfo['date']) ?></div>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Editable Document Body</label>
        <textarea name="document_text" rows="24" class="field-input" style="font-family:'DM Sans',sans-serif;line-height:1.6;white-space:pre-wrap;"><?= htmlspecialchars($defaultDraft) ?></textarea>
      </div>
      <div style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;">
        <button type="submit" name="send_document" class="btn-submit" style="background:var(--green);">Send to Patient</button>
        <a href="patients.php" class="btn-submit" style="background:var(--blue);text-decoration:none;display:inline-flex;align-items:center;">Back to Patients</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/nav.php'; ?>
</body>
</html>
