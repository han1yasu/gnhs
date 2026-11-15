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
        $formattedStatus = ucwords(str_replace('_', ' ', $status));
        if (!empty($ref['teacher_id'])) {
            $notifTitle = ($status === 'resolved' || $status === 'closed') ? 'Referral Archived / Resolved' : 'Referral Status Updated';
            $notifMsg = "Referral #{$ref['ref_number']} for {$ref['student_name']} has been updated to {$formattedStatus} by Guidance.";
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'referral')")
               ->execute([$ref['teacher_id'], $notifTitle, $notifMsg]);
        }

        // Also notify student if student_id is known or resolvable
        $studentId = !empty($ref['student_id']) ? (int)$ref['student_id'] : null;
        if (!$studentId && !empty($ref['student_name'])) {
            $sStmt = $db->prepare("SELECT id FROM users WHERE role='student' AND (LOWER(TRIM(CONCAT(first_name, ' ', last_name))) = LOWER(?) OR LOWER(TRIM(CONCAT(last_name, ' ', first_name))) = LOWER(?)) LIMIT 1");
            $sStmt->execute([$ref['student_name'], $ref['student_name']]);
            $studentId = $sStmt->fetchColumn() ?: null;
            if ($studentId) {
                try { $db->prepare("UPDATE referrals SET student_id = ? WHERE id = ?")->execute([$studentId, $id]); } catch(Exception $ue) {}
            }
        }
        if ($studentId) {
            $sTitle = ($status === 'resolved' || $status === 'closed') ? "Teacher Referral #{$ref['ref_number']} Resolved" : "Teacher Referral Status: {$formattedStatus}";
            $sMsg   = "The guidance referral submitted by your teacher regarding \"{$ref['concern_type']}\" has been marked as {$formattedStatus} by the Guidance Office.";
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'referral')")
               ->execute([$studentId, $sTitle, $sMsg]);
        }
    } catch (Exception $ne) {}

    jsonOut(['success'=>true, 'status'=>$status]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}
