<?php
require_once __DIR__ . '/../includes/config.php';

$user = requireLogin();
$db = getDB();

$isCounselor = ($user['role'] === 'admin');
$isTeacher = ($user['role'] === 'teacher');
$archiveId = (int)($_GET['archive_id'] ?? 0);
$refId = (int)($_GET['ref_id'] ?? 0);
$caseId = (int)($_GET['case_id'] ?? 0);

if (!$isCounselor && !$isTeacher) {
    jsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
}

// If opened from a case, find or initialize referral coordination thread with the teacher
if ($caseId > 0 && !$refId) {
    $cStmt = $db->prepare("SELECT c.*, CONCAT(s.first_name, ' ', s.last_name) AS full_student_name, s.grade_section AS student_grade_sec FROM cases c JOIN users s ON c.student_id = s.id WHERE c.id = ?");
    $cStmt->execute([$caseId]);
    $caseData = $cStmt->fetch(PDO::FETCH_ASSOC);
    if ($caseData) {
        $stName = trim($caseData['full_student_name']);
        $sec = trim($caseData['student_grade_sec'] ?: ($caseData['grade_section'] ?? ''));

        // Check if referral already exists for this student
        $rCheck = $db->prepare("SELECT id FROM referrals WHERE student_name = ? ORDER BY id DESC LIMIT 1");
        $rCheck->execute([$stName]);
        $existingRef = $rCheck->fetch(PDO::FETCH_ASSOC);
        if ($existingRef) {
            $refId = (int)$existingRef['id'];
        } else {
            // Find advisory teacher or any teacher
            $teacher = null;
            if ($sec) {
                $tStmt = $db->prepare("SELECT id FROM users WHERE role = 'teacher' AND advisory_class = ? LIMIT 1");
                $tStmt->execute([$sec]);
                $teacher = $tStmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$teacher) {
                $tStmt2 = $db->query("SELECT id FROM users WHERE role = 'teacher' ORDER BY id ASC LIMIT 1");
                $teacher = $tStmt2->fetch(PDO::FETCH_ASSOC);
            }
            if ($teacher) {
                $countRow = (int)$db->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
                $nextNum = 'R-' . str_pad((string)($countRow + 1), 4, '0', STR_PAD_LEFT);
                $ins = $db->prepare("INSERT INTO referrals (ref_number, teacher_id, student_name, grade_section, concern_type, urgency, observations, status, submitted_at, ai_summary) VALUES (?, ?, ?, ?, ?, 'moderate', ?, 'ongoing', NOW(), ?)");
                $obs = "Guidance Counselor case coordination thread regarding Case #" . ($caseData['case_number'] ?? $caseId) . " for student " . $stName . ".";
                $aiSum = "Case coordination between Guidance Counselor and Advisory Teacher for student " . $stName . " (" . $sec . ").";
                $ins->execute([$nextNum, (int)$teacher['id'], $stName, $sec, $caseData['concern_type'] ?? 'other', $obs, $aiSum]);
                $refId = (int)$db->lastInsertId();
            }
        }
    }
}

// Query active referrals for this user
if ($isCounselor) {
    if ($archiveId) {
        $whereClause = "r.id = ?";
        $params = [$user['id'], $archiveId];
    } elseif ($refId) {
        $whereClause = "(r.id = ? OR r.status NOT IN ('resolved', 'closed'))";
        $params = [$user['id'], $refId];
    } else {
        $whereClause = "r.status NOT IN ('resolved', 'closed')";
        $params = [$user['id']];
    }
    
    $orderClause = $refId ? "(r.id = $refId) DESC, latest_message_time DESC, r.submitted_at DESC" : "latest_message_time DESC, r.submitted_at DESC";

    $sql = "SELECT r.id, r.ref_number, r.student_name, r.grade_section, r.concern_type, r.urgency, r.status,
            r.observations, r.submitted_at,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            t.avatar_photo as teacher_avatar,
            t.email as teacher_email,
            (SELECT message FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM referral_messages WHERE referral_id = r.id AND user_id != ? AND is_read = 0) as unread_count
            FROM referrals r
            LEFT JOIN users t ON r.teacher_id = t.id
            WHERE $whereClause
            ORDER BY $orderClause";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
} else {
    // For teachers
    if ($archiveId) {
        $whereClause = "r.id = ? AND r.teacher_id = ?";
        $params = [$user['id'], $archiveId, $user['id']];
    } elseif ($refId) {
        $whereClause = "(r.id = ? OR r.status NOT IN ('resolved', 'closed')) AND r.teacher_id = ?";
        $params = [$user['id'], $refId, $user['id']];
    } else {
        $whereClause = "r.status NOT IN ('resolved', 'closed') AND r.teacher_id = ?";
        $params = [$user['id'], $user['id']];
    }
    
    $orderClause = $refId ? "(r.id = $refId) DESC, latest_message_time DESC, r.submitted_at DESC" : "latest_message_time DESC, r.submitted_at DESC";

    $sql = "SELECT r.id, r.ref_number, r.student_name, r.grade_section, r.concern_type, r.urgency, r.status,
            r.observations, r.submitted_at,
            'Guidance Counselor' as counselor_name,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            t.avatar_photo as teacher_avatar,
            (SELECT message FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM referral_messages WHERE referral_id = r.id AND user_id != ? AND is_read = 0) as unread_count
            FROM referrals r
            LEFT JOIN users t ON r.teacher_id = t.id
            WHERE $whereClause
            ORDER BY $orderClause";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

$inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonOut(['success' => true, 'inbox' => $inbox]);
