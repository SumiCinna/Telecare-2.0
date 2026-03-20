<?php
require_once 'includes/auth.php';

// ── Update lab status ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $lid    = (int)$_POST['lab_id'];
    $status = trim($_POST['lab_status'] ?? '');
    $notes  = trim($_POST['staff_notes'] ?? '');
    $allowed = ['Pending','Sent to MedTech','Processing','Results Ready','Completed','Cancelled'];
    if (in_array($status, $allowed)) {
        $stmt = $conn->prepare("UPDATE lab_requests SET status=?, staff_notes=?, updated_by_staff_id=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("ssii", $status, $notes, $staff_id, $lid);
        $stmt->execute();
        $_SESSION['toast'] = 'Lab request updated.';
    }
    header('Location: lab_requests.php'); exit;
}

$filter = $_GET['filter'] ?? 'all';
$where  = "1=1";
if ($filter === 'pending')   $where .= " AND lr.status='Pending'";
if ($filter === 'sent')      $where .= " AND lr.status='Sent to MedTech'";
if ($filter === 'processing')$where .= " AND lr.status='Processing'";
if ($filter === 'ready')     $where .= " AND lr.status='Results Ready'";

$labs = $conn->query("
    SELECT lr.*, p.full_name AS patient_name, d.full_name AS doctor_name
    FROM lab_requests lr
    JOIN patients p ON p.id = lr.patient_id
    JOIN doctors  d ON d.id = lr.doctor_id
    WHERE $where
    ORDER BY lr.created_at DESC
");

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$page_title = 'Lab Requests — TELE-CARE Staff';
$active_nav = 'lab';
require_once 'includes/head.php';
?>
<body>
<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.73rem;color:var(--muted);font-weight:600;">Staff Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Lab Request Coordination</div>
    </div>
  </div>

  <div class="page-content">
    <!-- Filter tabs -->
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1.2rem;">
      <?php foreach(['all'=>'All','pending'=>'Pending','sent'=>'Sent to MedTech','processing'=>'Processing','ready'=>'Results Ready'] as $k=>$lbl): ?>
      <a href="?filter=<?= $k ?>" class="btn-sm <?= $filter===$k ? 'btn-blue' : 'btn-gray' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Requested</th><th>Patient</th><th>Doctor</th><th>Test</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php
        if ($labs && $labs->num_rows > 0):
          while ($l = $labs->fetch_assoc()):
            $statusBadge = match($l['status']) {
              'Pending'          => 'badge-orange',
              'Sent to MedTech'  => 'badge-blue',
              'Processing'       => 'badge-blue',
              'Results Ready'    => 'badge-green',
              'Completed'        => 'badge-gray',
              default            => 'badge-red',
            };
        ?>
        <tr>
          <td style="font-size:0.78rem;color:var(--muted);white-space:nowrap;"><?= date('M d, Y', strtotime($l['created_at'])) ?></td>
          <td style="font-weight:600;"><?= htmlspecialchars($l['patient_name']) ?></td>
          <td style="font-size:0.83rem;">Dr. <?= htmlspecialchars($l['doctor_name']) ?></td>
          <td style="font-size:0.83rem;"><?= htmlspecialchars($l['test_name'] ?? $l['test_type'] ?? '—') ?></td>
          <td><span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($l['status']) ?></span></td>
          <td>
            <?php if (!in_array($l['status'], ['Completed','Cancelled'])): ?>
            <button class="btn-sm btn-blue" onclick="openUpdate(<?= $l['id'] ?>, '<?= htmlspecialchars(addslashes($l['status'])) ?>', '<?= htmlspecialchars(addslashes($l['staff_notes'] ?? '')) ?>')">Update</button>
            <?php else: ?>
            <span style="font-size:0.75rem;color:var(--muted);">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="6" class="empty-row">No lab requests found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Update Status Modal -->
<div class="modal-overlay" id="modal-update">
  <div class="modal">
    <h3>Update Lab Request</h3>
    <form method="POST">
      <input type="hidden" name="lab_id" id="update-lab-id"/>
      <div class="form-field">
        <label class="field-label">Status *</label>
        <select name="lab_status" id="update-status" class="field-input" required>
          <?php foreach(['Pending','Sent to MedTech','Processing','Results Ready','Completed','Cancelled'] as $s): ?>
          <option value="<?= $s ?>"><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label class="field-label">Staff Notes</label>
        <textarea name="staff_notes" id="update-notes" class="field-input" rows="3" placeholder="e.g. Sent specimen to lab at 10am…"></textarea>
      </div>
      <button type="submit" name="update_status" class="btn-submit">Save Update</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-update')">Cancel</button>
    </form>
  </div>
</div>

<script>
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));
  function openUpdate(id, status, notes) {
    document.getElementById('update-lab-id').value  = id;
    document.getElementById('update-status').value  = status;
    document.getElementById('update-notes').value   = notes;
    openModal('modal-update');
  }
  setTimeout(()=>{const t=document.querySelector('.toast');if(t)t.remove();},3500);
</script>
</body>
</html>