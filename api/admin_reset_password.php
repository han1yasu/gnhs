<?php
require_once __DIR__ . '/../includes/config.php';
$adminUser = requireLogin('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId   = (int)($raw['user_id'] ?? $raw['id'] ?? 0);
$password = trim($raw['password'] ?? '');

if (!$userId) {
    jsonOut(['success'=>false, 'message'=>'User ID is required.']);
}

if (!$password) {
    jsonOut(['success'=>false, 'message'=>'New password is required.']);
}

if (strlen($password) < 8) {
    jsonOut(['success'=>false, 'message'=>'Password must be at least 8 characters long.']);
}

try {
    $db = getDB();
    
    // Check if target user exists
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        jsonOut(['success'=>false, 'message'=>'User not found.']);
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password
    $updateStmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
    $updateStmt->execute([$hash, $userId]);
    
    // Delete any pending password reset tokens for this user
    try {
        $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$targetUser['email']]);
    } catch(Exception $e) {}

    // Add in-app notification
    try {
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'system', 0)");
        $notifStmt->execute([
            $userId,
            'Password Reset by Guidance Office',
            'Your account password was updated by Guidance Counselor ' . htmlspecialchars($adminUser['first_name'] . ' ' . $adminUser['last_name']) . '.'
        ]);
    } catch(Exception $e) {}

    jsonOut([
        'success' => true,
        'message' => 'Password reset successfully for ' . $targetUser['first_name'] . ' ' . $targetUser['last_name'] . '.'
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Database error: ' . $e->getMessage()], 500);
}
