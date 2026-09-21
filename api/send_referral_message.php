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

    // Insert message
    $stmt = $db->prepare("INSERT INTO referral_messages (referral_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$refId, $user['id'], $message]);
    
    $msgId = $db->lastInsertId();

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
