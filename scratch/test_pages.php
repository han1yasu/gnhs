<?php
$pages = [
    'pages/admin-dashboard.php',
    'pages/admin-cases.php',
    'pages/admin-chats.php',
    'pages/admin-followups.php',
    'pages/admin-referrals.php',
    'pages/admin-referrals-chat.php',
    'pages/admin-archive.php',
    'pages/admin-sessions.php',
    'pages/admin-analytics.php',
    'pages/admin-users.php',
    'pages/admin-reset-requests.php',
    'pages/admin-2fa-requests.php',
];

foreach ($pages as $p) {
    $code = 'require "includes/config.php"; startSession(); $_SERVER["REQUEST_METHOD"]="GET"; $db = getDB(); $_SESSION["user"] = $db->query("SELECT * FROM users WHERE role=\'admin\' LIMIT 1")->fetch(); $_SESSION["last_activity"] = time(); ob_start(); include "' . $p . '"; $out = ob_get_clean(); if (strpos($out, "Warning:") !== false || strpos($out, "Notice:") !== false || strpos($out, "Fatal error") !== false) { echo "ERR: " . substr($out, 0, 300); exit(1); } else { echo "OK"; }';
    $cmd = 'C:\\xampp\\php\\php.exe -r ' . escapeshellarg($code);
    exec($cmd, $output, $returnCode);
    if ($returnCode === 0) {
        echo "OK: $p rendered cleanly (0 warnings)\n";
    } else {
        echo "FAIL: $p produced output:\n" . implode("\n", $output) . "\n";
    }
    $output = [];
}

