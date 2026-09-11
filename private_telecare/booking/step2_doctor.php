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

$stmt = $conn->prepare("SELECT id, full_name, specialty, subspecialty, consultation_fee, profile_photo, rating, rating_count
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
.doctor-rating{font-size:.7rem;color:#ca8a04;display:flex;align-items:center;gap:.3rem;flex-wrap:wrap}
.doctor-rating .stars{letter-spacing:1px}
.doctor-rating .rating-num{font-weight:700;color:#b8860b}
.doctor-rating .rating-count{color:var(--muted);font-weight:500}
.doctor-rating .no-rating{color:var(--muted);font-style:italic}
.see-reviews-link{background:none;border:none;padding:0;font:inherit;font-size:.68rem;color:var(--blue);text-decoration:underline;cursor:pointer}
/* ── Reviews modal ── */
.reviews-modal-backdrop{display:none;position:fixed;inset:0;background:rgba(15,25,24,.55);z-index:1000;align-items:center;justify-content:center;padding:1rem}
.reviews-modal-backdrop.open{display:flex}
.reviews-modal{background:#fff;border-radius:14px;max-width:480px;width:100%;max-height:85vh;overflow-y:auto;padding:1.25rem 1.4rem}
.reviews-modal h3{font-family:'DM Sans',sans-serif;color:var(--green);margin:0 0 .9rem;font-size:1.02rem;display:flex;justify-content:space-between;align-items:center}
.reviews-modal h3 button{background:none;border:none;font-size:1.1rem;cursor:pointer;color:var(--muted)}
.reviews-summary{display:flex;gap:1rem;align-items:center;padding-bottom:1rem;margin-bottom:1rem;border-bottom:1px solid rgba(36,68,65,.1)}
.reviews-summary .avg-num{font-size:2rem;font-weight:800;color:var(--green);font-family:'DM Sans',sans-serif;line-height:1}
.reviews-summary .avg-stars{color:#ca8a04;font-size:.9rem;margin:.15rem 0}
.reviews-summary .avg-count{font-size:.7rem;color:var(--muted)}
.reviews-bars{flex:1;display:grid;gap:.2rem}
.reviews-bar-row{display:flex;align-items:center;gap:.4rem;font-size:.65rem;color:var(--muted)}
.reviews-bar-track{flex:1;height:6px;background:#eee;border-radius:4px;overflow:hidden}
.reviews-bar-fill{height:100%;background:#ca8a04;border-radius:4px}
.review-item{padding:.7rem 0;border-bottom:1px solid rgba(36,68,65,.07)}
.review-item:last-child{border-bottom:none}
.review-item-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.2rem}
.review-item-name{font-weight:700;font-size:.78rem;color:var(--green)}
.review-item-date{font-size:.65rem;color:var(--muted)}
.review-item-stars{color:#ca8a04;font-size:.72rem;margin-bottom:.25rem}
.review-item-comment{font-size:.76rem;color:#3b4a48;line-height:1.4}
.reviews-empty{text-align:center;padding:1.5rem;color:var(--muted);font-size:.8rem}
.reviews-loading{text-align:center;padding:1.5rem;color:var(--muted);font-size:.8rem}
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
      <div class="doctor-rating">
        <?php if ((int)$dr['rating_count'] > 0):
          $full = (int)floor((float)$dr['rating']);
          $half = ((float)$dr['rating'] - $full) >= 0.5;
        ?>
          <span class="stars">
            <?= str_repeat('&#9733;', $full) ?><?= $half ? '&#189;' : '' ?><?= str_repeat('&#9734;', 5 - $full - ($half ? 1 : 0)) ?>
          </span>
          <span class="rating-num"><?= number_format((float)$dr['rating'], 1) ?></span>
          <span class="rating-count">(<?= (int)$dr['rating_count'] ?>)</span>
          &middot; <button type="button" class="see-reviews-link" onclick="openReviews(<?= $dr['id'] ?>)">See reviews</button>
        <?php else: ?>
          <span class="no-rating">No ratings yet</span>
        <?php endif; ?>
      </div>
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
    <a href="router.php?page=booking/step1_details" class="wiz-btn ghost">Back</a>
  </div>
</div>

<!-- ══ Reviews modal ══ -->
<div class="reviews-modal-backdrop" id="reviewsBackdrop" onclick="if(event.target===this) closeReviews()">
  <div class="reviews-modal">
    <h3><span id="reviewsDoctorName">Reviews</span> <button type="button" onclick="closeReviews()">&times;</button></h3>
    <div id="reviewsBody"><div class="reviews-loading">Loading reviews…</div></div>
  </div>
</div>

<script>
function starString(n) {
  n = Math.round(n);
  return '&#9733;'.repeat(n) + '&#9734;'.repeat(5 - n);
}
function openReviews(doctorId) {
  const backdrop = document.getElementById('reviewsBackdrop');
  const body = document.getElementById('reviewsBody');
  document.getElementById('reviewsDoctorName').textContent = 'Reviews';
  body.innerHTML = '<div class="reviews-loading">Loading reviews…</div>';
  backdrop.classList.add('open');

  fetch('router.php?page=doctor_reviews&doctor_id=' + doctorId)
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { body.innerHTML = '<div class="reviews-empty">Could not load reviews.</div>'; return; }

      document.getElementById('reviewsDoctorName').textContent = 'Dr. ' + data.doctor_name;

      const total = data.rating_count || 0;
      let html = '<div class="reviews-summary">';
      html += '<div><div class="avg-num">' + (total ? data.avg_rating.toFixed(1) : '—') + '</div>';
      html += '<div class="avg-stars">' + starString(data.avg_rating) + '</div>';
      html += '<div class="avg-count">' + total + ' rating' + (total === 1 ? '' : 's') + '</div></div>';
      html += '<div class="reviews-bars">';
      [5,4,3,2,1].forEach(star => {
        const count = data.breakdown[star] || 0;
        const pct = total ? Math.round((count / total) * 100) : 0;
        html += '<div class="reviews-bar-row"><span>' + star + '&#9733;</span><div class="reviews-bar-track"><div class="reviews-bar-fill" style="width:' + pct + '%"></div></div><span>' + count + '</span></div>';
      });
      html += '</div></div>';

      if (data.reviews.length) {
        data.reviews.forEach(rv => {
          html += '<div class="review-item">';
          html += '<div class="review-item-top"><span class="review-item-name">' + escapeHtml(rv.name) + '</span><span class="review-item-date">' + rv.date + '</span></div>';
          html += '<div class="review-item-stars">' + starString(rv.rating) + '</div>';
          html += '<div class="review-item-comment">' + escapeHtml(rv.comment) + '</div>';
          html += '</div>';
        });
      } else {
        html += '<div class="reviews-empty">No written reviews yet.</div>';
      }

      body.innerHTML = html;
    })
    .catch(() => { body.innerHTML = '<div class="reviews-empty">Could not load reviews.</div>'; });
}
function closeReviews() {
  document.getElementById('reviewsBackdrop').classList.remove('open');
}
function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../../includes/nav.php'; ?>
</body>
</html>