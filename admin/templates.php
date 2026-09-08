<?php
// admin/templates.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
$admin_id = $_SESSION['admin_id'];

if (!function_exists('log_audit')) {
    function log_audit($conn, $admin_id, $action, $entity_type, $entity_id, $old = null, $new = null) {
        $old_json = $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES);
        $new_json = $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES);
        $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, old_values, new_values) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $admin_id, $action, $entity_type, $entity_id, $old_json, $new_json);
        $stmt->execute();
    }
}
function audit_enabled($conn) {
    $r = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    return $r && $r->num_rows > 0;
}

// The three document types this page manages, mirroring the blank templates PDF.
$DOC_TYPES = [
    'prescription' => 'Prescription',
    'lab_request'  => 'Laboratory Request',
    'med_cert'     => 'Medical Certificate',
];

// NOTE: Doctor-identity fields (doctor name, credentials, license no., PTR no.)
// are intentionally NOT part of the admin template anymore. Those come from
// each doctor's own account (doctor/credentials.php) and are stamped onto the
// document automatically at send time. The admin only controls the clinic
// letterhead / layout that surrounds the doctor's clinical content.
$FIELD_DEFS = [
    'prescription' => [
        'clinic_name'    => 'Doctor / Clinic Name',
        'address'        => 'Address',
        'contact_no'     => 'Contact Number',
    ],
    'lab_request' => [
        'clinic_name'    => 'Clinic / Medical Facility Name',
        'address'        => 'Address',
        'contact_no'     => 'Contact No.',
    ],
    'med_cert' => [
        'clinic_name'    => 'Clinic / Medical Facility Name',
        'address'        => 'Address',
        'contact_no'     => 'Contact No.',
        'footer_note'    => 'Footer Note',
    ],
];

// ---------------- Save (create or update) a template ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $doc_type = $_POST['doc_type'] ?? '';
    if (!array_key_exists($doc_type, $DOC_TYPES)) {
        $_SESSION['toast_error'] = 'Unknown template type.';
        header('Location: templates.php'); exit;
    }

    $template_id = (int)($_POST['template_id'] ?? 0);

    $values = [
        'template_name'  => trim($_POST['template_name'] ?? ''),
        'clinic_name'    => trim($_POST['clinic_name'] ?? ''),
        'address'        => trim($_POST['address'] ?? ''),
        'contact_no'     => trim($_POST['contact_no'] ?? ''),
        'footer_note'    => trim($_POST['footer_note'] ?? ''),
    ];

    $errors = [];
    if ($values['template_name'] === '') $errors[] = 'Template name is required.';
    foreach ($FIELD_DEFS[$doc_type] as $key => $label) {
        if ($values[$key] === '') $errors[] = $label . ' is required.';
    }

    if (empty($errors)) {
        if ($template_id > 0) {
            $chk = $conn->query("SELECT id FROM document_templates WHERE id=" . $template_id . " AND doc_type='" . $conn->real_escape_string($doc_type) . "'");
            if (!$chk || $chk->num_rows === 0) {
                $_SESSION['toast_error'] = 'That template no longer exists.';
                $_SESSION['toast_tab'] = $doc_type;
                header('Location: templates.php'); exit;
            }
            $stmt = $conn->prepare(
                "UPDATE document_templates SET
                    template_name=?, clinic_name=?, address=?, contact_no=?, footer_note=?
                 WHERE id=?"
            );
            $stmt->bind_param(
                "sssssi",
                $values['template_name'], $values['clinic_name'], $values['address'], $values['contact_no'],
                $values['footer_note'], $template_id
            );
            $stmt->execute();
            if (audit_enabled($conn)) {
                log_audit($conn, $admin_id, 'update', 'document_templates', $template_id, null, ['template_name' => $values['template_name']]);
            }
            $_SESSION['toast'] = $DOC_TYPES[$doc_type] . ' template "' . $values['template_name'] . '" updated.';
        } else {
            $countRes = $conn->query("SELECT COUNT(*) c FROM document_templates WHERE doc_type='" . $conn->real_escape_string($doc_type) . "'");
            $isFirst = ($countRes && (int)$countRes->fetch_assoc()['c'] === 0) ? 1 : 0;

            $stmt = $conn->prepare(
                "INSERT INTO document_templates
                    (doc_type, template_name, clinic_name, address, contact_no, footer_note, is_default, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')"
            );
            $stmt->bind_param(
              "ssssssi",
                $doc_type, $values['template_name'], $values['clinic_name'], $values['address'], $values['contact_no'],
                $values['footer_note'], $isFirst
            );
            $stmt->execute();
            $new_id = $conn->insert_id;
            if (audit_enabled($conn)) {
                log_audit($conn, $admin_id, 'create', 'document_templates', $new_id, null, ['template_name' => $values['template_name']]);
            }
            $_SESSION['toast'] = $DOC_TYPES[$doc_type] . ' template "' . $values['template_name'] . '" saved.';
        }
        $_SESSION['toast_tab'] = $doc_type;
    } else {
        $_SESSION['toast_error'] = implode(' ', $errors);
        $_SESSION['toast_tab'] = $doc_type;
        $_SESSION['form_repopulate'] = array_merge($values, ['doc_type' => $doc_type, 'template_id' => $template_id]);
    }
    header('Location: templates.php'); exit;
}

// ---------------- Archive / restore / set default ----------------
if (isset($_GET['archive_template'])) {
    $id = (int)$_GET['archive_template'];
    $row = $conn->query("SELECT * FROM document_templates WHERE id=$id")->fetch_assoc();
    if ($row) {
        $conn->query("UPDATE document_templates SET status='Archived', is_default=0 WHERE id=$id");
        if (audit_enabled($conn)) {
            log_audit($conn, $admin_id, 'archive', 'document_templates', $id, ['status' => 'Active'], ['status' => 'Archived']);
        }
        if ((int)$row['is_default'] === 1) {
            $doc_type_esc = $conn->real_escape_string($row['doc_type']);
            $next = $conn->query("SELECT id FROM document_templates WHERE doc_type='$doc_type_esc' AND status='Active' ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();
            if ($next) { $conn->query("UPDATE document_templates SET is_default=1 WHERE id=" . (int)$next['id']); }
        }
        $_SESSION['toast'] = 'Template archived.';
        $_SESSION['toast_tab'] = $row['doc_type'];
    }
    header('Location: templates.php'); exit;
}
if (isset($_GET['restore_template'])) {
    $id = (int)$_GET['restore_template'];
    $row = $conn->query("SELECT * FROM document_templates WHERE id=$id")->fetch_assoc();
    if ($row) {
        $conn->query("UPDATE document_templates SET status='Active' WHERE id=$id");
        if (audit_enabled($conn)) {
            log_audit($conn, $admin_id, 'restore', 'document_templates', $id, ['status' => 'Archived'], ['status' => 'Active']);
        }
        $_SESSION['toast'] = 'Template restored.';
        $_SESSION['toast_tab'] = $row['doc_type'];
    }
    header('Location: templates.php'); exit;
}
if (isset($_GET['set_default_template'])) {
    $id = (int)$_GET['set_default_template'];
    $row = $conn->query("SELECT * FROM document_templates WHERE id=$id")->fetch_assoc();
    if ($row && $row['status'] === 'Active') {
        $doc_type_esc = $conn->real_escape_string($row['doc_type']);
        $conn->query("UPDATE document_templates SET is_default=0 WHERE doc_type='$doc_type_esc'");
        $conn->query("UPDATE document_templates SET is_default=1 WHERE id=$id");
        if (audit_enabled($conn)) {
            log_audit($conn, $admin_id, 'set_default', 'document_templates', $id, null, ['doc_type' => $row['doc_type']]);
        }
        $_SESSION['toast'] = 'Default template updated.';
        $_SESSION['toast_tab'] = $row['doc_type'];
    }
    header('Location: templates.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
$toast_tab = $_SESSION['toast_tab'] ?? 'prescription';
$form_repopulate = $_SESSION['form_repopulate'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['toast_tab'], $_SESSION['form_repopulate']);

// All saved templates, handed to the client for the Saved Templates modal + edit-loading.
$allTemplates = [];
$res = $conn->query("SELECT * FROM document_templates ORDER BY doc_type, is_default DESC, template_name ASC");
if ($res) { while ($r = $res->fetch_assoc()) { $allTemplates[] = $r; } }

// The main form always starts blank (each save creates a new template, unless editing
// an existing one via the Saved Templates modal) — except right after a failed
// validation attempt, where we restore exactly what the admin typed.
$blank = [
    'template_id' => 0, 'template_name' => '', 'clinic_name' => '', 'address' => '',
    'contact_no' => '', 'footer_note' => '',
];
$form = ['prescription' => $blank, 'lab_request' => $blank, 'med_cert' => $blank];

// Sensible default boilerplate for a brand-new medical certificate template.
$form['med_cert']['footer_note'] = 'This document can be used for non-medico-legal purposes only.';

if ($form_repopulate && array_key_exists($form_repopulate['doc_type'] ?? '', $DOC_TYPES)) {
    $dt = $form_repopulate['doc_type'];
    $form[$dt] = array_merge($blank, [
        'template_id'    => (int)$form_repopulate['template_id'],
        'template_name'  => $form_repopulate['template_name'] ?? '',
        'clinic_name'    => $form_repopulate['clinic_name'] ?? '',
        'address'        => $form_repopulate['address'] ?? '',
        'contact_no'     => $form_repopulate['contact_no'] ?? '',
        'footer_note'    => $form_repopulate['footer_note'] ?? '',
    ]);
}

$activeNav = 'pos-templates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Templates — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    .main{flex:1;overflow-y:auto;margin-left:230px}
    .topbar{background:var(--white);padding:1rem 2rem;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .page-content{padding:2rem}

    .doc-tabs{display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.5rem}
    .doc-tab{padding:0.6rem 1.2rem;border-radius:50px;font-size:0.83rem;font-weight:700;border:1.5px solid rgba(36,68,65,0.12);background:var(--white);color:var(--green);cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s}
    .doc-tab:hover{border-color:var(--blue)}
    .doc-tab.active{background:var(--green);border-color:var(--green);color:#fff}

    .template-panel{display:none}
    .template-panel.active{display:grid;grid-template-columns:1fr 460px;gap:2rem;align-items:start}
    @media(max-width:1100px){.template-panel.active{grid-template-columns:1fr 380px}}
    @media(max-width:960px){.template-panel.active{grid-template-columns:1fr}}

    .card{background:var(--white);border-radius:16px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.8rem}
    .card-header-row{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:0.3rem}
    .card-header-row .btn-sm{white-space:nowrap}
    .card h3{font-size:1.05rem;margin-bottom:0.3rem}
    .card .card-sub{font-size:0.8rem;color:#9ab0ae;margin-bottom:1.3rem}
    .field-label{display:block;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none}
    .field-input:focus{border-color:var(--blue)}
    .form-field{margin-bottom:1rem}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    @media(max-width:520px){.form-row{grid-template-columns:1fr}}
    .btn-submit{padding:0.85rem 1.6rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.93rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif}
    .btn-submit:hover{background:#a82d38}

    .editing-banner{display:none;align-items:center;justify-content:space-between;gap:0.6rem;background:rgba(63,130,227,0.08);border:1px solid rgba(63,130,227,0.25);color:var(--blue);padding:0.65rem 1rem;border-radius:12px;font-size:0.8rem;font-weight:600;margin-bottom:1.1rem}
    .editing-banner button{background:none;border:none;color:var(--blue);font-weight:700;cursor:pointer;text-decoration:underline;font-size:0.78rem;font-family:'DM Sans',sans-serif;padding:0}
    .form-error{display:none;background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.25);color:var(--red);padding:0.65rem 1rem;border-radius:10px;font-size:0.78rem;margin-bottom:1.1rem;line-height:1.4}

    .doctor-info-note{display:flex;gap:0.6rem;align-items:flex-start;background:rgba(36,68,65,0.05);border:1px solid rgba(36,68,65,0.1);color:var(--green);padding:0.7rem 0.9rem;border-radius:12px;font-size:0.78rem;line-height:1.45;margin-bottom:1.2rem}
    .doctor-info-note svg{width:16px;height:16px;flex-shrink:0;margin-top:0.1rem;color:var(--blue)}

    .preview-wrap{position:sticky;top:96px}
    .preview-label{font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.6rem;display:flex;justify-content:space-between;align-items:center}
    .doc-preview{background:#fff;border:1px dashed rgba(36,68,65,0.25);border-radius:12px;padding:2.2rem 2rem;font-size:0.88rem;color:#222;font-family:'DM Sans',sans-serif;min-height:560px;box-shadow:0 4px 20px rgba(0,0,0,0.05)}
    .doc-preview .ph-head{text-align:center;margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1.5px solid #222}
    .doc-preview .ph-clinic{font-weight:700;font-size:1.15rem;font-family:'Playfair Display',serif}
    .doc-preview .ph-line{color:#555;font-size:0.85rem;margin-top:0.15rem}
    .doc-preview .ph-title{text-align:center;font-weight:700;letter-spacing:0.05em;margin:1.1rem 0;font-size:0.95rem}
    .doc-preview .ph-meta{display:flex;justify-content:space-between;font-size:0.84rem;color:#555;margin-bottom:0.8rem;flex-wrap:wrap;gap:0.3rem}
    .doc-preview .ph-body{border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;padding:1.6rem 0;min-height:150px;color:#9ab0ae;font-size:0.84rem;line-height:1.6}
    .doc-preview .ph-footnote{font-size:0.78rem;color:#888;margin-top:0.9rem;font-style:italic}
    .doc-preview .ph-sign{margin-top:2rem;text-align:left}
    .doc-preview .ph-sign .name{font-weight:700;font-size:0.95rem;color:#b9c2c1}
    .doc-preview .ph-sign .lic{font-size:0.84rem;color:#b9c2c1;margin-top:0.15rem}
    .doc-preview .ph-sign .doctor-tag{font-size:0.72rem;color:#9ab0ae;font-style:italic;margin-top:0.3rem}

    .template-row{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:0.9rem 1rem;border:1px solid rgba(36,68,65,0.1);border-radius:12px;flex-wrap:wrap}
    .template-row-name{font-weight:700;font-size:0.9rem;display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap}
    .template-row-meta{font-size:0.75rem;color:#9ab0ae;margin-top:0.25rem}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    .toast.error{background:var(--red)}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast error">✕ <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>

<?php include 'sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
    <div style="font-size:0.95rem;font-weight:700;">Templates</div>
  </div>

  <div class="page-content">
    <div class="doc-tabs">
      <button type="button" class="doc-tab" data-tab="prescription" onclick="showTab('prescription')">Prescription</button>
      <button type="button" class="doc-tab" data-tab="lab_request" onclick="showTab('lab_request')">Laboratory Request</button>
      <button type="button" class="doc-tab" data-tab="med_cert" onclick="showTab('med_cert')">Medical Certificate</button>
    </div>

    <!-- ================= PRESCRIPTION ================= -->
    <div class="template-panel" id="panel-prescription">
      <div class="card">
        <div class="card-header-row">
          <div>
            <h3>Prescription Letterhead</h3>
            <div class="card-sub">These clinic details are pre-filled at the top of every prescription slip. Doctor info, patient info, medicine and dosage are entered per-prescription elsewhere.</div>
          </div>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="btn-sm" style="background:rgba(36,68,65,0.08);color:var(--green);" onclick="cancelEdit('prescription')">+ New Template</button>
            <button type="button" class="btn-sm btn-edit" onclick="openTemplatesModal('prescription')">Saved Templates</button>
          </div>
        </div>
        <div class="doctor-info-note">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span>Doctor name, credentials, license no. and PTR no. are no longer set here — they're pulled automatically from each doctor's own verified account when they send a prescription.</span>
        </div>
        <div class="editing-banner" id="prescription-editing-banner">
          <span>Editing "<span class="editing-name"></span>"</span>
          <button type="button" onclick="cancelEdit('prescription')">Cancel</button>
        </div>
        <div class="form-error" id="prescription-form-error"></div>
        <form method="POST" onsubmit="return validateForm('prescription', event)">
          <input type="hidden" name="doc_type" value="prescription"/>
          <input type="hidden" name="template_id" value="<?= (int)$form['prescription']['template_id'] ?>"/>
          <div class="form-field"><label class="field-label">Template Name *</label><input type="text" name="template_name" class="field-input" placeholder="e.g. Main Clinic" data-required="1" data-label="Template Name" value="<?= htmlspecialchars($form['prescription']['template_name']) ?>"/></div>
          <div class="form-field"><label class="field-label">Doctor / Clinic Name *</label><input type="text" name="clinic_name" class="field-input" data-required="1" data-label="Doctor / Clinic Name" value="<?= htmlspecialchars($form['prescription']['clinic_name']) ?>" oninput="updatePreview('prescription')"/></div>
          <div class="form-field"><label class="field-label">Address *</label><input type="text" name="address" class="field-input" data-required="1" data-label="Address" value="<?= htmlspecialchars($form['prescription']['address']) ?>" oninput="updatePreview('prescription')"/></div>
          <div class="form-field"><label class="field-label">Contact Number *</label><input type="text" name="contact_no" class="field-input" data-required="1" data-label="Contact Number" value="<?= htmlspecialchars($form['prescription']['contact_no']) ?>" oninput="updatePreview('prescription')"/></div>
          <input type="hidden" name="footer_note" value=""/>
          <button type="submit" name="save_template" class="btn-submit" id="prescription-submit-btn">Save Prescription Template</button>
        </form>
      </div>
      <div class="preview-wrap">
        <div class="preview-label">Live Preview</div>
        <div class="doc-preview">
          <div class="ph-head">
            <div class="ph-clinic" id="prescription-clinic">[DOCTOR / CLINIC NAME]</div>
            <div class="ph-line" id="prescription-address">[ADDRESS]</div>
            <div class="ph-line" id="prescription-contact">[CONTACT NUMBER]</div>
          </div>
          <div class="ph-meta"><span>Patient's Name: ____________ Age: ___ Sex: ___</span></div>
          <div class="ph-meta"><span>Address: ____________</span><span>Date: ______</span></div>
          <div class="ph-body">Rx<br/>[MEDICATION NAME / STRENGTH / FORM]<br/>[INSTRUCTIONS / DOSAGE]<br/>Dispense: [QUANTITY / VOLUME]<br/>Label: [DIRECTIONS FOR USE]</div>
          <div class="ph-sign">
            <div class="name">[Doctor name, credentials — from doctor account]</div>
            <div class="lic">License No.: [from doctor account]</div>
            <div class="lic">PTR No.: [from doctor account]</div>
            <div class="doctor-tag">Auto-filled when the doctor sends this document</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= LAB REQUEST ================= -->
    <div class="template-panel" id="panel-lab_request">
      <div class="card">
        <div class="card-header-row">
          <div>
            <h3>Laboratory Request Letterhead</h3>
            <div class="card-sub">Header details for lab request slips. Doctor info, test selections and patient details are entered per-request elsewhere.</div>
          </div>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="btn-sm" style="background:rgba(36,68,65,0.08);color:var(--green);" onclick="cancelEdit('lab_request')">+ New Template</button>
            <button type="button" class="btn-sm btn-edit" onclick="openTemplatesModal('lab_request')">Saved Templates</button>
          </div>
        </div>
        <div class="doctor-info-note">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span>The requesting physician's name and license no. are pulled automatically from the sending doctor's account.</span>
        </div>
        <div class="editing-banner" id="lab_request-editing-banner">
          <span>Editing "<span class="editing-name"></span>"</span>
          <button type="button" onclick="cancelEdit('lab_request')">Cancel</button>
        </div>
        <div class="form-error" id="lab_request-form-error"></div>
        <form method="POST" onsubmit="return validateForm('lab_request', event)">
          <input type="hidden" name="doc_type" value="lab_request"/>
          <input type="hidden" name="template_id" value="<?= (int)$form['lab_request']['template_id'] ?>"/>
          <div class="form-field"><label class="field-label">Template Name *</label><input type="text" name="template_name" class="field-input" placeholder="e.g. Main Facility" data-required="1" data-label="Template Name" value="<?= htmlspecialchars($form['lab_request']['template_name']) ?>"/></div>
          <div class="form-field"><label class="field-label">Clinic / Medical Facility Name *</label><input type="text" name="clinic_name" class="field-input" data-required="1" data-label="Clinic / Medical Facility Name" value="<?= htmlspecialchars($form['lab_request']['clinic_name']) ?>" oninput="updatePreview('lab_request')"/></div>
          <div class="form-field"><label class="field-label">Address *</label><input type="text" name="address" class="field-input" data-required="1" data-label="Address" value="<?= htmlspecialchars($form['lab_request']['address']) ?>" oninput="updatePreview('lab_request')"/></div>
          <div class="form-field"><label class="field-label">Contact No. *</label><input type="text" name="contact_no" class="field-input" data-required="1" data-label="Contact No." value="<?= htmlspecialchars($form['lab_request']['contact_no']) ?>" oninput="updatePreview('lab_request')"/></div>
          <input type="hidden" name="footer_note" value=""/>
          <button type="submit" name="save_template" class="btn-submit" id="lab_request-submit-btn">Save Lab Request Template</button>
        </form>
      </div>
      <div class="preview-wrap">
        <div class="preview-label">Live Preview</div>
        <div class="doc-preview">
          <div class="ph-head">
            <div class="ph-clinic" id="lab_request-clinic">[CLINIC / MEDICAL FACILITY NAME]</div>
            <div class="ph-line" id="lab_request-address">[ADDRESS]</div>
            <div class="ph-line" id="lab_request-contact">Contact No.: [CONTACT NUMBER]</div>
          </div>
          <div class="ph-title">LABORATORY REQUEST</div>
          <div class="ph-meta"><span>Name: ____________</span><span>Age/Sex: ____</span></div>
          <div class="ph-meta"><span>Address: ____________</span><span>Date: ______</span></div>
          <div class="ph-body">Blood Chemistry (FBS, BUN, Crea, SGPT, SGOT, Lipid Profile, Uric Acid, Na, K)<br/>Other Tests (CBC, Platelet, Urinalysis, Fecalysis, Dengue NS1/IgM/IgG, HbA1c, FT3, FT4, TSH, Chest X-ray, 12-L ECG)</div>
          <div class="ph-sign">
            <div class="name">[Requesting physician — from doctor account]</div>
            <div class="lic">License No.: [from doctor account]</div>
            <div class="doctor-tag">Auto-filled when the doctor sends this document</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= MEDICAL CERTIFICATE ================= -->
    <div class="template-panel" id="panel-med_cert">
      <div class="card">
        <div class="card-header-row">
          <div>
            <h3>Medical Certificate Letterhead</h3>
            <div class="card-sub">Header and boilerplate note for medical certificates. Doctor info, patient details, assessment and recommendations are entered per-certificate elsewhere.</div>
          </div>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="btn-sm" style="background:rgba(36,68,65,0.08);color:var(--green);" onclick="cancelEdit('med_cert')">+ New Template</button>
            <button type="button" class="btn-sm btn-edit" onclick="openTemplatesModal('med_cert')">Saved Templates</button>
          </div>
        </div>
        <div class="doctor-info-note">
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span>The physician's name and license no. are pulled automatically from the signing doctor's account.</span>
        </div>
        <div class="editing-banner" id="med_cert-editing-banner">
          <span>Editing "<span class="editing-name"></span>"</span>
          <button type="button" onclick="cancelEdit('med_cert')">Cancel</button>
        </div>
        <div class="form-error" id="med_cert-form-error"></div>
        <form method="POST" onsubmit="return validateForm('med_cert', event)">
          <input type="hidden" name="doc_type" value="med_cert"/>
          <input type="hidden" name="template_id" value="<?= (int)$form['med_cert']['template_id'] ?>"/>
          <div class="form-field"><label class="field-label">Template Name *</label><input type="text" name="template_name" class="field-input" placeholder="e.g. Standard Certificate" data-required="1" data-label="Template Name" value="<?= htmlspecialchars($form['med_cert']['template_name']) ?>"/></div>
          <div class="form-field"><label class="field-label">Clinic / Medical Facility Name *</label><input type="text" name="clinic_name" class="field-input" data-required="1" data-label="Clinic / Medical Facility Name" value="<?= htmlspecialchars($form['med_cert']['clinic_name']) ?>" oninput="updatePreview('med_cert')"/></div>
          <div class="form-field"><label class="field-label">Address *</label><input type="text" name="address" class="field-input" data-required="1" data-label="Address" value="<?= htmlspecialchars($form['med_cert']['address']) ?>" oninput="updatePreview('med_cert')"/></div>
          <div class="form-field"><label class="field-label">Contact No. *</label><input type="text" name="contact_no" class="field-input" data-required="1" data-label="Contact No." value="<?= htmlspecialchars($form['med_cert']['contact_no']) ?>" oninput="updatePreview('med_cert')"/></div>
          <div class="form-field"><label class="field-label">Footer Note *</label><input type="text" name="footer_note" class="field-input" data-required="1" data-label="Footer Note" value="<?= htmlspecialchars($form['med_cert']['footer_note']) ?>" oninput="updatePreview('med_cert')"/></div>
          <button type="submit" name="save_template" class="btn-submit" id="med_cert-submit-btn">Save Medical Certificate Template</button>
        </form>
      </div>
      <div class="preview-wrap">
        <div class="preview-label">Live Preview</div>
        <div class="doc-preview">
          <div class="ph-head">
            <div class="ph-clinic" id="med_cert-clinic">[CLINIC / MEDICAL FACILITY NAME]</div>
            <div class="ph-line" id="med_cert-address">[ADDRESS]</div>
            <div class="ph-line" id="med_cert-contact">Contact No.: [CONTACT NUMBER]</div>
          </div>
          <div class="ph-title">MEDICAL CERTIFICATE</div>
          <div class="ph-body">To whom it may concern:<br/><br/>This is to certify that [PATIENT NAME], presently residing at [PATIENT ADDRESS], is [AGE] years old, [SEX], and was [CONSULTED/EXAMINED/TREATED] on [DATE/PERIOD] and advised to rest for [NUMBER OF DAYS/PERIOD].<br/><br/>Assessment/Impression: [ASSESSMENT/IMPRESSION]<br/>Recommendations/Remarks: [RECOMMENDATIONS/REMARKS]</div>
          <div class="ph-footnote" id="med_cert-footer">This document can be used for non-medico-legal purposes only.</div>
          <div class="ph-sign">
            <div class="name">[Physician name — from doctor account]</div>
            <div class="lic">License No.: [from doctor account]</div>
            <div class="doctor-tag">Auto-filled when the doctor sends this document</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-saved-templates">
  <div class="modal modal-wide">
    <h3 id="templatesModalTitle">Saved Templates</h3>
    <div class="sub" id="templatesModalSub"></div>
    <div id="templatesModalBody" style="display:flex;flex-direction:column;gap:0.7rem;max-height:52vh;overflow-y:auto;margin-bottom:1rem;"></div>
    <button type="button" class="btn-cancel" onclick="closeModal('modal-saved-templates')">Close</button>
  </div>
</div>

<div id="confirmModal" class="confirm-overlay" onclick="if(event.target===this)confirmResolve(false)">
  <div class="confirm-box">
    <h3 id="confirmTitle">Confirm Action</h3>
    <p id="confirmMessage"></p>
    <div class="confirm-actions">
      <button class="btn-confirm-no" onclick="confirmResolve(false)">Cancel</button>
      <button class="btn-confirm-yes" onclick="confirmResolve(true)">Yes, Proceed</button>
    </div>
  </div>
</div>

<script>
const DOC_TYPES = <?= json_encode($DOC_TYPES, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let ALL_TEMPLATES = <?= json_encode(array_values($allTemplates), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function showTab(tab) {
  document.querySelectorAll('.doc-tab').forEach(el => el.classList.toggle('active', el.dataset.tab === tab));
  document.querySelectorAll('.template-panel').forEach(el => el.classList.toggle('active', el.id === 'panel-' + tab));
}

function updatePreview(tab) {
  const panel = document.getElementById('panel-' + tab);
  const val = (name) => { const el = panel.querySelector(`[name="${name}"]`); return el ? el.value : ''; };

  const clinic = document.getElementById(tab + '-clinic');
  if (clinic) clinic.textContent = val('clinic_name') || placeholderFor(tab, 'clinic_name');

  const address = document.getElementById(tab + '-address');
  if (address) address.textContent = val('address') || placeholderFor(tab, 'address');

  const contact = document.getElementById(tab + '-contact');
  if (contact) {
    if (tab === 'prescription') contact.textContent = val('contact_no') || '[CONTACT NUMBER]';
    else contact.textContent = 'Contact No.: ' + (val('contact_no') || '[CONTACT NUMBER]');
  }

  if (tab === 'med_cert') {
    const footer = document.getElementById('med_cert-footer');
    if (footer) footer.textContent = val('footer_note') || 'This document can be used for non-medico-legal purposes only.';
  }
}

function placeholderFor(tab, field) {
  const map = {
    prescription: { clinic_name: '[DOCTOR / CLINIC NAME]', address: '[ADDRESS]' },
    lab_request:  { clinic_name: '[CLINIC / MEDICAL FACILITY NAME]', address: '[ADDRESS]' },
    med_cert:     { clinic_name: '[CLINIC / MEDICAL FACILITY NAME]', address: '[ADDRESS]' },
  };
  return map[tab][field];
}

// ---------------- Client-side "all fields required" validation ----------------
function validateForm(tab, e) {
  const panel = document.getElementById('panel-' + tab);
  const missing = [];
  panel.querySelectorAll('[data-required="1"]').forEach(el => {
    if (!el.value.trim()) missing.push(el.dataset.label || el.name);
  });
  const errBox = document.getElementById(tab + '-form-error');
  if (missing.length) {
    e.preventDefault();
    if (errBox) { errBox.textContent = 'Please fill in: ' + missing.join(', ') + '.'; errBox.style.display = 'block'; }
    errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return false;
  }
  if (errBox) errBox.style.display = 'none';
  return true;
}

// ---------------- Saved Templates modal ----------------
function openTemplatesModal(docType) {
  document.getElementById('templatesModalTitle').textContent = DOC_TYPES[docType] + ' — Saved Templates';
  document.getElementById('templatesModalSub').textContent = 'View, edit, set as default, or archive saved ' + DOC_TYPES[docType].toLowerCase() + ' templates.';
  renderTemplatesList(docType);
  openModal('modal-saved-templates');
}

function renderTemplatesList(docType) {
  const list = ALL_TEMPLATES.filter(t => t.doc_type === docType);
  const body = document.getElementById('templatesModalBody');
  if (list.length === 0) { body.innerHTML = '<div class="empty-row">No saved templates yet.</div>'; return; }

  body.innerHTML = list.map(t => {
    let badges = (t.is_default == 1) ? ' <span class="badge badge-blue">Default</span>' : '';
    badges += (t.status === 'Archived') ? ' <span class="badge badge-gray">Archived</span>' : ' <span class="badge badge-green">Active</span>';

    let actions = `<button type="button" class="btn-sm btn-edit" onclick="editTemplate(${t.id})">Edit</button>`;
    if (t.status === 'Active' && t.is_default != 1) {
      actions += `<a href="?set_default_template=${t.id}" class="btn-sm" style="background:rgba(63,130,227,0.1);color:var(--blue);" onclick="return confirmNav(event,this,'Set &quot;${escAttr(t.template_name)}&quot; as the default ${escAttr(DOC_TYPES[docType])} template?')">Set Default</a>`;
    }
    actions += (t.status === 'Active')
      ? `<a href="?archive_template=${t.id}" class="btn-sm btn-red" onclick="return confirmNav(event,this,'Archive &quot;${escAttr(t.template_name)}&quot;?')">Archive</a>`
      : `<a href="?restore_template=${t.id}" class="btn-sm btn-activate" onclick="return confirmNav(event,this,'Restore &quot;${escAttr(t.template_name)}&quot;?')">Restore</a>`;

    return `<div class="template-row">
      <div>
        <div class="template-row-name">${escHtml(t.template_name)}${badges}</div>
        <div class="template-row-meta">Updated ${escHtml(t.updated_at)}</div>
      </div>
      <div class="actions-cell">${actions}</div>
    </div>`;
  }).join('');
}

function confirmNav(e, el, msg) {
  e.preventDefault();
  showConfirm(msg).then(ok => { if (ok) window.location = el.href; });
  return false;
}

function editTemplate(id) {
  const t = ALL_TEMPLATES.find(x => x.id == id);
  if (!t) return;
  const tab = t.doc_type;
  const panel = document.getElementById('panel-' + tab);
  const setVal = (name, v) => { const el = panel.querySelector(`[name="${name}"]`); if (el) el.value = v || ''; };

  setVal('template_id', t.id);
  setVal('template_name', t.template_name);
  setVal('clinic_name', t.clinic_name);
  setVal('address', t.address);
  setVal('contact_no', t.contact_no);
  setVal('footer_note', t.footer_note);

  updatePreview(tab);
  setEditingBanner(tab, t.template_name);
  closeModal('modal-saved-templates');
  showTab(tab);
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function setEditingBanner(tab, name) {
  const banner = document.getElementById(tab + '-editing-banner');
  const btn = document.getElementById(tab + '-submit-btn');
  if (banner) { banner.style.display = 'flex'; banner.querySelector('.editing-name').textContent = name; }
  if (btn) btn.textContent = 'Update ' + DOC_TYPES[tab] + ' Template';
}

function cancelEdit(tab) {
  const panel = document.getElementById('panel-' + tab);
  const setVal = (name, v) => { const el = panel.querySelector(`[name="${name}"]`); if (el) el.value = v || ''; };

  setVal('template_id', 0);
  panel.querySelectorAll('input[type="text"]').forEach(el => { setVal(el.name, ''); });

  if (tab === 'med_cert') setVal('footer_note', 'This document can be used for non-medico-legal purposes only.');

  updatePreview(tab);
  const banner = document.getElementById(tab + '-editing-banner');
  if (banner) banner.style.display = 'none';
  const errBox = document.getElementById(tab + '-form-error');
  if (errBox) errBox.style.display = 'none';
  const btn = document.getElementById(tab + '-submit-btn');
  if (btn) btn.textContent = 'Save ' + DOC_TYPES[tab] + ' Template';
}

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function escHtml(str) { if (!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escAttr(str) { return escHtml(str).replace(/'/g, "\\'"); }

let _confirmResolve = null;
function showConfirm(message) {
  return new Promise(function(resolve) {
    _confirmResolve = resolve;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmModal').classList.add('open');
  });
}
function confirmResolve(value) {
  document.getElementById('confirmModal').classList.remove('open');
  if (_confirmResolve) { _confirmResolve(value); _confirmResolve = null; }
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' && document.getElementById('confirmModal').classList.contains('open')) {
    confirmResolve(false);
  }
});

// Initial tab: open the one just saved/acted on (if any), else default to prescription.
showTab('<?= htmlspecialchars($toast_tab) ?>');
['prescription','lab_request','med_cert'].forEach(updatePreview);

setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);
</script>
</body>
</html>