<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

if (empty($_SESSION['pending_user']['id'])) {
    jsonOut(['success'=>false, 'message'=>'Session expired. Please log in with your credentials first.']);
}

$userId = (int)$_SESSION['pending_user']['id'];

try {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET totp_reset_requested = 0 WHERE id = ?");
    $stmt->execute([$userId]);
    
    $_SESSION['pending_user']['totp_reset_requested'] = 0;

    jsonOut([
        'success' => true,
        'message' => '2FA reset request cancelled. You can enter your authenticator code to sign in.'
    ]);
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}
