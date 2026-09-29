<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../includes/config.php';
$db = getDB();
$stmt = $db->query('DESCRIBE users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
