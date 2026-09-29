<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) jsonOut(['success'=>false,'message'=>'Case ID required.']);
if (empty($_SESSION['user'])) jsonOut(['success'=>false,'message'=>'Login required.'], 401);

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT c.*,
        CASE WHEN c.is_anonymous=1 THEN 'Anonymous' ELSE CONCAT(s.first_name,' ',s.last_name) END AS student_name,
        s.grade_section, CONCAT(a.first_name,' ',a.last_name) AS assigned_counselor
        FROM cases c
        LEFT JOIN users s ON c.student_id=s.id
        LEFT JOIN users a ON c.assigned_to=a.id
        WHERE c.id=?");
    $stmt->execute([$id]);
    $case = $stmt->fetch();
    if (!$case) jsonOut(['success'=>false,'message'=>'Case not found.']);

    // Access control
    $u = $_SESSION['user'];
    if ($u['role'] === 'student' && (int)$case['student_id'] !== (int)$u['id']) {
        jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
    }
    if ($u['role'] !== 'student' && $u['role'] !== 'admin') {
        jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
    }

    // Notes
    $visFilter = empty($_SESSION['user']) || $_SESSION['user']['role']==='student' ? 'AND cn.is_visible_to_student=1' : '';
    $nStmt = $db->prepare("SELECT cn.*,CONCAT(u.first_name,' ',u.last_name) AS author_name, u.role AS author_role
        FROM case_notes cn JOIN users u ON cn.author_id=u.id
        WHERE cn.case_id=? $visFilter ORDER BY cn.created_at ASC");
    $nStmt->execute([$id]);
    $notes = $nStmt->fetchAll();

    // Session
    $sStmt = $db->prepare("SELECT * FROM counseling_sessions WHERE case_id=? AND status='scheduled' ORDER BY session_date ASC LIMIT 1");
    $sStmt->execute([$id]);
    $session = $sStmt->fetch();

    jsonOut(['success'=>true,'case'=>$case,'notes'=>$notes,'session'=>$session]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}



