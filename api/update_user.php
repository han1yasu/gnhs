<?php
require_once __DIR__ . '/../includes/config.php';
$adminUser = requireLogin('admin');
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? ($raw['action'] ?? '');
$id     = (int)($_GET['id'] ?? ($raw['id'] ?? ($raw['user_id'] ?? 0)));

if (!$id) jsonOut(['success'=>false, 'message'=>'User ID required.']);

try {
    $db = getDB();
    if ($action === 'deactivate') {
        $db->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
        jsonOut(['success'=>true, 'message'=>'User deactivated.']);
    } elseif ($action === 'activate') {
        $db->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$id]);
        jsonOut(['success'=>true, 'message'=>'User activated.']);
    } elseif ($action === 'reset_password') {
        $password = trim($raw['password'] ?? $_GET['password'] ?? '');
        if (strlen($password) < 8) {
            jsonOut(['success'=>false, 'message'=>'Password must be at least 8 characters long.']);
        }
        $stmt = $db->prepare("SELECT id, first_name, last_name, email FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) {
            jsonOut(['success'=>false, 'message'=>'User not found.']);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
        try {
            $db->prepare("DELETE FROM password_resets WHERE email=?")->execute([$targetUser['email']]);
        } catch (Exception $e) {}
        try {
            $notif = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'system', 0)");
            $notif->execute([
                $id,
                'Password Reset by Guidance Office',
                'Your account password was updated by Guidance Counselor ' . htmlspecialchars($adminUser['first_name'] . ' ' . $adminUser['last_name']) . '.'
            ]);
        } catch (Exception $e) {}
        jsonOut(['success'=>true, 'message'=>'Password reset successfully.']);
    } else {
        jsonOut(['success'=>false, 'message'=>'Unknown action.']);
    }
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>$e->getMessage()], 500);
}
