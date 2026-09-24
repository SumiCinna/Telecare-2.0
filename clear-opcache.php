<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared.";
} else {
    echo "opcache_reset() is not available/enabled on this server.";
}