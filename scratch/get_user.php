<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$u = $db->query("SELECT id, first_name, last_name, email, role, id_number, grade_section FROM users WHERE first_name LIKE '%Joaquin%' OR id=11")->fetch();
print_r($u);
