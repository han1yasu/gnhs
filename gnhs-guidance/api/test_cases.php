<?php
require 'c:/xampp/htdocs/gnhs-guidance/includes/config.php';
$db=getDB();
$stmt=$db->query('SELECT id, case_number, concern_type, subject, evidence_files FROM cases');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
