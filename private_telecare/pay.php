<?php
// private_telecare/pay.php
// Pay step of the booking flow:  Method -> Review -> Payment
// Methods: GCash (PayMongo), PhilHealth YAKAP, HMO.  (No card payments.)

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/legal_policy_helper.php';
require_once __DIR__ . '/booking/booking_helpers.php';
if (!isset($patient_id)) {
    die('❌ $patient_id is not set. Check auth.php — session key may differ.');
}

$paymongoSecretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? ($_SERVER['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY') ?: '');
define('PAYMONGO_SECRET_KEY', $paymongoSecretKey);

$appt_id = (int)($_GET['appt_id'] ?? 0);
if (!$appt_id) { header('Location: ../visits.php'); exit; }

$conn->query("UPDATE appointments SET status='Cancelled' WHERE status='Pending' AND payment_status='Unpaid' AND created_at < (NOW() - INTERVAL 10 MINUTE)");

$stmt = $conn->prepare("
    SELECT a.*, d.full_name AS doctor_name, d.specialty, d.consultation_fee,
           p.full_name AS patient_name, p.email AS patient_email, p.phone_number AS patient_phone
    FROM appointments a
    JOIN doctors  d ON d.id = a.doctor_id
    JOIN patients p ON p.id = a.patient_id
    WHERE a.id = ? AND a.patient_id = ? AND a.status = 'Pending' AND a.payment_status = 'Unpaid'
");
if ($stmt === false) { die('❌ Prepare failed: ' . htmlspecialchars($conn->error)); }
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    $_SESSION['toast_error'] = "Appointment not found or already paid.";
    header('Location: visits.php'); exit;
}

function formatPhone(?string $raw): ?string {
    if (empty(trim($raw ?? ''))) return null;
    $digits = preg_replace('/\D/', '', $raw);
    if (strlen($digits) === 11 && $digits[0] === '0')           return '+63' . substr($digits, 1);
    if (strlen($digits) === 10)                                  return '+63' . $digits;
    if (strlen($digits) === 12 && substr($digits,0,2) === '63') return '+' . $digits;
    return null;
}

// CSRF token for the payment form.
if (empty($_SESSION['pay_csrf'])) $_SESSION['pay_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['pay_csrf'];

$formErrors  = [];
$postedMeth  = '';
$isRepost    = false;

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_method'])) {
    while (ob_get_level()) ob_end_clean();

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $_SESSION['toast_error'] = 'Your session expired. Please try again.';
        header('Location: router.php?page=pay&appt_id=' . $appt_id); exit;
    }

    $method = (string)$_POST['pay_method'];

    // ── PhilHealth YAKAP / HMO: save the details, confirm the appointment ──
    if ($method === 'yakap' || $method === 'hmo') {
        $res = booking_save_coverage($conn, $patient_id, $appt_id, $method, $_POST);
        if (!empty($res['ok'])) {
            header('Location: ' . $res['redirect']); exit;
        }
        $formErrors = $res['errors'] ?? ['Could not save your details.'];
        $postedMeth = $method;
        $isRepost   = true;   // fall through and show the page again with the errors

    // ── GCash ──
    } elseif ($method === 'gcash') {
        $name         = trim((string)$appt['patient_name']);
        $email        = trim((string)$appt['patient_email']);
        $phone        = formatPhone($appt['patient_phone'] ?? '');
        $amount_cents = (int)(floatval($appt['consultation_fee']) * 100);
        if ($amount_cents < 10000) $amount_cents = 10000;

        $success_url = BASE_URL . '/router.php?page=pay_success&appt_id=' . $appt_id . '&patient=' . $patient_id;
        $failed_url  = BASE_URL . '/router.php?page=pay_cancel&appt_id='  . $appt_id;

        if (PAYMONGO_SECRET_KEY === '') {
            $_SESSION['toast_error'] = 'Payment setup error: missing PAYMONGO_SECRET_KEY in .env.';
            header('Location: router.php?page=pay&appt_id=' . $appt_id); exit;
        }

        $billing = ['name' => $name, 'email' => $email];
        if ($phone !== null) $billing['phone'] = $phone;

        $payload = ['data' => ['attributes' => [
            'amount'   => $amount_cents,
            'currency' => 'PHP',
            'type'     => 'gcash',
            'redirect' => ['success' => $success_url, 'failed' => $failed_url],
            'billing'  => $billing,
        ]]];

        $ch = curl_init('https://api.paymongo.com/v1/sources');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode((string)$response, true);

        if ($http_code === 200 && isset($result['data']['attributes']['redirect']['checkout_url'])) {
            $source_id = $result['data']['id'];
            $upd = $conn->prepare("UPDATE appointments SET paymongo_link_id = ? WHERE id = ?");
            if ($upd) { $upd->bind_param("si", $source_id, $appt_id); $upd->execute(); }
            // Remember the method on the appointment (best effort; needs the payment_method column).
            try {
                if (booking_ensure_payment_schema($conn)) {
                    $pm = $conn->prepare("UPDATE appointments SET payment_method='GCash' WHERE id=? AND patient_id=?");
                    $pm->bind_param('ii', $appt_id, $patient_id);
                    $pm->execute();
                }
            } catch (Throwable $ignore) { /* not critical */ }
            header('Location: ' . $result['data']['attributes']['redirect']['checkout_url']); exit;
        } else {
            $error = $result['errors'][0]['detail'] ?? 'GCash payment gateway error.';
            $_SESSION['toast_error'] = 'PayMongo Error: ' . $error;
            header('Location: router.php?page=pay&appt_id=' . $appt_id); exit;
        }

    } else {
        $_SESSION['toast_error'] = 'Invalid payment method.';
        header('Location: router.php?page=pay&appt_id=' . $appt_id); exit;
    }
}

$e         = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
// Re-show what the patient typed after a validation error; otherwise use profile defaults.
$old       = fn(string $k, string $d = '') => $e($isRepost ? (string)($_POST[$k] ?? '') : $d);
$checked   = fn(string $k, string $v) => ($isRepost && (string)($_POST[$k] ?? '') === $v) ? 'checked' : '';
$fee       = floatval($appt['consultation_fee']);
$fee_fmt   = '₱' . number_format($fee, 2);
$doc_name  = 'Dr. ' . $e($appt['doctor_name']);
$hmoList   = booking_hmo_provider_list($conn);
$dobFmt    = !empty($p['date_of_birth']) ? (new DateTime($p['date_of_birth']))->format('M j, Y') : '';
$apptWhen  = (new DateTime($appt['appointment_date']))->format('M j, Y') . ' at ' . date('g:i A', strtotime($appt['appointment_time']));
$defPhone  = (string)($appt['patient_phone'] ?? '');
$defAddr   = (string)($p['address'] ?? ($p['home_address'] ?? ''));

$ref_suffix = str_pad((string)((abs(crc32($appt_id . '|' . date('Ymd'))) % 9000) + 1000), 4, '0', STR_PAD_LEFT);
$reference_number = 'TC-' . date('dmY') . '-' . $ref_suffix;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/x-icon" href="/favicon.ico?v=2">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=2">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=2">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=2">
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Pay · TELE-CARE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --teal: #0d9488; --teal2: #0f766e; --green: #16a34a;
      --muted: #6b7280; --border: #e5e7eb; --bg: #f9fafb;
      --white: #ffffff; --text: #111827; --radius: 12px; --red: #ef4444;
    }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }

    .toast-container { position:fixed; top:1rem; left:50%; transform:translateX(-50%); width:90%; max-width:500px; z-index:9999; display:none; }
    .toast { padding:1rem 1.5rem; border-radius:10px; color:#fff; font-weight:600; font-size:0.9rem; box-shadow:0 4px 12px rgba(0,0,0,0.15); display:flex; align-items:center; gap:0.75rem; }
    .toast.error   { background:var(--red); }
    .toast.success { background:var(--green); }

    .checkout-header { background:linear-gradient(135deg,#0d9488 0%,#0f766e 60%,#134e4a 100%); padding:1.5rem 1rem 1.2rem; text-align:center; }
    .checkout-header h1 { font-family:'Plus Jakarta Sans',sans-serif; font-size:1.6rem; font-weight:800; color:#fff; }
    .checkout-header .tagline { font-size:0.72rem; color:rgba(255,255,255,0.65); margin-top:0.15rem; letter-spacing:0.04em; }

    .ref-bar { background:var(--white); border-bottom:1px solid var(--border); padding:0.6rem 1rem; display:flex; align-items:center; gap:0.6rem; font-size:0.78rem; color:var(--muted); flex-wrap:wrap; }
    .ref-num { font-weight:700; color:var(--text); }
    .method-tag { background:rgba(13,148,136,0.1); color:var(--teal); border:1px solid rgba(13,148,136,0.2); border-radius:6px; padding:0.15rem 0.5rem; font-size:0.7rem; font-weight:700; }

    .stepper { background:var(--white); border-bottom:1px solid var(--border); padding:0.9rem 1rem; display:flex; align-items:center; justify-content:center; overflow-x:auto; }
    .step-item { display:flex; align-items:center; gap:0.4rem; white-space:nowrap; }
    .step-circle { width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:700; flex-shrink:0; transition:all 0.3s; }
    .step-circle.done    { background:var(--teal); color:#fff; }
    .step-circle.active  { background:var(--teal); color:#fff; box-shadow:0 0 0 3px rgba(13,148,136,0.2); }
    .step-circle.pending { background:transparent; border:1.5px solid #d1d5db; color:#9ca3af; }
    .step-label { font-size:0.75rem; font-weight:600; color:var(--muted); }
    .step-label.active { color:var(--text); font-weight:700; }
    .step-line { flex:1; height:2px; background:#e5e7eb; margin:0 0.4rem; min-width:24px; max-width:60px; }
    .step-line.done { background:var(--teal); }

    .checkout-body { max-width:600px; margin:0 auto; padding:1.2rem 1rem 5rem; }
    .section-heading { font-family:'Plus Jakarta Sans',sans-serif; font-size:1rem; font-weight:800; color:var(--text); margin-bottom:0.8rem; }

    .amount-display { text-align:center; margin-bottom:1.5rem; }
    .amount-desc  { font-size:0.85rem; color:var(--muted); margin-bottom:0.3rem; }
    .amount-big   { font-family:'Plus Jakarta Sans',sans-serif; font-size:2.8rem; font-weight:800; color:var(--text); letter-spacing:-0.03em; line-height:1; }
    .amount-big .currency { font-size:1.5rem; vertical-align:top; margin-top:0.4rem; display:inline-block; }
    .amount-label { font-size:0.72rem; color:var(--muted); margin-top:0.3rem; }

    .method-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:0.6rem; margin-bottom:1.2rem; max-width:520px; margin-left:auto; margin-right:auto; }
    .method-card { border:2px solid var(--border); border-radius:var(--radius); padding:1rem 0.5rem; display:flex; flex-direction:column; align-items:center; gap:0.4rem; cursor:pointer; transition:all 0.2s; background:var(--white); position:relative; }
    .method-card:hover { border-color:var(--teal); }
    .method-card.selected { border-color:var(--teal); background:rgba(13,148,136,0.06); box-shadow:0 0 0 1px var(--teal); }
    .method-card .check-badge { position:absolute; top:-7px; right:-7px; width:20px; height:20px; background:var(--teal); border-radius:50%; display:none; align-items:center; justify-content:center; }
    .method-card.selected .check-badge { display:flex; }
    .method-icon  { width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; }
    .method-name  { font-size:0.78rem; font-weight:600; color:var(--text); text-align:center; }
    .method-sub   { font-size:0.65rem; color:var(--muted); text-align:center; }
    .logo-gcash   { background:#00a3e0; color:#fff; font-weight:800; font-size:1.2rem; }
    .logo-yakap   { background:#0b8f4d; color:#fff; font-weight:800; font-size:.8rem; }
    .logo-hmo     { background:#7e3af2; color:#fff; font-weight:800; font-size:.9rem; }

    .error-banner { background:#fef2f2; border:1px solid #fca5a5; border-radius:var(--radius); padding:0.65rem 0.9rem; font-size:0.8rem; color:#991b1b; margin-bottom:1rem; display:none; }
    .error-banner.visible { display:block; }

    .form-row   { display:grid; grid-template-columns:1fr 1fr; gap:0.8rem; margin-bottom:1rem; }
    .form-group { margin-bottom:1rem; }
    .form-label { display:block; font-size:0.75rem; font-weight:700; color:var(--text); margin-bottom:0.35rem; }
    .form-label .req { color:#ef4444; }
    .form-input { width:100%; padding:0.7rem 0.9rem; border:1.5px solid var(--border); border-radius:var(--radius); font-family:'DM Sans',sans-serif; font-size:0.88rem; color:var(--text); background:var(--white); outline:none; transition:border-color 0.2s,box-shadow 0.2s; }
    .form-input:focus { border-color:var(--teal); box-shadow:0 0 0 3px rgba(13,148,136,0.1); }

    .summary-block { background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:1.2rem; }
    .summary-block-title { font-weight:800; font-size:0.88rem; color:var(--text); padding:0.7rem 0.9rem 0.3rem; font-family:'Plus Jakarta Sans',sans-serif; }
    .summary-table { width:100%; border-collapse:collapse; font-size:0.85rem; margin-bottom:1.2rem; }
    .summary-table td { padding:0.7rem 0.9rem; border-bottom:1px solid var(--border); }
    .summary-table td:first-child { color:var(--muted); font-weight:500; width:40%; }
    .summary-table td:last-child  { color:var(--text); font-weight:600; }

    .privacy-note { display:flex; align-items:center; gap:0.5rem; margin-bottom:1.2rem; font-size:0.75rem; color:var(--muted); }
    .privacy-note input[type=checkbox] { width:16px; height:16px; accent-color:var(--teal); flex-shrink:0; }
    .privacy-note a { color:var(--teal); text-decoration:none; font-weight:600; }

    .nav-row { display:flex; align-items:center; gap:0.8rem; padding:1rem; position:fixed; bottom:0; left:0; right:0; background:var(--white); border-top:1px solid var(--border); z-index:10; }
    .btn-back { padding:0.7rem 1.4rem; border:1.5px solid var(--border); border-radius:50px; background:var(--white); font-family:'DM Sans',sans-serif; font-size:0.85rem; font-weight:600; color:var(--muted); cursor:pointer; transition:all 0.2s; }
    .btn-back:hover { border-color:var(--teal); color:var(--teal); }
    .btn-next { flex:1; padding:0.8rem 1.4rem; border-radius:50px; border:none; background:var(--teal); color:#fff; font-family:'DM Sans',sans-serif; font-size:0.9rem; font-weight:700; cursor:pointer; transition:all 0.2s; box-shadow:0 4px 14px rgba(13,148,136,0.3); }
    .btn-next:hover:not(:disabled) { background:var(--teal2); transform:translateY(-1px); }
    .btn-next:disabled { background:#d1d5db; color:#9ca3af; box-shadow:none; cursor:not-allowed; }

    .powered-by { text-align:center; font-size:0.7rem; color:#9ca3af; display:flex; align-items:center; justify-content:center; gap:0.3rem; margin-top:1.5rem; }

    .checkout-step { display:none; }
    .checkout-step.active { display:block; }

    @keyframes spin { to { transform:rotate(360deg); } }

    /* ── Privacy Modal ── */
    .policy-modal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9998; }
    .policy-modal.visible { display:flex; align-items:center; justify-content:center; }
    .policy-modal-content { background:var(--white); border-radius:16px; max-width:650px; max-height:85vh; overflow-y:auto; padding:2rem; box-shadow:0 20px 60px rgba(0,0,0,0.3); position:relative; }
    .policy-modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1.5px solid var(--border); }
    .policy-modal-header h2 { font-family:'Plus Jakarta Sans',sans-serif; font-size:1.4rem; font-weight:800; color:var(--text); margin:0; }
    .policy-modal-close { background:none; border:none; font-size:1.8rem; color:var(--muted); cursor:pointer; padding:0; width:32px; height:32px; display:flex; align-items:center; justify-content:center; transition:color 0.2s; }
    .policy-modal-close:hover { color:var(--text); }
    .policy-modal-body { font-size:0.92rem; color:var(--text); line-height:1.7; }
    .policy-modal-body h3 { font-weight:700; margin-top:1.2rem; margin-bottom:0.6rem; color:var(--text); }
    .policy-modal-body ul { margin:0.8rem 0 0.8rem 1.5rem; }
    .policy-modal-body li { margin-bottom:0.5rem; }
    .policy-modal-body .highlight { background:rgba(13,148,136,0.08); border-left:3px solid var(--teal); padding:1rem; border-radius:6px; margin:1rem 0; }
    .policy-modal-footer { display:flex; gap:0.8rem; margin-top:2rem; padding-top:1rem; border-top:1.5px solid var(--border); }
    .policy-modal-footer button { flex:1; padding:0.8rem 1.2rem; border:none; border-radius:50px; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif; font-size:0.88rem; transition:all 0.2s; }
    .btn-policy-close { background:var(--border); color:var(--muted); }
    .btn-policy-close:hover { background:#d1d5db; }
    .btn-policy-agree { background:var(--teal); color:#fff; box-shadow:0 4px 12px rgba(13,148,136,0.3); }
    .btn-policy-agree:hover { background:var(--teal2); }
    @media(max-width:600px) {
      .policy-modal-content { max-width:95vw; padding:1.5rem; }
      .policy-modal-header h2 { font-size:1.2rem; }
      .policy-modal-body { font-size:0.88rem; }
    }

    .method-sub { line-height:1.25; }
    @media(max-width:420px){ .method-grid{gap:.4rem} .method-card{padding:.8rem .3rem} .method-name{font-size:.72rem} }
    .cov-form { display:none; background:var(--white); border:1.5px solid var(--border); border-radius:var(--radius); padding:1rem; margin-bottom:1rem; }
    .cov-form.visible { display:block; }
    .cov-form fieldset { border:0; min-width:0; }
    .cov-title { font-family:'Plus Jakarta Sans',sans-serif; font-weight:800; font-size:.92rem; margin-bottom:.15rem; }
    .cov-sub { font-size:.74rem; color:var(--muted); margin-bottom:.9rem; line-height:1.4; }
    .cov-sec { font-size:.68rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; color:var(--teal2); margin:1rem 0 .55rem; }
    .cov-grid { display:grid; grid-template-columns:1fr 1fr; gap:.7rem .8rem; }
    .cov-grid .full { grid-column:1 / -1; }
    @media(max-width:520px){ .cov-grid{grid-template-columns:1fr} }
    .cov-form select.form-input { appearance:auto; }
    .radio-row { display:flex; gap:.9rem; flex-wrap:wrap; font-size:.84rem; padding:.35rem 0; }
    .radio-row label { display:flex; align-items:center; gap:.35rem; cursor:pointer; }
    .radio-row input { accent-color:var(--teal); }
    .cov-note { font-size:.72rem; color:var(--muted); background:rgba(13,148,136,.06); border-radius:8px; padding:.55rem .7rem; margin-top:.9rem; line-height:1.45; }
    .cov-consent { display:flex; gap:.5rem; align-items:flex-start; font-size:.76rem; margin-top:.9rem; line-height:1.4; }
    .cov-consent input { margin-top:.15rem; accent-color:var(--teal); }
    .ro-input { background:#f3f4f6; color:var(--muted); }
    .error-banner div + div { margin-top:.2rem; }
    .cov-sum td:last-child { word-break:break-word; }
  </style>
</head>
<body>

<div id="toast-container" class="toast-container">
  <div id="toast" class="toast error"><span id="toast-msg"></span></div>
</div>

<div class="checkout-header">
  <h1>TELE-CARE</h1>
  <div class="tagline">SECURE PAYMENT</div>
</div>

<div class="ref-bar">
  <span>Reference:</span>
  <span class="ref-num"><?= $e($reference_number) ?></span>
  <span class="method-tag" id="method-display" style="display:none;"></span>
</div>

<div class="stepper">
  <div class="step-item"><div class="step-circle active" id="sc1">1</div><span class="step-label active" id="sl1">Method</span></div>
  <div class="step-line" id="line1"></div>
  <div class="step-item"><div class="step-circle pending" id="sc2">2</div><span class="step-label" id="sl2">Review</span></div>
  <div class="step-line" id="line2"></div>
  <div class="step-item"><div class="step-circle pending" id="sc3">3</div><span class="step-label" id="sl3">Payment</span></div>
</div>

<div class="checkout-body">

<form method="POST" id="pay-form" action="router.php?page=pay&appt_id=<?= $appt_id ?>" novalidate>
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>"/>
  <input type="hidden" name="pay_method" id="f-method" value=""/>

  <!-- ════ STEP 1: METHOD ════ -->
  <div class="checkout-step active" id="step1">
    <div class="amount-display">
      <div class="amount-desc">Teleconsultation with <?= $doc_name ?></div>
      <div class="amount-big"><span class="currency">₱</span><?= number_format($fee, 2) ?></div>
      <div class="amount-label">Consultation fee</div>
    </div>

    <div class="section-heading">SELECT PAYMENT METHOD</div>

    <div class="method-grid">
      <div class="method-card" data-method="gcash" data-label="GCash" onclick="selectMethod(this)">
        <div class="check-badge"><svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
        <div class="method-icon logo-gcash">G</div>
        <div class="method-name">GCash</div>
        <div class="method-sub">e-Wallet</div>
      </div>
      <div class="method-card" data-method="yakap" data-label="PhilHealth YAKAP" onclick="selectMethod(this)">
        <div class="check-badge"><svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
        <div class="method-icon logo-yakap">YAKAP</div>
        <div class="method-name">PhilHealth YAKAP</div>
        <div class="method-sub">Covered by PhilHealth</div>
      </div>
      <div class="method-card" data-method="hmo" data-label="HMO" onclick="selectMethod(this)">
        <div class="check-badge"><svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
        <div class="method-icon logo-hmo">HMO</div>
        <div class="method-name">HMO</div>
        <div class="method-sub">Covered by your HMO</div>
      </div>
    </div>

    <div id="form-error" class="error-banner<?= $formErrors ? ' visible' : '' ?>"><?php foreach ($formErrors as $fe): ?><div><?= $e($fe) ?></div><?php endforeach; ?></div>

    <!-- YAKAP details -->
    <div class="cov-form" id="cov-yakap">
      <fieldset disabled>
        <div class="cov-title">PhilHealth YAKAP details</div>
        <div class="cov-sub">The clinic verifies these after booking. No payment is taken now.</div>

        <div class="cov-sec">Patient information</div>
        <div class="cov-grid">
          <div class="full">
            <label class="form-label" for="y-pin"><span class="req">*</span> PhilHealth PIN</label>
            <input class="form-input" id="y-pin" name="philhealth_pin" inputmode="numeric" autocomplete="off" maxlength="14" placeholder="12-345678901-2" value="<?= $old('philhealth_pin') ?>"/>
          </div>
          <div>
            <label class="form-label">Patient name</label>
            <input class="form-input ro-input" readonly value="<?= $e($appt['patient_name']) ?>"/>
          </div>
          <div>
            <label class="form-label">Date of birth</label>
            <input class="form-input ro-input" readonly value="<?= $e($dobFmt) ?>"/>
          </div>
          <div class="full">
            <label class="form-label"><span class="req">*</span> Member type</label>
            <div class="radio-row">
              <label><input type="radio" name="member_type" value="Member" <?= $checked('member_type','Member') ?>/> Member</label>
              <label><input type="radio" name="member_type" value="Dependent" <?= $checked('member_type','Dependent') ?>/> Dependent</label>
            </div>
          </div>
          <div>
            <label class="form-label" for="y-contact"><span class="req">*</span> Contact number</label>
            <input class="form-input" id="y-contact" type="tel" name="contact_number" placeholder="09XX XXX XXXX" value="<?= $old('contact_number', $defPhone) ?>"/>
          </div>
          <div>
            <label class="form-label" for="y-addr"><span class="req">*</span> Address</label>
            <input class="form-input" id="y-addr" name="address" maxlength="255" placeholder="Complete address" value="<?= $old('address', $defAddr) ?>"/>
          </div>
        </div>

        <div class="cov-sec">YAKAP details</div>
        <div class="cov-grid">
          <div class="full">
            <label class="form-label" for="y-clinic"><span class="req">*</span> YAKAP clinic</label>
            <input class="form-input" id="y-clinic" name="yakap_clinic" maxlength="150" placeholder="Your selected / accredited YAKAP clinic" value="<?= $old('yakap_clinic') ?>"/>
          </div>
          <div>
            <label class="form-label"><span class="req">*</span> YES / MCA (empanelment)</label>
            <div class="radio-row">
              <label><input type="radio" name="empanelment_status" value="Empaneled" <?= $checked('empanelment_status','Empaneled') ?>/> Empaneled</label>
              <label><input type="radio" name="empanelment_status" value="Not Yet Empaneled" <?= $checked('empanelment_status','Not Yet Empaneled') ?>/> Not yet</label>
            </div>
          </div>
          <div>
            <label class="form-label"><span class="req">*</span> First Patient Encounter (FPE)</label>
            <div class="radio-row">
              <label><input type="radio" name="fpe_status" value="Completed" <?= $checked('fpe_status','Completed') ?>/> Completed</label>
              <label><input type="radio" name="fpe_status" value="Not Yet Completed" <?= $checked('fpe_status','Not Yet Completed') ?>/> Not yet</label>
            </div>
          </div>
        </div>

        <div class="cov-note">Diagnosis, prescription and PCU reference are added by your doctor after the consultation.</div>
        <label class="cov-consent"><input type="checkbox" name="consent" value="1" <?= $checked('consent','1') ?>/> <span>I confirm that the information provided is correct and authorize its processing for PhilHealth / YAKAP purposes.</span></label>
      </fieldset>
    </div>

    <!-- HMO details -->
    <div class="cov-form" id="cov-hmo">
      <fieldset disabled>
        <div class="cov-title">HMO details</div>
        <div class="cov-sub">The clinic checks your coverage and authorization after booking. No payment is taken now.</div>

        <div class="cov-sec">HMO</div>
        <div class="cov-grid">
          <div>
            <label class="form-label" for="h-prov"><span class="req">*</span> HMO provider</label>
            <select class="form-input" id="h-prov" name="hmo_provider">
              <option value="">Select your HMO</option>
              <?php foreach ($hmoList as $hp): ?>
                <option value="<?= $e($hp) ?>" <?= ($isRepost && ($_POST['hmo_provider'] ?? '') === $hp) ? 'selected' : '' ?>><?= $e($hp) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="h-other-wrap" style="display:none;">
            <label class="form-label" for="h-other"><span class="req">*</span> HMO name</label>
            <input class="form-input" id="h-other" name="hmo_provider_other" maxlength="90" value="<?= $old('hmo_provider_other') ?>"/>
          </div>
          <div>
            <label class="form-label" for="h-mid"><span class="req">*</span> Member ID / Card no.</label>
            <input class="form-input" id="h-mid" name="hmo_member_id" maxlength="60" value="<?= $old('hmo_member_id') ?>"/>
          </div>
          <div>
            <label class="form-label"><span class="req">*</span> Member type</label>
            <div class="radio-row">
              <label><input type="radio" name="member_type" value="Member" <?= $checked('member_type','Member') ?>/> Member</label>
              <label><input type="radio" name="member_type" value="Dependent" <?= $checked('member_type','Dependent') ?>/> Dependent</label>
            </div>
          </div>
          <div>
            <label class="form-label" for="h-pm"><span class="req">*</span> Principal member</label>
            <input class="form-input" id="h-pm" name="principal_member_name" maxlength="150" placeholder="Full name of principal member" value="<?= $old('principal_member_name') ?>"/>
          </div>
          <div>
            <label class="form-label" for="h-comp">Company / employer</label>
            <input class="form-input" id="h-comp" name="company_employer" maxlength="150" placeholder="Optional" value="<?= $old('company_employer') ?>"/>
          </div>
          <div>
            <label class="form-label" for="h-plan">Plan / account type</label>
            <input class="form-input" id="h-plan" name="hmo_plan" maxlength="100" placeholder="Optional" value="<?= $old('hmo_plan') ?>"/>
          </div>
          <div>
            <label class="form-label" for="h-loa">LOA / authorization no.</label>
            <input class="form-input" id="h-loa" name="loa_number" maxlength="60" placeholder="If available" value="<?= $old('loa_number') ?>"/>
          </div>
        </div>

        <div class="cov-sec">Patient &amp; consultation</div>
        <div class="cov-grid">
          <div>
            <label class="form-label">Patient name</label>
            <input class="form-input ro-input" readonly value="<?= $e($appt['patient_name']) ?>"/>
          </div>
          <div>
            <label class="form-label">Date of birth</label>
            <input class="form-input ro-input" readonly value="<?= $e($dobFmt) ?>"/>
          </div>
          <div>
            <label class="form-label" for="h-contact"><span class="req">*</span> Contact number</label>
            <input class="form-input" id="h-contact" type="tel" name="contact_number" placeholder="09XX XXX XXXX" value="<?= $old('contact_number', $defPhone) ?>"/>
          </div>
          <div>
            <label class="form-label">Doctor</label>
            <input class="form-input ro-input" readonly value="<?= $doc_name ?>"/>
          </div>
          <div class="full">
            <label class="form-label"><span class="req">*</span> Service</label>
            <div class="radio-row">
              <label><input type="radio" name="service_type" value="Online Consultation" <?= $isRepost ? $checked('service_type','Online Consultation') : 'checked' ?>/> Online Consultation</label>
              <label><input type="radio" name="service_type" value="Follow-up Consultation" <?= $checked('service_type','Follow-up Consultation') ?>/> Follow-up Consultation</label>
            </div>
          </div>
        </div>

        <div class="cov-note">Diagnosis, prescription, HMO coverage amount, your share (co-payment) and the claim reference are added by the clinic.</div>
        <label class="cov-consent"><input type="checkbox" name="consent" value="1" <?= $checked('consent','1') ?>/> <span>I confirm that the information provided is accurate and authorize processing and sharing of my information for HMO purposes.</span></label>
      </fieldset>
    </div>
  </div>

  <!-- ════ STEP 2: REVIEW ════ -->
  <div class="checkout-step" id="step2">
    <div class="summary-block">
      <div class="summary-block-title">Appointment</div>
      <table class="summary-table">
        <tr><td>Doctor</td><td><?= $doc_name ?></td></tr>
        <tr><td>Date &amp; time</td><td><?= $e($apptWhen) ?></td></tr>
        <tr><td>Patient</td><td><?= $e($appt['patient_name']) ?></td></tr>
      </table>
    </div>
    <div class="summary-block">
      <div class="summary-block-title">Payment</div>
      <table class="summary-table cov-sum" id="sum-payment">
        <tr><td>Method</td><td id="sum-method"></td></tr>
        <tr><td>Consultation fee</td><td><?= $e($fee_fmt) ?></td></tr>
        <tr><td id="sum-due-label">Amount to pay now</td><td id="sum-due" style="font-size:1rem;color:var(--teal);"></td></tr>
      </table>
    </div>
    <div class="summary-block" id="sum-details-block" style="display:none;">
      <div class="summary-block-title">Your details</div>
      <table class="summary-table cov-sum" id="sum-details"></table>
    </div>
    <div class="privacy-note">
      <input type="checkbox" id="agree-chk"/>
      <label for="agree-chk">I have read and agreed to TELE-CARE's <a href="#" onclick="openPolicyModal(event)">Privacy Policy</a>.</label>
    </div>
  </div>

  <!-- ════ STEP 3: PAYMENT ════ -->
  <div class="checkout-step" id="step3">
    <div style="text-align:center;padding:3rem 1rem;">
      <div style="width:60px;height:60px;border:4px solid rgba(13,148,136,0.2);border-top-color:var(--teal);border-radius:50%;animation:spin 0.8s linear infinite;margin:0 auto 1.2rem;"></div>
      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.1rem;font-weight:800;margin-bottom:0.5rem;" id="processing-label">Processing…</div>
      <div style="font-size:0.82rem;color:var(--muted);">Please wait…</div>
    </div>
  </div>
</form>

  <div class="powered-by">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    Secured by TELE-CARE · GCash payments powered by PayMongo
  </div>
</div>

<!-- Privacy Policy Modal -->
<div class="policy-modal" id="policy-modal">
  <div class="policy-modal-content">
    <div class="policy-modal-header">
      <h2>Privacy Policy</h2>
      <button class="policy-modal-close" onclick="closePolicyModal()">&times;</button>
    </div>
    <div class="policy-modal-body">
      <?= legal_policy_content($conn, 'payment-policy') ?>
    </div>
    <div class="policy-modal-footer">
      <button class="btn-policy-close" onclick="closePolicyModal()">Close</button>
      <button class="btn-policy-agree" onclick="agreePolicyModal()">I Agree</button>
    </div>
  </div>
</div>

<div class="nav-row">
  <button type="button" class="btn-back" id="btn-back" onclick="goBack()" style="display:none;">Back</button>
  <button type="button" class="btn-next" id="btn-next" onclick="goNext()" disabled>Next</button>
</div>

<script>
  let step = 1, selMethod = null, selLabel = null;
  const FEE = <?= json_encode($fee_fmt) ?>;

  // ── Toast ──
  function showToast(msg, type='error') {
    const c = document.getElementById('toast-container');
    document.getElementById('toast').className = 'toast ' + type;
    document.getElementById('toast-msg').textContent = msg;
    c.style.display = 'block';
    setTimeout(() => c.style.display='none', 6000);
  }
  <?php if (isset($_SESSION['toast_error'])): ?>
    showToast(<?= json_encode($_SESSION['toast_error']) ?>, 'error');
    <?php unset($_SESSION['toast_error']); ?>
  <?php endif; ?>

  // ── Method select ──
  function selectMethod(el) {
    document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selMethod = el.dataset.method; selLabel = el.dataset.label;
    document.getElementById('method-display').textContent   = selLabel;
    document.getElementById('method-display').style.display = '';
    document.getElementById('f-method').value = selMethod;
    // Only the chosen coverage form is enabled, so its fields are the only ones submitted.
    ['yakap', 'hmo'].forEach(m => {
      const box = document.getElementById('cov-' + m);
      box.classList.toggle('visible', selMethod === m);
      box.querySelector('fieldset').disabled = (selMethod !== m);
    });
    setFormError([]);
    document.getElementById('btn-next').disabled = false;
  }

  function setFormError(list) {
    const b = document.getElementById('form-error');
    b.textContent = '';
    list.forEach(t => { const d = document.createElement('div'); d.textContent = t; b.appendChild(d); });
    b.classList.toggle('visible', list.length > 0);
  }

  // ── PIN + HMO "Other" helpers ──
  (function () {
    const el = document.getElementById('y-pin');
    el.addEventListener('input', () => {
      const d = el.value.replace(/\D/g, '').slice(0, 12);
      let out = d;
      if (d.length > 2)  out = d.slice(0, 2) + '-' + d.slice(2);
      if (d.length > 11) out = d.slice(0, 2) + '-' + d.slice(2, 11) + '-' + d.slice(11);
      el.value = out;
    });
    const prov = document.getElementById('h-prov');
    const sync = () => { document.getElementById('h-other-wrap').style.display = prov.value === 'Other' ? '' : 'none'; };
    prov.addEventListener('change', sync); sync();
  })();

  // ── Coverage form helpers (client side only helps the patient; the server re-checks everything) ──
  function covBox() { return document.getElementById('cov-' + selMethod); }
  function val(name) { const f = covBox().querySelector('[name="' + name + '"]'); return f ? f.value.trim() : ''; }
  function radio(name) { const f = covBox().querySelector('[name="' + name + '"]:checked'); return f ? f.value : ''; }
  function consented() { return covBox().querySelector('[name="consent"]').checked; }

  function validateCoverage() {
    const errs = [];
    if (!radio('member_type')) errs.push('Select whether the patient is a Member or a Dependent.');
    if (val('contact_number').replace(/\D/g, '').length < 10) errs.push('Enter a valid contact number.');
    if (selMethod === 'yakap') {
      if (val('philhealth_pin').replace(/\D/g, '').length !== 12) errs.push('PhilHealth PIN must be 12 digits.');
      if (!val('address')) errs.push('Address is required.');
      if (!val('yakap_clinic')) errs.push('Enter your YAKAP clinic.');
      if (!radio('empanelment_status')) errs.push('Select your YES/MCA (empanelment) status.');
      if (!radio('fpe_status')) errs.push('Select your First Patient Encounter (FPE) status.');
    } else {
      if (!val('hmo_provider')) errs.push('Select your HMO provider.');
      if (val('hmo_provider') === 'Other' && !val('hmo_provider_other')) errs.push('Enter the name of your HMO provider.');
      if (!val('hmo_member_id')) errs.push('HMO Member ID / Card No. is required.');
      if (!val('principal_member_name')) errs.push('Principal member name is required.');
      if (!radio('service_type')) errs.push('Select the type of consultation.');
    }
    if (!consented()) errs.push('Please confirm the consent statement.');
    return errs;
  }

  function maskPin(raw) {
    const d = raw.replace(/\D/g, '');
    return d.length === 12 ? '••-•••••' + d.slice(7, 11) + '-' + d.slice(11) : d;
  }

  function fillReview() {
    document.getElementById('sum-method').textContent = selLabel;
    const details = document.getElementById('sum-details');
    const block = document.getElementById('sum-details-block');
    details.textContent = '';
    const row = (k, v) => {
      if (!v) return;
      const tr = document.createElement('tr');
      const a = document.createElement('td'); a.textContent = k;
      const b = document.createElement('td'); b.textContent = v;
      tr.append(a, b); details.appendChild(tr);
    };
    if (selMethod === 'gcash') {
      block.style.display = 'none';
      document.getElementById('sum-due-label').textContent = 'Amount to pay now';
      document.getElementById('sum-due').textContent = FEE;
      return;
    }
    block.style.display = '';
    document.getElementById('sum-due-label').textContent = 'Amount to pay now';
    document.getElementById('sum-due').textContent = '₱0.00 (coverage — verified by the clinic)';
    row('Member type', radio('member_type'));
    row('Contact number', val('contact_number'));
    if (selMethod === 'yakap') {
      row('PhilHealth PIN', maskPin(val('philhealth_pin')));
      row('Address', val('address'));
      row('YAKAP clinic', val('yakap_clinic'));
      row('YES / MCA', radio('empanelment_status'));
      row('FPE', radio('fpe_status'));
    } else {
      row('HMO provider', val('hmo_provider') === 'Other' ? 'Other: ' + val('hmo_provider_other') : val('hmo_provider'));
      row('Member ID / Card no.', val('hmo_member_id'));
      row('Principal member', val('principal_member_name'));
      row('Company / employer', val('company_employer'));
      row('Plan', val('hmo_plan'));
      row('LOA no.', val('loa_number'));
      row('Service', radio('service_type'));
    }
  }

  // ── Stepper ──
  function updateStepper(n) {
    for (let i=1;i<=3;i++) {
      const sc = document.getElementById('sc'+i), sl = document.getElementById('sl'+i);
      sc.className = 'step-circle '+(i<n?'done':i===n?'active':'pending');
      sl.className = 'step-label'+(i===n?' active':'');
      if(i<n) sc.innerHTML='<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
      else sc.textContent=i;
      if(i<3) document.getElementById('line'+i).className='step-line '+(i<n?'done':'');
    }
  }

  // ── Show step ──
  function showStep(n) {
    document.querySelectorAll('.checkout-step').forEach(s=>s.classList.remove('active'));
    document.getElementById('step'+n).classList.add('active');
    updateStepper(n);
    const bb=document.getElementById('btn-back'), bn=document.getElementById('btn-next');
    bb.style.display = n===2 ? '' : 'none';
    if (n===1) { bn.disabled=!selMethod; bn.textContent='Next'; bn.style.display=''; }
    else if (n===2) {
      bn.textContent = selMethod === 'gcash' ? 'Confirm & Pay with GCash →' : 'Confirm & Submit →';
      const chk=document.getElementById('agree-chk');
      chk.checked=false; bn.disabled=true; bn.style.display='';
      chk.onchange=()=>{ bn.disabled=!chk.checked; };
    } else { bn.style.display='none'; bb.style.display='none'; }
    window.scrollTo(0, 0);
  }

  function goBack() { if (step===2) { step=1; showStep(1); } }

  function goNext() {
    if (step===1) {
      if (!selMethod) return;
      if (selMethod !== 'gcash') {
        const errs = validateCoverage();
        if (errs.length) { setFormError(errs); document.getElementById('form-error').scrollIntoView({behavior:'smooth', block:'center'}); return; }
        setFormError([]);
      }
      fillReview();
      step=2; showStep(2);
    } else if (step===2) {
      submitPayment();
    }
  }

  function submitPayment() {
    step=3; showStep(3);
    document.getElementById('processing-label').textContent =
      selMethod === 'gcash' ? 'Redirecting to GCash…' : 'Saving your details…';
    setTimeout(() => document.getElementById('pay-form').submit(), 600);
  }

  // After a server-side validation error, reopen the chosen method with the typed values.
  <?php if ($isRepost && $postedMeth !== ''): ?>
  (function () {
    const el = document.querySelector('[data-method="<?= $e($postedMeth) ?>"]');
    if (el) { selectMethod(el); setFormError(<?= json_encode(array_values($formErrors)) ?>); showToast(<?= json_encode($formErrors[0] ?? 'Please check your details.') ?>, 'error'); }
  })();
  <?php endif; ?>

  // ── Privacy Policy Modal ──
  function openPolicyModal(e) {
    e.preventDefault();
    document.getElementById('policy-modal').classList.add('visible');
  }
  function closePolicyModal() {
    document.getElementById('policy-modal').classList.remove('visible');
  }
  function agreePolicyModal() {
    document.getElementById('agree-chk').checked = true;
    document.getElementById('btn-next').disabled = false;
    closePolicyModal();
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.getElementById('policy-modal').classList.contains('visible')) {
      closePolicyModal();
    }
  });
</script>
</body>
</html>
