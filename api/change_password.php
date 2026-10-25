<?php
// ── GNHS Guidance System — User Change Password ───────────────
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$currentPass = trim($raw['current_password'] ?? '');
$newPass     = trim($raw['new_password'] ?? '');
$confirmPass = trim($raw['confirm_password'] ?? '');

if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
    jsonOut(['success' => false, 'message' => 'Please fill in all password fields.']);
}

if (strlen($newPass) < 8) {
    jsonOut(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
}

if ($newPass !== $confirmPass) {
    jsonOut(['success' => false, 'message' => 'New password and confirmation password do not match.']);
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, password, email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user['id']]);
    $dbUser = $stmt->fetch();

    if (!$dbUser) {
        jsonOut(['success' => false, 'message' => 'User account not found.'], 404);
    }

    // Verify current password
    if (!password_verify($currentPass, $dbUser['password'])) {
        jsonOut(['success' => false, 'message' => 'Your current password is incorrect. Please verify your current password.']);
    }

    if ($currentPass === $newPass) {
        jsonOut(['success' => false, 'message' => 'Your new password cannot be the same as your current password. Please choose a different password.']);
    }

    // Update password hash and clear any lockout / failed attempt counters
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $updateStmt = $db->prepare("UPDATE users SET password = ?, failed_attempts = 0, lockout_until = NULL WHERE id = ?");
    $updateStmt->execute([$newHash, $user['id']]);

    // Mark any active or approved reset requests for this user as 'completed'
    try {
        $db->prepare("UPDATE password_reset_requests SET status = 'completed' WHERE user_id = ? AND status IN ('pending', 'approved')")
           ->execute([$user['id']]);
    } catch (Exception $e) {}

    // Send in-app notification confirming password change
    try {
        $notifTitle = "Account Password Changed";
        $notifMsg   = "Your password was successfully updated on " . date('M d, Y h:i A') . ". If you did not make this change, please contact Guidance Counselor Milena Jacob immediately.";
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'system', 0, NOW())")
           ->execute([$user['id'], $notifTitle, $notifMsg]);
    } catch (Exception $e) {}

    jsonOut([
        'success' => true,
        'message' => 'Your password has been changed successfully!'
    ]);
} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
