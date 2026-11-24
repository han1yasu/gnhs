<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/summarizer.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw       = json_decode(file_get_contents('php://input'), true);
$caseId    = (int)($raw['case_id'] ?? 0);
$sessionId = (int)($raw['session_id'] ?? 0);
$notes     = trim($raw['notes'] ?? '');

$db = getDB();

// If notes were not passed in payload, try fetching from session or case_notes
if (empty($notes) && $sessionId > 0) {
    $sStmt = $db->prepare("SELECT notes FROM counseling_sessions WHERE id = ?");
    $sStmt->execute([$sessionId]);
    $notes = trim($sStmt->fetchColumn() ?: '');
}

if (empty($notes) && $caseId > 0) {
    // Check latest case_notes
    $cnStmt = $db->prepare("SELECT content FROM case_notes WHERE case_id = ? AND note_type IN ('session_notes', 'observation', 'general') ORDER BY id DESC LIMIT 1");
    $cnStmt->execute([$caseId]);
    $notes = trim($cnStmt->fetchColumn() ?: '');
}

// CRITICAL: Can only be done after the guidance admin puts some observation on students actions in the interview
if (empty($notes) || strlen($notes) < 8) {
    jsonOut([
        'success' => false,
        'requires_notes' => true,
        'message' => 'Please input your counselor case notes and student behavioral observations from the interview first. The AI requires your session observations to formulate a relevant resolution.'
    ], 400);
}

// Fetch case or referral details
$caseData = null;
if ($caseId > 0) {
    $cStmt = $db->prepare("SELECT c.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.grade_section 
        FROM cases c 
        LEFT JOIN users s ON c.student_id = s.id 
        WHERE c.id = ?");
    $cStmt->execute([$caseId]);
    $caseData = $cStmt->fetch();
} elseif ($sessionId > 0) {
    $cStmt = $db->prepare("SELECT cs.case_id, cs.referral_id,
        COALESCE(c.concern_type, r.concern_type, 'general') AS concern_type,
        COALESCE(c.subject, CONCAT('Teacher Referral: ', REPLACE(r.concern_type, '_', ' ')), 'Counseling Session') AS subject,
        COALESCE(c.description, r.observations, '') AS description,
        COALESCE(CONCAT(s.first_name, ' ', s.last_name), r.student_name, 'Student') AS student_name
        FROM counseling_sessions cs 
        LEFT JOIN cases c ON cs.case_id = c.id 
        LEFT JOIN referrals r ON cs.referral_id = r.id
        LEFT JOIN users s ON s.id = COALESCE(cs.student_id, c.student_id, r.student_id)
        WHERE cs.id = ?");
    $cStmt->execute([$sessionId]);
    $caseData = $cStmt->fetch();
}

$concernType = $caseData['concern_type'] ?? 'general';
$subject     = $caseData['subject'] ?? 'Counseling Session';
$description = $caseData['description'] ?? '';
$studentName = $caseData['student_name'] ?? 'Student';

$suggestion = generateResolutionSuggestion($concernType, $subject, $description, $notes, $studentName);

jsonOut($suggestion);
