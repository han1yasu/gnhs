<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$enable = filter_var($raw['enable'] ?? false, FILTER_VALIDATE_BOOLEAN);

try {
    $db = getDB();
    $db->prepare("UPDATE users SET totp_enabled = ? WHERE id = ?")->execute([$enable ? 1 : 0, $user['id']]);
    $_SESSION['user']['totp_enabled'] = $enable ? 1 : 0;
    
    jsonOut(['success'=>true, 'message' => $enable ? '2FA Enabled' : '2FA Disabled']);
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}



