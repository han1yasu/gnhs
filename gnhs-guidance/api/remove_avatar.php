<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

try {
    $db = getDB();
    $row = $db->query("SELECT avatar_photo FROM users WHERE id={$user['id']}")->fetch();
    if ($row && $row['avatar_photo']) {
        $path = __DIR__ . '/../' . ltrim($row['avatar_photo'], '/gnhs-guidance/');
        if (file_exists($path)) @unlink($path);
    }
    $db->prepare("UPDATE users SET avatar_photo=NULL WHERE id=?")->execute([$user['id']]);
    $_SESSION['user']['avatar_photo'] = null;
    jsonOut(['success'=>true,'message'=>'Profile picture removed.']);
} catch(Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()], 500);
}



