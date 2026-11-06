<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$u = $db->query("SELECT id, email, totp_enabled, totp_secret, is_active FROM users WHERE email='jacob@gmail.com'")->fetch();
print_r($u);
