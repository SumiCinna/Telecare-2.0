<?php
$urls = [
    "https://cdn.jsdelivr.net/npm/@metered-ca/realtime/dist/metered-peer.min.js",
    "https://cdn.jsdelivr.net/npm/@metered-ca/realtime",
    "https://cdn.jsdelivr.net/npm/@metered-ca/realtime/dist/index.min.js",
    "https://cdn.jsdelivr.net/npm/@metered-ca/realtime/umd/metered-peer.min.js",
];

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOBODY => true,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "$http - $url\n";
}