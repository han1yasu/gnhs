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

    // Fetch case and verify access & active status
    $caseStmt = $db->prepare("SELECT id, status, student_id, assigned_to, case_number FROM cases WHERE id=?");
    $caseStmt->execute([$caseId]);
    $c = $caseStmt->fetch();
    if (!$c) {
        jsonOut(['success'=>false,'message'=>'Case not found.'], 404);
    }

    if ($user['role'] === 'student') {
        if ((int)$c['student_id'] !== (int)$user['id']) {
            jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
        }
    } else if (!isAdminRole($user['role'] ?? '')) {
        jsonOut(['success'=>false,'message'=>'Access denied.'], 403);
    }

    if (in_array(strtolower($c['status'] ?? ''), ['resolved', 'closed'], true)) {
        jsonOut(['success'=>false,'message'=>'This case is resolved and archived. Chat is view-only unless reopened.'], 403);
    }

    // Insert message
    $stmt = $db->prepare("INSERT INTO case_messages (case_id, sender_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$caseId, $user['id'], $message]);

    // Add notification to the receiver
    // If sender is admin, notify student. If sender is student, notify admin (assigned_to).
    if ($c) {
        if (isAdminRole($user['role'] ?? '') && $c['student_id']) {
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
