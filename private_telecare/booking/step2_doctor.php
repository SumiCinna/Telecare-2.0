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

$stmt = $conn->prepare("SELECT id, full_name, specialty, subspecialty, consultation_fee, profile_photo, is_available
                         FROM doctors WHERE department=? AND status='active'
                         ORDER BY is_available DESC, full_name ASC");
$stmt->bind_param("s", $department);
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Select Doctor — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
?>
<style>
.doctor-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}
.doctor-card{background:#fff;border:1px solid rgba(36,68,65,0.08);border-radius:16px;padding:1.2rem;display:flex;flex-direction:column;gap:0.5rem}
.doctor-avatar{width:56px;height:56px;border-radius:14px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;overflow:hidden}
.doctor-avatar img{width:100%;height:100%;object-fit:cover}
.doctor-name{font-weight:700;font-size:1rem;color:var(--green)}
.doctor-spec{font-size:0.8rem;color:var(--red);font-weight:600}
.doctor-rating{font-size:0.76rem;color:var(--muted)}
.doctor-meta{display:flex;justify-content:space-between;font-size:0.8rem;color:var(--green);margin:0.4rem 0 0.6rem;font-weight:600}
.avail-pill{font-size:0.66rem;font-weight:700;padding:0.2rem 0.55rem;border-radius:50px;background:rgba(34,197,94,0.12);color:#16a34a}
.avail-pill.off{background:rgba(0,0,0,0.06);color:#888}
.empty-state{grid-column:1/-1;text-align:center;padding:2.5rem;color:var(--muted);font-size:0.88rem}
@media(max-width:700px){.doctor-grid{grid-template-columns:1fr}}
</style>

<div class="wiz-page">
  <div class="wiz-title">Select your Healthcare Provider</div>
  <div class="wiz-sub">Choose from our team in <strong><?= htmlspecialchars($department) ?></strong>.</div>

  <?php render_stepper(2); ?>

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
      <div class="doctor-rating">Rated after consultation &mdash; no reviews yet</div>
      <div class="doctor-meta">
        <span>&#8369;<?= number_format((float)$dr['consultation_fee'], 2) ?></span>
        <span class="avail-pill <?= $dr['is_available'] ? '' : 'off' ?>"><?= $dr['is_available'] ? 'Available today' : 'By schedule' ?></span>
      </div>
      <a href="router.php?page=booking/step2_doctor&doctor_id=<?= $dr['id'] ?>" class="wiz-btn primary" style="text-align:center;">Select Doctor</a>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="wiz-actions">
    <a href="router.php?page=booking/step1_details" class="wiz-btn ghost">&larr; Back</a>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>