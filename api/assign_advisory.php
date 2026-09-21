<?php
require_once __DIR__ . '/../includes/config.php';
$admin = requireLogin('admin');
header('Content-Type: application/json');

$userId = (int)($_GET['user_id'] ?? 0);
$class  = trim($_GET['class'] ?? '');

if (!$userId || !$class) jsonOut(['success'=>false,'message'=>'Missing parameters.']);

try {
    $db = getDB();
    // Ensure advisory_class column exists
    try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS advisory_class VARCHAR(60) DEFAULT NULL"); } catch(Exception $e){}
    $db->prepare("UPDATE users SET advisory_class=? WHERE id=? AND role='teacher'")->execute([$class, $userId]);
    jsonOut(['success'=>true,'message'=>'Advisory class assigned.']);
} catch(Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()], 500);
}



