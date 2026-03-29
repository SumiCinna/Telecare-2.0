<?php
require_once 'includes/auth.php';

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT p.*,
        (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id AND doctor_id=$doctor_id) AS total_visits,
        (SELECT appointment_date FROM appointments WHERE patient_id=p.id AND doctor_id=$doctor_id ORDER BY appointment_date DESC LIMIT 1) AS last_visit,
        (SELECT COUNT(*) FROM lab_results WHERE patient_id=p.id AND doc_type='lab_result') AS lab_count,
        (SELECT COUNT(*) FROM lab_results WHERE patient_id=p.id AND doc_type='prescription') AS rx_count
    FROM patients p
    JOIN patient_doctors pd ON pd.patient_id=p.id
    WHERE pd.doctor_id=$doctor_id
";
if ($search) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (p.full_name LIKE '%$s%' OR p.email LIKE '%$s%')";
}
$sql .= " ORDER BY p.full_name ASC";
$patients = $conn->query($sql);

$page_title       = 'Patients — TELE-CARE';
$page_title_short = 'My Patients';
$active_nav       = 'patients';
require_once 'includes/header.php';
?>

<div class="page">

  <!-- Search -->
  <div style="position:relative;margin-bottom:1rem;">
    <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;stroke:#9ab0ae;" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
    <form method="GET">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search patients..." style="width:100%;padding:0.75rem 1rem 0.75rem 2.5rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:50px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--green);outline:none;background:#fff;transition:border-color 0.2s;" onfocus="this.style.borderColor='var(--blue)'" onblur="this.style.borderColor='rgba(36,68,65,0.12)'"/>
    </form>
  </div>

  <?php if ($patients && $patients->num_rows > 0): ?>
  <div class="card" style="padding:0.5rem 0;">
    <?php while ($pt = $patients->fetch_assoc()): ?>
    <div style="padding:0.9rem 1.2rem;border-bottom:1px solid rgba(36,68,65,0.06);display:flex;align-items:center;gap:0.9rem;">
      <div class="pat-avatar">
        <?php if (!empty($pt['profile_photo'])): ?>
          <img src="../<?= htmlspecialchars($pt['profile_photo']) ?>"/>
        <?php else: ?>
          <?= strtoupper(substr($pt['full_name'], 0, 2)) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;font-size:0.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($pt['full_name']) ?></div>
        <div style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($pt['email']) ?></div>
        <div style="font-size:0.72rem;color:var(--muted);margin-top:0.15rem;">
          <?= $pt['total_visits'] ?> visit<?= $pt['total_visits'] != 1 ? 's' : '' ?>
          <?php if ($pt['last_visit']): ?> · Last: <?= date('M d', strtotime($pt['last_visit'])) ?><?php endif; ?>
        </div>
        <div style="font-size:0.7rem;color:var(--muted);margin-top:0.3rem;display:flex;gap:0.6rem;">
          <?php if ($pt['lab_count'] > 0): ?>
            <span style="background:rgba(63,130,227,0.1);color:var(--blue);padding:0.2rem 0.5rem;border-radius:4px;font-weight:600;">
              🧪 <?= $pt['lab_count'] ?> Lab<?= $pt['lab_count'] != 1 ? 's' : '' ?>
            </span>
          <?php endif; ?>
          <?php if ($pt['rx_count'] > 0): ?>
            <span style="background:rgba(244,132,95,0.1);color:#f4845f;padding:0.2rem 0.5rem;border-radius:4px;font-weight:600;">
              💊 <?= $pt['rx_count'] ?> Rx
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
        <a href="appointments.php?patient_id=<?= $pt['id'] ?>" style="background:rgba(63,130,227,0.1);color:var(--blue);border-radius:10px;padding:0.35rem 0.8rem;font-size:0.73rem;font-weight:600;text-decoration:none;">Visits</a>
        <a href="patient-records.php?patient_id=<?= $pt['id'] ?>" style="background:rgba(244,132,95,0.1);color:#f4845f;border-radius:10px;padding:0.35rem 0.8rem;font-size:0.73rem;font-weight:600;text-decoration:none;">Records</a>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="empty-state">
      <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <?= $search ? 'No patients found.' : 'No patients assigned yet.' ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>