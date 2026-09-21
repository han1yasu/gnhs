<?php
require 'c:/xampp/htdocs/gnhs-guidance/includes/config.php';
$db = getDB();
$stmt = $db->query('DESCRIBE users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
