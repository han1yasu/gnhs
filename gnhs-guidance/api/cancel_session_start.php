<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$sessionId = (int)($raw['session_id'] ?? ($_POST['session_id'] ?? ($_GET['id'] ?? 0)));

if (!$sessionId) {
    jsonOut(['success' => false, 'message' => 'Session ID is required.'], 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT cs.*, c.case_number, c.subject AS case_subject, c.status AS case_status
        FROM counseling_sessions cs
        JOIN cases c ON cs.case_id = c.id
        WHERE cs.id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        jsonOut(['success' => false, 'message' => 'Session not found.'], 404);
    }

    if ($session['status'] !== 'in_progress') {
        jsonOut(['success' => false, 'message' => 'Session is not currently in progress (Current status: ' . $session['status'] . ').'], 400);
    }

    // 1. Revert counseling session back to scheduled
    $updateStmt = $db->prepare("UPDATE counseling_sessions 
        SET status = 'scheduled', 
            started_at = NULL 
        WHERE id = ?");
    $updateStmt->execute([$sessionId]);

    // 2. Remove the accidental session_started note if one was created recently
    $db->prepare("DELETE FROM case_notes 
        WHERE case_id = ? AND note_type = 'session_started' AND author_id = ? 
        ORDER BY id DESC LIMIT 1")
       ->execute([$session['case_id'], $user['id']]);

    // 3. Optional: Delete or mark read any "session_active" notification sent to student for this case
    if (!empty($session['student_id'])) {
        $db->prepare("DELETE FROM notifications 
            WHERE user_id = ? AND type = 'session_active' AND title LIKE '%In Progress%' 
            ORDER BY id DESC LIMIT 1")
           ->execute([$session['student_id']]);
    }

    // 4. If case was put into ongoing by mistake and there are no other active/ongoing sessions or notes, revert case to under_review
    $otherOngoing = (int)$db->prepare("SELECT COUNT(*) FROM counseling_sessions WHERE case_id = ? AND id != ? AND status IN ('in_progress','ongoing','completed')")
                            ->execute([$session['case_id'], $sessionId]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
    
    // Check other sessions count
    $checkStmt = $db->prepare("SELECT COUNT(*) FROM counseling_sessions WHERE case_id = ? AND id != ? AND status IN ('in_progress','ongoing','completed')");
    $checkStmt->execute([$session['case_id'], $sessionId]);
    $activeOtherCount = (int)$checkStmt->fetchColumn();

    if ($activeOtherCount === 0 && $session['case_status'] === 'ongoing') {
        $db->prepare("UPDATE cases SET status = 'under_review', updated_at = NOW() WHERE id = ?")
           ->execute([$session['case_id']]);
    }

    jsonOut([
        'success' => true,
        'message' => 'Live session cancelled successfully. Returned to Scheduled status.',
        'session_id' => $sessionId,
        'status' => 'scheduled'
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}
