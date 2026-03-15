<?php
require_once 'includes/auth.php';

// ── Fetch assigned doctor ──
$doc = null;
$dr  = $conn->query("
    SELECT d.* FROM doctors d
    JOIN patient_doctors pd ON pd.doctor_id = d.id
    WHERE pd.patient_id = $patient_id LIMIT 1
");
if ($dr && $dr->num_rows > 0) $doc = $dr->fetch_assoc();

// ── Stats ──
$upcoming_count     = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$patient_id AND status IN ('Pending','Confirmed') AND appointment_date >= CURDATE()")->fetch_assoc()['c'];
$prescription_count = $conn->query("SELECT COUNT(*) c FROM prescriptions WHERE patient_id=$patient_id AND status='Active'")->fetch_assoc()['c'];
$completed_count    = $conn->query("SELECT COUNT(*) c FROM appointments WHERE patient_id=$patient_id AND status='Completed'")->fetch_assoc()['c'];

// ── Upcoming appointments (max 3) ──
$upcoming = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id AND a.status IN ('Pending','Confirmed') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 3
");

$page_title = 'Home — TELE-CARE';
$active_nav = 'home';
require_once 'includes/header.php';
?>

<div class="page">

  <!-- Welcome Banner -->
  <div style="background:linear-gradient(135deg,#3F82E3,#2563C4);border-radius:20px;padding:1.6rem;margin-bottom:1.2rem;position:relative;overflow:hidden;">
    <div style="position:absolute;inset:0;background-image:radial-gradient(circle at 80% 20%,rgba(255,255,255,0.15) 0%,transparent 50%),radial-gradient(circle at 20% 80%,rgba(255,255,255,0.08) 0%,transparent 40%);pointer-events:none;"></div>
    <div style="position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.07);pointer-events:none;"></div>
    <?php
      $firstName  = explode(' ', $p['full_name']);
      $firstName  = $firstName[0];
    ?>
    <h2 style="font-size:1.5rem;color:#fff;margin-bottom:0.3rem;position:relative;z-index:1;">
      Welcome back,<br/><?= htmlspecialchars($firstName) ?>.
    </h2>
    <p style="color:rgba(255,255,255,0.75);font-size:0.85rem;position:relative;z-index:1;margin-bottom:1.2rem;">
      <?php if ($doc):
        $docParts   = explode(' ', $doc['full_name']);
        $docLastName = end($docParts);
      ?>
        Your health is being looked after by Dr. <?= htmlspecialchars($docLastName) ?>.
      <?php else: ?>
        No doctor assigned yet. Contact your admin.
      <?php endif; ?>
    </p>
    <a href="visits.php" style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(255,255,255,0.2);color:#fff;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.3);padding:0.55rem 1.2rem;border-radius:50px;font-size:0.82rem;font-weight:600;text-decoration:none;position:relative;z-index:1;">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
      Book Appointment
    </a>
  </div>

  <!-- Quick Stats -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.8rem;margin-bottom:1.2rem;">
    <?php
    $stats = [
      ['num' => $upcoming_count,     'lbl' => 'Upcoming'],
      ['num' => $prescription_count, 'lbl' => 'Prescriptions'],
      ['num' => $completed_count,    'lbl' => 'Consultations'],
    ];
    foreach ($stats as $s): ?>
    <div class="card" style="text-align:center;padding:1rem 0.8rem;margin-bottom:0;">
      <div style="font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--blue);line-height:1;"><?= $s['num'] ?></div>
      <div style="font-size:0.68rem;color:var(--muted);margin-top:0.3rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;"><?= $s['lbl'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Assigned Doctor -->
  <?php if ($doc): ?>
  <div class="card">
    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#9ab0ae;margin-bottom:1rem;">My Doctor</div>
    <div style="display:flex;align-items:center;gap:1rem;">
      <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--blue),var(--blue-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;overflow:hidden;">
        <?php if (!empty($doc['profile_photo'])): ?>
          <img src="<?= htmlspecialchars($doc['profile_photo']) ?>" style="width:100%;height:100%;object-fit:cover;"/>
        <?php else: ?>
          <?= strtoupper(substr($doc['full_name'], 0, 2)) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:1rem;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
        <div style="font-size:0.82rem;color:#9ab0ae;"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
        <?php if (!empty($doc['clinic_name'])): ?>
        <div style="font-size:0.78rem;color:#9ab0ae;margin-top:0.2rem;">📍 <?= htmlspecialchars($doc['clinic_name']) ?></div>
        <?php endif; ?>
      </div>
      <?php if (!empty($doc['rating']) && $doc['rating'] > 0): ?>
      <div style="font-weight:700;color:var(--green);">⭐ <?= number_format($doc['rating'], 1) ?></div>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:0.7rem;margin-top:1.2rem;">
      <a href="chat.php" style="flex:1;text-align:center;padding:0.6rem;border-radius:12px;background:var(--green);color:#fff;font-size:0.85rem;font-weight:600;text-decoration:none;">Message</a>
      <a href="visits.php" style="flex:1;text-align:center;padding:0.6rem;border-radius:12px;border:1.5px solid rgba(36,68,65,0.15);font-size:0.85rem;font-weight:600;color:var(--green);text-decoration:none;">Book Visit</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Upcoming Appointments -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
      <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:#9ab0ae;">Upcoming Appointments</div>
      <a href="visits.php" style="font-size:0.78rem;color:var(--blue);font-weight:600;text-decoration:none;">See all</a>
    </div>
    <?php
    $has = false;
    if ($upcoming && $upcoming->num_rows > 0):
      while ($a = $upcoming->fetch_assoc()):
        $has = true;
        $d   = new DateTime($a['appointment_date']);
    ?>
    <div class="appt-item">
      <div class="appt-date-box">
        <div class="day"><?= $d->format('d') ?></div>
        <div class="mon"><?= $d->format('M') ?></div>
      </div>
      <div style="flex:1;">
        <div style="font-weight:600;font-size:0.92rem;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
        <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
      </div>
      <span class="badge <?= $a['status']==='Confirmed' ? 'badge-green' : ($a['status']==='Pending' ? 'badge-orange' : 'badge-red') ?>">
        <?= $a['status'] ?>
      </span>
    </div>
    <?php endwhile; endif; ?>
    <?php if (!$has): ?>
    <div class="empty-state" style="padding:1.5rem;">No upcoming appointments.</div>
    <?php endif; ?>
  </div>

      

</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>