<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false, 'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$email     = strtolower(trim($raw['email'] ?? ''));
$firstName = trim($raw['first_name'] ?? '');
$lastName  = trim($raw['last_name'] ?? '');
$role      = strtolower(trim($raw['role'] ?? 'student'));
$gradeSec  = trim($raw['grade_section'] ?? '');
$idNumber  = trim($raw['id_number'] ?? '');

// Auto-detect teacher if email ends with deped.gov.ph
if (str_ends_with($email, 'deped.gov.ph')) {
    $role = 'teacher';
}

// Verify that the email was legitimately verified in session
$vSession = $_SESSION['verified_email_signup'] ?? null;
if (!$vSession || $vSession['email'] !== $email) {
    jsonOut(['success'=>false, 'message'=>'Email session expired. Please request a new verification code.']);
}

if (!$firstName || !$lastName) {
    jsonOut(['success'=>false, 'message'=>'First name and last name are required.']);
}

// If student, require grade & section
if ($role === 'student' && !$gradeSec) {
    jsonOut(['success'=>false, 'message'=>'Please select your Grade & Section.']);
}

// If teacher, advisory class can be gradeSec if chosen
$advisory = ($role === 'teacher') ? $gradeSec : null;

// Generate initials
$initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));

// If idNumber is blank, generate temporary school ID
if (!$idNumber) {
    $idNumber = ($role === 'student' ? 'STU-' : 'TCH-') . date('Y') . '-' . random_int(1000, 9999);
}

// Generate random secure password hash
$dummyPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

try {
    $db = getDB();
    
    // Check if user exists already
    $check = $db->prepare("SELECT id, role, first_name, last_name, avatar_photo FROM users WHERE LOWER(email) = ? LIMIT 1");
    $check->execute([$email]);
    $existing = $check->fetch();
    
    if ($existing) {
        // Log in existing
        $_SESSION['user_id']     = $existing['id'];
        $_SESSION['user_email']  = $email;
        $_SESSION['user_name']   = trim($existing['first_name'] . ' ' . $existing['last_name']);
        $_SESSION['user_role']   = $existing['role'];
        $_SESSION['user_avatar'] = $existing['avatar_photo'] ?? null;
        $_SESSION['last_activity'] = time();
        unset($_SESSION['verified_email_signup']);

        $map = [
            'student' => 'pages/student-dashboard.php',
            'teacher' => 'pages/teacher-dashboard.php',
            'admin'   => 'pages/admin-dashboard.php'
        ];
        jsonOut(['success'=>true, 'redirect'=>($map[$existing['role']] ?? 'pages/student-dashboard.php')]);
    }

    $ins = $db->prepare("INSERT INTO users (
        first_name, last_name, email, id_number, password, role, 
        grade_section, advisory_class, avatar_initials, is_active, is_setup_complete
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");

    $ins->execute([
        $firstName,
        $lastName,
        $email,
        $idNumber,
        $dummyPass,
        $role,
        ($role === 'student' ? $gradeSec : null),
        $advisory,
        $initials
    ]);

    $newId = $db->lastInsertId();

    // Log user in directly
    $_SESSION['user_id']     = $newId;
    $_SESSION['user_email']  = $email;
    $_SESSION['user_name']   = trim($firstName . ' ' . $lastName);
    $_SESSION['user_role']   = $role;
    $_SESSION['user_avatar'] = null;
    $_SESSION['last_activity'] = time();
    unset($_SESSION['verified_email_signup']);

    $map = [
        'student' => 'pages/student-dashboard.php',
        'teacher' => 'pages/teacher-dashboard.php',
        'admin'   => 'pages/admin-dashboard.php'
    ];
    $redirect = $map[$role] ?? 'pages/student-dashboard.php';

    jsonOut([
        'success'  => true,
        'redirect' => $redirect,
        'message'  => 'Account created successfully! Redirecting...'
    ]);

} catch (Exception $e) {
    jsonOut(['success'=>false, 'message'=>'Registration failed: ' . $e->getMessage()]);
}
