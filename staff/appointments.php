<?php
require_once 'includes/auth.php';

$success = ''; $error = '';

// ── Quick approve from dashboard ──
if (isset($_GET['quick_approve'])) {
    $aid = (int)$_GET['quick_approve'];
    $conn->query("UPDATE appointments SET status='Confirmed' WHERE id=$aid");
    $_SESSION['toast'] = 'Appointment confirmed.';
    header('Location: appointments.php'); exit;
}

// ── Approve ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $aid = (int)$_POST['appt_id'];
    $conn->query("UPDATE appointments SET status='Confirmed' WHERE id=$aid");
    $_SESSION['toast'] = 'Appointment confirmed.';
    header('Location: appointments.php'); exit;
}

// ── Reject ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $aid    = (int)$_POST['appt_id'];
    $reason = trim($_POST['reject_reason'] ?? '');
    $conn->query("UPDATE appointments SET status='Cancelled', notes=CONCAT(COALESCE(notes,''), ' [Rejected: " . $conn->real_escape_string($reason) . "]') WHERE id=$aid");
    $_SESSION['toast'] = 'Appointment rejected.';
    header('Location: appointments.php'); exit;
}

// ── Reschedule ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule'])) {
    $aid      = (int)$_POST['appt_id'];
    $new_date = trim($_POST['new_date'] ?? '');
    $new_time = trim($_POST['new_time'] ?? '');
    if ($new_date && $new_time) {
        $conn->query("UPDATE appointments SET appointment_date='$new_date', appointment_time='$new_time', status='Confirmed' WHERE id=$aid");
        $_SESSION['toast'] = 'Appointment rescheduled.';
    }
    header('Location: appointments.php'); exit;
}

// ── Cancel ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    $aid = (int)$_POST['appt_id'];
    $conn->query("UPDATE appointments SET status='Cancelled' WHERE id=$aid");
    $_SESSION['toast'] = 'Appointment cancelled.';
    header('Location: appointments.php'); exit;
}

// ── Mark as completed ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete'])) {
    $aid = (int)$_POST['appt_id'];
    $conn->query("UPDATE appointments SET status='Completed' WHERE id=$aid");
    $_SESSION['toast'] = 'Marked as completed.';
    header('Location: appointments.php'); exit;
}

// ── Filter ──
$filter  = $_GET['filter'] ?? 'all';
$search  = trim($_GET['q'] ?? '');
$where   = "1=1";
if ($filter === 'pending')   $where .= " AND a.status='Pending'";
if ($filter === 'confirmed') $where .= " AND a.status='Confirmed'";
if ($filter === 'cancelled') $where .= " AND a.status='Cancelled'";
if ($filter === 'completed') $where .= " AND a.status='Completed'";
if ($filter === 'today')     $where .= " AND a.appointment_date=CURDATE()";
if ($search) {
    $sq = $conn->real_escape_string($search);
    $where .= " AND (p.full_name LIKE '%$sq%' OR d.full_name LIKE '%$sq%')";
}

$appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.email AS patient_email,
           d.full_name AS doctor_name, d.specialty
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    WHERE $where
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$page_title = 'Appointments — TELE-CARE Staff';
$active_nav = 'appointments';
require_once 'includes/head.php';
?>
<body>
<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.73rem;color:var(--muted);font-weight:600;">Staff Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Appointment Management</div>
    </div>
  </div>

  <div class="page-content">

    <!-- Filters -->
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.2rem;align-items:center;">
      <?php foreach(['all'=>'All','today'=>'Today','pending'=>'Pending','confirmed'=>'Confirmed','cancelled'=>'Cancelled','completed'=>'Completed'] as $k=>$lbl): ?>
      <a href="?filter=<?= $k ?>" class="btn-sm <?= $filter===$k ? 'btn-blue' : 'btn-gray' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
      <form method="GET" style="margin-left:auto;display:flex;gap:0.4rem;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"/>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="field-input" placeholder="Search patient / doctor…" style="padding:0.42rem 0.8rem;border-radius:50px;width:200px;"/>
        <button type="submit" class="btn-sm btn-blue">Search</button>
      </form>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Date & Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php
        if ($appts && $appts->num_rows > 0):
          while ($a = $appts->fetch_assoc()):
        ?>
        <tr>
          <td>
            <div style="font-weight:700;white-space:nowrap;"><?= date('M d, Y', strtotime($a['appointment_date'])) ?></div>
            <div style="font-size:0.75rem;color:var(--blue);font-weight:600;"><?= date('g:i A', strtotime($a['appointment_time'])) ?></div>
          </td>
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($a['patient_name']) ?></div>
            <div style="font-size:0.73rem;color:var(--muted);"><?= htmlspecialchars($a['patient_email']) ?></div>
          </td>
          <td>
            <div style="font-weight:600;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
            <div style="font-size:0.73rem;color:var(--muted);"><?= htmlspecialchars($a['specialty'] ?? '') ?></div>
          </td>
          <td><span class="badge badge-blue"><?= htmlspecialchars($a['type']) ?></span></td>
          <td>
            <span class="badge <?= match($a['status']) {
              'Confirmed'  => 'badge-green',
              'Pending'    => 'badge-orange',
              'Completed'  => 'badge-blue',
              default      => 'badge-red'
            } ?>">
              <?= $a['status'] ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:0.3rem;flex-wrap:wrap;">
              <?php if ($a['status'] === 'Pending'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="appt_id" value="<?= $a['id'] ?>"/>
                  <button type="submit" name="approve" class="btn-sm btn-green">Approve</button>
                </form>
                <button class="btn-sm btn-red" onclick="openReject(<?= $a['id'] ?>)">Reject</button>
              <?php endif; ?>
              <?php if ($a['status'] === 'Confirmed'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="appt_id" value="<?= $a['id'] ?>"/>
                  <button type="submit" name="complete" class="btn-sm btn-blue"
                          onclick="return confirm('Mark as completed?')">Done</button>
                </form>
                <button class="btn-sm btn-gray" onclick="openReschedule(<?= $a['id'] ?>, '<?= $a['appointment_date'] ?>', '<?= substr($a['appointment_time'],0,5) ?>')">Reschedule</button>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="appt_id" value="<?= $a['id'] ?>"/>
                  <button type="submit" name="cancel" class="btn-sm btn-red"
                          onclick="return confirm('Cancel this appointment?')">Cancel</button>
                </form>
              <?php endif; ?>
              <?php if (in_array($a['status'], ['Cancelled','Completed'])): ?>
                <span style="font-size:0.75rem;color:var(--muted);">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="6" class="empty-row">No appointments found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="modal-reject">
  <div class="modal">
    <h3>Reject Appointment</h3>
    <form method="POST">
      <input type="hidden" name="appt_id" id="reject-id"/>
      <div class="form-field">
        <label class="field-label">Reason (optional)</label>
        <textarea name="reject_reason" class="field-input" rows="3" placeholder="e.g. Doctor unavailable on this date…"></textarea>
      </div>
      <button type="submit" name="reject" class="btn-submit" style="background:var(--red);">Reject Appointment</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-reject')">Cancel</button>
    </form>
  </div>
</div>

<!-- Reschedule Modal -->
<div class="modal-overlay" id="modal-reschedule">
  <div class="modal">
    <h3>Reschedule Appointment</h3>
    <form method="POST">
      <input type="hidden" name="appt_id" id="reschedule-id"/>
      <div class="form-field">
        <label class="field-label">New Date *</label>
        <input type="date" name="new_date" id="reschedule-date" class="field-input" required min="<?= date('Y-m-d') ?>"/>
      </div>
      <div class="form-field">
        <label class="field-label">New Time *</label>
        <input type="time" name="new_time" id="reschedule-time" class="field-input" required step="3600"/>
      </div>
      <button type="submit" name="reschedule" class="btn-submit">Save New Schedule</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-reschedule')">Cancel</button>
    </form>
  </div>
</div>

<script>
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));
  function openReject(id)  { document.getElementById('reject-id').value=id; openModal('modal-reject'); }
  function openReschedule(id, date, time) {
    document.getElementById('reschedule-id').value   = id;
    document.getElementById('reschedule-date').value = date;
    document.getElementById('reschedule-time').value = time;
    openModal('modal-reschedule');
  }
  setTimeout(() => { const t=document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>