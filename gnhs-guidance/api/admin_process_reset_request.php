<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$requestId   = (int)($raw['request_id'] ?? 0);
$action      = trim($raw['action'] ?? '');
$newPassword = trim($raw['new_password'] ?? '');
$adminNotes  = trim($raw['admin_notes'] ?? '');

if (!$requestId || !in_array($action, ['approve', 'reject', 'complete'])) {
    jsonOut(['success' => false, 'message' => 'Invalid request parameters.']);
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM password_reset_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) {
        jsonOut(['success' => false, 'message' => 'Password reset request not found.']);
    }

    $targetUserId = (int)$req['user_id'];
    $itAdminName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'School IT Administrator';

    if ($action === 'approve') {
        if (empty($newPassword) || strlen($newPassword) < 6) {
            jsonOut(['success' => false, 'message' => 'Please provide a temporary password with at least 6 characters.']);
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update target user's password, reset failed attempts, clear lockout, and reset security questions so user can re-setup
        $uStmt = $db->prepare("UPDATE users SET 
            password = ?, 
            failed_attempts = 0, 
            lockout_until = NULL,
            security_question_1 = NULL,
            security_answer_1 = NULL,
            security_question_2 = NULL,
            security_answer_2 = NULL,
            is_setup_complete = 0
            WHERE id = ?");
        $uStmt->execute([$hash, $targetUserId]);

        // Update request status
        $rStmt = $db->prepare("UPDATE password_reset_requests 
            SET status = 'approved', temp_password = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
            WHERE id = ?");
        $rStmt->execute([$newPassword, $adminNotes ?: 'Approved and temporary password issued by School IT Administrator.', $user['id'], $requestId]);

        // Send in-app notification to student
        $notifTitle = "Password Reset Approved by School IT";
        $notifMsg   = "Your password reset request has been approved by IT Administrator {$itAdminName}. Your temporary password has been activated. Upon signing in, you will be prompted to re-setup your secondary security questions.";
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'system', 0, NOW())")
           ->execute([$targetUserId, $notifTitle, $notifMsg]);

        jsonOut([
            'success'       => true,
            'temp_password' => $newPassword,
            'message'       => "Password reset approved! Temporary credentials successfully issued for {$req['full_name']}."
        ]);
    } elseif ($action === 'complete') {
        $note = $adminNotes ?: 'Reset request fulfilled and marked as completed.';
        $rStmt = $db->prepare("UPDATE password_reset_requests 
            SET status = 'completed', 
                admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Completed by Counselor {$counselorName} on " . date('M d, Y h:i A') . ": ' , ? , ']'),
                reviewed_by = ?, 
                reviewed_at = NOW() 
            WHERE id = ?");
        $rStmt->execute([$note, $user['id'], $requestId]);

        jsonOut([
            'success' => true,
            'message' => "Password reset request has been marked as completed."
        ]);
    } else {
        // Reject request
        $rStmt = $db->prepare("UPDATE password_reset_requests 
            SET status = 'rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
            WHERE id = ?");
        $rStmt->execute([$adminNotes ?: 'Request rejected by Guidance Office.', $user['id'], $requestId]);

        // Notify student of rejection
        $notifTitle = "Password Reset Request Update";
        $notifMsg   = "Your password reset request was reviewed by Counselor {$counselorName}. Please visit the Guidance Office personally for manual identity verification.";
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'system', 0, NOW())")
           ->execute([$targetUserId, $notifTitle, $notifMsg]);

        jsonOut([
            'success' => true,
            'message' => "Password reset request has been rejected."
        ]);
    }
} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
