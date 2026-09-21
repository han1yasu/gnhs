<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$email = trim($raw['email'] ?? '');
$token = trim($raw['token'] ?? '');
$password = trim($raw['password'] ?? '');

if (!$email || !$token || !$password) {
    jsonOut(['success'=>false, 'message'=>'All fields are required.']);
}
if (strlen($password) < 8) {
    jsonOut(['success'=>false, 'message'=>'Password must be at least 8 characters long.']);
}

try {
    $db = getDB();
    
    // Check if token is valid and not expired
    $stmt = $db->prepare("SELECT id FROM password_resets WHERE email=? AND token=? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$email, $token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        jsonOut(['success'=>false, 'message'=>'Invalid or expired reset code.']);
    }
    
    // Update user password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $update = $db->prepare("UPDATE users SET password=? WHERE email=?");
    $update->execute([$hash, $email]);
    
    // Delete the token
    $db->prepare("DELETE FROM password_resets WHERE id=?")->execute([$reset['id']]);
    
    jsonOut(['success'=>true, 'message'=>'Password reset successfully. You can now log in.']);
    
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error. Please try again later.']);
}
