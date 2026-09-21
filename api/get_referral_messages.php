<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

$refId = (int)($_GET['referral_id'] ?? 0);
if (!$refId) {
    jsonOut(['success' => false, 'message' => 'Invalid referral ID'], 400);
}

try {
    $db = getDB();
    
    // Check if user has access to this referral
    $stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
    $stmt->execute([$refId]);
    $referral = $stmt->fetch();
    
    if (!$referral) {
        jsonOut(['success' => false, 'message' => 'Referral not found'], 404);
    }
    
    // If teacher, they can only view their own referrals. Admin can view all.
    if ($user['role'] === 'teacher' && $referral['teacher_id'] != $user['id']) {
        jsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    $stmt = $db->prepare("
        SELECT m.id, m.message, m.created_at, u.first_name, u.last_name, u.role, u.avatar_photo, u.avatar_initials, m.user_id 
        FROM referral_messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.referral_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$refId]);
    $messages = $stmt->fetchAll();

    // Mark messages from others as read
    $updateStmt = $db->prepare("UPDATE referral_messages SET is_read = 1 WHERE referral_id = ? AND user_id != ?");
    $updateStmt->execute([$refId, $user['id']]);

    jsonOut(['success' => true, 'messages' => $messages, 'current_user_id' => $user['id']]);
} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error'], 500);
}
