<?php
require_once 'includes/auth.php';

// ── Update appointment status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $aid     = (int)$_POST['appointment_id'];
    $status  = $_POST['status'] ?? '';
    $allowed = ['Confirmed','Completed','Cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE appointments SET status=? WHERE id=? AND doctor_id=?");
        $stmt->bind_param("sii", $status, $aid, $doctor_id);
        $stmt->execute();
    }
    header('Location: appointments.php'); exit;
}

$filter     = $_GET['filter']     ?? 'upcoming';
$patient_id = (int)($_GET['patient_id'] ?? 0);

$where = "a.doctor_id=$doctor_id";
if ($patient_id) $where .= " AND a.patient_id=$patient_id";
if ($filter === 'today')     $where .= " AND a.appointment_date=CURDATE() AND a.status IN ('Pending','Confirmed')";
elseif ($filter === 'pending')   $where .= " AND a.status='Pending'";
elseif ($filter === 'completed') $where .= " AND a.status='Completed'";
else $where .= " AND a.appointment_date >= CURDATE() AND a.status IN ('Pending','Confirmed')";

$appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.profile_photo AS patient_photo
    FROM appointments a JOIN patients p ON p.id=a.patient_id
    WHERE $where ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$page_title       = 'Schedule — TELE-CARE';
$page_title_short = 'Schedule';
$active_nav       = 'appointments';
require_once 'includes/header.php';
?>

<div class="page">

  <!-- Filter Tabs -->
  <div style="display:flex;gap:0.5rem;margin-bottom:1rem;overflow-x:auto;padding-bottom:0.2rem;">
    <?php foreach (['upcoming'=>'Upcoming','today'=>'Today','pending'=>'Pending','completed'=>'Completed'] as $k=>$v): ?>
    <a href="?filter=<?= $k ?>" style="flex-shrink:0;padding:0.45rem 1rem;border-radius:50px;font-size:0.78rem;font-weight:600;text-decoration:none;<?= $filter===$k?'background:var(--green);color:#fff;':'background:#fff;color:var(--muted);border:1px solid rgba(36,68,65,0.1);' ?>">
      <?= $v ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($appts && $appts->num_rows > 0):
    $shown_date = '';
    while ($a = $appts->fetch_assoc()):
      $appt_date = date('l, F j', strtotime($a['appointment_date']));
      if ($appt_date !== $shown_date): $shown_date = $appt_date; ?>
  <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin:1rem 0 0.5rem;padding-left:0.2rem;">
    <?= $appt_date === date('l, F j') ? '🗓 Today' : $appt_date ?>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:0.7rem;">
    <div style="display:flex;align-items:center;gap:0.9rem;margin-bottom:0.8rem;">
      <div class="pat-avatar">
        <?php if (!empty($a['patient_photo'])): ?>
          <img src="../../<?= htmlspecialchars($a['patient_photo']) ?>"/>
        <?php else: echo strtoupper(substr($a['patient_name'],0,2)); endif; ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:0.92rem;"><?= htmlspecialchars($a['patient_name']) ?></div>
        <div style="font-size:0.77rem;color:var(--muted);"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type'] ?? 'Consultation') ?></div>
      </div>
      <span class="badge <?= $a['status']==='Confirmed'?'badge-green':($a['status']==='Pending'?'badge-orange':($a['status']==='Completed'?'badge-blue':'badge-red')) ?>"><?= $a['status'] ?></span>
    </div>

    <?php if (!empty($a['notes'])): ?>
    <div style="background:rgba(36,68,65,0.05);border-radius:10px;padding:0.6rem 0.8rem;font-size:0.8rem;color:var(--green);margin-bottom:0.8rem;">
      📝 <?= htmlspecialchars($a['notes']) ?>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:0.5rem;">
      <?php if ($a['status']==='Pending'): ?>
      <form method="POST" style="flex:1;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Confirmed"/>
        <button name="update_status" style="width:100%;padding:0.55rem;border-radius:10px;background:var(--green);color:#fff;font-size:0.78rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;">Confirm</button>
      </form>
      <form method="POST" style="flex:1;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Cancelled"/>
        <button name="update_status" onclick="return confirm('Decline this appointment?')" style="width:100%;padding:0.55rem;border-radius:10px;background:rgba(195,54,67,0.1);color:var(--red);font-size:0.78rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;">Decline</button>
      </form>
      <?php elseif ($a['status']==='Confirmed'): ?>
      <form method="POST" style="flex:1;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Completed"/>
        <button name="update_status" style="width:100%;padding:0.55rem;border-radius:10px;background:rgba(34,197,94,0.1);color:#16a34a;font-size:0.78rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;">Mark Done</button>
      </form>
      <?php endif; ?>
      <a href="chat.php?patient_id=<?= $a['patient_id'] ?>" style="flex:1;display:flex;align-items:center;justify-content:center;padding:0.55rem;border-radius:10px;background:rgba(63,130,227,0.1);color:var(--blue);font-size:0.78rem;font-weight:600;text-decoration:none;">Message</a>
    </div>
  </div>

  <?php endwhile; else: ?>
  <div class="card"><div class="empty-state">
    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    No appointments found.
  </div></div>
  <?php endif; ?>

</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>