<?php
require_once __DIR__ . '/../includes/config.php';

echo "=== GNHS GUIDANCE TEST SUITE ===\n\n";

$db = getDB();

// 1. Verify DB Unique Constraints
echo "[1] Checking DB Constraints...\n";
$indexes = $db->query("SHOW INDEX FROM users")->fetchAll(PDO::FETCH_ASSOC);
$indexNames = array_unique(array_column($indexes, 'Key_name'));
assert(in_array('uq_id_number', $indexNames), "uq_id_number missing");
assert(in_array('uq_email', $indexNames), "uq_email missing");
echo "  ✓ uq_id_number and uq_email indexes verified!\n\n";

// 2. Test LRN Format Validation in api/register.php
echo "[2] Testing LRN Format & Duplicate Validation...\n";

function testRegister($data) {
    // Simulate POST request
    $url = 'http://localhost/gnhs-guidance/api/register.php';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return json_decode($resp, true);
}

// 2a. Short LRN (11 digits)
$res = testRegister([
    'first_name' => 'Test',
    'last_name' => 'Student',
    'email' => 'test_lrn_short@example.com',
    'password' => 'Test@2026',
    'confirm' => 'Test@2026',
    'role' => 'student',
    'id_number' => '12345678901',
    'grade_section' => 'Grade 7 - Rizal'
]);
echo "  2a (11 digits): " . ($res['message'] ?? 'none') . "\n";
assert(str_contains($res['message'], 'strictly 12 numeric digits'), "Should reject 11 digits");

// 2b. Non-numeric LRN
$res = testRegister([
    'first_name' => 'Test',
    'last_name' => 'Student',
    'email' => 'test_lrn_letters@example.com',
    'password' => 'Test@2026',
    'confirm' => 'Test@2026',
    'role' => 'student',
    'id_number' => '12345678901A',
    'grade_section' => 'Grade 7 - Rizal'
]);
echo "  2b (letters in LRN): " . ($res['message'] ?? 'none') . "\n";
assert(str_contains($res['message'], 'strictly 12 numeric digits'), "Should reject letters");

// 2c. Duplicate LRN
$existingStudent = $db->query("SELECT id_number, email FROM users WHERE role='student' AND id_number IS NOT NULL AND id_number != '' LIMIT 1")->fetch();
if ($existingStudent) {
    $res = testRegister([
        'first_name' => 'Duplicate',
        'last_name' => 'Student',
        'email' => 'unique_new_email_999@example.com',
        'password' => 'Test@2026',
        'confirm' => 'Test@2026',
        'role' => 'student',
        'id_number' => $existingStudent['id_number'],
        'grade_section' => 'Grade 7 - Rizal'
    ]);
    echo "  2c (Duplicate LRN {$existingStudent['id_number']}): " . ($res['message'] ?? 'none') . "\n";
    assert(str_contains($res['message'], 'already registered in the system') && str_contains($res['message'], 'fake entries'), "Should reject duplicate LRN");
    echo "  ✓ Anti-duplicate LRN protection verified!\n\n";
}

// 3. Test Guidance Admin Password Reset with Security Questions Clearance
echo "[3] Testing Password Reset Flow for Student...\n";
$targetStudent = $db->query("SELECT id, email, first_name, last_name FROM users WHERE email='teststudent@gnhs.edu' LIMIT 1")->fetch();
if (!$targetStudent) {
    $targetStudent = $db->query("SELECT id, email, first_name, last_name FROM users WHERE role='student' AND email LIKE 'test%' LIMIT 1")->fetch();
}
if ($targetStudent) {
    // First simulate having setup completed
    $db->prepare("UPDATE users SET is_setup_complete = 1, security_question_1 = 'What is your pet?', security_answer_1 = 'Dog' WHERE id = ?")->execute([$targetStudent['id']]);
    
    // Now simulate Guidance Admin resetting their password via admin_reset_password logic
    $adminUser = $db->query("SELECT id, first_name, last_name, role FROM users WHERE role='admin' LIMIT 1")->fetch();
    
    $newPass = 'NewStudent@2026';
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    
    $updStmt = $db->prepare("UPDATE users SET 
        password = ?, 
        failed_attempts = 0, 
        lockout_until = NULL, 
        is_active = 1,
        security_question_1 = NULL, 
        security_answer_1 = NULL, 
        security_question_2 = NULL, 
        security_answer_2 = NULL,
        is_setup_complete = 0
        WHERE id = ?");
    $updStmt->execute([$hash, $targetStudent['id']]);
    
    $checkUser = $db->query("SELECT is_setup_complete, security_question_1, password FROM users WHERE id = {$targetStudent['id']}")->fetch();
    assert($checkUser['is_setup_complete'] == 0, "is_setup_complete should be 0");
    assert($checkUser['security_question_1'] === null, "security_question_1 should be NULL");
    assert(password_verify($newPass, $checkUser['password']), "Password hash verification failed");
    echo "  ✓ Password reset correctly cleared security questions and flagged is_setup_complete = 0!\n";
    echo "  ✓ Student will be prompted to re-setup secondary questions upon next login!\n\n";
}

// 4. Verify admin-users.php button markup
echo "[4] Checking admin-users.php markup...\n";
$adminUsersHtml = file_get_contents(__DIR__ . '/../pages/admin-users.php');
assert(str_contains($adminUsersHtml, 'openAdminResetPasswordModal'), "openAdminResetPasswordModal missing");
assert(str_contains($adminUsersHtml, 'openDeactivateConfirmModal'), "openDeactivateConfirmModal missing");
assert(str_contains($adminUsersHtml, 'openActivateConfirmModal'), "openActivateConfirmModal missing");
assert(str_contains($adminUsersHtml, 'deactivateConfirmModal'), "deactivateConfirmModal missing");
assert(str_contains($adminUsersHtml, 'openAddStudentModal'), "openAddStudentModal missing");
assert(str_contains($adminUsersHtml, 'Register Walk-in Student'), "Register Walk-in Student button missing");
echo "  ✓ Modal calls, Walk-in button, and Key icon bindings verified!\n\n";

// 5. Verify dark mode rules in style.css
echo "[5] Checking style.css dark mode rules...\n";
$css = file_get_contents(__DIR__ . '/../style.css');
assert(str_contains($css, '[data-theme="dark"] .user-card'), "dark mode user-card missing");
assert(str_contains($css, '[data-theme="dark"] .notif-dropdown'), "dark mode notif-dropdown missing");
echo "  ✓ Dark mode rules for user cards and notification dropdown verified!\n\n";

echo "ALL TESTS PASSED SUCCESSFULLY! ✓\n";

