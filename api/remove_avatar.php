<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

try {
    $db = getDB();
    $row = $db->query("SELECT avatar_photo FROM users WHERE id={$user['id']}")->fetch();
    if ($row && $row['avatar_photo']) {
        $base = basename($row['avatar_photo']);
        $dirs = [
            str_replace('\\', '/', __DIR__ . '/../uploads/avatars/'),
            str_replace('\\', '/', dirname(__DIR__, 2) . '/uploads/avatars/'),
            str_replace('\\', '/', dirname(__DIR__) . '/gnhs-guidance/uploads/avatars/'),
        ];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
            $dirs[] = $docRoot . '/uploads/avatars/';
            $dirs[] = $docRoot . '/gnhs-guidance/uploads/avatars/';
        }
        $dirs = array_unique($dirs);
        foreach ($dirs as $d) {
            $path = rtrim($d, '/') . '/' . $base;
            if (file_exists($path)) @unlink($path);
        }
    }
    $db->prepare("UPDATE users SET avatar_photo=NULL WHERE id=?")->execute([$user['id']]);
    $_SESSION['user']['avatar_photo'] = null;
    jsonOut(['success'=>true,'message'=>'Profile picture removed.']);
} catch(Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()], 500);
}
