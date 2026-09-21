<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

$raw     = json_decode(file_get_contents('php://input'), true);
$caseId  = (int)($raw['case_id'] ?? 0);
$message = trim($raw['message'] ?? '');

if (!$caseId || !$message) {
    jsonOut(['success'=>false,'message'=>'Case ID and message are required.']);
}

try {
    $db = getDB();

    // Verify access to the case
    if ($user['role'] === 'student') {
        $stmt = $db->prepare("SELECT id FROM cases WHERE id=? AND student_id=?");
        $stmt->execute([$caseId, $user['id']]);
        if (!$stmt->fetch()) jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
    } else if ($user['role'] !== 'admin') {
        jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
    }

    // Insert message
    $stmt = $db->prepare("INSERT INTO case_messages (case_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$caseId, $user['id'], $message]);

    // Add notification to the receiver
    // If sender is admin, notify student. If sender is student, notify admin (assigned_to).
    $caseRow = $db->prepare("SELECT student_id, assigned_to, case_number FROM cases WHERE id=?");
    $caseRow->execute([$caseId]);
    $c = $caseRow->fetch();

    if ($c) {
        if ($user['role'] === 'admin' && $c['student_id']) {
            $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,'New Message',CONCAT('The guidance counselor sent a message regarding ', ?, '.'),'chat')")
               ->execute([$c['student_id'], $c['case_number']]);
        } else if ($user['role'] === 'student' && $c['assigned_to']) {
            $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,'New Message',CONCAT('The student sent a message regarding ', ?, '.'),'chat')")
               ->execute([$c['assigned_to'], $c['case_number']]);
        }
    }

    jsonOut(['success'=>true,'message'=>'Message sent.']);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()], 500);
}
