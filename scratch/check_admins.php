<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$admins = $db->query("SELECT id, email, first_name, last_name, totp_enabled FROM users WHERE role='admin'")->fetchAll();
print_r($admins);
