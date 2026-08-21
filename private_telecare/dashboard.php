<?php
// private_telecare/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
// dashboard.php - Patient's dashboard
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
require_once __DIR__ . '/../includes/header.php';

$parts     = explode(' ', $p['full_name']);
$firstName = $parts[0];
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

:root{
  --tc-red:#B31118; --tc-red-dark:#8a000b;
  --tc-teal:#006a61; --tc-teal-light:#0D9488;
  --tc-ink:#151c27; --tc-muted:rgba(21,28,39,0.55);
  --tc-line:rgba(21,28,39,0.08);
  --tc-red-tint:#FEF2F2; --tc-teal-tint:#ECFDF5;
}

/* ── PAGE LAYOUT ── */
.page {
  max-width: 1160px !important;
  margin: 0 auto !important;
  padding: 1.8rem 2rem 5rem !important;
  background: transparent !important;
  overflow-x: clip;
  font-family:'Inter',sans-serif;
}
.page-head{ margin-bottom:1.5rem; }
.page-title{
  font-family:'Inter',sans-serif; font-weight:800; font-size:1.9rem;
  color:var(--tc-ink); line-height:1.15; margin-bottom:0.3rem;
}
.page-sub{ color:var(--tc-muted); font-size:0.92rem; }

/* ── DASHBOARD GRID ── */
.db-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  grid-template-rows: auto auto;
  gap: 1.1rem;
}
.db-welcome { grid-column: 1 / -1; }
.db-stats { grid-column: 1 / -1; }
.db-appts   { grid-column: 1 / 2; }
.db-doctors { grid-column: 2 / 3; }

/* ── WELCOME CARD ── */
.welcome-banner {
  background: #fff;
  border: 1px solid var(--tc-line);
  box-shadow: 0 2px 12px rgba(21,28,39,0.05);
  border-radius: 16px;
  padding: 1.2rem 1.6rem;
  display: flex; align-items: center; justify-content: space-between;
  gap: 1.2rem; flex-wrap: wrap;
}
.welcome-left{ display:flex; align-items:center; gap:1rem; min-width:0; }
.welcome-avatar{
  width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0; overflow: hidden;
  background: linear-gradient(135deg, var(--tc-teal), var(--tc-teal-light));
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 1.15rem;
}
.welcome-avatar img{ width:100%; height:100%; object-fit:cover; }
.welcome-name {
  font-family: 'Inter', sans-serif;
  font-size: 1.15rem; font-weight: 800;
  color: var(--tc-ink); line-height: 1.2;
}
.welcome-status-line{
  display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap;
  font-size: 0.8rem; color: var(--tc-muted); margin-top: 0.3rem;
}
.welcome-status-dot {
  width: 7px; height: 7px; border-radius: 50%; background: var(--tc-teal-light);
  flex-shrink:0;
}
.welcome-status-text{ color:var(--tc-teal); font-weight:600; }
.welcome-dot-sep{ color:rgba(21,28,39,0.25); }
.welcome-btn {
  display: inline-flex; align-items: center; gap: 0.45rem;
  background: var(--tc-red); color: #fff;
  border: none;
  padding: 0.68rem 1.35rem; border-radius: 10px;
  font-size: 0.85rem; font-weight: 600; text-decoration: none;
  transition: all 0.25s; flex-shrink: 0;
  box-shadow: 0 4px 14px rgba(179,17,24,0.25);
}
.welcome-btn:hover { background: var(--tc-red-dark); transform: translateY(-2px); }
.welcome-btn svg { flex-shrink: 0; }

/* ── STATS ROW ── */
.stats-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.1rem;
}
.stat-card {
  background: #fff;
  border-radius: 16px;
  padding: 1.2rem 1.3rem;
  border: 1px solid var(--tc-line);
  box-shadow: 0 2px 12px rgba(21,28,39,0.05);
  display: flex; flex-direction: column; align-items: flex-start; gap: 0.7rem;
  transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
  position: relative; overflow: hidden;
}
.stat-card::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
  transform: scaleX(0); transform-origin: left;
  transition: transform 0.35s cubic-bezier(0.16,1,0.3,1);
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(21,28,39,0.08); }
.stat-card:hover::after { transform: scaleX(1); }
.stat-card.s-blue::after  { background: var(--tc-teal); }
.stat-card.s-green::after { background: var(--tc-red); }
.stat-card.s-red::after   { background: var(--tc-teal); }
.stat-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-icon svg { width: 18px; height: 18px; }
.si-blue  { background: var(--tc-teal-tint); color: var(--tc-teal); }
.si-green { background: var(--tc-red-tint); color: var(--tc-red); }
.si-red   { background: var(--tc-teal-tint);  color: var(--tc-teal-light); }
.stat-num {
  font-family: 'Inter', sans-serif;
  font-size: 1.9rem; font-weight: 800; line-height: 1;
  color: var(--tc-ink);
}
.stat-num.blue  { color: var(--tc-teal); }
.stat-num.green { color: var(--tc-red); }
.stat-num.red   { color: var(--tc-teal); }
.stat-lbl {
  font-size: 0.72rem; color: var(--tc-muted); margin-top: 0.2rem;
  font-weight: 600;
}

/* ── SECTION CARDS ── */
.db-card {
  background: #fff;
  border-radius: 16px;
  border: 1px solid var(--tc-line);
  box-shadow: 0 2px 12px rgba(21,28,39,0.05);
  overflow: hidden;
  height: 100%;
  display: flex; flex-direction: column;
}
.db-card.teal-card{
  background: var(--tc-teal-tint);
  border-color: rgba(0,106,97,0.15);
}
.db-card-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 1.2rem 1.4rem 0;
}
.db-card-title {
  font-size: 0.95rem; font-weight: 700; color: var(--tc-ink);
  display: flex; align-items: center; gap: 0.5rem;
}
.teal-card .db-card-title{ color: var(--tc-teal); }
.db-card-title svg { width: 16px; height: 16px; flex-shrink: 0; }
.db-card-link {
  font-size: 0.78rem; color: var(--tc-teal); font-weight: 600;
  text-decoration: none; transition: color 0.2s;
  display: flex; align-items: center; gap: 0.28rem;
}
.db-card-link:hover { color: var(--tc-teal-light); }
.db-card-link svg { width: 12px; height: 12px; }
.db-card-body { padding: 0.9rem 1.4rem 1.4rem; flex: 1; }

/* ── APPOINTMENT ITEMS ── */
.appt-item {
  display: flex; align-items: center; gap: 1rem;
  padding: 0.85rem 0;
  border-bottom: 1px solid rgba(21,28,39,0.05);
}
.appt-item:last-child { border-bottom: none; }
.appt-date-box {
  width: 48px; min-width: 48px; height: 56px;
  background: var(--tc-teal-tint);
  border: 1.5px solid rgba(0,106,97,0.18);
  border-radius: 12px;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
}
.appt-date-box .day {
  font-family: 'Inter', sans-serif;
  font-size: 1.2rem; font-weight: 800;
  color: var(--tc-teal); line-height: 1.1;
}
.appt-date-box .mon {
  font-size: 0.6rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--tc-muted);
}
.appt-info { flex: 1; min-width: 0; }
.appt-doctor { font-weight: 700; font-size: 0.92rem; color: var(--tc-ink); }
.appt-meta {
  font-size: 0.76rem; color: var(--tc-muted);
  display: flex; align-items: center; gap: 0.35rem; margin-top: 0.15rem;
}
.appt-meta svg { width: 11px; height: 11px; flex-shrink: 0; }

/* ── BADGES ── */
.badge {
  font-size: 0.64rem; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase; padding: 0.22rem 0.65rem; border-radius: 50px;
  white-space: nowrap; flex-shrink: 0;
}
.badge-green  { background: var(--tc-teal-tint);  color: var(--tc-teal); }
.badge-orange { background: rgba(234,179,8,0.12);  color: #ca8a04; }
.badge-red    { background: var(--tc-red-tint);   color: var(--tc-red); }

/* ── EMPTY STATE ── */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; text-align: center;
  padding: 2.2rem 1rem; color: rgba(21,28,39,0.28);
  gap: 0.7rem;
}
.empty-state svg { width: 34px; height: 34px; opacity: 0.5; }
.empty-state p { font-size: 0.85rem; font-weight: 500; }

/* ── DOCTOR ITEMS ── */
.doc-item {
  display: flex; align-items: center; gap: 1rem;
  padding: 0.85rem 0;
  border-bottom: 1px solid rgba(0,106,97,0.08);
}
.doc-item:last-child { border-bottom: none; }
.doc-avatar {
  width: 48px; height: 48px; border-radius: 13px;
  background: linear-gradient(135deg, var(--tc-teal), var(--tc-teal-light));
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 0.92rem;
  flex-shrink: 0; overflow: hidden;
  box-shadow: 0 4px 14px rgba(0,106,97,0.22);
}
.doc-avatar img { width: 100%; height: 100%; object-fit: cover; }
.doc-info { flex: 1; min-width: 0; }
.doc-name {
  font-weight: 700; font-size: 0.92rem; color: var(--tc-ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.doc-spec { font-size: 0.76rem; color: var(--tc-muted); margin-top: 0.12rem; }
.doc-sub  { font-size: 0.72rem; color: rgba(21,28,39,0.4); font-style: italic; margin-top: 0.06rem; }
.doc-clinic {
  font-size: 0.72rem; color: var(--tc-muted); margin-top: 0.1rem;
  display: flex; align-items: center; gap: 0.3rem;
}
.doc-clinic svg { width: 10px; height: 10px; flex-shrink: 0; }
.doc-right { text-align: right; flex-shrink: 0; }
.doc-fee {
  font-size: 0.85rem; font-weight: 800; color: var(--tc-ink);
  font-family: 'Inter', sans-serif;
}
.doc-avail {
  display: inline-block; margin-top: 0.28rem;
  background: rgba(255,255,255,0.7); color: var(--tc-teal);
  border-radius: 50px; padding: 0.18rem 0.62rem;
  font-size: 0.64rem; font-weight: 700; letter-spacing: 0.05em;
  text-transform: uppercase;
}

/* ── RESPONSIVE ── */
@media (max-width: 900px) {
  .db-grid { grid-template-columns: 1fr; }
  .db-appts, .db-doctors { grid-column: 1 / -1; }
  .topbar{ padding: 1rem 1.1rem; }
  .topbar-search{ max-width: none; }
  .page { padding: 1rem 1rem 6rem !important; }
  .page-title{ font-size: 1.55rem; }
  .welcome-banner{ padding: 1.1rem 1.25rem; }
  .db-card-head { padding: 1rem 1.1rem 0; }
  .db-card-body { padding: 0.8rem 1.1rem 1.1rem; }
  .appt-item, .doc-item { gap: 0.75rem; }
  .doc-name { white-space: normal; }
}
@media (max-width: 600px) {
  .topbar-search{ display:none; }
  .page { padding: 0.75rem 0.75rem 6.2rem !important; }
  .db-grid { gap: 0.8rem; }
  .stats-row { grid-template-columns: 1fr; gap: 0.75rem; }
  .stat-card { padding: 1rem 1rem; border-radius: 14px; }
  .stat-icon { width: 36px; height: 36px; border-radius: 9px; }
  .stat-icon svg { width: 16px; height: 16px; }
  .stat-num { font-size: 1.5rem; }
  .stat-lbl { font-size: 0.65rem; }

  .welcome-banner { border-radius: 14px; flex-direction:column; align-items:flex-start; }
  .welcome-btn{ width:100%; justify-content:center; }
  .welcome-name { font-size: 1.05rem; }

  .db-card { border-radius: 14px; }
  .db-card-head { padding: 0.9rem 0.9rem 0; }
  .db-card-body { padding: 0.65rem 0.9rem 0.9rem; }
  .db-card-link { font-size: 0.72rem; }

  .appt-item, .doc-item { padding: 0.75rem 0; align-items: flex-start; }
  .appt-date-box { width: 44px; min-width: 44px; height: 52px; }
  .appt-date-box .day { font-size: 1.1rem; }
  .appt-doctor, .doc-name { font-size: 0.88rem; }
  .appt-meta, .doc-spec, .doc-sub, .doc-clinic { font-size: 0.72rem; }

  .badge { font-size: 0.6rem; padding: 0.2rem 0.55rem; }
  .doc-right { text-align: left; }
  .doc-fee { font-size: 0.8rem; }
  .doc-avail { font-size: 0.6rem; padding: 0.16rem 0.52rem; }

  .empty-state { padding: 1.5rem 0.5rem; }
}
</style>

<div class="page">

  <div class="page-head">
    <div class="page-title">Welcome back, <?= htmlspecialchars($firstName) ?></div>
    <p class="page-sub">Here's an overview of your appointments and consultations.</p>
  </div>

<div class="db-grid">

  <!-- ══ WELCOME / PROFILE CARD ══ -->
  <div class="db-welcome">
    <div class="welcome-banner">
      <div class="welcome-left">
        <div class="welcome-avatar">
          <?php if (!empty($p['profile_photo'])): ?>
            <img src="<?= htmlspecialchars($p['profile_photo']) ?>" alt=""/>
          <?php else: ?>
            <?= strtoupper(substr($p['full_name'], 0, 2)) ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="welcome-name"><?= htmlspecialchars($p['full_name']) ?></div>
          <div class="welcome-status-line">
            <span class="welcome-status-dot"></span>
            <span class="welcome-status-text">Account Active</span>
            <span class="welcome-dot-sep">&middot;</span>
            <span id="wb-date">--</span>
            <span class="welcome-dot-sep">&middot;</span>
            <span id="wb-time">--:--</span>
          </div>
        </div>
      </div>
      <a href="router.php?page=visits" class="welcome-btn">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
        Book Appointment
      </a>
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
        <a href="router.php?page=visits" class="db-card-link">
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
              <span style="color:rgba(21,28,39,0.15);">&middot;</span>
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
    <div class="db-card teal-card">
      <div class="db-card-head">
        <div class="db-card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Recommended Doctors
        </div>
        <a href="router.php?page=visits" class="db-card-link">
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
/* ── Live date/time in welcome card ── */
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

<?php require_once __DIR__ . '/../includes/nav.php'; ?>
</body>
</html>









