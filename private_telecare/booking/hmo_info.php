<?php
// private_telecare/booking/hmo_info.php — Page 1: HMO information
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

$provMap = booking_hmo_providers($conn);
$holds   = booking_hmo_holds($conn);
// Teleconsultation coverage the clinic has confirmed (Admin > HMO > Coverage), by provider name.
$cov = [];
try {
    $r = $conn->query("SELECT p.name, c.description, c.coverage_type, c.coverage_value, c.notes
                       FROM hmo_coverage c JOIN hmo_providers p ON p.id = c.provider_id WHERE p.status = 'Active' ORDER BY c.id");
    while ($r && ($row = $r->fetch_assoc())) $cov[$row['name']][] = $row;
} catch (Throwable $ex) { /* no coverage table: skip */ }
$docs = [];
try {
    $r = $conn->query("SELECT full_name, department, clinic_name FROM doctors WHERE status='active' ORDER BY department, full_name LIMIT 60");
    while ($r && ($row = $r->fetch_assoc())) $docs[] = $row;
} catch (Throwable $ex) {}
$e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);

$page_title = 'Book with HMO — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
require_once __DIR__ . '/hmo_patient_helpers.php';
echo hmo_patient_css();
?>
<div class="hp-wrap">
  <div class="wiz-title">Book with your HMO</div>
  <div class="wiz-sub">What to know before you use your HMO for a teleconsultation.</div>

  <div class="hp-card">
    <h3>How it works</h3>
    <ol class="hp-muted" style="margin:0 0 0 1.1rem;padding:0">
      <li>Tell us your concern, pick a doctor and a schedule.</li>
      <li>Choose <b>HMO</b> as the payment method and enter your member details.</li>
      <li>Upload your HMO card and any LOA.</li>
      <li>The clinic checks your coverage. <b>If you already have an LOA</b>, the clinic only has to accept it. <b>If you don't</b>, the clinic asks your HMO for approval.</li>
      <li>Your appointment is confirmed once the HMO or LOA is approved. You are notified of every change.</li>
    </ol>
    <div class="hp-actions"><a class="wiz-btn primary" href="router.php?page=booking/hmo_start">Use HMO to Book</a></div>
  </div>

  <div class="hp-card">
    <h3>What you need</h3>
    <ul class="hp-muted" style="margin:0 0 0 1rem;padding:0">
      <li>HMO card or membership proof (member ID)</li>
      <li>A valid ID, if your HMO asks for it</li>
      <li>An LOA / LOG / eLOA if your HMO already issued one</li>
      <li>A doctor's request or referral, if your HMO needs one for this service</li>
    </ul>
    <p class="hp-muted" style="margin:.6rem 0 0">Some HMOs need an LOA before they pay. If you don't have one yet, tick "help me get the LOA" when you upload and the clinic will assist. Coverage is not guaranteed until approved. If it isn't approved you can fix your details, pay yourself, or cancel.</p>
  </div>

  <div class="hp-card">
    <h3>Supported HMOs (<?= count($provMap) ?>)</h3>
    <ul class="hp-list">
    <?php foreach ($provMap as $name => $lic): ?>
      <li>
        <span><b><?= $e($name) ?></b><?= $lic !== '' ? '<br/><span class="hp-muted">IC license ' . $e($lic) . '</span>' : '' ?>
        <?php if (!empty($cov[$name])): foreach ($cov[$name] as $c): ?>
          <br/><span class="hp-muted">✓ <?= $e($c['description']) ?> — <?= $c['coverage_type'] === 'Percentage' ? $e(rtrim(rtrim(number_format((float)$c['coverage_value'], 2), '0'), '.')) . '%' : '₱' . $e(number_format((float)$c['coverage_value'], 2)) ?></span>
        <?php endforeach; endif; ?></span>
        <?php if (isset($holds[$name])): ?><span class="hp-pill no">Not accepted right now</span><?php else: ?><span class="hp-pill ok">Accepted</span><?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
    <p class="hp-muted">Coverage details appear only where the clinic has confirmed them. Otherwise the HMO decides at approval. "Not accepted right now" means the clinic has paused that HMO.</p>
  </div>

  <?php if ($docs): ?>
  <div class="hp-card">
    <h3>Participating doctors &amp; clinics</h3>
    <ul class="hp-list"><?php foreach ($docs as $d): ?>
      <li><span><b>Dr. <?= $e($d['full_name']) ?></b></span><span class="hp-muted"><?= $e($d['department']) ?><?= $d['clinic_name'] ? ' · ' . $e($d['clinic_name']) : '' ?></span></li>
    <?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <div class="hp-actions"><a class="wiz-btn primary" href="router.php?page=booking/hmo_start">Use HMO to Book</a></div>
</div>
<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
