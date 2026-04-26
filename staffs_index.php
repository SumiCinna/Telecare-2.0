<?php
$pageTitle = 'TELE-CARE | Staff Portals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --red: #E05663;
      --green: #8fd1c9;
      --blue: #6fa8ff;
      --bg: #0b1016;
      --surface: #121923;
      --surface-2: #182231;
      --text: #e8f0ff;
      --muted: #9fb0c9;
      --line: rgba(159, 176, 201, 0.22);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background:
        radial-gradient(circle at 12% 14%, rgba(224,86,99,0.16), transparent 35%),
        radial-gradient(circle at 85% 10%, rgba(111,168,255,0.18), transparent 38%),
        radial-gradient(circle at 65% 78%, rgba(143,209,201,0.10), transparent 42%),
        var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }

    .card {
      width: 100%;
      max-width: 760px;
      background: linear-gradient(180deg, var(--surface), var(--surface-2));
      border-radius: 24px;
      padding: 2.2rem;
      border: 1px solid var(--line);
      box-shadow: 0 16px 50px rgba(0, 0, 0, 0.42);
    }

    .brand {
      font-family: 'Playfair Display', serif;
      font-size: 1.65rem;
      font-weight: 900;
      color: var(--text);
      margin-bottom: 0.2rem;
    }

    .brand span { color: var(--red); }

    .subtitle {
      color: var(--muted);
      font-size: 0.9rem;
      margin-bottom: 1.4rem;
    }

    .title {
      font-family: 'Playfair Display', serif;
      font-size: 1.55rem;
      line-height: 1.2;
      margin-bottom: 0.45rem;
    }

    .desc {
      color: rgba(232, 240, 255, 0.78);
      margin-bottom: 1.6rem;
      font-size: 0.95rem;
    }

    .portal-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 0.9rem;
      margin-bottom: 1.4rem;
    }

    .portal-link {
      display: block;
      text-decoration: none;
      border: 1.5px solid var(--line);
      border-radius: 16px;
      padding: 1rem;
      color: var(--text);
      transition: all 0.25s ease;
      background: rgba(11, 16, 22, 0.5);
    }

    .portal-link:hover {
      border-color: rgba(111,168,255,0.75);
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.35);
      background: rgba(24, 34, 49, 0.9);
    }

    .portal-name {
      display: block;
      font-weight: 700;
      font-size: 1rem;
      margin-bottom: 0.3rem;
    }

    .portal-note {
      font-size: 0.82rem;
      color: var(--muted);
      line-height: 1.45;
    }

    .footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.8rem;
      flex-wrap: wrap;
      border-top: 1px solid var(--line);
      padding-top: 1rem;
    }

    .back-link {
      color: var(--blue);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .hint {
      color: var(--muted);
      font-size: 0.8rem;
    }

    @media (max-width: 760px) {
      .card { padding: 1.4rem; }
      .portal-grid { grid-template-columns: 1fr; }
      .title { font-size: 1.3rem; }
    }
  </style>
</head>
<body>
  <main class="card">
    <div class="brand">TELE<span>-</span>CARE</div>
    <p class="subtitle">Secure Internal Portals</p>

    <h1 class="title">Choose a portal to sign in</h1>
    <p class="desc">Select the right access point for your account role.</p>

    <section class="portal-grid" aria-label="Portal choices">
      <a class="portal-link" href="doctor/login.php">
        <span class="portal-name">Doctor Login</span>
        <span class="portal-note">For licensed doctors handling consultations and patient records.</span>
      </a>

      <a class="portal-link" href="staff/login.php">
        <span class="portal-name">Staff Login</span>
        <span class="portal-note">For support staff managing appointments and operational tasks.</span>
      </a>

      <a class="portal-link" href="admin/login.php">
        <span class="portal-name">Admin Login</span>
        <span class="portal-note">For administrators with system-wide management access.</span>
      </a>
    </section>

    <div class="footer">
      <a class="back-link" href="index.php">← Back to Home</a>
      <p class="hint">Tip: Bookmark this page for quick portal access.</p>
    </div>
  </main>
</body>
</html>
