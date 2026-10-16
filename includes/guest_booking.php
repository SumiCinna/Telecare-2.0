<?php
// includes/guest_booking.php
// Everything the no-account ("guest") booking flow needs:
//   - schema (guest_bookings, guest_files, guest_mail_log, patients.is_guest)
//   - EmailJS sender with MULTIPLE EmailJS accounts (one route per email type)
//   - email verification codes, guest patient row, appointment creation
//   - e-mail notifications: room link, summary, prescription / lab request / medical certificate
//
// Safe to require_once from the root, /private_telecare and /doctor.
// All function names are prefixed guest_ so nothing collides with the existing helpers.

date_default_timezone_set('Asia/Manila');

$__envl = __DIR__ . '/env_loader.php';
if (is_file($__envl)) { require_once $__envl; }

if (!defined('GUEST_PAY_WINDOW_MIN')) {
    define('GUEST_PAY_WINDOW_MIN', 15);   // minutes the guest has to pay after verifying
    define('GUEST_OTP_TTL_MIN',    10);   // minutes the e-mail code stays valid
    define('GUEST_OTP_MAX_TRIES',   5);   // wrong codes allowed per code
    define('GUEST_TOKEN_DAYS',     45);   // how long e-mailed links keep working
}

/* ───────────────────────── basics ───────────────────────── */

function guest_env(string $key, string $default = ''): string {
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return (is_string($v) && trim($v) !== '') ? trim($v) : $default;
}

/** Site root URL without a trailing slash (used inside e-mailed links). */
function guest_base_url(): string {
    $env = guest_env('APP_URL') ?: guest_env('BASE_URL');
    if ($env !== '') return rtrim($env, '/');
    if (defined('BASE_URL') && BASE_URL) return rtrim((string)BASE_URL, '/');
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir    = preg_replace('#/(doctor|private_telecare|booking|auth|includes)(/.*)?$#', '', $dir);
    $dir    = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function guest_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function guest_clean(string $v, int $max = 255): string {
    $v = preg_replace('/\s+/u', ' ', trim($v));
    return mb_substr($v, 0, $max);
}

/* ───────────────────────── schema ───────────────────────── */

function guest_ensure_schema(mysqli $conn): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $r = $conn->query("SHOW COLUMNS FROM patients LIKE 'is_guest'");
        if ($r && $r->num_rows === 0) {
            $conn->query("ALTER TABLE patients ADD COLUMN is_guest TINYINT(1) NOT NULL DEFAULT 0");
        }
        $conn->query("CREATE TABLE IF NOT EXISTS guest_bookings (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            patient_id INT NOT NULL,
            access_token CHAR(64) NOT NULL,
            first_name VARCHAR(80) NOT NULL,
            middle_name VARCHAR(80) NULL,
            last_name VARCHAR(80) NOT NULL,
            full_name VARCHAR(200) NOT NULL,
            date_of_birth DATE NULL,
            sex VARCHAR(10) NULL,
            address VARCHAR(255) NULL,
            phone VARCHAR(30) NULL,
            email VARCHAR(150) NOT NULL,
            complaint VARCHAR(500) NULL,
            room_emailed_at DATETIME NULL,
            summary_emailed_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gb_appt (appointment_id),
            UNIQUE KEY uniq_gb_token (access_token),
            KEY idx_gb_patient (patient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $conn->query("CREATE TABLE IF NOT EXISTS guest_files (
            id INT NOT NULL AUTO_INCREMENT,
            appointment_id INT NOT NULL,
            kind VARCHAR(30) NOT NULL,
            label VARCHAR(190) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gf_appt (appointment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $conn->query("CREATE TABLE IF NOT EXISTS guest_mail_log (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(150) NOT NULL,
            ip VARCHAR(45) NULL,
            kind VARCHAR(20) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_gm_email (email, created_at),
            KEY idx_gm_ip (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $conn->query("CREATE TABLE IF NOT EXISTS doctor_schedule_settings (
            doctor_id INT NOT NULL,
            consultation_duration SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            appointment_interval SMALLINT UNSIGNED NOT NULL DEFAULT 5,
            break_start TIME NULL,
            break_end TIME NULL,
            consultation_types VARCHAR(255) NOT NULL DEFAULT 'Teleconsult,In-person',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (doctor_id),
            CONSTRAINT doctor_schedule_settings_doctor_fk FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        error_log('guest_ensure_schema: ' . $e->getMessage());
    }
}

/* ───────────────────────── EmailJS (multi-account) ─────────────────────────
 * Only TWO templates are needed in total (a free EmailJS account holds 2):
 *   OTP     the verification code
 *   NOTICE  one generic template used for: room link, summary, prescription, lab request, medical certificate
 * Each channel is routed to one EmailJS ACCOUNT (default A for both). If you ever add a second account,
 * set EMAILJS_GUEST_ROUTE_NOTICE=B and fill the EMAILJS_GUEST_B_* keys.
 *
 * .env keys (see env.guest.example):
 *   EMAILJS_GUEST_<ACCT>_SERVICE_ID / _PUBLIC_KEY / _PRIVATE_KEY
 *   EMAILJS_GUEST_<ACCT>_TEMPLATE_OTP / _TEMPLATE_NOTICE
 *   EMAILJS_GUEST_ROUTE_<CHANNEL>=A|B|C   (optional)
 * Separate from the EMAILJS_* keys that includes/emailjs.php uses.
 */
function guest_emailjs_config(string $channel): array {
    $ch      = strtoupper($channel);
    $default = 'A';
    $acct    = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', guest_env("EMAILJS_GUEST_ROUTE_$ch", $default)));
    return [
        'account'     => $acct,
        'service_id'  => guest_env("EMAILJS_GUEST_{$acct}_SERVICE_ID"),
        'public_key'  => guest_env("EMAILJS_GUEST_{$acct}_PUBLIC_KEY"),
        'private_key' => guest_env("EMAILJS_GUEST_{$acct}_PRIVATE_KEY"),
        'template_id' => guest_env("EMAILJS_GUEST_{$acct}_TEMPLATE_$ch"),
    ];
}

/** Sends one e-mail through the EmailJS REST API using the account routed to $channel. */
function guest_send_email(string $channel, string $toEmail, string $toName, array $params): array {
    $c = guest_emailjs_config($channel);
    foreach (['service_id', 'public_key', 'private_key', 'template_id'] as $k) {
        if ($c[$k] === '') {
            $why = "EmailJS $channel: missing EMAILJS_GUEST_" . $c['account'] . '_' . strtoupper($k) . ($k === 'template_id' ? "/_TEMPLATE_$channel" : '');
            error_log('guest_send_email: ' . $why);
            return ['ok' => false, 'error' => $why];
        }
    }
    $base = guest_base_url();
    $payload = [
        'service_id'      => $c['service_id'],
        'template_id'     => $c['template_id'],
        'user_id'         => $c['public_key'],
        'accessToken'     => $c['private_key'],
        'template_params' => array_merge([
            'to_email'  => $toEmail,
            'to_name'   => $toName,
            'reply_to'  => $toEmail,
            'site_name' => 'TELE-CARE',
            'site_url'  => $base,
            'year'      => date('Y'),
        ], $params),
    ];
    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Origin: ' . $base],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($code === 200) return ['ok' => true];
    error_log("guest_send_email[$channel/" . $c['account'] . "]: HTTP $code " . ($err ?: substr((string)$resp, 0, 200)));
    return ['ok' => false, 'error' => $err ?: (string)$resp, 'http' => $code];
}

/* ───────────────────────── throttling + codes ───────────────────────── */

/** Max 4 codes per e-mail / 30 min and 10 per IP / hour (protects the 200-email EmailJS quota). */
function guest_mail_allowed(mysqli $conn, string $email, string $kind = 'otp'): bool {
    guest_ensure_schema($conn);
    $conn->query("DELETE FROM guest_mail_log WHERE created_at < (NOW() - INTERVAL 2 DAY)");
    $st = $conn->prepare("SELECT COUNT(*) c FROM guest_mail_log WHERE email=? AND kind=? AND created_at > (NOW() - INTERVAL 30 MINUTE)");
    $st->bind_param('ss', $email, $kind); $st->execute();
    if ((int)$st->get_result()->fetch_assoc()['c'] >= 4) return false;
    $ip = guest_ip();
    $st = $conn->prepare("SELECT COUNT(*) c FROM guest_mail_log WHERE ip=? AND kind=? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $st->bind_param('ss', $ip, $kind); $st->execute();
    return (int)$st->get_result()->fetch_assoc()['c'] < 10;
}

function guest_mail_logged(mysqli $conn, string $email, string $kind = 'otp'): void {
    $ip = guest_ip();
    $st = $conn->prepare("INSERT INTO guest_mail_log (email, ip, kind) VALUES (?,?,?)");
    $st->bind_param('sss', $email, $ip, $kind); $st->execute();
}

function guest_mask_email(string $e): string {
    [$u, $d] = array_pad(explode('@', $e, 2), 2, '');
    $u = mb_strlen($u) <= 2 ? mb_substr($u, 0, 1) . '*' : mb_substr($u, 0, 2) . str_repeat('*', max(1, mb_strlen($u) - 2));
    return $u . '@' . $d;
}

/** Creates a 6-digit code, keeps only its hash in the session and e-mails it. */
function guest_issue_otp(mysqli $conn, string $email, string $name): array {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $salt = bin2hex(random_bytes(8));
    $_SESSION['gb_otp'] = [
        'email'   => $email,
        'salt'    => $salt,
        'hash'    => hash('sha256', $salt . $code),
        'expires' => time() + GUEST_OTP_TTL_MIN * 60,
        'tries'   => 0,
        'sent_at' => time(),
    ];
    $res = guest_send_email('OTP', $email, $name, [
        'subject'      => 'Your TELE-CARE verification code: ' . $code,
        'otp_code'     => $code,
        'otp_minutes'  => (string)GUEST_OTP_TTL_MIN,
    ]);
    if (!$res['ok']) { unset($_SESSION['gb_otp']); }
    else { guest_mail_logged($conn, $email, 'otp'); }
    return $res;
}

/** Returns ['ok'=>bool,'error'=>string]. */
function guest_check_otp(string $email, string $code): array {
    $o = $_SESSION['gb_otp'] ?? null;
    if (!$o || ($o['email'] ?? '') !== $email) return ['ok' => false, 'error' => 'Request a new code.'];
    if (time() > (int)$o['expires'])           { unset($_SESSION['gb_otp']); return ['ok' => false, 'error' => 'That code has expired. Request a new one.']; }
    if ((int)$o['tries'] >= GUEST_OTP_MAX_TRIES) { unset($_SESSION['gb_otp']); return ['ok' => false, 'error' => 'Too many attempts. Request a new code.']; }
    $_SESSION['gb_otp']['tries']++;
    if (!hash_equals((string)$o['hash'], hash('sha256', $o['salt'] . preg_replace('/\D/', '', $code)))) {
        $left = GUEST_OTP_MAX_TRIES - (int)$_SESSION['gb_otp']['tries'];
        return ['ok' => false, 'error' => 'Incorrect code.' . ($left > 0 ? " $left attempt" . ($left === 1 ? '' : 's') . ' left.' : '')];
    }
    unset($_SESSION['gb_otp']);
    return ['ok' => true];
}

/* ───────────────────────── doctors / schedule / slots ───────────────────────── */

function guest_schedule_settings(mysqli $conn, int $doctor_id): array {
    $s = ['consultation_duration' => 60, 'appointment_interval' => 30, 'break_start' => null, 'break_end' => null];
    $st = $conn->prepare('SELECT consultation_duration, appointment_interval, break_start, break_end FROM doctor_schedule_settings WHERE doctor_id=?');
    $st->bind_param('i', $doctor_id); $st->execute();
    if ($row = $st->get_result()->fetch_assoc()) $s = array_merge($s, $row);
    return $s;
}

/** Cancels reservations whose payment window (15 min) ran out so the slot is free again. */
function guest_expire_stale(mysqli $conn): void {
    $m = (int)GUEST_PAY_WINDOW_MIN;
    $conn->query("UPDATE appointments SET status='Cancelled' WHERE status='Pending' AND payment_status='Unpaid' AND created_at < (NOW() - INTERVAL $m MINUTE)");
}

/** [ok(bool), message]. Same rules as the logged-in wizard + a real overlap check. */
function guest_slot_valid(mysqli $conn, int $doctor_id, string $date, string $time): array {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    $t = substr($time, 0, 5);
    if (!$d || $d->format('Y-m-d') !== $date || !preg_match('/^\d{2}:\d{2}$/', $t)) return [false, 'Pick a valid date and time.'];
    if ($date < date('Y-m-d') || ($date === date('Y-m-d') && $t <= date('H:i'))) return [false, 'That time has already passed.'];

    $cfg   = guest_schedule_settings($conn, $doctor_id);
    $dur   = max(5, (int)$cfg['consultation_duration']) * 60;
    $start = strtotime("$date $t");
    $end   = $start + $dur;
    $bs    = $cfg['break_start'] ? strtotime($date . ' ' . substr($cfg['break_start'], 0, 5)) : null;
    $be    = $cfg['break_end']   ? strtotime($date . ' ' . substr($cfg['break_end'], 0, 5))   : null;
    if ($bs && $be && $start < $be && $end > $bs) return [false, 'That time falls in the doctor\'s break.'];

    $ok  = false;
    $day = $d->format('l');
    $q   = $conn->prepare("SELECT start_time, end_time FROM doctor_schedules WHERE doctor_id=? AND day_of_week=?");
    $q->bind_param('is', $doctor_id, $day); $q->execute();
    $rs = $q->get_result();
    while ($s = $rs->fetch_assoc()) {
        if ($t >= substr($s['start_time'], 0, 5) && $end <= strtotime("$date " . substr($s['end_time'], 0, 5))) { $ok = true; break; }
    }
    if (!$ok) return [false, 'The doctor is not available at that time.'];

    guest_expire_stale($conn);
    $q = $conn->prepare("SELECT appointment_time FROM appointments WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('Cancelled')");
    $q->bind_param('is', $doctor_id, $date); $q->execute();
    $rs = $q->get_result();
    while ($b = $rs->fetch_assoc()) {
        $bst = strtotime("$date " . substr($b['appointment_time'], 0, 5));
        if ($start < $bst + $dur && $end > $bst) return [false, 'That time slot was just taken. Please pick another.'];
    }
    return [true, ''];
}

/* ───────────────────────── guest patient row ───────────────────────── */

/**
 * Creates (or refreshes) the patient row that represents this guest, so every existing page
 * (call room, doctor pages, documents, summaries) keeps working unchanged.
 * Returns ['ok'=>true,'patient'=>row] | ['ok'=>false,'error'=>'registered'|'db'].
 * The row is introspected, so it adapts to whatever columns your patients table has.
 */
function guest_upsert_patient(mysqli $conn, array $f): array {
    guest_ensure_schema($conn);
    $cols = [];
    $r = $conn->query('SHOW COLUMNS FROM patients');
    while ($r && ($row = $r->fetch_assoc())) $cols[strtolower($row['Field'])] = $row;

    $st = $conn->prepare('SELECT id, is_guest FROM patients WHERE email=? LIMIT 1');
    $st->bind_param('s', $f['email']); $st->execute();
    $existing = $st->get_result()->fetch_assoc();
    if ($existing && !(int)$existing['is_guest']) return ['ok' => false, 'error' => 'registered'];

    $now = date('Y-m-d H:i:s');
    $pw  = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);   // nobody can log in with it
    $map = [
        'full_name' => $f['full_name'], 'name' => $f['full_name'],
        'first_name' => $f['first_name'], 'middle_name' => $f['middle_name'], 'last_name' => $f['last_name'],
        'email' => $f['email'],
        'phone_number' => $f['phone'], 'phone' => $f['phone'], 'mobile' => $f['phone'], 'mobile_number' => $f['phone'], 'contact_number' => $f['phone'],
        'date_of_birth' => $f['dob'], 'dob' => $f['dob'], 'birthdate' => $f['dob'],
        'gender' => $f['sex'], 'sex' => $f['sex'],
        'home_address' => $f['address'], 'address' => $f['address'],
        'is_guest' => 1,
        'email_verified' => 1, 'is_verified' => 1, 'verified' => 1, 'email_verified_at' => $now,
    ];
    $insertOnly = ['password' => $pw, 'password_hash' => $pw];

    $enumPick = function (array $col, string $want): string {
        if (preg_match_all("/'((?:[^']|'')*)'/", (string)$col['Type'], $m)) {
            foreach ($m[1] as $opt) if (strcasecmp($opt, $want) === 0) return $opt;
            return str_replace("''", "'", $m[1][0]);
        }
        return $want;
    };
    $val = function (string $name, $v) use ($cols, $enumPick) {
        $c = $cols[$name];
        if (stripos((string)$c['Type'], 'enum') === 0) return $enumPick($c, (string)$v);
        return $v;
    };

    try {
        if ($existing) {
            $sets = []; $vals = []; $types = '';
            foreach ($map as $k => $v) {
                if ($k === 'is_guest' || !isset($cols[$k]) || $k === 'email') continue;
                $sets[] = "`$k` = ?"; $vals[] = $val($k, $v); $types .= 's';
            }
            $id = (int)$existing['id'];
            if ($sets) {
                $vals[] = $id; $types .= 'i';
                $up = $conn->prepare('UPDATE patients SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $up->bind_param($types, ...$vals);
                $up->execute();
            }
        } else {
            $fields = []; $vals = [];
            foreach ($map + $insertOnly as $k => $v) {
                if (!isset($cols[$k])) continue;
                $fields[$k] = $val($k, $v);
            }
            if (isset($cols['status']) && !isset($fields['status'])) $fields['status'] = $enumPick($cols['status'], 'active');
            // Any other required column without a default gets a neutral value.
            foreach ($cols as $name => $c) {
                if (isset($fields[$name]) || $c['Null'] === 'YES' || $c['Default'] !== null || stripos((string)$c['Extra'], 'auto_increment') !== false) continue;
                $ty = strtolower((string)$c['Type']);
                if (preg_match('/int|decimal|float|double|bit/', $ty))          $fields[$name] = 0;
                elseif (strpos($ty, 'enum') === 0)                               $fields[$name] = $enumPick($c, '');
                elseif (strpos($ty, 'datetime') === 0 || strpos($ty, 'timestamp') === 0) $fields[$name] = $now;
                elseif (strpos($ty, 'date') === 0)                               $fields[$name] = date('Y-m-d');
                elseif (strpos($ty, 'json') === 0)                               $fields[$name] = '{}';
                else                                                             $fields[$name] = '';
            }
            $names = array_keys($fields);
            $ins = $conn->prepare('INSERT INTO patients (`' . implode('`,`', $names) . '`) VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')');
            $ins->bind_param(str_repeat('s', count($names)), ...array_values($fields));
            $ins->execute();
            $id = (int)$conn->insert_id;
        }
        $p = $conn->query('SELECT * FROM patients WHERE id = ' . (int)$id)->fetch_assoc();
        return ['ok' => true, 'patient' => $p];
    } catch (Throwable $e) {
        error_log('guest_upsert_patient: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'db'];
    }
}

/** Signs the browser in as the guest patient (so pay.php, the call room, etc. work as for any patient). */
function guest_login_session(array $patient): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    session_regenerate_id(true);
    $_SESSION['patient_id']    = (int)$patient['id'];
    $_SESSION['patient_name']  = (string)($patient['full_name'] ?? '');
    $_SESSION['patient_email'] = (string)($patient['email'] ?? '');
    $_SESSION['role']          = 'patient';
    $_SESSION['guest_mode']    = 1;
}

function guest_logout_session(): void {
    foreach (['patient_id', 'patient_name', 'patient_email', 'role', 'guest_mode', 'booking', 'pay_prefer', 'pay_csrf', 'gb', 'gb_otp', 'gb_pending', 'gb_appt_id', 'gb_verified', 'gb_rec_hist'] as $k) unset($_SESSION[$k]);
}

/* ───────────────────────── appointment ───────────────────────── */

/** Creates the Pending/Unpaid appointment (same columns as process_booking.php) under a slot lock. */
function guest_create_appointment(mysqli $conn, int $patient_id, array $b): array {
    $doctor_id = (int)$b['doctor_id'];
    $date      = $b['date'];
    $time      = substr($b['time'], 0, 5) . ':00';

    $dq = $conn->prepare("SELECT id, department FROM doctors WHERE id=? AND status='active'");
    $dq->bind_param('i', $doctor_id); $dq->execute();
    $doc = $dq->get_result()->fetch_assoc();
    if (!$doc) return ['ok' => false, 'error' => 'That doctor is no longer available.', 'back' => 'doctor'];

    $lock = 'gb_slot_' . $doctor_id . '_' . $date;
    $got  = $conn->query("SELECT GET_LOCK('" . $conn->real_escape_string($lock) . "', 6) AS l")->fetch_assoc();
    try {
        [$ok, $msg] = guest_slot_valid($conn, $doctor_id, $date, $time);
        if (!$ok) return ['ok' => false, 'error' => $msg, 'back' => 'schedule'];

        $reference = null;
        for ($i = 0; $i < 5 && !$reference; $i++) {
            $cand = 'APT-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $c = $conn->prepare('SELECT id FROM appointments WHERE reference_no=? LIMIT 1');
            $c->bind_param('s', $cand); $c->execute();
            if (!$c->get_result()->fetch_assoc()) $reference = $cand;
        }
        if (!$reference) $reference = 'APT-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        $type = 'Teleconsult'; $notes = ''; $status = 'Pending'; $pay = 'Unpaid';
        $reason = mb_substr($b['complaint'], 0, 500);
        $dept   = (string)$doc['department'];
        $nullS  = null;
        $stmt = $conn->prepare(
            "INSERT INTO appointments
                (patient_id, doctor_id, appointment_date, appointment_time, type, department,
                 notes, reason, attachment_path, attachment_type, attachment_ocr_text,
                 status, payment_status, reference_no)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('iissssssssssss', $patient_id, $doctor_id, $date, $time, $type, $dept, $notes, $reason, $nullS, $nullS, $nullS, $status, $pay, $reference);
        if (!$stmt->execute()) {
            error_log('guest_create_appointment: ' . $stmt->error);
            return ['ok' => false, 'error' => 'Could not reserve the appointment. Please try again.', 'back' => 'schedule'];
        }
        return ['ok' => true, 'appt_id' => (int)$conn->insert_id, 'reference' => $reference];
    } finally {
        $conn->query("SELECT RELEASE_LOCK('" . $conn->real_escape_string($lock) . "')");
    }
}

function guest_new_token(): string { return bin2hex(random_bytes(32)); }

/* ───────────────────────── lookups ───────────────────────── */

function guest_is_guest_appointment(mysqli $conn, int $appt_id): bool {
    try {
        guest_ensure_schema($conn);
        $r = $conn->query('SELECT 1 FROM guest_bookings WHERE appointment_id = ' . (int)$appt_id . ' LIMIT 1');
        return (bool)($r && $r->num_rows);
    } catch (Throwable $e) { return false; }
}

/** Booking + appointment + doctor by e-mailed token (valid GUEST_TOKEN_DAYS days). */
function guest_booking_by_token(mysqli $conn, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    guest_ensure_schema($conn);
    $days = (int)GUEST_TOKEN_DAYS;
    $st = $conn->prepare("SELECT g.*, a.appointment_date, a.appointment_time, a.status AS appt_status, a.payment_status, a.reference_no,
                                 a.consultation_summary, a.summary_reviewed_at,
                                 d.full_name AS doctor_name, d.specialty
                          FROM guest_bookings g
                          JOIN appointments a ON a.id = g.appointment_id
                          JOIN doctors d ON d.id = a.doctor_id
                          WHERE g.access_token = ? AND g.created_at > (NOW() - INTERVAL $days DAY) LIMIT 1");
    $st->bind_param('s', $token); $st->execute();
    return $st->get_result()->fetch_assoc() ?: null;
}

function guest_booking_by_appt(mysqli $conn, int $appt_id): ?array {
    guest_ensure_schema($conn);
    $st = $conn->prepare("SELECT g.*, a.appointment_date, a.appointment_time, a.status AS appt_status, a.payment_status, a.reference_no,
                                 d.full_name AS doctor_name, d.specialty
                          FROM guest_bookings g
                          JOIN appointments a ON a.id = g.appointment_id
                          JOIN doctors d ON d.id = a.doctor_id
                          WHERE g.appointment_id = ? LIMIT 1");
    $st->bind_param('i', $appt_id); $st->execute();
    return $st->get_result()->fetch_assoc() ?: null;
}

function guest_link(string $token, string $go, array $extra = []): string {
    return guest_base_url() . '/guest_access.php?' . http_build_query(['t' => $token, 'go' => $go] + $extra);
}

/* ───────────────────────── notifications (all use the one NOTICE template) ───────────────────────── */

function guest_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

/** Detail table for the e-mail body (HTML, values escaped). */
function guest_rows_html(array $rows): string {
    $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e6eef7;">';
    foreach ($rows as $k => $v) {
        if ($v === '' || $v === null) continue;
        $h .= '<tr><td style="padding:11px 0;border-bottom:1px solid #e6eef7;font-size:13px;color:#7b8794;width:38%;">' . guest_h((string)$k)
            . '</td><td style="padding:11px 0;border-bottom:1px solid #e6eef7;font-size:13px;font-weight:700;color:#193f72;">' . guest_h((string)$v) . '</td></tr>';
    }
    return $h . '</table>';
}

/** Sends the generic NOTICE e-mail. */
function guest_send_notice(array $g, string $subject, string $heading, string $message, array $rows, string $btnLabel, string $btnLink, string $footnote = ''): array {
    return guest_send_email('NOTICE', $g['email'], $g['full_name'], [
        'subject'      => $subject,
        'heading'      => $heading,
        'message'      => $message,
        'details_html' => guest_rows_html($rows),
        'button_label' => $btnLabel,
        'button_link'  => $btnLink,
        'footnote'     => $footnote,
    ]);
}

/** Booking confirmed → e-mail with the consultation room link. Sent once per booking. */
function guest_notify_booking_confirmed(mysqli $conn, int $appt_id, string $method = '', string $receipt = ''): bool {
    try {
        $g = guest_booking_by_appt($conn, $appt_id);
        if (!$g || !empty($g['room_emailed_at'])) return false;
        $when  = strtotime($g['appointment_date'] . ' ' . $g['appointment_time']);
        $label = ['gcash' => 'GCash', 'yakap' => 'PhilHealth YAKAP', 'hmo' => 'HMO'][strtolower($method)] ?? ($method ?: 'Paid');
        $note  = in_array(strtolower($method), ['yakap', 'hmo'], true)
            ? 'Your ' . $label . ' details are being verified by the clinic. The room link below stays the same.'
            : 'Your payment was received. See you at your appointment.';
        $res = guest_send_notice($g,
            'Your TELE-CARE consultation room · ' . date('M j, Y', $when) . ' ' . date('g:i A', $when),
            'Your consultation is booked',
            'Hi ' . $g['full_name'] . ', ' . $note,
            ['Doctor' => 'Dr. ' . $g['doctor_name'], 'Specialty' => (string)($g['specialty'] ?? ''), 'Date' => date('l, F j, Y', $when),
             'Time' => date('g:i A', $when), 'Reference' => (string)$g['reference_no'], 'Payment' => $label, 'Receipt' => $receipt],
            'Join consultation room', guest_link($g['access_token'], 'room'),
            'The room opens at ' . date('g:i A', $when - 900) . ', 15 minutes before your appointment. Allow camera and microphone when asked.');
        if ($res['ok']) $conn->query('UPDATE guest_bookings SET room_emailed_at = NOW() WHERE appointment_id = ' . (int)$appt_id);
        return $res['ok'];
    } catch (Throwable $e) { error_log('guest_notify_booking_confirmed: ' . $e->getMessage()); return false; }
}

/** Doctor published the summary → e-mail the secure link. Sent once per booking. */
function guest_notify_summary(mysqli $conn, int $appt_id): bool {
    try {
        $g = guest_booking_by_appt($conn, $appt_id);
        if (!$g || !empty($g['summary_emailed_at'])) return false;
        $when = strtotime($g['appointment_date'] . ' ' . $g['appointment_time']);
        $res = guest_send_notice($g,
            'Your teleconsultation summary · Dr. ' . $g['doctor_name'],
            'Your consultation summary is ready',
            'Hi ' . $g['full_name'] . ', Dr. ' . $g['doctor_name'] . ' reviewed and published the summary of your teleconsultation.',
            ['Doctor' => 'Dr. ' . $g['doctor_name'], 'Date' => date('l, F j, Y', $when), 'Time' => date('g:i A', $when), 'Reference' => (string)$g['reference_no']],
            'Open my summary', guest_link($g['access_token'], 'summary'),
            'Keep this email: the link opens your summary anytime, no login needed.');
        if ($res['ok']) $conn->query('UPDATE guest_bookings SET summary_emailed_at = NOW() WHERE appointment_id = ' . (int)$appt_id);
        return $res['ok'];
    } catch (Throwable $e) { error_log('guest_notify_summary: ' . $e->getMessage()); return false; }
}

/** Doctor issued a prescription / lab request / medical certificate → e-mail the secure link. */
function guest_notify_document(mysqli $conn, int $appt_id, string $doc_type, string $label, string $file_path): bool {
    try {
        $g = guest_booking_by_appt($conn, $appt_id);
        if (!$g) return false;
        $kind = ['prescription' => 'Prescription', 'lab_request' => 'Laboratory Request', 'med_cert' => 'Medical Certificate'][$doc_type] ?? 'Document';
        $st = $conn->prepare('INSERT INTO guest_files (appointment_id, kind, label, file_path) VALUES (?,?,?,?)');
        $st->bind_param('isss', $appt_id, $doc_type, $label, $file_path); $st->execute();
        $fid  = (int)$conn->insert_id;
        $when = strtotime($g['appointment_date'] . ' ' . $g['appointment_time']);
        $res = guest_send_notice($g,
            'Your ' . $kind . ' from Dr. ' . $g['doctor_name'],
            'Your ' . $kind . ' is ready',
            'Hi ' . $g['full_name'] . ', Dr. ' . $g['doctor_name'] . ' issued a document after your consultation.',
            ['Document' => $label, 'Doctor' => 'Dr. ' . $g['doctor_name'], 'Consultation' => date('l, F j, Y', $when), 'Reference' => (string)$g['reference_no']],
            'Open ' . $kind, guest_link($g['access_token'], 'file', ['id' => $fid]),
            'Keep this email: the link opens the PDF anytime, no login needed.');
        return $res['ok'];
    } catch (Throwable $e) { error_log('guest_notify_document: ' . $e->getMessage()); return false; }
}
