<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();

echo "--- CASES COLUMNS ---\n";
$cols = $db->query("SHOW COLUMNS FROM cases")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $cols) . "\n\n";

echo "--- COUNSELING_SESSIONS COLUMNS ---\n";
$cols2 = $db->query("SHOW COLUMNS FROM counseling_sessions")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $cols2) . "\n\n";

echo "--- CASE_NOTES COLUMNS ---\n";
$cols3 = $db->query("SHOW COLUMNS FROM case_notes")->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $cols3) . "\n\n";
