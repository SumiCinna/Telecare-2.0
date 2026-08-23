<?php
// admin/receipt_config.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_receipt_settings'])) {
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $tin         = trim($_POST['tin'] ?? '');
    $footer_note = trim($_POST['footer_note'] ?? '');
    $show_logo   = isset($_POST['show_logo']) ? 1 : 0;

    if (!$clinic_name) {
        $_SESSION['toast_error'] = 'Clinic name is required.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO receipt_settings (id, clinic_name, address, phone, tin, footer_note, show_logo)
             VALUES (1, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE clinic_name=VALUES(clinic_name), address=VALUES(address),
               phone=VALUES(phone), tin=VALUES(tin), footer_note=VALUES(footer_note), show_logo=VALUES(show_logo)"
        );
        $stmt->bind_param("sssssi", $clinic_name, $address, $phone, $tin, $footer_note, $show_logo);
        $stmt->execute();
        if (function_exists('log_audit') && $conn->query("SHOW TABLES LIKE 'audit_logs'")->num_rows > 0) {
            log_audit($conn, $admin_id, 'update', 'receipt_settings', 1, null, ['clinic_name' => $clinic_name]);
        }
        $_SESSION['toast'] = 'Receipt settings saved.';
    }
    header('Location: receipt_config.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$settingsRow = $conn->query("SELECT * FROM receipt_settings WHERE id=1")->fetch_assoc();
if (!$settingsRow) {
    $settingsRow = [
        'clinic_name' => 'ExcellCare Medical System Inc.',
        'address' => '', 'phone' => '', 'tin' => '',
        'footer_note' => 'Thank you for choosing ExcellCare!',
        'show_logo' => 1,
    ];
}

$activeNav = 'pos-receipt';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Receipt Configuration — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="assets/admin.css" rel="stylesheet"/>
  <style>
    
    .main{flex:1;overflow-y:auto;margin-left:230px}
    .topbar{background:var(--white);padding:1rem 2rem;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .page-content{padding:2rem;display:grid;grid-template-columns:1fr 320px;gap:2rem;align-items:start}
    @media(max-width:900px){.page-content{grid-template-columns:1fr}.sidebar{display:none}}

    .card{background:var(--white);border-radius:16px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);padding:1.8rem}
    .field-label{display:block;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none}
    .form-field{margin-bottom:1rem}
    .checkbox-row{display:flex;align-items:center;gap:0.6rem;margin-bottom:1.2rem}
    .checkbox-row input{width:18px;height:18px}
    .btn-submit{padding:0.85rem 1.6rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.93rem;border:none;cursor:pointer}
    .btn-submit:hover{background:#a82d38}

    .receipt-preview{background:#fff;border:1px dashed rgba(36,68,65,0.25);border-radius:12px;padding:1.4rem;font-family:'Courier New',monospace;font-size:0.8rem;color:#222}
    .receipt-preview .center{text-align:center}
    .receipt-preview hr{border:none;border-top:1px dashed #999;margin:0.7rem 0}
    .receipt-preview .row{display:flex;justify-content:space-between;margin:0.2rem 0}
    .preview-label{font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.6rem}

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
    <div style="font-size:0.95rem;font-weight:700;">Receipt Configuration</div>
  </div>

  <div class="page-content">
    <div class="card">
      <form method="POST" id="receipt-form">
        <div class="form-field"><label class="field-label">Clinic Name *</label><input type="text" name="clinic_name" id="f-clinic-name" class="field-input" value="<?= htmlspecialchars($settingsRow['clinic_name']) ?>" oninput="updatePreview()" required/></div>
        <div class="form-field"><label class="field-label">Address</label><input type="text" name="address" id="f-address" class="field-input" value="<?= htmlspecialchars($settingsRow['address'] ?? '') ?>" oninput="updatePreview()"/></div>
        <div class="form-field"><label class="field-label">Phone</label><input type="text" name="phone" id="f-phone" class="field-input" value="<?= htmlspecialchars($settingsRow['phone'] ?? '') ?>" oninput="updatePreview()"/></div>
        <div class="form-field"><label class="field-label">TIN</label><input type="text" name="tin" id="f-tin" class="field-input" value="<?= htmlspecialchars($settingsRow['tin'] ?? '') ?>" oninput="updatePreview()"/></div>
        <div class="form-field"><label class="field-label">Footer Note</label><input type="text" name="footer_note" id="f-footer" class="field-input" value="<?= htmlspecialchars($settingsRow['footer_note'] ?? '') ?>" oninput="updatePreview()"/></div>
        <div class="checkbox-row">
          <input type="checkbox" name="show_logo" id="f-show-logo" <?= $settingsRow['show_logo'] ? 'checked' : '' ?> onchange="updatePreview()"/>
          <label for="f-show-logo" style="font-size:0.85rem;font-weight:600;">Show clinic logo on printed receipt</label>
        </div>
        <button type="submit" name="save_receipt_settings" class="btn-submit">Save Settings</button>
      </form>
    </div>

    <div>
      <div class="preview-label">Live Preview</div>
      <div class="receipt-preview">
        <div class="center" id="p-logo" style="<?= $settingsRow['show_logo'] ? '' : 'display:none;' ?>">🏥</div>
        <div class="center" style="font-weight:700;" id="p-clinic-name"><?= htmlspecialchars($settingsRow['clinic_name']) ?></div>
        <div class="center" id="p-address"><?= htmlspecialchars($settingsRow['address'] ?? '') ?></div>
        <div class="center" id="p-phone"><?= htmlspecialchars($settingsRow['phone'] ?? '') ?></div>
        <div class="center" id="p-tin">TIN: <?= htmlspecialchars($settingsRow['tin'] ?? '') ?></div>
        <hr/>
        <div class="row"><span>Paracetamol 500mg x2</span><span>₱20.00</span></div>
        <div class="row"><span>CBC Test</span><span>₱350.00</span></div>
        <hr/>
        <div class="row" style="font-weight:700;"><span>TOTAL</span><span>₱370.00</span></div>
        <hr/>
        <div class="center" id="p-footer"><?= htmlspecialchars($settingsRow['footer_note'] ?? '') ?></div>
      </div>
    </div>
  </div>
</div>

<script>
function updatePreview() {
  document.getElementById('p-clinic-name').textContent = document.getElementById('f-clinic-name').value;
  document.getElementById('p-address').textContent = document.getElementById('f-address').value;
  document.getElementById('p-phone').textContent = document.getElementById('f-phone').value;
  document.getElementById('p-tin').textContent = 'TIN: ' + document.getElementById('f-tin').value;
  document.getElementById('p-footer').textContent = document.getElementById('f-footer').value;
  document.getElementById('p-logo').style.display = document.getElementById('f-show-logo').checked ? '' : 'none';
}
setTimeout(() => { const t = document.querySelector('.toast'); if (t) t.remove(); }, 3500);
</script>
</body>
</html>