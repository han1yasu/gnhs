<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$count = $db->exec("UPDATE notifications SET type='appeal' WHERE title LIKE '%Appealed%' OR title LIKE '%appeal%' OR message LIKE '%appeal/follow-up%'");
echo "Updated $count notifications to type='appeal'\n";

