<?php
// private_telecare/meds.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../ocr/ocr_api.php';

function formatOcrText(string $text, string $type): string {
    if (!$text) return '<span style="color:#9ab0ae;font-style:italic;">No text extracted.</span>';
    $text = htmlspecialchars($text);
    $medicines = ['amoxicillin','metformin','losartan','paracetamol','ibuprofen','aspirin',
                  'amlodipine','atorvastatin','omeprazole','cetirizine','azithromycin',
                  'ciprofloxacin','mefenamic','salbutamol','montelukast','prednisone',
                  'furosemide','lisinopril','hydrochlorothiazide','clopidogrel','insulin'];
    foreach ($medicines as $med) {
        $text = preg_replace('/\b('.preg_quote($med,'/').')\b/i',
            '<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }
    $text = preg_replace('/\b(\d+\.?\d*\s*(?:mg|ml|mcg|units?|g\b|iu))\b/i',
        '<mark style="background:rgba(244,132,95,0.15);color:#c05621;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    $freqs = ['once daily','twice daily','three times daily','every 4 hours','every 6 hours',
              'every 8 hours','every 12 hours','morning','bedtime','with meals','after meals',
              'before meals','od','bid','tid','qid','prn','sig:','dispense:','refills?:\s*\d+'];
    foreach ($freqs as $f) {
        $text = preg_replace('/\b('.$f.')\b/i',
            '<mark style="background:rgba(244,132,95,0.15);color:#c05621;border-radius:4px;padding:0 3px;font-weight:600;">$1</mark>', $text);
    }
    $warns = ['warning','caution','allergy','allergic','do not','avoid','emergency','urgent','refill'];
    foreach ($warns as $w) {
        $text = preg_replace('/\b('.preg_quote($w,'/').')\b/i',
            '<mark style="background:rgba(168,85,247,0.12);color:#6d28d9;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }
    return nl2br($text);
}

$notice = '';
$error  = '';
$modal_open = false;

// ── Handle rename of a scanned document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename_scan') {
    $scan_id   = (int)($_POST['scan_id'] ?? 0);
    $new_label = trim($_POST['new_label'] ?? '');
    $new_label = $new_label !== '' ? $new_label : 'Untitled';

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

// ── Handle upload + OCR scan (formerly ocr/scan.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc_file'])) {
    $modal_open = true; // stay open unless we succeed below
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
        $upload_dir = __DIR__ . '/../uploads/ocr/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fname    = uniqid('ocr_') . '.' . $ext;
        $filepath = $upload_dir . $fname;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $abs_path   = realpath($filepath);
            $ocr_result = ocr_space_scan($abs_path);

            if ($ocr_result['success']) {
                $text        = $ocr_result['text'];
                $doc_type    = $ocr_result['type'];
                $label       = $_POST['doc_label'] ?? 'Untitled';
                $filepath_db = 'uploads/ocr/' . $fname;

                $stmt = $conn->prepare("
                    INSERT INTO lab_results
                        (patient_id, file_path, doc_type, doc_label, extracted_text, uploaded_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("issss", $patient_id, $filepath_db, $doc_type, $label, $text);
                $stmt->execute();

                $notice     = 'Document scanned and saved.';
                $modal_open = false; // close on success
            } else {
                $error = 'OCR failed: ' . ($ocr_result['error'] ?? 'Unknown error');
            }
        } else {
            $error = 'Could not save uploaded file.';
        }
    }
}

$meds_res = $conn->query("
    SELECT p.*, d.full_name AS doctor_name
    FROM prescriptions p JOIN doctors d ON d.id = p.doctor_id
    WHERE p.patient_id=$patient_id AND p.status='Active'
    ORDER BY p.prescribed_date DESC
");
$meds = [];
if ($meds_res) { while ($row = $meds_res->fetch_assoc()) $meds[] = $row; }
$meds_count       = count($meds);
$refill_needed_ct = count(array_filter($meds, fn($m) => (int)$m['refills_remaining'] === 0));

// Scanned prescriptions from OCR
$scan_per_page = 5;
$scan_page = max(1, (int)($_GET['scan_page'] ?? 1));

$scan_filter = "
    patient_id=$patient_id
";

$scan_total_res = $conn->query("SELECT COUNT(*) AS total FROM lab_results WHERE $scan_filter");
$scan_total_row = $scan_total_res ? $scan_total_res->fetch_assoc() : ['total' => 0];
$scan_total = (int)($scan_total_row['total'] ?? 0);
$scan_total_pages = max(1, (int)ceil($scan_total / $scan_per_page));
$scan_page = min($scan_page, $scan_total_pages);
$scan_offset = ($scan_page - 1) * $scan_per_page;

$scanned = $conn->query("
    SELECT * FROM lab_results
    WHERE $scan_filter
    ORDER BY uploaded_at DESC
    LIMIT $scan_per_page OFFSET $scan_offset
");

$page_title = 'My Prescriptions — TELE-CARE';
$active_nav = 'meds';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.rx-page{max-width:1180px;margin:0 auto;padding:1.8rem 2rem 5rem}
.rx-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.6rem}
.rx-title{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:900;color:#244441;line-height:1}
.rx-sub{font-size:0.85rem;color:#9ab0ae;margin-top:0.4rem}
.rx-scan-btn{
  display:inline-flex;align-items:center;gap:0.45rem;background:#C33643;color:#fff;
  padding:0.7rem 1.4rem;border-radius:50px;font-size:0.84rem;font-weight:700;
  text-decoration:none;box-shadow:0 4px 16px rgba(195,54,67,0.28);
  transition:all 0.2s;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;
}
.rx-scan-btn:hover{background:#a82d38;transform:translateY(-1px)}

.rx-grid{display:grid;grid-template-columns:1fr 320px;gap:1.4rem;align-items:start}
@media(max-width:960px){.rx-grid{grid-template-columns:1fr}}

/* ── Section wrapper (matches Medical History / Lab card look) ── */
.rx-section{background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:18px;padding:1.4rem 1.5rem;margin-bottom:1.3rem;box-shadow:0 2px 10px rgba(0,0,0,0.03)}
.rx-section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem}
.rx-section-title{display:flex;align-items:center;gap:0.55rem;font-family:'Playfair Display',serif;font-weight:900;font-size:1.05rem;color:#244441}
.rx-section-icon{width:28px;height:28px;border-radius:8px;background:rgba(195,54,67,0.1);color:#C33643;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rx-section-icon svg{width:15px;height:15px}

/* ── Timeline for active prescriptions ── */
.rx-timeline{position:relative;padding-left:1.6rem}
.rx-timeline::before{content:'';position:absolute;left:5px;top:6px;bottom:6px;width:2px;background:rgba(36,68,65,0.1)}
.rx-titem{position:relative;margin-bottom:1.1rem}
.rx-titem:last-child{margin-bottom:0}
.rx-tdot{position:absolute;left:-1.6rem;top:6px;width:12px;height:12px;border-radius:50%;background:#fff;border:2.5px solid #3F82E3}
.rx-titem.zero-refill .rx-tdot{border-color:#C33643}
.rx-tcard{background:#fbfdfd;border:1px solid rgba(36,68,65,0.07);border-radius:14px;padding:1rem 1.15rem}
.rx-tcard-top{display:flex;align-items:flex-start;justify-content:space-between;gap:0.8rem;flex-wrap:wrap}
.rx-tname{font-weight:700;font-size:1rem;color:#244441}
.rx-tdate{font-size:0.72rem;color:#9ab0ae;font-weight:600;white-space:nowrap}
.rx-tmeta{font-size:0.82rem;color:#6b8886;margin:0.35rem 0 0.6rem}
.rx-badges{display:flex;gap:0.5rem;flex-wrap:wrap}
.badge{font-size:0.66rem;font-weight:700;letter-spacing:0.03em;padding:0.22rem 0.65rem;border-radius:50px;white-space:nowrap}
.badge-blue{background:rgba(63,130,227,0.1);color:#2563eb}
.badge-orange{background:rgba(245,158,11,0.12);color:#ca8a04}
.badge-red{background:rgba(195,54,67,0.1);color:#C33643}
.rx-notes{margin-top:0.7rem;font-size:0.8rem;color:#6b8886;background:rgba(36,68,65,0.04);border-radius:10px;padding:0.6rem 0.8rem}
.rx-prescriber{margin-top:0.6rem;font-size:0.73rem;color:#9ab0ae}

/* ── Scanned document rows ── */
.scan-row{display:flex;align-items:flex-start;gap:1rem;border:1px solid rgba(244,132,95,0.15);border-radius:14px;padding:1rem 1.1rem;margin-bottom:0.8rem;background:#fff}
.scan-thumb{width:52px;height:52px;border-radius:12px;object-fit:cover;border:1px solid rgba(63,130,227,0.1);flex-shrink:0;cursor:pointer}
.scan-thumb-pdf{width:52px;height:52px;border-radius:12px;background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.15);flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;gap:1px}
.scan-name-row{display:flex;align-items:center;gap:0.5rem;margin-bottom:0.3rem;flex-wrap:wrap}
.scan-name{font-weight:700;font-size:0.95rem;color:#244441}
.chip{border-radius:50px;padding:0.15rem 0.6rem;font-size:0.66rem;font-weight:700}
.scan-date{font-size:0.75rem;color:#9ab0ae}
.scan-toggle{background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0;flex-shrink:0}
.scan-rename-toggle{background:none;border:none;cursor:pointer;color:#9ab0ae;font-size:0.68rem;font-weight:700;text-decoration:underline;padding:0;margin-left:0.4rem;font-family:'DM Sans',sans-serif;}
.scan-rename-form{display:none;gap:0.45rem;margin-top:0.5rem;}
.scan-rename-form.open{display:flex;}
.scan-rename-form input[type="text"]{flex:1;padding:0.45rem 0.65rem;font-size:0.78rem;border-radius:9px;border:1.5px solid rgba(63,130,227,0.15);font-family:'DM Sans',sans-serif;outline:none;}
.scan-rename-form input[type="text"]:focus{border-color:#3F82E3;}
.scan-rename-form button{border:none;border-radius:9px;background:rgba(63,130,227,0.1);color:#3F82E3;font-weight:700;font-size:0.72rem;padding:0.45rem 0.68rem;cursor:pointer;white-space:nowrap;font-family:'DM Sans',sans-serif;}

/* ── Empty states ── */
.rx-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:2.4rem 1rem;color:#b8cccb;gap:0.6rem}
.rx-empty svg{width:36px;height:36px;opacity:0.35}
.rx-empty p{font-size:0.85rem;font-weight:500;color:#9ab0ae}

/* ── Sidebar ── */
.rx-side-card{background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:18px;padding:1.3rem 1.4rem;margin-bottom:1.1rem;box-shadow:0 2px 10px rgba(0,0,0,0.03)}
.rx-side-title{display:flex;align-items:center;gap:0.5rem;font-family:'Playfair Display',serif;font-weight:900;font-size:0.98rem;color:#244441;margin-bottom:0.9rem}
.rx-side-title svg{width:16px;height:16px;color:#C33643}
.rx-stat-row{display:flex;justify-content:space-between;align-items:center;padding:0.55rem 0;border-bottom:1px solid rgba(36,68,65,0.06);font-size:0.85rem}
.rx-stat-row:last-child{border-bottom:none}
.rx-stat-label{color:#6b8886}
.rx-stat-value{font-weight:800;color:#244441;font-family:'Playfair Display',serif;font-size:1.05rem}
.rx-stat-value.warn{color:#C33643}

.rx-actions-grid{display:grid;grid-template-columns:1fr;gap:0.6rem}
.rx-action-btn{
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.4rem;
  padding:0.9rem 0.5rem;border-radius:14px;border:1px solid rgba(36,68,65,0.1);
  background:#fbfdfd;text-decoration:none;transition:all 0.2s;text-align:center;
  cursor:pointer;font-family:'DM Sans',sans-serif;width:100%;
}
.rx-action-btn:hover{background:rgba(195,54,67,0.04);border-color:rgba(195,54,67,0.2)}
.rx-action-btn .ic{width:34px;height:34px;border-radius:10px;background:rgba(195,54,67,0.1);color:#C33643;display:flex;align-items:center;justify-content:center}
.rx-action-btn span{font-size:0.72rem;font-weight:700;color:#244441}

/* ── Legend chips inside expanded scan card ── */
.legend-chip{font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:50px;font-weight:700}
.pager{display:flex;justify-content:flex-end;gap:0.45rem;flex-wrap:wrap;margin-top:0.6rem}
.pager a{padding:0.35rem 0.7rem;border-radius:8px;font-size:0.76rem;font-weight:700;text-decoration:none}

/* ── Upload modal (formerly ocr/scan.php) ── */
.upl-overlay{
  position:fixed;inset:0;background:rgba(15,30,28,0.55);z-index:1000;
  align-items:center;justify-content:center;padding:1rem;
}
.upl-modal{
  background:#fff;border-radius:20px;padding:1.8rem;max-width:520px;width:100%;
  max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.25);
}
.upl-close{
  position:absolute;top:1rem;right:1rem;background:rgba(36,68,65,0.06);border:none;
  width:32px;height:32px;border-radius:50%;font-size:1.2rem;color:#6b8886;cursor:pointer;
  display:flex;align-items:center;justify-content:center;line-height:1;
}
.upl-close:hover{background:rgba(195,54,67,0.1);color:#C33643}
.upl-title{font-family:'Playfair Display',serif;font-weight:900;font-size:1.3rem;color:#244441;margin-bottom:0.3rem}
.upl-sub{font-size:0.83rem;color:#9ab0ae;margin-bottom:1.2rem}
.upl-alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.2);color:#C33643;border-radius:12px;padding:0.7rem 1rem;font-size:0.83rem;margin-bottom:1rem}
.upl-alert-success{background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);color:#3F82E3;border-radius:12px;padding:0.7rem 1rem;font-size:0.83rem;margin-bottom:1rem}
.upl-field-label{display:block;font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.4rem}
.upl-field-input{
  width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(63,130,227,0.15);
  border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:#244441;
  background:#fff;outline:none;transition:border-color 0.2s;margin-bottom:1rem;
}
.upl-field-input:focus{border-color:#3F82E3;box-shadow:0 0 0 3px rgba(63,130,227,0.1)}
.upl-dropzone{
  border:2px dashed rgba(63,130,227,0.3);border-radius:16px;padding:2rem 1rem;text-align:center;
  cursor:pointer;transition:all 0.25s;background:#EBF2FD;position:relative;
}
.upl-dropzone:hover,.upl-dropzone.dragover{border-color:#3F82E3;background:rgba(63,130,227,0.1)}
.upl-dropzone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upl-dropzone svg{width:36px;height:36px;stroke:#3F82E3;margin:0 auto 0.7rem;display:block}
.upl-dropzone .main-text{font-weight:700;color:#3F82E3;font-size:0.9rem}
.upl-dropzone .sub-text{font-size:0.75rem;color:#9ab0ae;margin-top:0.3rem}
.upl-preview-wrap{margin-top:1rem;display:none}
.upl-preview-wrap img{width:100%;max-height:200px;object-fit:contain;border-radius:12px;border:1px solid rgba(63,130,227,0.1)}
.upl-preview-name{font-size:0.75rem;color:#9ab0ae;margin-top:0.5rem;text-align:center}
.upl-submit-btn{
  width:100%;padding:0.9rem;border-radius:50px;background:#C33643;color:#fff;
  font-weight:700;font-size:0.92rem;border:none;cursor:pointer;transition:all 0.3s;
  box-shadow:0 6px 18px rgba(195,54,67,0.3);display:flex;align-items:center;justify-content:center;
  gap:0.5rem;font-family:'DM Sans',sans-serif;margin-top:1rem;
}
.upl-submit-btn:hover{background:#a82d38;transform:translateY(-2px)}
.upl-submit-btn:disabled{background:#b0c4e8;cursor:not-allowed;transform:none;box-shadow:none}
.upl-spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:upl-spin 0.7s linear infinite}
@keyframes upl-spin{to{transform:rotate(360deg)}}
.rx-top-notice{background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);color:#3F82E3;border-radius:12px;padding:0.75rem 1rem;font-size:0.85rem;margin-bottom:1.2rem}
</style>

<div class="rx-page">

  <?php if ($notice && !$modal_open): ?>
  <div class="rx-top-notice">✅ <?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <div class="rx-header">
    <div>
      <div class="rx-title">My Prescriptions</div>
      <div class="rx-sub">Active medications from your doctors, plus anything you've scanned yourself.</div>
    </div>
    <button type="button" class="rx-scan-btn" onclick="openUploadModal()">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0"/>
      </svg>
      Scan Prescription
    </button>
  </div>

  <div class="rx-grid">

    <!-- ══ MAIN COLUMN ══ -->
    <div>

      <!-- ── DOCTOR-ISSUED PRESCRIPTIONS (timeline) ── -->
      <div class="rx-section">
        <div class="rx-section-head">
          <div class="rx-section-title">
            <div class="rx-section-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            </div>
            Active Prescriptions
          </div>
        </div>

        <?php if ($meds_count > 0): ?>
        <div class="rx-timeline">
          <?php foreach ($meds as $m): $zero = (int)$m['refills_remaining'] === 0; ?>
          <div class="rx-titem <?= $zero ? 'zero-refill' : '' ?>">
            <div class="rx-tdot"></div>
            <div class="rx-tcard">
              <div class="rx-tcard-top">
                <div class="rx-tname"><?= htmlspecialchars($m['medication_name']) ?></div>
                <div class="rx-tdate"><?= date('M d, Y', strtotime($m['prescribed_date'])) ?></div>
              </div>
              <div class="rx-tmeta"><?= htmlspecialchars($m['dosage'] ?? '—') ?> &nbsp;·&nbsp; <?= htmlspecialchars($m['frequency'] ?? '—') ?></div>
              <div class="rx-badges">
                <span class="badge badge-blue">Refills: <?= (int)$m['refills_remaining'] ?></span>
                <?php if (!empty($m['expiry_date'])): ?>
                <span class="badge badge-orange">Expires: <?= date('M d, Y', strtotime($m['expiry_date'])) ?></span>
                <?php endif; ?>
              </div>
              <?php if (!empty($m['notes'])): ?>
              <div class="rx-notes">📝 <?= htmlspecialchars($m['notes']) ?></div>
              <?php endif; ?>
              <div class="rx-prescriber">Prescribed by Dr. <?= htmlspecialchars($m['doctor_name']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="rx-empty">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
          </svg>
          <p>No active prescriptions.</p>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── SCANNED DOCUMENTS ── -->
      <div class="rx-section">
        <div class="rx-section-head">
          <div class="rx-section-title">
            <div class="rx-section-icon" style="background:red;color:white;">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
            </div>
            Scanned Documents
          </div>
        </div>

        <?php if ($scanned && $scanned->num_rows > 0): ?>
          <?php while ($s = $scanned->fetch_assoc()):
            $doc_type = strtolower(trim((string)($s['doc_type'] ?? 'unknown')));
            $type_label = 'Document';
            $type_style = 'background:rgba(154,176,174,0.1);color:#7f9a97;';
            if ($doc_type === 'prescription') {
              $type_label = 'Prescription';
              $type_style = 'background:rgba(244,132,95,0.12);color:#f4845f;';
            } elseif ($doc_type === 'lab_result') {
              $type_label = 'Lab Result';
              $type_style = 'background:rgba(63,130,227,0.12);color:#3F82E3;';
            }
            $ext = strtolower(pathinfo($s['file_path'], PATHINFO_EXTENSION));
          ?>
          <div class="scan-row">
            <?php if ($ext === 'pdf'): ?>
              <div onclick="toggleScanned(<?= $s['id'] ?>)" class="scan-thumb-pdf">
                <span style="font-size:1.4rem;line-height:1;">📄</span>
                <span style="font-size:0.55rem;font-weight:700;color:#C33643;letter-spacing:0.04em;">PDF</span>
              </div>
            <?php else: ?>
              <img src="<?= htmlspecialchars($s['file_path']) ?>" class="scan-thumb" onclick="toggleScanned(<?= $s['id'] ?>)"/>
            <?php endif; ?>
            <div style="flex:1;">
              <div class="scan-name-row">
                <div class="scan-name"><?= htmlspecialchars($s['doc_label'] ?: 'Scanned Prescription') ?></div>
                <span class="chip" style="background:rgba(244,132,95,0.1);color:#f4845f;">Scanned</span>
                <span class="chip" style="<?= $type_style ?>"><?= htmlspecialchars($type_label) ?></span>
                <button type="button" class="scan-rename-toggle" onclick="toggleRename(<?= $s['id'] ?>)">Rename</button>
              </div>
              <div class="scan-date">📅 <?= date('M d, Y — g:i A', strtotime($s['uploaded_at'])) ?></div>

              <form method="POST" class="scan-rename-form" id="rename-<?= $s['id'] ?>" onclick="event.stopPropagation();">
                <input type="hidden" name="action" value="rename_scan"/>
                <input type="hidden" name="scan_id" value="<?= (int)$s['id'] ?>"/>
                <input type="hidden" name="scan_page" value="<?= (int)$scan_page ?>"/>
                <input type="text" name="new_label" value="<?= htmlspecialchars($s['doc_label'] ?: 'Untitled') ?>" maxlength="120"/>
                <button type="submit">Save</button>
              </form>

              <div id="scanned-<?= $s['id'] ?>" style="display:none;margin-top:0.8rem;">
                <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;margin-bottom:0.5rem;">Extracted Text</div>
                <div style="background:rgba(63,130,227,0.04);border:1px solid rgba(63,130,227,0.1);border-radius:10px;padding:0.9rem;font-size:0.82rem;line-height:2;color:#244441;max-height:220px;overflow-y:auto;font-family:'DM Sans',sans-serif;word-break:break-word;">
                  <?= formatOcrText($s['extracted_text'] ?? '', $s['doc_type']) ?>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.6rem;">
                  <span class="legend-chip" style="background:rgba(63,130,227,0.12);color:#1a4fa8;">💊 Medicine</span>
                  <span class="legend-chip" style="background:rgba(244,132,95,0.12);color:#c05621;">⚡ Dosage/Freq</span>
                  <span class="legend-chip" style="background:rgba(168,85,247,0.12);color:#6d28d9;">⚠️ Important</span>
                </div>
                <div style="display:flex;gap:0.6rem;margin-top:0.7rem;">
                  <button onclick="copyScanned(<?= $s['id'] ?>)" id="copy-<?= $s['id'] ?>" style="flex:1;padding:0.5rem;border-radius:50px;background:rgba(63,130,227,0.1);color:#3F82E3;border:none;font-weight:700;font-size:0.78rem;cursor:pointer;font-family:'DM Sans',sans-serif;">
                    Copy Text
                  </button>
                  <button type="button" onclick="openUploadModal()" style="flex:1;padding:0.5rem;border-radius:50px;background:rgba(244,132,95,0.1);color:#f4845f;border:none;font-weight:700;font-size:0.78rem;cursor:pointer;font-family:'DM Sans',sans-serif;">
                    Scan New
                  </button>
                </div>
              </div>
            </div>
            <button onclick="toggleScanned(<?= $s['id'] ?>)" class="scan-toggle" id="arrow-<?= $s['id'] ?>">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
          </div>
          <?php endwhile; ?>

          <?php if ($scan_total_pages > 1): ?>
          <div class="pager">
            <?php if ($scan_page > 1): ?>
              <a href="?scan_page=<?= $scan_page - 1 ?>" style="background:rgba(63,130,227,0.08);color:#3F82E3;">Prev</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $scan_total_pages; $i++): ?>
              <a href="?scan_page=<?= $i ?>" style="<?= $i === $scan_page ? 'background:#3F82E3;color:#fff;' : 'background:rgba(63,130,227,0.08);color:#3F82E3;' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($scan_page < $scan_total_pages): ?>
              <a href="?scan_page=<?= $scan_page + 1 ?>" style="background:rgba(63,130,227,0.08);color:#3F82E3;">Next</a>
            <?php endif; ?>
          </div>
          <?php endif; ?>

        <?php else: ?>
        <div class="rx-empty">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
          </svg>
          <p>No scanned documents yet.</p>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- ══ SIDEBAR ══ -->
    <div>
      <div class="rx-side-card">
        <div class="rx-side-title">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
          Prescription Summary
        </div>
        <div class="rx-stat-row">
          <span class="rx-stat-label">Active Medications</span>
          <span class="rx-stat-value"><?= $meds_count ?></span>
        </div>
        <div class="rx-stat-row">
          <span class="rx-stat-label">Needs Refill</span>
          <span class="rx-stat-value <?= $refill_needed_ct > 0 ? 'warn' : '' ?>"><?= $refill_needed_ct ?></span>
        </div>
        <div class="rx-stat-row">
          <span class="rx-stat-label">Scanned Documents</span>
          <span class="rx-stat-value"><?= $scan_total ?></span>
        </div>
      </div>

      <div class="rx-side-card">
        <div class="rx-side-title">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Quick Actions
        </div>
        <div class="rx-actions-grid">
          <button type="button" class="rx-action-btn" onclick="openUploadModal()">
            <div class="ic">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.9A5.5 5.5 0 0117 10.5a3.5 3.5 0 010 7H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6M9.5 15.5L12 18l2.5-2.5"/></svg>
            </div>
            <span>Upload Scan</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ══ UPLOAD MODAL (formerly ocr/scan.php) ══ -->
<div class="upl-overlay" id="uploadModalOverlay" style="<?= $modal_open ? 'display:flex;' : 'display:none;' ?>">
  <div class="upl-modal">
    <button type="button" class="upl-close" onclick="closeUploadModal()">&times;</button>
    <div class="upl-title">Scan a Document</div>
    <div class="upl-sub">Upload a photo of your lab result or prescription — we'll extract the text automatically.</div>

    <?php if ($notice && $modal_open === false && isset($_POST['doc_file'])): ?>
      <!-- unreachable branch guard, kept intentionally empty -->
    <?php endif; ?>
    <?php if ($error && isset($_FILES['doc_file'])): ?>
      <div class="upl-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="scanForm">
      <label class="upl-field-label">Document Label</label>
      <input type="text" name="doc_label" class="upl-field-input" placeholder="e.g. CBC Result March 2026, Dr. Santos Prescription"/>

      <label class="upl-field-label">Upload Image</label>
      <div class="upl-dropzone" id="dropZone">
        <input type="file" name="doc_file" id="fileInput" accept="image/*,.pdf" required onchange="previewFile(this)"/>
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
        <div class="main-text">Click to upload or drag & drop</div>
        <div class="sub-text">JPG, PNG, BMP, TIFF, or PDF — max 10MB</div>
      </div>

      <div class="upl-preview-wrap" id="previewWrap">
        <img id="previewImg" src="" alt="Preview" style="display:none;"/>
        <div id="pdfPreview" style="display:none;align-items:center;gap:1rem;background:rgba(195,54,67,0.06);border:1px solid rgba(195,54,67,0.15);border-radius:12px;padding:1rem 1.2rem;">
          <div style="font-size:2.4rem;line-height:1;">📄</div>
          <div>
            <div style="font-weight:700;color:#C33643;font-size:0.88rem;">PDF File Ready</div>
          </div>
        </div>
        <div id="previewName" class="upl-preview-name"></div>
      </div>

      <button type="submit" class="upl-submit-btn" id="scanBtn">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Scan Document
      </button>
    </form>
  </div>
</div>

<script>
function toggleScanned(id) {
  const el    = document.getElementById('scanned-' + id);
  const arrow = document.getElementById('arrow-' + id).querySelector('svg');
  const open  = el.style.display === 'block';
  el.style.display = open ? 'none' : 'block';
  arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
  arrow.style.transition = 'transform 0.25s';
}

function toggleRename(id) {
  document.getElementById('rename-' + id).classList.toggle('open');
}

function copyScanned(id) {
  const text = document.getElementById('scanned-' + id).querySelector('div').textContent.trim();
  navigator.clipboard.writeText(text);
  const btn = document.getElementById('copy-' + id);
  btn.textContent = '✓ Copied!';
  setTimeout(() => btn.textContent = 'Copy Text', 2000);
}

function openUploadModal() {
  document.getElementById('uploadModalOverlay').style.display = 'flex';
}
function closeUploadModal() {
  document.getElementById('uploadModalOverlay').style.display = 'none';
}

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
  btn.innerHTML = '<div class="upl-spinner"></div> Scanning...';
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
</script>

<?php require_once __DIR__ . '/../includes/nav.php'; ?>
</body>
</html>