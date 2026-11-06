<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$u = $db->query("SELECT id, email, password FROM users WHERE email='admin@gnhs.edu.ph'")->fetch();
$testPasswords = ['password', 'admin123', 'admin', 'password123', 'Guidance@123', 'counselor123', '12345678', 'admin2026', 'admin@123'];
foreach ($testPasswords as $p) {
    if (password_verify($p, $u['password'])) {
        echo "MATCH: '$p'\n";
        exit;
    }
}
echo "No match.\n";
