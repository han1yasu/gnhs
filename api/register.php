<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false,'message'=>'Method not allowed.'], 405);
}

$raw        = json_decode(file_get_contents('php://input'), true);
$firstName  = trim($raw['first_name']   ?? '');
$lastName   = trim($raw['last_name']    ?? '');
$email      = trim($raw['email']        ?? '');
$password   = trim($raw['password']     ?? '');
$confirm    = trim($raw['confirm']      ?? '');
$role       = trim($raw['role']         ?? 'student');
$idNumber   = trim($raw['id_number']    ?? '');
$grade      = trim($raw['grade_section'] ?? '');
$middleName = trim($raw['middle_name']  ?? '');
$suffix     = trim($raw['suffix']       ?? '');

// Validation
if (!$firstName || !$lastName || !$email || !$password || !$confirm || !$idNumber) {
    jsonOut(['success'=>false,'message'=>'All required fields must be filled.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(['success'=>false,'message'=>'Please enter a valid email address.']);
}
if ($password !== $confirm) {
    jsonOut(['success'=>false,'message'=>'Passwords do not match.']);
}
if (strlen($password) < 8) {
    jsonOut(['success'=>false,'message'=>'Password must be at least 8 characters long.']);
}
if (!preg_match('/[A-Z]/', $password)) {
    jsonOut(['success'=>false,'message'=>'Password must contain at least 1 uppercase letter (Caps Lock).']);
}
if (!preg_match('/[0-9]/', $password)) {
    jsonOut(['success'=>false,'message'=>'Password must contain at least 1 number.']);
}
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    jsonOut(['success'=>false,'message'=>'Password must contain at least 1 special character (e.g. !@#$%^&*).']);
}
if ($role === 'student' && !preg_match('/^\d{12}$/', $idNumber)) {
    jsonOut(['success'=>false,'message'=>'Learner Reference Number (LRN) must be strictly 12 numeric digits.']);
}
if (!in_array($role, ['student','teacher','admin'])) {
    jsonOut(['success'=>false,'message'=>'Invalid role.']);
}

try {
    $db = getDB();

    // Check duplicate ID Number / LRN first to prevent duplicate accounts and fake entries
    $chkId = $db->prepare("SELECT id, role FROM users WHERE id_number = ? LIMIT 1");
    $chkId->execute([$idNumber]);
    if ($chkId->fetch()) {
        if ($role === 'student') {
            jsonOut(['success' => false, 'message' => 'The 12-digit LRN is already registered in the system. Duplicate LRN accounts and fake entries are strictly prohibited.']);
        } else {
            jsonOut(['success' => false, 'message' => 'An account with that ID number already exists.']);
        }
    }

    // Check duplicate email
    $chkEmail = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chkEmail->execute([$email]);
    if ($chkEmail->fetch()) {
        jsonOut(['success' => false, 'message' => 'An account with that email address already exists. Please use a different email or log in.']);
    }

    $hash    = password_hash($password, PASSWORD_BCRYPT);
    $initials = strtoupper($firstName[0].($lastName[0]??''));

    require_once __DIR__ . '/../src/PureOTP.php';
    $totpSecret = PureOTP::generateSecret(16);

    $stmt = $db->prepare("INSERT INTO users (first_name,last_name,email,id_number,password,role,grade_section,avatar_initials,middle_name,suffix,totp_secret,totp_enabled) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)");
    $stmt->execute([$firstName,$lastName,$email,$idNumber,$hash,$role,$grade,$initials,$middleName,$suffix,$totpSecret]);
    $newUserId = (int)$db->lastInsertId();

    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['verified_email_signup']);
    }

    $fullName = trim($firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName . ($suffix ? ' ' . $suffix : ''));

    $_SESSION['pending_user'] = [
        'id'              => $newUserId,
        'email'           => $email,
        'first_name'      => $firstName,
        'last_name'       => $lastName,
        'id_number'       => $idNumber,
        'role'            => $role,
        'grade_section'   => $grade,
        'department'      => null,
        'avatar_initials' => $initials,
        'avatar_photo'    => null,
        'advisory_class'  => null,
        'is_setup_complete' => 0,
        'age'             => null,
    ];

    jsonOut([
        'success'     => true,
        'require_2fa' => true,
        'redirect'    => '/gnhs-guidance/pages/totp_verify.php',
        'message'     => 'Account created successfully! Proceeding directly to Two-Factor Authentication (2FA)...',
        'user'        => [
            'email'    => $email,
            'name'     => $fullName,
            'role'     => $role,
            'initials' => $initials
        ]
    ]);

} catch (PDOException $e) {
    if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062 Duplicate entry')) {
        if (str_contains($e->getMessage(), 'uq_id_number') || str_contains($e->getMessage(), 'id_number')) {
            jsonOut(['success' => false, 'message' => 'The 12-digit LRN is already registered in the system. Duplicate LRN accounts and fake entries are strictly prohibited.']);
        }
        jsonOut(['success' => false, 'message' => 'An account with that email or ID number already exists.']);
    }
    jsonOut(['success'=>false,'message'=>'Server error. Please try again later.'], 500);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>'Server error. Please try again later.'], 500);
}



