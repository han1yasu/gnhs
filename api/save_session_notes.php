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

    // If case_id was not provided, look it up from counseling_sessions
    if (!$caseId && $sessionId) {
        $stmt = $db->prepare("SELECT case_id FROM counseling_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $caseId = (int)$stmt->fetchColumn();
    }

    if ($sessionId) {
        $db->prepare("UPDATE counseling_sessions SET notes = ? WHERE id = ?")
           ->execute([$sessionNotes, $sessionId]);
    }

    // If an individual note entry was typed or if session notes should be archived as a record entry
    if ($newNoteEntry) {
        $db->prepare("INSERT INTO case_notes (case_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'session_note', ?, 0, NOW())")
           ->execute([$caseId, $user['id'], $newNoteEntry]);
    }

    // Retrieve fresh notes list for this case
    $notesStmt = $db->prepare("SELECT cn.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name, u.role AS author_role
        FROM case_notes cn
        LEFT JOIN users u ON cn.author_id = u.id
        WHERE cn.case_id = ?
        ORDER BY cn.created_at ASC");
    $notesStmt->execute([$caseId]);
    $freshNotes = $notesStmt->fetchAll();

    jsonOut([
        'success'   => true,
        'message'   => 'Case notes saved for records.',
        'saved_at'  => date('g:i:s A'),
        'notes'     => $freshNotes
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}
