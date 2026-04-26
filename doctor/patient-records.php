<?php
require_once 'includes/auth.php';

$patient_id = (int)($_GET['patient_id'] ?? 0);

// Verify this doctor has access to this patient
$check = $conn->query("
    SELECT p.* FROM patients p
    JOIN patient_doctors pd ON pd.patient_id=p.id
    WHERE p.id=$patient_id AND pd.doctor_id=$doctor_id LIMIT 1
")->fetch_assoc();

if (!$check) {
    header('Location: patients.php');
    exit;
}

$patient = $check;

// Get all lab results and prescriptions
$records = $conn->query("
    SELECT * FROM lab_results
    WHERE patient_id=$patient_id
    ORDER BY uploaded_at DESC
");

// Function to highlight important keywords in extracted text
function formatOcrText(string $text, string $type): string {
    if (!$text) return '<span style="color:#9ab0ae;font-style:italic;">No text could be extracted.</span>';

    $text = htmlspecialchars($text);

    // Hardcoded common medicine names
    $medicines = ['amoxicillin','metformin','losartan','paracetamol','ibuprofen','aspirin',
                  'amlodipine','atorvastatin','omeprazole','cetirizine','azithromycin',
                  'ciprofloxacin','mefenamic','salbutamol','montelukast','prednisone',
                  'furosemide','lisinopril','hydrochlorothiazide','clopidogrel','insulin',
                  'azithromycin','salbutamol'];
    foreach ($medicines as $med) {
        $text = preg_replace('/\b('.preg_quote($med, '/').')\b/i',
            '<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }

    // Dynamic — capitalized word before a dosage
    $text = preg_replace(
        '/\b([A-Z][a-zA-Z\-]{2,})(?=\s+\d+\.?\d*\s*(?:mg|ml|mcg|g\b|iu|units?))/i',
        '<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>',
        $text
    );

    // Numbered drug list
    $text = preg_replace(
        '/(\d+\.\s+)([A-Z][a-zA-Z\-]{2,})(\s+(?:Tablet|Capsule|Cap|Tab|Syrup|Solution|Drops|Cream|Injection)s?)?/i',
        '$1<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$2</mark>$3',
        $text
    );

    // Capitalized word directly before Tablet/Capsule/Cap/Tab
    $text = preg_replace(
        '/\b([A-Z][a-zA-Z\-]{2,})\s+(Tablet|Capsule|Cap|Tab)s?\b/i',
        '<mark style="background:rgba(63,130,227,0.15);color:#1a4fa8;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark> $2',
        $text
    );

    // Dosage: numbers + mg/ml/mcg/units
    $text = preg_replace('/\b(\d+\.?\d*\s*(?:mg|ml|mcg|units?|g\b|iu))\b/i',
        '<mark style="background:rgba(244,132,95,0.15);color:#c05621;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);

    // Frequencies & timing
    $freqs = ['once daily','twice daily','three times daily','four times daily',
              'every 4 hours','every 6 hours','every 8 hours','every 12 hours',
              'morning','bedtime','evening','at night','with meals','after meals','before meals',
              'od','bid','tid','qid','prn','sos','take \d+','sig:','dispense:','refills?:\s*\d+'];
    foreach ($freqs as $f) {
        $text = preg_replace('/\b('.$f.')\b/i',
            '<mark style="background:rgba(244,132,95,0.15);color:#c05621;border-radius:4px;padding:0 3px;font-weight:600;">$1</mark>', $text);
    }

    // Lab keywords
    $labs = ['hemoglobin','hematocrit','wbc','rbc','platelet','glucose','cholesterol',
             'creatinine','uric acid','sodium','potassium','normal range','reference range',
             'high','low','abnormal','result','reactive','non-reactive'];
    foreach ($labs as $lab) {
        $text = preg_replace('/\b('.preg_quote($lab, '/').')\b/i',
            '<mark style="background:rgba(34,197,94,0.12);color:#15803d;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }

    // Important warnings
    $warns = ['warning','caution','allergy','allergic','do not','avoid','contraindicated',
              'emergency','urgent','immediately','refill','expired','expiry'];
    foreach ($warns as $w) {
        $text = preg_replace('/\b('.preg_quote($w, '/').')\b/i',
            '<mark style="background:rgba(168,85,247,0.12);color:#6d28d9;border-radius:4px;padding:0 3px;font-weight:700;">$1</mark>', $text);
    }

    $text = nl2br($text);
    return $text;
}

$page_title       = htmlspecialchars($patient['full_name']) . ' — Records — TELE-CARE';
$page_title_short = 'Patient Records';
$active_nav       = 'patients';
require_once 'includes/header.php';
?>

<div class="page">

  <!-- Patient Header -->
  <div class="card" style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
    <div class="pat-avatar" style="width:60px;height:60px;font-size:1.2rem;">
      <?php if (!empty($patient['profile_photo'])): ?>
        <img src="../<?= htmlspecialchars($patient['profile_photo']) ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;"/>
      <?php else: ?>
        <?= strtoupper(substr($patient['full_name'], 0, 2)) ?>
      <?php endif; ?>
    </div>
    <div style="flex:1;">
      <div style="font-weight:700;font-size:1rem;"><?= htmlspecialchars($patient['full_name']) ?></div>
      <div style="font-size:0.78rem;color:var(--muted);"><?= htmlspecialchars($patient['email']) ?></div>
      <div style="font-size:0.75rem;color:var(--muted);margin-top:0.3rem;">
        DOB: <?= $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'Not set' ?>
        · Gender: <?= ucfirst($patient['gender'] ?? 'Not set') ?>
      </div>
    </div>
    <a href="patients.php" style="background:rgba(36,68,65,0.1);color:var(--green);border:none;border-radius:10px;padding:0.5rem 1rem;font-size:0.85rem;font-weight:600;text-decoration:none;">
      ← Back
    </a>
  </div>

  <!-- Records -->
  <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);margin-bottom:1rem;padding:0 0.2rem;">
    Medical Records
  </div>

  <?php if ($records && $records->num_rows > 0): ?>
    <?php while ($rec = $records->fetch_assoc()): ?>
      <?php
        $type_info = [
          'lab_result' => ['label' => 'Lab Result', 'color' => '#3F82E3', 'bg' => 'rgba(63,130,227,0.1)', 'icon' => '🧪'],
          'prescription' => ['label' => 'Prescription', 'color' => '#f4845f', 'bg' => 'rgba(244,132,95,0.1)', 'icon' => '💊'],
          'unknown' => ['label' => 'Document', 'color' => '#9ab0ae', 'bg' => 'rgba(154,176,174,0.1)', 'icon' => '📄'],
        ];
        $info = $type_info[$rec['doc_type']] ?? $type_info['unknown'];
      ?>
      <div class="card" style="margin-bottom:1rem;cursor:pointer;transition:all 0.2s;" onclick="toggleRecord(<?= $rec['id'] ?>)">
        <div style="display:flex;align-items:flex-start;gap:1rem;margin-bottom:0.8rem;">
          <div style="font-size:2rem;"><?= $info['icon'] ?></div>
          <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.3rem;">
              <div style="font-weight:700;font-size:0.95rem;"><?= !empty($rec['doc_label']) ? htmlspecialchars($rec['doc_label']) : 'Untitled' ?></div>
              <span style="background:<?= $info['bg'] ?>;color:<?= $info['color'] ?>;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;">
                <?= $info['label'] ?>
              </span>
            </div>
            <div style="font-size:0.75rem;color:var(--muted);">
              Uploaded: <?= date('M d, Y · g:i A', strtotime($rec['uploaded_at'])) ?>
              · <?= number_format(strlen($rec['extracted_text'] ?? '') / 1024, 1) ?> KB
            </div>
          </div>
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--blue);transition:transform 0.3s,color 0.3s;flex-shrink:0;margin-top:0.2rem;" class="toggle-arrow">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
          </svg>
        </div>

        <!-- Record Preview (Hidden by default) -->
        <div id="record-<?= $rec['id'] ?>" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid rgba(36,68,65,0.06);">
          <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.7rem;">
            Extracted Text
          </div>
          <div style="background:rgba(36,68,65,0.03);border:1px solid rgba(63,130,227,0.1);border-radius:12px;padding:1rem;font-size:0.84rem;line-height:1.8;color:var(--green);max-height:350px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;">
            <?= formatOcrText($rec['extracted_text'] ?? '', $rec['doc_type']) ?>
          </div>
          <div style="display:flex;gap:0.8rem;margin-top:0.8rem;flex-wrap:wrap;">
            <span style="font-size:0.68rem;background:rgba(63,130,227,0.12);color:var(--blue);padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">💊 Medicine</span>
            <span style="font-size:0.68rem;background:rgba(244,132,95,0.12);color:#f4845f;padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">⚡ Dosage/Freq</span>
            <span style="font-size:0.68rem;background:rgba(34,197,94,0.1);color:#16a34a;padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">🧪 Lab Value</span>
            <span style="font-size:0.68rem;background:rgba(168,85,247,0.1);color:#7c3aed;padding:0.2rem 0.6rem;border-radius:50px;font-weight:700;">⚠️ Important</span>
          </div>
          <div style="display:flex;gap:0.8rem;margin-top:0.8rem;">
            <a href="../<?= htmlspecialchars($rec['file_path'] ?? '') ?>" target="_blank" style="flex:1;padding:0.6rem;border-radius:12px;background:var(--blue);color:#fff;text-align:center;font-weight:700;font-size:0.82rem;text-decoration:none;">
              📥 View Original
            </a>
            <button onclick="copyRecordText(<?= $rec['id'] ?>)" style="flex:1;padding:0.6rem;border-radius:12px;background:rgba(36,68,65,0.1);color:var(--green);border:none;font-weight:700;font-size:0.82rem;cursor:pointer;font-family:'DM Sans',sans-serif;">
              📋 Copy Text
            </button>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="card">
      <div class="empty-state">
        <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        No medical records yet.
      </div>
    </div>
  <?php endif; ?>

</div>

<script>
function toggleRecord(id) {
  const el = document.getElementById('record-' + id);
  const arrow = el.previousElementSibling.querySelector('.toggle-arrow');
  const isOpen = el.style.display !== 'none';
  el.style.display = isOpen ? 'none' : 'block';
  arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
}

function copyRecordText(id) {
  const textEl = document.querySelector('#record-' + id + ' div:nth-child(2)');
  const text = textEl.textContent;
  navigator.clipboard.writeText(text);
  const btn = event.target;
  const original = btn.textContent;
  btn.textContent = '✓ Copied!';
  setTimeout(() => btn.textContent = original, 2000);
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>
