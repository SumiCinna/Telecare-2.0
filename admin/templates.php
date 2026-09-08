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

// The three document types this page manages, mirroring the blank templates PDF.
$DOC_TYPES = [
    'prescription' => 'Prescription',
    'lab_request'  => 'Laboratory Request',
    'med_cert'     => 'Medical Certificate',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $doc_type = $_POST['doc_type'] ?? '';
    if (!array_key_exists($doc_type, $DOC_TYPES)) {
        $_SESSION['toast_error'] = 'Unknown template type.';
        header('Location: templates.php'); exit;
    }

    $clinic_name    = trim($_POST['clinic_name'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $contact_no     = trim($_POST['contact_no'] ?? '');
    $physician_name = trim($_POST['physician_name'] ?? '');
    $credentials    = trim($_POST['credentials'] ?? '');
    $license_no     = trim($_POST['license_no'] ?? '');
    $ptr_no         = trim($_POST['ptr_no'] ?? '');
    $footer_note    = trim($_POST['footer_note'] ?? '');

    if (!$clinic_name) {
        $_SESSION['toast_error'] = 'Clinic / facility name is required.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO document_templates
                (doc_type, clinic_name, address, contact_no, physician_name, credentials, license_no, ptr_no, footer_note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                clinic_name=VALUES(clinic_name), address=VALUES(address), contact_no=VALUES(contact_no),
                physician_name=VALUES(physician_name), credentials=VALUES(credentials),
                license_no=VALUES(license_no), ptr_no=VALUES(ptr_no), footer_note=VALUES(footer_note)"
        );
        $stmt->bind_param(
            "sssssssss",
            $doc_type, $clinic_name, $address, $contact_no, $physician_name, $credentials, $license_no, $ptr_no, $footer_note
        );
        $stmt->execute();
        if ($conn->query("SHOW TABLES LIKE 'audit_logs'")->num_rows > 0) {
            log_audit($conn, $admin_id, 'update', 'document_templates', $doc_type, null, ['clinic_name' => $clinic_name]);
        }
        $_SESSION['toast'] = $DOC_TYPES[$doc_type] . ' template saved.';
        $_SESSION['toast_tab'] = $doc_type;
    }
    header('Location: templates.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
$toast_tab = $_SESSION['toast_tab'] ?? 'prescription';
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['toast_tab']);

// Pull existing rows keyed by doc_type; fall back to blank defaults.
$rows = [];
$res = $conn->query("SELECT * FROM document_templates");
if ($res) {
    while ($r = $res->fetch_assoc()) { $rows[$r['doc_type']] = $r; }
}
$defaults = [
    'clinic_name' => '', 'address' => '', 'contact_no' => '',
    'physician_name' => '', 'credentials' => '', 'license_no' => '',
    'ptr_no' => '', 'footer_note' => '',
];
$defaults_prescription = $defaults;
$defaults_labrequest   = $defaults;
$defaults_medcert      = array_merge($defaults, [
    'footer_note' => 'This document can be used for non-medico-legal purposes only.',
]);

$data = [
    'prescription' => array_merge($defaults_prescription, $rows['prescription'] ?? []),
    'lab_request'  => array_merge($defaults_labrequest, $rows['lab_request'] ?? []),
    'med_cert'     => array_merge($defaults_medcert, $rows['med_cert'] ?? []),
];

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
    .template-panel.active{display:grid;grid-template-columns:1fr 360px;gap:2rem;align-items:start}
    @media(max-width:960px){.template-panel.active{grid-template-columns:1fr}}

    .card{background:var(--white);border-radius:16px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.8rem}
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

    .preview-label{font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.6rem}
    .doc-preview{background:#fff;border:1px dashed rgba(36,68,65,0.25);border-radius:12px;padding:1.5rem;font-size:0.82rem;color:#222;font-family:'DM Sans',sans-serif}
    .doc-preview .ph-head{text-align:center;margin-bottom:0.9rem;padding-bottom:0.8rem;border-bottom:1.5px solid #222}
    .doc-preview .ph-clinic{font-weight:700;font-size:0.95rem;font-family:'Playfair Display',serif}
    .doc-preview .ph-line{color:#555;font-size:0.78rem}
    .doc-preview .ph-title{text-align:center;font-weight:700;letter-spacing:0.05em;margin:0.9rem 0;font-size:0.88rem}
    .doc-preview .ph-meta{display:flex;justify-content:space-between;font-size:0.78rem;color:#555;margin-bottom:0.7rem;flex-wrap:wrap;gap:0.3rem}
    .doc-preview .ph-body{border-top:1px dashed #ccc;border-bottom:1px dashed #ccc;padding:1.2rem 0;min-height:80px;color:#9ab0ae;font-size:0.78rem}
    .doc-preview .ph-footnote{font-size:0.72rem;color:#888;margin-top:0.8rem;font-style:italic}
    .doc-preview .ph-sign{margin-top:1.6rem;text-align:left}
    .doc-preview .ph-sign .name{font-weight:700}
    .doc-preview .ph-sign .lic{font-size:0.78rem;color:#555}

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
        <h3>Prescription Letterhead</h3>
        <div class="card-sub">These details are pre-filled at the top and bottom of every prescription slip. Patient, medicine and dosage info is entered per-prescription elsewhere.</div>
        <form method="POST">
          <input type="hidden" name="doc_type" value="prescription"/>
          <div class="form-field"><label class="field-label">Doctor / Clinic Name *</label><input type="text" name="clinic_name" class="field-input" value="<?= htmlspecialchars($data['prescription']['clinic_name']) ?>" oninput="updatePreview('prescription')" required/></div>
          <div class="form-field"><label class="field-label">Address</label><input type="text" name="address" class="field-input" value="<?= htmlspecialchars($data['prescription']['address']) ?>" oninput="updatePreview('prescription')"/></div>
          <div class="form-field"><label class="field-label">Contact Number</label><input type="text" name="contact_no" class="field-input" value="<?= htmlspecialchars($data['prescription']['contact_no']) ?>" oninput="updatePreview('prescription')"/></div>
          <div class="form-row">
            <div class="form-field"><label class="field-label">Doctor Name</label><input type="text" name="physician_name" class="field-input" value="<?= htmlspecialchars($data['prescription']['physician_name']) ?>" oninput="updatePreview('prescription')"/></div>
            <div class="form-field"><label class="field-label">Credentials</label><input type="text" name="credentials" class="field-input" placeholder="e.g. M.D." value="<?= htmlspecialchars($data['prescription']['credentials']) ?>" oninput="updatePreview('prescription')"/></div>
          </div>
          <div class="form-row">
            <div class="form-field"><label class="field-label">License No.</label><input type="text" name="license_no" class="field-input" value="<?= htmlspecialchars($data['prescription']['license_no']) ?>" oninput="updatePreview('prescription')"/></div>
            <div class="form-field"><label class="field-label">PTR No.</label><input type="text" name="ptr_no" class="field-input" value="<?= htmlspecialchars($data['prescription']['ptr_no']) ?>" oninput="updatePreview('prescription')"/></div>
          </div>
          <button type="submit" name="save_template" class="btn-submit">Save Prescription Template</button>
        </form>
      </div>
      <div>
        <div class="preview-label">Live Preview</div>
        <div class="doc-preview">
          <div class="ph-head">
            <div class="ph-clinic" id="rx-clinic">[DOCTOR / CLINIC NAME]</div>
            <div class="ph-line" id="rx-address">[ADDRESS]</div>
            <div class="ph-line" id="rx-contact">[CONTACT NUMBER]</div>
          </div>
          <div class="ph-meta"><span>Patient's Name: ____________ Age: ___ Sex: ___</span></div>
          <div class="ph-meta"><span>Address: ____________</span><span>Date: ______</span></div>
          <div class="ph-body">Rx<br/>[MEDICATION NAME / STRENGTH / FORM]<br/>[INSTRUCTIONS / DOSAGE]<br/>Dispense: [QUANTITY / VOLUME]<br/>Label: [DIRECTIONS FOR USE]</div>
          <div class="ph-sign">
            <div class="name" id="rx-doctor-line">[DOCTOR NAME], [CREDENTIALS]</div>
            <div class="lic">License No.: <span id="rx-license">[LICENSE NUMBER]</span></div>
            <div class="lic">PTR No.: <span id="rx-ptr">[PTR NUMBER]</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= LAB REQUEST ================= -->
    <div class="template-panel" id="panel-lab_request">
      <div class="card">
        <h3>Laboratory Request Letterhead</h3>
        <div class="card-sub">Header and requesting-physician details for lab request slips. Test selections and patient details are entered per-request elsewhere.</div>
        <form method="POST">
          <input type="hidden" name="doc_type" value="lab_request"/>
          <div class="form-field"><label class="field-label">Clinic / Medical Facility Name *</label><input type="text" name="clinic_name" class="field-input" value="<?= htmlspecialchars($data['lab_request']['clinic_name']) ?>" oninput="updatePreview('lab_request')" required/></div>
          <div class="form-field"><label class="field-label">Address</label><input type="text" name="address" class="field-input" value="<?= htmlspecialchars($data['lab_request']['address']) ?>" oninput="updatePreview('lab_request')"/></div>
          <div class="form-field"><label class="field-label">Contact No.</label><input type="text" name="contact_no" class="field-input" value="<?= htmlspecialchars($data['lab_request']['contact_no']) ?>" oninput="updatePreview('lab_request')"/></div>
          <div class="form-field"><label class="field-label">Requesting Physician Name</label><input type="text" name="physician_name" class="field-input" value="<?= htmlspecialchars($data['lab_request']['physician_name']) ?>" oninput="updatePreview('lab_request')"/></div>
          <input type="hidden" name="credentials" value=""/>
          <div class="form-field"><label class="field-label">License No.</label><input type="text" name="license_no" class="field-input" value="<?= htmlspecialchars($data['lab_request']['license_no']) ?>" oninput="updatePreview('lab_request')"/></div>
          <input type="hidden" name="ptr_no" value=""/>
          <input type="hidden" name="footer_note" value=""/>
          <button type="submit" name="save_template" class="btn-submit">Save Lab Request Template</button>
        </form>
      </div>
      <div>
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
            <div class="name" id="lab_request-doctor-line">[REQUESTING PHYSICIAN NAME]</div>
            <div class="lic">License No.: <span id="lab_request-license">[LICENSE NUMBER]</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================= MEDICAL CERTIFICATE ================= -->
    <div class="template-panel" id="panel-med_cert">
      <div class="card">
        <h3>Medical Certificate Letterhead</h3>
        <div class="card-sub">Header, signature block and boilerplate note for medical certificates. Patient details, assessment and recommendations are entered per-certificate elsewhere.</div>
        <form method="POST">
          <input type="hidden" name="doc_type" value="med_cert"/>
          <div class="form-field"><label class="field-label">Clinic / Medical Facility Name *</label><input type="text" name="clinic_name" class="field-input" value="<?= htmlspecialchars($data['med_cert']['clinic_name']) ?>" oninput="updatePreview('med_cert')" required/></div>
          <div class="form-field"><label class="field-label">Address</label><input type="text" name="address" class="field-input" value="<?= htmlspecialchars($data['med_cert']['address']) ?>" oninput="updatePreview('med_cert')"/></div>
          <div class="form-field"><label class="field-label">Contact No.</label><input type="text" name="contact_no" class="field-input" value="<?= htmlspecialchars($data['med_cert']['contact_no']) ?>" oninput="updatePreview('med_cert')"/></div>
          <div class="form-field"><label class="field-label">Physician Name</label><input type="text" name="physician_name" class="field-input" value="<?= htmlspecialchars($data['med_cert']['physician_name']) ?>" oninput="updatePreview('med_cert')"/></div>
          <input type="hidden" name="credentials" value=""/>
          <div class="form-field"><label class="field-label">License No.</label><input type="text" name="license_no" class="field-input" value="<?= htmlspecialchars($data['med_cert']['license_no']) ?>" oninput="updatePreview('med_cert')"/></div>
          <input type="hidden" name="ptr_no" value=""/>
          <div class="form-field"><label class="field-label">Footer Note</label><input type="text" name="footer_note" class="field-input" value="<?= htmlspecialchars($data['med_cert']['footer_note']) ?>" oninput="updatePreview('med_cert')"/></div>
          <button type="submit" name="save_template" class="btn-submit">Save Medical Certificate Template</button>
        </form>
      </div>
      <div>
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
            <div class="name" id="med_cert-doctor-line">[PHYSICIAN NAME]</div>
            <div class="lic">License No.: <span id="med_cert-license">[LICENSE NUMBER]</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
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

  if (tab === 'prescription') {
    const doctorLine = document.getElementById('rx-doctor-line');
    const doctor = val('physician_name') || '[DOCTOR NAME]';
    const creds = val('credentials') || '[CREDENTIALS]';
    if (doctorLine) doctorLine.textContent = doctor + ', ' + creds;
    const lic = document.getElementById('rx-license'); if (lic) lic.textContent = val('license_no') || '[LICENSE NUMBER]';
    const ptr = document.getElementById('rx-ptr'); if (ptr) ptr.textContent = val('ptr_no') || '[PTR NUMBER]';
  } else {
    const doctorLine = document.getElementById(tab + '-doctor-line');
    if (doctorLine) doctorLine.textContent = val('physician_name') || (tab === 'lab_request' ? '[REQUESTING PHYSICIAN NAME]' : '[PHYSICIAN NAME]');
    const lic = document.getElementById(tab + '-license'); if (lic) lic.textContent = val('license_no') || '[LICENSE NUMBER]';
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

// Initial tab: open the one just saved (if any), else default to prescription.
showTab('<?= htmlspecialchars($toast_tab) ?>');
['prescription','lab_request','med_cert'].forEach(updatePreview);

setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);
</script>
</body>
</html>
