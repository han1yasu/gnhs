<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$identifier = trim($raw['identifier'] ?? '');

if (empty($identifier)) {
    jsonOut(['success' => false, 'message' => 'Please enter your registered Email or Student ID / LRN.']);
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, id_number, role, grade_section, 
        security_question_1, security_answer_1, security_question_2, security_answer_2 
        FROM users 
        WHERE (email = ? OR id_number = ?) AND (is_active = 1 OR is_active IS NULL) 
        LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonOut(['success' => false, 'message' => 'Account not found. Please double-check your Student ID or Email.']);
    }

    $hasQuestions = !empty($user['security_question_1']) && !empty($user['security_answer_1']) &&
                    !empty($user['security_question_2']) && !empty($user['security_answer_2']);

    jsonOut([
        'success'               => true,
        'user_id'               => (int)$user['id'],
        'full_name'             => trim($user['first_name'] . ' ' . $user['last_name']),
        'email'                 => $user['email'],
        'role'                  => $user['role'],
        'has_security_questions'=> $hasQuestions,
        'question_1'            => $hasQuestions ? $user['security_question_1'] : 'In-Person Guidance Identity Verification Required',
        'question_2'            => $hasQuestions ? $user['security_question_2'] : null,
    ]);
} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
