<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once __DIR__ . '/../../includes/config.php';
$user = requireLogin();
$db = getDB();

$isCounselor = ($user['role'] === 'admin');

if ($isCounselor) {
    $sql = "SELECT c.id, c.case_number, c.concern_type, c.priority, u.name as student_name,
            (SELECT message FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM case_messages WHERE case_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
            FROM cases c
            JOIN users u ON c.student_id = u.id
            WHERE c.status != 'resolved' AND c.assigned_to = ?
            ORDER BY latest_message_time DESC, c.updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user['id'], $user['id']]);
} else {
    $sql = "SELECT c.id, c.case_number, c.concern_type, c.priority, 'Counselor' as student_name,
            (SELECT message FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message,
            (SELECT created_at FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
            (SELECT COUNT(*) FROM case_messages WHERE case_id = c.id AND sender_id != ? AND is_read = 0) as unread_count
            FROM cases c
            WHERE c.status != 'resolved' AND c.student_id = ?
            ORDER BY latest_message_time DESC, c.updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user['id'], $user['id']]);
}

$inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($inbox);
