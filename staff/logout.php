<?php
session_start();
session_unset();
session_destroy();
header("Cache-Control: no-store");
header('Location: login.php');
exit;