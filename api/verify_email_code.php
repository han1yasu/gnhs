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
        // User exists! Google/Gmail code verified: Log directly in to dashboard (no 2FA required for Gmail code login)
        $role = $user['role'];
        $_SESSION['user'] = [
            'id'              => (int)$user['id'],
            'email'           => $user['email'],
            'first_name'      => $user['first_name'],
            'last_name'       => $user['last_name'],
            'id_number'       => $user['id_number'],
            'role'            => $role,
            'grade_section'   => $user['grade_section'],
            'department'      => $user['department'] ?? null,
            'avatar_initials' => $user['avatar_initials'] ?? strtoupper($user['first_name'][0].($user['last_name'][0]??'')),
            'avatar_photo'    => $user['avatar_photo'] ?? null,
            'advisory_class'  => $user['advisory_class'] ?? null,
            'is_setup_complete' => (int)($user['is_setup_complete'] ?? 0),
            'age'             => $user['age'] ?? null,
        ];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['pending_user']);
        unset($_SESSION['pending_email_otp']);

        $homeMap = [
            'student' => '/gnhs-guidance/pages/student-dashboard.php',
            'teacher' => '/gnhs-guidance/pages/teacher-dashboard.php',
            'admin'   => '/gnhs-guidance/pages/admin-dashboard.php'
        ];
        $dest = $homeMap[$role] ?? '/gnhs-guidance/index.html';

        if ($role === 'student' && empty($user['is_setup_complete'])) {
            $dest = '/gnhs-guidance/pages/student-setup.php';
        } elseif ($role === 'teacher' && empty($user['is_setup_complete'])) {
            $dest = '/gnhs-guidance/pages/teacher-setup.php';
        }

        jsonOut([
            'success'     => true,
            'is_new'      => false,
            'require_2fa' => false,
            'redirect'    => $dest,
            'user_name'   => trim($user['first_name'] . ' ' . $user['last_name']),
            'role'        => $role,
            'message'     => 'Gmail verified! Logging you in to your dashboard...'
        ]);
    } else {
        jsonOut([
            'success' => false,
            'is_new'  => false,
            'message' => 'No GNHS Guidance account found for "' . htmlspecialchars($email) . '". Please contact your Advisory Teacher or Guidance Counselor to create your account.'
        ]);
    }
} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()]);
}

