<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$raw      = json_decode(file_get_contents('php://input'), true);
$caseId   = (int)($raw['case_id']     ?? 0);
$priority = trim($raw['priority']     ?? '');
$status   = trim($raw['status']       ?? '');
$note     = trim($raw['note']         ?? '');
$response = trim($raw['response']     ?? '');
$session  = trim($raw['session_date'] ?? '');
$sessionType = trim($raw['session_type'] ?? 'in-person');
$location = trim($raw['location'] ?? 'Guidance Office');

if (!$caseId) jsonOut(['success'=>false,'message'=>'Case ID required.']);

try {
    $db = getDB();

    // Update case
    $updates = []; $params = [];
    if ($priority) { $updates[]='priority=?'; $params[]=$priority; }
    if ($status)   { $updates[]='status=?';   $params[]=$status;   }
    if ($updates) {
        $params[] = $caseId;
        $db->prepare("UPDATE cases SET ".implode(',',$updates)." WHERE id=?")->execute($params);
    }

    // Removed Counselor note and Response to student logic as it's replaced by the Chat system.

    // Schedule session
    if ($session) {
        $caseRow = $db->prepare("SELECT student_id FROM cases WHERE id=?");
        $caseRow->execute([$caseId]);
        $cr = $caseRow->fetch();
        $db->prepare("INSERT INTO counseling_sessions (case_id,student_id,counselor_id,session_date,status,session_type,location) VALUES (?,?,?,?,'scheduled',?,?)")
           ->execute([$caseId,$cr['student_id']??null,$user['id'],$session,$sessionType,$location]);
        if ($cr && $cr['student_id']) {
            $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,'Session Scheduled','A counseling session has been scheduled for your case.','session_scheduled')")
               ->execute([$cr['student_id']]);
        }
    }

    jsonOut(['success'=>true,'message'=>'Case updated.']);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}



