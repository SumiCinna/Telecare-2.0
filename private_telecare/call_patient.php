<?php
// private_telecare/call_patient.php
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/auth.php';
// call_patient.php (patient side)
$appt_id = (int)($_GET['appt_id'] ?? 0);
if (!$appt_id) { header('Location: ../visits.php'); exit; }

$stmt = $conn->prepare("
    SELECT a.*, d.full_name AS doctor_name, d.specialty, d.profile_photo AS doctor_photo
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id
    WHERE a.id = ? AND a.patient_id = ? AND a.status IN ('Confirmed','Completed')
");
$stmt->bind_param("ii", $appt_id, $patient_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) { header('Location: ../visits.php'); exit; }

$appt_ts = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
$now     = (new DateTime('now', new DateTimeZone('Asia/Manila')))->getTimestamp();
if ($now < ($appt_ts - 900) || $now > ($appt_ts + 3600)) { header('Location: ../visits.php'); exit; }

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
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no"/>
  <title>Dr. <?= htmlspecialchars($appt['doctor_name']) ?> &mdash; TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js" crossorigin="anonymous"></script>
  <style>
    :root{
      --gm-blue:#1a73e8;--gm-bg:#202124;--gm-surface:#3c4043;--gm-surface2:#2d2e30;
      --gm-red:#ea4335;--gm-green:#34a853;--gm-text:#e8eaed;--gm-muted:#9aa0a6;
      --ctrl-h:72px;--top-h:52px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html{height:100%;overflow:hidden;}
    body{
      font-family:'Google Sans','Roboto',sans-serif;
      background:var(--gm-bg);color:var(--gm-text);
      width:100%;overflow:hidden;position:fixed;
      top:0;left:0;right:0;bottom:0;
      display:flex;flex-direction:column;height:100dvh;
    }
    .topbar{
      height:var(--top-h);min-height:var(--top-h);
      background:var(--gm-bg);
      display:flex;align-items:center;justify-content:space-between;
      padding:0 1rem;padding-top:env(safe-area-inset-top,0px);
      flex-shrink:0;border-bottom:1px solid rgba(255,255,255,0.06);z-index:10;
    }
    .tb-logo{font-size:0.85rem;font-weight:700;}
    .tb-logo span{color:var(--gm-blue);}
    .timer-pill{background:var(--gm-surface2);border-radius:20px;padding:0.25rem 0.8rem;font-size:0.78rem;font-weight:500;min-width:52px;text-align:center;font-variant-numeric:tabular-nums;}
    .timer-pill.urgent{color:var(--gm-red);}
    .conn-dot{display:flex;align-items:center;gap:0.35rem;font-size:0.7rem;color:var(--gm-muted);}
    .dot{width:7px;height:7px;border-radius:50%;background:#fbbc04;flex-shrink:0;}
    .dot.live{background:var(--gm-green);animation:blink 2s infinite;}
    @keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}

    .video-area{flex:1;min-height:0;position:relative;overflow:hidden;background:#111;}
    .remote-tile{position:absolute;inset:0;}
    #remote-video{width:100%;height:100%;object-fit:cover;display:block;}

    .cam-off-overlay{
      position:absolute;inset:0;background:#1c1c1f;
      display:none;flex-direction:column;align-items:center;justify-content:center;gap:0.8rem;z-index:2;
    }
    .cam-off-overlay.show{display:flex;}
    .co-avatar{
      width:72px;height:72px;border-radius:50%;
      border:3px solid rgba(255,255,255,0.15);background:var(--gm-surface);
      display:flex;align-items:center;justify-content:center;
      font-size:1.5rem;font-weight:700;overflow:hidden;
    }
    .co-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}

    .waiting-overlay{
      position:absolute;inset:0;
      display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.8rem;
      background:#1c1c1f;z-index:3;
    }
    .waiting-avatar{
      width:72px;height:72px;border-radius:50%;
      background:linear-gradient(135deg,#1a73e8,#0d47a1);
      display:flex;align-items:center;justify-content:center;
      font-size:1.5rem;font-weight:700;position:relative;overflow:hidden;
    }
    .waiting-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .pulse-ring{
      position:absolute;width:72px;height:72px;border-radius:50%;
      border:2px solid rgba(26,115,232,0.5);
      animation:pulse 2s ease-out infinite;pointer-events:none;
    }
    @keyframes pulse{0%{transform:scale(1);opacity:1}100%{transform:scale(1.75);opacity:0}}

    .name-tag{
      position:absolute;bottom:0.6rem;left:0.6rem;
      background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);
      border-radius:6px;padding:0.25rem 0.6rem;font-size:0.72rem;font-weight:500;z-index:4;
    }

    /* Permission error overlay */
    .perm-error-overlay{
      position:absolute;inset:0;
      display:none;flex-direction:column;align-items:center;justify-content:center;gap:0.9rem;
      background:#1c1c1f;z-index:10;padding:1.5rem;text-align:center;
    }
    .perm-error-overlay.show{display:flex;}
    .perm-error-icon{
      width:60px;height:60px;border-radius:50%;
      background:rgba(234,67,53,0.15);border:2px solid rgba(234,67,53,0.3);
      display:flex;align-items:center;justify-content:center;
    }
    .perm-error-icon svg{width:28px;height:28px;stroke:#ea4335;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
    .perm-error-title{font-size:1rem;font-weight:700;color:#e8eaed;}
    .perm-error-body{font-size:0.8rem;color:#9aa0a6;line-height:1.65;max-width:320px;}
    .perm-error-steps{
      background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);
      border-radius:10px;padding:0.75rem 1rem;text-align:left;
      font-size:0.78rem;color:#9aa0a6;line-height:1.8;max-width:320px;width:100%;
    }
    .perm-error-steps strong{color:#e8eaed;}
    .perm-reload-btn{
      background:var(--gm-blue);color:#fff;border:none;border-radius:24px;
      padding:0.55rem 1.4rem;font-size:0.85rem;font-weight:600;cursor:pointer;
      font-family:inherit;box-shadow:0 2px 10px rgba(26,115,232,0.4);
      -webkit-tap-highlight-color:transparent;margin-top:0.3rem;
    }

    /* ── Self tile: bigger + draggable ── */
    .self-tile{
      position:absolute;
      bottom:0.9rem;
      right:0.9rem;
      width:150px;
      height:200px;
      border-radius:12px;
      overflow:hidden;
      background:#2a2b2d;
      z-index:20;
      box-shadow:0 6px 24px rgba(0,0,0,0.75);
      border:2px solid rgba(255,255,255,0.18);
      cursor:grab;
      touch-action:none;
      user-select:none;
    }
    .self-tile:active{cursor:grabbing;}
    @media(max-width:600px){
      .self-tile{width:120px;height:160px;}
    }
   #local-video-raw{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;transform:scaleX(-1);}
#local-canvas{width:100%;height:100%;object-fit:cover;display:block;transform:scaleX(-1);}
    .self-cam-off{
      position:absolute;inset:0;background:#2a2b2d;
      display:none;flex-direction:column;align-items:center;justify-content:center;gap:0.4rem;
    }
    .self-cam-off.show{display:flex;}
    .self-cam-off-avatar{
      width:44px;height:44px;border-radius:50%;background:var(--gm-surface);
      display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;overflow:hidden;
    }
    .self-cam-off-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .self-name-tag{
      position:absolute;bottom:0;left:0;right:0;
      background:rgba(0,0,0,0.7);text-align:center;
      font-size:0.6rem;font-weight:500;padding:0.18rem 0.2rem;
    }
    .seg-loading{
      position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
      font-size:0.65rem;color:var(--gm-muted);text-align:center;z-index:5;pointer-events:none;
    }

    .controls{
      height:var(--ctrl-h);min-height:var(--ctrl-h);
      background:var(--gm-bg);
      display:flex;align-items:center;justify-content:space-around;
      flex-shrink:0;border-top:1px solid rgba(255,255,255,0.08);
      padding:0 0.3rem;padding-bottom:env(safe-area-inset-bottom,0px);z-index:30;
    }
    .ctrl-sep{width:1px;height:28px;background:rgba(255,255,255,0.1);}
    .cbtn{
      display:flex;flex-direction:column;align-items:center;gap:0.18rem;
      background:none;border:none;cursor:pointer;color:var(--gm-text);
      font-family:inherit;position:relative;
      padding:0.2rem 0.5rem;-webkit-tap-highlight-color:transparent;min-width:54px;
    }
    .cbtn-icon{
      width:44px;height:44px;border-radius:50%;background:var(--gm-surface);
      display:flex;align-items:center;justify-content:center;
    }
    .cbtn:active .cbtn-icon{transform:scale(0.92);}
    .cbtn.off .cbtn-icon{background:var(--gm-red);}
    .cbtn svg{width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
    .cbtn-lbl{font-size:0.54rem;color:var(--gm-muted);white-space:nowrap;}
    .cbtn-end .cbtn-icon{background:var(--gm-red)!important;width:52px;height:52px;box-shadow:0 2px 12px rgba(234,67,53,0.5);}
    .cbtn-end svg{width:21px;height:21px;}
    .chat-badge{
      position:absolute;top:0;right:4px;width:15px;height:15px;border-radius:50%;
      background:var(--gm-red);font-size:0.52rem;font-weight:700;
      display:none;align-items:center;justify-content:center;color:#fff;
    }
    .chat-badge.show{display:flex;}

    .chat-panel{
      position:fixed;top:0;left:0;right:0;bottom:0;
      background:var(--gm-surface2);display:flex;flex-direction:column;
      z-index:50;transform:translateX(100%);transition:transform .25s cubic-bezier(.4,0,.2,1);
    }
    .chat-panel.open{transform:translateX(0);}
    .chat-hd{
      padding:0.8rem 1rem;padding-top:calc(env(safe-area-inset-top,0px) + 0.8rem);
      border-bottom:1px solid rgba(255,255,255,0.08);
      display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
    }
    .chat-hd h3{font-size:0.9rem;font-weight:600;}
    .chat-hd button{background:none;border:none;color:var(--gm-muted);cursor:pointer;font-size:1.3rem;line-height:1;padding:0.3rem;-webkit-tap-highlight-color:transparent;}
    .chat-msgs{
      flex:1;overflow-y:auto;padding:0.8rem;
      display:flex;flex-direction:column;gap:0.6rem;-webkit-overflow-scrolling:touch;
    }
    .chat-msgs::-webkit-scrollbar{width:3px;}
    .chat-msgs::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.1);border-radius:4px;}
    .chat-empty-msg{text-align:center;padding:2rem 1rem;font-size:0.78rem;color:var(--gm-muted);}
    .msg{max-width:85%;}
    .msg.me{align-self:flex-end;}.msg.them{align-self:flex-start;}
    .msg-name{font-size:0.62rem;font-weight:600;color:var(--gm-muted);margin-bottom:0.15rem;padding:0 0.35rem;}
    .msg-bubble{padding:0.5rem 0.8rem;border-radius:16px;font-size:0.85rem;line-height:1.45;word-break:break-word;}
    .msg.me .msg-bubble{background:var(--gm-blue);color:#fff;border-bottom-right-radius:4px;}
    .msg.them .msg-bubble{background:var(--gm-surface);color:var(--gm-text);border-bottom-left-radius:4px;}
    .msg-time{font-size:0.58rem;color:var(--gm-muted);margin-top:0.15rem;padding:0 0.35rem;}
    .msg.me .msg-time{text-align:right;}.msg.them .msg-time{text-align:left;}
    .chat-input-row{
      padding:0.6rem;padding-bottom:calc(env(safe-area-inset-bottom,0px) + 0.6rem);
      border-top:1px solid rgba(255,255,255,0.08);
      display:flex;gap:0.5rem;flex-shrink:0;
    }
    .chat-input{
      flex:1;background:var(--gm-surface);border:none;border-radius:20px;
      padding:0.55rem 0.9rem;color:var(--gm-text);font-family:inherit;
      font-size:1rem;outline:none;resize:none;max-height:80px;
    }
    .chat-input::placeholder{color:var(--gm-muted);}
    .chat-send{
      width:40px;height:40px;border-radius:50%;background:var(--gm-blue);border:none;
      cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;
      -webkit-tap-highlight-color:transparent;
    }
    .chat-send svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

    /* BG panel: none + blur only */
    .bg-panel{
  position:fixed;
  bottom:calc(var(--ctrl-h) + env(safe-area-inset-bottom,0px) + 0.5rem);
  left:0.5rem;right:0.5rem;
  background:var(--gm-surface2);border:1px solid rgba(255,255,255,0.1);
  border-radius:14px;padding:0.6rem;z-index:40;display:none;
  max-height:220px;overflow-y:auto;
  box-shadow:0 8px 32px rgba(0,0,0,0.6);
}
.bg-panel.open{display:block;}
.bg-panel-hd{font-size:0.78rem;font-weight:700;margin-bottom:0.5rem;display:flex;justify-content:space-between;align-items:center;}
.bg-panel-hd button{background:none;border:none;color:var(--gm-muted);cursor:pointer;font-size:1rem;}
.bg-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:0.35rem;}
.bgo{
  aspect-ratio:16/10;border-radius:6px;border:2px solid transparent;
  cursor:pointer;overflow:hidden;position:relative;background:#333;
}
.bgo:active{border-color:var(--gm-blue);}
.bgo.on{border-color:var(--gm-blue);box-shadow:0 0 0 1px var(--gm-blue);}
.bgo img{width:100%;height:100%;object-fit:cover;display:block;}
.bgo-lbl{
  position:absolute;bottom:0;left:0;right:0;
  background:rgba(0,0,0,0.6);font-size:0.45rem;font-weight:600;
  text-align:center;padding:0.1rem;color:#fff;
}

    #toast{
      position:fixed;
      top:calc(var(--top-h) + env(safe-area-inset-top,0px) + 0.3rem);
      left:50%;transform:translateX(-50%);
      background:rgba(30,30,30,0.95);padding:0.45rem 1rem;border-radius:8px;
      font-size:0.8rem;z-index:60;opacity:0;transition:opacity .25s;
      pointer-events:none;max-width:88vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
      box-shadow:0 2px 8px rgba(0,0,0,0.5);
    }
    #toast.on{opacity:1;}
    @keyframes spin{to{transform:rotate(360deg)}}

    /* ── Background noise notice ── */
    .noise-notice{
      position:absolute;
      top:0.6rem;
      left:50%;
      transform:translateX(-50%);
      background:rgba(0,0,0,0.72);
      border:1px solid rgba(255,255,255,0.12);
      border-radius:8px;
      padding:0.45rem 0.85rem;
      font-size:1.2rem;
      font-weight:bold;
      color:rgba(255,255,255,0.75);
      line-height:1.5;
      text-align:center;
      z-index:25;
      max-width:88vw;
      white-space:nowrap;
      pointer-events:none;
    }
    @media(max-width:480px){
      .noise-notice{font-size:0.63rem;padding:0.38rem 0.7rem;white-space:normal;text-align:center;width:92vw;}
    }
  </style>
</head>
<body>

<div class="topbar">
  <div style="display:flex;align-items:center;gap:0.6rem;">
    <div class="tb-logo">TELE<span>-</span>CARE</div>
    <span style="font-size:0.68rem;color:var(--gm-muted);"><?= date('g:i A', $appt_ts) ?> &middot; Teleconsult</span>
  </div>
  <div style="display:flex;align-items:center;gap:0.5rem;">
    <div class="conn-dot"><div class="dot" id="conn-dot"></div><span id="conn-lbl">Connecting...</span></div>
    <div class="timer-pill" id="timer">--:--</div>
  </div>
</div>

<div class="video-area">

  <!-- Background noise notice -->
  <div class="noise-notice" id="noise-notice">
    Note: AI  transcription and summarization accuracy may be reduced if there is significant background noise during the call.
  </div>

  <div class="remote-tile">
    <video id="remote-video" autoplay playsinline></video>
    <div class="cam-off-overlay" id="remote-cam-off">
      <div class="co-avatar">
        <?php if ($doc_photo): ?><img src="../<?= htmlspecialchars($doc_photo) ?>" alt=""/><?php else: echo $doc_initials; endif; ?>
      </div>
      <div style="font-size:0.9rem;font-weight:600;">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
      <div style="font-size:0.75rem;color:var(--gm-muted);">Camera off</div>
    </div>

    <!-- Permission denied error overlay -->
    <div class="perm-error-overlay" id="perm-error-overlay">
      <div class="perm-error-icon">
        <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.723v6.554a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8zM3 3l18 18"/></svg>
      </div>
      <div class="perm-error-title">Camera &amp; Microphone Access Required</div>
      <div class="perm-error-body">Your browser blocked access to the camera and microphone. You need to allow access to join the call.</div>
      <div class="perm-error-steps">
        <strong>To fix this in Chrome:</strong><br/>
        1. Click the lock icon in the address bar<br/>
        2. Set Camera to <strong>Allow</strong><br/>
        3. Set Microphone to <strong>Allow</strong><br/>
        4. Click the button below to reload
      </div>
      <button class="perm-reload-btn" onclick="location.reload()">Reload and Try Again</button>
    </div>

    <div class="waiting-overlay" id="waiting-overlay">
      <div style="position:relative;display:flex;align-items:center;justify-content:center;">
        <div class="pulse-ring"></div>
        <div class="waiting-avatar">
          <?php if ($doc_photo): ?><img src="../<?= htmlspecialchars($doc_photo) ?>" alt=""/><?php else: echo $doc_initials; endif; ?>
        </div>
      </div>
      <div style="font-size:1rem;font-weight:600;">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
      <div style="font-size:0.78rem;color:var(--gm-muted);" id="waiting-sub">Waiting for doctor to join...</div>
    </div>
    <div class="name-tag">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
  </div>

  <!-- Self tile: bigger + draggable -->
  <div class="self-tile" id="self-tile">
    <video id="local-video-raw" autoplay muted playsinline></video>
    <canvas id="local-canvas"></canvas>
    <div class="self-cam-off" id="self-cam-off">
      <div class="self-cam-off-avatar">
        <?php if ($pat_photo): ?><img src="../<?= htmlspecialchars($pat_photo) ?>" alt=""/><?php else: echo $pat_initials; endif; ?>
      </div>
    </div>
    <div class="seg-loading" id="seg-loading">Cam</div>
    <div class="self-name-tag"><?= htmlspecialchars($pat_name) ?></div>
  </div>
</div>

<!-- BG panel: none + blur only -->
<div class="bg-panel" id="bgpanel">
  <div class="bg-panel-hd"><span>Background</span><button onclick="toggleBg()">&#10005;</button></div>
  <div class="bg-grid">
  <div class="bgo on" onclick="setBg('none',this)" style="background:#2a2b2d;display:flex;align-items:center;justify-content:center;">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#9aa0a6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M4.93 4.93l14.14 14.14"/></svg>
    <span class="bgo-lbl">None</span>
  </div>
  <div class="bgo" onclick="setBg('blur',this)" style="background:linear-gradient(135deg,#74b9ff,#0984e3);display:flex;align-items:center;justify-content:center;">
    <span class="bgo-lbl">Blur</span>
  </div>

  <!-- Nature -->
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&q=60" alt="Mountains"/>
    <span class="bgo-lbl">Mountains</span>
  </div>
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1448375240586-882707db888b?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1448375240586-882707db888b?w=400&q=60" alt="Forest"/>
    <span class="bgo-lbl">Forest</span>
  </div>
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&q=60" alt="Beach"/>
    <span class="bgo-lbl">Beach</span>
  </div>
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1470770903676-69b98201ea1c?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1470770903676-69b98201ea1c?w=400&q=60" alt="Lake"/>
    <span class="bgo-lbl">Lake</span>
  </div>

  <!-- Office -->
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1497366216548-37526070297c?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?w=400&q=60" alt="Office"/>
    <span class="bgo-lbl">Office</span>
  </div>
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1524758631624-e2822e304c36?w=400&q=60" alt="Desk"/>
    <span class="bgo-lbl">Desk</span>
  </div>
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1604328698692-f76ea9498e76?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1604328698692-f76ea9498e76?w=400&q=60" alt="Bookshelf"/>
    <span class="bgo-lbl">Bookshelf</span>
  </div>
  <div class="bgo" onclick="setBg('https://images.unsplash.com/photo-1533090161767-e6ffed986c88?w=1280&q=80',this)">
    <img src="https://images.unsplash.com/photo-1533090161767-e6ffed986c88?w=400&q=60" alt="Lounge"/>
    <span class="bgo-lbl">Lounge</span>
  </div>
</div>
  </div>
</div>

<div class="chat-panel" id="chat-panel">
  <div class="chat-hd">
    <h3>In-call Chat</h3>
    <button onclick="toggleChat()">&#10005;</button>
  </div>
  <div class="chat-msgs" id="chat-msgs">
    <div class="chat-empty-msg" id="chat-empty">No messages yet.<br/>Say hello to your doctor!</div>
  </div>
  <div class="chat-input-row">
    <textarea class="chat-input" id="chat-input" placeholder="Send a message..." rows="1"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChat();}"></textarea>
    <button class="chat-send" onclick="sendChat()">
      <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
    </button>
  </div>
</div>

<div id="toast"></div>

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
    <div class="cbtn-icon"><svg viewBox="0 0 24 24" style="width:21px;height:21px;"><path d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/></svg></div>
    <span class="cbtn-lbl" style="color:var(--gm-red);">Leave</span>
  </button>
  <div class="ctrl-sep"></div>
  <button class="cbtn" onclick="toggleBg()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
    <span class="cbtn-lbl">BG</span>
  </button>
  <button class="cbtn" onclick="toggleChat()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg></div>
    <div class="chat-badge" id="chat-badge">0</div>
    <span class="cbtn-lbl">Chat</span>
  </button>
</div>

<div id="leaving-overlay" style="display:none;position:fixed;inset:0;background:#202124;z-index:999;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem;">
  <div style="width:56px;height:56px;border:4px solid rgba(255,255,255,0.1);border-top-color:#1a73e8;border-radius:50%;animation:spin 1s linear infinite;"></div>
  <div style="text-align:center;">
    <div style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">AI is summarizing your consultation...</div>
    <div style="font-size:0.8rem;color:#9aa0a6;">Please wait, this may take a minute.<br/>You will be redirected automatically.</div>
  </div>
</div>

<script>
const ROOM_ID  = <?= json_encode($room_id) ?>;
const ROLE     = 'patient';
const APPT_TS  = <?= $appt_ts ?>;
const END_TS   = <?= $end_ts ?>;
const APPT_ID  = <?= $appt_id ?>;
const MY_NAME  = <?= json_encode($pat_name) ?>;
const WS_URL = `wss://mortgage-incredible-treatment-headed.trycloudflare.com/ws/${ROOM_ID}/${ROLE}`;
const ICE = {
  iceServers: [
    { urls: "stun:stun.relay.metered.ca:80" },
    { urls: "turn:global.relay.metered.ca:80", username: "6f40b21940d983d92c23df8c", credential: "h/GyYZSAdVA6RW0U" },
    { urls: "turn:global.relay.metered.ca:80?transport=tcp", username: "6f40b21940d983d92c23df8c", credential: "h/GyYZSAdVA6RW0U" },
    { urls: "turn:global.relay.metered.ca:443", username: "6f40b21940d983d92c23df8c", credential: "h/GyYZSAdVA6RW0U" },
    { urls: "turns:global.relay.metered.ca:443?transport=tcp", username: "6f40b21940d983d92c23df8c", credential: "h/GyYZSAdVA6RW0U" },
  ]
};

let ws, pc, rawStream, segInterval, selfieSegmentation, processedStream;
let mediaRecorder = null;
let audioChunks   = [];
let chatMessages  = [];
let micOn = true, camOn = true, bgMode = 'none', bgImg = null;
let chatOpen = false, unread = 0;
let callWasConnected = false;
let timerEnded = false;
let isDestroyed = false;
let wsReconnectDelay = 1500;
let pendingIceCandidates = [];

const canvas = document.getElementById('local-canvas');
const ctx    = canvas.getContext('2d');
canvas.width = 640; canvas.height = 480;

// ── Draggable self-tile ───────────────────────────────────────────────────
(function(){
  const tile = document.getElementById('self-tile');
  let dragging = false, startX, startY;

  function getPos() {
    const rect   = tile.getBoundingClientRect();
    const parent = tile.parentElement.getBoundingClientRect();
    return { left: rect.left - parent.left, top: rect.top - parent.top };
  }
  function startDrag(cx, cy) {
    dragging = true;
    const pos = getPos();
    tile.style.right  = 'auto';
    tile.style.bottom = 'auto';
    tile.style.left   = pos.left + 'px';
    tile.style.top    = pos.top  + 'px';
    startX = cx; startY = cy;
  }

  tile.addEventListener('mousedown', e => { startDrag(e.clientX, e.clientY); e.preventDefault(); });
  tile.addEventListener('touchstart', e => { startDrag(e.touches[0].clientX, e.touches[0].clientY); }, { passive: true });

  document.addEventListener('mousemove', e => {
    if (!dragging) return;
    const parent = tile.parentElement.getBoundingClientRect();
    const newLeft = Math.max(0, Math.min(parent.width  - tile.offsetWidth,  parseFloat(tile.style.left) + e.clientX - startX));
    const newTop  = Math.max(0, Math.min(parent.height - tile.offsetHeight, parseFloat(tile.style.top)  + e.clientY - startY));
    tile.style.left = newLeft + 'px'; tile.style.top = newTop + 'px';
    startX = e.clientX; startY = e.clientY;
  });
  document.addEventListener('touchmove', e => {
    if (!dragging) return;
    const parent = tile.parentElement.getBoundingClientRect();
    const newLeft = Math.max(0, Math.min(parent.width  - tile.offsetWidth,  parseFloat(tile.style.left) + e.touches[0].clientX - startX));
    const newTop  = Math.max(0, Math.min(parent.height - tile.offsetHeight, parseFloat(tile.style.top)  + e.touches[0].clientY - startY));
    tile.style.left = newLeft + 'px'; tile.style.top = newTop + 'px';
    startX = e.touches[0].clientX; startY = e.touches[0].clientY;
  }, { passive: true });

  document.addEventListener('mouseup',  () => { dragging = false; });
  document.addEventListener('touchend', () => { dragging = false; });
})();

// ── Noise notice auto-hide after 8 s ─────────────────────────────────────
setTimeout(() => {
  const n = document.getElementById('noise-notice');
  if (n) { n.style.transition = 'opacity 1s'; n.style.opacity = '0'; setTimeout(() => n.remove(), 1100); }
}, 8000);

function isSafariOrIOS() {
  return /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.userAgent.includes('Safari') && !navigator.userAgent.includes('Chrome') && !navigator.userAgent.includes('Firefox'));
}

async function init() {
  if (navigator.permissions) {
    try {
      const camPerm = await navigator.permissions.query({ name: 'camera' });
      const micPerm = await navigator.permissions.query({ name: 'microphone' });
      if (camPerm.state === 'denied' || micPerm.state === 'denied') {
        showPermError(); connectWS(); startTimer(); updateChatAvailability(); return;
      }
    } catch(e) {}
  }
  try {
    rawStream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: true });
    document.getElementById('local-video-raw').srcObject = rawStream;
  } catch(e) {
    try {
      rawStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      document.getElementById('local-video-raw').srcObject = rawStream;
    } catch(e2) {
      showPermError(); connectWS(); startTimer(); updateChatAvailability(); return;
    }
  }
  initSeg(); connectWS(); startTimer(); updateChatAvailability();
}

function showPermError() {
  document.getElementById('waiting-overlay').style.display = 'none';
  document.getElementById('perm-error-overlay').classList.add('show');
  document.getElementById('self-cam-off').classList.add('show');
  showToast('Camera or microphone access was blocked. See instructions on screen.');
}

function initSeg() {
  if (isSafariOrIOS()) {
    processedStream = rawStream;
    document.getElementById('seg-loading').style.display = 'none';
    const raw = document.getElementById('local-video-raw');
    raw.style.cssText = 'position:static;width:100%;height:100%;object-fit:cover;opacity:1;pointer-events:none;';
    document.getElementById('local-canvas').style.display = 'none';
    return;
  }
  selfieSegmentation = new SelfieSegmentation({
    locateFile: f => `https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/${f}`
  });
  selfieSegmentation.setOptions({ modelSelection: 1 });
  selfieSegmentation.onResults(onSegResult);
  selfieSegmentation.initialize().then(() => {
    document.getElementById('seg-loading').style.display = 'none';
    processedStream = canvas.captureStream(30);
    rawStream.getAudioTracks().forEach(t => processedStream.addTrack(t));
    const vid = document.getElementById('local-video-raw');
    segInterval = setInterval(async () => {
      if (vid.readyState >= 2) await selfieSegmentation.send({ image: vid });
    }, 33);
  }).catch(() => { document.getElementById('seg-loading').style.display = 'none'; });
}

function onSegResult(r) {
  ctx.save(); ctx.clearRect(0, 0, 640, 480);
  ctx.drawImage(r.segmentationMask, 0, 0, 640, 480);
  ctx.globalCompositeOperation = 'source-in';
  ctx.drawImage(r.image, 0, 0, 640, 480);
  ctx.globalCompositeOperation = 'destination-over';
  if (bgMode === 'none')            { ctx.drawImage(r.image, 0, 0, 640, 480); }
  else if (bgMode === 'blur')       { ctx.filter = 'blur(18px)'; ctx.drawImage(r.image, -30, -30, 700, 540); ctx.filter = 'none'; }
  else if (bgImg && bgImg.complete) { ctx.drawImage(bgImg, 0, 0, 640, 480); }
  ctx.restore();
}

function connectWS() {
  if (isDestroyed) return;
  ws = new WebSocket(WS_URL);
  ws.onopen = () => { wsReconnectDelay = 1500; setConn(false, 'Waiting for doctor...'); };
  ws.onmessage = async ({ data }) => {
    let m; try { m = JSON.parse(data); } catch(e) { return; }
    if (m.type === 'peer_joined') {
      setConn(false, 'Doctor joined!');
      if (Date.now() / 1000 < APPT_TS) {
        const sub = document.getElementById('waiting-sub');
        if (sub) sub.textContent = 'Doctor is here early. Call starts at ' + new Date(APPT_TS * 1000).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
        showToast('Doctor is here early. Waiting for scheduled time...');
      } else {
        showToast('Doctor joined. Waiting for call...');
      }
    }
    else if (m.type === 'offer') {
      if (!rawStream) { showToast('Cannot answer call: no camera or microphone access.'); return; }
      if (pc) { try { pc.close(); } catch(e) {} pc = null; }
      pendingIceCandidates = [];
      pc = new RTCPeerConnection(ICE);
      pc._remoteStream = new MediaStream();
      const stream = processedStream || rawStream;
      if (stream && stream.getTracks().length > 0) { stream.getTracks().forEach(t => pc.addTrack(t, stream)); }
      pc.ontrack = e => {
        if (e.streams && e.streams[0] && e.streams[0].getTracks().length > 0) { pc._remoteStream = e.streams[0]; }
        else { pc._remoteStream.addTrack(e.track); }
        const rv = document.getElementById('remote-video');
        rv.srcObject = pc._remoteStream;
        rv.muted = true;
        rv.play().then(() => { rv.muted = false; }).catch(() => { rv.muted = false; });
        document.getElementById('waiting-overlay').style.display = 'none';
        setConn(true, 'Connected');
        callWasConnected = true;
        showToast('Call connected!');
        const now = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        chatMessages.push(`[${now}] System: Call connected`);
        startRecording();
      };
      pc.onicecandidate = e => {
        if (e.candidate && ws && ws.readyState === WebSocket.OPEN) { ws.send(JSON.stringify({ type: 'ice', candidate: e.candidate })); }
      };
      pc.onconnectionstatechange = () => {
        if (!pc) return;
        if (pc.connectionState === 'connected') { callWasConnected = true; setConn(true, 'Connected'); }
        if (pc.connectionState === 'failed') { showToast('Connection failed. Waiting for doctor to retry...'); setConn(false, 'Reconnecting...'); }
      };
      try {
        await pc.setRemoteDescription(m.sdp);
        for (const c of pendingIceCandidates) { try { await pc.addIceCandidate(c); } catch(e) {} }
        pendingIceCandidates = [];
        let ans = await pc.createAnswer();
        ans = new RTCSessionDescription({
          type: ans.type,
          sdp: ans.sdp.replace(/m=video (\d+) UDP\/TLS\/RTP\/SAVPF ([\d ]+)/g, (match, port, payloads) => {
            const h264 = ['126','97','120','123'];
            const arr = payloads.split(' ');
            const preferred = [...h264.filter(p => arr.includes(p)), ...arr.filter(p => !h264.includes(p))];
            return `m=video ${port} UDP/TLS/RTP/SAVPF ${preferred.join(' ')}`;
          })
        });
        await pc.setLocalDescription(ans);
        if (ws && ws.readyState === WebSocket.OPEN) { ws.send(JSON.stringify({ type: 'answer', sdp: ans })); }
      } catch(e) {
        showToast('Answer failed. Waiting for retry...');
        if (pc) { try { pc.close(); } catch(_) {} pc = null; }
      }
    }
    else if (m.type === 'ice') {
      if (m.candidate) {
        if (pc && pc.remoteDescription) { try { await pc.addIceCandidate(m.candidate); } catch(e) {} }
        else { pendingIceCandidates.push(m.candidate); }
      }
    }
    else if (m.type === 'peer_left') {
      document.getElementById('remote-video').srcObject = null;
      if (!document.getElementById('perm-error-overlay').classList.contains('show')) {
        document.getElementById('waiting-overlay').style.display = 'flex';
      }
      const sub = document.getElementById('waiting-sub');
      if (sub) sub.textContent = 'Doctor disconnected...';
      setConn(false, 'Doctor left');
      if (pc) { try { pc.close(); } catch(e) {} pc = null; }
      pendingIceCandidates = [];
      showToast('Doctor left the call');
    }
    else if (m.type === 'chat') {
      if (!isChatWindowOpen()) return;
      addMsg(m.text, m.name || 'Doctor', false);
    }
    else if (m.type === 'cam_toggle') {
      const camOff = document.getElementById('remote-cam-off');
      m.cam_on ? camOff.classList.remove('show') : camOff.classList.add('show');
    }
  };
  ws.onclose = () => {
    if (isDestroyed) return;
    setConn(false, 'Reconnecting...');
    wsReconnectDelay = Math.min(wsReconnectDelay * 1.5, 10000);
    setTimeout(connectWS, wsReconnectDelay);
  };
  ws.onerror = () => { try { ws.close(); } catch(e) {} };
}

function startRecording() {
  if (!rawStream) return;
  try {
    const audioOnly = new MediaStream(rawStream.getAudioTracks());
    const mimeType =
      MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' :
      MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' :
      MediaRecorder.isTypeSupported('audio/ogg') ? 'audio/ogg' : '';
    const options = mimeType ? { mimeType } : {};
    mediaRecorder = new MediaRecorder(audioOnly, options);
    audioChunks = [];
    mediaRecorder.ondataavailable = e => { if (e.data && e.data.size > 0) audioChunks.push(e.data); };
    mediaRecorder.start(5000);
  } catch(e) { console.warn('Recording failed:', e); }
}

async function endCall(auto = false) {
  if (!auto && !confirm('Leave the call?')) return;
  isDestroyed = true;
  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    await Promise.race([
      new Promise(resolve => { mediaRecorder.onstop = resolve; mediaRecorder.stop(); }),
      new Promise(resolve => setTimeout(resolve, 1000))
    ]);
  }
  clearInterval(segInterval);
  try { selfieSegmentation?.close(); } catch(e) {}
  try { ws?.close(); } catch(e) {}
  try { pc?.close(); } catch(e) {}
  rawStream?.getTracks().forEach(t => t.stop());
  if (callWasConnected) {
    document.getElementById('leaving-overlay').style.display = 'flex';
    const now = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    chatMessages.push(`[${now}] System: Call ended`);
    const fd = new FormData();
    fd.append('appt_id', APPT_ID); fd.append('role', ROLE);
    fd.append('chat_log', chatMessages.join('\n'));
    if (audioChunks.length > 0) {
      const blob = new Blob(audioChunks, { type: mediaRecorder?.mimeType || 'audio/webm' });
      if (blob.size > 1000) {
        const ext = (mediaRecorder?.mimeType || '').includes('mp4') ? 'mp4' :
                    (mediaRecorder?.mimeType || '').includes('ogg') ? 'ogg' : 'webm';
        fd.append('audio', blob, `consultation.${ext}`);
      }
    }
    try {
      const startTime = Date.now(); const maxWait = 120000;
      const checkSummary = async () => {
        try {
          const response = await fetch(`router.php?page=check_summary&appt_id=${APPT_ID}`);
          const data = await response.json();
          if (data.done) { window.location.href = 'visits.php'; }
          else if (Date.now() - startTime > maxWait) { window.location.href = 'visits.php'; }
          else { setTimeout(checkSummary, 2000); }
        } catch(e) { setTimeout(checkSummary, 5000); }
      };
      await fetch('process_consultation.php_v2', { method: 'POST', body: fd });
      setTimeout(checkSummary, 2000);
    } catch(e) { setTimeout(() => { window.location.href = 'visits.php'; }, 5000); }
  } else {
    window.location.href = 'visits.php';
  }
}

function autoComplete() {
  if (Date.now() / 1000 < APPT_TS) return;
  fetch('auto_complete_appt.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `appt_id=${APPT_ID}&role=patient` }).catch(() => {});
}

function toggleMic() {
  if (!rawStream) { showToast('No microphone access.'); return; }
  micOn = !micOn;
  rawStream.getAudioTracks().forEach(t => t.enabled = micOn);
  document.getElementById('btn-mic').classList.toggle('off', !micOn);
  document.getElementById('lbl-mic').textContent = micOn ? 'Mute' : 'Unmute';
  showToast(micOn ? 'Mic on' : 'Muted');
}
function toggleCam() {
  if (!rawStream) { showToast('No camera access.'); return; }
  camOn = !camOn;
  rawStream.getVideoTracks().forEach(t => t.enabled = camOn);
  document.getElementById('btn-cam').classList.toggle('off', !camOn);
  document.getElementById('lbl-cam').textContent = camOn ? 'Camera' : 'Cam Off';
  if (isSafariOrIOS()) {
    document.getElementById('local-video-raw').style.opacity = camOn ? '1' : '0';
  } else {
    document.getElementById('local-canvas').style.display = camOn ? 'block' : 'none';
  }
  document.getElementById('self-cam-off').classList.toggle('show', !camOn);
  if (ws && ws.readyState === WebSocket.OPEN) { ws.send(JSON.stringify({ type: 'cam_toggle', cam_on: camOn })); }
  showToast(camOn ? 'Camera on' : 'Camera off');
}
function toggleBg() { document.getElementById('bgpanel').classList.toggle('open'); }
function setBg(mode, el) {
  bgMode = mode; bgImg = null;
  document.querySelectorAll('.bgo').forEach(e => e.classList.remove('on'));
  el.classList.add('on');
  document.getElementById('bgpanel').classList.remove('open');
  if (mode !== 'none' && mode !== 'blur') { bgImg = new Image(); bgImg.crossOrigin = 'anonymous'; bgImg.src = mode; }
  showToast(mode === 'none' ? 'Background removed' : 'Background blurred');
}

function isChatWindowOpen() {
  const nowSec = Math.floor(Date.now() / 1000);
  return nowSec >= APPT_TS && nowSec <= END_TS;
}
function updateChatAvailability() {
  const btn = document.querySelector('.controls [onclick="toggleChat()"]');
  const panel = document.getElementById('chat-panel');
  const input = document.getElementById('chat-input');
  const canChat = isChatWindowOpen();
  if (btn) { btn.disabled = !canChat; btn.style.opacity = canChat ? '1' : '0.55'; btn.style.pointerEvents = canChat ? 'auto' : 'none'; }
  if (input) { input.disabled = !canChat; input.placeholder = canChat ? 'Send a message...' : 'Chat opens during consultation'; }
  if (!canChat && panel) { chatOpen = false; panel.classList.remove('open'); }
}
function toggleChat() {
  if (!isChatWindowOpen()) { showToast('Chat opens during your consultation hour.'); return; }
  chatOpen = !chatOpen;
  document.getElementById('chat-panel').classList.toggle('open', chatOpen);
  if (chatOpen) {
    unread = 0;
    const b = document.getElementById('chat-badge');
    b.classList.remove('show'); b.textContent = '0';
    setTimeout(() => document.getElementById('chat-input').focus(), 300);
    document.getElementById('chat-msgs').scrollTop = 99999;
  }
}
function sendChat() {
  if (!isChatWindowOpen()) { showToast('Chat is disabled outside consultation hour.'); return; }
  const inp = document.getElementById('chat-input');
  const text = inp.value.trim();
  if (!text || !ws || ws.readyState !== WebSocket.OPEN) return;
  ws.send(JSON.stringify({ type: 'chat', text, name: MY_NAME }));
  addMsg(text, 'You', true);
  inp.value = '';
}
function addMsg(text, name, isMe) {
  const c = document.getElementById('chat-msgs');
  document.getElementById('chat-empty').style.display = 'none';
  const now = new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
  chatMessages.push(`[${now}] ${name}: ${text}`);
  const d = document.createElement('div');
  d.className = 'msg ' + (isMe ? 'me' : 'them');
  d.innerHTML = `<div class="msg-name">${esc(name)}</div><div class="msg-bubble">${esc(text)}</div><div class="msg-time">${now}</div>`;
  c.appendChild(d);
  c.scrollTop = c.scrollHeight;
  if (!isMe && !chatOpen) {
    unread++;
    const b = document.getElementById('chat-badge');
    b.textContent = unread > 9 ? '9+' : unread;
    b.classList.add('show');
    showToast(`${name}: ${text.length > 30 ? text.slice(0,30) + '...' : text}`);
  }
}

function startTimer() {
  const el = document.getElementById('timer');
  el.textContent = '60:00';
  let guardActive = true;
  setTimeout(() => { guardActive = false; }, 5000);
  setInterval(() => {
    if (isDestroyed) return;
    updateChatAvailability();
    const nowSec = Math.floor(Date.now() / 1000);
    if (nowSec < APPT_TS) {
      const w = APPT_TS - nowSec, m = Math.floor(w / 60), s = w % 60;
      el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
      el.style.color = '#fbbc04'; return;
    }
    el.style.color = '';
    const left = END_TS - nowSec;
    if (left <= 0) {
      if (timerEnded || guardActive) return;
      timerEnded = true;
      el.textContent = '00:00'; el.classList.add('urgent');
      autoComplete();
      if (callWasConnected) { showToast('Consultation ended. Leaving in 5 seconds...'); setTimeout(() => endCall(true), 5000); }
      else { showToast('Session time expired'); setTimeout(() => endCall(true), 30000); }
      return;
    }
    const m = Math.floor(left / 60), s = left % 60;
    el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    el.classList.toggle('urgent', left < 300);
  }, 1000);
}

function setConn(live, label) {
  document.getElementById('conn-dot').className = 'dot' + (live ? ' live' : '');
  document.getElementById('conn-lbl').textContent = label;
}
function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
let tT;
function showToast(msg) {
  const e = document.getElementById('toast'); e.textContent = msg; e.classList.add('on');
  clearTimeout(tT); tT = setTimeout(() => e.classList.remove('on'), 2800);
}

document.addEventListener('click', e => {
  if (!document.getElementById('bgpanel').contains(e.target) && !e.target.closest('[onclick="toggleBg()"]'))
    document.getElementById('bgpanel').classList.remove('open');
});
document.addEventListener('visibilitychange', () => {
  if (!document.hidden && rawStream) rawStream.getVideoTracks().forEach(t => { if (camOn) t.enabled = true; });
});
document.addEventListener('gesturestart', e => e.preventDefault(), { passive: false });
document.addEventListener('touchmove', e => { if (e.touches.length > 1) e.preventDefault(); }, { passive: false });

init();
</script>
</body>
</html>