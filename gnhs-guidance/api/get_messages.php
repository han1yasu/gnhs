<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$caseId = (int)($_GET['case_id'] ?? 0);

if (!$caseId) {
    jsonOut(['success'=>false,'message'=>'Case ID required.']);
}

try {
    $db = getDB();

    // Verify access to the case
    if ($user['role'] === 'student') {
        $stmt = $db->prepare("SELECT id FROM cases WHERE id=? AND student_id=?");
        $stmt->execute([$caseId, $user['id']]);
        if (!$stmt->fetch()) jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
    } else if ($user['role'] !== 'admin') {
        jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
    }

    // Check if case is anonymous
    $caseStmt = $db->prepare("SELECT is_anonymous FROM cases WHERE id = ?");
    $caseStmt->execute([$caseId]);
    $caseRow = $caseStmt->fetch();
    $isCaseAnon = !empty($caseRow['is_anonymous']);

    // Get messages
    $stmt = $db->prepare("
        SELECT m.id, m.sender_id, m.message, m.created_at, CONCAT(u.first_name, ' ', u.last_name) as sender_name, u.role as sender_role
        FROM case_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.case_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$caseId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If viewer is counselor and case is anonymous, mask student sender name
    if ($isCaseAnon && $user['role'] === 'admin') {
        foreach ($messages as &$m) {
            if ($m['sender_role'] === 'student') {
                $m['sender_name'] = 'Anonymous Student';
            }
        }
        unset($m);
    }

    // Mark as read
    $db->prepare("UPDATE case_messages SET is_read=1 WHERE case_id=? AND sender_id != ?")
       ->execute([$caseId, $user['id']]);

    jsonOut(['success'=>true,'messages'=>$messages, 'current_user_id' => $user['id'], 'is_anonymous' => $isCaseAnon]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()], 500);
}
