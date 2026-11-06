<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$c = $db->query("SELECT * FROM cases WHERE case_number='C-0011'")->fetch(PDO::FETCH_ASSOC);
print_r($c);

