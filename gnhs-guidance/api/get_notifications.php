<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

try {
    $db = getDB();

    // Mark single notification as read
    $markId = (int)($_GET['mark_id'] ?? 0);
    if ($markId) {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$markId, $user['id']]);
    }

    // Mark all as read
    if ($_GET['mark_read'] ?? false) {
        $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['id']]);
    }

    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$user['id']]);
    $rawNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifs = [];
    foreach ($rawNotifs as $n) {
        $ts = strtotime($n['created_at']);
        $n['formatted_date'] = date('M d, Y', $ts);
        $n['formatted_time'] = date('h:i A', $ts);
        $n['formatted_full'] = date('F d, Y \a\t g:i A', $ts);
        $notifs[] = $n;
    }

    $unread = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE user_id={$user['id']} AND is_read=0")->fetchColumn();

    jsonOut(['success'=>true,'notifications'=>$notifs,'unread'=>$unread]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}
