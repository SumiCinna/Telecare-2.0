<?php
$pageTitle = "TELE-CARE | Your Health, Connected";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $pageTitle ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --red: #C33643;
      --green: #244441;
      --blue: #3F82E3;
      --bg: #FFFFFF;
      --white: #FFFFFF;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background-color: var(--bg);
      color: var(--green);
      overflow-x: hidden;
    }

    h1, h2 { font-family: 'Playfair Display', serif; }
    h3 { font-family: 'DM Sans', sans-serif; }

    /* ── NAVBAR ── */
    nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      display: flex; align-items: center; justify-content: space-between;
      padding: 1.2rem 5%;
      background: rgba(255,255,255,0.97);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(36,68,65,0.1);
      animation: slideDown 0.7s ease forwards;
      box-shadow: 0 2px 20px rgba(36,68,65,0.07);
    }
    @keyframes slideDown {
      from { transform: translateY(-80px); opacity: 0; }
      to   { transform: translateY(0);     opacity: 1; }
    }

    .logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.7rem; font-weight: 900;
      color: var(--green); letter-spacing: 0.04em;
    }
    .logo span { color: var(--red); }

    .nav-links { display: flex; gap: 2.5rem; list-style: none; align-items: center; }
    .nav-links a {
      color: rgba(36,68,65,0.75); font-size: 0.9rem; font-weight: 500;
      text-decoration: none; transition: color 0.25s;
      position: relative;
    }
    .nav-links a::after {
      content: ''; position: absolute; bottom: -3px; left: 0;
      width: 0; height: 2px; background: var(--red);
      transition: width 0.3s ease;
    }
    .nav-links a:hover { color: var(--green); }
    .nav-links a:hover::after { width: 100%; }

    .nav-login {
      color: var(--green) !important; font-size: 0.88rem !important;
      font-weight: 600 !important; padding: 0.55rem 1.3rem;
      border: 2px solid var(--green) !important; border-radius: 50px;
      background: transparent;
      transition: all 0.25s !important;
    }
    .nav-login:hover { background: var(--green); color: #fff !important; }
    .nav-login::after { display: none !important; }
    .nav-cta {
      background: var(--red); color: #fff !important; opacity: 1 !important;
      padding: 0.55rem 1.5rem; border-radius: 50px;
      font-weight: 600 !important; font-size: 0.88rem !important;
      transition: background 0.25s, transform 0.2s !important;
    }
    .nav-cta:hover { background: #a82d38 !important; transform: translateY(-1px); }
    .nav-cta::after { display: none !important; }

    /* ── HERO ── */
    .hero {
      min-height: 100vh;
      display: flex; align-items: center;
      background: #FFFFFF;
      padding: 120px 5% 80px;
      position: relative; overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(36,68,65,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(36,68,65,0.04) 1px, transparent 1px);
      background-size: 60px 60px;
      animation: gridMove 20s linear infinite;
    }
    @keyframes gridMove {
      from { transform: translateY(0); }
      to   { transform: translateY(60px); }
    }

    .orb {
      position: absolute; border-radius: 50%;
      filter: blur(80px); pointer-events: none;
    }
    .orb-1 {
      width: 500px; height: 500px;
      background: radial-gradient(circle, rgba(195,54,67,0.07) 0%, transparent 70%);
      top: -100px; right: -100px;
      animation: pulse 6s ease-in-out infinite;
    }
    .orb-2 {
      width: 350px; height: 350px;
      background: radial-gradient(circle, rgba(36,68,65,0.06) 0%, transparent 70%);
      bottom: 50px; left: 30%;
      animation: pulse 8s ease-in-out infinite reverse;
    }
    @keyframes pulse {
      0%, 100% { transform: scale(1);   opacity: 0.8; }
      50%       { transform: scale(1.2); opacity: 1; }
    }

    .hero-content { position: relative; z-index: 2; max-width: 680px; }

    .hero-badge {
      display: inline-flex; align-items: center; gap: 0.5rem;
      background: rgba(36,68,65,0.07); border: 1px solid rgba(36,68,65,0.15);
      color: var(--green); font-size: 0.8rem; font-weight: 600; letter-spacing: 0.1em;
      padding: 0.4rem 1rem; border-radius: 50px; margin-bottom: 2rem;
      animation: fadeUp 0.8s 0.2s both;
    }
    .hero-badge .dot {
      width: 7px; height: 7px; border-radius: 50%;
      background: var(--green); animation: blink 1.5s infinite;
    }
    @keyframes blink {
      0%, 100% { opacity: 1; } 50% { opacity: 0.2; }
    }

    .hero h1 {
      font-size: clamp(3rem, 6vw, 5rem);
      font-weight: 900; line-height: 1.08;
      color: var(--green); margin-bottom: 1.5rem;
      animation: fadeUp 0.8s 0.35s both;
    }
    .hero h1 em { color: var(--red); font-style: normal; }

    .hero p {
      font-size: 1.1rem; color: rgba(36,68,65,0.65);
      line-height: 1.8; margin-bottom: 2.5rem;
      animation: fadeUp 0.8s 0.5s both;
    }

    .hero-btns {
      display: flex; gap: 1rem; flex-wrap: wrap;
      animation: fadeUp 0.8s 0.65s both;
    }

    .btn-primary {
      background: var(--red); color: #fff;
      padding: 0.85rem 2.2rem; border-radius: 50px;
      font-weight: 600; font-size: 0.95rem;
      text-decoration: none; border: none; cursor: pointer;
      transition: all 0.3s; display: inline-flex; align-items: center; gap: 0.5rem;
      box-shadow: 0 8px 30px rgba(195,54,67,0.25);
    }
    .btn-primary:hover {
      background: #a82d38; transform: translateY(-3px);
      box-shadow: 0 14px 40px rgba(195,54,67,0.35);
    }

    .btn-secondary {
      background: transparent; color: var(--green);
      padding: 0.85rem 2.2rem; border-radius: 50px;
      font-weight: 500; font-size: 0.95rem;
      text-decoration: none; border: 1px solid rgba(36,68,65,0.25);
      cursor: pointer; transition: all 0.3s;
      display: inline-flex; align-items: center; gap: 0.5rem;
    }
    .btn-secondary:hover {
      border-color: var(--green); background: rgba(36,68,65,0.05);
      transform: translateY(-3px);
    }

    /* ── SECTION COMMON ── */
    section { padding: 100px 5%; }

    .section-tag {
      display: inline-block; font-size: 0.75rem; font-weight: 700;
      letter-spacing: 0.15em; text-transform: uppercase;
      color: var(--red); margin-bottom: 1rem;
    }

    .section-title {
      font-size: clamp(2rem, 4vw, 2.8rem);
      font-weight: 800; color: var(--green); line-height: 1.15;
      margin-bottom: 1rem;
    }

    .section-sub {
      color: #5a7a77; font-size: 1.05rem; line-height: 1.75;
      max-width: 540px;
    }

    /* ── FEATURES ── */
    .features { background: #f8f8f8; }

    .features-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 2rem; margin-top: 4rem;
    }

    .feature-card {
      background: #FFFFFF; border-radius: 24px; padding: 2.2rem;
      border: 1px solid rgba(36,68,65,0.08);
      transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
      position: relative; overflow: hidden;
    }
    .feature-card::before {
      content: ''; position: absolute;
      top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--red), var(--green));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.4s ease;
    }
    .feature-card:hover { transform: translateY(-8px); box-shadow: 0 24px 60px rgba(36,68,65,0.10); }
    .feature-card:hover::before { transform: scaleX(1); }

    .feature-icon {
      width: 56px; height: 56px; border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; margin-bottom: 1.5rem;
    }
    .icon-red   { background: rgba(195,54,67,0.08);   color: var(--red);  }
    .icon-blue  { background: rgba(63,130,227,0.08);  color: var(--blue); }
    .icon-green { background: rgba(36,68,65,0.08);    color: var(--green);}

    .feature-card h3 { font-size: 1.15rem; font-weight: 700; margin-bottom: 0.7rem; color: var(--green); }
    .feature-card p  { font-size: 0.9rem; line-height: 1.7; color: #6b8a87; }

    /* ── HOW IT WORKS ── */
    .how { background: #FFFFFF; }

    .steps {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 0; margin-top: 4rem; position: relative;
      max-width: 900px; margin-left: auto; margin-right: auto;
    }
    .steps::before {
      content: '';
      position: absolute; top: 36px; left: 10%; right: 10%; height: 2px;
      background: linear-gradient(90deg, var(--red), var(--green));
      opacity: 0.2;
    }

    .step {
      text-align: center; padding: 0 1.5rem;
      animation: fadeUp 0.7s both;
    }
    .step:nth-child(1) { animation-delay: 0.1s; }
    .step:nth-child(2) { animation-delay: 0.25s; }
    .step:nth-child(3) { animation-delay: 0.4s; }
    .step:nth-child(4) { animation-delay: 0.55s; }

    .step-num {
      width: 72px; height: 72px; border-radius: 50%;
      background: #FFFFFF; border: 3px solid var(--green);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem; font-weight: 900; color: var(--green);
      margin: 0 auto 1.5rem; position: relative; z-index: 1;
      transition: all 0.35s;
    }
    .step:hover .step-num {
      background: var(--green); color: #fff;
      transform: scale(1.1); box-shadow: 0 10px 30px rgba(36,68,65,0.2);
    }

    .step h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--green); }
    .step p  { font-size: 0.88rem; color: #6b8a87; line-height: 1.65; }

    /* ── SERVICES ── */
    .services { background: #FFFFFF; border-top: 1px solid rgba(36,68,65,0.08); }
    .services .section-title { color: var(--green); }
    .services .section-tag    { color: var(--red); }
    .services .section-sub    { color: #5a7a77; }

    .services-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1.5rem; margin-top: 4rem;
    }

    .service-card {
      background: #f8f8f8;
      border: 1px solid rgba(36,68,65,0.08);
      border-radius: 20px; padding: 2rem;
      transition: all 0.35s; cursor: pointer;
      position: relative; overflow: hidden;
    }
    .service-card::before {
      content: ''; position: absolute;
      bottom: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--red), var(--green));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.4s ease;
    }
    .service-card:hover {
      background: #fff; transform: translateY(-6px);
      border-color: rgba(36,68,65,0.15);
      box-shadow: 0 20px 50px rgba(36,68,65,0.10);
    }
    .service-card:hover::before { transform: scaleX(1); }
    .service-emoji { font-size: 2.4rem; margin-bottom: 1rem; }
    .service-card h3 { font-size: 1.05rem; font-weight: 700; color: var(--green); margin-bottom: 0.5rem; }
    .service-card p  { font-size: 0.85rem; color: #6b8a87; line-height: 1.65; }

    /* ── CTA BANNER ── */
    .cta-banner {
      background: linear-gradient(135deg, var(--red) 0%, #8a1f2a 100%);
      padding: 90px 5%; text-align: center; position: relative; overflow: hidden;
    }
    .cta-banner::before {
      content: '';
      position: absolute; inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .cta-banner h2 {
      font-size: clamp(2rem, 4vw, 3rem); color: #fff;
      margin-bottom: 1rem; position: relative;
    }
    .cta-banner p { color: rgba(255,255,255,0.75); margin-bottom: 2.5rem; font-size: 1.1rem; position: relative; }
    .cta-banner .btn-white {
      background: #fff; color: var(--red);
      padding: 1rem 2.8rem; border-radius: 50px;
      font-weight: 700; font-size: 1rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
      transition: all 0.3s; position: relative;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .cta-banner .btn-white:hover {
      transform: translateY(-4px); box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }

    /* ── FOOTER ── */
    footer {
      background: #FFFFFF;
      border-top: 1px solid rgba(36,68,65,0.1);
      color: rgba(36,68,65,0.5);
      padding: 3rem 5%; text-align: center; font-size: 0.85rem;
    }
    footer .footer-logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.5rem; font-weight: 900; color: var(--green);
      margin-bottom: 0.5rem;
    }
    footer .footer-logo span { color: var(--red); }

    /* ── ANIMATIONS ── */
    @keyframes fadeUp {
      from { transform: translateY(40px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }

    .reveal {
      opacity: 0; transform: translateY(40px);
      transition: opacity 0.7s ease, transform 0.7s ease;
    }
    .reveal.visible { opacity: 1; transform: translateY(0); }

    /* ── MOBILE ── */
    @media (max-width: 900px) {
      .hero-stats { display: none; }
      .steps::before { display: none; }
    }
    @media (max-width: 640px) {
      nav { padding: 1rem 4%; }
      .nav-links { display: none; }
    }

    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #fff; }
    ::-webkit-scrollbar-thumb { background: var(--green); border-radius: 3px; }
  </style>
</head>
<body>

<!-- ══ NAVBAR ══ -->
<nav>
  <div class="logo">TELE<span>-</span>CARE</div>
  <ul class="nav-links">
    <li><a href="#features">Features</a></li>
    <li><a href="#how">How It Works</a></li>
    <li><a href="#services">Services</a></li>
    <li style="position:relative;display:flex;align-items:center;">
      <div style="display:flex;gap:0.5rem;align-items:center;">
        <a href="auth/login.php" class="nav-login" style="margin:0;">Patient Log In</a>
        <div style="width:1px;height:20px;background:rgba(36,68,65,0.2);"></div>
        <a href="doctor/login.php" class="nav-login" style="margin:0;">Doctor Log In</a>
      </div>
    </li>
    <li><a href="auth/register.php" class="nav-cta">Register</a></li>
  </ul>
</nav>

<!-- ══ HERO ══ -->
<section class="hero">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>

  <div class="hero-content">
    <div class="hero-badge">
      <span class="dot"></span>
      Accepting Patients Online
    </div>
    <h1>Your Health,<br><em>Always Connected</em></h1>
    <p>
      Book appointments, consult with doctors, and keep track of your health records — 
      all without leaving your home.
    </p>
    <div class="hero-btns">
      <a href="auth/register.php" class="btn-primary">
        Book a Consultation
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
        </svg>
      </a>
      <a href="#how" class="btn-secondary">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z" fill="currentColor" stroke="none"/>
        </svg>
        See How It Works
      </a>
    </div>
    <p style="margin-top:1.2rem;font-size:0.88rem;color:rgba(36,68,65,0.5);">
      Already have an account?
      <a href="auth/login.php" style="color:var(--green);font-weight:600;text-decoration:underline;text-underline-offset:3px;">Log in here</a>
    </p>
  </div>
</section>

<!-- ══ FEATURES ══ -->
<section class="features" id="features">
  <div class="reveal">
    <span class="section-tag">Why Choose Us</span>
    <h2 class="section-title">Everything You Need<br>In One Platform</h2>
    <p class="section-sub">A seamlessly integrated system built to make healthcare accessible, efficient, and secure.</p>
  </div>

  <div class="features-grid">
    <div class="feature-card reveal">
      <div class="feature-icon icon-red">📅</div>
      <h3>Smart Appointment Booking</h3>
      <p>Calendar-based scheduling that prevents conflicts and double bookings. Pick your preferred doctor and time slot in seconds.</p>
    </div>
    <div class="feature-card reveal">
      <div class="feature-icon icon-blue">💻</div>
      <h3>Video Teleconsultation</h3>
      <p>Face-to-face consultations with licensed physicians through secure, high-quality video calls from any device.</p>
    </div>
    <div class="feature-card reveal">
      <div class="feature-icon icon-green">📋</div>
      <h3>Digital Medical Records</h3>
      <p>All your prescriptions, lab results, and consultation notes stored securely and accessible anytime.</p>
    </div>
    <div class="feature-card reveal">
      <div class="feature-icon icon-blue">💳</div>
      <h3>Online Payment Processing</h3>
      <p>Pay consultation fees seamlessly via PayMongo API. Safe, fast, and automatically updated in your account.</p>
    </div>
    <div class="feature-card reveal">
      <div class="feature-icon icon-red">🔔</div>
      <h3>Email Confirmation & Reminders</h3>
      <p>EmailJS integration automatically sends booking confirmations and allows staff to manually trigger reminder emails.</p>
    </div>
    <div class="feature-card reveal">
      <div class="feature-icon icon-green">🔒</div>
      <h3>Secure Authentication</h3>
      <p>Google OAuth 2.0 login with role-based access control for Patients, Doctors, and Administrators.</p>
    </div>
  </div>
</section>

<!-- ══ HOW IT WORKS ══ -->
<section class="how" id="how">
  <div style="text-align:center;" class="reveal">
    <span class="section-tag">Simple Process</span>
    <h2 class="section-title">Get Seen in 4 Easy Steps</h2>
    <p class="section-sub" style="margin: 0 auto;">From sign-up to consultation in minutes — no waiting rooms required.</p>
  </div>

  <div class="steps">
    <div class="step reveal">
      <div class="step-num">1</div>
      <h3>Create an Account</h3>
      <p>Register with your basic information and verify your email to get started.</p>
    </div>
    <div class="step reveal">
      <div class="step-num">2</div>
      <h3>Book an Appointment</h3>
      <p>Choose a doctor, select a date and time that works for you.</p>
    </div>
    <div class="step reveal">
      <div class="step-num">3</div>
      <h3>Pay Securely</h3>
      <p>Complete payment online via our integrated PayMongo gateway.</p>
    </div>
    <div class="step reveal">
      <div class="step-num">4</div>
      <h3>Start Consulting</h3>
      <p>Join your video call and receive care from a licensed doctor.</p>
    </div>
  </div>
</section>

<!-- ══ SERVICES ══ -->
<section class="services" id="services">
  <div class="reveal">
    <span class="section-tag">Our Services</span>
    <h2 class="section-title">What Tele-Care<br>Offers You</h2>
    <p class="section-sub">A unified web-based platform integrating all the tools you need for modern healthcare delivery.</p>
  </div>

  <div class="services-grid" style="margin-top:3.5rem;">
    <div class="service-card reveal">
      <div class="service-emoji">🌐</div>
      <h3>Unified Teleconsultation Platform</h3>
      <p>A centralized web-based system integrating appointment booking, teleconsultation, digital records, payments, and administrative management in one place.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">📆</div>
      <h3>Automated Appointment Scheduling</h3>
      <p>Calendar-based booking module that lets patients select available time slots while preventing scheduling conflicts and double bookings.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">✉️</div>
      <h3>Email Confirmation & Reminders</h3>
      <p>Automated booking confirmations via EmailJS, with staff-triggered reminder emails to keep patients informed and on schedule.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">💳</div>
      <h3>Online Payment Processing</h3>
      <p>Secure consultation payments via PayMongo API, with automatic status updates reflected directly in your account.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">🔐</div>
      <h3>Secure Authentication & Access</h3>
      <p>Google OAuth 2.0 login with role-based access control for Patients, Doctors, and Administrators to ensure data privacy.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">🤖</div>
      <h3>AI-Assisted Clinical Documentation</h3>
      <p>OpenAI API integration to summarize patient complaints and generate structured clinical notes for faster case review.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">🎙️</div>
      <h3>Speech-to-Text Documentation</h3>
      <p>Whisper API converts voice notes into text, significantly reducing the manual documentation workload for healthcare providers.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">🧾</div>
      <h3>Medical Document OCR & Records</h3>
      <p>Tesseract OCR and Medical NER models extract and structure medical data from uploaded lab results and prescriptions.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">📝</div>
      <h3>AI Meeting Summarizer</h3>
      <p>AI-powered transcription and summarization of clinic meetings, generating structured summaries with decisions, action items, and follow-ups.</p>
    </div>
    <div class="service-card reveal">
      <div class="service-emoji">📊</div>
      <h3>Administrative Dashboard</h3>
      <p>A centralized dashboard for managing appointments, payments, records, and system activities with secure digital storage and backup.</p>
    </div>
  </div>
</section>

<!-- ══ CTA BANNER ══ -->
<div class="cta-banner reveal">
  <h2>Ready to See a Doctor Today?</h2>
  <p>Join thousands of patients experiencing healthcare without barriers.</p>
  <a href="auth/register.php" class="btn-white">
    Get Started — It's Free
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
    </svg>
  </a>
</div>

<!-- ══ FOOTER ══ -->
<footer>
  <div class="footer-logo">TELE<span>-</span>CARE</div>
  <p style="margin-top:0.4rem;">&copy; 2026 Tele-Care Development Team &mdash; University of Caloocan City</p>
</footer>

<script>
  const reveals = document.querySelectorAll('.reveal');
  const observer = new IntersectionObserver(entries => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => entry.target.classList.add('visible'), i * 80);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  reveals.forEach(el => observer.observe(el));

  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  const navEl = document.querySelector('nav');
  window.addEventListener('scroll', () => {
    navEl.style.padding = window.scrollY > 60 ? '0.8rem 5%' : '1.2rem 5%';
  });
</script>
</body>
</html>