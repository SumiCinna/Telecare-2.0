<?php
// includes/header.php
// $page_title must be set before including this.

if (!function_exists('tc_notif_time_ago')) {
    function tc_notif_time_ago($datetime) {
        $diff = time() - strtotime($datetime);
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j', strtotime($datetime));
    }
}

$notif_icons = [
    'appointment_request'    => '<path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>',
    'appointment_approved'   => '<path d="M20 6L9 17l-5-5"/>',
    'appointment_rescheduled'=> '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'appointment_cancelled'  => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
    'appointment_reminder'   => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
    'consultation_soon'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'appointment_missed'     => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>',
    'consultation_completed' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>',
    'consultation_summary'   => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h6M9 11h3"/>',
    'doctor_notes'           => '<path d="M11 4H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-4"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>',
    'default'                => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
];

$notif_unread_count = 0;
$notif_list = [];

if (isset($conn, $patient_id)) {

    $conn->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(150) NOT NULL,
            message TEXT,
            link VARCHAR(255) DEFAULT NULL,
            reference_type VARCHAR(30) DEFAULT NULL,
            reference_id INT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_patient_read (patient_id, is_read),
            INDEX idx_patient_created (patient_id, created_at)
        )
    ");

    $colCheck = $conn->query("SHOW COLUMNS FROM notifications LIKE 'reference_id'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE notifications ADD COLUMN reference_type VARCHAR(30) DEFAULT NULL, ADD COLUMN reference_id INT DEFAULT NULL");
    }
    $idxCheck = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'uniq_notif'");
    if ($idxCheck && $idxCheck->num_rows === 0) {
        @$conn->query("ALTER TABLE notifications ADD UNIQUE KEY uniq_notif (patient_id, type, reference_id)");
    }

    $stmt = $conn->prepare("
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, d.full_name AS doctor_name
        FROM appointments a JOIN doctors d ON d.id = a.doctor_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 50
    ");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $appts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $ins = $conn->prepare("INSERT IGNORE INTO notifications (patient_id, type, title, message, link, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, 'appointment', ?)");

    $now = time();
    foreach ($appts as $a) {
        $when      = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
        $dateLabel = date('M j, Y', $when);
        $timeLabel = date('g:i A', $when);
        $doc       = $a['doctor_name'];
        $link      = 'router.php?page=visits';

        $events = [];

        if ($a['status'] === 'Pending') {
            $events[] = ['appointment_request', 'Appointment Request Submitted', "Your request for an appointment with Dr. $doc on $dateLabel at $timeLabel is pending confirmation."];
        } elseif ($a['status'] === 'Confirmed') {
            $events[] = ['appointment_approved', 'Appointment Approved', "Your appointment with Dr. $doc on $dateLabel at $timeLabel has been confirmed."];
            if ($when > $now && ($when - $now) <= 86400) {
                $events[] = ['appointment_reminder', 'Appointment Reminder', "You have an appointment with Dr. $doc tomorrow at $timeLabel."];
            }
            if ($when > $now && ($when - $now) <= 1800) {
                $events[] = ['consultation_soon', 'Consultation Starting Soon', "Your consultation with Dr. $doc starts at $timeLabel."];
            }
            if ($when < $now) {
                $events[] = ['appointment_missed', 'Missed Appointment', "Your appointment with Dr. $doc on $dateLabel at $timeLabel was missed."];
            }
        } elseif ($a['status'] === 'Cancelled') {
            $events[] = ['appointment_cancelled', 'Appointment Cancelled', "Your appointment with Dr. $doc on $dateLabel at $timeLabel was cancelled."];
        } elseif ($a['status'] === 'Completed') {
            $events[] = ['consultation_completed', 'Consultation Completed', "Your consultation with Dr. $doc on $dateLabel has been completed."];
        }

        foreach ($events as [$type, $title, $message]) {
            $ins->bind_param('issssi', $patient_id, $type, $title, $message, $link, $a['id']);
            $ins->execute();
        }
    }
    $ins->close();

    $stmt = $conn->prepare("SELECT COUNT(*) c FROM notifications WHERE patient_id = ? AND is_read = 0");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $notif_unread_count = (int) $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, type, title, message, link, is_read, created_at FROM notifications WHERE patient_id = ? ORDER BY created_at DESC LIMIT 15");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $notif_list[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title ?? 'TELE-CARE') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="includes/styles.css"/>
  <style>
    :root{
      --tc-red:#B31118; --tc-red-dark:#8a000b;
      --tc-teal:#006a61; --tc-teal-light:#0D9488;
      --tc-ink:#151c27; --tc-muted:rgba(21,28,39,0.55);
      --tc-line:rgba(21,28,39,0.08);
      --tc-red-tint:#FEF2F2; --tc-teal-tint:#ECFDF5;
    }
    body{ font-family:'Inter',sans-serif; }

    .site-topbar{
      position:sticky; top:0; z-index:120;
      background:rgba(247,248,251,0.92);
      backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
      border-bottom:1px solid var(--tc-line);
      display:flex; align-items:center; justify-content:space-between;
      gap:1rem; padding:1.1rem 2rem;
    }
    .site-topbar-actions{ display:flex; align-items:center; gap:0.6rem; flex-shrink:0; }
    .site-topbar-icon{
      width:38px; height:38px; border-radius:10px; border:1px solid var(--tc-line);
      background:#fff; display:flex; align-items:center; justify-content:center;
      color:var(--tc-ink); cursor:pointer; transition:all 0.2s; flex-shrink:0;
      position:relative;
    }
    .site-topbar-icon:hover{ background:rgba(21,28,39,0.05); }
    .site-topbar-icon svg{ width:17px; height:17px; }
    .site-topbar-identity{ display:flex; align-items:center; gap:0.65rem; flex-shrink:0; }
    .site-topbar .avatar-circle{
      width:38px; height:38px; border-radius:10px;
      background:linear-gradient(135deg,var(--tc-teal),var(--tc-teal-light));
      color:#fff; display:flex; align-items:center; justify-content:center;
      font-weight:800; font-size:0.85rem; flex-shrink:0; overflow:hidden;
    }
    .site-topbar-photo{
      width:38px; height:38px; border-radius:10px; object-fit:cover; flex-shrink:0;
      border:1px solid var(--tc-line);
    }
    .site-topbar-name{ font-weight:700; font-size:0.85rem; color:var(--tc-ink); line-height:1.2; }
    .site-topbar-status{ display:flex; align-items:center; gap:0.35rem; font-size:0.7rem; color:var(--tc-teal); font-weight:700; margin-top:0.1rem; }
    .site-topbar-status-dot{ width:6px; height:6px; border-radius:50%; background:var(--tc-teal-light); flex-shrink:0; }
    .site-topbar-identity-text{ display:none; }
    @media (min-width:1080px){ .site-topbar-identity-text{ display:block; } }

    @media (max-width:900px){
      .site-topbar{ padding:1rem 1.1rem; }
    }

    .notif-badge{
      position:absolute; top:-4px; right:-4px;
      min-width:16px; height:16px; padding:0 3px;
      background:var(--tc-red); color:#fff;
      border-radius:50px; border:2px solid #f7f8fb;
      font-size:0.6rem; font-weight:800; line-height:12px;
      display:flex; align-items:center; justify-content:center;
    }
    .notif-wrap{ position:relative; }
    .notif-panel{
      position:absolute; top:calc(100% + 10px); right:0;
      width:360px; max-width:calc(100vw - 2rem);
      background:#fff; border-radius:16px;
      border:1px solid var(--tc-line);
      box-shadow:0 16px 50px rgba(21,28,39,0.16);
      display:none; flex-direction:column;
      overflow:hidden; z-index:200;
      animation: notifSlide 0.18s ease;
    }
    @keyframes notifSlide{ from{ opacity:0; transform:translateY(-6px);} to{ opacity:1; transform:translateY(0);} }
    .notif-panel.open{ display:flex; }
    .notif-panel-head{
      display:flex; align-items:center; justify-content:space-between;
      padding:0.95rem 1.1rem; border-bottom:1px solid var(--tc-line);
    }
    .notif-panel-title{ font-weight:800; font-size:0.9rem; color:var(--tc-ink); }
    .notif-mark-all{
      font-size:0.72rem; font-weight:700; color:var(--tc-teal);
      background:none; border:none; cursor:pointer; padding:0;
    }
    .notif-mark-all:hover{ color:var(--tc-teal-light); }
    .notif-panel-body{ max-height:380px; overflow-y:auto; }
    .notif-item{
      display:flex; gap:0.7rem; padding:0.8rem 1.1rem;
      border-bottom:1px solid rgba(21,28,39,0.05);
      cursor:pointer; transition:background 0.15s; text-decoration:none;
    }
    .notif-item:last-child{ border-bottom:none; }
    .notif-item:hover{ background:rgba(21,28,39,0.03); }
    .notif-item.unread{ background:var(--tc-teal-tint); }
    .notif-item.unread:hover{ background:#e2f7f2; }
    .notif-icon{
      width:34px; height:34px; border-radius:9px; flex-shrink:0;
      background:var(--tc-teal-tint); color:var(--tc-teal);
      display:flex; align-items:center; justify-content:center;
    }
    .notif-icon svg{ width:16px; height:16px; }
    .notif-body{ flex:1; min-width:0; }
    .notif-title{ font-size:0.82rem; font-weight:700; color:var(--tc-ink); }
    .notif-msg{ font-size:0.76rem; color:var(--tc-muted); margin-top:0.15rem; line-height:1.4; }
    .notif-time{ font-size:0.68rem; color:rgba(21,28,39,0.35); margin-top:0.3rem; font-weight:600; }
    .notif-dot{ width:8px; height:8px; border-radius:50%; background:var(--tc-red); flex-shrink:0; margin-top:0.35rem; }
    .notif-empty{
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      padding:2.4rem 1rem; color:rgba(21,28,39,0.28); gap:0.6rem; text-align:center;
    }
    .notif-empty svg{ width:32px; height:32px; opacity:0.5; }
    .notif-empty p{ font-size:0.82rem; font-weight:500; }
    @media (max-width:600px){
      .notif-panel{ position:fixed; top:auto; bottom:0; left:0; right:0; width:100%; max-width:100%; border-radius:16px 16px 0 0; max-height:75vh; }
      .notif-panel-body{ max-height:calc(75vh - 56px); }
    }

    .carebot-widget{
      position:fixed; bottom:24px; right:24px; z-index:999;
      display:flex; flex-direction:column; align-items:flex-end; gap:10px;
    }
    @media (max-width:900px){
      .carebot-widget{ bottom:90px; right:16px; }
    }
    @keyframes carebotPulse {
      0%   { transform:scale(1);   opacity:0.5; }
      100% { transform:scale(1.9); opacity:0; }
    }
    @keyframes carebotSlideUp {
      from { opacity:0; transform:translateY(16px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .carebot-btn       { transition:transform 0.2s, box-shadow 0.2s; }
    .carebot-btn:hover { transform:scale(1.1) !important; box-shadow:0 10px 28px rgba(179,17,24,0.4) !important; }
  </style>
</head>
<body>

<!-- ── Site Topbar ── -->
<div class="site-topbar" style="justify-content:flex-end;">
  <div class="site-topbar-actions">

    <div class="notif-wrap">
      <button type="button" class="site-topbar-icon" aria-label="Notifications" id="notifBtn" onclick="toggleNotifPanel()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <?php if ($notif_unread_count > 0): ?>
        <span class="notif-badge" id="notifBadge"><?= $notif_unread_count > 9 ? '9+' : $notif_unread_count ?></span>
        <?php endif; ?>
      </button>

      <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-head">
          <div class="notif-panel-title">Notifications</div>
          <?php if ($notif_unread_count > 0): ?>
          <button type="button" class="notif-mark-all" id="notifMarkAll" onclick="markAllNotifsRead()">Mark all as read</button>
          <?php endif; ?>
        </div>
        <div class="notif-panel-body" id="notifPanelBody">
          <?php if (count($notif_list) === 0): ?>
          <div class="notif-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            <p>No notifications yet.</p>
          </div>
          <?php else: foreach ($notif_list as $n):
              $icon = $notif_icons[$n['type']] ?? $notif_icons['default'];
              $tag  = !empty($n['link']) ? 'a' : 'div';
          ?>
          <<?= $tag ?> <?= !empty($n['link']) ? 'href="' . htmlspecialchars($n['link']) . '"' : '' ?> class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" data-id="<?= (int)$n['id'] ?>" onclick="markNotifRead(<?= (int)$n['id'] ?>)">
            <div class="notif-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icon ?></svg>
            </div>
            <div class="notif-body">
              <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
              <?php if (!empty($n['message'])): ?>
              <div class="notif-msg"><?= htmlspecialchars($n['message']) ?></div>
              <?php endif; ?>
              <div class="notif-time"><?= tc_notif_time_ago($n['created_at']) ?></div>
            </div>
            <?php if (!$n['is_read']): ?>
            <div class="notif-dot"></div>
            <?php endif; ?>
          </<?= $tag ?>>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <button type="button" class="site-topbar-icon" aria-label="Help">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 015 .5c0 1.5-2.5 1.5-2.5 3.5"/><path d="M12 17h.01"/></svg>
    </button>
    <div class="site-topbar-identity" title="<?= htmlspecialchars($p['email']) ?>">
      <?php if (!empty($p['profile_photo'])): ?>
        <img src="<?= htmlspecialchars($p['profile_photo']) ?>" class="site-topbar-photo" alt=""/>
      <?php else: ?>
        <div class="avatar-circle"><?= $initials ?></div>
      <?php endif; ?>
      <div class="site-topbar-identity-text">
        <div class="site-topbar-name"><?= htmlspecialchars($p['full_name']) ?></div>
        <div class="site-topbar-status">
          <span class="site-topbar-status-dot"></span>
          Active
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── CareBot Floating Widget ── -->
<div id="carebotWidget" class="carebot-widget">

  <div id="carebotPanel" style="
    width:340px;
    height:480px;
    background:#fff;
    border-radius:20px;
    box-shadow:0 16px 50px rgba(0,0,0,0.18);
    overflow:hidden;
    display:none;
    flex-direction:column;
    border:1px solid rgba(0,106,97,0.15);
    animation:carebotSlideUp 0.25s ease;
  ">
    <div style="background:linear-gradient(135deg,#151c27,#0a0e14);padding:0.9rem 1.1rem;display:flex;align-items:center;gap:0.7rem;flex-shrink:0;">
      <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#006a61,#0D9488);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M9 4h6"/><circle cx="9" cy="14" r="1.2" fill="#fff" stroke="none"/><circle cx="15" cy="14" r="1.2" fill="#fff" stroke="none"/><path d="M9 18h6"/></svg>
      </div>
      <div style="flex:1;">
        <div style="font-weight:700;font-size:0.88rem;color:#fff;">CareBot</div>
        <div style="font-size:0.68rem;color:rgba(255,255,255,0.5);">AI Health Assistant</div>
      </div>
      <div style="display:flex;align-items:center;gap:0.35rem;margin-right:0.5rem;">
        <div style="width:6px;height:6px;border-radius:50%;background:#0D9488;"></div>
        <span style="font-size:0.65rem;color:#0D9488;font-weight:700;">Online</span>
      </div>
      <button onclick="toggleCarebot()" style="background:rgba(255,255,255,0.1);border:none;border-radius:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;flex-shrink:0;">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <iframe
      src="https://www.chatbase.co/chatbot-iframe/HkPkNj6UCtO6aEae6tzHK"
      width="100%"
      style="flex:1;border:none;display:block;"
      frameborder="0">
    </iframe>
  </div>

  <button onclick="toggleCarebot()" class="carebot-btn" style="
    height:48px;
    border-radius:50px;
    background:linear-gradient(135deg,#B31118,#8a000b);
    border:none;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:0.5rem;
    padding:0 1.1rem 0 0.8rem;
    box-shadow:0 6px 20px rgba(179,17,24,0.4);
    position:relative;
    flex-shrink:0;
  ">
    <div style="position:absolute;inset:0;border-radius:50px;border:2px solid rgba(179,17,24,0.5);animation:carebotPulse 2s ease-out infinite;pointer-events:none;"></div>
    <div id="cbIconOpen" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="8" width="16" height="12" rx="3"/><path d="M12 8V4M9 4h6"/><circle cx="9" cy="14" r="1.2" fill="#fff" stroke="none"/><circle cx="15" cy="14" r="1.2" fill="#fff" stroke="none"/><path d="M9 18h6"/></svg>
    </div>
    <div id="cbIconClose" style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;display:none;">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </div>
    <span id="cbLabel" style="color:#fff;font-weight:700;font-size:0.82rem;white-space:nowrap;letter-spacing:0.01em;">Chat with CareBot</span>
  </button>
</div>

<script>
  function toggleCarebot() {
    const panel     = document.getElementById('carebotPanel');
    const iconOpen  = document.getElementById('cbIconOpen');
    const iconClose = document.getElementById('cbIconClose');
    const label     = document.getElementById('cbLabel');
    const isOpen    = panel.style.display === 'flex';
    panel.style.display     = isOpen ? 'none'  : 'flex';
    iconOpen.style.display  = isOpen ? 'flex'  : 'none';
    iconClose.style.display = isOpen ? 'none'  : 'flex';
    label.textContent       = isOpen ? 'Chat with CareBot' : 'Close CareBot';
  }

  function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    panel.classList.toggle('open');
  }

  document.addEventListener('click', function (e) {
    const wrap = document.querySelector('.notif-wrap');
    if (wrap && !wrap.contains(e.target)) {
      document.getElementById('notifPanel').classList.remove('open');
    }
  });

  function updateNotifBadge(count) {
    const btn = document.getElementById('notifBtn');
    let badge = document.getElementById('notifBadge');
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'notif-badge';
        badge.id = 'notifBadge';
        btn.appendChild(badge);
      }
      badge.textContent = count > 9 ? '9+' : count;
    } else if (badge) {
      badge.remove();
    }
    const markAllBtn = document.getElementById('notifMarkAll');
    if (markAllBtn && count === 0) markAllBtn.remove();
  }

  function markNotifRead(id) {
    const item = document.querySelector('.notif-item[data-id="' + id + '"]');
    if (item && item.classList.contains('unread')) {
      fetch('includes/notifications_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_read&id=' + encodeURIComponent(id)
      })
      .then(r => r.json())
      .then(data => {
        item.classList.remove('unread');
        const dot = item.querySelector('.notif-dot');
        if (dot) dot.remove();
        if (typeof data.unread_count !== 'undefined') updateNotifBadge(data.unread_count);
      })
      .catch(() => {});
    }
  }

  function markAllNotifsRead() {
    fetch('includes/notifications_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=mark_all_read'
    })
    .then(r => r.json())
    .then(data => {
      document.querySelectorAll('.notif-item.unread').forEach(el => {
        el.classList.remove('unread');
        const dot = el.querySelector('.notif-dot');
        if (dot) dot.remove();
      });
      updateNotifBadge(0);
    })
    .catch(() => {});
  }
</script>