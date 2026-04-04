<?php
$pageTitle = "TELE-CARE | Your Health, Connected";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $pageTitle ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400;1,700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --red: #C33643;
      --red-dark: #8a1f2a;
      --green: #244441;
      --green-light: #2e5550;
      --blue: #3F82E3;
      --cream: #fafaf8;
      --line: rgba(36,68,65,0.1);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'DM Sans', sans-serif;
      background: #fff; color: var(--green);
      overflow-x: hidden; cursor: none;
    }

    /* ── CONTAINER ── */
    .container {
      width: 100%;
      max-width: 1240px;
      margin: 0 auto;
      padding: 0 48px;
    }

    /* ── CURSOR ── */
    .cursor {
      width: 9px; height: 9px; background: var(--red); border-radius: 50%;
      position: fixed; pointer-events: none; z-index: 9999;
      transform: translate(-50%,-50%); transition: transform 0.1s;
    }
    .cursor-ring {
      width: 34px; height: 34px;
      border: 1.5px solid rgba(195,54,67,0.35); border-radius: 50%;
      position: fixed; pointer-events: none; z-index: 9998;
      transform: translate(-50%,-50%);
      transition: width 0.3s, height 0.3s, border-color 0.3s, background 0.3s;
    }
    body:has(a:hover) .cursor-ring,
    body:has(button:hover) .cursor-ring {
      width: 52px; height: 52px; border-color: rgba(195,54,67,0.55);
      background: rgba(195,54,67,0.06);
    }
    body:has(a:hover) .cursor { transform: translate(-50%,-50%) scale(0.4); }

    /* ── SCROLL PROGRESS ── */
    .scroll-bar {
      position: fixed; top: 0; left: 0; height: 2px;
      background: linear-gradient(90deg, var(--red), var(--green));
      z-index: 9999; width: 0%;
    }

    h1, h2, h3 { font-family: 'Playfair Display', serif; }

    /* ── NAV ── */
    nav {
      position: fixed; top: 0; left: 0; right: 0; z-index: 200;
      background: rgba(255,255,255,0.96);
      backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--line);
      box-shadow: 0 1px 30px rgba(36,68,65,0.06);
      animation: navIn 0.8s cubic-bezier(0.16,1,0.3,1) both;
      transition: padding 0.3s;
    }
    .nav-inner {
      max-width: 1240px; margin: 0 auto; padding: 0 48px;
      display: flex; align-items: center; justify-content: space-between;
      height: 68px;
    }
    @keyframes navIn {
      from { transform: translateY(-100%); opacity: 0; }
      to   { transform: translateY(0); opacity: 1; }
    }
    .nav-brand { display: flex; flex-direction: column; gap: 0.06rem; }
    .logo {
      font-family: 'Playfair Display', serif;
      font-size: 1.5rem; font-weight: 900;
      color: var(--green); letter-spacing: 0.04em; line-height: 1;
    }
    .logo span { color: var(--red); }
    .logo-sub {
      font-family: 'DM Mono', monospace;
      font-size: 0.5rem; letter-spacing: 0.15em; text-transform: uppercase;
      color: rgba(36,68,65,0.4); line-height: 1;
    }
    .nav-links { display: flex; gap: 2rem; list-style: none; align-items: center; }
    .nav-links a {
      color: rgba(36,68,65,0.6); font-size: 0.87rem; font-weight: 500;
      text-decoration: none; position: relative; transition: color 0.2s;
    }
    .nav-links a::after {
      content: ''; position: absolute; bottom: -3px; left: 0;
      width: 0; height: 1.5px; background: var(--red);
      transition: width 0.3s cubic-bezier(0.16,1,0.3,1);
    }
    .nav-links a:hover { color: var(--green); }
    .nav-links a:hover::after { width: 100%; }
    .nav-login {
      color: var(--green) !important; font-size: 0.8rem !important; font-weight: 600 !important;
      padding: 0.48rem 1.05rem; border: 1.5px solid rgba(36,68,65,0.22) !important; border-radius: 50px;
      background: transparent; transition: all 0.25s !important; white-space: nowrap;
    }
    .nav-login:hover { background: var(--green) !important; color: #fff !important; border-color: var(--green) !important; }
    .nav-login::after { display: none !important; }
    .nav-cta {
      background: var(--red) !important; color: #fff !important;
      padding: 0.48rem 1.25rem !important; border-radius: 50px;
      font-weight: 600 !important; font-size: 0.8rem !important;
      box-shadow: 0 4px 14px rgba(195,54,67,0.28);
      transition: background 0.25s, transform 0.2s, box-shadow 0.25s !important;
    }
    .nav-cta:hover { background: var(--red-dark) !important; transform: translateY(-2px) !important; box-shadow: 0 8px 24px rgba(195,54,67,0.38) !important; }
    .nav-cta::after { display: none !important; }
    .nav-divider { width: 1px; height: 18px; background: rgba(36,68,65,0.14); }

    .hamburger { display: none; flex-direction: column; gap: 0.28rem; cursor: none; }
    .hamburger span { width: 22px; height: 2px; background: var(--green); border-radius: 2px; transition: all 0.3s; }
    .hamburger.open span:nth-child(1) { transform: rotate(45deg) translate(5.5px,5.5px); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: rotate(-45deg) translate(5.5px,-5.5px); }
    .mob-menu {
      display: none; position: absolute; top: 68px; left: 0; right: 0;
      background: rgba(255,255,255,0.99); backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--line); flex-direction: column;
      padding: 1.1rem 4%; gap: 0.7rem;
      box-shadow: 0 12px 40px rgba(36,68,65,0.1); z-index: 199;
    }
    .mob-menu.open { display: flex; }
    .mob-menu a {
      color: rgba(36,68,65,0.72); font-size: 0.9rem; font-weight: 500;
      text-decoration: none; padding: 0.6rem 0;
      border-bottom: 1px solid rgba(36,68,65,0.05); transition: color 0.2s;
    }
    .mob-menu a:last-child { border-bottom: none; }
    .mob-menu a:hover { color: var(--green); }
    .mob-menu .nav-login { border: 1.5px solid var(--green) !important; text-align: center; margin: 0.25rem 0; display: block; }
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
        linear-gradient(rgba(36,68,65,0.027) 1px, transparent 1px),
        linear-gradient(90deg, rgba(36,68,65,0.027) 1px, transparent 1px);
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
      background: radial-gradient(circle, rgba(195,54,67,0.07) 0%, transparent 70%);
      animation: orbDrift 9s ease-in-out infinite; z-index: 1;
    }
    .orb-2 {
      width: 380px; height: 380px; bottom: 60px; left: 20%;
      background: radial-gradient(circle, rgba(36,68,65,0.055) 0%, transparent 70%);
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
      background: linear-gradient(to bottom, transparent, rgba(36,68,65,0.08) 25%, rgba(195,54,67,0.12) 70%, transparent);
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
      background: rgba(36,68,65,0.06); border: 1px solid rgba(36,68,65,0.12);
      color: var(--green); font-size: 0.7rem; font-weight: 700;
      letter-spacing: 0.12em; text-transform: uppercase;
      padding: 0.36rem 0.85rem; border-radius: 50px; margin-bottom: 1.8rem;
    }
    .live-dot {
      width: 6px; height: 6px; border-radius: 50%; background: #22c55e;
      box-shadow: 0 0 0 0 rgba(34,197,94,0.6);
      animation: livepulse 2s infinite;
    }
    @keyframes livepulse {
      0%  { box-shadow: 0 0 0 0 rgba(34,197,94,0.6); }
      70% { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
      100%{ box-shadow: 0 0 0 0 rgba(34,197,94,0); }
    }
    .hero h1 {
      font-size: clamp(2.8rem, 4.2vw, 4.6rem);
      font-weight: 900; line-height: 1.07;
      color: var(--green); margin-bottom: 1.4rem;
      letter-spacing: -0.02em;
    }
    .hero h1 em { color: var(--red); font-style: italic; }
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
      font-size: 1.02rem; color: rgba(36,68,65,0.58);
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
      font-size: 0.73rem; font-weight: 600; color: rgba(36,68,65,0.65);
      transition: all 0.22s;
    }
    .pill:hover { background: #fff; border-color: rgba(36,68,65,0.2); color: var(--green); transform: translateY(-2px); box-shadow: 0 4px 14px rgba(36,68,65,0.08); }
    .pill svg { width: 12px; height: 12px; flex-shrink: 0; }
    .p-r { color: var(--red); }
    .p-g { color: var(--green); }
    .p-b { color: var(--blue); }
    .hero-btns {
      display: flex; gap: 0.9rem; flex-wrap: wrap;
      margin-bottom: 1.8rem;
      animation: fadeUp 0.9s 0.94s both;
    }
    .btn-primary {
      background: var(--red); color: #fff;
      padding: 0.88rem 2.1rem; border-radius: 50px;
      font-weight: 600; font-size: 0.92rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem;
      box-shadow: 0 8px 28px rgba(195,54,67,0.26);
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
    .btn-primary:hover { background: var(--red-dark); transform: translateY(-3px); box-shadow: 0 16px 42px rgba(195,54,67,0.36); }
    .btn-primary svg { transition: transform 0.28s; position: relative; z-index: 1; }
    .btn-primary:hover svg { transform: translateX(4px); }
    .btn-primary span { position: relative; z-index: 1; }
    .btn-secondary {
      background: transparent; color: var(--green);
      padding: 0.88rem 2.1rem; border-radius: 50px;
      font-weight: 500; font-size: 0.92rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 0.45rem;
      border: 1.5px solid rgba(36,68,65,0.18);
      transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
    }
    .btn-secondary:hover { border-color: var(--green); background: rgba(36,68,65,0.05); transform: translateY(-3px); }
    .hero-note {
      font-size: 0.8rem; color: rgba(36,68,65,0.42);
      animation: fadeUp 0.9s 1.02s both;
    }
    .hero-note a { color: var(--green); font-weight: 600; text-underline-offset: 3px; text-decoration: underline; }

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
      background: #fff; border: 1px solid rgba(36,68,65,0.1);
      border-radius: 14px; padding: 0.7rem 1rem;
      box-shadow: 0 6px 22px rgba(36,68,65,0.08);
      width: fit-content;
    }
    .hv-badge-icon {
      width: 34px; height: 34px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .hv-badge-icon svg { width: 17px; height: 17px; }
    .ib-red   { background: rgba(195,54,67,0.1);  color: var(--red); }
    .ib-green { background: rgba(36,68,65,0.09);  color: var(--green); }
    .ib-blue  { background: rgba(63,130,227,0.1); color: var(--blue); }
    .hv-badge-label { font-size: 0.61rem; color: rgba(36,68,65,0.42); font-weight: 500; margin-bottom: 0.06rem; }
    .hv-badge-val   { font-size: 0.83rem; font-weight: 800; color: var(--green); font-family: 'DM Sans', sans-serif; }
    .badge-top { animation: bob 4s ease-in-out infinite; align-self: flex-end; }
    .badge-bot { animation: bob 5s ease-in-out infinite 1.2s; }
    @keyframes bob {
      0%,100% { transform: translateY(0); }
      50%      { transform: translateY(-7px); }
    }

    /* Main card */
    .hv-card {
      background: #fff; border-radius: 22px; padding: 1.8rem;
      border: 1px solid rgba(36,68,65,0.1);
      box-shadow: 0 18px 70px rgba(36,68,65,0.10), 0 4px 18px rgba(36,68,65,0.05);
      position: relative; overflow: hidden;
    }
    .hv-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2.5px;
      background: linear-gradient(90deg, var(--red), var(--green));
    }
    .hv-card-header {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1.3rem;
    }
    .hv-card-title {
      font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em;
      text-transform: uppercase; color: rgba(36,68,65,0.48);
    }
    .hv-live {
      display: flex; align-items: center; gap: 0.35rem;
      font-size: 0.68rem; font-weight: 700; color: #16a34a;
    }
    .hv-live-dot { width: 5px; height: 5px; border-radius: 50%; background: #22c55e; animation: livepulse 2s infinite; }

    /* Rows */
    .hv-rows { display: flex; flex-direction: column; gap: 0.65rem; margin-bottom: 1.3rem; }
    .hv-row {
      display: flex; align-items: center; gap: 0.85rem;
      padding: 0.78rem 0.9rem; border-radius: 12px;
      background: var(--cream); border: 1px solid transparent;
      transition: all 0.28s;
    }
    .hv-row:hover { background: #fff; border-color: rgba(36,68,65,0.1); transform: translateX(4px); }
    .hv-row.hl { background: rgba(195,54,67,0.05); border-color: rgba(195,54,67,0.14); }
    .hv-row-icon {
      width: 36px; height: 36px; border-radius: 9px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .hv-row-icon svg { width: 17px; height: 17px; }
    .hv-row-name { font-size: 0.83rem; font-weight: 700; color: var(--green); margin-bottom: 0.05rem; font-family: 'DM Sans', sans-serif; }
    .hv-row-sub  { font-size: 0.7rem; color: rgba(36,68,65,0.42); font-family: 'DM Sans', sans-serif; }
    .hv-row-info { flex: 1; min-width: 0; }
    .hv-tag {
      font-size: 0.62rem; font-weight: 700; letter-spacing: 0.07em;
      text-transform: uppercase; padding: 0.2rem 0.55rem; border-radius: 50px;
      white-space: nowrap; flex-shrink: 0; font-family: 'DM Sans', sans-serif;
    }
    .t-green { background: rgba(34,197,94,0.12); color: #16a34a; }
    .t-blue  { background: rgba(63,130,227,0.1);  color: var(--blue); }
    .t-red   { background: rgba(195,54,67,0.1);   color: var(--red); }

    /* Card CTA */
    .hv-cta {
      background: var(--green); border-radius: 12px; padding: 0.9rem 1.1rem;
      display: flex; align-items: center; justify-content: space-between;
      text-decoration: none; transition: all 0.28s; position: relative; overflow: hidden;
    }
    .hv-cta::after {
      content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.07), transparent);
      transition: left 0.5s;
    }
    .hv-cta:hover::after { left: 100%; }
    .hv-cta:hover { background: var(--green-light); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(36,68,65,0.2); }
    .hv-cta-text { font-size: 0.85rem; font-weight: 700; color: #fff; font-family: 'DM Sans', sans-serif; }
    .hv-cta-sub  { font-size: 0.68rem; color: rgba(255,255,255,0.55); margin-top: 0.07rem; font-family: 'DM Sans', sans-serif; }
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
      background: var(--green); padding: 0; overflow: hidden;
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
      font-weight: 800; color: var(--green); line-height: 1.13;
      margin-bottom: 1rem; letter-spacing: -0.01em;
    }
    .ssub { color: #5a7a77; font-size: 1rem; line-height: 1.8; max-width: 510px; font-family: 'DM Sans', sans-serif; }

    /* ══ FEATURES ══ */
    .features { background: var(--cream); }
    .feat-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 1.4rem; margin-top: 3.8rem;
    }
    .feat-card {
      background: #fff; border-radius: 20px; padding: 2rem;
      border: 1px solid rgba(36,68,65,0.07);
      transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
      position: relative; overflow: hidden; cursor: default;
    }
    .feat-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, var(--red), var(--green));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.4s cubic-bezier(0.16,1,0.3,1);
    }
    .feat-card::after {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(195,54,67,0.035) 0%, transparent 65%);
      opacity: 0; transition: opacity 0.4s;
    }
    .feat-card:hover { transform: translateY(-9px); box-shadow: 0 26px 65px rgba(36,68,65,0.11); }
    .feat-card:hover::before { transform: scaleX(1); }
    .feat-card:hover::after { opacity: 1; }
    .feat-icon {
      width: 50px; height: 50px; border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1.3rem; transition: transform 0.4s cubic-bezier(0.16,1,0.3,1);
    }
    .feat-card:hover .feat-icon { transform: scale(1.1) rotate(-3deg); }
    .feat-icon svg { width: 22px; height: 22px; }
    .feat-card h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.6rem; color: var(--green); }
    .feat-card p  { font-size: 0.87rem; line-height: 1.72; color: #6b8a87; font-family: 'DM Sans', sans-serif; }

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
      background: linear-gradient(90deg, var(--red), var(--green)); opacity: 0.18;
    }
    .step { text-align: center; padding: 0 1rem; }
    .step-num {
      width: 64px; height: 64px; border-radius: 50%;
      background: #fff; border: 2px solid rgba(36,68,65,0.18);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem; font-weight: 900; color: var(--green);
      margin: 0 auto 1.3rem; position: relative; z-index: 1;
      transition: all 0.38s cubic-bezier(0.16,1,0.3,1);
    }
    .step:hover .step-num {
      background: var(--green); color: #fff; border-color: var(--green);
      transform: scale(1.1); box-shadow: 0 10px 30px rgba(36,68,65,0.2);
    }
    .step h3 { font-size: 0.98rem; font-weight: 700; margin-bottom: 0.45rem; color: var(--green); }
    .step p  { font-size: 0.82rem; color: #6b8a87; line-height: 1.65; font-family: 'DM Sans', sans-serif; }

    /* ══ SERVICES ══ */
    .services { background: #fff; border-top: 1px solid var(--line); }
    .svc-grid {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 1.2rem; margin-top: 3.5rem;
    }
    .svc-card {
      background: var(--cream); border: 1px solid rgba(36,68,65,0.07);
      border-radius: 18px; padding: 1.7rem;
      transition: all 0.38s cubic-bezier(0.16,1,0.3,1);
      position: relative; overflow: hidden; cursor: default;
    }
    .svc-card::after {
      content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, var(--red), var(--green));
      transform: scaleX(0); transform-origin: left;
      transition: transform 0.38s cubic-bezier(0.16,1,0.3,1);
    }
    .svc-card:hover { background: #fff; transform: translateY(-7px); border-color: rgba(36,68,65,0.12); box-shadow: 0 20px 50px rgba(36,68,65,0.10); }
    .svc-card:hover::after { transform: scaleX(1); }
    .svc-icon {
      width: 44px; height: 44px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1rem; transition: transform 0.38s cubic-bezier(0.16,1,0.3,1);
    }
    .svc-card:hover .svc-icon { transform: scale(1.12) rotate(4deg); }
    .svc-icon svg { width: 20px; height: 20px; }
    .svc-card h3 { font-size: 0.98rem; font-weight: 700; color: var(--green); margin-bottom: 0.45rem; }
    .svc-card p  { font-size: 0.82rem; color: #6b8a87; line-height: 1.67; font-family: 'DM Sans', sans-serif; }

    /* ══ CTA BAND ══ */
    .cta-band {
      background: var(--green); padding: 100px 0;
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
      background: radial-gradient(circle, rgba(195,54,67,0.16) 0%, transparent 70%);
      top: 50%; left: 50%; transform: translate(-50%,-50%);
      animation: orbDrift 8s ease-in-out infinite;
    }
    .cta-orb-2 {
      width: 300px; height: 300px;
      background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%);
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
    .cta-band h2 em { color: rgba(195,54,67,0.9); font-style: normal; }
    .cta-band p { color: rgba(255,255,255,0.52); margin-bottom: 2.8rem; font-size: 1.02rem; line-height: 1.8; font-family: 'DM Sans', sans-serif; }
    .btn-white {
      background: #fff; color: var(--green);
      padding: 1rem 2.8rem; border-radius: 50px;
      font-weight: 700; font-size: 0.94rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
      box-shadow: 0 10px 36px rgba(0,0,0,0.18);
      transition: all 0.32s cubic-bezier(0.16,1,0.3,1);
      position: relative; overflow: hidden;
    }
    .btn-white::before {
      content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
      background: linear-gradient(90deg, transparent, rgba(36,68,65,0.06), transparent);
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
      color: rgba(36,68,65,0.42); font-size: 0.8rem;
    }
    .foot-logo { font-family: 'Playfair Display', serif; font-size: 1.35rem; font-weight: 900; color: var(--green); }
    .foot-logo span { color: var(--red); }
    .foot-client {
      font-family: 'DM Mono', monospace; font-size: 0.5rem;
      letter-spacing: 0.14em; text-transform: uppercase;
      color: rgba(36,68,65,0.32); margin-top: 0.12rem;
    }
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
      body { cursor: auto; }
      .cursor, .cursor-ring { display: none; }
      * { cursor: auto !important; }
      a, button { cursor: pointer !important; }
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
    ::-webkit-scrollbar-thumb { background: var(--green); border-radius: 3px; }
  </style>
</head>
<body>

<div class="cursor" id="cursor"></div>
<div class="cursor-ring" id="cursorRing"></div>
<div class="scroll-bar" id="scrollBar"></div>

<!-- ══ NAV ══ -->
<nav id="nav">
  <div class="nav-inner">
    <div class="nav-brand">
      <div class="logo">TELE<span>-</span>CARE</div>
      <div class="logo-sub">EXCELLCARE MEDICAL SYSTEM INC.</div>
    </div>
    <ul class="nav-links">
      <li><a href="#features">Features</a></li>
      <li><a href="#how">How It Works</a></li>
      <li><a href="#services">Services</a></li>
      <li style="display:flex;align-items:center;gap:0.32rem;">
        <a href="auth/login.php" class="nav-login">Patient Log In</a>
        <div class="nav-divider"></div>
        <a href="doctor/login.php" class="nav-login">Doctor Log In</a>
      </li>
      <li><a href="auth/register.php" class="nav-cta">Register</a></li>
    </ul>
    <div class="hamburger" id="ham"><span></span><span></span><span></span></div>
  </div>
</nav>

<div class="mob-menu" id="mobMenu">
  <a href="#features">Features</a>
  <a href="#how">How It Works</a>
  <a href="#services">Services</a>
  <a href="auth/login.php" class="nav-login">Patient Log In</a>
  <a href="doctor/login.php" class="nav-login">Doctor Log In</a>
  <a href="auth/register.php" class="nav-cta">Register</a>
</div>


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
              <div class="hv-badge-label">Secured with</div>
              <div class="hv-badge-val">Google OAuth 2.0</div>
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
        <p>Pay consultation fees seamlessly via PayMongo. Safe, fast, and automatically reflected in your account dashboard.</p>
      </div>
      <div class="feat-card reveal">
        <div class="feat-icon ib-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <h3>Prescription &amp; Lab Result Scanning</h3>
        <p>Upload photos of prescriptions or lab results — Tesseract OCR and Poppler extract and digitize the data automatically.</p>
      </div>
      <div class="feat-card reveal">
        <div class="feat-icon ib-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <h3>Secure Authentication</h3>
        <p>Google OAuth 2.0 login with role-based access control for Patients, Doctors, and Administrators.</p>
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
        <p>Register in under 5 minutes using your email or Google account.</p>
      </div>
      <div class="step reveal">
        <div class="step-num">2</div>
        <h3>Book an Appointment</h3>
        <p>Choose a doctor and pick a date and time that works for you.</p>
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
        <p>Upload a photo of your prescription and Tesseract OCR + Poppler extract and digitize the text automatically.</p>
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
        <p>Secure consultation payments via PayMongo, with automatic status updates in your account dashboard.</p>
      </div>
      <div class="svc-card reveal">
        <div class="svc-icon ib-blue">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </div>
        <h3>Secure Authentication</h3>
        <p>Google OAuth 2.0 login with role-based access for Patients, Doctors, and Administrators — data privacy guaranteed.</p>
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
      <p>Powered by Excellcare Medical System Inc. &mdash; experience healthcare without barriers.</p>
      <a href="auth/register.php" class="btn-white">
        <span>Get Started &mdash; It's Free</span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <div class="cta-tags">
        <span class="cta-tag"><span class="cta-tag-dot"></span>Video Call</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>Tesseract OCR</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>Poppler PDF</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>PayMongo</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>Google OAuth 2.0</span>
        <span class="cta-tag"><span class="cta-tag-dot"></span>AI Summary</span>
      </div>
    </div>
  </div>
</div>


<!-- ══ FOOTER ══ -->
<footer>
  <div class="container">
    <div>
      <div class="foot-logo">TELE<span>-</span>CARE</div>
      <div class="foot-client">EXCELLCARE MEDICAL SYSTEM INC.</div>
    </div>
    <p>&copy; 2026 Tele-Care Development Team &mdash; University of Caloocan City</p>
  </div>
</footer>


<script>
/* ── CURSOR ── */
const cur = document.getElementById('cursor');
const ring = document.getElementById('cursorRing');
let mx=0,my=0,rx=0,ry=0;
document.addEventListener('mousemove', e => {
  mx=e.clientX; my=e.clientY;
  cur.style.left=mx+'px'; cur.style.top=my+'px';
});
(function loop(){
  rx+=(mx-rx)*0.11; ry+=(my-ry)*0.11;
  ring.style.left=rx+'px'; ring.style.top=ry+'px';
  requestAnimationFrame(loop);
})();

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
    this.c  = Math.random() > 0.5 ? '36,68,65' : '195,54,67';
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
        ctx.strokeStyle=`rgba(36,68,65,${0.045*(1-dist/110)})`;
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
    ? '0 4px 32px rgba(36,68,65,0.1)'
    : '0 1px 30px rgba(36,68,65,0.06)';
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
  'Online Payments via PayMongo',
  'Google OAuth 2.0',
  'Tesseract OCR',
  'Role-Based Access Control',
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
</body>
</html>