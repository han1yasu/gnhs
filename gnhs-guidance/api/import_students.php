<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$user = requireLogin('admin,teacher');
$db   = getDB();

$raw = json_decode(file_get_contents('php://input'), true);
if (!$raw || empty($raw['students']) || !is_array($raw['students'])) {
    jsonOut(['success' => false, 'message' => 'No student data received.']);
}

$students = $raw['students'];
if (count($students) > 300) {
    jsonOut(['success' => false, 'message' => 'Maximum of 300 students can be imported in a single batch.']);
}

$isTeacher = ($user['role'] === 'teacher');
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

// 1. Collect all LRNs and Emails to do a single fast batch check against the database
$inputLrns = [];
$inputEmails = [];
foreach ($students as $s) {
    $lrn = trim($s['lrn'] ?? '');
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

// 2. Validate and Process each student
$results = [];
$importedCount = 0;
$skippedCount  = 0;
$seenBatchLrns = [];
$seenBatchEmails = [];

$insertStmt = $db->prepare("
    INSERT INTO users (
        first_name, last_name, middle_name, suffix,
        id_number, email, password, role,
        grade_section, avatar_initials
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'student', ?, ?)
");

try {
    $db->beginTransaction();

    foreach ($students as $idx => $s) {
        $rowNum = $idx + 1;
        $first  = trim($s['first_name'] ?? '');
        $last   = trim($s['last_name'] ?? '');
        $middle = trim($s['middle_name'] ?? '');
        $suffix = trim($s['suffix'] ?? '');
        $lrn    = trim($s['lrn'] ?? '');
        $email  = trim($s['email'] ?? '');
        $grade  = trim($s['grade_level'] ?? '');
        $sec    = trim($s['section'] ?? '');
        $pass   = trim($s['temporary_password'] ?? '');

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

        if (!preg_match('/^\d{12}$/', $lrn)) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'LRN must be strictly 12 numeric digits.'];
            $skippedCount++;
            continue;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Invalid email address format.'];
            $skippedCount++;
            continue;
        }

        if (!$gradeNum || !in_array($gradeNum, ['7','8','9','10']) || !$sec) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Valid Grade Level (7-10) and Section are required.'];
            $skippedCount++;
            continue;
        }

        $fullGradeSec = 'Grade ' . $gradeNum . ' - ' . $sec;

        // If teacher, verify that student matches teacher's advisory section
        if ($isTeacher && $fullGradeSec !== $teacherAdvisory) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => "Section must match your advisory class ($teacherAdvisory)."];
            $skippedCount++;
            continue;
        }

        // Check for duplicates in current database
        if (in_array((string)$lrn, $existingLrns, true)) {
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'LRN already registered in database.'];
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
            $results[] = ['row' => $rowNum, 'lrn' => $lrn, 'name' => "$first $last", 'status' => 'skipped', 'reason' => 'Duplicate LRN in this import file.'];
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
        if (!$pass) {
            $pass = 'Student@2026';
        } else {
            // Validate custom password standards
            if (strlen($pass) < 8 || !preg_match('/[A-Z]/', $pass) || !preg_match('/[0-9]/', $pass) || !preg_match('/[^A-Za-z0-9]/', $pass)) {
                $pass = 'Student@2026'; // fallback to compliant preset
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
            $fullGradeSec,
            $initials
        ]);

        $results[] = [
            'row' => $rowNum,
            'lrn' => $lrn,
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
        'message'  => "Import completed: {$importedCount} student accounts created successfully.",
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
