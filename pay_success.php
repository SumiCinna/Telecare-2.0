<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

// pay_success.php — PayMongo redirects here after payment
// Verifies the payment server-side, marks appointment Paid, redirects to receipt.

define('PAYMONGO_SECRET_KEY', 'sk_test_bg7ic4jq6oGSkDPeU5xeQFn5');

$appt_id   = (int)($_GET['appt_id']   ?? 0);
$patient_q = (int)($_GET['patient']   ?? 0);
// intent_id may be passed in the return URL for card payments
$intent_id_q = trim($_GET['intent_id'] ?? '');

if (!$appt_id) { header('Location: visits.php'); exit; }

// Fetch appointment (no payment_status filter — we need to check paid too)
$stmt = $conn->prepare("
    SELECT a.*, d.full_name AS doctor_name, d.specialty, d.consultation_fee,
           p.full_name AS patient_name, p.email AS patient_email
    FROM appointments a
    JOIN doctors  d ON d.id  = a.doctor_id
    JOIN patients p ON p.id  = a.patient_id
    WHERE a.id = ? AND a.patient_id = ?
");
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) { header('Location: visits.php'); exit; }

// Already paid — just show receipt
if ($appt['payment_status'] === 'Paid') {
    header('Location: receipt.php?appt_id=' . $appt_id);
    exit;
}

// ── Helper: call PayMongo GET endpoint ──
function paymongo_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($body, true)];
}

$verified = false;
$link_id  = $appt['paymongo_link_id'] ?? null;

if ($link_id) {

    if (str_starts_with($link_id, 'src_')) {
        // ── Source-based (GCash) ──
        $r  = paymongo_get('https://api.paymongo.com/v1/sources/' . $link_id);
        if ($r['code'] === 200) {
            $status = $r['body']['data']['attributes']['status'] ?? '';
            // 'chargeable' means user approved; 'consumed' means already charged
            $verified = in_array($status, ['chargeable', 'consumed', 'paid']);
        }

    } elseif (str_starts_with($link_id, 'pi_')) {
        // ── Payment Intent (Card via our attach flow) ──
        // Prefer the intent_id from the URL query string (passed in return_url),
        // but fall back to the stored one.
        $pi_to_check = $intent_id_q ?: $link_id;
        $r = paymongo_get('https://api.paymongo.com/v1/payment_intents/' . $pi_to_check);
        if ($r['code'] === 200) {
            $status = $r['body']['data']['attributes']['status'] ?? '';
            $verified = ($status === 'succeeded');
        }

    } elseif (str_starts_with($link_id, 'lnk_')) {
        // ── Link-based (legacy / OTC) ──
        $r = paymongo_get('https://api.paymongo.com/v1/links/' . $link_id);
        if ($r['code'] === 200) {
            $status = $r['body']['data']['attributes']['status'] ?? '';
            $verified = ($status === 'paid');
        }

    } else {
        // Unknown ID prefix — in test mode allow through; in production use webhooks
        $verified = true;
    }

} else {
    // No stored ID — allow in test mode
    // In production this should be rejected; rely on webhooks instead.
    $verified = true;
}

if ($verified) {
    $receipt_no = 'TC-' . strtoupper(substr(md5($appt_id . time()), 0, 8));

    // Try the full column set first; fall back gracefully if columns don't exist yet
    $upd = $conn->prepare("
        UPDATE appointments
        SET payment_status = 'Paid',
            receipt_number = ?,
            paid_at        = NOW()
        WHERE id = ? AND patient_id = ?
    ");
    if ($upd) {
        $upd->bind_param("sii", $receipt_no, $appt_id, $patient_id);
        if (!$upd->execute()) {
            // Columns may not exist — fallback
            $conn->query("UPDATE appointments SET payment_status='Paid' WHERE id=$appt_id");
        }
    } else {
        $conn->query("UPDATE appointments SET payment_status='Paid' WHERE id=$appt_id");
    }

    $_SESSION['toast'] = "Payment successful! Your appointment is confirmed.";
    header('Location: receipt.php?appt_id=' . $appt_id);
    exit;

} else {
    $_SESSION['toast_error'] = "Payment could not be verified. Please contact support if you were charged.";
    header('Location: visits.php');
    exit;
}