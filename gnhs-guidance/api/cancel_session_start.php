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

    $stmt = $db->prepare("SELECT cs.*, 
        COALESCE(c.case_number, r.ref_number) AS case_number, 
        COALESCE(c.subject, CONCAT('Teacher Referral: ', REPLACE(r.concern_type, '_', ' '))) AS case_subject, 
        COALESCE(c.status, r.status) AS case_status
        FROM counseling_sessions cs
        LEFT JOIN cases c ON cs.case_id = c.id
        LEFT JOIN referrals r ON cs.referral_id = r.id
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
    if (!empty($session['case_id'])) {
        $db->prepare("DELETE FROM case_notes 
            WHERE case_id = ? AND note_type = 'session_started' AND author_id = ? 
            ORDER BY id DESC LIMIT 1")
           ->execute([$session['case_id'], $user['id']]);
    } elseif (!empty($session['referral_id'])) {
        ensureReferralNotesTable($db);
        $db->prepare("DELETE FROM referral_notes 
            WHERE referral_id = ? AND note_type = 'session_started' AND author_id = ? 
            ORDER BY id DESC LIMIT 1")
           ->execute([$session['referral_id'], $user['id']]);
    }

    // 3. Optional: Delete or mark read any "session_active" notification sent to student for this case
    if (!empty($session['student_id'])) {
        $db->prepare("DELETE FROM notifications 
            WHERE user_id = ? AND type = 'session_active' AND title LIKE '%In Progress%' 
            ORDER BY id DESC LIMIT 1")
           ->execute([$session['student_id']]);
    }

    // 4. If case/referral was put into ongoing by mistake and there are no other active/ongoing sessions, revert status
    if (!empty($session['case_id'])) {
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM counseling_sessions WHERE case_id = ? AND id != ? AND status IN ('in_progress','ongoing','completed')");
        $checkStmt->execute([$session['case_id'], $sessionId]);
        $activeOtherCount = (int)$checkStmt->fetchColumn();
        if ($activeOtherCount === 0 && $session['case_status'] === 'ongoing') {
            $db->prepare("UPDATE cases SET status = 'under_review', updated_at = NOW() WHERE id = ?")
               ->execute([$session['case_id']]);
        }
    } elseif (!empty($session['referral_id'])) {
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM counseling_sessions WHERE referral_id = ? AND id != ? AND status IN ('in_progress','ongoing','completed')");
        $checkStmt->execute([$session['referral_id'], $sessionId]);
        $activeOtherCount = (int)$checkStmt->fetchColumn();
        if ($activeOtherCount === 0 && $session['case_status'] === 'ongoing') {
            $db->prepare("UPDATE referrals SET status = 'under_review' WHERE id = ?")
               ->execute([$session['referral_id']]);
        }
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
