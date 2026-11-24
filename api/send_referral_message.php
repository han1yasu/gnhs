<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
$refId = (int)($raw['referral_id'] ?? $_POST['referral_id'] ?? 0);
$message = trim($raw['message'] ?? $_POST['message'] ?? '');

if (!$refId || !$message) {
    jsonOut(['success' => false, 'message' => 'Invalid referral ID or empty message'], 400);
}

try {
    $db = getDB();
    
    // Check access
    $stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
    $stmt->execute([$refId]);
    $referral = $stmt->fetch();
    
    if (!$referral) {
        jsonOut(['success' => false, 'message' => 'Referral not found'], 404);
    }
    
    // Authorization
    if ($user['role'] === 'teacher' && $referral['teacher_id'] != $user['id']) {
        jsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    if (in_array(strtolower($referral['status'] ?? ''), ['resolved', 'closed'], true)) {
        jsonOut(['success' => false, 'message' => 'This referral is resolved and archived. Chat is view-only unless reopened.'], 403);
    }

    // Insert message
    $stmt = $db->prepare("INSERT INTO referral_messages (referral_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$refId, $user['id'], $message]);
    
    $msgId = $db->lastInsertId();

    // Send real-time in-app notification to the recipient(s)
    try {
        $senderName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $preview = mb_strimwidth($message, 0, 120, '...');
        $refNum = $referral['ref_number'] ?? ('R-' . $refId);
        $stuName = $referral['student_name'] ?? 'Student';

        if ($user['role'] === 'admin' && !empty($referral['teacher_id'])) {
            $cLabel = $senderName ? "Counselor {$senderName}" : 'The guidance counselor';
            $nTitle = "New Referral Message ({$refNum})";
            $nMsg   = "{$cLabel} sent a message regarding Referral #{$refNum} ({$stuName}): \"{$preview}\"";
            $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'referral_chat', 0, NOW())")
               ->execute([(int)$referral['teacher_id'], $nTitle, $nMsg]);
        } elseif ($user['role'] === 'teacher') {
            $tLabel = $senderName ? "Teacher {$senderName}" : 'Referring teacher';
            $nTitle = "New Referral Message ({$refNum})";
            $nMsg   = "{$tLabel} sent a message regarding Referral #{$refNum} ({$stuName}): \"{$preview}\"";
            $admins = $db->query("SELECT id FROM users WHERE role='admin' AND COALESCE(is_active, 1) = 1")->fetchAll(PDO::FETCH_COLUMN);
            $nStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'referral_chat', 0, NOW())");
            foreach ($admins as $admId) {
                $nStmt->execute([(int)$admId, $nTitle, $nMsg]);
            }
        }
    } catch (Exception $ne) {}

    // Fetch the inserted message to return
    $stmt = $db->prepare("
        SELECT m.id, m.message, m.created_at, u.first_name, u.last_name, u.role, u.avatar_photo, u.avatar_initials, m.user_id 
        FROM referral_messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$msgId]);
    $newMessage = $stmt->fetch();

    jsonOut(['success' => true, 'message' => $newMessage]);
} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error'], 500);
}
