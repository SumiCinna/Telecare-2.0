<?php
// doctor/send_document.php — TELE-CARE clinical document builder
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/includes/auth.php';

$__dd = __DIR__ . '/../includes/document_delivery.php';
if (is_file($__dd)) { require_once $__dd; }
require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (function_exists('telecare_document_delivery_schema')) { telecare_document_delivery_schema($conn); }

/* ============================================================
   SCHEMA GUARDS — keeps the page working even if the SQL
   upgrade script has not been run yet.
   ============================================================ */
function tc_has_column(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}
function tc_has_table(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    return $r && $r->num_rows > 0;
}
function tc_ensure_schema(mysqli $conn): void {
    if (!tc_has_column($conn, 'doctors', 'credentials')) {
        @$conn->query("ALTER TABLE `doctors` ADD COLUMN `credentials` VARCHAR(120) NOT NULL DEFAULT ''");
    }
    if (!tc_has_column($conn, 'doctors', 'ptr_number')) {
        @$conn->query("ALTER TABLE `doctors` ADD COLUMN `ptr_number` VARCHAR(100) NOT NULL DEFAULT ''");
    }
    if (!tc_has_table($conn, 'appointment_document_drafts')) {
        @$conn->query("CREATE TABLE `appointment_document_drafts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `appointment_id` INT(11) NOT NULL,
            `doc_type` ENUM('prescription','lab_request','med_cert') NOT NULL,
            `draft_text` LONGTEXT NOT NULL,
            `payload_json` LONGTEXT DEFAULT NULL,
            `source_summary` LONGTEXT DEFAULT NULL,
            `source_model` VARCHAR(100) DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_appt_doc` (`appointment_id`,`doc_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } elseif (!tc_has_column($conn, 'appointment_document_drafts', 'payload_json')) {
        @$conn->query("ALTER TABLE `appointment_document_drafts` ADD COLUMN `payload_json` LONGTEXT DEFAULT NULL");
    }
    if (!tc_has_table($conn, 'issued_documents')) {
        @$conn->query("CREATE TABLE `issued_documents` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `appointment_id` INT(11) NOT NULL,
            `patient_id` INT(11) NOT NULL,
            `doctor_id` INT(11) NOT NULL,
            `doc_type` ENUM('prescription','lab_request','med_cert') NOT NULL,
            `doc_no` VARCHAR(40) NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `payload_json` LONGTEXT DEFAULT NULL,
            `plain_text` LONGTEXT DEFAULT NULL,
            `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_doc_no` (`doc_no`),
            KEY `idx_patient` (`patient_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
tc_ensure_schema($conn);

/* ============================================================
   INPUT
   ============================================================ */
$DOC_TYPES = [
    'prescription' => 'Prescription',
    'lab_request'  => 'Laboratory Request',
    'med_cert'     => 'Medical Certificate',
];
$DOC_TITLES = [
    'prescription' => 'ELECTRONIC MEDICAL PRESCRIPTION',
    'lab_request'  => 'LABORATORY & DIAGNOSTIC REQUEST',
    'med_cert'     => 'OFFICIAL MEDICAL CERTIFICATE',
];

$appt_id  = (int)($_GET['appt_id'] ?? $_POST['appt_id'] ?? 0);
$doc_type = $_GET['doc_type'] ?? $_POST['doc_type'] ?? 'prescription';
if (!array_key_exists($doc_type, $DOC_TYPES)) { $doc_type = 'prescription'; }

if (!$appt_id) { header('Location: patients.php'); exit; }

$stmt = $conn->prepare("
    SELECT a.*,
           p.full_name    AS patient_name,
           p.home_address AS patient_home_address,
           p.city         AS patient_city,
           p.date_of_birth,
           p.gender,
           p.email        AS patient_email,
           p.phone_number AS patient_phone
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE a.id = ? AND a.doctor_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $appt_id, $doctor_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) { header('Location: patients.php'); exit; }

/* The gate is now the consultation status, not a 1-hour countdown. */
$isCompleted = (($appt['status'] ?? '') === 'Completed') || !empty($appt['completed_at']);
if (!$isCompleted) {
    $_SESSION['toast_error'] = 'Documents can only be issued after the teleconsultation is completed.';
    header('Location: patient-records.php?patient_id=' . (int)$appt['patient_id']);
    exit;
}

/* Doctor identity straight from the doctors table. */
$dstmt = $conn->prepare("SELECT * FROM doctors WHERE id = ? LIMIT 1");
$dstmt->bind_param('i', $doctor_id);
$dstmt->execute();
$docRow = $dstmt->get_result()->fetch_assoc() ?: [];
$dstmt->close();

/* ============================================================
   LETTERHEAD TEMPLATE (admin-managed), with fallbacks
   ============================================================ */
function tc_template(mysqli $conn, string $doc_type): array {
    $dt = $conn->real_escape_string($doc_type);
    $sql = [
        "SELECT * FROM document_templates WHERE doc_type='$dt' AND status='Active' AND is_default=1 LIMIT 1",
        "SELECT * FROM document_templates WHERE doc_type='$dt' AND status='Active' ORDER BY updated_at DESC LIMIT 1",
        "SELECT * FROM document_templates WHERE status='Active' AND is_default=1 ORDER BY updated_at DESC LIMIT 1",
        "SELECT * FROM document_templates WHERE status='Active' ORDER BY updated_at DESC LIMIT 1",
    ];
    foreach ($sql as $q) {
        $r = $conn->query($q);
        if ($r && $r->num_rows) { return $r->fetch_assoc(); }
    }
    return [];
}
$templates = [];
foreach (array_keys($DOC_TYPES) as $t) { $templates[$t] = tc_template($conn, $t); }

function tc_letterhead(array $tpl, array $docRow): array {
    $clinic = trim((string)($tpl['clinic_name'] ?? ''));
    if ($clinic === '') { $clinic = trim((string)($docRow['clinic_name'] ?? '')) ?: 'TELE-CARE'; }
    return [
        'clinic'   => $clinic,
        'address'  => trim((string)($tpl['address'] ?? '')),
        'contact'  => trim((string)($tpl['contact_no'] ?? $docRow['phone_number'] ?? '')),
        'footer'   => trim((string)($tpl['footer_note'] ?? '')),
    ];
}

/* ============================================================
   PATIENT / ENCOUNTER META
   ============================================================ */
$patientAge = '';
if (!empty($appt['date_of_birth'])) {
    $patientAge = (string)floor((time() - strtotime($appt['date_of_birth'])) / 31556926);
}
$patientAddressParts = array_filter([
    trim((string)($appt['patient_home_address'] ?? '')),
    trim((string)($appt['patient_city'] ?? '')),
]);
$patientMeta = [
    'name'    => (string)($appt['patient_name'] ?? ''),
    'age'     => $patientAge,
    'sex'     => ucfirst((string)($appt['gender'] ?? '')) ?: 'N/A',
    'address' => implode(', ', $patientAddressParts),
    'pid'     => '#PT-' . str_pad((string)$appt['patient_id'], 4, '0', STR_PAD_LEFT),
    'encdate' => !empty($appt['completed_at'])
        ? date('F j, Y - g:i A', strtotime($appt['completed_at']))
        : date('F j, Y - g:i A', strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time'])),
];
$doctorMeta = [
    'name'        => trim((string)($docRow['full_name'] ?? '')),
    'credentials' => trim((string)($docRow['credentials'] ?? '')),
    'specialty'   => trim((string)($docRow['specialty'] ?? '')),
    'license'     => trim((string)($docRow['license_number'] ?? '')),
];

/* ============================================================
   LABORATORY CATALOG
   ============================================================ */
$LAB_CATALOG = [
    '1. Hematology (Blood Tests)' => [
        'Complete Blood Count (CBC)',
        'Blood Typing (ABO/Rh)',
        'Platelet Count',
        'Erythrocyte Sedimentation Rate (ESR)',
        'Prothrombin Time (PT/INR)',
    ],
    '2. Blood Chemistry' => [
        'Fasting Blood Sugar (FBS)',
        'Random Blood Sugar (RBS)',
        'HbA1c',
        'Lipid Profile (Cholesterol, HDL, LDL, Triglycerides)',
        'Liver Function Test (SGOT, SGPT, Bilirubin)',
        'Kidney Function Test (BUN, Creatinine, Uric Acid)',
        'Electrolytes (Sodium, Potassium)',
    ],
    '3. Thyroid & Hormones' => [
        'TSH',
        'FT3 / FT4',
        'Beta-hCG (Pregnancy Test, blood)',
    ],
    '4. Urinalysis & Stool' => [
        'Routine Urinalysis',
        'Urine Pregnancy Test (UPT)',
        'Fecalysis (Stool Exam)',
        'Fecal Occult Blood Test',
    ],
    '5. Serology / Infectious Disease Screening' => [
        'Hepatitis B Screening (HBsAg)',
        'Hepatitis A/C Screening',
        'HIV Screening',
        'VDRL/RPR (Syphilis Screening)',
        'Dengue NS1/IgG/IgM',
        'Typhidot (Typhoid)',
        'COVID-19 Antigen/RT-PCR',
    ],
    '6. Clinical Microscopy' => [
        'Gram Stain',
        'KOH Test (Fungal)',
    ],
    '7. Imaging' => [
        'Chest X-Ray',
        'ECG (Electrocardiogram)',
        'Ultrasound (Abdominal, Pelvic, Whole Abdomen)',
        '2D Echo',
    ],
    '8. Physical Exam / Clearance Packages' => [
        'Pre-Employment Physical Exam (PEPE)',
        'Annual Physical Exam (APE)',
        'Medical Certificate Issuance',
        'Drug Testing',
    ],
    '9. Special / Add-on Tests' => [
        'Pap Smear',
        'PSA (Prostate Screening)',
        'CRP (Inflammation Marker)',
        'Iron Studies / Ferritin',
    ],
];

$FITNESS_OPTIONS = [
    'Unfit for work/school duties (Strict Bed Rest)',
    'Unfit for work/school duties (Home Rest)',
    'Fit to return to work/school',
    'Fit to return with light duty restrictions',
    'Fit for travel',
    'Cleared for physical activity',
];

/* ============================================================
   PAYLOAD HELPERS
   ============================================================ */
function tc_blank_payload(): array {
    return [
        'diagnosis'    => '',
        'prescription' => [
            'drugs' => [[ 'name'=>'', 'dispense'=>'', 'freq'=>'', 'duration'=>'', 'sig'=>'' ]],
            'notes' => '',
        ],
        'med_cert' => [
            'leave_from' => '',
            'leave_to'   => '',
            'fitness'    => '',
            'remarks'    => '',
            'notes'      => '',
        ],
        'lab_request' => [
            'priority'   => 'Routine',
            'tests'      => [],
            'indication' => '',
            'notes'      => '',
        ],
    ];
}
function tc_merge_payload($incoming): array {
    $base = tc_blank_payload();
    if (!is_array($incoming)) { return $base; }
    $base['diagnosis'] = (string)($incoming['diagnosis'] ?? '');

    $drugs = $incoming['prescription']['drugs'] ?? [];
    $clean = [];
    if (is_array($drugs)) {
        foreach ($drugs as $d) {
            if (!is_array($d)) { continue; }
            $clean[] = [
                'name'     => trim((string)($d['name'] ?? '')),
                'dispense' => trim((string)($d['dispense'] ?? '')),
                'freq'     => trim((string)($d['freq'] ?? '')),
                'duration' => trim((string)($d['duration'] ?? '')),
                'sig'      => trim((string)($d['sig'] ?? '')),
            ];
        }
    }
    if ($clean) { $base['prescription']['drugs'] = $clean; }
    $base['prescription']['notes'] = trim((string)($incoming['prescription']['notes'] ?? ''));

    foreach (['leave_from','leave_to','fitness','remarks','notes'] as $k) {
        $base['med_cert'][$k] = trim((string)($incoming['med_cert'][$k] ?? ''));
    }

    $base['lab_request']['priority']   = trim((string)($incoming['lab_request']['priority'] ?? 'Routine')) ?: 'Routine';
    $base['lab_request']['indication'] = trim((string)($incoming['lab_request']['indication'] ?? ''));
    $base['lab_request']['notes']      = trim((string)($incoming['lab_request']['notes'] ?? ''));
    $tests = $incoming['lab_request']['tests'] ?? [];
    $ct = [];
    if (is_array($tests)) {
        foreach ($tests as $cat => $items) {
            if (!is_array($items)) { continue; }
            $vals = array_values(array_filter(array_map(function ($v) { return trim((string)$v); }, $items)));
            if ($vals) { $ct[(string)$cat] = $vals; }
        }
    }
    $base['lab_request']['tests'] = $ct;
    return $base;
}

/* Existing draft for this appointment + type */
/* Drafts are no longer loaded — always start blank */
$draftPayload = tc_blank_payload();
if ($draftPayload['diagnosis'] === '') {
    $draftPayload['diagnosis'] = '';
}
if ($draftPayload['med_cert']['fitness'] === '') {
    $draftPayload['med_cert']['fitness'] = $FITNESS_OPTIONS[0];
}

/* ============================================================
   PLAIN-TEXT RENDERING (stored in lab_results.extracted_text)
   ============================================================ */
function tc_plain_text(string $doc_type, array $P, array $patientMeta, array $doctorMeta, array $head): string {
    $L = [];
    $L[] = $head['clinic'];
    if ($head['address'] !== '') { $L[] = $head['address']; }
    if ($head['contact'] !== '') { $L[] = 'Contact No.: ' . $head['contact']; }
    $L[] = '';
    $L[] = strtoupper($doc_type === 'prescription' ? 'Electronic Medical Prescription'
        : ($doc_type === 'med_cert' ? 'Official Medical Certificate' : 'Laboratory & Diagnostic Request'));
    $L[] = '';
    $L[] = 'Patient Name: ' . $patientMeta['name'];
    $L[] = 'Age / Gender: ' . $patientMeta['age'] . ' / ' . $patientMeta['sex'];
    $L[] = 'Patient ID: ' . $patientMeta['pid'];
    $L[] = 'Date: ' . $patientMeta['encdate'];
    if (trim($P['diagnosis']) !== '') { $L[] = 'Clinical Impression / Diagnosis: ' . $P['diagnosis']; }
    $L[] = '';

    if ($doc_type === 'prescription') {
        $L[] = 'Rx';
        $i = 1;
        foreach ($P['prescription']['drugs'] as $d) {
            if ($d['name'] === '') { continue; }
            $L[] = $i . '. ' . $d['name'] . ($d['dispense'] !== '' ? '   Disp: ' . $d['dispense'] : '');
            $sig = trim($d['sig']);
            $tail = trim(trim($d['freq']) . ($d['duration'] !== '' ? ' — ' . $d['duration'] : ''), " —");
            if ($sig !== '' || $tail !== '') {
                $L[] = '   Sig: ' . trim($sig . ($tail !== '' ? ' (' . $tail . ')' : ''));
            }
            $i++;
        }
        if (trim($P['prescription']['notes']) !== '') {
            $L[] = '';
            $L[] = 'Physician Instructions: ' . $P['prescription']['notes'];
        }
    } elseif ($doc_type === 'med_cert') {
        $L[] = 'TO WHOM IT MAY CONCERN';
        $L[] = '';
        $L[] = 'This is to certify that ' . $patientMeta['name'] . ', ' . $patientMeta['age']
             . ' years of age, was clinically examined and treated via TELE-CARE secure teleconsultation on '
             . $patientMeta['encdate'] . ' under my medical care.';
        if (trim($P['diagnosis']) !== '') { $L[] = 'Diagnostic Evaluation: ' . $P['diagnosis']; }
        if (trim($P['med_cert']['fitness']) !== '') { $L[] = 'Clinical Fitness Determination: ' . $P['med_cert']['fitness']; }
        if ($P['med_cert']['leave_from'] !== '' || $P['med_cert']['leave_to'] !== '') {
            $L[] = 'Recommended Rest Period: ' . $P['med_cert']['leave_from'] . ' to ' . $P['med_cert']['leave_to'];
        }
        if (trim($P['med_cert']['remarks']) !== '') { $L[] = 'Clinical Remarks: ' . $P['med_cert']['remarks']; }
        if (trim($P['med_cert']['notes']) !== '') { $L[] = 'Additional Instructions: ' . $P['med_cert']['notes']; }
    } else {
        $L[] = 'Priority: ' . $P['lab_request']['priority'];
        $L[] = 'Requested Diagnostic Examinations:';
        foreach ($P['lab_request']['tests'] as $cat => $items) {
            $L[] = '  ' . $cat;
            foreach ($items as $t) { $L[] = '    [X] ' . $t; }
        }
        if (trim($P['lab_request']['indication']) !== '') {
            $L[] = '';
            $L[] = 'Clinical Indication: ' . $P['lab_request']['indication'];
        }
        if (trim($P['lab_request']['notes']) !== '') {
            $L[] = 'Instructions to Facility: ' . $P['lab_request']['notes'];
        }
    }

    $L[] = '';
    $L[] = trim($doctorMeta['name'] . ($doctorMeta['credentials'] !== '' ? ', ' . $doctorMeta['credentials'] : ''));
        if ($doctorMeta['license'] !== '') { $L[] = 'License No.: ' . $doctorMeta['license']; }
    if ($head['footer'] !== '')        { $L[] = $head['footer']; }
    return implode("\n", $L);
}

/* ============================================================
   PDF RENDERER
   ============================================================ */
function tc_enc(string $s): string {
    $s = preg_replace('/\*\*(.*?)\*\*/', '$1', $s);
    $s = preg_replace('/#{1,6}\s*/', '', $s);
    $s = str_replace(['—', '–', '−', '“', '”', '‘', '’', '•'], ['-', '-', '-', '"', '"', "'", "'", '-'], $s);
    $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
    return $out === false ? $s : $out;
}

class TCDocPDF extends FPDF {
    public array $meta = [];

    public function Header() {
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Times', 'B', 13);
        $this->SetXY(15, 15);
        $this->Cell(0, 7, tc_enc(strtoupper($this->meta['clinic'] ?? 'CLINIC')), 0, 1, 'C');
        $this->SetFont('Times', '', 9);
        $sub = [];
        if (!empty($this->meta['address'])) { $sub[] = $this->meta['address']; }
        if (!empty($this->meta['contact'])) { $sub[] = 'Contact No.: ' . $this->meta['contact']; }
        if ($sub) {
            $this->SetX(15);
            $this->Cell(0, 5, tc_enc(implode('   |   ', $sub)), 0, 1, 'C');
        }
        $this->Ln(2);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.4);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(6);
    }

    public function Footer() {
        $this->SetY(-14);
        $this->SetFont('Times', 'I', 7.5);
        $this->SetTextColor(110, 110, 110);
        $this->Cell(90, 5, tc_enc('Ref: ' . ($this->meta['doc_no'] ?? '')), 0, 0, 'L');
        $this->Cell(90, 5, tc_enc('Page ' . $this->PageNo() . ' of {nb}'), 0, 0, 'R');
    }

    public function sectionTitle(string $txt) {
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Times', 'B', 10);
        $this->Cell(0, 6, tc_enc($txt), 0, 1, 'L');
    }

    public function labelLine(string $label, string $value, float $w = 90, bool $newline = true) {
        $this->SetFont('Times', '', 10);
        $lw = $this->GetStringWidth(tc_enc($label . ': ')) + 1;
        $this->Cell($lw, 6, tc_enc($label . ':'), 0, 0, 'L');
        $this->Cell($w - $lw, 6, tc_enc($value), 'B', $newline ? 1 : 0, 'L');
    }
}

function tc_build_pdf(string $doc_type, array $P, array $patientMeta, array $doctorMeta, array $head, array $titles, string $doc_no): TCDocPDF {
    global $LAB_CATALOG;
    $docLine = trim($doctorMeta['name'] . ($doctorMeta['credentials'] !== '' ? ', ' . $doctorMeta['credentials'] : ''));

    $pdf = new TCDocPDF('P', 'mm', 'A4');
    $pdf->meta = [
        'clinic'  => $head['clinic'],
        'address' => $head['address'],
        'contact' => $head['contact'],
        'doc_no'  => $doc_no,
    ];
    $pdf->AliasNbPages();
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetTextColor(0, 0, 0);

    /* Title */
    $pdf->SetFont('Times', 'B', 13);
    $pdf->Cell(0, 7, tc_enc($titles[$doc_type]), 0, 1, 'C');
    $tw = $pdf->GetStringWidth(tc_enc($titles[$doc_type]));
    $cx = (210 - $tw) / 2;
    $pdf->SetLineWidth(0.3);
    $pdf->Line($cx, $pdf->GetY() - 1.5, $cx + $tw, $pdf->GetY() - 1.5);
    $pdf->Ln(4);

    if ($doc_type === 'prescription') {

        $pdf->labelLine("Patient's Name", $patientMeta['name'], 130, false);
        $pdf->labelLine('Age', $patientMeta['age'], 50, true);
        $pdf->labelLine('Address', $patientMeta['address'] ?: '-', 130, false);
        $pdf->labelLine('Date', $patientMeta['encdate'], 50, true);
        $pdf->Ln(6);

        $pdf->SetFont('Times', 'B', 20);
        $pdf->Cell(14, 10, 'Rx', 0, 1, 'L');
        $pdf->Ln(1);

        $drugs = array_values(array_filter($P['prescription']['drugs'], function ($d) { return trim($d['name']) !== ''; }));
        if (!$drugs) {
            $pdf->SetFont('Times', 'I', 10);
            $pdf->Cell(0, 6, tc_enc('No medication orders recorded.'), 0, 1, 'L');
        }
        foreach ($drugs as $d) {
            $pdf->SetFont('Times', 'B', 11);
            $pdf->MultiCell(0, 6, tc_enc($d['name']), 0, 'L');
            $tail = trim(trim($d['freq']) . ($d['duration'] !== '' ? ' - ' . $d['duration'] : ''), ' -');
            $pdf->SetFont('Times', '', 10);
            if ($tail !== '') { $pdf->Cell(0, 5.5, tc_enc($tail), 0, 1, 'L'); }
            if (trim($d['dispense']) !== '') { $pdf->Cell(0, 5.5, tc_enc('Dispense: ' . $d['dispense']), 0, 1, 'L'); }
            if (trim($d['sig']) !== '') { $pdf->MultiCell(0, 5.5, tc_enc('Label: ' . $d['sig']), 0, 'L'); }
            $pdf->Ln(4);
        }

        if (trim($P['diagnosis']) !== '') {
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.5, tc_enc($P['diagnosis']), 0, 'L');
            $pdf->Ln(2);
        }
        if (trim($P['prescription']['notes']) !== '') {
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.5, tc_enc($P['prescription']['notes']), 0, 'L');
        }

    } elseif ($doc_type === 'med_cert') {

        $pdf->SetFont('Times', '', 10);
        $pdf->Cell(0, 6, tc_enc('Date: ' . $patientMeta['encdate']), 0, 1, 'R');
        $pdf->Ln(4);
        $pdf->Cell(0, 6, tc_enc('To whom it may concern:'), 0, 1, 'L');
        $pdf->Ln(2);

        $rest = '';
        if ($P['med_cert']['leave_from'] !== '' || $P['med_cert']['leave_to'] !== '') {
            $rest = ' and advised to rest from ' . ($P['med_cert']['leave_from'] ?: '____') . ' to ' . ($P['med_cert']['leave_to'] ?: '____');
        }
        $body = 'This is to certify that ' . $patientMeta['name'] . ' presently residing at '
              . ($patientMeta['address'] ?: '____') . ' is ' . ($patientMeta['age'] ?: '____')
              . ' years old, ' . ($patientMeta['sex'] ?: '____')
              . ' and was consulted/examined/treated via TELE-CARE teleconsultation on '
              . $patientMeta['encdate'] . $rest . '.';
        $pdf->SetFont('Times', '', 10);
        $pdf->MultiCell(0, 6, tc_enc($body), 0, 'J');
        $pdf->Ln(4);

        if (trim($P['diagnosis']) !== '') {
            $pdf->sectionTitle('Assessment/Impression:');
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.6, tc_enc($P['diagnosis']), 0, 'L');
            $pdf->Ln(3);
        }
        if (trim($P['med_cert']['remarks']) !== '') {
            $pdf->sectionTitle('Recommendations/Remarks:');
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.6, tc_enc($P['med_cert']['remarks']), 0, 'L');
            $pdf->Ln(3);
        }
        if (trim($P['med_cert']['fitness']) !== '') {
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.6, tc_enc($P['med_cert']['fitness']), 0, 'L');
            $pdf->Ln(3);
        }
        if (trim($P['med_cert']['notes']) !== '') {
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.6, tc_enc($P['med_cert']['notes']), 0, 'L');
            $pdf->Ln(3);
        }
        $pdf->Ln(2);
        $pdf->SetFont('Times', '', 10);
        $pdf->MultiCell(0, 5.6, tc_enc('This document can be used for non-medico-legal purposes only.'), 0, 'L');

    } else { /* lab_request */

        $pdf->labelLine('Name', $patientMeta['name'], 130, false);
        $pdf->labelLine('Age/Sex', trim($patientMeta['age'] . '/' . $patientMeta['sex'], '/'), 50, true);
        $pdf->labelLine('Address', $patientMeta['address'] ?: '-', 130, false);
        $pdf->labelLine('Date', $patientMeta['encdate'], 50, true);
        $pdf->Ln(4);

        if (trim($P['diagnosis']) !== '') {
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.6, tc_enc('Indication: ' . $P['diagnosis']), 0, 'L');
            $pdf->Ln(2);
        }

        $pdf->SetFillColor(0, 0, 0);
        foreach ($LAB_CATALOG as $cat => $items) {
            $sel = $P['lab_request']['tests'][$cat] ?? [];
            $pdf->SetFont('Times', 'B', 9.5);
            $pdf->Cell(0, 5.6, tc_enc($cat), 0, 1, 'L');
            $pdf->SetFont('Times', '', 9);
            foreach ($items as $t) {
                $checked = in_array($t, $sel, true);
                $pdf->SetX(18);
                $pdf->Rect($pdf->GetX(), $pdf->GetY() + 0.8, 3, 3, $checked ? 'DF' : 'D');
                $pdf->SetX($pdf->GetX() + 5.5);
                $pdf->Cell(0, 5, tc_enc($t), 0, 1, 'L');
            }
            $pdf->Ln(1.5);
        }

        if (trim($P['lab_request']['indication']) !== '') {
            $pdf->sectionTitle('Clinical Indication:');
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.4, tc_enc($P['lab_request']['indication']), 0, 'L');
            $pdf->Ln(2);
        }
        if (trim($P['lab_request']['notes']) !== '') {
            $pdf->sectionTitle('Instructions to Facility:');
            $pdf->SetFont('Times', '', 10);
            $pdf->MultiCell(0, 5.4, tc_enc($P['lab_request']['notes']), 0, 'L');
        }
    }

    /* ---------- FOOTER NOTE + SIGNATURE ---------- */
    if ($head['footer'] !== '') {
        $pdf->Ln(4);
        $pdf->SetFont('Times', 'I', 9);
        $pdf->MultiCell(0, 5, tc_enc($head['footer']), 0, 'L');
    }

    $pdf->Ln(14);
    if ($pdf->GetY() > 250) { $pdf->AddPage(); $pdf->Ln(6); }
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(15, $pdf->GetY(), 95, $pdf->GetY());
    $pdf->Ln(1.5);
    $pdf->SetFont('Times', 'B', 10);
    $pdf->Cell(0, 5, tc_enc($docLine), 0, 1, 'L');
    $pdf->SetFont('Times', '', 9.5);
        if ($doctorMeta['license'] !== '') { $pdf->Cell(0, 5, tc_enc('License No.: ' . $doctorMeta['license']), 0, 1, 'L'); }

    return $pdf;
}

/* ============================================================
   POST ACTIONS
   ============================================================ */
$error = '';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['issue', 'export'], true)) {
    $P    = tc_merge_payload(json_decode($_POST['payload'] ?? '', true));
    $head = tc_letterhead($templates[$doc_type] ?? [], $docRow);

    /* minimal validation */
    if ($action !== 'draft') {
        if ($doc_type === 'prescription') {
            $has = false;
            foreach ($P['prescription']['drugs'] as $d) { if (trim($d['name']) !== '') { $has = true; break; } }
            if (!$has) { $error = 'Add at least one medication before issuing the prescription.'; }
        } elseif ($doc_type === 'lab_request') {
            if (empty($P['lab_request']['tests'])) { $error = 'Select at least one diagnostic examination.'; }
        } elseif ($doc_type === 'med_cert') {
            if (trim($P['diagnosis']) === '' && trim($P['med_cert']['remarks']) === '') {
                $error = 'Enter a diagnosis or clinical remarks before issuing the certificate.';
            }
        }
    }

 

    if ($error === '') {
        $prefix = ['prescription' => 'RX', 'lab_request' => 'LAB', 'med_cert' => 'MC'][$doc_type];
        $doc_no = 'TC-' . $prefix . '-' . date('Y') . '-' . str_pad((string)$appt_id, 4, '0', STR_PAD_LEFT)
                . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        $pdf = tc_build_pdf($doc_type, $P, $patientMeta, $doctorMeta, $head, $DOC_TITLES, $doc_no);

        if ($action === 'export') {
            $pdf->Output('I', $doc_no . '.pdf');
            exit;
        }

        $uploadsDir = __DIR__ . '/../uploads/generated_documents/';
        if (!is_dir($uploadsDir)) { @mkdir($uploadsDir, 0755, true); }
        $fileName = 'document_' . $appt_id . '_' . $doc_type . '_' . date('Ymd_His') . '.pdf';
        $pdf->Output('F', $uploadsDir . $fileName);

        $filePathDb = 'uploads/generated_documents/' . $fileName;
        $plain      = tc_plain_text($doc_type, $P, $patientMeta, $doctorMeta, $head);
        $docLabel   = $DOC_TYPES[$doc_type] . ' - Dr. ' . trim((string)($docRow['full_name'] ?? ''));
        $json       = json_encode($P, JSON_UNESCAPED_UNICODE);

        $ins = $conn->prepare("INSERT INTO lab_results (patient_id, file_path, doc_type, doc_label, extracted_text, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->bind_param('issss', $appt['patient_id'], $filePathDb, $doc_type, $docLabel, $plain);
        $ins->execute();
        $ins->close();

        if (tc_has_table($conn, 'issued_documents')) {
            $ins2 = $conn->prepare("INSERT INTO issued_documents (appointment_id, patient_id, doctor_id, doc_type, doc_no, file_path, payload_json, plain_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins2->bind_param('iiisssss', $appt_id, $appt['patient_id'], $doctor_id, $doc_type, $doc_no, $filePathDb, $json, $plain);
            @$ins2->execute();
            $ins2->close();
        }

        $up = $conn->prepare("
            INSERT INTO appointment_document_drafts (appointment_id, doc_type, draft_text, payload_json)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE draft_text = VALUES(draft_text), payload_json = VALUES(payload_json)
        ");
        $up->bind_param('isss', $appt_id, $doc_type, $plain, $json);
        @$up->execute();
        $up->close();

        $_SESSION['toast'] = $DOC_TYPES[$doc_type] . ' issued to patient records.';
        header('Location: patient-records.php?patient_id=' . (int)$appt['patient_id']);
        exit;
    }

    /* validation failed — fall through and re-render with what was submitted */
    $draftPayload = $P;
}

$head = tc_letterhead($templates[$doc_type] ?? [], $docRow);

$page_title       = $DOC_TYPES[$doc_type] . ' — TELE-CARE';
$page_title_short = 'Issue Document';
$active_nav       = 'patients';
require_once __DIR__ . '/includes/header.php';
?>
<style>
.db-wrap{width:100%;max-width:1560px;padding:18px 22px 40px;box-sizing:border-box}
.db-topbar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 16px;background:#fff;border:1px solid var(--border-color,#e5e7eb);border-radius:14px;margin-bottom:14px}
.db-pt{display:flex;align-items:center;gap:10px;min-width:0}
.db-pt-name{font-size:1.05rem;font-weight:800;color:var(--neutral-900,#111827)}
.db-chip{padding:3px 10px;border-radius:999px;background:#e9fbf4;color:#087b53;border:1px solid #bdebd9;font-size:.62rem;font-weight:800;white-space:nowrap}
.db-chip.grey{background:#f3f4f6;color:#4b5563;border-color:#e5e7eb}
.db-doc{margin-left:auto;text-align:right;line-height:1.35}
.db-doc b{font-size:.8rem;color:var(--neutral-900,#111827)}
.db-doc span{display:block;font-size:.64rem;color:var(--neutral-500,#6b7280)}
.db-actions{display:flex;gap:8px;flex-wrap:wrap}
.db-btn{display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 15px;border-radius:9px;border:1px solid var(--border-color,#e5e7eb);background:#fff;color:var(--neutral-800,#1f2937);font-size:.72rem;font-weight:800;cursor:pointer;text-decoration:none;font-family:inherit}
.db-btn:hover{background:#f5f6fa}
.db-btn.primary{background:#9b1a2a;border-color:#9b1a2a;color:#fff}
.db-btn.primary:hover{filter:brightness(1.07);background:#9b1a2a}
.db-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.db-tab{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;border:1px solid var(--border-color,#e5e7eb);background:#fff;color:var(--neutral-700,#374151);font-size:.74rem;font-weight:800;cursor:pointer;font-family:inherit}
.db-tab.active{background:#9b1a2a;border-color:#9b1a2a;color:#fff}
.db-tab .dot{width:7px;height:7px;border-radius:50%;background:#10b981;display:none}
.db-tab.filled .dot{display:block}
.db-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:18px;align-items:start}
.db-card{background:#fff;border:1px solid var(--border-color,#e5e7eb);border-radius:14px;padding:16px;margin-bottom:14px}
.db-card h3{margin:0 0 12px;font-size:.92rem;color:var(--neutral-900,#111827);display:flex;align-items:center;gap:8px}
.db-card h3 .spacer{margin-left:auto}
.db-field{margin-bottom:11px}
.db-field label{display:block;font-size:.63rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--neutral-600,#4b5563);margin-bottom:5px}
.db-in,.db-ta,.db-sel{width:100%;box-sizing:border-box;padding:9px 11px;border:1px solid var(--border-color,#e5e7eb);border-radius:8px;font:inherit;font-size:.78rem;color:var(--neutral-900,#111827);background:#fff;outline:none}
.db-in:focus,.db-ta:focus,.db-sel:focus{border-color:#9b1a2a}
.db-ta{resize:vertical;min-height:70px;line-height:1.5}
.db-row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.drug-block{border:1px solid var(--border-color,#e5e7eb);border-radius:11px;padding:12px;margin-bottom:11px;background:#fcfcfd}
.drug-head{display:flex;align-items:center;gap:8px;margin-bottom:10px}
.drug-num{width:20px;height:20px;border-radius:50%;background:#9b1a2a;color:#fff;font-size:.62rem;font-weight:800;display:grid;place-items:center;flex-shrink:0}
.drug-title{font-size:.74rem;font-weight:800;color:var(--neutral-800,#1f2937)}
.drug-del{margin-left:auto;border:none;background:none;color:#b91c1c;cursor:pointer;font-size:.9rem;padding:2px 6px;border-radius:6px}
.drug-del:hover{background:#fee2e2}
.add-drug{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;border:1px solid #bdebd9;background:#e9fbf4;color:#087b53;font-size:.68rem;font-weight:800;cursor:pointer;font-family:inherit}
.lab-cat{border:1px solid var(--border-color,#e5e7eb);border-radius:10px;margin-bottom:8px;overflow:hidden}
.lab-cat-head{display:flex;align-items:center;gap:9px;padding:11px 13px;cursor:pointer;background:#fff;user-select:none}
.lab-cat-head:hover{background:#f8f9fc}
.lab-cat-name{font-size:.75rem;font-weight:800;color:var(--neutral-800,#1f2937)}
.lab-count{margin-left:auto;font-size:.6rem;font-weight:800;padding:2px 8px;border-radius:999px;background:#9b1a2a;color:#fff;display:none}
.lab-count.on{display:inline-block}
.lab-arrow{font-size:.7rem;color:var(--neutral-500,#6b7280);transition:transform .18s}
.lab-cat.open .lab-arrow{transform:rotate(90deg)}
.lab-items{display:none;padding:4px 13px 12px;grid-template-columns:1fr 1fr;gap:6px}
.lab-cat.open .lab-items{display:grid}
.lab-item{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border-color,#e5e7eb);border-radius:8px;font-size:.71rem;color:var(--neutral-800,#1f2937);cursor:pointer;background:#fff}
.lab-item:hover{background:#f8f9fc}
.lab-item.sel{background:#eef6ff;border-color:#9b1a2a}
.lab-item input{accent-color:#9b1a2a;width:15px;height:15px;flex-shrink:0}
.sel-strip{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.sel-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 9px;border-radius:999px;background:#f3f5ff;border:1px solid #dbe2ff;font-size:.63rem;font-weight:700;color:#28417a}
.sel-pill button{border:none;background:none;cursor:pointer;color:#28417a;font-size:.75rem;line-height:1;padding:0}
/* ---------- live preview ---------- */
.pv-wrap{position:sticky;top:14px}
.pv-label{display:flex;align-items:center;gap:8px;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--neutral-500,#6b7280);margin-bottom:8px}
.pv-paper{background:#fff;border:1px solid var(--border-color,#e5e7eb);border-radius:4px;box-shadow:0 2px 10px rgba(0,0,0,.05);padding:34px 40px;min-height:720px;color:#1a1a1a;font-family:'Times New Roman',Georgia,serif;font-size:.85rem;line-height:1.6;overflow-wrap:anywhere}
.pv-head{text-align:center;margin-bottom:6px}
.pv-clinic{font-size:1.05rem;font-weight:700;text-transform:uppercase;letter-spacing:.02em}
.pv-clinic-sub{font-size:.72rem;color:#333;margin-top:2px;line-height:1.4}
.pv-rule{border:none;border-top:1px solid #1a1a1a;margin:10px 0 16px}
.pv-title{text-align:center;font-size:1rem;font-weight:700;letter-spacing:.04em;text-decoration:underline;margin-bottom:14px}
.pv-row{display:flex;justify-content:space-between;gap:20px;margin:4px 0;font-size:.82rem}
.pv-row span{flex:1}
.pv-row .fill{border-bottom:1px solid #999;padding-bottom:1px}
.pv-rx{font-size:1.6rem;font-weight:700;font-family:Georgia,serif;margin:16px 0 8px}
.pv-drug{margin-bottom:16px;font-size:.85rem}
.pv-drug .nm{font-weight:700}
.pv-drug .line{margin-top:3px}
.pv-drug .line b{font-weight:700}
.pv-sec{font-weight:700;margin:14px 0 4px;font-size:.85rem}
.pv-body{font-size:.85rem;line-height:1.7;text-align:justify;margin:6px 0}
.pv-labgroup{margin:12px 0}
.pv-labgroup-title{font-weight:700;font-size:.83rem;margin-bottom:5px}
.pv-labgrid{display:flex;flex-wrap:wrap;gap:6px 16px}
.pv-labitem{font-size:.8rem;white-space:nowrap}
.pv-labitem .box{display:inline-block;width:11px;height:11px;border:1px solid #333;margin-right:5px;position:relative;top:1px}
.pv-labitem.sel .box{background:#1a1a1a}
.pv-foot{font-size:.78rem;font-style:italic;margin-top:16px}
.pv-sign{margin-top:44px}
.pv-sign b{font-size:.85rem;display:block;border-top:1px solid #1a1a1a;padding-top:4px;width:260px}
.pv-sign span{display:block;font-size:.8rem}
.pv-empty{color:#9ca3af;font-style:italic;font-size:.78rem}
.db-alert{padding:11px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:.73rem;font-weight:700;margin-bottom:12px}
@media(max-width:1180px){.db-grid{grid-template-columns:1fr}.pv-wrap{position:static}.lab-items{grid-template-columns:1fr}}
@media(max-width:600px){.db-wrap{padding:12px}.db-row2{grid-template-columns:1fr}.db-doc{margin-left:0;text-align:left}}
</style>

<main class="page db-wrap">

  <?php if ($error !== ''): ?><div class="db-alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="db-topbar">
    <div class="db-pt">
      <div class="db-pt-name"><?= htmlspecialchars($patientMeta['name']) ?></div>
      <span class="db-chip">Enc <?= htmlspecialchars($patientMeta['pid']) ?></span>
      <span class="db-chip grey"><?= htmlspecialchars(trim($patientMeta['age'] . ' yrs • ' . $patientMeta['sex'], ' •')) ?></span>
    </div>
    <div class="db-doc">
      <b><?= htmlspecialchars(trim($doctorMeta['name'] . ($doctorMeta['credentials'] !== '' ? ', ' . $doctorMeta['credentials'] : ''))) ?></b>
            <span><?= htmlspecialchars($doctorMeta['license'] !== '' ? 'PRC: ' . $doctorMeta['license'] : '') ?></span>
    </div>
    <div class="db-actions">
     
      <button type="button" class="db-btn" onclick="dbSubmit('export')">Export PDF</button>
      <button type="button" class="db-btn primary" onclick="dbSubmit('issue')">Sign &amp; Issue</button>
    </div>
  </div>

  <div class="db-tabs">
    <?php foreach ($DOC_TYPES as $k => $label): ?>
      <a class="db-tab <?= $doc_type === $k ? 'active' : '' ?>"
         href="send_document.php?appt_id=<?= $appt_id ?>&doc_type=<?= $k ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="db-grid">
    <!-- ================= BUILDER ================= -->
    <div class="db-left">

      <div class="db-card">
        <h3>Encounter</h3>
        <div class="db-field">
          <label>Clinical Impression / Diagnosis</label>
          <input type="text" class="db-in" id="f_diagnosis" placeholder="e.g. Acute Bronchitis (J20.9) with Moderate Cough">
        </div>
      </div>

      <?php if ($doc_type === 'prescription'): ?>
      <div class="db-card">
        <h3>Medication Orders <span class="spacer"></span>
          <button type="button" class="add-drug" onclick="addDrug()">+ Add Drug</button>
        </h3>
        <div id="drugList"></div>
      </div>
      <div class="db-card">
        <h3>Physician Special Instructions &amp; Lifestyle Management</h3>
        <div class="db-field">
          <textarea class="db-ta" id="f_rx_notes" rows="4" placeholder="Optional"></textarea>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($doc_type === 'med_cert'): ?>
      <div class="db-card">
        <h3>Medical Certificate Configuration</h3>
        <div class="db-row2">
          <div class="db-field"><label>Recommended Leave From</label><input type="date" class="db-in" id="f_mc_from"></div>
          <div class="db-field"><label>Recommended Leave To</label><input type="date" class="db-in" id="f_mc_to"></div>
        </div>
        <div class="db-field">
          <label>Duty Status / Fitness Assessment</label>
          <select class="db-sel" id="f_mc_fitness">
            <?php foreach ($FITNESS_OPTIONS as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="db-field">
          <label>Clinical Remarks &amp; Physical Limitations</label>
          <textarea class="db-ta" id="f_mc_remarks" rows="4"></textarea>
        </div>
      </div>
      <div class="db-card">
        <h3>Additional Instructions</h3>
        <div class="db-field"><textarea class="db-ta" id="f_mc_notes" rows="3" placeholder="Optional"></textarea></div>
      </div>
      <?php endif; ?>

      <?php if ($doc_type === 'lab_request'): ?>
      <div class="db-card">
        <h3>Diagnostic Orders &amp; Panels <span class="spacer"></span>
          <select class="db-sel" id="f_lab_priority" style="width:auto;padding:6px 10px;font-size:.7rem">
            <option value="Routine">Routine</option>
            <option value="STAT">STAT</option>
            <option value="Urgent">Urgent</option>
          </select>
        </h3>
        <div class="sel-strip" id="selStrip"></div>
        <div id="labCats"></div>
      </div>
      <div class="db-card">
        <h3>Clinical Indication</h3>
        <div class="db-field">
          <textarea class="db-ta" id="f_lab_indication" rows="3" placeholder="Reason for the request"></textarea>
        </div>
        <div class="db-field">
          <label>Instructions to Facility</label>
          <textarea class="db-ta" id="f_lab_notes" rows="3" placeholder="Optional"></textarea>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- ================= LIVE PREVIEW ================= -->
    <div class="pv-wrap">
      <div class="pv-label">Live Document Preview</div>
      <div class="pv-paper" id="preview"></div>
    </div>
  </div>
</main>

<form method="POST" id="dbForm" style="display:none">
  <input type="hidden" name="appt_id"  value="<?= $appt_id ?>">
  <input type="hidden" name="doc_type" value="<?= htmlspecialchars($doc_type) ?>">
  <input type="hidden" name="action"   id="dbAction" value="">
  <input type="hidden" name="payload"  id="dbPayload" value="">
</form>

<script>
const DOC_TYPE   = <?= json_encode($doc_type) ?>;
const DOC_TITLE  = <?= json_encode($DOC_TITLES[$doc_type]) ?>;
const LAB        = <?= json_encode($LAB_CATALOG, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const HEAD       = <?= json_encode($head, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const PT         = <?= json_encode($patientMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const DR         = <?= json_encode($doctorMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let   S          = <?= json_encode($draftPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function nl(s){return esc(s).replace(/\n/g,'<br>');}
function val(id){const e=document.getElementById(id);return e?e.value:'';}
function setVal(id,v){const e=document.getElementById(id);if(e)e.value=v||'';}
function fmtDate(d){
  if(!d) return '';
  const p=d.split('-'); if(p.length!==3) return d;
  const m=['January','February','March','April','May','June','July','August','September','October','November','December'];
  return m[parseInt(p[1],10)-1]+' '+parseInt(p[2],10)+', '+p[0];
}

/* ---------------- drugs ---------------- */
function drugTemplate(i,d){
  return `<div class="drug-block" data-i="${i}">
    <div class="drug-head">
      <div class="drug-num">${i+1}</div>
      <div class="drug-title">Medication Order ${i+1}</div>
      <button type="button" class="drug-del" onclick="delDrug(${i})" title="Remove">&#10005;</button>
    </div>
    <div class="db-row2">
      <div class="db-field"><label>Drug Name &amp; Formulation</label>
        <input class="db-in" data-f="name" value="${esc(d.name)}" placeholder="Cefuroxime Axetil 500mg Film-Coated Tablet"></div>
      <div class="db-field"><label>Dispense / Quantity</label>
        <input class="db-in" data-f="dispense" value="${esc(d.dispense)}" placeholder="#14 (Fourteen) Tablets"></div>
    </div>
    <div class="db-row2">
      <div class="db-field"><label>Frequency &amp; Route</label>
        <input class="db-in" data-f="freq" value="${esc(d.freq)}" placeholder="1 tablet every 12 hours orally"></div>
      <div class="db-field"><label>Duration</label>
        <input class="db-in" data-f="duration" value="${esc(d.duration)}" placeholder="For 7 continuous days"></div>
    </div>
    <div class="db-field"><label>Signa / Patient Instructions</label>
      <textarea class="db-ta" data-f="sig" rows="2" placeholder="Take after meals...">${esc(d.sig)}</textarea></div>
  </div>`;
}
function renderDrugs(){
  const box=document.getElementById('drugList'); if(!box) return;
  if(!S.prescription.drugs.length) S.prescription.drugs=[{name:'',dispense:'',freq:'',duration:'',sig:''}];
  box.innerHTML=S.prescription.drugs.map((d,i)=>drugTemplate(i,d)).join('');
  box.querySelectorAll('.drug-block').forEach(block=>{
    const i=parseInt(block.dataset.i,10);
    block.querySelectorAll('[data-f]').forEach(inp=>{
      inp.addEventListener('input',()=>{ S.prescription.drugs[i][inp.dataset.f]=inp.value; renderPreview(); });
    });
  });
}
function addDrug(){ S.prescription.drugs.push({name:'',dispense:'',freq:'',duration:'',sig:''}); renderDrugs(); renderPreview(); }
function delDrug(i){
  S.prescription.drugs.splice(i,1);
  if(!S.prescription.drugs.length) S.prescription.drugs=[{name:'',dispense:'',freq:'',duration:'',sig:''}];
  renderDrugs(); renderPreview();
}

/* ---------------- lab picker ---------------- */
function renderLab(){
  const box=document.getElementById('labCats'); if(!box) return;
  box.innerHTML=Object.keys(LAB).map((cat,ci)=>{
    const items=LAB[cat].map((t,ti)=>{
      const on=(S.lab_request.tests[cat]||[]).indexOf(t)>-1;
      return `<label class="lab-item ${on?'sel':''}" data-cat="${esc(cat)}" data-test="${esc(t)}">
        <input type="checkbox" ${on?'checked':''} onchange="toggleTest(this)"><span>${esc(t)}</span></label>`;
    }).join('');
    const n=(S.lab_request.tests[cat]||[]).length;
    return `<div class="lab-cat ${n?'open':''}" id="cat${ci}">
      <div class="lab-cat-head" onclick="toggleCat(${ci})">
        <span class="lab-arrow">&#9656;</span>
        <span class="lab-cat-name">${esc(cat)}</span>
        <span class="lab-count ${n?'on':''}">${n} selected</span>
      </div>
      <div class="lab-items">${items}</div>
    </div>`;
  }).join('');
  renderStrip();
}
function toggleCat(ci){ document.getElementById('cat'+ci).classList.toggle('open'); }
function toggleTest(cb){
  const lab=cb.closest('.lab-item');
  const cat=lab.dataset.cat, test=lab.dataset.test;
  if(!S.lab_request.tests[cat]) S.lab_request.tests[cat]=[];
  const arr=S.lab_request.tests[cat];
  const ix=arr.indexOf(test);
  if(cb.checked && ix<0) arr.push(test);
  if(!cb.checked && ix>-1) arr.splice(ix,1);
  if(!arr.length) delete S.lab_request.tests[cat];
  lab.classList.toggle('sel',cb.checked);
  const head=lab.closest('.lab-cat').querySelector('.lab-count');
  const n=(S.lab_request.tests[cat]||[]).length;
  head.textContent=n+' selected'; head.classList.toggle('on',n>0);
  renderStrip(); renderPreview();
}
function removeTest(cat,test){
  const arr=S.lab_request.tests[cat]||[];
  const ix=arr.indexOf(test); if(ix>-1) arr.splice(ix,1);
  if(!arr.length) delete S.lab_request.tests[cat];
  renderLab(); renderPreview();
}
function renderStrip(){
  const strip=document.getElementById('selStrip'); if(!strip) return;
  const out=[];
  Object.keys(S.lab_request.tests).forEach(cat=>{
    S.lab_request.tests[cat].forEach(t=>{
      out.push(`<span class="sel-pill">${esc(t)}<button type="button" onclick="removeTest('${esc(cat).replace(/'/g,"\\'")}','${esc(t).replace(/'/g,"\\'")}')">&#10005;</button></span>`);
    });
  });
  strip.innerHTML=out.join('');
}

/* ---------------- preview ---------------- */
function headerHTML(){
  const sub=[];
  if(HEAD.address) sub.push(esc(HEAD.address));
  if(HEAD.contact) sub.push('Contact No.: '+esc(HEAD.contact));
  return `<div class="pv-head">
    <div class="pv-clinic">${esc(HEAD.clinic)}</div>
    <div class="pv-clinic-sub">${sub.join('<br>')}</div>
  </div><hr class="pv-rule">`;
}
function patientLineHTML(nameLabel){
  return `<div class="pv-row"><span>${nameLabel}: <span class="fill">${esc(PT.name)}</span></span>
    <span style="flex:.5">Age: <span class="fill">${esc(PT.age)}</span></span>
    <span style="flex:.5">Sex: <span class="fill">${esc(PT.sex)}</span></span></div>
  <div class="pv-row"><span>Address: <span class="fill">${esc(PT.address||'')}</span></span>
    <span style="flex:.6">Date: <span class="fill">${esc(PT.encdate)}</span></span></div>`;
}
function signHTML(){
  return `${HEAD.footer?`<div class="pv-foot">${esc(HEAD.footer)}</div>`:''}
  <div class="pv-sign">
    <b>${esc(DR.name)}${DR.credentials?', '+esc(DR.credentials):''}</b>
    ${DR.license?`<span>License No.: ${esc(DR.license)}</span>`:''}
  </div>`;
}
function renderPreview(){
  let body='';
  if(DOC_TYPE==='prescription'){
    body += patientLineHTML("Patient's Name");
    body += `<div class="pv-rx">R&#8477;</div>`;
    const drugs=S.prescription.drugs.filter(d=>d.name.trim()!=='');
    if(!drugs.length){ body+=`<div class="pv-empty">No medication orders yet.</div>`; }
    drugs.forEach((d)=>{
      const tail=[d.freq.trim(),d.duration.trim()].filter(Boolean).join(' — ');
      body+=`<div class="pv-drug">
        <div class="nm">${esc(d.name)}</div>
        ${tail?`<div class="line">${esc(tail)}</div>`:''}
        ${d.dispense.trim()?`<div class="line"><b>Dispense:</b> ${esc(d.dispense)}</div>`:''}
        ${d.sig.trim()?`<div class="line"><b>Label:</b> ${esc(d.sig)}</div>`:''}
      </div>`;
    });
    if(S.diagnosis.trim()){ body+=`<div class="pv-body">${esc(S.diagnosis)}</div>`; }
    if(S.prescription.notes.trim()){ body+=`<div class="pv-body">${nl(S.prescription.notes)}</div>`; }
        body += signHTML();

  } else if(DOC_TYPE==='med_cert'){
    const M=S.med_cert;
    body += `<div class="pv-row" style="justify-content:flex-end"><span style="flex:0 0 auto">Date: <span class="fill">${esc(PT.encdate)}</span></span></div>`;
    body += `<div class="pv-body" style="margin-top:14px">To whom it may concern:</div>`;
    let rest='';
    if(M.leave_from||M.leave_to){ rest=` and advised to rest from ${esc(fmtDate(M.leave_from)||'____')} to ${esc(fmtDate(M.leave_to)||'____')}`; }
    body += `<div class="pv-body">This is to certify that <b>${esc(PT.name)}</b> presently residing at ${esc(PT.address||'____')}
      is ${esc(PT.age||'____')} years old, ${esc(PT.sex||'____')} and was consulted/examined/treated via TELE-CARE teleconsultation
      on ${esc(PT.encdate)}${rest}.</div>`;
    if(S.diagnosis.trim()){
      body += `<div class="pv-sec">Assessment/Impression:</div><div class="pv-body">${esc(S.diagnosis)}</div>`;
    }
    if(M.remarks.trim()){
      body += `<div class="pv-sec">Recommendations/Remarks:</div><div class="pv-body">${nl(M.remarks)}</div>`;
    }
    if(M.fitness.trim()){ body += `<div class="pv-body">${esc(M.fitness)}</div>`; }
    if(M.notes.trim()){ body += `<div class="pv-body">${nl(M.notes)}</div>`; }
    body += `<div class="pv-body" style="margin-top:14px">This document can be used for non-medico-legal purposes only.</div>`;
    body += signHTML();

  } else {
    const L=S.lab_request;
    body += patientLineHTML("Name");
    if(S.diagnosis.trim()){ body += `<div class="pv-body">Indication: ${esc(S.diagnosis)}</div>`; }
    Object.keys(LAB).forEach(cat=>{
      const sel = L.tests[cat] || [];
      body += `<div class="pv-labgroup"><div class="pv-labgroup-title">${esc(cat)}</div>
        <div class="pv-labgrid">${LAB[cat].map(t=>{
          const on = sel.indexOf(t) > -1;
          return `<span class="pv-labitem${on?' sel':''}"><span class="box"></span>${esc(t)}</span>`;
        }).join('')}</div></div>`;
    });
    if(L.indication.trim()){ body += `<div class="pv-body"><b>Clinical Indication:</b> ${nl(L.indication)}</div>`; }
    if(L.notes.trim()){ body += `<div class="pv-body"><b>Instructions to Facility:</b> ${nl(L.notes)}</div>`; }
    body += signHTML();
  }

  document.getElementById('preview').innerHTML =
    headerHTML() +
    `<div class="pv-title">${esc(DOC_TITLE)}</div>` +
    body;
}

/* ---------------- binding ---------------- */
function bind(id,setter){
  const e=document.getElementById(id); if(!e) return;
  const ev=(e.tagName==='SELECT'||e.type==='date')?'change':'input';
  e.addEventListener(ev,()=>{ setter(e.value); renderPreview(); });
}
function hydrate(){
  setVal('f_diagnosis',S.diagnosis);
  setVal('f_rx_notes',S.prescription.notes);
  setVal('f_mc_from',S.med_cert.leave_from);
  setVal('f_mc_to',S.med_cert.leave_to);
  setVal('f_mc_fitness',S.med_cert.fitness);
  setVal('f_mc_remarks',S.med_cert.remarks);
  setVal('f_mc_notes',S.med_cert.notes);
  setVal('f_lab_priority',S.lab_request.priority);
  setVal('f_lab_indication',S.lab_request.indication);
  setVal('f_lab_notes',S.lab_request.notes);

  bind('f_diagnosis',      v=>S.diagnosis=v);
  bind('f_rx_notes',       v=>S.prescription.notes=v);
  bind('f_mc_from',        v=>S.med_cert.leave_from=v);
  bind('f_mc_to',          v=>S.med_cert.leave_to=v);
  bind('f_mc_fitness',     v=>S.med_cert.fitness=v);
  bind('f_mc_remarks',     v=>S.med_cert.remarks=v);
  bind('f_mc_notes',       v=>S.med_cert.notes=v);
  bind('f_lab_priority',   v=>S.lab_request.priority=v);
  bind('f_lab_indication', v=>S.lab_request.indication=v);
  bind('f_lab_notes',      v=>S.lab_request.notes=v);

  renderDrugs();
  renderLab();
  renderPreview();
}

/* ---------------- submit ---------------- */
async function dbSubmit(action){
  const form=document.getElementById('dbForm');
  document.getElementById('dbPayload').value=JSON.stringify(S);
  document.getElementById('dbAction').value=action;


  if(action==='export'){ form.target='_blank'; } else { form.target='_self'; }
  form.submit();
}

hydrate();
</script>

<?php require_once __DIR__ . '/includes/nav.php'; ?>
</body>
</html>