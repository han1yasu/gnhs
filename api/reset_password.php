<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw        = json_decode(file_get_contents('php://input'), true);
$resetToken = trim($raw['reset_token'] ?? $raw['token'] ?? '');
$password   = trim($raw['password'] ?? $raw['new_password'] ?? '');
$confirm    = trim($raw['confirm_password'] ?? $raw['confirm'] ?? '');
$email      = strtolower(trim($raw['email'] ?? ''));

// If email not provided in payload, resolve from active verified session
if (!$email && !empty($_SESSION['pwd_reset_verified']['email'])) {
    $email = strtolower(trim($_SESSION['pwd_reset_verified']['email']));
}
if (!$email && !empty($_SESSION['pwd_reset']['email'])) {
    $email = strtolower(trim($_SESSION['pwd_reset']['email']));
}

try {
    $db = getDB();

    // If still empty, lookup email by reset token in password_resets table
    if (!$email && $resetToken) {
        $findStmt = $db->prepare("SELECT email FROM password_resets WHERE token = ? LIMIT 1");
        $findStmt->execute([$resetToken]);
        $row = $findStmt->fetch();
        if ($row && !empty($row['email'])) {
            $email = strtolower(trim($row['email']));
        }
    }

    if (!$resetToken || !$password) {
        jsonOut(['success'=>false, 'message'=>'Please enter your new password and confirmation.']);
    }
    if (!$email) {
        jsonOut(['success'=>false, 'message'=>'Reset authorization expired or invalid. Please request a new code.']);
    }

    // Strict password standard validation
    if (strlen($password) < 8) {
        jsonOut(['success'=>false, 'message'=>'Password must be at least 8 characters long.']);
    }
    if (!preg_match('/[A-Z]/', $password)) {
        jsonOut(['success'=>false, 'message'=>'Password must contain at least 1 uppercase letter (Caps Lock).']);
    }
    if (!preg_match('/[0-9]/', $password)) {
        jsonOut(['success'=>false, 'message'=>'Password must contain at least 1 number (0-9).']);
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        jsonOut(['success'=>false, 'message'=>'Password must contain at least 1 special character (e.g. !@#$%^&*).']);
    }
    if ($confirm && $password !== $confirm) {
        jsonOut(['success'=>false, 'message'=>'Passwords do not match. Please re-enter your password accurately.']);
    }

    // Verify token
    $sessionToken = $_SESSION['pwd_reset_verified']['token'] ?? '';
    $sessionEmail = strtolower($_SESSION['pwd_reset_verified']['email'] ?? '');
    $sessionExp   = $_SESSION['pwd_reset_verified']['expires_at'] ?? 0;

    $stmt = $db->prepare("SELECT id FROM password_resets WHERE LOWER(email) = ? AND token = ? LIMIT 1");
    $stmt->execute([$email, $resetToken]);
    $dbRow = $stmt->fetch();

    $isAuthorized = false;
    if ($dbRow) {
        $isAuthorized = true;
    } else if (($sessionEmail === $email || !$sessionEmail) && $sessionToken && hash_equals($sessionToken, $resetToken) && $sessionExp > time()) {
        $isAuthorized = true;
    }

    if (!$isAuthorized) {
        jsonOut(['success'=>false, 'message'=>'Reset authorization expired or invalid. Please request a new code.']);
    }

    // Hash the password with standard Bcrypt
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Update user password and clear any lockout
    $updateStmt = $db->prepare("UPDATE users SET password = ?, failed_attempts = 0, lockout_until = NULL WHERE LOWER(email) = ?");
    $updateStmt->execute([$hash, $email]);

    // Fetch user for in-app notification
    $uStmt = $db->prepare("SELECT id, first_name FROM users WHERE LOWER(email) = ? LIMIT 1");
    $uStmt->execute([$email]);
    $targetUser = $uStmt->fetch();

    if ($targetUser) {
        try {
            $notif = $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'system', 0, NOW())");
            $notif->execute([
                $targetUser['id'],
                'Password Successfully Updated',
                'Your account password was successfully reset via email verification. You can now use your new password.'
            ]);
        } catch (Exception $e) {}
    }

    // Clear reset tokens from DB and session
    $db->prepare("DELETE FROM password_resets WHERE LOWER(email) = ?")->execute([$email]);
    unset($_SESSION['pwd_reset'], $_SESSION['pwd_reset_verified']);

    jsonOut([
        'success' => true,
        'email'   => $email,
        'message' => 'Password successfully reset! You can now log in using your new password.'
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}
