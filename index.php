<?php
// index.php
// The Android app (Capacitor) appends "TeleCareApp" to the user agent.
// App users never see the landing page: they go to the login screen.
// The only exception is index.php?app=1, which shows just the guest booking widget.
$isApp   = stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'TeleCareApp') !== false;
$appBook = $isApp && !empty($_GET['app']);
if ($isApp && !$appBook) {
    header('Location: auth/login.php');
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['gb_csrf'])) { $_SESSION['gb_csrf'] = bin2hex(random_bytes(32)); }
// A signed-in (non-guest) patient books from the account, not from this widget.
$gbLoggedIn = !empty($_SESSION['patient_id']) && empty($_SESSION['guest_mode']);
$pageTitle = "TELE-CARE | Your Health, Connected";

// Where the request buttons go: a signed-in patient starts the booking wizard from the account,
// a guest uses the booking widget on this page (#book).
$reqHref        = $gbLoggedIn ? 'router.php?page=booking/step1_details' : '#book';
$bookDoctorHref = $reqHref;

// Active doctors + their consultation fees, shown before booking so rates are visible upfront.
// Same source as the booking wizard (doctors.status = 'active', doctors.consultation_fee).
$lpDoctors = [];
try {
    $lpCfg = __DIR__ . '/database/config.php';
    if (is_file($lpCfg)) {
        require_once $lpCfg;
        if (isset($conn) && $conn instanceof mysqli) {
            $lpRes = $conn->query("SELECT id, full_name, specialty, subspecialty, department, consultation_fee, profile_photo, rating, rating_count
                                   FROM doctors WHERE status = 'active' ORDER BY department ASC, full_name ASC");
            while ($lpRes && ($lpRow = $lpRes->fetch_assoc())) $lpDoctors[] = $lpRow;
        }
    }
} catch (Throwable $lpErr) { error_log('index doctors list: ' . $lpErr->getMessage()); }
$lpDepts = [];
$lpFees  = [];
foreach ($lpDoctors as $lpD) {
    $dn = trim((string)($lpD['department'] ?? '')) ?: 'General Medicine / General Practice';
    $lpDepts[$dn] = ($lpDepts[$dn] ?? 0) + 1;
    if ((float)$lpD['consultation_fee'] > 0) $lpFees[] = (float)$lpD['consultation_fee'];
}
$lpH = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" href="telecarelogo.png">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
  <title><?= $pageTitle ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --blue: #1f6fc6;
      --blue-dark: #193f72;
      --blue-bright: #4382d6;
      --blue-soft: #d9ecff;
      --sky: #d9ecff;
      --ink: #474748;
      --muted: #64748b;
      --line: #e2e8f0;
      --surface: #fafafa;
      --page: #fafafa;
      --green: #26ae83;
      --amber: #f2a51a;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body { font-family: 'Poppins', 'Poppins', sans-serif; background: var(--page); color: var(--ink); overflow-x:hidden; }

    /* ── CONTAINER ── */
    .container { width:100%; max-width:1440px; margin:0 auto; padding:0 44px; }

    /* ── NAV ── */
    nav { position:sticky; top:0; z-index:200; background:rgba(255,255,255,.96); backdrop-filter:blur(14px); border-bottom:1px solid var(--line); box-shadow:0 2px 16px rgba(33,102,177,.08); }
    .nav-inner { max-width:1440px; margin:0 auto; padding:0 44px; display:flex; align-items:center; justify-content:space-between; min-height:68px; }
    @keyframes navIn {
      from { transform: translateY(-100%); opacity: 0; }
      to   { transform: translateY(0); opacity: 1; }
    }
    .nav-brand a { display:flex; align-items:center; gap:.45rem; text-decoration:none; color:var(--blue-dark); font-size:1.15rem; font-weight:800; letter-spacing:-.03em; }
    .logo-img { width:42px; height:42px; object-fit:contain; flex-shrink:0; }
    .nav-links { display:flex; gap:1.15rem; list-style:none; align-items:center; }
    .nav-links a { color:var(--ink); font-size:.75rem; font-weight:500; text-decoration:none; white-space:nowrap; transition:color .2s; }
    .nav-links a::after {
      content: ''; position: absolute; bottom: -3px; left: 0;
      width: 0; height: 1.5px; background: var(--red);
      transition: width 0.3s cubic-bezier(0.16,1,0.3,1);
    }
    .nav-links a:hover { color:var(--blue-bright); }
    .nav-login { color:#fff !important; font-size:.72rem !important; font-weight:600 !important; padding:.55rem 1rem; border-radius:18px; background:var(--blue) !important; box-shadow:0 5px 14px rgba(33,102,177,.22); }
    .nav-login:hover { background:var(--blue-dark) !important; }
    .nav-login::after { display: none !important; }
    .nav-cta { background:var(--blue) !important; color:#fff !important; padding:.55rem 1rem !important; border-radius:18px; font-weight:600 !important; font-size:.72rem !important; }
    .nav-cta:hover { background:var(--blue-dark) !important; }
    .nav-cta::after { display: none !important; }
    .nav-divider { width:1px; height:18px; background:var(--line); }

    .hamburger { display: none; flex-direction: column; gap: 0.28rem; }
    .hamburger span { width: 22px; height: 2px; background: var(--ink); border-radius: 2px; transition: all 0.3s; }
    .hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5.5px,5.5px); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5.5px,-5.5px); }
    .mob-menu {
      display: none; position: absolute; top: 68px; left: 0; right: 0;
      background: rgba(255,255,255,0.99); backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--line); flex-direction: column;
      padding: 1.1rem 4%; gap: 0.7rem;
      box-shadow: 0 12px 40px rgba(21,28,39,0.1); z-index: 199;
    }
    .mob-menu.open { display: flex; }
    .mob-menu a {
      color: rgba(21,28,39,0.72); font-size: 0.9rem; font-weight: 500;
      text-decoration: none; padding: 0.6rem 0;
      border-bottom: 1px solid rgba(21,28,39,0.05); transition: color 0.2s;
    }
    .mob-menu a:last-child { border-bottom: none; }
    .mob-menu a:hover { color: var(--ink); }
    .mob-menu .nav-login { border: 1.5px solid var(--ink) !important; text-align: center; margin: 0.25rem 0; display: block; }
    .mob-menu .nav-cta   { text-align: center; margin: 0.25rem 0; display: block; }

    /* ══ HERO ══ */
    .hero-wrap {
      min-height: 100vh;
      display: flex; align-items: center;
      padding-top: 68px;
      position: relative; background: #fff;
      overflow: hidden;
    }
    .hero-canvas {
      position: absolute; inset: 0; pointer-events: none; z-index: 0;
    }
    .hero-bg-grid {
      position: absolute; inset: 0; pointer-events: none; z-index: 1;
      background-image:
        linear-gradient(rgba(21,28,39,0.027) 1px, transparent 1px),
        linear-gradient(90deg, rgba(21,28,39,0.027) 1px, transparent 1px);
      background-size: 52px 52px;
      animation: gridMove 28s linear infinite;
    }
    @keyframes gridMove { to { background-position: 52px 52px; } }
    .orb {
      position: absolute; border-radius: 50%;
      filter: blur(90px); pointer-events: none;
    }
    .orb-1 {
      width: 500px; height: 500px; top: -100px; right: 15%;
      background: radial-gradient(circle, rgba(179,17,24,0.07) 0%, transparent 70%);
      animation: orbDrift 9s ease-in-out infinite; z-index: 1;
    }
    .orb-2 {
      width: 380px; height: 380px; bottom: 60px; left: 20%;
      background: radial-gradient(circle, rgba(0,106,97,0.07) 0%, transparent 70%);
      animation: orbDrift 11s ease-in-out infinite reverse; z-index: 1;
    }
    @keyframes orbDrift {
      0%,100% { transform: translate(0,0) scale(1); }
      50%      { transform: translate(12px,-12px) scale(1.08); }
    }

    /* Hero grid inside container */
    .hero {
      display: grid; grid-template-columns: 1fr 1fr;
      align-items: center; gap: 4rem;
      padding: 72px 0 64px;
      position: relative; z-index: 2; width: 100%;
    }
    .hero-divider {
      position: absolute; top: 0; bottom: 0; left: 50%; width: 1px;
      background: linear-gradient(to bottom, transparent, rgba(21,28,39,0.08) 25%, rgba(179,17,24,0.12) 70%, transparent);
      pointer-events: none;
    }

    /* Hero Left */
    .hero-left {
      animation: fadeLeft 0.9s 0.2s cubic-bezier(0.16,1,0.3,1) both;
    }
    @keyframes fadeLeft {
      from { opacity: 0; transform: translateX(-36px); }
      to   { opacity: 1; transform: translateX(0); }
    }
    .live-pill {
      display: inline-flex; align-items: center; gap: 0.45rem;
      background: rgba(21,28,39,0.06); border: 1px solid rgba(21,28,39,0.12);
      color: var(--ink); font-size: 0.7rem; font-weight: 700;
      letter-spacing: 0.12em; text-transform: uppercase;
      padding: 0.36rem 0.85rem; border-radius: 50px; margin-bottom: 1.8rem;
    }
    .live-dot {
      width: 6px; height: 6px; border-radius: 50%; background: #0D9488;
      box-shadow: 0 0 0 0 rgba(13,148,136,0.6);
      animation: livepulse 2s infinite;
    }
    @keyframes livepulse {
      0%  { box-shadow: 0 0 0 0 rgba(13,148,136,0.6); }
      70% { box-shadow: 0 0 0 8px rgba(13,148,136,0); }
      100%{ box-shadow: 0 0 0 0 rgba(13,148,136,0); }
    }
    .hero h1 {
      font-size: clamp(2.8rem, 4.2vw, 4.6rem);
      font-weight: 900; line-height: 1.07;
      color: var(--ink); margin-bottom: 1.4rem;
      letter-spacing: -0.02em;
    }
    .hero h1 em { color: var(--red); font-style: normal; }
    .word { display: inline-block; overflow: hidden; }
    .word span { display: inline-block; animation: wordUp 0.9s cubic-bezier(0.16,1,0.3,1) both; }
    .word:nth-child(1) span { animation-delay: 0.28s; }
    .word:nth-child(2) span { animation-delay: 0.40s; }
    .word:nth-child(3) span { animation-delay: 0.50s; }
    .word:nth-child(4) span { animation-delay: 0.58s; }
    @keyframes wordUp {
      from { transform: translateY(108%); }
      to   { transform: translateY(0); }
    }
    .hero-desc {
      font-size: 1.02rem; color: rgba(21,28,39,0.58);
      line-height: 1.85; margin-bottom: 2rem; max-width: 450px;
      animation: fadeUp 0.9s 0.72s both;
    }
    .pills {
      display: flex; flex-wrap: wrap; gap: 0.55rem;
      margin-bottom: 2.2rem;
      animation: fadeUp 0.9s 0.84s both;
    }
    .pill {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: var(--cream); border: 1px solid var(--line);
      padding: 0.32rem 0.8rem; border-radius: 50px;
      font-size: 0.73rem; font-weight: 600; color: rgba(21,28,39,0.65);
      transition: all 0.22s;
    }
    .pill:hover { background: #fff; border-color: rgba(21,28,39,0.2); color: var(--ink); transform: translateY(-2px); box-shadow: 0 4px 14px rgba(21,28,39,0.08); }
    .pill svg { width: 12px; height: 12px; flex-shrink: 0; }
    .p-r { color: var(--red); }
    .p-g { color: var(--teal); }
    .p-b { color: var(--teal-light); }
    .hero-btns {
      display: flex; gap: 0.9rem; flex-wrap: wrap;
      margin-bottom: 1.8rem;
      animation: fadeUp 0.9s 0.94s both;
    }
    .btn-primary {
      background: var(--red); color: #fff;
      padding: 0.88rem 2.1rem; border-radius: 8px;
      font-weight: 600; font-size: 0.92rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem;
      box-shadow: 0 8px 28px rgba(179,17,24,0.26);
      transition: all 0.32s cubic-bezier(0.16,1,0.3,1);
      position: relative; overflow: hidden;
    }
    .btn-primary::before {
      content: ''; position: absolute; top: 50%; left: 50%;
      width: 0; height: 0; background: rgba(255,255,255,0.15);
      border-radius: 50%; transform: translate(-50%,-50%);
      transition: width 0.5s, height 0.5s;
    }
    .btn-primary:hover::before { width: 300px; height: 300px; }
    .btn-primary:hover { background: var(--red-dark); transform: translateY(-3px); box-shadow: 0 16px 42px rgba(179,17,24,0.36); }
    .btn-primary svg { transition: transform 0.28s; position: relative; z-index: 1; }
    .btn-primary:hover svg { transform: translateX(4px); }
    .btn-primary span { position: relative; z-index: 1; }
    .btn-secondary {
      background: transparent; color: var(--ink);
      padding: 0.88rem 2.1rem; border-radius: 8px;
      font-weight: 500; font-size: 0.92rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem;
      border: 1.5px solid rgba(21,28,39,0.18);
      transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
    }
    .btn-secondary:hover { border-color: var(--ink); background: rgba(21,28,39,0.05); transform: translateY(-3px); }
    .hero-note {
      font-size: 0.8rem; color: rgba(21,28,39,0.42);
      animation: fadeUp 0.9s 1.02s both;
    }
    .hero-note a { color: var(--ink); font-weight: 600; text-underline-offset: 3px; text-decoration: underline; }

    /* Hero Right */
    .hero-right {
      display: flex; align-items: center; justify-content: center;
      animation: fadeRight 0.9s 0.38s cubic-bezier(0.16,1,0.3,1) both;
    }
    @keyframes fadeRight {
      from { opacity: 0; transform: translateX(36px); }
      to   { opacity: 1; transform: translateX(0); }
    }
    .hv-wrap {
      width: 100%; max-width: 400px;
      display: flex; flex-direction: column; gap: 0.8rem;
    }

    /* Accent badges */
    .hv-badge {
      display: flex; align-items: center; gap: 0.7rem;
      background: #fff; border: 1px solid rgba(21,28,39,0.1);
      border-radius: 14px; padding: 0.7rem 1rem;
      box-shadow: 0 6px 22px rgba(21,28,39,0.08);
      width: fit-content;
    }
    .hv-badge-icon {
      width: 34px; height: 34px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .hv-badge-icon svg { width: 17px; height: 17px; }
    .ib-red   { background: rgba(179,17,24,0.1);  color: var(--red); }
    .ib-green { background: rgba(0,106,97,0.1);  color: var(--teal); }
    .ib-blue  { background: rgba(13,148,136,0.12); color: var(--teal-light); }
    .hv-badge-label { font-size: 0.61rem; color: rgba(21,28,39,0.42); font-weight: 500; margin-bottom: 0.06rem; }
    .hv-badge-val   { font-size: 0.83rem; font-weight: 800; color: var(--ink); font-family: 'Poppins', sans-serif; }
    .badge-top { animation: bob 4s ease-in-out infinite; align-self: flex-end; }
    .badge-bot { animation: bob 5s ease-in-out infinite 1.2s; }
    @keyframes bob {
      0%,100% { transform: translateY(0); }
      50%      { transform: translateY(-7px); }
    }

    /* Main card */
    .hv-card {
      background: #fff; border-radius: 16px; padding: 1.8rem;
      border: 1px solid rgba(21,28,39,0.1);
      box-shadow: 0 18px 70px rgba(21,28,39,0.10), 0 4px 18px rgba(21,28,39,0.05);
      position: relative; overflow: hidden;
    }
    .hv-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2.5px;
      background: linear-gradient(90deg, var(--red), var(--teal));
    }
    .hv-card-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1.3rem;
    }
    .hv-card-title {
      font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em;
      text-transform: uppercase; color: rgba(21,28,39,0.48);
    }
    .hv-live {
      display: flex; align-items: center; gap: 0.35rem;
      font-size: 0.68rem; font-weight: 700; color: var(--teal-light);
    }
    .hv-live-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--teal-light); animation: livepulse 2s infinite; }

    /* Rows */
    .hv-rows { display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1.3rem; }
    .hv-row {
      display: flex; align-items: center; gap: 0.85rem;
      padding: 0.78rem 0.9rem; border-radius: 12px;
      background: var(--cream); border: 1px solid transparent;
      transition: all 0.28s;
    }
    .hv-row:hover { background: #fff; border-color: rgba(21,28,39,0.1); transform: translateX(4px); }
    .hv-row.hl { background: var(--red-tint); border-color: rgba(179,17,24,0.14); }
    .hv-row-icon {
      width: 36px; height: 36px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .hv-row-icon svg { width: 17px; height: 17px; }
    .hv-row-name { font-size: 0.83rem; font-weight: 700; color: var(--ink); margin-bottom: 0.05rem; font-family: 'Poppins', sans-serif; }
    .hv-row-sub  { font-size: 0.7rem; color: rgba(21,28,39,0.42); font-family: 'Poppins', sans-serif; }
    .hv-row-info { flex: 1; min-width: 0; }
    .hv-tag {
      font-size: 0.62rem; font-weight: 700; letter-spacing: 0.07em;
      text-transform: uppercase; padding: 0.2rem 0.55rem; border-radius: 50px;
      white-space: nowrap; flex-shrink: 0; font-family: 'Poppins', sans-serif;
    }
    .t-green { background: var(--teal-tint); color: var(--teal-light); }
    .t-blue  { background: rgba(13,148,136,0.1);  color: var(--teal-light); }
    .t-red   { background: var(--red-tint);   color: var(--red); }

    /* Card CTA */
    .hv-cta {
      background: var(--ink); border-radius: 12px; padding: 0.9rem 1.1rem;
      display: flex; align-items: center; justify-content: space-between;
      text-decoration: none; transition: all 0.28s; position: relative; overflow: hidden;
    }
    .hv-cta::after {
      content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.07), transparent);
      transition: left 0.5s;
    }
    .hv-cta:hover::after { left: 100%; }
    .hv-cta:hover { background: #0a0e14; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(21,28,39,0.2); }
    .hv-cta-text { font-size: 0.85rem; font-weight: 700; color: #fff; font-family: 'Poppins', sans-serif; }
    .hv-cta-sub  { font-size: 0.68rem; color: rgba(255,255,255,0.55); margin-top: 0.07rem; font-family: 'Poppins', sans-serif; }
    .hv-cta-arrow {
      width: 30px; height: 30px; border-radius: 50%;
      background: rgba(255,255,255,0.14);
      display: flex; align-items: center; justify-content: center;
      transition: transform 0.28s; flex-shrink: 0;
    }
    .hv-cta-arrow svg { width: 15px; height: 15px; color: #fff; }
    .hv-cta:hover .hv-cta-arrow { transform: translateX(4px); }

    /* ══ MARQUEE STRIP ══ */
    .marquee-strip {
      background: var(--ink); padding: 0; overflow: hidden;
      border-top: 1px solid rgba(255,255,255,0.05);
      border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .marquee-track {
      display: flex; align-items: center; gap: 0;
      animation: marquee 28s linear infinite;
      width: max-content;
    }
    .marquee-track:hover { animation-play-state: paused; }
    @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    .marquee-item {
      display: flex; align-items: center; gap: 0.6rem;
      padding: 0.85rem 2.2rem;
      font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
      color: rgba(255,255,255,0.5); white-space: nowrap; border-right: 1px solid rgba(255,255,255,0.08);
      transition: color 0.2s;
    }
    .marquee-item:hover { color: rgba(255,255,255,0.9); }
    .marquee-dot {
      width: 4px; height: 4px; border-radius: 50%;
      background: var(--red); flex-shrink: 0; opacity: 0.7;
    }

    /* ══ SECTIONS ══ */
    section { padding: 100px 0; }
    .stag {
      display: inline-flex; align-items: center; gap: 0.5rem;
      font-size: 0.7rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase;
      color: var(--red); margin-bottom: 1.1rem; font-family: 'DM Mono', monospace;
    }
    .stag::before { content: ''; width: 22px; height: 2px; background: var(--red); display: block; }
    .stitle {
      font-size: clamp(1.9rem, 3.2vw, 2.8rem);
      font-weight: 800; color: var(--ink); line-height: 1.13;
      margin-bottom: 1rem; letter-spacing: -0.01em;
    }
    .ssub { color: rgba(21,28,39,0.55); font-size: 1rem; line-height: 1.8; max-width: 510px; font-family: 'Poppins', sans-serif; }

    /* ══ FEATURES ══ */
    .features { background: var(--cream); }
    .feat-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 1.4rem; margin-top: 3.8rem;
    }
    .feat-card {
      background: #fff; border-radius: 16px; padding: 2rem;
      border: 1px solid rgba(21,28,39,0.07);
      transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
      position: relative; overflow: hidden; cursor: default;
    }
    .feat-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, var(--red), var(--teal));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.4s cubic-bezier(0.16,1,0.3,1);
    }
    .feat-card::after {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(179,17,24,0.035) 0%, transparent 65%);
      opacity: 0; transition: opacity 0.4s;
    }
    .feat-card:hover { transform: translateY(-9px); box-shadow: 0 26px 65px rgba(21,28,39,0.11); }
    .feat-card:hover::before { transform: scaleX(1); }
    .feat-card:hover::after { opacity: 1; }
    .feat-icon {
      width: 50px; height: 50px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1.3rem; transition: transform 0.4s cubic-bezier(0.16,1,0.3,1);
    }
    .feat-card:hover .feat-icon { transform: scale(1.1) rotate(-3deg); }
    .feat-icon svg { width: 22px; height: 22px; }
    .feat-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.6rem; color: var(--ink); }
    .feat-card p  { font-size: 0.87rem; line-height: 1.72; color: rgba(21,28,39,0.55); font-family: 'Poppins', sans-serif; }

    /* ══ HOW IT WORKS ══ */
    .how { background: #fff; }
    .steps-row {
      display: grid; grid-template-columns: repeat(4,1fr);
      gap: 0; margin-top: 3.8rem; position: relative;
      max-width: 820px; margin-left: auto; margin-right: auto;
    }
    .steps-row::before {
      content: ''; position: absolute;
      top: 32px; left: calc(12.5% + 14px); right: calc(12.5% + 14px); height: 1px;
      background: linear-gradient(90deg, var(--red), var(--teal)); opacity: 0.18;
    }
    .step { text-align: center; padding: 0 1rem; }
    .step-num {
      width: 64px; height: 64px; border-radius: 50%;
      background: #fff; border: 2px solid rgba(21,28,39,0.18);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Poppins', sans-serif;
      font-size: 1.45rem; font-weight: 900; color: var(--ink);
      margin: 0 auto 1.3rem; position: relative; z-index: 1;
      transition: all 0.38s cubic-bezier(0.16,1,0.3,1);
    }
    .step:hover .step-num {
      background: var(--red); color: #fff; border-color: var(--red);
      transform: scale(1.1); box-shadow: 0 10px 30px rgba(179,17,24,0.2);
    }
    .step h3 { font-size: 0.98rem; font-weight: 700; margin-bottom: 0.45rem; color: var(--ink); }
    .step p  { font-size: 0.82rem; color: rgba(21,28,39,0.55); line-height: 1.65; font-family: 'Poppins', sans-serif; }

    /* ══ SERVICES ══ */
    .services { background: #fff; border-top: 1px solid var(--line); }
    .svc-grid {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 1.2rem; margin-top: 3.5rem;
    }
    .svc-card {
      background: var(--cream); border: 1px solid rgba(21,28,39,0.07);
      border-radius: 14px; padding: 1.7rem;
      transition: all 0.38s cubic-bezier(0.16,1,0.3,1);
      position: relative; overflow: hidden; cursor: default;
    }
    .svc-card::after {
      content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, var(--red), var(--teal));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.38s cubic-bezier(0.16,1,0.3,1);
    }
    .svc-card:hover { background: #fff; transform: translateY(-7px); border-color: rgba(21,28,39,0.12); box-shadow: 0 20px 50px rgba(21,28,39,0.10); }
    .svc-card:hover::after { transform: scaleX(1); }
    .svc-icon {
      width: 44px; height: 44px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1rem; transition: transform 0.38s cubic-bezier(0.16,1,0.3,1);
    }
    .svc-card:hover .svc-icon { transform: scale(1.12) rotate(4deg); }
    .svc-icon svg { width: 20px; height: 20px; }
    .svc-card h3 { font-size: 0.98rem; font-weight: 700; color: var(--ink); margin-bottom: 0.45rem; }
    .svc-card p  { font-size: 0.82rem; color: rgba(21,28,39,0.55); line-height: 1.67; font-family: 'Poppins', sans-serif; }

    /* ══ CTA BAND ══ */
    .cta-band {
      background: var(--ink); padding: 100px 0;
      position: relative; overflow: hidden;
    }
    .cta-bg-anim {
      position: absolute; inset: 0; pointer-events: none;
    }
    .cta-band::before {
      content: ''; position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(255,255,255,0.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.022) 1px, transparent 1px);
      background-size: 50px 50px;
      animation: gridMove 30s linear infinite;
    }
    .cta-orb {
      position: absolute; border-radius: 50%; pointer-events: none;
    }
    .cta-orb-1 {
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(179,17,24,0.18) 0%, transparent 70%);
      top: 50%; left: 50%; transform: translate(-50%,-50%);
      animation: orbDrift 8s ease-in-out infinite;
    }
    .cta-orb-2 {
      width: 300px; height: 300px;
      background: radial-gradient(circle, rgba(0,106,97,0.16) 0%, transparent 70%);
      bottom: -80px; right: 10%;
      animation: orbDrift 12s ease-in-out infinite reverse;
    }
    .cta-inner { position: relative; z-index: 1; text-align: center; }
    .cta-stag {
      display: inline-flex; align-items: center; gap: 0.5rem;
      font-size: 0.7rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
      color: rgba(255,255,255,0.45); margin-bottom: 1.1rem;
      font-family: 'DM Mono', monospace;
    }
    .cta-stag::before { content: ''; width: 22px; height: 1px; background: rgba(255,255,255,0.3); display: block; }
    .cta-band h2 {
      font-size: clamp(2.2rem, 4vw, 3.2rem); color: #fff;
      margin-bottom: 0.9rem; letter-spacing: -0.01em;
    }
    .cta-band h2 em { color: #ff5a63; font-style: normal; }
    .cta-band p { color: rgba(255,255,255,0.52); margin-bottom: 2.8rem; font-size: 1.02rem; line-height: 1.8; font-family: 'Poppins', sans-serif; }
    .btn-white {
      background: #fff; color: var(--ink);
      padding: 1rem 2.8rem; border-radius: 8px;
      font-weight: 700; font-size: 0.94rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
      box-shadow: 0 10px 36px rgba(0,0,0,0.18);
      transition: all 0.32s cubic-bezier(0.16,1,0.3,1);
      position: relative; overflow: hidden;
    }
    .btn-white::before {
      content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(21,28,39,0.06), transparent);
      transition: left 0.5s;
    }
    .btn-white:hover::before { left: 100%; }
    .btn-white:hover { transform: translateY(-4px); box-shadow: 0 22px 55px rgba(0,0,0,0.26); }
    .btn-white svg { transition: transform 0.28s; position: relative; z-index: 1; }
    .btn-white:hover svg { transform: translateX(5px); }
    .btn-white > span { position: relative; z-index: 1; }

    /* ══ FLOATING TECH TAGS (CTA) ══ */
    .cta-tags {
      display: flex; justify-content: center; gap: 0.65rem;
      flex-wrap: wrap; margin-top: 2.4rem;
    }
    .cta-tag {
      display: inline-flex; align-items: center; gap: 0.38rem;
      background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
      color: rgba(255,255,255,0.55); font-size: 0.7rem; font-weight: 600;
      padding: 0.3rem 0.82rem; border-radius: 50px; letter-spacing: 0.05em;
      font-family: 'DM Mono', monospace; transition: all 0.28s;
    }
    .cta-tag:hover { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.85); border-color: rgba(255,255,255,0.2); transform: translateY(-2px); }
    .cta-tag-dot { width: 4px; height: 4px; border-radius: 50%; background: var(--red); opacity: 0.8; }

    /* ══ FOOTER ══ */
    footer {
      background: var(--cream); border-top: 1px solid var(--line);
      padding: 2.8rem 0; text-align: center;
      color: rgba(21,28,39,0.42); font-size: 0.8rem;
    }
    .foot-logo { display: inline-flex; align-items: center; gap: 0.6rem; font-family: 'Poppins', sans-serif; font-size: 1.35rem; font-weight: 900; color: var(--ink); }
    .foot-logo span { color: var(--red); }
    footer p { margin-top: 0.5rem; }

    /* ══ ANIMATIONS ══ */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(38px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .reveal {
      opacity: 0; transform: translateY(34px);
      transition: opacity 0.75s cubic-bezier(0.16,1,0.3,1), transform 0.75s cubic-bezier(0.16,1,0.3,1);
    }
    .reveal.on { opacity: 1; transform: translateY(0); }
    .reveal-left {
      opacity: 0; transform: translateX(-28px);
      transition: opacity 0.75s cubic-bezier(0.16,1,0.3,1), transform 0.75s cubic-bezier(0.16,1,0.3,1);
    }
    .reveal-left.on { opacity: 1; transform: translateX(0); }
    .reveal-right {
      opacity: 0; transform: translateX(28px);
      transition: opacity 0.75s cubic-bezier(0.16,1,0.3,1), transform 0.75s cubic-bezier(0.16,1,0.3,1);
    }
    .reveal-right.on { opacity: 1; transform: translateX(0); }

    /* ══ RESPONSIVE ══ */
    @media (max-width: 1100px) {
      .container, .nav-inner { padding: 0 32px; }
      .feat-grid { grid-template-columns: repeat(2, 1fr); }
      .svc-grid  { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 900px) {
      .hero { grid-template-columns: 1fr; gap: 3rem; }
      .hero-divider { display: none; }
      .hero-right { justify-content: flex-start; }
    }
    @media (max-width: 768px) {
      nav .nav-links { display: none; }
      .hamburger { display: flex; }
      .container, .nav-inner { padding: 0 20px; }
      .hero { padding: 56px 0 48px; gap: 2.5rem; }
      .hero h1 { font-size: clamp(2rem,6vw,3.2rem); }
      .hero-btns { flex-direction: column; }
      .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
      section { padding: 64px 0; }
      .feat-grid { grid-template-columns: 1fr; }
      .svc-grid  { grid-template-columns: 1fr 1fr; }
      .badge-top, .badge-bot { display: none; }
      .steps-row { grid-template-columns: 1fr 1fr; }
      .steps-row::before { display: none; }
    }
    @media (max-width: 560px) {
      .steps-row { grid-template-columns: 1fr; gap: 2rem; }
      .svc-grid  { grid-template-columns: 1fr; }
      .cta-band  { padding: 64px 0; }
      .hero h1   { font-size: clamp(1.8rem,6vw,2.6rem); }
      .orb-1, .orb-2 { display: none; }
    }

    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: #fff; }
    ::-webkit-scrollbar-thumb { background: var(--ink); border-radius: 3px; }

    /* Patient landing-page design kit */
    .hero-wrap,.marquee-strip,.features,.how,.services,.cta-band { display:none !important; }
    .kit-page { background:var(--page); }
    .kit-hero { padding:64px 0 46px; background:var(--blue-soft); }
    .kit-hero-grid { display:grid; grid-template-columns:minmax(0,1.08fr) minmax(0,.92fr); align-items:center; gap:72px; }
    .kit-hero h1 { color:var(--blue-dark); font-size:clamp(2.4rem,5vw,4.4rem); line-height:.98; letter-spacing:-.06em; margin:12px 0 14px; }
    .kit-hero h1 span { color:var(--blue-bright); }
    .kit-hero p { max-width:520px; color:var(--muted); font-size:.92rem; line-height:1.7; margin-bottom:20px; }
    .kit-eyebrow { color:var(--blue); font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.12em; }
    .kit-actions { display:flex; gap:10px; flex-wrap:wrap; margin:18px 0; }
    .kit-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:10px 18px; border-radius:20px; border:1px solid transparent; text-decoration:none; font-size:.75rem; font-weight:700; }
    .kit-btn-primary { color:#fff; background:var(--blue); box-shadow:0 7px 16px rgba(33,102,177,.22); }
    .kit-btn-primary:hover { background:var(--blue-dark); }
    .kit-btn-outline { color:var(--blue); border-color:#a9d1f1; background:#fff; }
    .kit-search { display:flex; align-items:center; gap:8px; max-width:520px; padding:12px 15px; background:#fff; border:1px solid var(--line); border-radius:24px; box-shadow:0 5px 16px rgba(31,111,198,.08); }
    .kit-search svg { width:15px; color:var(--blue-bright); flex:0 0 auto; }
    .kit-search span { color:#90a7bc; font-size:.7rem; }
    .kit-search input { width:100%; border:0; outline:0; background:transparent; color:var(--ink); font:inherit; font-size:.7rem; }
    .kit-search input::placeholder { color:#90a7bc; }
    .kit-hero-art { min-height:310px; display:grid; place-items:center; position:relative; }
    .kit-art-circle { width:min(100%,360px); aspect-ratio:1; border-radius:50%; display:grid; place-items:center; background:radial-gradient(circle at 35% 30%,#fff 0 14%,#b8e1ff 15% 46%,#5aa9ed 47% 70%,#1d6bc0 71%); box-shadow:0 20px 50px rgba(33,102,177,.18); }
    .kit-art-circle img { width:57%; height:57%; object-fit:contain; filter:drop-shadow(0 14px 12px rgba(0,57,117,.2)); }
    .kit-float { position:absolute; display:flex; align-items:center; gap:7px; padding:9px 12px; border:1px solid var(--line); border-radius:12px; background:#fff; color:var(--blue-dark); font-size:.66rem; font-weight:700; box-shadow:0 8px 22px rgba(33,102,177,.12); }
    .kit-float svg { width:17px; color:var(--blue-bright); }
    .kit-float.one { top:16px; right:0; } .kit-float.two { bottom:24px; left:0; }
    .kit-section { padding:42px 0; }
    .kit-section-head { display:flex; justify-content:space-between; align-items:end; gap:20px; margin-bottom:20px; }
    .kit-section h2 { color:var(--blue-dark); font-size:clamp(1.5rem,3vw,2rem); margin:5px 0 0; letter-spacing:-.04em; }
    .kit-section-head p { color:var(--muted); font-size:.78rem; max-width:390px; line-height:1.6; }
    .kit-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; }
    .kit-card { position:relative; padding:18px; border:1px solid var(--line); border-radius:12px; background:#fff; box-shadow:0 4px 14px rgba(33,102,177,.05); transition:transform .2s,box-shadow .2s; }
    .kit-card:hover { transform:translateY(-3px); border-color:var(--blue-bright); box-shadow:0 12px 24px rgba(31,111,198,.14); }
    .kit-icon { width:38px; height:38px; display:grid; place-items:center; border-radius:10px; color:var(--blue); background:var(--blue-soft); margin-bottom:12px; }
    .kit-icon svg { width:20px; height:20px; }
    .kit-card h3 { color:var(--ink); font-size:.82rem; margin-bottom:5px; }
    .kit-card p { color:var(--muted); font-size:.68rem; line-height:1.55; }
    .kit-arrow { position:absolute; right:14px; bottom:13px; color:var(--blue-bright); font-size:1rem; }
    .kit-process { background:#fff; border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
    .kit-steps { display:grid; grid-template-columns:repeat(5,1fr); gap:16px; }
    .kit-step { position:relative; text-align:center; padding:8px 10px; }
    .kit-step:not(:last-child)::after { content:'→'; position:absolute; right:-12px; top:19px; color:#8bc0ed; font-size:1.4rem; }
    .kit-step-num { width:32px; height:32px; margin:0 auto 8px; display:grid; place-items:center; border-radius:50%; color:#fff; background:var(--blue); font-size:.75rem; font-weight:700; }
    .kit-step h3 { font-size:.78rem; color:var(--ink); margin-bottom:4px; }
    .kit-step p { color:var(--muted); font-size:.66rem; line-height:1.5; }
    .kit-benefits { background:#f1f8ff; }
    .kit-benefit-grid { display:grid; grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr); gap:52px; align-items:center; }
    .kit-benefit-panel { padding:24px; background:#fff; border:1px solid #cfe6f8; border-radius:14px; }
    .kit-benefit-row { display:flex; align-items:center; gap:10px; padding:11px 0; border-bottom:1px solid #e8f2fa; color:var(--ink); font-size:.76rem; }
    .kit-benefit-row:last-child { border-bottom:0; }
    .kit-benefit-row svg { width:18px; color:var(--blue-bright); }
    .kit-benefit-row strong { color:var(--blue-dark); }
    .kit-benefit-row a { color:var(--blue); font-weight:700; text-decoration:none; white-space:nowrap; }
    .kit-app { background:var(--blue-soft); color:var(--ink); }
    .kit-app-grid { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr); align-items:center; gap:52px; }
    .kit-app h2 { color:var(--blue-dark); } .kit-app p { color:var(--muted); font-size:.78rem; line-height:1.7; max-width:440px; }
    .kit-phone { justify-self:end; width:190px; padding:12px; border:7px solid #d8efff; border-radius:24px; background:#fff; box-shadow:0 15px 30px rgba(0,24,67,.25); transform:rotate(7deg); }
    .kit-phone img { display:block; width:100%; border-radius:14px; }
    .kit-store-row { display:flex; gap:9px; margin-top:16px; flex-wrap:wrap; }
    .kit-store { padding:8px 12px; border:1px solid var(--blue); border-radius:8px; color:var(--blue-dark); font-size:.65rem; text-decoration:none; background:#fff; }
    .kit-store-apk { display:inline-flex; align-items:center; gap:8px; padding:11px 18px; border-radius:20px; border:0; color:#fff; background:var(--blue); box-shadow:0 7px 16px rgba(33,102,177,.22); font-size:.75rem; font-weight:700; text-decoration:none; }
    .kit-store-apk:hover { background:var(--blue-dark); }
    .kit-store-apk svg { width:16px; height:16px; }
    .nav-links li:first-child a { color:var(--blue); font-weight:700; }
    .nav-links li:first-child a::after { width:100%; }
    footer { padding:28px 0; background:var(--blue-dark); border:0; color:#c8e4fb; }
    .foot-logo { color:#fff; font-size:1.1rem; } .foot-logo span { color:#8fd0ff; }
    footer p { font-size:.68rem; } 
    @media(max-width:780px) { .kit-hero-grid,.kit-benefit-grid,.kit-app-grid { grid-template-columns:1fr; } .kit-hero-art { min-height:270px; } .kit-grid { grid-template-columns:1fr 1fr; } .kit-phone { justify-self:center; } .nav-links { display:none; } .hamburger { display:flex; } }
    @media(max-width:520px) { .kit-grid,.kit-steps { grid-template-columns:1fr; } .kit-section-head { display:block; } .kit-section-head p { margin-top:8px; } .kit-step:not(:last-child)::after { display:none; } .kit-hero { padding-top:34px; } }

    /* ── Guest booking widget (landing page) ── */
    .gb-section { background:#fff; border-top:1px solid var(--line); border-bottom:1px solid var(--line); scroll-margin-top:76px; }
    .gb-card { max-width:980px; margin:0 auto; background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:0 18px 44px rgba(25,63,114,.08); padding:26px; }
    .gb-steps { display:flex; align-items:flex-start; margin-bottom:24px; }
    .gb-step { display:flex; flex-direction:column; align-items:center; gap:6px; min-width:62px; }
    .gb-dot { width:28px; height:28px; border-radius:50%; display:grid; place-items:center; font-size:.72rem; font-weight:700; background:#e8f1fb; color:var(--muted); transition:background .2s; }
    .gb-step.on .gb-dot { background:var(--blue); color:#fff; box-shadow:0 0 0 4px rgba(31,111,198,.15); }
    .gb-step.done .gb-dot { background:var(--green); color:#fff; }
    .gb-step span { font-size:.62rem; color:var(--muted); font-weight:600; text-align:center; }
    .gb-step.on span { color:var(--blue-dark); }
    .gb-line { flex:1; height:2px; background:var(--line); margin:14px 6px 0; }
    .gb-line.done { background:var(--green); }
    .gb-h { color:var(--blue-dark); font-size:1.05rem; font-weight:700; letter-spacing:-.02em; margin-bottom:12px; }
    .gb-label { display:block; font-size:.72rem; font-weight:600; color:var(--blue-dark); margin-bottom:6px; }
    .gb-label i { color:#e11d48; font-style:normal; }
    .gb-label small { color:var(--muted); font-weight:500; }
    .gb-input, .gb-text { width:100%; padding:11px 13px; border:1.5px solid var(--line); border-radius:11px; font:inherit; font-size:.82rem; color:var(--ink); background:#fff; outline:0; transition:border-color .2s, box-shadow .2s; }
    .gb-input:focus, .gb-text:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(31,111,198,.12); }
    .gb-input.bad, .gb-text.bad { border-color:#e11d48; }
    .gb-text { min-height:132px; resize:vertical; line-height:1.55; }
    .gb-meta { display:flex; justify-content:space-between; gap:12px; margin-top:6px; font-size:.66rem; color:var(--muted); }
    .gb-field { margin-bottom:15px; }
    .gb-fe { color:#e11d48; font-size:.68rem; margin-top:5px; min-height:0; }
    .gb-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:0 18px; }
    .gb-actions { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:20px; }
    .gb-btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:11px 22px; border-radius:999px; border:1px solid transparent; font:inherit; font-size:.78rem; font-weight:700; cursor:pointer; text-decoration:none; transition:background .2s, opacity .2s; }
    .gb-btn.primary { background:var(--blue); color:#fff; box-shadow:0 7px 16px rgba(33,102,177,.22); }
    .gb-btn.primary:hover:not(:disabled) { background:var(--blue-dark); }
    .gb-btn.ghost { background:#fff; color:var(--blue); border-color:#a9d1f1; }
    .gb-btn:disabled { opacity:.5; cursor:not-allowed; }
    .gb-alert { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:12px; padding:11px 14px; font-size:.76rem; line-height:1.5; margin-bottom:14px; }
    .gb-alert a { color:#991b1b; font-weight:700; }
    .gb-alert.warn { background:#fffbeb; border-color:#fde68a; color:#92400e; }
    .gb-rec { border:1.5px solid #cfe6f8; background:#f1f8ff; border-radius:14px; padding:15px 17px; margin-bottom:16px; }
    .gb-rec small { display:block; font-size:.62rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); }
    .gb-rec b { display:block; color:var(--blue-dark); font-size:1.15rem; margin:3px 0 4px; letter-spacing:-.02em; }
    .gb-rec p { font-size:.76rem; color:var(--ink); line-height:1.55; }
    .gb-rec em { display:block; font-style:normal; font-size:.64rem; color:var(--muted); margin-top:5px; }
    .gb-chips { margin-top:10px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; font-size:.68rem; color:var(--muted); }
    .gb-chip { padding:5px 12px; border:1.5px solid var(--line); border-radius:999px; background:#fff; font:inherit; font-size:.68rem; font-weight:600; color:var(--blue-dark); cursor:pointer; }
    .gb-chip:hover { border-color:var(--blue); color:var(--blue); }
    .gb-docs { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:14px; }
    .gb-doc { position:relative; border:1px solid var(--line); border-radius:14px; padding:15px; background:#fff; display:flex; flex-direction:column; gap:10px; transition:border-color .2s, box-shadow .2s; }
    .gb-doc:hover { border-color:var(--blue-bright); box-shadow:0 10px 22px rgba(31,111,198,.12); }
    .gb-doc-top { display:flex; gap:12px; align-items:center; }
    .gb-av { width:50px; height:50px; border-radius:50%; background:#e3efff; color:var(--blue); display:grid; place-items:center; font-weight:700; overflow:hidden; flex:0 0 auto; }
    .gb-av img { width:100%; height:100%; object-fit:cover; }
    .gb-doc b { display:block; color:var(--blue-dark); font-size:.84rem; line-height:1.3; }
    .gb-doc small { display:block; color:var(--muted); font-size:.68rem; margin-top:2px; }
    .gb-stars { color:#ca8a04; font-size:.68rem; }
    .gb-fee { background:#f1f8ff; border-radius:9px; padding:8px 11px; font-size:.78rem; font-weight:700; color:var(--blue-dark); }
    .gb-badge { position:absolute; top:-9px; right:12px; background:var(--blue); color:#fff; font-size:.58rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; padding:3px 10px; border-radius:999px; }
    .gb-sched { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(240px,.9fr); gap:18px; align-items:start; }
    .gb-box { border:1px solid var(--line); border-radius:14px; padding:16px; }
    .gb-strip { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
    .gb-cal-h { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
    .gb-cal-h b { font-size:.86rem; color:var(--blue-dark); }
    .gb-nav { background:none; border:0; cursor:pointer; color:var(--blue-dark); padding:4px 10px; border-radius:8px; font-size:1.1rem; }
    .gb-nav:hover:not(:disabled) { background:#eaf3fc; }
    .gb-nav:disabled { opacity:.25; cursor:default; }
    .gb-cal { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; }
    .gb-dn { text-align:center; font-size:.62rem; font-weight:700; color:var(--muted); padding:4px 0; }
    .gb-c { aspect-ratio:1; display:grid; place-items:center; border-radius:10px; font-size:.78rem; font-weight:500; }
    .gb-c.past { color:rgba(71,71,72,.22); }
    .gb-c.blocked { color:rgba(71,71,72,.25); text-decoration:line-through; }
    .gb-c.av { color:var(--blue-dark); background:#f1f6fc; cursor:pointer; font-weight:600; }
    .gb-c.av:hover { background:#dcebfb; color:var(--blue); }
    .gb-c.today.av { border:1.5px solid var(--blue); }
    .gb-c.sel { background:var(--blue) !important; color:#fff !important; font-weight:700; }
    .gb-sum-row { padding:10px 0; border-top:1px solid var(--line); font-size:.76rem; color:var(--blue-dark); }
    .gb-sum-row:first-of-type { border-top:0; padding-top:0; }
    .gb-sum-row small { display:block; color:var(--muted); font-size:.62rem; font-weight:600; margin-bottom:2px; }
    .gb-times { display:grid; grid-template-columns:repeat(2,1fr); gap:7px; margin-top:10px; max-height:300px; overflow:auto; padding-right:2px; }
    .gb-t { padding:9px 0; border:1.5px solid var(--line); border-radius:10px; text-align:center; font-size:.74rem; font-weight:600; color:var(--blue-dark); cursor:pointer; background:#fff; }
    .gb-t:hover { border-color:var(--blue); color:var(--blue); }
    .gb-t.sel { background:var(--blue); border-color:var(--blue); color:#fff; }
    .gb-t.off { background:#f5f7fa; color:rgba(71,71,72,.3); cursor:not-allowed; border-color:transparent; }
    .gb-mobile { display:flex; gap:8px; }
    .gb-mobile .pre { flex:0 0 auto; display:flex; align-items:center; gap:6px; padding:0 12px; border:1.5px solid var(--line); border-radius:11px; font-size:.82rem; color:var(--ink); background:#f8fafc; }
    .gb-radios { display:flex; gap:18px; padding:10px 0; font-size:.82rem; }
    .gb-radios label { display:flex; align-items:center; gap:7px; cursor:pointer; }
    .gb-radios input { accent-color:var(--blue); width:16px; height:16px; }
    .gb-accept { display:flex; gap:9px; align-items:flex-start; font-size:.74rem; color:var(--ink); line-height:1.5; }
    .gb-accept input { margin-top:2px; width:16px; height:16px; accent-color:var(--blue); flex:0 0 auto; }
    .gb-accept a { color:var(--blue); font-weight:600; }
    .gb-verify { max-width:380px; margin:0 auto; text-align:center; }
    .gb-verify p { font-size:.8rem; color:var(--muted); line-height:1.6; margin-bottom:14px; }
    .gb-verify p b { color:var(--blue-dark); }
    .gb-code { text-align:center; font-size:1.7rem; letter-spacing:.5em; padding:12px 10px 12px 20px; font-weight:700; color:var(--blue-dark); }
    .gb-link { background:none; border:0; font:inherit; font-size:.72rem; font-weight:700; color:var(--blue); cursor:pointer; padding:8px; }
    .gb-link:disabled { color:var(--muted); cursor:default; }
    .gb-center { text-align:center; padding:18px 6px; }
    .gb-center p { color:var(--muted); font-size:.82rem; margin-bottom:16px; }
    @media(max-width:780px) { .gb-sched { grid-template-columns:1fr; } .gb-card { padding:18px 14px; } .gb-grid2 { grid-template-columns:1fr; } }
    @media(max-width:520px) { .gb-step { min-width:46px; } .gb-step span { font-size:.56rem; } .gb-actions { flex-direction:column-reverse; align-items:stretch; } .gb-btn { width:100%; } .gb-docs { grid-template-columns:1fr; } }

    /* ── Doctors & fees list (landing page) ── */
    .dl-section { scroll-margin-top:76px; background:var(--page); }
    .dl-pay { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin:0 0 16px; font-size:.7rem; color:var(--muted); }
    .dl-pay b { color:var(--blue-dark); }
    .dl-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border-radius:999px; background:#fff; border:1px solid var(--line); color:var(--blue-dark); font-weight:700; font-size:.68rem; }
    .dl-pill i { width:8px; height:8px; border-radius:50%; display:inline-block; }
    .dl-tools { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:16px; }
    .dl-find { display:flex; align-items:center; gap:8px; flex:1 1 260px; max-width:360px; padding:10px 14px; background:#fff; border:1px solid var(--line); border-radius:22px; }
    .dl-find svg { width:15px; color:var(--blue-bright); flex:0 0 auto; }
    .dl-find input { width:100%; border:0; outline:0; background:transparent; font:inherit; font-size:.74rem; color:var(--ink); }
    .dl-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .dl-chip { border:1px solid #a9d1f1; background:#fff; color:var(--blue); border-radius:999px; padding:7px 13px; font:inherit; font-size:.68rem; font-weight:700; cursor:pointer; }
    .dl-chip.on { background:var(--blue); border-color:var(--blue); color:#fff; }
    .dl-range { margin-left:auto; font-size:.72rem; color:var(--muted); }
    .dl-range b { color:var(--blue-dark); }
    .dl-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px; }
    .dl-card { background:#fff; border:1px solid var(--line); border-radius:14px; padding:16px; display:flex; flex-direction:column; gap:11px; transition:border-color .2s, box-shadow .2s; }
    .dl-card[hidden], .dl-empty[hidden] { display:none !important; }
    .dl-card:hover { border-color:var(--blue-bright); box-shadow:0 10px 22px rgba(31,111,198,.12); }
    .dl-top { display:flex; gap:12px; align-items:center; }
    .dl-av { width:46px; height:46px; border-radius:50%; background:var(--blue-soft); color:var(--blue); display:grid; place-items:center; font-weight:700; font-size:.9rem; overflow:hidden; flex:0 0 auto; }
    .dl-av img { width:100%; height:100%; object-fit:cover; }
    .dl-card b { display:block; color:var(--blue-dark); font-size:.86rem; line-height:1.3; }
    .dl-card small { display:block; color:var(--muted); font-size:.68rem; margin-top:2px; }
    .dl-stars { color:#ca8a04; font-size:.68rem; }
    .dl-stars em { color:var(--muted); font-style:italic; }
    .dl-dept { font-size:.62rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
    .dl-fee { display:flex; justify-content:space-between; align-items:baseline; background:#f1f8ff; border-radius:10px; padding:9px 12px; }
    .dl-fee span { font-size:.68rem; color:var(--muted); font-weight:600; }
    .dl-fee strong { font-size:1rem; color:var(--blue-dark); letter-spacing:-.02em; }
    .dl-card .gb-btn { width:100%; margin-top:auto; }
    .dl-empty { grid-column:1/-1; text-align:center; color:var(--muted); font-size:.8rem; padding:26px 10px; }
    /* ── Quick-text chips (multi-select) ── */
    .gb-quick { margin-bottom:12px; }
    .gb-quick > small { display:block; font-size:.66rem; color:var(--muted); margin-bottom:7px; font-weight:600; }
    .gb-quick-row { display:flex; flex-wrap:wrap; gap:8px; }
    .gb-qbtn { border:1.5px solid #a9d1f1; background:#fff; color:var(--blue); border-radius:999px; padding:7px 14px; font:inherit; font-size:.7rem; font-weight:700; cursor:pointer; transition:background .15s,color .15s; }
    .gb-qbtn:hover { border-color:var(--blue); }
    .gb-qbtn.on { background:var(--blue); border-color:var(--blue); color:#fff; }
    .gb-qbtn.on::before { content:'\2713  '; }
    @media(max-width:520px){ .dl-range { margin-left:0; width:100%; } .dl-grid { grid-template-columns:1fr; } }

    /* ── Android app: booking-only mode (index.php?app=1) ── */
    .app-bar { display:none; }
    .app-mode nav, .app-mode .mob-menu, .app-mode footer { display:none !important; }
    .app-mode .kit-page > section:not(#book) { display:none !important; }
    .app-mode .app-bar { display:flex; position:sticky; top:0; z-index:200; align-items:center; gap:12px; padding:calc(12px + max(env(safe-area-inset-top, 0px), 32px)) 16px 12px; background:rgba(255,255,255,.97); border-bottom:1px solid var(--line); box-shadow:0 2px 16px rgba(33,102,177,.08); }
    .app-bar a { display:inline-flex; align-items:center; gap:6px; color:var(--blue); font-size:.78rem; font-weight:700; text-decoration:none; }
    .app-bar a svg { width:16px; height:16px; }
    .app-bar b { margin-left:auto; color:var(--blue-dark); font-size:.85rem; }
    .app-mode .gb-section { border-top:0; padding-top:24px; }
    .app-mode .container { padding:0 14px; }
    .app-mode { padding-bottom:max(env(safe-area-inset-bottom, 0px), 24px); }
  </style>
</head>
<body class="<?= $appBook ? 'app-mode' : '' ?>">

<?php if ($appBook): ?>
<div class="app-bar">
  <a href="auth/login.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Back to Login
  </a>
  <b>Book a Doctor</b>
</div>
<?php endif; ?>

<div class="scroll-bar" id="scrollBar"></div>

<!-- ══ NAV ══ -->
<nav id="nav">
  <div class="nav-inner">
    <div class="nav-brand">
      <a href="index.php">
        <img class="logo-img" src="telecarelogo.png" alt="Tele-Care logo"/>
        <div class="logo">TELE-CARE</div>
      </a>
    </div>
    <ul class="nav-links">
      <li><a href="index.php" aria-current="page">Home</a></li>
      <li><a href="#doctors">Find a Doctor</a></li>
      <li><a href="#consultation">Quick Consultation</a></li>
      <li><a href="#appointments">Appointments</a></li>
      <li><a href="#benefits">Insurance &amp; Benefits</a></li>
      <li><a href="#download">Download App</a></li>
      <li style="display:flex;align-items:center;gap:0.32rem;">
        <a href="auth/login.php" class="nav-login">Log In</a>
      </li>
      <li><a href="auth/register.php" class="nav-cta">Register</a></li>
    </ul>
    <div class="hamburger" id="ham"><span></span><span></span><span></span></div>
  </div>
</nav>

<div class="mob-menu" id="mobMenu">
  <a href="index.php" aria-current="page">Home</a>
  <a href="#doctors">Find a Doctor</a>
  <a href="#consultation">Quick Consultation</a>
  <a href="<?= $reqHref ?>" data-quick="medcert">Request Med Cert</a>
  <a href="<?= $reqHref ?>" data-quick="refill">Request Refill Prescription</a>
  <a href="#appointments">Appointments</a>
  <a href="#benefits">Insurance &amp; Benefits</a>
  <a href="#download">Download App</a>
  <a href="auth/login.php" class="nav-login">Log In</a>
  <a href="auth/register.php" class="nav-cta">Register</a>
</div>

<main class="kit-page">
  <section class="kit-hero" id="consultation">
    <div class="container kit-hero-grid">
      <div>
        <div class="kit-eyebrow">Your Healthcare, Anywhere</div>
        <h1>Your Healthcare,<br><span>Anywhere.</span></h1>
        <p>Connect with licensed healthcare professionals online, book consultations, and access healthcare services from wherever you are.</p>
        <div class="kit-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
          <input type="search" id="heroSearch" aria-label="Search doctor or specialty" placeholder="Search doctor or specialty...">
        </div>
        <div class="kit-actions">
          <a class="kit-btn kit-btn-primary" href="#doctors">Find a Doctor</a>
          <a class="kit-btn kit-btn-outline" href="#book" >Quick Consultation</a>
          <a class="kit-btn kit-btn-outline" href="<?= $reqHref ?>" data-quick="medcert">Request Med Cert</a>
          <a class="kit-btn kit-btn-outline" href="<?= $reqHref ?>" data-quick="refill">Request Refill Prescription</a>
        </div>
      </div>
      <div class="kit-hero-art" aria-label="Tele-Care healthcare illustration">
        <div class="kit-art-circle"><img src="telecarelogo.png" alt="Tele-Care"/></div>
        <div class="kit-float one"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg> Licensed professionals</div>
        <div class="kit-float two"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg> Private &amp; secure</div>
      </div>
    </div>
  </section>

  <!-- ══ Available doctors + consultation fees (before booking) ══ -->
  <section class="kit-section dl-section" id="doctors">
    <div class="container">
      <div class="kit-section-head"><div><div class="kit-eyebrow">Our doctors</div><h2>Available Doctors &amp; Consultation Fees</h2></div><p>See who is available and what a consultation costs before you book.</p></div>

      <div class="dl-pay">
        <b>Pay with:</b>
        <span class="dl-pill"><i style="background:#00a3e0"></i>GCash</span>
        <span class="dl-pill"><i style="background:#0b8f4d"></i>PhilHealth YAKAP</span>
        <span class="dl-pill"><i style="background:#7e3af2"></i>HMO</span>
        <span>YAKAP and HMO bookings are verified by the clinic. Choose your payment method when you book.</span>
      </div>

<?php if (!$lpDoctors): ?>
      <div class="gb-card"><div class="gb-center" style="text-align:center"><p style="color:var(--muted);font-size:.82rem">Our doctor list is not available right now. You can still <a href="#book" style="color:var(--blue);font-weight:700">start a booking</a> and pick a doctor there.</p></div></div>
<?php else: ?>
      <div class="dl-tools">
        <label class="dl-find"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" id="dlSearch" aria-label="Search doctors" placeholder="Search doctor or specialty..."></label>
        <?php if (count($lpDepts) > 1): ?>
        <div class="dl-chips" id="dlChips">
          <button type="button" class="dl-chip on" data-dept="">All (<?= count($lpDoctors) ?>)</button>
<?php foreach ($lpDepts as $dn => $cnt): ?>
          <button type="button" class="dl-chip" data-dept="<?= $lpH($dn) ?>"><?= $lpH($dn) ?> (<?= (int)$cnt ?>)</button>
<?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($lpFees): ?>
        <div class="dl-range">Fees: <b>&#8369;<?= number_format(min($lpFees), 2) ?></b><?= min($lpFees) !== max($lpFees) ? ' to <b>&#8369;' . number_format(max($lpFees), 2) . '</b>' : '' ?> per consultation</div>
        <?php endif; ?>
      </div>

      <div class="dl-grid" id="dlGrid">
<?php foreach ($lpDoctors as $lpD):
    $dn   = trim((string)($lpD['department'] ?? '')) ?: 'General Medicine / General Practice';
    $nm   = trim((string)$lpD['full_name']);
    $ini  = strtoupper(mb_substr($nm, 0, 1) . (str_contains($nm, ' ') ? mb_substr($nm, strpos($nm, ' ') + 1, 1) : ''));
    $fee  = (float)$lpD['consultation_fee'];
    $rc   = (int)$lpD['rating_count'];
    $rt   = (float)$lpD['rating'];
    $hay  = mb_strtolower($nm . ' ' . ($lpD['specialty'] ?? '') . ' ' . ($lpD['subspecialty'] ?? '') . ' ' . $dn);
?>
        <div class="dl-card" data-dept="<?= $lpH($dn) ?>" data-hay="<?= $lpH($hay) ?>">
          <div class="dl-top">
            <div class="dl-av"><?php if (!empty($lpD['profile_photo'])): ?><img src="<?= $lpH($lpD['profile_photo']) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?><?= $lpH($ini) ?></div>
            <div>
              <b>Dr. <?= $lpH($nm) ?></b>
              <small><?= $lpH($lpD['specialty'] ?: $dn) ?><?= !empty($lpD['subspecialty']) ? ' &middot; ' . $lpH($lpD['subspecialty']) : '' ?></small>
              <?php if ($rc > 0): $f = (int)floor($rt); $h = ($rt - $f) >= .5 ? 1 : 0; ?>
              <span class="dl-stars"><?= str_repeat('&#9733;', $f) . ($h ? '&#189;' : '') . str_repeat('&#9734;', 5 - $f - $h) ?> <?= number_format($rt, 1) ?> (<?= $rc ?>)</span>
              <?php else: ?><span class="dl-stars"><em>No ratings yet</em></span><?php endif; ?>
            </div>
          </div>
          <div class="dl-dept"><?= $lpH($dn) ?></div>
          <div class="dl-fee"><span>Consultation fee</span><strong><?= $fee > 0 ? '&#8369;' . number_format($fee, 2) : 'To be confirmed' ?></strong></div>
          <a class="gb-btn primary" href="<?= $bookDoctorHref ?>" >Book a consultation</a>
        </div>
<?php endforeach; ?>
        <div class="dl-empty" id="dlEmpty" hidden>No doctors match your search.</div>
      </div>
<?php endif; ?>
    </div>
  </section>

  <!-- ══ Guest booking: no account needed ══ -->
  <section class="kit-section gb-section" id="book">
    <div class="container">
      <div class="kit-section-head"><div><div class="kit-eyebrow">Book online</div><h2>Book a Doctor</h2></div><p>Tell us how you feel and pick a doctor.</p></div>

<?php if ($gbLoggedIn): ?>
      <div class="gb-card"><div class="gb-center">
        <div class="gb-h">You're signed in</div>
        <p>Book from your TELE-CARE account.</p>
        <a class="gb-btn primary" href="router.php?page=booking/step1_details">Book from my account</a>
      </div></div>
<?php else: ?>
      <div class="gb-card" id="gb" data-csrf="<?= htmlspecialchars($_SESSION['gb_csrf'], ENT_QUOTES) ?>">
        <div class="gb-steps" id="gbSteps"></div>
        <div id="gbAlert"></div>

        <!-- 1 · concern -->
        <div class="gb-panel" id="gbP1">
          <div class="gb-h">What are you feeling?</div>
          <div class="gb-quick">
            <small>Need something specific? Tap to add it (you can pick more than one, or just type below):</small>
            <div class="gb-quick-row" id="gbQuick">
              <button type="button" class="gb-qbtn" data-q="medcert">Request for Medical Certificate</button>
              <button type="button" class="gb-qbtn" data-q="lab">Request for Lab Results</button>
              <button type="button" class="gb-qbtn" data-q="refill">Request for Prescription Refill</button>
            </div>
          </div>
          <div class="gb-field">
            <label class="gb-label" for="gbConcern">Reason for booking / chief complaint <i>*</i></label>
            <textarea class="gb-text" id="gbConcern" maxlength="500" placeholder="e.g. I've had a dry cough and mild fever for 3 days, and I get short of breath at night."></textarea>
            <div class="gb-meta"><span>English or Filipino. Analyzed by AI only to suggest a department; not a diagnosis.</span><span><span id="gbCount">0</span>/500</span></div>
          </div>
          <div class="gb-actions"><span></span><button class="gb-btn primary" id="gbFind" type="button">Find a Doctor</button></div>
        </div>

        <!-- 2 · doctors -->
        <div class="gb-panel" id="gbP2" hidden>
          <div id="gbUrgent"></div>
          <div class="gb-rec"><small id="gbRecLabel">Suggested department</small><b id="gbRecDept"></b><p id="gbRecReason"></p><em id="gbRecSrc"></em><div class="gb-chips" id="gbChips"></div></div>
          <div class="gb-docs" id="gbDocs"></div>
          <div class="gb-actions"><button class="gb-btn ghost" type="button" data-back="1">Back</button><span></span></div>
        </div>

        <!-- 3 · schedule -->
        <div class="gb-panel" id="gbP3" hidden>
          <div class="gb-sched">
            <div class="gb-box">
              <div class="gb-strip"><div class="gb-av" id="gbSAv"></div><div><b style="color:var(--blue-dark);font-size:.88rem" id="gbSName"></b><small style="display:block;color:var(--muted);font-size:.7rem" id="gbSSpec"></small></div></div>
              <div class="gb-cal-h"><button class="gb-nav" id="gbPrev" type="button">&#8249;</button><b id="gbMonth"></b><button class="gb-nav" id="gbNext" type="button">&#8250;</button></div>
              <div class="gb-cal" id="gbCal"></div>
            </div>
            <aside class="gb-box">
              <div class="gb-sum-row"><small>Department</small><span id="gbSDept"></span></div>
              <div class="gb-sum-row"><small>Doctor</small><span id="gbSDoc"></span></div>
              <div class="gb-sum-row"><small>Date &amp; time</small><span id="gbSWhen">Pending selection</span></div>
              <div class="gb-times" id="gbTimes"></div>
            </aside>
          </div>
          <div class="gb-actions"><button class="gb-btn ghost" type="button" data-back="2">Back</button><button class="gb-btn primary" id="gbSlotGo" type="button" disabled>Continue</button></div>
        </div>

        <!-- 4 · patient details -->
        <div class="gb-panel" id="gbP4" hidden>
          <div class="gb-h">Patient Details</div>
          <form id="gbForm" novalidate autocomplete="on">
            <div class="gb-grid2">
              <div class="gb-field"><label class="gb-label" for="gbFirst">First Name <i>*</i></label><input class="gb-input" id="gbFirst" name="first_name" maxlength="60" autocomplete="given-name"><div class="gb-fe" data-e="first_name"></div></div>
              <div class="gb-field"><label class="gb-label" for="gbMid">Middle Name</label><input class="gb-input" id="gbMid" name="middle_name" maxlength="60" autocomplete="additional-name"><div class="gb-fe" data-e="middle_name"></div></div>
              <div class="gb-field"><label class="gb-label" for="gbLast">Last Name <i>*</i></label><input class="gb-input" id="gbLast" name="last_name" maxlength="60" autocomplete="family-name"><div class="gb-fe" data-e="last_name"></div></div>
              <div class="gb-field"><label class="gb-label" for="gbDob">Date of Birth <small>(MM/DD/YYYY)</small> <i>*</i></label><input class="gb-input" id="gbDob" name="dob" inputmode="numeric" maxlength="10" placeholder="MM/DD/YYYY" autocomplete="bday"><div class="gb-fe" data-e="dob"></div></div>
              <div class="gb-field"><label class="gb-label">Sex <i>*</i></label><div class="gb-radios"><label><input type="radio" name="sex" value="Male"> Male</label><label><input type="radio" name="sex" value="Female"> Female</label></div><div class="gb-fe" data-e="sex"></div></div>
              <div class="gb-field"><label class="gb-label" for="gbAddr">Address <i>*</i></label><input class="gb-input" id="gbAddr" name="address" maxlength="255" autocomplete="street-address"><div class="gb-fe" data-e="address"></div></div>
              <div class="gb-field"><label class="gb-label" for="gbMob">Mobile Number <i>*</i></label><div class="gb-mobile"><div class="pre">&#127477;&#127469; +63</div><input class="gb-input" id="gbMob" name="mobile" inputmode="numeric" maxlength="13" placeholder="9XX XXX XXXX" autocomplete="tel-national"></div><div class="gb-fe" data-e="mobile"></div></div>
              <div class="gb-field"><label class="gb-label" for="gbEmail">Email <i>*</i></label><input class="gb-input" id="gbEmail" name="email" type="email" maxlength="150" autocomplete="email"><div class="gb-fe" data-e="email"></div></div>
            </div>
            <div class="gb-field"><label class="gb-label" for="gbReason">Reason for Booking / Chief Complaint <i>*</i></label><input class="gb-input" id="gbReason" name="reason" maxlength="500"><div class="gb-fe" data-e="reason"></div></div>
            <div class="gb-field"><label class="gb-accept"><input type="checkbox" id="gbAccept" name="accept" value="1"><span>I have read, understood, and accepted the <a href="auth/termsandpolicy.php" target="_blank" rel="noopener">Terms &amp; Conditions</a> and <a href="private_telecare/privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a></span></label><div class="gb-fe" data-e="accept"></div></div>
            <div class="gb-actions"><button class="gb-btn ghost" type="button" data-back="3">Back</button><button class="gb-btn primary" id="gbSubmit" type="submit">Continue</button></div>
          </form>
        </div>

        <!-- 5 · email code -->
        <div class="gb-panel" id="gbP5" hidden>
          <div class="gb-verify">
            <div class="gb-h">We're almost there</div>
            <p>Enter the 6-digit code sent to <b id="gbMail"></b></p>
            <input class="gb-input gb-code" id="gbCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="------">
            <div><button class="gb-link" id="gbClear" type="button">CLEAR</button></div>
            <div class="gb-actions" style="justify-content:center;margin-top:8px"><button class="gb-btn primary" id="gbVerify" type="button" disabled>Verify &amp; Continue</button></div>
            <div style="margin-top:6px"><button class="gb-link" id="gbResend" type="button" disabled>Resend code</button> &middot; <button class="gb-link" type="button" data-back="4">Edit details</button></div>
          </div>
        </div>
      </div>
<?php endif; ?>
    </div>
  </section>

  <section class="kit-section" id="services">
    <div class="container">
      <div class="kit-section-head"><div><div class="kit-eyebrow">Clinical services</div><h2>Clinical Services</h2></div><p>Access healthcare services designed around your needs.</p></div>
      <div class="kit-grid">
        <a class="kit-card" href="auth/register.php"><div class="kit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3v5a4 4 0 0 0 8 0V3M6 3H4m10 0h2M10 12v2a5 5 0 0 0 10 0v-1"/><circle cx="20" cy="10" r="2"/></svg></div><h3>Online Doctor Consultation</h3><p>Talk to a licensed healthcare professional online.</p><span class="kit-arrow">→</span></a>
        <a class="kit-card" href="auth/register.php"><div class="kit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18"/></svg></div><h3>Appointment &amp; Follow-up Care</h3><p>Schedule your preferred date and time.</p><span class="kit-arrow">→</span></a>
        <a class="kit-card" href="ocr/scan.php"><div class="kit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3h6l2 2h3v16H4V5h3l2-2Z"/><path d="M8 11h8M8 15h6"/></svg></div><h3>Prescription &amp; Treatment Guidance</h3><p>Keep your treatment information organized.</p><span class="kit-arrow">→</span></a>
        <a class="kit-card" href="<?= $reqHref ?>" data-quick="medcert"><div class="kit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16 9 11l4 4 3-3 4 4"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div><h3>Medical Certificate</h3><p>Receive important records after your consultation.</p><span class="kit-arrow">→</span></a>
        <a class="kit-card" href="ocr/scan.php"><div class="kit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19h16M6 17V7m4 10V4m4 13v-7m4 7V3"/></svg></div><h3>Laboratory &amp; Diagnostic Requests</h3><p>Submit and digitize your health documents.</p><span class="kit-arrow">→</span></a>
        <a class="kit-card" href="auth/register.php"><div class="kit-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h18M5 8v11h14V8M8 5h8M9 12h6M9 16h4"/></svg></div><h3>Consultation Summary &amp; Medical Records</h3><p>Access structured consultation summaries and medical records.</p><span class="kit-arrow">→</span></a>
      </div>
    </div>
  </section>

  <section class="kit-section kit-process" id="appointments">
    <div class="container">
      <div class="kit-section-head"><div><div class="kit-eyebrow">How Tele-Care works</div><h2>How TELE-CARE Works</h2></div></div>
      <div class="kit-steps">
        <div class="kit-step"><div class="kit-step-num">1</div><h3>Find a Doctor</h3><p>Choose the right healthcare professional.</p></div>
        <div class="kit-step"><div class="kit-step-num">2</div><h3>Book / Quick Consult</h3><p>Select an appointment or start quickly.</p></div>
        <div class="kit-step"><div class="kit-step-num">3</div><h3>Verify</h3><p>Complete your secure patient details.</p></div>
        <div class="kit-step"><div class="kit-step-num">4</div><h3>Consult</h3><p>Meet your healthcare professional online.</p></div>
        <div class="kit-step"><div class="kit-step-num">5</div><h3>Receive Records</h3><p>Get your summary and care information.</p></div>
      </div>
    </div>
  </section>

  <section class="kit-section kit-benefits" id="benefits">
    <div class="container kit-benefit-grid">
      <div><div class="kit-eyebrow">Insurance &amp; benefits</div><h2>Insurance &amp; Benefits</h2><p style="color:var(--muted);font-size:.78rem;line-height:1.7;margin-top:10px;">Check available healthcare coverage for your consultation.</p></div>
      <div class="kit-benefit-panel">
        <div class="kit-benefit-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 7v5c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg><span><strong>HMO</strong><br>Check HMO availability and verify coverage with participating providers. <a href="auth/register.php">Learn More →</a></span></div>
        <div class="kit-benefit-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 7v5c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7l-8-4Z"/><path d="M8 12h8M12 8v8"/></svg><span><strong>PhilHealth YAKAP</strong><br>Access applicable PhilHealth YAKAP services through eligible primary-care providers. <a href="auth/register.php">Learn More →</a></span></div>
      </div>
    </div>
  </section>

  <section class="kit-section kit-app" id="download">
    <div class="container kit-app-grid"><div><div class="kit-eyebrow">Download TELE-CARE App</div><h2>Download TELE-CARE App</h2><p>Get convenient access to healthcare services from your mobile device.</p><div class="kit-store-row"><a class="kit-store-apk" href="downloads/telecare.apk" download><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0-4-4m4 4 4-4M5 21h14"/></svg>Download for Android (APK)</a></div><small style="display:block;margin-top:10px;color:var(--muted);">Android only &middot; iOS coming soon</small></div><div class="kit-phone"><img src="telecarelogo.png" alt="Tele-Care app"/></div></div>
  </section>
</main>


<!-- ══ HERO ══ -->
<div class="hero-wrap">
  <canvas class="hero-canvas" id="heroCanvas"></canvas>
  <div class="hero-bg-grid"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>

  <div class="container">
    <div class="hero">
      <div class="hero-divider"></div>

      <!-- LEFT -->
      <div class="hero-left">
        <div class="live-pill">
          <span class="live-dot"></span>
          Now Accepting Patients
        </div>

        <h1>
          <span class="word"><span>Your&nbsp;</span></span><span class="word"><span>Health,</span></span><br>
          <span class="word"><span><em>Always&nbsp;</em></span></span><span class="word"><span><em>Connected.</em></span></span>
        </h1>

        <p class="hero-desc">
          Book an appointment, consult a licensed doctor via video, upload your prescriptions or lab results for instant digitization, and receive an AI-generated summary of every session — all without leaving home.
        </p>

        <div class="pills">
          <span class="pill p-b">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87V15.13a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
            Video Teleconsultation
          </span>
          <span class="pill p-g">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Prescription Scanning
          </span>
          <span class="pill p-r">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
            Lab Result OCR
          </span>
          <span class="pill p-g">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M2 12h3M19 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>
            AI Consult Summary
          </span>
          <span class="pill p-b">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
            Secure Payments
          </span>
        </div>

        <div class="hero-btns">
          <a href="auth/register.php" class="btn-primary">
            <span>Book a Consultation</span>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </a>
          <a href="#how" class="btn-secondary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8" fill="currentColor" stroke="none"/></svg>
            How It Works
          </a>
        </div>

        <p class="hero-note">
          Registration takes under 5 minutes &mdash; already have an account? <a href="auth/login.php">Log in here</a>
        </p>
      </div>

      <!-- RIGHT -->
      <div class="hero-right">
        <div class="hv-wrap">

          <div class="hv-badge badge-top">
            <div class="hv-badge-icon ib-green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
            <div>
              <div class="hv-badge-label">Today's Consultations</div>
              <div class="hv-badge-val">2 Scheduled</div>
            </div>
          </div>

          <div class="hv-card">
            <div class="hv-card-header">
              <span class="hv-card-title">Your Health Dashboard</span>
              <span class="hv-live"><span class="hv-live-dot"></span>Live</span>
            </div>

            <div class="hv-rows">
              <div class="hv-row hl">
                <div class="hv-row-icon ib-red">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87V15.13a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
                </div>
                <div class="hv-row-info">
                  <div class="hv-row-name">Video Consultation</div>
                  <div class="hv-row-sub">Today &middot; 10:00 AM</div>
                </div>
                <span class="hv-tag t-green">Confirmed</span>
              </div>

              <div class="hv-row">
                <div class="hv-row-icon ib-blue">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                </div>
                <div class="hv-row-info">
                  <div class="hv-row-name">Lab Result Scan</div>
                  <div class="hv-row-sub">CBC uploaded &middot; Processing</div>
                </div>
                <span class="hv-tag t-blue">Scanning</span>
              </div>

              <div class="hv-row">
                <div class="hv-row-icon ib-green">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M2 12h3M19 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>
                </div>
                <div class="hv-row-info">
                  <div class="hv-row-name">AI Consult Summary</div>
                  <div class="hv-row-sub">Apr 1 session &middot; Ready</div>
                </div>
                <span class="hv-tag t-green">Done</span>
              </div>
            </div>

            <a href="auth/register.php" class="hv-cta">
              <div>
                <div class="hv-cta-text">Book a Consultation</div>
                <div class="hv-cta-sub">Register in under 5 minutes</div>
              </div>
              <div class="hv-cta-arrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </div>
            </a>
          </div>

          <div class="hv-badge badge-bot">
            <div class="hv-badge-icon ib-green">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
            </div>
            <div>
              <div class="hv-badge-label">Your records are</div>
              <div class="hv-badge-val">Private &amp; Secure</div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>


<!-- ══ MARQUEE STRIP ══ -->
<div class="marquee-strip">
  <div class="marquee-track" id="marqueeTrack">
    <!-- items duplicated in JS for infinite scroll -->
  </div>
</div>


<!-- ══ FEATURES ══ -->
<section class="features" id="features">
  <div class="container">
    <div class="reveal">
      <span class="stag">Why Choose Us</span>
      <h2 class="stitle">Everything You Need<br>In One Platform</h2>
      <p class="ssub">A focused, integrated system built to make healthcare accessible, efficient, and secure.</p>
    </div>
    <div class="feat-grid">
      <div class="feat-card reveal">
        <div class="feat-icon ib-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <h3>Smart Appointment Booking</h3>
        <p>Calendar-based scheduling that prevents conflicts and double bookings. Pick your doctor and time slot in seconds.</p>
      </div>
      <div class="feat-card reveal">
        <div class="feat-icon ib-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87V15.13a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
        </div>
        <h3>Video Teleconsultation</h3>
        <p>Face-to-face consultations with licensed physicians through secure, high-quality video calls from any device.</p>
      </div>
      <div class="feat-card reveal">
        <div class="feat-icon ib-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M2 12h3M19 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>
        </div>
        <h3>AI Consultation Summary</h3>
        <p>After every session, AI generates a structured summary — key findings, notes, and recommended next steps, instantly.</p>
      </div>
      <div class="feat-card reveal">
        <div class="feat-icon ib-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        </div>
        <h3>Online Payment Processing</h3>
        <p>Pay consultation fees seamlessly online. Safe, fast, and automatically reflected in your account dashboard.</p>
      </div>
      <div class="feat-card reveal">
        <div class="feat-icon ib-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <h3>Prescription &amp; Lab Result Scanning</h3>
        <p>Upload photos of prescriptions or lab results — the data is extracted and digitized automatically.</p>
      </div>
      <div class="feat-card reveal">
        <div class="feat-icon ib-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <h3>Secure Authentication</h3>
        <p>Secure login with role-based access for Patients, Doctors, and Administrators.</p>
      </div>
    </div>
  </div>
</section>


<!-- ══ HOW IT WORKS ══ -->
<section class="how" id="how">
  <div class="container">
    <div style="text-align:center;" class="reveal">
      <span class="stag">Simple Process</span>
      <h2 class="stitle">Get Seen in 4 Easy Steps</h2>
      <p class="ssub" style="margin:0 auto;">From sign-up to consultation in minutes — no waiting rooms, no paperwork.</p>
    </div>
    <div class="steps-row">
      <div class="step reveal">
        <div class="step-num">1</div>
        <h3>Create an Account</h3>
        <p>Create your account in under 5 minutes.</p>
      </div>
      <div class="step reveal">
        <div class="step-num">2</div>
        <h3>Book an Appointment</h3>
        <p>Choose a doctor and pick a date and time that works for you.</p>
      </div>
      <div class="step reveal">
        <div class="step-num">3</div>
        <h3>Pay Securely</h3>
        <p>Complete your payment online, quickly and securely.</p>
      </div>
      <div class="step reveal">
        <div class="step-num">4</div>
        <h3>Start Consulting</h3>
        <p>Join your video call and receive care from a licensed doctor.</p>
      </div>
    </div>
  </div>
</section>


<!-- ══ SERVICES ══ -->
<section class="services" id="services">
  <div class="container">
    <div class="reveal">
      <span class="stag">Our Services</span>
      <h2 class="stitle">What Tele-Care<br>Offers You</h2>
      <p class="ssub">A focused set of tools built for accessible, digital-first healthcare delivery.</p>
    </div>
    <div class="svc-grid">
      <div class="svc-card reveal">
        <div class="svc-icon ib-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87V15.13a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/></svg>
        </div>
        <h3>Video Teleconsultation</h3>
        <p>Connect face-to-face with a licensed doctor via secure video call — from your phone, tablet, or computer.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <h3>Appointment Scheduling</h3>
        <p>Calendar-based booking that lets patients select available slots while preventing conflicts and double bookings.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M2 12h3M19 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>
        </div>
        <h3>AI Consultation Summary</h3>
        <p>After every video session, an AI-generated structured summary is produced — capturing key findings and next steps.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <h3>Prescription Scanning</h3>
        <p>Upload a photo of your prescription and the text is extracted and digitized automatically.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
        </div>
        <h3>Lab Result Digitization</h3>
        <p>Upload scanned lab documents and have key values extracted and stored in your digital health record automatically.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        </div>
        <h3>Online Payment Processing</h3>
        <p>Secure online consultation payments, with automatic status updates in your account dashboard.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <h3>Secure Authentication</h3>
        <p>Secure login with role-based access for Patients, Doctors, and Administrators — your data privacy protected.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        </div>
        <h3>Administrative Dashboard</h3>
        <p>A centralized panel for managing appointments, payments, scanned records, and all system activity securely.</p>
      </div>
    </div>
  </div>
</section>


<!-- ══ CTA ══ -->
<div class="cta-band reveal">
  <div class="cta-orb cta-orb-1"></div>
  <div class="cta-orb cta-orb-2"></div>
  <div class="container">
    <div class="cta-inner">
      <div class="cta-stag">Get Started</div>
      <h2>Ready to See a Doctor <em>Today?</em></h2>
      <p>Experience healthcare without barriers — anytime, anywhere.</p>
      <a href="auth/register.php" class="btn-white">
        <span>Get Started</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <div class="cta-tags">
        <span class="cta-tag"><span class="cta-tag-dot"></span>Teleconsultation</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>OCR</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>Online Payment</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>AI Summary</span>
      </div>
    </div>
  </div>
</div>


<!-- ══ FOOTER ══ -->
<footer>
  <div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;">
      <div>
        <div class="foot-logo"><img class="logo-img" src="telecarelogo.png" alt="Tele-Care logo"/>TELE-CARE</div>
        <p>Your Healthcare, Anywhere.</p>
        <p>Connecting patients with accessible and quality healthcare services online.</p>
      </div>
      <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:.68rem;">
        <a href="index.php" style="color:#c8e4fb;text-decoration:none;">Home</a>
        <a href="#doctors" style="color:#c8e4fb;text-decoration:none;">Find a Doctor</a>
        <a href="#consultation" style="color:#c8e4fb;text-decoration:none;">Quick Consultation</a>
        <a href="<?= $reqHref ?>" data-quick="medcert" style="color:#c8e4fb;text-decoration:none;">Request Med Cert</a>
        <a href="<?= $reqHref ?>" data-quick="refill" style="color:#c8e4fb;text-decoration:none;">Request Refill Prescription</a>
        <a href="#appointments" style="color:#c8e4fb;text-decoration:none;">Appointments</a>
        <a href="#benefits" style="color:#c8e4fb;text-decoration:none;">Insurance &amp; Benefits</a>
        <a href="#download" style="color:#c8e4fb;text-decoration:none;">Download App</a>
        <a href="#faq" style="color:#c8e4fb;text-decoration:none;">FAQ</a>
        <a href="#contact" style="color:#c8e4fb;text-decoration:none;">Contact</a>
        <a href="private_telecare/privacy-policy.php" style="color:#c8e4fb;text-decoration:none;">Privacy Policy</a>
        <a href="auth/termsandpolicy.php" style="color:#c8e4fb;text-decoration:none;">Terms &amp; Conditions</a>
      </div>
    </div>
    <p>&copy; 2026 TELE-CARE. Frontend prototype.</p>
  </div>
</footer>


<script>
/* ── HERO CANVAS PARTICLES ── */
const canvas = document.getElementById('heroCanvas');
const ctx = canvas.getContext('2d');
let W, H, particles = [];

function resize() {
  W = canvas.width  = canvas.offsetWidth;
  H = canvas.height = canvas.offsetHeight;
}
resize();
window.addEventListener('resize', resize);

class Particle {
  constructor() { this.reset(); }
  reset() {
    this.x  = Math.random() * W;
    this.y  = Math.random() * H;
    this.vx = (Math.random() - 0.5) * 0.28;
    this.vy = (Math.random() - 0.5) * 0.28;
    this.r  = Math.random() * 1.6 + 0.4;
    this.o  = Math.random() * 0.25 + 0.05;
    this.c  = Math.random() > 0.5 ? '21,28,39' : '179,17,24';
  }
  update() {
    this.x += this.vx; this.y += this.vy;
    if(this.x<0||this.x>W||this.y<0||this.y>H) this.reset();
  }
  draw() {
    ctx.beginPath();
    ctx.arc(this.x, this.y, this.r, 0, Math.PI*2);
    ctx.fillStyle = `rgba(${this.c},${this.o})`;
    ctx.fill();
  }
}

for(let i=0;i<90;i++) particles.push(new Particle());

function drawLines() {
  for(let i=0;i<particles.length;i++){
    for(let j=i+1;j<particles.length;j++){
      const dx=particles[i].x-particles[j].x, dy=particles[i].y-particles[j].y;
      const dist=Math.sqrt(dx*dx+dy*dy);
      if(dist<110){
        ctx.beginPath();
        ctx.moveTo(particles[i].x,particles[i].y);
        ctx.lineTo(particles[j].x,particles[j].y);
        ctx.strokeStyle=`rgba(21,28,39,${0.045*(1-dist/110)})`;
        ctx.lineWidth=0.5;
        ctx.stroke();
      }
    }
  }
}

function animParticles() {
  ctx.clearRect(0,0,W,H);
  particles.forEach(p=>{ p.update(); p.draw(); });
  drawLines();
  requestAnimationFrame(animParticles);
}
animParticles();

/* ── SCROLL BAR + NAV SHRINK ── */
const scrollBar = document.getElementById('scrollBar');
const nav = document.getElementById('nav');
window.addEventListener('scroll', () => {
  const p = window.scrollY / (document.documentElement.scrollHeight - window.innerHeight);
  scrollBar.style.width = (p * 100) + '%';
  nav.style.boxShadow = window.scrollY > 60
    ? '0 4px 32px rgba(21,28,39,0.1)'
    : '0 1px 30px rgba(21,28,39,0.06)';
}, {passive:true});

/* ── HAMBURGER ── */
const ham = document.getElementById('ham');
const mob = document.getElementById('mobMenu');
ham.addEventListener('click', () => {
  ham.classList.toggle('open');
  mob.classList.toggle('open');
});
mob.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  ham.classList.remove('open'); mob.classList.remove('open');
}));

/* ── MARQUEE BUILD ── */
const marqueeItems = [
  'Video Teleconsultation',
  'Appointment Scheduling',
  'AI Consultation Summary',
  'Prescription Scanning',
  'Lab Result Digitization',
  'Online Payments',
  'Administrative Dashboard',
];
const track = document.getElementById('marqueeTrack');
const buildItems = () => {
  track.innerHTML = '';
  [...marqueeItems, ...marqueeItems].forEach(txt => {
    const el = document.createElement('div');
    el.className = 'marquee-item';
    el.innerHTML = `<span class="marquee-dot"></span>${txt}`;
    track.appendChild(el);
  });
};
buildItems();

/* ── REVEAL ON SCROLL ── */
const obs = new IntersectionObserver(entries => {
  entries.forEach((e, i) => {
    if(e.isIntersecting){
      setTimeout(() => e.target.classList.add('on'), i * 65);
      obs.unobserve(e.target);
    }
  });
}, {threshold: 0.1});
document.querySelectorAll('.reveal, .reveal-left, .reveal-right').forEach(el => obs.observe(el));

/* ── SMOOTH ANCHOR ── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if(t){ e.preventDefault(); t.scrollIntoView({behavior:'smooth', block:'start'}); }
  });
});

/* ── DASHBOARD ROW HOVER ── */
document.querySelectorAll('.hv-row').forEach(r => {
  r.addEventListener('mouseenter', () => {
    document.querySelectorAll('.hv-row').forEach(x => x.classList.remove('hl'));
    r.classList.add('hl');
  });
});

/* ── ORB PARALLAX ── */
document.addEventListener('mousemove', e => {
  const x = (e.clientX / window.innerWidth - 0.5);
  const y = (e.clientY / window.innerHeight - 0.5);
  const o1 = document.querySelector('.orb-1'), o2 = document.querySelector('.orb-2');
  if(o1) o1.style.transform = `translate(${x*28}px,${y*18}px)`;
  if(o2) o2.style.transform = `translate(${x*-18}px,${y*-12}px)`;
});

/* ── FEATURE CARD MOUSE GLOW ── */
document.querySelectorAll('.feat-card').forEach(card => {
  card.addEventListener('mousemove', e => {
    const rect = card.getBoundingClientRect();
    const mx = ((e.clientX - rect.left) / rect.width) * 100;
    const my = ((e.clientY - rect.top)  / rect.height) * 100;
    card.style.setProperty('--mx', mx + '%');
    card.style.setProperty('--my', my + '%');
  });
});

/* ── STAGGER REVEAL DELAY ── */
document.querySelectorAll('.feat-grid .feat-card, .svc-grid .svc-card').forEach((el, i) => {
  el.style.transitionDelay = (i * 55) + 'ms';
});
</script>
<script>
/* ── Doctors list: search + department filter ── */
(function () {
  var grid = document.getElementById('dlGrid');
  if (!grid) return;
  var cards = Array.prototype.slice.call(grid.querySelectorAll('.dl-card'));
  var box = document.getElementById('dlSearch'), hero = document.getElementById('heroSearch'), empty = document.getElementById('dlEmpty');
  var dept = '', q = '';
  function apply() {
    var n = 0;
    cards.forEach(function (c) {
      var ok = (!dept || c.dataset.dept === dept) && (!q || c.dataset.hay.indexOf(q) > -1);
      c.hidden = !ok; if (ok) n++;
    });
    empty.hidden = n > 0;
  }
  function setQ(v, from) { q = (v || '').toLowerCase().trim(); if (box && from !== box) box.value = v; if (hero && from !== hero) hero.value = v; apply(); }
  if (box) box.addEventListener('input', function () { setQ(this.value, this); });
  if (hero) {
    hero.addEventListener('input', function () { setQ(this.value, this); });
    hero.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); setQ(this.value, this); document.getElementById('doctors').scrollIntoView({ behavior: 'smooth' }); } });
  }
  var chips = document.getElementById('dlChips');
  if (chips) chips.addEventListener('click', function (e) {
    var b = e.target.closest('[data-dept]'); if (!b) return;
    chips.querySelectorAll('.dl-chip').forEach(function (x) { x.classList.toggle('on', x === b); });
    dept = b.dataset.dept; apply();
  });
})();
</script>
<script>
/* ── Guest booking (no account) ── */
(function () {
  var root = document.getElementById('gb');
  if (!root) return;
  var CSRF = root.dataset.csrf;
  var $ = function (id) { return document.getElementById(id); };
  var S = { step: 1, concern: '', dept: '', doctor: null, schedules: [], settings: {}, booked: [], now: null, selDate: null, selTime: null, calY: 0, calM: 0, busy: false, resendAt: 0, mailed: false };
  var LABELS = ['Concern', 'Doctor', 'Schedule', 'Details', 'Verify'];
  var DAYS_S = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  var DAYS_L = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function money(n) { return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function api(action, data) {
    var b = new URLSearchParams(Object.assign({ action: action, csrf: CSRF }, data || {}));
    return fetch('guest_booking_api.php', { method: 'POST', body: b, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response. Please try again.' }; }); })
      .catch(function () { return { ok: false, error: 'Connection problem. Please try again.' }; });
  }
  function busy(btn, on, label) {
    if (!btn) return;
    if (on) { btn.dataset.l = btn.textContent; btn.disabled = true; if (label) btn.textContent = label; }
    else { btn.disabled = false; if (btn.dataset.l) btn.textContent = btn.dataset.l; }
  }
  function alertBox(msg, html, warn) {
    var a = $('gbAlert');
    if (!msg) { a.innerHTML = ''; return; }
    a.innerHTML = '<div class="gb-alert' + (warn ? ' warn' : '') + '">' + (html ? msg : esc(msg)) + '</div>';
    a.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function handleFail(r) {
    if (r.logged_in) { location.reload(); return; }
    if (r.expired)   { alertBox('Your session expired. Reloading…'); setTimeout(function () { location.reload(); }, 900); return; }
    if (r.restart)   { go(1); }
    if ((r.back === 'schedule' || r.refresh_schedule) && S.doctor) { loadSchedule(S.doctor.id, true); go(3); }
    if (r.back === 'doctor') { go(2); }
    if (r.registered) { alertBox(esc(r.error) + ' <a href="auth/login.php">Log in</a>', true); return; }
    alertBox(r.error || 'Something went wrong. Please try again.');
  }

  /* ── stepper + panels ── */
  function renderSteps() {
    var h = '';
    LABELS.forEach(function (l, i) {
      var n = i + 1, cls = n < S.step ? 'done' : (n === S.step ? 'on' : '');
      h += '<div class="gb-step ' + cls + '"><div class="gb-dot">' + (n < S.step ? '✓' : n) + '</div><span>' + l + '</span></div>';
      if (n < LABELS.length) h += '<div class="gb-line ' + (n < S.step ? 'done' : '') + '"></div>';
    });
    $('gbSteps').innerHTML = h;
  }
  function go(n) {
    S.step = n;
    for (var i = 1; i <= 5; i++) $('gbP' + i).hidden = (i !== n);
    renderSteps();
    alertBox('');
    if (n === 4 && !$('gbReason').value) $('gbReason').value = S.concern;
    var top = root.getBoundingClientRect().top + window.pageYOffset - 90;
    if (Math.abs(window.pageYOffset - top) > 160) window.scrollTo({ top: top, behavior: 'smooth' });
  }
  root.addEventListener('click', function (e) {
    var b = e.target.closest('[data-back]');
    if (b) go(parseInt(b.dataset.back, 10));
  });

  /* ── quick-text chips: build "I'm requesting A, B and C" without touching what the patient typed ── */
  var QUICK = { medcert: 'a medical certificate', lab: 'lab results', refill: 'a prescription refill' };
  var QORDER = ['medcert', 'lab', 'refill'], qOn = {}, qAuto = '';
  function qSentence() {
    var parts = QORDER.filter(function (k) { return qOn[k]; }).map(function (k) { return QUICK[k]; });
    if (!parts.length) return '';
    var list = parts.length === 1 ? parts[0] : parts.slice(0, -1).join(', ') + ' and ' + parts[parts.length - 1];
    return "I'm requesting " + list + '.';
  }
  function qApply() {
    var ta = $('gbConcern'), now = qSentence(), v = ta.value, i = qAuto ? v.indexOf(qAuto) : -1;
    if (i > -1) v = (v.slice(0, i) + now + v.slice(i + qAuto.length)).replace(/\s{2,}/g, ' ').trim();   // update our sentence, keep the rest
    else if (now) v = v.trim() ? now + ' ' + v.trim() : now;                                         // patient typed first: put ours in front
    qAuto = now;
    ta.value = v.slice(0, 500);
    $('gbCount').textContent = ta.value.length;
    document.querySelectorAll('#gbQuick [data-q]').forEach(function (b) { b.classList.toggle('on', !!qOn[b.dataset.q]); });
  }
  $('gbQuick').addEventListener('click', function (e) {
    var b = e.target.closest('[data-q]'); if (!b) return;
    qOn[b.dataset.q] = !qOn[b.dataset.q]; qApply(); $('gbConcern').focus();
  });
  document.addEventListener('click', function (e) {   // hero / footer / service-card links: switch the chip on
    var a = e.target.closest('a[data-quick]');
    if (a && a.getAttribute('href') === '#book') { qOn[a.dataset.quick] = true; qApply(); }
  });

  /* ── 1 · concern ── */

  /* Rejects gibberish concerns ("ahsdawdjask", "asdf", "aaaa") before anything is sent. Mirrors booking_concern_error() in PHP. */
  var OK_CC = {}; ('bl br ch ck cl cr ct dr dg ff fl fr gh gl gr gn kn ld lf lk lm lp lt lb lc lv ly mb mp mn ms nd ng nk nt ns nc nj nf nv nl ph pl pr ps pt rb rc rd rf rg rk rl rm rn rp rs rt rv rw rh sc sh sk sl sm sn sp st sw sq th tr ts tw wh wr wl xp xc ft bt dm dv bd gm pn tl kt gk mg tc cc bb dd gg ll mm nn pp rr ss tt zz kl kb kw dl ny cy cs').split(' ').forEach(function (k) { OK_CC[k] = 1; });
  function concernLooksFake(text) {
    var t = (text || '').toLowerCase().trim(), flat = t.replace(/\s+/g, '');
    if (!flat) return true;
    var letters = (flat.match(/\p{L}/gu) || []).length;
    if (letters < 3 || letters / flat.length < 0.6) return true;
    if (/^(.{1,3})\1{3,}$/u.test(flat)) return true;
    var words = t.match(/\p{L}+/gu) || [], shortOk = ['ng','sa','ko','ay','na','at','ba','po','mo','ka','ta','ha','di'],
        allow = ['strengths','lengths','twelfths','strength'], V = /[aeiouyáéíóúàèìòùäëïöü]/u, judged = 0, bad = 0;
    words.forEach(function (w) {
      var len = Array.from(w).length;
      if (len < 3 || shortOk.indexOf(w) > -1) return;
      judged++;
      if (allow.indexOf(w) > -1) return;
      var vow = (w.match(/[aeiouyáéíóúàèìòùäëïöü]/gu) || []).length, isBad = false;
      if (len >= 4 && vow === 0) isBad = true;
      if (!isBad && len >= 7 && vow / len < 0.25) isBad = true;
      if (!isBad && /[^aeiouyáéíóúàèìòùäëïöü]{5,}/u.test(w)) isBad = true;
      if (!isBad && /(.)\1{3,}/u.test(w)) isBad = true;
      if (!isBad && /^(.{1,3})\1{2,}$/u.test(w)) isBad = true;
      if (!isBad) {
        var ch = Array.from(w), pairs = 0;
        for (var i = 0; i < ch.length - 1; i++) {
          if (V.test(ch[i]) || V.test(ch[i + 1]) || ch[i] === ch[i + 1]) continue;
          if (ch[i + 1] === 's' && i + 1 === ch.length - 1) continue;
          if (!OK_CC[ch[i] + ch[i + 1]]) pairs++;
        }
        if (pairs >= 2) isBad = true;
      }
      if (!isBad && /^(ha|he|hi|ho|ja|ji|je|xd|lol|wk|kek)+$/.test(w)) isBad = true;
      if (!isBad && /asdf|sdfg|dfgh|fghj|ghjk|hjkl|qwer|wert|erty|rtyu|tyui|yuio|uiop|zxcv|xcvb|cvbn|vbnm|asd[fg]|kjh|lkj/.test(w)) isBad = true;
      if (isBad) bad++;
    });
    return judged === 0 || bad / judged >= 0.5;
  }
  $('gbConcern').addEventListener('input', function () { $('gbCount').textContent = this.value.length; });
  $('gbFind').addEventListener('click', function () {
    var v = $('gbConcern').value.trim(), btn = this;
    if (v.length < 10) { alertBox('Please describe how you feel in a little more detail (at least 10 characters).'); return; }
    if (concernLooksFake(v)) { alertBox('Please describe your health concern in real words (for example: "dry cough and fever for 3 days").'); return; }
    alertBox(''); busy(btn, true, 'Finding the right doctor…');
    api('recommend', { concern: v }).then(function (r) {
      busy(btn, false);
      if (!r.ok) { handleFail(r); return; }
      S.concern = r.concern; S.dept = r.department; S.doctor = null; S.selDate = S.selTime = null; S.urgent = r.urgent; S.reason = r.reason; S.source = r.source;
      renderDoctors(r);
      go(2);
    });
  });

  /* ── 2 · doctors ── */
  function stars(r, c) {
    if (!c) return '<span class="gb-stars" style="color:var(--muted);font-style:italic">No ratings yet</span>';
    var f = Math.floor(r), half = (r - f) >= .5 ? 1 : 0;
    return '<span class="gb-stars">' + '★'.repeat(f) + (half ? '½' : '') + '☆'.repeat(5 - f - half) + ' ' + r.toFixed(1) + ' (' + c + ')</span>';
  }
  function avatar(d) {
    var ini = (d.name || '?').trim().split(/\s+/).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
    return d.photo ? '<img src="' + esc(encodeURI(d.photo)) + '" alt="" onerror="this.remove()">' + esc(ini) : esc(ini);
  }
  function renderDoctors(r) {
    $('gbUrgent').innerHTML = r.urgent ? '<div class="gb-alert"><b>Some symptoms you described can be serious.</b> If they are severe, sudden, or getting worse, do not wait for an appointment. Call <b>911</b> or go to the nearest emergency room.</div>' : '';
    var suggested = r.department === r.suggested;
    $('gbRecLabel').textContent = suggested ? 'Suggested department' : 'Selected department';
    $('gbRecDept').textContent = r.department;
    $('gbRecReason').textContent = suggested ? (r.reason || 'Based on the words in your description.') : 'You chose this instead of our suggestion (' + r.suggested + ').';
    $('gbRecSrc').textContent = suggested ? ((r.source === 'ai' ? 'Suggested by our AI assistant' : 'Suggested from your keywords') + ' · this is not a diagnosis.') : '';
    var chips = '';
    (r.departments || []).forEach(function (d) { if (d !== r.department) chips += '<button type="button" class="gb-chip" data-dept="' + esc(d) + '">' + esc(d) + '</button>'; });
    $('gbChips').innerHTML = chips ? '<span>Another department:</span>' + chips : '';
    var h = '';
    if (!r.doctors.length) h = '<div class="gb-center" style="grid-column:1/-1"><p>No doctors are listed under this department right now.</p></div>';
    r.doctors.forEach(function (d) {
      h += '<div class="gb-doc">' + (d.recommended ? '<span class="gb-badge">Recommended</span>' : '')
        + '<div class="gb-doc-top"><div class="gb-av">' + avatar(d) + '</div><div><b>Dr. ' + esc(d.name) + '</b><small>' + esc(d.specialty || r.department) + (d.subspecialty ? ' · ' + esc(d.subspecialty) : '') + '</small>' + stars(d.rating, d.rating_count) + '</div></div>'
        + '<div class="gb-fee">' + money(d.fee) + '</div>'
        + '<button type="button" class="gb-btn primary" data-doc="' + d.id + '">Select Doctor</button></div>';
    });
    $('gbDocs').innerHTML = h;
  }
  $('gbP2').addEventListener('click', function (e) {
    var c = e.target.closest('[data-dept]'), d = e.target.closest('[data-doc]');
    if (c) {
      api('doctors', { department: c.dataset.dept }).then(function (r) {
        if (!r.ok) { handleFail(r); return; }
        S.dept = r.department; r.concern = S.concern; r.reason = S.reason; r.urgent = S.urgent; r.source = S.source; r.departments = null;
        var chips = c.parentNode, all = [];
        chips.querySelectorAll('[data-dept]').forEach(function (x) { all.push(x.textContent); });
        all.push($('gbRecDept').textContent);
        r.departments = all;
        renderDoctors(r);
      });
    }
    if (d) {
      var btn = d; busy(btn, true, 'Loading…');
      loadSchedule(parseInt(d.dataset.doc, 10)).then(function (ok) { busy(btn, false); if (ok) go(3); });
    }
  });

  /* ── 3 · schedule ── */
  function toMin(t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1] || 0); }
  function fmt12(t) { var p = t.split(':').map(Number), ap = p[0] >= 12 ? 'PM' : 'AM', h = p[0] % 12 || 12; return h + ':' + String(p[1] || 0).padStart(2, '0') + ' ' + ap; }
  function loadSchedule(id, keepSel) {
    return api('schedule', { doctor_id: id }).then(function (r) {
      if (!r.ok) { handleFail(r); return false; }
      S.doctor = r.doctor; S.schedules = r.schedules; S.settings = r.settings; S.booked = r.booked; S.now = r.now; S.dept = r.department;
      if (!keepSel) { S.selDate = S.selTime = null; }
      var p = S.now.date.split('-'); S.calY = +p[0]; S.calM = +p[1] - 1;
      $('gbSAv').innerHTML = avatar(r.doctor);
      $('gbSName').textContent = 'Dr. ' + r.doctor.name;
      $('gbSSpec').textContent = r.doctor.specialty || '';
      $('gbSDept').textContent = r.department;
      $('gbSDoc').textContent = 'Dr. ' + r.doctor.name + ' · ' + money(r.doctor.fee);
      renderCal(); renderTimes(); sumUpdate();
      return true;
    });
  }
  function slotsFor(s) {
    var out = [], step = Number(S.settings.appointment_interval) || 30, m = toMin(s.start_time.slice(0, 5)), end = toMin(s.end_time.slice(0, 5));
    for (; m < end; m += step) out.push(String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'));
    return out;
  }
  function isOpen(dayName, t, date) {
    var DUR = Number(S.settings.consultation_duration) || 60, m = toMin(t), nowM = S.now.mins;
    if (date === S.now.date && m <= nowM) return false;
    var sched = S.schedules.some(function (s) { return s.day_of_week === dayName && m >= toMin(s.start_time.slice(0, 5)) && m + DUR <= toMin(s.end_time.slice(0, 5)); });
    if (!sched) return false;
    var bs = S.settings.break_start ? toMin(S.settings.break_start.slice(0, 5)) : null, be = S.settings.break_end ? toMin(S.settings.break_end.slice(0, 5)) : null;
    if (bs !== null && be !== null && m < be && m + DUR > bs) return false;
    return !S.booked.some(function (b) { if (b.appointment_date !== date) return false; var bm = toMin(b.appointment_time.slice(0, 5)); return m < bm + DUR && m + DUR > bm; });
  }
  function dayHasOpen(dayName, date) {
    return S.schedules.filter(function (s) { return s.day_of_week === dayName; }).some(function (s) { return slotsFor(s).some(function (t) { return isOpen(dayName, t, date); }); });
  }
  function renderCal() {
    var first = new Date(S.calY, S.calM, 1).getDay(), dim = new Date(S.calY, S.calM + 1, 0).getDate();
    $('gbMonth').textContent = MONTHS[S.calM] + ' ' + S.calY;
    var np = S.now.date.split('-');
    $('gbPrev').disabled = (S.calY === +np[0] && S.calM === +np[1] - 1);
    var h = DAYS_S.map(function (d) { return '<div class="gb-dn">' + d + '</div>'; }).join('');
    for (var i = 0; i < first; i++) h += '<div class="gb-c"></div>';
    for (var d = 1; d <= dim; d++) {
      var ds = S.calY + '-' + String(S.calM + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      var dn = DAYS_L[new Date(S.calY, S.calM, d).getDay()];
      var past = ds < S.now.date, inSched = S.schedules.some(function (s) { return s.day_of_week === dn; });
      var open = !past && inSched && dayHasOpen(dn, ds);
      var cls = 'gb-c' + (past ? ' past' : (!inSched ? ' blocked' : (open ? ' av' : ' past'))) + (ds === S.now.date ? ' today' : '') + (S.selDate === ds && open ? ' sel' : '');
      h += '<div class="' + cls + '"' + (open ? ' data-date="' + ds + '"' : '') + '>' + d + '</div>';
    }
    $('gbCal').innerHTML = h;
  }
  function renderTimes() {
    var g = $('gbTimes');
    if (!S.selDate) { g.innerHTML = '<div style="grid-column:1/-1;color:var(--muted);font-size:.74rem">Select a date to view times.</div>'; return; }
    var dn = DAYS_L[new Date(S.selDate + 'T00:00:00').getDay()], seen = {}, all = [];
    S.schedules.filter(function (s) { return s.day_of_week === dn; }).forEach(function (s) { slotsFor(s).forEach(function (t) { if (!seen[t]) { seen[t] = 1; all.push(t); } }); });
    all.sort();
    var h = '', any = false;
    all.forEach(function (t) {
      var ok = isOpen(dn, t, S.selDate); if (ok) any = true;
      h += '<div class="gb-t' + (ok ? '' : ' off') + (S.selTime === t ? ' sel' : '') + '"' + (ok ? ' data-time="' + t + '"' : '') + '>' + fmt12(t) + '</div>';
    });
    g.innerHTML = any ? h : '<div style="grid-column:1/-1;color:var(--muted);font-size:.74rem">No times available for this date.</div>';
  }
  function sumUpdate() {
    $('gbSWhen').textContent = S.selDate ? (S.selDate + (S.selTime ? ' at ' + fmt12(S.selTime) : '')) : 'Pending selection';
    $('gbSlotGo').disabled = !(S.selDate && S.selTime);
  }
  $('gbPrev').addEventListener('click', function () { S.calM--; if (S.calM < 0) { S.calM = 11; S.calY--; } renderCal(); });
  $('gbNext').addEventListener('click', function () { S.calM++; if (S.calM > 11) { S.calM = 0; S.calY++; } renderCal(); });
  $('gbCal').addEventListener('click', function (e) { var c = e.target.closest('[data-date]'); if (!c) return; S.selDate = c.dataset.date; S.selTime = null; renderCal(); renderTimes(); sumUpdate(); });
  $('gbTimes').addEventListener('click', function (e) { var t = e.target.closest('[data-time]'); if (!t) return; S.selTime = t.dataset.time; renderTimes(); sumUpdate(); });
  $('gbSlotGo').addEventListener('click', function () {
    var btn = this; busy(btn, true, 'Checking…');
    api('select_slot', { date: S.selDate, time: S.selTime }).then(function (r) {
      busy(btn, false);
      if (!r.ok) { if (r.refresh_schedule) { S.selTime = null; loadSchedule(S.doctor.id, true); } handleFail(r); return; }
      go(4);
    });
  });

  /* ── 4 · patient details ── */
  var form = $('gbForm');
  $('gbDob').addEventListener('input', function () {
    var d = this.value.replace(/\D/g, '').slice(0, 8), o = d;
    if (d.length > 2) o = d.slice(0, 2) + '/' + d.slice(2);
    if (d.length > 4) o = d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4);
    this.value = o;
  });
  $('gbMob').addEventListener('input', function () {
    var d = this.value.replace(/\D/g, '');
    if (d.slice(0, 2) === '63') d = d.slice(2);
    if (d[0] === '0') d = d.slice(1);
    d = d.slice(0, 10);
    this.value = d.length > 6 ? d.slice(0, 3) + ' ' + d.slice(3, 6) + ' ' + d.slice(6) : (d.length > 3 ? d.slice(0, 3) + ' ' + d.slice(3) : d);
  });
  function fieldErrors(f) {
    form.querySelectorAll('.gb-fe').forEach(function (n) { n.textContent = ''; });
    form.querySelectorAll('.bad').forEach(function (n) { n.classList.remove('bad'); });
    Object.keys(f || {}).forEach(function (k) {
      var n = form.querySelector('[data-e="' + k + '"]'); if (n) n.textContent = f[k];
      var i = form.querySelector('[name="' + k + '"]'); if (i && i.type !== 'radio' && i.type !== 'checkbox') i.classList.add('bad');
    });
    var first = form.querySelector('.gb-fe:not(:empty)');
    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(form), data = {}; fd.forEach(function (v, k) { data[k] = v; });
    var errs = {};
    if ((data.first_name || '').trim().length < 2) errs.first_name = 'Enter your first name.';
    if ((data.last_name || '').trim().length < 2) errs.last_name = 'Enter your last name.';
    if (!/^\d{2}\/\d{2}\/\d{4}$/.test(data.dob || '')) errs.dob = 'Enter your date of birth as MM/DD/YYYY.';
    if (!data.sex) errs.sex = 'Select your sex.';
    if ((data.address || '').trim().length < 5) errs.address = 'Enter your address.';
    if ((data.mobile || '').replace(/\D/g, '').length !== 10) errs.mobile = 'Enter a valid mobile number.';
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(data.email || '')) errs.email = 'Enter a valid email address.';
    if ((data.reason || '').trim().length < 10) errs.reason = 'Describe your reason for booking.';
    if (!data.accept) errs.accept = 'Please accept the Terms & Conditions and Privacy Policy.';
    fieldErrors(errs);
    if (Object.keys(errs).length) return;
    alertBox('');
    var btn = $('gbSubmit'); busy(btn, true, 'Sending code…');
    api('submit_details', data).then(function (r) {
      busy(btn, false);
      if (!r.ok) { if (r.fields) fieldErrors(r.fields); handleFail(r); return; }
      if (r.already_verified) { finish({ skip: 1 }, btn); return; }
      showVerify(r);
    });
  });

  /* ── 5 · e-mail code ── */
  function showVerify(r) {
    $('gbMail').textContent = r.email; $('gbCode').value = ''; $('gbVerify').disabled = true;
    S.resendAt = Date.now() + (r.resend_in || 45) * 1000; tick(); go(5); $('gbCode').focus();
  }
  function tick() {
    var left = Math.ceil((S.resendAt - Date.now()) / 1000), b = $('gbResend');
    if (left > 0) { b.disabled = true; b.textContent = 'Resend code (' + left + 's)'; setTimeout(tick, 500); }
    else { b.disabled = false; b.textContent = 'Resend code'; }
  }
  $('gbCode').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    $('gbVerify').disabled = this.value.length !== 6;
    if (this.value.length === 6) $('gbVerify').click();
  });
  $('gbClear').addEventListener('click', function () { $('gbCode').value = ''; $('gbVerify').disabled = true; $('gbCode').focus(); });
  $('gbResend').addEventListener('click', function () {
    var b = this; b.disabled = true;
    api('resend_otp').then(function (r) {
      if (!r.ok) { alertBox(r.error || 'Could not resend the code.'); if (r.wait) { S.resendAt = Date.now() + r.wait * 1000; tick(); } else { b.disabled = false; } return; }
      alertBox(''); S.resendAt = Date.now() + (r.resend_in || 45) * 1000; tick(); $('gbCode').value = ''; $('gbCode').focus();
    });
  });
  function finish(extra, btn, done) {
    busy(btn, true, 'Verifying…');
    api('verify_otp', extra).then(function (r) {
      if (!r.ok) {
        if (done) done();
        busy(btn, false);
        if (r.verified) { S.mailed = true; }
        handleFail(r);
        if (r.bad_code) { $('gbCode').value = ''; $('gbVerify').disabled = true; $('gbCode').focus(); }
        return;
      }
      btn.textContent = 'Redirecting…';
      location.href = r.redirect;
    });
  }
  var verifying = false;
  $('gbVerify').addEventListener('click', function () {
    if (verifying) return; verifying = true;
    var btn = this, code = $('gbCode').value;
    finish({ code: code }, btn, function () { verifying = false; });
  });

  renderSteps();
})();
</script>
</body>
</html>