<?php
// guest_payment.php  (site root)
// Step after the e-mail code is verified: 15-minute payment window with PAY NOW / CANCEL REQUEST.
// PAY NOW hands off to private_telecare/pay.php (GCash, PhilHealth YAKAP, HMO) exactly like a logged-in patient.
date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/includes/guest_booking.php';

$appt_id = (int)($_GET['appt_id'] ?? ($_SESSION['gb_appt_id'] ?? 0));
$pid     = (int)($_SESSION['patient_id'] ?? 0);
if (!$appt_id || !$pid || empty($_SESSION['guest_mode'])) { header('Location: index.php#book'); exit; }

guest_ensure_schema($conn);
guest_expire_stale($conn);

$st = $conn->prepare("SELECT a.id, a.status, a.payment_status, a.appointment_date, a.appointment_time, a.reference_no,
                             d.full_name AS doctor_name, d.specialty, d.consultation_fee,
                             TIMESTAMPDIFF(SECOND, NOW(), a.created_at + INTERVAL " . (int)GUEST_PAY_WINDOW_MIN . " MINUTE) AS left_s
                      FROM appointments a
                      JOIN doctors d ON d.id = a.doctor_id
                      JOIN guest_bookings g ON g.appointment_id = a.id AND g.patient_id = a.patient_id
                      WHERE a.id = ? AND a.patient_id = ?");
$st->bind_param('ii', $appt_id, $pid); $st->execute();
$a = $st->get_result()->fetch_assoc();
if (!$a) { header('Location: index.php#book'); exit; }

if ($a['payment_status'] === 'Paid' || $a['status'] === 'Confirmed') {
    header('Location: router.php?page=booking/success&appt_id=' . $appt_id); exit;
}

$expired = ($a['status'] === 'Cancelled') || (int)$a['left_s'] <= 0;
$left    = $expired ? 0 : (int)$a['left_s'];
$total   = max(1, (int)GUEST_PAY_WINDOW_MIN) * 60;
$fee     = (float)$a['consultation_fee'];
$when    = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
$csrf    = $_SESSION['gb_csrf'] ?? ($_SESSION['gb_csrf'] = bin2hex(random_bytes(32)));
$e       = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Complete Your Payment | TELE-CARE</title>
  <link rel="icon" type="image/png" href="telecarelogo.png">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    :root{--blue:#1f6fc6;--blue-dark:#193f72;--blue-soft:#d9ecff;--ink:#474748;--muted:#64748b;--line:#e2e8f0;--red:#d92d3f;--green:#26ae83;--page:#fafafa}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Poppins',sans-serif;background:var(--page);color:var(--ink);min-height:100vh;display:flex;flex-direction:column}
    .top{background:#fff;border-bottom:1px solid var(--line);box-shadow:0 2px 16px rgba(33,102,177,.08)}
    .top-in{max-width:760px;margin:0 auto;padding:0 20px;min-height:62px;display:flex;align-items:center;gap:.5rem}
    .top a{display:flex;align-items:center;gap:.45rem;text-decoration:none;color:var(--blue-dark);font-weight:800;font-size:1.05rem;letter-spacing:-.03em}
    .top img{width:36px;height:36px;object-fit:contain}
    main{flex:1;display:grid;place-items:start center;padding:32px 16px 48px}
    .card{width:100%;max-width:440px;background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 44px rgba(25,63,114,.10);padding:30px 26px;text-align:center}
    .ring{position:relative;width:176px;height:176px;margin:0 auto 18px}
    .ring svg{width:100%;height:100%;transform:rotate(-90deg)}
    .ring .bg{fill:none;stroke:#e8f1fb;stroke-width:10}
    .ring .fg{fill:none;stroke:var(--blue);stroke-width:10;stroke-linecap:round;transition:stroke-dashoffset .9s linear,stroke .3s}
    .ring.low .fg{stroke:var(--red)}
    .ring .mid{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
    .ring .time{font-size:2.1rem;font-weight:800;color:var(--blue-dark);letter-spacing:-.02em;font-variant-numeric:tabular-nums}
    .ring.low .time{color:var(--red)}
    .ring .lbl{font-size:.64rem;font-weight:700;letter-spacing:.12em;color:var(--muted)}
    h1{font-size:1.25rem;font-weight:800;color:var(--blue-dark);letter-spacing:-.03em;margin-bottom:6px}
    .sub{font-size:.82rem;color:var(--muted);line-height:1.6;margin-bottom:18px}
    .sub b{color:var(--blue-dark)}
    .rows{border-top:1px solid var(--line);margin:0 0 20px;text-align:left}
    .row{display:flex;justify-content:space-between;gap:14px;padding:11px 0;border-bottom:1px solid var(--line);font-size:.8rem}
    .row span:first-child{color:var(--muted)}
    .row span:last-child{font-weight:600;color:var(--blue-dark);text-align:right}
    .btn{display:block;width:100%;padding:13px 18px;border:0;border-radius:999px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;text-decoration:none;text-align:center}
    .btn-pay{background:var(--blue);color:#fff;box-shadow:0 8px 20px rgba(31,111,198,.28)}
    .btn-pay:hover{background:var(--blue-dark)}
    .btn-pay[disabled],.btn-pay.off{background:#cbd5e1;box-shadow:none;pointer-events:none}
    .btn-cancel{margin-top:10px;background:none;color:var(--muted);font-size:.78rem;font-weight:700;letter-spacing:.04em;padding:10px}
    .btn-cancel:hover{color:var(--red)}
    .expired .ring .fg{stroke:#cbd5e1}
    .toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#1f2937;color:#fff;padding:10px 16px;border-radius:12px;font-size:.8rem;display:none}
  </style>
</head>
<body>
<header class="top"><div class="top-in"><a href="index.php"><img src="telecarelogo.png" alt="">TELE-CARE</a></div></header>

<main>
  <div class="card<?= $expired ? ' expired' : '' ?>" id="card">
    <div class="ring<?= (!$expired && $left <= 120) ? ' low' : '' ?>" id="ring">
      <svg viewBox="0 0 120 120"><circle class="bg" cx="60" cy="60" r="52"/><circle class="fg" id="fg" cx="60" cy="60" r="52" stroke-dasharray="326.7" stroke-dashoffset="0"/></svg>
      <div class="mid"><div class="time" id="time"><?= sprintf('%02d:%02d', intdiv($left, 60), $left % 60) ?></div><div class="lbl">TIME LEFT</div></div>
    </div>

    <?php if ($expired): ?>
      <h1>Time is up</h1>
      <p class="sub">Your reserved time slot was released. You can book again anytime.</p>
      <a class="btn btn-pay" href="index.php#book">Book again</a>
    <?php else: ?>
      <h1>Complete Your Payment</h1>
      <p class="sub">To continue with your booking, please complete your payment of <b>&#8369;<?= number_format($fee, 2) ?></b> by pressing the button below.</p>

      <div class="rows">
        <div class="row"><span>Doctor</span><span>Dr. <?= $e($a['doctor_name']) ?></span></div>
        <div class="row"><span>Schedule</span><span><?= $e(date('M j, Y', $when)) ?> &middot; <?= $e(date('g:i A', $when)) ?></span></div>
        <div class="row"><span>Reference</span><span><?= $e($a['reference_no']) ?></span></div>
      </div>

      <a class="btn btn-pay" id="payBtn" href="router.php?page=pay&amp;appt_id=<?= $appt_id ?>">PAY NOW</a>
      <button class="btn btn-cancel" id="cancelBtn" type="button">CANCEL REQUEST</button>
    <?php endif; ?>
  </div>
</main>
<div class="toast" id="toast"></div>

<script>
(function () {
  var TOTAL = <?= (int)$total ?>, left = <?= (int)$left ?>, EXPIRED = <?= $expired ? 'true' : 'false' ?>;
  var APPT = <?= (int)$appt_id ?>, CSRF = <?= json_encode($csrf) ?>;
  var C = 2 * Math.PI * 52;
  var fg = document.getElementById('fg'), time = document.getElementById('time'), ring = document.getElementById('ring');
  var t0 = Date.now(), base = left;

  function toast(m) { var t = document.getElementById('toast'); t.textContent = m; t.style.display = 'block'; setTimeout(function () { t.style.display = 'none'; }, 4000); }
  function post(action, extra) {
    var b = new URLSearchParams(Object.assign({ action: action, csrf: CSRF }, extra || {}));
    return fetch('guest_booking_api.php', { method: 'POST', body: b, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function draw() {
    fg.style.strokeDasharray = C;
    fg.style.strokeDashoffset = C * (1 - Math.max(0, Math.min(1, left / TOTAL)));
    time.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
    ring.classList.toggle('low', left <= 120 && left > 0);
  }
  draw();
  if (EXPIRED) return;

  var iv = setInterval(function () {
    left = Math.max(0, base - Math.round((Date.now() - t0) / 1000));
    draw();
    if (left <= 0) { clearInterval(iv); post('cancel', { appt_id: APPT }).finally(function () { location.reload(); }); }
  }, 1000);

  document.getElementById('cancelBtn').addEventListener('click', function () {
    if (!confirm('Cancel this booking request?')) return;
    this.disabled = true;
    post('cancel', { appt_id: APPT }).then(function (r) { location.href = (r && r.redirect) || 'index.php#book'; })
      .catch(function () { toast('Could not cancel. Please try again.'); });
  });
})();
</script>
</body>
</html>
