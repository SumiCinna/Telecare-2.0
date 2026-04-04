<?php
require_once 'includes/auth.php';

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

// ── Recommended doctors (active, available, random 3) ──
$recommended = $conn->query("
    SELECT * FROM doctors
    WHERE status = 'active' AND is_available = 1
    ORDER BY RAND()
    LIMIT 3
");

$page_title = 'Home — TELE-CARE';
$active_nav = 'home';
require_once 'includes/header.php';
?>

<style>
/* ── PAGE LAYOUT OVERRIDES ── */
.page {
  max-width: 1160px !important;
  margin: 0 auto !important;
  padding: 1.8rem 2rem 5rem !important;
  background: transparent !important;
}

/* ── DASHBOARD GRID ── */
.db-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: auto auto;
  gap: 1.1rem;
}

/* Welcome spans full width */
.db-welcome { grid-column: 1 / -1; }

/* Stats spans full width */
.db-stats { grid-column: 1 / -1; }

/* Appointments: left | Doctors: right */
.db-appts   { grid-column: 1 / 2; }
.db-doctors { grid-column: 2 / 3; }

/* ── WELCOME BANNER ── */
.welcome-banner {
  background: linear-gradient(135deg, #3F82E3 0%, #2563C4 60%, #1a4fa8 100%);
  border-radius: 18px;
  padding: 0;
  overflow: hidden;
  position: relative;
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: stretch;
  min-height: 140px;
}
.welcome-banner-left {
  padding: 1.8rem 2rem;
  position: relative; z-index: 2;
  display: flex; flex-direction: column; justify-content: center;
}
.welcome-banner-right {
  padding: 1.8rem 2rem;
  position: relative; z-index: 2;
  display: flex; flex-direction: column; justify-content: center; align-items: flex-end; gap: 0.5rem;
  border-left: 1px solid rgba(255,255,255,0.1);
  min-width: 220px;
}
.welcome-orb-1 {
  position: absolute; top: -40px; right: 200px;
  width: 180px; height: 180px; border-radius: 50%;
  background: rgba(255,255,255,0.07); pointer-events: none;
}
.welcome-orb-2 {
  position: absolute; bottom: -50px; right: 100px;
  width: 140px; height: 140px; border-radius: 50%;
  background: rgba(255,255,255,0.05); pointer-events: none;
}
.welcome-orb-3 {
  position: absolute; top: -20px; left: 45%;
  width: 90px; height: 90px; border-radius: 50%;
  background: rgba(255,255,255,0.04); pointer-events: none;
}
.welcome-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.9rem; font-weight: 900;
  color: #fff; line-height: 1.1; margin-bottom: 0.4rem;
}
.welcome-sub {
  color: rgba(255,255,255,0.72); font-size: 0.88rem; line-height: 1.5; margin-bottom: 1.1rem;
}
.welcome-btn {
  display: inline-flex; align-items: center; gap: 0.45rem;
  background: rgba(255,255,255,0.18); color: #fff;
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(255,255,255,0.28);
  padding: 0.58rem 1.25rem; border-radius: 50px;
  font-size: 0.82rem; font-weight: 600; text-decoration: none;
  transition: all 0.25s; align-self: flex-start;
}
.welcome-btn:hover { background: rgba(255,255,255,0.28); transform: translateY(-2px); }
.welcome-btn svg { flex-shrink: 0; }
.welcome-date {
  font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
  text-transform: uppercase; color: rgba(255,255,255,0.5);
  font-family: 'DM Mono', monospace;
}
.welcome-time {
  font-family: 'Playfair Display', serif;
  font-size: 2rem; font-weight: 900; color: #fff; line-height: 1;
}
.welcome-status {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: rgba(34,197,94,0.2); border: 1px solid rgba(34,197,94,0.3);
  color: #86efac; font-size: 0.72rem; font-weight: 700;
  padding: 0.3rem 0.75rem; border-radius: 50px;
}
.welcome-status-dot {
  width: 5px; height: 5px; border-radius: 50%; background: #22c55e;
  animation: pulse-dot 2s infinite;
}
@keyframes pulse-dot {
  0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.5); }
  50%      { box-shadow: 0 0 0 5px rgba(34,197,94,0); }
}

/* ── STATS ROW ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.1rem;
}
.stat-card {
  background: #fff;
  border-radius: 16px;
  padding: 1.3rem 1.5rem;
  border: 1px solid rgba(36,68,65,0.08);
  box-shadow: 0 2px 12px rgba(36,68,65,0.05);
  display: flex; align-items: center; gap: 1.1rem;
  transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
  position: relative; overflow: hidden;
}
.stat-card::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
  transform: scaleX(0); transform-origin: left;
  transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(36,68,65,0.1); }
.stat-card:hover::after { transform: scaleX(1); }
.stat-card.s-blue::after  { background: linear-gradient(90deg,#3F82E3,#2563C4); }
.stat-card.s-green::after { background: linear-gradient(90deg,#244441,#2e5550); }
.stat-card.s-red::after   { background: linear-gradient(90deg,#C33643,#8a1f2a); }
.stat-icon {
  width: 48px; height: 48px; border-radius: 13px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-icon svg { width: 22px; height: 22px; }
.si-blue  { background: rgba(63,130,227,0.1); color: #3F82E3; }
.si-green { background: rgba(36,68,65,0.09); color: #244441; }
.si-red   { background: rgba(195,54,67,0.1);  color: #C33643; }
.stat-num {
  font-family: 'Playfair Display', serif;
  font-size: 2rem; font-weight: 900; line-height: 1;
  color: #244441;
}
.stat-num.blue  { color: #3F82E3; }
.stat-num.green { color: #244441; }
.stat-num.red   { color: #C33643; }
.stat-lbl {
  font-size: 0.7rem; color: #9ab0ae; margin-top: 0.22rem;
  font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
}

/* ── SECTION CARDS ── */
.db-card {
  background: #fff;
  border-radius: 18px;
  border: 1px solid rgba(36,68,65,0.08);
  box-shadow: 0 2px 16px rgba(36,68,65,0.05);
  overflow: hidden;
  height: 100%;
  display: flex; flex-direction: column;
}
.db-card-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.2rem 1.5rem 0;
}
.db-card-title {
  font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.12em; color: #9ab0ae;
  display: flex; align-items: center; gap: 0.5rem;
}
.db-card-title svg { width: 13px; height: 13px; }
.db-card-link {
  font-size: 0.78rem; color: #3F82E3; font-weight: 600;
  text-decoration: none; transition: color 0.2s;
  display: flex; align-items: center; gap: 0.28rem;
}
.db-card-link:hover { color: #2563C4; }
.db-card-link svg { width: 12px; height: 12px; }
.db-card-body { padding: 0.9rem 1.5rem 1.4rem; flex: 1; }

/* ── APPOINTMENT ITEMS ── */
.appt-item {
  display: flex; align-items: center; gap: 1rem;
  padding: 0.85rem 0;
  border-bottom: 1px solid rgba(36,68,65,0.05);
  transition: background 0.2s;
}
.appt-item:last-child { border-bottom: none; }
.appt-date-box {
  width: 48px; min-width: 48px; height: 56px;
  background: rgba(63,130,227,0.07);
  border: 1.5px solid rgba(63,130,227,0.15);
  border-radius: 12px;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 0;
}
.appt-date-box .day {
  font-family: 'Playfair Display', serif;
  font-size: 1.25rem; font-weight: 900;
  color: #3F82E3; line-height: 1.1;
}
.appt-date-box .mon {
  font-size: 0.6rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: #9ab0ae;
}
.appt-info { flex: 1; min-width: 0; }
.appt-doctor { font-weight: 700; font-size: 0.92rem; color: #244441; }
.appt-meta {
  font-size: 0.76rem; color: #9ab0ae;
  display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem;
}
.appt-meta svg { width: 11px; height: 11px; flex-shrink: 0; }

/* ── BADGES ── */
.badge {
  font-size: 0.64rem; font-weight: 700; letter-spacing: 0.07em;
  text-transform: uppercase; padding: 0.22rem 0.65rem; border-radius: 50px;
  white-space: nowrap; flex-shrink: 0;
}
.badge-green  { background: rgba(34,197,94,0.12);  color: #16a34a; }
.badge-orange { background: rgba(234,179,8,0.12);  color: #ca8a04; }
.badge-red    { background: rgba(195,54,67,0.1);   color: #C33643; }

/* ── EMPTY STATE ── */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; text-align: center;
  padding: 2.2rem 1rem; color: #b8cccb;
  gap: 0.7rem;
}
.empty-state svg { width: 36px; height: 36px; opacity: 0.35; }
.empty-state p { font-size: 0.85rem; font-weight: 500; }

/* ── DOCTOR ITEMS ── */
.doc-item {
  display: flex; align-items: center; gap: 1rem;
  padding: 0.85rem 0;
  border-bottom: 1px solid rgba(36,68,65,0.05);
  transition: all 0.22s;
}
.doc-item:last-child { border-bottom: none; }
.doc-avatar {
  width: 48px; height: 48px; border-radius: 13px;
  background: linear-gradient(135deg, #3F82E3, #2563C4);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 0.92rem;
  flex-shrink: 0; overflow: hidden;
  box-shadow: 0 4px 14px rgba(63,130,227,0.22);
}
.doc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.doc-info { flex: 1; min-width: 0; }
.doc-name {
  font-weight: 700; font-size: 0.92rem; color: #244441;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.doc-spec { font-size: 0.76rem; color: #9ab0ae; margin-top: 0.12rem; }
.doc-sub  { font-size: 0.72rem; color: #b8cccb; font-style: italic; margin-top: 0.06rem; }
.doc-clinic {
  font-size: 0.72rem; color: #9ab0ae; margin-top: 0.1rem;
  display: flex; align-items: center; gap: 0.3rem;
}
.doc-clinic svg { width: 10px; height: 10px; flex-shrink: 0; }
.doc-right { text-align: right; flex-shrink: 0; }
.doc-fee {
  font-size: 0.85rem; font-weight: 800; color: #244441;
  font-family: 'DM Sans', sans-serif;
}
.doc-avail {
  display: inline-block; margin-top: 0.28rem;
  background: rgba(34,197,94,0.1); color: #16a34a;
  border-radius: 50px; padding: 0.18rem 0.62rem;
  font-size: 0.65rem; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
  .db-grid { grid-template-columns: 1fr; }
  .db-appts, .db-doctors { grid-column: 1 / -1; }
  .welcome-banner { grid-template-columns: 1fr; }
  .welcome-banner-right { display: none; }
  .page { padding: 1rem 1rem 5rem !important; }
}
@media (max-width: 600px) {
  .stats-row { grid-template-columns: 1fr; }
}
</style>

<div class="page">
<div class="db-grid">

  <!-- ══ WELCOME BANNER ══ -->
  <div class="db-welcome">
    <div class="welcome-banner">
      <div class="welcome-orb-1"></div>
      <div class="welcome-orb-2"></div>
      <div class="welcome-orb-3"></div>

      <div class="welcome-banner-left">
        <?php
          $parts     = explode(' ', $p['full_name']);
          $firstName = $parts[0];
        ?>
        <div class="welcome-name">
          Welcome back,<br><?= htmlspecialchars($firstName) ?>.
        </div>
        <p class="welcome-sub">Manage your appointments and consultations all in one place.</p>
        <a href="visits.php" class="welcome-btn">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
          Book Appointment
        </a>
      </div>

      <div class="welcome-banner-right">
        <div class="welcome-date" id="wb-date">--</div>
        <div class="welcome-time" id="wb-time">--:--</div>
        <div class="welcome-status">
          <span class="welcome-status-dot"></span>
          Account Active
        </div>
      </div>
    </div>
  </div>

  <!-- ══ STATS ══ -->
  <div class="db-stats">
    <div class="stats-row">

      <div class="stat-card s-blue">
        <div class="stat-icon si-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <div>
          <div class="stat-num blue"><?= $upcoming_count ?></div>
          <div class="stat-lbl">Upcoming</div>
        </div>
      </div>

      <div class="stat-card s-green">
        <div class="stat-icon si-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <div>
          <div class="stat-num green"><?= $prescription_count ?></div>
          <div class="stat-lbl">Prescriptions</div>
        </div>
      </div>

      <div class="stat-card s-red">
        <div class="stat-icon si-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87V15.13a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
        </div>
        <div>
          <div class="stat-num red"><?= $completed_count ?></div>
          <div class="stat-lbl">Consultations</div>
        </div>
      </div>

    </div>
  </div>

  <!-- ══ UPCOMING APPOINTMENTS ══ -->
  <div class="db-appts">
    <div class="db-card">
      <div class="db-card-head">
        <div class="db-card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          Upcoming Appointments
        </div>
        <a href="visits.php" class="db-card-link">
          See all
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
      <div class="db-card-body">
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
          <div class="appt-info">
            <div class="appt-doctor">Dr. <?= htmlspecialchars($a['doctor_name']) ?></div>
            <div class="appt-meta">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              <?= date('g:i A', strtotime($a['appointment_time'])) ?>
              <span style="color:rgba(36,68,65,0.15);">·</span>
              <?= htmlspecialchars($a['type']) ?>
            </div>
          </div>
          <span class="badge <?= $a['status']==='Confirmed' ? 'badge-green' : ($a['status']==='Pending' ? 'badge-orange' : 'badge-red') ?>">
            <?= $a['status'] ?>
          </span>
        </div>
        <?php endwhile; endif; ?>
        <?php if (!$has): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          <p>No upcoming appointments.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ══ RECOMMENDED DOCTORS ══ -->
  <div class="db-doctors">
    <div class="db-card">
      <div class="db-card-head">
        <div class="db-card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Recommended Doctors
        </div>
        <a href="visits.php" class="db-card-link">
          Book now
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a>
      </div>
      <div class="db-card-body">
        <?php if ($recommended && $recommended->num_rows > 0): ?>
          <?php while ($doc = $recommended->fetch_assoc()): ?>
          <div class="doc-item">
            <div class="doc-avatar">
              <?php if (!empty($doc['profile_photo'])): ?>
                <img src="../<?= htmlspecialchars($doc['profile_photo']) ?>" alt=""/>
              <?php else: ?>
                <?= strtoupper(substr($doc['full_name'], 0, 2)) ?>
              <?php endif; ?>
            </div>
            <div class="doc-info">
              <div class="doc-name">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
              <div class="doc-spec"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
              <?php if (!empty($doc['subspecialty'])): ?>
              <div class="doc-sub"><?= htmlspecialchars($doc['subspecialty']) ?></div>
              <?php endif; ?>
              <?php if (!empty($doc['clinic_name'])): ?>
              <div class="doc-clinic">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= htmlspecialchars($doc['clinic_name']) ?>
              </div>
              <?php endif; ?>
            </div>
            <div class="doc-right">
              <?php if (!empty($doc['consultation_fee']) && $doc['consultation_fee'] > 0): ?>
              <div class="doc-fee">&#8369;<?= number_format($doc['consultation_fee'], 0) ?></div>
              <?php endif; ?>
              <span class="doc-avail">Available</span>
            </div>
          </div>
          <?php endwhile; ?>
        <?php else: ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <p>No doctors available right now.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div><!-- /db-grid -->
</div><!-- /page -->

<script>
/* ── Live clock in welcome banner ── */
function updateClock() {
  const now  = new Date();
  const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const mons = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const h    = now.getHours(), m = now.getMinutes();
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hh   = h % 12 || 12;
  const mm   = String(m).padStart(2, '0');
  const el_t = document.getElementById('wb-time');
  const el_d = document.getElementById('wb-date');
  if (el_t) el_t.textContent = `${hh}:${mm} ${ampm}`;
  if (el_d) el_d.textContent = `${days[now.getDay()]}, ${mons[now.getMonth()]} ${now.getDate()}`;
}
updateClock();
setInterval(updateClock, 1000);

/* ── Stat counter animation ── */
document.querySelectorAll('.stat-num').forEach(el => {
  const target = parseInt(el.textContent, 10);
  if (isNaN(target) || target === 0) return;
  let start = 0;
  const dur = 900, step = 16;
  const inc = target / (dur / step);
  const timer = setInterval(() => {
    start = Math.min(start + inc, target);
    el.textContent = Math.round(start);
    if (start >= target) clearInterval(timer);
  }, step);
});
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>