<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$id           = (int)($raw['id'] ?? $_REQUEST['id'] ?? 0);
$status       = trim($raw['status'] ?? $_REQUEST['status'] ?? '');
$reopenReason = trim($raw['reopen_reason'] ?? $_REQUEST['reopen_reason'] ?? '');
if (!$id || !$status) jsonOut(['success'=>false,'message'=>'Missing params.']);

$allowedStatuses = ['pending', 'ongoing', 'resolved'];
if (!in_array($status, $allowedStatuses, true)) {
    jsonOut(['success'=>false,'message'=>'Invalid referral status selected.']);
}

try {
    $db = getDB();
    ensureReferralNotesTable($db);
    $refStmt = $db->prepare("SELECT * FROM referrals WHERE id=?");
    $refStmt->execute([$id]);
    $ref = $refStmt->fetch();
    if (!$ref) {
        jsonOut(['success'=>false,'message'=>'Referral not found.']);
    }

    $wasArchived = in_array($ref['status'], ['resolved', 'closed'], true);
    $isReopening = ($wasArchived && in_array($status, ['pending', 'ongoing'], true));

    if ($isReopening && mb_strlen($reopenReason) < 5) {
        jsonOut(['success'=>false,'message'=>'Please provide a reason for reopening this referral (at least 5 characters).'], 400);
    }

    if ($isReopening) {
        $db->prepare("UPDATE referrals SET status=?, reopen_reason=?, reopened_at=NOW() WHERE id=?")
           ->execute([$status, $reopenReason, $id]);
        try {
            $db->prepare("INSERT INTO referral_notes (referral_id, author_id, note_type, content, is_visible_to_student, created_at) VALUES (?, ?, 'referral_reopened', ?, 1, NOW())")
               ->execute([$id, $user['id'], "Referral Reopened by Guidance: " . $reopenReason]);
        } catch (Exception $ne) {}
    } else {
        $db->prepare("UPDATE referrals SET status=? WHERE id=?")->execute([$status, $id]);
    }

    // Send notification to referring teacher
    try {
        $formattedStatus = ucwords(str_replace('_', ' ', $status));
        $counselorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Guidance Counselor';
        if (!empty($ref['teacher_id'])) {
            if ($isReopening) {
                $notifTitle = "Referral #{$ref['ref_number']} Reopened";
                $notifMsg = "Referral #{$ref['ref_number']} for {$ref['student_name']} has been reopened to {$formattedStatus} by {$counselorName}.\n\nReason: {$reopenReason}";
            } else {
                $notifTitle = ($status === 'resolved') ? 'Referral Archived / Resolved' : 'Referral Status Updated';
                $notifMsg = "Referral #{$ref['ref_number']} for {$ref['student_name']} has been updated to {$formattedStatus} by Guidance.";
            }
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
            if ($isReopening) {
                $sTitle = "Teacher Referral #{$ref['ref_number']} Reopened";
                $sMsg   = "Your teacher referral regarding \"{$ref['concern_type']}\" has been reopened to {$formattedStatus} by {$counselorName}.\n\nReason: {$reopenReason}";
            } else {
                $sTitle = ($status === 'resolved') ? "Teacher Referral #{$ref['ref_number']} Resolved" : "Teacher Referral Status: {$formattedStatus}";
                $sMsg   = "The guidance referral submitted by your teacher regarding \"{$ref['concern_type']}\" has been marked as {$formattedStatus} by the Guidance Office.";
            }
            $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'referral')")
               ->execute([$studentId, $sTitle, $sMsg]);
        }
    } catch (Exception $ne) {}

    jsonOut(['success'=>true, 'status'=>$status, 'reopened'=>$isReopening]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}
