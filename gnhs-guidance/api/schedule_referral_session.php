<?php
require_once __DIR__ . '/../includes/config.php';
$user = requireLogin('admin');
header('Content-Type: application/json');

$raw = json_decode(file_get_contents('php://input'), true);
if (!$raw) {
    $raw = $_POST;
}

$referralId  = (int)($raw['referral_id'] ?? 0);
$sessionDate = trim($raw['session_date'] ?? '');
$sessionType = trim($raw['session_type'] ?? 'in-person');
$location    = trim($raw['location'] ?? 'Guidance Office');
$meetingLink = trim($raw['meeting_link'] ?? '');
$notes       = trim($raw['notes'] ?? '');

if (!$referralId) {
    jsonOut(['success' => false, 'message' => 'Referral ID is required.'], 400);
}

if (!$sessionDate) {
    jsonOut(['success' => false, 'message' => 'Session date and time is required.'], 400);
}

$sessionTimestamp = strtotime($sessionDate);
if (!$sessionTimestamp || $sessionTimestamp < (time() - 60)) {
    jsonOut(['success' => false, 'message' => 'Counseling session cannot be scheduled in the past. Please select a present or future date and time.'], 400);
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM referrals WHERE id = ?");
    $stmt->execute([$referralId]);
    $referral = $stmt->fetch();

    if (!$referral) {
        jsonOut(['success' => false, 'message' => 'Referral not found.'], 404);
    }

    // Resolve student_id if not present
    $studentId = !empty($referral['student_id']) ? (int)$referral['student_id'] : null;
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
        $sStmt->execute([$referral['student_name'], $referral['student_name'], $referral['student_name'], $referral['student_name'], $referral['grade_section']]);
        $match = $sStmt->fetch();
        if ($match) {
            $studentId = (int)$match['id'];
            try {
                $db->prepare("UPDATE referrals SET student_id = ? WHERE id = ?")->execute([$studentId, $referral['id']]);
            } catch (Exception $e) {}
        }
    }

    if ($sessionType === 'online' || $sessionType === 'virtual') {
        if (empty($location) || $location === 'Guidance Office') {
            $location = 'Virtual Online Meeting';
        }
    }

    // Insert into counseling_sessions
    $ins = $db->prepare("INSERT INTO counseling_sessions 
        (referral_id, student_id, counselor_id, session_date, status, session_type, location, meeting_link, notes, created_at)
        VALUES (?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, NOW())");
    $ins->execute([
        $referral['id'],
        $studentId,
        $user['id'],
        $sessionDate,
        $sessionType,
        $location,
        $meetingLink ?: null,
        $notes ?: null
    ]);
    $newSessionId = (int)$db->lastInsertId();

    // Update referral status to ongoing when counseling session is scheduled
    if (in_array($referral['status'], ['pending', 'under_review'], true)) {
        $db->prepare("UPDATE referrals SET status = 'ongoing' WHERE id = ?")->execute([$referral['id']]);
    }

    $formattedDate = date('F d, Y \a\t g:i A', $sessionTimestamp);
    $formattedMode = ($sessionType === 'online' || $sessionType === 'virtual') ? 'Online / Virtual' : 'In-Person';
    $counselorName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Guidance Counselor';

    // 1. Notify Student
    if ($studentId) {
        $sTitle = "Counseling Session Scheduled";
        $sMsg   = "A counseling session has been scheduled with Counselor {$counselorName} regarding a teacher referral.\n\n• Date & Time: {$formattedDate}\n• Session Mode: {$formattedMode}\n";
        if (($sessionType === 'online' || $sessionType === 'virtual') && !empty($meetingLink)) {
            $sMsg .= "• Virtual Meeting Link: {$meetingLink}\n";
        } else {
            $sMsg .= "• Location: {$location}\n";
        }
        if (!empty($notes)) {
            $sMsg .= "• Counselor Remarks: {$notes}\n";
        }
        $sMsg .= "\nPlease be present and prepared for your counseling session.";

        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'session', 0, NOW())")
           ->execute([$studentId, $sTitle, $sMsg]);
    }

    // 2. Notify Referring Teacher
    if (!empty($referral['teacher_id'])) {
        $tTitle = "Counseling Session Scheduled: Referral #{$referral['ref_number']}";
        $tMsg   = "A counseling session has been scheduled with Counselor {$counselorName} for your referred student {$referral['student_name']}.\n\n• Date & Time: {$formattedDate}\n• Session Mode: {$formattedMode}\n";
        if (($sessionType === 'online' || $sessionType === 'virtual') && !empty($meetingLink)) {
            $tMsg .= "• Virtual Meeting Link: {$meetingLink}\n";
        } else {
            $tMsg .= "• Location: {$location}\n";
        }
        $db->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, 'referral', 0, NOW())")
           ->execute([$referral['teacher_id'], $tTitle, $tMsg]);
    }

    jsonOut([
        'success'        => true,
        'message'        => 'Counseling session scheduled successfully and student notified!',
        'session_id'     => $newSessionId,
        'formatted_date' => $formattedDate,
        'formatted_mode' => $formattedMode
    ]);

} catch (Exception $e) {
    jsonOut(['success' => false, 'message' => $e->getMessage()], 500);
}

