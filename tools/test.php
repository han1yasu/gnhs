<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/config.php';
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
