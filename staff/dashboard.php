<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// ── Handle quick approve / reject from dashboard ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['appt_id'])) {
    $aid    = (int)$_POST['appt_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['action_notes'] ?? '');
    $map    = ['approve' => 'Confirmed', 'reject' => 'Cancelled'];

    if (isset($map[$action])) {
        $new_status = $map[$action];
        $conn->query("UPDATE appointments SET status='$new_status' WHERE id=$aid");
        logAction($conn, $aid, $staff_id, ucfirst($action) . 'd', $notes);
        $_SESSION['toast'] = "Appointment " . $map[$action] . " successfully.";
    }
    header('Location: dashboard.php');
    exit;
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$active_page = 'dashboard';
$today       = date('Y-m-d');

// ── Data ──
$today_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name,
           d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.appointment_date = '$today'
    ORDER BY a.appointment_time ASC
");

$pending_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name,
           d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE a.status = 'Pending'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");

$stat_today    = $today_appts   ? $today_appts->num_rows   : 0;
$stat_pending  = $pending_appts ? $pending_appts->num_rows : 0;
$stat_patients = $conn->query("SELECT COUNT(*) c FROM patients")->fetch_assoc()['c'];
$stat_doctors  = $conn->query("SELECT COUNT(*) c FROM doctors WHERE status='active'")->fetch_assoc()['c'];

require_once 'includes/header.php';
?>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-num" style="color:var(--blue)"><?= $stat_today ?></div>
    <div class="stat-lbl">Today's Appointments</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:#d97706"><?= $stat_pending ?></div>
    <div class="stat-lbl">Pending Approval</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--green)"><?= $stat_patients ?></div>
    <div class="stat-lbl">Total Patients</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--red)"><?= $stat_doctors ?></div>
    <div class="stat-lbl">Active Doctors</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;">

  <!-- Today's Queue -->
  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">📋 Today's Queue</h2>
      <span class="badge bg-blue"><?= date('M j') ?></span>
    </div>
    <?php
    $q = 0;
    if ($today_appts && $today_appts->num_rows > 0):
      $today_appts->data_seek(0);
      while ($a = $today_appts->fetch_assoc()):
        $q++;
    ?>
    <div class="queue-item">
      <div class="queue-num"><?= $q ?></div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:.88rem"><?= htmlspecialchars($a['patient_name']) ?></div>
        <div style="font-size:.75rem;color:var(--muted)">
          <?= date('g:i A', strtotime($a['appointment_time'])) ?> · Dr. <?= htmlspecialchars($a['doctor_name']) ?>
        </div>
      </div>
      <span class="badge <?= $a['status'] === 'Confirmed' ? 'bg-green' : ($a['status'] === 'Pending' ? 'bg-orange' : 'bg-gray') ?>">
        <?= $a['status'] ?>
      </span>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-row">No appointments today.</div>
    <?php endif ?>
  </div>

  <!-- Pending Approvals -->
  <div class="card">
    <div class="sec-head" style="margin-bottom:.8rem">
      <h2 style="font-size:1rem">⏳ Pending Approvals</h2>
      <?php if ($stat_pending > 0): ?>
        <span class="badge bg-orange"><?= $stat_pending ?></span>
      <?php endif ?>
    </div>
    <?php
    if ($pending_appts && $pending_appts->num_rows > 0):
      $pending_appts->data_seek(0);
      while ($a = $pending_appts->fetch_assoc()):
    ?>
    <div class="queue-item">
      <div style="flex:1">
        <div style="font-weight:700;font-size:.87rem"><?= htmlspecialchars($a['patient_name']) ?></div>
        <div style="font-size:.74rem;color:var(--muted)">
          <?= date('M j', strtotime($a['appointment_date'])) ?>
          <?= date('g:i A', strtotime($a['appointment_time'])) ?>
          · Dr. <?= htmlspecialchars($a['doctor_name']) ?>
        </div>
      </div>
      <div style="display:flex;gap:.4rem">
        <button class="btn-green btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'approve')">✓</button>
        <button class="btn-red   btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'reject')">✕</button>
      </div>
    </div>
    <?php endwhile; else: ?>
    <div class="empty-row">All caught up! No pending requests.</div>
    <?php endif ?>
  </div>

</div>

<!-- Hidden quick-action form -->
<form method="POST" id="quick-form" style="display:none">
  <input type="hidden" name="action"       id="qf-action"/>
  <input type="hidden" name="appt_id"      id="qf-appt-id"/>
  <input type="hidden" name="action_notes" id="qf-notes"/>
</form>

<script>
function quickAction(id, action) {
  document.getElementById('qf-appt-id').value = id;
  document.getElementById('qf-action').value  = action;
  document.getElementById('qf-notes').value   = '';
  document.getElementById('quick-form').submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>