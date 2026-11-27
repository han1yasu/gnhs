<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('staff');
header('Content-Type: application/json');

$raw          = json_decode(file_get_contents('php://input'), true);
$studentName  = trim($raw['student_name']  ?? '');
$gradeSection = trim($raw['grade_section'] ?? '');
$concernType  = trim($raw['concern_type']  ?? '');
$urgency      = trim($raw['urgency']       ?? 'moderate');
$observations = trim($raw['observations']  ?? '');

if (!$studentName || !$gradeSection || !$concernType || !$observations) {
    jsonOut(['success'=>false,'message'=>'All required fields must be filled.']);
}

// Validate observations quality: min 30 chars, max 400 words, spam/gibberish rejection
$quality = validateContentQuality($observations, 30, 400);
if (!$quality['valid']) {
    jsonOut(['success'=>false, 'message'=>$quality['message']]);
}

if (strlen($studentName) < 3) {
    jsonOut(['success'=>false, 'message'=>'Student name must be at least 3 characters long.']);
}

// Profanity filter
if (containsProfanity($studentName) || containsProfanity($observations)) {
    jsonOut(['success'=>false,'message'=>'Please remove inappropriate language or profanity from your submission.']);
}

require_once __DIR__ . '/../includes/summarizer.php';

try {
    $db      = getDB();
    $refNum  = generateRefNumber();
    
    // Generate AI summary via Gemini API or intelligent guidance fallback
    $aiSummary = generateReferralSummary($concernType, $studentName, $observations, $urgency);

    // Attempt to match student in users table
    $studentId = !empty($raw['student_id']) ? (int)$raw['student_id'] : null;
    if (!$studentId) {
        $sStmt = $db->prepare("SELECT id FROM users 
            WHERE role='student' 
              AND (
                LOWER(TRIM(CONCAT(first_name, ' ', last_name))) = LOWER(?)
                OR LOWER(TRIM(CONCAT(last_name, ' ', first_name))) = LOWER(?)
                OR LOWER(TRIM(last_name)) = LOWER(?)
                OR LOWER(?) LIKE CONCAT('%', LOWER(TRIM(last_name)), '%')
              )
            ORDER BY (CASE WHEN grade_section = ? THEN 1 ELSE 2 END), id ASC LIMIT 1");
        $sStmt->execute([$studentName, $studentName, $studentName, $studentName, $gradeSection]);
        $studentMatch = $sStmt->fetch();
        if ($studentMatch) {
            $studentId = (int)$studentMatch['id'];
        }
    }

    // Check if referrals table has student_id column
    $hasStudentIdCol = false;
    try {
        $chk = $db->query("SHOW COLUMNS FROM referrals LIKE 'student_id'")->fetch();
        if ($chk) {
            $hasStudentIdCol = true;
        } else {
            // Attempt auto-migration
            $db->exec("ALTER TABLE referrals ADD COLUMN student_id INT(11) NULL DEFAULT NULL AFTER teacher_id");
            $hasStudentIdCol = true;
        }
    } catch (Exception $e) {
        $hasStudentIdCol = false;
    }

    if ($hasStudentIdCol) {
        $stmt = $db->prepare("INSERT INTO referrals (ref_number,teacher_id,student_id,student_name,grade_section,concern_type,urgency,observations,ai_summary) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$refNum,$user['id'],$studentId,$studentName,$gradeSection,$concernType,$urgency,$observations,$aiSummary ?: null]);
    } else {
        $stmt = $db->prepare("INSERT INTO referrals (ref_number,teacher_id,student_name,grade_section,concern_type,urgency,observations,ai_summary) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$refNum,$user['id'],$studentName,$gradeSection,$concernType,$urgency,$observations,$aiSummary ?: null]);
    }

    // Notify admins
    $admins = $db->query("SELECT id FROM users WHERE role IN ('admin','super_admin')")->fetchAll();
    $ns = $db->prepare("INSERT INTO notifications (user_id,title,message,type) VALUES (?,?,?,'referral')");
    foreach ($admins as $a) {
        $ns->execute([$a['id'],"New Referral: $refNum","Teacher {$user['first_name']} {$user['last_name']} submitted a ".strtoupper($urgency)." urgency referral."]);
    }

    // Notify student that they have been referred to guidance
    if ($studentId) {
        $teacherFullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Your Teacher';
        $concernLabel = ucfirst(str_replace('_', ' ', $concernType));
        $sTitle = "Guidance Referral Submitted";
        $sMsg = "Teacher {$teacherFullName} has submitted a guidance referral on your behalf regarding \"{$concernLabel}\". The Guidance Office has received this and will coordinate with you for guidance and counseling support.";
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'referral', 0, NOW())")
           ->execute([$studentId, $sTitle, $sMsg]);
    }

    jsonOut(['success'=>true,'ref_number'=>$refNum]);
} catch (Exception $e) {
    jsonOut(['success'=>false,'message'=>$e->getMessage()],500);
}





