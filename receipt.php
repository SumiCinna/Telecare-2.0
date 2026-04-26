<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

// receipt.php — Shared receipt accessible by patient, doctor (via doctor/receipt.php symlink), or staff

$appt_id = (int)($_GET['appt_id'] ?? 0);
if (!$appt_id) { header('Location: visits.php'); exit; }

// Access control: patient, staff, or doctor
$access = false;
$role   = $_SESSION['role'] ?? 'patient';

if ($role === 'patient') {
    $stmt = $conn->prepare("
        SELECT a.*, 
               d.full_name AS doctor_name, d.specialty, d.consultation_fee,
               p.full_name AS patient_name, p.email AS patient_email, p.phone_number AS patient_phone
        FROM appointments a
        JOIN doctors  d ON d.id = a.doctor_id
        JOIN patients p ON p.id = a.patient_id
        WHERE a.id = ? AND a.patient_id = ? AND a.payment_status = 'Paid'
    ");
    $stmt->bind_param("ii", $appt_id, $patient_id);
} elseif ($role === 'staff') {
    $stmt = $conn->prepare("
        SELECT a.*, 
               d.full_name AS doctor_name, d.specialty, d.consultation_fee,
               p.full_name AS patient_name, p.email AS patient_email, p.phone_number AS patient_phone
        FROM appointments a
        JOIN doctors  d ON d.id = a.doctor_id
        JOIN patients p ON p.id = a.patient_id
        WHERE a.id = ? AND a.payment_status = 'Paid'
    ");
    $stmt->bind_param("i", $appt_id);
} elseif ($role === 'doctor') {
    $stmt = $conn->prepare("
        SELECT a.*, 
               d.full_name AS doctor_name, d.specialty, d.consultation_fee,
               p.full_name AS patient_name, p.email AS patient_email, p.phone_number AS patient_phone
        FROM appointments a
        JOIN doctors  d ON d.id = a.doctor_id
        JOIN patients p ON p.id = a.patient_id
        WHERE a.id = ? AND a.doctor_id = ? AND a.payment_status = 'Paid'
    ");
    $stmt->bind_param("ii", $appt_id, $doctor_id);
} else {
    header('Location: visits.php'); exit;
}

$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();

if (!$appt) {
    $_SESSION['toast_error'] = "Receipt not found or payment not yet completed.";
    header('Location: visits.php'); exit;
}

$toast       = $_SESSION['toast']       ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error']);

$paid_at       = !empty($appt['paid_at']) ? new DateTime($appt['paid_at']) : new DateTime();
$appt_date     = new DateTime($appt['appointment_date']);
$amount        = floatval($appt['consultation_fee'] ?? 0);

function makeReceiptRef(DateTimeInterface $date, int $seed): string {
  $random4 = str_pad((string)((abs(crc32($seed . '|' . $date->format('YmdHis'))) % 9000) + 1000), 4, '0', STR_PAD_LEFT);
  return 'TC-' . $date->format('dmY') . '-' . $random4;
}

$receipt_no = !empty($appt['receipt_number'])
  ? $appt['receipt_number']
  : makeReceiptRef($paid_at, $appt_id);

$back_url = match($role) {
    'staff'  => 'staff/appointments.php',
    'doctor' => 'doctor/appointments.php',
    default  => 'visits.php',
};

$page_title = 'Payment Receipt — TELE-CARE';
require_once 'includes/header.php';
?>

<style>
  .receipt-wrap{max-width:480px;margin:0 auto;padding:1.2rem 0 3rem;}
  .receipt-card{background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(36,68,65,0.12);border:1px solid rgba(36,68,65,0.08);}

  /* Zigzag tear effect top */
  .receipt-top{background:linear-gradient(135deg,var(--green,#244441),#1a3533);padding:1.6rem 1.4rem 1.2rem;color:#fff;position:relative;}
  .receipt-top::after{content:'';position:absolute;bottom:-10px;left:0;right:0;height:20px;
    background:repeating-linear-gradient(-45deg,#fff 0,#fff 7px,transparent 7px,transparent 14px),
               repeating-linear-gradient(45deg,#fff 0,#fff 7px,transparent 7px,transparent 14px);
    background-size:20px 20px;background-position:0 0,10px 0;}

  .receipt-logo{font-size:0.72rem;font-weight:800;letter-spacing:0.12em;text-transform:uppercase;opacity:0.7;margin-bottom:0.5rem;}
  .receipt-title{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:800;margin-bottom:0.3rem;}
  .receipt-subtitle{font-size:0.78rem;opacity:0.75;}

  .receipt-body{padding:1.8rem 1.4rem 0;}
  .receipt-status{display:flex;align-items:center;gap:0.6rem;background:rgba(34,197,94,0.08);border:1.5px solid rgba(34,197,94,0.25);border-radius:14px;padding:0.75rem 1rem;margin-bottom:1.2rem;}
  .receipt-status-icon{width:36px;height:36px;background:linear-gradient(135deg,#16a34a,#15803d);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
  .receipt-status-text{font-weight:700;font-size:0.9rem;color:#15803d;}
  .receipt-status-sub{font-size:0.73rem;color:#16a34a;opacity:0.8;}

  .receipt-no{text-align:center;margin-bottom:1.2rem;}
  .receipt-no-label{font-size:0.65rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:0.2rem;}
  .receipt-no-val{font-size:1.15rem;font-weight:800;color:var(--green);letter-spacing:0.05em;font-family:'DM Mono',monospace,sans-serif;}

  .receipt-divider{border:none;border-top:1.5px dashed rgba(36,68,65,0.12);margin:1rem 0;}

  .receipt-row{display:flex;justify-content:space-between;align-items:flex-start;padding:0.35rem 0;font-size:0.83rem;}
  .receipt-row-label{color:var(--muted);font-weight:600;}
  .receipt-row-val{color:var(--green);font-weight:700;text-align:right;max-width:60%;}

  .receipt-amount-box{background:rgba(36,68,65,0.04);border-radius:14px;padding:1rem;text-align:center;margin:1.2rem 0;}
  .receipt-amount-label{font-size:0.7rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin-bottom:0.3rem;}
  .receipt-amount-val{font-size:2rem;font-weight:800;color:var(--green);}
  .receipt-amount-status{display:inline-flex;align-items:center;gap:0.3rem;background:#16a34a;color:#fff;border-radius:50px;padding:0.2rem 0.8rem;font-size:0.72rem;font-weight:700;margin-top:0.4rem;}

  /* Bottom tear + footer */
  .receipt-tear{height:20px;
    background:repeating-linear-gradient(-45deg,rgba(36,68,65,0.04) 0,rgba(36,68,65,0.04) 7px,transparent 7px,transparent 14px),
               repeating-linear-gradient(45deg,rgba(36,68,65,0.04) 0,rgba(36,68,65,0.04) 7px,transparent 7px,transparent 14px);
    background-size:20px 20px;background-position:0 0,10px 0;margin:0 -1.4rem;}
  .receipt-footer{padding:1rem 1.4rem 1.4rem;text-align:center;}
  .receipt-footer-text{font-size:0.72rem;color:var(--muted);line-height:1.6;}
  .receipt-qr-placeholder{width:64px;height:64px;background:rgba(36,68,65,0.06);border-radius:10px;margin:0.6rem auto;display:flex;align-items:center;justify-content:center;font-size:0.6rem;color:var(--muted);}

  .btn-print{display:inline-flex;align-items:center;gap:0.4rem;background:var(--green);color:#fff;padding:0.65rem 1.4rem;border-radius:50px;font-size:0.82rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:1rem;text-decoration:none;}
  .btn-back-link{display:inline-flex;align-items:center;gap:0.35rem;color:var(--muted);font-size:0.8rem;font-weight:600;text-decoration:none;margin-top:0.6rem;}
  .toast-bar{position:fixed;bottom:5rem;left:50%;transform:translateX(-50%);z-index:400;padding:0.75rem 1.4rem;border-radius:16px;font-size:0.82rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.15);white-space:normal;max-width:88vw;text-align:center;}
  .toast-bar.success{background:var(--green);color:#fff;animation:toastOut 0.4s 3.5s ease forwards;}
  .toast-bar.error{background:#C33643;color:#fff;}
  @keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}
  @media print{
    body * { visibility: hidden !important; }

    .receipt-wrap,
    .receipt-wrap * {
      visibility: visible !important;
    }

    .receipt-wrap {
      position: absolute;
      left: 0;
      top: 0;
      width: 100%;
      max-width: 100%;
      margin: 0;
      padding: 0;
    }

    .page {
      max-width: 100% !important;
      margin: 0 !important;
      padding: 0 !important;
      background: #fff !important;
    }

    .receipt-card {
      box-shadow: none;
      border: none;
      border-radius: 0;
    }

    .toast-bar,
    .no-print {
      display: none !important;
      visibility: hidden !important;
    }
  }
</style>

<?php if ($toast): ?><div class="toast-bar success">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php if ($toast_error): ?><div class="toast-bar error">✕ <?= htmlspecialchars($toast_error) ?></div><?php endif; ?>

<div class="page">
<div class="receipt-wrap">

  <!-- Back button -->
  <a href="<?= $back_url ?>" class="btn-back-link no-print" style="display:flex;margin-bottom:1rem;">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Back to Appointments
  </a>

  <div class="receipt-card">

    <!-- Header -->
    <div class="receipt-top">
      <div class="receipt-logo">🏥 Tele-Care</div>
      <div class="receipt-title">Payment Receipt</div>
      <div class="receipt-subtitle">Official Consultation Receipt</div>
    </div>

    <div class="receipt-body">

      <!-- Status -->
      <div class="receipt-status">
        <div class="receipt-status-icon">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div>
          <div class="receipt-status-text">Payment Successful</div>
          <div class="receipt-status-sub"><?= $paid_at->format('F j, Y · g:i A') ?></div>
        </div>
      </div>

      <!-- Receipt number -->
      <div class="receipt-no">
        <div class="receipt-no-label">Receipt No.</div>
        <div class="receipt-no-val"><?= htmlspecialchars($receipt_no) ?></div>
      </div>

      <hr class="receipt-divider"/>

      <!-- Details -->
      <div class="receipt-row">
        <span class="receipt-row-label">Patient</span>
        <span class="receipt-row-val"><?= htmlspecialchars($appt['patient_name']) ?></span>
      </div>
      <?php if (!empty($appt['patient_email'])): ?>
      <div class="receipt-row">
        <span class="receipt-row-label">Email</span>
        <span class="receipt-row-val" style="font-size:0.75rem"><?= htmlspecialchars($appt['patient_email']) ?></span>
      </div>
      <?php endif; ?>
      <div class="receipt-row">
        <span class="receipt-row-label">Doctor</span>
        <span class="receipt-row-val">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Specialty</span>
        <span class="receipt-row-val"><?= htmlspecialchars($appt['specialty'] ?? '—') ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Appointment</span>
        <span class="receipt-row-val"><?= $appt_date->format('F j, Y') ?> · <?= date('g:i A', strtotime($appt['appointment_time'])) ?></span>
      </div>
      <div class="receipt-row">
        <span class="receipt-row-label">Type</span>
        <span class="receipt-row-val">📹 <?= htmlspecialchars($appt['type']) ?></span>
      </div>

      <hr class="receipt-divider"/>

      <!-- Amount -->
      <div class="receipt-amount-box">
        <div class="receipt-amount-label">Total Amount Paid</div>
        <div class="receipt-amount-val">₱<?= number_format($amount, 2) ?></div>
        <div>
          <span class="receipt-amount-status">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            PAID
          </span>
        </div>
      </div>

      <div class="receipt-row" style="font-size:0.75rem;">
        <span class="receipt-row-label">Payment via</span>
        <span class="receipt-row-val">PayMongo (GCash / Card)</span>
      </div>
      <div class="receipt-row" style="font-size:0.75rem;">
        <span class="receipt-row-label">Appointment ID</span>
        <span class="receipt-row-val">#<?= $appt_id ?></span>
      </div>

    </div>

    <!-- Tear + Footer -->
    <div style="padding:0 1.4rem;margin-top:1.2rem;">
      <div class="receipt-tear"></div>
    </div>
    <div class="receipt-footer">
      <div class="receipt-qr-placeholder">
        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="opacity:0.3"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM17 17h3v3h-3zM14 17h.01"/></svg>
      </div>
      <div class="receipt-footer-text">
        Thank you for choosing TELE-CARE.<br/>
        Please present this receipt to your doctor at the time of your teleconsultation.<br/>
        <strong>This serves as your official proof of payment.</strong>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div style="text-align:center;margin-top:1.2rem;" class="no-print">
    <button class="btn-print" onclick="window.print()">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Print / Save PDF
    </button>
    <br/>
    <a href="<?= $back_url ?>" class="btn-back-link" style="justify-content:center;">Back to Appointments</a>
  </div>

</div>
</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>