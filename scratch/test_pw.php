<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$u = $db->query("SELECT id, email, password, role FROM users WHERE email='jacob@gmail.com'")->fetch();
$testPasswords = ['password', 'admin123', 'admin', 'password123', 'Guidance@123', 'counselor123', 'Jacob@123', 'admin@123', '12345678', '123456'];
foreach ($testPasswords as $p) {
    if (password_verify($p, $u['password'])) {
        echo "MATCH FOUND: '$p'\n";
        exit;
    }
}
echo "No match found in test list. Hash: " . $u['password'] . "\n";
