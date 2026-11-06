<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$cases = $db->query("SELECT id, case_number, status, is_follow_up, follow_up_msg, follow_up_date, submitted_at, updated_at FROM cases ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
print_r($cases);

