<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
if (!$id) jsonOut(['success'=>false,'message'=>'User ID required.']);

try {
    $db = getDB();
    if ($action === 'deactivate') {
        $db->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
    } elseif ($action === 'activate') {
        $db->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$id]);
    } else {
        jsonOut(['success'=>false,'message'=>'Unknown action.']);
    }
    jsonOut(['success'=>true]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}



