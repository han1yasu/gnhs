<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$id     = (int)($_REQUEST['id']     ?? 0);
$status = trim($_REQUEST['status']  ?? '');
if (!$id || !$status) jsonOut(['success'=>false,'message'=>'Missing params.']);

try {
    $db = getDB();
    $ref = $db->query("SELECT * FROM referrals WHERE id=$id")->fetch();
    if (!$ref) {
        jsonOut(['success'=>false,'message'=>'Referral not found.']);
    }

    $db->prepare("UPDATE referrals SET status=? WHERE id=?")->execute([$status,$id]);

    // Send notification to referring teacher
    try {
        if (!empty($ref['teacher_id'])) {
            $formattedStatus = ucwords(str_replace('_', ' ', $status));
            $notifTitle = ($status === 'resolved' || $status === 'closed') ? 'Referral Archived / Resolved' : 'Referral Status Updated';
            $notifMsg = "Referral #{$ref['ref_number']} for {$ref['student_name']} has been updated to {$formattedStatus} by Guidance.";
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'referral')")
               ->execute([$ref['teacher_id'], $notifTitle, $notifMsg]);
        }
    } catch (Exception $ne) {}

    jsonOut(['success'=>true, 'status'=>$status]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}
