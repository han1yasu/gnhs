<?php
require_once __DIR__ . '/../includes/config.php';

$user = requireLogin();
$db = getDB();

$isCounselor = ($user['role'] === 'admin');
$isTeacher = ($user['role'] === 'teacher');
$archiveId = (int)($_GET['archive_id'] ?? 0);

if (!$isCounselor && !$isTeacher) {
    jsonOut(['success' => false, 'message' => 'Unauthorized'], 403);
}

// Query active referrals for this user
if ($isCounselor) {
    if ($archiveId) {
        $whereClause = "r.id = ?";
        $params = [$user['id'], $archiveId];
    } else {
        $whereClause = "r.status NOT IN ('resolved', 'closed')";
        $params = [$user['id']];
    }
    
    $sql = "SELECT r.id, r.ref_number, r.concern_type, r.urgency, r.status, r.student_name,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            (SELECT message FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM referral_messages WHERE referral_id = r.id AND user_id != ? AND is_read = 0) as unread_count
            FROM referrals r
            LEFT JOIN users t ON r.teacher_id = t.id
            WHERE $whereClause
            ORDER BY latest_message_time DESC, r.submitted_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
} else {
    // For teachers
    if ($archiveId) {
        $whereClause = "r.id = ? AND r.teacher_id = ?";
        $params = [$user['id'], $archiveId, $user['id']];
    } else {
        $whereClause = "r.status NOT IN ('resolved', 'closed') AND r.teacher_id = ?";
        $params = [$user['id'], $user['id']];
    }
    
    $sql = "SELECT r.id, r.ref_number, r.concern_type, r.urgency, r.status, r.student_name,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            (SELECT message FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM referral_messages WHERE referral_id = r.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM referral_messages WHERE referral_id = r.id AND user_id != ? AND is_read = 0) as unread_count
            FROM referrals r
            LEFT JOIN users t ON r.teacher_id = t.id
            WHERE $whereClause
            ORDER BY latest_message_time DESC, r.submitted_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

$inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonOut(['success' => true, 'inbox' => $inbox]);
