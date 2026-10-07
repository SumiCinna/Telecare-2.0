<?php
// private_telecare/booking/step4_payment_method.php
// Step 4 of the booking wizard: how will the patient pay? (Regular / YAKAP / HMO)
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/booking_helpers.php';

booking_require(['department', 'doctor_id', 'appt_date', 'appt_time']);

$methods = booking_payment_methods();
$selected = $_SESSION['booking']['payment_method'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $choice = $_POST['payment_method'] ?? '';
    if (!isset($methods[$choice])) {
        $error = 'Please choose how you want to pay.';
    } else {
        // Switching method discards details entered for a different one.
        if (($_SESSION['booking']['payment_method'] ?? '') !== $choice) {
            unset($_SESSION['booking']['coverage']);
        }
        $_SESSION['booking']['payment_method'] = $choice;

        if ($choice === 'YAKAP') { header('Location: router.php?page=booking/step4_yakap'); exit; }
        if ($choice === 'HMO')   { header('Location: router.php?page=booking/step4_hmo');   exit; }
        header('Location: router.php?page=booking/step4_review'); exit;
    }
}

$page_title = 'Payment Method — TELE-CARE';
$active_nav = 'visits';
require_once __DIR__ . '/../../includes/header.php';
echo booking_wizard_css();
echo booking_form_css();
?>
<div class="wiz-page">
  <div class="wiz-title">Select Payment Method</div>
  <div class="wiz-sub">Choose how you will pay for your consultation.</div>

  <?php render_stepper(4); ?>

  <?php if ($error): ?><div class="wiz-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" id="methodForm">
    <div class="method-list">
      <?php foreach ($methods as $key => $m): ?>
        <label class="method-card <?= $selected === $key ? 'selected' : '' ?>">
          <input type="radio" name="payment_method" value="<?= htmlspecialchars($key) ?>" <?= $selected === $key ? 'checked' : '' ?> required>
          <span class="method-icon"><?= booking_method_icon($m['icon']) ?></span>
          <span>
            <strong><?= htmlspecialchars($m['label']) ?></strong>
            <small><?= htmlspecialchars($m['desc']) ?></small>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="wiz-actions">
      <a href="router.php?page=booking/step3_schedule" class="wiz-btn ghost">Back</a>
      <button type="submit" class="wiz-btn primary">Next</button>
    </div>
  </form>
</div>

<script>
document.querySelectorAll('.method-card input').forEach(function (r) {
  r.addEventListener('change', function () {
    document.querySelectorAll('.method-card').forEach(function (c) { c.classList.remove('selected'); });
    r.closest('.method-card').classList.add('selected');
  });
});
</script>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>
