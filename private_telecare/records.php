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

  function scanFileUrl(string $filePath): string {
    return '../' . ltrim($filePath, '/');
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

$history_stmt = $conn->prepare("SELECT a.appointment_date, a.status, a.reason, d.full_name AS doctor_name, d.specialty
  FROM appointments a JOIN doctors d ON d.id = a.doctor_id
  WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 5");
$history_stmt->bind_param('i', $patient_id);
$history_stmt->execute();
$history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

$page_title = 'Medical Records — TELE-CARE';
$active_nav = 'records';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.rx-page{max-width:1320px;margin:0 auto;padding:1.4rem 2rem 5rem}
.rx-header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.2rem}
.rx-title{font-family:'Playfair Display',serif;font-size:1.9rem;font-weight:900;color:#244441;line-height:1}
.rx-sub{font-size:0.85rem;color:#9ab0ae;margin-top:0.4rem}
.rx-header-tools{display:flex;align-items:center;gap:0.5rem}
.rx-icon-btn{width:38px;height:38px;border-radius:50%;border:1px solid rgba(36,68,65,0.12);background:#fff;color:#244441;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s}
.rx-icon-btn:hover{background:rgba(195,54,67,0.06);border-color:rgba(195,54,67,0.25);color:#C33643}
.rx-icon-btn svg{width:16px;height:16px}

.record-profile{display:flex;align-items:center;justify-content:space-between;gap:1rem;background:#fff;border:1px solid rgba(36,68,65,.08);border-radius:16px;padding:1.1rem 1.3rem;margin-bottom:1rem;box-shadow:0 2px 10px rgba(0,0,0,0.03)}
.record-profile-main{display:flex;align-items:center;gap:.9rem;min-width:0}
.record-avatar{width:58px;height:58px;border-radius:50%;overflow:hidden;background:#eaf1ff;color:#3F82E3;display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0}
.record-avatar img{width:100%;height:100%;object-fit:cover}
.record-name{font-weight:800;color:#244441;font-size:1.08rem;font-family:'Playfair Display',serif}
.record-meta{display:flex;flex-wrap:wrap;gap:.4rem 1.1rem;color:#6b8886;font-size:.72rem;margin-top:.35rem}
.record-meta span{display:flex;align-items:center;gap:.3rem}
.record-meta svg{width:13px;height:13px;flex-shrink:0;color:#9ab0ae}
.record-edit{display:flex;align-items:center;gap:.35rem;padding:.55rem .95rem;border:1px solid rgba(195,54,67,.25);border-radius:8px;color:#C33643;text-decoration:none;font-size:.75rem;font-weight:700;white-space:nowrap}
.record-edit:hover{background:rgba(195,54,67,.06)}
.record-edit svg{width:13px;height:13px}

.rx-search-row{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.2rem}
.rx-search-box{flex:1;min-width:200px;position:relative;display:flex;align-items:center}
.rx-search-box svg{position:absolute;left:.9rem;width:15px;height:15px;color:#9ab0ae}
.rx-search-box input{width:100%;padding:.65rem .9rem .65rem 2.3rem;border:1px solid rgba(36,68,65,.1);border-radius:10px;background:#fff;font-family:'DM Sans',sans-serif;font-size:.82rem;color:#244441;outline:none}
.rx-search-box input:focus{border-color:#3F82E3}
.rx-filter-select{padding:.65rem .9rem;border:1px solid rgba(36,68,65,.1);border-radius:10px;background:#fff;font-family:'DM Sans',sans-serif;font-size:.78rem;color:#244441;outline:none;cursor:pointer}

.rx-grid{display:grid;grid-template-columns:1fr 320px;gap:1.4rem;align-items:start}
@media(max-width:960px){.rx-grid{grid-template-columns:1fr}}

.rx-section{background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:18px;padding:1.4rem 1.5rem;margin-bottom:1.3rem;box-shadow:0 2px 10px rgba(0,0,0,0.03)}
.rx-section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem}
.rx-section-title{display:flex;align-items:center;gap:0.55rem;font-family:'Playfair Display',serif;font-weight:900;font-size:1.05rem;color:#244441}
.rx-section-icon{width:28px;height:28px;border-radius:8px;background:rgba(195,54,67,0.1);color:#C33643;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rx-section-icon svg{width:15px;height:15px}
.rx-section-link{font-family:'DM Sans',sans-serif;font-size:.75rem;font-weight:700;color:#C33643;text-decoration:none}
.rx-section-link:hover{text-decoration:underline}

.hist-list{display:flex;flex-direction:column;gap:0}
.hist-item{position:relative;padding:.9rem 0 .9rem 1.2rem;border-top:1px solid rgba(36,68,65,.07)}
.hist-item:first-child{border-top:none;padding-top:0}
.hist-dot{position:absolute;left:0;top:1.25rem;width:9px;height:9px;border-radius:50%;border:2.5px solid #C33643;background:#fff}
.hist-dot.blue{border-color:#3F82E3}
.hist-dot.green{border-color:#16a34a}
.hist-dot.gray{border-color:#9ab0ae}
.hist-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem;flex-wrap:wrap}
.hist-date{font-size:.7rem;color:#C33643;font-weight:700;letter-spacing:.02em}
.hist-name{font-size:.9rem;color:#244441;font-weight:700;margin-top:.15rem}
.hist-detail{font-size:.76rem;color:#6b8886;margin-top:.2rem}
.hist-status{font-size:.62rem;padding:.22rem .6rem;border-radius:50px;font-weight:700;white-space:nowrap}
.hist-status.completed{background:rgba(22,163,74,.1);color:#16a34a}
.hist-status.confirmed{background:rgba(63,130,227,.1);color:#2563eb}
.hist-status.archived{background:rgba(154,176,174,.15);color:#6b8886}
.hist-status.cancelled{background:rgba(195,54,67,.1);color:#C33643}
.hist-view{font-size:.72rem;font-weight:700;color:#3F82E3;text-decoration:none;margin-top:.4rem;display:inline-flex;align-items:center;gap:.25rem;cursor:pointer;background:none;border:none;padding:0;font-family:'DM Sans',sans-serif}
.hist-view:hover{text-decoration:underline}
.hist-detail-panel{display:none;margin-top:.6rem;background:rgba(63,130,227,0.04);border:1px solid rgba(63,130,227,0.1);border-radius:10px;padding:.7rem .9rem;font-size:.78rem;color:#244441}
.hist-detail-panel.open{display:block}

.med-list{display:flex;flex-direction:column;gap:.7rem}
.med-item{display:flex;align-items:flex-start;gap:.65rem;padding:.7rem;border:1px solid rgba(36,68,65,.07);border-radius:12px;background:#fbfdfd}
.med-icon{width:34px;height:34px;border-radius:9px;background:rgba(195,54,67,0.1);color:#C33643;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.med-icon svg{width:16px;height:16px}
.med-name{font-weight:700;font-size:.86rem;color:#244441}
.med-meta{font-size:.72rem;color:#9ab0ae;margin-top:.15rem}
.med-chip{display:inline-block;margin-top:.4rem;font-size:.6rem;font-weight:700;letter-spacing:.03em;padding:.18rem .5rem;border-radius:50px}
.med-chip.active{background:rgba(22,163,74,.1);color:#16a34a}
.med-chip.refill{background:rgba(195,54,67,.1);color:#C33643}

.upload-tile{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:16px;padding:1.3rem;box-shadow:0 2px 10px rgba(0,0,0,0.03);cursor:pointer;width:100%;font-family:'DM Sans',sans-serif}
.upload-tile:hover{border-color:rgba(195,54,67,.25);background:rgba(195,54,67,0.03)}
.upload-tile .ic{width:38px;height:38px;border-radius:10px;background:rgba(63,130,227,0.1);color:#3F82E3;display:flex;align-items:center;justify-content:center}
.upload-tile .ic svg{width:18px;height:18px}
.upload-tile span{font-size:.78rem;font-weight:700;color:#244441}

.scan-table{width:100%;border-collapse:collapse}
.scan-table thead th{text-align:left;font-size:.66rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#9ab0ae;padding:0 0 .6rem;border-bottom:1px solid rgba(36,68,65,.08)}
.scan-table tbody td{padding:.85rem 0;border-bottom:1px solid rgba(36,68,65,.06);font-size:.82rem;color:#244441;vertical-align:top}
.scan-table tbody tr:last-child td{border-bottom:none}
.scan-name-cell{display:flex;align-items:center;gap:.6rem}
.scan-thumb{width:38px;height:38px;border-radius:9px;object-fit:cover;border:1px solid rgba(63,130,227,0.1);flex-shrink:0;cursor:pointer}
.scan-thumb-pdf{width:38px;height:38px;border-radius:9px;background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.15);flex-shrink:0;display:flex;align-items:center;justify-content:center;cursor:pointer}
.scan-name{font-weight:700;font-size:.85rem;color:#244441}
.chip{border-radius:50px;padding:0.15rem 0.6rem;font-size:0.62rem;font-weight:700;white-space:nowrap;display:inline-block;margin-top:.2rem}
.scan-actions{display:flex;align-items:center;gap:.6rem;justify-content:flex-end}
.scan-action-link{display:flex;align-items:center;gap:.3rem;background:none;border:none;cursor:pointer;color:#3F82E3;font-size:.75rem;font-weight:700;font-family:'DM Sans',sans-serif;padding:0}
.scan-action-link.muted{color:#6b8886}
.scan-action-link svg{width:14px;height:14px}
.scan-rename-toggle{background:none;border:none;cursor:pointer;color:#9ab0ae;font-size:0.68rem;font-weight:700;text-decoration:underline;padding:0;margin-top:.25rem;font-family:'DM Sans',sans-serif;display:block}
.scan-rename-form{display:none;gap:0.45rem;margin-top:0.5rem}
.scan-rename-form.open{display:flex}
.scan-rename-form input[type="text"]{flex:1;padding:0.45rem 0.65rem;font-size:0.78rem;border-radius:9px;border:1.5px solid rgba(63,130,227,0.15);font-family:'DM Sans',sans-serif;outline:none}
.scan-rename-form input[type="text"]:focus{border-color:#3F82E3}
.scan-rename-form button{border:none;border-radius:9px;background:rgba(63,130,227,0.1);color:#3F82E3;font-weight:700;font-size:0.72rem;padding:0.45rem 0.68rem;cursor:pointer;white-space:nowrap;font-family:'DM Sans',sans-serif}
.scan-detail-row td{padding:0 !important;border-bottom:1px solid rgba(36,68,65,.06) !important}
.scan-detail-inner{display:none;padding:0 0 1rem}
.scan-detail-inner.open{display:block}

.rx-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:2.4rem 1rem;color:#b8cccb;gap:0.6rem}
.rx-empty svg{width:36px;height:36px;opacity:0.35}
.rx-empty p{font-size:0.85rem;font-weight:500;color:#9ab0ae}

.legend-chip{display:inline-flex;align-items:center;gap:.25rem;font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:50px;font-weight:700}
.legend-chip .record-inline-icon{width:12px;height:12px}
.pager{display:flex;justify-content:flex-end;gap:0.45rem;flex-wrap:wrap;margin-top:0.9rem}
.pager a{padding:0.35rem 0.7rem;border-radius:8px;font-size:0.76rem;font-weight:700;text-decoration:none}

.upl-overlay{position:fixed;inset:0;background:rgba(15,30,28,0.55);z-index:1000;align-items:center;justify-content:center;padding:1rem}
.original-overlay{position:fixed;inset:0;z-index:1100;display:none;align-items:center;justify-content:center;padding:1rem;background:rgba(15,30,28,.72);backdrop-filter:blur(4px)}
.original-overlay.open{display:flex}
.original-modal{width:min(100%,980px);height:min(92vh,760px);display:flex;flex-direction:column;overflow:hidden;background:#fff;border-radius:16px;box-shadow:0 20px 70px rgba(0,0,0,.35)}
.original-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1rem;border-bottom:1px solid rgba(36,68,65,.1);flex-shrink:0}
.original-title{min-width:0;font-size:.88rem;font-weight:800;color:#244441;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.original-tools{display:flex;align-items:center;gap:.35rem;flex-shrink:0}
.original-tool,.original-close{width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:1px solid rgba(36,68,65,.12);border-radius:8px;background:#fff;color:#244441;cursor:pointer;font:700 .8rem 'DM Sans',sans-serif}
.original-tool:hover,.original-close:hover{background:#f1f3fc}
.original-close{font-size:1.1rem;color:#C33643}
.original-stage{position:relative;display:flex;align-items:center;justify-content:center;min-height:0;flex:1;overflow:auto;background:#eef1f6;padding:1.2rem}
.original-image{max-width:none;max-height:none;display:none;transform-origin:center center;transition:transform .15s ease;box-shadow:0 4px 18px rgba(21,28,39,.18);background:#fff}
.original-frame{width:100%;height:100%;display:none;border:0;background:#fff}
.original-hint{position:absolute;bottom:.7rem;left:50%;transform:translateX(-50%);padding:.35rem .65rem;border-radius:50px;background:rgba(21,28,39,.72);color:#fff;font-size:.68rem;pointer-events:none}
.upl-modal{background:#fff;border-radius:20px;padding:1.8rem;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 60px rgba(0,0,0,0.25)}
.upl-close{position:absolute;top:1rem;right:1rem;background:rgba(36,68,65,0.06);border:none;width:32px;height:32px;border-radius:50%;font-size:1.2rem;color:#6b8886;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1}
.upl-close:hover{background:rgba(195,54,67,0.1);color:#C33643}
.upl-title{font-family:'Playfair Display',serif;font-weight:900;font-size:1.3rem;color:#244441;margin-bottom:0.3rem}
.upl-sub{font-size:0.83rem;color:#9ab0ae;margin-bottom:1.2rem}
.upl-alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.2);color:#C33643;border-radius:12px;padding:0.7rem 1rem;font-size:0.83rem;margin-bottom:1rem}
.upl-alert-success{background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);color:#3F82E3;border-radius:12px;padding:0.7rem 1rem;font-size:0.83rem;margin-bottom:1rem}
.upl-field-label{display:block;font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.4rem}
.upl-field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(63,130,227,0.15);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:#244441;background:#fff;outline:none;transition:border-color 0.2s;margin-bottom:1rem}
.upl-field-input:focus{border-color:#3F82E3;box-shadow:0 0 0 3px rgba(63,130,227,0.1)}
.upl-dropzone{border:2px dashed rgba(63,130,227,0.3);border-radius:16px;padding:2rem 1rem;text-align:center;cursor:pointer;transition:all 0.25s;background:#EBF2FD;position:relative}
.upl-dropzone:hover,.upl-dropzone.dragover{border-color:#3F82E3;background:rgba(63,130,227,0.1)}
.upl-dropzone input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.upl-dropzone svg{width:36px;height:36px;stroke:#3F82E3;margin:0 auto 0.7rem;display:block}
.upl-dropzone .main-text{font-weight:700;color:#3F82E3;font-size:0.9rem}
.upl-dropzone .sub-text{font-size:0.75rem;color:#9ab0ae;margin-top:0.3rem}
.upl-preview-wrap{margin-top:1rem;display:none}
.upl-preview-wrap img{width:100%;max-height:200px;object-fit:contain;border-radius:12px;border:1px solid rgba(63,130,227,0.1)}
.upl-preview-name{font-size:0.75rem;color:#9ab0ae;margin-top:0.5rem;text-align:center}
.upl-submit-btn{width:100%;padding:0.9rem;border-radius:50px;background:#C33643;color:#fff;font-weight:700;font-size:0.92rem;border:none;cursor:pointer;transition:all 0.3s;box-shadow:0 6px 18px rgba(195,54,67,0.3);display:flex;align-items:center;justify-content:center;gap:0.5rem;font-family:'DM Sans',sans-serif;margin-top:1rem}
.upl-submit-btn:hover{background:#a82d38;transform:translateY(-2px)}
.upl-submit-btn:disabled{background:#b0c4e8;cursor:not-allowed;transform:none;box-shadow:none}
.upl-spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:upl-spin 0.7s linear infinite}
@keyframes upl-spin{to{transform:rotate(360deg)}}
.rx-top-notice{display:flex;align-items:center;gap:.45rem;background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.2);color:#3F82E3;border-radius:12px;padding:0.75rem 1rem;font-size:0.85rem;margin-bottom:1.2rem}
.record-inline-icon{width:14px;height:14px;display:inline-block;vertical-align:-2px;flex-shrink:0}
</style>

<div class="rx-page">

  <div class="rx-header">
    <div>
      <div class="rx-title">Medical Records</div>
      <div class="rx-sub">View and manage your personal medical information and health history.</div>
    </div>
    <div class="rx-header-tools">
      <button type="button" class="rx-icon-btn" onclick="window.print()" title="Print">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18h12v-6H6v6zM6 14H4a1 1 0 01-1-1v-3a2 2 0 012-2h14a2 2 0 012 2v3a1 1 0 01-1 1h-2"/></svg>
      </button>
      <button type="button" class="rx-icon-btn" title="Download">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
      </button>
    </div>
  </div>

  <?php if ($notice && !$modal_open): ?>
  <div class="rx-top-notice"><svg class="record-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg><?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <div class="record-profile">
    <div class="record-profile-main">
      <div class="record-avatar">
        <?php if (!empty($p['profile_photo'])): ?><img src="<?= htmlspecialchars($p['profile_photo']) ?>" alt=""/><?php else: ?><?= htmlspecialchars($initials) ?><?php endif; ?>
      </div>
      <div>
        <div class="record-name"><?= htmlspecialchars($p['full_name']) ?></div>
        <div class="record-meta">
          <?php if (!empty($p['patient_id'])): ?><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h4M7 13h7"/></svg>ID: <?= htmlspecialchars($p['patient_id']) ?></span><?php endif; ?>
          <?php if (!empty($p['date_of_birth'])): ?><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>DOB: <?= htmlspecialchars($p['date_of_birth']) ?></span><?php endif; ?>
          <?php if (!empty($p['blood_type'])): ?><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3s6 6.5 6 11a6 6 0 01-12 0c0-4.5 6-11 6-11z"/></svg>Blood Type: <?= htmlspecialchars($p['blood_type']) ?></span><?php endif; ?>
          <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.68 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.32 1.85.55 2.81.68A2 2 0 0122 16.92z"/></svg>Contact: <?= htmlspecialchars($p['emergency_name'] ?? 'Not set') ?></span>
        </div>
      </div>
    </div>
    <a class="record-edit" href="router.php?page=profile">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Edit Profile
    </a>
  </div>

  <div class="rx-search-row">
    <div class="rx-search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/></svg>
      <input type="text" placeholder="Search medical records..."/>
    </div>
    <select class="rx-filter-select">
      <option>All Types</option>
      <option>Prescriptions</option>
      <option>Scanned Documents</option>
    </select>
    <select class="rx-filter-select">
      <option>Last 6 Months</option>
      <option>Last 12 Months</option>
      <option>All Time</option>
    </select>
  </div>

  <div class="rx-grid">

    <!-- ══ MAIN COLUMN ══ -->
    <div>

      <!-- ── MEDICAL HISTORY ── -->
      <div class="rx-section">
        <div class="rx-section-head">
          <div class="rx-section-title">
            <div class="rx-section-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 3"/></svg>
            </div>
            Medical History
          </div>
          <a href="router.php?page=visits" class="rx-section-link">View All</a>
        </div>

        <?php if ($history): ?>
        <div class="hist-list">
          <?php foreach ($history as $i => $visit):
            $status = strtolower($visit['status']);
            $dot_class = 'gray';
            $badge_class = 'archived';
            if ($status === 'completed') { $dot_class = 'green'; $badge_class = 'completed'; }
            elseif ($status === 'confirmed') { $dot_class = 'blue'; $badge_class = 'confirmed'; }
            elseif ($status === 'cancelled') { $dot_class = ''; $badge_class = 'cancelled'; }
          ?>
          <div class="hist-item">
            <div class="hist-dot <?= $dot_class ?>"></div>
            <div class="hist-top">
              <div>
                <div class="hist-date"><?= date('M j, Y', strtotime($visit['appointment_date'])) ?></div>
                <div class="hist-name">Dr. <?= htmlspecialchars($visit['doctor_name']) ?></div>
                <div class="hist-detail"><?= htmlspecialchars($visit['specialty'] ?: 'Consultation') ?><?= !empty($visit['reason']) ? ' · ' . htmlspecialchars($visit['reason']) : '' ?></div>
              </div>
              <span class="hist-status <?= $badge_class ?>"><?= htmlspecialchars($visit['status']) ?></span>
            </div>
            <button type="button" class="hist-view" onclick="toggleHist(<?= $i ?>)">
              View Details
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6"/></svg>
            </button>
            <div class="hist-detail-panel" id="hist-panel-<?= $i ?>">
              <?= htmlspecialchars($visit['specialty'] ?: 'General Consultation') ?><?= !empty($visit['reason']) ? ' — ' . htmlspecialchars($visit['reason']) : '' ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="rx-empty">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <p>No consultation history yet.</p>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── SCANNED DOCUMENTS ── -->
      <div class="rx-section">
        <div class="rx-section-head">
          <div class="rx-section-title">
            <div class="rx-section-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h1M4 6a2 2 0 012-2h8l6 6v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/></svg>
            </div>
            Laboratory &amp; Scanned Documents
          </div>
          <button type="button" class="rx-section-link" style="background:none;border:none;cursor:pointer;" onclick="openUploadModal()">+ Add New</button>
        </div>

        <?php if ($scanned && $scanned->num_rows > 0): ?>
        <table class="scan-table">
          <thead>
            <tr><th>Document</th><th>Date</th><th>Type</th><th style="text-align:right;">Action</th></tr>
          </thead>
          <tbody>
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
            <tr>
              <td>
                <div class="scan-name-cell">
                  <?php if ($ext === 'pdf'): ?>
                    <div onclick="toggleScanned(<?= $s['id'] ?>)" class="scan-thumb-pdf">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#C33643" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h8l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v5h5"/></svg>
                    </div>
                  <?php else: ?>
                    <img src="<?= htmlspecialchars(scanFileUrl($s['file_path'])) ?>" class="scan-thumb" onclick="toggleScanned(<?= $s['id'] ?>)"/>
                  <?php endif; ?>
                  <div>
                    <div class="scan-name"><?= htmlspecialchars($s['doc_label'] ?: 'Scanned Prescription') ?></div>
                    <button type="button" class="scan-rename-toggle" onclick="toggleRename(<?= $s['id'] ?>)">Rename</button>
                    <form method="POST" class="scan-rename-form" id="rename-<?= $s['id'] ?>" onclick="event.stopPropagation();">
                      <input type="hidden" name="action" value="rename_scan"/>
                      <input type="hidden" name="scan_id" value="<?= (int)$s['id'] ?>"/>
                      <input type="hidden" name="scan_page" value="<?= (int)$scan_page ?>"/>
                      <input type="text" name="new_label" value="<?= htmlspecialchars($s['doc_label'] ?: 'Untitled') ?>" maxlength="120"/>
                      <button type="submit">Save</button>
                    </form>
                  </div>
                </div>
              </td>
              <td><?= date('M j, Y', strtotime($s['uploaded_at'])) ?></td>
              <td><span class="chip" style="<?= $type_style ?>"><?= htmlspecialchars($type_label) ?></span></td>
              <td>
                <div class="scan-actions">
                  <button type="button" class="scan-action-link" onclick="toggleScanned(<?= $s['id'] ?>)" id="arrow-<?= $s['id'] ?>">
                    View
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                  </button>
                  <button type="button" class="scan-action-link muted" onclick="openOriginalModal(<?= htmlspecialchars(json_encode(scanFileUrl($s['file_path']))) ?>, <?= htmlspecialchars(json_encode($s['doc_label'] ?: 'Scanned Document')) ?>, <?= htmlspecialchars(json_encode($ext)) ?>)">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr class="scan-detail-row">
              <td colspan="4">
                <div class="scan-detail-inner" id="scanned-<?= $s['id'] ?>">
                  <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;margin-bottom:0.5rem;">Extracted Text</div>
                  <div style="background:rgba(63,130,227,0.04);border:1px solid rgba(63,130,227,0.1);border-radius:10px;padding:0.9rem;font-size:0.82rem;line-height:2;color:#244441;max-height:220px;overflow-y:auto;font-family:'DM Sans',sans-serif;word-break:break-word;">
                    <?= formatOcrText($s['extracted_text'] ?? '', $s['doc_type']) ?>
                  </div>
                  <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.6rem;">
                    <span class="legend-chip" style="background:rgba(63,130,227,0.12);color:#1a4fa8;"><svg class="record-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3h8v4H8z"/><path d="M6 7h12v14H6z"/><path d="M9 12h6M9 16h6"/></svg>Medicine</span>
                    <span class="legend-chip" style="background:rgba(244,132,95,0.12);color:#c05621;"><svg class="record-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m13 2-9 12h7l-1 8 9-12h-7z"/></svg>Dosage/Freq</span>
                    <span class="legend-chip" style="background:rgba(168,85,247,0.12);color:#6d28d9;"><svg class="record-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3z"/><path d="M12 9v5M12 17h.01"/></svg>Important</span>
                  </div>
                  <div style="display:flex;gap:0.6rem;margin-top:0.7rem;">
                    <button onclick="copyScanned(<?= $s['id'] ?>)" id="copy-<?= $s['id'] ?>" style="flex:1;padding:0.5rem;border-radius:50px;background:rgba(63,130,227,0.1);color:#3F82E3;border:none;font-weight:700;font-size:0.78rem;cursor:pointer;font-family:'DM Sans',sans-serif;">
                      Copy Text
                    </button>
                    <button type="button" onclick="openOriginalModal(<?= htmlspecialchars(json_encode(scanFileUrl($s['file_path']))) ?>, <?= htmlspecialchars(json_encode($s['doc_label'] ?: 'Scanned Document')) ?>, <?= htmlspecialchars(json_encode($ext)) ?>)" style="flex:1;padding:0.5rem;border-radius:50px;background:rgba(36,68,65,0.08);color:#244441;border:none;font-weight:700;font-size:0.78rem;cursor:pointer;font-family:'DM Sans',sans-serif;">
                      View Original
                    </button>
                    <button type="button" onclick="openUploadModal()" style="flex:1;padding:0.5rem;border-radius:50px;background:rgba(244,132,95,0.1);color:#f4845f;border:none;font-weight:700;font-size:0.78rem;cursor:pointer;font-family:'DM Sans',sans-serif;">
                      Scan New
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>

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
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/></svg>
          <p>No scanned documents yet.</p>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- ══ SIDEBAR ══ -->
    <div>
      <div class="rx-section" style="margin-bottom:1rem;">
        <div class="rx-section-head">
          <div class="rx-section-title">
            <div class="rx-section-icon" style="background:rgba(63,130,227,0.1);color:#3F82E3;">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            </div>
            Medications
          </div>
        </div>

        <?php if ($meds_count > 0): ?>
        <div class="med-list">
          <?php foreach ($meds as $m): $zero = (int)$m['refills_remaining'] === 0; ?>
          <div class="med-item">
            <div class="med-icon">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3h8v4H8z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12v14H6z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6"/></svg>
            </div>
            <div style="min-width:0;flex:1;">
              <div class="med-name"><?= htmlspecialchars($m['medication_name']) ?></div>
              <div class="med-meta"><?= htmlspecialchars($m['dosage'] ?? '—') ?> · <?= htmlspecialchars($m['frequency'] ?? '—') ?></div>
              <span class="med-chip <?= $zero ? 'refill' : 'active' ?>"><?= $zero ? 'REFILL NEEDED' : 'ACTIVE' ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="rx-empty" style="padding:1.4rem 0.5rem;">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
          <p>No active prescriptions.</p>
        </div>
        <?php endif; ?>
      </div>

      <button type="button" class="upload-tile" onclick="openUploadModal()">
        <div class="ic">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
        </div>
        <span>Upload Record</span>
      </button>
    </div>

  </div>
</div>

<!-- Original scanned document viewer -->
<div class="original-overlay" id="originalModal" role="dialog" aria-modal="true" aria-labelledby="originalTitle" onclick="closeOriginalModal(event)">
  <section class="original-modal">
    <header class="original-head">
      <div class="original-title" id="originalTitle">Scanned Document</div>
      <div class="original-tools">
        <button type="button" class="original-tool" id="zoomOutBtn" onclick="zoomOriginal(-.15)" aria-label="Zoom out">−</button>
        <button type="button" class="original-tool" onclick="resetOriginalZoom()" aria-label="Reset zoom">100%</button>
        <button type="button" class="original-tool" id="zoomInBtn" onclick="zoomOriginal(.15)" aria-label="Zoom in">+</button>
        <button type="button" class="original-close" onclick="closeOriginalModal()" aria-label="Close document viewer">&times;</button>
      </div>
    </header>
    <div class="original-stage" id="originalStage">
      <img class="original-image" id="originalImage" alt="Scanned document"/>
      <iframe class="original-frame" id="originalFrame" title="Scanned document PDF"></iframe>
      <div class="original-hint" id="originalHint">Use + and − to zoom</div>
    </div>
  </section>
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
      <div class="upl-alert-error"><svg class="record-inline-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3z"/><path d="M12 9v5M12 17h.01"/></svg><?= htmlspecialchars($error) ?></div>
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
          <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#C33643" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h8l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M14 3v5h5"/></svg>
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
  const open  = el.classList.contains('open');
  el.classList.toggle('open', !open);
  arrow.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
  arrow.style.transition = 'transform 0.25s';
}

function toggleHist(id) {
  document.getElementById('hist-panel-' + id).classList.toggle('open');
}

let originalZoom = 1;

function openOriginalModal(url, title, extension) {
  const modal = document.getElementById('originalModal');
  const image = document.getElementById('originalImage');
  const frame = document.getElementById('originalFrame');
  const stage = document.getElementById('originalStage');
  const isPdf = String(extension).toLowerCase() === 'pdf';

  document.getElementById('originalTitle').textContent = title;
  originalZoom = 1;
  updateOriginalZoom();
  image.style.display = isPdf ? 'none' : 'block';
  frame.style.display = isPdf ? 'block' : 'none';
  if (isPdf) {
    frame.src = url;
    image.removeAttribute('src');
  } else {
    image.src = url;
    frame.removeAttribute('src');
  }
  stage.scrollTop = 0;
  stage.scrollLeft = 0;
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeOriginalModal(event) {
  if (event && event.target !== document.getElementById('originalModal')) return;
  const modal = document.getElementById('originalModal');
  const image = document.getElementById('originalImage');
  const frame = document.getElementById('originalFrame');
  modal.classList.remove('open');
  image.removeAttribute('src');
  frame.removeAttribute('src');
  document.body.style.overflow = '';
}

function zoomOriginal(amount) {
  originalZoom = Math.min(3, Math.max(.5, originalZoom + amount));
  updateOriginalZoom();
}

function resetOriginalZoom() {
  originalZoom = 1;
  updateOriginalZoom();
}

function updateOriginalZoom() {
  document.getElementById('originalImage').style.transform = 'scale(' + originalZoom + ')';
  document.querySelector('.original-tool[aria-label="Reset zoom"]').textContent = Math.round(originalZoom * 100) + '%';
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeOriginalModal();
});

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