<?php
require_once __DIR__ . '/../includes/config.php';

// Set error reporting to maximum
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup mock session for admin (Guidance Counselor)
startSession();
$db = getDB();
$counselor = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1")->fetch();
$_SESSION['user'] = $counselor;
$_SESSION['last_activity'] = time();

ob_start();
include __DIR__ . '/../pages/admin-users.php';
$output = ob_get_clean();

// Check if there are any "Warning:" or "Notice:" in output
if (strpos($output, 'Warning:') !== false || strpos($output, 'Notice:') !== false || strpos($output, 'Undefined variable') !== false) {
    echo "FAILED: Warnings found in output!\n";
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (strpos($line, 'Warning:') !== false || strpos($line, 'Notice:') !== false) {
            echo "  $line\n";
        }
    }
} else {
    echo "SUCCESS: admin-users.php rendered with 0 PHP warnings or notices!\n";
}

// Check that user cards have data-theme dark overrides
if (strpos($output, '[data-theme="dark"] .user-card') !== false) {
    echo "SUCCESS: Embedded dark mode override for .user-card confirmed in HTML!\n";
} else {
    echo "FAILED: Embedded dark mode override missing from HTML!\n";
}

