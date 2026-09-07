<?php
// doctor/index.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['doctor_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Doctor Portal — TELE-CARE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --red: #B31118;
      --red-dark: #8a000b;
      --red-tint: #fef2f2;
      --ink: #151c27;
      --ink-soft: rgba(21,28,39,0.64);
      --teal: #006a61;
      --teal-dark: #005249;
      --teal-tint: #ecfffb;
      --bg: #f7f8fc;
      --panel: rgba(255,255,255,0.82);
      --line: rgba(21,28,39,0.1);
      --shadow: 0 18px 48px rgba(12,18,28,0.08);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Inter', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(179,17,24,0.06), transparent 30%),
        radial-gradient(circle at top right, rgba(0,106,97,0.08), transparent 24%),
        linear-gradient(180deg, #ffffff 0%, #f7f8fc 48%, #f2f5fb 100%);
      min-height: 100vh;
      overflow-x: hidden;
    }

    a { color: inherit; }

    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      backdrop-filter: blur(18px);
      background: rgba(255,255,255,0.84);
      border-bottom: 1px solid var(--line);
    }

    .topbar-inner {
      width: min(100%, 1240px);
      margin: 0 auto;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }

    .brand {
      display: inline-flex;
      align-items: center;
      gap: 0.65rem;
      font-weight: 900;
      letter-spacing: 0.02em;
      text-decoration: none;
    }

    .brand-mark {
      width: 36px;
      height: 36px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--teal), var(--teal-dark));
      display: grid;
      place-items: center;
      color: #fff;
      box-shadow: 0 10px 24px rgba(0,106,97,0.18);
      flex-shrink: 0;
    }

    .brand-text {
      display: flex;
      flex-direction: column;
      line-height: 1.05;
    }

    .brand-text strong {
      font-size: 1.02rem;
      color: var(--ink);
    }

    .brand-text span {
      font-size: 0.72rem;
      color: var(--ink-soft);
      font-weight: 600;
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
      justify-content: center;
    }

    .nav-chip {
      text-decoration: none;
      font-size: 0.84rem;
      font-weight: 600;
      color: var(--ink-soft);
      padding: 0.5rem 0.85rem;
      border-radius: 999px;
      border: 1px solid transparent;
      transition: all 0.2s ease;
    }

    .nav-chip:hover {
      color: var(--ink);
      border-color: rgba(21,28,39,0.1);
      background: rgba(255,255,255,0.9);
    }

    .nav-actions {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      flex-shrink: 0;
    }

    .login-link {
      text-decoration: none;
      font-size: 0.84rem;
      font-weight: 700;
      color: var(--ink);
      border: 1.5px solid rgba(21,28,39,0.16);
      background: rgba(255,255,255,0.8);
      padding: 0.62rem 1rem;
      border-radius: 12px;
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    .login-link:hover {
      transform: translateY(-1px);
      border-color: rgba(21,28,39,0.25);
      box-shadow: 0 10px 24px rgba(21,28,39,0.08);
    }

    .cta-link {
      text-decoration: none;
      font-size: 0.84rem;
      font-weight: 700;
      color: #fff;
      background: var(--red);
      padding: 0.62rem 1rem;
      border-radius: 12px;
      box-shadow: 0 10px 24px rgba(179,17,24,0.24);
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    .cta-link:hover {
      background: var(--red-dark);
      transform: translateY(-1px);
      box-shadow: 0 14px 28px rgba(179,17,24,0.3);
    }

    .shell {
      width: min(100%, 1240px);
      margin: 0 auto;
      padding: 2rem 1.25rem 3rem;
    }

    .hero {
      display: grid;
      grid-template-columns: 1.15fr 0.85fr;
      gap: 1.2rem;
      align-items: stretch;
      margin-top: 0.4rem;
    }

    .hero-main,
    .hero-side,
    .panel,
    .feature-card,
    .snapshot-card {
      background: var(--panel);
      border: 1px solid rgba(21,28,39,0.08);
      border-radius: 28px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(16px);
    }

    .hero-main {
      padding: 2.2rem;
      position: relative;
      overflow: hidden;
      min-height: 100%;
    }

    .hero-main::before,
    .hero-main::after {
      content: '';
      position: absolute;
      border-radius: 999px;
      filter: blur(2px);
      pointer-events: none;
    }

    .hero-main::before {
      width: 320px;
      height: 320px;
      right: -150px;
      top: -100px;
      background: radial-gradient(circle, rgba(179,17,24,0.12), transparent 65%);
    }

    .hero-main::after {
      width: 260px;
      height: 260px;
      left: -130px;
      bottom: -110px;
      background: radial-gradient(circle, rgba(0,106,97,0.12), transparent 65%);
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      font-size: 0.73rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: var(--teal);
      background: var(--teal-tint);
      border: 1px solid rgba(0,106,97,0.12);
      border-radius: 999px;
      padding: 0.42rem 0.78rem;
      position: relative;
      z-index: 1;
    }

    .eyebrow-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--teal);
      box-shadow: 0 0 0 0 rgba(0,106,97,0.4);
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(0,106,97,0.4); }
      70% { box-shadow: 0 0 0 9px rgba(0,106,97,0); }
      100% { box-shadow: 0 0 0 0 rgba(0,106,97,0); }
    }

    h1 {
      font-size: clamp(2.45rem, 4vw, 4.4rem);
      line-height: 0.96;
      letter-spacing: -0.05em;
      margin-top: 1.2rem;
      margin-bottom: 1rem;
      position: relative;
      z-index: 1;
    }

    .accent {
      color: var(--red);
    }

    .hero-copy {
      color: var(--ink-soft);
      font-size: 1.02rem;
      line-height: 1.9;
      max-width: 46rem;
      position: relative;
      z-index: 1;
    }

    .hero-actions {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      flex-wrap: wrap;
      margin-top: 1.5rem;
      position: relative;
      z-index: 1;
    }

    .primary-btn,
    .secondary-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 700;
      border-radius: 14px;
      padding: 0.9rem 1.2rem;
      transition: all 0.2s ease;
    }

    .primary-btn {
      background: var(--red);
      color: #fff;
      box-shadow: 0 16px 30px rgba(179,17,24,0.24);
    }

    .primary-btn:hover { background: var(--red-dark); transform: translateY(-1px); }

    .secondary-btn {
      color: var(--ink);
      border: 1.5px solid rgba(21,28,39,0.14);
      background: rgba(255,255,255,0.75);
    }

    .secondary-btn:hover { transform: translateY(-1px); border-color: rgba(21,28,39,0.24); }

    .hero-meta {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.8rem;
      margin-top: 1.7rem;
      position: relative;
      z-index: 1;
    }

    .meta-card {
      border: 1px solid rgba(21,28,39,0.08);
      background: rgba(255,255,255,0.72);
      border-radius: 18px;
      padding: 1rem;
    }

    .meta-value {
      font-size: 1.55rem;
      font-weight: 900;
      letter-spacing: -0.05em;
      color: var(--ink);
      line-height: 1;
      margin-bottom: 0.25rem;
    }

    .meta-label {
      font-size: 0.76rem;
      color: var(--ink-soft);
      font-weight: 600;
      line-height: 1.45;
    }

    .hero-side {
      padding: 1.2rem;
      display: flex;
      flex-direction: column;
      gap: 0.9rem;
    }

    .snapshot-card {
      padding: 1rem;
      border-radius: 22px;
    }

    .snapshot-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.85rem;
    }

    .snapshot-title {
      font-size: 0.74rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--ink-soft);
    }

    .status-pill {
      font-size: 0.74rem;
      font-weight: 800;
      color: var(--teal);
      background: rgba(0,106,97,0.09);
      border: 1px solid rgba(0,106,97,0.12);
      padding: 0.3rem 0.65rem;
      border-radius: 999px;
    }

    .snapshot-main {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-bottom: 0.85rem;
    }

    .doctor-avatar {
      width: 52px;
      height: 52px;
      border-radius: 18px;
      background: linear-gradient(135deg, rgba(0,106,97,0.12), rgba(179,17,24,0.1));
      display: grid;
      place-items: center;
      flex-shrink: 0;
      font-weight: 900;
      color: var(--ink);
    }

    .snapshot-name {
      font-size: 1rem;
      font-weight: 800;
      color: var(--ink);
      line-height: 1.2;
    }

    .snapshot-role {
      font-size: 0.8rem;
      color: var(--ink-soft);
      margin-top: 0.2rem;
      line-height: 1.4;
    }

    .snapshot-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.7rem;
      margin-top: 0.8rem;
    }

    .snapshot-stat {
      border: 1px solid rgba(21,28,39,0.08);
      border-radius: 16px;
      padding: 0.85rem;
      background: rgba(255,255,255,0.82);
    }

    .snapshot-stat .label {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--ink-soft);
      margin-bottom: 0.35rem;
    }

    .snapshot-stat .value {
      font-size: 1.3rem;
      font-weight: 900;
      letter-spacing: -0.04em;
      line-height: 1.05;
    }

    .snapshot-note {
      margin-top: 0.85rem;
      background: rgba(0,106,97,0.06);
      border: 1px solid rgba(0,106,97,0.12);
      border-radius: 18px;
      padding: 0.95rem;
    }

    .snapshot-note strong {
      display: block;
      margin-bottom: 0.35rem;
      font-size: 0.86rem;
    }

    .snapshot-note p {
      font-size: 0.82rem;
      line-height: 1.65;
      color: var(--ink-soft);
    }

    .snapshot-cta {
      display: flex;
      gap: 0.65rem;
      margin-top: 0.95rem;
    }

    .snapshot-cta a {
      flex: 1;
      text-align: center;
      text-decoration: none;
      border-radius: 14px;
      padding: 0.82rem 1rem;
      font-size: 0.88rem;
      font-weight: 800;
    }

    .snapshot-login {
      color: #fff;
      background: var(--red);
      box-shadow: 0 14px 26px rgba(179,17,24,0.2);
    }

    .snapshot-login:hover { background: var(--red-dark); }

    .snapshot-link {
      color: var(--ink);
      border: 1.5px solid rgba(21,28,39,0.14);
      background: rgba(255,255,255,0.82);
    }

    .section {
      margin-top: 1.35rem;
    }

    .section-head {
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.85rem;
    }

    .section-kicker {
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--red);
      margin-bottom: 0.4rem;
    }

    .section-title {
      font-size: clamp(1.5rem, 2vw, 2.25rem);
      line-height: 1.05;
      letter-spacing: -0.04em;
    }

    .section-desc {
      color: var(--ink-soft);
      font-size: 0.95rem;
      line-height: 1.8;
      max-width: 52rem;
    }

    .feature-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.9rem;
      margin-top: 1rem;
    }

    .feature-card {
      padding: 1.1rem;
      border-radius: 22px;
    }

    .feature-icon {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display: grid;
      place-items: center;
      margin-bottom: 0.85rem;
      background: rgba(63,130,227,0.08);
      color: var(--blue);
    }

    .feature-card:nth-child(2) .feature-icon { background: rgba(0,106,97,0.08); color: var(--teal); }
    .feature-card:nth-child(3) .feature-icon { background: rgba(179,17,24,0.08); color: var(--red); }
    .feature-card:nth-child(4) .feature-icon { background: rgba(63,130,227,0.08); color: var(--blue); }
    .feature-card:nth-child(5) .feature-icon { background: rgba(130,94,198,0.1); color: #7c3aed; }
    .feature-card:nth-child(6) .feature-icon { background: rgba(245,158,11,0.1); color: #b45309; }

    .feature-card h3 {
      font-size: 1rem;
      font-weight: 800;
      margin-bottom: 0.4rem;
      letter-spacing: -0.02em;
    }

    .feature-card p {
      color: var(--ink-soft);
      font-size: 0.86rem;
      line-height: 1.7;
    }

    .feature-foot {
      margin-top: 0.85rem;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--red);
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      text-decoration: none;
    }

    .workflow {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.9rem;
      margin-top: 1rem;
    }

    .step {
      padding: 1rem;
      border-radius: 20px;
      background: rgba(255,255,255,0.86);
      border: 1px solid rgba(21,28,39,0.08);
      box-shadow: var(--shadow);
    }

    .step-num {
      width: 30px;
      height: 30px;
      border-radius: 999px;
      display: grid;
      place-items: center;
      font-size: 0.78rem;
      font-weight: 900;
      color: #fff;
      background: var(--red);
      margin-bottom: 0.85rem;
    }

    .step h4 {
      font-size: 0.96rem;
      font-weight: 800;
      margin-bottom: 0.35rem;
    }

    .step p {
      font-size: 0.84rem;
      line-height: 1.65;
      color: var(--ink-soft);
    }

    .stats-band {
      margin-top: 1.35rem;
      padding: 1rem;
      border-radius: 26px;
      background: linear-gradient(135deg, rgba(255,255,255,0.88), rgba(255,255,255,0.74));
      border: 1px solid rgba(21,28,39,0.08);
      box-shadow: var(--shadow);
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 0.8rem;
    }

    .band-stat {
      border-radius: 18px;
      padding: 1rem;
      background: rgba(255,255,255,0.86);
      border: 1px solid rgba(21,28,39,0.08);
    }

    .band-stat .num {
      font-size: 1.55rem;
      font-weight: 900;
      line-height: 1;
      letter-spacing: -0.05em;
      margin-bottom: 0.25rem;
    }

    .band-stat .txt {
      font-size: 0.78rem;
      color: var(--ink-soft);
      line-height: 1.45;
      font-weight: 600;
    }

    .footer {
      margin-top: 1.35rem;
      padding: 1.1rem 0 0.5rem;
      color: var(--ink-soft);
      font-size: 0.82rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .footer a {
      color: var(--ink-soft);
      text-decoration: none;
      font-weight: 600;
    }

    .footer a:hover { color: var(--ink); }

    @media (max-width: 1100px) {
      .hero { grid-template-columns: 1fr; }
      .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .workflow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .stats-band { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 760px) {
      .topbar-inner { padding: 0.85rem 0.85rem; }
      .nav-links { display: none; }
      .hero-main, .hero-side { padding: 1.2rem; border-radius: 22px; }
      .hero-meta { grid-template-columns: 1fr; }
      .feature-grid, .workflow, .stats-band { grid-template-columns: 1fr; }
      .snapshot-grid { grid-template-columns: 1fr; }
      .section-head { align-items: start; flex-direction: column; }
      h1 { font-size: 2.35rem; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <a class="brand" href="index.php" aria-label="TELE-CARE Doctor Portal">
        <div class="brand-mark">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v3m6-3v3M4 8h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/>
          </svg>
        </div>
        <div class="brand-text">
          <strong>Tele-Care AI</strong>
          <span>Doctor Portal</span>
        </div>
      </a>

      <nav class="nav-links" aria-label="Doctor portal navigation">
        <a class="nav-chip" href="#features">Features</a>
        <a class="nav-chip" href="#workflow">How it works</a>
        <a class="nav-chip" href="#live">Live system</a>
      </nav>

      <div class="nav-actions">
        <a class="login-link" href="login.php">Login</a>
      </div>
    </div>
  </header>

  <main class="shell">
    <section class="hero">
      <div class="hero-main">
        <div class="eyebrow"><span class="eyebrow-dot"></span>Doctor workspace</div>
        <h1>One portal for <span class="accent">clinical coordination</span>, scheduling, and patient follow-up.</h1>
        <p class="hero-copy">
          TELE-CARE gives doctors a single place to manage appointments, review patient records, conduct consultations, and follow up on summaries already stored in the system.
        </p>
        <div class="hero-actions">
          <a class="primary-btn" href="login.php">Login to Doctor Portal</a>
          <a class="secondary-btn" href="#features">See platform features</a>
        </div>

        <div class="hero-meta">
          <div class="meta-card">
            <div class="meta-value">Secure</div>
            <div class="meta-label">Protected doctor access to the portal</div>
          </div>
          <div class="meta-card">
            <div class="meta-value">Fast</div>
            <div class="meta-label">Quick access to appointments and patient records</div>
          </div>
          <div class="meta-card">
            <div class="meta-value">Connected</div>
            <div class="meta-label">Records, summaries, and follow-ups in one workspace</div>
          </div>
        </div>
      </div>

      <aside class="hero-side" id="live">
        <div class="snapshot-card">
          <div class="snapshot-head">
            <div class="snapshot-title">Portal preview</div>
            <div class="status-pill">What doctors can access</div>
          </div>

          <div class="snapshot-main">
            <div class="doctor-avatar">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14c3.314 0 6-2.686 6-6s-2.686-6-6-6-6 2.686-6 6 2.686 6 6 6z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 22a8 8 0 0116 0"/>
              </svg>
            </div>
            <div>
              <div class="snapshot-name">Clinical workflow overview</div>
              <div class="snapshot-role">Appointments, records, consultations, and summaries</div>
            </div>
          </div>

          <div class="snapshot-note">
            <strong>What the portal shows after login</strong>
            <p>Use the doctor dashboard to view the current appointment queue, review patient information, and save consultation summaries without switching tools.</p>
          </div>

          <div class="snapshot-cta">
            <a class="snapshot-login" href="login.php">Login</a>
            <a class="snapshot-link" href="#features">Explore features</a>
          </div>
        </div>
      </aside>
    </section>

    <section class="section" id="features">
      <div class="section-kicker">Clinical suite</div>
      <div class="section-head">
        <div>
          <div class="section-title">Everything a doctor needs to work inside TELE-CARE</div>
        </div>
      </div>
      <p class="section-desc">
        This landing page describes the actual portal capabilities already present in the doctor area: appointments, patient records, consultation calls, and summary review.
      </p>

      <div class="feature-grid">
        <article class="feature-card">
          <div class="feature-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v3m6-3v3M4 8h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/></svg>
          </div>
          <h3>Appointments</h3>
          <p>Open the doctor schedule, confirm consultations, and move through today's patient queue.</p>
          <a class="feature-foot" href="appointments.php">Open appointments →</a>
        </article>

        <article class="feature-card">
          <div class="feature-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M12 9v6M4 6h16M4 18h16"/></svg>
          </div>
          <h3>Patient records</h3>
          <p>Review the records, histories, and profile details available under the doctor workspace.</p>
          <a class="feature-foot" href="patient-records.php">Open records →</a>
        </article>

        <article class="feature-card">
          <div class="feature-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.17V11a6 6 0 10-12 0v3.17c0 .53-.21 1.04-.59 1.42L4 17h5"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v1a3 3 0 006 0v-1"/></svg>
          </div>
          <h3>Consultations</h3>
          <p>Use the call and consultation flow already wired into the doctor side of the system.</p>
          <a class="feature-foot" href="call.php">Open consultation tools →</a>
        </article>

        <article class="feature-card">
          <div class="feature-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-4"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
          </div>
          <h3>Summaries</h3>
          <p>Review and save consultation summaries from the doctor portal’s existing workflow.</p>
          <a class="feature-foot" href="review_summary.php">Review summaries →</a>
        </article>

        <article class="feature-card">
          <div class="feature-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6"/></svg>
          </div>
          <h3>Availability</h3>
          <p>Update your practice hours and availability blocks inside the doctor area.</p>
          <a class="feature-foot" href="availability.php">Set availability →</a>
        </article>
      </div>
    </section>

    <section class="section" id="workflow">
      <div class="section-kicker">Workflow</div>
      <div class="section-head">
        <div>
          <div class="section-title">A simple path from login to patient follow-up</div>
        </div>
      </div>
      <div class="workflow">
        <div class="step">
          <div class="step-num">1</div>
          <h4>Login securely</h4>
          <p>Use the doctor login page to enter the portal and access your assigned workspace.</p>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <h4>Open today's appointments</h4>
          <p>Review the current queue, confirmed visits, and pending consultations.</p>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <h4>Consult and document</h4>
          <p>Handle the appointment and record the consultation summary.</p>
        </div>
        <div class="step">
          <div class="step-num">4</div>
          <h4>Follow up</h4>
          <p>Return to summaries, schedule updates, and patient records for ongoing care.</p>
        </div>
      </div>
    </section>

    <section class="stats-band">
      <div class="band-stat">
        <div class="num">Appointments</div>
        <div class="txt">Queue, confirm, and manage consultations from one dashboard.</div>
      </div>
      <div class="band-stat">
        <div class="num">Records</div>
        <div class="txt">Review patient history and profile details before each visit.</div>
      </div>
      <div class="band-stat">
        <div class="num">Availability</div>
        <div class="txt">Set and update your available schedule blocks for consultations.</div>
      </div>
      <div class="band-stat">
        <div class="num">Summaries</div>
        <div class="txt">Store consultation notes and revisit them later.</div>
      </div>
    </section>

    <div class="footer">
      <div>&copy; <?= date('Y') ?> TELE-CARE. Doctor portal landing page.</div>
      <div style="display:flex;gap:1rem;flex-wrap:wrap;">
        <a href="login.php">Login</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="../index.php">Main site</a>
      </div>
    </div>
  </main>
</body>
</html>
