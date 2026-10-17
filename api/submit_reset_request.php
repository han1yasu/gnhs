<?php
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$identifier = trim($raw['identifier'] ?? '');
$ans1       = trim($raw['answer_1'] ?? '');
$ans2       = trim($raw['answer_2'] ?? '');

if (empty($identifier)) {
    jsonOut(['success' => false, 'message' => 'Please provide your registered Student ID or Email.']);
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE (email = ? OR id_number = ?) AND (is_active = 1 OR is_active IS NULL) LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonOut(['success' => false, 'message' => 'Account not found. Please double-check your credentials.']);
    }

    $userId = (int)$user['id'];
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
    $email = $user['email'];
    $idNum = $user['id_number'];
    $role = $user['role'];
    $gradeSec = $user['grade_section'];

    $hasQuestions = !empty($user['security_question_1']) && !empty($user['security_answer_1']);

    if ($hasQuestions) {
        if (empty($ans1) || (empty($ans2) && !empty($user['security_question_2']))) {
            jsonOut(['success' => false, 'message' => 'Please provide your answers to the security questions.']);
        }

        // Verify Answer 1
        $cleanAns1 = strtolower($ans1);
        $storedHash1 = $user['security_answer_1'];
        $passed1 = password_verify($cleanAns1, $storedHash1) || ($cleanAns1 === strtolower($storedHash1));

        // Verify Answer 2 if set
        $passed2 = true;
        if (!empty($user['security_question_2']) && !empty($user['security_answer_2'])) {
            $cleanAns2 = strtolower($ans2);
            $storedHash2 = $user['security_answer_2'];
            $passed2 = password_verify($cleanAns2, $storedHash2) || ($cleanAns2 === strtolower($storedHash2));
        }

        if (!$passed1 || !$passed2) {
            jsonOut(['success' => false, 'message' => 'Security question verification failed. Your answers do not match our records. Please try again or visit the Guidance Office.']);
        }
    }

    // Check for existing pending request
    $chkStmt = $db->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
    $chkStmt->execute([$userId]);
    $existing = $chkStmt->fetch();

    if ($existing) {
        jsonOut([
            'success' => true,
            'already_pending' => true,
            'message' => "Security identity verified! You already have a pending password reset request on file. Please visit Guidance Counselor Milena Jacob to claim your new password."
        ]);
    }

    // Insert new reset request
    $insert = $db->prepare("INSERT INTO password_reset_requests 
        (user_id, email, id_number, full_name, role, grade_section, security_verified, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    $insert->execute([$userId, $email, $idNum, $fullName, $role, $gradeSec, $hasQuestions ? 1 : 0]);

    // Send high-priority notification to Guidance Counselor
    $adminUser = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if ($adminUser) {
        $notifTitle = "Password Reset Request: {$fullName}";
        $notifMsg   = "Student {$fullName} ({$gradeSec}, ID: {$idNum}) has verified their security test questions and requested a password reset. Please review and issue new credentials.";
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'system', 0, NOW())")
           ->execute([$adminUser['id'], $notifTitle, $notifMsg]);
    }

    jsonOut([
        'success' => true,
        'message' => "Security identity verified! Your password reset request has been officially submitted to Guidance Counselor Milena Jacob. Please visit the Guidance Office to claim your new credentials."
    ]);
} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
