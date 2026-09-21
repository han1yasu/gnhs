<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$id     = (int)($_GET['id']     ?? 0);
$status = trim($_GET['status']  ?? '');
if (!$id || !$status) jsonOut(['success'=>false,'message'=>'Missing params.']);

try {
    $db = getDB();
    $db->prepare("UPDATE referrals SET status=? WHERE id=?")->execute([$status,$id]);
    jsonOut(['success'=>true]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}



