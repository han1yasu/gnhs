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

    // Counselor note
    if ($note) {
        $db->prepare("INSERT INTO case_notes (case_id,author_id,note_type,content,is_visible_to_student) VALUES (?,?,'counselor_note',?,0)")
           ->execute([$caseId,$user['id'],$note]);
    }
    // Response to student
    if ($response) {
        $db->prepare("INSERT INTO case_notes (case_id,author_id,note_type,content,is_visible_to_student) VALUES (?,?,'student_reply',?,1)")
           ->execute([$caseId,$user['id'],$response]);

        // Get student for notification
        $caseRow = $db->prepare("SELECT student_id,case_number FROM cases WHERE id=?");
        $caseRow->execute([$caseId]);
        $cr = $caseRow->fetch();
        if ($cr && $cr['student_id']) {
            $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,'Counselor Replied','The guidance counselor has replied to your case '||?||'.','case_update')")
               ->execute([$cr['student_id'],$cr['case_number']]);
        }
    }

    // Schedule session
    if ($session) {
        $caseRow = $db->prepare("SELECT student_id FROM cases WHERE id=?");
        $caseRow->execute([$caseId]);
        $cr = $caseRow->fetch();
        $db->prepare("INSERT INTO counseling_sessions (case_id,student_id,counselor_id,session_date,status) VALUES (?,?,?,?,'scheduled')")
           ->execute([$caseId,$cr['student_id']??null,$user['id'],$session]);
        if ($cr && $cr['student_id']) {
            $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,'Session Scheduled','A counseling session has been scheduled for your case.','session_scheduled')")
               ->execute([$cr['student_id']]);
        }
    }

    jsonOut(['success'=>true,'message'=>'Case updated.']);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}
