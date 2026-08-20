<?php

define('BASE_URL', $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?: 'http://localhost:3000');

// Load key=value pairs from project .env so $_ENV/getenv are available in Apache/XAMPP.
if (!function_exists('telecare_load_env')) {
    function telecare_load_env(string $envPath): void
    {
        if (!is_file($envPath) || !is_readable($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Remove wrapping quotes if present.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key === '') {
                continue;
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
}

telecare_load_env(__DIR__ . '/../.env');

// ── Database Connection ──
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'DREAMTEAM');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'telecare');
define('DB_PORT', $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306);
define('DB_SSL_CA', $_ENV['DB_SSL_CA'] ?? getenv('DB_SSL_CA') ?: '');

$conn = mysqli_init();

if (DB_SSL_CA && is_file(DB_SSL_CA)) {
    mysqli_ssl_set($conn, null, null, DB_SSL_CA, null, null);
    $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, null, MYSQLI_CLIENT_SSL);
} else {
    $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>