<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/PureOTP.php';

startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

if (empty($_SESSION['pending_user'])) {
    jsonOut(['success'=>false, 'message'=>'Session expired. Please log in again.']);
}

$raw = json_decode(file_get_contents('php://input'), true);
// Handle both JSON payload or standard POST array
$code = $raw['code'] ?? $_POST['code'] ?? '';
$code = preg_replace('/[^0-9]/', '', $code); // Clean input to just digits

if (empty($code) || strlen($code) !== 6) {
    jsonOut(['success'=>false, 'message'=>'Please enter a valid 6-digit code.']);
}

try {
    $db = getDB();
    $userId = $_SESSION['pending_user']['id'];
    
    // Fetch the user's secret and totp_enabled status from the database
    $stmt = $db->prepare("SELECT totp_secret, totp_enabled FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['totp_secret'])) {
        jsonOut(['success'=>false, 'message'=>'2FA is not properly configured for this account.']);
    }

    $secret = $user['totp_secret'];

    // Strictly verify the TOTP code with $discrepancy = 1 to allow +/- 30 seconds for clock drift
    if (PureOTP::verifyCode($secret, $code, 1)) {
        // Code is correct, promote pending_user to full user session
        
        if (empty($user['totp_enabled'])) {
            $db->prepare("UPDATE users SET totp_enabled = 1, totp_reset_requested = 0 WHERE id = ?")->execute([$userId]);
            $_SESSION['pending_user']['totp_enabled'] = 1;
        } else {
            $db->prepare("UPDATE users SET totp_reset_requested = 0 WHERE id = ?")->execute([$userId]);
        }
        $_SESSION['pending_user']['totp_reset_requested'] = 0;

        $_SESSION['user'] = $_SESSION['pending_user'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['pending_user']);
        
        $role = $_SESSION['user']['role'];
        $redirectMap = [
            'student'  => '/gnhs-guidance/pages/student-dashboard.php',
            'teacher'  => '/gnhs-guidance/pages/teacher-dashboard.php',
            'admin'    => '/gnhs-guidance/pages/admin-dashboard.php',
        ];
        
        $redirectUrl = $redirectMap[$role] ?? '/gnhs-guidance/index.html';
        if ($role === 'student' && empty($_SESSION['user']['is_setup_complete'])) {
            $redirectUrl = '/gnhs-guidance/pages/student-setup.php';
        } else if ($role === 'teacher' && empty($_SESSION['user']['is_setup_complete'])) {
            $redirectUrl = '/gnhs-guidance/pages/teacher-setup.php';
        }
        
        jsonOut(['success'=>true, 'redirect'=>$redirectUrl]);
    } else {
        // Check if the user entered an expired code that was active in the previous 30s cycle
        $wasPreviousCode = hash_equals(PureOTP::getCode($secret, time() - 30), $code);
        if ($wasPreviousCode) {
            jsonOut(['success' => false, 'message' => 'That code has expired! Verification is strictly timed to the active circle timer. Please enter the new code currently showing in your authenticator app.']);
        }
        jsonOut(['success'=>false, 'message'=>'Incorrect verification code. Please check your authenticator app and try again.']);
    }

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}



