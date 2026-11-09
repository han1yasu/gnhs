<?php
$q = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$isNested = str_contains($uri, '/gnhs-guidance');
$target = $isNested ? '/gnhs-guidance/pages/admin-chats.php' : '/pages/admin-chats.php';
header("Location: {$target}{$q}", true, 302);
exit;

