<?php
// includes/env_loader.php
function load_env(string $path): void {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$name, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        $value = trim($value, "\"'");
        if ($name === '') continue;
        putenv("$name=$value");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
load_env(__DIR__ . '/../.env');