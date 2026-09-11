<?php
// doctor/reviews.php
require_once 'includes/auth.php';

// $doc already holds the full doctor row (fetched in includes/auth.php),
// so $doc['rating'] / $doc['rating_count'] are the live, trigger-kept
// average and count straight from the doctors table.
$avg_rating   = (float)($doc['rating'] ?? 0);
$rating_count = (int)($doc['rating_count'] ?? 0);

// Star breakdown (5 -> 1) for the bar chart.
$breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$bd_stmt = $conn->prepare("SELECT rating, COUNT(*) c FROM doctor_ratings WHERE doctor_id=? GROUP BY rating");
$bd_stmt->bind_param('i', $doctor_id);
$bd_stmt->execute();
$bd_res = $bd_stmt->get_result();
while ($row = $bd_res->fetch_assoc()) {
    $breakdown[(int)$row['rating']] = (int)$row['c'];
}

// Filter: All / With comments only / by star.
$filter = trim($_GET['filter'] ?? '');

$sql = "
    SELECT r.rating, r.comment, r.created_at, r.updated_at, p.full_name AS patient_name, p.profile_photo
    FROM doctor_ratings r
    JOIN patients p ON p.id = r.patient_id
    WHERE r.doctor_id = ?
";
$params = [$doctor_id];
$types  = 'i';

if ($filter === 'comments') {
    $sql .= " AND r.comment IS NOT NULL AND r.comment <> ''";
} elseif (in_array($filter, ['1','2','3','4','5'], true)) {
    $sql .= " AND r.rating = ?";
    $params[] = (int)$filter;
    $types .= 'i';
}

$sql .= " ORDER BY r.updated_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$reviews = $stmt->get_result();

function reviewerInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(substr($parts[0] ?? 'P', 0, 1) . substr(end($parts) ?: '', 0, 1));
}

$page_title = 'Reviews — TELE-CARE';
$page_title_short = 'Reviews';
$active_nav = 'reviews';

require_once 'includes/header.php';
?>

<style>
.reviews-page{width:100%;max-width:1000px;margin:auto;padding:20px 24px 90px;box-sizing:border-box}
.reviews-heading{margin-bottom:18px}
.reviews-heading h1{margin:0;color:var(--neutral-900);font-size:1.55rem}
.reviews-heading p{margin:5px 0 0;color:var(--neutral-500);font-size:.75rem}

.reviews-summary-card{display:flex;gap:2rem;flex-wrap:wrap;padding:20px;border:1px solid var(--border-color);border-radius:14px;background:#fff;box-shadow:var(--shadow-sm);margin-bottom:20px}
.summary-avg{text-align:center;min-width:140px}
.summary-avg .num{font-size:2.6rem;font-weight:800;color:var(--neutral-900);line-height:1;font-family:'Inter',sans-serif}
.summary-avg .stars{color:#ca8a04;font-size:1.1rem;margin:.4rem 0}
.summary-avg .count{font-size:.72rem;color:var(--neutral-500)}
.summary-bars{flex:1;min-width:220px;display:grid;gap:.35rem;align-content:center}
.bar-row{display:flex;align-items:center;gap:.5rem;font-size:.68rem;color:var(--neutral-600)}
.bar-row .label{width:34px;flex:none;text-align:right}
.bar-track{flex:1;height:8px;background:var(--neutral-100);border-radius:5px;overflow:hidden}
.bar-fill{height:100%;background:#ca8a04;border-radius:5px}
.bar-row .cnt{width:28px;flex:none;color:var(--neutral-500)}

.reviews-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.filter-chip{padding:6px 13px;border:1px solid var(--border-color);border-radius:20px;font-size:.68rem;font-weight:700;color:var(--neutral-600);background:#fff;text-decoration:none}
.filter-chip.active{background:var(--primary-soft);border-color:var(--primary);color:var(--primary)}

.review-card{display:flex;gap:12px;padding:14px;border:1px solid var(--border-color);border-radius:11px;background:#fff;margin-bottom:10px}
.review-avatar{width:38px;height:38px;flex:none;border-radius:50%;overflow:hidden;display:grid;place-items:center;background:var(--secondary-soft);color:var(--secondary-dark);font-weight:800;font-size:.72rem}
.review-avatar img{width:100%;height:100%;object-fit:cover}
.review-main{flex:1;min-width:0}
.review-top{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
.review-name{font-weight:800;font-size:.78rem;color:var(--neutral-900)}
.review-date{font-size:.62rem;color:var(--neutral-500)}
.review-stars{color:#ca8a04;font-size:.78rem;margin:.2rem 0}
.review-comment{font-size:.76rem;color:var(--neutral-700);line-height:1.5}
.review-comment.empty{color:var(--neutral-500);font-style:italic}

.reviews-empty{text-align:center;padding:50px;color:var(--neutral-500);border:1px solid var(--border-color);border-radius:10px;background:#fff}

@media(max-width:620px){
.reviews-page{padding:14px 12px 90px}
.reviews-summary-card{flex-direction:column;align-items:center;text-align:center}
.summary-bars{width:100%}
}
</style>

<main class="page reviews-page">

<header class="reviews-heading">
  <h1>Ratings &amp; Reviews</h1>
  <p>See how patients have rated their consultations with you.</p>
</header>

<section class="reviews-summary-card">
  <div class="summary-avg">
    <div class="num"><?= $rating_count ? number_format($avg_rating, 1) : '—' ?></div>
    <?php
      $full = (int)floor($avg_rating);
      $half = ($avg_rating - $full) >= 0.5;
    ?>
    <div class="stars"><?= str_repeat('&#9733;', $full) ?><?= $half ? '&#189;' : '' ?><?= str_repeat('&#9734;', 5 - $full - ($half ? 1 : 0)) ?></div>
    <div class="count"><?= $rating_count ?> rating<?= $rating_count === 1 ? '' : 's' ?></div>
  </div>

  <div class="summary-bars">
    <?php foreach ([5,4,3,2,1] as $star):
      $count = $breakdown[$star];
      $pct   = $rating_count ? round(($count / $rating_count) * 100) : 0;
    ?>
    <div class="bar-row">
      <span class="label"><?= $star ?> &#9733;</span>
      <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
      <span class="cnt"><?= $count ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="reviews-filters">
  <a href="reviews.php" class="filter-chip <?= $filter === '' ? 'active' : '' ?>">All</a>
  <a href="reviews.php?filter=comments" class="filter-chip <?= $filter === 'comments' ? 'active' : '' ?>">With comments</a>
  <?php foreach ([5,4,3,2,1] as $star): ?>
    <a href="reviews.php?filter=<?= $star ?>" class="filter-chip <?= $filter === (string)$star ? 'active' : '' ?>"><?= $star ?> &#9733;</a>
  <?php endforeach; ?>
</div>

<?php if ($reviews && $reviews->num_rows): ?>
  <?php while ($rv = $reviews->fetch_assoc()): ?>
  <div class="review-card">
    <div class="review-avatar">
      <?php if (!empty($rv['profile_photo'])): ?>
        <img src="../<?= htmlspecialchars($rv['profile_photo']) ?>" alt="">
      <?php else: ?>
        <?= reviewerInitials($rv['patient_name']) ?>
      <?php endif; ?>
    </div>
    <div class="review-main">
      <div class="review-top">
        <span class="review-name"><?= htmlspecialchars($rv['patient_name']) ?></span>
        <span class="review-date"><?= date('M j, Y', strtotime($rv['updated_at'])) ?></span>
      </div>
      <div class="review-stars"><?= str_repeat('&#9733;', (int)$rv['rating']) . str_repeat('&#9734;', 5 - (int)$rv['rating']) ?></div>
      <?php if (!empty($rv['comment'])): ?>
        <div class="review-comment"><?= nl2br(htmlspecialchars($rv['comment'])) ?></div>
      <?php else: ?>
        <div class="review-comment empty">No written comment.</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endwhile; ?>
<?php else: ?>
  <div class="reviews-empty">No reviews yet<?= $filter ? ' for this filter' : '' ?>.</div>
<?php endif; ?>

</main>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>
