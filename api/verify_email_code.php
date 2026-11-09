<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($raw['email'] ?? ''));
$code  = trim($raw['code'] ?? '');
$role  = strtolower(trim($raw['role'] ?? 'student'));

if (!$email || !$code) {
    jsonOut(['success'=>false, 'message'=>'Email and verification code are required.']);
}

$isValid = false;
$detectedRole = $role;

// 1. Check database verification codes
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM `email_verification_codes` WHERE `email` = ? AND `code` = ? AND `is_used` = 0 AND `expires_at` > NOW() ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([$email, $code]);
    $otpRecord = $stmt->fetch();
    
    if ($otpRecord) {
        $isValid = true;
        $detectedRole = $otpRecord['role'] ?: $role;
        // Mark code as used
        $db->prepare("UPDATE `email_verification_codes` SET `is_used` = 1 WHERE `id` = ?")->execute([$otpRecord['id']]);
    }
} catch (Exception $e) {
    // DB error fallback
}

// 2. Check session fallback
if (!$isValid && isset($_SESSION['pending_email_otp'])) {
    $sOtp = $_SESSION['pending_email_otp'];
    if ($sOtp['email'] === $email && $sOtp['code'] === $code && $sOtp['expires_at'] > time()) {
        $isValid = true;
        $detectedRole = $sOtp['role'] ?: $role;
        unset($_SESSION['pending_email_otp']);
    }
}

if (!$isValid) {
    jsonOut(['success'=>false, 'message'=>'Invalid or expired verification code. Please try again.']);
}

// Auto-detect teacher role if email is @deped.gov.ph
if (str_ends_with($email, 'deped.gov.ph')) {
    $detectedRole = 'teacher';
}

// Code is valid! Now check if user exists in `users` table
try {
    $userStmt = $db->prepare("SELECT * FROM `users` WHERE LOWER(`email`) = ? AND (`is_active` = 1 OR `is_active` IS NULL) LIMIT 1");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();

    if ($user) {
        // User exists! Log them in immediately.
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_name']   = trim($user['first_name'] . ' ' . $user['last_name']);
        $_SESSION['user_role']   = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar_photo'] ?? null;
        $_SESSION['last_activity'] = time();

        $map = [
            'student' => 'pages/student-dashboard.php',
            'teacher' => 'pages/teacher-dashboard.php',
            'admin'   => 'pages/admin-dashboard.php'
        ];
        $redirect = $map[$user['role']] ?? 'pages/student-dashboard.php';

        jsonOut([
            'success'   => true,
            'is_new'    => false,
            'user_name' => $_SESSION['user_name'],
            'role'      => $user['role'],
            'redirect'  => $redirect,
            'message'   => 'Welcome back, ' . htmlspecialchars($_SESSION['user_name']) . '!'
        ]);
    } else {
        // New user! They need to complete their profile (name, grade/section or dept)
        // Store verification token in session
        $_SESSION['verified_email_signup'] = [
            'email' => $email,
            'role'  => $detectedRole,
            'verified_at' => time()
        ];

        jsonOut([
            'success'   => true,
            'is_new'    => true,
            'email'     => $email,
            'role'      => $detectedRole,
            'message'   => 'Email verified! Please complete your name to continue.'
        ]);
    }
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()]);
}
