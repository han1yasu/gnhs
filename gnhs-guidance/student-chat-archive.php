<?php
$q = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$isNested = str_contains($uri, '/gnhs-guidance');
$target = $isNested ? '/gnhs-guidance/pages/student-chat-archive.php' : '/pages/student-chat-archive.php';
header("Location: {$target}{$q}", true, 302);
exit;

