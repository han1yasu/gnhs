<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user['id']]);
    $notifs = $stmt->fetchAll();

    $countStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $countStmt->execute([$user['id']]);
    $unread = (int)$countStmt->fetchColumn();

    // Mark all as read
    if ($_GET['mark_read'] ?? false) {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
        $unread = 0;
    }

    jsonOut(['success'=>true,'notifications'=>$notifs,'unread'=>$unread]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}



