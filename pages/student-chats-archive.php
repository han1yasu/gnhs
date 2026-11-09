<?php
$q = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: student-chat-archive.php{$q}", true, 302);
exit;

