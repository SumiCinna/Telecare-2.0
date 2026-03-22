<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

// ── Helper functions ──
function isCallActive(string $date, string $time): bool {
    $appt = strtotime($date . ' ' . $time);
    $now  = time();
    return $now >= ($appt - 900) && $now <= ($appt + 3600);
}
function getJitsiRoom(int $appt_id, string $date): string {
    return 'telecare-appt-' . $appt_id . '-' . str_replace('-', '', $date);
}

// ── Update appointment status (doctor can only mark done or cancel confirmed ones) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $aid     = (int)$_POST['appointment_id'];
    $status  = $_POST['status'] ?? '';
    $allowed = ['Completed','Cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE appointments SET status=? WHERE id=? AND doctor_id=? AND status='Confirmed'");
        $stmt->bind_param("sii", $status, $aid, $doctor_id);
        $stmt->execute();
        $_SESSION['toast'] = match($status) {
            'Completed' => 'Appointment marked as completed.',
            'Cancelled' => 'Appointment cancelled.',
            default     => 'Status updated.'
        };
    }
    header('Location: appointments.php' . (isset($_GET['filter']) ? '?filter='.$_GET['filter'] : '')); exit;
}

$filter            = $_GET['filter']     ?? 'upcoming';
$patient_id_filter = (int)($_GET['patient_id'] ?? 0);

// ── Doctor only sees Staff-confirmed appointments (Pending stays with staff) ──
$where = "a.doctor_id=$doctor_id AND a.status != 'Pending'";
if ($patient_id_filter) $where .= " AND a.patient_id=$patient_id_filter";
if ($filter === 'today')       $where .= " AND a.appointment_date=CURDATE() AND a.status='Confirmed'";
elseif ($filter === 'completed') $where .= " AND a.status='Completed'";
elseif ($filter === 'cancelled') $where .= " AND a.status='Cancelled'";
else $where .= " AND a.appointment_date >= CURDATE() AND a.status='Confirmed'";

$appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.profile_photo AS patient_photo,
           p.email AS patient_email, p.phone_number AS patient_phone
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE $where
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$page_title       = 'Schedule — TELE-CARE';
$page_title_short = 'Schedule';
$active_nav       = 'appointments';
require_once 'includes/header.php';
?>

<style>
  .join-call-btn{display:inline-flex;align-items:center;gap:0.45rem;background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;padding:0.55rem 1rem;border-radius:50px;font-size:0.78rem;font-weight:700;text-decoration:none;box-shadow:0 4px 14px rgba(22,163,74,0.35);animation:callPulse 2s ease-in-out infinite;}
  @keyframes callPulse{0%,100%{box-shadow:0 4px 14px rgba(22,163,74,0.35)}50%{box-shadow:0 4px 20px rgba(22,163,74,0.6)}}
  .call-soon{font-size:0.72rem;color:#d97706;font-weight:600;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2);border-radius:50px;padding:0.2rem 0.6rem;}margin-bottom:0.75rem;overflow:hidden;border:1.5px solid rgba(36,68,65,0.07);box-shadow:0 2px 8px rgba(0,0,0,0.04);transition:transform 0.15s,box-shadow 0.15s;}
  .appt-card:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(0,0,0,0.08);}
  .appt-card.confirmed-card{border-left:3px solid #16a34a;}
  .appt-card.completed-card{border-left:3px solid #3F82E3;}
  .appt-card.cancelled-card{border-left:3px solid #C33643;}
  .appt-card-body{padding:0.9rem 1rem;}
  .appt-time-strip{background:rgba(36,68,65,0.03);padding:0.45rem 1rem;display:flex;align-items:center;gap:0.5rem;font-size:0.73rem;font-weight:700;color:var(--muted);border-bottom:1px solid rgba(36,68,65,0.05);}
  .action-row{display:flex;gap:0.5rem;padding:0.7rem 1rem;border-top:1px solid rgba(36,68,65,0.05);background:rgba(36,68,65,0.015);}
  .act-btn{flex:1;padding:0.55rem;border-radius:10px;font-size:0.78rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:0.3rem;}
  .act-btn-done{background:rgba(34,197,94,0.1);color:#16a34a;}
  .act-btn-done:hover{background:rgba(34,197,94,0.2);}
  .act-btn-cancel{background:rgba(195,54,67,0.08);color:#C33643;}
  .act-btn-cancel:hover{background:rgba(195,54,67,0.18);}
  .notes-pill{background:rgba(245,158,11,0.08);border-radius:10px;padding:0.5rem 0.7rem;font-size:0.78rem;color:#92400e;margin:0.6rem 0 0;display:flex;align-items:flex-start;gap:0.4rem;line-height:1.45;}
  .staff-badge{display:inline-flex;align-items:center;gap:0.3rem;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:50px;padding:0.15rem 0.6rem;font-size:0.65rem;font-weight:700;color:#16a34a;}
  .toast-bar{position:fixed;bottom:5rem;left:50%;transform:translateX(-50%);z-index:400;padding:0.75rem 1.4rem;border-radius:50px;font-size:0.85rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.15);white-space:nowrap;background:var(--green);color:#fff;animation:toastIn 0.3s ease,toastOut 0.4s 3s ease forwards;}
  @keyframes toastIn{from{opacity:0;transform:translateX(-50%) translateY(12px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
  @keyframes toastOut{from{opacity:1}to{opacity:0;pointer-events:none}}
</style>

<?php if ($toast): ?>
<div class="toast-bar">✓ <?= htmlspecialchars($toast) ?></div>
<?php endif; ?>

<div class="page">

  <!-- Info banner -->
  <div style="background:rgba(34,197,94,0.07);border:1px solid rgba(34,197,94,0.18);border-radius:14px;padding:0.7rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:0.6rem;font-size:0.78rem;color:#15803d;">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span><strong>Appointments shown here are pre-approved by staff.</strong> New booking requests are reviewed by staff first before appearing in your schedule.</span>
  </div>

  <!-- Filter Tabs -->
  <div style="display:flex;gap:0.5rem;margin-bottom:1rem;overflow-x:auto;padding-bottom:0.2rem;">
    <?php
    $tabs = ['upcoming'=>'Upcoming','today'=>'Today','completed'=>'Completed','cancelled'=>'Cancelled'];
    foreach ($tabs as $k=>$v):
    ?>
    <a href="?filter=<?= $k ?>" style="flex-shrink:0;padding:0.45rem 1rem;border-radius:50px;font-size:0.78rem;font-weight:600;text-decoration:none;<?= $filter===$k?'background:var(--green);color:#fff;':'background:#fff;color:var(--muted);border:1px solid rgba(36,68,65,0.1);' ?>">
      <?= $v ?>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if ($appts && $appts->num_rows > 0):
    $shown_date = '';
    while ($a = $appts->fetch_assoc()):
      $appt_date_label = date('l, F j', strtotime($a['appointment_date']));
      if ($appt_date_label !== $shown_date): $shown_date = $appt_date_label; ?>
  <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin:1rem 0 0.5rem;padding-left:0.2rem;">
    <?= $appt_date_label === date('l, F j') ? '🗓 Today' : $appt_date_label ?>
  </div>
  <?php endif;
    $cardClass = $a['status']==='Confirmed' ? 'confirmed-card' : ($a['status']==='Completed' ? 'completed-card' : 'cancelled-card');
    $apptTs    = strtotime($a['appointment_date'].' '.$a['appointment_time']);
    $now       = time();
    $active    = $now >= ($apptTs - 900) && $now <= ($apptTs + 3600);
    $early     = $active && $now < $apptTs;
    $soon      = $now < ($apptTs - 900) && $now >= ($apptTs - 3600);
    $room      = getJitsiRoom($a['id'], $a['appointment_date']);
  ?>

  <div class="appt-card <?= $cardClass ?>">
    <!-- Time strip -->
    <div class="appt-time-strip">
      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/></svg>
      <?= date('g:i A', strtotime($a['appointment_time'])) ?>
      &nbsp;·&nbsp;
      <?= htmlspecialchars($a['type'] ?? 'Consultation') ?>
      <span style="margin-left:auto;display:flex;align-items:center;gap:0.5rem;">
        <span class="staff-badge">✓ Staff Approved</span>
        <span class="badge <?= $a['status']==='Confirmed'?'badge-green':($a['status']==='Completed'?'badge-blue':'badge-red') ?>"><?= $a['status'] ?></span>
      </span>
    </div>

    <!-- Patient info -->
    <div class="appt-card-body">
      <div style="display:flex;align-items:center;gap:0.8rem;">
        <div class="pat-avatar">
          <?php if (!empty($a['patient_photo'])): ?>
            <img src="../../<?= htmlspecialchars($a['patient_photo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;"/>
          <?php else: echo strtoupper(substr($a['patient_name'],0,2)); endif; ?>
        </div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:0.92rem;"><?= htmlspecialchars($a['patient_name']) ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:0.8rem;margin-top:0.2rem;">
            <?php if (!empty($a['patient_phone'])): ?>
            <span style="font-size:0.73rem;color:var(--muted);">📞 <?= htmlspecialchars($a['patient_phone']) ?></span>
            <?php endif; ?>
            <?php if (!empty($a['patient_email'])): ?>
            <span style="font-size:0.73rem;color:var(--muted);">✉ <?= htmlspecialchars($a['patient_email']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!empty($a['notes'])): ?>
      <div class="notes-pill">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        <?= htmlspecialchars($a['notes']) ?>
      </div>
      <?php endif; ?>

      <!-- Video Call -->
      <?php if ($a['status'] === 'Confirmed'): ?>
      <div style="margin-top:0.8rem;">
        <?php
        $apptTs = strtotime($a['appointment_date'].' '.$a['appointment_time']);
        $now2   = time();
        $active = $now2 >= ($apptTs - 900) && $now2 <= ($apptTs + 3600);
        $early  = $active && $now2 < $apptTs;
        $soon   = !$active && $now2 >= ($apptTs - 3600);
        ?>
        <?php if ($active): ?>
          <a href="call.php?appt_id=<?= $a['id'] ?>" class="join-call-btn">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
            <?= $early ? '📹 Open Call Room Early' : '📹 Start Video Call' ?>
          </a>
        <?php elseif ($soon): ?>
          <span class="call-soon">🕐 Call opens at <?= date('g:i A', $apptTs - 900) ?></span>
        <?php else: ?>
          <span style="font-size:0.72rem;color:var(--muted);">📹 Video call available 15 min before appointment</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Action buttons — only for Confirmed appointments -->
    <?php if ($a['status'] === 'Confirmed'): ?>
    <div class="action-row">
      <form method="POST" style="flex:1;display:contents;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Completed"/>
        <button name="update_status" class="act-btn act-btn-done">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Mark Done
        </button>
      </form>
      <form method="POST" style="flex:1;display:contents;">
        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>"/>
        <input type="hidden" name="status" value="Cancelled"/>
        <button name="update_status" class="act-btn act-btn-cancel" onclick="return confirm('Cancel this confirmed appointment?')">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          Cancel
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <?php endwhile; else: ?>
  <div class="card"><div class="empty-state">
    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    No <?= $filter ?> appointments.
  </div></div>
  <?php endif; ?>

</div>

<script>
setTimeout(()=>{ const t=document.querySelector('.toast-bar'); if(t)t.remove(); }, 3500);
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>