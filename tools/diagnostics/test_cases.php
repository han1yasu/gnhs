<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../includes/config.php';
$db=getDB();
$stmt=$db->query('SELECT id, status, assigned_to, student_id FROM cases');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
