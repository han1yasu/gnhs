<?php
require_once __DIR__ . '/../includes/config.php';
error_reporting(E_ALL);
ini_set('display_errors', '1');

startSession();
$_SERVER['REQUEST_METHOD'] = 'GET';
$db = getDB();
$counselor = $db->query("SELECT * FROM users WHERE role='admin' LIMIT 1")->fetch();
$_SESSION['user'] = $counselor;
$_SESSION['last_activity'] = time();

$target = $argv[1] ?? '';
if (!file_exists($target)) {
    die("File not found: $target\n");
}

ob_start();
include $target;
$out = ob_get_clean();

if (strpos($out, 'Warning:') !== false || strpos($out, 'Notice:') !== false || strpos($out, 'Fatal error') !== false) {
    echo "ERROR in $target:\n";
    $lines = explode("\n", $out);
    foreach ($lines as $l) {
        if (strpos($l, 'Warning:') !== false || strpos($l, 'Notice:') !== false || strpos($l, 'Fatal error') !== false) {
            echo "  $l\n";
        }
    }
    exit(1);
} else {
    echo "OK: $target rendered cleanly (0 warnings)\n";
    exit(0);
}

