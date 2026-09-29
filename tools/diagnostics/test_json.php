<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../includes/config.php';
$db = getDB();
$sql = "SELECT c.id, c.case_number, c.concern_type, c.priority, u.name as student_name,
        (SELECT message FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message,
        (SELECT created_at FROM case_messages WHERE case_id = c.id ORDER BY created_at DESC LIMIT 1) as latest_message_time,
        (SELECT COUNT(*) FROM case_messages WHERE case_id = c.id AND sender_id != 1 AND is_read = 0) as unread_count
        FROM cases c
        LEFT JOIN users u ON c.student_id = u.id
        WHERE c.status != 'resolved'
        ORDER BY latest_message_time DESC, c.updated_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents(__DIR__ . '/test_out.json', json_encode($data, JSON_PRETTY_PRINT));
