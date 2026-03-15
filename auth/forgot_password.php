<?php
require_once '../database/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — TELE-CARE</title>
  <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:linear-gradient(160deg,var(--green) 0%,#1a3330 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
    body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(63,130,227,0.06) 1px,transparent 1px),linear-gradient(90deg,rgba(63,130,227,0.06) 1px,transparent 1px);background-size:44px 44px;animation:gridMove 20s linear infinite;pointer-events:none;}
    @keyframes gridMove{from{transform:translateY(0)}to{transform:translateY(44px)}}
    .card{background:#fff;border-radius:24px;padding:2.5rem 2rem;width:100%;max-width:420px;position:relative;z-index:1;box-shadow:0 30px 80px rgba(0,0,0,0.25);animation:fadeUp 0.5s ease;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .logo{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:var(--green);text-decoration:none;display:block;margin-bottom:2rem;}
    .logo span{color:var(--red)}
    .field-label{display:block;font-size:0.78rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:#5a7a77;margin-bottom:0.45rem;}
    .field-input{width:100%;padding:0.8rem 1rem;border:1.5px solid rgba(36,68,65,0.15);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.95rem;background:var(--white);color:var(--green);outline:none;transition:border-color 0.25s,box-shadow 0.25s;}
    .field-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(63,130,227,0.12);}
    .btn{width:100%;padding:0.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:600;font-size:0.95rem;border:none;cursor:pointer;transition:all 0.3s;box-shadow:0 6px 20px rgba(195,54,67,0.3);margin-top:1.2rem;font-family:'DM Sans',sans-serif;display:flex;align-items:center;justify-content:center;gap:0.5rem;}
    .btn:hover{background:#a82d38;transform:translateY(-2px);}
    .btn:disabled{background:#c88;cursor:not-allowed;transform:none;}
    .spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;}
    @keyframes spin{to{transform:rotate(360deg)}}
    .alert-success{background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);color:#15803d;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;margin-top:1rem;}
    .alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.25);color:var(--red);border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;margin-top:1rem;}
    .alert-warn{background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);color:#b45309;border-radius:12px;padding:0.85rem 1rem;font-size:0.88rem;margin-top:1rem;}
  </style>
</head>
<body>
<div class="card">
  <a href="login.php" class="logo">TELE<span>-</span>CARE</a>

  <div style="margin-bottom:1.8rem;">
    <div style="width:52px;height:52px;border-radius:14px;background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.15);display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:1rem;">🔐</div>
    <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--green);margin-bottom:0.3rem;">Forgot Password?</h2>
    <p style="color:#6b8a87;font-size:0.88rem;line-height:1.6;">No worries. Enter your registered email and we'll send you a reset link.</p>
  </div>

  <div style="margin-bottom:1rem;">
    <label class="field-label">Email Address</label>
    <input type="email" id="emailInput" class="field-input" placeholder="you@email.com"/>
  </div>

  <button class="btn" id="sendBtn" onclick="sendReset()">
    Send Reset Link
  </button>

  <div id="statusMsg"></div>

  <p style="text-align:center;margin-top:1.5rem;font-size:0.85rem;color:#9ab0ae;">
    <a href="login.php" style="color:var(--green);font-weight:600;text-decoration:none;">← Back to Login</a>
  </p>
</div>

<script>
  // ── EMAILJS KEYS ──
  const EMAILJS_PUBLIC_KEY    = 'WCw8iwd51l4iljHV-';
  const EMAILJS_SERVICE_ID    = 'service_bknr7e6';
  const EMAILJS_RESET_TEMPLATE = 'template_eh6dcuy';

  emailjs.init(EMAILJS_PUBLIC_KEY);

  let isSending = false; // guard against double clicks

  function sendReset() {
    if (isSending) return; // block spam

    const email = document.getElementById('emailInput').value.trim();
    const btn   = document.getElementById('sendBtn');
    const msg   = document.getElementById('statusMsg');

    if (!email) {
      msg.innerHTML = '<div class="alert-error">⚠️ Please enter your email address.</div>';
      return;
    }

    // Basic email format check
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      msg.innerHTML = '<div class="alert-error">⚠️ Please enter a valid email address.</div>';
      return;
    }

    // Lock button immediately
    isSending = true;
    btn.disabled  = true;
    btn.innerHTML = '<div class="spinner"></div> Sending...';
    msg.innerHTML = '';

    fetch('send_reset.php', {
      method:  'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body:    'email=' + encodeURIComponent(email)
    })
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        const isCooldown   = data.message && data.message.includes('wait');
        const isNotFound   = data.message && data.message.includes('not found');
        let   alertClass   = isCooldown ? 'alert-warn' : 'alert-error';
        let   icon         = isCooldown ? '⏳' : '✗';
        msg.innerHTML = `<div class="${alertClass}">${icon} ${data.message}</div>`;
        // Re-enable after short delay so they can fix their input
        setTimeout(() => { btn.disabled = false; btn.innerHTML = 'Send Reset Link'; isSending = false; }, 2000);
        return Promise.reject('handled');
      }

      // Send email via EmailJS
      if (data.name && data.link) {
        return emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_RESET_TEMPLATE, {
          to_email:     data.email,
          patient_name: data.name,
          reset_link:   data.link,
        });
      }
    })
    .then(() => {
      msg.innerHTML = '<div class="alert-success">✓ Reset link sent! Check your inbox — it expires in 1 hour.</div>';
      // Start 3-minute frontend cooldown
      startCooldown(btn);
    })
    .catch(err => {
      if (err === 'handled') return;
      msg.innerHTML = '<div class="alert-error">✗ ' + (err.message || 'Something went wrong. Try again.') + '</div>';
      btn.disabled  = false;
      btn.innerHTML = 'Send Reset Link';
      isSending     = false;
    });
  }

  function startCooldown(btn) {
    let secs = 180; // 3 minutes
    btn.disabled = true;
    const t = setInterval(() => {
      secs--;
      const m = Math.floor(secs / 60);
      const s = secs % 60;
      btn.innerHTML = `Resend in ${m}:${String(s).padStart(2,'0')}`;
      if (secs <= 0) {
        clearInterval(t);
        btn.disabled  = false;
        btn.innerHTML = 'Send Reset Link';
        isSending     = false;
      }
    }, 1000);
  }

  // Allow Enter key
  document.getElementById('emailInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') sendReset();
  });
</script>
</body>
</html>