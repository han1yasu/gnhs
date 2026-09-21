<?php
require 'c:/xampp/htdocs/gnhs-guidance/includes/config.php';
$db=getDB();
$stmt=$db->query('SELECT id, status, assigned_to, student_id FROM cases');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
