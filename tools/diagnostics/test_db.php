<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../includes/config.php';
$db = getDB();
$stmt = $db->query("SHOW TABLES");
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $db->query("DESCRIBE cases");
var_dump($stmt2->fetchAll(PDO::FETCH_ASSOC));
$stmt3 = $db->query("DESCRIBE case_messages");
var_dump($stmt3->fetchAll(PDO::FETCH_ASSOC));
