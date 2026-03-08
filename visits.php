<?php
require_once 'includes/auth.php';

$visits_upcoming = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC
");
$visits_past = $conn->query("
    SELECT a.*, d.full_name AS doctor_name, d.specialty
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.patient_id=$patient_id AND a.appointment_date < CURDATE()
    ORDER BY a.appointment_date DESC
");

$page_title = 'My Visits — TELE-CARE';
$active_nav = 'visits';
require_once 'includes/header.php';
?>

<style>
  .inner-tabs { display:flex; gap:0.5rem; margin-bottom:1.2rem; }
  .inner-tab {
    flex:1; padding:0.6rem; border-radius:50px;
    border:1.5px solid rgba(63,130,227,0.15); background:transparent;
    cursor:pointer; font-family:'DM Sans',sans-serif;
    font-size:0.82rem; font-weight:600; color:var(--muted); transition:all 0.2s;
  }
  .inner-tab.active { background:var(--blue); color:#fff; border-color:var(--blue); }
</style>

<div class="page">
  <h2 style="font-size:1.5rem;margin-bottom:1.2rem;">My Appointments</h2>

  <div class="inner-tabs">
    <button class="inner-tab active" id="btn-upcoming" onclick="switchTab('upcoming')">Upcoming</button>
    <button class="inner-tab"        id="btn-past"     onclick="switchTab('past')">Past</button>
  </div>

  <!-- Upcoming -->
  <div id="visits-upcoming">
    <div class="card" style="padding:0.5rem 1.4rem;">
      <?php
      $has = false;
      if ($visits_upcoming && $visits_upcoming->num_rows > 0):
        while ($a = $visits_upcoming->fetch_assoc()):
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
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($a['specialty'] ?? '') ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
          <?php if (!empty($a['notes'])): ?>
          <div style="font-size:0.78rem;color:#9ab0ae;margin-top:0.2rem;">📝 <?= htmlspecialchars($a['notes']) ?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;">
          <span class="badge <?= $a['status']==='Confirmed' ? 'badge-green' : ($a['status']==='Pending' ? 'badge-orange' : 'badge-red') ?>"><?= $a['status'] ?></span>
          <span class="badge <?= $a['payment_status']==='Paid' ? 'badge-green' : 'badge-red' ?>"><?= $a['payment_status'] ?></span>
        </div>
      </div>
      <?php endwhile; endif; ?>
      <?php if (!$has): ?>
      <div class="empty-state">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        No upcoming appointments.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Past -->
  <div id="visits-past" style="display:none;">
    <div class="card" style="padding:0.5rem 1.4rem;">
      <?php
      $has = false;
      if ($visits_past && $visits_past->num_rows > 0):
        while ($a = $visits_past->fetch_assoc()):
          $has = true;
          $d   = new DateTime($a['appointment_date']);
      ?>
      <div class="appt-item">
        <div class="appt-date-box" style="background:rgba(36,68,65,0.04);">
          <div class="day" style="color:#9ab0ae;"><?= $d->format('d') ?></div>
          <div class="mon"><?= $d->format('M') ?></div>
        </div>
        <div style="flex:1;">
          <div style="font-weight:600;font-size:0.92rem;">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type']) ?></div>
        </div>
        <span class="badge <?= $a['status']==='Completed' ? 'badge-green' : 'badge-red' ?>"><?= $a['status'] ?></span>
      </div>
      <?php endwhile; endif; ?>
      <?php if (!$has): ?>
      <div class="empty-state">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        No past appointments.
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function switchTab(type) {
  document.getElementById('visits-upcoming').style.display = type === 'upcoming' ? 'block' : 'none';
  document.getElementById('visits-past').style.display     = type === 'past'     ? 'block' : 'none';
  document.getElementById('btn-upcoming').classList.toggle('active', type === 'upcoming');
  document.getElementById('btn-past').classList.toggle('active',     type === 'past');
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>