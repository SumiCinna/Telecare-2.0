<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/auth.php';
header('Permissions-Policy: camera=*, microphone=*, geolocation=*');
header('Feature-Policy: camera *; microphone *');

$appt_id = (int)($_GET['appt_id'] ?? 0);
if (!$appt_id) { header('Location: appointments.php'); exit; }
// doctor/call.php
$stmt = $conn->prepare("
    SELECT a.*, p.full_name AS patient_name, p.profile_photo AS patient_photo
    FROM appointments a JOIN patients p ON p.id = a.patient_id
    WHERE a.id = ? AND a.doctor_id = ? AND a.status IN ('Confirmed','Completed')
");
$stmt->bind_param("ii", $appt_id, $doctor_id);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
if (!$appt) { header('Location: appointments.php'); exit; }

$appt_ts = strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
$now     = (new DateTime('now', new DateTimeZone('Asia/Manila')))->getTimestamp();
if ($now < ($appt_ts - 900) || $now > ($appt_ts + 3600)) { header('Location: appointments.php'); exit; }

$room_id = 'telecare-' . $appt_id . '-' . str_replace('-', '', $appt['appointment_date']);
$end_ts  = $appt_ts + 3600;

$pat_initials = strtoupper(substr($appt['patient_name'], 0, 2));
$doc_initials  = strtoupper(substr($doc['full_name'], 0, 2));
$doc_photo     = $doc['profile_photo'] ?? '';
$pat_photo     = $appt['patient_photo'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Permissions-Policy" content="camera=*, microphone=*">
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no"/>
  <title><?= htmlspecialchars($appt['patient_name']) ?> — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/@mediapipe/selfie_segmentation/selfie_segmentation.js" crossorigin="anonymous"></script>
  <style>
    :root{
      --gm-blue:#1a73e8;--gm-bg:#202124;--gm-surface:#3c4043;--gm-surface2:#2d2e30;
      --gm-red:#ea4335;--gm-green:#34a853;--gm-text:#e8eaed;--gm-muted:#9aa0a6;
      --ctrl-h:72px;
      --top-h:52px;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html{height:100%;overflow:hidden;}
    body{
      font-family:'Google Sans','Roboto',sans-serif;
      background:var(--gm-bg);
      color:var(--gm-text);
      width:100%;
      overflow:hidden;
      position:fixed;
      top:0;left:0;right:0;bottom:0;
      display:flex;
      flex-direction:column;
      height:100dvh;
    }
    .topbar{
      height:var(--top-h);
      min-height:var(--top-h);
      background:var(--gm-bg);
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:0 1rem;
      padding-top:env(safe-area-inset-top,0px);
      flex-shrink:0;
      border-bottom:1px solid rgba(255,255,255,0.06);
      z-index:10;
    }
    .tb-logo{font-size:0.85rem;font-weight:700;}
    .tb-logo span{color:var(--gm-blue);}
    .timer-pill{background:var(--gm-surface2);border-radius:20px;padding:0.25rem 0.8rem;font-size:0.78rem;font-weight:500;min-width:52px;text-align:center;font-variant-numeric:tabular-nums;}
    .timer-pill.urgent{color:var(--gm-red);}
    .conn-dot{display:flex;align-items:center;gap:0.35rem;font-size:0.7rem;color:var(--gm-muted);}
    .dot{width:7px;height:7px;border-radius:50%;background:#fbbc04;flex-shrink:0;}
    .dot.live{background:var(--gm-green);animation:blink 2s infinite;}
    @keyframes blink{0%,100%{opacity:1}50%{opacity:0.4}}

    .video-area{
      flex:1;
      min-height:0;
      position:relative;
      overflow:hidden;
      background:#111;
    }

    .remote-tile{
      position:absolute;
      inset:0;
    }
    #remote-video{
      width:100%;
      height:100%;
      object-fit:cover;
      display:block;
    }
    .cam-off-overlay{
      position:absolute;inset:0;background:#1c1c1f;
      display:none;flex-direction:column;align-items:center;justify-content:center;gap:0.8rem;z-index:2;
    }
    .cam-off-overlay.show{display:flex;}
    .co-avatar{
      width:72px;height:72px;border-radius:50%;
      border:3px solid rgba(255,255,255,0.15);background:var(--gm-surface);
      display:flex;align-items:center;justify-content:center;
      font-size:1.5rem;font-weight:700;overflow:hidden;flex-shrink:0;
    }
    .co-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .co-name{font-size:0.9rem;font-weight:600;}.co-sub{font-size:0.75rem;color:var(--gm-muted);}
    .waiting-overlay{
      position:absolute;inset:0;
      display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.8rem;
      background:#1c1c1f;z-index:3;
    }
    .waiting-avatar{
      width:72px;height:72px;border-radius:50%;
      background:linear-gradient(135deg,#34a853,#0f9d58);
      display:flex;align-items:center;justify-content:center;
      font-size:1.5rem;font-weight:700;position:relative;overflow:hidden;
    }
    .waiting-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
    .pulse-ring{
      position:absolute;width:72px;height:72px;border-radius:50%;
      border:2px solid rgba(52,168,83,0.5);
      animation:pulse 2s ease-out infinite;pointer-events:none;
    }
    @keyframes pulse{0%{transform:scale(1);opacity:1}100%{transform:scale(1.75);opacity:0}}
    .name-tag{
      position:absolute;bottom:0.6rem;left:0.6rem;
      background:rgba(0,0,0,0.65);backdrop-filter:blur(6px);
      border-radius:6px;padding:0.25rem 0.6rem;font-size:0.72rem;font-weight:500;z-index:4;
    }
    .start-call-btn{
      background:var(--gm-blue);color:#fff;border:none;border-radius:24px;
      padding:0.55rem 1.4rem;font-size:0.85rem;font-weight:600;cursor:pointer;
      font-family:inherit;margin-top:0.3rem;
      box-shadow:0 2px 10px rgba(26,115,232,0.4);
      -webkit-tap-highlight-color:transparent;
    }
    .start-call-btn:disabled{background:var(--gm-surface);cursor:not-allowed;opacity:0.6;}

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
      background:rgba(0,0,0,0.7);
      text-align:center;
      font-size:0.6rem;font-weight:500;padding:0.18rem 0.2rem;
    }
    .seg-loading{
      position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
      font-size:0.65rem;color:var(--gm-muted);text-align:center;z-index:5;pointer-events:none;
    }

    .controls{
      height:var(--ctrl-h);
      min-height:var(--ctrl-h);
      background:var(--gm-bg);
      display:flex;
      align-items:center;
      justify-content:space-around;
      gap:0;
      flex-shrink:0;
      border-top:1px solid rgba(255,255,255,0.08);
      padding:0 0.3rem;
      padding-bottom:env(safe-area-inset-bottom,0px);
      z-index:30;
    }
    .ctrl-sep{width:1px;height:28px;background:rgba(255,255,255,0.1);}
    .cbtn{
      display:flex;flex-direction:column;align-items:center;gap:0.18rem;
      background:none;border:none;cursor:pointer;color:var(--gm-text);
      font-family:inherit;
      padding:0.2rem 0.5rem;
      -webkit-tap-highlight-color:transparent;
      min-width:54px;
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

    .chat-panel{
      position:fixed;
      top:0;left:0;right:0;bottom:0;
      background:var(--gm-surface2);
      display:flex;flex-direction:column;
      z-index:50;
      transform:translateX(100%);
      transition:transform .25s cubic-bezier(.4,0,.2,1);
    }
    .chat-panel.open{transform:translateX(0);}
    .chat-header{
      padding:0.8rem 1rem;
      padding-top:calc(env(safe-area-inset-top,0px) + 0.8rem);
      font-size:0.9rem;font-weight:700;
      border-bottom:1px solid rgba(255,255,255,0.07);
      display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
    }
    .chat-header button{background:none;border:none;color:var(--gm-muted);cursor:pointer;font-size:1.3rem;line-height:1;padding:0.3rem;-webkit-tap-highlight-color:transparent;}
    .chat-messages{
      flex:1;overflow-y:auto;padding:0.8rem;
      display:flex;flex-direction:column;gap:0.6rem;
      -webkit-overflow-scrolling:touch;
    }
    .chat-messages::-webkit-scrollbar{width:3px;}
    .chat-messages::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.15);border-radius:2px;}
    .msg-row{display:flex;flex-direction:column;gap:0.15rem;}
    .msg-row.mine{align-items:flex-end;}.msg-row.theirs{align-items:flex-start;}
    .msg-sender{font-size:0.65rem;color:var(--gm-muted);padding:0 0.4rem;}
    .msg-bubble{max-width:85%;padding:0.5rem 0.8rem;border-radius:18px;font-size:0.84rem;line-height:1.4;word-break:break-word;}
    .msg-row.mine .msg-bubble{background:var(--gm-blue);color:#fff;border-bottom-right-radius:4px;}
    .msg-row.theirs .msg-bubble{background:var(--gm-surface);color:var(--gm-text);border-bottom-left-radius:4px;}
    .msg-time{font-size:0.6rem;color:var(--gm-muted);padding:0 0.4rem;}
    .chat-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:0.5rem;color:var(--gm-muted);}
    .chat-empty svg{width:40px;height:40px;stroke:var(--gm-muted);fill:none;stroke-width:1.5;}
    .chat-empty p{font-size:0.78rem;text-align:center;}
    .chat-input-row{
      padding:0.6rem;
      padding-bottom:calc(env(safe-area-inset-bottom,0px) + 0.6rem);
      border-top:1px solid rgba(255,255,255,0.07);
      display:flex;align-items:flex-end;gap:0.5rem;flex-shrink:0;
    }
    .chat-input{
      flex:1;background:var(--gm-surface);border:none;border-radius:24px;
      padding:0.6rem 1rem;color:var(--gm-text);font-family:inherit;
      font-size:1rem;outline:none;resize:none;max-height:100px;line-height:1.4;
    }
    .chat-input::placeholder{color:var(--gm-muted);}
    .chat-send{
      width:40px;height:40px;border-radius:50%;background:var(--gm-blue);border:none;
      cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;
      -webkit-tap-highlight-color:transparent;
    }
    .chat-send svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}

    /* BG panel  */
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
      pointer-events:none;
      max-width:88vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
      box-shadow:0 2px 8px rgba(0,0,0,0.5);
    }
    #toast.on{opacity:1;}
    @keyframes spin{to{transform:rotate(360deg)}}

    #chat-unread{
      position:absolute;top:-2px;right:-2px;
      background:#ea4335;border-radius:50%;width:15px;height:15px;
      font-size:0.52rem;font-weight:700;
      display:none;align-items:center;justify-content:center;color:#fff;
    }

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
    <span style="font-size:0.68rem;color:var(--gm-muted);"><?= date('g:i A', $appt_ts) ?> · Teleconsult</span>
  </div>
  <div style="display:flex;align-items:center;gap:0.5rem;">
    <div class="conn-dot"><div class="dot" id="conn-dot"></div><span id="conn-lbl">Connecting…</span></div>
    <div class="timer-pill" id="timer">--:--</div>
  </div>
</div>

<div class="video-area">

  <!-- Background noise notice -->
  <div class="noise-notice" id="noise-notice">
    Note: AI transcription and summarization accuracy may be reduced if there is significant background noise during the call.
  </div>

  <div class="remote-tile">
    <video id="remote-video" autoplay playsinline></video>
    <div class="cam-off-overlay" id="remote-cam-off">
      <div class="co-avatar">
        <?php if ($pat_photo): ?><img src="../<?= htmlspecialchars($pat_photo) ?>" alt=""/><?php else: echo $pat_initials; endif; ?>
      </div>
      <div class="co-name"><?= htmlspecialchars($appt['patient_name']) ?></div>
      <div class="co-sub">Camera off</div>
    </div>
    <div class="waiting-overlay" id="waiting-overlay">
      <div style="position:relative;display:flex;align-items:center;justify-content:center;">
        <div class="pulse-ring"></div>
        <div class="waiting-avatar">
          <?php if ($pat_photo): ?><img src="../<?= htmlspecialchars($pat_photo) ?>" alt=""/><?php else: echo $pat_initials; endif; ?>
        </div>
      </div>
      <div style="font-size:1rem;font-weight:600;"><?= htmlspecialchars($appt['patient_name']) ?></div>
      <div style="font-size:0.78rem;color:var(--gm-muted);" id="waiting-sub">
        <?= time() < $appt_ts ? 'Early access — patient may not be here yet.' : 'Waiting for patient to join…' ?>
      </div>
      <button class="start-call-btn" id="start-btn" onclick="manualStart()">Start Call</button>
    </div>
    <div class="name-tag"><?= htmlspecialchars($appt['patient_name']) ?></div>
  </div>

  <!-- Self tile: bigger + draggable -->
  <div class="self-tile" id="self-tile">
    <video id="local-video-raw" autoplay muted playsinline></video>
    <canvas id="local-canvas"></canvas>
    <div class="self-cam-off" id="self-cam-off">
      <div class="self-cam-off-avatar">
        <?php if ($doc_photo): ?><img src="../<?= htmlspecialchars($doc_photo) ?>" alt=""/><?php else: echo $doc_initials; endif; ?>
      </div>
    </div>
    <div class="seg-loading" id="seg-loading">Cam</div>
    <div class="self-name-tag">You (Dr.)</div>
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
  <div class="chat-header">
    <span>In-call Chat</span>
    <button onclick="toggleChat()">&#10005;</button>
  </div>
  <div class="chat-messages" id="chat-messages">
    <div class="chat-empty" id="chat-empty">
      <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
      <p>No messages yet.<br/>Messages only visible during this call.</p>
    </div>
  </div>
  <div class="chat-input-row">
    <textarea class="chat-input" id="chat-input" placeholder="Send a message…" rows="1"
      oninput="autoResize(this)"
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
  <button class="cbtn cbtn-end" onclick="endCall()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24" style="width:21px;height:21px;"><path d="M16 8l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M5 3a2 2 0 00-2 2v1c0 8.284 6.716 15 15 15h1a2 2 0 002-2v-3.28a1 1 0 00-.684-.948l-4.493-1.498a1 1 0 00-1.21.502l-1.13 2.257a11.042 11.042 0 01-5.516-5.517l2.257-1.128a1 1 0 00.502-1.21L9.228 3.683A1 1 0 008.279 3H5z"/></svg></div>
    <span class="cbtn-lbl" style="color:var(--gm-red);">Leave</span>
  </button>
  <div class="ctrl-sep"></div>
  <button class="cbtn" onclick="toggleBg()">
    <div class="cbtn-icon"><svg viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
    <span class="cbtn-lbl">BG</span>
  </button>
  <button class="cbtn" id="btn-chat" onclick="toggleChat()">
    <div class="cbtn-icon" style="position:relative;">
      <svg viewBox="0 0 24 24"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
      <span id="chat-unread"></span>
    </div>
    <span class="cbtn-lbl">Chat</span>
  </button>
</div>

<div id="leaving-overlay" style="display:none;position:fixed;inset:0;background:#202124;z-index:999;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem;">
  <div style="width:56px;height:56px;border:4px solid rgba(255,255,255,0.1);border-top-color:#1a73e8;border-radius:50%;animation:spin 1s linear infinite;"></div>
  <div style="text-align:center;">
    <div style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">AI is summarizing the consultation…</div>
    <div style="font-size:0.8rem;color:#9aa0a6;">Please wait, this may take a minute.<br/>You'll be redirected automatically.</div>
  </div>
</div>

<script>
const ROOM_ID  = <?= json_encode($room_id) ?>;
const ROLE     = 'doctor';
const APPT_TS  = <?= $appt_ts ?>;
const END_TS   = <?= $end_ts ?>;
const APPT_ID  = <?= $appt_id ?>;
const MY_NAME  = <?= json_encode('Dr. ' . $doc['full_name']) ?>;
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
let micOn = true, camOn = true;
let bgMode = 'none', bgImg = null;
let chatOpen = false, unreadCount = 0;
let callStarted = false;
let patientPresent = false;
let callWasConnected = false;
let timerEnded = false;
let wsReconnectDelay = 1500;
let isDestroyed = false;

const canvas = document.getElementById('local-canvas');
const ctx    = canvas.getContext('2d');
canvas.width = 640; canvas.height = 480;

// ── Draggable self-tile ───────────────────────────────────────────────────
(function(){
  const tile = document.getElementById('self-tile');
  let dragging = false, startX, startY, origLeft, origBottom;

  function getPos() {
    const rect = tile.getBoundingClientRect();
    const parent = tile.parentElement.getBoundingClientRect();
    return {
      left: rect.left - parent.left,
      top:  rect.top  - parent.top,
    };
  }

  function startDrag(cx, cy) {
    dragging = true;
    const pos = getPos();
    tile.style.right  = 'auto';
    tile.style.bottom = 'auto';
    tile.style.left   = pos.left + 'px';
    tile.style.top    = pos.top  + 'px';
    startX = cx; startY = cy;
    origLeft = pos.left; origLeft; // stored via closure below
  }

  tile.addEventListener('mousedown', e => {
    startDrag(e.clientX, e.clientY);
    e.preventDefault();
  });
  tile.addEventListener('touchstart', e => {
    startDrag(e.touches[0].clientX, e.touches[0].clientY);
  }, { passive: true });

  document.addEventListener('mousemove', e => {
    if (!dragging) return;
    const dx = e.clientX - startX, dy = e.clientY - startY;
    const pos = getPos();
    const parent = tile.parentElement.getBoundingClientRect();
    const newLeft = Math.max(0, Math.min(parent.width - tile.offsetWidth,  parseFloat(tile.style.left) + dx));
    const newTop  = Math.max(0, Math.min(parent.height - tile.offsetHeight, parseFloat(tile.style.top)  + dy));
    tile.style.left = newLeft + 'px';
    tile.style.top  = newTop  + 'px';
    startX = e.clientX; startY = e.clientY;
  });
  document.addEventListener('touchmove', e => {
    if (!dragging) return;
    const dx = e.touches[0].clientX - startX, dy = e.touches[0].clientY - startY;
    const parent = tile.parentElement.getBoundingClientRect();
    const newLeft = Math.max(0, Math.min(parent.width - tile.offsetWidth,  parseFloat(tile.style.left) + dx));
    const newTop  = Math.max(0, Math.min(parent.height - tile.offsetHeight, parseFloat(tile.style.top)  + dy));
    tile.style.left = newLeft + 'px';
    tile.style.top  = newTop  + 'px';
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
  try {
    rawStream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: true });
    document.getElementById('local-video-raw').srcObject = rawStream;
  } catch(e) {
    const overlay = document.getElementById('waiting-overlay');
    overlay.style.display = 'flex';
    overlay.innerHTML = `
      <div style="text-align:center;padding:1rem;">
        <div style="font-size:2rem;margin-bottom:0.8rem;">&#128683;</div>
        <div style="font-size:1rem;font-weight:700;margin-bottom:0.5rem;">Camera & Mic Access Denied</div>
        <div style="font-size:0.8rem;color:#9aa0a6;margin-bottom:1.2rem;line-height:1.6;">
          Please allow camera and microphone access in your browser settings, then reload.
        </div>
        <button class="start-call-btn" onclick="location.reload()">Reload & Try Again</button>
      </div>`;
    toast('Camera/mic access denied — check browser permissions');
    connectWS(); startTimer(); updateChatAvailability(); return;
  }
  initSegmentation();
  connectWS(); startTimer(); updateChatAvailability();
}

function initSegmentation() {
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
  selfieSegmentation.onResults(onSegResults);
  const vid = document.getElementById('local-video-raw');
  selfieSegmentation.initialize().then(() => {
    document.getElementById('seg-loading').style.display = 'none';
    processedStream = canvas.captureStream(30);
    rawStream.getAudioTracks().forEach(t => processedStream.addTrack(t));
    segInterval = setInterval(async () => {
      if (vid.readyState >= 2) await selfieSegmentation.send({ image: vid });
    }, 33);
  }).catch(() => { document.getElementById('seg-loading').style.display = 'none'; });
}

function onSegResults(results) {
  ctx.save(); ctx.clearRect(0, 0, 640, 480);
  ctx.drawImage(results.segmentationMask, 0, 0, 640, 480);
  ctx.globalCompositeOperation = 'source-in';
  ctx.drawImage(results.image, 0, 0, 640, 480);
  ctx.globalCompositeOperation = 'destination-over';
  if (bgMode === 'none')            { ctx.drawImage(results.image, 0, 0, 640, 480); }
  else if (bgMode === 'blur')       { ctx.filter = 'blur(18px)'; ctx.drawImage(results.image, -30, -30, 700, 540); ctx.filter = 'none'; }
  else if (bgImg && bgImg.complete) { ctx.drawImage(bgImg, 0, 0, 640, 480); }
  ctx.restore();
}

function connectWS() {
  if (isDestroyed) return;
  ws = new WebSocket(WS_URL);
  ws.onopen = () => {
    wsReconnectDelay = 1500;
    setConn(false, patientPresent ? 'Reconnected…' : 'Waiting for patient…');
    if (patientPresent) { callStarted = false; setTimeout(() => scheduleStartCall(), 1000); }
  };
  ws.onmessage = async ({ data }) => {
    let m; try { m = JSON.parse(data); } catch(e) { return; }
    if (m.type === 'peer_joined') {
      patientPresent = true; callStarted = false;
      setConn(false, 'Patient joined!');
      document.getElementById('waiting-overlay').style.display = 'flex';
      const sub = document.getElementById('waiting-sub');
      if (Date.now() / 1000 < APPT_TS) {
        sub.textContent = 'Patient is here. Call starts at ' + new Date(APPT_TS * 1000).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
        toast('Patient is here early — waiting for scheduled time…');
      } else {
        sub.textContent = 'Patient is ready!'; toast('Patient joined — starting call…'); resetStartBtn();
      }
      if (Date.now() / 1000 >= APPT_TS) { scheduleStartCall(); }
    }
    else if (m.type === 'answer') {
      if (pc && pc.signalingState === 'have-local-offer') {
        try {
          await pc.setRemoteDescription(m.sdp);
          document.getElementById('waiting-overlay').style.display = 'none';
          setConn(true, 'Connected'); toast('Call connected!');
        } catch(e) { toast('Answer error — retrying…'); callStarted = false; setTimeout(scheduleStartCall, 2000); }
      }
    }
    else if (m.type === 'ice') {
      if (pc && m.candidate && pc.remoteDescription) { try { await pc.addIceCandidate(m.candidate); } catch(e) {} }
    }
    else if (m.type === 'peer_left') {
      patientPresent = false;
      document.getElementById('remote-video').srcObject = null;
      document.getElementById('waiting-overlay').style.display = 'flex';
      document.getElementById('waiting-sub').textContent = 'Patient disconnected.';
      setConn(false, 'Patient left');
      if (pc) { pc.close(); pc = null; }
      callStarted = false; resetStartBtn(); toast('Patient left the call');
    }
    else if (m.type === 'chat') {
      if (!isChatWindowOpen()) return;
      addChatMsg(m.text, m.name || m.from || 'Patient', false);
    }
    else if (m.type === 'cam_toggle') {
      const co = document.getElementById('remote-cam-off');
      m.cam_on ? co.classList.remove('show') : co.classList.add('show');
    }
  };
  ws.onclose = () => {
    if (isDestroyed) return;
    setConn(false, 'Reconnecting…');
    wsReconnectDelay = Math.min(wsReconnectDelay * 1.5, 10000);
    setTimeout(connectWS, wsReconnectDelay);
  };
  ws.onerror = () => { try { ws.close(); } catch(e) {} };
}

function scheduleStartCall() {
  if (callStarted || isDestroyed) return;
  if (processedStream || rawStream) { startCall(); }
  else { setTimeout(scheduleStartCall, 300); }
}
function manualStart() {
  if (!patientPresent) { toast('Waiting for patient to join first…'); return; }
  callStarted = false; scheduleStartCall();
}

async function startCall() {
  if (callStarted || isDestroyed) return;
  if (Date.now() / 1000 < APPT_TS) {
    toast('Waiting for scheduled call time…');
    const waitForTime = () => {
      if (isDestroyed) return;
      if (Date.now() / 1000 >= APPT_TS) { startCall(); }
      else { setTimeout(waitForTime, 5000); }
    };
    setTimeout(waitForTime, 5000); return;
  }
  if (!ws || ws.readyState !== WebSocket.OPEN) {
    toast('Waiting for connection…');
    setTimeout(() => { if (!callStarted) scheduleStartCall(); }, 2000); return;
  }
  callStarted = true;
  const sb = document.getElementById('start-btn');
  if (sb) { sb.disabled = true; sb.textContent = 'Connecting…'; }
  if (pc) { try { pc.close(); } catch(e) {} pc = null; }
  pc = new RTCPeerConnection(ICE);
  pc._remoteStream = new MediaStream();
  const stream = processedStream || rawStream;
  if (!stream || stream.getTracks().length === 0) {
    toast('No media stream available'); callStarted = false; resetStartBtn(); return;
  }
  stream.getTracks().forEach(t => pc.addTrack(t, stream));
  pc.ontrack = e => {
    if (e.streams && e.streams[0] && e.streams[0].getTracks().length > 0) { pc._remoteStream = e.streams[0]; }
    else { pc._remoteStream.addTrack(e.track); }
    const rv = document.getElementById('remote-video');
    rv.srcObject = pc._remoteStream;
    rv.muted = true;
    rv.play().then(() => { rv.muted = false; }).catch(() => { rv.muted = false; });
    document.getElementById('waiting-overlay').style.display = 'none';
    callWasConnected = true; setConn(true, 'Connected'); toast('Call connected!');
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
    if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
      toast('Connection lost — retrying…'); callStarted = false;
      setTimeout(() => { if (!isDestroyed && patientPresent) scheduleStartCall(); }, 2000);
    }
  };
  try {
    let offer = await pc.createOffer();
    offer = new RTCSessionDescription({
      type: offer.type,
      sdp: offer.sdp.replace(/m=video (\d+) UDP\/TLS\/RTP\/SAVPF ([\d ]+)/g, (match, port, payloads) => {
        const h264 = ['126','97','120','123'];
        const arr = payloads.split(' ');
        const preferred = [...h264.filter(p => arr.includes(p)), ...arr.filter(p => !h264.includes(p))];
        return `m=video ${port} UDP/TLS/RTP/SAVPF ${preferred.join(' ')}`;
      })
    });
    await pc.setLocalDescription(offer);
    if (!ws || ws.readyState !== WebSocket.OPEN) throw new Error('WS closed');
    ws.send(JSON.stringify({ type: 'offer', sdp: offer }));
    toast('Calling patient…');
  } catch(e) {
    callStarted = false;
    if (pc) { try { pc.close(); } catch(_) {} pc = null; }
    resetStartBtn(); toast('Connection failed — will retry when ready…');
    setTimeout(() => { if (!isDestroyed && patientPresent && !callStarted) scheduleStartCall(); }, 3000);
  }
}

function resetStartBtn() {
  const sb = document.getElementById('start-btn');
  if (sb) { sb.disabled = false; sb.textContent = 'Retry Call'; }
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
          const response = await fetch(`../check_summary.php?appt_id=${APPT_ID}`);
          const data = await response.json();
          if (data.done) { window.location.href = 'appointments.php'; }
          else if (Date.now() - startTime > maxWait) { window.location.href = 'appointments.php'; }
          else { setTimeout(checkSummary, 2000); }
        } catch(e) { setTimeout(checkSummary, 5000); }
      };
      await fetch('../process_consultation_v2.php', { method: 'POST', body: fd });
      setTimeout(checkSummary, 2000);
    } catch(e) { setTimeout(() => { window.location.href = 'appointments.php'; }, 5000); }
  } else {
    window.location.href = 'appointments.php';
  }
}

function autoCompleteAppt() {
  if (Date.now() / 1000 < APPT_TS) return;
  fetch('../auto_complete_appt.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `appt_id=${APPT_ID}&role=doctor` }).catch(() => {});
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
      autoCompleteAppt();
      if (callWasConnected) { toast('Consultation ended — leaving in 5 seconds…'); setTimeout(() => endCall(true), 5000); }
      else { toast('Session time expired'); setTimeout(() => endCall(true), 30000); }
      return;
    }
    const m = Math.floor(left / 60), s = left % 60;
    el.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    el.classList.toggle('urgent', left < 300);
  }, 1000);
}

function toggleMic() {
  micOn = !micOn;
  rawStream?.getAudioTracks().forEach(t => t.enabled = micOn);
  document.getElementById('btn-mic').classList.toggle('off', !micOn);
  document.getElementById('lbl-mic').textContent = micOn ? 'Mute' : 'Unmute';
  toast(micOn ? 'Mic on' : 'Muted');
}
function toggleCam() {
  camOn = !camOn;
  rawStream?.getVideoTracks().forEach(t => t.enabled = camOn);
  document.getElementById('btn-cam').classList.toggle('off', !camOn);
  document.getElementById('lbl-cam').textContent = camOn ? 'Camera' : 'Cam Off';
  if (isSafariOrIOS()) {
    document.getElementById('local-video-raw').style.opacity = camOn ? '1' : '0';
  } else {
    document.getElementById('local-canvas').style.display = camOn ? 'block' : 'none';
  }
  document.getElementById('self-cam-off').classList.toggle('show', !camOn);
  if (ws && ws.readyState === WebSocket.OPEN) { ws.send(JSON.stringify({ type: 'cam_toggle', cam_on: camOn })); }
  toast(camOn ? 'Camera on' : 'Camera off');
}
function toggleBg() { document.getElementById('bgpanel').classList.toggle('open'); }
function setBg(mode, el) {
  bgMode = mode; bgImg = null;
  document.querySelectorAll('.bgo').forEach(e => e.classList.remove('on'));
  el.classList.add('on');
  document.getElementById('bgpanel').classList.remove('open');
  if (mode !== 'none' && mode !== 'blur') { bgImg = new Image(); bgImg.crossOrigin = 'anonymous'; bgImg.src = mode; }
  toast(mode === 'none' ? 'Background removed' : 'Background blurred');
}

function isChatWindowOpen() {
  const nowSec = Math.floor(Date.now() / 1000);
  return nowSec >= APPT_TS && nowSec <= END_TS;
}
function updateChatAvailability() {
  const btn = document.getElementById('btn-chat');
  const panel = document.getElementById('chat-panel');
  const input = document.getElementById('chat-input');
  const canChat = isChatWindowOpen();
  if (btn) { btn.disabled = !canChat; btn.style.opacity = canChat ? '1' : '0.55'; btn.style.pointerEvents = canChat ? 'auto' : 'none'; }
  if (input) { input.disabled = !canChat; input.placeholder = canChat ? 'Send a message…' : 'Chat opens during consultation'; }
  if (!canChat && panel) { chatOpen = false; panel.classList.remove('open'); unreadCount = 0; updateUnread(); }
}
function toggleChat() {
  if (!isChatWindowOpen()) { toast('Chat will open during the scheduled consultation hour.'); return; }
  chatOpen = !chatOpen;
  document.getElementById('chat-panel').classList.toggle('open', chatOpen);
  if (chatOpen) {
    unreadCount = 0; updateUnread();
    setTimeout(() => document.getElementById('chat-input').focus(), 300);
    scrollChatBottom();
  }
}
function sendChat() {
  if (!isChatWindowOpen()) { toast('Chat is disabled outside the consultation hour.'); return; }
  const inp = document.getElementById('chat-input');
  const msg = inp.value.trim();
  if (!msg || !ws || ws.readyState !== WebSocket.OPEN) return;
  ws.send(JSON.stringify({ type: 'chat', text: msg, name: MY_NAME }));
  addChatMsg(msg, MY_NAME, true);
  inp.value = ''; inp.style.height = 'auto';
}
function addChatMsg(text, sender, isMine) {
  const empty = document.getElementById('chat-empty');
  if (empty) empty.remove();
  const now = new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
  chatMessages.push(`[${now}] ${isMine ? MY_NAME : sender}: ${text}`);
  const row = document.createElement('div');
  row.className = 'msg-row ' + (isMine ? 'mine' : 'theirs');
  row.innerHTML = `<div class="msg-sender">${isMine ? 'You' : escHtml(sender)}</div>
                   <div class="msg-bubble">${escHtml(text)}</div>
                   <div class="msg-time">${now}</div>`;
  document.getElementById('chat-messages').appendChild(row);
  scrollChatBottom();
  if (!isMine && !chatOpen) {
    unreadCount++; updateUnread();
    toast(`${sender}: ${text.length > 40 ? text.slice(0,40) + '…' : text}`);
  }
}
function scrollChatBottom() { const el = document.getElementById('chat-messages'); el.scrollTop = el.scrollHeight; }
function updateUnread() {
  const b = document.getElementById('chat-unread');
  if (unreadCount > 0) { b.textContent = unreadCount > 9 ? '9+' : unreadCount; b.style.display = 'flex'; }
  else { b.style.display = 'none'; }
}
function autoResize(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 100) + 'px'; }
function setConn(live, label) {
  document.getElementById('conn-dot').className = 'dot' + (live ? ' live' : '');
  document.getElementById('conn-lbl').textContent = label;
}
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
let tT;
function toast(msg) {
  const e = document.getElementById('toast'); e.textContent = msg; e.classList.add('on');
  clearTimeout(tT); tT = setTimeout(() => e.classList.remove('on'), 2800);
}

document.addEventListener('click', e => {
  const p = document.getElementById('bgpanel');
  if (!p.contains(e.target) && !e.target.closest('[onclick="toggleBg()"]')) p.classList.remove('open');
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