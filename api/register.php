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
    jsonOut(['success'=>false,'message'=>'Password must be at least 8 characters.']);
}
if (!in_array($role, ['student','teacher','admin'])) {
    jsonOut(['success'=>false,'message'=>'Invalid role.']);
}

try {
    $db = getDB();

    // Check duplicate email
    $chk = $db->prepare("SELECT id FROM users WHERE email = ? OR id_number = ? LIMIT 1");
    $chk->execute([$email, $idNumber]);
    if ($chk->fetch()) {
        jsonOut(['success'=>false,'message'=>'An account with that email or ID number already exists.']);
    }

    $hash    = password_hash($password, PASSWORD_BCRYPT);
    $initials = strtoupper($firstName[0].($lastName[0]??''));

    $stmt = $db->prepare("INSERT INTO users (first_name,last_name,email,id_number,password,role,grade_section,avatar_initials,middle_name,suffix) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$firstName,$lastName,$email,$idNumber,$hash,$role,$grade,$initials,$middleName,$suffix]);

    jsonOut(['success'=>true,'message'=>'Account created successfully! You may now log in.']);

} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>'Server error. Please try again later.'], 500);
}



