<?php
// ── Database Connection ──
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'DREAMTEAM');
define('DB_NAME', 'telecare');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>