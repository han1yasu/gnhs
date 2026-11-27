<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$user = requireLogin('admin,teacher');
$db   = getDB();

$raw = json_decode(file_get_contents('php://input'), true);
$rowsList = $raw['users'] ?? ($raw['students'] ?? []);
if (!$raw || empty($rowsList) || !is_array($rowsList)) {
    jsonOut(['success' => false, 'message' => 'No user data received.']);
}

$students = $rowsList;
if (count($students) > 300) {
    jsonOut(['success' => false, 'message' => 'Maximum of 300 user records can be imported in a single batch.']);
}

$isTeacher = ($user['role'] === 'teacher');
$isTopAdmin = isTopAdminRole($user['role'] ?? '');
$defaultRole = $isTeacher ? 'student' : strtolower(trim($raw['default_role'] ?? 'student'));
$allowedDefaults = $isTopAdmin ? ['student', 'teacher', 'admin', 'super_admin'] : ['student', 'teacher'];
if (!in_array($defaultRole, $allowedDefaults, true)) {
    $defaultRole = 'student';
}
$teacherAdvisory = $user['advisory_class'] ?? '';
$advisoryGradeNum = '';
$advisorySection = '';
if ($isTeacher) {
    if (!$teacherAdvisory) {
        jsonOut(['success' => false, 'message' => 'You do not have an advisory class assigned. Please contact the Guidance Counselor.'], 403);
    }
    $parts = explode(' - ', $teacherAdvisory, 2);
    $advisoryGradeNum = preg_replace('/[^0-9]/', '', $parts[0] ?? '');
    $advisorySection  = trim($parts[1] ?? '');
}

// 1. Collect all ID Numbers / LRNs and Emails to do a single fast batch check against the database
$inputLrns = [];
$inputEmails = [];
foreach ($students as $s) {
    $lrn = trim($s['id_number'] ?? ($s['lrn'] ?? ''));
    $em  = strtolower(trim($s['email'] ?? ''));
    if ($lrn) $inputLrns[] = $lrn;
    if ($em)  $inputEmails[] = $em;
}

$existingLrns = [];
if (!empty($inputLrns)) {
    $placeholders = implode(',', array_fill(0, count($inputLrns), '?'));
    $stmt = $db->prepare("SELECT id_number FROM users WHERE id_number IN ($placeholders)");
    $stmt->execute($inputLrns);
    $existingLrns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $existingLrns = array_map('strval', $existingLrns);
}

$existingEmails = [];
if (!empty($inputEmails)) {
    $placeholders = implode(',', array_fill(0, count($inputEmails), '?'));
    $stmt = $db->prepare("SELECT LOWER(email) FROM users WHERE LOWER(email) IN ($placeholders)");
    $stmt->execute($inputEmails);
    $existingEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 2. Validate and Process each user
$results = [];
$importedCount = 0;
$skippedCount  = 0;
$seenBatchLrns = [];
$seenBatchEmails = [];

$insertStmt = $db->prepare("
    INSERT INTO users (
        first_name, last_name, middle_name, suffix,
        id_number, email, password, role, position,
        grade_section, avatar_initials
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

try {
    $db->beginTransaction();

    foreach ($students as $idx => $s) {
        $rowNum   = $idx + 1;
        $first    = trim($s['first_name'] ?? '');
        $last     = trim($s['last_name'] ?? '');
        $middle   = trim($s['middle_name'] ?? '');
        $suffix   = trim($s['suffix'] ?? '');
        $lrn      = trim($s['id_number'] ?? ($s['lrn'] ?? ''));
        $email    = trim($s['email'] ?? '');
        $grade    = trim($s['grade_level'] ?? '');
        $sec      = trim($s['section'] ?? '');
        $pass     = trim($s['temporary_password'] ?? '');
        $position = trim($s['position'] ?? '');

        $rawRole = $isTeacher ? 'student' : strtolower(trim($s['role'] ?? $defaultRole));
        $rowRole = 'student';
        if (!$isTeacher) {
            if (in_array($rawRole, ['teacher', 'faculty', 'adviser', 'instructor'], true)) {
                $rowRole = 'teacher';
            } elseif (in_array($rawRole, ['super_admin', 'super admin', 'system admin', 'principal', 'developer', 'it'], true)) {
                if (!$isTopAdmin) {
                    $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Only Top Administrators can import Principal/Developer accounts.'];
                    $skippedCount++;
                    continue;
                }
                $rowRole = 'super_admin';
                if ($position === '') {
                    $position = ($rawRole === 'developer' || $rawRole === 'it') ? 'Developer' : 'Principal';
                }
            } elseif (in_array($rawRole, ['admin', 'guidance', 'counselor', 'guidance counselor', 'staff'], true)) {
                if (!$isTopAdmin) {
                    $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Only Top Administrators can import Guidance Counselor accounts.'];
                    $skippedCount++;
                    continue;
                }
                $rowRole = 'admin';
                if ($position === '') $position = 'Guidance Counselor';
            } elseif ($rawRole === 'student' || $rawRole === '') {
                $rowRole = 'student';
            }
        }

        // Fallback for advisory teachers
        if ($isTeacher) {
            if (!$grade) $grade = $advisoryGradeNum;
            if (!$sec)   $sec   = $advisorySection;
        }

        // Clean grade: e.g. "Grade 9" -> "9"
        $gradeNum = preg_replace('/[^0-9]/', '', $grade);

        // Validation Checks
        if (!$first || !$last) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'First name and last name are required.'];
            $skippedCount++;
            continue;
        }

        if ($rowRole === 'student' && !preg_match('/^\d{12}$/', $lrn)) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Student LRN must be strictly 12 numeric digits.'];
            $skippedCount++;
            continue;
        }
        if ($rowRole !== 'student' && $lrn === '') {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Employee / Staff ID Number is required.'];
            $skippedCount++;
            continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Invalid email address format.'];
            $skippedCount++;
            continue;
        }

        $fullGradeSec = '';
        if ($rowRole === 'student') {
            if (!$gradeNum || !in_array($gradeNum, ['7','8','9','10']) || !$sec) {
                $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Valid Grade Level (7-10) and Section are required for students.'];
                $skippedCount++;
                continue;
            }
            $fullGradeSec = 'Grade ' . $gradeNum . ' - ' . $sec;
        }

        // If teacher, verify that student matches teacher's advisory section
        if ($isTeacher && $fullGradeSec !== $teacherAdvisory) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => "Section must match your advisory class ($teacherAdvisory)."];
            $skippedCount++;
            continue;
        }

        // Check for duplicates in current database
        if (in_array((string)$lrn, $existingLrns, true)) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'ID / LRN already registered in database.'];
            $skippedCount++;
            continue;
        }

        if (in_array(strtolower($email), $existingEmails, true)) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Email already registered in database.'];
            $skippedCount++;
            continue;
        }

        // Check for duplicates within this same batch
        if (isset($seenBatchLrns[$lrn])) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Duplicate ID / LRN in this import file.'];
            $skippedCount++;
            continue;
        }
        if (isset($seenBatchEmails[strtolower($email)])) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Duplicate email in this import file.'];
            $skippedCount++;
            continue;
        }

        $seenBatchLrns[$lrn] = true;
        $seenBatchEmails[strtolower($email)] = true;

        // Default password if none provided
        $fallbackPwd = $rowRole === 'student' ? 'Student@2026' : 'Staff@2026';
        if (!$pass) {
            $pass = $fallbackPwd;
        } else {
            // Validate custom password standards
            if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[0-9]/', $pass) || !preg_match('/[^A-Za-z0-9]/', $pass)) {
                $pass = $fallbackPwd; // fallback to compliant preset
            }
        }

        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
        $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));

        $insertStmt->execute([
            $first,
            $last,
            $middle,
            $suffix,
            $lrn,
            $email,
            $hashedPassword,
            $rowRole,
            $position ?: null,
            $fullGradeSec ?: null,
            $initials
        ]);

        $results[] = [
            'row' => $rowNum,
            'lrn' => $lrn,
            'role' => $rowRole,
            'position' => $position,
            'name' => "$first $last",
            'email' => $email,
            'grade_section' => $fullGradeSec,
            'status' => 'created',
            'temp_password' => $pass
        ];
        $importedCount++;
    }

    $db->commit();

    jsonOut([
        'success'  => true,
        'message'  => "Import completed: {$importedCount} user account(s) created successfully.",
        'total'    => count($students),
        'imported' => $importedCount,
        'skipped'  => $skippedCount,
        'results'  => $results
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    jsonOut(['success' => false, 'message' => 'Database error during import: ' . $e->getMessage()], 500);
}
