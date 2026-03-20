<?php
require_once 'includes/auth.php';

// ── Update patient info ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_patient'])) {
    $pid   = (int)$_POST['patient_id'];
    $phone = trim($_POST['phone_number'] ?? '');
    $addr  = trim($_POST['address']      ?? '');
    $bdate = trim($_POST['birth_date']   ?? '');
    $gender= trim($_POST['gender']       ?? '');
    $stmt  = $conn->prepare("UPDATE patients SET phone_number=?, address=?, birth_date=?, gender=? WHERE id=?");
    $stmt->bind_param("ssssi", $phone, $addr, $bdate, $gender, $pid);
    $stmt->execute();
    $_SESSION['toast'] = 'Patient info updated.';
    header('Location: patients.php'); exit;
}

$search = trim($_GET['q'] ?? '');
$view   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Single patient view
$patient = null;
$p_appts = null;
if ($view) {
    $r = $conn->prepare("SELECT * FROM patients WHERE id=? LIMIT 1");
    $r->bind_param("i", $view);
    $r->execute();
    $patient = $r->get_result()->fetch_assoc();

    if ($patient) {
        $p_appts = $conn->query("
            SELECT a.*, d.full_name AS doctor_name
            FROM appointments a JOIN doctors d ON d.id=a.doctor_id
            WHERE a.patient_id=$view
            ORDER BY a.appointment_date DESC LIMIT 20
        ");
    }
}

// Patient list
$where = "1=1";
if ($search) {
    $sq = $conn->real_escape_string($search);
    $where .= " AND (full_name LIKE '%$sq%' OR email LIKE '%$sq%')";
}
$patients = $conn->query("SELECT * FROM patients WHERE $where ORDER BY full_name ASC");

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$page_title = 'Patients — TELE-CARE Staff';
$active_nav = 'patients';
require_once 'includes/head.php';
?>
<body>
<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>
<?php require_once 'includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.73rem;color:var(--muted);font-weight:600;">Staff Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Patient Management</div>
    </div>
  </div>

  <div class="page-content">

  <?php if ($patient): ?>
    <!-- ── Single Patient View ── -->
    <a href="patients.php" style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.82rem;color:var(--muted);font-weight:600;text-decoration:none;margin-bottom:1rem;">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      Back to list
    </a>

    <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:1.5rem;">
      <!-- Edit info card -->
      <div class="card">
        <div class="section-label">Patient Information</div>
        <form method="POST">
          <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>"/>
          <div class="form-field">
            <label class="field-label">Full Name</label>
            <input type="text" class="field-input" value="<?= htmlspecialchars($patient['full_name']) ?>" disabled style="opacity:0.6;"/>
          </div>
          <div class="form-field">
            <label class="field-label">Email</label>
            <input type="email" class="field-input" value="<?= htmlspecialchars($patient['email']) ?>" disabled style="opacity:0.6;"/>
          </div>
          <div class="form-field">
            <label class="field-label">Phone Number</label>
            <input type="tel" name="phone_number" class="field-input" value="<?= htmlspecialchars($patient['phone_number'] ?? '') ?>" placeholder="09XXXXXXXXX"/>
          </div>
          <div class="form-field">
            <label class="field-label">Address</label>
            <input type="text" name="address" class="field-input" value="<?= htmlspecialchars($patient['address'] ?? '') ?>" placeholder="Home address"/>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.8rem;">
            <div class="form-field">
              <label class="field-label">Birth Date</label>
              <input type="date" name="birth_date" class="field-input" value="<?= htmlspecialchars($patient['birth_date'] ?? '') ?>"/>
            </div>
            <div class="form-field">
              <label class="field-label">Gender</label>
              <select name="gender" class="field-input">
                <option value="">Select</option>
                <?php foreach(['Male','Female','Other'] as $g): ?>
                <option value="<?= $g ?>" <?= ($patient['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-field">
            <label class="field-label">Joined</label>
            <input type="text" class="field-input" value="<?= date('F j, Y', strtotime($patient['created_at'])) ?>" disabled style="opacity:0.6;"/>
          </div>
          <button type="submit" name="update_patient" class="btn-submit">Save Changes</button>
        </form>
      </div>

      <!-- Appointment history -->
      <div>
        <div class="section-label" style="margin-bottom:0.8rem;">Appointment History</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Status</th></tr></thead>
            <tbody>
            <?php
            $h_has = false;
            if ($p_appts && $p_appts->num_rows > 0):
              while ($ha = $p_appts->fetch_assoc()):
                $h_has = true;
            ?>
            <tr>
              <td style="white-space:nowrap;font-weight:600;"><?= date('M d, Y', strtotime($ha['appointment_date'])) ?></td>
              <td style="font-size:0.8rem;color:var(--blue);font-weight:600;"><?= date('g:i A', strtotime($ha['appointment_time'])) ?></td>
              <td style="font-size:0.83rem;">Dr. <?= htmlspecialchars($ha['doctor_name']) ?></td>
              <td><span class="badge <?= match($ha['status']) {
                'Confirmed'=>'badge-green','Pending'=>'badge-orange','Completed'=>'badge-blue',default=>'badge-red'
              } ?>"><?= $ha['status'] ?></span></td>
            </tr>
            <?php endwhile; endif; ?>
            <?php if (!$h_has): ?>
            <tr><td colspan="4" class="empty-row">No appointment history.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- ── Patient List ── -->
    <form method="GET" style="margin-bottom:1rem;display:flex;gap:0.5rem;">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="field-input" placeholder="Search by name or email…" style="max-width:300px;"/>
      <button type="submit" class="btn-sm btn-blue">Search</button>
      <?php if ($search): ?><a href="patients.php" class="btn-sm btn-gray">Clear</a><?php endif; ?>
    </form>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Patient</th><th>Contact</th><th>Joined</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php
        if ($patients && $patients->num_rows > 0):
          while ($pt = $patients->fetch_assoc()):
        ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($pt['full_name']) ?></div>
            <div style="font-size:0.73rem;color:var(--muted);"><?= htmlspecialchars($pt['email']) ?></div>
          </td>
          <td style="font-size:0.83rem;color:var(--muted);"><?= htmlspecialchars($pt['phone_number'] ?? '—') ?></td>
          <td style="font-size:0.78rem;color:var(--muted);"><?= date('M d, Y', strtotime($pt['created_at'])) ?></td>
          <td><span class="badge <?= ($pt['is_active']??1) ? 'badge-green' : 'badge-red' ?>"><?= ($pt['is_active']??1) ? 'Active' : 'Inactive' ?></span></td>
          <td><a href="?id=<?= $pt['id'] ?>" class="btn-sm btn-blue">View / Edit</a></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" class="empty-row">No patients found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  </div>
</div>
<script>setTimeout(()=>{const t=document.querySelector('.toast');if(t)t.remove();},3500);</script>
</body>
</html>