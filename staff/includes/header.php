<?php
// staff/includes/header.php
// staff/includes/header.php mga sidebar
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= ucfirst($active_page) ?> — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#EEF3FB;--white:#fff;--muted:#8fa3c8;--text:#1a2f5e}
    html{margin:0;padding:0;width:100%;height:100%}
    body{margin:0;padding:0;width:100%;height:100%;font-family:'DM Sans',sans-serif;background:var(--green);color:var(--text);display:flex;min-height:100vh;overflow:hidden}
    *{box-sizing:border-box;margin:0;padding:0}
    h1,h2,h3{font-family:'Playfair Display',serif}

    /* ── Main layout ── */
    .main{flex:1;overflow-y:auto;overflow-x:hidden;background:var(--bg);height:100vh}
    .topbar{background:#fff;padding:1.2rem 3rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,.08);position:sticky;top:0;z-index:50;box-shadow:0 1px 4px rgba(0,0,0,.04)}
  .topbar-left{display:flex;align-items:center;gap:1rem}
  .sb-toggle{width:34px;height:34px;border:1px solid rgba(36,68,65,.14);border-radius:10px;background:#fff;color:var(--green);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s}
  .sb-toggle:hover{background:rgba(36,68,65,.05)}
  .sb-toggle svg{width:18px;height:18px;stroke:currentColor;stroke-width:2.2;fill:none}
  
  /* ── Topbar Right Elements ── */
  .topbar-right{display:flex;align-items:center;gap:1.2rem}
  .notif-bell{width:38px;height:38px;border:1px solid rgba(36,68,65,.14);border-radius:10px;background:#fff;color:var(--green);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;position:relative}
  .notif-bell:hover{background:rgba(36,68,65,.05)}
  .notif-bell svg{width:18px;height:18px;stroke:currentColor;stroke-width:2}
  .notif-badge{position:absolute;top:-4px;right:-4px;width:20px;height:20px;background:var(--red);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:900}
  
  .profile-menu{display:flex;align-items:center;gap:.6rem;padding:.5rem .8rem;border:1px solid rgba(36,68,65,.12);border-radius:50px;background:#fff;cursor:pointer;transition:all .2s}
  .profile-menu:hover{background:rgba(36,68,65,.04);border-color:rgba(36,68,65,.2)}
  .profile-name{font-size:.82rem;font-weight:600;color:var(--text)}
  .profile-avatar{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--green),#1a2f3e);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem}
    
    .page-wrap{padding:2.5rem 3rem}

    /* ── Cards ── */
    .card{background:#fff;border-radius:16px;padding:2rem;border:1px solid rgba(36,68,65,.06);box-shadow:0 2px 10px rgba(0,0,0,.04);margin-bottom:2rem}
  .stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1.5rem;margin-bottom:2.5rem}
    .stat-card{background:#fff;border-radius:14px;padding:1.8rem 1.6rem;border:1px solid rgba(36,68,65,.06);box-shadow:0 2px 8px rgba(0,0,0,.04);transition:all .2s}
    .stat-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-2px)}
    .stat-num{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;line-height:1;margin-bottom:.6rem}
    .stat-lbl{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}

    /* ── Table ── */
    .tbl-wrap{background:#fff;border-radius:14px;overflow:hidden;border:1px solid rgba(36,68,65,.07);box-shadow:0 2px 8px rgba(0,0,0,.04)}
    table{width:100%;border-collapse:collapse}
    th{background:rgba(36,68,65,.04);padding:1rem;text-align:left;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid rgba(36,68,65,.08)}
    td{padding:1rem;font-size:.88rem;border-bottom:1px solid rgba(36,68,65,.06);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(63,130,227,.03)}

    /* ── Badges ── */
    .badge{display:inline-block;padding:.3rem .8rem;border-radius:50px;font-size:.72rem;font-weight:700;letter-spacing:.03em}
    .bg-green{background:rgba(34,197,94,.1);color:#16a34a}
    .bg-orange{background:rgba(245,158,11,.1);color:#d97706}
    .bg-red{background:rgba(195,54,67,.1);color:var(--red)}
    .bg-blue{background:rgba(63,130,227,.1);color:var(--blue)}
    .bg-gray{background:rgba(0,0,0,.06);color:#888}

    /* ── Buttons ── */
    .btn-primary{background:var(--blue);color:#fff;padding:.6rem 1.2rem;border-radius:50px;font-size:.85rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;text-decoration:none;display:inline-block}
    .btn-primary:hover{background:#2d6fd4}
    .btn-green{background:rgba(34,197,94,.1);color:#16a34a;padding:.5rem .9rem;border-radius:50px;font-size:.78rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-green:hover{background:#16a34a;color:#fff}
    .btn-red{background:rgba(195,54,67,.1);color:var(--red);padding:.5rem .9rem;border-radius:50px;font-size:.78rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-red:hover{background:var(--red);color:#fff}
    .btn-orange{background:rgba(245,158,11,.1);color:#d97706;padding:.5rem .9rem;border-radius:50px;font-size:.78rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-orange:hover{background:#d97706;color:#fff}
    .btn-sm{padding:.4rem .9rem;border-radius:50px;font-size:.76rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}

    /* ── Modal ── */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1rem;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;animation:mUp .3s ease}
    @keyframes mUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .modal h3{font-size:1.3rem;margin-bottom:1.5rem;font-weight:700}
    .f-label{display:block;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:.5rem}
    .f-input{width:100%;padding:.8rem 1rem;border:1.5px solid rgba(36,68,65,.12);border-radius:11px;font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--text);outline:none;transition:border-color .2s;background:#fff;margin-bottom:1rem}
    .f-input:focus{border-color:var(--blue)}
    select.f-input{cursor:pointer}
    .f-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    .btn-submit{width:100%;padding:.9rem;border-radius:50px;background:var(--blue);color:#fff;font-weight:700;font-size:.95rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .25s;margin-top:.5rem}
    .btn-submit:hover{background:#2d6fd4}
    .btn-cancel-modal{width:100%;padding:.8rem;border-radius:50px;background:transparent;color:var(--text);font-weight:600;font-size:.88rem;border:1.5px solid rgba(36,68,65,.15);cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:.7rem}

    /* ── Queue card ── */
    .queue-item{display:flex;align-items:center;gap:1.2rem;padding:1.2rem 0;border-bottom:1px solid rgba(36,68,65,.08)}
    .queue-item:last-child{border-bottom:none}
    .queue-num{width:36px;height:36px;border-radius:50%;background:rgba(63,130,227,.1);color:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.9rem;flex-shrink:0}

    /* ── Toast ── */
  .toast{position:fixed;top:1.1rem;right:1.2rem;z-index:300;background:linear-gradient(135deg,#244441,#1f3a37);color:#fff;padding:1rem 1.35rem;border-radius:16px;font-size:.98rem;font-weight:700;box-shadow:0 10px 30px rgba(0,0,0,.2);animation:slideIn .35s ease,fadeOut .4s 3.2s ease forwards;max-width:min(92vw,430px);line-height:1.35}
  .toast.error{background:linear-gradient(135deg,#c33643,#9f2230)}
  @keyframes slideIn{from{transform:translateY(-14px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}

    /* ── Section header ── */
    .sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem}
    .sec-head h2{font-size:1.3rem;margin:0}
    .empty-row{text-align:center;padding:3rem 2.5rem;color:var(--muted);font-size:.88rem}

    /* ── Search ── */
    .search-bar{padding:.75rem 1rem;border:1.5px solid rgba(36,68,65,.12);border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.88rem;color:var(--text);outline:none;width:240px;transition:border-color .2s}
    .search-bar:focus{border-color:var(--blue)}

    @media(max-width:1200px){.stat-grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:900px){.sidebar{display:none}.stat-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:640px){.stat-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>

<?php
  $global_toast = $toast ?? null;
  $global_toast_error = $toast_error ?? null;
  if ($global_toast_error):
?>
<div class="toast error">✕ <?= htmlspecialchars($global_toast_error) ?></div>
<?php elseif ($global_toast): ?>
<div class="toast">✓ <?= htmlspecialchars($global_toast) ?></div>
<?php endif ?>

<?php require_once 'sidebar.php'; ?>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="sb-toggle" onclick="toggleStaffSidebar()" aria-label="Toggle sidebar" title="Toggle sidebar">
        <svg viewBox="0 0 24 24">
          <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
      </button>
      <div>
      <div style="font-size:.73rem;color:var(--muted);font-weight:600;">TELE-CARE Staff</div>
      <div style="font-size:.95rem;font-weight:700;"><?= ucfirst($active_page) ?></div>
      </div>
    </div>
    <div class="topbar-right">
      <button type="button" class="notif-bell" title="Notifications">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span class="notif-badge">2</span>
      </button>
      <div class="profile-menu">
        <span class="profile-name"><?= htmlspecialchars($staff_name ?? 'Staff User') ?></span>
        <div class="profile-avatar"><?= strtoupper(substr($staff_name ?? 'S', 0, 1)) ?></div>
      </div>
    </div>
  </div>
  <div class="page-wrap">