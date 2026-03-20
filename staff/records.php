<?php
require_once 'includes/auth.php';

// ── Encode paper-based record ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['encode_record'])) {
    $pid    = (int)$_POST['patient_id'];
    $did    = (int)$_POST['doctor_id'];
    $rtype  = trim($_POST['record_type']  ?? '');
    $rdate  = trim($_POST['record_date']  ?? '');
    $notes  = trim($_POST['record_notes'] ?? '');
    $file_path = null;

    if (!empty($_FILES['record_file']['name'])) {
        $dir = '../../uploads/records/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext   = pathinfo($_FILES['record_file']['name'], PATHINFO_EXTENSION);
        $fname = uniqid('rec_') . '.' . $ext;
        if (move_uploaded_file($_FILES['record_file']['tmp_name'], $dir . $fname)) {
            $file_path = 'uploads/records/' . $fname;
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO patient_records (patient_id, doctor_id, record_type, record_date, notes, file_path, encoded_by_staff_id)
        VALUES (?,?,?,?,?,?,?)
    ");
    if ($stmt) {
        $stmt->bind_param("iissssi", $pid, $did, $rtype, $rdate, $notes, $file_path, $staff_id);
        $stmt->execute();
        $_SESSION['toast'] = 'Record encoded successfully.';
    } else {
        $_SESSION['toast'] = 'DB error: ' . $conn->error;
    }
    header('Location: records.php'); exit;
}

// ── Data for form dropdowns ──
$patients_list = $conn->query("SELECT id, full_name FROM patients WHERE is_active=1 ORDER BY full_name");
$doctors_list  = $conn->query("SELECT id, full_name FROM doctors WHERE status='active' ORDER BY full_name");

// ── Recent records ──
$recent = $conn->query("
    SELECT pr.*, p.full_name AS patient_name, d.full_name AS doctor_name
    FROM patient_records pr
    JOIN patients p ON p.id = pr.patient_id
    JOIN doctors  d ON d.id = pr.doctor_id
    ORDER BY pr.created_at DESC
    LIMIT 30
");

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$page_title = 'Records — TELE-CARE Staff';
$active_nav = 'records';
require_once 'includes/head.php';
?>
<body>
<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.73rem;color:var(--muted);font-weight:600;">Staff Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Records Assistance</div>
    </div>
    <button class="btn-primary" onclick="openModal('modal-encode')">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Encode Record
    </button>
  </div>

  <div class="page-content">
    <div class="section-label">Recent Encoded Records</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date Encoded</th><th>Patient</th><th>Doctor</th><th>Type</th><th>Record Date</th><th>File</th></tr></thead>
        <tbody>
        <?php
        if ($recent && $recent->num_rows > 0):
          while ($r = $recent->fetch_assoc()):
        ?>
        <tr>
          <td style="font-size:0.78rem;color:var(--muted);"><?= date('M d, Y g:i A', strtotime($r['created_at'])) ?></td>
          <td style="font-weight:600;"><?= htmlspecialchars($r['patient_name']) ?></td>
          <td style="font-size:0.83rem;">Dr. <?= htmlspecialchars($r['doctor_name']) ?></td>
          <td><span class="badge badge-blue"><?= htmlspecialchars($r['record_type']) ?></span></td>
          <td style="font-size:0.82rem;"><?= $r['record_date'] ? date('M d, Y', strtotime($r['record_date'])) : '—' ?></td>
          <td>
            <?php if (!empty($r['file_path'])): ?>
              <a href="../../<?= htmlspecialchars($r['file_path']) ?>" target="_blank" class="btn-sm btn-blue">View</a>
            <?php else: ?>
              <span style="font-size:0.75rem;color:var(--muted);">No file</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="6" class="empty-row">No records encoded yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Encode Modal -->
<div class="modal-overlay" id="modal-encode">
  <div class="modal">
    <h3>Encode Paper-Based Record</h3>
    <form method="POST" enctype="multipart/form-data">
      <div class="form-field">
        <label class="field-label">Patient *</label>
        <select name="patient_id" class="field-input" required>
          <option value="">Select patient</option>
          <?php if($patients_list) while($p=$patients_list->fetch_assoc()): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-field">
        <label class="field-label">Doctor *</label>
        <select name="doctor_id" class="field-input" required>
          <option value="">Select doctor</option>
          <?php if($doctors_list) while($d=$doctors_list->fetch_assoc()): ?>
          <option value="<?= $d['id'] ?>">Dr. <?= htmlspecialchars($d['full_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
        <div class="form-field">
          <label class="field-label">Record Type *</label>
          <select name="record_type" class="field-input" required>
            <option value="">Select type</option>
            <?php foreach(['Consultation Notes','Lab Result','Prescription','Medical Certificate','Referral','Imaging','Other'] as $rt): ?>
            <option value="<?= $rt ?>"><?= $rt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label class="field-label">Record Date</label>
          <input type="date" name="record_date" class="field-input" max="<?= date('Y-m-d') ?>"/>
        </div>
      </div>
      <div class="form-field">
        <label class="field-label">Notes / Remarks</label>
        <textarea name="record_notes" class="field-input" rows="3" placeholder="Any notes about the record…"></textarea>
      </div>
      <div class="form-field">
        <label class="field-label">Upload Document <span style="font-weight:400;font-size:0.68rem;">(PDF, image)</span></label>
        <input type="file" name="record_file" class="field-input" accept=".pdf,.jpg,.jpeg,.png" style="padding:0.5rem;"/>
      </div>
      <button type="submit" name="encode_record" class="btn-submit">Save Record</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-encode')">Cancel</button>
    </form>
  </div>
</div>

<script>
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));
  setTimeout(()=>{const t=document.querySelector('.toast');if(t)t.remove();},3500);
</script>
</body>
</html>