<?php
// ── GNHS Guidance System — Check Password Reset Request Status & Updates ──
require_once __DIR__ . '/../includes/config.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = json_decode(file_get_contents('php://input'), true);
$identifier = trim($raw['identifier'] ?? '');
$ans1       = trim($raw['answer_1'] ?? '');

if (empty($identifier)) {
    jsonOut(['success' => false, 'message' => 'Please enter your registered Student ID / LRN or Email.']);
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
        jsonOut(['success' => false, 'message' => 'No account found matching this Student ID or Email. Please check your credentials.']);
    }

    $userId = (int)$user['id'];
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);

    // Find the latest password reset request for this user
    $reqStmt = $db->prepare("SELECT prr.*, 
        CONCAT(COALESCE(c.first_name, 'Guidance'), ' ', COALESCE(c.last_name, 'Counselor')) AS counselor_name
        FROM password_reset_requests prr
        LEFT JOIN users c ON prr.reviewed_by = c.id
        WHERE prr.user_id = ?
        ORDER BY prr.id DESC LIMIT 1");
    $reqStmt->execute([$userId]);
    $prr = $reqStmt->fetch();

    if (!$prr) {
        jsonOut([
            'success'     => true,
            'has_request' => false,
            'full_name'   => $fullName,
            'message'     => 'No password reset request found on file for this account. If you cannot log in, you may submit a reset request.'
        ]);
    }

    $counselorName = !empty($prr['counselor_name']) ? trim($prr['counselor_name']) : 'Guidance Counselor Milena Jacob';

    // 1. Pending status
    if ($prr['status'] === 'pending') {
        $dt = new DateTime($prr['created_at']);
        jsonOut([
            'success'        => true,
            'has_request'    => true,
            'status'         => 'pending',
            'full_name'      => $fullName,
            'id_number'      => $user['id_number'],
            'email'          => $user['email'],
            'created_at'     => $prr['created_at'],
            'created_at_fmt' => $dt->format('M d, Y h:i A'),
            'counselor_name' => $counselorName,
            'message'        => "Your password reset request is currently PENDING review by {$counselorName}. Please check back shortly or visit the Guidance Office."
        ]);
    }

    // 2. Rejected status
    if ($prr['status'] === 'rejected') {
        $dt = !empty($prr['reviewed_at']) ? new DateTime($prr['reviewed_at']) : new DateTime($prr['created_at']);
        jsonOut([
            'success'         => true,
            'has_request'     => true,
            'status'          => 'rejected',
            'full_name'       => $fullName,
            'admin_notes'     => $prr['admin_notes'] ?: 'In-person identity verification required.',
            'counselor_name'  => $counselorName,
            'reviewed_at_fmt' => $dt->format('M d, Y h:i A'),
            'message'         => "Your password reset request was reviewed but could not be approved. Remarks: " . ($prr['admin_notes'] ?: 'Please visit the Guidance Office personally.')
        ]);
    }

    // 3. Completed status (user already logged in and changed their password)
    if ($prr['status'] === 'completed') {
        jsonOut([
            'success'     => true,
            'has_request' => true,
            'status'      => 'completed',
            'full_name'   => $fullName,
            'message'     => 'Your previous password reset request was already completed and your password was updated. If you forgot your password again, please submit a new request.'
        ]);
    }

    // 4. Approved status — Password has been changed by Guidance!
    if ($prr['status'] === 'approved') {
        $hasQuestions = !empty($user['security_question_1']) && !empty($user['security_answer_1']);
        $dt = !empty($prr['reviewed_at']) ? new DateTime($prr['reviewed_at']) : new DateTime($prr['created_at']);

        // Check if security answer was provided
        if ($hasQuestions && empty($ans1)) {
            // Require user to answer security question 1 to reveal the password
            jsonOut([
                'success'                 => true,
                'has_request'             => true,
                'status'                  => 'approved',
                'requires_security_check' => true,
                'verified'                => false,
                'question_1'              => $user['security_question_1'],
                'full_name'               => $fullName,
                'email'                   => $user['email'],
                'id_number'               => $user['id_number'],
                'counselor_name'          => $counselorName,
                'reviewed_at_fmt'         => $dt->format('M d, Y h:i A'),
                'admin_notes'             => $prr['admin_notes'] ?: 'Temporary password issued by Guidance Office.',
                'message'                 => "Great news! {$counselorName} has approved your request and updated your password. Answer your security question below to reveal your new password."
            ]);
        }

        // Verify security question answer if questions exist
        if ($hasQuestions) {
            $cleanAns1 = strtolower($ans1);
            $storedHash1 = $user['security_answer_1'];
            $passed1 = password_verify($cleanAns1, $storedHash1) || ($cleanAns1 === strtolower($storedHash1));

            if (!$passed1) {
                jsonOut([
                    'success'  => false,
                    'status'   => 'approved',
                    'verified' => false,
                    'message'  => 'Security question verification failed. Your answer does not match our records. Please try again or visit Guidance Counselor Milena Jacob.'
                ]);
            }
        }

        // Return the changed/temporary password!
        jsonOut([
            'success'         => true,
            'has_request'     => true,
            'status'          => 'approved',
            'verified'        => true,
            'temp_password'   => $prr['temp_password'],
            'admin_notes'     => $prr['admin_notes'] ?: 'Approved and temporary password issued by Guidance Office.',
            'counselor_name'  => $counselorName,
            'reviewed_at'     => $prr['reviewed_at'],
            'reviewed_at_fmt' => $dt->format('M d, Y h:i A'),
            'full_name'       => $fullName,
            'email'           => $user['email'],
            'id_number'       => $user['id_number'],
            'message'         => "Password reset is DONE! Guidance Counselor {$counselorName} has changed your password. You can now log in using the credentials below."
        ]);
    }

    // Default fallback
    jsonOut([
        'success'     => true,
        'has_request' => true,
        'status'      => $prr['status'],
        'full_name'   => $fullName,
        'message'     => "Your request status: " . ucfirst($prr['status'])
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
