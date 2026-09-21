<?php
require 'includes/config.php';
try {
    $db = getDB();
    $stmt = $db->query("SHOW TABLES LIKE 'password_resets'");
    if ($stmt->fetch()) {
        echo "Table exists.\n";
    } else {
        echo "Table does not exist.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
