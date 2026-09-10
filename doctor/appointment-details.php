<?php
// doctor/appointment-details.php
require_once 'includes/auth.php';

date_default_timezone_set('Asia/Manila');
$appointment_id = (int)($_GET['appt_id'] ?? 0);

$stmt = $conn->prepare("SELECT a.*, p.full_name AS patient_name, p.profile_photo AS patient_photo, p.email AS patient_email, p.phone_number AS patient_phone, p.date_of_birth, p.gender, p.address, p.home_address, p.city, p.country_region, p.emergency_name, p.emergency_relationship, p.emergency_number, p.insurance_provider, p.insurance_policy_no, p.preferred_language, d.full_name AS doctor_name, d.specialty, d.consultation_fee FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id WHERE a.id=? AND a.doctor_id=? LIMIT 1");
$stmt->bind_param('ii', $appointment_id, $doctor_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
if (!$appointment) {
    header('Location: appointments.php');
    exit;
}

function detail_value(?string $value, string $fallback = 'Not provided'): string {
    $value = trim((string)$value);
    return $value !== '' ? htmlspecialchars($value) : $fallback;
}

$attachment_paths = array_values(array_filter(array_map('trim', explode(',', (string)($appointment['attachment_path'] ?? '')))));
$attachment_types = array_values(array_map('trim', explode(',', (string)($appointment['attachment_type'] ?? ''))));
$attachment_ocr = array_values(explode("\n---\n", (string)($appointment['attachment_ocr_text'] ?? '')));
$appointment_timestamp = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
$location = implode(', ', array_filter([$appointment['address'] ?? '', $appointment['home_address'] ?? '', $appointment['city'] ?? '', $appointment['country_region'] ?? '']));

$page_title = 'Appointment Details — TELE-CARE';
$page_title_short = 'Appointment Details';
$active_nav = 'appointments';
require_once 'includes/header.php';
?>

<style>
  .details-page{max-width:1200px;width:100%;}
  .details-heading{align-items:flex-start;display:flex;justify-content:space-between;gap:1rem;margin-bottom:1rem;}
  .details-heading h1{color:var(--neutral-900);font-size:clamp(1.35rem,2.4vw,1.8rem);margin-bottom:.25rem;}
  .details-heading p{color:var(--neutral-500);font-size:.78rem;}
  .details-back{color:var(--primary);font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap;}
  .details-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(260px,.8fr);gap:1rem;align-items:start;}
  .details-card{background:var(--surface);border:1px solid var(--border-color);border-radius:var(--radius-md);box-shadow:var(--shadow-sm);margin-bottom:1rem;padding:1rem;}
  .details-card h2{color:var(--neutral-900);font-size:.92rem;margin-bottom:.8rem;}
  .patient-banner{align-items:center;display:flex;gap:.8rem;}
  .patient-photo{align-items:center;background:var(--secondary-soft);border-radius:50%;color:var(--secondary-dark);display:flex;flex:none;font-size:1rem;font-weight:700;height:52px;justify-content:center;overflow:hidden;width:52px;}
  .patient-photo img{height:100%;object-fit:cover;width:100%;}
  .patient-name{color:var(--neutral-900);font-size:1rem;font-weight:700;}
  .patient-meta{color:var(--neutral-500);font-size:.72rem;margin-top:.18rem;}
  .status-pill{border-radius:999px;font-size:.64rem;font-weight:700;padding:.3rem .6rem;white-space:nowrap;}
  .status-confirmed{background:var(--secondary-soft);color:var(--secondary-dark);}.status-pending{background:#fff1dc;color:#9a5b00;}.status-cancelled{background:var(--primary-soft);color:var(--primary-dark);}.status-completed{background:#e7f8ef;color:#078447;}.status-doctorapproved{background:#e8efff;color:#3158a5;}
  .details-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem 1rem;}
  .detail-label{color:var(--neutral-500);display:block;font-size:.62rem;font-weight:700;letter-spacing:.04em;margin-bottom:.22rem;text-transform:uppercase;}
  .detail-value{color:var(--neutral-900);font-size:.78rem;font-weight:600;line-height:1.4;}
  .detail-wide{grid-column:1/-1;}
  .reason-box{background:var(--neutral-50);border:1px solid var(--border-color);border-radius:var(--radius-sm);color:var(--neutral-700);font-size:.78rem;line-height:1.55;padding:.75rem;}
  .document-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:.65rem;}
  .document-item{border:1px solid var(--border-color);border-radius:var(--radius-sm);overflow:hidden;}
  .document-item img{display:block;height:90px;object-fit:cover;width:100%;}
  .document-item figcaption{color:var(--neutral-500);font-size:.62rem;padding:.45rem;}
  .ocr-box{background:var(--neutral-50);border:1px solid var(--border-color);border-radius:var(--radius-sm);color:var(--neutral-700);font-size:.72rem;line-height:1.5;margin-top:.65rem;max-height:180px;overflow:auto;padding:.7rem;white-space:pre-wrap;}
  .action-list{display:grid;gap:.55rem;}
  .detail-action{align-items:center;border:1px solid var(--border-color);border-radius:var(--radius-sm);color:var(--neutral-700);display:flex;font-size:.72rem;font-weight:700;gap:.5rem;padding:.65rem .7rem;text-decoration:none;}
  .detail-action:hover{border-color:var(--primary);color:var(--primary);}
  .detail-action.primary{background:var(--primary);border-color:var(--primary);color:#fff;justify-content:center;}
  .detail-action svg{flex:none;height:16px;width:16px;}
  .empty-detail{color:var(--neutral-500);font-size:.76rem;padding:.3rem 0;}
  @media(max-width:850px){.details-layout{grid-template-columns:1fr;}.details-sidebar{order:-1;}}
  @media(max-width:520px){.details-heading{flex-direction:column;}.details-grid{grid-template-columns:1fr;}.detail-wide{grid-column:auto;}}
</style>

<main class="page details-page">
  <div class="details-heading">
    <div><h1>Appointment Details</h1><p>Review the patient's booking information before the consultation.</p></div>
    <a class="details-back" href="appointments.php">&#8592; Back to Schedule</a>
  </div>

  <div class="details-layout">
    <div>
      <section class="details-card">
        <div class="patient-banner">
          <div class="patient-photo">
            <?php if (!empty($appointment['patient_photo'])): ?><img src="../<?= htmlspecialchars($appointment['patient_photo']) ?>" alt="Patient photo"/><?php else: ?><?= htmlspecialchars(strtoupper(substr($appointment['patient_name'], 0, 2))) ?><?php endif; ?>
          </div>
          <div style="flex:1;min-width:0;"><div class="patient-name"><?= htmlspecialchars($appointment['patient_name']) ?></div><div class="patient-meta">Patient ID #<?= (int)$appointment['patient_id'] ?> · <?= detail_value($appointment['patient_email']) ?></div></div>
          <span class="status-pill status-<?= htmlspecialchars(strtolower((string)$appointment['status'])) ?>"><?= htmlspecialchars($appointment['status']) ?></span>
        </div>
      </section>

      <section class="details-card">
        <h2>Appointment Information</h2>
        <div class="details-grid">
          <div><span class="detail-label">Date &amp; Time</span><span class="detail-value"><?= date('F j, Y', $appointment_timestamp) ?> · <?= date('g:i A', $appointment_timestamp) ?></span></div>
          <div><span class="detail-label">Consultation Type</span><span class="detail-value"><?= detail_value($appointment['type'], 'Teleconsultation') ?></span></div>
          <div><span class="detail-label">Department</span><span class="detail-value"><?= detail_value($appointment['department']) ?></span></div>
          <div><span class="detail-label">Reference Number</span><span class="detail-value"><?= detail_value($appointment['reference_no']) ?></span></div>
          <div><span class="detail-label">Payment</span><span class="detail-value"><?= detail_value($appointment['payment_status']) ?><?= !empty($appointment['receipt_number']) ? ' · ' . htmlspecialchars($appointment['receipt_number']) : '' ?></span></div>
          <div><span class="detail-label">Booked On</span><span class="detail-value"><?= !empty($appointment['created_at']) ? date('M j, Y g:i A', strtotime($appointment['created_at'])) : 'Not recorded' ?></span></div>
          <div class="detail-wide"><span class="detail-label">Reason for Consultation</span><div class="reason-box"><?= detail_value($appointment['reason'], 'No reason was provided.') ?></div></div>
          <div class="detail-wide"><span class="detail-label">Additional Notes</span><div class="reason-box"><?= detail_value($appointment['notes'], 'No additional notes were provided.') ?></div></div>
        </div>
      </section>

      <section class="details-card">
        <h2>Patient Information</h2>
        <div class="details-grid">
          <div><span class="detail-label">Email</span><span class="detail-value"><?= detail_value($appointment['patient_email']) ?></span></div>
          <div><span class="detail-label">Phone</span><span class="detail-value"><?= detail_value($appointment['patient_phone']) ?></span></div>
          <div><span class="detail-label">Date of Birth</span><span class="detail-value"><?= !empty($appointment['date_of_birth']) ? date('F j, Y', strtotime($appointment['date_of_birth'])) : 'Not provided' ?></span></div>
          <div><span class="detail-label">Gender</span><span class="detail-value"><?= detail_value($appointment['gender']) ?></span></div>
          <div class="detail-wide"><span class="detail-label">Address</span><span class="detail-value"><?= detail_value($location) ?></span></div>
          <div><span class="detail-label">Preferred Language</span><span class="detail-value"><?= detail_value($appointment['preferred_language']) ?></span></div>
          <div><span class="detail-label">Emergency Contact</span><span class="detail-value"><?= detail_value($appointment['emergency_name']) ?><?= !empty($appointment['emergency_relationship']) ? ' · ' . htmlspecialchars($appointment['emergency_relationship']) : '' ?><?= !empty($appointment['emergency_number']) ? ' · ' . htmlspecialchars($appointment['emergency_number']) : '' ?></span></div>
          <div><span class="detail-label">Insurance Provider</span><span class="detail-value"><?= detail_value($appointment['insurance_provider']) ?></span></div>
          <div><span class="detail-label">Policy Number</span><span class="detail-value"><?= detail_value($appointment['insurance_policy_no']) ?></span></div>
        </div>
      </section>

      <section class="details-card">
        <h2>Uploaded Booking Documents</h2>
        <?php if ($attachment_paths): ?>
          <div class="document-grid">
            <?php foreach ($attachment_paths as $index => $path): ?><figure class="document-item"><a href="../<?= htmlspecialchars($path) ?>" target="_blank" rel="noopener"><img src="../<?= htmlspecialchars($path) ?>" alt="Uploaded booking document"/></a><figcaption><?= htmlspecialchars($attachment_types[$index] ?? 'Patient document') ?></figcaption></figure><?php endforeach; ?>
          </div>
          <?php foreach ($attachment_ocr as $index => $ocr): if (trim($ocr) !== ''): ?><div class="ocr-box"><strong>Extracted text <?= $index + 1 ?>:</strong><br><?= htmlspecialchars($ocr) ?></div><?php endif; endforeach; ?>
        <?php else: ?><div class="empty-detail">No documents were attached during booking.</div><?php endif; ?>
      </section>
    </div>

    <aside class="details-sidebar">
      <section class="details-card">
        <h2>Available Actions</h2>
        <div class="action-list">
          <a class="detail-action primary" href="chat.php?patient_id=<?= (int)$appointment['patient_id'] ?>"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m7-2a8 8 0 11-16 0c0 1.3.31 2.53.87 3.62L4 20l4.38-1.87A8 8 0 0120 12z"/></svg>Message Patient</a>
          <a class="detail-action" href="patient-records.php?patient_id=<?= (int)$appointment['patient_id'] ?>"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 4h12a2 2 0 012 2v14H4V6a2 2 0 012-2Zm3 0v4h6V4M8 13h8M8 17h5"/></svg>View Medical Records</a>
          <?php if ($appointment['status'] === 'Confirmed' && $appointment['payment_status'] === 'Paid'): ?><a class="detail-action" href="call.php?appt_id=<?= (int)$appointment['id'] ?>"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.89L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>Open Consultation</a><?php endif; ?>
          <a class="detail-action" href="appointments.php">Back to Schedule</a>
        </div>
      </section>
      <section class="details-card"><h2>Consultation Output</h2><?php if (!empty($appointment['summary_pdf_path'])): ?><a class="detail-action" href="/summary?appt_id=<?= (int)$appointment['id'] ?>">View Consultation Summary</a><?php elseif (!empty($appointment['consultation_summary'])): ?><a class="detail-action" href="review_summary.php?appt_id=<?= (int)$appointment['id'] ?>">Review Consultation Summary</a><?php else: ?><div class="empty-detail">No consultation summary is available yet.</div><?php endif; ?></section>
    </aside>
  </div>
</main>
<?php require_once 'includes/nav.php'; ?>
</body>
</html>
