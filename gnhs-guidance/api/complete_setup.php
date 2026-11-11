<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if (empty($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['student', 'teacher'])) {
    jsonOut(['success'=>false,'message'=>'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success'=>false,'message'=>'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$firstName = trim($raw['first_name'] ?? '');
$middleName= trim($raw['middle_name'] ?? '');
$lastName  = trim($raw['last_name'] ?? '');
$role      = $_SESSION['user']['role'];

$q1 = trim($raw['security_question_1'] ?? '');
$a1 = trim($raw['security_answer_1'] ?? '');
$q2 = trim($raw['security_question_2'] ?? '');
$a2 = trim($raw['security_answer_2'] ?? '');

$hashedA1 = $a1 ? password_hash(strtolower($a1), PASSWORD_DEFAULT) : null;
$hashedA2 = $a2 ? password_hash(strtolower($a2), PASSWORD_DEFAULT) : null;

if (!$firstName || !$lastName) {
    jsonOut(['success'=>false,'message'=>'First and last name are required.']);
}

try {
    $db = getDB();
    $userId = $_SESSION['user']['id'];
    
    if ($role === 'student') {
        $age = (int)($raw['age'] ?? 0);
        $gradeSection = trim($raw['grade_section'] ?? '');
        
        if (!$age || !$gradeSection) {
            jsonOut(['success'=>false,'message'=>'Age and Grade & Section are required.']);
        }
        
        $stmt = $db->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, age=?, grade_section=?, 
            is_setup_complete=1 
            WHERE id=?");
        $stmt->execute([$firstName, $middleName, $lastName, $age, $gradeSection, $userId]);
        
        $_SESSION['user']['age'] = $age;
        $_SESSION['user']['grade_section'] = $gradeSection;
    } else if ($role === 'teacher') {
        $advisoryClass = trim($raw['advisory_class'] ?? '');
        $employeeId    = trim($raw['id_number'] ?? $raw['employee_id'] ?? '');
        $position      = trim($raw['position'] ?? '');
        $contactNumber = trim($raw['contact_number'] ?? '');
        
        // If employeeId is provided and different, check uniqueness
        if ($employeeId && $employeeId !== ($_SESSION['user']['id_number'] ?? '')) {
            $chkEmp = $db->prepare("SELECT id FROM users WHERE id_number = ? AND id != ? LIMIT 1");
            $chkEmp->execute([$employeeId, $userId]);
            if ($chkEmp->fetch()) {
                jsonOut(['success'=>false,'message'=>'Employee ID number is already registered to another account.']);
            }
        }
        
        $stmt = $db->prepare("UPDATE users SET first_name=?, middle_name=?, last_name=?, 
            id_number=COALESCE(NULLIF(?,''), id_number),
            position=?, contact_number=?, advisory_class=?, 
            is_setup_complete=1 
            WHERE id=?");
        $stmt->execute([$firstName, $middleName, $lastName, $employeeId, $position ?: null, $contactNumber ?: null, $advisoryClass ?: null, $userId]);
        
        if ($employeeId) $_SESSION['user']['id_number'] = $employeeId;
        $_SESSION['user']['position'] = $position ?: null;
        $_SESSION['user']['contact_number'] = $contactNumber ?: null;
        $_SESSION['user']['advisory_class'] = $advisoryClass ?: null;
    }
    
    // Update session
    $_SESSION['user']['first_name'] = $firstName;
    $_SESSION['user']['last_name'] = $lastName;
    $_SESSION['user']['is_setup_complete'] = 1;
    
    // Update initials just in case
    $initials = strtoupper($firstName[0].($lastName[0]??''));
    $db->prepare("UPDATE users SET avatar_initials=? WHERE id=?")->execute([$initials, $userId]);
    $_SESSION['user']['avatar_initials'] = $initials;
    
    jsonOut(['success'=>true]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
