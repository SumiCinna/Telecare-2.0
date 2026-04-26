<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) && !isset($_SESSION['patient_id'])) {
    header('Location: login.php');
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
    header('Location: login.php');
    exit;
}

$user_name = $_SESSION['user_name'] ?? $_SESSION['patient_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Logout — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    :root{--red:#C33643;--green:#244441;--blue:#3F82E3;--bg:#F2F2F2;--white:#FFFFFF}
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--green);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;}
    h1,h2{font-family:'Playfair Display',serif}
    .card{background:var(--white);border-radius:24px;padding:2.8rem 2.5rem;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(36,68,65,0.12);text-align:center;animation:fadeUp 0.35s ease;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    .logo{font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:900;color:var(--green);margin-bottom:2rem;letter-spacing:0.02em;}
    .logo span{color:var(--red)}
    .icon-wrap{width:72px;height:72px;border-radius:20px;background:rgba(195,54,67,0.08);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;}
    h2{font-size:1.5rem;margin-bottom:0.5rem;}
    .sub{font-size:0.88rem;color:#6b8a87;line-height:1.6;margin-bottom:2rem;}
    .sub strong{color:var(--green);}
    .btn-logout{width:100%;padding:0.9rem;border-radius:50px;background:var(--red);color:#fff;font-weight:700;font-size:0.95rem;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;box-shadow:0 6px 18px rgba(195,54,67,0.25);transition:all 0.25s;margin-bottom:0.75rem;}
    .btn-logout:hover{background:#a82d38;transform:translateY(-2px);}
    .btn-cancel{width:100%;padding:0.85rem;border-radius:50px;background:transparent;color:var(--green);font-weight:600;font-size:0.92rem;border:1.5px solid rgba(36,68,65,0.15);cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.2s;text-decoration:none;display:block;}
    .btn-cancel:hover{background:rgba(36,68,65,0.05);}
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">TELE<span>-</span>CARE</div>
    <div class="icon-wrap">
      <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="#C33643" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
    </div>
    <h2>Logging Out?</h2>
    <p class="sub">You're signed in as <strong><?= htmlspecialchars($user_name) ?></strong>.<br/>Are you sure you want to end your session?</p>
    <form method="POST">
      <button type="submit" name="confirm_logout" class="btn-logout">Yes, Log Me Out</button>
    </form>
    <a href="javascript:history.back()" class="btn-cancel">Cancel — Go Back</a>
  </div>
</body>
</html>