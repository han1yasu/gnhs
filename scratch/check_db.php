<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();

$hasIdx = $db->query("SHOW INDEX FROM users WHERE Key_name = 'uq_id_number'")->fetch();
if (!$hasIdx) {
    $db->exec("ALTER TABLE users ADD UNIQUE KEY uq_id_number (id_number)");
    echo "Added uq_id_number.\n";
} else {
    echo "uq_id_number already exists.\n";
}

$hasEmailIdx = $db->query("SHOW INDEX FROM users WHERE Key_name = 'uq_email'")->fetch();
if (!$hasEmailIdx) {
    $db->exec("ALTER TABLE users ADD UNIQUE KEY uq_email (email)");
    echo "Added uq_email.\n";
} else {
    echo "uq_email already exists.\n";
}

$indexes = $db->query("SHOW INDEX FROM users")->fetchAll(PDO::FETCH_ASSOC);
$indexNames = array_unique(array_column($indexes, 'Key_name'));
echo "All current indexes on users: " . implode(', ', $indexNames) . "\n";
