<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();

echo "=== TESTING FOLLOW-UP REOPEN & ALL CASES REFLECTION ===\n";

// 1. Check current status of C-0011
$cBefore = $db->query("SELECT id, case_number, status, is_follow_up, follow_up_msg FROM cases WHERE case_number='C-0011'")->fetch(PDO::FETCH_ASSOC);
echo "Before reopen: Case {$cBefore['case_number']}, status: {$cBefore['status']}, is_follow_up: {$cBefore['is_follow_up']}\n";

// 2. Simulate counselor clicking 'reopen_case' in admin-followups.php
$caseId = $cBefore['id'];
$db->prepare("UPDATE cases SET status='pending', is_follow_up=0, updated_at=NOW() WHERE id=?")->execute([$caseId]);
$db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'case_update')")
   ->execute([
       11,
       "Case Reopened: {$cBefore['case_number']}",
       "Your appeal for Case {$cBefore['case_number']} has been accepted by the Guidance Counselor. The case is now active under review."
   ]);

echo "Reopened case ID {$caseId} in DB...\n";

// 3. Query All Cases using the EXACT query from admin-cases.php
$cases = $db->query("SELECT c.*,
    CASE WHEN c.is_anonymous=1 THEN 'Anonymous' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
    s.grade_section
    FROM cases c LEFT JOIN users s ON c.student_id=s.id
    WHERE c.status NOT IN ('resolved','closed')
    ORDER BY
    (c.status = 'pending' AND c.follow_up_msg IS NOT NULL AND c.follow_up_msg != '' AND COALESCE(c.is_follow_up, 0) = 0) DESC,
    c.updated_at DESC,
    FIELD(c.priority,'high','medium','low'),
    c.submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "Total active cases in admin-cases.php: " . count($cases) . "\n";
echo "Top 3 cases in All Cases:\n";
for ($i = 0; $i < min(3, count($cases)); $i++) {
    $c = $cases[$i];
    $isReopened = (!empty($c['follow_up_msg']) && empty($c['is_follow_up']) && in_array($c['status'], ['pending', 'under_review', 'ongoing']));
    echo "  #$i: Case {$c['case_number']} | Status: {$c['status']} | Updated: {$c['updated_at']} | ReopenedBadge: " . ($isReopened ? 'YES [REOPENED]' : 'NO') . "\n";
}

// 4. Verify C-0011 is at the very top (#0)
if ($cases[0]['case_number'] === 'C-0011') {
    echo "✓ SUCCESS: Reopened case C-0011 is now #0 (at the very top of All Cases)!\n";
} else {
    echo "✗ FAILED: Reopened case C-0011 is not at #0 (actual: " . $cases[0]['case_number'] . ")\n";
}
