<?php
// private_telecare/booking/step2_doctor.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department']);
$department = $_SESSION['booking']['department'];

// Selecting a doctor is a simple GET link — validate + store, then move on.
if (isset($_GET['doctor_id'])) {
    $did = (int)$_GET['doctor_id'];
    $chk = $conn->prepare("SELECT id FROM doctors WHERE id=? AND department=? AND status='active'");
    $chk->bind_param("is", $did, $department);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
        $_SESSION['booking']['doctor_id'] = $did;
        header('Location: router.php?page=booking/step3_schedule'); exit;
    }
}

$stmt = $conn->prepare("SELECT id, full_name, specialty, subspecialty, consultation_fee, profile_photo
                         FROM doctors WHERE department=? AND status='active'
                         ORDER BY full_name ASC");
$stmt->bind_param("s", $department);
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Select Doctor — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.wiz-page{max-width:1180px;padding:1rem 2rem .5rem}
.doctor-layout{display:grid;grid-template-columns:minmax(0,1fr) 270px;gap:1rem;align-items:start}
.doctor-toolbar{display:flex;align-items:center;justify-content:space-between;background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:12px;padding:.65rem .8rem;margin-bottom:1rem;color:var(--muted);font-size:.78rem}
.doctor-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:1rem;max-height:600px;overflow-y:auto;padding-right:.4rem}
.doctor-card{background:#fff;border:1px solid rgba(195,54,67,.16);border-radius:12px;padding:1rem;display:grid;grid-template-columns:44px 1fr;gap:.15rem .75rem}
.doctor-avatar{width:44px;height:44px;border-radius:50%;background:#eaf1ff;color:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;overflow:hidden;grid-row:span 4}
.doctor-avatar img{width:100%;height:100%;object-fit:cover}
.doctor-name{font-weight:700;font-size:.95rem;color:var(--green)}
.doctor-spec{font-size:.75rem;color:var(--muted);font-weight:500}
.doctor-rating{font-size:.7rem;color:#ca8a04}
.doctor-meta{grid-column:1/-1;background:#f3f6ff;border-radius:8px;padding:.55rem .7rem;font-size:.76rem;color:var(--green);margin:.5rem 0 .1rem;font-weight:600}
.doctor-card .wiz-btn{grid-column:1/-1;width:100%;padding:.6rem 1rem;font-size:.76rem;border-radius:6px}
.doctor-summary{background:#fff;border:1px solid rgba(36,68,65,.1);border-radius:12px;padding:1rem;font-size:.78rem;color:var(--green);position:sticky;top:1rem}
.doctor-summary h3{font-family:'DM Sans',sans-serif;font-size:.9rem;margin-bottom:.8rem;color:var(--green)}
.summary-line{display:flex;justify-content:space-between;gap:.6rem;padding:.55rem 0;border-top:1px solid rgba(36,68,65,.08)}
.summary-line span:first-child{color:var(--muted)}
.summary-line span:last-child{text-align:right;font-weight:700}
.empty-state{grid-column:1/-1;text-align:center;padding:2.5rem;color:var(--muted);font-size:0.88rem}
@media(max-width:800px){.doctor-layout{grid-template-columns:1fr}.doctor-summary{order:-1;position:static}}
@media(max-width:560px){.doctor-grid{grid-template-columns:1fr;max-height:none;overflow-y:visible}}
</style>

<div class="wiz-page">
  <div class="wiz-title">Select your Healthcare Provider</div>
  <div class="wiz-sub">Choose from our team in <strong><?= htmlspecialchars($department) ?></strong>.</div>

  <?php render_stepper(2); ?>

  <div class="doctor-layout">
  <div>
  <div class="doctor-toolbar"><span>Available healthcare providers</span><span><?= count($doctors) ?> provider<?= count($doctors) === 1 ? '' : 's' ?></span></div>
  <div class="doctor-grid">
    <?php if (!$doctors): ?>
      <div class="empty-state">No doctors are currently listed under this department.<br/><a href="router.php?page=booking/step1_details">Choose another department</a>.</div>
    <?php endif; ?>
    <?php foreach ($doctors as $dr):
      $initials = strtoupper(substr($dr['full_name'],0,1).(strpos($dr['full_name'],' ')!==false ? substr($dr['full_name'],strpos($dr['full_name'],' ')+1,1) : ''));
    ?>
    <div class="doctor-card">
      <div class="doctor-avatar">
        <?php if (!empty($dr['profile_photo'])): ?><img src="../../<?= htmlspecialchars($dr['profile_photo']) ?>"/><?php else: echo $initials; endif; ?>
      </div>
      <div class="doctor-name">Dr. <?= htmlspecialchars($dr['full_name']) ?></div>
      <div class="doctor-spec"><?= htmlspecialchars($dr['specialty'] ?: $department) ?><?= $dr['subspecialty'] ? ' &middot; ' . htmlspecialchars($dr['subspecialty']) : '' ?></div>
      <div class="doctor-rating">Consultation provider</div>
      <div class="doctor-meta">
        <span>&#8369;<?= number_format((float)$dr['consultation_fee'], 2) ?></span>
      </div>
      <a href="router.php?page=booking/step2_doctor&doctor_id=<?= $dr['id'] ?>" class="wiz-btn primary" style="text-align:center;">Select Doctor</a>
    </div>
    <?php endforeach; ?>
  </div>
  </div>
  <aside class="doctor-summary">
    <h3>Appointment Summary</h3>
    <div class="summary-line"><span>Department</span><span><?= htmlspecialchars($department) ?></span></div>
    <div class="summary-line"><span>Doctor</span><span>Pending Selection</span></div>
    <div class="summary-line"><span>Date &amp; Time</span><span>Pending Selection</span></div>
  </aside>
  </div>

  <div class="wiz-actions">
    <a href="router.php?page=booking/step1_details" class="wiz-btn ghost">&larr; Back</a>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>