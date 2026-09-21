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
    
    // Fetch the user's secret from the database
    $stmt = $db->prepare("SELECT totp_secret FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['totp_secret'])) {
        jsonOut(['success'=>false, 'message'=>'2FA is not properly configured for this account.']);
    }

    $secret = $user['totp_secret'];

    // Verify the TOTP code
    if (PureOTP::verifyCode($secret, $code, 3)) { // 3 slices = +/- 90 seconds tolerance
        // Code is correct, promote pending_user to full user session
        $_SESSION['user'] = $_SESSION['pending_user'];
        unset($_SESSION['pending_user']);
        
        $role = $_SESSION['user']['role'];
        $redirectMap = [
            'student' => '/gnhs-guidance/pages/student-dashboard.php',
            'teacher' => '/gnhs-guidance/pages/teacher-dashboard.php',
            'admin'   => '/gnhs-guidance/pages/admin-dashboard.php',
        ];
        
        jsonOut(['success'=>true, 'redirect'=>$redirectMap[$role] ?? '/gnhs-guidance/index.html']);
    } else {
        jsonOut(['success'=>false, 'message'=>'Incorrect code. Please try again.']);
    }

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Server error: ' . $e->getMessage()], 500);
}



