<?php
require_once '../database/config.php';

$message = '';
$success = false;

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    $message = 'Invalid or missing activation link.';
} else {
    $stmt = $conn->prepare("SELECT id, full_name, is_verified, token_expires_at FROM patients WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();

    if (!$patient) {
        $message = 'This activation link is invalid or has already been used.';
    } elseif ($patient['is_verified']) {
        $message = 'Your account is already activated. You can log in!';
        $success = true;
    } elseif (strtotime($patient['token_expires_at']) < time()) {
        $message = 'This activation link has expired. Please register again.';
    } else {
        // Activate the account
        $upd = $conn->prepare("UPDATE patients SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = ?");
        $upd->bind_param("i", $patient['id']);
        $upd->execute();
        $success = true;
        $message = 'Your account has been activated successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Activate Account — TELE-CARE</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    :root { --red:#C33643; --green:#244441; --blue:#3F82E3; }
    * { box-sizing:border-box; margin:0; padding:0; }
    body {
      font-family:'DM Sans',sans-serif;
      background: linear-gradient(160deg, var(--green) 0%, #1a3330 100%);
      min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem;
    }
    .card {
      background:#fff; border-radius:24px; padding:3rem 2.5rem;
      max-width:440px; width:100%; text-align:center;
      box-shadow:0 30px 80px rgba(0,0,0,0.25);
    }
    .icon { font-size:3.5rem; margin-bottom:1.5rem; }
    h1 { font-family:'Playfair Display',serif; font-size:1.8rem; color:var(--green); margin-bottom:0.8rem; }
    p  { color:#6b8a87; font-size:0.95rem; line-height:1.7; margin-bottom:2rem; }
    .btn {
      display:inline-block; padding:0.9rem 2.5rem; border-radius:50px;
      background:var(--red); color:#fff; font-weight:600; font-size:0.95rem;
      text-decoration:none; box-shadow:0 6px 20px rgba(195,54,67,0.3);
      transition:all 0.3s;
    }
    .btn:hover { background:#a82d38; transform:translateY(-2px); }
    .btn-outline {
      display:inline-block; padding:0.9rem 2.5rem; border-radius:50px;
      border:1.5px solid rgba(36,68,65,0.2); color:var(--green);
      font-weight:600; font-size:0.95rem; text-decoration:none;
      transition:all 0.3s; margin-top:0.8rem;
    }
    .btn-outline:hover { background:rgba(36,68,65,0.06); }
    .logo { font-family:'Playfair Display',serif; font-size:1.3rem; font-weight:900; color:var(--green); margin-bottom:2rem; display:block; }
    .logo span { color:var(--red); }
  </style>
</head>
<body>
<div class="card">
  <a href="../index.php" class="logo">TELE<span>-</span>CARE</a>

  <?php if ($success): ?>
    <div class="icon">✅</div>
    <h1>Account Activated!</h1>
    <p><?= htmlspecialchars($message) ?><br/>You can now log in and start booking consultations.</p>
    <a href="login.php" class="btn">Go to Login →</a>
  <?php else: ?>
    <div class="icon">❌</div>
    <h1>Activation Failed</h1>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="register.php" class="btn">Register Again</a><br/>
    <a href="login.php" class="btn-outline">Go to Login</a>
  <?php endif; ?>
</div>
</body>
</html>