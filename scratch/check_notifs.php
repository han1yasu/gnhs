<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDB();
$notifs = $db->query("SELECT id, user_id, title, message, type, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
print_r($notifs);

