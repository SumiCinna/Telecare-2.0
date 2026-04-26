<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';
require_once 'ocr_api.php';

if (!isset($_SESSION['patient_id'])) {
    header('Location: ../auth/login.php'); exit;
}

$patient_id = $_SESSION['patient_id'];
$p = $conn->query("SELECT * FROM patients WHERE id = $patient_id")->fetch_assoc();
$initials = strtoupper(substr($p['full_name'],0,1).(strpos($p['full_name'],' ')!==false?substr($p['full_name'],strpos($p['full_name'],' ')+1,1):''));

$error   = '';
$result  = null;
$notice  = '';

// ── Handle title edit for previous scans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename_scan') {
  $scan_id    = (int)($_POST['scan_id'] ?? 0);
  $new_label  = trim($_POST['new_label'] ?? '');
  $new_label  = $new_label !== '' ? $new_label : 'Untitled';

  if ($scan_id > 0) {
    $ustmt = $conn->prepare("UPDATE lab_results SET doc_label = ? WHERE id = ? AND patient_id = ?");
    $ustmt->bind_param("sii", $new_label, $scan_id, $patient_id);
    if ($ustmt->execute()) {
      $notice = 'Scan title updated.';
    } else {
      $error = 'Could not update title. Please try again.';
    }
  } else {
    $error = 'Invalid scan selected.';
  }
}

// ── Handle upload + scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc_file'])) {
    $file     = $_FILES['doc_file'];
    $allowed  = ['image/jpeg','image/png','image/jpg','image/bmp','image/tiff','application/pdf'];
    $max_size = 10 * 1024 * 1024; // 10MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
    } elseif (!in_array($file['type'], $allowed)) {
        $error = 'Only JPG, PNG, BMP, TIFF, or PDF files are allowed.';
    } elseif ($file['size'] > $max_size) {
        $error = 'File too large. Max 10MB.';
    } else {
        $upload_dir = '../uploads/ocr/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fname    = uniqid('ocr_') . '.' . $ext;
        $filepath = $upload_dir . $fname;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $abs_path  = realpath($filepath);
      $ocr_result = ocr_space_scan($abs_path);

      if ($ocr_result['success']) {
        $text     = $ocr_result['text'];
        $doc_type = $ocr_result['type'];
                $label    = $_POST['doc_label'] ?? 'Untitled';

                $filepath_db = 'uploads/ocr/' . $fname;
                $stmt = $conn->prepare("
                    INSERT INTO lab_results
                        (patient_id, file_path, doc_type, doc_label, extracted_text, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("issss", $patient_id, $filepath_db, $doc_type, $label, $text);
                $stmt->execute();
                $insert_id = $conn->insert_id;

                $result = [
                    'id'       => $insert_id,
                    'text'     => $text,
                    'type'     => $doc_type,
                    'label'    => $label,
                    'filepath' => 'uploads/ocr/'.$fname,
                ];
            } else {
        $error = 'OCR failed: ' . ($ocr_result['error'] ?? 'Unknown error');
            }
        } else {
            $error = 'Could not save uploaded file.';
        }
    }
}

// ── Fetch past scans
$scan_per_page = 5;
$scan_page = max(1, (int)($_GET['scan_page'] ?? 1));

$scan_total_res = $conn->query("SELECT COUNT(*) AS total FROM lab_results WHERE patient_id = $patient_id");
$scan_total_row = $scan_total_res ? $scan_total_res->fetch_assoc() : ['total' => 0];
$scan_total = (int)($scan_total_row['total'] ?? 0);
$scan_total_pages = max(1, (int)ceil($scan_total / $scan_per_page));
$scan_page = min($scan_page, $scan_total_pages);
$scan_offset = ($scan_page - 1) * $scan_per_page;

$past_scans = $conn->query("
  SELECT * FROM lab_results
  WHERE patient_id = $patient_id
  ORDER BY uploaded_at DESC
  LIMIT $scan_per_page OFFSET $scan_offset
");

$type_labels = [
    'lab_result'   => ['label'=>'Lab Result',   'color'=>'#3F82E3', 'bg'=>'rgba(63,130,227,0.1)',  'icon'=>'🧪'],
    'prescription' => ['label'=>'Prescription', 'color'=>'#f4845f', 'bg'=>'rgba(244,132,95,0.1)',  'icon'=>'💊'],
    'unknown'      => ['label'=>'Document',     'color'=>'#9ab0ae', 'bg'=>'rgba(154,176,174,0.1)', 'icon'=>'📄'],
];

function formatOcrText(string $text, string $type): string {
    if (!$text) return '<span style="color:#9ab0ae;font-style:italic;">No text could be extracted.</span>';

    $text = htmlspecialchars($text);

    // ── STEP 1: Hardcoded common medicine names
    $medicines = ['amoxicillin','metformin','losartan','paracetamol','ibuprofen','aspirin',
                  'amlodipine','atorvastatin','omeprazole','cetirizine','azithromycin',
                  'ciprofloxacin','mefenamic','salbutamol','montelukast','prednisone',
                  'furosemide','lisinopril','hydrochlorothiazide','clopidogrel','insulin'];
    foreach ($medicines as $med) {
        $text = preg_replace('/\b('.preg_quote($med, '/').')\b/i',
            '<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }

    // ── STEP 2: Dynamic — capitalized word before a dosage (e.g. "Fosinopril 10mg", "Colchicine 0.5mg")
    $text = preg_replace(
        '/\b([A-Z][a-zA-Z\-]{2,})(?=\s+\d+\.?\d*\s*(?:mg|ml|mcg|g\b|iu|units?))/i',
        '<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>',
        $text
    );

    // ── STEP 3: Numbered drug list — "1. Fosinopril", "2. Colchicine"
    $text = preg_replace(
        '/(\d+\.\s+)([A-Z][a-zA-Z\-]{2,})(\s+(?:Tablet|Capsule|Cap|Tab|Syrup|Solution|Drops|Cream|Injection)s?)?/i',
        '$1<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$2</mark>$3',
        $text
    );

    // ── STEP 4: Capitalized word directly before Tablet/Capsule/Cap/Tab
    $text = preg_replace(
        '/\b([A-Z][a-zA-Z\-]{2,})\s+(Tablet|Capsule|Cap|Tab)s?\b/i',
        '<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark> $2',
        $text
    );

    // ── Dosage: numbers + mg/ml/mcg/units
    $text = preg_replace('/\b(\d+\.?\d*\s*(?:mg|ml|mcg|units?|g\b|iu))\b/i',
        '<mark style="background:rgba(244,132,95,0.15);color:#c05621;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);

    // ── Frequencies & timing
    $freqs = ['once daily','twice daily','three times daily','four times daily',
              'every 4 hours','every 6 hours','every 8 hours','every 12 hours',
              'morning','bedtime','evening','at night','with meals','after meals','before meals',
              'od','bid','tid','qid','prn','sos','take \d+','sig:','dispense:','refills?:\s*\d+'];
    foreach ($freqs as $f) {
        $text = preg_replace('/\b('.$f.')\b/i',
            '<mark style="background:rgba(244,132,95,0.15);color:#c05621;border-radius:4px;padding:0 3px;font-weight:600;">$1</mark>', $text);
    }

    // ── Lab keywords
    $labs = ['hemoglobin','hematocrit','wbc','rbc','platelet','glucose','cholesterol',
             'creatinine','uric acid','sodium','potassium','normal range','reference range',
             'high','low','abnormal','result','reactive','non-reactive'];
    foreach ($labs as $lab) {
        $text = preg_replace('/\b('.preg_quote($lab, '/').')\b/i',
            '<mark style="background:rgba(34,197,94,0.12);color:#15803d;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }

    // ── Important warnings
    $warns = ['warning','caution','allergy','allergic','do not','avoid','contraindicated',
              'emergency','urgent','immediately','refill','expired','expiry'];
    foreach ($warns as $w) {
        $text = preg_replace('/\b('.preg_quote($w, '/').')\b/i',
            '<mark style="background:rgba(168,85,247,0.12);color:#6d28d9;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }

    $text = nl2br($text);
    return $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link rel="icon" type="image/x-icon" href="/uploads/logo/favicon.ico" />
  <link rel="icon" type="image/png" sizes="32x32" href="/uploads/logo/favicon-32x32.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="/uploads/logo/favicon-16x16.png" />
  <link rel="apple-touch-icon" href="/uploads/logo/apple-touch-icon.png" />
  <link rel="manifest" href="/uploads/logo/site.webmanifest" />
  <title>Scan Document — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root { --red:#C33643; --green:#244441; --blue:#3F82E3; --blue-dark:#2563C4; --blue-light:#EBF2FD; --bg:#EEF3FB; --white:#FFFFFF; --text:#1a2f5e; --muted:#8fa3c8; }
    * { box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; margin:0; }
    h1,h2,h3 { font-family:'Playfair Display',serif; }

    .top-header {
      background:var(--white); padding:1rem 1.5rem;
      display:flex; align-items:center; justify-content:space-between;
      border-bottom:1px solid rgba(63,130,227,0.08);
      box-shadow:0 2px 12px rgba(63,130,227,0.07);
      position:sticky; top:0; z-index:50;
    }
    .back-btn {
      display:inline-flex; align-items:center; gap:0.4rem;
      color:var(--muted); font-size:0.85rem; font-weight:600;
      text-decoration:none; transition:color 0.2s;
    }
    .back-btn:hover { color:var(--blue); }
    .avatar-circle {
      width:38px; height:38px; border-radius:50%;
      background:linear-gradient(135deg,var(--blue),var(--blue-dark));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:0.85rem;
    }

    .page-wrap { max-width:680px; margin:0 auto; padding:1.5rem; }

    .upload-card {
      background:var(--white); border-radius:20px; padding:2rem;
      border:1px solid rgba(63,130,227,0.08);
      box-shadow:0 4px 20px rgba(63,130,227,0.08);
      margin-bottom:1.5rem;
    }
    .drop-zone {
      border:2px dashed rgba(63,130,227,0.3); border-radius:16px;
      padding:2.5rem 1rem; text-align:center; cursor:pointer;
      transition:all 0.25s; background:var(--blue-light);
      position:relative;
    }
    .drop-zone:hover, .drop-zone.dragover { border-color:var(--blue); background:rgba(63,130,227,0.1); }
    .drop-zone input { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
    .drop-zone svg { width:40px; height:40px; stroke:var(--blue); margin:0 auto 0.8rem; display:block; }
    .drop-zone .main-text { font-weight:700; color:var(--blue); font-size:0.95rem; }
    .drop-zone .sub-text  { font-size:0.78rem; color:var(--muted); margin-top:0.3rem; }

    .preview-wrap { margin-top:1rem; display:none; }
    .preview-wrap img { width:100%; max-height:220px; object-fit:contain; border-radius:12px; border:1px solid rgba(63,130,227,0.1); }

    .field-label { display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--muted); margin-bottom:0.4rem; }
    .field-input {
      width:100%; padding:0.72rem 0.9rem; border:1.5px solid rgba(63,130,227,0.15);
      border-radius:12px; font-family:'DM Sans',sans-serif; font-size:0.9rem;
      color:var(--text); background:var(--white); outline:none; transition:border-color 0.2s;
    }
    .field-input:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(63,130,227,0.1); }

    .btn-scan {
      width:100%; padding:0.9rem; border-radius:50px; background:var(--blue); color:#fff;
      font-weight:700; font-size:0.95rem; border:none; cursor:pointer;
      transition:all 0.3s; box-shadow:0 6px 18px rgba(63,130,227,0.3);
      display:flex; align-items:center; justify-content:center; gap:0.5rem;
      font-family:'DM Sans',sans-serif; margin-top:1rem;
    }
    .btn-scan:hover { background:var(--blue-dark); transform:translateY(-2px); }
    .btn-scan:disabled { background:#b0c4e8; cursor:not-allowed; transform:none; box-shadow:none; }

    .alert-error   { background:rgba(195,54,67,0.08); border:1px solid rgba(195,54,67,0.2); color:var(--red); border-radius:12px; padding:0.75rem 1rem; font-size:0.86rem; margin-bottom:1rem; }
    .alert-success { background:rgba(63,130,227,0.08); border:1px solid rgba(63,130,227,0.2); color:var(--blue); border-radius:12px; padding:0.75rem 1rem; font-size:0.86rem; margin-bottom:1rem; }

    .result-card {
      background:var(--white); border-radius:20px; padding:1.5rem;
      border:1px solid rgba(63,130,227,0.12);
      box-shadow:0 4px 20px rgba(63,130,227,0.1);
      margin-bottom:1.5rem; animation:fadeUp 0.4s ease;
    }
    @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    .result-header { display:flex; align-items:center; gap:0.8rem; margin-bottom:1rem; }
    .result-text {
      background:rgba(36,68,65,0.03); border:1px solid rgba(63,130,227,0.1);
      border-radius:12px; padding:1.1rem; font-size:0.84rem; line-height:2;
      color:var(--text); max-height:320px; overflow-y:auto;
      font-family:'DM Sans',sans-serif; word-break:break-word;
    }
    .result-text mark { border-radius:4px; padding:1px 4px; }

    .scan-item {
      background:var(--white); border-radius:16px; padding:1.2rem;
      border:1px solid rgba(63,130,227,0.08); margin-bottom:0.8rem;
      box-shadow:0 2px 10px rgba(63,130,227,0.05);
      cursor:pointer; transition:all 0.2s;
    }
    .scan-item:hover { border-color:rgba(63,130,227,0.25); transform:translateY(-2px); }
    .scan-preview { max-height:0; overflow:hidden; transition:max-height 0.4s ease; }
    .scan-preview.open { max-height:400px; }
    .badge { display:inline-block; padding:0.22rem 0.65rem; border-radius:50px; font-size:0.7rem; font-weight:700; }

    .spinner { width:18px; height:18px; border:2px solid rgba(255,255,255,0.4); border-top-color:#fff; border-radius:50%; animation:spin 0.7s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }

    .result-actions { display:flex; gap:0.8rem; margin-top:1rem; }
    .scan-head-row { display:flex; align-items:center; gap:0.8rem; }
    .scan-main { flex:1; min-width:0; }
    .scan-title { font-weight:700; font-size:0.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    @media (max-width: 700px) {
      .top-header { padding:0.8rem 0.9rem; }
      .top-header > div:first-child { gap:0.55rem !important; }
      .back-btn { font-size:0.78rem; }
      .avatar-circle { width:34px; height:34px; font-size:0.78rem; }

      .page-wrap { padding:1rem 0.85rem 1.4rem; }
      .upload-card, .result-card { border-radius:16px; }
      .upload-card { padding:1.1rem; }
      .result-card { padding:1rem; }

      .drop-zone { padding:1.25rem 0.8rem; }
      .drop-zone svg { width:34px; height:34px; margin-bottom:0.55rem; }
      .drop-zone .main-text { font-size:0.86rem; }
      .drop-zone .sub-text { font-size:0.72rem; }

      .result-header { align-items:flex-start; gap:0.65rem; }
      .result-text { max-height:240px; font-size:0.8rem; line-height:1.85; padding:0.85rem; }
      .result-actions { flex-direction:column; gap:0.55rem; }
      .result-actions > * { width:100%; }

      .scan-item { padding:0.9rem; border-radius:14px; }
      .scan-head-row { align-items:flex-start; gap:0.65rem; }
      .scan-title { font-size:0.84rem; }
    }

    @media (max-width: 480px) {
      .top-header { padding:0.7rem 0.75rem; }
      .top-header > div:first-child > div { font-size:0.9rem !important; }

      .page-wrap { padding:0.8rem 0.7rem 1.2rem; }
      .upload-card, .result-card { padding:0.85rem; border-radius:14px; }
      .field-input { font-size:0.84rem; padding:0.66rem 0.78rem; }
      .btn-scan { font-size:0.86rem; padding:0.82rem; }

      .result-header > div:first-child { font-size:1.7rem !important; }
      .result-header > div:last-child { margin-left:0 !important; font-size:0.7rem !important; }

      .scan-head-row { flex-wrap:wrap; }
      .scan-main { width:100%; }
      .scan-title { white-space:normal; overflow:visible; text-overflow:clip; }
      .scan-head-row .badge { order:3; }
      .scan-head-row svg[id^='arrow-'] { margin-left:auto; }
    }
  </style>
</head>
<body>

<div class="top-header">
  <div style="display:flex;align-items:center;gap:1rem;">
    <a href="../dashboard.php" class="back-btn">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      Back
    </a>
    <div style="font-family:'Playfair Display',serif;font-weight:700;font-size:1rem;color:var(--text);">Document Scanner</div>
  </div>
  <div style="display:flex;align-items:center;gap:0.8rem;">
    <?php if($p['profile_photo']): ?>
      <img src="../<?= htmlspecialchars($p['profile_photo']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid rgba(63,130,227,0.2);" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"/>
      <div class="avatar-circle" style="display:none;"><?= $initials ?></div>
    <?php else: ?>
      <div class="avatar-circle"><?= $initials ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="page-wrap">

  <div style="margin-bottom:1.5rem;">
    <h2 style="font-size:1.5rem;margin-bottom:0.3rem;">Scan a Document</h2>
    <p style="font-size:0.85rem;color:var(--muted);">Upload a photo of your lab result or prescription — we'll extract the text automatically.</p>
  </div>

  <div class="upload-card">
    <?php if($notice): ?><div class="alert-success">✅ <?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if($error): ?><div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="scanForm">
      <div style="margin-bottom:1rem;">
        <label class="field-label">Document Label</label>
        <input type="text" name="doc_label" class="field-input" placeholder="e.g. CBC Result March 2026, Dr. Santos Prescription"/>
      </div>

      <label class="field-label">Upload Image</label>
      <div class="drop-zone" id="dropZone">
        <input type="file" name="doc_file" id="fileInput" accept="image/*,.pdf" required onchange="previewFile(this)"/>
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
        <div class="main-text">Click to upload or drag & drop</div>
        <div class="sub-text">JPG, PNG, BMP, TIFF, or PDF — max 10MB</div>
      </div>

      <div class="preview-wrap" id="previewWrap">
        <img id="previewImg" src="" alt="Preview" style="display:none;"/>
        <div id="pdfPreview" style="display:none;align-items:center;gap:1rem;background:rgba(195,54,67,0.06);border:1px solid rgba(195,54,67,0.15);border-radius:12px;padding:1rem 1.2rem;">
          <div style="font-size:3rem;line-height:1;">📄</div>
          <div>
            <div style="font-weight:700;color:var(--red);font-size:0.9rem;">PDF File Ready</div>
            <div style="font-size:0.75rem;color:var(--muted);margin-top:0.2rem;" id="previewName"></div>
          </div>
        </div>
        <div id="previewName" style="font-size:0.75rem;color:var(--muted);margin-top:0.5rem;text-align:center;"></div>
      </div>

      <button type="submit" class="btn-scan" id="scanBtn">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Scan Document
      </button>
    </form>
  </div>

  <?php if($result): ?>
  <div class="result-card">
    <div class="result-header">
      <div style="font-size:2rem;"><?= $type_labels[$result['type']]['icon'] ?></div>
      <div>
        <div style="font-weight:700;font-size:1rem;"><?= htmlspecialchars($result['label']) ?></div>
        <span class="badge" style="background:<?= $type_labels[$result['type']]['bg'] ?>;color:<?= $type_labels[$result['type']]['color'] ?>;">
          <?= $type_labels[$result['type']]['label'] ?>
        </span>
      </div>
      <div style="margin-left:auto;font-size:0.75rem;color:var(--muted);">✓ Saved</div>
    </div>

    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.7rem;">Extracted & Highlighted</div>
    <div class="result-text" id="resultTextBox"><?= formatOcrText($result['text'], $result['type']) ?></div>

    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.8rem;">
      <span style="font-size:0.68rem;background:rgba(63,130,227,0.12);color:var(--blue);padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">💊 Medicine</span>
      <span style="font-size:0.68rem;background:rgba(244,132,95,0.12);color:#f4845f;padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">⚡ Dosage/Freq</span>
      <span style="font-size:0.68rem;background:rgba(34,197,94,0.1);color:#16a34a;padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">🧪 Lab Value</span>
      <span style="font-size:0.68rem;background:rgba(168,85,247,0.1);color:#7c3aed;padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">⚠️ Important</span>
    </div>

    <div class="result-actions">
      <button onclick="copyRawText()" style="flex:1;padding:0.65rem;border-radius:50px;background:var(--blue-light);color:var(--blue);border:none;font-weight:700;font-size:0.82rem;cursor:pointer;font-family:'DM Sans',sans-serif;" id="copyBtn">
        Copy Text
      </button>
      <a href="../dashboard.php" style="flex:1;padding:0.65rem;border-radius:50px;background:var(--blue);color:#fff;font-weight:700;font-size:0.82rem;text-decoration:none;text-align:center;display:block;">
        Back to Dashboard
      </a>
    </div>
  </div>

  <script>
    function copyRawText() {
      navigator.clipboard.writeText(`<?= addslashes($result['text']) ?>`);
      const btn = document.getElementById('copyBtn');
      btn.textContent = '✓ Copied!';
      setTimeout(() => btn.textContent = 'Copy Text', 2000);
    }
  </script>
  <?php endif; ?>

  <?php if($past_scans && $past_scans->num_rows > 0): ?>
  <div style="margin-bottom:1rem;">
    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);border-bottom:1px solid rgba(63,130,227,0.1);padding-bottom:0.5rem;margin-bottom:1rem;">Previous Scans</div>
    <?php while($scan = $past_scans->fetch_assoc()):
      $t   = $type_labels[$scan['doc_type']] ?? $type_labels['unknown'];
      $ext = strtolower(pathinfo($scan['file_path'], PATHINFO_EXTENSION));
    ?>
    <div class="scan-item" onclick="toggleScan(<?= $scan['id'] ?>)">
      <div class="scan-head-row">
        <?php if($ext === 'pdf'): ?>
          <div style="width:44px;height:44px;border-radius:10px;background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.2);display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;gap:1px;">
            <span style="font-size:1.2rem;line-height:1;">📄</span>
            <span style="font-size:0.5rem;font-weight:700;color:#C33643;letter-spacing:0.04em;">PDF</span>
          </div>
        <?php else: ?>
          <img src="../<?= htmlspecialchars($scan['file_path']) ?>" style="width:44px;height:44px;object-fit:cover;border-radius:10px;border:1px solid rgba(63,130,227,0.1);flex-shrink:0;"/>
        <?php endif; ?>
        <div class="scan-main">
          <div class="scan-title"><?= htmlspecialchars($scan['doc_label'] ?: 'Untitled') ?></div>
          <div style="font-size:0.75rem;color:var(--muted);margin-top:0.1rem;"><?= date('M d, Y — g:i A', strtotime($scan['uploaded_at'])) ?></div>
          <form method="POST" style="display:flex;gap:0.45rem;margin-top:0.55rem;" onclick="event.stopPropagation();">
            <input type="hidden" name="action" value="rename_scan"/>
            <input type="hidden" name="scan_id" value="<?= (int)$scan['id'] ?>"/>
            <input type="hidden" name="scan_page" value="<?= (int)$scan_page ?>"/>
            <input
              type="text"
              name="new_label"
              value="<?= htmlspecialchars($scan['doc_label'] ?: 'Untitled') ?>"
              class="field-input"
              style="padding:0.45rem 0.65rem;font-size:0.74rem;border-radius:9px;"
              maxlength="120"
            />
            <button
              type="submit"
              style="border:none;border-radius:9px;background:var(--blue-light);color:var(--blue);font-weight:700;font-size:0.72rem;padding:0.45rem 0.68rem;cursor:pointer;white-space:nowrap;"
            >
              Save title
            </button>
          </form>
        </div>
        <span class="badge" style="background:<?= $t['bg'] ?>;color:<?= $t['color'] ?>;"><?= $t['label'] ?></span>
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" id="arrow-<?= $scan['id'] ?>" style="color:var(--muted);flex-shrink:0;transition:transform 0.25s;"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
      </div>
      <div class="scan-preview" id="scan-<?= $scan['id'] ?>">
        <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(63,130,227,0.08);">
          <div class="result-text" style="max-height:200px;">
            <?= formatOcrText($scan['extracted_text'] ?? '', $scan['doc_type']) ?>
          </div>
        </div>
      </div>
    </div>
    <?php endwhile; ?>

    <?php if ($scan_total_pages > 1): ?>
    <div style="display:flex;justify-content:flex-end;gap:0.45rem;flex-wrap:wrap;margin-top:0.8rem;">
      <?php if ($scan_page > 1): ?>
        <a href="?scan_page=<?= $scan_page - 1 ?>" style="padding:0.35rem 0.7rem;border-radius:8px;background:var(--blue-light);color:var(--blue);font-size:0.76rem;font-weight:700;text-decoration:none;">Prev</a>
      <?php endif; ?>

      <?php for ($i = 1; $i <= $scan_total_pages; $i++): ?>
        <a href="?scan_page=<?= $i ?>" style="padding:0.35rem 0.62rem;border-radius:8px;font-size:0.76rem;font-weight:700;text-decoration:none;<?= $i === $scan_page ? 'background:var(--blue);color:#fff;' : 'background:rgba(63,130,227,0.08);color:var(--blue);' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <?php if ($scan_page < $scan_total_pages): ?>
        <a href="?scan_page=<?= $scan_page + 1 ?>" style="padding:0.35rem 0.7rem;border-radius:8px;background:var(--blue-light);color:var(--blue);font-size:0.76rem;font-weight:700;text-decoration:none;">Next</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<script>
  function previewFile(input) {
    const wrap = document.getElementById('previewWrap');
    const img  = document.getElementById('previewImg');
    const pdf  = document.getElementById('pdfPreview');
    const name = document.getElementById('previewName');
    if (input.files && input.files[0]) {
      const file = input.files[0];
      wrap.style.display = 'block';
      name.textContent = file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
      if (file.type === 'application/pdf') {
        img.style.display = 'none';
        pdf.style.display = 'flex';
      } else {
        pdf.style.display = 'none';
        img.style.display = 'block';
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; };
        reader.readAsDataURL(file);
      }
    }
  }

  document.getElementById('scanForm').addEventListener('submit', function() {
    const btn = document.getElementById('scanBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> Scanning...';
  });

  const dz    = document.getElementById('dropZone');
  const input = document.getElementById('fileInput');

  dz.addEventListener('dragover',  e => { e.preventDefault(); dz.classList.add('dragover'); });
  dz.addEventListener('dragleave', e => { e.preventDefault(); dz.classList.remove('dragover'); });
  dz.addEventListener('drop', e => {
    e.preventDefault();
    dz.classList.remove('dragover');
    if (e.dataTransfer.files.length > 0) {
      const dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      input.files = dt.files;
      previewFile(input);
    }
  });

  function toggleScan(id) {
    const el = document.getElementById('scan-' + id);
    el.classList.toggle('open');
  }
</script>
</body>
</html>