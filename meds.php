<?php
require_once 'includes/auth.php';

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

$meds = $conn->query("
    SELECT p.*, d.full_name AS doctor_name
    FROM prescriptions p JOIN doctors d ON d.id = p.doctor_id
    WHERE p.patient_id=$patient_id AND p.status='Active'
    ORDER BY p.prescribed_date DESC
");

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
require_once 'includes/header.php';
?>

<div class="page">

  <!-- Header row with scan button -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;">
    <h2 style="font-size:1.5rem;margin:0;">My Prescriptions</h2>
    <a href="ocr/scan.php" style="
      display:inline-flex;align-items:center;gap:0.4rem;
      background:var(--blue);color:#fff;
      padding:0.55rem 1.1rem;border-radius:50px;
      font-size:0.82rem;font-weight:700;text-decoration:none;
      box-shadow:0 4px 14px rgba(63,130,227,0.3);
      transition:all 0.2s;
    ">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0"/>
      </svg>
      Scan Prescription
    </a>
  </div>

  <!-- ── DOCTOR-ISSUED PRESCRIPTIONS ── -->
  <?php
  $has = false;
  if ($meds && $meds->num_rows > 0):
    while ($m = $meds->fetch_assoc()):
      $has = true;
  ?>
  <div class="card">
    <div style="display:flex;align-items:flex-start;gap:1rem;">
      <div style="width:44px;height:44px;border-radius:12px;flex-shrink:0;background:var(--blue-light);display:flex;align-items:center;justify-content:center;font-size:1.3rem;">💊</div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:1rem;margin-bottom:0.3rem;"><?= htmlspecialchars($m['medication_name']) ?></div>
        <div style="font-size:0.82rem;color:#9ab0ae;margin-bottom:0.5rem;">
          <?= htmlspecialchars($m['dosage'] ?? '—') ?> &nbsp;·&nbsp; <?= htmlspecialchars($m['frequency'] ?? '—') ?>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
          <span class="badge badge-blue">Refills: <?= $m['refills_remaining'] ?></span>
          <?php if (!empty($m['expiry_date'])): ?>
          <span class="badge badge-orange">Expires: <?= date('M d, Y', strtotime($m['expiry_date'])) ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($m['notes'])): ?>
        <div style="margin-top:0.7rem;font-size:0.82rem;color:#6b8a87;background:rgba(36,68,65,0.05);border-radius:10px;padding:0.6rem 0.8rem;">
          📝 <?= htmlspecialchars($m['notes']) ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:0.7rem;font-size:0.75rem;color:#9ab0ae;">
          Prescribed by Dr. <?= htmlspecialchars($m['doctor_name']) ?> on <?= date('M d, Y', strtotime($m['prescribed_date'])) ?>
        </div>
      </div>
    </div>
    <?php if ($m['refills_remaining'] == 0): ?>
    <div style="margin-top:0.9rem;padding-top:0.9rem;border-top:1px solid rgba(36,68,65,0.08);font-size:0.82rem;color:var(--red);">
      ⚠️ No refills remaining — <a href="chat.php" style="color:var(--red);font-weight:600;">message your doctor</a> to request more.
    </div>
    <?php endif; ?>
  </div>
  <?php endwhile; endif; ?>

  <?php if (!$has): ?>
  <div class="card">
    <div class="empty-state">
      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
      </svg>
      No active prescriptions.
    </div>
  </div>
  <?php endif; ?>

  <!-- ── SCANNED DOCUMENTS ── -->
  <?php if ($scanned && $scanned->num_rows > 0): ?>
  <div style="margin-top:1.8rem;">
    <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#9ab0ae;border-bottom:1px solid rgba(63,130,227,0.1);padding-bottom:0.5rem;margin-bottom:1rem;">
      📷 Scanned Documents
    </div>

    <?php while ($s = $scanned->fetch_assoc()): ?>
    <?php
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
    ?>
    <div class="card" style="border:1px solid rgba(244,132,95,0.15);">
      <div style="display:flex;align-items:flex-start;gap:1rem;">
        <!-- Thumbnail -->
        <?php $ext = strtolower(pathinfo($s['file_path'], PATHINFO_EXTENSION)); ?>
        <?php if ($ext === 'pdf'): ?>
          <div onclick="toggleScanned(<?= $s['id'] ?>)" style="width:52px;height:52px;border-radius:12px;background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.15);flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;gap:1px;">
            <span style="font-size:1.4rem;line-height:1;">📄</span>
            <span style="font-size:0.55rem;font-weight:700;color:#C33643;letter-spacing:0.04em;">PDF</span>
          </div>
        <?php else: ?>
          <img src="<?= htmlspecialchars($s['file_path']) ?>"
               style="width:52px;height:52px;border-radius:12px;object-fit:cover;border:1px solid rgba(63,130,227,0.1);flex-shrink:0;cursor:pointer;"
               onclick="toggleScanned(<?= $s['id'] ?>)"/>
        <?php endif; ?>
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.3rem;">
            <div style="font-weight:700;font-size:0.95rem;"><?= htmlspecialchars($s['doc_label'] ?: 'Scanned Prescription') ?></div>
            <span style="background:rgba(244,132,95,0.1);color:#f4845f;border-radius:50px;padding:0.15rem 0.6rem;font-size:0.68rem;font-weight:700;">Scanned</span>
            <span style="<?= $type_style ?>border-radius:50px;padding:0.15rem 0.6rem;font-size:0.68rem;font-weight:700;"><?= htmlspecialchars($type_label) ?></span>
          </div>
          <div style="font-size:0.75rem;color:#9ab0ae;">
            📅 <?= date('M d, Y — g:i A', strtotime($s['uploaded_at'])) ?>
          </div>
          <!-- Extracted text -->
          <div id="scanned-<?= $s['id'] ?>" style="display:none;margin-top:0.8rem;">
            <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;margin-bottom:0.5rem;">Extracted Text</div>
            <div style="background:rgba(63,130,227,0.04);border:1px solid rgba(63,130,227,0.1);border-radius:10px;padding:0.9rem;font-size:0.82rem;line-height:2;color:var(--text);max-height:220px;overflow-y:auto;font-family:'DM Sans',sans-serif;word-break:break-word;">
              <?= formatOcrText($s['extracted_text'] ?? '', $s['doc_type']) ?>
            </div>
            <!-- Legend -->
            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-top:0.6rem;">
              <span style="font-size:0.65rem;background:rgba(63,130,227,0.12);color:#1a4fa8;padding:0.15rem 0.5rem;border-radius:50px;font-weight:700;">💊 Medicine</span>
              <span style="font-size:0.65rem;background:rgba(244,132,95,0.12);color:#c05621;padding:0.15rem 0.5rem;border-radius:50px;font-weight:700;">⚡ Dosage/Freq</span>
              <span style="font-size:0.65rem;background:rgba(168,85,247,0.12);color:#6d28d9;padding:0.15rem 0.5rem;border-radius:50px;font-weight:700;">⚠️ Important</span>
            </div>
            <div style="display:flex;gap:0.6rem;margin-top:0.7rem;">
              <button onclick="copyScanned(<?= $s['id'] ?>)" style="flex:1;padding:0.5rem;border-radius:50px;background:var(--blue-light);color:var(--blue);border:none;font-weight:700;font-size:0.78rem;cursor:pointer;font-family:'DM Sans',sans-serif;" id="copy-<?= $s['id'] ?>">
                Copy Text
              </button>
              <a href="ocr/scan.php" style="flex:1;padding:0.5rem;border-radius:50px;background:rgba(244,132,95,0.1);color:#f4845f;font-weight:700;font-size:0.78rem;text-decoration:none;text-align:center;display:block;">
                Scan New
              </a>
            </div>
          </div>
        </div>
        <!-- Toggle arrow -->
        <button onclick="toggleScanned(<?= $s['id'] ?>)" style="background:none;border:none;cursor:pointer;color:#9ab0ae;padding:0;flex-shrink:0;" id="arrow-<?= $s['id'] ?>">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </button>
      </div>
    </div>
    <?php endwhile; ?>

    <?php if ($scan_total_pages > 1): ?>
    <div style="display:flex;justify-content:flex-end;gap:0.45rem;flex-wrap:wrap;margin-top:0.8rem;">
      <?php if ($scan_page > 1): ?>
        <a href="?scan_page=<?= $scan_page - 1 ?>" style="padding:0.35rem 0.7rem;border-radius:8px;background:var(--blue-light);color:var(--blue);font-size:0.76rem;font-weight:700;text-decoration:none;">Prev</a>
      <?php endif; ?>

      <?php for ($i = 1; $i <= $scan_total_pages; $i++): ?>
        <a href="?scan_page=<?= $i ?>" style="padding:0.35rem 0.62rem;border-radius:8px;font-size:0.76rem;font-weight:700;text-decoration:none;<?= $i === $scan_page ? 'background:var(--blue);color:#fff;' : 'background:rgba(63,130,227,0.08);color:var(--blue);' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>

      <?php if ($scan_page < $scan_total_pages): ?>
        <a href="?scan_page=<?= $scan_page + 1 ?>" style="padding:0.35rem 0.7rem;border-radius:8px;background:var(--blue-light);color:var(--blue);font-size:0.76rem;font-weight:700;text-decoration:none;">Next</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

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

function copyScanned(id) {
  const text = document.getElementById('scanned-' + id).querySelector('div').textContent.trim();
  navigator.clipboard.writeText(text);
  const btn = document.getElementById('copy-' + id);
  btn.textContent = '✓ Copied!';
  setTimeout(() => btn.textContent = 'Copy Text', 2000);
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>