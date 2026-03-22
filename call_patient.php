<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';

$appt_id = (int)($_GET['appt_id'] ?? 0);
if (!$appt_id) { header('Location: visits.php'); exit; }

$stmt = $conn->prepare("
    SELECT a.*, d.full_name AS doctor_name, d.specialty, d.profile_photo AS doctor_photo
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.id = ? AND a.patient_id = ? AND a.status IN ('Confirmed','Completed')
");
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) { header('Location: visits.php'); exit; }

$appt_ts = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
$now = time();
if ($now < ($appt_ts - 900) || $now > ($appt_ts + 3600)) { header('Location: visits.php'); exit; }

$room_id = 'telecare-' . $appt_id . '-' . str_replace('-', '', $appt['appointment_date']);
$end_ts  = $appt_ts + 3600;
$doc_initials = strtoupper(substr($appt['doctor_name'], 0, 2));

$pstmt = $conn->prepare("SELECT full_name, profile_photo FROM patients WHERE id=? LIMIT 1");
$pstmt->bind_param("i", $patient_id);
$pstmt->execute();
$pat = $pstmt->get_result()->fetch_assoc();
$pat_name     = $pat['full_name'] ?? 'You';
$pat_photo    = $pat['profile_photo'] ?? '';
$pat_initials = strtoupper(substr($pat_name, 0, 2));
$doc_photo    = $appt['doctor_photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Dr. <?= htmlspecialchars($appt['doctor_name']) ?> — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js" crossorigin="anonymous"></script>
  <style>
    :root{--gm-blue:#1a73e8;--gm-bg:#202124;--gm-surface:#3c4043;--gm-surface2:#2d2e30;--gm-red:#ea4335;--gm-green:#34a853;--gm-text:#e8eaed;--gm-muted:#9aa0a6;}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Google Sans','Roboto',sans-serif;background:var(--gm-bg);color:var(--gm-text);height:100vh;display:flex;flex-direction:column;overflow:hidden;}

    /* TOP BAR */
    .topbar{height:56px;background:var(--gm-bg);display:flex;align-items:center;justify-content:space-between;padding:0 1.2rem;flex-shrink:0;border-bottom:1px solid rgba(255,255,255,0.06);}
    .tb-logo{font-size:0.9rem;font-weight:700;}.tb-logo span{color:var(--gm-blue);}
    .timer-pill{background:var(--gm-surface2);border-radius:20px;padding:0.3rem 0.9rem;font-size:0.8rem;font-weight:500;min-width:54px;text-align:center;font-variant-numeric:tabular-nums;}
    .timer-pill.urgent{color:var(--gm-red);}
    .conn-dot{display:flex;align-items:center;gap:0.4rem;font-size:0.75rem;color:var(--gm-muted);}
    .dot{width:8px;height:8px;border-radius:50%;background:#fbbc04;flex-shrink:0;}
    .dot.live{background:var(--gm-green);animation:blink 2s infinite;}
    @keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}

    /* VIDEO AREA */
    .video-area{flex:1;display:flex;align-items:center;justify-content:center;padding:1rem;gap:1rem;overflow:hidden;position:relative;}
    .remote-tile{flex:1;max-width:860px;aspect-ratio:16/9;border-radius:16px;overflow:hidden;background:#1c1c1f;position:relative;box-shadow:0 8px 40px rgba(0,0,0,0.5);}
    #remote-video{width:100%;height:100%;object-fit:cover;display:block;}
    .cam-off-overlay{position:absolute;inset:0;background:#1c1c1f;display:none;flex-direction:column;align-items:center;justify-content:center;gap:0.8rem;z-index:2;}
    .cam-off-overlay.show{display:flex;}
    .co-avatar{width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,0.15);background:var(--gm-surface);display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;overflow:hidden;}
    .co-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .waiting-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.8rem;background:#1c1c1f;z-index:3;}
    .waiting-avatar{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#1a73e8,#0d47a1);display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;position:relative;overflow:hidden;}
    .waiting-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .pulse-ring{position:absolute;width:80px;height:80px;border-radius:50%;border:2px solid rgba(26,115,232,0.5);animation:pulse 2s ease-out infinite;pointer-events:none;}
    @keyframes pulse{0%{transform:scale(1);opacity:1}100%{transform:scale(1.75);opacity:0}}
    .name-tag{position:absolute;bottom:0.7rem;left:0.7rem;background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);border-radius:8px;padding:0.3rem 0.7rem;font-size:0.76rem;font-weight:500;}

    /* SELF TILE */
    .self-tile{width:200px;aspect-ratio:4/3;border-radius:14px;overflow:hidden;background:#2a2b2d;position:relative;flex-shrink:0;box-shadow:0 4px 20px rgba(0,0,0,0.4);border:2px solid rgba(255,255,255,0.08);}
    #local-video-raw{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;}
    #local-canvas{width:100%;height:100%;object-fit:cover;display:block;}
    .self-cam-off{position:absolute;inset:0;background:#2a2b2d;display:none;flex-direction:column;align-items:center;justify-content:center;gap:0.5rem;}
    .self-cam-off.show{display:flex;}
    .self-cam-off-avatar{width:48px;height:48px;border-radius:50%;background:var(--gm-surface);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;overflow:hidden;}
    .self-cam-off-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .self-name-tag{position:absolute;bottom:0.45rem;left:0.45rem;background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);border-radius:6px;padding:0.2rem 0.5rem;font-size:0.68rem;font-weight:500;}
    .seg-loading{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:0.7rem;color:var(--gm-muted);text-align:center;z-index:5;pointer-events:none;}

    /* CONTROLS */
    .controls{height:80px;background:var(--gm-bg);display:flex;align-items:center;justify-content:center;gap:0.7rem;flex-shrink:0;border-top:1px solid rgba(255,255,255,0.06);}
    .ctrl-sep{width:1px;height:32px;background:rgba(255,255,255,0.1);margin:0 0.2rem;}
    .cbtn{display:flex;flex-direction:column;align-items:center;gap:0.25rem;background:none;border:none;cursor:pointer;color:var(--gm-text);font-family:inherit;position:relative;}
    .cbtn-icon{width:48px;height:48px;border-radius:50%;background:var(--gm-surface);display:flex;align-items:center;justify-content:center;transition:background .2s,transform .15s;}
    .cbtn:hover .cbtn-icon{background:#4e5256;transform:scale(1.07);}
    .cbtn.off .cbtn-icon{background:var(--gm-red);}
    .cbtn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
    .cbtn-lbl{font-size:0.61rem;color:var(--gm-muted);white-space:nowrap;}
    .cbtn-end .cbtn-icon{background:var(--gm-red)!important;width:56px;height:56px;box-shadow:0 2px 14px rgba(234,67,53,0.5);}
    .cbtn-end:hover .cbtn-icon{background:#c5352a!important;}
    .cbtn-end svg{width:22px;height:22px;}
    .chat-badge{position:absolute;top:0;right:0;width:16px;height:16px;border-radius:50%;background:var(--gm-red);font-size:0.58rem;font-weight:700;display:none;align-items:center;justify-content:center;color:#fff;}
    .chat-badge.show{display:flex;}

    /* CHAT PANEL */
    .chat-panel{position:absolute;top:0;right:0;bottom:80px;width:300px;background:var(--gm-surface2);border-left:1px solid rgba(255,255,255,0.08);display:flex;flex-direction:column;z-index:40;transform:translateX(100%);transition:transform .25s cubic-bezier(.4,0,.2,1);}
    .chat-panel.open{transform:translateX(0);}
    .chat-hd{padding:0.9rem 1rem;border-bottom:1px solid rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
    .chat-hd h3{font-size:0.88rem;font-weight:600;}
    .chat-hd button{background:none;border:none;color:var(--gm-muted);cursor:pointer;font-size:1.1rem;line-height:1;}
    .chat-msgs{flex:1;overflow-y:auto;padding:0.8rem;display:flex;flex-direction:column;gap:0.6rem;}
    .chat-msgs::-webkit-scrollbar{width:4px;}
    .chat-msgs::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:4px;}
    .chat-empty-msg{text-align:center;padding:2rem 1rem;font-size:0.78rem;color:var(--gm-muted);}
    .msg{max-width:90%;}
    .msg.me{align-self:flex-end;}.msg.them{align-self:flex-start;}
    .msg-name{font-size:0.62rem;font-weight:600;color:var(--gm-muted);margin-bottom:0.15rem;padding:0 0.35rem;}
    .msg-bubble{padding:0.5rem 0.75rem;border-radius:16px;font-size:0.82rem;line-height:1.45;word-break:break-word;}
    .msg.me .msg-bubble{background:var(--gm-blue);color:#fff;border-bottom-right-radius:4px;}
    .msg.them .msg-bubble{background:var(--gm-surface);color:var(--gm-text);border-bottom-left-radius:4px;}
    .msg-time{font-size:0.58rem;color:var(--gm-muted);margin-top:0.15rem;padding:0 0.35rem;}
    .msg.me .msg-time{text-align:right;}.msg.them .msg-time{text-align:left;}
    .chat-input-row{padding:0.7rem;border-top:1px solid rgba(255,255,255,0.08);display:flex;gap:0.5rem;flex-shrink:0;}
    .chat-input{flex:1;background:var(--gm-surface);border:none;border-radius:20px;padding:0.55rem 0.9rem;color:var(--gm-text);font-family:inherit;font-size:0.82rem;outline:none;resize:none;max-height:80px;}
    .chat-input::placeholder{color:var(--gm-muted);}
    .chat-send{width:36px;height:36px;border-radius:50%;background:var(--gm-blue);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .2s;}
    .chat-send:hover{background:#1557b0;}
    .chat-send svg{width:15px;height:15px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

    /* BG PANEL */
    .bg-panel{position:absolute;bottom:88px;left:50%;transform:translateX(-50%);background:var(--gm-surface2);border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:1.2rem;width:340px;z-index:50;display:none;box-shadow:0 8px 32px rgba(0,0,0,0.6);}
    .bg-panel.open{display:block;}
    .bg-panel-hd{font-size:0.82rem;font-weight:700;margin-bottom:0.9rem;display:flex;justify-content:space-between;align-items:center;}
    .bg-panel-hd button{background:none;border:none;color:var(--gm-muted);cursor:pointer;font-size:1rem;}
    .bg-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:0.55rem;}
    .bgo{aspect-ratio:16/10;border-radius:8px;border:2px solid transparent;cursor:pointer;overflow:hidden;transition:border-color .15s,transform .15s;position:relative;background:#333;}
    .bgo:hover{border-color:var(--gm-blue);transform:scale(1.05);}
    .bgo.on{border-color:var(--gm-blue);box-shadow:0 0 0 1px var(--gm-blue);}
    .bgo img{width:100%;height:100%;object-fit:cover;display:block;}
    .bgo-lbl{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.6);font-size:0.54rem;font-weight:600;text-align:center;padding:0.18rem;color:#fff;}

    /* TOAST */
    #toast{position:absolute;top:66px;left:50%;transform:translateX(-50%);background:rgba(60,64,67,0.95);padding:0.5rem 1.2rem;border-radius:8px;font-size:0.82rem;z-index:60;opacity:0;transition:opacity .25s;pointer-events:none;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,0.4);}
    #toast.on{opacity:1;}
  </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <div style="display:flex;align-items:center;gap:0.8rem;">
    <div class="tb-logo">TELE<span>-</span>CARE</div>
    <span style="font-size:0.76rem;color:var(--gm-muted);"><?= date('g:i A', $appt_ts) ?> · Teleconsultation</span>
  </div>
  <div style="display:flex;align-items:center;gap:0.6rem;">
    <div class="conn-dot"><div class="dot" id="conn-dot"></div><span id="conn-lbl">Connecting…</span></div>
    <div class="timer-pill" id="timer">--:--</div>
  </div>
</div>

<!-- VIDEO AREA -->
<div class="video-area">

  <!-- Remote (Doctor) tile -->
  <div class="remote-tile">
    <video id="remote-video" autoplay playsinline></video>

    <!-- Doctor cam-off -->
    <div class="cam-off-overlay" id="remote-cam-off">
      <div class="co-avatar">
        <?php if ($doc_photo): ?><img src="../<?= htmlspecialchars($doc_photo) ?>" alt=""/><?php else: echo $doc_initials; endif; ?>
      </div>
      <div style="font-size:0.95rem;font-weight:600;">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
      <div style="font-size:0.75rem;color:var(--gm-muted);">Camera off</div>
    </div>

    <!-- Waiting overlay -->
    <div class="waiting-overlay" id="waiting-overlay">
      <div style="position:relative;display:flex;align-items:center;justify-content:center;">
        <div class="pulse-ring"></div>
        <div class="waiting-avatar">
          <?php if ($doc_photo): ?><img src="../<?= htmlspecialchars($doc_photo) ?>" alt=""/><?php else: echo $doc_initials; endif; ?>
        </div>
      </div>
      <div style="font-size:1rem;font-weight:600;">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
      <div style="font-size:0.78rem;color:var(--gm-muted);" id="waiting-sub">Waiting for doctor to join…</div>
    </div>

    <div class="name-tag">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
  </div>

  <!-- Self tile -->
  <div class="self-tile">
    <video id="local-video-raw" autoplay muted playsinline></video>
    <canvas id="local-canvas"></canvas>
    <div class="self-cam-off" id="self-cam-off">
      <div class="self-cam-off-avatar">
        <?php if ($pat_photo): ?><img src="../<?= htmlspecialchars($pat_photo) ?>" alt=""/><?php else: echo $pat_initials; endif; ?>
      </div>
      <span style="font-size:0.68rem;color:var(--gm-muted);"><?= htmlspecialchars($pat_name) ?></span>
    </div>
    <div class="seg-loading" id="seg-loading">Loading camera…</div>
    <div class="self-name-tag"><?= htmlspecialchars($pat_name) ?> (You)</div>
  </div>

</div>

<!-- BG PANEL -->
<div class="bg-panel" id="bgpanel">
  <div class="bg-panel-hd"><span>🎨 Virtual Background</span><button onclick="toggleBg()">✕</button></div>
  <div class="bg-grid">
    <div class="bgo on" onclick="setBg('none',this)" style="background:#2a2b2d;display:flex;align-items:center;justify-content:center;">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#9aa0a6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M4.93 4.93l14.14 14.14"/></svg>
      <span class="bgo-lbl">None</span>
    </div>
    <div class="bgo" onclick="setBg('blur',this)" style="background:linear-gradient(135deg,#74b9ff,#0984e3);display:flex;align-items:center;justify-content:center;font-size:1.3rem;">🌫<span class="bgo-lbl">Blur</span></div>
    <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1497366216548-37526070297c?w=640&q=80',this)"><img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=200&q=60" loading="lazy"/><span class="bgo-lbl">Office</span></div>
    <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1505576399279-565b52d4ac71?w=640&q=80',this)"><img src="https://images.unsplash.com/photo-1505576399279-565b52d4ac71?w=200&q=60" loading="lazy"/><span class="bgo-lbl">Clinic</span></div>
    <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1433878455169-4698e60005b1?w=640&q=80',this)"><img src="https://images.unsplash.com/photo-1433878455169-4698e60005b1?w=200&q=60" loading="lazy"/><span class="bgo-lbl">Nature</span></div>
    <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=640&q=80',this)"><img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&q=60" loading="lazy"/><span class="bgo-lbl">Library</span></div>
    <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=640&q=80',this)"><img src="https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=200&q=60" loading="lazy"/><span class="bgo-lbl">Room</span></div>
    <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1476231682828-37e571bc172f?w=640&q=80',this)"><img src="https://images.unsplash.com/photo-1476231682828-37e571bc172f?w=200&q=60" loading="lazy"/><span class="bgo-lbl">Forest</span></div>
  </div>
</div>

<!-- CHAT PANEL (single, clean) -->
<div class="chat-panel" id="chat-panel">
  <div class="chat-hd">
    <h3>💬 In-call Chat</h3>
    <button onclick="toggleChat()">✕</button>
  </div>
  <div class="chat-msgs" id="chat-msgs">
    <div class="chat-empty-msg" id="chat-empty">No messages yet.<br/>Say hello to your doctor!</div>
  </div>
  <div class="chat-input-row">
    <textarea class="chat-input" id="chat-input" placeholder="Send a message…" rows="1"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChat();}"></textarea>
    <button class="chat-send" onclick="sendChat()">
      <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
    </button>
  </div>
</div>

<div id="toast"></div>

<!-- CONTROLS -->
<div class="controls">
  <button class="cbtn" id="btn-mic" onclick="toggleMic()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24"><path d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M12 3a4 4 0 014 4v4a4 4 0 01-8 0V7a4 4 0 014-4z"/></svg></div>
    <span class="cbtn-lbl" id="lbl-mic">Mute</span>
  </button>

  <button class="cbtn" id="btn-cam" onclick="toggleCam()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24"><path d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg></div>
    <span class="cbtn-lbl" id="lbl-cam">Camera</span>
  </button>

  <div class="ctrl-sep"></div>

  <button class="cbtn cbtn-end" onclick="endCall(false)">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24" style="width:22px;height:22px;"><path d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/></svg></div>
    <span class="cbtn-lbl" style="color:var(--gm-red);">Leave</span>
  </button>

  <div class="ctrl-sep"></div>

  <button class="cbtn" onclick="toggleBg()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
    <span class="cbtn-lbl">Background</span>
  </button>

  <button class="cbtn" onclick="toggleChat()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></div>
    <div class="chat-badge" id="chat-badge">0</div>
    <span class="cbtn-lbl">Chat</span>
  </button>
</div>

<script>
const ROOM_ID  = <?= json_encode($room_id) ?>;
const ROLE     = 'patient';
const END_TS   = <?= $end_ts ?>;
const APPT_TS  = <?= $appt_ts ?>;
const APPT_ID  = <?= $appt_id ?>;
const MY_NAME  = <?= json_encode($pat_name) ?>;
const WS_URL   = `ws://localhost:8765/ws/${ROOM_ID}/${ROLE}`;
const ICE      = { iceServers:[{urls:'stun:stun.l.google.com:19302'},{urls:'stun:stun1.l.google.com:19302'}] };

let ws, pc, rawStream, segInterval, selfieSegmentation, processedStream;
let micOn=true, camOn=true, bgMode='none', bgImg=null;
let chatOpen=false, unread=0;

// Canvas for segmentation
const canvas = document.getElementById('local-canvas');
const ctx    = canvas.getContext('2d');
canvas.width=640; canvas.height=480;

// ── INIT ──────────────────────────────────────────────────────────────────
async function init() {
  try {
    rawStream = await navigator.mediaDevices.getUserMedia({video:{width:640,height:480},audio:true});
    document.getElementById('local-video-raw').srcObject = rawStream;
  } catch(e) {
    showToast('❌ Camera/mic access denied'); return;
  }
  initSeg();
  connectWS();
  startTimer();
}

// ── SEGMENTATION ──────────────────────────────────────────────────────────
function initSeg() {
  selfieSegmentation = new SelfieSegmentation({
    locateFile: f => `https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/${f}`
  });
  selfieSegmentation.setOptions({modelSelection:1});
  selfieSegmentation.onResults(onSegResult);
  selfieSegmentation.initialize().then(() => {
    document.getElementById('seg-loading').style.display = 'none';
    processedStream = canvas.captureStream(30);
    rawStream.getAudioTracks().forEach(t => processedStream.addTrack(t));
    const vid = document.getElementById('local-video-raw');
    segInterval = setInterval(async () => {
      if (vid.readyState >= 2) await selfieSegmentation.send({image:vid});
    }, 33);
  });
}

function onSegResult(r) {
  ctx.save(); ctx.clearRect(0,0,640,480);
  ctx.drawImage(r.segmentationMask,0,0,640,480);
  ctx.globalCompositeOperation='source-in';
  ctx.drawImage(r.image,0,0,640,480);
  ctx.globalCompositeOperation='destination-over';
  if (bgMode==='none') { ctx.drawImage(r.image,0,0,640,480); }
  else if (bgMode==='blur') { ctx.filter='blur(18px)'; ctx.drawImage(r.image,-30,-30,700,540); ctx.filter='none'; }
  else if (bgImg&&bgImg.complete) { ctx.drawImage(bgImg,0,0,640,480); }
  ctx.restore();
}

// ── WEBSOCKET ─────────────────────────────────────────────────────────────
function connectWS() {
  ws = new WebSocket(WS_URL);
  ws.onopen = () => setConn(false, 'Waiting for doctor…');
  ws.onmessage = async ({data}) => {
    const m = JSON.parse(data);
    if (m.type === 'peer_joined') {
      setConn(false, 'Doctor joined!');
      // Don't auto-complete here — complete only when the 1hr session ends
      if (Date.now()/1000 < APPT_TS) {
        document.getElementById('waiting-sub').textContent = '✅ Doctor is here! Call starts at ' + new Date(APPT_TS*1000).toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit',hour12:true});
        showToast('Doctor is here early — waiting for scheduled time…');
      } else {
        showToast('Doctor has joined!');
      }
    }
    else if (m.type === 'cam_toggle') {
      // Doctor toggled their camera
      const camOff = document.getElementById('remote-cam-off');
      if (m.cam_on) {
        camOff.classList.remove('show');
      } else {
        camOff.classList.add('show');
      }
    }
    else if (m.type === 'offer') {
      // Wait up to 5s for stream to be ready
      const doAnswer = async () => {
        if (pc) { pc.close(); pc = null; }
        pc = new RTCPeerConnection(ICE);
        const stream = processedStream || rawStream;
        stream.getTracks().forEach(t => pc.addTrack(t, stream));
        pc.ontrack = e => {
          document.getElementById('remote-video').srcObject = e.streams[0];
          document.getElementById('waiting-overlay').style.display = 'none';
          setConn(true, 'Connected');
          callWasConnected = true;
          showToast('Call connected!');
        };
        pc.onicecandidate = e => {
          if (e.candidate) ws.send(JSON.stringify({type:'ice',candidate:e.candidate}));
        };
        await pc.setRemoteDescription(m.sdp);
        const ans = await pc.createAnswer();
        await pc.setLocalDescription(ans);
        ws.send(JSON.stringify({type:'answer', sdp:ans}));
      };
      if (processedStream || rawStream) {
        await doAnswer();
      } else {
        let waited = 0;
        const poll = setInterval(async () => {
          waited += 200;
          if (processedStream || rawStream || waited >= 5000) {
            clearInterval(poll);
            await doAnswer();
          }
        }, 200);
      }
    }
    else if (m.type === 'ice') {
      if (pc && m.candidate) try { await pc.addIceCandidate(m.candidate); } catch(e){}
    }
    else if (m.type === 'peer_left') {
      document.getElementById('remote-video').srcObject = null;
      document.getElementById('waiting-overlay').style.display = 'flex';
      document.getElementById('waiting-sub').textContent = 'Doctor disconnected…';
      setConn(false, 'Doctor left');
      if (pc) { pc.close(); pc = null; }
      showToast('Doctor left the call');
    }
    else if (m.type === 'chat') {
      // Use m.name (sent by peer) — m.from is the role string added by server
      const senderName = m.name || 'Doctor';
      addMsg(m.text, senderName, false);
    }
  };
  ws.onclose = () => { setConn(false,'Reconnecting…'); setTimeout(connectWS, 3000); };
  ws.onerror = () => ws.close();
}

// ── END CALL ─────────────────────────────────────────────────────────────
function endCall(auto=false) {
  if (!auto && !confirm('Leave the call?')) return;
  clearInterval(segInterval);
  try { selfieSegmentation?.close(); } catch(e) {}
  try { ws?.close(); } catch(e) {}
  try { pc?.close(); } catch(e) {}
  rawStream?.getTracks().forEach(t => t.stop());
  window.location.href = 'visits.php';
}

// ── AUTO COMPLETE ─────────────────────────────────────────────────────────
function autoComplete() {
  if (Date.now()/1000 < APPT_TS) return;
  fetch('auto_complete_appt.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`appt_id=${APPT_ID}&role=patient`
  }).catch(()=>{});
}

// ── CONTROLS ──────────────────────────────────────────────────────────────
function toggleMic() {
  micOn=!micOn;
  rawStream?.getAudioTracks().forEach(t=>t.enabled=micOn);
  document.getElementById('btn-mic').classList.toggle('off',!micOn);
  document.getElementById('lbl-mic').textContent=micOn?'Mute':'Unmute';
  showToast(micOn?'🎤 Mic on':'🔇 Muted');
}
function toggleCam() {
  camOn=!camOn;
  rawStream?.getVideoTracks().forEach(t=>t.enabled=camOn);
  document.getElementById('btn-cam').classList.toggle('off',!camOn);
  document.getElementById('lbl-cam').textContent=camOn?'Camera':'Cam Off';
  document.getElementById('local-canvas').style.display=camOn?'block':'none';
  document.getElementById('self-cam-off').classList.toggle('show',!camOn);
  // Signal the other peer
  if (ws && ws.readyState===WebSocket.OPEN) ws.send(JSON.stringify({type:'cam_toggle',cam_on:camOn}));
  showToast(camOn?'📹 Camera on':'🚫 Camera off');
}
function toggleBg() {
  document.getElementById('bgpanel').classList.toggle('open');
}
function setBg(mode,el) {
  bgMode=mode; bgImg=null;
  document.querySelectorAll('.bgo').forEach(e=>e.classList.remove('on'));
  el.classList.add('on');
  document.getElementById('bgpanel').classList.remove('open');
  if (mode!=='none'&&mode!=='blur') { bgImg=new Image(); bgImg.crossOrigin='anonymous'; bgImg.src=mode; }
  showToast(mode==='none'?'Background removed':mode==='blur'?'🌫 Background blurred':'🌄 Background changed');
}

// ── CHAT ──────────────────────────────────────────────────────────────────
function toggleChat() {
  chatOpen=!chatOpen;
  document.getElementById('chat-panel').classList.toggle('open',chatOpen);
  if (chatOpen) {
    unread=0;
    const b=document.getElementById('chat-badge');
    b.classList.remove('show'); b.textContent='0';
    document.getElementById('chat-input').focus();
    document.getElementById('chat-msgs').scrollTop=99999;
  }
}
function sendChat() {
  const inp=document.getElementById('chat-input');
  const text=inp.value.trim();
  if (!text||!ws||ws.readyState!==WebSocket.OPEN) return;
  ws.send(JSON.stringify({type:'chat',text,name:MY_NAME}));
  addMsg(text,'You',true);
  inp.value='';
}
function addMsg(text,name,isMe) {
  const c=document.getElementById('chat-msgs');
  document.getElementById('chat-empty').style.display='none';
  const now=new Date().toLocaleTimeString('en-PH',{hour:'numeric',minute:'2-digit',hour12:true});
  const d=document.createElement('div');
  d.className='msg '+(isMe?'me':'them');
  d.innerHTML=`<div class="msg-name">${esc(name)}</div><div class="msg-bubble">${esc(text)}</div><div class="msg-time">${now}</div>`;
  c.appendChild(d);
  c.scrollTop=c.scrollHeight;
  if (!isMe&&!chatOpen) {
    unread++;
    const b=document.getElementById('chat-badge');
    b.textContent=unread>9?'9+':unread; b.classList.add('show');
    showToast(`💬 ${name}: ${text.length>30?text.slice(0,30)+'…':text}`);
  }
}

// ── TIMER ─────────────────────────────────────────────────────────────────
let callWasConnected = false;
let timerEnded = false;

function startTimer() {
  const el = document.getElementById('timer');
  el.textContent = '60:00'; // show full hour until call actually starts

  setInterval(() => {
    const nowSec = Math.floor(Date.now() / 1000);

    // Don't start counting down until scheduled time
    if (nowSec < APPT_TS) {
      const waitSec = APPT_TS - nowSec;
      const m = Math.floor(waitSec / 60), s = waitSec % 60;
      el.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
      el.style.color = '#fbbc04'; // yellow while waiting
      el.title = 'Starts in ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
      return;
    }

    // Scheduled time reached — count down from 1hr
    el.style.color = '';
    el.title = '';
    const left = END_TS - nowSec;

    if (left <= 0) {
      if (timerEnded) return;
      timerEnded = true;
      el.textContent = '00:00';
      el.classList.add('urgent');
      autoComplete(); // mark complete only when session actually ends
      if (callWasConnected) {
        showToast('⏰ Consultation ended — leaving in 5 seconds…');
        setTimeout(() => endCall(true), 5000);
      } else {
        showToast('⏰ Session time expired');
        setTimeout(() => endCall(true), 30000);
      }
      return;
    }

    const m = Math.floor(left / 60), s = left % 60;
    el.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    el.classList.toggle('urgent', left < 300);
  }, 1000);
}

// ── HELPERS ───────────────────────────────────────────────────────────────
function setConn(live,label){
  document.getElementById('conn-dot').className='dot'+(live?' live':'');
  document.getElementById('conn-lbl').textContent=label;
}
let tT;
function showToast(msg){
  const e=document.getElementById('toast'); e.textContent=msg; e.classList.add('on');
  clearTimeout(tT); tT=setTimeout(()=>e.classList.remove('on'),2800);
}
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// Close panels on outside click
document.addEventListener('click',e=>{
  if (!document.getElementById('bgpanel').contains(e.target)&&!e.target.closest('[onclick="toggleBg()"]'))
    document.getElementById('bgpanel').classList.remove('open');
});
document.addEventListener('visibilitychange',()=>{
  if (!document.hidden&&rawStream) rawStream.getVideoTracks().forEach(t=>{if(camOn)t.enabled=true;});
});

init();
</script>
</body>
</html>