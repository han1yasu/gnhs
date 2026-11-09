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
    $token = sprintf("%06d", mt_rand(100000, 999999));
    
    // Invalidate old tokens for this email
    $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$email]);
    
    // Insert new token, valid for 15 minutes
    $insert = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    $insert->execute([$email, $token]);
    
    // NOTE: In a real system, you would send an email here using mail() or PHPMailer.
    // For local development / demonstration, we return the token in the API response 
    // so the frontend can display it in an alert box.
    jsonOut([
        'success' => true,
        'demo_token' => $token, // Exposing token purely for dev/demo purposes
        'message' => 'Reset code generated successfully.'
    ]);
    
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()]);
}
