<?php
require_once __DIR__ . '/../includes/config.php';
$adminUser = requireLogin('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId = (int)($raw['user_id'] ?? $raw['id'] ?? 0);

if (!$userId) {
    jsonOut(['success'=>false, 'message'=>'User ID is required.']);
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
    
    $action = trim($raw['action'] ?? 'reset'); // 'reset' or 'dismiss'
    $counselorName = $adminUser['first_name'] . ' ' . $adminUser['last_name'];
    
    if ($action === 'dismiss') {
        // Just clear the reset request flag without resetting their authenticator
        $updateStmt = $db->prepare("UPDATE users SET totp_reset_requested = 0 WHERE id = ?");
        $updateStmt->execute([$userId]);
        
        jsonOut([
            'success' => true,
            'message' => '2FA reset request for ' . $targetUser['first_name'] . ' ' . $targetUser['last_name'] . ' was dismissed.'
        ]);
    } else {
        // Reset 2FA configuration
        $updateStmt = $db->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_reset_requested = 0 WHERE id = ?");
        $updateStmt->execute([$userId]);
        
        // Add in-app notification for the target user
        try {
            $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'system', 0)");
            $notifStmt->execute([
                $userId,
                '2FA Authenticator Reset',
                "Your Two-Factor Authenticator (2FA) was reset by Guidance Counselor $counselorName. You can scan a new QR code on your next login."
            ]);
        } catch (Exception $e) {}
        
        jsonOut([
            'success' => true,
            'message' => '2FA has been successfully reset for ' . $targetUser['first_name'] . ' ' . $targetUser['last_name'] . '. They can now scan a new QR code upon their next login.'
        ]);
    }
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Database error: ' . $e->getMessage()], 500);
}
