<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$email = trim($raw['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['success'=>false, 'message'=>'Valid email required.']);
}

try {
    $db = getDB();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email=? AND is_active=1 LIMIT 1");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        // Return success even if email not found to prevent user enumeration
        jsonOut(['success'=>true, 'message'=>'If this email is registered, a code has been sent.']);
    }
    
    // Generate 6-digit token
    $token = (string)random_int(100000, 999999);
    
    // Invalidate old tokens for this email
    $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);
    
    // Insert new token, valid for 15 minutes
    $insert = $db->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    $insert->execute([$email, password_hash($token, PASSWORD_DEFAULT)]);

    if (getenv('APP_ENV') === 'local') {
        jsonOut(['success'=>true, 'dev_token'=>$token, 'message'=>'Local reset code generated.']);
    }
    if (!mail($email, 'GNHS password reset code', "Your GNHS reset code is $token. It expires in 15 minutes.")) {
        $db->prepare('DELETE FROM password_resets WHERE email=?')->execute([$email]);
        jsonOut(['success'=>false, 'message'=>'Email delivery is unavailable. Contact an administrator.'], 503);
    }
    jsonOut(['success'=>true, 'message'=>'If this email is registered, a code has been sent.']);
    
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()]);
}
