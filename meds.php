<?php
require_once 'includes/auth.php';

$meds = $conn->query("
    SELECT p.*, d.full_name AS doctor_name
    FROM prescriptions p JOIN doctors d ON d.id = p.doctor_id
    WHERE p.patient_id=$patient_id AND p.status='Active'
    ORDER BY p.prescribed_date DESC
");

$page_title = 'My Prescriptions — TELE-CARE';
$active_nav = 'meds';
require_once 'includes/header.php';
?>

<div class="page">
  <h2 style="font-size:1.5rem;margin-bottom:1.2rem;">My Prescriptions</h2>

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
</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>