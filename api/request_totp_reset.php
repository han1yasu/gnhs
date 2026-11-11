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
    
    // Set 2FA reset requested flag
    $stmt = $db->prepare("UPDATE users SET totp_reset_requested = 1 WHERE id = ?");
    $stmt->execute([$userId]);
    
    $_SESSION['pending_user']['totp_reset_requested'] = 1;

    // Send in-app notification to counselors/admins
    try {
        $uName = ($_SESSION['pending_user']['first_name'] ?? '') . ' ' . ($_SESSION['pending_user']['last_name'] ?? '');
        $uRole = ucfirst($_SESSION['pending_user']['role'] ?? 'User');
        $admins = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        
        $notifStmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'system', 0, NOW())");
        foreach ($admins as $adm) {
            $notifStmt->execute([
                $adm['id'],
                '2FA Reset Request',
                "$uName ($uRole) requested assistance resetting their Two-Factor Authenticator (2FA)."
            ]);
        }

        // Send explicit in-app notification to the requesting user/student
        $notifStmt->execute([
            $userId,
            '2FA Reset Request Submitted',
            'Your request to reset your Two-Factor Authenticator (2FA) has been logged and submitted to the Guidance Office. Please visit the Guidance Office or wait for counselor approval.'
        ]);
    } catch (Exception $e) {}

    jsonOut([
        'success' => true,
        'message' => 'Your 2FA reset request has been submitted to the Guidance Office. Please notify or wait for a Guidance Counselor to reset your 2FA.'
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}
