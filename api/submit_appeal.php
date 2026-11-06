<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('student');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$caseId = (int)($raw['case_id'] ?? 0);
$message = trim($raw['message'] ?? '');

if (!$caseId || !$message) {
    jsonOut(['success'=>false, 'message'=>'Invalid input.']);
}

// Length limit and profanity filter
if (strlen($message) < 10 || str_word_count($message) > 400) {
    jsonOut(['success'=>false, 'message'=>'Appeal message must not exceed 400 words.']);
}

if (containsProfanity($message)) {
    jsonOut(['success'=>false, 'message'=>'Please remove inappropriate language from your appeal.']);
}

try {
    $db = getDB();
    
    // Verify ownership and status
    $stmt = $db->prepare("SELECT id, case_number, assigned_to FROM cases WHERE id=? AND student_id=? AND status IN ('resolved','closed')");
    $stmt->execute([$caseId, $user['id']]);
    $case = $stmt->fetch();
    
    if (!$case) {
        jsonOut(['success'=>false, 'message'=>'Case not found or not eligible for appeal.']);
    }
    
    // Update case with follow_up
    $update = $db->prepare("UPDATE cases SET is_follow_up=1, follow_up_msg=?, follow_up_date=NOW(), updated_at=NOW() WHERE id=?");
    $update->execute([$message, $caseId]);
    
    // Notify counselor if assigned, or all counselors if unassigned
    $notifTitle = "Case Appealed: {$case['case_number']}";
    $notifBody  = "The student has submitted an appeal/follow-up for resolved case {$case['case_number']}.";
    if (!empty($case['assigned_to'])) {
        $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'appeal')")
           ->execute([$case['assigned_to'], $notifTitle, $notifBody]);
    } else {
        $admins = $db->query("SELECT id FROM users WHERE role='admin'")->fetchAll();
        foreach ($admins as $adm) {
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'appeal')")
               ->execute([$adm['id'], $notifTitle, $notifBody]);
        }
    }
    
    jsonOut(['success'=>true]);

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error.']);
}
