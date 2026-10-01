<?php
// auth/app_handoff_lib.php
// Lets the Tele-Care Android app finish a Google sign-in that happened in Chrome.
// The app adds "TeleCareApp" to its user agent. Normal browsers are never affected.

if (!defined('TC_APP_STATE_SUFFIX')) define('TC_APP_STATE_SUFFIX', '-app');
if (!defined('TC_APP_PACKAGE'))      define('TC_APP_PACKAGE', 'website.telecare.app');

// Only these session keys are ever handed over to the app.
function tc_app_handoff_keys(): array
{
    return ['patient_id', 'patient_name', 'patient_email', 'doctor_id', 'doctor_name', 'google_reg'];
}

function tc_is_app_request(): bool
{
    return stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'TeleCareApp') !== false;
}

// Used by the google-*login / google-register starter pages instead of a plain random state.
function tc_app_state(): string
{
    $state = bin2hex(random_bytes(16));
    return tc_is_app_request() ? $state . TC_APP_STATE_SUFFIX : $state;
}

// True when the Google callback belongs to a sign-in that was started from the app.
function tc_callback_from_app(): bool
{
    $state = $_GET['state'] ?? '';
    $len   = strlen(TC_APP_STATE_SUFFIX);
    return is_string($state) && strlen($state) > $len && substr($state, -$len) === TC_APP_STATE_SUFFIX;
}

function tc_app_handoff_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS app_login_handoffs (
        token_hash   CHAR(64)     NOT NULL PRIMARY KEY,
        session_data MEDIUMTEXT   NOT NULL,
        redirect     VARCHAR(500) NOT NULL,
        expires_at   INT UNSIGNED NOT NULL,
        KEY idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function tc_app_handoff_create(mysqli $conn, string $redirect): ?string
{
    tc_app_handoff_table($conn);

    $data = [];
    foreach (tc_app_handoff_keys() as $k) {
        if (isset($_SESSION[$k])) {
            $data[$k] = $_SESSION[$k];
        }
    }
    $json = json_encode($data);
    if ($json === false) {
        return null;
    }

    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $expires = time() + 120; // valid for 2 minutes, single use

    $conn->query('DELETE FROM app_login_handoffs WHERE expires_at < ' . time());

    $stmt = $conn->prepare('INSERT INTO app_login_handoffs (token_hash, session_data, redirect, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('sssi', $hash, $json, $redirect, $expires);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? $token : null;
}

function tc_app_safe_redirect(string $url): string
{
    // Relative paths only (no http:, //, javascript:, or line breaks).
    if ($url === '' || preg_match('/[\r\n]/', $url) || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\\\\)#i', $url)) {
        return 'login.php';
    }
    return $url;
}

function tc_app_handoff_consume(mysqli $conn, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    tc_app_handoff_table($conn);

    $hash = hash('sha256', $token);
    $now  = time();

    $stmt = $conn->prepare('SELECT session_data, redirect FROM app_login_handoffs WHERE token_hash = ? AND expires_at >= ?');
    $stmt->bind_param('si', $hash, $now);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    // Single use: only the request that actually deletes the row may continue.
    $del = $conn->prepare('DELETE FROM app_login_handoffs WHERE token_hash = ?');
    $del->bind_param('s', $hash);
    $del->execute();
    $deleted = $del->affected_rows;
    $del->close();
    if ($deleted !== 1) {
        return null;
    }

    $session = json_decode($row['session_data'], true);
    if (!is_array($session)) {
        return null;
    }
    $session = array_intersect_key($session, array_flip(tc_app_handoff_keys()));

    return ['session' => $session, 'redirect' => tc_app_safe_redirect($row['redirect'])];
}

function tc_app_handoff_page(string $token): void
{
    $intent = 'intent://auth?token=' . $token . '#Intent;scheme=telecare;package=' . TC_APP_PACKAGE . ';end';
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Return to Tele-Care</title>'
       . '<style>body{font-family:system-ui,sans-serif;display:flex;flex-direction:column;align-items:center;'
       . 'justify-content:center;min-height:100vh;margin:0;padding:24px;text-align:center;color:#1f2937}'
       . 'a{display:inline-block;margin-top:20px;padding:14px 32px;border-radius:12px;background:#1a73e8;'
       . 'color:#fff;text-decoration:none;font-size:18px}</style></head><body>'
       . '<h2>Signed in &#10003;</h2><p>Returning to the Tele-Care app&hellip;</p>'
       . '<a href="' . $intent . '">Open Tele-Care</a>'
       . '<script>setTimeout(function(){location.href=' . json_encode($intent) . ';},300);</script>'
       . '</body></html>';
}

// Call this near the top of each Google callback (after config + session_start).
// If the sign-in started in the app, the final redirect is swapped for a hand-off back into the app.
function tc_app_handoff_enable(mysqli $conn): void
{
    if (!tc_callback_from_app()) {
        return;
    }

    // Start clean so only what THIS callback sets can be handed to the app.
    foreach (tc_app_handoff_keys() as $k) {
        unset($_SESSION[$k]);
    }

    ob_start();
    register_shutdown_function(function () use ($conn) {
        $location = null;
        foreach (headers_list() as $h) {
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
                break;
            }
        }
        if ($location === null) {
            return; // no redirect (e.g. an error message page): leave it alone
        }

        try {
            $token = tc_app_handoff_create($conn, $location);
        } catch (Throwable $e) {
            $token = null;
        }
        if ($token === null) {
            return; // fall back to normal behaviour
        }

        header_remove('Location');
        http_response_code(200);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        tc_app_handoff_page($token);
    });
}
