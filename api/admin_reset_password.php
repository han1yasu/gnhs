<?php
// ── GNHS Guidance System — Admin Password Reset for Users ──
require_once __DIR__ . '/../includes/config.php';
startSession();
$adminUser = requireLogin('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId         = (int)($raw['user_id'] ?? $raw['id'] ?? 0);
$password       = trim($raw['password'] ?? '');
$resetQuestions = !empty($raw['reset_questions']) || !empty($raw['reset_security_questions']);

if (!$userId) {
    jsonOut(['success' => false, 'message' => 'User ID is required.']);
}

if (!$password) {
    jsonOut(['success' => false, 'message' => 'New password is required.']);
}

if (strlen($password) < 8) {
    jsonOut(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
}

try {
    $db = getDB();
    
    // Check if target user exists
    $stmt = $db->prepare("SELECT id, first_name, middle_name, last_name, email, id_number, role, grade_section, is_active FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        jsonOut(['success' => false, 'message' => 'Target user account not found.']);
    }

    $counselorName = trim(($adminUser['first_name'] ?? '') . ' ' . ($adminUser['last_name'] ?? '')) ?: 'Guidance Counselor';
    $targetRole    = $targetUser['role'] ?: 'student';
    $fullName      = trim($targetUser['first_name'] . ' ' . ($targetUser['last_name'] ?? ''));

    // Hash the new password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user: set new password, clear lockouts/failed attempts, ensure active
    if ($resetQuestions) {
        // Reset security recovery questions as backup for users who forgot startup questions
        $updStmt = $db->prepare("UPDATE users SET 
            password = ?, 
            failed_attempts = 0, 
            lockout_until = NULL, 
            is_active = 1,
            security_question_1 = NULL, 
            security_answer_1 = NULL, 
            security_question_2 = NULL, 
            security_answer_2 = NULL,
            is_setup_complete = 0
            WHERE id = ?");
        $updStmt->execute([$hash, $userId]);
    } else {
        $updStmt = $db->prepare("UPDATE users SET 
            password = ?, 
            failed_attempts = 0, 
            lockout_until = NULL, 
            is_active = 1 
            WHERE id = ?");
        $updStmt->execute([$hash, $userId]);
    }
    
    // Delete any pending password reset tokens for this user email
    try {
        $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$targetUser['email']]);
    } catch (Exception $e) {}

    // Synchronize with password_reset_requests so the student can track their status
    $adminNotes = $resetQuestions
        ? "Password and startup security recovery questions reset by Counselor {$counselorName} via User Management (In-Person Backup)."
        : "Password reset by Counselor {$counselorName} via User Management.";

    try {
        // First check if there is an existing pending request
        $chkPending = $db->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
        $chkPending->execute([$userId]);
        $pendingReq = $chkPending->fetch();

        if ($pendingReq) {
            $db->prepare("UPDATE password_reset_requests 
                SET status = 'approved',
                    temp_password = ?,
                    admin_notes = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    security_verified = 1
                WHERE id = ?")
               ->execute([$password, $adminNotes, $adminUser['id'], $pendingReq['id']]);
        } else {
            // Check latest request
            $chkLatest = $db->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1");
            $chkLatest->execute([$userId]);
            $latestReq = $chkLatest->fetch();

            if ($latestReq) {
                $db->prepare("UPDATE password_reset_requests 
                    SET status = 'approved',
                        temp_password = ?,
                        admin_notes = ?,
                        reviewed_by = ?,
                        reviewed_at = NOW(),
                        security_verified = 1
                    WHERE id = ?")
                   ->execute([$password, $adminNotes, $adminUser['id'], $latestReq['id']]);
            } else {
                // Insert approved record so Check Request Update displays the reset
                $db->prepare("INSERT INTO password_reset_requests 
                    (user_id, email, id_number, full_name, role, grade_section, security_verified, status, temp_password, admin_notes, reviewed_by, reviewed_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, 'approved', ?, ?, ?, NOW(), NOW())")
                   ->execute([
                       $userId,
                       $targetUser['email'],
                       $targetUser['id_number'],
                       $fullName,
                       $targetRole,
                       $targetUser['grade_section'],
                       $password,
                       $adminNotes,
                       $adminUser['id']
                   ]);
            }
        }
    } catch (Exception $e) {}

    // Add in-app notification for the user
    try {
        $notifMsg = "Your account password was updated by Guidance Counselor {$counselorName}." .
            ($resetQuestions ? " Your startup security recovery questions were also reset. You can set fresh recovery questions when you next sign in." : "");
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, 'Password Reset by Guidance Office', ?, 'system', 0, NOW())")
           ->execute([$userId, $notifMsg]);
    } catch (Exception $e) {}

    jsonOut([
        'success'         => true,
        'message'         => 'Password reset successfully for ' . $fullName . '.',
        'reset_questions' => $resetQuestions,
        'user_id'         => $userId,
        'user_name'       => $fullName
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
