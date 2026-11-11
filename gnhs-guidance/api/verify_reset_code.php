<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw   = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($raw['email'] ?? ''));
$code  = trim($raw['code'] ?? '');

if (!$email || !$code) {
    jsonOut(['success'=>false, 'message'=>'Email and 6-digit confirmation code are required.']);
}

try {
    $db = getDB();

    // Verify token from database
    $stmt = $db->prepare("SELECT id, email, token, expires_at FROM password_resets WHERE LOWER(email) = ? AND token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email, $code]);
    $row = $stmt->fetch();

    $sessionCode = $_SESSION['pwd_reset']['code'] ?? '';
    $sessionEmail = strtolower($_SESSION['pwd_reset']['email'] ?? '');
    $sessionExp = $_SESSION['pwd_reset']['expires_at'] ?? 0;

    $isValid = false;
    if ($row) {
        $isValid = true;
    } else if ($sessionEmail === $email && $sessionCode === $code && $sessionExp > time()) {
        $isValid = true;
    }

    if (!$isValid) {
        jsonOut(['success'=>false, 'message'=>'Invalid or expired verification code. Please check your email or request a new code.']);
    }

    // Generate a secure single-use reset authorization token
    $resetToken = bin2hex(random_bytes(24));
    $_SESSION['pwd_reset_verified'] = [
        'email'      => $email,
        'token'      => $resetToken,
        'expires_at' => time() + 900 // 15 mins to set new password
    ];

    // Update password_resets with this verified token
    $db->prepare("UPDATE password_resets SET token = ? WHERE LOWER(email) = ?")->execute([$resetToken, $email]);

    jsonOut([
        'success'     => true,
        'reset_token' => $resetToken,
        'message'     => 'Verification code confirmed! You can now set your new password.'
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}
