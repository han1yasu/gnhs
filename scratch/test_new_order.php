<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$cases = $db->query("SELECT c.id, c.case_number, c.priority, c.status, c.submitted_at, c.updated_at, c.follow_up_msg, c.is_follow_up
    FROM cases c
    WHERE c.status NOT IN ('resolved','closed')
    ORDER BY 
    (c.status = 'pending' AND c.follow_up_msg IS NOT NULL AND c.is_follow_up = 0) DESC,
    c.updated_at DESC,
    FIELD(c.priority,'high','medium','low'),
    c.submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cases as $i => $c) {
    echo "#$i: Case {$c['case_number']} (ID: {$c['id']}) - Priority: {$c['priority']}, Status: {$c['status']}, Updated: {$c['updated_at']}, FollowupMsg: " . ($c['follow_up_msg'] ? 'YES' : 'NO') . "\n";
}

