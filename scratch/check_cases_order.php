<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$cases = $db->query("SELECT c.id, c.case_number, c.priority, c.status, c.submitted_at, c.updated_at
    FROM cases c
    WHERE c.status NOT IN ('resolved','closed')
    ORDER BY FIELD(c.priority,'high','medium','low'), c.submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cases as $i => $c) {
    echo "#$i: Case {$c['case_number']} (ID: {$c['id']}) - Priority: {$c['priority']}, Status: {$c['status']}, Submitted: {$c['submitted_at']}, Updated: {$c['updated_at']}\n";
}

