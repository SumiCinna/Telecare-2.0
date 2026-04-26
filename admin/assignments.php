<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once '../database/config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

// ── Fetch all active doctors ──
$doctors = $conn->query("SELECT * FROM doctors WHERE status='active' ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Appointments by Doctor — TELE-CARE</title>
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

    /* Doctor card */
    .doc-card{background:var(--white);border-radius:18px;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:1.5rem;overflow:hidden}
    .doc-header{display:flex;align-items:center;gap:1rem;padding:1.2rem 1.5rem;border-bottom:1px solid rgba(36,68,65,0.07);cursor:pointer;user-select:none}
    .doc-header:hover{background:rgba(36,68,65,0.02)}
    .doc-avatar{width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--blue),#2563C4);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;flex-shrink:0;overflow:hidden}
    .doc-avatar img{width:100%;height:100%;object-fit:cover}
    .chevron{margin-left:auto;transition:transform 0.25s;color:#9ab0ae}
    .doc-card.open .chevron{transform:rotate(180deg)}

    /* Tabs */
    .appt-body{display:none;padding:0 1.5rem 1.2rem}
    .doc-card.open .appt-body{display:block}
    .tabs{display:flex;gap:0;border-bottom:1px solid rgba(36,68,65,0.08);margin-bottom:1rem;margin-top:1rem}
    .tab-btn{padding:0.55rem 1.1rem;font-size:0.8rem;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;border:none;background:none;cursor:pointer;color:#9ab0ae;font-family:'DM Sans',sans-serif;border-bottom:2px solid transparent;transition:all 0.2s;margin-bottom:-1px}
    .tab-btn.active{color:var(--green);border-bottom-color:var(--green)}
    .tab-btn:hover:not(.active){color:var(--green)}
    .tab-panel{display:none}.tab-panel.active{display:block}

    /* Appointment rows */
    .appt-row{display:flex;align-items:center;gap:1rem;padding:0.75rem 0;border-bottom:1px solid rgba(36,68,65,0.05)}
    .appt-row:last-child{border-bottom:none}
    .appt-date{min-width:80px;text-align:center;background:rgba(36,68,65,0.05);border-radius:10px;padding:0.4rem 0.5rem}
    .appt-date .day{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;line-height:1;color:var(--green)}
    .appt-date .mon{font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#9ab0ae;margin-top:0.1rem}
    .empty-appt{padding:1.5rem 0;text-align:center;color:#9ab0ae;font-size:0.85rem}

    .badge{display:inline-block;padding:0.22rem 0.65rem;border-radius:50px;font-size:0.7rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-red{background:rgba(195,54,67,0.1);color:var(--red)}
    .badge-blue{background:rgba(63,130,227,0.1);color:var(--blue)}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}

  .appt-pagination{display:flex;align-items:center;justify-content:center;gap:0.35rem;margin-top:0.9rem;flex-wrap:wrap}
  .page-btn{border:1px solid rgba(36,68,65,0.15);background:#fff;color:var(--green);font-family:'DM Sans',sans-serif;font-size:0.76rem;font-weight:600;border-radius:8px;padding:0.3rem 0.55rem;cursor:pointer;min-width:32px;transition:all 0.18s}
  .page-btn:hover{border-color:var(--green)}
  .page-btn.active{background:var(--green);color:#fff;border-color:var(--green)}
  .page-btn:disabled{opacity:0.45;cursor:not-allowed}

    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}
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
    <a href="patients.php" class="nav-link">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Patients
    </a>
    <a href="assignments.php" class="nav-link active">
      <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Appointments
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
      <div style="font-size:0.95rem;font-weight:700;">Appointments by Doctor</div>
    </div>
    <span style="font-size:0.82rem;color:#9ab0ae;">Click a doctor to expand their appointments</span>
  </div>

  <div class="page-content">

    <?php if (!$doctors || $doctors->num_rows === 0): ?>
      <div style="text-align:center;padding:4rem;color:#9ab0ae;font-size:0.9rem;">No active doctors found.</div>
    <?php else: while ($doc = $doctors->fetch_assoc()):

      $did = (int)$doc['id'];

      // Upcoming: today or future, Pending or Confirmed
      $upcoming = $conn->query("
          SELECT a.*, p.full_name AS patient_name
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
          WHERE a.doctor_id = $did
            AND a.status IN ('Pending','Confirmed')
            AND a.appointment_date >= CURDATE()
          ORDER BY a.appointment_date ASC, a.appointment_time ASC
      ");

      // Ongoing / today's confirmed
      $today = $conn->query("
          SELECT a.*, p.full_name AS patient_name
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
          WHERE a.doctor_id = $did
            AND a.status = 'Confirmed'
            AND a.appointment_date = CURDATE()
          ORDER BY a.appointment_time ASC
      ");

      // Past: completed or cancelled
      $past = $conn->query("
          SELECT a.*, p.full_name AS patient_name
          FROM appointments a
          JOIN patients p ON p.id = a.patient_id
          WHERE a.doctor_id = $did
            AND (a.status IN ('Completed','Cancelled')
                 OR (a.appointment_date < CURDATE() AND a.status NOT IN ('Pending','Confirmed')))
      ORDER BY a.appointment_date DESC, a.appointment_time DESC
      ");

      $upCnt   = $upcoming ? $upcoming->num_rows : 0;
      $todayCnt = $today   ? $today->num_rows    : 0;
      $pastCnt = $past     ? $past->num_rows      : 0;
      $totalCnt = $upCnt + $pastCnt;
    ?>
    <div class="doc-card" id="card-<?= $did ?>">
      <div class="doc-header" onclick="toggleCard(<?= $did ?>)">
        <div class="doc-avatar">
          <?php if (!empty($doc['profile_photo'])): ?>
            <img src="../<?= htmlspecialchars($doc['profile_photo']) ?>"/>
          <?php else: ?>
            <?= strtoupper(substr($doc['full_name'], 0, 2)) ?>
          <?php endif; ?>
        </div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:0.97rem;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
          <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($doc['specialty'] ?? '—') ?><?= !empty($doc['subspecialty']) ? ' · ' . htmlspecialchars($doc['subspecialty']) : '' ?></div>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;margin-right:0.8rem;">
          <?php if ($todayCnt > 0): ?>
            <span class="badge badge-green"><?= $todayCnt ?> today</span>
          <?php endif; ?>
          <?php if ($upCnt > 0): ?>
            <span class="badge badge-blue"><?= $upCnt ?> upcoming</span>
          <?php endif; ?>
          <span class="badge badge-gray"><?= $totalCnt ?> total</span>
        </div>
        <svg class="chevron" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
      </div>

      <div class="appt-body">
        <div class="tabs">
          <button class="tab-btn active" onclick="switchTab(<?= $did ?>, 'upcoming', this)">Upcoming</button>
          <button class="tab-btn"        onclick="switchTab(<?= $did ?>, 'today',    this)">Today</button>
          <button class="tab-btn"        onclick="switchTab(<?= $did ?>, 'past',     this)">Past</button>
        </div>

        <!-- UPCOMING -->
        <div class="tab-panel active" id="tab-<?= $did ?>-upcoming">
          <?php
          $upcoming->data_seek(0);
          $has = false;
          while ($a = $upcoming->fetch_assoc()):
            $has = true;
            $d   = new DateTime($a['appointment_date']);
          ?>
          <div class="appt-row">
            <div class="appt-date">
              <div class="day"><?= $d->format('d') ?></div>
              <div class="mon"><?= $d->format('M') ?></div>
            </div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($a['patient_name']) ?></div>
              <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type'] ?? 'Consultation') ?></div>
            </div>
            <span class="badge <?= $a['status']==='Confirmed' ? 'badge-green' : 'badge-orange' ?>"><?= $a['status'] ?></span>
          </div>
          <?php endwhile; ?>
          <?php if (!$has): ?><div class="empty-appt">No upcoming appointments.</div><?php endif; ?>
          <div class="appt-pagination" id="pager-<?= $did ?>-upcoming"></div>
        </div>

        <!-- TODAY -->
        <div class="tab-panel" id="tab-<?= $did ?>-today">
          <?php
          $today->data_seek(0);
          $has = false;
          while ($a = $today->fetch_assoc()):
            $has = true;
            $d   = new DateTime($a['appointment_date']);
          ?>
          <div class="appt-row">
            <div class="appt-date">
              <div class="day"><?= $d->format('d') ?></div>
              <div class="mon"><?= $d->format('M') ?></div>
            </div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($a['patient_name']) ?></div>
              <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type'] ?? 'Consultation') ?></div>
            </div>
            <span class="badge badge-green">Confirmed</span>
          </div>
          <?php endwhile; ?>
          <?php if (!$has): ?><div class="empty-appt">No appointments today.</div><?php endif; ?>
          <div class="appt-pagination" id="pager-<?= $did ?>-today"></div>
        </div>

        <!-- PAST -->
        <div class="tab-panel" id="tab-<?= $did ?>-past">
          <?php
          $past->data_seek(0);
          $has = false;
          while ($a = $past->fetch_assoc()):
            $has = true;
            $d   = new DateTime($a['appointment_date']);
          ?>
          <div class="appt-row">
            <div class="appt-date" style="opacity:0.6;">
              <div class="day"><?= $d->format('d') ?></div>
              <div class="mon"><?= $d->format('M') ?></div>
            </div>
            <div style="flex:1;">
              <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($a['patient_name']) ?></div>
              <div style="font-size:0.78rem;color:#9ab0ae;"><?= date('g:i A', strtotime($a['appointment_time'])) ?> · <?= htmlspecialchars($a['type'] ?? 'Consultation') ?></div>
            </div>
            <span class="badge <?= $a['status']==='Completed' ? 'badge-gray' : 'badge-red' ?>"><?= $a['status'] ?></span>
          </div>
          <?php endwhile; ?>
          <?php if (!$has): ?><div class="empty-appt">No past appointments.</div><?php endif; ?>
          <div class="appt-pagination" id="pager-<?= $did ?>-past"></div>
        </div>

      </div><!-- /appt-body -->
    </div><!-- /doc-card -->
    <?php endwhile; endif; ?>

  </div>
</div>

<script>
  const APPT_PER_PAGE = 10;
  const panelPageState = {};

  function paginatePanel(panelId, page = 1) {
    const panel = document.getElementById(panelId);
    if (!panel) return;

    const rows = Array.from(panel.querySelectorAll('.appt-row'));
    const pagerId = 'pager-' + panelId.replace('tab-', '');
    const pager = document.getElementById(pagerId);
    if (!pager) return;

    if (rows.length <= APPT_PER_PAGE) {
      rows.forEach(r => r.style.display = 'flex');
      pager.innerHTML = '';
      pager.style.display = 'none';
      panelPageState[panelId] = 1;
      return;
    }

    pager.style.display = 'flex';
    const totalPages = Math.ceil(rows.length / APPT_PER_PAGE);
    const safePage = Math.max(1, Math.min(page, totalPages));
    panelPageState[panelId] = safePage;

    rows.forEach((row, idx) => {
      const start = (safePage - 1) * APPT_PER_PAGE;
      const end = start + APPT_PER_PAGE;
      row.style.display = (idx >= start && idx < end) ? 'flex' : 'none';
    });

    let html = `<button class="page-btn" ${safePage === 1 ? 'disabled' : ''} onclick="paginatePanel('${panelId}', ${safePage - 1})">Prev</button>`;
    for (let p = 1; p <= totalPages; p++) {
      html += `<button class="page-btn ${p === safePage ? 'active' : ''}" onclick="paginatePanel('${panelId}', ${p})">${p}</button>`;
    }
    html += `<button class="page-btn" ${safePage === totalPages ? 'disabled' : ''} onclick="paginatePanel('${panelId}', ${safePage + 1})">Next</button>`;
    pager.innerHTML = html;
  }

  function initCardPagination(did) {
    ['upcoming', 'today', 'past'].forEach(name => {
      const panelId = `tab-${did}-${name}`;
      paginatePanel(panelId, panelPageState[panelId] || 1);
    });
  }

  function toggleCard(did) {
    const card = document.getElementById('card-' + did);
    card.classList.toggle('open');
    if (card.classList.contains('open')) {
      initCardPagination(did);
    }
  }

  function switchTab(did, name, btn) {
    // Deactivate all tabs and panels for this card
    const card = document.getElementById('card-' + did);
    card.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    card.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    // Activate selected
    btn.classList.add('active');
    const panelId = 'tab-' + did + '-' + name;
    document.getElementById(panelId).classList.add('active');
    paginatePanel(panelId, panelPageState[panelId] || 1);
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.doc-card').forEach(card => {
      const did = card.id.replace('card-', '');
      initCardPagination(did);
    });
  });

  setTimeout(() => { const t = document.querySelector('.toast'); if(t) t.remove(); }, 3500);
</script>
</body>
</html>