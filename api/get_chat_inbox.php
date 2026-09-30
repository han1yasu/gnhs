<?php
require_once __DIR__ . '/../includes/config.php';

$user = requireLogin();
$db = getDB();

$isCounselor = ($user['role'] === 'admin');
$archiveId = (int)($_GET['archive_id'] ?? 0);

// Query active cases for this user (or specific archived case)
if ($isCounselor) {
    if ($archiveId) {
        $whereClause = "c.id = ?";
        $params = [$user['id'], $archiveId];
    } else {
        $whereClause = "c.status != 'resolved'";
        $params = [$user['id']];
    }
    
    $sql = "SELECT c.id, c.case_number, c.concern_type, c.priority, c.status, c.is_anonymous,
            CASE WHEN c.is_anonymous = 1 THEN 'Anonymous Student' ELSE CONCAT(u.first_name, ' ', u.last_name) END as student_name,
            (SELECT message FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM case_messages WHERE case_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
            FROM cases c
            LEFT JOIN users u ON c.student_id = u.id
            WHERE $whereClause
            ORDER BY latest_message_time DESC, c.updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
} else {
    // For students
    if ($archiveId) {
        $whereClause = "c.id = ? AND c.student_id = ?";
        $params = [$user['id'], $archiveId, $user['id']];
    } else {
        $whereClause = "c.status != 'resolved' AND c.student_id = ?";
        $params = [$user['id'], $user['id']];
    }
    
    $sql = "SELECT c.id, c.case_number, c.concern_type, c.priority, c.status, c.is_anonymous, 'Counselor' as student_name,
            (SELECT message FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM case_messages WHERE case_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
            FROM cases c
            WHERE $whereClause
            ORDER BY latest_message_time DESC, c.updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

$inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
echo json_encode(['success' => true, 'inbox' => $inbox]);
