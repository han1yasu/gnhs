<?php
$q = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: student-chats.php{$q}", true, 302);
exit;

