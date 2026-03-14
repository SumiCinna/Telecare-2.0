<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_patient'])) {
    $pid = (int)$_POST['patient_id'];
    $did = (int)$_POST['doctor_id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO patient_doctors (patient_id, doctor_id) VALUES (?,?)");
    $stmt->bind_param("ii", $pid, $did);
    $stmt->execute();
    $_SESSION['toast'] = "Patient assigned.";
    header('Location: patients.php'); exit;
}

if (isset($_GET['unassign'])) {
    $pid = (int)$_GET['pid'];
    $did = (int)$_GET['did'];
    $conn->query("DELETE FROM patient_doctors WHERE patient_id=$pid AND doctor_id=$did");
    $_SESSION['toast'] = "Patient unassigned.";
    header('Location: patients.php'); exit;
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Patients — TELE-CARE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);display:flex;min-height:100vh}
    h1,h2,h3{font-family:'Playfair Display',serif}
    .sidebar{width:230px;min-width:230px;background:var(--green);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-logo{padding:1.8rem 1.5rem 1.2rem;font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:#fff;border-bottom:1px solid rgba(255,255,255,0.08)}
    .sidebar-logo span{color:var(--red)}
    .sidebar-admin{padding:1rem 1.5rem;font-size:0.78rem;color:rgba(255,255,255,0.45);border-bottom:1px solid rgba(255,255,255,0.08)}
    .sidebar-admin strong{color:rgba(255,255,255,0.8);font-weight:600;display:block;font-size:0.88rem}
    .nav-links{padding:1rem 0;flex:1}
    .nav-link{display:flex;align-items:center;gap:0.8rem;padding:0.8rem 1.5rem;color:rgba(255,255,255,0.55);font-size:0.88rem;font-weight:500;width:100%;text-align:left;font-family:'DM Sans',sans-serif;transition:all 0.2s;border-left:3px solid transparent;text-decoration:none}
    .nav-link svg{width:18px;height:18px;stroke:currentColor;flex-shrink:0}
    .nav-link:hover{color:#fff;background:rgba(255,255,255,0.06)}
    .nav-link.active{color:#fff;background:rgba(255,255,255,0.1);border-left-color:var(--red)}
    .sidebar-logout{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,0.08)}
    .logout-btn{display:flex;align-items:center;gap:0.6rem;color:rgba(255,255,255,0.45);font-size:0.82rem;text-decoration:none;transition:color 0.2s}
    .logout-btn:hover{color:var(--red)}
    .main{flex:1;overflow-y:auto}
    .topbar{background:var(--white);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .page-content{padding:2rem}
    .section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem}
    .section-header h2{font-size:1.3rem}
    .btn-sm{padding:0.4rem 0.9rem;border-radius:50px;font-size:0.78rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-decoration:none;display:inline-block}
    .btn-red{background:rgba(195,54,67,0.1);color:var(--red)}.btn-red:hover{background:var(--red);color:#fff}
    .btn-blue{background:rgba(63,130,227,0.1);color:var(--blue)}.btn-blue:hover{background:var(--blue);color:#fff}
    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse}
    th{padding:0.9rem 1.2rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#9ab0ae;text-align:left;background:rgba(36,68,65,0.03);border-bottom:1px solid rgba(36,68,65,0.07)}
    td{padding:0.9rem 1.2rem;font-size:0.88rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(36,68,65,0.02)}
    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1rem;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.3rem;margin-bottom:1.2rem}
    .field-label{display:block;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#9ab0ae;margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.9rem;color:var(--green);outline:none;transition:border-color 0.2s}
    .field-input:focus{border-color:var(--blue)}
    select.field-input{cursor:pointer}
    .form-field{margin-bottom:0.9rem}
    .btn-submit{width:100%;padding:0.85rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.93rem;border:none;cursor:pointer;margin-top:0.5rem;transition:all 0.25s;font-family:'DM Sans',sans-serif}
    .btn-submit:hover{background:#a82d38}
    .btn-cancel{width:100%;padding:0.7rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:0.88rem;border:1.5px solid rgba(36,68,65,0.15);cursor:pointer;margin-top:0.5rem;font-family:'DM Sans',sans-serif}
    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    .empty-row{text-align:center;padding:3rem;color:#9ab0ae;font-size:0.88rem}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
    @media(max-width:900px){.sidebar{display:none}}
  </style>
</head>
<body>

<?php if ($toast): ?><div class="toast">✓ <?= htmlspecialchars($toast) ?></div><?php endif; ?>

<aside class="sidebar">
  <div class="sidebar-logo">TELE<span>-</span>CARE</div>
  <div class="sidebar-admin">Admin Portal<br/><strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></strong></div>
  <nav class="nav-links">
    <a href="dashboard.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="doctors.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Doctors
    </a>
    <a href="patients.php" class="nav-link active">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patients
    </a>
    <a href="assignments.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Assignments
    </a>
  </nav>
  <div class="sidebar-logout">
    <a href="logout.php" class="logout-btn">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Log Out
    </a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div style="font-size:0.75rem;color:#9ab0ae;font-weight:600;">Admin Portal</div>
      <div style="font-size:0.95rem;font-weight:700;">Patients</div>
    </div>
  </div>

  <div class="page-content">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Patient</th><th>Contact</th><th>Joined</th><th>Assigned Doctor</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php
        $pres = $conn->query("SELECT p.*, d.full_name AS doctor_name, pd.doctor_id FROM patients p LEFT JOIN patient_doctors pd ON pd.patient_id=p.id LEFT JOIN doctors d ON d.id=pd.doctor_id ORDER BY p.created_at DESC");
        if ($pres && $pres->num_rows > 0): while ($pt = $pres->fetch_assoc()): ?>
        <tr>
          <td><div style="font-weight:600;"><?= htmlspecialchars($pt['full_name']) ?></div><div style="font-size:0.75rem;color:#9ab0ae;"><?= htmlspecialchars($pt['email']) ?></div></td>
          <td style="font-size:0.82rem;color:#9ab0ae;"><?= htmlspecialchars($pt['phone_number']) ?></td>
          <td style="font-size:0.78rem;color:#9ab0ae;"><?= date('M d, Y', strtotime($pt['created_at'])) ?></td>
          <td>
            <?php if ($pt['doctor_name']): ?>
              <span class="badge badge-green">Dr. <?= htmlspecialchars($pt['doctor_name']) ?></span>
            <?php else: ?>
              <span class="badge badge-orange">Unassigned</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$pt['doctor_id']): ?>
            <button class="btn-sm btn-blue" onclick="openAssignModal(<?= $pt['id'] ?>, '<?= htmlspecialchars($pt['full_name']) ?>')">Assign</button>
            <?php else: ?>
            <a href="?unassign=1&pid=<?= $pt['id'] ?>&did=<?= $pt['doctor_id'] ?>" class="btn-sm btn-red" onclick="return confirm('Unassign this patient?')">Unassign</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" class="empty-row">No patients registered yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL: Assign Patient -->
<div class="modal-overlay" id="modal-assign-patient">
  <div class="modal">
    <h3>Assign Patient to Doctor</h3>
    <form method="POST">
      <input type="hidden" name="patient_id" id="assign-patient-id"/>
      <div class="form-field"><label class="field-label">Patient</label><input type="text" id="assign-patient-name" class="field-input" disabled/></div>
      <div class="form-field">
        <label class="field-label">Assign to Doctor *</label>
        <select name="doctor_id" class="field-input" required>
          <option value="">Select a doctor</option>
          <?php
          $adocs = $conn->query("SELECT id, full_name, specialty FROM doctors WHERE status='active' ORDER BY full_name");
          if ($adocs): while ($ad = $adocs->fetch_assoc()): ?>
          <option value="<?= $ad['id'] ?>">Dr. <?= htmlspecialchars($ad['full_name']) ?> — <?= htmlspecialchars($ad['specialty'] ?? 'General') ?></option>
          <?php endwhile; endif; ?>
        </select>
      </div>
      <button type="submit" name="assign_patient" class="btn-submit">Assign Patient</button>
      <button type="button" class="btn-cancel" onclick="closeModal('modal-assign-patient')">Cancel</button>
    </form>
  </div>
</div>

<script>
  function openModal(id)  { document.getElementById(id).classList.add('open'); }
  function closeModal(id) { document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('.modal-overlay').forEach(m => { m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }); });
  function openAssignModal(id, name) { document.getElementById('assign-patient-id').value=id; document.getElementById('assign-patient-name').value=name; openModal('modal-assign-patient'); }
  setTimeout(() => { const t=document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>