<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$cols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);

