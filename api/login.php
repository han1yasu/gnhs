<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false,'message'=>'Method not allowed.'], 405);
}

$raw  = json_decode(file_get_contents('php://input'), true);
$email    = trim($raw['email']    ?? '');
$password = trim($raw['password'] ?? '');
$role     = trim($raw['role']     ?? '');

if (!$email || !$password) {
    jsonOut(['success'=>false,'message'=>'Email and password are required.']);
}

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
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
    $secret = $user['totp_secret'];
    if (empty($secret)) {
        require_once __DIR__ . '/../src/PureOTP.php';
        $secret = PureOTP::generateSecret();
        $db->prepare("UPDATE users SET totp_secret = ? WHERE id = ?")->execute([$secret, $user['id']]);
    }
    
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
    ];
    
    jsonOut(['success'=>true, 'require_2fa'=>true, 'redirect'=>'/gnhs-guidance/pages/totp_verify.php']);

    // Store session for normal login (No 2FA)
    $_SESSION['user'] = [
        'id'           => $user['id'],
        'first_name'   => $user['first_name'],
        'last_name'    => $user['last_name'],
        'email'        => $user['email'],
        'id_number'    => $user['id_number'],
        'role'         => $user['role'],
        'grade_section'=> $user['grade_section'],
        'department'   => $user['department'] ?? null,
        'avatar_initials' => $user['avatar_initials'] ?? strtoupper($user['first_name'][0].($user['last_name'][0]??'')),
        'avatar_photo'   => $user['avatar_photo'] ?? null,
        'advisory_class' => $user['advisory_class'] ?? null,
    ];

    $redirectMap = [
        'student' => '/gnhs-guidance/pages/student-dashboard.php',
        'teacher' => '/gnhs-guidance/pages/teacher-dashboard.php',
        'admin'   => '/gnhs-guidance/pages/admin-dashboard.php',
    ];

    jsonOut(['success'=>true,'redirect'=>$redirectMap[$user['role']] ?? '/gnhs-guidance/index.html','user'=>$_SESSION['user']]);

} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>'Server error: ' . $e->getMessage()], 500);
}



