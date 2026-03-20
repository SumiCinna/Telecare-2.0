<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($page_title ?? 'Staff — TELE-CARE') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --red:#C33643; --green:#244441; --green-dark:#1a3330;
      --blue:#3F82E3; --blue-dark:#2563C4;
      --bg:#F2F2F2;  --white:#FFFFFF;
      --muted:#9ab0ae;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html{background:#F2F2F2}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);display:flex;min-height:100vh;opacity:0;}
    @keyframes fadeIn{to{opacity:1}}
    h1,h2,h3{font-family:'Playfair Display',serif}

    /* ── Page loader ── */
    #page-loader{position:fixed;inset:0;background:#F2F2F2;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;transition:opacity 0.3s ease}
    #page-loader.hide{opacity:0;pointer-events:none}
    .loader-logo{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--green);letter-spacing:0.04em;margin-bottom:1.5rem}
    .loader-logo span{color:var(--red)}
    .loader-bar{width:160px;height:3px;background:rgba(36,68,65,0.1);border-radius:99px;overflow:hidden}
    .loader-bar-fill{height:100%;width:0%;background:var(--red);border-radius:99px;animation:loadBar 0.5s ease forwards}
    @keyframes loadBar{from{width:0%}to{width:100%}}

    /* ── Sidebar ── */
    .sidebar{width:240px;min-width:240px;background:var(--green);display:flex;flex-direction:column;position:sticky;top:0;height:100vh;overflow-y:auto}
    .sidebar-logo{padding:1.6rem 1.5rem 1rem;font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:#fff;border-bottom:1px solid rgba(255,255,255,0.08);letter-spacing:0.04em}
    .sidebar-logo span{color:var(--red)}
    .sidebar-user{display:flex;align-items:center;gap:0.75rem;padding:1rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.08)}
    .sidebar-user-avatar{width:36px;height:36px;border-radius:10px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.82rem;flex-shrink:0;overflow:hidden}
    .nav-links{padding:0.8rem 0;flex:1}
    .nav-group-label{padding:0.6rem 1.5rem 0.2rem;font-size:0.62rem;font-weight:800;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.28)}
    .nav-link{display:flex;align-items:center;gap:0.75rem;padding:0.72rem 1.5rem;color:rgba(255,255,255,0.55);font-size:0.86rem;font-weight:500;width:100%;text-align:left;font-family:'DM Sans',sans-serif;transition:all 0.2s;border-left:3px solid transparent;text-decoration:none}
    .nav-link svg{width:17px;height:17px;stroke:currentColor;flex-shrink:0}
    .nav-link:hover{color:#fff;background:rgba(255,255,255,0.06)}
    .nav-link.active{color:#fff;background:rgba(255,255,255,0.1);border-left-color:var(--red)}
    .nav-badge{margin-left:auto;background:var(--red);color:#fff;border-radius:50px;padding:0.1rem 0.5rem;font-size:0.65rem;font-weight:800;min-width:18px;text-align:center}
    .nav-soon{margin-left:auto;background:rgba(255,255,255,0.12);color:rgba(255,255,255,0.4);border-radius:50px;padding:0.1rem 0.5rem;font-size:0.62rem;font-weight:700}
    .sidebar-logout{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,0.08)}
    .logout-btn{display:flex;align-items:center;gap:0.6rem;color:rgba(255,255,255,0.45);font-size:0.82rem;text-decoration:none;transition:color 0.2s}
    .logout-btn:hover{color:var(--red)}

    /* ── Main ── */
    .main{flex:1;overflow-y:auto;display:flex;flex-direction:column}
    .topbar{background:var(--white);padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,0.07);position:sticky;top:0;z-index:50}
    .page-content{padding:2rem;flex:1}

    /* ── Cards ── */
    .card{background:var(--white);border-radius:16px;padding:1.3rem;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:1.2rem}
    .section-label{font-size:0.7rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;color:var(--muted);margin-bottom:0.9rem}

    /* ── Stat cards ── */
    .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem}
    .stat-card{background:var(--white);border-radius:16px;padding:1.3rem;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    .stat-card .s-label{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);margin-bottom:0.4rem}
    .stat-card .s-value{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;color:var(--green);line-height:1}
    .stat-card .s-sub{font-size:0.73rem;color:var(--muted);margin-top:0.25rem}

    /* ── Tables ── */
    .table-wrap{background:var(--white);border-radius:16px;overflow:hidden;border:1px solid rgba(36,68,65,0.07);box-shadow:0 2px 10px rgba(0,0,0,0.04)}
    table{width:100%;border-collapse:collapse}
    th{padding:0.85rem 1.2rem;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);text-align:left;background:rgba(36,68,65,0.03);border-bottom:1px solid rgba(36,68,65,0.07)}
    td{padding:0.85rem 1.2rem;font-size:0.87rem;border-bottom:1px solid rgba(36,68,65,0.05);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(36,68,65,0.02)}
    .empty-row{text-align:center;padding:2.5rem;color:var(--muted);font-size:0.87rem}

    /* ── Badges ── */
    .badge{display:inline-block;padding:0.2rem 0.6rem;border-radius:50px;font-size:0.68rem;font-weight:700;letter-spacing:0.04em}
    .badge-green{background:rgba(34,197,94,0.1);color:#16a34a}
    .badge-orange{background:rgba(245,158,11,0.1);color:#d97706}
    .badge-red{background:rgba(195,54,67,0.1);color:var(--red)}
    .badge-blue{background:rgba(63,130,227,0.1);color:var(--blue)}
    .badge-gray{background:rgba(0,0,0,0.06);color:#888}

    /* ── Buttons ── */
    .btn-primary{display:inline-flex;align-items:center;gap:0.4rem;background:var(--red);color:#fff;padding:0.6rem 1.2rem;border-radius:50px;font-size:0.83rem;font-weight:600;border:none;cursor:pointer;transition:all 0.25s;font-family:'DM Sans',sans-serif;box-shadow:0 4px 14px rgba(195,54,67,0.25);text-decoration:none}
    .btn-primary:hover{background:#a82d38;transform:translateY(-1px)}
    .btn-sm{padding:0.35rem 0.8rem;border-radius:50px;font-size:0.75rem;font-weight:600;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-decoration:none;display:inline-block;white-space:nowrap}
    .btn-green{background:rgba(34,197,94,0.1);color:#16a34a}.btn-green:hover{background:#16a34a;color:#fff}
    .btn-red{background:rgba(195,54,67,0.1);color:var(--red)}.btn-red:hover{background:var(--red);color:#fff}
    .btn-blue{background:rgba(63,130,227,0.1);color:var(--blue)}.btn-blue:hover{background:var(--blue);color:#fff}
    .btn-gray{background:rgba(0,0,0,0.06);color:#666}.btn-gray:hover{background:rgba(0,0,0,0.12)}

    /* ── Modal ── */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1rem;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:var(--white);border-radius:20px;padding:2rem;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;animation:fadeUp 0.3s ease}
    .modal h3{font-size:1.25rem;margin-bottom:1rem}
    @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

    /* ── Form fields ── */
    .field-label{display:block;font-size:0.7rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--muted);margin-bottom:0.4rem}
    .field-input{width:100%;padding:0.72rem 0.9rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:12px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--green);outline:none;transition:border-color 0.2s;background:#fff}
    .field-input:focus{border-color:var(--blue)}
    .form-field{margin-bottom:0.9rem}
    .btn-submit{width:100%;padding:0.82rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.9rem;border:none;cursor:pointer;margin-top:0.5rem;transition:all 0.25s;font-family:'DM Sans',sans-serif}
    .btn-submit:hover{background:#a82d38}
    .btn-cancel{width:100%;padding:0.7rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:0.86rem;border:1.5px solid rgba(36,68,65,0.15);cursor:pointer;margin-top:0.4rem;font-family:'DM Sans',sans-serif}

    /* ── Alerts ── */
    .alert-success{background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:#15803d;border-radius:12px;padding:0.75rem 1rem;font-size:0.86rem;margin-bottom:1.2rem}
    .alert-error{background:rgba(195,54,67,0.08);border:1px solid rgba(195,54,67,0.2);color:var(--red);border-radius:12px;padding:0.75rem 1rem;font-size:0.86rem;margin-bottom:1.2rem}

    /* ── Toast ── */
    .toast{position:fixed;bottom:2rem;right:2rem;z-index:300;background:var(--green);color:#fff;padding:0.9rem 1.5rem;border-radius:14px;font-size:0.88rem;font-weight:600;box-shadow:0 8px 30px rgba(0,0,0,0.15);animation:slideIn 0.4s ease,fadeOut 0.4s 3s ease forwards}
    @keyframes slideIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}

    /* ── Queue item ── */
    .queue-item{display:flex;align-items:center;gap:0.9rem;padding:0.75rem 0;border-bottom:1px solid rgba(36,68,65,0.05)}
    .queue-item:last-child{border-bottom:none}
    .queue-num{width:30px;height:30px;border-radius:50%;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.78rem;flex-shrink:0}

    /* ── Notif item ── */
    .notif-item{display:flex;align-items:flex-start;gap:0.8rem;padding:0.8rem 0;border-bottom:1px solid rgba(36,68,65,0.05)}
    .notif-item:last-child{border-bottom:none}
    .notif-dot{width:8px;height:8px;border-radius:50%;background:var(--blue);flex-shrink:0;margin-top:5px}
    .notif-dot.read{background:rgba(36,68,65,0.15)}

    @media(max-width:900px){.sidebar{display:none}.stats-grid{grid-template-columns:repeat(2,1fr)}}
  </style>
</head>
<body>

<!-- Page Loader -->
<div id="page-loader">
  <div class="loader-logo">TELE<span>-</span>CARE</div>
  <div class="loader-bar"><div class="loader-bar-fill"></div></div>
</div>

<script>
  window.addEventListener('load', function() {
    const loader = document.getElementById('page-loader');
    loader.classList.add('hide');
    setTimeout(() => { loader.remove(); }, 300);
    document.body.style.animation = 'fadeIn 0.2s ease forwards';
  });
</script>