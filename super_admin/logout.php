<?php
// super_admin/logout.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['super_admin_id'])) {
    header('Location: ../router.php?page=staffs_index');
    exit;
}

if (isset($_POST['confirm_logout'])) {
    session_unset();
    session_destroy();
    session_write_close();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    header('Location: ../router.php?page=staffs_index');
    exit;
}

$super_admin_name = $_SESSION['super_admin_name'] ?? 'Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Logout — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#b31118;--red-dark:#8a000b;--teal:#006a61;--ink:#151c27;--muted:#718096;--bg:#f7f9fb;--white:#fff}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
    h1,h2{font-family:'Playfair Display',serif}
    .card{background:var(--white);border-radius:24px;padding:2.8rem 2.5rem;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(21,28,39,.1);text-align:center;animation:fadeUp .35s ease}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .logo{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;color:var(--ink);margin-bottom:2rem;letter-spacing:.02em}
    .logo span{color:var(--red)}
    .icon-wrap{width:72px;height:72px;border-radius:20px;background:rgba(179,17,24,.08);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}
    .role-badge{display:inline-block;background:rgba(0,106,97,.08);color:var(--teal);font-size:.7rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:.25rem .75rem;border-radius:50px;margin-bottom:1.5rem}
    h2{font-size:1.5rem;margin-bottom:.5rem}
    .sub{font-size:.88rem;color:var(--muted);line-height:1.6;margin-bottom:2rem}
    .sub strong{color:var(--ink)}
    .btn-logout{width:100%;padding:.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:.95rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 6px 18px rgba(179,17,24,.25);transition:all .25s;margin-bottom:.75rem}
    .btn-logout:hover{background:var(--red-dark);transform:translateY(-2px)}
    .btn-cancel{width:100%;padding:.85rem;border-radius:50px;background:transparent;color:var(--ink);font-weight:600;font-size:.92rem;border:1.5px solid rgba(21,28,39,.12);cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;text-decoration:none;display:block}
    .btn-cancel:hover{background:rgba(21,28,39,.04)}
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">TELE<span>-</span>CARE</div>
    <div class="icon-wrap">
      <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#b31118" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
    </div>
    <div class="role-badge">Super Admin Portal</div>
    <h2>Logging Out?</h2>
    <p class="sub">You're signed in as <strong><?= htmlspecialchars($super_admin_name) ?></strong>.<br/>Are you sure you want to end your session?</p>
    <form method="POST">
      <button type="submit" name="confirm_logout" class="btn-logout">Yes, Log Me Out</button>
    </form>
    <a href="dashboard.php" class="btn-cancel">Cancel — Go Back</a>
  </div>
</body>
</html>
