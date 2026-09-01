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
    .topbar{background:#fff;padding:.9rem 2rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(36,68,65,.07);position:sticky;top:0;z-index:50;box-shadow:0 1px 4px rgba(0,0,0,.04)}
  .topbar-left{display:flex;align-items:center;gap:.7rem}
  .sb-toggle{width:34px;height:34px;border:1px solid rgba(36,68,65,.14);border-radius:10px;background:#fff;color:var(--green);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s}
  .sb-toggle:hover{background:rgba(36,68,65,.05)}
  .sb-toggle svg{width:18px;height:18px;stroke:currentColor;stroke-width:2.2;fill:none}
    .page-wrap{padding:1.8rem 2rem}

    /* ── Cards ── */
    .card{background:#fff;border-radius:16px;padding:1.3rem;border:1px solid rgba(36,68,65,.06);box-shadow:0 2px 10px rgba(0,0,0,.04);margin-bottom:1.2rem}
  .stat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem}
    .stat-card{background:#fff;border-radius:14px;padding:1.1rem 1.2rem;border:1px solid rgba(36,68,65,.06);box-shadow:0 2px 8px rgba(0,0,0,.04)}
    .stat-num{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;line-height:1}
    .stat-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-top:.3rem}

    /* ── Table ── */
    .tbl-wrap{background:#fff;border-radius:14px;overflow:hidden;border:1px solid rgba(36,68,65,.07);box-shadow:0 2px 8px rgba(0,0,0,.04)}
    table{width:100%;border-collapse:collapse}
    th{background:rgba(36,68,65,.04);padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid rgba(36,68,65,.07)}
    td{padding:.75rem 1rem;font-size:.85rem;border-bottom:1px solid rgba(36,68,65,.05);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(63,130,227,.02)}

    /* ── Badges ── */
    .badge{display:inline-block;padding:.2rem .65rem;border-radius:50px;font-size:.68rem;font-weight:700;letter-spacing:.03em}
    .bg-green{background:rgba(34,197,94,.1);color:#16a34a}
    .bg-orange{background:rgba(245,158,11,.1);color:#d97706}
    .bg-red{background:rgba(195,54,67,.1);color:var(--red)}
    .bg-blue{background:rgba(63,130,227,.1);color:var(--blue)}
    .bg-gray{background:rgba(0,0,0,.06);color:#888}

    /* ── Buttons ── */
    .btn-primary{background:var(--blue);color:#fff;padding:.5rem 1rem;border-radius:50px;font-size:.8rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;text-decoration:none;display:inline-block}
    .btn-primary:hover{background:#2d6fd4}
    .btn-green{background:rgba(34,197,94,.1);color:#16a34a;padding:.4rem .8rem;border-radius:50px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-green:hover{background:#16a34a;color:#fff}
    .btn-red{background:rgba(195,54,67,.1);color:var(--red);padding:.4rem .8rem;border-radius:50px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-red:hover{background:var(--red);color:#fff}
    .btn-orange{background:rgba(245,158,11,.1);color:#d97706;padding:.4rem .8rem;border-radius:50px;font-size:.75rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-orange:hover{background:#d97706;color:#fff}
    .btn-sm{padding:.35rem .8rem;border-radius:50px;font-size:.73rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s}

    /* ── Modal ── */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:200;padding:1rem;backdrop-filter:blur(4px)}
    .modal-overlay.open{display:flex}
    .modal{background:#fff;border-radius:20px;padding:1.8rem;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;animation:mUp .3s ease}
    @keyframes mUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
    .modal h3{font-size:1.2rem;margin-bottom:1rem}
    .f-label{display:block;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:.35rem}
    .f-input{width:100%;padding:.68rem .9rem;border:1.5px solid rgba(36,68,65,.12);border-radius:11px;font-family:'DM Sans',sans-serif;font-size:.88rem;color:var(--text);outline:none;transition:border-color .2s;background:#fff;margin-bottom:.8rem}
    .f-input:focus{border-color:var(--blue)}
    select.f-input{cursor:pointer}
    .f-row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
    .btn-submit{width:100%;padding:.8rem;border-radius:50px;background:var(--blue);color:#fff;font-weight:700;font-size:.9rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .25s;margin-top:.3rem}
    .btn-submit:hover{background:#2d6fd4}
    .btn-cancel-modal{width:100%;padding:.65rem;border-radius:50px;background:transparent;color:var(--text);font-weight:600;font-size:.85rem;border:1.5px solid rgba(36,68,65,.15);cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:.5rem}

    /* ── Queue card ── */
    .queue-item{display:flex;align-items:center;gap:.9rem;padding:.85rem 0;border-bottom:1px solid rgba(36,68,65,.06)}
    .queue-item:last-child{border-bottom:none}
    .queue-num{width:32px;height:32px;border-radius:50%;background:rgba(63,130,227,.1);color:var(--blue);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.85rem;flex-shrink:0}

    /* ── Toast ── */
  .toast{position:fixed;top:1.1rem;right:1.2rem;z-index:300;background:linear-gradient(135deg,#244441,#1f3a37);color:#fff;padding:1rem 1.35rem;border-radius:16px;font-size:.98rem;font-weight:700;box-shadow:0 10px 30px rgba(0,0,0,.2);animation:slideIn .35s ease,fadeOut .4s 3.2s ease forwards;max-width:min(92vw,430px);line-height:1.35}
  .toast.error{background:linear-gradient(135deg,#c33643,#9f2230)}
  @keyframes slideIn{from{transform:translateY(-14px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}

    /* ── Section header ── */
    .sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem}
    .sec-head h2{font-size:1.25rem}
    .empty-row{text-align:center;padding:2.5rem;color:var(--muted);font-size:.88rem}

    /* ── Search ── */
    .search-bar{padding:.6rem .9rem;border:1.5px solid rgba(36,68,65,.12);border-radius:50px;font-family:'DM Sans',sans-serif;font-size:.85rem;color:var(--text);outline:none;width:220px;transition:border-color .2s}
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
    <div style="display:flex;align-items:center;gap:.8rem;">
      <div style="font-size:.8rem;color:var(--muted);"><?= date('l, F j, Y') ?></div>
      <?php if ($stat_pending > 0): ?>
      <div style="background:rgba(195,54,67,.1);color:var(--red);border-radius:50px;padding:.3rem .8rem;font-size:.75rem;font-weight:700;">
        ⏳ <?= $stat_pending ?> pending
      </div>
      <?php endif ?>
    </div>
  </div>
  <div class="page-wrap">