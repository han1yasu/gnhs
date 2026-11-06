<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$cases = $db->query("SELECT id, case_number, status, is_follow_up, follow_up_msg, submitted_at, updated_at FROM cases WHERE follow_up_msg IS NOT NULL OR is_follow_up=1")->fetchAll(PDO::FETCH_ASSOC);
print_r($cases);

