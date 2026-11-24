<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$sessionId    = (int)($raw['session_id'] ?? 0);
$caseId       = (int)($raw['case_id'] ?? 0);
$sessionNotes = trim($raw['notes'] ?? '');
$newNoteEntry = trim($raw['new_note_entry'] ?? '');

if (!$sessionId && !$caseId) {
    jsonOut(['success' => false, 'message' => 'Session ID or Case ID is required.'], 400);
}

try {
    $db = getDB();

    // Resolve case_id and referral_id from counseling_sessions
    $referralId = 0;
    if ($sessionId) {
        $stmt = $db->prepare("SELECT case_id, referral_id FROM counseling_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $sessRow = $stmt->fetch();
        if ($sessRow) {
            if (!$caseId) $caseId = (int)($sessRow['case_id'] ?? 0);
            $referralId = (int)($sessRow['referral_id'] ?? 0);
        }
    }

    if ($sessionId) {
        $db->prepare("UPDATE counseling_sessions SET notes = ? WHERE id = ?")
           ->execute([$sessionNotes, $sessionId]);
    }

    // Save timestamped note entry to case_notes (for cases) or referral_notes (for teacher referrals)
    if ($newNoteEntry) {
        if ($caseId > 0) {
            $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_note', ?, 0, NOW())")
               ->execute([$caseId, $user['id'], $newNoteEntry]);
        } elseif ($referralId > 0) {
            ensureReferralNotesTable($db);
            $db->prepare("INSERT INTO referral_notes (referral_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_note', ?, 0, NOW())")
               ->execute([$referralId, $user['id'], $newNoteEntry]);
        }
    }

    // Retrieve fresh notes list for this case or referral
    $freshNotes = [];
    if ($caseId > 0) {
        $notesStmt = $db->prepare("SELECT cn.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name, u.role AS author_role
            FROM case_notes cn
            LEFT JOIN users u ON cn.author_id = u.id
            WHERE cn.case_id = ?
            ORDER BY cn.created_at ASC");
        $notesStmt->execute([$caseId]);
        $freshNotes = $notesStmt->fetchAll();
    } elseif ($referralId > 0) {
        ensureReferralNotesTable($db);
        $notesStmt = $db->prepare("SELECT rn.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name, u.role AS author_role
            FROM referral_notes rn
            LEFT JOIN users u ON rn.author_id = u.id
            WHERE rn.referral_id = ?
            ORDER BY rn.created_at ASC");
        $notesStmt->execute([$referralId]);
        $freshNotes = $notesStmt->fetchAll();
    }

    jsonOut([
        'success'   => true,
        'message'   => 'Case notes saved for records.',
        'saved_at'  => date('g:i:s A'),
        'notes'     => $freshNotes
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}
