<?php
// guest_access.php  (site root)
// Targets of the links inside the e-mails sent to guests (no login needed, the secret token is the key):
//   ?t=TOKEN&go=room      consultation room (opens 15 min before the appointment)
//   ?t=TOKEN&go=summary   teleconsultation summary PDF
//   ?t=TOKEN&go=file&id=N prescription / lab request / medical certificate PDF
//   ?go=done              page shown when the call ends (and signs the guest out)
date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/includes/guest_booking.php';

$go    = (string)($_GET['go'] ?? 'room');
$token = (string)($_GET['t'] ?? '');
$e     = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES);

function ga_page(string $title, string $body, string $extraHead = ''): never {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . htmlspecialchars($title) . ' | TELE-CARE</title><link rel="icon" type="image/png" href="telecarelogo.png">'
       . '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">'
       . '<style>:root{--blue:#1f6fc6;--blue-dark:#193f72;--ink:#474748;--muted:#64748b;--line:#e2e8f0}*{box-sizing:border-box;margin:0;padding:0}'
       . 'body{font-family:Poppins,sans-serif;background:#fafafa;color:var(--ink);min-height:100vh;display:grid;place-items:center;padding:24px}'
       . '.c{width:100%;max-width:440px;background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 18px 44px rgba(25,63,114,.10);padding:32px 26px;text-align:center}'
       . '.c img{width:48px;height:48px;object-fit:contain;margin-bottom:10px}h1{font-size:1.2rem;font-weight:800;color:var(--blue-dark);margin-bottom:8px;letter-spacing:-.02em}'
       . 'p{font-size:.84rem;color:var(--muted);line-height:1.65;margin-bottom:14px}.big{font-size:2rem;font-weight:800;color:var(--blue);font-variant-numeric:tabular-nums;margin:6px 0 14px}'
       . '.b{display:inline-block;padding:12px 26px;border-radius:999px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;font-size:.85rem}.b:hover{background:var(--blue-dark)}</style>'
       . $extraHead . '</head><body><div class="c"><img src="telecarelogo.png" alt="">' . $body . '</div></body></html>';
    exit;
}

/* ── call finished ── */
if ($go === 'done') {
    guest_logout_session();
    ga_page('Consultation ended', '<h1>Thank you for your consultation</h1><p>Your doctor will review the summary and send it to your email, together with any prescription, laboratory request or medical certificate.</p><a class="b" href="index.php">Back to home</a>');
}

$g = guest_booking_by_token($conn, $token);
if (!$g) {
    http_response_code(404);
    ga_page('Link not valid', '<h1>This link is not valid</h1><p>It may have expired or been copied incompletely. Please open the latest email we sent you.</p><a class="b" href="index.php">Back to home</a>');
}
$appt_id = (int)$g['appointment_id'];

/* ── consultation room ── */
if ($go === 'room') {
    if (!in_array($g['appt_status'], ['Confirmed', 'Completed'], true)) {
        ga_page('Booking not confirmed', '<h1>Your booking is not confirmed yet</h1><p>The room opens once your booking is confirmed.</p><a class="b" href="index.php">Back to home</a>');
    }
    $ts  = strtotime($g['appointment_date'] . ' ' . $g['appointment_time']);
    $now = time();
    if ($now > $ts + 3600) {
        ga_page('Session ended', '<h1>This consultation has ended</h1><p>Your summary and any documents are sent to your email by your doctor.</p><a class="b" href="index.php">Back to home</a>');
    }
    if ($now < $ts - 900) {
        $opens = $ts - 900;
        ga_page('Room opens soon',
            '<h1>Your room opens soon</h1><p>Dr. ' . $e($g['doctor_name']) . ' &middot; ' . $e(date('M j, Y', $ts)) . ' at ' . $e(date('g:i A', $ts)) . '</p>'
          . '<div class="big" id="cd">--:--:--</div><p>The room opens 15 minutes before your appointment. This page opens it automatically.</p>'
          . '<script>(function(){var o=' . (int)$opens . '*1000;function p(n){return String(n).padStart(2,"0")}'
          . 'function t(){var d=Math.max(0,Math.round((o-Date.now())/1000));document.getElementById("cd").textContent=p(Math.floor(d/3600))+":"+p(Math.floor(d%3600/60))+":"+p(d%60);if(d<=0){location.reload();}}t();setInterval(t,1000);})();</script>');
    }
    $patient = $conn->query('SELECT * FROM patients WHERE id = ' . (int)$g['patient_id'])->fetch_assoc();
    if (!$patient) ga_page('Not available', '<h1>Room not available</h1><p>Please contact the clinic.</p>');
    guest_login_session($patient);
    header('Location: router.php?page=call_patient&appt_id=' . $appt_id);
    exit;
}

/* ── summary ── */
if ($go === 'summary') {
    $file = __DIR__ . '/consultation_summaries/summary_' . $appt_id . '.pdf';
    if (is_file($file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Teleconsultation_Summary_' . $appt_id . '.pdf"');
        header('Content-Length: ' . filesize($file));
        header('X-Robots-Tag: noindex');
        readfile($file);
        exit;
    }
    $txt = trim((string)($g['consultation_summary'] ?? ''));
    if ($txt === '') ga_page('Summary not ready', '<h1>Your summary is not ready yet</h1><p>Please check the email again shortly.</p>');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Consultation Summary</title></head>'
       . '<body style="font-family:Arial,sans-serif;max-width:860px;margin:2rem auto;padding:0 1rem;color:#1f2937;line-height:1.65">'
       . '<h2 style="color:#193f72;margin-bottom:.6rem">Consultation Summary</h2>'
       . '<div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:1rem 1.1rem">' . nl2br($e($txt)) . '</div></body></html>';
    exit;
}

/* ── issued documents ── */
if ($go === 'file') {
    $fid = (int)($_GET['id'] ?? 0);
    $st  = $conn->prepare('SELECT label, file_path FROM guest_files WHERE id = ? AND appointment_id = ? LIMIT 1');
    $st->bind_param('ii', $fid, $appt_id); $st->execute();
    $f = $st->get_result()->fetch_assoc();
    $dir  = realpath(__DIR__ . '/uploads/generated_documents');
    $path = $f ? realpath(__DIR__ . '/' . ltrim($f['file_path'], '/')) : false;
    if (!$f || !$dir || !$path || strpos($path, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
        http_response_code(404);
        ga_page('Document not found', '<h1>Document not found</h1><p>Please open the latest email we sent you.</p>');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $f['label']) . '.pdf"');
    header('Content-Length: ' . filesize($path));
    header('X-Robots-Tag: noindex');
    readfile($path);
    exit;
}

http_response_code(400);
ga_page('Not found', '<h1>Page not found</h1><a class="b" href="index.php">Back to home</a>');
