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
    $cnStmt = $db->prepare("SELECT content FROM case_notes WHERE case_id = ? AND note_type IN ('session_notes', 'observation', 'general') ORDER BY id DESC LIMIT 1");
    $cnStmt->execute([$caseId]);
    $notes = trim($cnStmt->fetchColumn() ?: '');
}

// Check notes length
if (empty($notes) || strlen($notes) < 15) {
    jsonOut([
        'success'        => false,
        'requires_notes' => true,
        'message'        => 'Please input your session notes and behavioral observations first. The AI requires your counselor observations to evaluate the session conclusion.'
    ], 400);
}

// Fetch case & student details
$caseData = null;
if ($caseId > 0) {
    $cStmt = $db->prepare("SELECT c.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.grade_section 
        FROM cases c 
        LEFT JOIN users s ON c.student_id = s.id 
        WHERE c.id = ?");
    $cStmt->execute([$caseId]);
    $caseData = $cStmt->fetch();
} elseif ($sessionId > 0) {
    $cStmt = $db->prepare("SELECT c.*, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.grade_section 
        FROM counseling_sessions cs 
        JOIN cases c ON cs.case_id = c.id 
        LEFT JOIN users s ON c.student_id = s.id 
        WHERE cs.id = ?");
    $cStmt->execute([$sessionId]);
    $caseData = $cStmt->fetch();
    if ($caseData) {
        $caseId = (int)$caseData['id'];
    }
}

// Fetch existing sessions count and records
$existingSessions = [];
if ($caseId > 0) {
    $sessStmt = $db->prepare("SELECT id, session_date, status, notes FROM counseling_sessions WHERE case_id = ? ORDER BY session_date ASC");
    $sessStmt->execute([$caseId]);
    $existingSessions = $sessStmt->fetchAll();
}

$concernType = $caseData['concern_type'] ?? 'general';
$subject     = $caseData['subject'] ?? 'Counseling Session';
$description = $caseData['description'] ?? '';
$studentName = $caseData['student_name'] ?? 'Student';

$analysis = analyzeSessionConclusion($concernType, $subject, $description, $notes, $studentName, $existingSessions);

jsonOut($analysis);
