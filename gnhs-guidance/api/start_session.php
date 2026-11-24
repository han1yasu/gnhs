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

    $counselorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Guidance Counselor';
    $now = date('Y-m-d H:i:s');

    // Update counseling_sessions: status to in_progress and started_at if not set
    $updateStmt = $db->prepare("UPDATE counseling_sessions 
        SET status = 'in_progress', 
            started_at = COALESCE(started_at, ?), 
            counselor_id = ? 
        WHERE id = ?");
    $updateStmt->execute([$now, $user['id'], $sessionId]);

    // Update case / referral status to ongoing if pending or under_review
    if (!empty($session['case_id']) && in_array($session['case_status'], ['pending', 'under_review'])) {
        $db->prepare("UPDATE cases SET status = 'ongoing', updated_at = NOW() WHERE id = ?")
           ->execute([$session['case_id']]);
    }
    if (!empty($session['referral_id'])) {
        $db->prepare("UPDATE referrals SET status = 'ongoing' WHERE id = ?")
           ->execute([$session['referral_id']]);
    }

    // Add record note in case_notes or referral_notes
    $noteContent = "Counseling session officially started by Counselor {$counselorName} at " . date('M d, Y g:i A') . " (" . ($session['location'] ?: 'Guidance Office') . ").";
    if (!empty($session['case_id'])) {
        $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_started', ?, 0, NOW())")
           ->execute([$session['case_id'], $user['id'], $noteContent]);
    } elseif (!empty($session['referral_id'])) {
        ensureReferralNotesTable($db);
        $db->prepare("INSERT INTO referral_notes (referral_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_started', ?, 0, NOW())")
           ->execute([$session['referral_id'], $user['id'], $noteContent]);
    }

    // Send student notification if student_id exists
    if (!empty($session['student_id'])) {
        $notifTitle = "Counseling Session In Progress";
        $notifMsg   = "Your counseling session for Case #{$session['case_number']} (\"{$session['case_subject']}\") has officially started in person with Counselor {$counselorName} at {$session['location']}.\n\nTime Started: " . date('g:i A, F d, Y');
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'session_active', 0, NOW())")
           ->execute([$session['student_id'], $notifTitle, $notifMsg]);
    }

    jsonOut([
        'success'    => true,
        'message'    => 'Session is now live and in progress.',
        'started_at' => $now,
        'status'     => 'in_progress'
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}
