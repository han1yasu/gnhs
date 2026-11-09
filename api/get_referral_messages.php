<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$refId = (int)($_GET['referral_id'] ?? 0);
if (!$refId) {
    jsonOut(['success' => false, 'message' => 'Invalid referral ID'], 400);
}

try {
    $db = getDB();
    
    // Check if user has access to this referral
    $stmt = $db->prepare("SELECT r.*, 
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name, 
        t.avatar_photo as teacher_avatar,
        t.email as teacher_email
        FROM referrals r
        LEFT JOIN users t ON r.teacher_id = t.id
        WHERE r.id = ?");
    $stmt->execute([$refId]);
    $referral = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$referral) {
        jsonOut(['success' => false, 'message' => 'Referral not found'], 404);
    }
    
    // If teacher, they can only view their own referrals. Admin can view all.
    if ($user['role'] === 'teacher' && $referral['teacher_id'] != $user['id']) {
        jsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
    }

    // Mark messages from others as read and record seen timestamp
    $updateStmt = $db->prepare("UPDATE referral_messages SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE referral_id = ? AND user_id != ? AND (is_read = 0 OR is_read IS NULL)");
    $updateStmt->execute([$refId, $user['id']]);

    $stmt = $db->prepare("
        SELECT m.id, m.message, m.created_at, m.is_read, m.read_at, u.first_name, u.last_name, u.role, u.avatar_photo, u.avatar_initials, m.user_id 
        FROM referral_messages m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.referral_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$refId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonOut([
        'success' => true, 
        'messages' => $messages, 
        'referral_info' => $referral,
        'current_user_id' => $user['id']
    ]);
} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
