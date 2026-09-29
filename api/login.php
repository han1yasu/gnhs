<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false,'message'=>'Method not allowed.'], 405);
}

$raw  = json_decode(file_get_contents('php://input'), true);
$email    = trim($raw['email']    ?? '');
$password = (string)($raw['password'] ?? '');
$role     = trim($raw['role']     ?? '');

if (!$email || !$password) {
    jsonOut(['success'=>false,'message'=>'Email and password are required.']);
}

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR id_number = ?) AND is_active = 1 LIMIT 1");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonOut(['success'=>false,'message'=>'Invalid credentials. Please try again.']);
    }

    // Role check
    if ($role && $user['role'] !== $role) {
        $labels = ['student'=>'Student','teacher'=>'Teacher','admin'=>'Guidance Counselor'];
        jsonOut(['success'=>false,'message'=>'This account is registered as a '.($labels[$user['role']]??$user['role']).', not a '.($labels[$role]??$role).'.']);
    }

    // ── 2FA Check ──────────────────────────────────────────────────
    $secret = $user['totp_secret'] ?? null;
    if (empty($secret)) {
        require_once __DIR__ . '/../src/PureOTP.php';
        $secret = PureOTP::generateSecret();
        $db->prepare("UPDATE users SET totp_secret = ? WHERE id = ?")->execute([$secret, $user['id']]);
    }
    
    session_regenerate_id(true);
    $_SESSION['pending_user'] = [
        'id'           => $user['id'],
        'email'        => $user['email'],
        'first_name'   => $user['first_name'],
        'last_name'    => $user['last_name'],
        'id_number'    => $user['id_number'],
        'role'         => $user['role'],
        'grade_section'=> $user['grade_section'],
        'department'   => $user['department'] ?? null,
        'avatar_initials' => $user['avatar_initials'] ?? strtoupper($user['first_name'][0].($user['last_name'][0]??'')),
        'avatar_photo'   => $user['avatar_photo'] ?? null,
        'advisory_class' => $user['advisory_class'] ?? null,
        'is_setup_complete' => $user['is_setup_complete'] ?? 0,
        'age'            => $user['age'] ?? null,
    ];
    
    jsonOut(['success'=>true, 'require_2fa'=>true, 'redirect'=>'/pages/totp_verify.php']);

} catch (Exception $e) {
    error_log($e->getMessage());
    jsonOut(['success'=>false,'message'=>'Login is temporarily unavailable.'], 500);
}



