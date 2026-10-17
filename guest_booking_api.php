<?php
// guest_booking_api.php  (site root)
// JSON endpoint behind the landing-page booking widget in index.php.
// Flow: recommend -> (doctors) -> schedule -> select_slot -> submit_details -> verify_otp -> guest_payment.php
date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Any crash comes back as JSON (and is logged). Set GUEST_DEBUG=1 in .env to see the real message.
function gb_debug_on(): bool { $v = $_ENV['GUEST_DEBUG'] ?? $_SERVER['GUEST_DEBUG'] ?? getenv('GUEST_DEBUG'); return $v === '1' || $v === 1 || $v === true; }
function gb_crash(string $msg): void {
    error_log('guest_booking_api: ' . $msg);
    if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode(['ok' => false, 'error' => 'Something went wrong on our side. Please try again.'] + (gb_debug_on() ? ['debug' => $msg] : []));
}
set_exception_handler(function (Throwable $e) { gb_crash($e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()); });
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) { ob_clean(); gb_crash($e['message'] . ' @ ' . basename($e['file']) . ':' . $e['line']); }
});
ob_start();

require_once __DIR__ . '/database/config.php';
require_once __DIR__ . '/includes/guest_booking.php';
require_once __DIR__ . '/private_telecare/booking/booking_helpers.php';

function gb_out(array $a, int $code = 200): never {
    http_response_code($code);
    echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function gb_fail(string $msg, int $code = 422, array $extra = []): never { gb_out(['ok' => false, 'error' => $msg] + $extra, $code); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') gb_fail('Invalid request.', 405);
if (empty($_SESSION['gb_csrf']) || !hash_equals($_SESSION['gb_csrf'], (string)($_POST['csrf'] ?? ''))) {
    gb_fail('Your session expired. Please refresh the page.', 403, ['expired' => true]);
}

// A real (registered) patient who is signed in uses the normal booking flow.
if (!empty($_SESSION['patient_id']) && empty($_SESSION['guest_mode'])) {
    gb_fail('You are signed in. Please book from your account.', 409, ['logged_in' => true]);
}

guest_ensure_schema($conn);
$action = (string)($_POST['action'] ?? '');

/* ── helpers ── */
function gb_doctor_json(array $d, string $concern, bool $first, int $n): array {
    return [
        'id'           => (int)$d['id'],
        'name'         => $d['full_name'],
        'specialty'    => (string)($d['specialty'] ?? ''),
        'subspecialty' => (string)($d['subspecialty'] ?? ''),
        'fee'          => (float)$d['consultation_fee'],
        'photo'        => (string)($d['profile_photo'] ?? ''),
        'rating'       => (float)$d['rating'],
        'rating_count' => (int)$d['rating_count'],
        'recommended'  => $first && $n > 1,
    ];
}
function gb_doctors(mysqli $conn, string $dept, string $concern): array {
    $st = $conn->prepare("SELECT id, full_name, specialty, subspecialty, consultation_fee, profile_photo, rating, rating_count
                          FROM doctors WHERE department=? AND status='active' ORDER BY full_name ASC");
    $st->bind_param('s', $dept); $st->execute();
    $rows = booking_rank_doctors($st->get_result()->fetch_all(MYSQLI_ASSOC), $concern);
    $out = [];
    foreach ($rows as $i => $d) $out[] = gb_doctor_json($d, $concern, $i === 0, count($rows));
    return $out;
}
function gb_phone(string $raw): ?string {
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) === 12 && substr($d, 0, 2) === '63') $d = substr($d, 2);
    elseif (strlen($d) === 11 && $d[0] === '0')        $d = substr($d, 1);
    return (strlen($d) === 10 && $d[0] === '9') ? '0' . $d : null;
}
function gb_dob(string $raw): ?string {
    $raw = trim($raw);
    $d = DateTime::createFromFormat('!m/d/Y', $raw);
    if (!$d || $d->format('m/d/Y') !== $raw) return null;
    $iso = $d->format('Y-m-d');
    if ($iso > date('Y-m-d') || $iso < date('Y-m-d', strtotime('-120 years'))) return null;
    return $iso;
}
function gb_name(string $s): string { return guest_clean($s, 60); }

switch ($action) {

/* ── 1. complaint -> department + doctors ── */
case 'recommend': {
    $concern = booking_clean($_POST['concern'] ?? '', 500);
    if (mb_strlen($concern) < 10) gb_fail('Please describe how you feel in a little more detail (at least 10 characters).');

    $now = time();
    $hist = array_filter($_SESSION['gb_rec_hist'] ?? [], fn($t) => $t > $now - 3600);
    if (count($hist) >= 15) gb_fail('Too many searches. Please try again later.', 429);
    $hist[] = $now; $_SESSION['gb_rec_hist'] = array_values($hist);

    $available = booking_available_departments($conn);
    if (!$available) gb_fail('No doctors are available for booking right now. Please check back later.');

    $rec  = booking_recommend_department($conn, $concern);
    $dept = isset($available[$rec['department']]) ? $rec['department'] : (string)array_key_first($available);

    $_SESSION['gb'] = ['concern' => $concern, 'rec' => $rec, 'department' => $dept];
    gb_out([
        'ok' => true, 'concern' => $concern, 'department' => $dept, 'suggested' => $rec['department'],
        'reason' => $rec['reason'], 'urgent' => !empty($rec['urgent']), 'source' => $rec['source'],
        'departments' => array_keys($available),
        'doctors' => gb_doctors($conn, $dept, $concern),
    ]);
}

/* ── 1b. patient picks another department ── */
case 'doctors': {
    $g = $_SESSION['gb'] ?? null;
    if (!$g) gb_fail('Please describe how you feel first.', 422, ['restart' => true]);
    $available = booking_available_departments($conn);
    $dept = (string)($_POST['department'] ?? '');
    if (!isset($available[$dept])) gb_fail('That department is not available.');
    if (($g['department'] ?? '') !== $dept) unset($_SESSION['gb']['doctor_id'], $_SESSION['gb']['date'], $_SESSION['gb']['time']);
    $_SESSION['gb']['department'] = $dept;
    gb_out(['ok' => true, 'department' => $dept, 'suggested' => $g['rec']['department'], 'doctors' => gb_doctors($conn, $dept, $g['concern'])]);
}

/* ── 2. doctor chosen -> his schedule ── */
case 'schedule': {
    $g = $_SESSION['gb'] ?? null;
    if (!$g) gb_fail('Please describe how you feel first.', 422, ['restart' => true]);
    $id = (int)($_POST['doctor_id'] ?? 0);
    $st = $conn->prepare("SELECT id, full_name, specialty, subspecialty, consultation_fee, profile_photo, department FROM doctors WHERE id=? AND status='active'");
    $st->bind_param('i', $id); $st->execute();
    $doc = $st->get_result()->fetch_assoc();
    if (!$doc) gb_fail('That doctor is no longer available.');
    if (($g['doctor_id'] ?? 0) !== $id) unset($_SESSION['gb']['date'], $_SESSION['gb']['time']);
    $_SESSION['gb']['doctor_id']  = $id;
    $_SESSION['gb']['department'] = $doc['department'];

    guest_expire_stale($conn);
    $s = $conn->prepare("SELECT day_of_week, start_time, end_time FROM doctor_schedules WHERE doctor_id=?");
    $s->bind_param('i', $id); $s->execute();
    $schedules = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $b = $conn->prepare("SELECT appointment_date, appointment_time FROM appointments WHERE doctor_id=? AND status NOT IN ('Cancelled') AND appointment_date >= CURDATE()");
    $b->bind_param('i', $id); $b->execute();
    $booked = $b->get_result()->fetch_all(MYSQLI_ASSOC);
    $b->close();
    $settings = guest_schedule_settings($conn, $id);
    gb_out([
        'ok' => true,
        'doctor' => ['id' => $id, 'name' => $doc['full_name'], 'specialty' => (string)$doc['specialty'], 'photo' => (string)($doc['profile_photo'] ?? ''), 'fee' => (float)$doc['consultation_fee']],
        'schedules' => $schedules,
        'settings'  => $settings,
        'booked'    => $booked,
        'now'       => ['date' => date('Y-m-d'), 'mins' => (int)date('G') * 60 + (int)date('i')],
        'department' => $doc['department'],
    ]);
}

/* ── 3. date + time chosen ── */
case 'select_slot': {
    $g = $_SESSION['gb'] ?? null;
    if (!$g || empty($g['doctor_id'])) gb_fail('Please choose a doctor first.', 422, ['restart' => true]);
    $date = trim((string)($_POST['date'] ?? ''));
    $time = substr(trim((string)($_POST['time'] ?? '')), 0, 5);
    [$ok, $msg] = guest_slot_valid($conn, (int)$g['doctor_id'], $date, $time);
    if (!$ok) gb_fail($msg, 422, ['refresh_schedule' => true]);
    $_SESSION['gb']['date'] = $date;
    $_SESSION['gb']['time'] = $time;
    gb_out(['ok' => true, 'when' => date('l, F j, Y', strtotime($date)) . ' at ' . date('g:i A', strtotime("$date $time"))]);
}

/* ── 4. patient details -> e-mail code ── */
case 'submit_details': {
    $g = $_SESSION['gb'] ?? null;
    if (!$g || empty($g['doctor_id']) || empty($g['date']) || empty($g['time'])) gb_fail('Please pick a doctor and schedule first.', 422, ['restart' => true]);

    $first = gb_name($_POST['first_name'] ?? '');
    $mid   = gb_name($_POST['middle_name'] ?? '');
    $last  = gb_name($_POST['last_name'] ?? '');
    $dob   = gb_dob((string)($_POST['dob'] ?? ''));
    $sex   = (string)($_POST['sex'] ?? '');
    $addr  = guest_clean((string)($_POST['address'] ?? ''), 255);
    $phone = gb_phone((string)($_POST['mobile'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $why   = booking_clean($_POST['reason'] ?? '', 500);

    $errs = [];
    if (mb_strlen($first) < 2)                        $errs['first_name'] = 'Enter your first name.';
    if (mb_strlen($last) < 2)                         $errs['last_name']  = 'Enter your last name.';
    if (!$dob)                                        $errs['dob']        = 'Enter your date of birth as MM/DD/YYYY.';
    if (!in_array($sex, ['Male', 'Female'], true))    $errs['sex']        = 'Select your sex.';
    if (mb_strlen($addr) < 5)                         $errs['address']    = 'Enter your address.';
    if (!$phone)                                      $errs['mobile']     = 'Enter a valid mobile number (09XX XXX XXXX).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errs['email'] = 'Enter a valid email address.';
    if (mb_strlen($why) < 10)                         $errs['reason']     = 'Describe your reason for booking.';
    if (empty($_POST['accept']))                      $errs['accept']     = 'Please accept the Terms & Conditions and Privacy Policy.';
    if ($errs) gb_out(['ok' => false, 'error' => 'Please check the highlighted fields.', 'fields' => $errs], 422);

    // Re-check the slot: it may have been taken while the patient filled in the form.
    [$ok, $msg] = guest_slot_valid($conn, (int)$g['doctor_id'], $g['date'], $g['time']);
    if (!$ok) gb_fail($msg, 422, ['back' => 'schedule']);

    // An e-mail that already has a TELE-CARE account must log in instead.
    $q = $conn->prepare('SELECT id, is_guest FROM patients WHERE email = ? LIMIT 1');
    $q->bind_param('s', $email); $q->execute();
    $ex = $q->get_result()->fetch_assoc();
    if ($ex && !(int)$ex['is_guest']) gb_fail('This email already has a TELE-CARE account. Please log in to book.', 409, ['registered' => true]);

    $ver = $_SESSION['gb_verified'] ?? null;
    if ($ver && ($ver['email'] ?? '') === $email && time() < (int)$ver['until']) {
        // This e-mail was verified a moment ago (the slot was lost afterwards): no second code needed.
        $_SESSION['gb_pending'] = [
            'first_name' => $first, 'middle_name' => $mid, 'last_name' => $last,
            'full_name' => trim($first . ' ' . ($mid !== '' ? $mid . ' ' : '') . $last),
            'dob' => $dob, 'sex' => $sex, 'address' => $addr, 'phone' => $phone, 'email' => $email, 'complaint' => $why,
        ];
        gb_out(['ok' => true, 'already_verified' => true]);
    }
    if (!empty($_SESSION['gb_otp']['sent_at']) && time() - (int)$_SESSION['gb_otp']['sent_at'] < 45 && ($_SESSION['gb_otp']['email'] ?? '') === $email && !empty($_SESSION['gb_pending'])) {
        // identical resubmit within the cooldown: reuse the code already sent
    } else {
        if (!guest_mail_allowed($conn, $email)) gb_fail('Too many codes requested. Please wait a few minutes and try again.', 429);
        $full = trim($first . ' ' . ($mid !== '' ? $mid . ' ' : '') . $last);
        $sent = guest_issue_otp($conn, $email, $full);
        if (!$sent['ok']) gb_fail('We could not send the verification email right now. Please try again.', 502);
    }

    $_SESSION['gb_pending'] = [
        'first_name' => $first, 'middle_name' => $mid, 'last_name' => $last,
        'full_name' => trim($first . ' ' . ($mid !== '' ? $mid . ' ' : '') . $last),
        'dob' => $dob, 'sex' => $sex, 'address' => $addr, 'phone' => $phone, 'email' => $email, 'complaint' => $why,
    ];
    gb_out(['ok' => true, 'email' => guest_mask_email($email), 'expires_in' => GUEST_OTP_TTL_MIN * 60, 'resend_in' => 45]);
}

/* ── 4b. new code ── */
case 'resend_otp': {
    $p = $_SESSION['gb_pending'] ?? null;
    if (!$p) gb_fail('Your booking session expired. Please start again.', 422, ['restart' => true]);
    $last = (int)($_SESSION['gb_otp']['sent_at'] ?? 0);
    if ($last && time() - $last < 45) gb_fail('Please wait a moment before requesting another code.', 429, ['wait' => 45 - (time() - $last)]);
    if (!guest_mail_allowed($conn, $p['email'])) gb_fail('Too many codes requested. Please wait a few minutes and try again.', 429);
    $sent = guest_issue_otp($conn, $p['email'], $p['full_name']);
    if (!$sent['ok']) gb_fail('We could not send the verification email right now. Please try again.', 502);
    gb_out(['ok' => true, 'email' => guest_mask_email($p['email']), 'expires_in' => GUEST_OTP_TTL_MIN * 60, 'resend_in' => 45]);
}

/* ── 5. code entered -> reserve the slot, sign the guest in, go to payment ── */
case 'verify_otp': {
    $p = $_SESSION['gb_pending'] ?? null;
    $g = $_SESSION['gb'] ?? null;
    if (!$p || !$g || empty($g['doctor_id']) || empty($g['date']) || empty($g['time'])) gb_fail('Your booking session expired. Please start again.', 422, ['restart' => true]);

    $ver = $_SESSION['gb_verified'] ?? null;
    if (!empty($_POST['skip']) && $ver && ($ver['email'] ?? '') === $p['email'] && time() < (int)$ver['until']) {
        // already verified moments ago
    } else {
        $chk = guest_check_otp($p['email'], (string)($_POST['code'] ?? ''));
        if (!$chk['ok']) gb_fail($chk['error'], 422, ['bad_code' => true]);
        $_SESSION['gb_verified'] = ['email' => $p['email'], 'until' => time() + 900];
    }

    $pat = guest_upsert_patient($conn, $p);
    if (!$pat['ok']) {
        if (($pat['error'] ?? '') === 'registered') gb_fail('This email already has a TELE-CARE account. Please log in to book.', 409, ['registered' => true]);
        gb_fail('We could not save your details. Please try again.', 500);
    }
    $patient = $pat['patient'];

    $appt = guest_create_appointment($conn, (int)$patient['id'], [
        'doctor_id' => $g['doctor_id'], 'date' => $g['date'], 'time' => $g['time'], 'complaint' => $p['complaint'],
    ]);
    if (!$appt['ok']) gb_fail($appt['error'], 409, ['back' => $appt['back'] ?? 'schedule', 'verified' => true]);

    $token = guest_new_token();
    $st = $conn->prepare("INSERT INTO guest_bookings
        (appointment_id, patient_id, access_token, first_name, middle_name, last_name, full_name, date_of_birth, sex, address, phone, email, complaint)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $pid = (int)$patient['id']; $aid = (int)$appt['appt_id'];
    $st->bind_param('iisssssssssss', $aid, $pid, $token, $p['first_name'], $p['middle_name'], $p['last_name'], $p['full_name'], $p['dob'], $p['sex'], $p['address'], $p['phone'], $p['email'], $p['complaint']);
    $st->execute();

    unset($_SESSION['gb_pending'], $_SESSION['gb_rec_hist'], $_SESSION['gb_verified']);
    guest_login_session($patient);
    $_SESSION['gb_appt_id'] = $aid;
    unset($_SESSION['gb']);   // wizard state no longer needed
    gb_out(['ok' => true, 'redirect' => 'guest_payment.php?appt_id=' . $aid]);
}

/* ── cancel the request (payment page) ── */
case 'cancel': {
    $aid = (int)($_POST['appt_id'] ?? 0);
    $pid = (int)($_SESSION['patient_id'] ?? 0);
    if ($aid && $pid && !empty($_SESSION['guest_mode'])) {
        $st = $conn->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND patient_id=? AND status='Pending' AND payment_status='Unpaid'");
        $st->bind_param('ii', $aid, $pid); $st->execute();
    }
    guest_logout_session();
    gb_out(['ok' => true, 'redirect' => 'index.php#book']);
}

default:
    gb_fail('Unknown action.', 400);
}